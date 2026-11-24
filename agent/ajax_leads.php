<?php

require_once dirname(__DIR__) . "/config.php";
require_once dirname(__DIR__) . "/functions.php";
require_once dirname(__DIR__) . "/includes/check_login.php";

header('Content-Type: application/json');

if (isset($_POST['update_kanban_lead'])) {
    validateCSRFToken();
    enforceUserPermission('module_client', 2);
    $lead_id = intval($_POST['lead_id'] ?? 0);
    $stage_id = intval($_POST['stage_id'] ?? 0);
    $stage_row = null;
    if ($stage_id > 0) {
        $stage_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT lead_stage_name, lead_stage_color FROM lead_stages WHERE lead_stage_id = $stage_id LIMIT 1"));
    }
    if ($lead_id > 0 && $stage_id > 0) {
        $old_res = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT lead_stage_id FROM leads WHERE lead_id = $lead_id LIMIT 1"));
        $old_stage_id = intval($old_res['lead_stage_id'] ?? 0);
        if ($old_stage_id !== $stage_id) {
            mysqli_query($mysqli, "UPDATE leads SET lead_stage_id = $stage_id WHERE lead_id = $lead_id");
            $creator_id = intval($session_user_id ?? ($_SESSION['user_id'] ?? 0));
            $stage_name = escapeSql($stage_row['lead_stage_name'] ?? 'Updated Stage');
            mysqli_query($mysqli, "INSERT INTO lead_activities SET
                lead_activity_lead_id = $lead_id,
                lead_activity_type = 'Stage Change',
                lead_activity_title = 'Stage Changed to $stage_name',
                lead_activity_details = 'Moved on Kanban board.',
                lead_activity_created_by = $creator_id");
        }
    }
    $stat_active = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT COUNT(l.lead_id) AS cnt,
               COALESCE(SUM(l.lead_estimated_mrr), 0) AS total_mrr,
               COALESCE(SUM(l.lead_estimated_seats), 0) AS total_seats
        FROM leads l
        LEFT JOIN lead_stages ls ON l.lead_stage_id = ls.lead_stage_id
        WHERE l.lead_status = 'Open'
          AND l.lead_archived_at IS NULL
          AND COALESCE(ls.lead_stage_is_lost, 0) = 0
    "));
    $total_mrr = floatval($stat_active['total_mrr'] ?? 0);
    $mrr_formatted = (isset($currency_format) && !empty($session_company_currency))
        ? numfmt_format_currency($currency_format, $total_mrr, $session_company_currency)
        : '$' . number_format($total_mrr, 2);
    echo json_encode([
        'status' => 'success',
        'stage_color' => (string)($stage_row['lead_stage_color'] ?? ''),
        'stats' => [
            'cnt' => intval($stat_active['cnt'] ?? 0),
            'total_mrr_formatted' => $mrr_formatted,
            'total_seats' => intval($stat_active['total_seats'] ?? 0),
        ],
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'error' => 'Invalid action']);
