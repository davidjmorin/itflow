<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_lead_email_template'])) {
    validateCSRFToken();
    enforceUserPermission('module_client', 2);

    $template_name = escapeSql($_POST['template_name']);
    $template_subject = escapeSql($_POST['template_subject']);
    $template_body = escapeSql($_POST['template_body']);

    mysqli_query($mysqli, "INSERT INTO lead_email_templates SET
        lead_email_template_name = '$template_name',
        lead_email_template_subject = '$template_subject',
        lead_email_template_body = '$template_body'");

    flashAlert("Email template <strong>$template_name</strong> added!", "success");
    redirect("/agent/lead_email_templates.php");
}

if (isset($_POST['edit_lead_email_template'])) {
    validateCSRFToken();
    enforceUserPermission('module_client', 2);

    $template_id = intval($_POST['template_id']);
    $template_name = escapeSql($_POST['template_name']);
    $template_subject = escapeSql($_POST['template_subject']);
    $template_body = escapeSql($_POST['template_body']);

    mysqli_query($mysqli, "UPDATE lead_email_templates SET
        lead_email_template_name = '$template_name',
        lead_email_template_subject = '$template_subject',
        lead_email_template_body = '$template_body'
        WHERE lead_email_template_id = $template_id");

    flashAlert("Email template <strong>$template_name</strong> updated!", "success");
    redirect("/agent/lead_email_templates.php");
}

if (isset($_POST['delete_lead_email_template']) || isset($_GET['delete_lead_email_template'])) {
    validateCSRFToken();
    enforceUserPermission('module_client', 3);

    $template_id = intval($_POST['template_id'] ?? $_GET['delete_lead_email_template']);
    mysqli_query($mysqli, "DELETE FROM lead_email_templates WHERE lead_email_template_id = $template_id");

    flashAlert("Email template deleted.", "danger");
    redirect("/agent/lead_email_templates.php");
}
