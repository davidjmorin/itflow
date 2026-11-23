<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

function tbrSaveAttendeesFromRequest(int $tbr_id, int $client_id, int $assigned_user_id): void {
    global $mysqli;

    mysqli_query($mysqli, "DELETE FROM tbr_attendees WHERE attendee_tbr_id = $tbr_id");

    $internal_ids = array_map('intval', $_POST['internal_attendees'] ?? []);
    if ($assigned_user_id > 0 && !in_array($assigned_user_id, $internal_ids, true)) {
        array_unshift($internal_ids, $assigned_user_id);
    }
    $internal_ids = array_unique(array_filter($internal_ids));

    foreach ($internal_ids as $uid) {
        $u = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name, user_email FROM users WHERE user_id = $uid AND user_archived_at IS NULL LIMIT 1"));
        if ($u) {
            $un = escapeSql($u['user_name']);
            $ue = escapeSql($u['user_email']);
            mysqli_query($mysqli, "INSERT INTO tbr_attendees SET attendee_tbr_id = $tbr_id, attendee_type = 'internal', attendee_user_id = $uid, attendee_name = '$un', attendee_email = '$ue', attendee_receive_notifications = 1");
        }
    }

    $client_ids = array_unique(array_filter(array_map('intval', $_POST['client_attendees'] ?? [])));
    $notify_ids = array_map('intval', $_POST['client_notify'] ?? []);

    foreach ($client_ids as $ctid) {
        $ct = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_email FROM contacts WHERE contact_id = $ctid AND contact_client_id = $client_id AND contact_archived_at IS NULL LIMIT 1"));
        if ($ct) {
            $cn = escapeSql($ct['contact_name']);
            $ce = escapeSql($ct['contact_email'] ?? '');
            $notify = in_array($ctid, $notify_ids, true) ? 1 : 0;
            mysqli_query($mysqli, "INSERT INTO tbr_attendees SET attendee_tbr_id = $tbr_id, attendee_type = 'client', attendee_contact_id = $ctid, attendee_name = '$cn', attendee_email = '$ce', attendee_receive_notifications = $notify");
        }
    }

    $extra_raw = trim((string)($_POST['tbr_additional_emails'] ?? ''));
    if ($extra_raw !== '') {
        foreach (preg_split('/[\s,;]+/', $extra_raw) as $em) {
            $em = trim($em);
            if (filter_var($em, FILTER_VALIDATE_EMAIL)) {
                $em_esc = escapeSql($em);
                mysqli_query($mysqli, "INSERT INTO tbr_attendees SET attendee_tbr_id = $tbr_id, attendee_type = 'external', attendee_name = '$em_esc', attendee_email = '$em_esc', attendee_receive_notifications = 1");
            }
        }
    }
}

if (isset($_POST['schedule_tbr'])) {
    validateCSRFToken();
    tbrEnforcePermission('schedule');

    $client_id = intval($_POST['client_id'] ?? 0);
    enforceClientAccess($client_id);

    $title = escapeSql($_POST['tbr_title'] ?? 'Technology Business Review');
    $type = in_array($_POST['tbr_type'] ?? '', ['Quarterly', 'Annual', 'Special'], true) ? $_POST['tbr_type'] : 'Quarterly';
    $meeting_date = escapeSql($_POST['tbr_meeting_date'] ?? date('Y-m-d'));
    $start_time = escapeSql(($_POST['tbr_start_time'] ?? '10:00') . ':00');
    $duration = max(15, intval($_POST['tbr_duration_minutes'] ?? 60));
    $location = escapeSql($_POST['tbr_location'] ?? '');
    $assigned_user_id = intval($_POST['tbr_assigned_user_id'] ?? $session_user_id);
    $period_start = !empty($_POST['tbr_reporting_period_start']) ? "'" . escapeSql($_POST['tbr_reporting_period_start']) . "'" : 'NULL';
    $period_end = !empty($_POST['tbr_reporting_period_end']) ? "'" . escapeSql($_POST['tbr_reporting_period_end']) . "'" : 'NULL';
    $additional_emails = escapeSql($_POST['tbr_additional_emails'] ?? '');
    $prep_notes = tbrHasPermission('view_internal_notes') ? mysqli_real_escape_string($mysqli, $_POST['tbr_prep_notes'] ?? '') : '';
    $recurring_interval = in_array($_POST['tbr_recurring_interval'] ?? '', ['None', 'Quarterly', 'Annual'], true) ? $_POST['tbr_recurring_interval'] : 'None';
    $recurring_enabled = $recurring_interval !== 'None' ? 1 : 0;
    $auto_agenda = !empty($_POST['tbr_auto_generate_agenda']) ? 1 : 0;

    $prev = tbrFindPreviousMeeting($client_id, null, $_POST['tbr_meeting_date'] ?? null);
    $prev_id_sql = $prev ? intval($prev['tbr_id']) : 'NULL';

    mysqli_query($mysqli, "
        INSERT INTO tbr_meetings SET
            tbr_client_id = $client_id,
            tbr_previous_tbr_id = $prev_id_sql,
            tbr_title = '$title',
            tbr_type = '$type',
            tbr_status = 'Scheduled',
            tbr_meeting_date = '$meeting_date',
            tbr_start_time = '$start_time',
            tbr_duration_minutes = $duration,
            tbr_location = '$location',
            tbr_assigned_user_id = $assigned_user_id,
            tbr_reporting_period_start = $period_start,
            tbr_reporting_period_end = $period_end,
            tbr_additional_emails = '$additional_emails',
            tbr_prep_notes = '$prep_notes',
            tbr_recurring_enabled = $recurring_enabled,
            tbr_recurring_interval = '$recurring_interval',
            tbr_auto_generate_agenda = $auto_agenda,
            tbr_created_by = $session_user_id,
            tbr_last_edited_by = $session_user_id
    ");
    $tbr_id = mysqli_insert_id($mysqli);

    if ($tbr_id <= 0) {
        flashAlert('Failed to schedule TBR.', 'error');
        redirect('tbrs.php');
    }

    tbrSaveAttendeesFromRequest($tbr_id, $client_id, $assigned_user_id);

    if ($auto_agenda) {
        tbrInitializeAgenda($tbr_id, $session_user_id);
    }

    if (!empty($_POST['carry_forward_actions']) && $prev) {
        tbrCarryForwardFromPrevious($tbr_id, intval($prev['tbr_id']), $session_user_id, true, false);
    }

    tbrSyncCalendarEvent($tbr_id);
    $send_invite = !empty($_POST['send_confirmation_email']);
    tbrSyncNotificationSchedules($tbr_id, $send_invite, 'scheduled_confirmation');

    tbrLogAudit($tbr_id, $client_id, 'Scheduled', "Scheduled $type TBR: $title on $meeting_date", $session_user_id);

    flashAlert('Technology Business Review scheduled successfully.', 'success');
    redirect("tbr.php?tbr_id=$tbr_id");
}

if (isset($_POST['edit_tbr'])) {
    validateCSRFToken();
    tbrEnforcePermission('edit');

    $tbr_id = intval($_POST['tbr_id'] ?? 0);
    $existing = tbrGetMeeting($tbr_id);
    if (!$existing) {
        flashAlert('TBR not found.', 'error');
        redirect('tbrs.php');
    }

    $client_id = intval($existing['tbr_client_id']);
    enforceClientAccess($client_id);

    $old_sig = tbrComputeMeetingSignature($existing);

    $title = escapeSql($_POST['tbr_title'] ?? $existing['tbr_title']);
    $type = in_array($_POST['tbr_type'] ?? '', ['Quarterly', 'Annual', 'Special'], true) ? $_POST['tbr_type'] : $existing['tbr_type'];
    $meeting_date = escapeSql($_POST['tbr_meeting_date'] ?? $existing['tbr_meeting_date']);
    $raw_time = $_POST['tbr_start_time'] ?? substr($existing['tbr_start_time'], 0, 5);
    $start_time = escapeSql(strlen($raw_time) === 5 ? $raw_time . ':00' : $raw_time);
    $duration = max(15, intval($_POST['tbr_duration_minutes'] ?? 60));
    $location = escapeSql($_POST['tbr_location'] ?? '');
    $assigned_user_id = intval($_POST['tbr_assigned_user_id'] ?? $existing['tbr_assigned_user_id']);
    $period_start = !empty($_POST['tbr_reporting_period_start']) ? "'" . escapeSql($_POST['tbr_reporting_period_start']) . "'" : 'NULL';
    $period_end = !empty($_POST['tbr_reporting_period_end']) ? "'" . escapeSql($_POST['tbr_reporting_period_end']) . "'" : 'NULL';
    $additional_emails = escapeSql($_POST['tbr_additional_emails'] ?? '');
    $recurring_interval = in_array($_POST['tbr_recurring_interval'] ?? '', ['None', 'Quarterly', 'Annual'], true) ? $_POST['tbr_recurring_interval'] : 'None';
    $recurring_enabled = $recurring_interval !== 'None' ? 1 : 0;

    $prep_sql = "";
    if (tbrHasPermission('view_internal_notes') && isset($_POST['tbr_prep_notes'])) {
        $prep_notes = mysqli_real_escape_string($mysqli, $_POST['tbr_prep_notes']);
        $prep_sql = ", tbr_prep_notes = '$prep_notes'";
    }

    $candidate = [
        'tbr_meeting_date' => $_POST['tbr_meeting_date'] ?? $existing['tbr_meeting_date'],
        'tbr_start_time' => strlen($raw_time) === 5 ? $raw_time . ':00' : $raw_time,
        'tbr_duration_minutes' => $duration,
        'tbr_location' => $_POST['tbr_location'] ?? '',
    ];
    $new_sig = tbrComputeMeetingSignature($candidate);
    $schedule_changed = ($old_sig !== $new_sig);
    $seq_sql = $schedule_changed ? ", tbr_ical_sequence = tbr_ical_sequence + 1" : "";

    mysqli_query($mysqli, "
        UPDATE tbr_meetings SET
            tbr_title = '$title',
            tbr_type = '$type',
            tbr_meeting_date = '$meeting_date',
            tbr_start_time = '$start_time',
            tbr_duration_minutes = $duration,
            tbr_location = '$location',
            tbr_assigned_user_id = $assigned_user_id,
            tbr_reporting_period_start = $period_start,
            tbr_reporting_period_end = $period_end,
            tbr_additional_emails = '$additional_emails',
            tbr_recurring_enabled = $recurring_enabled,
            tbr_recurring_interval = '$recurring_interval',
            tbr_last_edited_by = $session_user_id
            $prep_sql
            $seq_sql
        WHERE tbr_id = $tbr_id
    ");

    tbrSaveAttendeesFromRequest($tbr_id, $client_id, $assigned_user_id);
    tbrSyncCalendarEvent($tbr_id);

    if ($schedule_changed) {
        $send_update = !empty($_POST['send_reschedule_email']);
        tbrSyncNotificationSchedules($tbr_id, $send_update, 'rescheduled');
        tbrLogAudit($tbr_id, $client_id, 'Rescheduled', "Rescheduled TBR to $meeting_date $raw_time", $session_user_id);
    } else {
        tbrLogAudit($tbr_id, $client_id, 'Updated', "Updated TBR meeting details", $session_user_id);
    }

    flashAlert('TBR schedule updated.', 'success');
    redirect("tbr.php?tbr_id=$tbr_id");
}

if (isset($_GET['start_tbr'])) {
    validateCSRFToken();
    tbrEnforcePermission('edit');

    $tbr_id = intval($_GET['start_tbr']);
    $meeting = tbrGetMeeting($tbr_id);
    if (!$meeting) {
        flashAlert('TBR not found.', 'error');
        redirect('tbrs.php');
    }

    $client_id = intval($meeting['tbr_client_id']);
    enforceClientAccess($client_id);

    tbrInitializeAgenda($tbr_id, $session_user_id);

    if ($meeting['tbr_status'] === 'Scheduled') {
        mysqli_query($mysqli, "UPDATE tbr_meetings SET tbr_status = 'In Progress', tbr_last_edited_by = $session_user_id WHERE tbr_id = $tbr_id");
        tbrLogAudit($tbr_id, $client_id, 'Started', 'Started TBR meeting', $session_user_id);
    }

    redirect("tbr.php?tbr_id=$tbr_id");
}

if (isset($_GET['cancel_tbr'])) {
    validateCSRFToken();
    tbrEnforcePermission('edit');

    $tbr_id = intval($_GET['cancel_tbr']);
    $meeting = tbrGetMeeting($tbr_id);
    if (!$meeting) {
        flashAlert('TBR not found.', 'error');
        redirect('tbrs.php');
    }

    $client_id = intval($meeting['tbr_client_id']);
    enforceClientAccess($client_id);

    mysqli_query($mysqli, "UPDATE tbr_meetings SET tbr_status = 'Cancelled', tbr_ical_sequence = tbr_ical_sequence + 1, tbr_last_edited_by = $session_user_id WHERE tbr_id = $tbr_id");
    tbrSyncCalendarEvent($tbr_id);
    tbrSyncNotificationSchedules($tbr_id, true, 'cancelled');
    tbrLogAudit($tbr_id, $client_id, 'Cancelled', 'Cancelled TBR meeting and pending reminders', $session_user_id);

    flashAlert('TBR meeting cancelled.', 'warning');
    redirect($_SERVER['HTTP_REFERER'] ?? 'tbrs.php');
}

if (isset($_GET['archive_tbr'])) {
    validateCSRFToken();
    if (!tbrHasPermission('manage_settings') && !$session_is_admin && lookupUserPermission('module_tbr') < 3) {
        tbrEnforcePermission('manage_settings');
    }

    $tbr_id = intval($_GET['archive_tbr']);
    $meeting = tbrGetMeeting($tbr_id);
    if ($meeting) {
        $client_id = intval($meeting['tbr_client_id']);
        enforceClientAccess($client_id);
        mysqli_query($mysqli, "UPDATE tbr_meetings SET tbr_archived_at = NOW() WHERE tbr_id = $tbr_id");
        mysqli_query($mysqli, "UPDATE tbr_notification_schedules SET schedule_status = 'Cancelled' WHERE schedule_tbr_id = $tbr_id AND schedule_status = 'Pending'");
        if (!empty($meeting['tbr_calendar_event_id'])) {
            mysqli_query($mysqli, "DELETE FROM calendar_events WHERE event_id = " . intval($meeting['tbr_calendar_event_id']));
        }
        tbrLogAudit($tbr_id, $client_id, 'Archived', 'Archived TBR record', $session_user_id);
        flashAlert('TBR archived.', 'success');
    }

    redirect('tbrs.php');
}
