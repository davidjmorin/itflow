<?php

require_once "config.php";
require_once "functions.php";
require_once __DIR__ . "/includes/session_init.php";
require_once "includes/inc_set_timezone.php";

header('Content-Type: application/json');

$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT'] ?? '');

$row = mysqli_fetch_assoc(mysqli_query(
    $mysqli,
    "SELECT COUNT(log_id) AS failed_login_count
     FROM logs
     WHERE log_ip = '$session_ip'
       AND log_type = 'Login'
       AND log_action IN ('Failed', 'MFA Failed')
       AND log_created_at > (NOW() - INTERVAL 10 MINUTE)"
));
if (intval($row['failed_login_count'] ?? 0) >= 15) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many failed login attempts. Please try again later.']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'check_email') {
        $email = trim($_GET['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => true, 'has_passkey' => false]);
            exit;
        }

        $emailEscaped = escapeSql($email);
        $sql = mysqli_query($mysqli, "
            SELECT users.user_id, COUNT(user_passkeys.passkey_id) AS passkey_count
            FROM users
            JOIN user_passkeys ON users.user_id = user_passkeys.passkey_user_id
            WHERE user_email = '$emailEscaped'
              AND user_status = 1
              AND user_archived_at IS NULL
            GROUP BY users.user_id
        ");

        $count = 0;
        if ($row = mysqli_fetch_assoc($sql)) {
            $count = intval($row['passkey_count']);
        }

        echo json_encode(['success' => true, 'has_passkey' => ($count > 0)]);
        exit;
    }

    if ($action === 'get_login_args') {
        $email = trim($_GET['email'] ?? '');
        $credentialIds = [];

        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailEscaped = escapeSql($email);
            $sql = mysqli_query($mysqli, "
                SELECT user_passkeys.passkey_credential_id
                FROM user_passkeys
                JOIN users ON user_passkeys.passkey_user_id = users.user_id
                WHERE users.user_email = '$emailEscaped'
                  AND users.user_status = 1
                  AND users.user_archived_at IS NULL
            ");
            while ($r = mysqli_fetch_assoc($sql)) {
                $rawId = base64_decode($r['passkey_credential_id']);
                if ($rawId !== false) {
                    $credentialIds[] = $rawId;
                }
            }
        }

        $webauthn = getWebAuthnInstance();
        $getArgs = $webauthn->getGetArgs($credentialIds, 60 * 4, true, true, true, true, true, 'preferred');
        $_SESSION['webauthn_login_challenge'] = $webauthn->getChallenge();
        $_SESSION['webauthn_login_email'] = $email;

        echo json_encode(['success' => true, 'args' => $getArgs]);
        exit;
    }

    if ($action === 'verify_login') {
        $raw = file_get_contents('php://input');
        $post = json_decode($raw);
        if (!$post || empty($post->id) || empty($post->clientDataJSON) || empty($post->authenticatorData) || empty($post->signature)) {
            throw new Exception('Invalid passkey response.');
        }

        $challenge = $_SESSION['webauthn_login_challenge'] ?? null;
        if (!$challenge) {
            throw new Exception('Authentication session expired. Please refresh and try again.');
        }

        $rawId = base64_decode($post->id);
        $idHash = escapeSql(hash('sha256', $rawId));

        $sql = mysqli_query($mysqli, "
            SELECT user_passkeys.*, users.user_id, users.user_name, users.user_email, users.user_type, users.user_status, users.user_archived_at,
                   users.user_password, users.user_service_account,
                   user_settings.user_config_force_mfa, user_settings.user_config_records_per_page
            FROM user_passkeys
            JOIN users ON user_passkeys.passkey_user_id = users.user_id
            LEFT JOIN user_settings ON users.user_id = user_settings.user_id
            WHERE user_passkeys.passkey_credential_id_hash = '$idHash'
              AND users.user_status = 1
              AND users.user_archived_at IS NULL
            LIMIT 1
        ");

        $passkey = mysqli_fetch_assoc($sql);
        if (!$passkey) {
            logAudit("Login", "Failed", "Passkey not recognized or account disabled", 0, 0);
            throw new Exception('Passkey not recognized.');
        }

        if (!empty($passkey['user_service_account'])) {
            throw new Exception('Service accounts cannot sign in.');
        }

        $webauthn = getWebAuthnInstance();
        $processResult = $webauthn->processGet(
            base64_decode($post->clientDataJSON),
            base64_decode($post->authenticatorData),
            base64_decode($post->signature),
            $passkey['passkey_public_key'],
            $challenge,
            null,
            false
        );

        $signCount = intval($processResult->signatureCounter ?? 0);
        $lastSignCount = intval($passkey['passkey_sign_count']);
        if ($signCount > 0 && $signCount <= $lastSignCount) {
            logAudit("Login", "Failed", "Passkey replay detected for user " . $passkey['user_email'], 0, intval($passkey['user_id']));
            throw new Exception('Authenticator verification anomaly detected.');
        }

        $passkeyId = intval($passkey['passkey_id']);
        mysqli_query($mysqli, "UPDATE user_passkeys SET passkey_sign_count = $signCount, passkey_last_used_at = NOW() WHERE passkey_id = $passkeyId");

        $userId = intval($passkey['user_id']);
        $userType = intval($passkey['user_type']);
        $userName = $passkey['user_name'];
        $userEmail = $passkey['user_email'];

        session_regenerate_id(true);

        if (!empty($_SESSION['pending_mfa_login']['agent_master_key'])) {
            generateUserSessionKey($_SESSION['pending_mfa_login']['agent_master_key']);
        }

        unset($_SESSION['webauthn_login_challenge']);
        unset($_SESSION['webauthn_login_email']);
        unset($_SESSION['pending_dual_login']);
        unset($_SESSION['pending_mfa_login']);

        $sql_settings = mysqli_query($mysqli, "SELECT config_start_page, config_client_portal_enable FROM settings WHERE company_id = 1");
        $settingsRow = mysqli_fetch_assoc($sql_settings);
        $config_start_page = $settingsRow['config_start_page'] ?? 'dashboard.php';
        $config_client_portal_enable = intval($settingsRow['config_client_portal_enable'] ?? 0);

        if ($userType === 1) {
            $_SESSION['user_id'] = $userId;
            $_SESSION['csrf_token'] = randomString(32);
            $_SESSION['logged'] = true;
            $_SESSION['passkey_logged_in'] = true;
            $_SESSION['user_session_stamp'] = userSessionStamp($passkey['user_password'] ?? '');

            $session_user_id = $userId;
            logAudit("Login", "Success", "$userName successfully logged in with Passkey", 0, $userId);

            $target = "agent/$config_start_page";
            if (isset($_GET['last_visited'])) {
                $lastVisited = base64_decode($_GET['last_visited']);
                if (str_starts_with($lastVisited, '/agent') || str_starts_with($lastVisited, '/admin')) {
                    $target = ltrim($lastVisited, '/');
                }
            }

            echo json_encode(['success' => true, 'redirect' => $target]);
            exit;
        } elseif ($userType === 2) {
            if ($config_client_portal_enable != 1) {
                throw new Exception('Client portal is disabled.');
            }

            $sqlContact = mysqli_query($mysqli, "
                SELECT contacts.contact_id, contacts.contact_client_id
                FROM contacts
                WHERE contacts.contact_user_id = $userId
                LIMIT 1
            ");
            $contactRow = mysqli_fetch_assoc($sqlContact);
            $clientId = intval($contactRow['contact_client_id'] ?? 0);
            $contactId = intval($contactRow['contact_id'] ?? 0);

            $_SESSION['client_logged_in'] = true;
            $_SESSION['client_id'] = $clientId;
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_type'] = 2;
            $_SESSION['contact_id'] = $contactId;
            $_SESSION['login_method'] = "passkey";
            $_SESSION['logged'] = true;
            $_SESSION['csrf_token'] = randomString(32);
            $_SESSION['user_session_stamp'] = userSessionStamp($passkey['user_password'] ?? '');

            $session_user_id = $userId;
            logAudit("Client Login", "Success", "Client contact $userEmail successfully logged in with Passkey", $clientId, $userId);

            echo json_encode(['success' => true, 'redirect' => "client/index.php"]);
            exit;
        }

        throw new Exception('Invalid user account type.');
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
