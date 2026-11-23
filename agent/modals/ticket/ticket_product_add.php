<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ticket_id = intval($_GET['ticket_id'] ?? $_GET['id'] ?? 0);

$ticket_sql = mysqli_query(
    $mysqli,
    "SELECT t.ticket_id, t.ticket_client_id, t.ticket_prefix, t.ticket_number, c.client_name
    FROM tickets t
    LEFT JOIN clients c ON t.ticket_client_id = c.client_id
    WHERE t.ticket_id = $ticket_id
    " . clientScopeSql('t.ticket_client_id') . "
    LIMIT 1"
);

$ticket_row = $ticket_sql ? mysqli_fetch_assoc($ticket_sql) : null;
if (!$ticket_row) {
    echo json_encode(['error' => 'Ticket not found or access denied.']);
    exit;
}

$client_id = intval($ticket_row['ticket_client_id']);
if ($client_id) {
    enforceClientAccess();
}

$ticket_prefix = escapeHtml($ticket_row['ticket_prefix']);
$ticket_number = intval($ticket_row['ticket_number']);
$client_name = escapeHtml($ticket_row['client_name'] ?: 'No Client');

$serialized_units = [];
$inv_sql = mysqli_query($mysqli, "
    SELECT ii.item_id, ii.item_product_id, ii.item_serial, ii.item_mac, ii.item_location, ii.item_price,
           p.product_name, p.product_code, p.product_description, p.product_price, p.product_tax_id
    FROM inventory_items ii
    JOIN products p ON ii.item_product_id = p.product_id
    WHERE ii.item_status = 'In Stock' AND ii.item_archived_at IS NULL AND p.product_archived_at IS NULL
    ORDER BY p.product_name ASC, ii.item_serial ASC
");
if ($inv_sql) {
    while ($r = mysqli_fetch_assoc($inv_sql)) {
        $unit_price = floatval($r['item_price']) > 0 ? floatval($r['item_price']) : floatval($r['product_price']);
        $desc_parts = [];
        if (!empty($r['product_description'])) {
            $desc_parts[] = trim($r['product_description']);
        }
        $sn_line = 'Serial Number: ' . $r['item_serial'];
        if (!empty($r['item_mac'])) {
            $sn_line .= ' (MAC: ' . $r['item_mac'] . ')';
        }
        $desc_parts[] = $sn_line;
        $serialized_units[] = [
            'item_id' => intval($r['item_id']),
            'product_id' => intval($r['item_product_id']),
            'name' => $r['product_name'],
            'code' => $r['product_code'] ?? '',
            'serial' => $r['item_serial'],
            'mac' => $r['item_mac'] ?? '',
            'location' => $r['item_location'] ?? '',
            'description' => implode("\n", $desc_parts),
            'price' => number_format($unit_price, 2, '.', ''),
            'tax_id' => intval($r['product_tax_id']),
            'asset_type' => inferAssetTypeFromProductName($r['product_name'])
        ];
    }
}

$catalog_products = [];
$prod_sql = mysqli_query($mysqli, "
    SELECT p.product_id, p.product_name, p.product_code, p.product_type, p.product_tracking_type,
           p.product_description, p.product_price, p.product_tax_id, c.category_name,
           COALESCE(s.bulk_stock, 0) AS bulk_stock,
           COALESCE(i.serialized_stock, 0) AS serialized_stock
    FROM products p
    LEFT JOIN categories c ON p.product_category_id = c.category_id
    LEFT JOIN (
        SELECT stock_product_id, SUM(stock_qty) AS bulk_stock
        FROM product_stock
        GROUP BY stock_product_id
    ) s ON p.product_id = s.stock_product_id
    LEFT JOIN (
        SELECT item_product_id, COUNT(*) AS serialized_stock
        FROM inventory_items
        WHERE item_status = 'In Stock' AND item_archived_at IS NULL
        GROUP BY item_product_id
    ) i ON p.product_id = i.item_product_id
    WHERE p.product_archived_at IS NULL
    ORDER BY p.product_name ASC
");
if ($prod_sql) {
    while ($r = mysqli_fetch_assoc($prod_sql)) {
        $stock_val = null;
        if ($r['product_type'] === 'product') {
            $stock_val = $r['product_tracking_type'] === 'serialized'
                ? intval($r['serialized_stock'])
                : intval($r['bulk_stock']);
        }
        $catalog_products[] = [
            'product_id' => intval($r['product_id']),
            'name' => $r['product_name'],
            'code' => $r['product_code'] ?? '',
            'type' => $r['product_type'],
            'tracking_type' => $r['product_tracking_type'] ?? 'serialized',
            'category' => $r['category_name'] ?? '',
            'description' => $r['product_description'] ?? '',
            'price' => number_format(floatval($r['product_price']), 2, '.', ''),
            'tax_id' => intval($r['product_tax_id']),
            'stock' => $stock_val
        ];
    }
}

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
    <h5 class="modal-title"><i class="fas fa-fw fa-box-open mr-2"></i>Add Product / Charge to <?= $ticket_prefix . $ticket_number ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
    <input type="hidden" name="product_id" id="tp_product_id" value="0">
    <input type="hidden" name="inventory_item_id" id="tp_inventory_item_id" value="0">

    <div class="modal-body">
        <div class="card card-outline card-secondary mb-3">
            <div class="card-header py-2">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <ul class="nav nav-pills nav-sm" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active py-1 px-2" data-toggle="pill" href="#tp-tab-serialized" role="tab">
                                <i class="fas fa-barcode mr-1"></i>In-Stock Serialized (<?= count($serialized_units) ?>)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-2" data-toggle="pill" href="#tp-tab-catalog" role="tab">
                                <i class="fas fa-boxes mr-1"></i>Products &amp; Catalog (<?= count($catalog_products) ?>)
                            </a>
                        </li>
                    </ul>
                    <div class="mt-2 mt-sm-0" style="min-width: 240px;">
                        <input type="text" class="form-control form-control-sm" id="tp_picker_search" placeholder="Search product, SKU, serial, MAC...">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tp-tab-serialized" role="tabpanel">
                        <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Serial / MAC</th>
                                        <th>Location</th>
                                        <th class="text-right">Price</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($serialized_units)) { ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No serialized items currently in stock.</td></tr>
                                    <?php } else { ?>
                                        <?php foreach ($serialized_units as $u) { ?>
                                            <tr class="tp-picker-row" data-search="<?= escapeHtml(strtolower($u['name'] . ' ' . $u['code'] . ' ' . $u['serial'] . ' ' . $u['mac'] . ' ' . $u['location'])) ?>">
                                                <td class="align-middle font-weight-bold"><?= escapeHtml($u['name']) ?></td>
                                                <td class="align-middle">
                                                    <span class="badge badge-info">S/N: <?= escapeHtml($u['serial']) ?></span>
                                                    <?php if ($u['mac'] !== '') { ?>
                                                        <small class="text-muted d-block">MAC: <?= escapeHtml($u['mac']) ?></small>
                                                    <?php } ?>
                                                </td>
                                                <td class="align-middle text-muted small"><?= escapeHtml($u['location'] ?: '-') ?></td>
                                                <td class="align-middle text-right font-weight-bold"><?= numfmt_format_currency($currency_format, floatval($u['price']), $session_company_currency) ?></td>
                                                <td class="align-middle text-center">
                                                    <button type="button" class="btn btn-xs btn-primary tp-select-unit"
                                                        data-product-id="<?= $u['product_id'] ?>"
                                                        data-item-id="<?= $u['item_id'] ?>"
                                                        data-name="<?= escapeHtml($u['name']) ?>"
                                                        data-serial="<?= escapeHtml($u['serial']) ?>"
                                                        data-description="<?= escapeHtml($u['description']) ?>"
                                                        data-price="<?= escapeHtml($u['price']) ?>"
                                                        data-tax-id="<?= $u['tax_id'] ?>"
                                                        data-asset-type="<?= escapeHtml($u['asset_type']) ?>">
                                                        <i class="fas fa-check mr-1"></i>Select
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tp-tab-catalog" role="tabpanel">
                        <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Product / SKU</th>
                                        <th>Type</th>
                                        <th>Stock</th>
                                        <th class="text-right">Price</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($catalog_products)) { ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No catalog products found.</td></tr>
                                    <?php } else { ?>
                                        <?php foreach ($catalog_products as $p) { ?>
                                            <tr class="tp-picker-row" data-search="<?= escapeHtml(strtolower($p['name'] . ' ' . $p['code'] . ' ' . $p['category'] . ' ' . $p['description'])) ?>">
                                                <td class="align-middle">
                                                    <div class="font-weight-bold"><?= escapeHtml($p['name']) ?></div>
                                                    <?php if ($p['code'] !== '') { ?>
                                                        <small class="text-muted"><?= escapeHtml($p['code']) ?></small>
                                                    <?php } ?>
                                                </td>
                                                <td class="align-middle">
                                                    <?php if ($p['type'] === 'service') { ?>
                                                        <span class="badge badge-secondary">Service</span>
                                                    <?php } elseif ($p['tracking_type'] === 'serialized') { ?>
                                                        <span class="badge badge-info">Serialized</span>
                                                    <?php } else { ?>
                                                        <span class="badge badge-warning text-dark">Bulk</span>
                                                    <?php } ?>
                                                </td>
                                                <td class="align-middle">
                                                    <?php if ($p['stock'] === null) { ?>
                                                        <span class="text-muted">-</span>
                                                    <?php } elseif ($p['stock'] > 0) { ?>
                                                        <span class="badge badge-success"><?= $p['stock'] ?> in stock</span>
                                                    <?php } else { ?>
                                                        <span class="badge badge-light border text-muted">0</span>
                                                    <?php } ?>
                                                </td>
                                                <td class="align-middle text-right font-weight-bold"><?= numfmt_format_currency($currency_format, floatval($p['price']), $session_company_currency) ?></td>
                                                <td class="align-middle text-center">
                                                    <button type="button" class="btn btn-xs btn-primary tp-select-catalog"
                                                        data-product-id="<?= $p['product_id'] ?>"
                                                        data-name="<?= escapeHtml($p['name']) ?>"
                                                        data-description="<?= escapeHtml($p['description']) ?>"
                                                        data-price="<?= escapeHtml($p['price']) ?>"
                                                        data-tax-id="<?= $p['tax_id'] ?>">
                                                        <i class="fas fa-check mr-1"></i>Select
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="tp_selected_banner" class="alert alert-info py-2 px-3 mb-3 d-none">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-link mr-1"></i>
                    <strong id="tp_selected_label"></strong>
                </div>
                <button type="button" class="btn btn-xs btn-outline-dark" id="tp_clear_selection">
                    <i class="fas fa-times mr-1"></i>Clear / Custom Write-In
                </button>
            </div>
        </div>

        <div class="form-group">
            <label>Item / Product Name <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="name" id="tp_name" placeholder="e.g. UniFi Switch 24 PoE or custom charge" maxlength="200" required>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" rows="3" name="description" id="tp_description" placeholder="Optional product details, serial number, notes..."></textarea>
        </div>

        <div class="form-row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>QTY <strong class="text-danger">*</strong></label>
                    <input type="number" step="0.01" min="0.01" class="form-control" name="qty" id="tp_qty" value="1" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Price <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                        </div>
                        <input type="number" step="0.01" class="form-control" name="price" id="tp_price" value="0.00" required>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Tax</label>
                    <select class="form-control" name="tax_id" id="tp_tax_id">
                        <option value="0">None</option>
                        <?php foreach ($taxes_list as $tax) { ?>
                            <option value="<?= intval($tax['tax_id']) ?>"><?= escapeHtml($tax['tax_name']) . ' ' . floatval($tax['tax_percent']) . '%' ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>

        <div id="tp_asset_type_group" class="form-group d-none mb-0">
            <label>Client Asset Type (upon invoicing)</label>
            <select class="form-control" name="asset_type" id="tp_asset_type">
                <?php foreach (array_keys($asset_types_array) as $at) { ?>
                    <option value="<?= escapeHtml($at) ?>"><?= escapeHtml($at) ?></option>
                <?php } ?>
            </select>
            <small class="form-text text-muted">
                This serialized unit will be reserved immediately and linked to <strong><?= $client_name ?></strong>'s Assets only when this ticket is invoiced.
            </small>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="add_ticket_product" class="btn btn-primary text-bold">
            <i class="fa fa-check mr-2"></i>Add to Ticket
        </button>
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fa fa-times mr-2"></i>Cancel
        </button>
    </div>
</form>

<script>
(function () {
    $('#tp_picker_search').on('input', function () {
        var term = $(this).val().toLowerCase().trim();
        $('.tp-picker-row').each(function () {
            var hay = ($(this).data('search') || '').toString();
            $(this).toggle(term === '' || hay.indexOf(term) !== -1);
        });
    });

    function clearSelection() {
        $('#tp_product_id').val(0);
        $('#tp_inventory_item_id').val(0);
        $('#tp_qty').prop('readonly', false);
        $('#tp_asset_type_group').addClass('d-none');
        $('#tp_selected_banner').addClass('d-none');
    }

    $('.tp-select-unit').on('click', function () {
        var $btn = $(this);
        $('#tp_product_id').val($btn.data('product-id'));
        $('#tp_inventory_item_id').val($btn.data('item-id'));
        $('#tp_name').val($btn.data('name'));
        $('#tp_description').val($btn.data('description'));
        $('#tp_qty').val(1).prop('readonly', true);
        $('#tp_price').val($btn.data('price'));
        $('#tp_tax_id').val($btn.data('tax-id') || 0);
        $('#tp_asset_type').val($btn.data('asset-type') || 'Other');
        $('#tp_asset_type_group').removeClass('d-none');
        $('#tp_selected_label').text('Serialized Unit: ' + $btn.data('name') + ' (S/N: ' + $btn.data('serial') + ')');
        $('#tp_selected_banner').removeClass('d-none');
    });

    $('.tp-select-catalog').on('click', function () {
        var $btn = $(this);
        $('#tp_product_id').val($btn.data('product-id'));
        $('#tp_inventory_item_id').val(0);
        $('#tp_name').val($btn.data('name'));
        $('#tp_description').val($btn.data('description'));
        $('#tp_qty').val(1).prop('readonly', false);
        $('#tp_price').val($btn.data('price'));
        $('#tp_tax_id').val($btn.data('tax-id') || 0);
        $('#tp_asset_type_group').addClass('d-none');
        $('#tp_selected_label').text('Catalog Product: ' + $btn.data('name'));
        $('#tp_selected_banner').removeClass('d-none');
    });

    $('#tp_clear_selection').on('click', function () {
        clearSelection();
        $('#tp_name').val('').focus();
        $('#tp_description').val('');
        $('#tp_qty').val(1);
        $('#tp_price').val('0.00');
        $('#tp_tax_id').val(0);
    });
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';

