<?php

// App/UI helpers - icons, badges, lookups, mail queue, iCal, taxes, update check
// Split from the former monolithic functions.php

function getAssetIcon($asset_type) {
    if ($asset_type == 'Laptop') {
        $device_icon = "laptop";
    } elseif ($asset_type == 'Desktop') {
        $device_icon = "desktop";
    } elseif ($asset_type == 'Server') {
        $device_icon = "server";
    } elseif ($asset_type == 'Printer') {
        $device_icon = "print";
    } elseif ($asset_type == 'Camera') {
        $device_icon = "video";
    } elseif ($asset_type == 'Switch') {
        $device_icon = "network-wired";
    } elseif ($asset_type == 'Firewall/Router') {
        $device_icon = "fire-alt";
    } elseif ($asset_type == 'Access Point') {
        $device_icon = "wifi";
    } elseif ($asset_type == 'Phone') {
        $device_icon = "phone";
    } elseif ($asset_type == 'Mobile Phone') {
        $device_icon = "mobile-alt";
    } elseif ($asset_type == 'Tablet') {
        $device_icon = "tablet-alt";
    } elseif ($asset_type == 'Display') {
        $device_icon = "tv";
    } elseif ($asset_type == 'Virtual Machine') {
        $device_icon = "cloud";
    } else {
        $device_icon = "tag";
    }

    return $device_icon;
}

function getInvoiceBadgeColor($invoice_status) {
    if ($invoice_status == "Sent") {
        $invoice_badge_color = "warning text-white";
    } elseif ($invoice_status == "Viewed") {
        $invoice_badge_color = "info";
    } elseif ($invoice_status == "Partial") {
        $invoice_badge_color = "primary";
    } elseif ($invoice_status == "Paid") {
        $invoice_badge_color = "success";
    } elseif ($invoice_status == "Cancelled") {
        $invoice_badge_color = "danger";
    } else {
        $invoice_badge_color = "secondary";
    }

    return $invoice_badge_color;
}

/*
 * The display name for a ticket status id, RAW. Escaping is the caller's job -
 * same convention as getFieldById() above.
 */
function getTicketStatusName($ticket_status) {

    global $mysqli;

    $status_id = intval($ticket_status);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_status_name FROM ticket_statuses WHERE ticket_status_id = $status_id LIMIT 1"));

    if (!$row) {
        // Default return
        return "Unknown";
    }

    return $row['ticket_status_name'];

}


/**
 * Copies a ticket template's tasks onto a ticket.
 *
 * Called anywhere a ticket is raised from a template - the ticket add modals,
 * the bulk asset add, and every recurring ticket run (cron, force, bulk force).
 * Tasks are a snapshot: editing the template later does not touch tickets that
 * have already been raised from it.
 *
 * @param int $ticket_id          The ticket to attach the tasks to.
 * @param int $ticket_template_id The template to copy tasks from. 0 = no-op.
 *
 * @return int The number of tasks created.
 */
function addTasksFromTicketTemplate($ticket_id, $ticket_template_id) {

    global $mysqli;

    $ticket_id = intval($ticket_id);
    $ticket_template_id = intval($ticket_template_id);

    if (!$ticket_id || !$ticket_template_id) {
        return 0;
    }

    $sql_task_templates = mysqli_query($mysqli, "SELECT task_template_completion_estimate, task_template_name, task_template_order FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id ORDER BY task_template_order ASC");

    $tasks_added = 0;

    while ($row = mysqli_fetch_assoc($sql_task_templates)) {
        $task_order = intval($row['task_template_order']);
        $task_name = escapeSql($row['task_template_name']);
        $task_completion_estimate = intval($row['task_template_completion_estimate']);

        mysqli_query($mysqli, "INSERT INTO tasks SET task_name = '$task_name', task_order = $task_order, task_completion_estimate = $task_completion_estimate, task_ticket_id = $ticket_id");

        $tasks_added++;
    }

    return $tasks_added;

}

/**
 * Copies a recurring ticket's task list onto a ticket it has just raised.
 *
 * Recurring tickets own their tasks (see recurring_ticket_tasks) rather than
 * reading the linked ticket template at run time, so that a schedule's task
 * list can be edited without touching the template or any other schedule.
 *
 * @param int $ticket_id           The ticket to attach the tasks to.
 * @param int $recurring_ticket_id The schedule to copy tasks from. 0 = no-op.
 *
 * @return void
 */
function addTasksFromRecurringTicket($ticket_id, $recurring_ticket_id) {

    global $mysqli;

    $ticket_id = intval($ticket_id);
    $recurring_ticket_id = intval($recurring_ticket_id);

    if (!$ticket_id || !$recurring_ticket_id) {
        return;
    }

    mysqli_query($mysqli, "INSERT INTO tasks (task_name, task_order, task_completion_estimate, task_ticket_id)
        SELECT recurring_ticket_task_name, recurring_ticket_task_order, recurring_ticket_task_completion_estimate, $ticket_id
        FROM recurring_ticket_tasks
        WHERE recurring_ticket_task_recurring_ticket_id = $recurring_ticket_id
        ORDER BY recurring_ticket_task_order ASC");

}

/**
 * Reads the editable task rows posted by the ticket and recurring ticket modals.
 *
 * The rows submit as parallel tasks[] and task_estimates[] arrays, aligned by
 * their order in the form. Rows left blank are dropped, and the order is taken
 * from the surviving rows rather than the raw array index.
 *
 * Names come back already escaped for SQL, as every caller inserts them.
 *
 * @return array List of ['name' => string, 'order' => int, 'estimate' => int]
 */
function parseSubmittedTasks() {

    $tasks = [];

    if (empty($_POST['tasks']) || !is_array($_POST['tasks'])) {
        return $tasks;
    }

    $estimates = $_POST['task_estimates'] ?? [];
    $task_order = 0;

    foreach ($_POST['tasks'] as $index => $task_name) {
        $task_name = trim($task_name);

        if ($task_name === '') {
            continue;
        }

        $tasks[] = [
            'name' => escapeSql($task_name),
            'order' => $task_order,
            'estimate' => intval($estimates[$index] ?? 0)
        ];

        $task_order++;
    }

    return $tasks;
}

/*
 * Fetches one field from one row by id, and returns it RAW.
 *
 * Escaping is the caller's job, the same as any other value read out of the
 * database - wrap the call in escapeSql() for a query or escapeHtml() for
 * output. This function used to escape for you via an $escape_method argument,
 * which meant half its callers wrapped it in escapeSql() anyway and got a
 * double-escaped value: a client named O'Brien came back as O\'Brien and the
 * backslash ended up in export filenames, flash messages and, on the user
 * restore path, written back into the database.
 *
 * Table, field and id are still validated here - that is about building a safe
 * query, not about escaping what comes out of it.
 */
function getFieldById($table, $id, $field) {
    global $mysqli;  // Use the global MySQLi connection

    // Validate table and field names to allow only letters, numbers, and underscores
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
        return null; // Invalid table or field name
    }

    // Sanitize id as an integer
    $id = (int)$id;

    /*
     * Get the list of columns from the table, to find the primary key and to
     * confirm the requested field exists.
     *
     * The catch is what makes the "table not found" case actually return null:
     * mysqli throws on an unknown table by default on PHP 8.1+, so this
     * function's own not-found branch was unreachable and a bad table name
     * took the whole page down instead.
     */
    try {
        $columns_result = mysqli_query($mysqli, "SHOW COLUMNS FROM `$table`");
    } catch (mysqli_sql_exception $e) {
        return null; // Table not found
    }

    if (!$columns_result || mysqli_num_rows($columns_result) == 0) {
        return null; // Table not found or has no columns
    }

    $columns = [];
    $id_field = null;
    while ($row = mysqli_fetch_assoc($columns_result)) {
        $columns[$row['Field']] = true;
        if (!$id_field && $row['Key'] === 'PRI') {
            $id_field = $row['Field'];
        }
    }

    // Fallback: if no primary key is found, use the first column
    if (!$id_field) {
        reset($columns);
        $id_field = key($columns);
    }

    // Ensure the requested field exists; if not, default to the id field
    if (!array_key_exists($field, $columns)) {
        $field = $id_field;
    }

    // Build and execute the query to fetch the specified field value
    $sql = mysqli_query($mysqli, "SELECT `$field` FROM `$table` WHERE `$id_field` = $id");

    if ($sql && mysqli_num_rows($sql) > 0) {
        $row = mysqli_fetch_assoc($sql);
        return $row[$field];
    }

    return null; // Return null if no record was found
}


// Recursive function to display folder options - Used in folders files and documents
function displayFolderOptions($parent_folder_id, $client_id, $indent = 0) {
    global $mysqli;

    $sql_folders = mysqli_query($mysqli, "SELECT folder_id, folder_name FROM folders WHERE parent_folder = $parent_folder_id AND folder_client_id = $client_id ORDER BY folder_name ASC");
    while ($row = mysqli_fetch_assoc($sql_folders)) {
        $folder_id = intval($row['folder_id']);
        $folder_name = escapeHtml($row['folder_name']);

        // Indentation for subfolders
        $indentation = str_repeat('&nbsp;', $indent * 4);

        // Check if this folder is selected
        $selected = '';
        if ((isset($_GET['folder_id']) && intval($_GET['folder_id']) === $folder_id) ||
            (isset($_POST['folder']) && intval($_POST['folder']) === $folder_id)) {
            $selected = 'selected';
        }

        echo "<option value=\"$folder_id\" $selected>$indentation$folder_name</option>";

        // Recursively display subfolders
        displayFolderOptions($folder_id, $client_id, $indent + 1);
    }
}

/*
 * The branch this install tracks. Both setup paths write $repo_branch into config.php, but
 * an install older than that has no value at all, and every caller puts it into a shell
 * command - so it is defaulted and escaped here rather than trusted at each call site.
 */
function getRepoBranch(): string
{
    global $repo_branch;

    $branch = trim((string) ($repo_branch ?? ''));

    return $branch === '' ? 'master' : $branch;
}

function checkForUpdates() {

    $remote_ref = escapeshellarg("origin/" . getRepoBranch());

    // Fetch the latest code changes but don't apply them. stderr is merged in because git
    // reports failures there, and it is the only thing the update page can show when this
    // breaks - it used to run a second git fetch of its own just to get the message.
    exec("git fetch 2>&1", $output, $result);
    $latest_version = exec("git rev-parse $remote_ref");
    $current_version = exec("git rev-parse HEAD");

    if ($current_version == $latest_version) {
        $update_message = "No Updates available";
    } else {
        $update_message = "New Updates are Available [$latest_version]";
    }


    $updates = new stdClass();
    $updates->output = $output;
    $updates->result = $result;
    $updates->current_version = $current_version;
    $updates->latest_version = $latest_version;
    $updates->update_message = $update_message;


    return $updates;

}

function getMonthlyTax($tax_name, $month, $year, $mysqli) {
    // SQL to calculate monthly tax
    $sql = "SELECT SUM(item_tax) AS monthly_tax FROM invoice_items
            LEFT JOIN invoices ON invoice_items.item_invoice_id = invoices.invoice_id
            LEFT JOIN payments ON invoices.invoice_id = payments.payment_invoice_id
            WHERE YEAR(payments.payment_date) = $year AND MONTH(payments.payment_date) = $month
            AND invoice_items.item_tax_id = (SELECT tax_id FROM taxes WHERE tax_name = '$tax_name')";
    $result = mysqli_query($mysqli, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['monthly_tax'] ?? 0;
}

function getQuarterlyTax($tax_name, $quarter, $year, $mysqli) {
    // Calculate start and end months for the quarter
    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $start_month + 2;

    // SQL to calculate quarterly tax
    $sql = "SELECT SUM(item_tax) AS quarterly_tax FROM invoice_items
            LEFT JOIN invoices ON invoice_items.item_invoice_id = invoices.invoice_id
            LEFT JOIN payments ON invoices.invoice_id = payments.payment_invoice_id
            WHERE YEAR(payments.payment_date) = $year AND MONTH(payments.payment_date) BETWEEN $start_month AND $end_month
            AND invoice_items.item_tax_id = (SELECT tax_id FROM taxes WHERE tax_name = '$tax_name')";
    $result = mysqli_query($mysqli, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['quarterly_tax'] ?? 0;
}

function addToMailQueue($data) {

    if (empty($data) || !is_iterable($data)) {
        return;
    }

    global $mysqli;

    $settings_sql = mysqli_query($mysqli, "SELECT config_email_header, config_email_footer FROM settings WHERE company_id = 1");
    $settings_row = mysqli_fetch_assoc($settings_sql);
    $header = $settings_row['config_email_header'] ?? '';
    $footer = $settings_row['config_email_footer'] ?? '';

    foreach ($data as $email) {
        $from = strval($email['from']);
        $from_name = strval($email['from_name']);
        $recipient = strval($email['recipient']);
        $recipient_name = strval($email['recipient_name']);
        $subject = strval($email['subject']);
        $body = $header . strval($email['body']) . $footer;

        $cal_str = '';
        if (isset($email['cal_str'])) {
            $cal_str = mysqli_escape_string($mysqli, $email['cal_str']);
        }

        // Attachments travel as a manifest of app-root-relative paths rather than
        // file contents, so the queue table stays small. cron/mail_queue.php
        // re-checks each path is inside uploads/ before attaching it.
        $attachments = '';
        if (!empty($email['attachments']) && is_array($email['attachments'])) {
            $attachment_manifest = [];
            foreach ($email['attachments'] as $attachment) {
                if (empty($attachment['path']) || empty($attachment['name'])) {
                    continue;
                }
                $attachment_manifest[] = [
                    'path' => strval($attachment['path']),
                    'name' => strval($attachment['name'])
                ];
            }
            if ($attachment_manifest) {
                $attachments = mysqli_escape_string($mysqli, json_encode($attachment_manifest));
            }
        }

        // Check if 'email_queued_at' is set and not empty
        if (isset($email['queued_at']) && !empty($email['queued_at'])) {
            $queued_at = "'" . escapeSql($email['queued_at']) . "'";
        } else {
            // Use the current date and time if 'email_queued_at' is not set or empty
            $queued_at = 'CURRENT_TIMESTAMP()';
        }

        mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$recipient', email_recipient_name = '$recipient_name', email_from = '$from', email_from_name = '$from_name', email_subject = '$subject', email_content = '$body', email_queued_at = $queued_at, email_cal_str = '$cal_str', email_attachments = '$attachments'");
    }

    return true;
}

function getTicketCalendarUid($ticket_id) {
    // An invite and its later cancellation MUST carry the same UID or the
    // recipient's calendar client cannot match them up. Derive it from the
    // ticket so it is stable across both, rather than from the current time.
    $ticket_id = intval($ticket_id);
    $host = $_SERVER['SERVER_NAME'] ?? 'itflow';
    return "ticket-$ticket_id@$host";
}

function createiCalStr($datetime, $title, $description, $location, $uid = null) {
    require_once "../libs/zapcal/zapcallib.php";

    // Create the iCal object
    $cal_event = new ZCiCal();
    $event = new ZCiCalNode("VEVENT", $cal_event->curnode);


    // Set the method to REQUEST to indicate an invite
    $event->addNode(new ZCiCalDataNode("METHOD:REQUEST"));
    $event->addNode(new ZCiCalDataNode("SUMMARY:" . $title));
    $event->addNode(new ZCiCalDataNode("DTSTART:" . ZCiCal::fromSqlDateTime($datetime)));
    // Assuming the end time is the same as start time.
    // Todo: adjust this for actual duration
    $event->addNode(new ZCiCalDataNode("DTEND:" . ZCiCal::fromSqlDateTime($datetime)));
    $event->addNode(new ZCiCalDataNode("DTSTAMP:" . ZCiCal::fromSqlDateTime()));
    if (empty($uid)) {
        $uid = date('Y-m-d-H-i-s') . "@" . ($_SERVER['SERVER_NAME'] ?? 'itflow');
    }
    $event->addNode(new ZCiCalDataNode("UID:" . $uid));
    $event->addNode(new ZCiCalDataNode("SEQUENCE:0"));
    $event->addNode(new ZCiCalDataNode("LOCATION:" . $location));
    $event->addNode(new ZCiCalDataNode("DESCRIPTION:" . $description));
    // Todo: add organizer details
    // $event->addNode(new ZCiCalDataNode("ORGANIZER;CN=Organizer Name:MAILTO:organizer@example.com"));

    // Return the iCal string
    return $cal_event->export();
}

function createiCalStrCancel($datetime, $title, $uid) {
    require_once "../libs/zapcal/zapcallib.php";

    // Build the cancellation fresh. There is no stored copy of the original
    // invite to reopen - the match is made by UID, not by the body.
    $cal_event = new ZCiCal();

    // METHOD belongs on the VCALENDAR, not on the VEVENT
    $cal_event->tree->data['METHOD'] = new ZCiCalDataNode("METHOD:CANCEL");

    $event = new ZCiCalNode("VEVENT", $cal_event->curnode);
    $event->addNode(new ZCiCalDataNode("UID:" . $uid));
    $event->addNode(new ZCiCalDataNode("SUMMARY:" . $title));
    if (!empty($datetime)) {
        $event->addNode(new ZCiCalDataNode("DTSTART:" . ZCiCal::fromSqlDateTime($datetime)));
        $event->addNode(new ZCiCalDataNode("DTEND:" . ZCiCal::fromSqlDateTime($datetime)));
    }
    $event->addNode(new ZCiCalDataNode("DTSTAMP:" . ZCiCal::fromSqlDateTime()));
    // Must outrank the invite's SEQUENCE:0 or clients ignore the cancellation
    $event->addNode(new ZCiCalDataNode("SEQUENCE:1"));
    $event->addNode(new ZCiCalDataNode("STATUS:CANCELLED"));

    return $cal_event->export();
}

function renderTicketCreatedEmail($ticket_id, $recipient_name = '', $recipient_email = '', $is_guest = false) {
    global $mysqli, $config_base_url;

    $ticket_id = intval($ticket_id);
    $ticket_sql = mysqli_query($mysqli, "SELECT t.*, c.client_name, s.ticket_status_name 
        FROM tickets t 
        LEFT JOIN clients c ON t.ticket_client_id = c.client_id 
        LEFT JOIN ticket_statuses s ON t.ticket_status = s.ticket_status_id 
        WHERE t.ticket_id = $ticket_id LIMIT 1");
    if (!$ticket_sql || mysqli_num_rows($ticket_sql) === 0) {
        return ['subject' => '', 'body' => ''];
    }
    $ticket = mysqli_fetch_assoc($ticket_sql);

    $ticket_prefix = $ticket['ticket_prefix'] ?? '';
    $ticket_number = $ticket['ticket_number'] ?? '';
    $ticket_subject = $ticket['ticket_subject'] ?? '';
    $ticket_details = $ticket['ticket_details'] ?? '';
    $ticket_priority = $ticket['ticket_priority'] ?? 'Standard';
    $ticket_status = $ticket['ticket_status_name'] ?? 'Open';
    $client_name = $ticket['client_name'] ?? 'Guest';
    $url_key = $ticket['ticket_url_key'] ?? '';

    if (empty($recipient_name)) {
        $contact_id = intval($ticket['ticket_contact_id'] ?? 0);
        if ($contact_id > 0) {
            $contact_sql = mysqli_query($mysqli, "SELECT contact_name FROM contacts WHERE contact_id = $contact_id LIMIT 1");
            if ($contact_sql && mysqli_num_rows($contact_sql) > 0) {
                $contact_row = mysqli_fetch_assoc($contact_sql);
                $recipient_name = $contact_row['contact_name'] ?? '';
            }
        }
        if (empty($recipient_name)) {
            $recipient_name = 'Customer';
        }
    }

    $company_sql = mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1 LIMIT 1");
    $company = mysqli_fetch_assoc($company_sql);
    $company_name = $company['company_name'] ?? 'Support';
    $company_phone = !empty($company['company_phone']) ? formatPhoneNumber($company['company_phone'], $company['company_phone_country_code'] ?? '') : '';

    if ($is_guest || empty($ticket['ticket_client_id']) || !empty($url_key)) {
        $portal_url = "https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key";
    } else {
        $portal_url = "https://$config_base_url/client/ticket.php?id=$ticket_id";
    }

    $settings_sql = mysqli_query($mysqli, "SELECT config_ticket_client_email_subject, config_ticket_client_email_body FROM settings WHERE company_id = 1 LIMIT 1");
    $settings = mysqli_fetch_assoc($settings_sql);

    $template_subject = !empty($settings['config_ticket_client_email_subject']) 
        ? $settings['config_ticket_client_email_subject'] 
        : '[{{ticket_prefix}}{{ticket_number}}] Ticket Received - {{ticket_subject}}';

    $default_body = '<p><i style="color: #808080;">##- Please type your reply above this line -##</i></p>' . "\n" .
        '<p>Hello {{contact_name}},</p>' . "\n" .
        '<p>Thank you for reaching out. We have received your support request regarding "<strong>{{ticket_subject}}</strong>".</p>' . "\n" .
        '<table style="border-collapse: collapse; width: 100%; max-width: 500px; margin: 15px 0;">' . "\n" .
        '  <tr><td style="padding: 6px 12px 6px 0; font-weight: bold; width: 130px;">Ticket Number:</td><td style="padding: 6px 0;">{{ticket_prefix}}{{ticket_number}}</td></tr>' . "\n" .
        '  <tr><td style="padding: 6px 12px 6px 0; font-weight: bold;">Subject:</td><td style="padding: 6px 0;">{{ticket_subject}}</td></tr>' . "\n" .
        '  <tr><td style="padding: 6px 12px 6px 0; font-weight: bold;">Status:</td><td style="padding: 6px 0;">{{ticket_status}}</td></tr>' . "\n" .
        '  <tr><td style="padding: 6px 12px 6px 0; font-weight: bold;">Priority:</td><td style="padding: 6px 0;">{{ticket_priority}}</td></tr>' . "\n" .
        '</table>' . "\n" .
        '<p>You can track the progress and reply to this ticket online at:<br>' . "\n" .
        '<a href="{{portal_url}}">{{portal_url}}</a></p>' . "\n" .
        '<p>--<br><strong>{{company_name}}</strong> - Support<br>{{company_phone}}</p>';

    $template_body = !empty($settings['config_ticket_client_email_body']) 
        ? $settings['config_ticket_client_email_body'] 
        : $default_body;

    $placeholders = [
        '{{ticket_prefix}}' => $ticket_prefix,
        '{{ticket_number}}' => $ticket_number,
        '{{ticket_prefix_number}}' => $ticket_prefix . $ticket_number,
        '{{ticket_subject}}' => $ticket_subject,
        '{{subject}}' => $ticket_subject,
        '{{ticket_details}}' => $ticket_details,
        '{{details}}' => $ticket_details,
        '{{ticket_priority}}' => $ticket_priority,
        '{{priority}}' => $ticket_priority,
        '{{ticket_status}}' => $ticket_status,
        '{{status}}' => $ticket_status,
        '{{contact_name}}' => $recipient_name,
        '{{name}}' => $recipient_name,
        '{{client_name}}' => $client_name,
        '{{portal_url}}' => $portal_url,
        '{{ticket_url}}' => $portal_url,
        '{{company_name}}' => $company_name,
        '{{company_phone}}' => $company_phone,
        '{{reply_above_line}}' => '<i style="color: #808080">##- Please type your reply above this line -##</i>'
    ];

    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $template_subject);
    $body = str_replace(array_keys($placeholders), array_values($placeholders), $template_body);

    return [
        'subject' => $subject,
        'body' => $body
    ];
}

function inferAssetTypeFromProductName($product_name) {
    $name = strtolower((string) $product_name);
    $map = [
        'Switch' => ['switch', 'unifi usw', 'catalyst', 'procurve', 'aruba instant on'],
        'Laptop' => ['laptop', 'notebook', 'macbook', 'thinkpad', 'chromebook', 'elitebook', 'latitude', 'inspiron'],
        'Desktop' => ['desktop', 'workstation', 'optiplex', 'prodesk', 'elitedesk', 'imac', 'mac mini', 'nuc', 'mini pc'],
        'Server' => ['server', 'poweredge', 'proliant', 'thinksystem', 'xeon', 'nas', 'synology', 'qnap'],
        'Firewall/Router' => ['firewall', 'router', 'gateway', 'udm', 'fortigate', 'sonicwall', 'pfsense', 'opnsense', 'watchguard', 'meraki mx', 'mikrotik'],
        'Access Point' => ['access point', 'wifi', 'wi-fi', 'uap', 'u6-', 'u7-', 'wap'],
        'Printer' => ['printer', 'scanner', 'mfp', 'laserjet', 'officejet', 'copier'],
        'Display' => ['monitor', 'display', 'screen', 'tv'],
        'Camera' => ['camera', 'nvr', 'cctv', 'webcam', 'protect'],
        'Tablet' => ['tablet', 'ipad', 'surface pro', 'galaxy tab'],
        'Mobile Phone' => ['mobile', 'iphone', 'android', 'smartphone', 'cell'],
        'Phone' => ['phone', 'voip', 'yealink', 'polycom', 'grandstream', 'sip'],
        'Virtual Machine' => ['virtual machine', 'vm ']
    ];

    foreach ($map as $asset_type => $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($name, $kw) !== false) {
                return $asset_type;
            }
        }
    }

    return 'Other';
}

function fulfillTicketProductsOnInvoice($mysqli, $ticket_id, $invoice_id, $selected_ticket_product_ids = null) {
    global $asset_types_array, $session_name;

    $ticket_id = intval($ticket_id);
    $invoice_id = intval($invoice_id);
    if ($ticket_id <= 0 || $invoice_id <= 0) {
        return;
    }

    $where_ids = '';
    if (is_array($selected_ticket_product_ids)) {
        $clean_ids = array_values(array_filter(array_map('intval', $selected_ticket_product_ids), function ($v) {
            return $v > 0;
        }));
        if (empty($clean_ids)) {
            return;
        }
        $where_ids = ' AND ticket_product_id IN (' . implode(',', $clean_ids) . ')';
    }

    $ticket_sql = mysqli_query($mysqli, "SELECT ticket_client_id, ticket_location_id, ticket_contact_id, ticket_asset_id, ticket_prefix, ticket_number FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
    $ticket_row = $ticket_sql ? mysqli_fetch_assoc($ticket_sql) : null;
    if (!$ticket_row) {
        return;
    }

    $client_id = intval($ticket_row['ticket_client_id']);
    $location_id = intval($ticket_row['ticket_location_id']);
    $contact_id = intval($ticket_row['ticket_contact_id']);
    $ticket_asset_id = intval($ticket_row['ticket_asset_id']);
    $ticket_prefix = (string) ($ticket_row['ticket_prefix'] ?? '');
    $ticket_number = intval($ticket_row['ticket_number']);

    $invoice_sql = mysqli_query($mysqli, "SELECT invoice_prefix, invoice_number, invoice_date FROM invoices WHERE invoice_id = $invoice_id LIMIT 1");
    $invoice_row = $invoice_sql ? mysqli_fetch_assoc($invoice_sql) : null;
    $invoice_prefix = (string) ($invoice_row['invoice_prefix'] ?? '');
    $invoice_number = intval($invoice_row['invoice_number'] ?? 0);
    $invoice_date = !empty($invoice_row['invoice_date']) ? $invoice_row['invoice_date'] : date('Y-m-d');
    $invoice_date_esc = escapeSql($invoice_date);

    $tp_sql = mysqli_query($mysqli, "SELECT * FROM ticket_products WHERE ticket_product_ticket_id = $ticket_id AND ticket_product_invoiced = 0 $where_ids ORDER BY ticket_product_id ASC");
    if (!$tp_sql) {
        return;
    }

    while ($tp_row = mysqli_fetch_assoc($tp_sql)) {
        $tp_id = intval($tp_row['ticket_product_id']);
        $prod_id = intval($tp_row['ticket_product_product_id']);
        $inv_item_id = intval($tp_row['ticket_product_inventory_item_id']);
        $qty = floatval($tp_row['ticket_product_qty']);

        mysqli_query($mysqli, "UPDATE ticket_products SET ticket_product_invoiced = 1 WHERE ticket_product_id = $tp_id");

        if ($inv_item_id > 0) {
            $inv_sql = mysqli_query($mysqli, "
                SELECT ii.item_serial, ii.item_mac, ii.item_notes, p.product_name, p.product_code, p.product_description
                FROM inventory_items ii
                LEFT JOIN products p ON ii.item_product_id = p.product_id
                WHERE ii.item_id = $inv_item_id
                LIMIT 1
            ");
            $inv_row = $inv_sql ? mysqli_fetch_assoc($inv_sql) : null;

            mysqli_query($mysqli, "UPDATE inventory_items SET item_status = 'Sold', item_client_id = $client_id, item_invoice_id = $invoice_id WHERE item_id = $inv_item_id");

            if ($client_id > 0 && $inv_row) {
                $serial = trim((string) ($inv_row['item_serial'] ?? ''));
                $serial_esc = escapeSql($serial);
                $new_asset_id = 0;

                if ($serial !== '') {
                    $existing_sql = mysqli_query($mysqli, "SELECT asset_id FROM assets WHERE asset_client_id = $client_id AND asset_serial = '$serial_esc' AND asset_serial != '' AND asset_archived_at IS NULL LIMIT 1");
                    if ($existing_sql && mysqli_num_rows($existing_sql) > 0) {
                        $existing_row = mysqli_fetch_assoc($existing_sql);
                        $new_asset_id = intval($existing_row['asset_id']);
                    }
                }

                if ($new_asset_id === 0) {
                    $product_name = trim((string) ($inv_row['product_name'] ?: $tp_row['ticket_product_name']));
                    $product_code = trim((string) ($inv_row['product_code'] ?? ''));
                    $product_description = trim((string) ($inv_row['product_description'] ?? ''));
                    $mac = trim((string) ($inv_row['item_mac'] ?? ''));
                    $item_notes = trim((string) ($inv_row['item_notes'] ?? ''));

                    $tp_asset_type = trim((string) ($tp_row['ticket_product_asset_type'] ?? ''));
                    if ($tp_asset_type !== '' && isset($asset_types_array) && array_key_exists($tp_asset_type, $asset_types_array)) {
                        $asset_type = $tp_asset_type;
                    } else {
                        $asset_type = inferAssetTypeFromProductName($product_name);
                    }

                    $asset_name = substr($product_name . ($serial !== '' ? " ($serial)" : ''), 0, 200);
                    $name_parts = preg_split('/\s+/', $product_name);
                    $asset_make = substr($name_parts[0] ?? $product_name, 0, 200);
                    $asset_model = substr($product_code, 0, 200);
                    $asset_desc = substr($product_description, 0, 255);
                    $purch_ref = substr("Invoice $invoice_prefix$invoice_number (Ticket $ticket_prefix$ticket_number)", 0, 200);

                    $asset_name_esc = escapeSql($asset_name);
                    $asset_desc_esc = escapeSql($asset_desc);
                    $asset_type_esc = escapeSql($asset_type);
                    $asset_make_esc = escapeSql($asset_make);
                    $asset_model_esc = escapeSql($asset_model);
                    $purch_ref_esc = escapeSql($purch_ref);
                    $notes_esc = escapeSql($item_notes);
                    $mac_esc = escapeSql($mac);

                    mysqli_query($mysqli, "INSERT INTO assets SET
                        asset_name = '$asset_name_esc',
                        asset_description = '$asset_desc_esc',
                        asset_type = '$asset_type_esc',
                        asset_make = '$asset_make_esc',
                        asset_model = '$asset_model_esc',
                        asset_serial = '$serial_esc',
                        asset_status = 'Deployed',
                        asset_purchase_reference = '$purch_ref_esc',
                        asset_purchase_date = '$invoice_date_esc',
                        asset_install_date = CURDATE(),
                        asset_location_id = $location_id,
                        asset_contact_id = $contact_id,
                        asset_client_id = $client_id,
                        asset_notes = '$notes_esc'");

                    $new_asset_id = mysqli_insert_id($mysqli);

                    if ($new_asset_id > 0) {
                        mysqli_query($mysqli, "INSERT INTO asset_interfaces SET interface_name = '01', interface_mac = '$mac_esc', interface_primary = 1, interface_asset_id = $new_asset_id");
                        $hist_desc = escapeSql("Created from Ticket $ticket_prefix$ticket_number on Invoice $invoice_prefix$invoice_number");
                        mysqli_query($mysqli, "INSERT INTO asset_history SET asset_history_status = 'Deployed', asset_history_description = '$hist_desc', asset_history_asset_id = $new_asset_id");
                        if (function_exists('logAudit')) {
                            $actor = !empty($session_name) ? $session_name : 'System';
                            logAudit("Asset", "Create", "$actor created asset $asset_name_esc from Ticket $ticket_prefix$ticket_number on Invoice $invoice_prefix$invoice_number", $client_id, $new_asset_id);
                        }
                    }
                }

                if ($new_asset_id > 0) {
                    mysqli_query($mysqli, "UPDATE ticket_products SET ticket_product_asset_id = $new_asset_id WHERE ticket_product_id = $tp_id");
                    if ($ticket_asset_id === 0) {
                        mysqli_query($mysqli, "UPDATE tickets SET ticket_asset_id = $new_asset_id WHERE ticket_id = $ticket_id");
                        $ticket_asset_id = $new_asset_id;
                    } elseif ($ticket_asset_id !== $new_asset_id) {
                        $check_ta = mysqli_query($mysqli, "SELECT 1 FROM ticket_assets WHERE ticket_id = $ticket_id AND asset_id = $new_asset_id LIMIT 1");
                        if ($check_ta && mysqli_num_rows($check_ta) === 0) {
                            mysqli_query($mysqli, "INSERT INTO ticket_assets SET ticket_id = $ticket_id, asset_id = $new_asset_id");
                        }
                    }
                }
            }
        } elseif ($prod_id > 0) {
            $p_sql = mysqli_query($mysqli, "SELECT product_type, product_tracking_type FROM products WHERE product_id = $prod_id LIMIT 1");
            $p_row = $p_sql ? mysqli_fetch_assoc($p_sql) : null;
            if ($p_row && ($p_row['product_type'] ?? '') === 'product' && ($p_row['product_tracking_type'] ?? '') === 'bulk' && $qty > 0) {
                $stock_note = escapeSql("Ticket $ticket_prefix$ticket_number - Invoice $invoice_prefix$invoice_number");
                $deduct_qty = -$qty;
                mysqli_query($mysqli, "INSERT INTO product_stock SET stock_qty = $deduct_qty, stock_note = '$stock_note', stock_product_id = $prod_id");
            }
        }
    }
}
