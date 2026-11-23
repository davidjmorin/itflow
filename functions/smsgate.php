<?php

function smsgateFormatPhone($phone, $country_code = '') {
    $phone = trim((string)$phone);
    if ($phone === '') {
        return '';
    }

    $has_plus = str_starts_with($phone, '+');
    $digits = preg_replace('/[^\d]/', '', $phone);
    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '00')) {
        return '+' . substr($digits, 2);
    }

    $country_digits = preg_replace('/[^\d]/', '', (string)$country_code);
    if (!empty($country_digits) && $country_digits !== '1') {
        if (!str_starts_with($digits, $country_digits)) {
            return '+' . $country_digits . $digits;
        }
        return '+' . $digits;
    }

    if (strlen($digits) === 10) {
        return '+1' . $digits;
    }

    if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        return '+' . $digits;
    }

    if ($has_plus) {
        return '+' . $digits;
    }

    return '+1' . $digits;
}

function smsgateParseNumbers($input) {
    if (is_array($input)) {
        $raw = $input;
    } else {
        $raw = preg_split('/[\r\n,;]+/', (string)$input);
    }

    $numbers = [];
    foreach ($raw as $item) {
        $formatted = smsgateFormatPhone($item);
        if ($formatted !== '' && strlen($formatted) >= 8) {
            $numbers[] = $formatted;
        }
    }

    return array_values(array_unique($numbers));
}

function smsgateNormalizeUrl($url) {
    $url = trim((string)$url);
    if ($url === '') {
        return 'https://api.sms-gate.app/3rdparty/v1/messages';
    }

    if (!preg_match('#^https?://#i', $url)) {
        if (str_contains($url, ':8080')) {
            $url = 'http://' . $url;
        } else {
            $url = 'https://' . $url;
        }
    }

    $parsed = parse_url($url);
    $path = $parsed['path'] ?? '';

    if ($path === '' || $path === '/') {
        if (!empty($parsed['port']) && $parsed['port'] == 8080) {
            $url = rtrim($url, '/') . '/message';
        } else {
            $url = rtrim($url, '/') . '/3rdparty/v1/messages';
        }
    }

    return $url;
}

function smsgateGetConfig() {
    global $mysqli, $config_smsgate_enable, $config_smsgate_url, $config_smsgate_username, $config_smsgate_password, $config_smsgate_emergency_recipients, $config_smsgate_ack_message, $config_smsgate_urgent_message;

    if (!isset($config_smsgate_enable) || !isset($config_smsgate_username) || empty($config_smsgate_username)) {
        if (!empty($mysqli)) {
            $s_q = mysqli_query($mysqli, "SELECT config_smsgate_enable, config_smsgate_url, config_smsgate_username, config_smsgate_password, config_smsgate_emergency_recipients, config_smsgate_ack_message, config_smsgate_urgent_message FROM settings WHERE company_id = 1 LIMIT 1");
            if ($s_q && $s_row = mysqli_fetch_assoc($s_q)) {
                $config_smsgate_enable = intval($s_row['config_smsgate_enable']);
                $config_smsgate_url = $s_row['config_smsgate_url'];
                $config_smsgate_username = $s_row['config_smsgate_username'];
                $config_smsgate_password = $s_row['config_smsgate_password'];
                $config_smsgate_emergency_recipients = $s_row['config_smsgate_emergency_recipients'];
                $config_smsgate_ack_message = $s_row['config_smsgate_ack_message'];
                $config_smsgate_urgent_message = $s_row['config_smsgate_urgent_message'];
            }
        }
    }

    return [
        'enable' => intval($config_smsgate_enable ?? 0),
        'url' => $config_smsgate_url ?? 'https://api.sms-gate.app/3rdparty/v1/messages',
        'username' => $config_smsgate_username ?? '',
        'password' => $config_smsgate_password ?? '',
        'emergency_recipients' => $config_smsgate_emergency_recipients ?? '',
        'ack_message' => $config_smsgate_ack_message ?? '',
        'urgent_message' => $config_smsgate_urgent_message ?? '',
    ];
}

function smsgateSend($phone_numbers, $message, $force = false) {
    $config = smsgateGetConfig();

    if (!$force && empty($config['enable'])) {
        return ['success' => false, 'error' => 'SMSgate is disabled in settings.'];
    }

    $recipients = smsgateParseNumbers($phone_numbers);
    if (empty($recipients)) {
        return ['success' => false, 'error' => 'No valid phone numbers provided.'];
    }

    $message = trim((string)$message);
    if ($message === '') {
        return ['success' => false, 'error' => 'Message is empty.'];
    }

    $url = smsgateNormalizeUrl($config['url']);

    $payload = [
        'phoneNumbers' => $recipients,
        'textMessage' => [
            'text' => $message
        ]
    ];

    $json_payload = json_encode($payload);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    if (!empty($config['username']) || !empty($config['password'])) {
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $config['username'] . ':' . $config['password']);
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $success = ($http_code >= 200 && $http_code < 300);

    if ($success) {
        if (function_exists('logApp')) {
            logApp('SMSgate', 'info', "Sent SMS to " . implode(', ', $recipients) . ": " . substr($message, 0, 100));
        }
    } else {
        $err = !empty($curl_error) ? $curl_error : "HTTP $http_code: $response";
        if (function_exists('logApp')) {
            logApp('SMSgate', 'error', "Failed sending SMS to " . implode(', ', $recipients) . ". Error: $err");
        }
    }

    return [
        'success' => $success,
        'http_code' => $http_code,
        'response' => $response,
        'error' => $curl_error ?: ($success ? null : "HTTP $http_code: $response")
    ];
}

function smsgateTicketNotify($ticket_id, $provided_phone = null) {
    global $mysqli;

    static $processed = [];

    $ticket_id = intval($ticket_id);
    if ($ticket_id <= 0 || isset($processed[$ticket_id])) {
        return;
    }
    $processed[$ticket_id] = true;

    $config = smsgateGetConfig();

    if (empty($config['enable'])) {
        return;
    }

    $ticket_sql = mysqli_query($mysqli, "
        SELECT t.ticket_id, t.ticket_prefix, t.ticket_number, t.ticket_subject, t.ticket_priority, t.ticket_status, t.ticket_contact_id, t.ticket_client_id,
               c.contact_name, c.contact_phone, c.contact_mobile, c.contact_phone_country_code, c.contact_mobile_country_code,
               cl.client_name
        FROM tickets t
        LEFT JOIN contacts c ON t.ticket_contact_id = c.contact_id
        LEFT JOIN clients cl ON t.ticket_client_id = cl.client_id
        WHERE t.ticket_id = $ticket_id
        LIMIT 1
    ");

    if (!$ticket_sql || mysqli_num_rows($ticket_sql) === 0) {
        return;
    }

    $row = mysqli_fetch_assoc($ticket_sql);
    $ticket_ref = ($row['ticket_prefix'] ?? '') . $row['ticket_number'];
    $subject = $row['ticket_subject'] ?? '';
    $priority = $row['ticket_priority'] ?? 'Low';
    $client_name = $row['client_name'] ?? 'General';
    $contact_name = $row['contact_name'] ?? 'Client';

    if (strcasecmp($priority, 'Urgent') === 0 && !empty($config['emergency_recipients'])) {
        $emergency_numbers = smsgateParseNumbers($config['emergency_recipients']);
        if (!empty($emergency_numbers)) {
            if (!empty($config['urgent_message'])) {
                $urgent_body = str_replace(
                    ['{ticket_prefix}', '{ticket_number}', '{ticket_ref}', '{ticket_subject}', '{client_name}', '{contact_name}', '{priority}'],
                    [$row['ticket_prefix'] ?? '', $row['ticket_number'], $ticket_ref, $subject, $client_name, $contact_name, $priority],
                    $config['urgent_message']
                );
            } else {
                $urgent_body = "URGENT Ticket #$ticket_ref ($client_name): $subject";
            }
            smsgateSend($emergency_numbers, $urgent_body);
        }
    }

    $ack_number = '';
    if (!empty($provided_phone)) {
        $ack_number = smsgateFormatPhone($provided_phone);
    }
    if (empty($ack_number) && !empty($row['contact_mobile'])) {
        $ack_number = smsgateFormatPhone($row['contact_mobile'], $row['contact_mobile_country_code'] ?? '');
    }
    if (empty($ack_number) && !empty($row['contact_phone'])) {
        $ack_number = smsgateFormatPhone($row['contact_phone'], $row['contact_phone_country_code'] ?? '');
    }

    if (!empty($ack_number)) {
        if (!empty($config['ack_message'])) {
            $ack_body = str_replace(
                ['{ticket_prefix}', '{ticket_number}', '{ticket_ref}', '{ticket_subject}', '{client_name}', '{contact_name}', '{priority}'],
                [$row['ticket_prefix'] ?? '', $row['ticket_number'], $ticket_ref, $subject, $client_name, $contact_name, $priority],
                $config['ack_message']
            );
        } else {
            $ack_body = "Your ticket #$ticket_ref ($subject) has been received. Our team is reviewing it.";
        }
        smsgateSend([$ack_number], $ack_body);
    }
}

function smsgateEnsureTechWeeklyTable($mysqli) {
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tech_weekly_summary_configs` (
      `config_id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `enabled` tinyint(1) NOT NULL DEFAULT 0,
      `sms_numbers` text DEFAULT NULL,
      `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
      `extra_emails` text DEFAULT NULL,
      `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`config_id`),
      UNIQUE KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
}

function smsgateFormatSecondsToHms($seconds) {
    $seconds = (int) max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

function smsgateFormatSecondsToDecimalHours($seconds) {
    $seconds = (int) max(0, $seconds);
    if ($seconds === 0) {
        return '0.00';
    }
    return number_format($seconds / 3600, 2);
}

function smsgateGetPriorWeekRange($ref_timestamp = null, $exact_week = false) {
    if ($ref_timestamp === null) {
        $ref_timestamp = time();
    }
    $day_of_week = (int) date('N', $ref_timestamp);
    $current_monday = strtotime('-' . ($day_of_week - 1) . ' days', strtotime(date('Y-m-d', $ref_timestamp)));
    $target_monday = $exact_week ? $current_monday : strtotime('-7 days', $current_monday);
    $target_sunday = strtotime('+6 days', $target_monday);

    return [
        'week_start' => date('Y-m-d', $target_monday),
        'week_end' => date('Y-m-d', $target_sunday),
    ];
}

function smsgateGetTechWeeklySummaryData($mysqli, $tech_id, $week_start, $week_end) {
    $tech_id = (int) $tech_id;
    $week_start_esc = escapeSql($week_start);
    $week_end_esc = escapeSql($week_end);
    $week_start_dt = $week_start_esc . ' 00:00:00';
    $week_end_dt = $week_end_esc . ' 23:59:59';

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
        GROUP BY c.client_id, c.client_name
        ORDER BY c.client_name ASC
    ");

    $sql_trips_by_client = mysqli_query($mysqli, "
        SELECT 
            COALESCE(c.client_id, 0) AS client_id,
            COALESCE(c.client_name, 'No Client Assigned / General') AS client_name,
            COUNT(tp.trip_id) AS trip_count,
            SUM(tp.trip_miles) AS total_miles
        FROM trips tp
        LEFT JOIN clients c ON tp.trip_client_id = c.client_id
        WHERE tp.trip_user_id = $tech_id
          AND tp.trip_date >= '$week_start_esc'
          AND tp.trip_date <= '$week_end_esc'
          AND tp.trip_archived_at IS NULL
        GROUP BY c.client_id, c.client_name
        ORDER BY c.client_name ASC
    ");

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
        ORDER BY tr.ticket_reply_created_at ASC
    ");

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
          AND tp.trip_date >= '$week_start_esc'
          AND tp.trip_date <= '$week_end_esc'
          AND tp.trip_archived_at IS NULL
        ORDER BY tp.trip_date ASC, tp.trip_id ASC
    ");

    $client_map = [];

    if ($sql_time_by_client) {
        while ($time_row = mysqli_fetch_assoc($sql_time_by_client)) {
            $c_id = (int) $time_row['client_id'];
            $client_map[$c_id] = [
                'client_id' => $c_id,
                'client_name' => $time_row['client_name'],
                'seconds' => (int) $time_row['total_seconds'],
                'tickets' => (int) $time_row['ticket_count'],
                'entries' => (int) $time_row['entry_count'],
                'miles' => 0.0,
                'trips' => 0
            ];
        }
    }

    if ($sql_trips_by_client) {
        while ($trip_row = mysqli_fetch_assoc($sql_trips_by_client)) {
            $c_id = (int) $trip_row['client_id'];
            if (!isset($client_map[$c_id])) {
                $client_map[$c_id] = [
                    'client_id' => $c_id,
                    'client_name' => $trip_row['client_name'],
                    'seconds' => 0,
                    'tickets' => 0,
                    'entries' => 0,
                    'miles' => (float) $trip_row['total_miles'],
                    'trips' => (int) $trip_row['trip_count']
                ];
            } else {
                $client_map[$c_id]['miles'] += (float) $trip_row['total_miles'];
                $client_map[$c_id]['trips'] += (int) $trip_row['trip_count'];
            }
        }
    }

    $tech_total_seconds = 0;
    $tech_total_miles = 0.0;
    $tech_total_tickets = 0;
    $tech_total_trips = 0;

    foreach ($client_map as $c_data) {
        $tech_total_seconds += $c_data['seconds'];
        $tech_total_miles += $c_data['miles'];
        $tech_total_tickets += $c_data['tickets'];
        $tech_total_trips += $c_data['trips'];
    }

    $time_rows = [];
    if ($sql_tech_time_details) {
        while ($r = mysqli_fetch_assoc($sql_tech_time_details)) {
            $time_rows[] = $r;
        }
    }

    $trip_rows = [];
    if ($sql_tech_trip_details) {
        while ($r = mysqli_fetch_assoc($sql_tech_trip_details)) {
            $trip_rows[] = $r;
        }
    }

    return [
        'client_map' => $client_map,
        'total_seconds' => $tech_total_seconds,
        'total_miles' => $tech_total_miles,
        'total_tickets' => $tech_total_tickets,
        'total_trips' => $tech_total_trips,
        'time_rows' => $time_rows,
        'trip_rows' => $trip_rows
    ];
}

function smsgateBuildTechWeeklySms($tech_name, $tech_data, $week_start, $week_end) {
    $range_str = date('M j', strtotime($week_start)) . ' - ' . date('M j', strtotime($week_end));
    $dec_hours = smsgateFormatSecondsToDecimalHours($tech_data['total_seconds']);
    $hms_hours = smsgateFormatSecondsToHms($tech_data['total_seconds']);
    $miles = number_format($tech_data['total_miles'], 1);
    $tickets = (int) $tech_data['total_tickets'];
    $trips = (int) $tech_data['total_trips'];

    return "Weekly Summary ($range_str) - $tech_name:\n"
        . "Hours: $dec_hours hrs ($hms_hours)\n"
        . "Miles: $miles mi\n"
        . "Tickets: $tickets | Trips: $trips";
}

function smsgateBuildTechWeeklyEmail($tech_name, $tech_id, $tech_data, $week_start, $week_end, $base_url) {
    $safe_name = escapeHtml($tech_name);
    $dec_hours = smsgateFormatSecondsToDecimalHours($tech_data['total_seconds']);
    $hms_hours = smsgateFormatSecondsToHms($tech_data['total_seconds']);
    $miles = number_format($tech_data['total_miles'], 1);
    $tickets = (int) $tech_data['total_tickets'];
    $trips = (int) $tech_data['total_trips'];
    $period_str = date('F j, Y', strtotime($week_start)) . ' &ndash; ' . date('F j, Y', strtotime($week_end));
    $base_url_clean = rtrim((string) $base_url, '/');
    if ($base_url_clean !== '' && !preg_match('#^https?://#i', $base_url_clean)) {
        $base_url_clean = 'https://' . $base_url_clean;
    }
    $report_url = $base_url_clean !== '' ? "$base_url_clean/agent/reports/tech_weekly_summary.php?date=$week_start&user_id=" . (int) $tech_id : '';

    $body = "
<div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #212529; max-width: 820px; margin: 0 auto; line-height: 1.5;'>
    <div style='background-color: #343a40; color: #ffffff; padding: 18px 24px; border-radius: 6px 6px 0 0;'>
        <h2 style='margin: 0; font-size: 20px; font-weight: 600;'>Weekly Time &amp; Mileage Summary &mdash; $safe_name</h2>
        <div style='color: #ced4da; font-size: 13px; margin-top: 4px;'>
            Week of $period_str (Monday &ndash; Sunday)
        </div>
    </div>

    <div style='border: 1px solid #dee2e6; border-top: none; padding: 20px 24px; background: #ffffff; border-radius: 0 0 6px 6px;'>
        <table style='width: 100%; border-collapse: separate; border-spacing: 10px 0; margin: 0 -10px 20px -10px;'>
            <tr>
                <td style='background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 12px 14px; text-align: left; width: 25%;'>
                    <div style='font-size: 11px; text-transform: uppercase; color: #6c757d; font-weight: 600;'>Total Hours</div>
                    <div style='font-size: 20px; font-weight: 700; color: #007bff; margin-top: 4px;'>$dec_hours hrs</div>
                    <div style='font-size: 12px; color: #6c757d;'>$hms_hours</div>
                </td>
                <td style='background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 12px 14px; text-align: left; width: 25%;'>
                    <div style='font-size: 11px; text-transform: uppercase; color: #6c757d; font-weight: 600;'>Total Mileage</div>
                    <div style='font-size: 20px; font-weight: 700; color: #28a745; margin-top: 4px;'>$miles mi</div>
                </td>
                <td style='background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 12px 14px; text-align: left; width: 25%;'>
                    <div style='font-size: 11px; text-transform: uppercase; color: #6c757d; font-weight: 600;'>Tickets Worked</div>
                    <div style='font-size: 20px; font-weight: 700; color: #343a40; margin-top: 4px;'>$tickets</div>
                </td>
                <td style='background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 12px 14px; text-align: left; width: 25%;'>
                    <div style='font-size: 11px; text-transform: uppercase; color: #6c757d; font-weight: 600;'>Trips Logged</div>
                    <div style='font-size: 20px; font-weight: 700; color: #343a40; margin-top: 4px;'>$trips</div>
                </td>
            </tr>
        </table>

        <h3 style='font-size: 15px; font-weight: 600; margin: 16px 0 8px 0; color: #343a40;'>Client Breakdown</h3>
        <table style='width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px;'>
            <thead>
                <tr style='background-color: #f1f3f5; border-bottom: 2px solid #dee2e6; text-align: left;'>
                    <th style='padding: 8px 10px; font-weight: 600; color: #495057;'>Client Name</th>
                    <th style='padding: 8px 10px; font-weight: 600; color: #495057; text-align: right;'>Tickets</th>
                    <th style='padding: 8px 10px; font-weight: 600; color: #495057; text-align: right;'>Hours (Dec)</th>
                    <th style='padding: 8px 10px; font-weight: 600; color: #495057; text-align: right;'>Hours (H:M:S)</th>
                    <th style='padding: 8px 10px; font-weight: 600; color: #495057; text-align: right;'>Trips</th>
                    <th style='padding: 8px 10px; font-weight: 600; color: #495057; text-align: right;'>Miles</th>
                </tr>
            </thead>
            <tbody>";

    foreach ($tech_data['client_map'] as $c_stat) {
        $c_name = escapeHtml($c_stat['client_name']);
        $c_tickets = (int) $c_stat['tickets'];
        $c_dec = smsgateFormatSecondsToDecimalHours($c_stat['seconds']);
        $c_hms = smsgateFormatSecondsToHms($c_stat['seconds']);
        $c_trips = (int) $c_stat['trips'];
        $c_miles = number_format($c_stat['miles'], 1);

        $body .= "
                <tr style='border-bottom: 1px solid #e9ecef;'>
                    <td style='padding: 8px 10px; font-weight: 600; color: #212529;'>$c_name</td>
                    <td style='padding: 8px 10px; text-align: right; color: #6c757d;'>$c_tickets</td>
                    <td style='padding: 8px 10px; text-align: right; font-weight: 600; color: #007bff;'>$c_dec hrs</td>
                    <td style='padding: 8px 10px; text-align: right; color: #6c757d;'>$c_hms</td>
                    <td style='padding: 8px 10px; text-align: right; color: #6c757d;'>$c_trips</td>
                    <td style='padding: 8px 10px; text-align: right; font-weight: 600; color: #28a745;'>$c_miles mi</td>
                </tr>";
    }

    $body .= "
            </tbody>
            <tfoot>
                <tr style='background-color: #f8f9fa; border-top: 2px solid #dee2e6; font-weight: 700;'>
                    <td style='padding: 8px 10px;'>Total &mdash; $safe_name</td>
                    <td style='padding: 8px 10px; text-align: right;'>$tickets</td>
                    <td style='padding: 8px 10px; text-align: right; color: #007bff;'>$dec_hours hrs</td>
                    <td style='padding: 8px 10px; text-align: right; color: #6c757d;'>$hms_hours</td>
                    <td style='padding: 8px 10px; text-align: right;'>$trips</td>
                    <td style='padding: 8px 10px; text-align: right; color: #28a745;'>$miles mi</td>
                </tr>
            </tfoot>
        </table>";

    if (!empty($tech_data['time_rows'])) {
        $body .= "
        <h3 style='font-size: 15px; font-weight: 600; margin: 16px 0 8px 0; color: #343a40;'>Ticket Time Logs (" . count($tech_data['time_rows']) . ")</h3>
        <table style='width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 20px;'>
            <thead>
                <tr style='background-color: #f1f3f5; border-bottom: 1px solid #dee2e6; text-align: left;'>
                    <th style='padding: 6px 10px;'>Date</th>
                    <th style='padding: 6px 10px;'>Ticket</th>
                    <th style='padding: 6px 10px;'>Client</th>
                    <th style='padding: 6px 10px;'>Subject</th>
                    <th style='padding: 6px 10px; text-align: right;'>Time Worked</th>
                </tr>
            </thead>
            <tbody>";
        foreach ($tech_data['time_rows'] as $log) {
            $log_date = date('M j, g:i A', strtotime($log['ticket_reply_created_at']));
            $t_ref = escapeHtml(($log['ticket_prefix'] ?? '') . $log['ticket_number']);
            $t_subj = escapeHtml($log['ticket_subject'] ?? '');
            $c_name = escapeHtml($log['client_name']);
            $t_worked = escapeHtml($log['ticket_reply_time_worked']);
            $t_link = $base_url_clean !== '' ? "<a href='$base_url_clean/agent/ticket.php?ticket_id=" . (int)$log['ticket_id'] . "' style='color: #007bff; text-decoration: none; font-weight: 600;'>#$t_ref</a>" : "#$t_ref";

            $body .= "
                <tr style='border-bottom: 1px solid #e9ecef;'>
                    <td style='padding: 6px 10px; white-space: nowrap;'>$log_date</td>
                    <td style='padding: 6px 10px; white-space: nowrap;'>$t_link</td>
                    <td style='padding: 6px 10px;'>$c_name</td>
                    <td style='padding: 6px 10px; color: #495057;'>$t_subj</td>
                    <td style='padding: 6px 10px; text-align: right; font-weight: 600;'>$t_worked</td>
                </tr>";
        }
        $body .= "
            </tbody>
        </table>";
    }

    if (!empty($tech_data['trip_rows'])) {
        $body .= "
        <h3 style='font-size: 15px; font-weight: 600; margin: 16px 0 8px 0; color: #343a40;'>Trip Mileage Logs (" . count($tech_data['trip_rows']) . ")</h3>
        <table style='width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 20px;'>
            <thead>
                <tr style='background-color: #f1f3f5; border-bottom: 1px solid #dee2e6; text-align: left;'>
                    <th style='padding: 6px 10px;'>Date</th>
                    <th style='padding: 6px 10px;'>Client</th>
                    <th style='padding: 6px 10px;'>Purpose / Route</th>
                    <th style='padding: 6px 10px; text-align: right;'>Miles</th>
                </tr>
            </thead>
            <tbody>";
        foreach ($tech_data['trip_rows'] as $trip) {
            $trip_date = date('M j', strtotime($trip['trip_date']));
            $c_name = escapeHtml($trip['client_name']);
            $purpose = escapeHtml($trip['trip_purpose']);
            $route = '';
            if (!empty($trip['trip_source']) || !empty($trip['trip_destination'])) {
                $route = " <span style='color: #6c757d;'>(" . escapeHtml($trip['trip_source']) . " &rarr; " . escapeHtml($trip['trip_destination']) . ")</span>";
            }
            if ((int)$trip['round_trip'] === 1) {
                $route .= " <span style='font-size: 11px; color: #495057;'>[Round Trip]</span>";
            }
            $t_miles = number_format((float)$trip['trip_miles'], 1);

            $body .= "
                <tr style='border-bottom: 1px solid #e9ecef;'>
                    <td style='padding: 6px 10px; white-space: nowrap;'>$trip_date</td>
                    <td style='padding: 6px 10px;'>$c_name</td>
                    <td style='padding: 6px 10px;'>$purpose$route</td>
                    <td style='padding: 6px 10px; text-align: right; font-weight: 600; color: #28a745;'>$t_miles mi</td>
                </tr>";
        }
        $body .= "
            </tbody>
        </table>";
    }

    if ($report_url !== '') {
        $body .= "
        <div style='margin-top: 16px; text-align: right;'>
            <a href='$report_url' style='display: inline-block; background-color: #007bff; color: #ffffff; text-decoration: none; padding: 8px 16px; border-radius: 4px; font-size: 13px; font-weight: 600;'>View Full Report in ITFlow</a>
        </div>";
    }

    $body .= "
    </div>
</div>";

    return $body;
}

function smsgateDispatchTechWeeklySummaries($mysqli, $options = []) {
    global $config_base_url;

    smsgateEnsureTechWeeklyTable($mysqli);

    $settings_q = mysqli_query($mysqli, "SELECT config_tech_weekly_summary_enable, config_mail_from_email, config_mail_from_name FROM settings WHERE company_id = 1 LIMIT 1");
    $settings = $settings_q ? mysqli_fetch_assoc($settings_q) : [];

    $force = !empty($options['force']);
    $dry_run = !empty($options['dry_run']);
    $only_user_id = intval($options['user_id'] ?? 0);

    if (!$force && empty($settings['config_tech_weekly_summary_enable'])) {
        return [
            'success' => false,
            'error' => 'Weekly Technician Summary is disabled in settings.',
            'sent_sms_techs' => 0,
            'sent_sms_numbers' => 0,
            'queued_emails' => 0,
            'skipped_zero' => 0,
            'details' => []
        ];
    }

    if (!empty($options['week_start']) && !empty($options['week_end'])) {
        $week_start = $options['week_start'];
        $week_end = $options['week_end'];
    } else {
        $range = smsgateGetPriorWeekRange();
        $week_start = $range['week_start'];
        $week_end = $range['week_end'];
    }

    $from_email = !empty($settings['config_mail_from_email']) ? $settings['config_mail_from_email'] : ('noreply@' . ($config_base_url ?? 'localhost'));
    $from_name = !empty($settings['config_mail_from_name']) ? $settings['config_mail_from_name'] : 'ITFlow Reports';

    $user_filter = $only_user_id > 0 ? "AND u.user_id = $only_user_id" : "AND twsc.enabled = 1";

    $sql_techs = mysqli_query($mysqli, "
        SELECT 
            u.user_id,
            u.user_name,
            u.user_email,
            COALESCE(twsc.enabled, 0) AS enabled,
            COALESCE(twsc.sms_numbers, '') AS sms_numbers,
            COALESCE(twsc.email_enabled, 1) AS email_enabled,
            COALESCE(twsc.extra_emails, '') AS extra_emails
        FROM users u
        LEFT JOIN tech_weekly_summary_configs twsc ON u.user_id = twsc.user_id
        WHERE u.user_type = 1
          AND u.user_status = 1
          AND u.user_archived_at IS NULL
          $user_filter
        ORDER BY u.user_name ASC
    ");

    $results = [
        'success' => true,
        'week_start' => $week_start,
        'week_end' => $week_end,
        'sent_sms_techs' => 0,
        'sent_sms_numbers' => 0,
        'queued_emails' => 0,
        'skipped_zero' => 0,
        'errors' => [],
        'details' => []
    ];

    $mail_queue_batch = [];

    if ($sql_techs) {
        while ($tech = mysqli_fetch_assoc($sql_techs)) {
            $tech_id = (int) $tech['user_id'];
            $tech_name = $tech['user_name'];
            $tech_email = trim((string) $tech['user_email']);
            $sms_numbers = smsgateParseNumbers($tech['sms_numbers']);
            $email_enabled = intval($tech['email_enabled']) === 1;

            $tech_data = smsgateGetTechWeeklySummaryData($mysqli, $tech_id, $week_start, $week_end);

            if ($tech_data['total_seconds'] <= 0 && $tech_data['total_miles'] <= 0) {
                $results['skipped_zero']++;
                $results['details'][] = [
                    'tech_id' => $tech_id,
                    'tech_name' => $tech_name,
                    'status' => 'skipped_zero',
                    'hours' => '0.00',
                    'miles' => '0.0'
                ];
                continue;
            }

            $sms_sent = false;
            $sms_error = null;
            if (!empty($sms_numbers)) {
                $sms_body = smsgateBuildTechWeeklySms($tech_name, $tech_data, $week_start, $week_end);
                if ($dry_run) {
                    $sms_sent = true;
                    $results['sent_sms_techs']++;
                    $results['sent_sms_numbers'] += count($sms_numbers);
                } else {
                    $sms_res = smsgateSend($sms_numbers, $sms_body, true);
                    if (!empty($sms_res['success'])) {
                        $sms_sent = true;
                        $results['sent_sms_techs']++;
                        $results['sent_sms_numbers'] += count($sms_numbers);
                    } else {
                        $sms_error = $sms_res['error'] ?? 'SMS failed';
                        $results['errors'][] = "$tech_name SMS: $sms_error";
                    }
                }
            }

            $email_recipients = [];
            if ($email_enabled && filter_var($tech_email, FILTER_VALIDATE_EMAIL)) {
                $email_recipients[] = $tech_email;
            }
            if (!empty($tech['extra_emails'])) {
                $extra_parts = preg_split('/[\r\n,;]+/', (string) $tech['extra_emails']);
                foreach ($extra_parts as $ep) {
                    $ep = trim($ep);
                    if (filter_var($ep, FILTER_VALIDATE_EMAIL) && !in_array($ep, $email_recipients, true)) {
                        $email_recipients[] = $ep;
                    }
                }
            }

            if (!empty($email_recipients)) {
                $subject_line = "Weekly Time & Mileage Summary ($tech_name) - " . date('M j', strtotime($week_start)) . " to " . date('M j, Y', strtotime($week_end));
                $email_body = smsgateBuildTechWeeklyEmail($tech_name, $tech_id, $tech_data, $week_start, $week_end, $config_base_url ?? '');

                foreach ($email_recipients as $rcpt) {
                    if (!$dry_run) {
                        $mail_queue_batch[] = [
                            'from' => escapeSql($from_email),
                            'from_name' => escapeSql($from_name),
                            'recipient' => escapeSql($rcpt),
                            'recipient_name' => escapeSql($tech_name),
                            'subject' => escapeSql($subject_line),
                            'body' => mysqli_real_escape_string($mysqli, $email_body)
                        ];
                    }
                    $results['queued_emails']++;
                }
            }

            $results['details'][] = [
                'tech_id' => $tech_id,
                'tech_name' => $tech_name,
                'status' => 'sent',
                'hours' => smsgateFormatSecondsToDecimalHours($tech_data['total_seconds']),
                'miles' => number_format($tech_data['total_miles'], 1),
                'sms_recipients' => $sms_numbers,
                'sms_sent' => $sms_sent,
                'sms_error' => $sms_error,
                'email_recipients' => $email_recipients
            ];
        }
    }

    if (!$dry_run && !empty($mail_queue_batch)) {
        addToMailQueue($mail_queue_batch);
    }

    return $results;
}
