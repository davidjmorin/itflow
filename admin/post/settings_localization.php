<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_localization'])) {

    validateCSRFToken();

    $locale = escapeSql($_POST['locale']);
    $currency_code = escapeSql($_POST['currency_code']);
    $timezone = escapeSql($_POST['timezone']);
    $google_places_api_key = escapeSql(trim($_POST['google_places_api_key'] ?? ''));

    mysqli_query($mysqli,"UPDATE companies SET company_locale = '$locale', company_currency = '$currency_code' WHERE company_id = 1");

    mysqli_query($mysqli,"UPDATE settings SET config_timezone = '$timezone', config_google_places_api_key = '$google_places_api_key' WHERE company_id = 1");

    logAudit("Settings", "Edit", "$session_name edited localization settings");

    flashAlert("Company localization updated");

    redirect();

}
