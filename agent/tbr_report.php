<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/check_login.php';
require_once __DIR__ . '/../functions/tbr_pdf.php';

$action = $_GET['action'] ?? 'preview';

if ($action === 'generate') {
    tbrEnforcePermission('generate_pdf');

    $tbr_id = intval($_GET['tbr_id'] ?? 0);
    $mode = ($_GET['mode'] ?? 'client') === 'internal' ? 'internal' : 'client';
    $disposition = ($_GET['disposition'] ?? 'inline') === 'attachment' ? 'attachment' : 'inline';

    if ($mode === 'internal') {
        tbrEnforcePermission('view_internal_notes');
    }

    $meeting = tbrGetMeeting($tbr_id);
    if (!$meeting) {
        flashAlert('TBR meeting not found.', 'error');
        redirect('tbrs.php');
    }

    enforceClientAccess(intval($meeting['tbr_client_id']));

    if ($mode === 'client' && empty($meeting['tbr_client_report_approved']) && empty($_GET['preview_draft'])) {
        flashAlert('Client-facing report must be reviewed and approved before generating the final client PDF.', 'error');
        redirect("tbr.php?tbr_id=$tbr_id#panel-reports");
    }

    if (!empty($_GET['preview_draft']) && !tbrHasPermission('edit')) {
        tbrEnforcePermission('edit');
    }

    $report = tbrGeneratePdfReport($tbr_id, $mode, $session_user_id);

    if (!empty($_GET['redirect_back'])) {
        flashAlert(ucfirst($mode) . ' PDF report v' . $report['report_version'] . ' generated.', 'success');
        redirect("tbr.php?tbr_id=$tbr_id#panel-reports");
    }

    $report_id = intval($report['report_id']);
    $action = ($disposition === 'attachment') ? 'download' : 'preview';
    $_GET['report_id'] = $report_id;
}

if (in_array($action, ['preview', 'download'], true)) {
    tbrEnforcePermission('generate_pdf');

    $report_id = intval($_GET['report_id'] ?? 0);
    $report = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbr_reports WHERE report_id = $report_id LIMIT 1"));
    if (!$report) {
        http_response_code(404);
        exit('Report not found.');
    }

    if ($report['report_type'] === 'internal') {
        tbrEnforcePermission('view_internal_notes');
    }

    enforceClientAccess(intval($report['report_client_id']));

    $base_dir = realpath(__DIR__ . '/../uploads/tbr');
    $abs_path = realpath(__DIR__ . '/../' . $report['report_filepath']);

    if (!$base_dir || !$abs_path || strpos($abs_path, $base_dir) !== 0 || !is_file($abs_path)) {
        http_response_code(404);
        exit('Report file is missing on disk.');
    }

    $disp = ($action === 'download') ? 'attachment' : 'inline';
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . $disp . '; filename="' . basename($report['report_filename']) . '"');
    header('Content-Length: ' . filesize($abs_path));
    header('X-Content-Type-Options: nosniff');
    readfile($abs_path);
    exit();
}

http_response_code(400);
exit('Invalid report action.');
