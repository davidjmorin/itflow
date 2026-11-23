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

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-edit mr-2"></i>Edit Ticket Product</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_product_id" value="<?= $ticket_product_id ?>">

    <div class="modal-body">
        <?php if ($is_serialized) { ?>
            <div class="alert alert-info py-2 px-3 mb-3">
                <i class="fas fa-barcode mr-1"></i>
                Linked Serialized Inventory Unit: <strong>S/N <?= escapeHtml($row['item_serial']) ?></strong>
            </div>
        <?php } ?>

        <div class="form-group">
            <label>Item / Product Name <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="name" value="<?= escapeHtml($row['ticket_product_name']) ?>" maxlength="200" required <?= $is_invoiced ? 'readonly' : '' ?>>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" rows="3" name="description" <?= $is_invoiced ? 'readonly' : '' ?>><?= escapeHtml($row['ticket_product_description']) ?></textarea>
        </div>

        <div class="form-row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>QTY <strong class="text-danger">*</strong></label>
                    <input type="number" step="0.01" min="0.01" class="form-control" name="qty" value="<?= number_format(floatval($row['ticket_product_qty']), 2, '.', '') ?>" required <?= ($is_serialized || $is_invoiced) ? 'readonly' : '' ?>>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Price <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                        </div>
                        <input type="number" step="0.01" class="form-control" name="price" value="<?= number_format(floatval($row['ticket_product_price']), 2, '.', '') ?>" required <?= $is_invoiced ? 'readonly' : '' ?>>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Tax</label>
                    <select class="form-control" name="tax_id" <?= $is_invoiced ? 'disabled' : '' ?>>
                        <option value="0">None</option>
                        <?php foreach ($taxes_list as $tax) { ?>
                            <option value="<?= intval($tax['tax_id']) ?>" <?= intval($row['ticket_product_tax_id']) === intval($tax['tax_id']) ? 'selected' : '' ?>>
                                <?= escapeHtml($tax['tax_name']) . ' ' . floatval($tax['tax_percent']) . '%' ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>

        <?php if ($is_serialized) { ?>
            <div class="form-group mb-0">
                <label>Client Asset Type (upon invoicing)</label>
                <select class="form-control" name="asset_type" <?= $is_invoiced ? 'disabled' : '' ?>>
                    <?php foreach (array_keys($asset_types_array) as $at) { ?>
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
            <button type="submit" name="edit_ticket_product" class="btn btn-primary text-bold">
                <i class="fa fa-check mr-2"></i>Save
            </button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fa fa-times mr-2"></i>Cancel
        </button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';

