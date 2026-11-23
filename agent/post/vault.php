<?php

/*
 * ITFlow - Unlock the credential vault (needed after passkey login, which has no password to derive the key from)
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['unlock_vault'])) {

    validateCSRFToken();

    $session_ip_esc = escapeSql(getIP());
    $row = mysqli_fetch_assoc(mysqli_query(
        $mysqli,
        "SELECT COUNT(log_id) AS failed_count FROM logs
         WHERE log_ip = '$session_ip_esc' AND log_type = 'Vault' AND log_action = 'Failed'
           AND log_created_at > (NOW() - INTERVAL 10 MINUTE)"
    ));
    if (intval($row['failed_count'] ?? 0) >= 5) {
        flashAlert("Too many failed unlock attempts. Try again later.", 'error');
        redirect();
    }

    if (unlockVault($session_user_id, $_POST['vault_password'] ?? '')) {
        unset($_SESSION['vault_locked']);
        logAudit("Vault", "Unlock", "$session_name unlocked the credential vault");
        flashAlert("Credential vault unlocked");
    } else {
        logAudit("Vault", "Failed", "$session_name failed to unlock the credential vault");
        flashAlert("Incorrect password", 'error');
    }

    redirect();
}
