<?php
define('FROM_POST_HANDLER', true);
require_once "../../config.php";
require_once "../../functions.php";
require_once "../../includes/check_login.php";

if (isset($_POST['add_canned_response'])) {
    validateCSRFToken();
    $name = escapeSql($_POST['canned_name']);
    $body = escapeSql($_POST['canned_body']);
    mysqli_query($mysqli, "INSERT INTO canned_responses SET canned_name = '$name', canned_body = '$body'");
    logAudit("Canned Response", "Create", "$session_name created canned response $name");
    flashAlert("Canned response created.");
    redirect('/admin/canned_responses.php');
}

if (isset($_POST['edit_canned_response'])) {
    validateCSRFToken();
    $id = intval($_POST['canned_id']);
    $name = escapeSql($_POST['canned_name']);
    $body = escapeSql($_POST['canned_body']);
    mysqli_query($mysqli, "UPDATE canned_responses SET canned_name = '$name', canned_body = '$body' WHERE canned_id = $id");
    logAudit("Canned Response", "Update", "$session_name updated canned response $name");
    flashAlert("Canned response updated.");
    redirect('/admin/canned_responses.php');
}

if (isset($_POST['delete_canned_response'])) {
    validateCSRFToken();
    $id = intval($_POST['canned_id']);
    mysqli_query($mysqli, "DELETE FROM canned_responses WHERE canned_id = $id");
    logAudit("Canned Response", "Delete", "$session_name deleted canned response");
    flashAlert("Canned response deleted.");
    redirect('/admin/canned_responses.php');
}
