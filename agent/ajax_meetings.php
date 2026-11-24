<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['logged']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required.']);
    exit();
}

require_once __DIR__ . '/../includes/check_login.php';

function mtgJsonError(string $msg, int $code = 400, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['ok' => false, 'error' => $msg], $extra));
    exit();
}

function mtgJsonOk(array $data = []): void {
    echo json_encode(array_merge(['ok' => true], $data));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        mtgJsonError('CSRF token validation failed.', 403);
    }
}

$action = $_REQUEST['action'] ?? '';
$occurrence_id = intval($_REQUEST['occurrence_id'] ?? 0);
$series_id = intval($_REQUEST['series_id'] ?? 0);
$is_admin = !empty($session_is_admin);

if (empty($is_admin) && lookupUserPermission('module_meetings') < 1) {
    mtgJsonError(WORDING_ROLECHECK_FAILED, 403);
}

$is_metric_action = str_starts_with($action, 'metric_');

if ($is_metric_action) {
    function mtgResolveMetricAuth(int $mid, int $user_id, bool $is_admin): array {
        global $mysqli;
        $m = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM meeting_metrics WHERE metric_id = $mid AND archived_at IS NULL LIMIT 1"));
        if (!$m) {
            mtgJsonError('Metric not found.', 404);
        }
        $msid = $m['series_id'] !== null ? intval($m['series_id']) : 0;
        $s = $msid > 0 ? meetingGetSeries($msid) : null;
        $can_view = $is_admin || intval($m['owner_user_id']) === $user_id || ($m['privacy'] ?? 'Public') === 'Public' || ($s && meetingCanAccessSeries($s, $user_id, $is_admin));
        if (!$can_view) {
            mtgJsonError(WORDING_ROLECHECK_FAILED, 403);
        }
        $can_edit = $is_admin || intval($m['owner_user_id']) === $user_id || ($s && meetingIsFacilitatorOrAdmin($s, $user_id, $is_admin));
        return ['metric' => $m, 'series' => $s, 'series_id' => $msid, 'can_edit' => $can_edit];
    }

    function mtgBumpMetricSync(int $msid, string $scope = 'Attendee'): void {
        global $mysqli;
        if ($msid > 0) {
            meetingBumpSeriesSyncSeq($msid);
        } elseif ($scope === 'Company') {
            mysqli_query($mysqli, "UPDATE meeting_occurrences SET sync_seq = sync_seq + 1");
        }
    }

    if ($action === 'metric_detail') {
        $mid = intval($_GET['metric_id'] ?? 0);
        $auth = mtgResolveMetricAuth($mid, $session_user_id, $is_admin);
        $m = $auth['metric'];
        $as_of = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['as_of'] ?? '') ? $_GET['as_of'] : date('Y-m-d');
        $q_key = $_GET['quarter'] ?? null;
        $summary = meetingComputeMetricSummary($m, $as_of, null, $q_key);
        $u = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name, user_avatar FROM users WHERE user_id = " . intval($m['owner_user_id']) . " LIMIT 1"));
        $av = meetingUserAvatarData(intval($m['owner_user_id']), $u['user_name'] ?? 'User', $u['user_avatar'] ?? null);
        mtgJsonOk([
            'metric' => [
                'metric_id' => intval($m['metric_id']),
                'series_id' => $auth['series_id'] ?: null,
                'owner_user_id' => intval($m['owner_user_id']),
                'owner_name' => $u['user_name'] ?? 'Unassigned',
                'owner_avatar_url' => $av['avatar_url'],
                'owner_initials' => $av['initials'],
                'owner_color' => $av['color'],
                'metric_name' => $m['metric_name'],
                'metric_type' => $m['metric_type'] ?? 'Count',
                'frequency' => $m['frequency'] ?? 'Monthly',
                'work_days' => $m['work_days'] ?? 'Mon,Tue,Wed,Thu,Fri',
                'week_start' => $m['week_start'] ?? 'Monday',
                'unit' => $m['unit'],
                'scope' => $m['scope'],
                'privacy' => $m['privacy'] ?? 'Public',
                'is_starred' => intval($m['is_starred']),
                'goal_direction' => $m['goal_direction'],
                'start_target' => floatval($m['start_target'] ?? 0),
                'auto_set_status' => intval($m['auto_set_status'] ?? 1),
                'super_green_pct' => floatval($m['super_green_pct'] ?? 120),
                'yellow_pct' => floatval($m['yellow_pct'] ?? 80),
                'can_edit' => $auth['can_edit'],
            ],
            'summary' => $summary,
        ]);
    }

    if ($action === 'metric_export_csv') {
        $mid = intval($_GET['metric_id'] ?? 0);
        $auth = mtgResolveMetricAuth($mid, $session_user_id, $is_admin);
        $m = $auth['metric'];
        $as_of = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['as_of'] ?? '') ? $_GET['as_of'] : date('Y-m-d');
        $q_key = $_GET['quarter'] ?? null;
        $summary = meetingComputeMetricSummary($m, $as_of, null, $q_key);

        $fname = sanitizeFilename($m['metric_name'] . '-' . $summary['quarter']['label']) . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Period', 'Actual', 'Rolling Target', 'Approved Target', 'Period To Date Actual', 'Period To Date Target', 'Status', 'Status Note']);
        foreach ($summary['rows'] as $r) {
            fputcsv($out, [
                escapeCsvFormula($r['label']),
                $r['actual_value'] !== null ? $r['actual_value'] : '',
                $r['rolling_target'],
                $r['approved_target'],
                $r['period_to_date_actual'] !== null ? $r['period_to_date_actual'] : '',
                $r['period_to_date_target'],
                $r['status_color'],
                escapeCsvFormula((string)$r['status_note']),
            ]);
        }
        fclose($out);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        mtgJsonError('Invalid request method.', 400);
    }

    if ($action === 'metric_save') {
        $mid = intval($_POST['metric_id'] ?? 0);
        $owner_uid = intval($_POST['owner_user_id'] ?? $session_user_id);
        $target_sid = intval($_POST['series_id'] ?? $series_id);

        if ($mid > 0) {
            $auth = mtgResolveMetricAuth($mid, $session_user_id, $is_admin);
            if (!$auth['can_edit']) {
                mtgJsonError('Only the metric owner, facilitator, or admin can edit this metric.', 403);
            }
            if (!isset($_POST['series_id'])) {
                $target_sid = $auth['series_id'];
            }
        } elseif ($target_sid > 0) {
            $s = meetingGetSeries($target_sid);
            if (!$s || !meetingCanAccessSeries($s, $session_user_id, $is_admin)) {
                mtgJsonError(WORDING_ROLECHECK_FAILED, 403);
            }
        }

        $name = escapeSql($_POST['metric_name'] ?? '');
        if ($name === '') {
            mtgJsonError('Metric name is required.');
        }
        $mtype = in_array($_POST['metric_type'] ?? '', ['Count', 'Measurement', 'Flat'], true) ? $_POST['metric_type'] : 'Count';
        $freq = in_array($_POST['frequency'] ?? '', ['Daily', 'Weekly', 'Monthly'], true) ? $_POST['frequency'] : 'Monthly';
        $wd_raw = is_array($_POST['work_days'] ?? null) ? implode(',', $_POST['work_days']) : ($_POST['work_days'] ?? 'Mon,Tue,Wed,Thu,Fri');
        $work_days = escapeSql($wd_raw ?: 'Mon,Tue,Wed,Thu,Fri');
        $week_start = ($_POST['week_start'] ?? '') === 'Sunday' ? 'Sunday' : 'Monday';
        $unit = in_array($_POST['unit'] ?? '', ['Number', 'Currency', 'Percent'], true) ? $_POST['unit'] : 'Number';
        $scope = ($_POST['scope'] ?? '') === 'Company' ? 'Company' : 'Attendee';
        $privacy = ($_POST['privacy'] ?? '') === 'Private' ? 'Private' : 'Public';
        $dir = ($_POST['goal_direction'] ?? '') === 'LowerBetter' ? 'LowerBetter' : 'HigherBetter';
        $start_target = floatval($_POST['start_target'] ?? 0);
        $p_target = floatval($_POST['period_target'] ?? 0);
        $auto_status = isset($_POST['auto_set_status']) ? (!empty($_POST['auto_set_status']) ? 1 : 0) : 1;
        $sg_pct = max(100.0, floatval($_POST['super_green_pct'] ?? 120.0));
        $y_pct = max(1.0, min(99.9, floatval($_POST['yellow_pct'] ?? 80.0)));
        $starred = !empty($_POST['is_starred']) ? 1 : 0;
        $sid_sql = $target_sid > 0 ? $target_sid : 'NULL';

        if ($mid > 0) {
            mysqli_query($mysqli, "
                UPDATE meeting_metrics SET
                    series_id = $sid_sql,
                    owner_user_id = $owner_uid,
                    metric_name = '$name',
                    metric_type = '$mtype',
                    frequency = '$freq',
                    work_days = '$work_days',
                    week_start = '$week_start',
                    unit = '$unit',
                    scope = '$scope',
                    privacy = '$privacy',
                    is_starred = $starred,
                    goal_direction = '$dir',
                    start_target = $start_target,
                    period_target = $p_target,
                    auto_set_status = $auto_status,
                    super_green_pct = $sg_pct,
                    yellow_pct = $y_pct
                WHERE metric_id = $mid
            ");
        } else {
            $max_o = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM meeting_metrics"))[0]);
            mysqli_query($mysqli, "
                INSERT INTO meeting_metrics SET
                    series_id = $sid_sql,
                    owner_user_id = $owner_uid,
                    metric_name = '$name',
                    metric_type = '$mtype',
                    frequency = '$freq',
                    work_days = '$work_days',
                    week_start = '$week_start',
                    unit = '$unit',
                    scope = '$scope',
                    privacy = '$privacy',
                    is_starred = $starred,
                    goal_direction = '$dir',
                    start_target = $start_target,
                    period_target = $p_target,
                    auto_set_status = $auto_status,
                    super_green_pct = $sg_pct,
                    yellow_pct = $y_pct,
                    sort_order = $max_o
            ");
            $mid = mysqli_insert_id($mysqli);
        }

        if (isset($_POST['quarter_goal']) && trim((string)$_POST['quarter_goal']) !== '') {
            $q_goal = floatval($_POST['quarter_goal']);
            $q_key = $_POST['quarter_key'] ?? meetingGetQuarterBounds(date('Y-m-d'))['quarter_key'];
            $qb = meetingGetQuarterBounds($q_key);
            $intervals = meetingGetQuarterIntervals($qb, $freq, $week_start, date('Y-m-d'));
            $cnt = max(1, count($intervals));
            foreach ($intervals as $idx => $iv) {
                $pk_esc = escapeSql($iv['period_key']);
                if ($mtype === 'Count') {
                    $iv_target = round($q_goal / $cnt, 2);
                } elseif ($mtype === 'Measurement') {
                    $iv_target = round($start_target + (($q_goal - $start_target) * (($idx + 1) / $cnt)), 2);
                } else {
                    $iv_target = $q_goal;
                }
                mysqli_query($mysqli, "
                    INSERT INTO meeting_metric_values SET
                        metric_id = $mid,
                        period_key = '$pk_esc',
                        approved_target = $iv_target,
                        rolling_target = $iv_target,
                        updated_by = $session_user_id
                    ON DUPLICATE KEY UPDATE
                        approved_target = VALUES(approved_target),
                        rolling_target = VALUES(rolling_target),
                        updated_by = VALUES(updated_by)
                ");
            }
        } elseif (isset($_POST['actual_value']) && trim((string)$_POST['actual_value']) !== '') {
            $pk_esc = escapeSql($_POST['period_key'] ?? date('Y-m'));
            $act_val = floatval($_POST['actual_value']);
            mysqli_query($mysqli, "
                INSERT INTO meeting_metric_values SET
                    metric_id = $mid,
                    period_key = '$pk_esc',
                    actual_value = $act_val,
                    approved_target = $p_target,
                    rolling_target = $p_target,
                    updated_by = $session_user_id
                ON DUPLICATE KEY UPDATE
                    actual_value = VALUES(actual_value),
                    approved_target = VALUES(approved_target),
                    updated_by = VALUES(updated_by)
            ");
        }

        mtgBumpMetricSync($target_sid, $scope);
        mtgJsonOk(['metric_id' => $mid]);
    }

    if ($action === 'metric_save_cell') {
        $mid = intval($_POST['metric_id'] ?? 0);
        $auth = mtgResolveMetricAuth($mid, $session_user_id, $is_admin);
        if (!$auth['can_edit']) {
            mtgJsonError('Only the metric owner, facilitator, or admin can update this table.', 403);
        }
        $m = $auth['metric'];
        $pk = trim((string)($_POST['period_key'] ?? ''));
        if ($pk === '') {
            mtgJsonError('Period key is required.');
        }
        $pk_esc = escapeSql($pk);

        $existing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM meeting_metric_values WHERE metric_id = $mid AND period_key = '$pk_esc' LIMIT 1"));
        $actual_sql = array_key_exists('actual_value', $_POST)
            ? (trim((string)$_POST['actual_value']) === '' ? 'NULL' : floatval($_POST['actual_value']))
            : ($existing && $existing['actual_value'] !== null ? floatval($existing['actual_value']) : 'NULL');
        $rol_t = array_key_exists('rolling_target', $_POST)
            ? floatval($_POST['rolling_target'] ?: 0)
            : ($existing ? floatval($existing['rolling_target']) : 0.0);
        $app_t = array_key_exists('approved_target', $_POST)
            ? floatval($_POST['approved_target'] ?: 0)
            : ($existing ? floatval($existing['approved_target']) : 0.0);
        $note_esc = array_key_exists('status_note', $_POST)
            ? mysqli_real_escape_string($mysqli, (string)$_POST['status_note'])
            : mysqli_real_escape_string($mysqli, (string)($existing['status_note'] ?? ''));
        $color = array_key_exists('status_color', $_POST) && in_array($_POST['status_color'], ['super_green', 'green', 'yellow', 'red', 'gray'], true)
            ? $_POST['status_color']
            : ($existing['status_color'] ?? 'gray');

        mysqli_query($mysqli, "
            INSERT INTO meeting_metric_values SET
                metric_id = $mid,
                period_key = '$pk_esc',
                actual_value = $actual_sql,
                rolling_target = $rol_t,
                approved_target = $app_t,
                status_color = '$color',
                status_note = '$note_esc',
                updated_by = $session_user_id
            ON DUPLICATE KEY UPDATE
                actual_value = VALUES(actual_value),
                rolling_target = VALUES(rolling_target),
                approved_target = VALUES(approved_target),
                status_color = VALUES(status_color),
                status_note = VALUES(status_note),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
        ");

        mtgBumpMetricSync($auth['series_id'], $m['scope']);
        $as_of = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['as_of'] ?? '') ? $_POST['as_of'] : date('Y-m-d');
        $q_key = $_POST['quarter'] ?? null;
        mtgJsonOk(['summary' => meetingComputeMetricSummary($m, $as_of, null, $q_key)]);
    }

    if ($action === 'metric_split_quarter_goal') {
        $mid = intval($_POST['metric_id'] ?? 0);
        $auth = mtgResolveMetricAuth($mid, $session_user_id, $is_admin);
        if (!$auth['can_edit']) {
            mtgJsonError('Permission denied.', 403);
        }
        $m = $auth['metric'];
        $total_goal = floatval($_POST['total_goal'] ?? 0);
        $q_key = $_POST['quarter'] ?? meetingGetQuarterBounds(date('Y-m-d'))['quarter_key'];
        $qb = meetingGetQuarterBounds($q_key);
        $as_of = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['as_of'] ?? '') ? $_POST['as_of'] : date('Y-m-d');
        $intervals = meetingGetQuarterIntervals($qb, $m['frequency'] ?? 'Monthly', $m['week_start'] ?? 'Monday', $as_of);
        $cnt = max(1, count($intervals));
        $mtype = $m['metric_type'] ?? 'Count';
        $start_t = floatval($m['start_target'] ?? 0);

        foreach ($intervals as $idx => $iv) {
            $pk_esc = escapeSql($iv['period_key']);
            if ($mtype === 'Count') {
                $val = round($total_goal / $cnt, 2);
            } elseif ($mtype === 'Measurement') {
                $val = round($start_t + (($total_goal - $start_t) * (($idx + 1) / $cnt)), 2);
            } else {
                $val = $total_goal;
            }
            mysqli_query($mysqli, "
                INSERT INTO meeting_metric_values SET
                    metric_id = $mid,
                    period_key = '$pk_esc',
                    approved_target = $val,
                    rolling_target = $val,
                    updated_by = $session_user_id
                ON DUPLICATE KEY UPDATE
                    approved_target = VALUES(approved_target),
                    rolling_target = VALUES(rolling_target),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()
            ");
        }

        mtgBumpMetricSync($auth['series_id'], $m['scope']);
        mtgJsonOk(['summary' => meetingComputeMetricSummary($m, $as_of, null, $q_key)]);
    }

    if ($action === 'metric_clear_data') {
        $mid = intval($_POST['metric_id'] ?? 0);
        $auth = mtgResolveMetricAuth($mid, $session_user_id, $is_admin);
        if (!$auth['can_edit']) {
            mtgJsonError('Permission denied.', 403);
        }
        $m = $auth['metric'];
        $q_key = $_POST['quarter'] ?? meetingGetQuarterBounds(date('Y-m-d'))['quarter_key'];
        $qb = meetingGetQuarterBounds($q_key);
        $as_of = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['as_of'] ?? '') ? $_POST['as_of'] : date('Y-m-d');
        $intervals = meetingGetQuarterIntervals($qb, $m['frequency'] ?? 'Monthly', $m['week_start'] ?? 'Monday', $as_of);
        $keys = array_map(fn($iv) => "'" . escapeSql($iv['period_key']) . "'", $intervals);
        if (!empty($keys)) {
            mysqli_query($mysqli, "UPDATE meeting_metric_values SET actual_value = NULL, status_color = 'gray' WHERE metric_id = $mid AND period_key IN (" . implode(',', $keys) . ")");
        }
        mtgBumpMetricSync($auth['series_id'], $m['scope']);
        mtgJsonOk(['summary' => meetingComputeMetricSummary($m, $as_of, null, $q_key)]);
    }

    if ($action === 'metric_toggle_star') {
        $mid = intval($_POST['metric_id'] ?? 0);
        $auth = mtgResolveMetricAuth($mid, $session_user_id, $is_admin);
        if (!$auth['can_edit']) {
            mtgJsonError('Permission denied.', 403);
        }
        $new_star = intval($auth['metric']['is_starred']) ? 0 : 1;
        mysqli_query($mysqli, "UPDATE meeting_metrics SET is_starred = $new_star WHERE metric_id = $mid");
        mtgBumpMetricSync($auth['series_id'], $auth['metric']['scope']);
        mtgJsonOk(['is_starred' => $new_star]);
    }

    if ($action === 'metric_cycle_color') {
        $mid = intval($_POST['metric_id'] ?? 0);
        $auth = mtgResolveMetricAuth($mid, $session_user_id, $is_admin);
        if (!$auth['can_edit']) {
            mtgJsonError('Permission denied.', 403);
        }
        $pk = trim((string)($_POST['period_key'] ?? date('Y-m')));
        $pk_esc = escapeSql($pk);
        $v_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT status_color FROM meeting_metric_values WHERE metric_id = $mid AND period_key = '$pk_esc' LIMIT 1"));
        $cur = $v_row['status_color'] ?? 'gray';
        $cycle = ['gray' => 'super_green', 'super_green' => 'green', 'green' => 'yellow', 'yellow' => 'red', 'red' => 'gray'];
        $next = $cycle[$cur] ?? 'green';
        mysqli_query($mysqli, "
            INSERT INTO meeting_metric_values SET metric_id = $mid, period_key = '$pk_esc', status_color = '$next', updated_by = $session_user_id
            ON DUPLICATE KEY UPDATE status_color = '$next', updated_by = $session_user_id, updated_at = NOW()
        ");
        mtgBumpMetricSync($auth['series_id'], $auth['metric']['scope']);
        mtgJsonOk(['status_color' => $next]);
    }

    if ($action === 'metric_delete') {
        $mid = intval($_POST['metric_id'] ?? 0);
        $auth = mtgResolveMetricAuth($mid, $session_user_id, $is_admin);
        if (!$auth['can_edit']) {
            mtgJsonError('Permission denied.', 403);
        }
        mysqli_query($mysqli, "UPDATE meeting_metrics SET archived_at = NOW() WHERE metric_id = $mid");
        mtgBumpMetricSync($auth['series_id'], $auth['metric']['scope']);
        mtgJsonOk();
    }
}

$occ = null;
if ($occurrence_id > 0) {
    $occ = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM meeting_occurrences WHERE occurrence_id = $occurrence_id LIMIT 1"));
    if ($occ) {
        $series_id = intval($occ['series_id']);
    }
}

$series = meetingGetSeries($series_id);
if (!$series) {
    mtgJsonError('Meeting series not found.', 404);
}

if (!meetingCanAccessSeries($series, $session_user_id, $is_admin)) {
    mtgJsonError(WORDING_ROLECHECK_FAILED, 403);
}

$can_edit_any = meetingIsFacilitatorOrAdmin($series, $session_user_id, $is_admin);

if ($action === 'sync') {
    if (!$occ) {
        mtgJsonError('Occurrence not found.', 404);
    }

    $active_item = !empty($_GET['active_item_id']) ? intval($_GET['active_item_id']) : 'NULL';
    mysqli_query($mysqli, "
        INSERT INTO meeting_presence SET
            occurrence_id = $occurrence_id,
            user_id = $session_user_id,
            active_agenda_item_id = $active_item,
            last_seen_at = NOW()
        ON DUPLICATE KEY UPDATE
            active_agenda_item_id = VALUES(active_agenda_item_id),
            last_seen_at = NOW()
    ");

    $since_seq = intval($_GET['since_seq'] ?? -1);
    $force = !empty($_GET['force']);
    $metric_period = $_GET['metric_period'] ?? null;
    $server_seq = intval($occ['sync_seq']);

    if (!$force && $since_seq === $server_seq) {
        $online_users = [];
        $rp = mysqli_query($mysqli, "SELECT user_id FROM meeting_presence WHERE occurrence_id = $occurrence_id AND last_seen_at >= DATE_SUB(NOW(), INTERVAL 20 SECOND)");
        while ($rp && ($pr = mysqli_fetch_assoc($rp))) {
            $online_users[] = intval($pr['user_id']);
        }
        mtgJsonOk([
            'changed' => false,
            'sync_seq' => $server_seq,
            'online_users' => $online_users,
        ]);
    }

    $state = meetingBuildOccurrenceState($occurrence_id, $session_user_id, $is_admin, $metric_period);
    mtgJsonOk([
        'changed' => true,
        'sync_seq' => $server_seq,
        'state' => $state,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mtgJsonError('Invalid request method.', 400);
}

if ($action === 'save_response') {
    if (!$occ) {
        mtgJsonError('Occurrence not found.', 404);
    }
    $agenda_item_id = intval($_POST['agenda_item_id'] ?? 0);
    $target_user_id = intval($_POST['user_id'] ?? $session_user_id);

    if ($target_user_id !== $session_user_id && !$can_edit_any) {
        mtgJsonError('You can only edit your own response row.', 403);
    }

    $ag = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT agenda_item_id FROM meeting_agenda_items WHERE agenda_item_id = $agenda_item_id AND series_id = $series_id LIMIT 1"));
    if (!$ag) {
        mtgJsonError('Agenda item not found.', 404);
    }

    $sanitized_content = meetingSanitizeResponseHtml((string)($_POST['content'] ?? ''));
    $content = mysqli_real_escape_string($mysqli, $sanitized_content);
    mysqli_query($mysqli, "
        INSERT INTO meeting_responses SET
            occurrence_id = $occurrence_id,
            agenda_item_id = $agenda_item_id,
            user_id = $target_user_id,
            content = '$content',
            updated_by = $session_user_id
        ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            updated_by = VALUES(updated_by),
            updated_at = NOW()
    ");

    $new_seq = meetingBumpSyncSeq($occurrence_id);
    mtgJsonOk(['sync_seq' => $new_seq, 'saved_at' => date('g:i:s A'), 'content' => $sanitized_content]);
}

if ($action === 'save_shared_notes') {
    if (!$occ) {
        mtgJsonError('Occurrence not found.', 404);
    }

    $server_ver = intval($occ['shared_notes_version']);
    $expected_ver = intval($_POST['expected_version'] ?? $server_ver);
    $force = !empty($_POST['force_overwrite']);

    if (!$force && $expected_ver !== $server_ver) {
        mtgJsonError('Shared notes were updated by another participant.', 409, [
            'conflict' => true,
            'server_version' => $server_ver,
            'server_content' => (string)($occ['shared_notes'] ?? ''),
        ]);
    }

    $notes = mysqli_real_escape_string($mysqli, (string)($_POST['shared_notes'] ?? ''));
    $new_ver = $server_ver + 1;
    mysqli_query($mysqli, "
        UPDATE meeting_occurrences SET
            shared_notes = '$notes',
            shared_notes_updated_by = $session_user_id,
            shared_notes_version = $new_ver
        WHERE occurrence_id = $occurrence_id
    ");
    $new_seq = meetingBumpSyncSeq($occurrence_id);
    mtgJsonOk(['sync_seq' => $new_seq, 'shared_notes_version' => $new_ver, 'saved_at' => date('g:i:s A')]);
}

if ($action === 'save_personal_notes') {
    if (!$occ) {
        mtgJsonError('Occurrence not found.', 404);
    }
    $notes = mysqli_real_escape_string($mysqli, (string)($_POST['personal_note'] ?? ''));
    mysqli_query($mysqli, "
        INSERT INTO meeting_personal_notes SET
            occurrence_id = $occurrence_id,
            user_id = $session_user_id,
            content = '$notes'
        ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            updated_at = NOW()
    ");
    mtgJsonOk(['saved_at' => date('g:i:s A')]);
}

if ($action === 'update_occurrence_meta') {
    if (!$occ) {
        mtgJsonError('Occurrence not found.', 404);
    }
    $date_ovr = !empty($_POST['date_override']) ? 1 : 0;
    $period_label = escapeSql($_POST['period_label'] ?? $occ['period_label']);
    mysqli_query($mysqli, "UPDATE meeting_occurrences SET date_override = $date_ovr, period_label = '$period_label' WHERE occurrence_id = $occurrence_id");
    $new_seq = meetingBumpSyncSeq($occurrence_id);
    mtgJsonOk(['sync_seq' => $new_seq]);
}

if ($action === 'agenda_save') {
    if (!$can_edit_any) {
        mtgJsonError('Only the facilitator or an admin can edit the agenda.', 403);
    }
    $aid = intval($_POST['agenda_item_id'] ?? 0);
    $title = escapeSql($_POST['title'] ?? '');
    if ($title === '') {
        mtgJsonError('Agenda item title is required.');
    }
    $item_type = in_array($_POST['item_type'] ?? '', ['prompt_per_user', 'metrics', 'action_items', 'discussion'], true)
        ? $_POST['item_type']
        : 'prompt_per_user';
    $icon = escapeSql($_POST['icon'] ?? 'fas fa-comment-dots');

    if ($aid > 0) {
        mysqli_query($mysqli, "UPDATE meeting_agenda_items SET title = '$title', item_type = '$item_type', icon = '$icon' WHERE agenda_item_id = $aid AND series_id = $series_id");
    } else {
        $max_o = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM meeting_agenda_items WHERE series_id = $series_id"))[0]);
        mysqli_query($mysqli, "INSERT INTO meeting_agenda_items SET series_id = $series_id, title = '$title', item_type = '$item_type', icon = '$icon', sort_order = $max_o, is_active = 1");
        $aid = mysqli_insert_id($mysqli);
    }

    meetingBumpSeriesSyncSeq($series_id);
    mtgJsonOk(['agenda_item_id' => $aid]);
}

if ($action === 'agenda_delete') {
    if (!$can_edit_any) {
        mtgJsonError('Only the facilitator or an admin can remove agenda items.', 403);
    }
    $aid = intval($_POST['agenda_item_id'] ?? 0);
    mysqli_query($mysqli, "UPDATE meeting_agenda_items SET is_active = 0 WHERE agenda_item_id = $aid AND series_id = $series_id");
    meetingBumpSeriesSyncSeq($series_id);
    mtgJsonOk();
}

if ($action === 'agenda_reorder') {
    if (!$can_edit_any) {
        mtgJsonError('Only the facilitator or an admin can reorder the agenda.', 403);
    }
    $order = $_POST['order'] ?? [];
    if (is_array($order)) {
        $idx = 1;
        foreach ($order as $aid_raw) {
            $aid = intval($aid_raw);
            if ($aid > 0) {
                mysqli_query($mysqli, "UPDATE meeting_agenda_items SET sort_order = $idx WHERE agenda_item_id = $aid AND series_id = $series_id");
                $idx++;
            }
        }
        meetingBumpSeriesSyncSeq($series_id);
    }
    mtgJsonOk();
}

if ($action === 'attendee_add') {
    if (!$can_edit_any) {
        mtgJsonError('Only the facilitator or an admin can add attendees.', 403);
    }
    $uid = intval($_POST['user_id'] ?? 0);
    $u = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_id FROM users WHERE user_id = $uid AND user_type = 1 AND user_archived_at IS NULL LIMIT 1"));
    if (!$u) {
        mtgJsonError('User not found.');
    }
    $max_o = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM meeting_attendees WHERE series_id = $series_id"))[0]);
    mysqli_query($mysqli, "INSERT IGNORE INTO meeting_attendees SET series_id = $series_id, user_id = $uid, role = 'Member', sort_order = $max_o");
    meetingBumpSeriesSyncSeq($series_id);
    mtgJsonOk();
}

if ($action === 'attendee_remove') {
    if (!$can_edit_any) {
        mtgJsonError('Only the facilitator or an admin can remove attendees.', 403);
    }
    $uid = intval($_POST['user_id'] ?? 0);
    if ($uid === intval($series['facilitator_user_id'])) {
        mtgJsonError('Cannot remove the meeting facilitator.');
    }
    mysqli_query($mysqli, "DELETE FROM meeting_attendees WHERE series_id = $series_id AND user_id = $uid");
    meetingBumpSeriesSyncSeq($series_id);
    mtgJsonOk();
}

if ($action === 'action_save') {
    $aid = intval($_POST['action_id'] ?? 0);
    $desc = mysqli_real_escape_string($mysqli, trim((string)($_POST['description'] ?? '')));
    if ($desc === '') {
        mtgJsonError('Action item description is required.');
    }
    $owner_uid = intval($_POST['owner_user_id'] ?? $session_user_id);
    $due = !empty($_POST['due_date']) ? "'" . escapeSql($_POST['due_date']) . "'" : 'NULL';
    $prio = in_array($_POST['priority'] ?? '', ['High', 'Medium', 'Low'], true) ? $_POST['priority'] : 'Medium';
    $status = in_array($_POST['status'] ?? '', ['Open', 'In Progress', 'Completed'], true) ? $_POST['status'] : 'Open';
    $comp_occ = ($status === 'Completed' && $occurrence_id > 0) ? $occurrence_id : 'NULL';

    if ($aid > 0) {
        $existing_act = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT owner_user_id, created_by FROM meeting_action_items WHERE action_id = $aid AND series_id = $series_id LIMIT 1"));
        if (!$existing_act) {
            mtgJsonError('Action item not found.', 404);
        }
        if (!$can_edit_any && intval($existing_act['owner_user_id']) !== $session_user_id && intval($existing_act['created_by']) !== $session_user_id) {
            mtgJsonError('Permission denied.', 403);
        }
        mysqli_query($mysqli, "
            UPDATE meeting_action_items SET
                owner_user_id = $owner_uid,
                description = '$desc',
                due_date = $due,
                priority = '$prio',
                status = '$status',
                completed_occurrence_id = $comp_occ
            WHERE action_id = $aid AND series_id = $series_id
        ");
    } else {
        $orig_occ = $occurrence_id > 0 ? $occurrence_id : 'NULL';
        mysqli_query($mysqli, "
            INSERT INTO meeting_action_items SET
                series_id = $series_id,
                origin_occurrence_id = $orig_occ,
                completed_occurrence_id = $comp_occ,
                owner_user_id = $owner_uid,
                description = '$desc',
                due_date = $due,
                priority = '$prio',
                status = '$status',
                created_by = $session_user_id
        ");
        $aid = mysqli_insert_id($mysqli);
    }

    meetingBumpSeriesSyncSeq($series_id);
    mtgJsonOk(['action_id' => $aid]);
}

if ($action === 'action_toggle') {
    $aid = intval($_POST['action_id'] ?? 0);
    $existing_act = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT owner_user_id, created_by, status FROM meeting_action_items WHERE action_id = $aid AND series_id = $series_id LIMIT 1"));
    if (!$existing_act) {
        mtgJsonError('Action item not found.', 404);
    }
    if (!$can_edit_any && intval($existing_act['owner_user_id']) !== $session_user_id && intval($existing_act['created_by']) !== $session_user_id) {
        mtgJsonError('Only the action owner, creator, facilitator, or admin can toggle this item.', 403);
    }
    $new_status = ($existing_act['status'] === 'Completed') ? 'Open' : 'Completed';
    $comp_occ = ($new_status === 'Completed' && $occurrence_id > 0) ? $occurrence_id : 'NULL';
    mysqli_query($mysqli, "UPDATE meeting_action_items SET status = '$new_status', completed_occurrence_id = $comp_occ WHERE action_id = $aid");
    meetingBumpSeriesSyncSeq($series_id);
    mtgJsonOk(['status' => $new_status]);
}

if ($action === 'action_delete') {
    $aid = intval($_POST['action_id'] ?? 0);
    $existing_act = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT owner_user_id, created_by FROM meeting_action_items WHERE action_id = $aid AND series_id = $series_id LIMIT 1"));
    if (!$existing_act) {
        mtgJsonError('Action item not found.', 404);
    }
    if (!$can_edit_any && intval($existing_act['owner_user_id']) !== $session_user_id && intval($existing_act['created_by']) !== $session_user_id) {
        mtgJsonError('Permission denied.', 403);
    }
    mysqli_query($mysqli, "DELETE FROM meeting_action_items WHERE action_id = $aid AND series_id = $series_id");
    meetingBumpSeriesSyncSeq($series_id);
    mtgJsonOk();
}

if ($action === 'attachment_add') {
    $title = escapeSql(trim((string)($_POST['title'] ?? '')));
    $ext_url = trim((string)($_POST['external_url'] ?? ''));
    $occ_sql = $occurrence_id > 0 ? $occurrence_id : 'NULL';
    $file_name = null;
    $file_path = null;

    if (!empty($_FILES['file']['name'])) {
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'zip', 'pptx'];
        $stored = checkFileUpload($_FILES['file'], $allowed);
        if (!$stored) {
            mtgJsonError('Invalid or disallowed file upload.');
        }
        $dir_rel = "uploads/meetings/$series_id";
        $dir_abs = __DIR__ . '/../' . $dir_rel;
        if (!is_dir($dir_abs)) {
            mkdir($dir_abs, 0775, true);
        }
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir_abs . '/' . $stored)) {
            mtgJsonError('Failed to save uploaded file.');
        }
        $file_name = escapeSql(basename($_FILES['file']['name']));
        $file_path = escapeSql($dir_rel . '/' . $stored);
        if ($title === '') {
            $title = $file_name;
        }
    } elseif ($ext_url !== '') {
        if (!preg_match('#^https?://#i', $ext_url)) {
            mtgJsonError('External link must begin with http:// or https://');
        }
        if ($title === '') {
            $title = escapeSql($ext_url);
        }
    } else {
        mtgJsonError('Please upload a file or provide a URL.');
    }

    $fn_sql = $file_name ? "'$file_name'" : 'NULL';
    $fp_sql = $file_path ? "'$file_path'" : 'NULL';
    $url_sql = $ext_url !== '' ? "'" . escapeSql($ext_url) . "'" : 'NULL';

    mysqli_query($mysqli, "
        INSERT INTO meeting_attachments SET
            series_id = $series_id,
            occurrence_id = $occ_sql,
            title = '$title',
            file_name = $fn_sql,
            file_path = $fp_sql,
            external_url = $url_sql,
            uploaded_by = $session_user_id
    ");

    meetingBumpSeriesSyncSeq($series_id);
    mtgJsonOk(['attachment_id' => mysqli_insert_id($mysqli)]);
}

if ($action === 'attachment_delete') {
    $atid = intval($_POST['attachment_id'] ?? 0);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM meeting_attachments WHERE attachment_id = $atid AND series_id = $series_id LIMIT 1"));
    if (!$row) {
        mtgJsonError('Attachment not found.', 404);
    }
    if (!$can_edit_any && intval($row['uploaded_by']) !== $session_user_id) {
        mtgJsonError('Permission denied.', 403);
    }
    if (!empty($row['file_path'])) {
        $base = realpath(__DIR__ . '/../uploads/meetings');
        $abs = realpath(__DIR__ . '/../' . $row['file_path']);
        if ($base && $abs && strpos($abs, $base) === 0 && is_file($abs)) {
            @unlink($abs);
        }
    }
    mysqli_query($mysqli, "DELETE FROM meeting_attachments WHERE attachment_id = $atid");
    meetingBumpSeriesSyncSeq($series_id);
    mtgJsonOk();
}

mtgJsonError('Unknown action.');
