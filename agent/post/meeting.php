<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

function meetingSaveSeriesAttendees(int $series_id, int $facilitator_id, array $attendee_ids): void {
    global $mysqli;

    $ids = array_unique(array_filter(array_map('intval', $attendee_ids)));
    if ($facilitator_id > 0 && !in_array($facilitator_id, $ids, true)) {
        array_unshift($ids, $facilitator_id);
    }

    mysqli_query($mysqli, "DELETE FROM meeting_attendees WHERE series_id = $series_id");
    $order = 1;
    foreach ($ids as $uid) {
        $role = ($uid === $facilitator_id) ? 'Facilitator' : 'Member';
        mysqli_query($mysqli, "
            INSERT INTO meeting_attendees SET
                series_id = $series_id,
                user_id = $uid,
                role = '$role',
                sort_order = $order
        ");
        $order++;
    }
}

if (isset($_POST['create_meeting_series'])) {
    validateCSRFToken();
    if (empty($session_is_admin) && lookupUserPermission('module_meetings') < 2) {
        enforceUserPermission('module_meetings', 2);
    }

    $name = escapeSql($_POST['series_name'] ?? '');
    if ($name === '') {
        flashAlert('Meeting name is required.', 'error');
        redirect('meetings.php');
    }

    $desc = escapeSql($_POST['series_description'] ?? '');
    $fac_id = intval($_POST['facilitator_user_id'] ?? $session_user_id);
    $rec_type = in_array($_POST['recurrence_type'] ?? '', ['One-Time', 'Daily', 'Weekdays', 'Weekly', 'Bi-Weekly', 'Monthly', 'Quarterly'], true)
        ? $_POST['recurrence_type']
        : 'Daily';
    $rec_days = escapeSql(implode(',', array_map('strval', $_POST['recurrence_days'] ?? [])));
    $start_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['start_date'] ?? '') ? $_POST['start_date'] : date('Y-m-d');
    $raw_st = $_POST['start_time'] ?? '09:00';
    $raw_et = $_POST['end_time'] ?? '09:30';
    $start_time = escapeSql(strlen($raw_st) === 5 ? $raw_st . ':00' : $raw_st);
    $end_time = escapeSql(strlen($raw_et) === 5 ? $raw_et . ':00' : $raw_et);
    $tz = escapeSql($_POST['timezone'] ?? 'America/New_York');
    $period_type = in_array($_POST['default_period_type'] ?? '', ['Quarterly', 'Monthly', 'Annual'], true) ? $_POST['default_period_type'] : 'Quarterly';
    $visibility = ($_POST['visibility'] ?? '') === 'Company-Wide' ? 'Company-Wide' : 'Attendees Only';
    $preset = $_POST['agenda_preset'] ?? 'daily_huddle';

    mysqli_query($mysqli, "
        INSERT INTO meeting_series SET
            series_name = '$name',
            series_description = '$desc',
            facilitator_user_id = $fac_id,
            recurrence_type = '$rec_type',
            recurrence_days = '$rec_days',
            start_date = '$start_date',
            start_time = '$start_time',
            end_time = '$end_time',
            timezone = '$tz',
            default_period_type = '$period_type',
            visibility = '$visibility',
            created_by = $session_user_id
    ");
    $series_id = mysqli_insert_id($mysqli);
    if ($series_id <= 0) {
        flashAlert('Failed to create meeting series.', 'error');
        redirect('meetings.php');
    }

    meetingSaveSeriesAttendees($series_id, $fac_id, $_POST['attendees'] ?? [$session_user_id]);
    meetingSeedDefaultAgenda($series_id, $preset);

    $series = meetingGetSeries($series_id);
    $default_date = meetingGetDefaultOccurrenceDate($series);
    meetingGetOrCreateOccurrence($series_id, $default_date);

    logAudit('Meetings', 'Create', "Created meeting series $name (#$series_id)", 0, $series_id);
    redirect("meeting.php?series_id=$series_id&date=$default_date");
}

if (isset($_POST['edit_meeting_series'])) {
    validateCSRFToken();

    $series_id = intval($_POST['series_id'] ?? 0);
    $series = meetingGetSeries($series_id);
    if (!$series || !meetingIsFacilitatorOrAdmin($series, $session_user_id, !empty($session_is_admin))) {
        flashAlert(WORDING_ROLECHECK_FAILED, 'error');
        redirect('meetings.php');
    }

    $name = escapeSql($_POST['series_name'] ?? $series['series_name']);
    $desc = escapeSql($_POST['series_description'] ?? '');
    $fac_id = intval($_POST['facilitator_user_id'] ?? $series['facilitator_user_id']);
    $rec_type = in_array($_POST['recurrence_type'] ?? '', ['One-Time', 'Daily', 'Weekdays', 'Weekly', 'Bi-Weekly', 'Monthly', 'Quarterly'], true)
        ? $_POST['recurrence_type']
        : $series['recurrence_type'];
    $rec_days = escapeSql(implode(',', array_map('strval', $_POST['recurrence_days'] ?? [])));
    $start_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['start_date'] ?? '') ? $_POST['start_date'] : $series['start_date'];
    $raw_st = $_POST['start_time'] ?? substr($series['start_time'], 0, 5);
    $raw_et = $_POST['end_time'] ?? substr($series['end_time'], 0, 5);
    $start_time = escapeSql(strlen($raw_st) === 5 ? $raw_st . ':00' : $raw_st);
    $end_time = escapeSql(strlen($raw_et) === 5 ? $raw_et . ':00' : $raw_et);
    $tz = escapeSql($_POST['timezone'] ?? $series['timezone']);
    $period_type = in_array($_POST['default_period_type'] ?? '', ['Quarterly', 'Monthly', 'Annual'], true) ? $_POST['default_period_type'] : 'Quarterly';
    $visibility = ($_POST['visibility'] ?? '') === 'Company-Wide' ? 'Company-Wide' : 'Attendees Only';

    mysqli_query($mysqli, "
        UPDATE meeting_series SET
            series_name = '$name',
            series_description = '$desc',
            facilitator_user_id = $fac_id,
            recurrence_type = '$rec_type',
            recurrence_days = '$rec_days',
            start_date = '$start_date',
            start_time = '$start_time',
            end_time = '$end_time',
            timezone = '$tz',
            default_period_type = '$period_type',
            visibility = '$visibility'
        WHERE series_id = $series_id
    ");

    meetingSaveSeriesAttendees($series_id, $fac_id, $_POST['attendees'] ?? []);
    meetingBumpSeriesSyncSeq($series_id);

    logAudit('Meetings', 'Edit', "Updated meeting series $name (#$series_id)", 0, $series_id);
    flashAlert('Meeting series updated.', 'success');
    redirect("meeting.php?series_id=$series_id");
}

if (isset($_GET['archive_meeting_series'])) {
    validateCSRFToken();

    $series_id = intval($_GET['archive_meeting_series']);
    $series = meetingGetSeries($series_id);
    if ($series && meetingIsFacilitatorOrAdmin($series, $session_user_id, !empty($session_is_admin))) {
        mysqli_query($mysqli, "UPDATE meeting_series SET archived_at = NOW() WHERE series_id = $series_id");
        logAudit('Meetings', 'Archive', "Archived meeting series #{$series_id}", 0, $series_id);
        flashAlert('Meeting archived.', 'success');
    }

    redirect('meetings.php');
}
