<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_support');

// Helper to format seconds to HH:MM:SS
function formatSecondsToHms($seconds) {
    $seconds = (int) max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

// Helper to format seconds to decimal hours
function formatSecondsToDecimalHours($seconds) {
    $seconds = (int) max(0, $seconds);
    if ($seconds === 0) return '0.00';
    return number_format($seconds / 3600, 2);
}

// Helper to validate YYYY-MM-DD
function isValidYmd($dateStr) {
    return is_string($dateStr) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr);
}

// Determine target date reference
if (isset($_GET['date']) && isValidYmd($_GET['date'])) {
    $ref_timestamp = strtotime($_GET['date']);
} elseif (isset($_GET['from']) && isValidYmd($_GET['from'])) {
    $ref_timestamp = strtotime($_GET['from']);
} else {
    $ref_timestamp = time();
}

// Calculate Monday to Sunday week boundary (1 = Monday, 7 = Sunday)
$day_of_week = (int) date('N', $ref_timestamp);
$monday_timestamp = strtotime('-' . ($day_of_week - 1) . ' days', $ref_timestamp);
$sunday_timestamp = strtotime('+' . (7 - $day_of_week) . ' days', $ref_timestamp);

$week_start = date('Y-m-d', $monday_timestamp);
$week_end   = date('Y-m-d', $sunday_timestamp);
$week_start_dt = $week_start . ' 00:00:00';
$week_end_dt   = $week_end . ' 23:59:59';

// Navigation week references
$prev_week_date = date('Y-m-d', strtotime('-7 days', $monday_timestamp));
$next_week_date = date('Y-m-d', strtotime('+7 days', $monday_timestamp));
$current_week_date = date('Y-m-d');

// Filters
$filter_user_id = intval($_GET['user_id'] ?? 0);
$filter_client_id = intval($_GET['client_id'] ?? 0);

// Fetch technicians for dropdown
$sql_tech_users = mysqli_query($mysqli, "
    SELECT user_id, user_name 
    FROM users 
    WHERE user_status = 1 AND user_archived_at IS NULL 
    ORDER BY user_name ASC
");

// Fetch clients for dropdown
$sql_clients_list = mysqli_query($mysqli, "
    SELECT client_id, client_name 
    FROM clients 
    WHERE client_archived_at IS NULL 
    ORDER BY client_name ASC
");

// Fetch active technicians to report on
$tech_condition = $filter_user_id > 0 ? "AND user_id = $filter_user_id" : "";
$sql_report_techs = mysqli_query($mysqli, "
    SELECT user_id, user_name, user_avatar 
    FROM users 
    WHERE user_status = 1 AND user_archived_at IS NULL 
    $tech_condition
    ORDER BY user_name ASC
");

// Grand Totals Accumulators
$grand_total_seconds = 0;
$grand_total_miles   = 0.0;
$grand_total_tickets = 0;
$grand_total_trips   = 0;

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2">
            <i class="fas fa-fw fa-user-clock mr-2"></i>Technician Weekly Time & Mileage Summary
        </h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none" onclick="window.print();">
                <i class="fas fa-fw fa-print mr-2"></i>Print Report
            </button>
        </div>
    </div>
    <div class="card-body">

        <!-- Filter & Week Navigation Bar -->
        <div class="card card-outline card-primary p-3 mb-4 bg-light d-print-none">
            <form method="GET" autocomplete="off">
                <div class="row align-items-end">
                    
                    <!-- Week Range Quick Buttons & Datepicker -->
                    <div class="col-md-5 mb-2">
                        <label class="font-weight-bold"><i class="far fa-calendar-alt mr-1"></i> Week (Mon – Sun)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <a href="?date=<?= $prev_week_date ?>&user_id=<?= $filter_user_id ?>&client_id=<?= $filter_client_id ?>" 
                                   class="btn btn-outline-secondary" title="Previous Week">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </div>
                            <input type="date" class="form-control text-center font-weight-bold" name="date" 
                                   value="<?= $week_start ?>" onchange="this.form.submit()">
                            <div class="input-group-append">
                                <a href="?date=<?= $next_week_date ?>&user_id=<?= $filter_user_id ?>&client_id=<?= $filter_client_id ?>" 
                                   class="btn btn-outline-secondary" title="Next Week">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                                <a href="?date=<?= $current_week_date ?>&user_id=<?= $filter_user_id ?>&client_id=<?= $filter_client_id ?>" 
                                   class="btn btn-secondary" title="Jump to Current Week">
                                    Current Week
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Filter by Technician -->
                    <div class="col-md-3 mb-2">
                        <label class="font-weight-bold"><i class="fas fa-user-cog mr-1"></i> Technician</label>
                        <select class="form-control select2" name="user_id" onchange="this.form.submit()">
                            <option value="0">- All Technicians -</option>
                            <?php 
                            mysqli_data_seek($sql_tech_users, 0);
                            while ($tech = mysqli_fetch_assoc($sql_tech_users)) { 
                                $t_id = (int) $tech['user_id'];
                            ?>
                                <option value="<?= $t_id ?>" <?= $filter_user_id === $t_id ? 'selected' : '' ?>>
                                    <?= escapeHtml($tech['user_name']) ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- Filter by Client -->
                    <div class="col-md-3 mb-2">
                        <label class="font-weight-bold"><i class="fas fa-building mr-1"></i> Client</label>
                        <select class="form-control select2" name="client_id" onchange="this.form.submit()">
                            <option value="0">- All Clients -</option>
                            <?php 
                            mysqli_data_seek($sql_clients_list, 0);
                            while ($client_opt = mysqli_fetch_assoc($sql_clients_list)) { 
                                $c_id = (int) $client_opt['client_id'];
                            ?>
                                <option value="<?= $c_id ?>" <?= $filter_client_id === $c_id ? 'selected' : '' ?>>
                                    <?= escapeHtml($client_opt['client_name']) ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- Reset / Filter Action -->
                    <div class="col-md-1 mb-2">
                        <a href="tech_weekly_summary.php" class="btn btn-outline-danger btn-block" title="Reset Filters">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Report Header Banner -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-0 font-weight-bold text-dark">
                    <i class="fas fa-calendar-week mr-2 text-primary"></i>
                    Week of <?= date('F j, Y', strtotime($week_start)) ?> &ndash; <?= date('F j, Y', strtotime($week_end)) ?>
                </h4>
                <small class="text-muted">Monday to Sunday period</small>
            </div>
            <div class="text-right">
                <span class="badge badge-light border p-2">
                    <i class="fas fa-clock mr-1 text-secondary"></i> Generated: <?= date('Y-m-d H:i') ?>
                </span>
            </div>
        </div>

        <?php
        // Prepare arrays to hold report data for summary rendering
        $report_data = [];

        while ($tech_row = mysqli_fetch_assoc($sql_report_techs)) {
            $tech_id = (int) $tech_row['user_id'];
            $tech_name = escapeHtml($tech_row['user_name']);

            // 1. Time Worked by Client in the Week
            $client_filter_sql_time = $filter_client_id > 0 ? "AND t.ticket_client_id = $filter_client_id" : "";
            $sql_time_by_client = mysqli_query($mysqli, "
                SELECT 
                    COALESCE(c.client_id, 0) AS client_id,
                    COALESCE(c.client_name, 'No Client Assigned / Internal') AS client_name,
                    COUNT(DISTINCT tr.ticket_reply_ticket_id) AS ticket_count,
                    COUNT(tr.ticket_reply_id) AS entry_count,
                    SUM(TIME_TO_SEC(tr.ticket_reply_time_worked)) AS total_seconds
                FROM ticket_replies tr
                LEFT JOIN tickets t ON tr.ticket_reply_ticket_id = t.ticket_id
                LEFT JOIN clients c ON t.ticket_client_id = c.client_id
                WHERE tr.ticket_reply_by = $tech_id
                  AND tr.ticket_reply_created_at >= '$week_start_dt'
                  AND tr.ticket_reply_created_at <= '$week_end_dt'
                  AND tr.ticket_reply_archived_at IS NULL
                  AND tr.ticket_reply_time_worked IS NOT NULL
                  AND tr.ticket_reply_time_worked != '00:00:00'
                  $client_filter_sql_time
                GROUP BY c.client_id, c.client_name
                ORDER BY c.client_name ASC
            ");

            // 2. Mileage / Trips by Client in the Week
            $client_filter_sql_trips = $filter_client_id > 0 ? "AND tp.trip_client_id = $filter_client_id" : "";
            $sql_trips_by_client = mysqli_query($mysqli, "
                SELECT 
                    COALESCE(c.client_id, 0) AS client_id,
                    COALESCE(c.client_name, 'No Client Assigned / General') AS client_name,
                    COUNT(tp.trip_id) AS trip_count,
                    SUM(tp.trip_miles) AS total_miles
                FROM trips tp
                LEFT JOIN clients c ON tp.trip_client_id = c.client_id
                WHERE tp.trip_user_id = $tech_id
                  AND tp.trip_date >= '$week_start'
                  AND tp.trip_date <= '$week_end'
                  AND tp.trip_archived_at IS NULL
                  $client_filter_sql_trips
                GROUP BY c.client_id, c.client_name
                ORDER BY c.client_name ASC
            ");

            // 3. Detailed Time Logs for this Tech in the Week
            $sql_tech_time_details = mysqli_query($mysqli, "
                SELECT 
                    tr.ticket_reply_id,
                    tr.ticket_reply_created_at,
                    tr.ticket_reply_time_worked,
                    tr.ticket_reply_type,
                    t.ticket_id,
                    t.ticket_prefix,
                    t.ticket_number,
                    t.ticket_subject,
                    COALESCE(c.client_id, 0) AS client_id,
                    COALESCE(c.client_name, 'No Client Assigned') AS client_name
                FROM ticket_replies tr
                LEFT JOIN tickets t ON tr.ticket_reply_ticket_id = t.ticket_id
                LEFT JOIN clients c ON t.ticket_client_id = c.client_id
                WHERE tr.ticket_reply_by = $tech_id
                  AND tr.ticket_reply_created_at >= '$week_start_dt'
                  AND tr.ticket_reply_created_at <= '$week_end_dt'
                  AND tr.ticket_reply_archived_at IS NULL
                  AND tr.ticket_reply_time_worked IS NOT NULL
                  AND tr.ticket_reply_time_worked != '00:00:00'
                  $client_filter_sql_time
                ORDER BY tr.ticket_reply_created_at ASC
            ");

            // 4. Detailed Trip Logs for this Tech in the Week
            $sql_tech_trip_details = mysqli_query($mysqli, "
                SELECT 
                    tp.trip_id,
                    tp.trip_date,
                    tp.trip_purpose,
                    tp.trip_source,
                    tp.trip_destination,
                    tp.trip_miles,
                    tp.round_trip,
                    COALESCE(c.client_id, 0) AS client_id,
                    COALESCE(c.client_name, 'No Client Assigned') AS client_name
                FROM trips tp
                LEFT JOIN clients c ON tp.trip_client_id = c.client_id
                WHERE tp.trip_user_id = $tech_id
                  AND tp.trip_date >= '$week_start'
                  AND tp.trip_date <= '$week_end'
                  AND tp.trip_archived_at IS NULL
                  $client_filter_sql_trips
                ORDER BY tp.trip_date ASC, tp.trip_id ASC
            ");

            // Merge time and trips per client into unified map
            $client_map = [];

            while ($time_row = mysqli_fetch_assoc($sql_time_by_client)) {
                $c_id = (int) $time_row['client_id'];
                $client_map[$c_id] = [
                    'client_id'   => $c_id,
                    'client_name' => $time_row['client_name'],
                    'seconds'     => (int) $time_row['total_seconds'],
                    'tickets'     => (int) $time_row['ticket_count'],
                    'entries'     => (int) $time_row['entry_count'],
                    'miles'       => 0.0,
                    'trips'       => 0
                ];
            }

            while ($trip_row = mysqli_fetch_assoc($sql_trips_by_client)) {
                $c_id = (int) $trip_row['client_id'];
                if (!isset($client_map[$c_id])) {
                    $client_map[$c_id] = [
                        'client_id'   => $c_id,
                        'client_name' => $trip_row['client_name'],
                        'seconds'     => 0,
                        'tickets'     => 0,
                        'entries'     => 0,
                        'miles'       => (float) $trip_row['total_miles'],
                        'trips'       => (int) $trip_row['trip_count']
                    ];
                } else {
                    $client_map[$c_id]['miles'] += (float) $trip_row['total_miles'];
                    $client_map[$c_id]['trips'] += (int) $trip_row['trip_count'];
                }
            }

            // Tech Totals
            $tech_total_seconds = 0;
            $tech_total_miles   = 0.0;
            $tech_total_tickets = 0;
            $tech_total_trips   = 0;

            foreach ($client_map as $c_data) {
                $tech_total_seconds += $c_data['seconds'];
                $tech_total_miles   += $c_data['miles'];
                $tech_total_tickets += $c_data['tickets'];
                $tech_total_trips   += $c_data['trips'];
            }

            // Accumulate Grand Totals
            $grand_total_seconds += $tech_total_seconds;
            $grand_total_miles   += $tech_total_miles;
            $grand_total_tickets += $tech_total_tickets;
            $grand_total_trips   += $tech_total_trips;

            $report_data[] = [
                'tech_id'            => $tech_id,
                'tech_name'          => $tech_name,
                'client_map'         => $client_map,
                'total_seconds'      => $tech_total_seconds,
                'total_miles'        => $tech_total_miles,
                'total_tickets'      => $tech_total_tickets,
                'total_trips'        => $tech_total_trips,
                'time_details'       => $sql_tech_time_details,
                'trip_details'       => $sql_tech_trip_details
            ];
        }
        ?>

        <!-- KPI Summary Cards (Grand Totals) -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-2">
                <div class="info-box bg-light border shadow-sm">
                    <span class="info-box-icon bg-primary"><i class="fas fa-hourglass-half"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Total Hours (All Techs)</span>
                        <span class="info-box-number text-dark">
                            <?= formatSecondsToDecimalHours($grand_total_seconds) ?> <small class="text-secondary">hrs</small>
                            <span class="small font-weight-normal text-muted ml-1">(<?= formatSecondsToHms($grand_total_seconds) ?>)</span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-2">
                <div class="info-box bg-light border shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-road"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Total Mileage (All Techs)</span>
                        <span class="info-box-number text-dark">
                            <?= number_format($grand_total_miles, 1) ?> <small class="text-secondary">miles</small>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-2">
                <div class="info-box bg-light border shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-ticket-alt"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Tickets Worked</span>
                        <span class="info-box-number text-dark"><?= $grand_total_tickets ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-2">
                <div class="info-box bg-light border shadow-sm">
                    <span class="info-box-icon bg-warning text-white"><i class="fas fa-car-side"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Trips Logged</span>
                        <span class="info-box-number text-dark"><?= $grand_total_trips ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Technician Breakdowns -->
        <?php if (empty($report_data)) { ?>
            <div class="alert alert-info text-center p-4">
                <i class="fas fa-info-circle fa-2x mb-2"></i>
                <p class="mb-0">No technician records found for the selected criteria.</p>
            </div>
        <?php } ?>

        <?php foreach ($report_data as $data) { 
            $t_id = $data['tech_id'];
            $t_name = $data['tech_name'];
            $has_activity = ($data['total_seconds'] > 0 || $data['total_miles'] > 0);
        ?>
            <div class="card card-dark card-outline mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="card-title font-weight-bold mb-0">
                            <i class="fas fa-user-circle mr-2 text-primary"></i><?= $t_name ?>
                        </h4>
                        <div>
                            <span class="badge badge-primary px-2 py-1 mr-2">
                                <i class="fas fa-clock mr-1"></i> <?= formatSecondsToDecimalHours($data['total_seconds']) ?> hrs (<?= formatSecondsToHms($data['total_seconds']) ?>)
                            </span>
                            <span class="badge badge-success px-2 py-1">
                                <i class="fas fa-route mr-1"></i> <?= number_format($data['total_miles'], 1) ?> miles
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <?php if (!empty($data['client_map'])) { ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Client Name</th>
                                        <th class="text-right">Tickets</th>
                                        <th class="text-right">Hours (Decimal)</th>
                                        <th class="text-right">Hours (H:M:S)</th>
                                        <th class="text-right">Trips</th>
                                        <th class="text-right">Miles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['client_map'] as $client_stat) { ?>
                                        <tr>
                                            <td class="font-weight-bold">
                                                <?php if ($client_stat['client_id'] > 0) { ?>
                                                    <a href="/agent/client_overview.php?client_id=<?= $client_stat['client_id'] ?>">
                                                        <i class="fas fa-building mr-1 text-secondary"></i>
                                                        <?= escapeHtml($client_stat['client_name']) ?>
                                                    </a>
                                                <?php } else { ?>
                                                    <i class="fas fa-tag mr-1 text-muted"></i>
                                                    <?= escapeHtml($client_stat['client_name']) ?>
                                                <?php } ?>
                                            </td>
                                            <td class="text-right text-muted"><?= $client_stat['tickets'] ?></td>
                                            <td class="text-right font-weight-bold text-primary">
                                                <?= formatSecondsToDecimalHours($client_stat['seconds']) ?> hrs
                                            </td>
                                            <td class="text-right text-secondary">
                                                <?= formatSecondsToHms($client_stat['seconds']) ?>
                                            </td>
                                            <td class="text-right text-muted"><?= $client_stat['trips'] ?></td>
                                            <td class="text-right font-weight-bold text-success">
                                                <?= number_format($client_stat['miles'], 1) ?> mi
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                                <tfoot class="bg-light font-weight-bold border-top">
                                    <tr>
                                        <td>Total &mdash; <?= $t_name ?></td>
                                        <td class="text-right"><?= $data['total_tickets'] ?></td>
                                        <td class="text-right text-primary"><?= formatSecondsToDecimalHours($data['total_seconds']) ?> hrs</td>
                                        <td class="text-right text-secondary"><?= formatSecondsToHms($data['total_seconds']) ?></td>
                                        <td class="text-right"><?= $data['total_trips'] ?></td>
                                        <td class="text-right text-success"><?= number_format($data['total_miles'], 1) ?> mi</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Collapsible Detailed Activity Logs -->
                        <div class="p-3 bg-white border-top d-print-none">
                            <button class="btn btn-outline-secondary btn-sm" type="button" 
                                    data-toggle="collapse" data-target="#details-tech-<?= $t_id ?>">
                                <i class="fas fa-list-ul mr-1"></i> Toggle Individual Activity Logs
                            </button>

                            <div class="collapse mt-3" id="details-tech-<?= $t_id ?>">
                                <div class="row">
                                    <!-- Time Log Entries -->
                                    <div class="col-lg-6 mb-3">
                                        <div class="card card-outline card-secondary mb-0">
                                            <div class="card-header py-1 bg-light">
                                                <h6 class="card-title mb-0 small font-weight-bold">
                                                    <i class="fas fa-clock mr-1 text-primary"></i> Ticket Time Logs (<?= mysqli_num_rows($data['time_details']) ?>)
                                                </h6>
                                            </div>
                                            <div class="card-body p-0" style="max-height: 250px; overflow-y: auto;">
                                                <table class="table table-sm table-striped small mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Ticket</th>
                                                            <th>Client</th>
                                                            <th class="text-right">Time Worked</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        if (mysqli_num_rows($data['time_details']) > 0) {
                                                            while ($log = mysqli_fetch_assoc($data['time_details'])) { ?>
                                                                <tr>
                                                                    <td><?= date('M j, g:i A', strtotime($log['ticket_reply_created_at'])) ?></td>
                                                                    <td>
                                                                        <a href="/agent/ticket.php?ticket_id=<?= $log['ticket_id'] ?>" class="text-bold">
                                                                            #<?= $log['ticket_prefix'] ?><?= $log['ticket_number'] ?>
                                                                        </a>
                                                                    </td>
                                                                    <td><?= escapeHtml($log['client_name']) ?></td>
                                                                    <td class="text-right font-weight-bold"><?= $log['ticket_reply_time_worked'] ?></td>
                                                                </tr>
                                                            <?php }
                                                        } else { ?>
                                                            <tr><td colspan="4" class="text-center text-muted p-2">No time entries logged.</td></tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Trip Mileage Entries -->
                                    <div class="col-lg-6 mb-3">
                                        <div class="card card-outline card-secondary mb-0">
                                            <div class="card-header py-1 bg-light">
                                                <h6 class="card-title mb-0 small font-weight-bold">
                                                    <i class="fas fa-road mr-1 text-success"></i> Trip Logs (<?= mysqli_num_rows($data['trip_details']) ?>)
                                                </h6>
                                            </div>
                                            <div class="card-body p-0" style="max-height: 250px; overflow-y: auto;">
                                                <table class="table table-sm table-striped small mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Client</th>
                                                            <th>Purpose / Route</th>
                                                            <th class="text-right">Miles</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        if (mysqli_num_rows($data['trip_details']) > 0) {
                                                            while ($trip = mysqli_fetch_assoc($data['trip_details'])) { ?>
                                                                <tr>
                                                                    <td><?= date('M j', strtotime($trip['trip_date'])) ?></td>
                                                                    <td><?= escapeHtml($trip['client_name']) ?></td>
                                                                    <td>
                                                                        <?= escapeHtml($trip['trip_purpose']) ?>
                                                                        <?php if (!empty($trip['trip_source']) || !empty($trip['trip_destination'])) { ?>
                                                                            <br><span class="text-muted"><?= escapeHtml($trip['trip_source']) ?> &rarr; <?= escapeHtml($trip['trip_destination']) ?></span>
                                                                        <?php } ?>
                                                                        <?php if ($trip['round_trip'] == 1) { ?>
                                                                            <span class="badge badge-light border">Round Trip</span>
                                                                        <?php } ?>
                                                                    </td>
                                                                    <td class="text-right font-weight-bold text-success"><?= number_format($trip['trip_miles'], 1) ?> mi</td>
                                                                </tr>
                                                            <?php }
                                                        } else { ?>
                                                            <tr><td colspan="4" class="text-center text-muted p-2">No trips logged.</td></tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php } else { ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-bed fa-2x mb-2 text-secondary"></i>
                            <p class="mb-0">No time or mileage logged for this technician during this week.</p>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <!-- Grand Total Summary Footer -->
        <div class="card card-dark bg-light border">
            <div class="card-body p-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0 font-weight-bold text-dark">
                            <i class="fas fa-calculator mr-2 text-primary"></i>Grand Total (All Technicians)
                        </h5>
                        <small class="text-muted">For period <?= date('M j, Y', strtotime($week_start)) ?> to <?= date('M j, Y', strtotime($week_end)) ?></small>
                    </div>
                    <div class="col-md-6 text-md-right mt-2 mt-md-0">
                        <span class="h5 font-weight-bold text-primary mr-3">
                            <i class="fas fa-clock mr-1"></i> <?= formatSecondsToDecimalHours($grand_total_seconds) ?> hrs (<?= formatSecondsToHms($grand_total_seconds) ?>)
                        </span>
                        <span class="h5 font-weight-bold text-success">
                            <i class="fas fa-route mr-1"></i> <?= number_format($grand_total_miles, 1) ?> miles
                        </span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
require_once "../../includes/footer.php";
?>
