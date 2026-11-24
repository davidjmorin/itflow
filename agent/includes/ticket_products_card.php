<?php if ($config_module_enable_accounting == 1 && (!empty($ticket_products_list) || ($can_edit_ticket && !$ticket_is_closed))) { ?>
    <div class="card mb-3">
        <div class="card-header px-3 py-2">
            <h5 class="card-title mt-1">
                <i class="fas fa-fw fa-box-open me-2"></i>Products &amp; Charges
                <?php if (!empty($ticket_products_list)) { ?>
                    <span class="badge rounded-pill text-bg-secondary ms-1"><?= count($ticket_products_list) ?></span>
                <?php } ?>
            </h5>
            <?php if ($can_edit_ticket && !$ticket_is_closed) { ?>
                <div class="card-tools">
                    <a href="#" class="btn btn-tool ajax-modal" data-modal-size="lg" data-modal-url="modals/ticket/ticket_product_add.php?ticket_id=<?= $ticket_id ?>" title="Add Product / Charge">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
            <?php } ?>
        </div>
        <?php if (empty($ticket_products_list)) { ?>
            <div class="card-body small text-body-secondary">
                No products or hardware charges added yet.
            </div>
        <?php } else { ?>
            <div class="list-group list-group-flush small">
                <?php foreach ($ticket_products_list as $tp) {
                    $tp_id = intval($tp['ticket_product_id']);
                    $tp_qty = floatval($tp['ticket_product_qty']);
                    $tp_price = floatval($tp['ticket_product_price']);
                    $tp_total = floatval($tp['ticket_product_total']);
                    $tp_invoiced = intval($tp['ticket_product_invoiced']) === 1;
                    $tp_asset_id = intval($tp['ticket_product_asset_id']);
                ?>
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-semibold text-truncate"><?= escapeHtml($tp['ticket_product_name']) ?></div>
                                <div class="text-body-secondary">
                                    <?= number_format($tp_qty, $tp_qty == floor($tp_qty) ? 0 : 2) ?> &times; <?= numfmt_format_currency($currency_format, $tp_price, $session_company_currency) ?>
                                    <?php if (floatval($tp['ticket_product_tax']) > 0) { ?>
                                        <span class="ms-1">(+tax)</span>
                                    <?php } ?>
                                </div>
                                <?php if (!empty($tp['item_serial'])) { ?>
                                    <div class="mt-1">
                                        <span class="badge text-bg-info">S/N: <?= escapeHtml($tp['item_serial']) ?></span>
                                        <?php if (!$tp_invoiced) { ?>
                                            <span class="badge text-bg-warning">Reserved</span>
                                        <?php } elseif ($tp_asset_id > 0) { ?>
                                            <a class="badge text-bg-success text-decoration-none ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?<?= $client_url ?>&amp;id=<?= $tp_asset_id ?>" title="View Client Asset">
                                                <i class="fas fa-desktop me-1"></i><?= escapeHtml($tp['linked_asset_name'] ?: 'Asset') ?>
                                            </a>
                                        <?php } ?>
                                    </div>
                                <?php } elseif ($tp_invoiced) { ?>
                                    <div class="mt-1">
                                        <span class="badge text-bg-success">Invoiced</span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="text-end text-nowrap">
                                <div class="fw-semibold"><?= numfmt_format_currency($currency_format, $tp_total, $session_company_currency) ?></div>
                                <?php if ($can_edit_ticket && !$tp_invoiced && !$ticket_is_closed) { ?>
                                    <div class="mt-1">
                                        <a href="#" class="text-secondary ajax-modal me-2" data-modal-url="modals/ticket/ticket_product_edit.php?id=<?= $tp_id ?>" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="post.php?delete_ticket_product=<?= $tp_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>" class="text-danger confirm-link" title="Remove">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center fw-semibold small">
                <span>Products Total</span>
                <span><?= numfmt_format_currency($currency_format, $ticket_products_total, $session_company_currency) ?></span>
            </div>
        <?php } ?>
    </div>
<?php } ?>
