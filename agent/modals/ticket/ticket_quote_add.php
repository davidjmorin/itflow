<?php

require_once '../../../includes/modal_header.php';

$ticket_id = intval($_GET['ticket_id']);

$ticket_sql = mysqli_query(
    $mysqli,
    "SELECT asset_id, asset_name, asset_type, category_name, client_id, client_rate, contact_id,
        contact_name, ticket_assigned_to, ticket_billable, ticket_category, ticket_created_at,
        ticket_created_by, ticket_first_response_at, ticket_number, ticket_onsite, ticket_prefix,
        ticket_priority, ticket_resolved_at, ticket_subject, user_name FROM tickets
    LEFT JOIN clients ON ticket_client_id = client_id
    LEFT JOIN contacts ON ticket_contact_id = contact_id
    LEFT JOIN users ON ticket_assigned_to = user_id
    LEFT JOIN locations ON ticket_location_id = location_id
    LEFT JOIN assets ON ticket_asset_id = asset_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN categories ON ticket_category = category_id
    WHERE ticket_id = $ticket_id
    " . clientScopeSql('ticket_client_id') . "
    LIMIT 1"
);

$row = mysqli_fetch_assoc($ticket_sql);
$client_id = intval($row['client_id']);
$client_rate = floatval($row['client_rate']);
$ticket_prefix = escapeHtml($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$ticket_category = intval($row['ticket_category']);
$ticket_category_display = escapeHtml($row['category_name']);
$ticket_subject = escapeHtml($row['ticket_subject']);
$ticket_priority = escapeHtml($row['ticket_priority']);
$ticket_billable = intval($row['ticket_billable']);
$ticket_onsite = intval($row['ticket_onsite']);

$ticket_created_at = escapeHtml($row['ticket_created_at']);
$ticket_created_by = intval($row['ticket_created_by']);
$ticket_date = date('Y-m-d g:i A', strtotime($ticket_created_at));
$ticket_first_response_at = escapeHtml($row['ticket_first_response_at']);
if ($ticket_first_response_at) {
    $ticket_first_response_date_time = date('Y-m-d g:i A', strtotime($ticket_first_response_at));
} else {
    $ticket_first_response_date_time = '';
}
$ticket_resolved_at = escapeHtml($row['ticket_resolved_at']);
if ($ticket_resolved_at) {
    $ticket_resolved_date = date('Y-m-d g:i A', strtotime($ticket_resolved_at));
} else {
    $ticket_resolved_date = '';
}

$ticket_assigned_to = intval($row['ticket_assigned_to']);
if ($ticket_assigned_to) {
    $ticket_assigned_agent = escapeHtml($row['user_name']);
} else {
    $ticket_assigned_agent = '';
}

$contact_id = intval($row['contact_id']);
$contact_name = escapeHtml($row['contact_name']);

$asset_id = intval($row['asset_id']);
$asset_name = escapeHtml($row['asset_name']);
$asset_type = escapeHtml($row['asset_type']);

if ($client_id) {
    enforceClientAccess();
}

$ticket_products = [];
$ticket_products_total = 0.0;
$sql_tp = mysqli_query($mysqli, "
    SELECT tp.*, ii.item_serial
    FROM ticket_products tp
    LEFT JOIN inventory_items ii ON tp.ticket_product_inventory_item_id = ii.item_id
    WHERE tp.ticket_product_ticket_id = $ticket_id
      AND tp.ticket_product_invoiced = 0
    ORDER BY tp.ticket_product_id ASC
");
if ($sql_tp) {
    while ($tp_row = mysqli_fetch_assoc($sql_tp)) {
        $ticket_products[] = $tp_row;
        $ticket_products_total += floatval($tp_row['ticket_product_total']);
    }
}

$include_labor_default = empty($ticket_products);

ob_start();

?>

    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-comment-dollar mr-2"></i>Quote ticket</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
        </button>
    </div>

    <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">

        <div class="modal-body">

            <!-- Row 1 -->
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Scope</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                            </div>
                            <input type="text" class="form-control" name="scope" placeholder="Quick description" maxlength="255">
                        </div>
                    </div>
                </div>

                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Quote Category <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                            </div>

                            <select class="form-control select2" name="category">
                                <option value="">- Select a Category -</option>
                                <?php
                                $sql = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Income' AND category_archived_at IS NULL ORDER BY category_name ASC");
                                while ($row = mysqli_fetch_assoc($sql)) {
                                    $category_id = intval($row['category_id']);
                                    $category_name = escapeHtml($row['category_name']);
                                    ?>
                                    <option value="<?= $category_id ?>"><?= $category_name ?></option>
                                <?php } ?>
                            </select>

                            <div class="input-group-append">
                                <button type="button" class="btn btn-secondary ajax-modal" data-modal-url="/admin/modals/category/category_add.php?category=Expense">
                                    <i class="fas fa-fw fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End row 1 -->

            <!-- Row 2 -->
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Quote Date <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date("Y-m-d") ?>">
                        </div>
                    </div>
                </div>

                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Expire <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <input type="date" class="form-control" name="expire" min="<?= date("Y-m-d") ?>" max="2999-12-31" value="<?= date("Y-m-d", strtotime("+30 days")) ?>" required>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End row 2 -->

            <hr>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="font-weight-bold mb-0"><i class="fas fa-fw fa-clock mr-1 text-primary"></i>Support Labor Line Item</h6>
                <div class="custom-control custom-switch">
                    <input type="hidden" name="include_labor" value="0">
                    <input type="checkbox" class="custom-control-input" id="quoteIncludeLaborSwitch" name="include_labor" value="1" <?= $include_labor_default ? 'checked' : '' ?>>
                    <label class="custom-control-label font-weight-bold" for="quoteIncludeLaborSwitch">Include Labor</label>
                </div>
            </div>

            <div id="quoteLaborSection" class="<?= $include_labor_default ? '' : 'd-none' ?>">
                <div class="form-group">
                    <label>Item <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
                        </div>
                        <input type="text" class="form-control" name="item_name" placeholder="Item" maxlength="200" required>
                    </div>
                </div>

                <?php $description = "Ticket: {$ticket_prefix}{$ticket_number} - $ticket_subject\n"; ?>

                <div class="form-group">
                    <label>Item Description</label>
                    <div class="input-group">
                        <textarea class="form-control" rows="5" name="item_description"><?= trim($description) ?></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="col">
                        <div class="form-group">
                            <label>QTY <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                                </div>
                                <input type="text" class="form-control" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" name="qty" required>
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="form-group">
                            <label>Price <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                </div>
                                <input type="text" class="form-control" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" name="price" value="<?= number_format($client_rate, 2, '.', '') ?>" required>
                            </div>
                            <small class="form-text text-muted">
                                Hourly Client rate is <strong><?= numfmt_format_currency($currency_format, $client_rate, $session_company_currency); ?></strong>
                            </small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Tax <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                        </div>

                        <select class="form-control select2" name="tax_id" required>
                            <option value="0">None</option>
                            <?php
                            $taxes_sql = mysqli_query($mysqli, "SELECT tax_id, tax_name, tax_percent FROM taxes WHERE tax_archived_at IS NULL ORDER BY tax_name ASC");
                            while ($row = mysqli_fetch_assoc($taxes_sql)) {
                                $tax_id_select = intval($row['tax_id']);
                                $tax_name = escapeHtml($row['tax_name']);
                                $tax_percent = floatval($row['tax_percent']);
                                ?>
                                <option value="<?= $tax_id_select ?>"><?= "$tax_name $tax_percent%" ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>

            <?php if (!empty($ticket_products)) { ?>
                <hr>
                <h6 class="font-weight-bold mb-2"><i class="fas fa-fw fa-box-open mr-1 text-success"></i>Products &amp; Hardware Charges on Ticket</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 36px;" class="text-center">Inc</th>
                                <th>Product</th>
                                <th class="text-right">QTY</th>
                                <th class="text-right">Price</th>
                                <th class="text-right">Tax</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ticket_products as $tp) {
                                $tp_id = intval($tp['ticket_product_id']);
                                $tp_qty = floatval($tp['ticket_product_qty']);
                                ?>
                                <tr>
                                    <td class="text-center align-middle">
                                        <input type="checkbox" name="ticket_product_ids[]" value="<?= $tp_id ?>" checked>
                                    </td>
                                    <td class="align-middle">
                                        <div class="font-weight-bold"><?= escapeHtml($tp['ticket_product_name']) ?></div>
                                        <?php if (!empty($tp['item_serial'])) { ?>
                                            <span class="badge badge-info">S/N: <?= escapeHtml($tp['item_serial']) ?></span>
                                        <?php } ?>
                                    </td>
                                    <td class="text-right align-middle"><?= number_format($tp_qty, $tp_qty == floor($tp_qty) ? 0 : 2) ?></td>
                                    <td class="text-right align-middle"><?= numfmt_format_currency($currency_format, floatval($tp['ticket_product_price']), $session_company_currency) ?></td>
                                    <td class="text-right align-middle"><?= numfmt_format_currency($currency_format, floatval($tp['ticket_product_tax']), $session_company_currency) ?></td>
                                    <td class="text-right align-middle font-weight-bold"><?= numfmt_format_currency($currency_format, floatval($tp['ticket_product_total']), $session_company_currency) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-light font-weight-bold">
                                <td colspan="5" class="text-right">Ticket Products Total</td>
                                <td class="text-right"><?= numfmt_format_currency($currency_format, $ticket_products_total, $session_company_currency) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php } ?>

        </div>

        <div class="modal-footer">
            <button type="submit" name="add_quote_from_ticket" class="btn btn-primary text-bold">
                <i class="fa fa-check mr-2"></i>Quote
            </button>
            <button type="button" class="btn btn-light" data-dismiss="modal">
                <i class="fa fa-times mr-2"></i>Cancel
            </button>
        </div>
    </form>

    <script>
    (function () {
        function syncQuoteLaborVisibility() {
            var enabled = $('#quoteIncludeLaborSwitch').is(':checked');
            var $section = $('#quoteLaborSection');
            $section.toggleClass('d-none', !enabled);
            $section.find('input, textarea, select').prop('disabled', !enabled);
        }
        $('#quoteIncludeLaborSwitch').on('change', syncQuoteLaborVisibility);
        syncQuoteLaborVisibility();
    })();
    </script>

<?php
require_once '../../../includes/modal_footer.php';
