<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/check_login.php';

$attachment_id = intval($_GET['attachment_id'] ?? 0);
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM meeting_attachments WHERE attachment_id = $attachment_id LIMIT 1"));
if (!$row) {
    http_response_code(404);
    exit('Attachment not found.');
}

$series = meetingGetSeries(intval($row['series_id']));
if (!$series || !meetingCanAccessSeries($series, $session_user_id, !empty($session_is_admin))) {
    http_response_code(403);
    exit(WORDING_ROLECHECK_FAILED);
}

$base_dir = realpath(__DIR__ . '/../uploads/meetings');
$abs_path = realpath(__DIR__ . '/../' . $row['file_path']);
if (!$base_dir || !$abs_path || strpos($abs_path, $base_dir) !== 0 || !is_file($abs_path)) {
    http_response_code(404);
    exit('File missing on disk.');
}

$download_name = basename((string)($row['file_name'] ?: $row['title']));
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $download_name) . '"');
header('Content-Length: ' . filesize($abs_path));
header('X-Content-Type-Options: nosniff');
readfile($abs_path);
exit();
