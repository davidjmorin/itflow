<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/check_login.php';

header('Content-Type: application/json');

function tbrJsonError(string $msg, int $code = 400, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['ok' => false, 'error' => $msg], $extra));
    exit();
}

function tbrJsonOk(array $data = []): void {
    echo json_encode(array_merge(['ok' => true], $data));
    exit();
}

function tbrLoadFindingsPayload(int $tbr_id): array {
    global $mysqli;
    $can_internal = tbrHasPermission('view_internal_notes');
    $rows = [];
    $res = mysqli_query($mysqli, "SELECT * FROM tbr_findings WHERE finding_tbr_id = $tbr_id ORDER BY FIELD(finding_risk_level,'Critical','High','Moderate','Low'), finding_id ASC");
    while ($res && ($r = mysqli_fetch_assoc($res))) {
        if (!$can_internal) {
            unset($r['finding_internal_notes']);
        }
        $rows[] = $r;
    }
    return $rows;
}

function tbrLoadRecommendationsPayload(int $tbr_id): array {
    global $mysqli;
    $can_internal = tbrHasPermission('view_internal_notes');
    $rows = [];
    $res = mysqli_query($mysqli, "SELECT * FROM tbr_recommendations WHERE recommendation_tbr_id = $tbr_id ORDER BY FIELD(recommendation_priority,'Critical','High','Medium','Low'), recommendation_id ASC");
    while ($res && ($r = mysqli_fetch_assoc($res))) {
        if (!$can_internal) {
            unset($r['recommendation_internal_notes']);
        }
        $rows[] = $r;
    }
    return $rows;
}

function tbrLoadRoadmapPayload(int $tbr_id): array {
    global $mysqli;
    $rows = [];
    $quarters = ['Q1' => 0.0, 'Q2' => 0.0, 'Q3' => 0.0, 'Q4' => 0.0];
    $categories = [
        'Required Security' => 0.0,
        'Hardware Replacement' => 0.0,
        'Software & Licensing' => 0.0,
        'Infrastructure Improvement' => 0.0,
        'Optional Enhancement' => 0.0,
    ];
    $annual = 0.0;

    $res = mysqli_query($mysqli, "SELECT * FROM tbr_roadmap_items WHERE roadmap_tbr_id = $tbr_id ORDER BY roadmap_fiscal_year ASC, FIELD(roadmap_quarter,'Q1','Q2','Q3','Q4'), FIELD(roadmap_priority,'Critical','High','Medium','Low'), roadmap_id ASC");
    while ($res && ($r = mysqli_fetch_assoc($res))) {
        $cost = floatval($r['roadmap_estimated_cost']);
        $quarters[$r['roadmap_quarter']] = ($quarters[$r['roadmap_quarter']] ?? 0.0) + $cost;
        $categories[$r['roadmap_category']] = ($categories[$r['roadmap_category']] ?? 0.0) + $cost;
        $annual += $cost;
        $rows[] = $r;
    }

    return [
        'items' => $rows,
        'totals' => [
            'quarters' => $quarters,
            'categories' => $categories,
            'annual' => $annual,
        ],
    ];
}

function tbrLoadActionsPayload(int $tbr_id): array {
    global $mysqli;
    $can_internal = tbrHasPermission('view_internal_notes');
    $rows = [];
    $res = mysqli_query($mysqli, "
        SELECT a.*, u.user_name AS assigned_user_name, t.ticket_prefix, t.ticket_number
        FROM tbr_action_items a
        LEFT JOIN users u ON a.action_assigned_user_id = u.user_id
        LEFT JOIN tickets t ON a.action_ticket_id = t.ticket_id
        WHERE a.action_tbr_id = $tbr_id
        ORDER BY FIELD(a.action_priority,'Critical','High','Medium','Low'), a.action_due_date ASC, a.action_id ASC
    ");
    while ($res && ($r = mysqli_fetch_assoc($res))) {
        if (!$can_internal) {
            unset($r['action_internal_notes']);
        }
        $rows[] = $r;
    }
    return $rows;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'client_schedule_context') {
    tbrEnforcePermission('view', true);
    $client_id = intval($_GET['client_id'] ?? 0);
    if (!tbrCanAccessClient($client_id)) {
        tbrJsonError('Access denied for this client.', 403);
    }

    $contacts = [];
    $res = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_email, contact_primary FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_name ASC");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $contacts[] = $row;
    }

    $prev = tbrFindPreviousMeeting($client_id);
    tbrJsonOk(['contacts' => $contacts, 'previous_tbr' => $prev]);
}

$tbr_id = intval($_REQUEST['tbr_id'] ?? 0);
$meeting = tbrGetMeeting($tbr_id);
if (!$meeting) {
    tbrJsonError('TBR meeting not found.', 404);
}
$client_id = intval($meeting['tbr_client_id']);
if (!tbrCanAccessClient($client_id)) {
    tbrJsonError('Access denied for this client.', 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        tbrJsonError('CSRF token validation failed.', 403);
    }
}

$is_locked = in_array($meeting['tbr_status'], ['Completed', 'Cancelled'], true);

if ($action === 'get_section') {
    tbrEnforcePermission('view', true);
    $section_key = escapeSql($_GET['section_key'] ?? '');
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbr_section_responses WHERE response_tbr_id = $tbr_id AND section_key = '$section_key' LIMIT 1"));
    if (!$row) {
        tbrJsonError('Section not found.', 404);
    }
    if (!tbrHasPermission('view_internal_notes')) {
        unset($row['internal_notes']);
    }
    $row['answers'] = json_decode((string)$row['answers_json'], true) ?: [];
    tbrJsonOk(['section' => $row]);
}

if ($action === 'refresh_live_data') {
    tbrEnforcePermission('view', true);
    $p_start = $_GET['period_start'] ?? $meeting['tbr_reporting_period_start'];
    $p_end = $_GET['period_end'] ?? $meeting['tbr_reporting_period_end'];
    $live = tbrFetchLiveClientData($client_id, $p_start, $p_end);
    tbrJsonOk(['live_data' => $live]);
}

if ($action === 'validate_completion') {
    tbrEnforcePermission('complete', true);
    tbrJsonOk(['validation' => tbrValidateCompletion($tbr_id, $_GET['next_review_date'] ?? null)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tbrJsonError('Invalid request.', 400);
}

if ($action === 'save_section') {
    tbrEnforcePermission('edit', true);
    if ($is_locked) {
        tbrJsonError('This TBR is locked (' . $meeting['tbr_status'] . '). Reopen it before editing.', 409);
    }

    $section_key = escapeSql($_POST['section_key'] ?? '');
    $valid_sections = array_keys(tbrGetSectionsConfig());
    if (!in_array($section_key, $valid_sections, true)) {
        tbrJsonError('Invalid section key.');
    }

    tbrInitializeAgenda($tbr_id, $session_user_id);

    $existing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbr_section_responses WHERE response_tbr_id = $tbr_id AND section_key = '$section_key' LIMIT 1"));
    $server_version = intval($existing['version'] ?? 1);
    $expected_version = intval($_POST['expected_version'] ?? $server_version);
    $force = !empty($_POST['force_overwrite']);

    if (!$force && $expected_version !== $server_version) {
        $existing['answers'] = json_decode((string)$existing['answers_json'], true) ?: [];
        if (!tbrHasPermission('view_internal_notes')) {
            unset($existing['internal_notes']);
        }
        tbrJsonError('Section was modified by another user or tab.', 409, [
            'conflict' => true,
            'server_version' => $server_version,
            'server_data' => $existing,
        ]);
    }

    $section_status = in_array($_POST['section_status'] ?? '', ['Not Started', 'In Progress', 'Completed', 'Not Assessed'], true)
        ? $_POST['section_status']
        : $existing['section_status'];
    $section_health = in_array($_POST['section_health'] ?? '', ['Good', 'Needs Attention', 'Critical', 'Not Assessed'], true)
        ? $_POST['section_health']
        : $existing['section_health'];

    $answers_raw = $_POST['answers_json'] ?? '{}';
    $answers_arr = json_decode($answers_raw, true);
    if (!is_array($answers_arr)) {
        $answers_arr = [];
    }
    $answers_esc = mysqli_real_escape_string($mysqli, json_encode($answers_arr, JSON_UNESCAPED_UNICODE));
    $client_notes = mysqli_real_escape_string($mysqli, (string)($_POST['client_notes'] ?? ''));
    $follow_up_notes = mysqli_real_escape_string($mysqli, (string)($_POST['follow_up_notes'] ?? ''));

    $internal_sql = "";
    if (tbrHasPermission('view_internal_notes') && array_key_exists('internal_notes', $_POST)) {
        $internal_notes = mysqli_real_escape_string($mysqli, (string)$_POST['internal_notes']);
        $internal_sql = ", internal_notes = '$internal_notes'";
    }

    $new_version = $server_version + 1;
    mysqli_query($mysqli, "
        UPDATE tbr_section_responses SET
            section_status = '$section_status',
            section_health = '$section_health',
            answers_json = '$answers_esc',
            client_notes = '$client_notes',
            follow_up_notes = '$follow_up_notes',
            version = $new_version,
            updated_by = $session_user_id
            $internal_sql
        WHERE response_tbr_id = $tbr_id AND section_key = '$section_key'
    ");

    if ($meeting['tbr_status'] === 'Scheduled') {
        mysqli_query($mysqli, "UPDATE tbr_meetings SET tbr_status = 'In Progress', tbr_last_edited_by = $session_user_id WHERE tbr_id = $tbr_id");
    } else {
        mysqli_query($mysqli, "UPDATE tbr_meetings SET tbr_last_edited_by = $session_user_id WHERE tbr_id = $tbr_id");
    }

    $progress = tbrCalculateProgress($tbr_id);
    tbrJsonOk([
        'section_key' => $section_key,
        'version' => $new_version,
        'saved_at' => date('g:i:s A'),
        'progress' => $progress,
    ]);
}

if ($action === 'save_meeting_meta') {
    tbrEnforcePermission('edit', true);
    if ($is_locked) {
        tbrJsonError('This TBR is locked (' . $meeting['tbr_status'] . ').', 409);
    }

    $exec_summary = mysqli_real_escape_string($mysqli, (string)($_POST['tbr_executive_summary'] ?? ''));
    $overall_health = in_array($_POST['tbr_overall_health'] ?? '', ['Good', 'Needs Attention', 'Critical', 'Not Assessed'], true)
        ? $_POST['tbr_overall_health']
        : $meeting['tbr_overall_health'];
    $period_start = !empty($_POST['tbr_reporting_period_start']) ? "'" . escapeSql($_POST['tbr_reporting_period_start']) . "'" : 'NULL';
    $period_end = !empty($_POST['tbr_reporting_period_end']) ? "'" . escapeSql($_POST['tbr_reporting_period_end']) . "'" : 'NULL';
    $next_review = !empty($_POST['tbr_next_review_date']) ? "'" . escapeSql($_POST['tbr_next_review_date']) . "'" : 'NULL';

    $prep_sql = "";
    if (tbrHasPermission('view_internal_notes') && array_key_exists('tbr_prep_notes', $_POST)) {
        $prep = mysqli_real_escape_string($mysqli, (string)$_POST['tbr_prep_notes']);
        $prep_sql = ", tbr_prep_notes = '$prep'";
    }

    mysqli_query($mysqli, "
        UPDATE tbr_meetings SET
            tbr_executive_summary = '$exec_summary',
            tbr_overall_health = '$overall_health',
            tbr_reporting_period_start = $period_start,
            tbr_reporting_period_end = $period_end,
            tbr_next_review_date = $next_review,
            tbr_last_edited_by = $session_user_id
            $prep_sql
        WHERE tbr_id = $tbr_id
    ");

    if (isset($_POST['attendees_attended']) && is_array($_POST['attendees_attended'])) {
        foreach ($_POST['attendees_attended'] as $att_id => $val) {
            $aid = intval($att_id);
            $att = intval($val) ? 1 : 0;
            mysqli_query($mysqli, "UPDATE tbr_attendees SET attendee_attended = $att WHERE attendee_id = $aid AND attendee_tbr_id = $tbr_id");
        }
    }

    tbrJsonOk(['saved_at' => date('g:i:s A'), 'overall_health' => $overall_health]);
}

if ($action === 'finding_save') {
    tbrEnforcePermission('edit', true);
    if ($is_locked) {
        tbrJsonError('TBR is locked.', 409);
    }

    $fid = intval($_POST['finding_id'] ?? 0);
    $section = escapeSql($_POST['finding_section'] ?? 'sec_04');
    $category = escapeSql($_POST['finding_category'] ?? '');
    $title = escapeSql($_POST['finding_title'] ?? '');
    if ($title === '') {
        tbrJsonError('Finding title is required.');
    }
    $desc = mysqli_real_escape_string($mysqli, (string)($_POST['finding_description'] ?? ''));
    $impact = mysqli_real_escape_string($mysqli, (string)($_POST['finding_business_impact'] ?? ''));
    $rec_action = mysqli_real_escape_string($mysqli, (string)($_POST['finding_recommended_action'] ?? ''));
    $risk = in_array($_POST['finding_risk_level'] ?? '', ['Low', 'Moderate', 'High', 'Critical'], true) ? $_POST['finding_risk_level'] : 'Moderate';
    $prio = in_array($_POST['finding_priority'] ?? '', ['Low', 'Medium', 'High', 'Critical'], true) ? $_POST['finding_priority'] : 'Medium';
    $status = in_array($_POST['finding_status'] ?? '', ['Open', 'In Progress', 'Mitigated', 'Accepted', 'Resolved'], true) ? $_POST['finding_status'] : 'Open';
    $imm = !empty($_POST['finding_immediate_attention']) ? 1 : 0;
    $party = escapeSql($_POST['finding_responsible_party'] ?? '');
    $tdate = !empty($_POST['finding_target_date']) ? "'" . escapeSql($_POST['finding_target_date']) . "'" : 'NULL';
    $cost = ($_POST['finding_estimated_cost'] ?? '') !== '' ? floatval($_POST['finding_estimated_cost']) : 'NULL';
    $vis = isset($_POST['finding_client_visible']) ? (intval($_POST['finding_client_visible']) ? 1 : 0) : 1;

    $int_sql = "";
    if (tbrHasPermission('view_internal_notes')) {
        $int_notes = mysqli_real_escape_string($mysqli, (string)($_POST['finding_internal_notes'] ?? ''));
        $int_sql = ", finding_internal_notes = '$int_notes'";
    }

    if ($fid > 0) {
        mysqli_query($mysqli, "
            UPDATE tbr_findings SET
                finding_category = '$category',
                finding_title = '$title',
                finding_description = '$desc',
                finding_business_impact = '$impact',
                finding_recommended_action = '$rec_action',
                finding_risk_level = '$risk',
                finding_priority = '$prio',
                finding_status = '$status',
                finding_immediate_attention = $imm,
                finding_responsible_party = '$party',
                finding_target_date = $tdate,
                finding_estimated_cost = $cost,
                finding_client_visible = $vis
                $int_sql
            WHERE finding_id = $fid AND finding_tbr_id = $tbr_id
        ");
    } else {
        mysqli_query($mysqli, "
            INSERT INTO tbr_findings SET
                finding_tbr_id = $tbr_id,
                finding_client_id = $client_id,
                finding_section = '$section',
                finding_category = '$category',
                finding_title = '$title',
                finding_description = '$desc',
                finding_business_impact = '$impact',
                finding_recommended_action = '$rec_action',
                finding_risk_level = '$risk',
                finding_priority = '$prio',
                finding_status = '$status',
                finding_immediate_attention = $imm,
                finding_responsible_party = '$party',
                finding_target_date = $tdate,
                finding_estimated_cost = $cost,
                finding_client_visible = $vis,
                finding_created_by = $session_user_id
                $int_sql
        ");
        $fid = mysqli_insert_id($mysqli);
    }

    tbrJsonOk(['finding_id' => $fid, 'findings' => tbrLoadFindingsPayload($tbr_id)]);
}

if ($action === 'finding_delete') {
    tbrEnforcePermission('edit', true);
    if ($is_locked) {
        tbrJsonError('TBR is locked.', 409);
    }
    $fid = intval($_POST['finding_id'] ?? 0);
    mysqli_query($mysqli, "DELETE FROM tbr_findings WHERE finding_id = $fid AND finding_tbr_id = $tbr_id");
    tbrJsonOk(['findings' => tbrLoadFindingsPayload($tbr_id)]);
}

if ($action === 'recommendation_save') {
    tbrEnforcePermission('edit', true);
    if ($is_locked) {
        tbrJsonError('TBR is locked.', 409);
    }

    $rid = intval($_POST['recommendation_id'] ?? 0);
    $title = escapeSql($_POST['recommendation_title'] ?? '');
    if ($title === '') {
        tbrJsonError('Recommendation title is required.');
    }
    $desc = mysqli_real_escape_string($mysqli, (string)($_POST['recommendation_description'] ?? ''));
    $just = mysqli_real_escape_string($mysqli, (string)($_POST['recommendation_justification'] ?? ''));
    $prob = mysqli_real_escape_string($mysqli, (string)($_POST['recommendation_problem'] ?? ''));
    $risk = mysqli_real_escape_string($mysqli, (string)($_POST['recommendation_risk_addressed'] ?? ''));
    $prio = in_array($_POST['recommendation_priority'] ?? '', ['Critical', 'High', 'Medium', 'Low'], true) ? $_POST['recommendation_priority'] : 'Medium';
    $cost = ($_POST['recommendation_estimated_cost'] ?? '') !== '' ? floatval($_POST['recommendation_estimated_cost']) : 'NULL';
    $timeline = escapeSql($_POST['recommendation_timeline'] ?? '');
    $party = escapeSql($_POST['recommendation_responsible_party'] ?? '');
    $status = in_array($_POST['recommendation_status'] ?? '', ['Proposed', 'Approved', 'Declined', 'Deferred', 'Completed'], true) ? $_POST['recommendation_status'] : 'Proposed';
    $notes = mysqli_real_escape_string($mysqli, (string)($_POST['recommendation_notes'] ?? ''));

    $int_sql = "";
    if (tbrHasPermission('view_internal_notes')) {
        $int_notes = mysqli_real_escape_string($mysqli, (string)($_POST['recommendation_internal_notes'] ?? ''));
        $int_sql = ", recommendation_internal_notes = '$int_notes'";
    }

    if ($rid > 0) {
        mysqli_query($mysqli, "
            UPDATE tbr_recommendations SET
                recommendation_title = '$title',
                recommendation_description = '$desc',
                recommendation_justification = '$just',
                recommendation_problem = '$prob',
                recommendation_risk_addressed = '$risk',
                recommendation_priority = '$prio',
                recommendation_estimated_cost = $cost,
                recommendation_timeline = '$timeline',
                recommendation_responsible_party = '$party',
                recommendation_status = '$status',
                recommendation_notes = '$notes'
                $int_sql
            WHERE recommendation_id = $rid AND recommendation_tbr_id = $tbr_id
        ");
    } else {
        mysqli_query($mysqli, "
            INSERT INTO tbr_recommendations SET
                recommendation_tbr_id = $tbr_id,
                recommendation_client_id = $client_id,
                recommendation_title = '$title',
                recommendation_description = '$desc',
                recommendation_justification = '$just',
                recommendation_problem = '$prob',
                recommendation_risk_addressed = '$risk',
                recommendation_priority = '$prio',
                recommendation_estimated_cost = $cost,
                recommendation_timeline = '$timeline',
                recommendation_responsible_party = '$party',
                recommendation_status = '$status',
                recommendation_notes = '$notes',
                recommendation_created_by = $session_user_id
                $int_sql
        ");
        $rid = mysqli_insert_id($mysqli);
    }

    tbrJsonOk(['recommendation_id' => $rid, 'recommendations' => tbrLoadRecommendationsPayload($tbr_id)]);
}

if ($action === 'recommendation_delete') {
    tbrEnforcePermission('edit', true);
    if ($is_locked) {
        tbrJsonError('TBR is locked.', 409);
    }
    $rid = intval($_POST['recommendation_id'] ?? 0);
    mysqli_query($mysqli, "DELETE FROM tbr_recommendations WHERE recommendation_id = $rid AND recommendation_tbr_id = $tbr_id");
    tbrJsonOk(['recommendations' => tbrLoadRecommendationsPayload($tbr_id)]);
}

if ($action === 'recommendation_to_roadmap') {
    tbrEnforcePermission('edit', true);
    if ($is_locked) {
        tbrJsonError('TBR is locked.', 409);
    }
    $rid = intval($_POST['recommendation_id'] ?? 0);
    $rec = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbr_recommendations WHERE recommendation_id = $rid AND recommendation_tbr_id = $tbr_id LIMIT 1"));
    if (!$rec) {
        tbrJsonError('Recommendation not found.', 404);
    }
    $quarter = in_array($_POST['roadmap_quarter'] ?? '', ['Q1', 'Q2', 'Q3', 'Q4'], true) ? $_POST['roadmap_quarter'] : 'Q1';
    $category = in_array($_POST['roadmap_category'] ?? '', ['Required Security', 'Hardware Replacement', 'Software & Licensing', 'Infrastructure Improvement', 'Optional Enhancement'], true)
        ? $_POST['roadmap_category']
        : 'Infrastructure Improvement';
    $year = intval($_POST['roadmap_fiscal_year'] ?? date('Y'));
    $pname = mysqli_real_escape_string($mysqli, $rec['recommendation_title']);
    $pdesc = mysqli_real_escape_string($mysqli, (string)$rec['recommendation_description']);
    $cost = floatval($rec['recommendation_estimated_cost'] ?? 0);
    $prio = escapeSql($rec['recommendation_priority']);
    $status = $rec['recommendation_status'] === 'Approved' ? 'Approved' : 'Planned';

    mysqli_query($mysqli, "
        INSERT INTO tbr_roadmap_items SET
            roadmap_tbr_id = $tbr_id,
            roadmap_client_id = $client_id,
            roadmap_recommendation_id = $rid,
            roadmap_project_name = '$pname',
            roadmap_description = '$pdesc',
            roadmap_category = '$category',
            roadmap_quarter = '$quarter',
            roadmap_fiscal_year = $year,
            roadmap_estimated_cost = $cost,
            roadmap_priority = '$prio',
            roadmap_status = '$status',
            roadmap_created_by = $session_user_id
    ");
    tbrJsonOk(['roadmap' => tbrLoadRoadmapPayload($tbr_id)]);
}

if ($action === 'roadmap_save') {
    tbrEnforcePermission('edit', true);
    if ($is_locked) {
        tbrJsonError('TBR is locked.', 409);
    }

    $rmid = intval($_POST['roadmap_id'] ?? 0);
    $name = escapeSql($_POST['roadmap_project_name'] ?? '');
    if ($name === '') {
        tbrJsonError('Project name is required.');
    }
    $desc = mysqli_real_escape_string($mysqli, (string)($_POST['roadmap_description'] ?? ''));
    $cat = in_array($_POST['roadmap_category'] ?? '', ['Required Security', 'Hardware Replacement', 'Software & Licensing', 'Infrastructure Improvement', 'Optional Enhancement'], true)
        ? $_POST['roadmap_category']
        : 'Infrastructure Improvement';
    $quarter = in_array($_POST['roadmap_quarter'] ?? '', ['Q1', 'Q2', 'Q3', 'Q4'], true) ? $_POST['roadmap_quarter'] : 'Q1';
    $year = max(2020, min(2100, intval($_POST['roadmap_fiscal_year'] ?? date('Y'))));
    $cost = floatval($_POST['roadmap_estimated_cost'] ?? 0);
    $prio = in_array($_POST['roadmap_priority'] ?? '', ['Critical', 'High', 'Medium', 'Low'], true) ? $_POST['roadmap_priority'] : 'Medium';
    $status = in_array($_POST['roadmap_status'] ?? '', ['Planned', 'Approved', 'In Progress', 'Completed', 'Deferred'], true) ? $_POST['roadmap_status'] : 'Planned';

    if ($rmid > 0) {
        mysqli_query($mysqli, "
            UPDATE tbr_roadmap_items SET
                roadmap_project_name = '$name',
                roadmap_description = '$desc',
                roadmap_category = '$cat',
                roadmap_quarter = '$quarter',
                roadmap_fiscal_year = $year,
                roadmap_estimated_cost = $cost,
                roadmap_priority = '$prio',
                roadmap_status = '$status'
            WHERE roadmap_id = $rmid AND roadmap_tbr_id = $tbr_id
        ");
    } else {
        mysqli_query($mysqli, "
            INSERT INTO tbr_roadmap_items SET
                roadmap_tbr_id = $tbr_id,
                roadmap_client_id = $client_id,
                roadmap_project_name = '$name',
                roadmap_description = '$desc',
                roadmap_category = '$cat',
                roadmap_quarter = '$quarter',
                roadmap_fiscal_year = $year,
                roadmap_estimated_cost = $cost,
                roadmap_priority = '$prio',
                roadmap_status = '$status',
                roadmap_created_by = $session_user_id
        ");
        $rmid = mysqli_insert_id($mysqli);
    }

    tbrJsonOk(['roadmap_id' => $rmid, 'roadmap' => tbrLoadRoadmapPayload($tbr_id)]);
}

if ($action === 'roadmap_delete') {
    tbrEnforcePermission('edit', true);
    if ($is_locked) {
        tbrJsonError('TBR is locked.', 409);
    }
    $rmid = intval($_POST['roadmap_id'] ?? 0);
    mysqli_query($mysqli, "DELETE FROM tbr_roadmap_items WHERE roadmap_id = $rmid AND roadmap_tbr_id = $tbr_id");
    tbrJsonOk(['roadmap' => tbrLoadRoadmapPayload($tbr_id)]);
}

if ($action === 'action_save') {
    tbrEnforcePermission('edit', true);
    if ($is_locked) {
        tbrJsonError('TBR is locked.', 409);
    }

    $aid = intval($_POST['action_id'] ?? 0);
    if (isset($_POST['inline_status']) && $aid > 0) {
        $st = in_array($_POST['inline_status'], ['Not Started', 'In Progress', 'Waiting on Client', 'Completed', 'Cancelled'], true) ? $_POST['inline_status'] : 'Not Started';
        $comp_sql = ($st === 'Completed') ? ", action_completed_at = NOW()" : ", action_completed_at = NULL";
        mysqli_query($mysqli, "UPDATE tbr_action_items SET action_status = '$st' $comp_sql WHERE action_id = $aid AND action_tbr_id = $tbr_id");
        tbrJsonOk(['actions' => tbrLoadActionsPayload($tbr_id)]);
    }

    $desc = mysqli_real_escape_string($mysqli, trim((string)($_POST['action_description'] ?? '')));
    if ($desc === '') {
        tbrJsonError('Action item description is required.');
    }
    $party = in_array($_POST['action_responsible_party'] ?? '', ['MSP', 'Client', 'Shared'], true) ? $_POST['action_responsible_party'] : 'MSP';
    $assigned_uid = !empty($_POST['action_assigned_user_id']) ? intval($_POST['action_assigned_user_id']) : 'NULL';
    $client_owner = escapeSql($_POST['action_client_owner'] ?? '');
    $prio = in_array($_POST['action_priority'] ?? '', ['Critical', 'High', 'Medium', 'Low'], true) ? $_POST['action_priority'] : 'Medium';
    $due = !empty($_POST['action_due_date']) ? "'" . escapeSql($_POST['action_due_date']) . "'" : 'NULL';
    $status = in_array($_POST['action_status'] ?? '', ['Not Started', 'In Progress', 'Waiting on Client', 'Completed', 'Cancelled'], true) ? $_POST['action_status'] : 'Not Started';
    $comp_sql = ($status === 'Completed') ? ", action_completed_at = COALESCE(action_completed_at, NOW())" : ", action_completed_at = NULL";

    $int_sql = "";
    if (tbrHasPermission('view_internal_notes')) {
        $int_notes = mysqli_real_escape_string($mysqli, (string)($_POST['action_internal_notes'] ?? ''));
        $int_sql = ", action_internal_notes = '$int_notes'";
    }

    if ($aid > 0) {
        mysqli_query($mysqli, "
            UPDATE tbr_action_items SET
                action_description = '$desc',
                action_responsible_party = '$party',
                action_assigned_user_id = $assigned_uid,
                action_client_owner = '$client_owner',
                action_priority = '$prio',
                action_due_date = $due,
                action_status = '$status'
                $comp_sql
                $int_sql
            WHERE action_id = $aid AND action_tbr_id = $tbr_id
        ");
    } else {
        mysqli_query($mysqli, "
            INSERT INTO tbr_action_items SET
                action_tbr_id = $tbr_id,
                action_client_id = $client_id,
                action_description = '$desc',
                action_responsible_party = '$party',
                action_assigned_user_id = $assigned_uid,
                action_client_owner = '$client_owner',
                action_priority = '$prio',
                action_due_date = $due,
                action_status = '$status',
                action_created_by = $session_user_id
                $comp_sql
                $int_sql
        ");
        $aid = mysqli_insert_id($mysqli);
    }

    tbrJsonOk(['action_id' => $aid, 'actions' => tbrLoadActionsPayload($tbr_id)]);
}

if ($action === 'action_delete') {
    tbrEnforcePermission('edit', true);
    if ($is_locked) {
        tbrJsonError('TBR is locked.', 409);
    }
    $aid = intval($_POST['action_id'] ?? 0);
    mysqli_query($mysqli, "DELETE FROM tbr_action_items WHERE action_id = $aid AND action_tbr_id = $tbr_id");
    tbrJsonOk(['actions' => tbrLoadActionsPayload($tbr_id)]);
}

if ($action === 'action_create_ticket') {
    tbrEnforcePermission('edit', true);
    enforceUserPermission('module_support', 2);
    $aid = intval($_POST['action_id'] ?? 0);
    $chk = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT action_id FROM tbr_action_items WHERE action_id = $aid AND action_tbr_id = $tbr_id LIMIT 1"));
    if (!$chk) {
        tbrJsonError('Action item not found.', 404);
    }
    $res = tbrCreateTicketForActionItem($aid, $session_user_id);
    if (empty($res['ok'])) {
        tbrJsonError($res['error'] ?? 'Could not create ticket.');
    }
    tbrJsonOk(['ticket' => $res, 'actions' => tbrLoadActionsPayload($tbr_id)]);
}

if ($action === 'carry_forward') {
    tbrEnforcePermission('edit', true);
    if ($is_locked) {
        tbrJsonError('TBR is locked.', 409);
    }
    $prev_id = intval($meeting['tbr_previous_tbr_id'] ?? 0);
    if ($prev_id <= 0) {
        $prev = tbrFindPreviousMeeting($client_id, $tbr_id);
        $prev_id = $prev ? intval($prev['tbr_id']) : 0;
    }
    if ($prev_id <= 0) {
        tbrJsonError('No previous TBR found for this client.');
    }
    $inc_actions = !empty($_POST['include_actions']);
    $inc_recs = !empty($_POST['include_recommendations']);
    $result = tbrCarryForwardFromPrevious($tbr_id, $prev_id, $session_user_id, $inc_actions, $inc_recs);
    tbrJsonOk([
        'result' => $result,
        'actions' => tbrLoadActionsPayload($tbr_id),
        'recommendations' => tbrLoadRecommendationsPayload($tbr_id),
    ]);
}

if ($action === 'approve_client_report') {
    tbrEnforcePermission('complete', true);
    $approved = intval($_POST['approved'] ?? 1) ? 1 : 0;
    if ($approved) {
        mysqli_query($mysqli, "UPDATE tbr_meetings SET tbr_client_report_approved = 1, tbr_client_report_approved_by = $session_user_id, tbr_client_report_approved_at = NOW() WHERE tbr_id = $tbr_id");
        tbrLogAudit($tbr_id, $client_id, 'Report Approved', 'Approved client-facing TBR report for generation/delivery', $session_user_id);
    } else {
        mysqli_query($mysqli, "UPDATE tbr_meetings SET tbr_client_report_approved = 0, tbr_client_report_approved_by = NULL, tbr_client_report_approved_at = NULL WHERE tbr_id = $tbr_id");
        tbrLogAudit($tbr_id, $client_id, 'Approval Revoked', 'Revoked client-facing report approval', $session_user_id);
    }
    tbrJsonOk(['approved' => $approved]);
}

if ($action === 'complete_tbr') {
    tbrEnforcePermission('complete', true);
    if ($meeting['tbr_status'] === 'Completed') {
        tbrJsonError('This TBR is already completed.');
    }

    if (!empty($_POST['tbr_next_review_date'])) {
        $nrd = escapeSql($_POST['tbr_next_review_date']);
        mysqli_query($mysqli, "UPDATE tbr_meetings SET tbr_next_review_date = '$nrd' WHERE tbr_id = $tbr_id");
    }

    $validation = tbrValidateCompletion($tbr_id);
    if (!$validation['valid']) {
        tbrJsonError('Please resolve completion requirements.', 422, ['validation' => $validation]);
    }

    $meeting_fresh = tbrGetMeeting($tbr_id);
    $snapshot = [
        'completed_at' => date('Y-m-d H:i:s'),
        'completed_by' => $session_user_id,
        'overall_health' => $meeting_fresh['tbr_overall_health'],
        'progress' => tbrCalculateProgress($tbr_id),
        'findings_count' => count(tbrLoadFindingsPayload($tbr_id)),
        'recommendations_count' => count(tbrLoadRecommendationsPayload($tbr_id)),
        'roadmap' => tbrLoadRoadmapPayload($tbr_id)['totals'],
        'action_items_count' => count(tbrLoadActionsPayload($tbr_id)),
        'live_data' => tbrFetchLiveClientData($client_id, $meeting_fresh['tbr_reporting_period_start'], $meeting_fresh['tbr_reporting_period_end']),
    ];
    $snap_esc = mysqli_real_escape_string($mysqli, json_encode($snapshot, JSON_UNESCAPED_UNICODE));

    $approve_now = !empty($_POST['approve_client_report']) ? 1 : intval($meeting_fresh['tbr_client_report_approved']);
    $app_sql = ($approve_now && empty($meeting_fresh['tbr_client_report_approved']))
        ? ", tbr_client_report_approved = 1, tbr_client_report_approved_by = $session_user_id, tbr_client_report_approved_at = NOW()"
        : "";

    mysqli_query($mysqli, "
        UPDATE tbr_meetings SET
            tbr_status = 'Completed',
            tbr_completed_at = NOW(),
            tbr_completed_by = $session_user_id,
            tbr_completion_snapshot = '$snap_esc'
            $app_sql
        WHERE tbr_id = $tbr_id
    ");

    mysqli_query($mysqli, "UPDATE tbr_notification_schedules SET schedule_status = 'Cancelled' WHERE schedule_tbr_id = $tbr_id AND schedule_status = 'Pending'");
    tbrLogAudit($tbr_id, $client_id, 'Completed', 'Marked TBR as Completed and archived historical snapshot', $session_user_id);

    $generated_report = null;
    if ($approve_now && tbrHasPermission('generate_pdf')) {
        require_once __DIR__ . '/../functions/tbr_pdf.php';
        $generated_report = tbrGeneratePdfReport($tbr_id, 'client', $session_user_id);
    }

    $next_tbr_id = null;
    if (!empty($_POST['schedule_next_tbr']) && !empty($meeting_fresh['tbr_next_review_date']) && tbrHasPermission('schedule')) {
        $next_date = escapeSql($meeting_fresh['tbr_next_review_date']);
        $next_type = $meeting_fresh['tbr_type'] === 'Annual' ? 'Annual' : 'Quarterly';
        $nq = intval(ceil(date('n', strtotime($next_date)) / 3));
        $ny = date('Y', strtotime($next_date));
        $next_title = escapeSql($next_type === 'Annual' ? "$ny Annual Strategic Review" : "Q$nq $ny Technology Business Review");
        $rep_uid = intval($meeting_fresh['tbr_assigned_user_id'] ?? $session_user_id);
        $start_tm = escapeSql($meeting_fresh['tbr_start_time']);
        $dur = intval($meeting_fresh['tbr_duration_minutes']);
        $loc = escapeSql((string)$meeting_fresh['tbr_location']);
        $p_end = $next_date;
        $p_start = date('Y-m-d', strtotime($next_date . ($next_type === 'Annual' ? ' -365 days' : ' -90 days')));

        mysqli_query($mysqli, "
            INSERT INTO tbr_meetings SET
                tbr_client_id = $client_id,
                tbr_previous_tbr_id = $tbr_id,
                tbr_title = '$next_title',
                tbr_type = '$next_type',
                tbr_status = 'Scheduled',
                tbr_meeting_date = '$next_date',
                tbr_start_time = '$start_tm',
                tbr_duration_minutes = $dur,
                tbr_location = '$loc',
                tbr_assigned_user_id = $rep_uid,
                tbr_reporting_period_start = '$p_start',
                tbr_reporting_period_end = '$p_end',
                tbr_recurring_enabled = 1,
                tbr_recurring_interval = '$next_type',
                tbr_auto_generate_agenda = 1,
                tbr_created_by = $session_user_id
        ");
        $next_tbr_id = mysqli_insert_id($mysqli);
        if ($next_tbr_id > 0) {
            mysqli_query($mysqli, "
                INSERT INTO tbr_attendees (attendee_tbr_id, attendee_type, attendee_user_id, attendee_contact_id, attendee_name, attendee_email, attendee_receive_notifications)
                SELECT $next_tbr_id, attendee_type, attendee_user_id, attendee_contact_id, attendee_name, attendee_email, attendee_receive_notifications
                FROM tbr_attendees WHERE attendee_tbr_id = $tbr_id
            ");
            tbrInitializeAgenda($next_tbr_id, $session_user_id);
            tbrCarryForwardFromPrevious($next_tbr_id, $tbr_id, $session_user_id, true, true);
            tbrSyncCalendarEvent($next_tbr_id);
            tbrSyncNotificationSchedules($next_tbr_id, false);
            tbrLogAudit($next_tbr_id, $client_id, 'Auto-Scheduled', "Auto-scheduled follow-up review from TBR #$tbr_id", $session_user_id);
        }
    }

    tbrJsonOk(['completed' => true, 'report' => $generated_report, 'next_tbr_id' => $next_tbr_id]);
}

if ($action === 'reopen_tbr') {
    tbrEnforcePermission('reopen', true);
    $reason = trim((string)($_POST['reopen_reason'] ?? ''));
    if ($reason === '') {
        tbrJsonError('A reason is required to reopen a completed TBR.');
    }
    mysqli_query($mysqli, "UPDATE tbr_meetings SET tbr_status = 'In Progress', tbr_last_edited_by = $session_user_id WHERE tbr_id = $tbr_id");
    tbrLogAudit($tbr_id, $client_id, 'Reopened', "Reopened completed TBR. Reason: $reason", $session_user_id);
    tbrJsonOk(['status' => 'In Progress']);
}

if ($action === 'email_report') {
    tbrEnforcePermission('email_report', true);
    if (empty($meeting['tbr_client_report_approved'])) {
        tbrJsonError('The client-facing report must be approved before emailing to the client.', 422);
    }

    $report = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tbr_reports WHERE report_tbr_id = $tbr_id AND report_type = 'client' ORDER BY report_id DESC LIMIT 1"));
    if (!$report) {
        require_once __DIR__ . '/../functions/tbr_pdf.php';
        $report = tbrGeneratePdfReport($tbr_id, 'client', $session_user_id);
    }

    $recipients = [];
    $selected = $_POST['recipients'] ?? [];
    if (is_array($selected)) {
        foreach ($selected as $em) {
            $em = trim((string)$em);
            if (filter_var($em, FILTER_VALIDATE_EMAIL)) {
                $att = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT attendee_name FROM tbr_attendees WHERE attendee_tbr_id = $tbr_id AND attendee_email = '" . escapeSql($em) . "' LIMIT 1"));
                $recipients[$em] = $att['attendee_name'] ?? $em;
            }
        }
    }
    $extra = trim((string)($_POST['extra_recipients'] ?? ''));
    if ($extra !== '') {
        foreach (preg_split('/[\s,;]+/', $extra) as $em) {
            $em = trim($em);
            if (filter_var($em, FILTER_VALIDATE_EMAIL)) {
                $recipients[$em] = $recipients[$em] ?? $em;
            }
        }
    }

    if (empty($recipients)) {
        tbrJsonError('Please select or enter at least one valid email recipient.');
    }

    $subject = trim((string)($_POST['email_subject'] ?? ''));
    $body = trim((string)($_POST['email_body'] ?? ''));
    if ($subject === '' || $body === '') {
        $subject = null;
        $body = null;
    }

    $attachments = [[
        'path' => $report['report_filepath'],
        'name' => $report['report_filename'],
    ]];

    $queued = tbrQueueMeetingEmail($tbr_id, 'report_delivery', 'client', $subject, $body, $attachments, $recipients);
    if ($queued <= 0) {
        tbrJsonError('Could not queue email. Check mail settings (From Email) in Admin > Mail.');
    }

    $to_list = escapeSql(implode(', ', array_keys($recipients)));
    $rid = intval($report['report_id']);
    mysqli_query($mysqli, "UPDATE tbr_reports SET report_emailed_at = NOW(), report_emailed_to = '$to_list' WHERE report_id = $rid");
    tbrLogAudit($tbr_id, $client_id, 'Report Emailed', "Queued Client TBR PDF v{$report['report_version']} to $to_list", $session_user_id);

    tbrJsonOk(['queued' => $queued, 'recipients' => array_keys($recipients)]);
}

tbrJsonError('Unknown action.');
