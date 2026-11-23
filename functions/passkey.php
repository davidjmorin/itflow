<?php

require_once dirname(__DIR__) . '/libs/webauthn/WebAuthn.php';

function getWebAuthnRpId() {
    global $config_base_url;
    $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ($config_base_url ?? 'localhost');
    $host = preg_replace('/^https?:\/\//', '', $host);
    $host = explode('/', $host)[0];
    return explode(':', $host)[0];
}

function getWebAuthnInstance() {
    global $config_app_name;
    $appName = !empty($config_app_name) ? $config_app_name : 'ITFlow';
    $rpId = getWebAuthnRpId();
    $formats = ['android-key', 'android-safetynet', 'apple', 'fido-u2f', 'none', 'packed', 'tpm'];
    return new \lbuchs\WebAuthn\WebAuthn($appName, $rpId, $formats);
}
