<?php

require_once '../../../includes/modal_header.php';

if (empty($session_is_admin) && lookupUserPermission('module_meetings') < 2) {
    echo json_encode(['error' => WORDING_ROLECHECK_FAILED]);
    exit();
}

$series_id = intval($_GET['series_id'] ?? 0);
$is_edit = $series_id > 0;
$series = null;
$selected_attendees = [$session_user_id];

if ($is_edit) {
    $series = meetingGetSeries($series_id);
    if (!$series || !meetingIsFacilitatorOrAdmin($series, $session_user_id, !empty($session_is_admin))) {
        echo json_encode(['error' => 'Meeting series not found or insufficient permission.']);
        exit();
    }
    $selected_attendees = [];
    $ra = mysqli_query($mysqli, "SELECT user_id FROM meeting_attendees WHERE series_id = $series_id");
    while ($ra && ($ar = mysqli_fetch_assoc($ra))) {
        $selected_attendees[] = intval($ar['user_id']);
    }
}

$users = [];
$ru = mysqli_query($mysqli, "SELECT user_id, user_name, user_email FROM users WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
while ($ru && ($u = mysqli_fetch_assoc($ru))) {
    $users[] = $u;
}

$name = $series ? $series['series_name'] : '';
$desc = $series ? (string)$series['series_description'] : '';
$fac_id = $series ? intval($series['facilitator_user_id']) : intval($session_user_id);
$rec_type = $series ? $series['recurrence_type'] : 'Daily';
$rec_days = $series ? explode(',', (string)$series['recurrence_days']) : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
$start_date = $series ? $series['start_date'] : date('Y-m-d');
$start_time = $series ? substr($series['start_time'], 0, 5) : '09:00';
$end_time = $series ? substr($series['end_time'], 0, 5) : '09:30';
$tz = $series ? $series['timezone'] : ($config_timezone ?: 'America/New_York');
$period_type = $series ? $series['default_period_type'] : 'Quarterly';
$visibility = $series ? $series['visibility'] : 'Attendees Only';

ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fas fa-fw fa-comments mr-2"></i>
        <?= $is_edit ? 'Edit Meeting Series' : 'New Metronome Meeting' ?>
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>
<form action="/agent/post.php" method="post" autocomplete="off" id="meetingSeriesForm">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if ($is_edit) { ?>
        <input type="hidden" name="series_id" value="<?= $series_id ?>">
    <?php } ?>

    <div class="modal-body">
        <div class="row">
            <div class="col-md-8 form-group">
                <label>Meeting Name <strong class="text-danger">*</strong></label>
                <input type="text" class="form-control" name="series_name" placeholder="e.g. Sales - Daily Huddle or Weekly Leadership L10" value="<?= escapeHtml($name) ?>" maxlength="255" required>
            </div>
            <div class="col-md-4 form-group">
                <label>Facilitator <strong class="text-danger">*</strong></label>
                <select class="form-control select2" name="facilitator_user_id" required>
                    <?php foreach ($users as $u) { ?>
                        <option value="<?= intval($u['user_id']) ?>" <?= $fac_id === intval($u['user_id']) ? 'selected' : '' ?>>
                            <?= escapeHtml($u['user_name']) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <input type="text" class="form-control" name="series_description" placeholder="Optional purpose or team scope..." value="<?= escapeHtml($desc) ?>">
        </div>

        <div class="row">
            <div class="col-md-4 form-group">
                <label>Recurrence <strong class="text-danger">*</strong></label>
                <select class="form-control" name="recurrence_type" id="meetingRecurrenceSelect">
                    <?php foreach (['Daily', 'Weekdays', 'Weekly', 'Bi-Weekly', 'Monthly', 'Quarterly', 'One-Time'] as $rt) { ?>
                        <option value="<?= $rt ?>" <?= $rec_type === $rt ? 'selected' : '' ?>><?= $rt === 'One-Time' ? 'One-Time Meeting' : 'Recurring ' . $rt ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-4 form-group">
                <label>Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?= escapeHtml($start_date) ?>" required>
            </div>
            <div class="col-md-4 form-group">
                <label>Default Period</label>
                <select class="form-control" name="default_period_type">
                    <option value="Quarterly" <?= $period_type === 'Quarterly' ? 'selected' : '' ?>>Quarterly (FY-Q#)</option>
                    <option value="Monthly" <?= $period_type === 'Monthly' ? 'selected' : '' ?>>Monthly</option>
                    <option value="Annual" <?= $period_type === 'Annual' ? 'selected' : '' ?>>Annual (FY)</option>
                </select>
            </div>
        </div>

        <div class="form-group" id="meetingWeeklyDaysRow" style="<?= $rec_type === 'Weekly' ? '' : 'display:none;' ?>">
            <label class="d-block">Days of Week</label>
            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dw) { ?>
                <div class="custom-control custom-checkbox custom-control-inline">
                    <input type="checkbox" class="custom-control-input" id="dw_<?= $dw ?>" name="recurrence_days[]" value="<?= $dw ?>" <?= in_array($dw, $rec_days, true) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="dw_<?= $dw ?>"><?= $dw ?></label>
                </div>
            <?php } ?>
        </div>

        <div class="row">
            <div class="col-md-4 form-group">
                <label>Start Time</label>
                <input type="time" class="form-control" name="start_time" value="<?= escapeHtml($start_time) ?>" required>
            </div>
            <div class="col-md-4 form-group">
                <label>End Time</label>
                <input type="time" class="form-control" name="end_time" value="<?= escapeHtml($end_time) ?>" required>
            </div>
            <div class="col-md-4 form-group">
                <label>Timezone</label>
                <input type="text" class="form-control" name="timezone" value="<?= escapeHtml($tz) ?>">
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 form-group">
                <label>Visibility Scope</label>
                <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons">
                    <label class="btn btn-outline-secondary w-50 <?= $visibility === 'Attendees Only' ? 'active' : '' ?>">
                        <input type="radio" name="visibility" value="Attendees Only" <?= $visibility === 'Attendees Only' ? 'checked' : '' ?>>
                        <i class="fas fa-lock mr-1"></i>Attendees Only
                    </label>
                    <label class="btn btn-outline-info w-50 <?= $visibility === 'Company-Wide' ? 'active' : '' ?>">
                        <input type="radio" name="visibility" value="Company-Wide" <?= $visibility === 'Company-Wide' ? 'checked' : '' ?>>
                        <i class="fas fa-globe mr-1"></i>Company-Wide
                    </label>
                </div>
            </div>
            <?php if (!$is_edit) { ?>
                <div class="col-md-6 form-group">
                    <label>Starter Agenda Preset</label>
                    <select class="form-control" name="agenda_preset">
                        <option value="daily_huddle">Daily Huddle (Good News, Metrics, #1 Thing, Stucks, Actions)</option>
                        <option value="weekly_leadership">Weekly Leadership (Good News, Metrics, Priorities, Stucks, Actions)</option>
                        <option value="monthly_review">Monthly Review (Wins, Metrics, Learnings, Discussion, Actions)</option>
                        <option value="blank">Minimal (Good News, Action Items)</option>
                    </select>
                </div>
            <?php } ?>
        </div>

        <div class="form-group mb-0">
            <label>Meeting Attendees <small class="text-muted">(logged-in ITFlow users)</small></label>
            <select class="form-control select2" name="attendees[]" multiple data-placeholder="Select team members" required>
                <?php foreach ($users as $u) { ?>
                    <option value="<?= intval($u['user_id']) ?>" <?= in_array(intval($u['user_id']), $selected_attendees, true) ? 'selected' : '' ?>>
                        <?= escapeHtml($u['user_name']) ?> (<?= escapeHtml($u['user_email']) ?>)
                    </option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="modal-footer bg-white">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" name="<?= $is_edit ? 'edit_meeting_series' : 'create_meeting_series' ?>" class="btn btn-primary">
            <i class="fas fa-check mr-1"></i><?= $is_edit ? 'Save Series' : 'Create & Open Meeting' ?>
        </button>
    </div>
</form>

<script>
$(function() {
    $('#meetingSeriesForm .select2').select2({
        theme: 'bootstrap4',
        dropdownParent: $('#meetingSeriesForm').closest('.modal')
    });
    $('#meetingRecurrenceSelect').on('change', function() {
        $('#meetingWeeklyDaysRow').toggle($(this).val() === 'Weekly');
    });
});
</script>

<?php require_once '../../../includes/modal_footer.php';
