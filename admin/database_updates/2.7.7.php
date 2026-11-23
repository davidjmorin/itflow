<?php

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

require_once dirname(__DIR__, 2) . '/functions/tbr.php';

$tpls = tbrGetDefaultEmailTemplates();
$conf_sub = mysqli_real_escape_string($mysqli, $tpls['tpl_confirmation_subject']);
$conf_body = mysqli_real_escape_string($mysqli, $tpls['tpl_confirmation_body']);
$rem_sub = mysqli_real_escape_string($mysqli, $tpls['tpl_reminder_subject']);
$rem_body = mysqli_real_escape_string($mysqli, $tpls['tpl_reminder_body']);
$rep_sub = mysqli_real_escape_string($mysqli, $tpls['tpl_report_subject']);
$rep_body = mysqli_real_escape_string($mysqli, $tpls['tpl_report_body']);
$int_sub = mysqli_real_escape_string($mysqli, $tpls['tpl_internal_reminder_subject']);
$int_body = mysqli_real_escape_string($mysqli, $tpls['tpl_internal_reminder_body']);

mysqli_query($mysqli, "
    UPDATE `tbr_settings` SET
        `tpl_confirmation_subject` = '$conf_sub',
        `tpl_confirmation_body` = '$conf_body',
        `tpl_reminder_subject` = '$rem_sub',
        `tpl_reminder_body` = '$rem_body',
        `tpl_report_subject` = '$rep_sub',
        `tpl_report_body` = '$rep_body',
        `tpl_internal_reminder_subject` = '$int_sub',
        `tpl_internal_reminder_body` = '$int_body'
    WHERE `setting_id` = 1
");
