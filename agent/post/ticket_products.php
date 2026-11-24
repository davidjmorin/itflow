<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_ticket_product'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $product_id = intval($_POST['product_id'] ?? 0);
    $inventory_item_id = intval($_POST['inventory_item_id'] ?? 0);
    $name_raw = trim((string) ($_POST['name'] ?? ''));
    $name = escapeSql($name_raw);
    $description = escapeSql(trim((string) ($_POST['description'] ?? '')));
    $qty = floatval($_POST['qty'] ?? 1);
    $price = floatval($_POST['price'] ?? 0);
    $tax_id = intval($_POST['tax_id'] ?? 0);
    $raw_asset_type = trim((string) ($_POST['asset_type'] ?? ''));

    $ticket_sql = mysqli_query($mysqli, "SELECT ticket_client_id, ticket_prefix, ticket_number FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
    $ticket_row = $ticket_sql ? mysqli_fetch_assoc($ticket_sql) : null;
    if (!$ticket_row) {
        flashAlert("Ticket not found", 'error');
        redirect();
    }

    $client_id = intval($ticket_row['ticket_client_id']);
    if ($client_id) {
        enforceClientAccess();
    }

    if ($name_raw === '' || $qty <= 0) {
        flashAlert("Product name and quantity greater than 0 are required", 'error');
        redirect();
    }

    $asset_type = 'Other';
    if ($inventory_item_id > 0) {
        $qty = 1.00;
        $inv_check = mysqli_query($mysqli, "SELECT item_id, item_product_id, item_status FROM inventory_items WHERE item_id = $inventory_item_id AND item_archived_at IS NULL LIMIT 1");
        $inv_row = $inv_check ? mysqli_fetch_assoc($inv_check) : null;
        if (!$inv_row || $inv_row['item_status'] !== 'In Stock') {
            flashAlert("Selected serialized item is no longer in stock", 'error');
            redirect();
        }
        if ($product_id === 0) {
            $product_id = intval($inv_row['item_product_id']);
        }
        mysqli_query($mysqli, "UPDATE inventory_items SET item_status = 'Reserved', item_client_id = $client_id WHERE item_id = $inventory_item_id AND item_status = 'In Stock'");
        if (mysqli_affected_rows($mysqli) !== 1) {
            flashAlert("Could not reserve serialized inventory item", 'error');
            redirect();
        }
        $asset_type = ($raw_asset_type !== '' && isset($asset_types_array) && array_key_exists($raw_asset_type, $asset_types_array))
            ? $raw_asset_type
            : inferAssetTypeFromProductName($name_raw);
    }

    $subtotal = round($qty * $price, 2);
    $tax_amount = 0.00;
    if ($tax_id > 0) {
        $tax_sql = mysqli_query($mysqli, "SELECT tax_percent FROM taxes WHERE tax_id = $tax_id LIMIT 1");
        if ($tax_sql && $tax_row = mysqli_fetch_assoc($tax_sql)) {
            $tax_amount = round($subtotal * floatval($tax_row['tax_percent']) / 100, 2);
        }
    }
    $total = round($subtotal + $tax_amount, 2);
    $asset_type_esc = escapeSql($asset_type);

    mysqli_query($mysqli, "INSERT INTO ticket_products SET
        ticket_product_ticket_id = $ticket_id,
        ticket_product_product_id = $product_id,
        ticket_product_inventory_item_id = $inventory_item_id,
        ticket_product_name = '$name',
        ticket_product_description = '$description',
        ticket_product_qty = $qty,
        ticket_product_price = $price,
        ticket_product_tax_id = $tax_id,
        ticket_product_tax = $tax_amount,
        ticket_product_subtotal = $subtotal,
        ticket_product_total = $total,
        ticket_product_asset_type = '$asset_type_esc',
        ticket_product_added_by = $session_user_id");

    mysqli_query($mysqli, "UPDATE tickets SET ticket_billable = 1 WHERE ticket_id = $ticket_id");

    logTicketHistory($ticket_id, "$session_name added product $name");
    logAudit("Ticket", "Edit", "$session_name added product $name to ticket {$ticket_row['ticket_prefix']}{$ticket_row['ticket_number']}", $client_id, $ticket_id);

    flashAlert("Product <strong>" . escapeHtml($name_raw) . "</strong> added to ticket");
    redirect();

}

if (isset($_POST['edit_ticket_product'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $ticket_product_id = intval($_POST['ticket_product_id'] ?? 0);
    $name_raw = trim((string) ($_POST['name'] ?? ''));
    $name = escapeSql($name_raw);
    $description = escapeSql(trim((string) ($_POST['description'] ?? '')));
    $qty = floatval($_POST['qty'] ?? 1);
    $price = floatval($_POST['price'] ?? 0);
    $tax_id = intval($_POST['tax_id'] ?? 0);
    $raw_asset_type = trim((string) ($_POST['asset_type'] ?? ''));

    $tp_sql = mysqli_query($mysqli, "
        SELECT tp.*, t.ticket_client_id, t.ticket_prefix, t.ticket_number
        FROM ticket_products tp
        JOIN tickets t ON tp.ticket_product_ticket_id = t.ticket_id
        WHERE tp.ticket_product_id = $ticket_product_id
        LIMIT 1
    ");
    $tp_row = $tp_sql ? mysqli_fetch_assoc($tp_sql) : null;
    if (!$tp_row) {
        flashAlert("Ticket product not found", 'error');
        redirect();
    }

    $ticket_id = intval($tp_row['ticket_product_ticket_id']);
    $client_id = intval($tp_row['ticket_client_id']);
    if ($client_id) {
        enforceClientAccess();
    }

    if (intval($tp_row['ticket_product_invoiced']) === 1) {
        flashAlert("Invoiced products cannot be modified", 'error');
        redirect();
    }

    if ($name_raw === '' || $qty <= 0) {
        flashAlert("Product name and quantity greater than 0 are required", 'error');
        redirect();
    }

    $asset_type = $tp_row['ticket_product_asset_type'] ?: 'Other';
    if (intval($tp_row['ticket_product_inventory_item_id']) > 0) {
        $qty = 1.00;
        if ($raw_asset_type !== '' && isset($asset_types_array) && array_key_exists($raw_asset_type, $asset_types_array)) {
            $asset_type = $raw_asset_type;
        }
    }

    $subtotal = round($qty * $price, 2);
    $tax_amount = 0.00;
    if ($tax_id > 0) {
        $tax_sql = mysqli_query($mysqli, "SELECT tax_percent FROM taxes WHERE tax_id = $tax_id LIMIT 1");
        if ($tax_sql && $tax_row = mysqli_fetch_assoc($tax_sql)) {
            $tax_amount = round($subtotal * floatval($tax_row['tax_percent']) / 100, 2);
        }
    }
    $total = round($subtotal + $tax_amount, 2);
    $asset_type_esc = escapeSql($asset_type);

    mysqli_query($mysqli, "UPDATE ticket_products SET
        ticket_product_name = '$name',
        ticket_product_description = '$description',
        ticket_product_qty = $qty,
        ticket_product_price = $price,
        ticket_product_tax_id = $tax_id,
        ticket_product_tax = $tax_amount,
        ticket_product_subtotal = $subtotal,
        ticket_product_total = $total,
        ticket_product_asset_type = '$asset_type_esc'
        WHERE ticket_product_id = $ticket_product_id");

    logTicketHistory($ticket_id, "$session_name updated product $name");
    logAudit("Ticket", "Edit", "$session_name updated product $name on ticket {$tp_row['ticket_prefix']}{$tp_row['ticket_number']}", $client_id, $ticket_id);

    flashAlert("Product <strong>" . escapeHtml($name_raw) . "</strong> updated");
    redirect();

}

if (isset($_GET['delete_ticket_product'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $ticket_product_id = intval($_GET['delete_ticket_product'] ?? 0);

    $tp_sql = mysqli_query($mysqli, "
        SELECT tp.*, t.ticket_client_id, t.ticket_prefix, t.ticket_number
        FROM ticket_products tp
        JOIN tickets t ON tp.ticket_product_ticket_id = t.ticket_id
        WHERE tp.ticket_product_id = $ticket_product_id
        LIMIT 1
    ");
    $tp_row = $tp_sql ? mysqli_fetch_assoc($tp_sql) : null;
    if (!$tp_row) {
        flashAlert("Ticket product not found", 'error');
        redirect();
    }

    $ticket_id = intval($tp_row['ticket_product_ticket_id']);
    $client_id = intval($tp_row['ticket_client_id']);
    if ($client_id) {
        enforceClientAccess();
    }

    if (intval($tp_row['ticket_product_invoiced']) === 1) {
        flashAlert("Invoiced products cannot be removed from the ticket", 'error');
        redirect();
    }

    $inv_item_id = intval($tp_row['ticket_product_inventory_item_id']);
    if ($inv_item_id > 0) {
        mysqli_query($mysqli, "UPDATE inventory_items SET item_status = 'In Stock', item_client_id = 0 WHERE item_id = $inv_item_id AND item_status = 'Reserved'");
    }

    mysqli_query($mysqli, "DELETE FROM ticket_products WHERE ticket_product_id = $ticket_product_id");

    $prod_name_esc = escapeSql($tp_row['ticket_product_name']);
    logTicketHistory($ticket_id, "$session_name removed product $prod_name_esc");
    logAudit("Ticket", "Edit", "$session_name removed product $prod_name_esc from ticket {$tp_row['ticket_prefix']}{$tp_row['ticket_number']}", $client_id, $ticket_id);

    flashAlert("Product removed from ticket", 'error');
    redirect();

}
