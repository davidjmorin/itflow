<?php

require_once "includes/inc_all.php";

if (empty($session_is_admin) && lookupUserPermission('module_meetings') < 1) {
    enforceUserPermission('module_meetings', 1);
}

$series_id = intval($_GET['series_id'] ?? 0);
$series = meetingGetSeries($series_id);

if (!$series || !meetingCanAccessSeries($series, $session_user_id, !empty($session_is_admin))) {
    echo "<div class='alert alert-danger mt-4'>Meeting not found or access denied. <a href='meetings.php'>Back to Meetings</a></div>";
    require_once "../includes/footer.php";
    exit();
}

$is_admin = !empty($session_is_admin);
$can_edit_any = meetingIsFacilitatorOrAdmin($series, $session_user_id, $is_admin);

$requested_date = $_GET['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested_date)) {
    $requested_date = meetingGetDefaultOccurrenceDate($series);
}
$date_override = !empty($_GET['override']);
$occurrence = meetingGetOrCreateOccurrence($series_id, $requested_date, $date_override);
$occurrence_id = intval($occurrence['occurrence_id']);

$initial_state = meetingBuildOccurrenceState($occurrence_id, $session_user_id, $is_admin);
$accessible_series = meetingListAccessibleSeries($session_user_id, $is_admin);
$period_options = meetingGetPeriodOptions($requested_date);
if (!in_array($initial_state['occurrence']['period_label'], $period_options, true)) {
    array_unshift($period_options, $initial_state['occurrence']['period_label']);
}

$all_users = [];
$ru = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
while ($ru && ($u = mysqli_fetch_assoc($ru))) {
    $all_users[] = $u;
}

$time_display = date('g:i A', strtotime($series['start_time'])) . ' - ' . date('g:i A', strtotime($series['end_time']));
?>
<link rel="stylesheet" href="/agent/css/meetings.css?v=<?= filemtime(__DIR__ . '/css/meetings.css') ?>">

<div class="mtg-workspace">
    <div class="mtg-left-panel">
        <div class="p-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-muted text-uppercase font-weight-bold">Meeting:</small>
                <span id="mtgSyncBadge" class="mtg-live-pill"><span class="mtg-live-dot"></span> Live</span>
            </div>

            <div class="dropdown mb-1">
                <button class="btn btn-light btn-sm btn-block text-left d-flex justify-content-between align-items-center border font-weight-bold" type="button" data-toggle="dropdown">
                    <span class="text-truncate"><?= escapeHtml($series['series_name']) ?></span>
                    <i class="fas fa-caret-down ml-2"></i>
                </button>
                <div class="dropdown-menu w-100">
                    <?php foreach ($accessible_series as $as) { ?>
                        <a class="dropdown-item <?= intval($as['series_id']) === $series_id ? 'active' : '' ?>" href="/agent/meeting.php?series_id=<?= intval($as['series_id']) ?>">
                            <?= escapeHtml($as['series_name']) ?>
                        </a>
                    <?php } ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="/agent/meetings.php"><i class="fas fa-th-list mr-2"></i>All Meetings</a>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center small text-muted">
                <span><?= escapeHtml($series['facilitator_name'] ?: 'Facilitator') ?></span>
                <?php if ($can_edit_any) { ?>
                    <a href="#" class="ajax-modal small" data-modal-url="/agent/modals/meeting/meeting_series_modal.php?series_id=<?= $series_id ?>" data-modal-size="lg">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                <?php } ?>
            </div>
        </div>

        <div class="mtg-icon-strip">
            <a href="#" data-section="details" title="Details"><i class="far fa-calendar-alt"></i></a>
            <a href="#" data-section="agenda" title="Agenda"><i class="fas fa-list"></i></a>
            <a href="#" data-section="attendees" title="Attendees"><i class="fas fa-users"></i></a>
            <a href="#" data-section="actions" title="Action Items"><i class="fas fa-fire"></i></a>
            <a href="#" data-section="notes" title="Notes"><i class="far fa-sticky-note"></i></a>
            <a href="#" data-section="attachments" title="Attachments"><i class="fas fa-paperclip"></i></a>
        </div>

        <div class="mtg-accordion-header" data-toggle="collapse" data-target="#mtgSec_details">
            <span><i class="far fa-calendar-alt mr-2 text-secondary"></i>Details</span>
            <i class="fas fa-chevron-up small text-muted"></i>
        </div>
        <div id="mtgSec_details" class="collapse show mtg-accordion-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <a href="/agent/meeting.php?series_id=<?= $series_id ?>&date=<?= escapeHtml($initial_state['occurrence']['prev_date']) ?>" class="btn btn-xs btn-outline-secondary" title="Previous Occurrence">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <input type="date" class="form-control form-control-sm mx-2 text-center" value="<?= escapeHtml($requested_date) ?>" onchange="window.location.href='/agent/meeting.php?series_id=<?= $series_id ?>&date=' + encodeURIComponent(this.value);">
                <a href="/agent/meeting.php?series_id=<?= $series_id ?>&date=<?= escapeHtml($initial_state['occurrence']['next_date']) ?>" class="btn btn-xs btn-outline-secondary" title="Next Occurrence">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>

            <div class="row small mb-2">
                <div class="col-6">
                    <div class="text-muted font-weight-bold">Time</div>
                    <div><?= escapeHtml($time_display) ?></div>
                </div>
                <div class="col-6">
                    <div class="text-muted font-weight-bold">Type</div>
                    <div><i class="fas fa-sync-alt mr-1 text-secondary"></i><?= $series['recurrence_type'] === 'One-Time' ? 'One-Time' : 'Recurring ' . escapeHtml($series['recurrence_type']) ?></div>
                </div>
            </div>

            <div class="row small align-items-center mb-2">
                <div class="col-5">
                    <div class="text-muted font-weight-bold mb-1">Date Override</div>
                    <input type="checkbox" id="mtgDateOverrideChk" <?= !empty($initial_state['occurrence']['date_override']) ? 'checked' : '' ?>>
                </div>
                <div class="col-7">
                    <div class="text-muted font-weight-bold mb-1">Period</div>
                    <select class="form-control form-control-sm" id="mtgPeriodSelect">
                        <?php foreach ($period_options as $po) { ?>
                            <option value="<?= escapeHtml($po) ?>" <?= $initial_state['occurrence']['period_label'] === $po ? 'selected' : '' ?>><?= escapeHtml($po) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="form-group mb-0">
                <label class="small text-muted font-weight-bold mb-1">Team Members Filter</label>
                <select class="form-control form-control-sm select2" id="mtgTeamFilter" multiple data-placeholder="All Team Members">
                    <?php foreach ($initial_state['attendees'] as $att) { ?>
                        <option value="<?= intval($att['user_id']) ?>"><?= escapeHtml($att['user_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mtg-accordion-header" data-toggle="collapse" data-target="#mtgSec_agenda">
            <span><i class="fas fa-list mr-2 text-secondary"></i>Agenda</span>
            <div>
                <?php if ($can_edit_any) { ?>
                    <button type="button" class="btn btn-xs btn-link text-info font-weight-bold p-0 mr-2" id="mtgAddAgendaBtn" onclick="event.stopPropagation();">ADD AGENDA ITEM</button>
                <?php } ?>
                <i class="fas fa-chevron-up small text-muted"></i>
            </div>
        </div>
        <div id="mtgSec_agenda" class="collapse show mtg-accordion-body">
            <div id="mtgAgendaList" class="mb-2"></div>
            <div class="custom-control custom-switch pt-2 border-top">
                <input type="checkbox" class="custom-control-input" id="mtgShowAllToggle" checked>
                <label class="custom-control-label small font-weight-bold" for="mtgShowAllToggle">Show All Agenda Items</label>
            </div>
        </div>

        <div class="mtg-accordion-header" data-toggle="collapse" data-target="#mtgSec_attendees">
            <span><i class="fas fa-users mr-2 text-secondary"></i>Attendees (<span id="mtgAttendeesCount">0</span>)</span>
            <i class="fas fa-chevron-down small text-muted"></i>
        </div>
        <div id="mtgSec_attendees" class="collapse mtg-accordion-body">
            <div id="mtgAttendeesList" class="mb-2"></div>
            <?php if ($can_edit_any) { ?>
                <div class="input-group input-group-sm mt-2">
                    <select class="form-control" id="mtgAddAttendeeSelect">
                        <option value="">+ Add ITFlow user...</option>
                        <?php foreach ($all_users as $u) { ?>
                            <option value="<?= intval($u['user_id']) ?>"><?= escapeHtml($u['user_name']) ?></option>
                        <?php } ?>
                    </select>
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-primary" id="mtgAddAttendeeBtn">Add</button>
                    </div>
                </div>
            <?php } ?>
        </div>

        <div class="mtg-accordion-header" data-toggle="collapse" data-target="#mtgSec_actions">
            <span><i class="fas fa-fire mr-2 text-secondary"></i>Action Items (<span id="mtgSidebarActionsCount">0</span>)</span>
            <i class="fas fa-chevron-up small text-muted"></i>
        </div>
        <div id="mtgSec_actions" class="collapse show mtg-accordion-body">
            <div id="mtgSidebarActionsList" class="mb-2"></div>
            <button type="button" class="btn btn-xs btn-link text-info font-weight-bold p-0 mtg-open-action-modal">
                <i class="fas fa-plus-circle mr-1"></i>Add Action Item
            </button>
        </div>

        <div class="mtg-accordion-header" data-toggle="collapse" data-target="#mtgSec_notes">
            <span><i class="far fa-sticky-note mr-2 text-secondary"></i>Notes</span>
            <div>
                <button type="button" class="btn btn-xs btn-link text-muted p-0 mr-2" data-toggle="modal" data-target="#mtgNotesFullModal" onclick="event.stopPropagation();" title="Fullscreen Notes"><i class="fas fa-expand"></i></button>
                <i class="fas fa-chevron-up small text-muted"></i>
            </div>
        </div>
        <div id="mtgSec_notes" class="collapse show mtg-accordion-body">
            <ul class="nav nav-tabs nav-fill mb-2 small" role="tablist">
                <li class="nav-item"><a class="nav-link active py-1" data-toggle="tab" href="#mtgNoteSharedTab">SHARED</a></li>
                <li class="nav-item"><a class="nav-link py-1" data-toggle="tab" href="#mtgNotePersonalTab">PERSONAL</a></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="mtgNoteSharedTab">
                    <textarea class="form-control form-control-sm" id="mtgSharedNotesInput" rows="4" placeholder="Shared meeting notes (live synced to all attendees)..."><?= escapeHtml($initial_state['occurrence']['shared_notes']) ?></textarea>
                </div>
                <div class="tab-pane fade" id="mtgNotePersonalTab">
                    <textarea class="form-control form-control-sm" id="mtgPersonalNotesInput" rows="4" placeholder="Your private notes (only visible to you)..."><?= escapeHtml($initial_state['personal_note']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="mtg-accordion-header" data-toggle="collapse" data-target="#mtgSec_attachments">
            <span><i class="fas fa-paperclip mr-2 text-secondary"></i>Attachments (<span id="mtgAttachmentsCount">0</span>)</span>
            <i class="fas fa-chevron-down small text-muted"></i>
        </div>
        <div id="mtgSec_attachments" class="collapse mtg-accordion-body">
            <div id="mtgAttachmentsList" class="mb-2"></div>
            <button type="button" class="btn btn-xs btn-outline-primary" data-toggle="modal" data-target="#mtgAttachmentModal">
                <i class="fas fa-plus mr-1"></i>Add File / Link
            </button>
        </div>
    </div>

    <div class="mtg-right-panel">
        <div id="meetingCardsContainer"></div>
    </div>
</div>

<div class="modal fade" id="mtgAgendaModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="mtgAgendaForm" class="modal-content">
            <input type="hidden" name="agenda_item_id" id="mtgAgendaId" value="0">
            <div class="modal-header bg-dark">
                <h5 class="modal-title">Agenda Item</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Title <strong class="text-danger">*</strong></label>
                    <input type="text" class="form-control" name="title" id="mtgAgendaTitle" required maxlength="200">
                </div>
                <div class="form-group">
                    <label>Card Type</label>
                    <select class="form-control" name="item_type" id="mtgAgendaType">
                        <option value="prompt_per_user">Per-User Speech Bubbles (Good News, #1 Thing, Stucks)</option>
                        <option value="metrics">Metrics Review Scorecard</option>
                        <option value="action_items">Action Items Checklist</option>
                        <option value="discussion">Team Discussion</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label>Icon</label>
                    <select class="form-control" name="icon" id="mtgAgendaIcon">
                        <option value="fas fa-comment-dots">Speech Bubble</option>
                        <option value="fas fa-chart-line">Chart / Metrics</option>
                        <option value="fas fa-bullseye">Target / Priority</option>
                        <option value="fas fa-exclamation-triangle">Warning / Stucks</option>
                        <option value="fas fa-bell">Bell / Action Items</option>
                        <option value="fas fa-trophy">Trophy / Wins</option>
                        <option value="fas fa-lightbulb">Lightbulb / Ideas</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Agenda Item</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="mtgMetricModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="mtgMetricForm" class="modal-content">
            <input type="hidden" name="metric_id" id="mtgMetricId" value="0">
            <input type="hidden" name="period_key" id="mtgMetricPeriodKey" value="<?= escapeHtml($initial_state['metric_period']) ?>">
            <input type="hidden" name="quarter_key" id="mtgMetricQuarterKey" value="<?= escapeHtml(meetingGetQuarterBounds($occurrence['occurrence_date'])['quarter_key']) ?>">
            <div class="modal-header bg-dark">
                <h5 class="modal-title">KPI Metric &amp; Quarterly Goals</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5 form-group">
                        <label>Metric Name <strong class="text-danger">*</strong></label>
                        <input type="text" class="form-control" name="metric_name" id="mtgMetricName" placeholder="e.g. Monthly Sales, New Clients Signed" required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Owner</label>
                        <select class="form-control" name="owner_user_id" id="mtgMetricOwner">
                            <?php foreach ($all_users as $u) { ?>
                                <option value="<?= intval($u['user_id']) ?>"><?= escapeHtml($u['user_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Metric Type</label>
                        <select class="form-control" name="metric_type" id="mtgMetricType">
                            <option value="Count">Count (Cumulative)</option>
                            <option value="Measurement">Measurement (Point-in-Time)</option>
                            <option value="Flat">Flat (Constant Target)</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Frequency</label>
                        <select class="form-control" name="frequency" id="mtgMetricFrequency">
                            <option value="Monthly">Monthly</option>
                            <option value="Weekly">Weekly</option>
                            <option value="Daily">Daily</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Unit</label>
                        <select class="form-control" name="unit" id="mtgMetricUnit">
                            <option value="Number">Number</option>
                            <option value="Currency">Currency ($)</option>
                            <option value="Percent">Percent (%)</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Scope</label>
                        <select class="form-control" name="scope" id="mtgMetricScope">
                            <option value="Attendee">Attendee</option>
                            <option value="Company">Company</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Privacy</label>
                        <select class="form-control" name="privacy" id="mtgMetricPrivacy">
                            <option value="Public">Public (Shared)</option>
                            <option value="Private">Private</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Goal Direction</label>
                        <select class="form-control" name="goal_direction" id="mtgMetricDir">
                            <option value="HigherBetter">Higher is Better</option>
                            <option value="LowerBetter">Lower is Better</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Week Start</label>
                        <select class="form-control" name="week_start" id="mtgMetricWeekStart">
                            <option value="Monday">Monday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="d-block">Work Days (used for Target Pace)</label>
                        <div class="d-flex flex-wrap pt-1" style="gap:0.6rem;">
                            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $wd) { ?>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input mtg-work-day-chk" name="work_days[]" value="<?= $wd ?>" id="mtgWd<?= $wd ?>" <?= in_array($wd, ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'], true) ? 'checked' : '' ?>>
                                    <label class="custom-control-label small" for="mtgWd<?= $wd ?>"><?= $wd ?></label>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="row border-top pt-3">
                    <div class="col-md-3 form-group" id="mtgStartTargetWrap">
                        <label>Start Target</label>
                        <input type="number" step="0.01" class="form-control" name="start_target" id="mtgMetricStartTarget" value="0">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Quarter Goal <small class="text-muted">(Auto-split)</small></label>
                        <input type="number" step="0.01" class="form-control" name="quarter_goal" id="mtgMetricQuarterGoal" placeholder="Optional">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Interval Target</label>
                        <input type="number" step="0.01" class="form-control" name="period_target" id="mtgMetricTarget" value="0">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Current Interval Actual</label>
                        <input type="number" step="0.01" class="form-control" name="actual_value" id="mtgMetricActual" placeholder="Leave blank for -">
                    </div>
                </div>
                <div class="row border-top pt-3 align-items-end">
                    <div class="col-md-3 form-group">
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" class="custom-control-input" id="mtgMetricAutoStatus" checked>
                            <label class="custom-control-label" for="mtgMetricAutoStatus">Auto Set Status</label>
                        </div>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Super Green &ge; %</label>
                        <input type="number" step="1" min="100" class="form-control" name="super_green_pct" id="mtgMetricSuperGreenPct" value="120">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Yellow &ge; %</label>
                        <input type="number" step="1" min="1" max="99" class="form-control" name="yellow_pct" id="mtgMetricYellowPct" value="80">
                    </div>
                    <div class="col-md-3 form-group">
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" class="custom-control-input" name="is_starred" value="1" id="mtgMetricStarred">
                            <label class="custom-control-label" for="mtgMetricStarred">Starred Priority</label>
                        </div>
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

<div class="modal fade" id="mtgMetricTableModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <div>
                    <h5 class="modal-title mb-0" id="mtgTableModalTitle">Quarterly Goals &amp; Sales Table</h5>
                    <small class="text-light" id="mtgTableModalSub"></small>
                </div>
                <div class="d-flex align-items-center" style="gap:0.5rem;">
                    <a href="/agent/metrics.php" target="_blank" id="mtgTableModalFullLink" class="btn btn-xs btn-outline-light"><i class="fas fa-external-link-alt mr-1"></i>Full Metrics Page</a>
                    <button type="button" class="close text-white ml-2" data-dismiss="modal"><span>&times;</span></button>
                </div>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4 mb-2">
                        <div class="border rounded p-2 bg-light h-100">
                            <div class="small text-muted" id="mtgTblIntervalLabel">Current Interval</div>
                            <div class="h5 mb-0 font-weight-bold" id="mtgTblIntervalVal">-</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="border rounded p-2 bg-light h-100">
                            <div class="small text-muted" id="mtgTblQuarterLabel">Quarter To Date / Target</div>
                            <div class="h5 mb-0 font-weight-bold" id="mtgTblToDateVal">-</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="border rounded p-2 bg-light h-100">
                            <div class="small text-muted" id="mtgTblWorkDaysInfo">Target Pace</div>
                            <div class="h5 mb-0 font-weight-bold text-primary" id="mtgTblPaceVal">-</div>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 p-2 border rounded bg-white" id="mtgTblSplitWrap" style="gap:0.5rem;">
                    <div class="d-flex align-items-center flex-wrap" style="gap:0.5rem;">
                        <span class="small font-weight-bold text-muted">Quarter Goal:</span>
                        <input type="number" step="0.01" class="form-control form-control-sm" id="mtgTblQuarterGoalInput" style="width:140px;" placeholder="Total Qtr Goal">
                        <button type="button" class="btn btn-sm btn-primary" id="mtgTblSplitBtn">Auto-Split Across Intervals</button>
                    </div>
                    <span class="small text-muted" id="mtgTblSaveHint">Edit any cell and press Tab/Enter to auto-save &amp; sync.</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Interval</th>
                                <th class="text-right" style="width:140px;">Actual (Sales)</th>
                                <th class="text-right" style="width:140px;">Rolling Target</th>
                                <th class="text-right" style="width:140px;">Approved Target</th>
                                <th class="text-right" style="width:170px;">Period To Date</th>
                                <th>Status Note</th>
                            </tr>
                        </thead>
                        <tbody id="mtgTblBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mtgActionModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="mtgActionForm" class="modal-content">
            <input type="hidden" name="action_id" id="mtgActionId" value="0">
            <div class="modal-header bg-dark">
                <h5 class="modal-title">Action Item</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>What needs to be done? <strong class="text-danger">*</strong></label>
                    <textarea class="form-control" name="description" id="mtgActionDesc" rows="2" required></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Owner (Who)</label>
                        <select class="form-control" name="owner_user_id" id="mtgActionOwner">
                            <?php foreach ($all_users as $u) { ?>
                                <option value="<?= intval($u['user_id']) ?>"><?= escapeHtml($u['user_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Due Date (When)</label>
                        <input type="date" class="form-control" name="due_date" id="mtgActionDue">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group mb-0">
                        <label>Priority</label>
                        <select class="form-control" name="priority" id="mtgActionPrio">
                            <option value="High">High</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group mb-0">
                        <label>Status</label>
                        <select class="form-control" name="status" id="mtgActionStatus">
                            <option value="Open">Open</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Action Item</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="mtgAttachmentModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="mtgAttachmentForm" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header bg-dark">
                <h5 class="modal-title">Add Attachment or Link</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" class="form-control" name="title" placeholder="Optional display title">
                </div>
                <div class="form-group">
                    <label>Upload File</label>
                    <input type="file" class="form-control-file" name="file">
                </div>
                <div class="form-group mb-0">
                    <label>Or External URL</label>
                    <input type="url" class="form-control" name="external_url" placeholder="https://...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Attach</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="mtgNotesFullModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title">Meeting Notes</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 form-group mb-md-0">
                        <label>Shared Notes (Live Synced)</label>
                        <textarea class="form-control" id="mtgSharedNotesFull" rows="12"><?= escapeHtml($initial_state['occurrence']['shared_notes']) ?></textarea>
                    </div>
                    <div class="col-md-6 form-group mb-0">
                        <label>Personal Notes (Private to You)</label>
                        <textarea class="form-control" id="mtgPersonalNotesFull" rows="12"><?= escapeHtml($initial_state['personal_note']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/custom_legacy_js.php'; ?>
<script>
window.MTG_CONFIG = <?= json_encode([
    'seriesId' => $series_id,
    'occurrenceId' => $occurrence_id,
    'currentUserId' => $session_user_id,
    'canEditAny' => $can_edit_any,
    'csrfToken' => $_SESSION['csrf_token'],
    'initialState' => $initial_state,
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/libs/SortableJS/Sortable.min.js"></script>
<script src="/agent/js/meetings.js?v=<?= filemtime(__DIR__ . '/js/meetings.js') ?>"></script>

<?php require_once "../includes/footer.php";
