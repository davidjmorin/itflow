<?php

require_once "includes/inc_all.php";

if (empty($session_is_admin) && lookupUserPermission('module_meetings') < 1) {
    enforceUserPermission('module_meetings', 1);
}

$is_admin = !empty($session_is_admin);
$as_of_date = date('Y-m-d');
$quarter_key = $_GET['quarter'] ?? meetingGetQuarterBounds($as_of_date)['quarter_key'];
$qb = meetingGetQuarterBounds($quarter_key);
$prev_q = meetingStepQuarterKey($qb['quarter_key'], -1);
$next_q = meetingStepQuarterKey($qb['quarter_key'], 1);

$filter_user = intval($_GET['user_id'] ?? 0);
$selected_metric_id = intval($_GET['metric_id'] ?? 0);

$metrics = meetingListAccessibleMetrics($session_user_id, $is_admin, $qb['quarter_key'], $as_of_date);
if ($filter_user > 0) {
    $metrics = array_values(array_filter($metrics, fn($m) => intval($m['owner_user_id']) === $filter_user));
}

if ($selected_metric_id <= 0 && !empty($metrics)) {
    $selected_metric_id = intval($metrics[0]['metric_id']);
}

$all_users = [];
$ru = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
while ($ru && ($u = mysqli_fetch_assoc($ru))) {
    $all_users[] = $u;
}

$accessible_series = meetingListAccessibleSeries($session_user_id, $is_admin);
?>
<link rel="stylesheet" href="/agent/css/meetings.css?v=<?= filemtime(__DIR__ . '/css/meetings.css') ?>">

<div class="card card-dark mb-3">
    <div class="card-header py-2">
        <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:0.75rem;">
            <div class="d-flex flex-wrap align-items-center" style="gap:0.75rem;">
                <h3 class="card-title mb-0"><i class="fas fa-tachometer-alt mr-2"></i>Metrics &amp; Quarterly Goals</h3>
                <div class="btn-group btn-group-sm">
                    <a href="?quarter=<?= escapeHtml($prev_q) ?>&user_id=<?= $filter_user ?>" class="btn btn-outline-light"><i class="fas fa-chevron-left"></i></a>
                    <span class="btn btn-light font-weight-bold"><?= escapeHtml($qb['label']) ?> <small class="text-muted">(<?= escapeHtml($qb['range_label']) ?>)</small></span>
                    <a href="?quarter=<?= escapeHtml($next_q) ?>&user_id=<?= $filter_user ?>" class="btn btn-outline-light"><i class="fas fa-chevron-right"></i></a>
                </div>
                <form class="form-inline" method="get">
                    <input type="hidden" name="quarter" value="<?= escapeHtml($qb['quarter_key']) ?>">
                    <select class="form-control form-control-sm" name="user_id" onchange="this.form.submit()">
                        <option value="0">- All Team Members -</option>
                        <?php foreach ($all_users as $u) { ?>
                            <option value="<?= intval($u['user_id']) ?>" <?= $filter_user === intval($u['user_id']) ? 'selected' : '' ?>><?= escapeHtml($u['user_name']) ?></option>
                        <?php } ?>
                    </select>
                </form>
            </div>
            <div>
                <button type="button" class="btn btn-primary btn-sm" id="metricsAddBtn">
                    <i class="fas fa-plus mr-1"></i>Add Metric
                </button>
            </div>
        </div>
    </div>
</div>

<?php if (empty($metrics)) { ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
            <h4>No Metrics Yet</h4>
            <p class="text-muted">Let your team own and track the numbers that power your quarterly goals and meetings.</p>
            <button type="button" class="btn btn-primary" id="metricsAddEmptyBtn"><i class="fas fa-plus mr-1"></i>Add Metric</button>
        </div>
    </div>
<?php } else { ?>
    <div class="row">
        <div class="col-lg-4 mb-3">
            <div class="card h-100">
                <div class="card-body p-2">
                    <input type="search" class="form-control form-control-sm mb-2" id="metricsListSearch" placeholder="Search (Metric name, Assignee)">
                    <div class="list-group list-group-flush" id="metricsSidebarList">
                        <?php foreach ($metrics as $m) {
                            $sm = $m['summary'];
                            $is_sel = intval($m['metric_id']) === $selected_metric_id;
                            $fmt = function($v) use ($m) {
                                if ($v === null) return '-';
                                if ($m['unit'] === 'Currency') return '$' . number_format($v, 2);
                                if ($m['unit'] === 'Percent') return number_format($v, 1) . '%';
                                return number_format($v, 2) + 0;
                            };
                        ?>
                            <a href="#" class="list-group-item list-group-item-action px-2 py-2 mtg-metric-list-item <?= $is_sel ? 'active' : '' ?>" data-metric-id="<?= intval($m['metric_id']) ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center text-truncate" style="gap:0.5rem;">
                                        <span class="mtg-health-square mtg-health-<?= escapeHtml($sm['status_color']) ?>"></span>
                                        <span class="font-weight-bold text-truncate"><?= escapeHtml($m['metric_name']) ?></span>
                                    </div>
                                    <div class="text-right small text-nowrap ml-2">
                                        <div><?= escapeHtml($sm['interval_label']) ?>: <strong><?= escapeHtml($fmt($sm['interval_actual'])) ?> / <?= escapeHtml($fmt($sm['interval_target'])) ?></strong></div>
                                        <div class="text-muted"><?= escapeHtml($qb['label']) ?>: <strong><?= escapeHtml($fmt($sm['to_date_actual'])) ?> / <?= escapeHtml($fmt($sm['to_date_target'])) ?></strong></div>
                                    </div>
                                </div>
                                <div class="small mt-1 <?= $is_sel ? 'text-light' : 'text-muted' ?>">
                                    <?= escapeHtml($m['owner_name']) ?> &middot; <?= escapeHtml($m['metric_type']) ?> (<?= escapeHtml($m['frequency']) ?>)
                                </div>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center" style="gap:0.6rem;">
                        <i class="far fa-star mtg-star-btn" id="mdStarIcon"></i>
                        <span class="mtg-health-square mtg-health-gray" id="mdStatusSquare"></span>
                        <h4 class="mb-0 font-weight-bold" id="mdTitle">Loading...</h4>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="mdEditPropsBtn"><i class="fas fa-sliders-h mr-1"></i>Properties</button>
                        <a href="#" class="btn btn-outline-success" id="mdExportCsvBtn"><i class="fas fa-download mr-1"></i>CSV</a>
                        <button type="button" class="btn btn-outline-danger" id="mdDeleteBtn"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="card-body py-2 bg-light border-bottom small" id="mdPropsBar"></div>
                <div class="card-body">
                    <div class="mb-3" style="height:220px;">
                        <canvas id="mdChartCanvas"></canvas>
                    </div>

                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="border rounded p-2 bg-light">
                                <small class="text-muted d-block" id="mdIntervalCardTitle">Current Interval</small>
                                <strong class="h5 mb-0" id="mdIntervalCardVal">-</strong>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2 bg-light">
                                <small class="text-muted d-block"><?= escapeHtml($qb['label']) ?> To Date (Actual / Goal)</small>
                                <strong class="h5 mb-0" id="mdQuarterCardVal">-</strong>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2 bg-dark text-white">
                                <small class="d-block">Target Pace (Work Days: <span id="mdPaceDays">0/0</span>)</small>
                                <strong class="h5 mb-0" id="mdPaceCardVal">-</strong>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2" style="gap:0.5rem;">
                        <div class="form-inline">
                            <label class="small font-weight-bold mr-2">Set Quarter Goal:</label>
                            <input type="number" step="0.01" class="form-control form-control-sm mr-2" id="mdQuickQuarterGoal" placeholder="e.g. 15000" style="width:130px;">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="mdApplyQuarterGoalBtn">Auto-Split Across <?= escapeHtml($qb['label']) ?></button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="mdClearDataBtn"><i class="fas fa-trash-alt mr-1"></i>Clear Data</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Name</th>
                                    <th style="width:130px;">Actual</th>
                                    <th style="width:130px;">Rolling Target</th>
                                    <th style="width:130px;">Approved Target</th>
                                    <th>Period To Date</th>
                                    <th class="text-center" style="width:110px;">Status Update</th>
                                </tr>
                            </thead>
                            <tbody id="mdTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<div class="modal fade" id="metricsFormModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="metricsForm" class="modal-content">
            <input type="hidden" name="metric_id" id="mfMetricId" value="0">
            <input type="hidden" name="quarter_key" value="<?= escapeHtml($qb['quarter_key']) ?>">
            <div class="modal-header bg-dark">
                <h5 class="modal-title">Add / Edit Metric</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Metric Owner <strong class="text-danger">*</strong></label>
                        <select class="form-control" name="owner_user_id" id="mfOwner">
                            <?php foreach ($all_users as $u) { ?>
                                <option value="<?= intval($u['user_id']) ?>" <?= intval($u['user_id']) === $session_user_id ? 'selected' : '' ?>><?= escapeHtml($u['user_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Metric Name <strong class="text-danger">*</strong></label>
                        <input type="text" class="form-control" name="metric_name" id="mfName" placeholder="e.g. Sales, New Clients, MRR" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Metric Type</label>
                        <select class="form-control" name="metric_type" id="mfType">
                            <option value="Count">Count (Cumulative - summed across quarter)</option>
                            <option value="Measurement">Measurement (Non-Cumulative - e.g. MRR)</option>
                            <option value="Flat">Flat (Constant target - e.g. NPS, SLA %)</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Frequency</label>
                        <select class="form-control" name="frequency" id="mfFreq">
                            <option value="Monthly">Monthly</option>
                            <option value="Weekly">Weekly</option>
                            <option value="Daily">Daily</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Unit</label>
                        <select class="form-control" name="unit" id="mfUnit">
                            <option value="Number">Number</option>
                            <option value="Currency">Currency ($)</option>
                            <option value="Percent">Percent (%)</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Quarter Goal (Auto-splits into table)</label>
                        <input type="number" step="0.01" class="form-control" name="quarter_goal" id="mfQuarterGoal" placeholder="Optional e.g. 15">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Start Target <small class="text-muted">(for Measurement)</small></label>
                        <input type="number" step="0.01" class="form-control" name="start_target" id="mfStartTarget" value="0">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Goal Direction</label>
                        <select class="form-control" name="goal_direction" id="mfDir">
                            <option value="HigherBetter">Higher is Better</option>
                            <option value="LowerBetter">Lower is Better</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Link to Meeting Series</label>
                        <select class="form-control" name="series_id" id="mfSeries">
                            <option value="0">- Company / Standalone -</option>
                            <?php foreach ($accessible_series as $as) { ?>
                                <option value="<?= intval($as['series_id']) ?>"><?= escapeHtml($as['series_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Scope</label>
                        <select class="form-control" name="scope" id="mfScope">
                            <option value="Attendee">Attendee Metric</option>
                            <option value="Company">Company Metric</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Privacy</label>
                        <select class="form-control" name="privacy" id="mfPrivacy">
                            <option value="Public">Public</option>
                            <option value="Private">Private (Owner/Admin only)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="d-block">Work Days (used for Target Pace)</label>
                    <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $wd) { ?>
                        <div class="custom-control custom-checkbox custom-control-inline">
                            <input type="checkbox" class="custom-control-input mf-wd" id="mf_wd_<?= $wd ?>" name="work_days[]" value="<?= $wd ?>" <?= in_array($wd, ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'], true) ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="mf_wd_<?= $wd ?>"><?= $wd ?></label>
                        </div>
                    <?php } ?>
                </div>

                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="mfAutoStatus" name="auto_set_status" value="1" checked>
                            <label class="custom-control-label" for="mfAutoStatus">Auto Set Status</label>
                        </div>
                        <div class="custom-control custom-checkbox mt-1">
                            <input type="checkbox" class="custom-control-input" id="mfStarred" name="is_starred" value="1">
                            <label class="custom-control-label" for="mfStarred">Starred Priority</label>
                        </div>
                    </div>
                    <div class="col-md-4 form-group mb-0">
                        <label class="small">Super Green (&ge; % of Pace)</label>
                        <input type="number" step="1" class="form-control form-control-sm" name="super_green_pct" id="mfSgPct" value="120">
                    </div>
                    <div class="col-md-4 form-group mb-0">
                        <label class="small">Yellow (&ge; % of Pace)</label>
                        <input type="number" step="1" class="form-control form-control-sm" name="yellow_pct" id="mfYPct" value="80">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Metric</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="mdNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="mdNoteForm" class="modal-content">
            <input type="hidden" id="mdNotePeriodKey" value="">
            <div class="modal-header bg-dark">
                <h5 class="modal-title">Status Update Note (<span id="mdNoteLabel"></span>)</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <textarea class="form-control" id="mdNoteText" rows="4" placeholder="Add context or commentary for this interval..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Note</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const csrf = <?= json_encode($_SESSION['csrf_token']) ?>;
    const quarterKey = <?= json_encode($qb['quarter_key']) ?>;
    const asOfDate = <?= json_encode($as_of_date) ?>;
    let selectedMetricId = <?= intval($selected_metric_id) ?>;
    let currentDetail = null;
    let chartInst = null;

    function esc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    function fmt(v, unit) {
        if (v === null || v === undefined || v === '') return '-';
        const n = Number(v);
        if (unit === 'Currency') return '$' + n.toLocaleString(undefined, { maximumFractionDigits: 2 });
        if (unit === 'Percent') return n.toLocaleString(undefined, { maximumFractionDigits: 2 }) + '%';
        return n.toLocaleString(undefined, { maximumFractionDigits: 2 });
    }

    function renderDetail(res) {
        currentDetail = res;
        const m = res.metric;
        const s = res.summary;
        const canEdit = !!m.can_edit;

        $('#mdTitle').text(m.metric_name);
        $('#mdStarIcon').attr('class', (m.is_starred ? 'fas fa-star mtg-star-btn starred' : 'far fa-star mtg-star-btn'));
        $('#mdStatusSquare').attr('class', 'mtg-health-square mtg-health-' + s.status_color);
        $('#mdExportCsvBtn').attr('href', '/agent/ajax_meetings.php?action=metric_export_csv&metric_id=' + m.metric_id + '&quarter=' + encodeURIComponent(quarterKey) + '&as_of=' + encodeURIComponent(asOfDate));

        $('#mdPropsBar').html(
            '<strong>Owner:</strong> ' + esc(m.owner_name)
            + ' &middot; <strong>Type:</strong> ' + esc(m.metric_type)
            + ' &middot; <strong>Frequency:</strong> ' + esc(m.frequency)
            + ' &middot; <strong>Work Days:</strong> ' + esc(m.work_days)
            + ' &middot; <strong>Auto Status:</strong> ' + (m.auto_set_status ? 'On' : 'Manual')
        );

        $('#mdIntervalCardTitle').text(s.interval_label || 'Interval');
        $('#mdIntervalCardVal').text(fmt(s.interval_actual, m.unit) + ' / ' + fmt(s.interval_target, m.unit));
        $('#mdQuarterCardVal').text(fmt(s.to_date_actual, m.unit) + ' / ' + fmt(s.to_date_target, m.unit));
        $('#mdPaceDays').text(s.elapsed_work_days + '/' + s.total_work_days);
        $('#mdPaceCardVal').text(fmt(s.target_pace, m.unit));

        const rowsHtml = (s.rows || []).map(function(r) {
            const ptdTxt = fmt(r.period_to_date_actual, m.unit) + ' / ' + fmt(r.period_to_date_target, m.unit);
            const dis = canEdit ? '' : 'disabled';
            const actVal = r.actual_value !== null ? r.actual_value : '';
            const hasNote = r.status_note && r.status_note.trim() !== '';
            return '<tr data-pk="' + esc(r.period_key) + '">'
                + '<td><span class="mtg-health-square mtg-health-' + esc(r.status_color) + ' mr-2"></span><strong>' + esc(r.label) + '</strong></td>'
                + '<td><input type="number" step="0.01" class="form-control form-control-sm md-cell-input" data-field="actual_value" placeholder="-" value="' + esc(actVal) + '" ' + dis + '></td>'
                + '<td><input type="number" step="0.01" class="form-control form-control-sm md-cell-input" data-field="rolling_target" value="' + esc(r.rolling_target) + '" ' + dis + '></td>'
                + '<td><input type="number" step="0.01" class="form-control form-control-sm md-cell-input" data-field="approved_target" value="' + esc(r.approved_target) + '" ' + dis + '></td>'
                + '<td class="font-weight-bold">' + esc(ptdTxt) + '</td>'
                + '<td class="text-center"><button type="button" class="btn btn-xs btn-' + (hasNote ? 'info' : 'outline-secondary') + ' md-note-btn" data-pk="' + esc(r.period_key) + '" data-label="' + esc(r.label) + '"><i class="far fa-sticky-note"></i></button></td>'
                + '</tr>';
        }).join('');
        $('#mdTableBody').html(rowsHtml);

        const ctx = document.getElementById('mdChartCanvas');
        if (ctx && typeof Chart !== 'undefined') {
            if (chartInst) chartInst.destroy();
            chartInst = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: (s.rows || []).map(r => r.short_label || r.label),
                    datasets: [
                        { label: 'Actual', data: (s.rows || []).map(r => r.actual_value), backgroundColor: '#0d9488' },
                        { label: 'Approved Target', data: (s.rows || []).map(r => r.approved_target), type: 'line', borderColor: '#1e293b', borderWidth: 2, fill: false }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        const listItem = $('.mtg-metric-list-item[data-metric-id="' + m.metric_id + '"]');
        listItem.find('.mtg-health-square').attr('class', 'mtg-health-square mtg-health-' + s.status_color);
    }

    function loadMetric(mid) {
        if (!mid) return;
        selectedMetricId = mid;
        $('.mtg-metric-list-item').removeClass('active');
        $('.mtg-metric-list-item[data-metric-id="' + mid + '"]').addClass('active');
        $.getJSON('/agent/ajax_meetings.php', {
            action: 'metric_detail',
            metric_id: mid,
            quarter: quarterKey,
            as_of: asOfDate
        }, function(res) {
            if (res && res.ok) renderDetail(res);
        });
    }

    $(function() {
        if (selectedMetricId > 0) {
            loadMetric(selectedMetricId);
        }

        $('.mtg-metric-list-item').on('click', function(e) {
            e.preventDefault();
            loadMetric(Number($(this).data('metric-id')));
        });

        $('#metricsListSearch').on('input', function() {
            const q = $(this).val().toLowerCase();
            $('.mtg-metric-list-item').each(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
            });
        });

        $('#metricsAddBtn, #metricsAddEmptyBtn').on('click', function() {
            $('#metricsForm')[0].reset();
            $('#mfMetricId').val(0);
            $('#metricsFormModal').modal('show');
        });

        $('#mdEditPropsBtn').on('click', function() {
            if (!currentDetail) return;
            const m = currentDetail.metric;
            $('#mfMetricId').val(m.metric_id);
            $('#mfOwner').val(m.owner_user_id);
            $('#mfName').val(m.metric_name);
            $('#mfType').val(m.metric_type);
            $('#mfFreq').val(m.frequency);
            $('#mfUnit').val(m.unit);
            $('#mfDir').val(m.goal_direction);
            $('#mfStartTarget').val(m.start_target);
            $('#mfSeries').val(m.series_id || 0);
            $('#mfScope').val(m.scope);
            $('#mfPrivacy').val(m.privacy);
            $('#mfAutoStatus').prop('checked', !!m.auto_set_status);
            $('#mfStarred').prop('checked', !!m.is_starred);
            $('#mfSgPct').val(m.super_green_pct);
            $('#mfYPct').val(m.yellow_pct);
            $('#mfQuarterGoal').val('');
            const wds = (m.work_days || '').split(',');
            $('.mf-wd').each(function() { $(this).prop('checked', wds.indexOf($(this).val()) !== -1); });
            $('#metricsFormModal').modal('show');
        });

        $('#metricsForm').on('submit', function(e) {
            e.preventDefault();
            $.post('/agent/ajax_meetings.php', $(this).serialize() + '&action=metric_save&csrf_token=' + encodeURIComponent(csrf), function(res) {
                if (res && res.ok) {
                    window.location.href = '/agent/metrics.php?quarter=' + encodeURIComponent(quarterKey) + '&metric_id=' + res.metric_id;
                }
            }, 'json').fail(function(xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to save metric.');
            });
        });

        $(document).on('change', '.md-cell-input', function() {
            const tr = $(this).closest('tr');
            const pk = tr.data('pk');
            const payload = {
                action: 'metric_save_cell',
                metric_id: selectedMetricId,
                period_key: pk,
                quarter: quarterKey,
                as_of: asOfDate,
                actual_value: tr.find('[data-field="actual_value"]').val(),
                rolling_target: tr.find('[data-field="rolling_target"]').val(),
                approved_target: tr.find('[data-field="approved_target"]').val(),
                csrf_token: csrf
            };
            $.post('/agent/ajax_meetings.php', payload, function(res) {
                if (res && res.ok) {
                    loadMetric(selectedMetricId);
                    toastr.success('Saved');
                }
            }, 'json');
        });

        $('#mdApplyQuarterGoalBtn').on('click', function() {
            const val = $('#mdQuickQuarterGoal').val();
            if (val === '') return;
            $.post('/agent/ajax_meetings.php', {
                action: 'metric_split_quarter_goal',
                metric_id: selectedMetricId,
                quarter: quarterKey,
                as_of: asOfDate,
                total_goal: val,
                csrf_token: csrf
            }, function(res) {
                if (res && res.ok) {
                    loadMetric(selectedMetricId);
                    toastr.success('Quarterly goal split across intervals.');
                }
            }, 'json');
        });

        $('#mdClearDataBtn').on('click', function() {
            if (!confirm('Clear all actual values for ' + quarterKey + '?')) return;
            $.post('/agent/ajax_meetings.php', {
                action: 'metric_clear_data',
                metric_id: selectedMetricId,
                quarter: quarterKey,
                as_of: asOfDate,
                csrf_token: csrf
            }, function(res) {
                if (res && res.ok) {
                    loadMetric(selectedMetricId);
                    toastr.info('Cleared quarter actuals.');
                }
            }, 'json');
        });

        $('#mdDeleteBtn').on('click', function() {
            if (!confirm('Delete this metric?')) return;
            $.post('/agent/ajax_meetings.php', { action: 'metric_delete', metric_id: selectedMetricId, csrf_token: csrf }, function() {
                window.location.href = '/agent/metrics.php?quarter=' + encodeURIComponent(quarterKey);
            }, 'json');
        });

        $(document).on('click', '.md-note-btn', function() {
            const pk = $(this).data('pk');
            const row = (currentDetail.summary.rows || []).find(r => r.period_key === pk);
            $('#mdNotePeriodKey').val(pk);
            $('#mdNoteLabel').text($(this).data('label'));
            $('#mdNoteText').val(row ? (row.status_note || '') : '');
            $('#mdNoteModal').modal('show');
        });

        $('#mdNoteForm').on('submit', function(e) {
            e.preventDefault();
            $.post('/agent/ajax_meetings.php', {
                action: 'metric_save_cell',
                metric_id: selectedMetricId,
                period_key: $('#mdNotePeriodKey').val(),
                quarter: quarterKey,
                as_of: asOfDate,
                status_note: $('#mdNoteText').val(),
                csrf_token: csrf
            }, function(res) {
                if (res && res.ok) {
                    $('#mdNoteModal').modal('hide');
                    loadMetric(selectedMetricId);
                }
            }, 'json');
        });
    });
})();
</script>

<?php require_once "../includes/footer.php";
