<?php

function ticketProductsParseSelectedIds($raw_ids): array
{
    if (!is_array($raw_ids)) {
        return [];
    }
    $ids = [];
    foreach ($raw_ids as $raw) {
        $id = intval($raw);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function ticketProductsAttachToInvoice(mysqli $mysqli, int $ticket_id, int $invoice_id, array $selected_tp_ids): int
{
    $ids = ticketProductsParseSelectedIds($selected_tp_ids);
    if ($ticket_id <= 0 || $invoice_id <= 0 || empty($ids)) {
        return 0;
    }

    $max_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(MAX(item_order), 0) AS max_order FROM invoice_items WHERE item_invoice_id = $invoice_id"));
    $order_num = intval($max_row['max_order'] ?? 0) + 1;

    $ids_sql = implode(',', $ids);
    $tp_q = mysqli_query($mysqli, "SELECT * FROM ticket_products WHERE ticket_product_ticket_id = $ticket_id AND ticket_product_invoiced = 0 AND ticket_product_id IN ($ids_sql) ORDER BY ticket_product_id ASC");
    $attached = 0;
    $attached_ids = [];
    if ($tp_q) {
        while ($tp = mysqli_fetch_assoc($tp_q)) {
            $tp_name = escapeSql($tp['ticket_product_name']);
            $tp_desc = escapeSql($tp['ticket_product_description'] ?? '');
            $tp_qty = floatval($tp['ticket_product_qty']);
            $tp_price = floatval($tp['ticket_product_price']);
            $tp_sub = floatval($tp['ticket_product_subtotal']);
            $tp_tax = floatval($tp['ticket_product_tax']);
            $tp_tot = floatval($tp['ticket_product_total']);
            $tp_tax_id = intval($tp['ticket_product_tax_id']);
            $tp_prod_id = intval($tp['ticket_product_product_id']);

            mysqli_query($mysqli, "INSERT INTO invoice_items SET item_name = '$tp_name', item_description = '$tp_desc', item_quantity = $tp_qty, item_price = $tp_price, item_subtotal = $tp_sub, item_tax = $tp_tax, item_total = $tp_tot, item_order = $order_num, item_tax_id = $tp_tax_id, item_product_id = $tp_prod_id, item_invoice_id = $invoice_id");
            $order_num++;
            $attached++;
            $attached_ids[] = intval($tp['ticket_product_id']);
        }
    }

    if ($attached > 0) {
        if (function_exists('billingRecalc')) {
            billingRecalc('invoice', $invoice_id);
        }
        if (function_exists('fulfillTicketProductsOnInvoice')) {
            fulfillTicketProductsOnInvoice($mysqli, $ticket_id, $invoice_id, $attached_ids);
        }
    }

    return $attached;
}

function ticketProductsInvoiceOnly(mysqli $mysqli, int $ticket_id, array $selected_tp_ids, array $opts, ?string &$error = null): ?array
{
    global $session_company_currency, $session_user_id, $session_name, $config_invoice_prefix, $config_default_net_terms;

    $ids = ticketProductsParseSelectedIds($selected_tp_ids);
    if ($ticket_id <= 0 || empty($ids)) {
        $error = 'Please include support labor or select at least one ticket product to invoice.';
        return null;
    }

    $t_sql = mysqli_query($mysqli, "
        SELECT t.ticket_id, t.ticket_client_id, t.ticket_prefix, t.ticket_number, c.client_net_terms
        FROM tickets t
        LEFT JOIN clients c ON t.ticket_client_id = c.client_id
        WHERE t.ticket_id = $ticket_id
        LIMIT 1
    ");
    $t_row = $t_sql ? mysqli_fetch_assoc($t_sql) : null;
    if (!$t_row || intval($t_row['ticket_client_id']) <= 0) {
        $error = 'Move the ticket to a client before invoicing it.';
        return null;
    }

    $client_id = intval($t_row['ticket_client_id']);
    $client_net_terms = intval($t_row['client_net_terms'] ?: $config_default_net_terms);
    $ticket_prefix = escapeSql($t_row['ticket_prefix']);
    $ticket_number = intval($t_row['ticket_number']);
    $invoice_id = intval($opts['invoice_id'] ?? 0);

    if ($invoice_id > 0) {
        $inv_q = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number FROM invoices WHERE invoice_id = $invoice_id AND invoice_client_id = $client_id AND invoice_status = 'Draft' AND invoice_archived_at IS NULL LIMIT 1");
        $inv_row = $inv_q ? mysqli_fetch_assoc($inv_q) : null;
        if (!$inv_row) {
            $error = 'Selected draft invoice was not found.';
            return null;
        }
        $invoice_prefix = escapeSql($inv_row['invoice_prefix']);
        $invoice_number = intval($inv_row['invoice_number']);
    } else {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($opts['date'] ?? '')) ? $opts['date'] : date('Y-m-d');
        $category = intval($opts['category_id'] ?? 0);
        if ($category <= 0) {
            $error = 'Please select an invoice category.';
            return null;
        }
        $scope = escapeSql(mb_substr(trim((string) ($opts['scope'] ?? '')), 0, 255));
        $invoice_prefix = escapeSql($config_invoice_prefix);

        mysqli_query($mysqli, "
            UPDATE settings
            SET
                config_invoice_next_number = LAST_INSERT_ID(config_invoice_next_number),
                config_invoice_next_number = config_invoice_next_number + 1
            WHERE company_id = 1
        ");
        $invoice_number = mysqli_insert_id($mysqli);
        $url_key = randomString(32);

        mysqli_query($mysqli, "INSERT INTO invoices SET invoice_prefix = '$invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms DAY), invoice_currency_code = '$session_company_currency', invoice_category_id = $category, invoice_status = 'Draft', invoice_url_key = '$url_key', invoice_client_id = $client_id");
        $invoice_id = mysqli_insert_id($mysqli);
    }

    $attached = ticketProductsAttachToInvoice($mysqli, $ticket_id, $invoice_id, $ids);
    if ($attached === 0) {
        $error = 'No uninvoiced ticket products were selected.';
        return null;
    }

    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Draft', history_description = 'Invoice created from Ticket $ticket_prefix$ticket_number', history_invoice_id = $invoice_id");
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Created invoice <a href=\"invoice.php?invoice_id=$invoice_id\">$invoice_prefix$invoice_number</a> for this ticket.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:00:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");
    mysqli_query($mysqli, "UPDATE tickets SET ticket_invoice_id = $invoice_id WHERE ticket_id = $ticket_id LIMIT 1");

    logAudit("Invoice", "Create", "$session_name created invoice $invoice_prefix$invoice_number from Ticket $ticket_prefix$ticket_number", $client_id, $invoice_id);

    return [
        'invoice_id' => $invoice_id,
        'number' => $invoice_prefix . $invoice_number,
        'products_count' => $attached,
    ];
}

function ticketProductsAttachToQuote(mysqli $mysqli, int $ticket_id, int $quote_id, array $selected_tp_ids): int
{
    $ids = ticketProductsParseSelectedIds($selected_tp_ids);
    if ($ticket_id <= 0 || $quote_id <= 0 || empty($ids)) {
        return 0;
    }

    $max_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(MAX(item_order), 0) AS max_order FROM quote_items WHERE item_quote_id = $quote_id"));
    $order_num = intval($max_row['max_order'] ?? 0) + 1;

    $ids_sql = implode(',', $ids);
    $tp_q = mysqli_query($mysqli, "SELECT * FROM ticket_products WHERE ticket_product_ticket_id = $ticket_id AND ticket_product_invoiced = 0 AND ticket_product_id IN ($ids_sql) ORDER BY ticket_product_id ASC");
    $attached = 0;
    if ($tp_q) {
        while ($tp = mysqli_fetch_assoc($tp_q)) {
            $tp_name = escapeSql($tp['ticket_product_name']);
            $tp_desc = escapeSql($tp['ticket_product_description'] ?? '');
            $tp_qty = floatval($tp['ticket_product_qty']);
            $tp_price = floatval($tp['ticket_product_price']);
            $tp_sub = floatval($tp['ticket_product_subtotal']);
            $tp_tax = floatval($tp['ticket_product_tax']);
            $tp_tot = floatval($tp['ticket_product_total']);
            $tp_tax_id = intval($tp['ticket_product_tax_id']);
            $tp_prod_id = intval($tp['ticket_product_product_id']);

            mysqli_query($mysqli, "INSERT INTO quote_items SET item_name = '$tp_name', item_description = '$tp_desc', item_quantity = $tp_qty, item_price = $tp_price, item_subtotal = $tp_sub, item_tax = $tp_tax, item_total = $tp_tot, item_order = $order_num, item_tax_id = $tp_tax_id, item_product_id = $tp_prod_id, item_quote_id = $quote_id");
            $order_num++;
            $attached++;
        }
    }

    if ($attached > 0 && function_exists('billingRecalc')) {
        billingRecalc('quote', $quote_id);
    }

    return $attached;
}
