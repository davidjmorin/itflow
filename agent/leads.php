<?php

require_once "includes/inc_all.php";

enforceUserPermission('module_client');

$view = $_GET['view'] ?? 'kanban';
$query_search = escapeSql($_GET['q'] ?? '');
$filter_stage = isset($_GET['stage_id']) && $_GET['stage_id'] !== '' ? intval($_GET['stage_id']) : null;
$filter_user = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? intval($_GET['user_id']) : null;
$filter_status = escapeSql($_GET['status'] ?? 'Open');

$where_clauses = ["lead_archived_at IS NULL"];

if ($filter_status === 'Open') {
    $where_clauses[] = "lead_status = 'Open'";
} elseif ($filter_status === 'Converted') {
    $where_clauses[] = "lead_status = 'Converted'";
} elseif ($filter_status === 'Lost') {
    $where_clauses[] = "lead_status = 'Lost'";
}

if (!empty($query_search)) {
    $where_clauses[] = "(lead_name LIKE '%$query_search%' OR lead_contact_name LIKE '%$query_search%' OR lead_contact_email LIKE '%$query_search%' OR lead_contact_phone LIKE '%$query_search%')";
}

if ($filter_stage !== null) {
    $where_clauses[] = "leads.lead_stage_id = $filter_stage";
}

if ($filter_user !== null) {
    $where_clauses[] = "lead_assigned_user_id = $filter_user";
}

$where_sql = implode(' AND ', $where_clauses);

$stat_active = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(lead_id) AS cnt, SUM(lead_estimated_mrr) AS total_mrr, SUM(lead_estimated_seats) AS total_seats FROM leads WHERE lead_status = 'Open' AND lead_archived_at IS NULL"));
$stat_converted = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(lead_id) AS cnt FROM leads WHERE lead_status = 'Converted' AND lead_archived_at IS NULL"));

$stages_res = mysqli_query($mysqli, "SELECT * FROM lead_stages WHERE lead_stage_active = 1 ORDER BY lead_stage_order ASC");
$stages = [];
while ($st = mysqli_fetch_assoc($stages_res)) {
    $stages[$st['lead_stage_id']] = $st;
}

?>

<link rel="stylesheet" href="css/leads.css">

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= intval($stat_active['cnt'] ?? 0) ?></h3>
                <p>Active Pipeline Leads</p>
            </div>
            <div class="icon">
                <i class="fas fa-funnel-dollar"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= numfmt_format_currency($currency_format, floatval($stat_active['total_mrr'] ?? 0), $session_company_currency) ?></h3>
                <p>Pipeline Monthly Recurring (MRR)</p>
            </div>
            <div class="icon">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?= intval($stat_active['total_seats'] ?? 0) ?></h3>
                <p>Potential Endpoints / Seats</p>
            </div>
            <div class="icon">
                <i class="fas fa-laptop"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3><?= intval($stat_converted['cnt'] ?? 0) ?></h3>
                <p>Won & Converted Clients</p>
            </div>
            <div class="icon">
                <i class="fas fa-user-check"></i>
            </div>
        </div>
    </div>
</div>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-funnel-dollar mr-2"></i>Sales Leads</h3>
        <div class="card-tools">
            <a href="lead_email_templates.php" class="btn btn-outline-light btn-sm mr-2">
                <i class="fas fa-envelope-open-text mr-1"></i>Email Templates
            </a>
            <?php if (lookupUserPermission("module_client") >= 2) { ?>
                <button type="button" class="btn btn-primary btn-sm ajax-modal" data-modal-url="modals/lead/lead_add.php" data-modal-size="lg">
                    <i class="fas fa-plus mr-1"></i>New Lead
                </button>
            <?php } ?>
        </div>
    </div>
    <div class="card-body p-3">
        <form method="get" class="mb-3">
            <input type="hidden" name="view" value="<?= escapeHtml($view) ?>">
            <div class="form-row align-items-center">
                <div class="col-md-3 my-1">
                    <div class="input-group">
                        <input type="text" class="form-control" name="q" value="<?= escapeHtml($query_search) ?>" placeholder="Search company, contact, email...">
                        <div class="input-group-append">
                            <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 my-1">
                    <select class="form-control" name="stage_id" onchange="this.form.submit()">
                        <option value="">- All Stages -</option>
                        <?php foreach ($stages as $s_id => $st) { ?>
                            <option value="<?= $s_id ?>" <?= $filter_stage === $s_id ? 'selected' : '' ?>><?= escapeHtml($st['lead_stage_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-2 my-1">
                    <select class="form-control" name="status" onchange="this.form.submit()">
                        <option value="Open" <?= $filter_status === 'Open' ? 'selected' : '' ?>>Open Leads</option>
                        <option value="Converted" <?= $filter_status === 'Converted' ? 'selected' : '' ?>>Converted</option>
                        <option value="Lost" <?= $filter_status === 'Lost' ? 'selected' : '' ?>>Lost / Disqualified</option>
                        <option value="All" <?= $filter_status === 'All' ? 'selected' : '' ?>>All Statuses</option>
                    </select>
                </div>
                <div class="col-md-2 my-1">
                    <select class="form-control" name="user_id" onchange="this.form.submit()">
                        <option value="">- All Agents -</option>
                        <?php
                        $agents_query = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
                        while ($agent = mysqli_fetch_assoc($agents_query)) {
                            $a_id = intval($agent['user_id']);
                            $a_name = escapeHtml($agent['user_name']);
                            $selected = $filter_user === $a_id ? 'selected' : '';
                            echo "<option value='$a_id' $selected>$a_name</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3 my-1 text-md-right">
                    <div class="btn-group">
                        <a href="?<?= http_build_query(array_merge($_GET, ['view' => 'kanban'])) ?>" class="btn btn-sm <?= $view === 'kanban' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                            <i class="fas fa-columns mr-1"></i>Board
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['view' => 'list'])) ?>" class="btn btn-sm <?= $view === 'list' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                            <i class="fas fa-list mr-1"></i>List
                        </a>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($view === 'kanban') { ?>
            <div class="lead-kanban-board" id="lead-kanban-board">
                <?php foreach ($stages as $stage_id => $st) {
                    $stage_color = escapeHtml($st['lead_stage_color'] ?: '#007bff');
                    $stage_leads_sql = mysqli_query($mysqli, "SELECT leads.*, users.user_name FROM leads LEFT JOIN users ON leads.lead_assigned_user_id = users.user_id WHERE $where_sql AND lead_stage_id = $stage_id ORDER BY lead_id DESC");
                    $stage_lead_count = mysqli_num_rows($stage_leads_sql);
                ?>
                    <div class="lead-kanban-column" data-stage-id="<?= $stage_id ?>">
                        <div class="lead-kanban-header text-white" style="background-color: <?= $stage_color ?>;">
                            <span><?= escapeHtml($st['lead_stage_name']) ?></span>
                            <span class="badge badge-light lead-stage-count"><?= $stage_lead_count ?></span>
                        </div>
                        <div class="lead-kanban-stage-list" data-stage-id="<?= $stage_id ?>">
                            <?php while ($lead = mysqli_fetch_assoc($stage_leads_sql)) {
                                $l_id = intval($lead['lead_id']);
                                $mrr_display = floatval($lead['lead_estimated_mrr']) > 0 ? numfmt_format_currency($currency_format, floatval($lead['lead_estimated_mrr']), $session_company_currency) : '';
                            ?>
                                <div class="lead-card" data-lead-id="<?= $l_id ?>" style="border-left-color: <?= $stage_color ?>;">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <a href="lead.php?lead_id=<?= $l_id ?>" class="lead-card-title text-dark">
                                            <?= escapeHtml($lead['lead_name']) ?>
                                        </a>
                                        <div class="dropdown dropleft">
                                            <button class="btn btn-link btn-sm text-secondary p-0" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="lead.php?lead_id=<?= $l_id ?>"><i class="fas fa-eye mr-2"></i>View Lead</a>
                                                <?php if ($lead['lead_status'] === 'Open') { ?>
                                                    <a class="dropdown-item text-success ajax-modal" href="#" data-modal-url="modals/lead/lead_convert.php?lead_id=<?= $l_id ?>" data-modal-size="lg"><i class="fas fa-user-check mr-2"></i>Convert to Client</a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (!empty($lead['lead_contact_name'])) { ?>
                                        <div class="lead-card-contact">
                                            <i class="fas fa-user mr-1"></i><?= escapeHtml($lead['lead_contact_name']) ?>
                                            <?php if (!empty($lead['lead_contact_title'])) { ?>
                                                <span class="text-muted">(<?= escapeHtml($lead['lead_contact_title']) ?>)</span>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>

                                    <div class="lead-card-badges">
                                        <?php if ($mrr_display) { ?>
                                            <span class="badge badge-success"><i class="fas fa-dollar-sign mr-1"></i><?= $mrr_display ?>/mo</span>
                                        <?php } ?>
                                        <?php if (intval($lead['lead_estimated_seats']) > 0) { ?>
                                            <span class="badge badge-info"><i class="fas fa-laptop mr-1"></i><?= intval($lead['lead_estimated_seats']) ?> seats</span>
                                        <?php } ?>
                                        <?php if (!empty($lead['lead_source'])) { ?>
                                            <span class="badge badge-secondary"><?= escapeHtml($lead['lead_source']) ?></span>
                                        <?php } ?>
                                    </div>

                                    <?php if (!empty($lead['lead_next_follow_up'])) {
                                        $follow_due = strtotime($lead['lead_next_follow_up']) < strtotime('today');
                                    ?>
                                        <div class="mb-2 font-weight-bold" style="font-size: 0.8rem;">
                                            <span class="<?= $follow_due ? 'text-danger' : 'text-primary' ?>">
                                                <i class="far fa-calendar-alt mr-1"></i>Follow-up: <?= escapeHtml($lead['lead_next_follow_up']) ?>
                                            </span>
                                        </div>
                                    <?php } ?>

                                    <div class="lead-card-footer">
                                        <span>
                                            <i class="fas fa-user-circle mr-1"></i><?= escapeHtml($lead['user_name'] ?: 'Unassigned') ?>
                                        </span>
                                        <span><?= timeAgo($lead['lead_created_at']) ?></span>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Company / Lead</th>
                            <th>Contact</th>
                            <th>Stage</th>
                            <th>Status</th>
                            <th>Estimated MRR</th>
                            <th>Seats</th>
                            <th>Assigned Agent</th>
                            <th>Next Follow-up</th>
                            <th>Created</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $list_sql = mysqli_query($mysqli, "SELECT leads.*, lead_stages.lead_stage_name, lead_stages.lead_stage_color, users.user_name FROM leads LEFT JOIN lead_stages ON leads.lead_stage_id = lead_stages.lead_stage_id LEFT JOIN users ON leads.lead_assigned_user_id = users.user_id WHERE $where_sql ORDER BY lead_id DESC");
                        if (mysqli_num_rows($list_sql) === 0) {
                            echo "<tr><td colspan='10' class='text-center text-muted py-4'>No leads found matching criteria.</td></tr>";
                        }
                        while ($row = mysqli_fetch_assoc($list_sql)) {
                            $l_id = intval($row['lead_id']);
                            $stage_bg = escapeHtml($row['lead_stage_color'] ?: '#6c757d');
                        ?>
                            <tr>
                                <td class="font-weight-bold">
                                    <a href="lead.php?lead_id=<?= $l_id ?>"><?= escapeHtml($row['lead_name']) ?></a>
                                    <?php if (!empty($row['lead_website'])) { ?>
                                        <br><small class="text-muted"><i class="fas fa-globe mr-1"></i><?= escapeHtml($row['lead_website']) ?></small>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?= escapeHtml($row['lead_contact_name'] ?: '-') ?>
                                    <?php if (!empty($row['lead_contact_email'])) { ?>
                                        <br><small><a href="mailto:<?= escapeHtml($row['lead_contact_email']) ?>"><?= escapeHtml($row['lead_contact_email']) ?></a></small>
                                    <?php } ?>
                                </td>
                                <td>
                                    <span class="badge text-white" style="background-color: <?= $stage_bg ?>;">
                                        <?= escapeHtml($row['lead_stage_name'] ?: 'Unknown') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['lead_status'] === 'Converted') { ?>
                                        <span class="badge badge-success">Converted</span>
                                    <?php } elseif ($row['lead_status'] === 'Lost') { ?>
                                        <span class="badge badge-danger">Lost</span>
                                    <?php } else { ?>
                                        <span class="badge badge-info">Open</span>
                                    <?php } ?>
                                </td>
                                <td><?= floatval($row['lead_estimated_mrr']) > 0 ? numfmt_format_currency($currency_format, floatval($row['lead_estimated_mrr']), $session_company_currency) : '-' ?></td>
                                <td><?= intval($row['lead_estimated_seats']) > 0 ? intval($row['lead_estimated_seats']) : '-' ?></td>
                                <td><?= escapeHtml($row['user_name'] ?: 'Unassigned') ?></td>
                                <td><?= escapeHtml($row['lead_next_follow_up'] ?: '-') ?></td>
                                <td><?= escapeHtml(date('Y-m-d', strtotime($row['lead_created_at']))) ?></td>
                                <td class="text-right">
                                    <a href="lead.php?lead_id=<?= $l_id ?>" class="btn btn-sm btn-light"><i class="fas fa-eye"></i></a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<script src="../libs/SortableJS/Sortable.min.js"></script>
<script src="js/leads_kanban.js"></script>

<?php
require_once "../includes/footer.php";
?>
