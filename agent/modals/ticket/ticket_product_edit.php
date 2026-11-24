<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ticket_product_id = intval($_GET['id'] ?? 0);

$sql = mysqli_query(
    $mysqli,
    "SELECT tp.*, t.ticket_client_id, t.ticket_prefix, t.ticket_number, ii.item_serial
    FROM ticket_products tp
    JOIN tickets t ON tp.ticket_product_ticket_id = t.ticket_id
    LEFT JOIN inventory_items ii ON tp.ticket_product_inventory_item_id = ii.item_id
    WHERE tp.ticket_product_id = $ticket_product_id
    " . clientScopeSql('t.ticket_client_id') . "
    LIMIT 1"
);

$row = $sql ? mysqli_fetch_assoc($sql) : null;
if (!$row) {
    echo json_encode(['error' => 'Ticket product not found or access denied.']);
    exit;
}

$client_id = intval($row['ticket_client_id']);
if ($client_id) {
    enforceClientAccess();
}

$is_serialized = intval($row['ticket_product_inventory_item_id']) > 0;
$is_invoiced = intval($row['ticket_product_invoiced']) === 1;

$taxes_list = [];
$taxes_sql = mysqli_query($mysqli, "SELECT tax_id, tax_name, tax_percent FROM taxes WHERE tax_archived_at IS NULL ORDER BY tax_name ASC");
if ($taxes_sql) {
    while ($t = mysqli_fetch_assoc($taxes_sql)) {
        $taxes_list[] = $t;
    }
}

$asset_type_names = function_exists('assetTypes')
    ? array_keys(assetTypes())
    : (is_array($asset_types_array ?? null) ? array_keys($asset_types_array) : ['Other']);
if (empty($asset_type_names)) {
    $asset_type_names = ['Other'];
}

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-edit me-2"></i>Edit Ticket Product</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_product_id" value="<?= $ticket_product_id ?>">

    <div class="modal-body">
        <?php if ($is_serialized) { ?>
            <div class="alert alert-info py-2 px-3 mb-3">
                <i class="fas fa-barcode me-1"></i>
                Linked Serialized Inventory Unit: <strong>S/N <?= escapeHtml($row['item_serial']) ?></strong>
            </div>
        <?php } ?>

        <div class="mb-3">
            <label class="form-label">Item / Product Name <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="name" value="<?= escapeHtml($row['ticket_product_name']) ?>" maxlength="200" required <?= $is_invoiced ? 'readonly' : '' ?>>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" rows="3" name="description" <?= $is_invoiced ? 'readonly' : '' ?>><?= escapeHtml($row['ticket_product_description']) ?></textarea>
        </div>

        <div class="row g-2">
            <div class="col-md-4 mb-3">
                <label class="form-label">QTY <strong class="text-danger">*</strong></label>
                <input type="number" step="0.01" min="0.01" class="form-control" name="qty" value="<?= number_format(floatval($row['ticket_product_qty']), 2, '.', '') ?>" required <?= ($is_serialized || $is_invoiced) ? 'readonly' : '' ?>>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Price <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                    <input type="number" step="0.01" class="form-control" name="price" value="<?= number_format(floatval($row['ticket_product_price']), 2, '.', '') ?>" required <?= $is_invoiced ? 'readonly' : '' ?>>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Tax</label>
                <select class="form-select" name="tax_id" <?= $is_invoiced ? 'disabled' : '' ?>>
                    <option value="0">None</option>
                    <?php foreach ($taxes_list as $tax) { ?>
                        <option value="<?= intval($tax['tax_id']) ?>" <?= intval($row['ticket_product_tax_id']) === intval($tax['tax_id']) ? 'selected' : '' ?>>
                            <?= escapeHtml($tax['tax_name']) . ' ' . floatval($tax['tax_percent']) . '%' ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <?php if ($is_serialized) { ?>
            <div class="mb-0">
                <label class="form-label">Client Asset Type (upon invoicing)</label>
                <select class="form-select" name="asset_type" <?= $is_invoiced ? 'disabled' : '' ?>>
                    <?php foreach ($asset_type_names as $at) { ?>
                        <option value="<?= escapeHtml($at) ?>" <?= ($row['ticket_product_asset_type'] ?? 'Other') === $at ? 'selected' : '' ?>>
                            <?= escapeHtml($at) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        <?php } ?>
    </div>

    <div class="modal-footer">
        <?php if (!$is_invoiced) { ?>
            <button type="submit" name="edit_ticket_product" class="btn btn-primary fw-bold">
                <i class="fa fa-check me-2"></i>Save
            </button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            <i class="fa fa-times me-2"></i>Cancel
        </button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';

