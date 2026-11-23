<?php

require_once "includes/inc_all.php";

tbrEnforcePermission('view');

$tbr_id = intval($_GET['tbr_id'] ?? 0);
$meeting = tbrGetMeeting($tbr_id);
if (!$meeting) {
    echo "<div class='alert alert-danger mt-4'>TBR meeting not found or archived. <a href='tbrs.php'>Return to TBR Dashboard</a></div>";
    require_once "../includes/footer.php";
    exit();
}

$client_id = intval($meeting['tbr_client_id']);
enforceClientAccess($client_id);

tbrInitializeAgenda($tbr_id, $session_user_id);

$can_edit = tbrHasPermission('edit') && !in_array($meeting['tbr_status'], ['Completed', 'Cancelled'], true);
$can_complete = tbrHasPermission('complete');
$can_reopen = tbrHasPermission('reopen');
$can_pdf = tbrHasPermission('generate_pdf');
$can_email = tbrHasPermission('email_report');
$can_internal = tbrHasPermission('view_internal_notes');
$can_support_write = lookupUserPermission('module_support') >= 2;
$disabled_attr = $can_edit ? '' : 'disabled';

$sections_cfg = tbrGetSectionsConfig();
$progress = tbrCalculateProgress($tbr_id);
$live_data = tbrFetchLiveClientData($client_id, $meeting['tbr_reporting_period_start'], $meeting['tbr_reporting_period_end']);

$responses = [];
$section_versions = [];
$res_resp = mysqli_query($mysqli, "SELECT * FROM tbr_section_responses WHERE response_tbr_id = $tbr_id");
while ($res_resp && ($r = mysqli_fetch_assoc($res_resp))) {
    $r['answers'] = json_decode((string)$r['answers_json'], true) ?: [];
    $responses[$r['section_key']] = $r;
    $section_versions[$r['section_key']] = intval($r['version']);
}

$attendees = [];
$res_att = mysqli_query($mysqli, "SELECT * FROM tbr_attendees WHERE attendee_tbr_id = $tbr_id ORDER BY attendee_type ASC, attendee_name ASC");
while ($res_att && ($a = mysqli_fetch_assoc($res_att))) {
    $attendees[] = $a;
}

$findings = [];
$res_f = mysqli_query($mysqli, "SELECT * FROM tbr_findings WHERE finding_tbr_id = $tbr_id ORDER BY FIELD(finding_risk_level,'Critical','High','Moderate','Low'), finding_id ASC");
while ($res_f && ($f = mysqli_fetch_assoc($res_f))) {
    if (!$can_internal) {
        unset($f['finding_internal_notes']);
    }
    $findings[] = $f;
}

$recommendations = [];
$res_rec = mysqli_query($mysqli, "SELECT * FROM tbr_recommendations WHERE recommendation_tbr_id = $tbr_id ORDER BY FIELD(recommendation_priority,'Critical','High','Medium','Low'), recommendation_id ASC");
while ($res_rec && ($rec = mysqli_fetch_assoc($res_rec))) {
    if (!$can_internal) {
        unset($rec['recommendation_internal_notes']);
    }
    $recommendations[] = $rec;
}

$roadmap_items = [];
$roadmap_totals = ['quarters' => ['Q1' => 0.0, 'Q2' => 0.0, 'Q3' => 0.0, 'Q4' => 0.0], 'categories' => ['Required Security' => 0.0, 'Hardware Replacement' => 0.0, 'Software & Licensing' => 0.0, 'Infrastructure Improvement' => 0.0, 'Optional Enhancement' => 0.0], 'annual' => 0.0];
$res_rm = mysqli_query($mysqli, "SELECT * FROM tbr_roadmap_items WHERE roadmap_tbr_id = $tbr_id ORDER BY roadmap_fiscal_year ASC, FIELD(roadmap_quarter,'Q1','Q2','Q3','Q4'), FIELD(roadmap_priority,'Critical','High','Medium','Low'), roadmap_id ASC");
while ($res_rm && ($rm = mysqli_fetch_assoc($res_rm))) {
    $cost = floatval($rm['roadmap_estimated_cost']);
    $roadmap_totals['quarters'][$rm['roadmap_quarter']] = ($roadmap_totals['quarters'][$rm['roadmap_quarter']] ?? 0) + $cost;
    $roadmap_totals['categories'][$rm['roadmap_category']] = ($roadmap_totals['categories'][$rm['roadmap_category']] ?? 0) + $cost;
    $roadmap_totals['annual'] += $cost;
    $roadmap_items[] = $rm;
}

$action_items = [];
$res_act = mysqli_query($mysqli, "
    SELECT a.*, u.user_name AS assigned_user_name, t.ticket_prefix, t.ticket_number
    FROM tbr_action_items a
    LEFT JOIN users u ON a.action_assigned_user_id = u.user_id
    LEFT JOIN tickets t ON a.action_ticket_id = t.ticket_id
    WHERE a.action_tbr_id = $tbr_id
    ORDER BY FIELD(a.action_priority,'Critical','High','Medium','Low'), a.action_due_date ASC, a.action_id ASC
");
while ($res_act && ($act = mysqli_fetch_assoc($res_act))) {
    if (!$can_internal) {
        unset($act['action_internal_notes']);
    }
    $action_items[] = $act;
}

$reports = [];
$rep_where = $can_internal ? "" : " AND r.report_type = 'client' ";
$res_rep = mysqli_query($mysqli, "SELECT r.*, u.user_name FROM tbr_reports r LEFT JOIN users u ON r.report_generated_by = u.user_id WHERE r.report_tbr_id = $tbr_id $rep_where ORDER BY r.report_id DESC");
while ($res_rep && ($rp = mysqli_fetch_assoc($res_rep))) {
    $reports[] = $rp;
}

$audit_logs = [];
$res_aud = mysqli_query($mysqli, "SELECT h.*, u.user_name FROM tbr_audit_history h LEFT JOIN users u ON h.audit_user_id = u.user_id WHERE h.audit_tbr_id = $tbr_id ORDER BY h.audit_created_at DESC, h.audit_id DESC LIMIT 100");
while ($res_aud && ($au = mysqli_fetch_assoc($res_aud))) {
    $audit_logs[] = $au;
}

$users = [];
$res_u = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
while ($res_u && ($u = mysqli_fetch_assoc($res_u))) {
    $users[] = $u;
}

$prev_tbr = !empty($meeting['tbr_previous_tbr_id'])
    ? tbrGetMeeting(intval($meeting['tbr_previous_tbr_id']))
    : tbrFindPreviousMeeting($client_id, $tbr_id);

$currency_code = $meeting['client_currency_code'] ?: ($session_company_currency ?: 'USD');
$status_badge_class = match ($meeting['tbr_status']) {
    'Scheduled' => 'primary',
    'In Progress' => 'warning',
    'Completed' => 'success',
    'Cancelled' => 'secondary',
    default => 'light',
};
$health_badge_class = match ($meeting['tbr_overall_health']) {
    'Good' => 'success',
    'Needs Attention' => 'warning',
    'Critical' => 'danger',
    default => 'secondary',
};
?>
<link rel="stylesheet" href="/agent/css/tbr.css?v=<?= filemtime(__DIR__ . '/css/tbr.css') ?>">

<div class="tbr-workspace-bar px-3 py-2 mb-3 rounded">
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div class="mb-2 mb-lg-0">
            <div class="small text-muted">
                <a href="/agent/tbrs.php"><i class="fas fa-arrow-left mr-1"></i>TBR Dashboard</a>
                &middot;
                <a href="/agent/tbrs.php?client_id=<?= $client_id ?>" class="font-weight-bold"><?= escapeHtml($meeting['client_name']) ?></a>
                &middot;
                <a href="/agent/client_overview.php?client_id=<?= $client_id ?>">Client Profile</a>
            </div>
            <h4 class="mb-0 mt-1">
                <strong><?= escapeHtml($meeting['tbr_title']) ?></strong>
                <span class="badge badge-<?= $status_badge_class ?> ml-1"><?= escapeHtml($meeting['tbr_status']) ?></span>
                <span class="badge badge-light border ml-1"><?= escapeHtml($meeting['tbr_type']) ?></span>
                <span class="badge badge-<?= $health_badge_class ?> ml-1" id="tbrHeaderHealthBadge"><?= escapeHtml($meeting['tbr_overall_health']) ?></span>
            </h4>
            <div class="small text-muted mt-1">
                <i class="far fa-calendar mr-1"></i><?= date('M j, Y', strtotime($meeting['tbr_meeting_date'])) ?> at <?= date('g:i A', strtotime($meeting['tbr_start_time'])) ?> (<?= intval($meeting['tbr_duration_minutes']) ?> min)
                <?php if (!empty($meeting['tbr_location'])) { ?>
                    &middot; <i class="fas fa-map-marker-alt mr-1"></i><?= escapeHtml($meeting['tbr_location']) ?>
                <?php } ?>
                &middot; <i class="fas fa-user-tie mr-1"></i><?= escapeHtml($meeting['rep_name'] ?: 'Unassigned') ?>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center" style="gap:0.5rem;">
            <div style="min-width:170px;" class="mr-2">
                <div class="d-flex justify-content-between small">
                    <span>Agenda Progress</span>
                    <strong id="tbrProgressText"><?= $progress['percentage'] ?>% (<?= $progress['done'] ?>/<?= $progress['total'] ?>)</strong>
                </div>
                <div class="progress progress-xs mt-1">
                    <div class="progress-bar bg-success" id="tbrProgressBar" style="width: <?= $progress['percentage'] ?>%"></div>
                </div>
            </div>

            <span id="tbrSaveIndicator" class="tbr-save-badge tbr-save-saved">
                <i class="fas fa-check-circle"></i> <?= $can_edit ? 'Saved' : 'Read-Only' ?>
            </span>

            <?php if ($meeting['tbr_status'] === 'Scheduled' && tbrHasPermission('edit')) { ?>
                <a href="/agent/post.php?start_tbr=<?= $tbr_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-play mr-1"></i>Start TBR
                </a>
            <?php } ?>

            <?php if ($can_edit) { ?>
                <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="/agent/modals/tbr/tbr_schedule.php?tbr_id=<?= $tbr_id ?>" data-modal-size="lg">
                    <i class="fas fa-edit mr-1"></i>Schedule
                </button>
            <?php } ?>

            <?php if ($can_complete && in_array($meeting['tbr_status'], ['Scheduled', 'In Progress'], true)) { ?>
                <button type="button" class="btn btn-sm btn-success" id="tbrOpenCompleteModalBtn">
                    <i class="fas fa-check-double mr-1"></i>Complete TBR
                </button>
            <?php } ?>

            <?php if ($meeting['tbr_status'] === 'Completed' && $can_reopen) { ?>
                <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#tbrReopenModal">
                    <i class="fas fa-unlock mr-1"></i>Reopen TBR
                </button>
            <?php } ?>

            <?php if ($can_pdf) { ?>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-dark dropdown-toggle" data-toggle="dropdown">
                        <i class="fas fa-file-pdf mr-1"></i>Reports
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item tbr-jump-panel" href="#panel-reports" data-target="panel-reports">
                            <i class="fas fa-clipboard-check mr-2"></i>Review, Approve &amp; Export
                        </a>
                        <?php if (!empty($meeting['tbr_client_report_approved'])) { ?>
                            <a class="dropdown-item" href="/agent/tbr_report.php?action=generate&tbr_id=<?= $tbr_id ?>&mode=client" target="_blank">
                                <i class="fas fa-file-pdf text-success mr-2"></i>Generate Client PDF
                            </a>
                        <?php } ?>
                        <?php if ($can_internal) { ?>
                            <a class="dropdown-item" href="/agent/tbr_report.php?action=generate&tbr_id=<?= $tbr_id ?>&mode=internal" target="_blank">
                                <i class="fas fa-user-secret text-danger mr-2"></i>Generate Internal MSP PDF
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div id="tbrConflictAlert" class="alert alert-danger d-none">
    <div class="d-flex align-items-center justify-content-between flex-wrap">
        <div>
            <strong><i class="fas fa-exclamation-triangle mr-1"></i>Simultaneous Edit Conflict Detected (<span id="tbrConflictSectionLabel"></span>):</strong>
            Another user or browser tab saved newer changes to this section.
        </div>
        <div class="mt-2 mt-md-0">
            <button type="button" class="btn btn-sm btn-light" id="tbrConflictReloadBtn">Reload Latest</button>
            <button type="button" class="btn btn-sm btn-outline-light" id="tbrConflictForceBtn">Keep My Changes &amp; Overwrite</button>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-3 mb-3">
        <div class="tbr-sidebar-sticky">
            <div class="card card-dark">
                <div class="card-header py-2">
                    <h3 class="card-title"><i class="fas fa-list-ul mr-2"></i>TBR Navigation</h3>
                </div>
                <div class="list-group list-group-flush tbr-nav-list">
                    <a href="#panel-overview" class="list-group-item list-group-item-action tbr-nav-item active" data-target="panel-overview">
                        <span><i class="fas fa-info-circle fa-fw mr-2"></i>Overview &amp; Prep</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <?php foreach ($sections_cfg as $sec_key => $sec) {
                        $st = $progress['sections'][$sec_key]['status'] ?? 'Not Started';
                        $icon_cls = match ($st) {
                            'Completed' => 'fas fa-check-circle text-success',
                            'In Progress' => 'fas fa-adjust text-warning',
                            'Not Assessed' => 'fas fa-minus-circle text-muted',
                            default => 'far fa-circle text-secondary',
                        };
                    ?>
                        <a href="#panel-<?= $sec_key ?>" class="list-group-item list-group-item-action tbr-nav-item" data-target="panel-<?= $sec_key ?>">
                            <span><strong><?= $sec['number'] ?>.</strong> <?= escapeHtml($sec['short_title']) ?></span>
                            <i class="<?= $icon_cls ?> tbr-nav-status-icon"></i>
                        </a>
                    <?php } ?>
                    <a href="#panel-reports" class="list-group-item list-group-item-action tbr-nav-item" data-target="panel-reports">
                        <span><i class="fas fa-file-pdf fa-fw mr-2 text-danger"></i>Reports &amp; Delivery</span>
                        <span class="badge badge-light border"><?= count($reports) ?></span>
                    </a>
                    <a href="#panel-audit" class="list-group-item list-group-item-action tbr-nav-item" data-target="panel-audit">
                        <span><i class="fas fa-history fa-fw mr-2"></i>Audit Trail</span>
                        <span class="badge badge-light border"><?= count($audit_logs) ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <div id="panel-overview" class="tbr-panel active">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle mr-2"></i>Meeting Overview &amp; Executive Summary</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Overall IT Environment Health</label>
                            <select class="form-control" id="tbrOverallHealth" <?= $disabled_attr ?>>
                                <?php foreach (['Not Assessed', 'Good', 'Needs Attention', 'Critical'] as $hl) { ?>
                                    <option value="<?= $hl ?>" <?= $meeting['tbr_overall_health'] === $hl ? 'selected' : '' ?>><?= $hl ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Reporting Period Start</label>
                            <input type="date" class="form-control" id="tbrPeriodStart" value="<?= escapeHtml((string)$meeting['tbr_reporting_period_start']) ?>" <?= $disabled_attr ?>>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Reporting Period End</label>
                            <input type="date" class="form-control" id="tbrPeriodEnd" value="<?= escapeHtml((string)$meeting['tbr_reporting_period_end']) ?>" <?= $disabled_attr ?>>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Next Review Date</label>
                            <input type="date" class="form-control" id="tbrNextReviewDate" value="<?= escapeHtml((string)$meeting['tbr_next_review_date']) ?>" <?= $disabled_attr ?>>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Executive Summary <small class="text-muted">(Included in Client-Facing PDF Report)</small></label>
                        <textarea class="form-control" id="tbrExecutiveSummary" rows="4" placeholder="High-level summary of IT health, business alignment, key wins, and strategic priorities..." <?= $disabled_attr ?>><?= escapeHtml((string)$meeting['tbr_executive_summary']) ?></textarea>
                    </div>

                    <?php if ($can_internal) { ?>
                        <div class="tbr-internal-box mb-3">
                            <label class="text-danger mb-1"><i class="fas fa-lock mr-1"></i>Internal Preparation Notes (MSP Only)</label>
                            <textarea class="form-control" id="tbrPrepNotes" rows="3" placeholder="Private MSP preparation notes, account risks, or renewal notes..." <?= $disabled_attr ?>><?= escapeHtml((string)$meeting['tbr_prep_notes']) ?></textarea>
                        </div>
                    <?php } ?>

                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-users mr-1"></i>Meeting Attendees &amp; Attendance</h6>
                            <div class="border rounded p-2">
                                <?php if (empty($attendees)) { ?>
                                    <span class="text-muted small">No attendees configured. Use Schedule button to add attendees.</span>
                                <?php } else {
                                    foreach ($attendees as $att) { ?>
                                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                        <div>
                                            <span class="badge badge-<?= $att['attendee_type'] === 'internal' ? 'dark' : 'info' ?> mr-1"><?= ucfirst($att['attendee_type']) ?></span>
                                            <strong><?= escapeHtml($att['attendee_name']) ?></strong>
                                            <small class="text-muted"><?= escapeHtml((string)$att['attendee_email']) ?></small>
                                        </div>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input tbr-attendee-attended" id="att_chk_<?= intval($att['attendee_id']) ?>" data-attendee-id="<?= intval($att['attendee_id']) ?>" <?= !empty($att['attendee_attended']) ? 'checked' : '' ?> <?= $disabled_attr ?>>
                                            <label class="custom-control-label small" for="att_chk_<?= intval($att['attendee_id']) ?>">Attended</label>
                                        </div>
                                    </div>
                                <?php } } ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-history mr-1"></i>Previous TBR Reference</h6>
                            <div class="border rounded p-3 bg-light">
                                <?php if ($prev_tbr) { ?>
                                    <div><strong><?= escapeHtml($prev_tbr['tbr_title']) ?></strong></div>
                                    <div class="small text-muted mb-2">
                                        <?= date('M j, Y', strtotime($prev_tbr['tbr_meeting_date'])) ?> &middot; Status: <?= escapeHtml($prev_tbr['tbr_status']) ?> &middot; Health: <?= escapeHtml($prev_tbr['tbr_overall_health']) ?>
                                    </div>
                                    <a href="/agent/tbr.php?tbr_id=<?= intval($prev_tbr['tbr_id']) ?>" target="_blank" class="btn btn-xs btn-outline-secondary mr-1">
                                        <i class="fas fa-external-link-alt mr-1"></i>Open Previous TBR
                                    </a>
                                    <?php if ($can_edit) { ?>
                                        <button type="button" class="btn btn-xs btn-outline-primary tbr-carry-forward-btn" data-actions="1" data-recs="0">
                                            <i class="fas fa-forward mr-1"></i>Carry Forward Open Actions
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-info tbr-carry-forward-btn" data-actions="0" data-recs="1">
                                            <i class="fas fa-lightbulb mr-1"></i>Carry Forward Deferred Recs
                                        </button>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span class="text-muted small">No previous TBR recorded for this client.</span>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
        $renderSectionHeader = function(string $sec_key) use ($sections_cfg, $responses, $disabled_attr) {
            $cfg = $sections_cfg[$sec_key];
            $resp = $responses[$sec_key] ?? [];
            $st = $resp['section_status'] ?? 'Not Started';
            $hl = $resp['section_health'] ?? 'Not Assessed';
        ?>
            <div class="card-header">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h3 class="card-title"><i class="<?= $cfg['icon'] ?> mr-2"></i>Section <?= $cfg['number'] ?> – <?= escapeHtml($cfg['title']) ?></h3>
                    </div>
                    <div class="d-flex align-items-center mt-2 mt-md-0" style="gap:0.5rem;">
                        <select class="form-control form-control-sm tbr-section-health" <?= $disabled_attr ?> title="Section Health">
                            <?php foreach (['Not Assessed', 'Good', 'Needs Attention', 'Critical'] as $hopt) { ?>
                                <option value="<?= $hopt ?>" <?= $hl === $hopt ? 'selected' : '' ?>>Health: <?= $hopt ?></option>
                            <?php } ?>
                        </select>
                        <select class="form-control form-control-sm tbr-section-status" <?= $disabled_attr ?> title="Section Completion Status">
                            <?php foreach (['Not Started', 'In Progress', 'Completed', 'Not Assessed'] as $sopt) { ?>
                                <option value="<?= $sopt ?>" <?= $st === $sopt ? 'selected' : '' ?>><?= $sopt ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="alert alert-light border-bottom rounded-0 py-2 mb-0 small">
                <strong>Objective:</strong> <?= escapeHtml($cfg['objective']) ?>
            </div>
        <?php };

        $renderSectionNotes = function(string $sec_key) use ($responses, $can_internal, $disabled_attr) {
            $resp = $responses[$sec_key] ?? [];
        ?>
            <hr>
            <div class="row">
                <div class="col-md-6 form-group">
                    <label>Client-Facing Summary / Discussion Notes</label>
                    <textarea class="form-control tbr-client-notes" rows="3" placeholder="Notes suitable for sharing in the client report..." <?= $disabled_attr ?>><?= escapeHtml((string)($resp['client_notes'] ?? '')) ?></textarea>
                </div>
                <div class="col-md-6 form-group">
                    <label>Section Follow-Up Items</label>
                    <textarea class="form-control tbr-followup-notes" rows="3" placeholder="Key follow-up notes for this section..." <?= $disabled_attr ?>><?= escapeHtml((string)($resp['follow_up_notes'] ?? '')) ?></textarea>
                </div>
            </div>
            <?php if ($can_internal) { ?>
                <div class="tbr-internal-box">
                    <label class="text-danger mb-1"><i class="fas fa-lock mr-1"></i>Internal MSP Notes (Never shown in Client PDF)</label>
                    <textarea class="form-control tbr-internal-notes" rows="2" placeholder="Private MSP observations, margins, technical debt notes..." <?= $disabled_attr ?>><?= escapeHtml((string)($resp['internal_notes'] ?? '')) ?></textarea>
                </div>
            <?php } ?>
        <?php };
        ?>

        <?php $s1_ans = $responses['sec_01']['answers'] ?? []; ?>
        <div id="panel-sec_01" class="tbr-panel" data-section-key="sec_01">
            <div class="card card-outline card-dark">
                <?php $renderSectionHeader('sec_01'); ?>
                <div class="card-body">
                    <h6 class="text-uppercase text-muted mb-3">Discovery Questions</h6>
                    <?php foreach ($sections_cfg['sec_01']['questions'] as $qk => $qlabel) { ?>
                        <div class="tbr-question-row">
                            <label class="mb-1"><?= escapeHtml($qlabel) ?></label>
                            <textarea class="form-control form-control-sm" rows="2" data-answer-key="<?= $qk ?>" <?= $disabled_attr ?>><?= escapeHtml((string)($s1_ans[$qk] ?? '')) ?></textarea>
                        </div>
                    <?php } ?>
                    <hr>
                    <h6 class="text-uppercase text-muted mb-3">Structured Updates</h6>
                    <div class="row">
                        <?php foreach ($sections_cfg['sec_01']['fields'] as $fk => $flabel) { ?>
                            <div class="col-md-6 form-group">
                                <label><?= escapeHtml($flabel) ?></label>
                                <textarea class="form-control" rows="3" data-answer-key="<?= $fk ?>" <?= $disabled_attr ?>><?= escapeHtml((string)($s1_ans[$fk] ?? '')) ?></textarea>
                            </div>
                        <?php } ?>
                    </div>
                    <?php $renderSectionNotes('sec_01'); ?>
                </div>
            </div>
        </div>

        <?php
        $s2_ans = $responses['sec_02']['answers'] ?? [];
        $infra = $live_data['infrastructure'];
        $ac = $infra['asset_counts'];
        ?>
        <div id="panel-sec_02" class="tbr-panel" data-section-key="sec_02">
            <div class="card card-outline card-dark">
                <?php $renderSectionHeader('sec_02'); ?>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="badge badge-success tbr-sync-badge">Synced from ITFlow</span>
                            <small class="text-muted ml-1">Assets, Software, Services, Networks, Domains &amp; Certificates</small>
                        </div>
                        <button type="button" class="btn btn-xs btn-outline-secondary tbr-refresh-live-btn"><i class="fas fa-sync-alt mr-1"></i>Refresh Live Data</button>
                    </div>
                    <div class="row text-center mb-3">
                        <div class="col-4 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Workstations</small><strong><?= intval($ac['workstations']) ?></strong></div></div>
                        <div class="col-4 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Servers</small><strong><?= intval($ac['servers']) ?></strong></div></div>
                        <div class="col-4 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Network/FW</small><strong><?= intval($ac['network']) ?></strong></div></div>
                        <div class="col-4 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Licenses</small><strong><?= intval($infra['software_counts']['total']) ?></strong></div></div>
                        <div class="col-4 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Services</small><strong><?= count($infra['services']) ?></strong></div></div>
                        <div class="col-4 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Domains/Certs</small><strong><?= intval($infra['domains_count']) ?> / <?= intval($infra['certs_count']) ?></strong></div></div>
                    </div>

                    <?php if (!empty($infra['os_breakdown'])) { ?>
                        <div class="mb-3 small">
                            <strong>Detected Operating Systems:</strong>
                            <?php foreach ($infra['os_breakdown'] as $os_name => $os_cnt) { ?>
                                <span class="badge badge-light border mr-1"><?= escapeHtml($os_name) ?>: <?= intval($os_cnt) ?></span>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <div class="alert alert-light border small py-2 mb-3">
                        <span class="badge badge-secondary tbr-sync-badge mr-1">Manual Entry</span>
                        Microsoft 365 Licensing and Endpoint Security Telemetry are not directly integrated in this ITFlow instance — enter verified counts and assessment notes below.
                        <?php if ($infra['rmm']['configured']) { ?>
                            <br><strong>N-able RMM:</strong> <?= $infra['rmm']['mapped'] ? 'Client mapped' : 'Client not mapped' ?> (Last sync: <?= escapeHtml($infra['rmm']['last_sync_at'] ?: 'Never') ?>)
                        <?php } ?>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:18%;">Category</th>
                                    <th style="width:14%;">Status &amp; Health</th>
                                    <th style="width:22%;">Findings</th>
                                    <th style="width:22%;">Issues Identified</th>
                                    <th style="width:24%;">Recommended Improvements &amp; Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sections_cfg['sec_02']['categories'] as $ck => $clabel) {
                                    $cval = $s2_ans['categories'][$ck] ?? [];
                                ?>
                                    <tr>
                                        <td><strong><?= escapeHtml($clabel) ?></strong></td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm mb-1" placeholder="Current status" data-cat-key="<?= $ck ?>" data-cat-field="status" value="<?= escapeHtml((string)($cval['status'] ?? '')) ?>" <?= $disabled_attr ?>>
                                            <select class="form-control form-control-sm" data-cat-key="<?= $ck ?>" data-cat-field="health" <?= $disabled_attr ?>>
                                                <?php foreach (['Not Assessed', 'Good', 'Needs Attention', 'Critical'] as $ho) { ?>
                                                    <option value="<?= $ho ?>" <?= ($cval['health'] ?? 'Not Assessed') === $ho ? 'selected' : '' ?>><?= $ho ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td><textarea class="form-control form-control-sm" rows="2" placeholder="Findings..." data-cat-key="<?= $ck ?>" data-cat-field="findings" <?= $disabled_attr ?>><?= escapeHtml((string)($cval['findings'] ?? '')) ?></textarea></td>
                                        <td><textarea class="form-control form-control-sm" rows="2" placeholder="Issues identified..." data-cat-key="<?= $ck ?>" data-cat-field="issues" <?= $disabled_attr ?>><?= escapeHtml((string)($cval['issues'] ?? '')) ?></textarea></td>
                                        <td>
                                            <textarea class="form-control form-control-sm mb-1" rows="1" placeholder="Recommendations..." data-cat-key="<?= $ck ?>" data-cat-field="recommendations" <?= $disabled_attr ?>><?= escapeHtml((string)($cval['recommendations'] ?? '')) ?></textarea>
                                            <input type="text" class="form-control form-control-sm" placeholder="Notes..." data-cat-key="<?= $ck ?>" data-cat-field="notes" value="<?= escapeHtml((string)($cval['notes'] ?? '')) ?>" <?= $disabled_attr ?>>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                        <h6 class="mb-0">Infrastructure Findings</h6>
                        <?php if ($can_edit) { ?>
                            <button type="button" class="btn btn-xs btn-primary tbr-open-finding-modal" data-section="sec_02"><i class="fas fa-plus mr-1"></i>Add Finding</button>
                        <?php } ?>
                    </div>
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Finding</th><th>Risk</th><th>Business Impact</th><th>Recommended Action</th><th>Owner / Target</th><th>Status</th><th></th></tr></thead>
                        <tbody id="tbrSec02FindingsBody"></tbody>
                    </table>

                    <?php $renderSectionNotes('sec_02'); ?>
                </div>
            </div>
        </div>

        <?php
        $s3_ans = $responses['sec_03']['answers'] ?? [];
        $hd = $live_data['helpdesk'];
        ?>
        <div id="panel-sec_03" class="tbr-panel" data-section-key="sec_03">
            <div class="card card-outline card-dark">
                <?php $renderSectionHeader('sec_03'); ?>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="badge badge-success tbr-sync-badge">Synced from ITFlow Tickets</span>
                            <small class="text-muted ml-1">Period: <?= escapeHtml($live_data['period_start']) ?> to <?= escapeHtml($live_data['period_end']) ?></small>
                        </div>
                        <button type="button" class="btn btn-xs btn-outline-secondary tbr-refresh-live-btn"><i class="fas fa-sync-alt mr-1"></i>Recalculate Metrics</button>
                    </div>

                    <div class="row text-center mb-3">
                        <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Tickets Opened</small><strong id="live_hd_opened" class="h5"><?= intval($hd['tickets_opened']) ?></strong></div></div>
                        <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Resolved</small><strong id="live_hd_resolved" class="h5"><?= intval($hd['tickets_resolved']) ?></strong></div></div>
                        <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Outstanding</small><strong id="live_hd_outstanding" class="h5"><?= intval($hd['tickets_outstanding']) ?></strong></div></div>
                        <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Avg Response</small><strong id="live_hd_resp" class="h6"><?= escapeHtml(tbrFormatMinutes($hd['avg_response_minutes'])) ?></strong></div></div>
                        <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Avg Resolution</small><strong id="live_hd_res" class="h6"><?= escapeHtml(tbrFormatMinutes($hd['avg_resolution_minutes'])) ?></strong></div></div>
                        <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">SLA Compliance</small><strong id="live_hd_sla" class="h6"><?= $hd['sla_resolution_compliance_pct'] !== null ? $hd['sla_resolution_compliance_pct'] . '%' : 'N/A' ?></strong></div></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Most Common Ticket Categories</h6>
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light"><tr><th>Category</th><th class="text-right">Count</th></tr></thead>
                                <tbody>
                                    <?php if (empty($hd['top_categories'])) { ?>
                                        <tr><td colspan="2" class="text-muted text-center">No tickets in reporting period.</td></tr>
                                    <?php } else { foreach ($hd['top_categories'] as $tc) { ?>
                                        <tr><td><?= escapeHtml($tc['category']) ?></td><td class="text-right"><?= intval($tc['count']) ?></td></tr>
                                    <?php } } ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Detected Recurring Issues</h6>
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light"><tr><th>Type</th><th>Pattern / Asset</th><th class="text-right">Count</th></tr></thead>
                                <tbody>
                                    <?php if (empty($hd['recurring_issues'])) { ?>
                                        <tr><td colspan="3" class="text-muted text-center">No recurring patterns detected in period.</td></tr>
                                    <?php } else { foreach ($hd['recurring_issues'] as $ri) { ?>
                                        <tr><td><?= escapeHtml($ri['type']) ?></td><td><?= escapeHtml($ri['label']) ?></td><td class="text-right"><?= intval($ri['count']) ?></td></tr>
                                    <?php } } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row">
                        <?php foreach ($sections_cfg['sec_03']['fields'] as $fk => $flabel) { ?>
                            <div class="col-md-6 form-group">
                                <label><?= escapeHtml($flabel) ?></label>
                                <textarea class="form-control" rows="3" data-answer-key="<?= $fk ?>" <?= $disabled_attr ?>><?= escapeHtml((string)($s3_ans[$fk] ?? '')) ?></textarea>
                            </div>
                        <?php } ?>
                    </div>
                    <?php $renderSectionNotes('sec_03'); ?>
                </div>
            </div>
        </div>

        <?php $s4_ans = $responses['sec_04']['answers'] ?? []; ?>
        <div id="panel-sec_04" class="tbr-panel" data-section-key="sec_04">
            <div class="card card-outline card-danger">
                <?php $renderSectionHeader('sec_04'); ?>
                <div class="card-body">
                    <div class="card card-outline card-danger mb-3">
                        <div class="card-header py-2 bg-light">
                            <strong class="text-danger"><i class="fas fa-exclamation-circle mr-1"></i>Outstanding Security Concerns Requiring Immediate Attention</strong>
                        </div>
                        <div class="card-body py-2" id="tbrSec04ImmediateBox"></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Security Findings &amp; Risk Register</h6>
                        <?php if ($can_edit) { ?>
                            <button type="button" class="btn btn-sm btn-danger tbr-open-finding-modal" data-section="sec_04">
                                <i class="fas fa-plus mr-1"></i>Add Security Finding
                            </button>
                        <?php } ?>
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-striped table-bordered">
                            <thead class="thead-light">
                                <tr><th>Finding</th><th>Risk Level</th><th>Business Impact</th><th>Recommended Action</th><th>Responsible / Target</th><th>Status</th><th></th></tr>
                            </thead>
                            <tbody id="tbrSec04FindingsBody"></tbody>
                        </table>
                    </div>

                    <h6>Security Control Areas Assessment <span class="badge badge-secondary tbr-sync-badge ml-1">Manual Review</span></h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="thead-light"><tr><th style="width:32%;">Security Area</th><th style="width:20%;">Status</th><th style="width:48%;">Observations / Notes</th></tr></thead>
                            <tbody>
                                <?php foreach ($sections_cfg['sec_04']['areas'] as $ak => $alabel) {
                                    $av = $s4_ans['areas'][$ak] ?? [];
                                ?>
                                    <tr>
                                        <td><strong><?= escapeHtml($alabel) ?></strong></td>
                                        <td>
                                            <select class="form-control form-control-sm" data-area-key="<?= $ak ?>" data-area-field="status" <?= $disabled_attr ?>>
                                                <option value="">- Not Reviewed -</option>
                                                <?php foreach (['Good', 'Needs Attention', 'Critical', 'Not Assessed'] as $st_o) { ?>
                                                    <option value="<?= $st_o ?>" <?= ($av['status'] ?? '') === $st_o ? 'selected' : '' ?>><?= $st_o ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td><input type="text" class="form-control form-control-sm" placeholder="Observations..." data-area-key="<?= $ak ?>" data-area-field="notes" value="<?= escapeHtml((string)($av['notes'] ?? '')) ?>" <?= $disabled_attr ?>></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php $renderSectionNotes('sec_04'); ?>
                </div>
            </div>
        </div>

        <?php $s5_ans = $responses['sec_05']['answers'] ?? []; ?>
        <div id="panel-sec_05" class="tbr-panel" data-section-key="sec_05">
            <div class="card card-outline card-dark">
                <?php $renderSectionHeader('sec_05'); ?>
                <div class="card-body">
                    <?php if (!empty($infra['backup_services'])) { ?>
                        <div class="alert alert-info py-2 small">
                            <strong>ITFlow Documented Service Backups:</strong>
                            <?php foreach ($infra['backup_services'] as $bs) { ?>
                                <span class="badge badge-light border mr-1"><?= escapeHtml($bs['service_name']) ?>: <?= escapeHtml($bs['service_backup']) ?></span>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="alert alert-light border py-2 small">
                            <span class="badge badge-secondary tbr-sync-badge mr-1">Manual Entry</span>
                            No external backup telemetry synced; document verified backup metrics and restoration test outcomes below.
                        </div>
                    <?php } ?>

                    <h6 class="text-uppercase text-muted mb-2">Backup &amp; Recovery Metrics</h6>
                    <div class="row">
                        <?php foreach ($sections_cfg['sec_05']['metrics'] as $mk => $mlabel) { ?>
                            <div class="col-md-4 form-group">
                                <label class="small mb-1"><?= escapeHtml($mlabel) ?></label>
                                <input type="text" class="form-control form-control-sm" data-answer-key="<?= $mk ?>" value="<?= escapeHtml((string)($s5_ans[$mk] ?? '')) ?>" <?= $disabled_attr ?>>
                            </div>
                        <?php } ?>
                    </div>

                    <hr>
                    <h6 class="text-uppercase text-muted mb-2">Disaster Recovery Readiness Questions</h6>
                    <?php foreach ($sections_cfg['sec_05']['questions'] as $qk => $qlabel) { ?>
                        <div class="tbr-question-row">
                            <label class="mb-1"><?= escapeHtml($qlabel) ?></label>
                            <textarea class="form-control form-control-sm" rows="2" data-answer-key="<?= $qk ?>" <?= $disabled_attr ?>><?= escapeHtml((string)($s5_ans[$qk] ?? '')) ?></textarea>
                        </div>
                    <?php } ?>

                    <hr>
                    <div class="row">
                        <?php foreach ($sections_cfg['sec_05']['fields'] as $fk => $flabel) { ?>
                            <div class="col-md-6 form-group">
                                <label><?= escapeHtml($flabel) ?></label>
                                <textarea class="form-control" rows="3" data-answer-key="<?= $fk ?>" <?= $disabled_attr ?>><?= escapeHtml((string)($s5_ans[$fk] ?? '')) ?></textarea>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2 mb-2">
                        <h6 class="mb-0">Backup &amp; DR Findings</h6>
                        <?php if ($can_edit) { ?>
                            <button type="button" class="btn btn-xs btn-primary tbr-open-finding-modal" data-section="sec_05"><i class="fas fa-plus mr-1"></i>Add Finding</button>
                        <?php } ?>
                    </div>
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Finding</th><th>Risk</th><th>Business Impact</th><th>Recommended Action</th><th>Owner / Target</th><th>Status</th><th></th></tr></thead>
                        <tbody id="tbrSec05FindingsBody"></tbody>
                    </table>
                    <?php $renderSectionNotes('sec_05'); ?>
                </div>
            </div>
        </div>

        <?php
        $s6_ans = $responses['sec_06']['answers'] ?? [];
        $asset_reviews = $s6_ans['asset_reviews'] ?? [];
        ?>
        <div id="panel-sec_06" class="tbr-panel" data-section-key="sec_06">
            <div class="card card-outline card-dark">
                <?php $renderSectionHeader('sec_06'); ?>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="badge badge-success tbr-sync-badge">Synced from ITFlow Assets &amp; Licenses</span>
                            <small class="text-muted ml-2">Aging 4+ yrs: <strong><?= intval($ac['aging_4_plus_years']) ?></strong> &middot; Warranty Expired: <strong><?= intval($ac['warranty_expired']) ?></strong> &middot; Expiring 90d: <strong><?= intval($ac['warranty_expiring_90d']) ?></strong></small>
                        </div>
                        <a href="/agent/assets.php?client_id=<?= $client_id ?>" target="_blank" class="btn btn-xs btn-outline-secondary">Open Client Assets</a>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>Asset</th>
                                    <th>Age</th>
                                    <th>Warranty</th>
                                    <th>Status</th>
                                    <th>Replacement Recommendation</th>
                                    <th style="width:120px;">Est. Cost</th>
                                    <th style="width:140px;">Target Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($infra['assets'])) { ?>
                                    <tr><td colspan="7" class="text-center text-muted py-3">No assets documented for this client.</td></tr>
                                <?php } else { foreach ($infra['assets'] as $a) {
                                    $aid = strval($a['asset_id']);
                                    $rev = $asset_reviews[$aid] ?? [];
                                    $wbadge = match ($a['warranty_status']) {
                                        'Expired' => 'danger',
                                        'Expiring Soon' => 'warning',
                                        'Active' => 'success',
                                        default => 'secondary',
                                    };
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?= escapeHtml($a['asset_name']) ?></strong>
                                            <br><small class="text-muted"><?= escapeHtml($a['asset_type']) ?> &middot; <?= escapeHtml(trim($a['asset_make'] . ' ' . $a['asset_model'])) ?><?= !empty($a['asset_os']) ? ' &middot; ' . escapeHtml($a['asset_os']) : '' ?></small>
                                        </td>
                                        <td><?= $a['age_years'] !== null ? $a['age_years'] . ' yrs' : '-' ?></td>
                                        <td>
                                            <span class="badge badge-<?= $wbadge ?>"><?= escapeHtml($a['warranty_status']) ?></span>
                                            <?= !empty($a['asset_warranty_expire']) ? '<br><small>' . escapeHtml($a['asset_warranty_expire']) . '</small>' : '' ?>
                                        </td>
                                        <td><?= escapeHtml((string)($a['asset_status'] ?: 'Active')) ?></td>
                                        <td><input type="text" class="form-control form-control-sm" data-asset-id="<?= $aid ?>" data-asset-field="recommendation" placeholder="Keep / Replace / Upgrade..." value="<?= escapeHtml((string)($rev['recommendation'] ?? '')) ?>" <?= $disabled_attr ?>></td>
                                        <td><input type="number" step="0.01" class="form-control form-control-sm" data-asset-id="<?= $aid ?>" data-asset-field="est_cost" placeholder="0.00" value="<?= escapeHtml((string)($rev['est_cost'] ?? '')) ?>" <?= $disabled_attr ?>></td>
                                        <td><input type="date" class="form-control form-control-sm" data-asset-id="<?= $aid ?>" data-asset-field="target_date" value="<?= escapeHtml((string)($rev['target_date'] ?? '')) ?>" <?= $disabled_attr ?>></td>
                                    </tr>
                                <?php } } ?>
                            </tbody>
                        </table>
                    </div>

                    <h6>Software &amp; Licensing</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered">
                            <thead class="thead-light"><tr><th>Software</th><th>Type</th><th>Seats</th><th>Expires</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php if (empty($infra['software'])) { ?>
                                    <tr><td colspan="5" class="text-center text-muted py-2">No software licenses documented.</td></tr>
                                <?php } else { foreach ($infra['software'] as $sw) { ?>
                                    <tr>
                                        <td><strong><?= escapeHtml($sw['software_name']) ?></strong> <?= escapeHtml((string)$sw['software_version']) ?></td>
                                        <td><?= escapeHtml($sw['software_type']) ?></td>
                                        <td><?= intval($sw['software_seats']) ?></td>
                                        <td><?= escapeHtml($sw['software_expire'] ?: '-') ?></td>
                                        <td><span class="badge badge-<?= $sw['license_status'] === 'Expired' ? 'danger' : ($sw['license_status'] === 'Expiring Soon' ? 'warning' : 'success') ?>"><?= escapeHtml($sw['license_status']) ?></span></td>
                                    </tr>
                                <?php } } ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Additional Lifecycle / EOL Findings</h6>
                        <?php if ($can_edit) { ?>
                            <button type="button" class="btn btn-xs btn-primary tbr-open-finding-modal" data-section="sec_06"><i class="fas fa-plus mr-1"></i>Add Lifecycle Finding</button>
                        <?php } ?>
                    </div>
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Finding</th><th>Risk</th><th>Business Impact</th><th>Recommended Action</th><th>Owner / Target</th><th>Status</th><th></th></tr></thead>
                        <tbody id="tbrSec06FindingsBody"></tbody>
                    </table>
                    <?php $renderSectionNotes('sec_06'); ?>
                </div>
            </div>
        </div>

        <div id="panel-sec_07" class="tbr-panel" data-section-key="sec_07">
            <div class="card card-outline card-dark">
                <?php $renderSectionHeader('sec_07'); ?>
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <?php if ($prev_tbr && $can_edit) { ?>
                                <button type="button" class="btn btn-sm btn-outline-info tbr-carry-forward-btn" data-actions="0" data-recs="1">
                                    <i class="fas fa-forward mr-1"></i>Carry Forward Proposed/Deferred from Previous TBR
                                </button>
                            <?php } ?>
                        </div>
                        <?php if ($can_edit) { ?>
                            <button type="button" class="btn btn-sm btn-primary tbr-open-rec-modal">
                                <i class="fas fa-plus mr-1"></i>Add Strategic Recommendation
                            </button>
                        <?php } ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered">
                            <thead class="thead-light">
                                <tr><th>Recommendation &amp; Justification</th><th>Priority</th><th>Est. Cost</th><th>Timeline</th><th>Responsible</th><th>Status</th><th></th></tr>
                            </thead>
                            <tbody id="tbrRecommendationsBody"></tbody>
                        </table>
                    </div>
                    <?php $renderSectionNotes('sec_07'); ?>
                </div>
            </div>
        </div>

        <div id="panel-sec_08" class="tbr-panel" data-section-key="sec_08">
            <div class="card card-outline card-dark">
                <?php $renderSectionHeader('sec_08'); ?>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Q1 Projected</small><strong id="tbrRoadmapTotal_Q1"></strong></div></div>
                        <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Q2 Projected</small><strong id="tbrRoadmapTotal_Q2"></strong></div></div>
                        <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Q3 Projected</small><strong id="tbrRoadmapTotal_Q3"></strong></div></div>
                        <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">Q4 Projected</small><strong id="tbrRoadmapTotal_Q4"></strong></div></div>
                        <div class="col-12 col-md-4 mb-2"><div class="border rounded p-2 bg-dark text-white"><small class="d-block">12-Month Projected Annual Spend</small><strong id="tbrRoadmapTotal_Annual" class="h5 mb-0"></strong></div></div>
                    </div>

                    <div class="row justify-content-center mb-3" id="tbrRoadmapCategoriesRow"></div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">12-Month Technology Roadmap Projects</h6>
                        <?php if ($can_edit) { ?>
                            <button type="button" class="btn btn-sm btn-primary tbr-open-roadmap-modal"><i class="fas fa-plus mr-1"></i>Add Roadmap Item</button>
                        <?php } ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered">
                            <thead class="thead-light">
                                <tr><th>Quarter</th><th>Project</th><th>Category</th><th>Priority</th><th class="text-right">Est. Cost</th><th>Status</th><th></th></tr>
                            </thead>
                            <tbody id="tbrRoadmapBody"></tbody>
                        </table>
                    </div>
                    <?php $renderSectionNotes('sec_08'); ?>
                </div>
            </div>
        </div>

        <?php $s9_ans = $responses['sec_09']['answers'] ?? []; ?>
        <div id="panel-sec_09" class="tbr-panel" data-section-key="sec_09">
            <div class="card card-outline card-dark">
                <?php $renderSectionHeader('sec_09'); ?>
                <div class="card-body">
                    <?php foreach ($sections_cfg['sec_09']['questions'] as $qk => $qlabel) { ?>
                        <div class="tbr-question-row">
                            <label class="mb-1"><?= escapeHtml($qlabel) ?></label>
                            <textarea class="form-control form-control-sm" rows="2" data-answer-key="<?= $qk ?>" <?= $disabled_attr ?>><?= escapeHtml((string)($s9_ans[$qk] ?? '')) ?></textarea>
                        </div>
                    <?php } ?>
                    <hr>
                    <div class="row">
                        <?php foreach ($sections_cfg['sec_09']['fields'] as $fk => $flabel) { ?>
                            <div class="col-md-6 form-group">
                                <label><?= escapeHtml($flabel) ?></label>
                                <textarea class="form-control" rows="3" data-answer-key="<?= $fk ?>" <?= $disabled_attr ?>><?= escapeHtml((string)($s9_ans[$fk] ?? '')) ?></textarea>
                            </div>
                        <?php } ?>
                    </div>
                    <?php $renderSectionNotes('sec_09'); ?>
                </div>
            </div>
        </div>

        <div id="panel-sec_10" class="tbr-panel" data-section-key="sec_10">
            <div class="card card-outline card-dark">
                <?php $renderSectionHeader('sec_10'); ?>
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <?php if ($prev_tbr && $can_edit) { ?>
                                <button type="button" class="btn btn-sm btn-outline-info tbr-carry-forward-btn" data-actions="1" data-recs="0">
                                    <i class="fas fa-forward mr-1"></i>Carry Forward Open Actions from Previous TBR
                                </button>
                            <?php } ?>
                        </div>
                        <?php if ($can_edit) { ?>
                            <button type="button" class="btn btn-sm btn-primary tbr-open-action-modal">
                                <i class="fas fa-plus mr-1"></i>Add Action Item
                            </button>
                        <?php } ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered">
                            <thead class="thead-light">
                                <tr><th>Task Description</th><th>Owner</th><th>Priority</th><th>Due Date</th><th>Status</th><th>ITFlow Ticket</th><th></th></tr>
                            </thead>
                            <tbody id="tbrActionsBody"></tbody>
                        </table>
                    </div>
                    <?php $renderSectionNotes('sec_10'); ?>
                </div>
            </div>
        </div>

        <div id="panel-reports" class="tbr-panel">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-pdf mr-2"></i>Report Approval, PDF Generation &amp; Client Delivery</h3>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <h5>1. Client-Facing Report</h5>
                                <p class="small text-muted">Contains Executive Summary, IT Health, Helpdesk KPIs, Client-Visible Findings, Recommendations, 12-Month Roadmap, and Action Items. <strong>Internal notes are strictly excluded.</strong></p>
                                <div class="mb-3">
                                    <?php if (!empty($meeting['tbr_client_report_approved'])) { ?>
                                        <span class="badge badge-success p-2"><i class="fas fa-check-circle mr-1"></i>Approved by <?= escapeHtml($meeting['approved_by_name'] ?: 'Staff') ?> (<?= escapeHtml((string)$meeting['tbr_client_report_approved_at']) ?>)</span>
                                    <?php } else { ?>
                                        <span class="badge badge-warning p-2"><i class="fas fa-hourglass-half mr-1"></i>Pending Final Review / Approval</span>
                                    <?php } ?>
                                </div>
                                <div class="d-flex flex-wrap" style="gap:0.5rem;">
                                    <?php if ($can_complete) { ?>
                                        <button type="button" class="btn btn-sm btn-outline-<?= !empty($meeting['tbr_client_report_approved']) ? 'secondary' : 'success' ?>" id="tbrApproveReportBtn" data-approved="<?= !empty($meeting['tbr_client_report_approved']) ? 1 : 0 ?>">
                                            <i class="fas fa-check mr-1"></i><?= !empty($meeting['tbr_client_report_approved']) ? 'Revoke Approval' : 'Approve Client Report' ?>
                                        </button>
                                    <?php } ?>
                                    <?php if ($can_pdf) { ?>
                                        <?php if (!empty($meeting['tbr_client_report_approved'])) { ?>
                                            <a href="/agent/tbr_report.php?action=generate&tbr_id=<?= $tbr_id ?>&mode=client&redirect_back=1" class="btn btn-sm btn-primary">
                                                <i class="fas fa-file-pdf mr-1"></i>Generate Client TBR Report (PDF)
                                            </a>
                                        <?php } elseif ($can_edit) { ?>
                                            <a href="/agent/tbr_report.php?action=generate&tbr_id=<?= $tbr_id ?>&mode=client&preview_draft=1" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye mr-1"></i>Preview Unapproved Draft PDF
                                            </a>
                                        <?php } ?>
                                    <?php } ?>
                                    <?php if ($can_email && !empty($meeting['tbr_client_report_approved'])) { ?>
                                        <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#tbrEmailReportModal">
                                            <i class="fas fa-envelope mr-1"></i>Email Report to Client
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($can_internal) { ?>
                        <div class="col-md-6">
                            <div class="border border-danger rounded p-3 h-100">
                                <h5 class="text-danger">2. Internal MSP Report</h5>
                                <p class="small text-muted">Contains all client-facing content <strong>plus</strong> preparation notes, private internal observations, and internal-only findings.</p>
                                <?php if ($can_pdf) { ?>
                                    <a href="/agent/tbr_report.php?action=generate&tbr_id=<?= $tbr_id ?>&mode=internal&redirect_back=1" class="btn btn-sm btn-danger">
                                        <i class="fas fa-user-secret mr-1"></i>Generate Internal MSP Report (PDF)
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>
                    </div>

                    <h6>Previously Generated Reports</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered">
                            <thead class="thead-light">
                                <tr><th>Type</th><th>Version</th><th>Filename</th><th>Generated</th><th>By</th><th>Emailed To</th><th class="text-right">Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reports)) { ?>
                                    <tr><td colspan="7" class="text-center text-muted py-3">No PDF reports generated for this meeting yet.</td></tr>
                                <?php } else { foreach ($reports as $rp) { ?>
                                    <tr>
                                        <td><span class="badge badge-<?= $rp['report_type'] === 'internal' ? 'danger' : 'primary' ?>"><?= ucfirst(escapeHtml($rp['report_type'])) ?></span></td>
                                        <td>v<?= intval($rp['report_version']) ?></td>
                                        <td><?= escapeHtml($rp['report_filename']) ?></td>
                                        <td><?= escapeHtml($rp['report_generated_at']) ?></td>
                                        <td><?= escapeHtml($rp['user_name'] ?: '-') ?></td>
                                        <td><?= !empty($rp['report_emailed_to']) ? escapeHtml($rp['report_emailed_to']) . '<br><small class="text-muted">' . escapeHtml($rp['report_emailed_at']) . '</small>' : '-' ?></td>
                                        <td class="text-right text-nowrap">
                                            <a href="/agent/tbr_report.php?action=preview&report_id=<?= intval($rp['report_id']) ?>" target="_blank" class="btn btn-xs btn-outline-primary"><i class="fas fa-eye mr-1"></i>Preview</a>
                                            <a href="/agent/tbr_report.php?action=download&report_id=<?= intval($rp['report_id']) ?>" class="btn btn-xs btn-outline-success"><i class="fas fa-download mr-1"></i>Download</a>
                                        </td>
                                    </tr>
                                <?php } } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="panel-audit" class="tbr-panel">
            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history mr-2"></i>TBR Audit History</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
                        <tbody>
                            <?php if (empty($audit_logs)) { ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No audit events recorded.</td></tr>
                            <?php } else { foreach ($audit_logs as $au) { ?>
                                <tr>
                                    <td><?= escapeHtml($au['audit_created_at']) ?></td>
                                    <td><?= escapeHtml($au['user_name'] ?: 'System') ?></td>
                                    <td><span class="badge badge-light border"><?= escapeHtml($au['audit_action']) ?></span></td>
                                    <td><?= escapeHtml($au['audit_details']) ?></td>
                                </tr>
                            <?php } } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="tbrFindingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="tbrFindingForm" class="modal-content">
            <input type="hidden" name="finding_id" id="tbrFindingId" value="0">
            <input type="hidden" name="finding_section" id="tbrFindingSection" value="sec_04">
            <div class="modal-header bg-dark">
                <h5 class="modal-title">Document Finding</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8 form-group">
                        <label>Security / Technical Finding <strong class="text-danger">*</strong></label>
                        <input type="text" class="form-control" name="finding_title" id="tbrFindingTitle" required maxlength="255">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Category</label>
                        <input type="text" class="form-control" name="finding_category" id="tbrFindingCategory" placeholder="e.g. MFA, Patching, Firewall">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Risk Level</label>
                        <select class="form-control" name="finding_risk_level" id="tbrFindingRisk">
                            <option value="Low">Low Risk</option>
                            <option value="Moderate" selected>Moderate Risk</option>
                            <option value="High">High Risk</option>
                            <option value="Critical">Critical Risk</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Priority</label>
                        <select class="form-control" name="finding_priority" id="tbrFindingPriority">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Status</label>
                        <select class="form-control" name="finding_status" id="tbrFindingStatus">
                            <option value="Open">Open</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Mitigated">Mitigated</option>
                            <option value="Accepted">Accepted</option>
                            <option value="Resolved">Resolved</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="finding_description" id="tbrFindingDesc" rows="2"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Business Impact</label>
                        <textarea class="form-control" name="finding_business_impact" id="tbrFindingImpact" rows="2"></textarea>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Recommended Action</label>
                        <textarea class="form-control" name="finding_recommended_action" id="tbrFindingAction" rows="2"></textarea>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Responsible Party</label>
                        <input type="text" class="form-control" name="finding_responsible_party" id="tbrFindingParty" placeholder="MSP / Client">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Target Completion Date</label>
                        <input type="date" class="form-control" name="finding_target_date" id="tbrFindingTargetDate">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Estimated Cost</label>
                        <input type="number" step="0.01" class="form-control" name="finding_estimated_cost" id="tbrFindingCost">
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-6">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" name="finding_immediate_attention" value="1" id="tbrFindingImmediate">
                            <label class="custom-control-label text-danger" for="tbrFindingImmediate">Requires Immediate Attention</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" name="finding_client_visible" value="1" id="tbrFindingVisible" checked>
                            <label class="custom-control-label" for="tbrFindingVisible">Include in Client-Facing PDF Report</label>
                        </div>
                    </div>
                </div>
                <?php if ($can_internal) { ?>
                    <div class="form-group mb-0">
                        <label class="text-danger">Internal Notes (MSP Only)</label>
                        <textarea class="form-control" name="finding_internal_notes" id="tbrFindingInternalNotes" rows="2"></textarea>
                    </div>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Finding</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="tbrRecModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="tbrRecForm" class="modal-content">
            <input type="hidden" name="recommendation_id" id="tbrRecId" value="0">
            <div class="modal-header bg-dark">
                <h5 class="modal-title">Strategic IT Recommendation</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Recommendation Title <strong class="text-danger">*</strong></label>
                        <input type="text" class="form-control" name="recommendation_title" id="tbrRecTitle" required maxlength="255">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Priority</label>
                        <select class="form-control" name="recommendation_priority" id="tbrRecPriority">
                            <option value="Critical">Critical</option>
                            <option value="High">High</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Client Approval Status</label>
                        <select class="form-control" name="recommendation_status" id="tbrRecStatus">
                            <option value="Proposed">Proposed</option>
                            <option value="Approved">Approved</option>
                            <option value="Declined">Declined</option>
                            <option value="Deferred">Deferred</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Estimated Cost</label>
                        <input type="number" step="0.01" class="form-control" name="recommendation_estimated_cost" id="tbrRecCost">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Recommended Timeline</label>
                        <input type="text" class="form-control" name="recommendation_timeline" id="tbrRecTimeline" placeholder="e.g. Q2 2027">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Responsible Party</label>
                        <input type="text" class="form-control" name="recommendation_responsible_party" id="tbrRecParty" placeholder="MSP / Client">
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="recommendation_description" id="tbrRecDesc" rows="2"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Business Justification</label>
                        <textarea class="form-control" name="recommendation_justification" id="tbrRecJust" rows="2"></textarea>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Current Problem or Opportunity</label>
                        <textarea class="form-control" name="recommendation_problem" id="tbrRecProblem" rows="2"></textarea>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Risk Addressed</label>
                        <textarea class="form-control" name="recommendation_risk_addressed" id="tbrRecRisk" rows="2"></textarea>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Additional Notes (Client-Visible)</label>
                        <textarea class="form-control" name="recommendation_notes" id="tbrRecNotes" rows="2"></textarea>
                    </div>
                </div>
                <?php if ($can_internal) { ?>
                    <div class="form-group mb-0">
                        <label class="text-danger">Internal Notes (MSP Only)</label>
                        <textarea class="form-control" name="recommendation_internal_notes" id="tbrRecInternalNotes" rows="2"></textarea>
                    </div>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Recommendation</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="tbrRoadmapModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="tbrRoadmapForm" class="modal-content">
            <input type="hidden" name="roadmap_id" id="tbrRoadmapId" value="0">
            <div class="modal-header bg-dark">
                <h5 class="modal-title">12-Month Technology Roadmap Item</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Project Name <strong class="text-danger">*</strong></label>
                        <input type="text" class="form-control" name="roadmap_project_name" id="tbrRoadmapName" required maxlength="255">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Investment Category</label>
                        <select class="form-control" name="roadmap_category" id="tbrRoadmapCategory">
                            <option value="Required Security">Required Security</option>
                            <option value="Hardware Replacement">Hardware Replacement</option>
                            <option value="Software & Licensing">Software &amp; Licensing</option>
                            <option value="Infrastructure Improvement" selected>Infrastructure Improvement</option>
                            <option value="Optional Enhancement">Optional Enhancement</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Target Quarter</label>
                        <select class="form-control" name="roadmap_quarter" id="tbrRoadmapQuarter">
                            <option value="Q1">Q1</option>
                            <option value="Q2">Q2</option>
                            <option value="Q3">Q3</option>
                            <option value="Q4">Q4</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Fiscal Year</label>
                        <input type="number" class="form-control" name="roadmap_fiscal_year" id="tbrRoadmapYear" value="<?= date('Y') ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Estimated Cost</label>
                        <input type="number" step="0.01" class="form-control" name="roadmap_estimated_cost" id="tbrRoadmapCost" value="0.00">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Priority</label>
                        <select class="form-control" name="roadmap_priority" id="tbrRoadmapPriority">
                            <option value="Critical">Critical</option>
                            <option value="High">High</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-8 form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="roadmap_description" id="tbrRoadmapDesc" rows="2"></textarea>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Status</label>
                        <select class="form-control" name="roadmap_status" id="tbrRoadmapStatus">
                            <option value="Planned">Planned</option>
                            <option value="Approved">Approved</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Deferred">Deferred</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Roadmap Item</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="tbrActionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="tbrActionForm" class="modal-content">
            <input type="hidden" name="action_id" id="tbrActionId" value="0">
            <div class="modal-header bg-dark">
                <h5 class="modal-title">TBR Action Item</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Task Description <strong class="text-danger">*</strong></label>
                    <textarea class="form-control" name="action_description" id="tbrActionDesc" rows="2" required></textarea>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Responsible Party</label>
                        <select class="form-control" name="action_responsible_party" id="tbrActionParty">
                            <option value="MSP">MSP</option>
                            <option value="Client">Client</option>
                            <option value="Shared">Shared</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Assigned Employee (MSP)</label>
                        <select class="form-control" name="action_assigned_user_id" id="tbrActionUser">
                            <option value="">- Unassigned -</option>
                            <?php foreach ($users as $u) { ?>
                                <option value="<?= intval($u['user_id']) ?>"><?= escapeHtml($u['user_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Client Responsibility / Owner</label>
                        <input type="text" class="form-control" name="action_client_owner" id="tbrActionClientOwner" placeholder="Client contact name">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Priority</label>
                        <select class="form-control" name="action_priority" id="tbrActionPriority">
                            <option value="Critical">Critical</option>
                            <option value="High">High</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Due Date</label>
                        <input type="date" class="form-control" name="action_due_date" id="tbrActionDueDate">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Status</label>
                        <select class="form-control" name="action_status" id="tbrActionStatus">
                            <option value="Not Started">Not Started</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Waiting on Client">Waiting on Client</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <?php if ($can_internal) { ?>
                    <div class="form-group mb-0">
                        <label class="text-danger">Internal Notes (MSP Only)</label>
                        <textarea class="form-control" name="action_internal_notes" id="tbrActionInternalNotes" rows="2"></textarea>
                    </div>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Action Item</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="tbrCompleteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-double mr-2"></i>Complete Technology Business Review</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="tbrCompleteValidationBox"></div>
                <div class="form-group">
                    <label>Next Review Date <strong class="text-danger">*</strong></label>
                    <input type="date" class="form-control" id="tbrCompleteNextDate" value="<?= escapeHtml((string)$meeting['tbr_next_review_date']) ?>">
                </div>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" id="tbrCompleteApprove" <?= !empty($meeting['tbr_client_report_approved']) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="tbrCompleteApprove">Approve &amp; automatically generate the Client-Facing PDF Report upon completion</label>
                </div>
                <?php if (tbrHasPermission('schedule')) { ?>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="tbrCompleteScheduleNext" checked>
                        <label class="custom-control-label" for="tbrCompleteScheduleNext">Automatically schedule the next <?= $meeting['tbr_type'] === 'Annual' ? 'Annual' : 'Quarterly' ?> review on the Next Review Date (and carry forward open items)</label>
                    </div>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="tbrConfirmCompleteBtn">Confirm &amp; Complete TBR</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="tbrReopenModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="tbrReopenForm" class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Reopen Completed TBR</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-0">
                    <label>Reason for Reopening <strong class="text-danger">*</strong></label>
                    <textarea class="form-control" id="tbrReopenReason" rows="3" required placeholder="Document why this completed review is being reopened (recorded in Audit Trail)..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning">Reopen TBR</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="tbrEmailReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="tbrEmailReportForm" class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-envelope mr-2"></i>Email Client-Facing TBR Report</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Select Client Recipients</label>
                    <div class="border rounded p-2 bg-light">
                        <?php foreach ($attendees as $att) {
                            if ($att['attendee_type'] === 'internal' || empty($att['attendee_email'])) continue;
                        ?>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="recipients[]" value="<?= escapeHtml($att['attendee_email']) ?>" id="em_rec_<?= intval($att['attendee_id']) ?>" checked>
                                <label class="custom-control-label" for="em_rec_<?= intval($att['attendee_id']) ?>"><?= escapeHtml($att['attendee_name']) ?> &lt;<?= escapeHtml($att['attendee_email']) ?>&gt;</label>
                            </div>
                        <?php } ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Additional Email Recipients <small class="text-muted">(comma-separated)</small></label>
                    <input type="text" class="form-control" name="extra_recipients" placeholder="executive@client.com">
                </div>
                <div class="form-group">
                    <label>Subject <small class="text-muted">(leave blank for default template)</small></label>
                    <input type="text" class="form-control" name="email_subject" placeholder="Technology Business Review Report: <?= escapeHtml($meeting['client_name']) ?>">
                </div>
                <div class="form-group mb-0">
                    <label>Message <small class="text-muted">(leave blank for default template)</small></label>
                    <textarea class="form-control" name="email_body" rows="4" placeholder="Optional custom message..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-info" id="tbrSendReportEmailBtn"><i class="fas fa-paper-plane mr-1"></i>Queue Report Email</button>
            </div>
        </form>
    </div>
</div>

<script>
window.TBR_CONFIG = <?= json_encode([
    'tbrId' => $tbr_id,
    'clientId' => $client_id,
    'canEdit' => $can_edit,
    'canSupportWrite' => $can_support_write,
    'csrfToken' => $_SESSION['csrf_token'],
    'currencyCode' => $currency_code,
    'sectionVersions' => $section_versions,
    'initialFindings' => $findings,
    'initialRecommendations' => $recommendations,
    'initialRoadmap' => ['items' => $roadmap_items, 'totals' => $roadmap_totals],
    'initialActions' => $action_items,
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/agent/js/tbr.js?v=<?= filemtime(__DIR__ . '/js/tbr.js') ?>"></script>

<?php require_once "../includes/footer.php";
