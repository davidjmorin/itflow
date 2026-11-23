<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_ticket_rule'])) {

    validateCSRFToken();

    $name = escapeSql($_POST['name']);
    $order = intval($_POST['order'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;
    $match_type = in_array($_POST['match_type'] ?? '', ['all', 'any']) ? $_POST['match_type'] : 'all';
    $sender_email = !empty($_POST['sender_email']) ? escapeSql(trim($_POST['sender_email'])) : 'NULL';
    $sender_domain = !empty($_POST['sender_domain']) ? escapeSql(trim($_POST['sender_domain'])) : 'NULL';
    $subject = !empty($_POST['subject']) ? escapeSql(trim($_POST['subject'])) : 'NULL';
    $body = !empty($_POST['body']) ? escapeSql(trim($_POST['body'])) : 'NULL';
    $use_regex = isset($_POST['use_regex']) ? 1 : 0;
    $action = in_array($_POST['action'] ?? '', ['route', 'ignore']) ? $_POST['action'] : 'route';
    $client_id = intval($_POST['client_id'] ?? 0);
    $priority = !empty($_POST['priority']) ? "'" . escapeSql($_POST['priority']) . "'" : "NULL";
    $category = !empty($_POST['category']) ? "'" . escapeSql($_POST['category']) . "'" : "NULL";
    $assigned_to = intval($_POST['assigned_to'] ?? 0);
    $status = intval($_POST['status'] ?? 1);
    $stop_processing = isset($_POST['stop_processing']) ? 1 : 0;

    $sender_email_val = ($sender_email === 'NULL') ? 'NULL' : "'$sender_email'";
    $sender_domain_val = ($sender_domain === 'NULL') ? 'NULL' : "'$sender_domain'";
    $subject_val = ($subject === 'NULL') ? 'NULL' : "'$subject'";
    $body_val = ($body === 'NULL') ? 'NULL' : "'$body'";

    mysqli_query($mysqli, "INSERT INTO ticket_rules SET
        ticket_rule_name = '$name',
        ticket_rule_order = $order,
        ticket_rule_active = $active,
        ticket_rule_match_type = '$match_type',
        ticket_rule_sender_email = $sender_email_val,
        ticket_rule_sender_domain = $sender_domain_val,
        ticket_rule_subject = $subject_val,
        ticket_rule_body = $body_val,
        ticket_rule_use_regex = $use_regex,
        ticket_rule_action = '$action',
        ticket_rule_client_id = $client_id,
        ticket_rule_priority = $priority,
        ticket_rule_category = $category,
        ticket_rule_assigned_to = $assigned_to,
        ticket_rule_status = $status,
        ticket_rule_stop_processing = $stop_processing
    ");

    $rule_id = mysqli_insert_id($mysqli);

    logAudit("Ticket Rule", "Create", "$session_name created inbound ticket rule $name", 0, $rule_id);

    flashAlert("Inbound Ticket Rule <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_ticket_rule'])) {

    validateCSRFToken();

    $rule_id = intval($_POST['ticket_rule_id']);
    $name = escapeSql($_POST['name']);
    $order = intval($_POST['order'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;
    $match_type = in_array($_POST['match_type'] ?? '', ['all', 'any']) ? $_POST['match_type'] : 'all';
    $sender_email = !empty($_POST['sender_email']) ? escapeSql(trim($_POST['sender_email'])) : 'NULL';
    $sender_domain = !empty($_POST['sender_domain']) ? escapeSql(trim($_POST['sender_domain'])) : 'NULL';
    $subject = !empty($_POST['subject']) ? escapeSql(trim($_POST['subject'])) : 'NULL';
    $body = !empty($_POST['body']) ? escapeSql(trim($_POST['body'])) : 'NULL';
    $use_regex = isset($_POST['use_regex']) ? 1 : 0;
    $action = in_array($_POST['action'] ?? '', ['route', 'ignore']) ? $_POST['action'] : 'route';
    $client_id = intval($_POST['client_id'] ?? 0);
    $priority = !empty($_POST['priority']) ? "'" . escapeSql($_POST['priority']) . "'" : "NULL";
    $category = !empty($_POST['category']) ? "'" . escapeSql($_POST['category']) . "'" : "NULL";
    $assigned_to = intval($_POST['assigned_to'] ?? 0);
    $status = intval($_POST['status'] ?? 1);
    $stop_processing = isset($_POST['stop_processing']) ? 1 : 0;

    $sender_email_val = ($sender_email === 'NULL') ? 'NULL' : "'$sender_email'";
    $sender_domain_val = ($sender_domain === 'NULL') ? 'NULL' : "'$sender_domain'";
    $subject_val = ($subject === 'NULL') ? 'NULL' : "'$subject'";
    $body_val = ($body === 'NULL') ? 'NULL' : "'$body'";

    mysqli_query($mysqli, "UPDATE ticket_rules SET
        ticket_rule_name = '$name',
        ticket_rule_order = $order,
        ticket_rule_active = $active,
        ticket_rule_match_type = '$match_type',
        ticket_rule_sender_email = $sender_email_val,
        ticket_rule_sender_domain = $sender_domain_val,
        ticket_rule_subject = $subject_val,
        ticket_rule_body = $body_val,
        ticket_rule_use_regex = $use_regex,
        ticket_rule_action = '$action',
        ticket_rule_client_id = $client_id,
        ticket_rule_priority = $priority,
        ticket_rule_category = $category,
        ticket_rule_assigned_to = $assigned_to,
        ticket_rule_status = $status,
        ticket_rule_stop_processing = $stop_processing
        WHERE ticket_rule_id = $rule_id
    ");

    logAudit("Ticket Rule", "Edit", "$session_name edited inbound ticket rule $name", 0, $rule_id);

    flashAlert("Inbound Ticket Rule <strong>$name</strong> updated");

    redirect();

}

if (isset($_GET['delete_ticket_rule'])) {

    validateCSRFToken();

    $rule_id = intval($_GET['delete_ticket_rule']);

    $rule_sql = mysqli_query($mysqli, "SELECT ticket_rule_name FROM ticket_rules WHERE ticket_rule_id = $rule_id LIMIT 1");
    $rule_row = mysqli_fetch_assoc($rule_sql);
    $rule_name = escapeSql($rule_row['ticket_rule_name'] ?? '');

    mysqli_query($mysqli, "DELETE FROM ticket_rules WHERE ticket_rule_id = $rule_id");

    logAudit("Ticket Rule", "Delete", "$session_name deleted inbound ticket rule $rule_name", 0, $rule_id);

    flashAlert("Inbound Ticket Rule <strong>$rule_name</strong> deleted", 'error');

    redirect();

}

if (isset($_GET['toggle_ticket_rule'])) {

    validateCSRFToken();

    $rule_id = intval($_GET['toggle_ticket_rule']);

    mysqli_query($mysqli, "UPDATE ticket_rules SET ticket_rule_active = IF(ticket_rule_active = 1, 0, 1) WHERE ticket_rule_id = $rule_id");

    flashAlert("Rule status updated");

    redirect();

}
