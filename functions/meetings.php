<?php

function meetingUserAvatarData(int $user_id, string $user_name, ?string $user_avatar): array {
    $avatar_url = null;
    if (!empty($user_avatar)) {
        $rel = "/uploads/users/$user_id/" . rawurlencode($user_avatar);
        $abs = __DIR__ . "/../uploads/users/$user_id/" . basename($user_avatar);
        if (is_file($abs)) {
            $avatar_url = $rel;
        }
    }

    $palette = ['#0f766e', '#1d4ed8', '#b91c1c', '#7c3aed', '#c2410c', '#0369a1', '#4d7c0f', '#be185d'];
    $color = $palette[$user_id % count($palette)];
    $init = function_exists('initials') ? initials($user_name) : strtoupper(substr($user_name, 0, 2));

    return [
        'avatar_url' => $avatar_url,
        'initials' => $init ?: 'U',
        'color' => $color,
    ];
}

function meetingGetSeries(int $series_id): ?array {
    global $mysqli;

    $series_id = intval($series_id);
    if ($series_id <= 0) {
        return null;
    }

    $sql = mysqli_query($mysqli, "
        SELECT s.*, u.user_name AS facilitator_name, u.user_email AS facilitator_email, u.user_avatar AS facilitator_avatar
        FROM meeting_series s
        LEFT JOIN users u ON s.facilitator_user_id = u.user_id
        WHERE s.series_id = $series_id AND s.archived_at IS NULL
        LIMIT 1
    ");

    if ($sql && mysqli_num_rows($sql) > 0) {
        return mysqli_fetch_assoc($sql);
    }

    return null;
}

function meetingCanAccessSeries(array $series, int $user_id, bool $is_admin = false): bool {
    global $mysqli;

    if ($is_admin) {
        return true;
    }

    if ($user_id <= 0) {
        return false;
    }

    if (($series['visibility'] ?? 'Attendees Only') === 'Company-Wide') {
        return true;
    }

    if (intval($series['facilitator_user_id'] ?? 0) === $user_id) {
        return true;
    }

    $sid = intval($series['series_id']);
    $chk = mysqli_query($mysqli, "SELECT attendee_id FROM meeting_attendees WHERE series_id = $sid AND user_id = $user_id LIMIT 1");
    return ($chk && mysqli_num_rows($chk) > 0);
}

function meetingIsFacilitatorOrAdmin(array $series, int $user_id, bool $is_admin = false): bool {
    global $mysqli;

    if ($is_admin) {
        return true;
    }

    if (intval($series['facilitator_user_id'] ?? 0) === $user_id) {
        return true;
    }

    $sid = intval($series['series_id']);
    $chk = mysqli_query($mysqli, "SELECT attendee_id FROM meeting_attendees WHERE series_id = $sid AND user_id = $user_id AND role = 'Facilitator' LIMIT 1");
    return ($chk && mysqli_num_rows($chk) > 0);
}

function meetingListAccessibleSeries(int $user_id, bool $is_admin = false): array {
    global $mysqli;

    $user_id = intval($user_id);
    $where_vis = $is_admin ? "1=1" : "(s.visibility = 'Company-Wide' OR s.facilitator_user_id = $user_id OR EXISTS (SELECT 1 FROM meeting_attendees ma WHERE ma.series_id = s.series_id AND ma.user_id = $user_id))";

    $sql = mysqli_query($mysqli, "
        SELECT s.*,
               u.user_name AS facilitator_name,
               u.user_avatar AS facilitator_avatar,
               (SELECT COUNT(*) FROM meeting_attendees ma2 WHERE ma2.series_id = s.series_id) AS attendee_count,
               (SELECT COUNT(*) FROM meeting_action_items ai WHERE ai.series_id = s.series_id AND ai.status != 'Completed') AS open_actions_count
        FROM meeting_series s
        LEFT JOIN users u ON s.facilitator_user_id = u.user_id
        WHERE s.archived_at IS NULL AND $where_vis
        ORDER BY s.series_name ASC
    ");

    $list = [];
    while ($sql && ($row = mysqli_fetch_assoc($sql))) {
        $sid = intval($row['series_id']);
        $atts = [];
        $ra = mysqli_query($mysqli, "
            SELECT ma.user_id, ma.role, us.user_name, us.user_avatar
            FROM meeting_attendees ma
            JOIN users us ON ma.user_id = us.user_id
            WHERE ma.series_id = $sid AND us.user_archived_at IS NULL
            ORDER BY ma.sort_order ASC, us.user_name ASC
        ");
        while ($ra && ($ar = mysqli_fetch_assoc($ra))) {
            $av = meetingUserAvatarData(intval($ar['user_id']), $ar['user_name'], $ar['user_avatar']);
            $ar['avatar_url'] = $av['avatar_url'];
            $ar['initials'] = $av['initials'];
            $ar['color'] = $av['color'];
            $atts[] = $ar;
        }
        $row['attendees'] = $atts;
        $row['default_date'] = meetingGetDefaultOccurrenceDate($row);
        $list[] = $row;
    }

    return $list;
}

function meetingFormatPeriodLabel(string $date, string $period_type = 'Quarterly'): string {
    $ts = strtotime($date) ?: time();
    $year = intval(date('Y', $ts));
    $month = intval(date('n', $ts));

    if ($period_type === 'Monthly') {
        return date('M Y', $ts) . ' (' . date('M 1', $ts) . ' - ' . date('M t', $ts) . ')';
    }
    if ($period_type === 'Annual') {
        return "FY{$year} (Jan 1 - Dec 31)";
    }

    $q = intval(ceil($month / 3));
    $q_ranges = [
        1 => 'Jan 1 - Mar 31',
        2 => 'Apr 1 - Jun 30',
        3 => 'Jul 1 - Sep 30',
        4 => 'Oct 1 - Dec 31',
    ];
    return "FY{$year}-Q{$q} (" . $q_ranges[$q] . ")";
}

function meetingGetPeriodOptions(string $center_date): array {
    $ts = strtotime($center_date) ?: time();
    $options = [];
    for ($offset = -2; $offset <= 2; $offset++) {
        $d = date('Y-m-d', strtotime(($offset >= 0 ? "+$offset" : "$offset") . " quarters", $ts));
        $lbl = meetingFormatPeriodLabel($d, 'Quarterly');
        if (!in_array($lbl, $options, true)) {
            $options[] = $lbl;
        }
    }
    return $options;
}

function meetingCalculateStepDate(array $series, string $current_date, int $direction): string {
    $dir = $direction >= 0 ? 1 : -1;
    $ts = strtotime($current_date) ?: time();
    $type = $series['recurrence_type'] ?? 'Daily';

    if ($type === 'One-Time') {
        return date('Y-m-d', strtotime(($dir > 0 ? '+1 day' : '-1 day'), $ts));
    }
    if ($type === 'Daily') {
        return date('Y-m-d', strtotime(($dir > 0 ? '+1 day' : '-1 day'), $ts));
    }
    if ($type === 'Weekdays') {
        $probe = $ts;
        do {
            $probe = strtotime(($dir > 0 ? '+1 day' : '-1 day'), $probe);
            $dow = intval(date('N', $probe));
        } while ($dow >= 6);
        return date('Y-m-d', $probe);
    }
    if ($type === 'Weekly') {
        $days_str = trim((string)($series['recurrence_days'] ?? ''));
        if ($days_str !== '') {
            $map = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7];
            $allowed = [];
            foreach (explode(',', $days_str) as $d) {
                $d = trim($d);
                if (isset($map[$d])) {
                    $allowed[] = $map[$d];
                }
            }
            if (!empty($allowed)) {
                $probe = $ts;
                for ($i = 0; $i < 14; $i++) {
                    $probe = strtotime(($dir > 0 ? '+1 day' : '-1 day'), $probe);
                    if (in_array(intval(date('N', $probe)), $allowed, true)) {
                        return date('Y-m-d', $probe);
                    }
                }
            }
        }
        return date('Y-m-d', strtotime(($dir > 0 ? '+7 days' : '-7 days'), $ts));
    }
    if ($type === 'Bi-Weekly') {
        return date('Y-m-d', strtotime(($dir > 0 ? '+14 days' : '-14 days'), $ts));
    }
    if ($type === 'Monthly') {
        return date('Y-m-d', strtotime(($dir > 0 ? '+1 month' : '-1 month'), $ts));
    }
    if ($type === 'Quarterly') {
        return date('Y-m-d', strtotime(($dir > 0 ? '+3 months' : '-3 months'), $ts));
    }

    return date('Y-m-d', strtotime(($dir > 0 ? '+1 day' : '-1 day'), $ts));
}

function meetingGetDefaultOccurrenceDate(array $series): string {
    $today = date('Y-m-d');
    $type = $series['recurrence_type'] ?? 'Daily';
    $start = $series['start_date'] ?? $today;

    if ($type === 'One-Time') {
        return $start;
    }
    if ($type === 'Weekdays') {
        $dow = intval(date('N', strtotime($today)));
        if ($dow === 6) {
            return date('Y-m-d', strtotime('-1 day', strtotime($today)));
        }
        if ($dow === 7) {
            return date('Y-m-d', strtotime('-2 days', strtotime($today)));
        }
    }
    return $today;
}

function meetingGetOrCreateOccurrence(int $series_id, string $date, bool $date_override = false): array {
    global $mysqli;

    $series_id = intval($series_id);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    $date_esc = escapeSql($date);
    $res = mysqli_query($mysqli, "SELECT * FROM meeting_occurrences WHERE series_id = $series_id AND occurrence_date = '$date_esc' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        return mysqli_fetch_assoc($res);
    }

    $series = meetingGetSeries($series_id);
    $period_type = $series['default_period_type'] ?? 'Quarterly';
    $period_label = escapeSql(meetingFormatPeriodLabel($date, $period_type));
    $ovr = $date_override ? 1 : 0;

    mysqli_query($mysqli, "
        INSERT IGNORE INTO meeting_occurrences SET
            series_id = $series_id,
            occurrence_date = '$date_esc',
            date_override = $ovr,
            period_label = '$period_label',
            status = 'In Progress',
            sync_seq = 1
    ");

    $res2 = mysqli_query($mysqli, "SELECT * FROM meeting_occurrences WHERE series_id = $series_id AND occurrence_date = '$date_esc' LIMIT 1");
    return ($res2 && mysqli_num_rows($res2) > 0) ? mysqli_fetch_assoc($res2) : [];
}

function meetingSeedDefaultAgenda(int $series_id, string $preset = 'daily_huddle'): void {
    global $mysqli;

    $series_id = intval($series_id);
    $chk = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM meeting_agenda_items WHERE series_id = $series_id"));
    if (intval($chk[0] ?? 0) > 0) {
        return;
    }

    $presets = [
        'daily_huddle' => [
            ['Good News', 'prompt_per_user', 'fas fa-comment-dots'],
            ['Metrics Review', 'metrics', 'fas fa-chart-line'],
            ['#1 Thing to Get Done Today', 'prompt_per_user', 'fas fa-bullseye'],
            ['Stucks', 'prompt_per_user', 'fas fa-exclamation-triangle'],
            ['Action Items', 'action_items', 'fas fa-bell'],
        ],
        'weekly_leadership' => [
            ['Good News', 'prompt_per_user', 'fas fa-comment-dots'],
            ['Metrics Review', 'metrics', 'fas fa-chart-line'],
            ['Top Weekly Priorities', 'prompt_per_user', 'fas fa-bullseye'],
            ['Stucks & Roadblocks', 'prompt_per_user', 'fas fa-exclamation-triangle'],
            ['Action Items', 'action_items', 'fas fa-bell'],
        ],
        'monthly_review' => [
            ['Monthly Wins', 'prompt_per_user', 'fas fa-trophy'],
            ['Metrics Review', 'metrics', 'fas fa-chart-line'],
            ['Key Learnings & Roadblocks', 'prompt_per_user', 'fas fa-lightbulb'],
            ['Strategic Discussion', 'discussion', 'fas fa-comments'],
            ['Action Items', 'action_items', 'fas fa-bell'],
        ],
        'blank' => [
            ['Good News', 'prompt_per_user', 'fas fa-comment-dots'],
            ['Action Items', 'action_items', 'fas fa-bell'],
        ],
    ];

    $items = $presets[$preset] ?? $presets['daily_huddle'];
    $order = 1;
    foreach ($items as [$title, $type, $icon]) {
        $t = escapeSql($title);
        $tp = escapeSql($type);
        $ic = escapeSql($icon);
        mysqli_query($mysqli, "
            INSERT INTO meeting_agenda_items SET
                series_id = $series_id,
                title = '$t',
                item_type = '$tp',
                icon = '$ic',
                sort_order = $order,
                is_active = 1
        ");
        $order++;
    }
}

function meetingBumpSyncSeq(int $occurrence_id): int {
    global $mysqli;

    $occurrence_id = intval($occurrence_id);
    if ($occurrence_id <= 0) {
        return 0;
    }

    mysqli_query($mysqli, "
        UPDATE meeting_occurrences
        SET sync_seq = LAST_INSERT_ID(sync_seq + 1)
        WHERE occurrence_id = $occurrence_id
    ");
    return intval(mysqli_insert_id($mysqli));
}

function meetingBumpSeriesSyncSeq(int $series_id): void {
    global $mysqli;

    $series_id = intval($series_id);
    if ($series_id <= 0) {
        return;
    }

    mysqli_query($mysqli, "UPDATE meeting_occurrences SET sync_seq = sync_seq + 1 WHERE series_id = $series_id");
}

function meetingGetQuarterBounds(string $date_or_quarter): array {
    $date_or_quarter = trim($date_or_quarter);
    $year = intval(date('Y'));
    $q = intval(ceil(intval(date('n')) / 3));

    if (preg_match('/^(?:FY)?(\d{4})-Q([1-4])$/i', $date_or_quarter, $m)) {
        $year = intval($m[1]);
        $q = intval($m[2]);
    } elseif (preg_match('/^(\d{4})-(\d{2})/', $date_or_quarter, $m)) {
        $year = intval($m[1]);
        $q = intval(ceil(intval($m[2]) / 3));
    }

    $start_month = (($q - 1) * 3) + 1;
    $start_date = sprintf('%04d-%02d-01', $year, $start_month);
    $end_month = $start_month + 2;
    $end_date = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $end_month)));
    $months = [
        sprintf('%04d-%02d', $year, $start_month),
        sprintf('%04d-%02d', $year, $start_month + 1),
        sprintf('%04d-%02d', $year, $start_month + 2),
    ];

    $q_ranges = [
        1 => 'Jan 1 - Mar 31',
        2 => 'Apr 1 - Jun 30',
        3 => 'Jul 1 - Sep 30',
        4 => 'Oct 1 - Dec 31',
    ];

    return [
        'year' => $year,
        'quarter' => $q,
        'quarter_key' => sprintf('%04d-Q%d', $year, $q),
        'label' => sprintf('FY%04d-Q%d', $year, $q),
        'range_label' => $q_ranges[$q],
        'start_date' => $start_date,
        'end_date' => $end_date,
        'months' => $months,
    ];
}

function meetingStepQuarterKey(string $quarter_key, int $delta): string {
    $qb = meetingGetQuarterBounds($quarter_key);
    $idx = ($qb['year'] * 4) + ($qb['quarter'] - 1) + $delta;
    $new_year = intdiv($idx, 4);
    $new_q = ($idx % 4);
    if ($new_q < 0) {
        $new_q += 4;
        $new_year--;
    }
    return sprintf('%04d-Q%d', $new_year, $new_q + 1);
}

function meetingCountWorkDays(string $start_date, string $end_date, string $work_days_csv = 'Mon,Tue,Wed,Thu,Fri'): int {
    $start_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);
    if ($start_ts === false || $end_ts === false || $end_ts < $start_ts) {
        return 0;
    }

    $allowed = array_flip(array_filter(array_map('trim', explode(',', $work_days_csv))));
    if (empty($allowed)) {
        $allowed = ['Mon' => 1, 'Tue' => 1, 'Wed' => 1, 'Thu' => 1, 'Fri' => 1];
    }

    $count = 0;
    for ($ts = $start_ts; $ts <= $end_ts; $ts += 86400) {
        if (isset($allowed[date('D', $ts)])) {
            $count++;
        }
    }
    return $count;
}

function meetingGetQuarterIntervals(array $quarter_bounds, string $frequency = 'Monthly', string $week_start = 'Monday', ?string $focus_date = null): array {
    $intervals = [];

    if ($frequency === 'Weekly') {
        $start_ts = strtotime($quarter_bounds['start_date']);
        $end_ts = strtotime($quarter_bounds['end_date']);
        $target_dow = ($week_start === 'Sunday') ? 0 : 1;
        $cur_dow = intval(date('w', $start_ts));
        $diff = ($cur_dow - $target_dow + 7) % 7;
        $cursor = strtotime("-$diff days", $start_ts);
        while ($cursor <= $end_ts) {
            $w_end = strtotime('+6 days', $cursor);
            $w_start_str = date('Y-m-d', $cursor);
            $w_end_str = date('Y-m-d', $w_end);
            $key = date('o-\WW', $cursor);
            $intervals[] = [
                'period_key' => $key,
                'label' => 'Week of ' . date('M j, Y', $cursor),
                'short_label' => date('M j', $cursor),
                'start_date' => max($w_start_str, $quarter_bounds['start_date']),
                'end_date' => min($w_end_str, $quarter_bounds['end_date']),
            ];
            $cursor = strtotime('+7 days', $cursor);
        }
        return $intervals;
    }

    if ($frequency === 'Daily') {
        $ref = ($focus_date && $focus_date >= $quarter_bounds['start_date'] && $focus_date <= $quarter_bounds['end_date'])
            ? $focus_date
            : $quarter_bounds['start_date'];
        $m_start = date('Y-m-01', strtotime($ref));
        $m_end = date('Y-m-t', strtotime($ref));
        $start_ts = strtotime($m_start);
        $end_ts = strtotime($m_end);
        for ($ts = $start_ts; $ts <= $end_ts; $ts += 86400) {
            $d = date('Y-m-d', $ts);
            $intervals[] = [
                'period_key' => $d,
                'label' => date('D, M j, Y', $ts),
                'short_label' => date('M j', $ts),
                'start_date' => $d,
                'end_date' => $d,
            ];
        }
        return $intervals;
    }

    foreach ($quarter_bounds['months'] as $ym) {
        $ts = strtotime($ym . '-01');
        $intervals[] = [
            'period_key' => $ym,
            'label' => date('F Y', $ts),
            'short_label' => date('M', $ts),
            'start_date' => date('Y-m-01', $ts),
            'end_date' => date('Y-m-t', $ts),
        ];
    }

    return $intervals;
}

function meetingComputeAutoStatus(float $actual, float $benchmark, string $direction = 'HigherBetter', float $super_green_pct = 120.0, float $yellow_pct = 80.0): string {
    if ($benchmark <= 0) {
        return 'gray';
    }

    if ($direction === 'LowerBetter') {
        $sg_mult = 100.0 / max(100.0, $super_green_pct);
        $y_mult = (200.0 - min(99.0, max(1.0, $yellow_pct))) / 100.0;
        if ($actual <= $benchmark * $sg_mult) {
            return 'super_green';
        }
        if ($actual <= $benchmark) {
            return 'green';
        }
        if ($actual <= $benchmark * $y_mult) {
            return 'yellow';
        }
        return 'red';
    }

    $pct = ($actual / $benchmark) * 100.0;
    if ($pct >= $super_green_pct) {
        return 'super_green';
    }
    if ($pct >= 100.0) {
        return 'green';
    }
    if ($pct >= $yellow_pct) {
        return 'yellow';
    }
    return 'red';
}

function meetingComputeMetricStatus(float $actual, float $target, string $direction = 'HigherBetter'): string {
    return meetingComputeAutoStatus($actual, $target, $direction);
}

function meetingComputeMetricSummary(array $metric, string $as_of_date, ?string $selected_interval_key = null, ?string $quarter_key = null): array {
    global $mysqli;

    $mid = intval($metric['metric_id']);
    $mtype = $metric['metric_type'] ?? 'Count';
    $freq = $metric['frequency'] ?? 'Monthly';
    $work_days = $metric['work_days'] ?? 'Mon,Tue,Wed,Thu,Fri';
    $week_start = $metric['week_start'] ?? 'Monday';
    $direction = $metric['goal_direction'] ?? 'HigherBetter';
    $start_target = floatval($metric['start_target'] ?? 0);
    $auto_status = !isset($metric['auto_set_status']) || !empty($metric['auto_set_status']);
    $sg_pct = floatval($metric['super_green_pct'] ?? 120.0);
    $y_pct = floatval($metric['yellow_pct'] ?? 80.0);

    $qb = meetingGetQuarterBounds($quarter_key ?: $as_of_date);
    $intervals = meetingGetQuarterIntervals($qb, $freq, $week_start, $as_of_date);

    $keys = array_map(fn($iv) => "'" . escapeSql($iv['period_key']) . "'", $intervals);
    $vals_by_key = [];
    if (!empty($keys)) {
        $res_v = mysqli_query($mysqli, "SELECT * FROM meeting_metric_values WHERE metric_id = $mid AND period_key IN (" . implode(',', $keys) . ")");
        while ($res_v && ($vr = mysqli_fetch_assoc($res_v))) {
            $vals_by_key[$vr['period_key']] = $vr;
        }
    }

    $total_work_days = max(1, meetingCountWorkDays($qb['start_date'], $qb['end_date'], $work_days));
    if ($as_of_date < $qb['start_date']) {
        $elapsed_work_days = 0;
    } elseif ($as_of_date > $qb['end_date']) {
        $elapsed_work_days = $total_work_days;
    } else {
        $elapsed_work_days = meetingCountWorkDays($qb['start_date'], $as_of_date, $work_days);
    }
    $pace_ratio = $elapsed_work_days / $total_work_days;

    $rows = [];
    $running_actual = 0.0;
    $running_target = 0.0;
    $has_any_actual = false;
    $latest_actual = null;
    $last_nonzero_target = 0.0;
    $all_targets_zero = true;

    foreach ($intervals as $iv) {
        $pk = $iv['period_key'];
        $vr = $vals_by_key[$pk] ?? null;
        $actual = ($vr && $vr['actual_value'] !== null) ? floatval($vr['actual_value']) : null;
        $app_t = ($vr && isset($vr['approved_target'])) ? floatval($vr['approved_target']) : 0.0;
        $rol_t = ($vr && isset($vr['rolling_target'])) ? floatval($vr['rolling_target']) : 0.0;
        $eff_t = $app_t > 0 ? $app_t : $rol_t;
        if ($eff_t > 0) {
            $all_targets_zero = false;
            $last_nonzero_target = $eff_t;
        }
        if ($actual !== null) {
            $has_any_actual = true;
            $latest_actual = $actual;
        }

        if ($mtype === 'Count') {
            if ($actual !== null) {
                $running_actual += $actual;
            }
            $running_target += $eff_t;
            $ptd_actual = $has_any_actual ? $running_actual : null;
            $ptd_target = $running_target;
        } elseif ($mtype === 'Measurement') {
            $ptd_actual = $latest_actual;
            $ptd_target = $eff_t > 0 ? $eff_t : $last_nonzero_target;
        } else {
            $ptd_actual = $actual !== null ? $actual : $latest_actual;
            $ptd_target = $eff_t > 0 ? $eff_t : $last_nonzero_target;
        }

        if ($auto_status) {
            $row_color = ($actual !== null && $eff_t > 0)
                ? meetingComputeAutoStatus($actual, $eff_t, $direction, $sg_pct, $y_pct)
                : 'gray';
        } else {
            $row_color = $vr['status_color'] ?? 'gray';
        }

        $rows[] = [
            'period_key' => $pk,
            'label' => $iv['label'],
            'short_label' => $iv['short_label'],
            'start_date' => $iv['start_date'],
            'end_date' => $iv['end_date'],
            'actual_value' => $actual,
            'rolling_target' => $rol_t,
            'approved_target' => $app_t,
            'effective_target' => $eff_t,
            'period_to_date_actual' => $ptd_actual,
            'period_to_date_target' => $ptd_target,
            'status_color' => $row_color,
            'status_note' => $vr['status_note'] ?? '',
        ];
    }

    $fallback_period_target = floatval($metric['period_target'] ?? 0);
    if ($mtype === 'Count') {
        $to_date_actual = $has_any_actual ? round($running_actual, 2) : null;
        $to_date_target = $all_targets_zero && $fallback_period_target > 0
            ? round($fallback_period_target * count($intervals), 2)
            : round($running_target, 2);
        $target_pace = round($to_date_target * $pace_ratio, 2);
    } elseif ($mtype === 'Measurement') {
        $to_date_actual = $latest_actual !== null ? round($latest_actual, 2) : null;
        $to_date_target = $all_targets_zero && $fallback_period_target > 0
            ? round($fallback_period_target, 2)
            : round($last_nonzero_target, 2);
        $target_pace = round($start_target + (($to_date_target - $start_target) * $pace_ratio), 2);
    } else {
        $to_date_actual = $latest_actual !== null ? round($latest_actual, 2) : null;
        $to_date_target = $all_targets_zero && $fallback_period_target > 0
            ? round($fallback_period_target, 2)
            : round($last_nonzero_target, 2);
        $target_pace = $to_date_target;
    }

    $active_row = null;
    if (!empty($selected_interval_key)) {
        foreach ($rows as $r) {
            if ($r['period_key'] === $selected_interval_key) {
                $active_row = $r;
                break;
            }
        }
    }
    if (!$active_row) {
        $match_key = substr($as_of_date, 0, 7);
        if ($freq === 'Weekly') {
            $match_key = date('o-\WW', strtotime($as_of_date));
        } elseif ($freq === 'Daily') {
            $match_key = $as_of_date;
        }
        foreach ($rows as $r) {
            if ($r['period_key'] === $match_key) {
                $active_row = $r;
                break;
            }
        }
    }
    if (!$active_row && !empty($rows)) {
        $active_row = $rows[0];
    }

    $interval_actual = $active_row ? $active_row['actual_value'] : null;
    $interval_target = $active_row
        ? ($active_row['effective_target'] > 0 ? $active_row['effective_target'] : $fallback_period_target)
        : $fallback_period_target;

    if ($auto_status) {
        $overall_color = ($to_date_actual !== null && $target_pace > 0)
            ? meetingComputeAutoStatus($to_date_actual, $target_pace, $direction, $sg_pct, $y_pct)
            : (($interval_actual !== null && $interval_target > 0)
                ? meetingComputeAutoStatus($interval_actual, $interval_target, $direction, $sg_pct, $y_pct)
                : 'gray');
    } else {
        $overall_color = $active_row['status_color'] ?? 'gray';
    }

    return [
        'quarter' => $qb,
        'elapsed_work_days' => $elapsed_work_days,
        'total_work_days' => $total_work_days,
        'interval_key' => $active_row['period_key'] ?? '',
        'interval_label' => $active_row['label'] ?? '',
        'interval_actual' => $interval_actual,
        'interval_target' => $interval_target,
        'to_date_actual' => $to_date_actual,
        'to_date_target' => $to_date_target,
        'target_pace' => $target_pace,
        'status_color' => $overall_color,
        'rows' => $rows,
    ];
}

function meetingListAccessibleMetrics(int $user_id, bool $is_admin = false, string $quarter_key = '', string $as_of_date = ''): array {
    global $mysqli;

    if ($as_of_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $as_of_date)) {
        $as_of_date = date('Y-m-d');
    }
    if ($quarter_key === '') {
        $quarter_key = meetingGetQuarterBounds($as_of_date)['quarter_key'];
    }

    $user_id = intval($user_id);
    $where_access = $is_admin
        ? "1=1"
        : "(m.owner_user_id = $user_id OR m.privacy = 'Public' OR m.series_id IN (SELECT s.series_id FROM meeting_series s LEFT JOIN meeting_attendees ma ON s.series_id = ma.series_id WHERE s.archived_at IS NULL AND (s.visibility = 'Company-Wide' OR s.facilitator_user_id = $user_id OR ma.user_id = $user_id)))";

    $sql = mysqli_query($mysqli, "
        SELECT m.*, u.user_name AS owner_name, u.user_avatar AS owner_avatar, s.series_name
        FROM meeting_metrics m
        LEFT JOIN users u ON m.owner_user_id = u.user_id
        LEFT JOIN meeting_series s ON m.series_id = s.series_id
        WHERE m.archived_at IS NULL AND $where_access
        ORDER BY m.is_starred DESC, m.sort_order ASC, m.metric_id ASC
    ");

    $list = [];
    while ($sql && ($mt = mysqli_fetch_assoc($sql))) {
        $ouid = intval($mt['owner_user_id']);
        $oav = meetingUserAvatarData($ouid, $mt['owner_name'] ?: 'User', $mt['owner_avatar'] ?? null);
        $summary = meetingComputeMetricSummary($mt, $as_of_date, null, $quarter_key);
        $list[] = [
            'metric_id' => intval($mt['metric_id']),
            'series_id' => $mt['series_id'] !== null ? intval($mt['series_id']) : null,
            'series_name' => $mt['series_name'] ?: 'Company / Standalone',
            'owner_user_id' => $ouid,
            'owner_name' => $mt['owner_name'] ?: 'Unassigned',
            'owner_avatar_url' => $oav['avatar_url'],
            'owner_initials' => $oav['initials'],
            'owner_color' => $oav['color'],
            'metric_name' => $mt['metric_name'],
            'metric_type' => $mt['metric_type'] ?? 'Count',
            'frequency' => $mt['frequency'] ?? 'Monthly',
            'work_days' => $mt['work_days'] ?? 'Mon,Tue,Wed,Thu,Fri',
            'week_start' => $mt['week_start'] ?? 'Monday',
            'unit' => $mt['unit'],
            'scope' => $mt['scope'],
            'privacy' => $mt['privacy'] ?? 'Public',
            'is_starred' => intval($mt['is_starred']),
            'goal_direction' => $mt['goal_direction'],
            'start_target' => floatval($mt['start_target'] ?? 0),
            'auto_set_status' => intval($mt['auto_set_status'] ?? 1),
            'super_green_pct' => floatval($mt['super_green_pct'] ?? 120),
            'yellow_pct' => floatval($mt['yellow_pct'] ?? 80),
            'summary' => $summary,
            'can_edit' => ($is_admin || $ouid === $user_id),
        ];
    }
    return $list;
}

function meetingBuildOccurrenceState(int $occurrence_id, int $user_id, bool $is_admin, ?string $metric_period = null): array {
    global $mysqli;

    $occurrence_id = intval($occurrence_id);
    $user_id = intval($user_id);

    $occ = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM meeting_occurrences WHERE occurrence_id = $occurrence_id LIMIT 1"));
    if (!$occ) {
        return [];
    }

    $series_id = intval($occ['series_id']);
    $series = meetingGetSeries($series_id);
    if (!$series) {
        return [];
    }

    $can_edit_any = meetingIsFacilitatorOrAdmin($series, $user_id, $is_admin);
    if (empty($metric_period) || !preg_match('/^\d{4}-\d{2}$/', $metric_period)) {
        $metric_period = substr($occ['occurrence_date'], 0, 7);
    }

    $presence = [];
    $online_users = [];
    $rp = mysqli_query($mysqli, "
        SELECT p.user_id, p.active_agenda_item_id, p.last_seen_at
        FROM meeting_presence p
        WHERE p.occurrence_id = $occurrence_id
          AND p.last_seen_at >= DATE_SUB(NOW(), INTERVAL 20 SECOND)
    ");
    while ($rp && ($pr = mysqli_fetch_assoc($rp))) {
        $uid = intval($pr['user_id']);
        $presence[$uid] = $pr;
        $online_users[] = $uid;
    }

    $attendees = [];
    $ra = mysqli_query($mysqli, "
        SELECT ma.attendee_id, ma.user_id, ma.role, ma.sort_order, u.user_name, u.user_email, u.user_avatar
        FROM meeting_attendees ma
        JOIN users u ON ma.user_id = u.user_id
        WHERE ma.series_id = $series_id AND u.user_archived_at IS NULL
        ORDER BY ma.sort_order ASC, u.user_name ASC
    ");
    $seen_uids = [];
    while ($ra && ($ar = mysqli_fetch_assoc($ra))) {
        $uid = intval($ar['user_id']);
        $seen_uids[$uid] = true;
        $av = meetingUserAvatarData($uid, $ar['user_name'], $ar['user_avatar']);
        $attendees[] = [
            'attendee_id' => intval($ar['attendee_id']),
            'user_id' => $uid,
            'user_name' => $ar['user_name'],
            'user_email' => $ar['user_email'],
            'role' => $ar['role'],
            'avatar_url' => $av['avatar_url'],
            'initials' => $av['initials'],
            'color' => $av['color'],
            'is_online' => isset($presence[$uid]),
            'active_agenda_item_id' => isset($presence[$uid]) ? intval($presence[$uid]['active_agenda_item_id']) : null,
        ];
    }

    $agenda_items = [];
    $r_ag = mysqli_query($mysqli, "SELECT * FROM meeting_agenda_items WHERE series_id = $series_id AND is_active = 1 ORDER BY sort_order ASC, agenda_item_id ASC");
    while ($r_ag && ($ag = mysqli_fetch_assoc($r_ag))) {
        $agenda_items[] = [
            'agenda_item_id' => intval($ag['agenda_item_id']),
            'title' => $ag['title'],
            'item_type' => $ag['item_type'],
            'icon' => $ag['icon'],
            'sort_order' => intval($ag['sort_order']),
        ];
    }

    $responses = [];
    $r_resp = mysqli_query($mysqli, "SELECT agenda_item_id, user_id, content, updated_by, updated_at FROM meeting_responses WHERE occurrence_id = $occurrence_id");
    while ($r_resp && ($rr = mysqli_fetch_assoc($r_resp))) {
        $aid = strval($rr['agenda_item_id']);
        $uid = strval($rr['user_id']);
        $responses[$aid][$uid] = [
            'content' => (string)($rr['content'] ?? ''),
            'updated_by' => intval($rr['updated_by']),
            'updated_at' => $rr['updated_at'],
        ];
    }

    $metrics = [];
    $r_met = mysqli_query($mysqli, "
        SELECT m.*, u.user_name AS owner_name, u.user_avatar AS owner_avatar
        FROM meeting_metrics m
        LEFT JOIN users u ON m.owner_user_id = u.user_id
        WHERE m.archived_at IS NULL
          AND (m.series_id = $series_id OR (m.series_id IS NULL AND m.scope = 'Company' AND m.privacy = 'Public'))
        ORDER BY m.is_starred DESC, m.sort_order ASC, m.metric_id ASC
    ");
    while ($r_met && ($mt = mysqli_fetch_assoc($r_met))) {
        $ouid = intval($mt['owner_user_id']);
        $oav = meetingUserAvatarData($ouid, $mt['owner_name'] ?: 'User', $mt['owner_avatar'] ?? null);
        $summary = meetingComputeMetricSummary($mt, $occ['occurrence_date'], $metric_period);
        $metrics[] = [
            'metric_id' => intval($mt['metric_id']),
            'series_id' => $mt['series_id'] !== null ? intval($mt['series_id']) : null,
            'owner_user_id' => $ouid,
            'owner_name' => $mt['owner_name'] ?: 'Unassigned',
            'owner_avatar_url' => $oav['avatar_url'],
            'owner_initials' => $oav['initials'],
            'owner_color' => $oav['color'],
            'metric_name' => $mt['metric_name'],
            'metric_type' => $mt['metric_type'] ?? 'Count',
            'frequency' => $mt['frequency'] ?? 'Monthly',
            'work_days' => $mt['work_days'] ?? 'Mon,Tue,Wed,Thu,Fri',
            'week_start' => $mt['week_start'] ?? 'Monday',
            'unit' => $mt['unit'],
            'scope' => $mt['scope'],
            'privacy' => $mt['privacy'] ?? 'Public',
            'is_starred' => intval($mt['is_starred']),
            'goal_direction' => $mt['goal_direction'],
            'start_target' => floatval($mt['start_target'] ?? 0),
            'auto_set_status' => intval($mt['auto_set_status'] ?? 1),
            'super_green_pct' => floatval($mt['super_green_pct'] ?? 120),
            'yellow_pct' => floatval($mt['yellow_pct'] ?? 80),
            'period_target' => $summary['interval_target'],
            'actual_value' => $summary['interval_actual'],
            'to_date_target' => $summary['to_date_target'],
            'to_date_actual' => $summary['to_date_actual'],
            'target_pace' => $summary['target_pace'],
            'status_color' => $summary['status_color'],
            'quarter_label' => $summary['quarter']['label'],
            'quarter_key' => $summary['quarter']['quarter_key'],
            'period_key' => $summary['interval_key'],
            'interval_label' => $summary['interval_label'],
            'table_rows' => $summary['rows'],
            'can_edit' => ($can_edit_any || $ouid === $user_id),
        ];
    }

    $action_items = [];
    $r_act = mysqli_query($mysqli, "
        SELECT a.*, u.user_name AS owner_name, u.user_avatar AS owner_avatar
        FROM meeting_action_items a
        LEFT JOIN users u ON a.owner_user_id = u.user_id
        WHERE a.series_id = $series_id
          AND (a.status != 'Completed' OR a.completed_occurrence_id = $occurrence_id)
        ORDER BY (a.status = 'Completed') ASC, a.due_date IS NULL ASC, a.due_date ASC, a.action_id DESC
    ");
    while ($r_act && ($ac = mysqli_fetch_assoc($r_act))) {
        $ouid = intval($ac['owner_user_id']);
        $oav = meetingUserAvatarData($ouid, $ac['owner_name'] ?: 'User', $ac['owner_avatar'] ?? null);
        $action_items[] = [
            'action_id' => intval($ac['action_id']),
            'owner_user_id' => $ouid,
            'owner_name' => $ac['owner_name'] ?: 'Unassigned',
            'owner_avatar_url' => $oav['avatar_url'],
            'owner_initials' => $oav['initials'],
            'owner_color' => $oav['color'],
            'description' => $ac['description'],
            'due_date' => $ac['due_date'],
            'priority' => $ac['priority'],
            'status' => $ac['status'],
            'created_by' => intval($ac['created_by']),
            'can_edit' => ($can_edit_any || $ouid === $user_id || intval($ac['created_by']) === $user_id),
        ];
    }

    $pn_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT content, updated_at FROM meeting_personal_notes WHERE occurrence_id = $occurrence_id AND user_id = $user_id LIMIT 1"));

    $attachments = [];
    $r_att = mysqli_query($mysqli, "
        SELECT a.*, u.user_name AS uploaded_by_name
        FROM meeting_attachments a
        LEFT JOIN users u ON a.uploaded_by = u.user_id
        WHERE a.series_id = $series_id AND (a.occurrence_id IS NULL OR a.occurrence_id = $occurrence_id)
        ORDER BY a.attachment_id DESC
    ");
    while ($r_att && ($at = mysqli_fetch_assoc($r_att))) {
        $attachments[] = [
            'attachment_id' => intval($at['attachment_id']),
            'title' => $at['title'],
            'file_name' => $at['file_name'],
            'has_file' => !empty($at['file_path']),
            'external_url' => $at['external_url'],
            'uploaded_by' => intval($at['uploaded_by']),
            'uploaded_by_name' => $at['uploaded_by_name'] ?: 'User',
            'created_at' => $at['created_at'],
            'can_delete' => ($can_edit_any || intval($at['uploaded_by']) === $user_id),
        ];
    }

    return [
        'occurrence' => [
            'occurrence_id' => $occurrence_id,
            'series_id' => $series_id,
            'occurrence_date' => $occ['occurrence_date'],
            'occurrence_date_display' => date('M j, Y', strtotime($occ['occurrence_date'])),
            'date_override' => intval($occ['date_override']),
            'period_label' => $occ['period_label'] ?: meetingFormatPeriodLabel($occ['occurrence_date'], $series['default_period_type']),
            'status' => $occ['status'],
            'shared_notes' => (string)($occ['shared_notes'] ?? ''),
            'shared_notes_version' => intval($occ['shared_notes_version']),
            'sync_seq' => intval($occ['sync_seq']),
            'prev_date' => meetingCalculateStepDate($series, $occ['occurrence_date'], -1),
            'next_date' => meetingCalculateStepDate($series, $occ['occurrence_date'], 1),
        ],
        'metric_period' => $metric_period,
        'metric_period_display' => date('M Y', strtotime($metric_period . '-01')),
        'can_edit_any' => $can_edit_any,
        'current_user_id' => $user_id,
        'attendees' => $attendees,
        'agenda_items' => $agenda_items,
        'responses' => $responses,
        'metrics' => $metrics,
        'action_items' => $action_items,
        'personal_note' => (string)($pn_row['content'] ?? ''),
        'attachments' => $attachments,
        'online_users' => $online_users,
    ];
}
