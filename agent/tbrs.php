<?php

$sort = "tbr_meeting_date";
$order = "DESC";

$is_client_mode = isset($_GET['client_id']) && intval($_GET['client_id']) > 0;

if ($is_client_mode) {
    require_once "includes/inc_all_client.php";
    tbrEnforcePermission('view');
    $scope_sql = " AND m.tbr_client_id = $client_id ";
} else {
    require_once "includes/inc_all.php";
    tbrEnforcePermission('view');
    $scope_sql = clientScopeSql('m.tbr_client_id');
}

$filter_client_id = intval($_GET['filter_client_id'] ?? 0);
$filter_status = $_GET['status'] ?? '';
$filter_user_id = intval($_GET['assigned_user_id'] ?? 0);
$filter_type = $_GET['type'] ?? '';
$quick = $_GET['quick'] ?? '';

$where_sql = " WHERE m.tbr_archived_at IS NULL AND c.client_archived_at IS NULL $scope_sql ";

if ($q !== '') {
    $where_sql .= " AND (m.tbr_title LIKE '%$q%' OR c.client_name LIKE '%$q%' OR u.user_name LIKE '%$q%' OR m.tbr_location LIKE '%$q%') ";
}
if ($filter_client_id > 0 && !$is_client_mode) {
    $where_sql .= " AND m.tbr_client_id = $filter_client_id ";
}
if (in_array($filter_status, ['Scheduled', 'In Progress', 'Completed', 'Cancelled'], true)) {
    $fs = escapeSql($filter_status);
    $where_sql .= " AND m.tbr_status = '$fs' ";
}
if ($filter_user_id > 0) {
    $where_sql .= " AND m.tbr_assigned_user_id = $filter_user_id ";
}
if (in_array($filter_type, ['Quarterly', 'Annual', 'Special'], true)) {
    $ft = escapeSql($filter_type);
    $where_sql .= " AND m.tbr_type = '$ft' ";
}
if (!empty($_GET['dtf']) && !empty($_GET['dtt']) && $_GET['canned_date'] !== 'alltime') {
    $where_sql .= " AND m.tbr_meeting_date BETWEEN '$dtf' AND '$dtt' ";
}

if ($quick === 'upcoming') {
    $where_sql .= " AND m.tbr_status IN ('Scheduled', 'In Progress') AND m.tbr_meeting_date >= CURDATE() AND m.tbr_meeting_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ";
} elseif ($quick === 'overdue') {
    $where_sql .= " AND m.tbr_status = 'Scheduled' AND m.tbr_meeting_date < CURDATE() ";
} elseif ($quick === 'followup') {
    $where_sql .= " AND (EXISTS (SELECT 1 FROM tbr_action_items ai WHERE ai.action_tbr_id = m.tbr_id AND ai.action_status IN ('Not Started','In Progress','Waiting on Client')) OR (m.tbr_status = 'Completed' AND m.tbr_client_report_approved = 0)) ";
} elseif ($quick === 'completed') {
    $where_sql .= " AND m.tbr_status = 'Completed' ";
} elseif ($quick === 'scheduled') {
    $where_sql .= " AND m.tbr_status = 'Scheduled' ";
}

$allowed_sorts = ['tbr_meeting_date', 'client_name', 'tbr_type', 'tbr_status', 'rep_name'];
if (!in_array($sort, $allowed_sorts, true)) {
    $sort = 'tbr_meeting_date';
}

$sql = mysqli_query($mysqli, "
    SELECT SQL_CALC_FOUND_ROWS m.*,
           c.client_name,
           u.user_name AS rep_name,
           (SELECT MAX(pm.tbr_meeting_date) FROM tbr_meetings pm WHERE pm.tbr_client_id = m.tbr_client_id AND pm.tbr_archived_at IS NULL AND pm.tbr_status = 'Completed' AND pm.tbr_id != m.tbr_id) AS last_tbr_date,
           (SELECT COUNT(*) FROM tbr_action_items ai WHERE ai.action_tbr_id = m.tbr_id AND ai.action_status IN ('Not Started','In Progress','Waiting on Client')) AS open_actions_count
    FROM tbr_meetings m
    JOIN clients c ON m.tbr_client_id = c.client_id
    LEFT JOIN users u ON m.tbr_assigned_user_id = u.user_id
    $where_sql
    ORDER BY $sort $order, m.tbr_start_time DESC
    LIMIT $record_from, $record_to
");
$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$status_badge = function(string $status, string $date): string {
    if ($status === 'Scheduled' && $date < date('Y-m-d')) {
        return '<span class="badge badge-danger">Overdue</span> <span class="badge badge-primary">Scheduled</span>';
    }
    return match ($status) {
        'Scheduled' => '<span class="badge badge-primary">Scheduled</span>',
        'In Progress' => '<span class="badge badge-warning">In Progress</span>',
        'Completed' => '<span class="badge badge-success">Completed</span>',
        'Cancelled' => '<span class="badge badge-secondary">Cancelled</span>',
        default => '<span class="badge badge-light">' . escapeHtml($status) . '</span>',
    };
};

if (!$is_client_mode) {
    $global_scope = clientScopeSql('m.tbr_client_id');
    $client_list_scope = clientScopeSql('c.client_id');

    $metric_total_scheduled = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM tbr_meetings m JOIN clients c ON m.tbr_client_id = c.client_id WHERE m.tbr_archived_at IS NULL AND c.client_archived_at IS NULL AND m.tbr_status = 'Scheduled' $global_scope"))[0] ?? 0);
    $metric_upcoming = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM tbr_meetings m JOIN clients c ON m.tbr_client_id = c.client_id WHERE m.tbr_archived_at IS NULL AND c.client_archived_at IS NULL AND m.tbr_status IN ('Scheduled','In Progress') AND m.tbr_meeting_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) $global_scope"))[0] ?? 0);
    $metric_overdue = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM tbr_meetings m JOIN clients c ON m.tbr_client_id = c.client_id WHERE m.tbr_archived_at IS NULL AND c.client_archived_at IS NULL AND m.tbr_status = 'Scheduled' AND m.tbr_meeting_date < CURDATE() $global_scope"))[0] ?? 0);
    $metric_completed = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM tbr_meetings m JOIN clients c ON m.tbr_client_id = c.client_id WHERE m.tbr_archived_at IS NULL AND c.client_archived_at IS NULL AND m.tbr_status = 'Completed' $global_scope"))[0] ?? 0);
    $metric_followup = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM tbr_meetings m JOIN clients c ON m.tbr_client_id = c.client_id WHERE m.tbr_archived_at IS NULL AND c.client_archived_at IS NULL AND (EXISTS (SELECT 1 FROM tbr_action_items ai WHERE ai.action_tbr_id = m.tbr_id AND ai.action_status IN ('Not Started','In Progress','Waiting on Client')) OR (m.tbr_status = 'Completed' AND m.tbr_client_report_approved = 0)) $global_scope"))[0] ?? 0);

    $clients_without_tbr = [];
    $res_no_tbr = mysqli_query($mysqli, "
        SELECT c.client_id, c.client_name, c.client_type,
               (SELECT MAX(pm.tbr_meeting_date) FROM tbr_meetings pm WHERE pm.tbr_client_id = c.client_id AND pm.tbr_archived_at IS NULL AND pm.tbr_status = 'Completed') AS last_completed_tbr
        FROM clients c
        WHERE c.client_lead = 0
          AND c.client_archived_at IS NULL
          $client_list_scope
          AND NOT EXISTS (
              SELECT 1 FROM tbr_meetings m
              WHERE m.tbr_client_id = c.client_id
                AND m.tbr_archived_at IS NULL
                AND m.tbr_status IN ('Scheduled', 'In Progress')
          )
        ORDER BY c.client_name ASC
    ");
    while ($res_no_tbr && ($nr = mysqli_fetch_assoc($res_no_tbr))) {
        $clients_without_tbr[] = $nr;
    }
    $metric_no_tbr = count($clients_without_tbr);

    $all_clients_filter = [];
    $rc = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients c WHERE client_lead = 0 AND client_archived_at IS NULL $client_list_scope ORDER BY client_name ASC");
    while ($rc && ($cr = mysqli_fetch_assoc($rc))) {
        $all_clients_filter[] = $cr;
    }
    $all_users_filter = [];
    $ru = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
    while ($ru && ($ur = mysqli_fetch_assoc($ru))) {
        $all_users_filter[] = $ur;
    }
}
?>

<?php if (!$is_client_mode) { ?>
    <div class="row mb-3">
        <div class="col-6 col-md-4 col-xl-2">
            <a href="?quick=scheduled" class="text-decoration-none">
                <div class="small-box bg-primary mb-2">
                    <div class="inner">
                        <h3><?= $metric_total_scheduled ?></h3>
                        <p class="mb-0">Total Scheduled</p>
                    </div>
                    <div class="icon"><i class="fas fa-calendar-check"></i></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="?quick=upcoming" class="text-decoration-none">
                <div class="small-box bg-info mb-2">
                    <div class="inner">
                        <h3><?= $metric_upcoming ?></h3>
                        <p class="mb-0">Upcoming (30d)</p>
                    </div>
                    <div class="icon"><i class="fas fa-clock"></i></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="?quick=overdue" class="text-decoration-none">
                <div class="small-box bg-danger mb-2">
                    <div class="inner">
                        <h3><?= $metric_overdue ?></h3>
                        <p class="mb-0">Overdue TBRs</p>
                    </div>
                    <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="?quick=completed" class="text-decoration-none">
                <div class="small-box bg-success mb-2">
                    <div class="inner">
                        <h3><?= $metric_completed ?></h3>
                        <p class="mb-0">Completed</p>
                    </div>
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="?quick=followup" class="text-decoration-none">
                <div class="small-box bg-warning mb-2">
                    <div class="inner">
                        <h3><?= $metric_followup ?></h3>
                        <p class="mb-0">Requiring Follow-Up</p>
                    </div>
                    <div class="icon"><i class="fas fa-tasks"></i></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="#clientsWithoutTbrModal" data-toggle="modal" class="text-decoration-none">
                <div class="small-box bg-secondary mb-2">
                    <div class="inner">
                        <h3><?= $metric_no_tbr ?></h3>
                        <p class="mb-0">Clients w/o TBR</p>
                    </div>
                    <div class="icon"><i class="fas fa-user-clock"></i></div>
                </div>
            </a>
        </div>
    </div>
<?php } ?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2">
            <i class="fas fa-fw fa-chart-line mr-2"></i>
            <?= $is_client_mode ? 'Technology Business Reviews — ' . $client_name : 'Technology Business Reviews (TBR Management)' ?>
        </h3>
        <div class="card-tools">
            <?php if (!$is_client_mode && tbrHasPermission('manage_settings')) { ?>
                <a href="/admin/settings_tbr.php" class="btn btn-outline-light btn-sm mr-2">
                    <i class="fas fa-cog mr-1"></i>Settings
                </a>
            <?php } ?>
            <?php if (tbrHasPermission('schedule')) { ?>
                <button type="button" class="btn btn-primary btn-sm ajax-modal" data-modal-url="/agent/modals/tbr/tbr_schedule.php<?= $is_client_mode ? '?client_id=' . $client_id : '' ?>" data-modal-size="lg">
                    <i class="fas fa-calendar-plus mr-2"></i>Schedule New TBR
                </button>
            <?php } ?>
        </div>
    </div>

    <div class="card-body">
        <?php if ($is_client_mode) { ?>
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-client-meetings"><i class="fas fa-calendar-alt mr-1"></i>TBR Meetings</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-client-actions"><i class="fas fa-tasks mr-1"></i>Outstanding Action Items</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-client-recs"><i class="fas fa-lightbulb mr-1"></i>Recommendations History</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-client-findings"><i class="fas fa-shield-alt mr-1"></i>Previous Findings</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-client-roadmaps"><i class="fas fa-road mr-1"></i>Historical Roadmaps</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-client-reports"><i class="fas fa-file-pdf mr-1"></i>Completed Reports</a></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-client-meetings">
        <?php } ?>

        <form autocomplete="off" class="mb-3">
            <?php if ($is_client_mode) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?= escapeHtml(stripslashes($q)) ?>" placeholder="Search TBR title, client, rep...">
                        <div class="input-group-append">
                            <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <?php if (!$is_client_mode) { ?>
                    <div class="col-md-2 mb-2">
                        <select class="form-control select2" name="filter_client_id" onchange="this.form.submit()">
                            <option value="0">- All Clients -</option>
                            <?php foreach ($all_clients_filter as $cf) { ?>
                                <option value="<?= intval($cf['client_id']) ?>" <?= $filter_client_id === intval($cf['client_id']) ? 'selected' : '' ?>><?= escapeHtml($cf['client_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
                <div class="col-md-2 mb-2">
                    <select class="form-control" name="status" onchange="this.form.submit()">
                        <option value="">- All Statuses -</option>
                        <?php foreach (['Scheduled', 'In Progress', 'Completed', 'Cancelled'] as $st) { ?>
                            <option value="<?= $st ?>" <?= $filter_status === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <select class="form-control" name="type" onchange="this.form.submit()">
                        <option value="">- All Types -</option>
                        <?php foreach (['Quarterly', 'Annual', 'Special'] as $tp) { ?>
                            <option value="<?= $tp ?>" <?= $filter_type === $tp ? 'selected' : '' ?>><?= $tp ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php if (!$is_client_mode) { ?>
                    <div class="col-md-2 mb-2">
                        <select class="form-control" name="assigned_user_id" onchange="this.form.submit()">
                            <option value="0">- All Reps -</option>
                            <?php foreach ($all_users_filter as $uf) { ?>
                                <option value="<?= intval($uf['user_id']) ?>" <?= $filter_user_id === intval($uf['user_id']) ? 'selected' : '' ?>><?= escapeHtml($uf['user_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
                <div class="col-md-<?= $is_client_mode ? '5' : '1' ?> mb-2">
                    <div class="d-flex">
                        <button type="button" class="btn btn-outline-secondary btn-block" data-toggle="collapse" data-target="#tbrDateFilterRow" title="Date Range Filter">
                            <i class="fas fa-calendar"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="collapse <?= (!empty($_GET['dtf']) || !empty($_GET['dtt'])) ? 'show' : '' ?> mt-2" id="tbrDateFilterRow">
                <div class="row">
                    <div class="col-md-3">
                        <input type="date" class="form-control" name="dtf" value="<?= escapeHtml($_GET['dtf'] ?? '') ?>" placeholder="From">
                    </div>
                    <div class="col-md-3">
                        <input type="date" class="form-control" name="dtt" value="<?= escapeHtml($_GET['dtt'] ?? '') ?>" placeholder="To">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-secondary">Apply Dates</button>
                        <a href="<?= $is_client_mode ? "?client_id=$client_id" : 'tbrs.php' ?>" class="btn btn-link">Reset</a>
                    </div>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark">
                    <tr>
                        <?php if (!$is_client_mode) { ?>
                            <th><a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">Client</a></th>
                        <?php } ?>
                        <th>Title</th>
                        <th><a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=tbr_meeting_date&order=<?= $disp ?>">Meeting Date</a></th>
                        <th>Meeting Time</th>
                        <th><a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=tbr_type&order=<?= $disp ?>">Meeting Type</a></th>
                        <th><a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=tbr_status&order=<?= $disp ?>">Status</a></th>
                        <th><a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=rep_name&order=<?= $disp ?>">Assigned To</a></th>
                        <th>Last TBR</th>
                        <th>Progress</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($sql) === 0) { ?>
                        <tr>
                            <td colspan="<?= $is_client_mode ? 9 : 10 ?>" class="text-center text-muted py-4">
                                No Technology Business Reviews found matching your criteria.
                            </td>
                        </tr>
                    <?php } else {
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $tid = intval($row['tbr_id']);
                            $cid = intval($row['tbr_client_id']);
                            $prog = tbrCalculateProgress($tid);
                            $pct = $prog['percentage'];
                            $latest_client_rep = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT report_id FROM tbr_reports WHERE report_tbr_id = $tid AND report_type = 'client' ORDER BY report_id DESC LIMIT 1"));
                    ?>
                        <tr>
                            <?php if (!$is_client_mode) { ?>
                                <td>
                                    <a href="/agent/tbrs.php?client_id=<?= $cid ?>" class="font-weight-bold text-dark">
                                        <?= escapeHtml($row['client_name']) ?>
                                    </a>
                                </td>
                            <?php } ?>
                            <td>
                                <a href="/agent/tbr.php?tbr_id=<?= $tid ?>" class="font-weight-bold">
                                    <?= escapeHtml($row['tbr_title']) ?>
                                </a>
                                <?php if (intval($row['open_actions_count']) > 0) { ?>
                                    <span class="badge badge-light border ml-1" title="Open Action Items"><?= intval($row['open_actions_count']) ?> open</span>
                                <?php } ?>
                            </td>
                            <td><?= date('M j, Y', strtotime($row['tbr_meeting_date'])) ?></td>
                            <td><?= date('g:i A', strtotime($row['tbr_start_time'])) ?> <small class="text-muted">(<?= intval($row['tbr_duration_minutes']) ?>m)</small></td>
                            <td><span class="badge badge-light border"><?= escapeHtml($row['tbr_type']) ?></span></td>
                            <td><?= $status_badge($row['tbr_status'], $row['tbr_meeting_date']) ?></td>
                            <td><?= escapeHtml($row['rep_name'] ?: 'Unassigned') ?></td>
                            <td><?= !empty($row['last_tbr_date']) ? date('M j, Y', strtotime($row['last_tbr_date'])) : '<span class="text-muted">-</span>' ?></td>
                            <td style="min-width:110px;">
                                <div class="progress progress-xs mt-1">
                                    <div class="progress-bar bg-<?= $pct === 100 ? 'success' : ($pct >= 50 ? 'info' : 'warning') ?>" style="width: <?= $pct ?>%"></div>
                                </div>
                                <small class="text-muted"><?= $pct ?>% (<?= $prog['done'] ?>/<?= $prog['total'] ?>)</small>
                            </td>
                            <td class="text-center text-nowrap">
                                <div class="btn-group btn-group-sm">
                                    <a href="/agent/tbr.php?tbr_id=<?= $tid ?>" class="btn btn-outline-secondary" title="View TBR">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (tbrHasPermission('edit') && in_array($row['tbr_status'], ['Scheduled', 'In Progress'], true)) { ?>
                                        <a href="/agent/post.php?start_tbr=<?= $tid ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-outline-primary" title="<?= $row['tbr_status'] === 'Scheduled' ? 'Start TBR' : 'Resume TBR' ?>">
                                            <i class="fas fa-play"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="/agent/modals/tbr/tbr_schedule.php?tbr_id=<?= $tid ?>" data-modal-size="lg" title="Edit Schedule">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php } ?>
                                    <?php if (tbrHasPermission('generate_pdf')) { ?>
                                        <?php if ($latest_client_rep) { ?>
                                            <a href="/agent/tbr_report.php?action=download&report_id=<?= intval($latest_client_rep['report_id']) ?>" class="btn btn-outline-success" title="Export Latest Client PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        <?php } elseif (!empty($row['tbr_client_report_approved'])) { ?>
                                            <a href="/agent/tbr_report.php?action=generate&tbr_id=<?= $tid ?>&mode=client&disposition=attachment" class="btn btn-outline-success" title="Generate & Download Client PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        <?php } elseif (tbrHasPermission('view_internal_notes')) { ?>
                                            <a href="/agent/tbr_report.php?action=generate&tbr_id=<?= $tid ?>&mode=internal&disposition=attachment" class="btn btn-outline-dark" title="Export Internal PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        <?php } ?>
                                    <?php } ?>
                                    <?php if (tbrHasPermission('edit') && $row['tbr_status'] === 'Scheduled') { ?>
                                        <a href="/agent/post.php?cancel_tbr=<?= $tid ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-outline-danger confirm-link" title="Cancel TBR">
                                            <i class="fas fa-ban"></i>
                                        </a>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </div>

        <?php require_once "../includes/filter_footer.php"; ?>

        <?php if ($is_client_mode) {
            $hist_actions = mysqli_query($mysqli, "SELECT a.*, m.tbr_title, m.tbr_meeting_date, u.user_name FROM tbr_action_items a JOIN tbr_meetings m ON a.action_tbr_id = m.tbr_id LEFT JOIN users u ON a.action_assigned_user_id = u.user_id WHERE a.action_client_id = $client_id AND m.tbr_archived_at IS NULL AND a.action_status IN ('Not Started','In Progress','Waiting on Client') ORDER BY a.action_due_date ASC, a.action_id DESC");
            $hist_recs = mysqli_query($mysqli, "SELECT r.*, m.tbr_title, m.tbr_meeting_date FROM tbr_recommendations r JOIN tbr_meetings m ON r.recommendation_tbr_id = m.tbr_id WHERE r.recommendation_client_id = $client_id AND m.tbr_archived_at IS NULL ORDER BY m.tbr_meeting_date DESC, r.recommendation_id DESC");
            $hist_findings = mysqli_query($mysqli, "SELECT f.*, m.tbr_title, m.tbr_meeting_date FROM tbr_findings f JOIN tbr_meetings m ON f.finding_tbr_id = m.tbr_id WHERE f.finding_client_id = $client_id AND m.tbr_archived_at IS NULL ORDER BY m.tbr_meeting_date DESC, FIELD(f.finding_risk_level,'Critical','High','Moderate','Low')");
            $hist_roadmaps = mysqli_query($mysqli, "SELECT rm.*, m.tbr_title, m.tbr_meeting_date FROM tbr_roadmap_items rm JOIN tbr_meetings m ON rm.roadmap_tbr_id = m.tbr_id WHERE rm.roadmap_client_id = $client_id AND m.tbr_archived_at IS NULL ORDER BY rm.roadmap_fiscal_year DESC, FIELD(rm.roadmap_quarter,'Q1','Q2','Q3','Q4')");
            $rep_filter = tbrHasPermission('view_internal_notes') ? '' : " AND rp.report_type = 'client' ";
            $hist_reports = mysqli_query($mysqli, "SELECT rp.*, m.tbr_title, m.tbr_meeting_date, u.user_name FROM tbr_reports rp JOIN tbr_meetings m ON rp.report_tbr_id = m.tbr_id LEFT JOIN users u ON rp.report_generated_by = u.user_id WHERE rp.report_client_id = $client_id AND m.tbr_archived_at IS NULL $rep_filter ORDER BY rp.report_generated_at DESC");
        ?>
                </div>
                <div class="tab-pane fade" id="tab-client-actions">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>TBR</th><th>Action Item</th><th>Party</th><th>Owner</th><th>Priority</th><th>Due Date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if ($hist_actions && mysqli_num_rows($hist_actions) > 0) {
                                while ($ha = mysqli_fetch_assoc($hist_actions)) { ?>
                                <tr>
                                    <td><a href="/agent/tbr.php?tbr_id=<?= intval($ha['action_tbr_id']) ?>#sec_10"><?= escapeHtml($ha['tbr_title']) ?></a></td>
                                    <td><?= escapeHtml($ha['action_description']) ?></td>
                                    <td><?= escapeHtml($ha['action_responsible_party']) ?></td>
                                    <td><?= escapeHtml($ha['action_responsible_party'] === 'Client' ? ($ha['action_client_owner'] ?: 'Client') : ($ha['user_name'] ?: 'MSP')) ?></td>
                                    <td><span class="badge badge-info"><?= escapeHtml($ha['action_priority']) ?></span></td>
                                    <td><?= escapeHtml($ha['action_due_date'] ?: '-') ?></td>
                                    <td><span class="badge badge-warning"><?= escapeHtml($ha['action_status']) ?></span></td>
                                </tr>
                            <?php } } else { ?>
                                <tr><td colspan="7" class="text-center text-muted py-3">No outstanding action items.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="tab-client-recs">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>TBR Date</th><th>Recommendation</th><th>Priority</th><th>Est. Cost</th><th>Timeline</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if ($hist_recs && mysqli_num_rows($hist_recs) > 0) {
                                while ($hr = mysqli_fetch_assoc($hist_recs)) { ?>
                                <tr>
                                    <td><a href="/agent/tbr.php?tbr_id=<?= intval($hr['recommendation_tbr_id']) ?>#sec_07"><?= escapeHtml($hr['tbr_meeting_date']) ?></a></td>
                                    <td><strong><?= escapeHtml($hr['recommendation_title']) ?></strong><br><small class="text-muted"><?= escapeHtml($hr['recommendation_justification'] ?? '') ?></small></td>
                                    <td><?= escapeHtml($hr['recommendation_priority']) ?></td>
                                    <td><?= $hr['recommendation_estimated_cost'] !== null ? number_format(floatval($hr['recommendation_estimated_cost']), 2) : '-' ?></td>
                                    <td><?= escapeHtml($hr['recommendation_timeline'] ?: '-') ?></td>
                                    <td><span class="badge badge-secondary"><?= escapeHtml($hr['recommendation_status']) ?></span></td>
                                </tr>
                            <?php } } else { ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">No historical recommendations recorded.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="tab-client-findings">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>TBR Date</th><th>Finding</th><th>Risk Level</th><th>Status</th><th>Recommended Action</th></tr></thead>
                        <tbody>
                            <?php if ($hist_findings && mysqli_num_rows($hist_findings) > 0) {
                                while ($hf = mysqli_fetch_assoc($hist_findings)) { ?>
                                <tr>
                                    <td><a href="/agent/tbr.php?tbr_id=<?= intval($hf['finding_tbr_id']) ?>#<?= escapeHtml($hf['finding_section']) ?>"><?= escapeHtml($hf['tbr_meeting_date']) ?></a></td>
                                    <td><strong><?= escapeHtml($hf['finding_title']) ?></strong></td>
                                    <td><span class="badge badge-<?= $hf['finding_risk_level'] === 'Critical' ? 'danger' : ($hf['finding_risk_level'] === 'High' ? 'warning' : 'info') ?>"><?= escapeHtml($hf['finding_risk_level']) ?></span></td>
                                    <td><?= escapeHtml($hf['finding_status']) ?></td>
                                    <td><?= escapeHtml($hf['finding_recommended_action'] ?: '-') ?></td>
                                </tr>
                            <?php } } else { ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No previous findings recorded.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="tab-client-roadmaps">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>TBR</th><th>Quarter</th><th>Project</th><th>Category</th><th>Est. Cost</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if ($hist_roadmaps && mysqli_num_rows($hist_roadmaps) > 0) {
                                while ($hrm = mysqli_fetch_assoc($hist_roadmaps)) { ?>
                                <tr>
                                    <td><a href="/agent/tbr.php?tbr_id=<?= intval($hrm['roadmap_tbr_id']) ?>#sec_08"><?= escapeHtml($hrm['tbr_title']) ?></a></td>
                                    <td><?= escapeHtml($hrm['roadmap_quarter'] . ' ' . $hrm['roadmap_fiscal_year']) ?></td>
                                    <td><?= escapeHtml($hrm['roadmap_project_name']) ?></td>
                                    <td><?= escapeHtml($hrm['roadmap_category']) ?></td>
                                    <td><?= number_format(floatval($hrm['roadmap_estimated_cost']), 2) ?></td>
                                    <td><?= escapeHtml($hrm['roadmap_status']) ?></td>
                                </tr>
                            <?php } } else { ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">No historical roadmap items recorded.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="tab-client-reports">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>TBR</th><th>Type</th><th>Version</th><th>Generated</th><th>Generated By</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if ($hist_reports && mysqli_num_rows($hist_reports) > 0) {
                                while ($hrp = mysqli_fetch_assoc($hist_reports)) { ?>
                                <tr>
                                    <td><?= escapeHtml($hrp['tbr_title']) ?> (<?= escapeHtml($hrp['tbr_meeting_date']) ?>)</td>
                                    <td><span class="badge badge-<?= $hrp['report_type'] === 'internal' ? 'danger' : 'primary' ?>"><?= ucfirst(escapeHtml($hrp['report_type'])) ?></span></td>
                                    <td>v<?= intval($hrp['report_version']) ?></td>
                                    <td><?= escapeHtml($hrp['report_generated_at']) ?></td>
                                    <td><?= escapeHtml($hrp['user_name'] ?: '-') ?></td>
                                    <td>
                                        <a href="/agent/tbr_report.php?action=preview&report_id=<?= intval($hrp['report_id']) ?>" target="_blank" class="btn btn-xs btn-outline-primary"><i class="fas fa-eye mr-1"></i>Preview</a>
                                        <a href="/agent/tbr_report.php?action=download&report_id=<?= intval($hrp['report_id']) ?>" class="btn btn-xs btn-outline-success"><i class="fas fa-download mr-1"></i>Download</a>
                                    </td>
                                </tr>
                            <?php } } else { ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">No PDF reports generated yet.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<?php if (!$is_client_mode) { ?>
<div class="modal fade" id="clientsWithoutTbrModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-user-clock mr-2"></i>Clients Without a Scheduled TBR (<?= $metric_no_tbr ?>)</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-striped mb-0">
                    <thead><tr><th>Client</th><th>Type</th><th>Last Completed TBR</th><th class="text-right">Action</th></tr></thead>
                    <tbody>
                        <?php if (empty($clients_without_tbr)) { ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Every active client has a scheduled or in-progress TBR.</td></tr>
                        <?php } else {
                            foreach ($clients_without_tbr as $nct) { ?>
                            <tr>
                                <td><a href="/agent/tbrs.php?client_id=<?= intval($nct['client_id']) ?>" class="font-weight-bold"><?= escapeHtml($nct['client_name']) ?></a></td>
                                <td><?= escapeHtml($nct['client_type'] ?: '-') ?></td>
                                <td><?= !empty($nct['last_completed_tbr']) ? date('M j, Y', strtotime($nct['last_completed_tbr'])) : '<span class="text-danger">Never</span>' ?></td>
                                <td class="text-right">
                                    <?php if (tbrHasPermission('schedule')) { ?>
                                        <button type="button" class="btn btn-xs btn-primary ajax-modal" data-dismiss="modal" data-modal-url="/agent/modals/tbr/tbr_schedule.php?client_id=<?= intval($nct['client_id']) ?>" data-modal-size="lg">
                                            <i class="fas fa-calendar-plus mr-1"></i>Schedule TBR
                                        </button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php require_once "../includes/footer.php";
