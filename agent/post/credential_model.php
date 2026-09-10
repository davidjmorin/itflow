<?php
// Model of reusable variables for client credentials - not to be confused with the ITFLow login process
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// The form maxlength is client-side only - a hand-rolled POST gets here without it
if ($credential_field_too_long = checkCredentialLengths($_POST)) {
    flashAlert("Credential <strong>$credential_field_too_long</strong> is too long to store", 'error');
    redirect();
    exit;
}

$name = escapeSql($_POST['name']);
$description = escapeSql($_POST['description']);
$type = escapeSql($_POST['type'] ?? 'Standard');
$favorite = intval($_POST['favorite'] ?? 0);
$contact_id = intval($_POST['contact'] ?? 0);
$asset_id = intval($_POST['asset'] ?? 0);
$note = escapeSql($_POST['note']);

if ($type === 'Wi-Fi') {
    $wifi_ssid = escapeSql($_POST['wifi_ssid'] ?? '');
    $wifi_passcode = !empty($_POST['wifi_passcode']) ? encryptCredentialEntry(trim($_POST['wifi_passcode'])) : '';
    $wifi_encryption = escapeSql($_POST['wifi_encryption'] ?? '');
    $uri = escapeSql($_POST['wifi_admin_uri'] ?? $_POST['uri'] ?? '');
    $uri_2 = escapeSql($_POST['uri_2'] ?? '');
    $username = !empty($_POST['wifi_admin_username']) ? encryptCredentialEntry(trim($_POST['wifi_admin_username'])) : (!empty($_POST['username']) ? encryptCredentialEntry(trim($_POST['username'])) : '');
    $password = !empty($_POST['wifi_admin_password']) ? encryptCredentialEntry(trim($_POST['wifi_admin_password'])) : (!empty($_POST['password']) ? encryptCredentialEntry(trim($_POST['password'])) : '');
    $otp_secret = escapeSql($_POST['wifi_admin_otp_secret'] ?? $_POST['otp_secret'] ?? '');
} else {
    $wifi_ssid = '';
    $wifi_passcode = '';
    $wifi_encryption = '';
    $uri = escapeSql($_POST['uri'] ?? '');
    $uri_2 = escapeSql($_POST['uri_2'] ?? '');
    $username = !empty($_POST['username']) ? encryptCredentialEntry(trim($_POST['username'])) : '';
    $password = !empty($_POST['password']) ? encryptCredentialEntry(trim($_POST['password'])) : '';
    $otp_secret = escapeSql($_POST['otp_secret'] ?? '');
}
