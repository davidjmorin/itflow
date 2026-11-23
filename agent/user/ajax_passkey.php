<?php

require_once dirname(__DIR__, 2) . "/config.php";
require_once dirname(__DIR__, 2) . "/functions.php";
require_once dirname(__DIR__, 2) . "/includes/check_login.php";

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_create_args') {
        $webauthn = getWebAuthnInstance();
        $userIdBin = hex2bin(str_pad(dechex($session_user_id), 32, '0', STR_PAD_LEFT));
        $createArgs = $webauthn->getCreateArgs($userIdBin, $session_email, $session_name, 60 * 4, false, 'preferred', null);
        $_SESSION['webauthn_register_challenge'] = $webauthn->getChallenge();
        echo json_encode(['success' => true, 'args' => $createArgs]);
        exit;
    }

    if ($action === 'process_create') {
        $raw = file_get_contents('php://input');
        $post = json_decode($raw);
        if (!$post || empty($post->clientDataJSON) || empty($post->attestationObject)) {
            throw new Exception('Invalid payload');
        }

        $name = trim($post->name ?? '');
        if (empty($name)) {
            $name = 'My Passkey';
        }
        $name = escapeSql($name);

        $challenge = $_SESSION['webauthn_register_challenge'] ?? null;
        if (!$challenge) {
            throw new Exception('Challenge expired');
        }

        $webauthn = getWebAuthnInstance();
        $data = $webauthn->processCreate(
            base64_decode($post->clientDataJSON),
            base64_decode($post->attestationObject),
            $challenge,
            false,
            true,
            false
        );

        unset($_SESSION['webauthn_register_challenge']);

        $credentialId = $data->credentialId;
        $credentialIdBase64 = escapeSql(base64_encode($credentialId));
        $credentialIdHash = escapeSql(hash('sha256', $credentialId));
        $publicKeyEscaped = escapeSql($data->credentialPublicKey);
        $signCount = intval($data->signatureCounter ?? 0);
        $aaguid = escapeSql($data->aaguid ?? '');

        mysqli_query($mysqli, "INSERT INTO user_passkeys SET 
            passkey_user_id = $session_user_id, 
            passkey_name = '$name', 
            passkey_credential_id = '$credentialIdBase64', 
            passkey_credential_id_hash = '$credentialIdHash', 
            passkey_public_key = '$publicKeyEscaped', 
            passkey_sign_count = $signCount, 
            passkey_aaguid = '$aaguid'");

        logAudit("User Account", "Edit", "$session_name added a passkey: $name");

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
