<?php

require_once '../../../includes/modal_header.php';

$tbr_id = intval($_GET['tbr_id'] ?? 0);
$is_edit = $tbr_id > 0;
$meeting = null;

if ($is_edit) {
    tbrEnforcePermission('edit');
    $meeting = tbrGetMeeting($tbr_id);
    if (!$meeting) {
        echo json_encode(['error' => 'TBR meeting not found.']);
        exit();
    }
    enforceClientAccess(intval($meeting['tbr_client_id']));
    $client_id = intval($meeting['tbr_client_id']);
} else {
    tbrEnforcePermission('schedule');
    $client_id = intval($_GET['client_id'] ?? 0);
    if ($client_id > 0) {
        enforceClientAccess($client_id);
    }
}

$settings = tbrGetSettings();
$default_duration = intval($settings['default_duration_minutes'] ?? 60);

$quarter_num = intval(ceil(date('n') / 3));
$default_title = "Q{$quarter_num} " . date('Y') . " Technology Business Review";
$default_date = date('Y-m-d', strtotime('+7 days'));
$default_time = '10:00';
$default_period_end = date('Y-m-d');
$default_period_start = date('Y-m-d', strtotime('-90 days'));

$title = $meeting ? $meeting['tbr_title'] : $default_title;
$type = $meeting ? $meeting['tbr_type'] : 'Quarterly';
$meeting_date = $meeting ? $meeting['tbr_meeting_date'] : $default_date;
$start_time = $meeting ? substr((string)$meeting['tbr_start_time'], 0, 5) : $default_time;
$duration = $meeting ? intval($meeting['tbr_duration_minutes']) : $default_duration;
$location = $meeting ? (string)$meeting['tbr_location'] : '';
$assigned_user_id = $meeting ? intval($meeting['tbr_assigned_user_id']) : intval($session_user_id);
$period_start = $meeting && !empty($meeting['tbr_reporting_period_start']) ? $meeting['tbr_reporting_period_start'] : $default_period_start;
$period_end = $meeting && !empty($meeting['tbr_reporting_period_end']) ? $meeting['tbr_reporting_period_end'] : $default_period_end;
$additional_emails = $meeting ? (string)$meeting['tbr_additional_emails'] : '';
$prep_notes = $meeting ? (string)$meeting['tbr_prep_notes'] : '';
$recurring_interval = $meeting ? (string)$meeting['tbr_recurring_interval'] : 'None';
$auto_agenda = $meeting ? intval($meeting['tbr_auto_generate_agenda']) : 1;

$selected_internal_ids = [];
$selected_contact_ids = [];
$selected_notify_contact_ids = [];
if ($meeting) {
    $res_att = mysqli_query($mysqli, "SELECT * FROM tbr_attendees WHERE attendee_tbr_id = $tbr_id");
    while ($res_att && ($a = mysqli_fetch_assoc($res_att))) {
        if ($a['attendee_type'] === 'internal' && !empty($a['attendee_user_id'])) {
            $selected_internal_ids[] = intval($a['attendee_user_id']);
        } elseif ($a['attendee_type'] === 'client' && !empty($a['attendee_contact_id'])) {
            $selected_contact_ids[] = intval($a['attendee_contact_id']);
            if (!empty($a['attendee_receive_notifications'])) {
                $selected_notify_contact_ids[] = intval($a['attendee_contact_id']);
            }
        }
    }
}

$prev_tbr = $client_id > 0 ? tbrFindPreviousMeeting($client_id, $tbr_id ?: null) : null;

$clients = [];
$res_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_lead = 0 AND client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
while ($res_clients && ($c = mysqli_fetch_assoc($res_clients))) {
    $clients[] = $c;
}

$users = [];
$res_users = mysqli_query($mysqli, "SELECT user_id, user_name, user_email FROM users WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
while ($res_users && ($u = mysqli_fetch_assoc($res_users))) {
    $users[] = $u;
}

$contacts = [];
if ($client_id > 0) {
    $res_contacts = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_email, contact_primary FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_name ASC");
    while ($res_contacts && ($ct = mysqli_fetch_assoc($res_contacts))) {
        $contacts[] = $ct;
    }
}

ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fas fa-fw fa-chart-line mr-2"></i>
        <?= $is_edit ? 'Edit / Reschedule TBR' : 'Schedule New Technology Business Review' ?>
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="/agent/post.php" method="post" autocomplete="off" id="tbrScheduleForm">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if ($is_edit) { ?>
        <input type="hidden" name="tbr_id" value="<?= $tbr_id ?>">
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <?php } ?>

    <div class="modal-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Client <strong class="text-danger">*</strong></label>
                    <select class="form-control select2" name="client_id" id="tbrModalClientSelect" required <?= $is_edit ? 'disabled' : '' ?>>
                        <option value="">- Select Client -</option>
                        <?php foreach ($clients as $c) { ?>
                            <option value="<?= intval($c['client_id']) ?>" <?= ($client_id === intval($c['client_id'])) ? 'selected' : '' ?>>
                                <?= escapeHtml($c['client_name']) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Meeting Type <strong class="text-danger">*</strong></label>
                    <select class="form-control" name="tbr_type" id="tbrModalTypeSelect" required>
                        <option value="Quarterly" <?= $type === 'Quarterly' ? 'selected' : '' ?>>Quarterly Business Review</option>
                        <option value="Annual" <?= $type === 'Annual' ? 'selected' : '' ?>>Annual Strategic Review</option>
                        <option value="Special" <?= $type === 'Special' ? 'selected' : '' ?>>Special Review</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="tbrPrevBanner" class="alert alert-info py-2 mb-3 <?= $prev_tbr ? '' : 'd-none' ?>">
            <i class="fas fa-history mr-1"></i>
            <strong>Previous TBR Detected:</strong>
            <span id="tbrPrevBannerText">
                <?php if ($prev_tbr) { ?>
                    <?= escapeHtml($prev_tbr['tbr_title']) ?> (<?= date('M j, Y', strtotime($prev_tbr['tbr_meeting_date'])) ?> — <?= escapeHtml($prev_tbr['tbr_status']) ?>)
                <?php } ?>
            </span>
        </div>

        <div class="form-group">
            <label>Meeting Title <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="tbr_title" id="tbrModalTitleInput" value="<?= escapeHtml($title) ?>" maxlength="255" required>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Meeting Date <strong class="text-danger">*</strong></label>
                    <input type="date" class="form-control" name="tbr_meeting_date" value="<?= escapeHtml($meeting_date) ?>" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Start Time <strong class="text-danger">*</strong></label>
                    <input type="time" class="form-control" name="tbr_start_time" value="<?= escapeHtml($start_time) ?>" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Expected Duration (min)</label>
                    <select class="form-control" name="tbr_duration_minutes">
                        <?php foreach ([30, 45, 60, 90, 120] as $mins) { ?>
                            <option value="<?= $mins ?>" <?= $duration === $mins ? 'selected' : '' ?>><?= $mins ?> minutes</option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Location or Video Conference URL</label>
                    <input type="text" class="form-control" name="tbr_location" placeholder="https://teams.microsoft.com/... or Client Office" value="<?= escapeHtml($location) ?>" maxlength="500">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Assigned MSP Representative <strong class="text-danger">*</strong></label>
                    <select class="form-control select2" name="tbr_assigned_user_id" required>
                        <?php foreach ($users as $u) { ?>
                            <option value="<?= intval($u['user_id']) ?>" <?= ($assigned_user_id === intval($u['user_id'])) ? 'selected' : '' ?>>
                                <?= escapeHtml($u['user_name']) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Reporting Period Start</label>
                    <input type="date" class="form-control" name="tbr_reporting_period_start" value="<?= escapeHtml($period_start) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Reporting Period End</label>
                    <input type="date" class="form-control" name="tbr_reporting_period_end" value="<?= escapeHtml($period_end) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Recurring Schedule</label>
                    <select class="form-control" name="tbr_recurring_interval">
                        <option value="None" <?= $recurring_interval === 'None' ? 'selected' : '' ?>>One-Time / No Recurrence</option>
                        <option value="Quarterly" <?= $recurring_interval === 'Quarterly' ? 'selected' : '' ?>>Recurring Quarterly</option>
                        <option value="Annual" <?= $recurring_interval === 'Annual' ? 'selected' : '' ?>>Recurring Annual Strategic</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Additional Internal MSP Attendees</label>
                    <select class="form-control select2" name="internal_attendees[]" multiple data-placeholder="Select internal attendees">
                        <?php foreach ($users as $u) { ?>
                            <option value="<?= intval($u['user_id']) ?>" <?= in_array(intval($u['user_id']), $selected_internal_ids, true) ? 'selected' : '' ?>>
                                <?= escapeHtml($u['user_name']) ?> (<?= escapeHtml($u['user_email']) ?>)
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Additional Email Recipients <small class="text-muted">(comma-separated)</small></label>
                    <input type="text" class="form-control" name="tbr_additional_emails" placeholder="ceo@client.com, cfo@client.com" value="<?= escapeHtml($additional_emails) ?>">
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Client Attendees &amp; Reminder Recipients</label>
            <div id="tbrClientContactsBox" class="border rounded p-2 bg-light" style="max-height: 170px; overflow-y: auto;">
                <?php if (empty($contacts)) { ?>
                    <small class="text-muted">Select a client to load contacts.</small>
                <?php } else { ?>
                    <?php foreach ($contacts as $ct) {
                        $cid = intval($ct['contact_id']);
                        $checked = $is_edit ? in_array($cid, $selected_contact_ids, true) : (!empty($ct['contact_primary']));
                        $notify_checked = $is_edit ? in_array($cid, $selected_notify_contact_ids, true) : true;
                    ?>
                        <div class="d-flex align-items-center justify-content-between py-1 border-bottom">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="tbr_ct_<?= $cid ?>" name="client_attendees[]" value="<?= $cid ?>" <?= $checked ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="tbr_ct_<?= $cid ?>">
                                    <strong><?= escapeHtml($ct['contact_name']) ?></strong>
                                    <?= !empty($ct['contact_title']) ? '<small class="text-muted">(' . escapeHtml($ct['contact_title']) . ')</small>' : '' ?>
                                    <?= !empty($ct['contact_email']) ? '<small class="text-secondary"> &lt;' . escapeHtml($ct['contact_email']) . '&gt;</small>' : '<small class="text-danger"> (No Email)</small>' ?>
                                </label>
                            </div>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="tbr_ct_notify_<?= $cid ?>" name="client_notify[]" value="<?= $cid ?>" <?= $notify_checked ? 'checked' : '' ?>>
                                <label class="custom-control-label small" for="tbr_ct_notify_<?= $cid ?>">Invites/Reminders</label>
                            </div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>

        <?php if (tbrHasPermission('view_internal_notes')) { ?>
            <div class="form-group">
                <label>Internal Preparation Notes <small class="text-danger">(MSP Only - never shown to client)</small></label>
                <textarea class="form-control" name="tbr_prep_notes" rows="3" placeholder="Internal prep checklist, renewal topics, account health notes..."><?= escapeHtml($prep_notes) ?></textarea>
            </div>
        <?php } ?>

        <div class="row">
            <?php if (!$is_edit) { ?>
                <div class="col-md-4">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="tbrAutoAgenda" name="tbr_auto_generate_agenda" value="1" <?= $auto_agenda ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="tbrAutoAgenda">Automatically generate 10-section agenda</label>
                    </div>
                </div>
                <div class="col-md-4" id="tbrCarryForwardWrap" style="<?= $prev_tbr ? '' : 'display:none;' ?>">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="tbrCarryForward" name="carry_forward_actions" value="1" checked>
                        <label class="custom-control-label" for="tbrCarryForward">Carry forward open action items</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="tbrSendInvite" name="send_confirmation_email" value="1" checked>
                        <label class="custom-control-label" for="tbrSendInvite">Send confirmation &amp; ICS invite</label>
                    </div>
                </div>
            <?php } else { ?>
                <div class="col-md-12">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="tbrSendUpdateInvite" name="send_reschedule_email" value="1" checked>
                        <label class="custom-control-label" for="tbrSendUpdateInvite">Send updated invitation / ICS notice if date, time, or location changed</label>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <div class="modal-footer bg-white">
        <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancel</button>
        <button type="submit" name="<?= $is_edit ? 'edit_tbr' : 'schedule_tbr' ?>" class="btn btn-primary">
            <i class="fas fa-check mr-1"></i><?= $is_edit ? 'Save Changes' : 'Schedule TBR' ?>
        </button>
    </div>
</form>

<script>
$(function() {
    $('#tbrScheduleForm .select2').select2({
        theme: 'bootstrap4',
        dropdownParent: $('#tbrScheduleForm').closest('.modal')
    });

    $('#tbrModalTypeSelect').on('change', function() {
        var t = $(this).val();
        var titleEl = $('#tbrModalTitleInput');
        if (titleEl.val().indexOf('Technology Business Review') !== -1 || titleEl.val().indexOf('Annual Strategic Review') !== -1 || titleEl.val().indexOf('Special Review') !== -1) {
            var y = new Date().getFullYear();
            var q = Math.ceil((new Date().getMonth() + 1) / 3);
            if (t === 'Annual') {
                titleEl.val(y + ' Annual Strategic Review');
            } else if (t === 'Special') {
                titleEl.val(y + ' Special Technology Review');
            } else {
                titleEl.val('Q' + q + ' ' + y + ' Technology Business Review');
            }
        }
    });

    $('#tbrModalClientSelect').on('change', function() {
        var cid = $(this).val();
        var box = $('#tbrClientContactsBox');
        if (!cid) {
            box.html('<small class="text-muted">Select a client to load contacts.</small>');
            $('#tbrPrevBanner').addClass('d-none');
            $('#tbrCarryForwardWrap').hide();
            return;
        }
        box.html('<small class="text-muted"><i class="fas fa-spinner fa-spin mr-1"></i>Loading client contacts...</small>');
        $.getJSON('/agent/ajax_tbr.php', { action: 'client_schedule_context', client_id: cid }, function(res) {
            if (!res || !res.ok) {
                box.html('<small class="text-danger">Unable to load contacts.</small>');
                return;
            }
            if (res.previous_tbr) {
                $('#tbrPrevBannerText').text(res.previous_tbr.tbr_title + ' (' + res.previous_tbr.tbr_meeting_date + ' — ' + res.previous_tbr.tbr_status + ')');
                $('#tbrPrevBanner').removeClass('d-none');
                $('#tbrCarryForwardWrap').show();
            } else {
                $('#tbrPrevBanner').addClass('d-none');
                $('#tbrCarryForwardWrap').hide();
            }
            if (!res.contacts || res.contacts.length === 0) {
                box.html('<small class="text-muted">No contacts found for this client. You can still use Additional Email Recipients.</small>');
                return;
            }
            var html = '';
            res.contacts.forEach(function(ct) {
                var checked = ct.contact_primary == 1 ? 'checked' : '';
                var emailBadge = ct.contact_email ? '<small class="text-secondary"> &lt;' + $('<div>').text(ct.contact_email).html() + '&gt;</small>' : '<small class="text-danger"> (No Email)</small>';
                var titleBadge = ct.contact_title ? '<small class="text-muted"> (' + $('<div>').text(ct.contact_title).html() + ')</small>' : '';
                html += '<div class="d-flex align-items-center justify-content-between py-1 border-bottom">'
                    + '<div class="custom-control custom-checkbox">'
                    + '<input type="checkbox" class="custom-control-input" id="tbr_ct_' + ct.contact_id + '" name="client_attendees[]" value="' + ct.contact_id + '" ' + checked + '>'
                    + '<label class="custom-control-label" for="tbr_ct_' + ct.contact_id + '"><strong>' + $('<div>').text(ct.contact_name).html() + '</strong>' + titleBadge + emailBadge + '</label>'
                    + '</div>'
                    + '<div class="custom-control custom-switch">'
                    + '<input type="checkbox" class="custom-control-input" id="tbr_ct_notify_' + ct.contact_id + '" name="client_notify[]" value="' + ct.contact_id + '" checked>'
                    + '<label class="custom-control-label small" for="tbr_ct_notify_' + ct.contact_id + '">Invites/Reminders</label>'
                    + '</div>'
                    + '</div>';
            });
            box.html(html);
        });
    });
});
</script>

<?php require_once '../../../includes/modal_footer.php';
