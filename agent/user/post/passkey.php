<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_GET['delete_passkey'])) {
    validateCSRFToken();
    $passkey_id = intval($_GET['delete_passkey']);
    $sql = mysqli_query($mysqli, "SELECT passkey_name FROM user_passkeys WHERE passkey_id = $passkey_id AND passkey_user_id = $session_user_id");
    if ($row = mysqli_fetch_assoc($sql)) {
        $passkey_name = escapeSql($row['passkey_name']);
        mysqli_query($mysqli, "DELETE FROM user_passkeys WHERE passkey_id = $passkey_id AND passkey_user_id = $session_user_id");
        logAudit("User Account", "Edit", "$session_name deleted passkey $passkey_name");
        flashAlert("Passkey deleted");
    }
    redirect();
}
