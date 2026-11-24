<?php

if (isset($_POST['guest_submit_ticket'])) {
    require_once __DIR__ . '/guest_check_company.php';

    if (!empty($_POST['website_hp'])) {
        header("Location: guest_submit_ticket.php");
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $priority = trim($_POST['priority'] ?? 'Low');
    $details = trim($_POST['details'] ?? '');

    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($subject) || empty($details)) {
        flashAlert("Please fill in all required fields with a valid email address.", 'danger');
        header("Location: guest_submit_ticket.php");
        exit;
    }

    if (!in_array($priority, ['Low', 'Medium', 'High', 'Urgent'])) {
        $priority = 'Low';
    }

    $name_esc = mysqli_real_escape_string($mysqli, $name);
    $email_esc = mysqli_real_escape_string($mysqli, $email);
    $phone_esc = mysqli_real_escape_string($mysqli, $phone);
    $company_name_esc = mysqli_real_escape_string($mysqli, $company_name);
    $subject_esc = mysqli_real_escape_string($mysqli, $subject);
    $priority_esc = mysqli_real_escape_string($mysqli, $priority);

    $formatted_details = "<i>Submitted via Web Portal by <b>" . escapeHtml($name) . "</b> &lt;" . escapeHtml($email) . "&gt;</i><br><br>" . nl2br(escapeHtml($details));
    $formatted_details_esc = mysqli_real_escape_string($mysqli, $formatted_details);

    $duplicate_check_sql = mysqli_query($mysqli, "
        SELECT ticket_id, ticket_url_key, ticket_prefix, ticket_number
        FROM tickets
        WHERE ticket_subject = '$subject_esc'
          AND ticket_details = '$formatted_details_esc'
          AND ticket_created_at >= NOW() - INTERVAL 30 SECOND
        LIMIT 1
    ");

    if ($duplicate_check_sql && mysqli_num_rows($duplicate_check_sql) > 0) {
        $dup_row = mysqli_fetch_assoc($duplicate_check_sql);
        $dup_ticket_id = intval($dup_row['ticket_id']);
        $dup_url_key = $dup_row['ticket_url_key'];
        $dup_prefix = $dup_row['ticket_prefix'];
        $dup_number = $dup_row['ticket_number'];

        flashAlert("Your ticket #$dup_prefix$dup_number has been submitted successfully!");
        header("Location: guest_view_ticket.php?ticket_id=$dup_ticket_id&url_key=$dup_url_key");
        exit;
    }

    $match = guestFindClientByEmail($mysqli, $email);
    if (!$match['matched'] || intval($match['client_id']) <= 0) {
        $support_email = !empty($config_ticket_from_email) ? $config_ticket_from_email : ($config_mail_from_email ?? '');
        flashAlert("We could not match your email domain to an existing company. Please email " . escapeHtml($support_email) . " directly to open a ticket.", 'warning');
        header("Location: guest_submit_ticket.php");
        exit;
    }

    $client_id = intval($match['client_id']);
    $contact_id = intval($match['contact_id']);
    $company_name = (string)$match['client_name'];

    if ($contact_id === 0) {
        mysqli_query($mysqli, "INSERT INTO contacts SET contact_name = '$name_esc', contact_email = '$email_esc', contact_phone = '$phone_esc', contact_notes = 'Added automatically via guest ticket submission.', contact_client_id = $client_id");
        $contact_id = mysqli_insert_id($mysqli);
    }

    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number),
            config_ticket_next_number = config_ticket_next_number + 1
        WHERE company_id = 1
    ");
    $ticket_number = mysqli_insert_id($mysqli);

    $url_key = randomString(32);
    $config_ticket_prefix_esc = mysqli_real_escape_string($mysqli, $config_ticket_prefix ?? 'ITF-');

    $default_billable = intval($config_ticket_default_billable ?? 0);

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix_esc', ticket_number = $ticket_number, ticket_source = 'Portal', ticket_subject = '$subject_esc', ticket_details = '$formatted_details_esc', ticket_priority = '$priority_esc', ticket_status = 1, ticket_billable = $default_billable, ticket_created_by = 0, ticket_contact_id = $contact_id, ticket_client_id = $client_id, ticket_url_key = '$url_key'");
    $ticket_id = mysqli_insert_id($mysqli);
    applyTicketSla($ticket_id);

    $allowed_extensions = ['jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx', 'csv', 'xls', 'xlsx', 'xlsm', 'zip', 'tar', 'gz'];
    if (!empty($_FILES['attachments']['name'][0])) {
        $ticket_dir = "../uploads/tickets/" . $ticket_id . "/";
        mkdirMissing($ticket_dir);
        foreach ($_FILES['attachments']['name'] as $key => $att_name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $att_tmp = $_FILES['attachments']['tmp_name'][$key];
                $att_ext = strtolower(pathinfo($att_name, PATHINFO_EXTENSION));
                if (in_array($att_ext, $allowed_extensions)) {
                    $att_saved_name = md5(uniqid(rand(), true)) . '.' . $att_ext;
                    if (move_uploaded_file($att_tmp, $ticket_dir . $att_saved_name)) {
                        $att_name_esc = mysqli_real_escape_string($mysqli, $att_name);
                        $att_saved_name_esc = mysqli_real_escape_string($mysqli, $att_saved_name);
                        mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = '$att_name_esc', ticket_attachment_reference_name = '$att_saved_name_esc', ticket_attachment_ticket_id = $ticket_id");
                    }
                }
            }
        }
    }

    if (!empty($config_ticket_new_ticket_notification_email)) {
        $client_label = $company_name ?: ($name . ' (Guest)');
        if ($client_id) {
            $c_q = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id"));
            if (!empty($c_q['client_name'])) {
                $client_label = $c_q['client_name'];
            }
        }
        $agent_email_subject = mysqli_real_escape_string($mysqli, "ITFlow - New Ticket - $client_label: $subject");
        $agent_email_body = mysqli_real_escape_string($mysqli, "Hello,<br><br>A new ticket has been submitted via the guest portal.<br>Client: " . escapeHtml($client_label) . "<br>Contact: " . escapeHtml($name) . " &lt;" . escapeHtml($email) . "&gt;<br>Priority: $priority<br>Ticket: $config_ticket_prefix_esc$ticket_number<br>Link: https://$config_base_url/agent/ticket.php?ticket_id=$ticket_id<br><br><b>" . escapeHtml($subject) . "</b><br>" . nl2br(escapeHtml($details)));

        $mail_data = [
            [
                'from' => $config_ticket_from_email,
                'from_name' => mysqli_real_escape_string($mysqli, $config_ticket_from_name),
                'recipient' => $config_ticket_new_ticket_notification_email,
                'recipient_name' => mysqli_real_escape_string($mysqli, $config_ticket_from_name),
                'subject' => $agent_email_subject,
                'body' => $agent_email_body
            ]
        ];
        addToMailQueue($mail_data);
    }

    $rendered_mail = renderTicketCreatedEmail($ticket_id, $name, $email, true);
    $client_mail_data = [
        [
            'from' => $config_ticket_from_email,
            'from_name' => mysqli_real_escape_string($mysqli, $config_ticket_from_name),
            'recipient' => $email,
            'recipient_name' => mysqli_real_escape_string($mysqli, $name),
            'subject' => mysqli_real_escape_string($mysqli, $rendered_mail['subject']),
            'body' => mysqli_real_escape_string($mysqli, $rendered_mail['body'])
        ]
    ];
    addToMailQueue($client_mail_data);

    smsgateTicketNotify($ticket_id, $phone);
    triggerCustomAction('ticket_create', $ticket_id);
    $audit_description = mysqli_real_escape_string($mysqli, "Guest $name ($email) created ticket $config_ticket_prefix_esc$ticket_number ($subject)");
    logAudit("Ticket", "Create", $audit_description, $client_id, $ticket_id);

    flashAlert("Your ticket #$config_ticket_prefix_esc$ticket_number has been submitted successfully!");
    header("Location: guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key");
    exit;
}
