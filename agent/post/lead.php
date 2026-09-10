<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_lead'])) {
    validateCSRFToken();
    enforceUserPermission('module_client', 2);

    $lead_name = escapeSql($_POST['lead_name']);
    $lead_contact_name = escapeSql($_POST['lead_contact_name'] ?? '');
    $lead_contact_title = escapeSql($_POST['lead_contact_title'] ?? '');
    $lead_contact_email = escapeSql($_POST['lead_contact_email'] ?? '');
    $lead_contact_phone = escapeSql($_POST['lead_contact_phone'] ?? '');
    $lead_contact_mobile = escapeSql($_POST['lead_contact_mobile'] ?? '');
    $lead_website = escapeSql($_POST['lead_website'] ?? '');
    $lead_address = escapeSql($_POST['lead_address'] ?? '');
    $lead_city = escapeSql($_POST['lead_city'] ?? '');
    $lead_state = escapeSql($_POST['lead_state'] ?? '');
    $lead_zip = escapeSql($_POST['lead_zip'] ?? '');
    $lead_country = escapeSql($_POST['lead_country'] ?? '');
    $lead_stage_id = intval($_POST['lead_stage_id'] ?? 1);
    $lead_source = escapeSql($_POST['lead_source'] ?? '');
    $lead_estimated_mrr = floatval($_POST['lead_estimated_mrr'] ?? 0);
    $lead_estimated_seats = intval($_POST['lead_estimated_seats'] ?? 0);
    $lead_assigned_user_id = intval($_POST['lead_assigned_user_id'] ?? 0);
    $lead_notes = escapeSql($_POST['lead_notes'] ?? '');
    $lead_next_follow_up = !empty($_POST['lead_next_follow_up']) ? "'" . escapeSql($_POST['lead_next_follow_up']) . "'" : "NULL";

    mysqli_query($mysqli, "INSERT INTO leads SET
        lead_name = '$lead_name',
        lead_contact_name = '$lead_contact_name',
        lead_contact_title = '$lead_contact_title',
        lead_contact_email = '$lead_contact_email',
        lead_contact_phone = '$lead_contact_phone',
        lead_contact_mobile = '$lead_contact_mobile',
        lead_website = '$lead_website',
        lead_address = '$lead_address',
        lead_city = '$lead_city',
        lead_state = '$lead_state',
        lead_zip = '$lead_zip',
        lead_country = '$lead_country',
        lead_stage_id = $lead_stage_id,
        lead_status = 'Open',
        lead_source = '$lead_source',
        lead_estimated_mrr = $lead_estimated_mrr,
        lead_estimated_seats = $lead_estimated_seats,
        lead_assigned_user_id = $lead_assigned_user_id,
        lead_notes = '$lead_notes',
        lead_next_follow_up = $lead_next_follow_up");

    $lead_id = mysqli_insert_id($mysqli);

    $creator_id = intval($_SESSION['user_id'] ?? 0);
    $stage_name_res = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT lead_stage_name FROM lead_stages WHERE lead_stage_id = $lead_stage_id LIMIT 1"));
    $stage_name = escapeSql($stage_name_res['lead_stage_name'] ?? 'New');
    
    mysqli_query($mysqli, "INSERT INTO lead_activities SET
        lead_activity_lead_id = $lead_id,
        lead_activity_type = 'Stage Change',
        lead_activity_title = 'Lead Created in $stage_name',
        lead_activity_details = 'Lead was added into the pipeline.',
        lead_activity_created_by = $creator_id");

    logAudit("Lead", "Create", "$session_name created lead $lead_name (ID: $lead_id)");
    flashAlert("Lead <strong>$lead_name</strong> added successfully!", "success");
    redirect("/agent/lead.php?lead_id=$lead_id");
}

if (isset($_POST['edit_lead'])) {
    validateCSRFToken();
    enforceUserPermission('module_client', 2);

    $lead_id = intval($_POST['lead_id']);
    $lead_name = escapeSql($_POST['lead_name']);
    $lead_status = escapeSql($_POST['lead_status'] ?? 'Open');
    $lead_contact_name = escapeSql($_POST['lead_contact_name'] ?? '');
    $lead_contact_title = escapeSql($_POST['lead_contact_title'] ?? '');
    $lead_contact_email = escapeSql($_POST['lead_contact_email'] ?? '');
    $lead_contact_phone = escapeSql($_POST['lead_contact_phone'] ?? '');
    $lead_contact_mobile = escapeSql($_POST['lead_contact_mobile'] ?? '');
    $lead_website = escapeSql($_POST['lead_website'] ?? '');
    $lead_address = escapeSql($_POST['lead_address'] ?? '');
    $lead_city = escapeSql($_POST['lead_city'] ?? '');
    $lead_state = escapeSql($_POST['lead_state'] ?? '');
    $lead_zip = escapeSql($_POST['lead_zip'] ?? '');
    $lead_country = escapeSql($_POST['lead_country'] ?? '');
    $lead_stage_id = intval($_POST['lead_stage_id'] ?? 1);
    $lead_source = escapeSql($_POST['lead_source'] ?? '');
    $lead_estimated_mrr = floatval($_POST['lead_estimated_mrr'] ?? 0);
    $lead_estimated_seats = intval($_POST['lead_estimated_seats'] ?? 0);
    $lead_assigned_user_id = intval($_POST['lead_assigned_user_id'] ?? 0);
    $lead_notes = escapeSql($_POST['lead_notes'] ?? '');
    $lead_next_follow_up = !empty($_POST['lead_next_follow_up']) ? "'" . escapeSql($_POST['lead_next_follow_up']) . "'" : "NULL";

    $old_res = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT lead_stage_id, lead_status FROM leads WHERE lead_id = $lead_id LIMIT 1"));
    $old_stage_id = intval($old_res['lead_stage_id'] ?? 0);

    mysqli_query($mysqli, "UPDATE leads SET
        lead_name = '$lead_name',
        lead_status = '$lead_status',
        lead_contact_name = '$lead_contact_name',
        lead_contact_title = '$lead_contact_title',
        lead_contact_email = '$lead_contact_email',
        lead_contact_phone = '$lead_contact_phone',
        lead_contact_mobile = '$lead_contact_mobile',
        lead_website = '$lead_website',
        lead_address = '$lead_address',
        lead_city = '$lead_city',
        lead_state = '$lead_state',
        lead_zip = '$lead_zip',
        lead_country = '$lead_country',
        lead_stage_id = $lead_stage_id,
        lead_source = '$lead_source',
        lead_estimated_mrr = $lead_estimated_mrr,
        lead_estimated_seats = $lead_estimated_seats,
        lead_assigned_user_id = $lead_assigned_user_id,
        lead_notes = '$lead_notes',
        lead_next_follow_up = $lead_next_follow_up
        WHERE lead_id = $lead_id");

    if ($old_stage_id !== $lead_stage_id) {
        $creator_id = intval($_SESSION['user_id'] ?? 0);
        $stage_name_res = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT lead_stage_name FROM lead_stages WHERE lead_stage_id = $lead_stage_id LIMIT 1"));
        $stage_name = escapeSql($stage_name_res['lead_stage_name'] ?? 'Updated Stage');
        mysqli_query($mysqli, "INSERT INTO lead_activities SET
            lead_activity_lead_id = $lead_id,
            lead_activity_type = 'Stage Change',
            lead_activity_title = 'Stage Changed to $stage_name',
            lead_activity_details = 'Lead stage was updated manually.',
            lead_activity_created_by = $creator_id");
    }

    logAudit("Lead", "Edit", "$session_name updated lead $lead_name (ID: $lead_id)");
    flashAlert("Lead <strong>$lead_name</strong> updated!", "success");
    redirect("/agent/lead.php?lead_id=$lead_id");
}

if (isset($_POST['convert_lead'])) {
    validateCSRFToken();
    enforceUserPermission('module_client', 2);

    $lead_id = intval($_POST['lead_id']);
    $client_name = escapeSql($_POST['client_name']);
    $client_type = escapeSql($_POST['client_type'] ?? 'Commercial');
    $client_website = escapeSql($_POST['client_website'] ?? '');
    $client_referral = escapeSql($_POST['client_referral'] ?? '');
    $client_rate = floatval($_POST['client_rate'] ?? 0);
    $client_net_terms = intval($_POST['client_net_terms'] ?? 30);
    $address = escapeSql($_POST['address'] ?? '');
    $city = escapeSql($_POST['city'] ?? '');
    $state = escapeSql($_POST['state'] ?? '');
    $zip = escapeSql($_POST['zip'] ?? '');
    $country = escapeSql($_POST['country'] ?? '');
    $contact_name = escapeSql($_POST['contact_name'] ?? '');
    $contact_title = escapeSql($_POST['contact_title'] ?? '');
    $contact_email = escapeSql($_POST['contact_email'] ?? '');
    $contact_phone = escapeSql($_POST['contact_phone'] ?? '');

    mysqli_query($mysqli, "INSERT INTO clients SET
        client_name = '$client_name',
        client_type = '$client_type',
        client_website = '$client_website',
        client_referral = '$client_referral',
        client_rate = $client_rate,
        client_currency_code = '$session_company_currency',
        client_net_terms = $client_net_terms,
        client_lead = 0,
        client_notes = 'Converted from Sales Lead (Lead ID: $lead_id)',
        client_accessed_at = NOW()");

    $client_id = mysqli_insert_id($mysqli);

    $client_folder = $_SERVER['DOCUMENT_ROOT'] . "/uploads/clients/$client_id";
    if (!file_exists($client_folder)) {
        mkdir($client_folder, 0755, true);
        file_put_contents("$client_folder/index.php", "");
    }

    if (!empty($address) || !empty($city) || !empty($state) || !empty($zip)) {
        mysqli_query($mysqli, "INSERT INTO locations SET
            location_name = 'Primary',
            location_address = '$address',
            location_city = '$city',
            location_state = '$state',
            location_zip = '$zip',
            location_country = '$country',
            location_phone = '$contact_phone',
            location_primary = 1,
            location_client_id = $client_id");
    }

    if (!empty($contact_name) || !empty($contact_email) || !empty($contact_phone)) {
        mysqli_query($mysqli, "INSERT INTO contacts SET
            contact_name = '$contact_name',
            contact_title = '$contact_title',
            contact_email = '$contact_email',
            contact_phone = '$contact_phone',
            contact_primary = 1,
            contact_important = 1,
            contact_client_id = $client_id");
    }

    $won_stage_res = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT lead_stage_id FROM lead_stages WHERE lead_stage_is_won = 1 LIMIT 1"));
    $won_stage_id = intval($won_stage_res['lead_stage_id'] ?? 5);

    mysqli_query($mysqli, "UPDATE leads SET
        lead_status = 'Converted',
        lead_stage_id = $won_stage_id,
        lead_converted_client_id = $client_id,
        lead_converted_at = NOW()
        WHERE lead_id = $lead_id");

    $creator_id = intval($_SESSION['user_id'] ?? 0);
    mysqli_query($mysqli, "INSERT INTO lead_activities SET
        lead_activity_lead_id = $lead_id,
        lead_activity_type = 'Conversion',
        lead_activity_title = 'Converted to Client #$client_id',
        lead_activity_details = 'Lead successfully signed up and converted to full client: $client_name.',
        lead_activity_created_by = $creator_id");

    logAudit("Lead", "Convert", "$session_name converted lead $client_name (Lead ID: $lead_id) to Client ID: $client_id");
    flashAlert("Lead successfully converted to Client <strong>$client_name</strong>!", "success");
    redirect("/agent/client_overview.php?client_id=$client_id");
}

if (isset($_POST['send_lead_email'])) {
    validateCSRFToken();
    enforceUserPermission('module_client', 2);

    $lead_id = intval($_POST['lead_id']);
    $recipient_name = escapeSql($_POST['recipient_name']);
    $recipient_email = escapeSql($_POST['recipient_email']);
    $email_subject = escapeSql($_POST['email_subject']);
    $email_body = $_POST['email_body'];

    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        flashAlert("Invalid recipient email address.", "danger");
        redirect("/agent/lead.php?lead_id=$lead_id");
    }

    $email_data = [
        [
            'from' => $config_smtp_from_email ?? $session_email,
            'from_name' => $session_company_name ?? $session_name,
            'recipient' => $recipient_email,
            'recipient_name' => $recipient_name,
            'subject' => $email_subject,
            'body' => $email_body
        ]
    ];

    addToMailQueue($email_data);

    $creator_id = intval($_SESSION['user_id'] ?? 0);
    $preview_text = escapeSql(strip_tags($email_body));
    if (strlen($preview_text) > 250) {
        $preview_text = substr($preview_text, 0, 250) . '...';
    }

    mysqli_query($mysqli, "INSERT INTO lead_activities SET
        lead_activity_lead_id = $lead_id,
        lead_activity_type = 'Email',
        lead_activity_title = 'Outreach Email: $email_subject',
        lead_activity_details = 'Sent to $recipient_name ($recipient_email):<br>$preview_text',
        lead_activity_created_by = $creator_id");

    logAudit("Lead", "Email", "$session_name queued lead outreach email to $recipient_email for Lead ID: $lead_id");
    flashAlert("Email queued successfully for <strong>$recipient_email</strong>!", "success");
    redirect("/agent/lead.php?lead_id=$lead_id");
}

if (isset($_POST['add_lead_activity'])) {
    validateCSRFToken();
    enforceUserPermission('module_client', 2);

    $lead_id = intval($_POST['lead_id']);
    $lead_activity_type = escapeSql($_POST['lead_activity_type']);
    $lead_activity_title = escapeSql($_POST['lead_activity_title']);
    $lead_activity_details = escapeSql($_POST['lead_activity_details'] ?? '');
    $creator_id = intval($_SESSION['user_id'] ?? 0);

    mysqli_query($mysqli, "INSERT INTO lead_activities SET
        lead_activity_lead_id = $lead_id,
        lead_activity_type = '$lead_activity_type',
        lead_activity_title = '$lead_activity_title',
        lead_activity_details = '$lead_activity_details',
        lead_activity_created_by = $creator_id");

    if (!empty($_POST['lead_next_follow_up'])) {
        $lead_next_follow_up = "'" . escapeSql($_POST['lead_next_follow_up']) . "'";
        mysqli_query($mysqli, "UPDATE leads SET lead_next_follow_up = $lead_next_follow_up WHERE lead_id = $lead_id");
    }

    flashAlert("Activity logged successfully!", "success");
    redirect("/agent/lead.php?lead_id=$lead_id");
}

if (isset($_POST['archive_lead']) || isset($_GET['archive_lead'])) {
    validateCSRFToken();
    enforceUserPermission('module_client', 2);

    $lead_id = intval($_POST['lead_id'] ?? $_GET['archive_lead']);
    mysqli_query($mysqli, "UPDATE leads SET lead_archived_at = NOW() WHERE lead_id = $lead_id");

    logAudit("Lead", "Archive", "$session_name archived lead ID: $lead_id");
    flashAlert("Lead archived.", "warning");
    redirect("/agent/leads.php");
}

if (isset($_POST['delete_lead']) || isset($_GET['delete_lead'])) {
    validateCSRFToken();
    enforceUserPermission('module_client', 3);

    $lead_id = intval($_POST['lead_id'] ?? $_GET['delete_lead']);
    mysqli_query($mysqli, "DELETE FROM lead_activities WHERE lead_activity_lead_id = $lead_id");
    mysqli_query($mysqli, "DELETE FROM leads WHERE lead_id = $lead_id");

    logAudit("Lead", "Delete", "$session_name deleted lead ID: $lead_id");
    flashAlert("Lead permanently deleted.", "danger");
    redirect("/agent/leads.php");
}
