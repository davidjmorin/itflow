<?php if (!empty($ticket_products)) { ?>
    <div class="col-12 mt-3" data-tp-picker>
        <div class="card border">
            <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold small"><i class="fas fa-fw fa-box-open me-1"></i>Ticket Products &amp; Inventory (<?= count($ticket_products) ?>)</span>
                <div class="form-check form-switch mb-0">
                    <input type="hidden" name="include_labor" value="0">
                    <input class="form-check-input" type="checkbox" id="<?= escapeHtml($tp_labor_switch_id ?? 'tpIncludeLaborSwitch') ?>" name="include_labor" value="1" data-tp-labor-switch="<?= escapeHtml($tp_labor_section_id) ?>" <?= !empty($include_labor_default) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="<?= escapeHtml($tp_labor_switch_id ?? 'tpIncludeLaborSwitch') ?>">Include Support Labor Line</label>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 small">
                    <thead>
                        <tr>
                            <th style="width:32px;"></th>
                            <th>Product / Charge</th>
                            <th class="text-end">Qty &times; Price</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ticket_products as $tp) {
                            $tp_id = intval($tp['ticket_product_id']);
                            $tp_qty = floatval($tp['ticket_product_qty']);
                            $tp_price = floatval($tp['ticket_product_price']);
                            $tp_total = floatval($tp['ticket_product_total']);
                        ?>
                            <tr>
                                <td class="align-middle text-center">
                                    <input class="form-check-input" type="checkbox" name="ticket_product_ids[]" value="<?= $tp_id ?>" data-tp-total="<?= number_format($tp_total, 2, '.', '') ?>" checked>
                                </td>
                                <td class="align-middle">
                                    <div class="fw-semibold"><?= escapeHtml($tp['ticket_product_name']) ?></div>
                                    <?php if (!empty($tp['item_serial'])) { ?>
                                        <span class="badge text-bg-info">S/N: <?= escapeHtml($tp['item_serial']) ?></span>
                                    <?php } ?>
                                </td>
                                <td class="align-middle text-end text-nowrap"><?= number_format($tp_qty, $tp_qty == floor($tp_qty) ? 0 : 2) ?> &times; <?= numfmt_format_currency($currency_format, $tp_price, $session_company_currency) ?></td>
                                <td class="align-middle text-end text-nowrap fw-semibold"><?= numfmt_format_currency($currency_format, $tp_total, $session_company_currency) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
    (function () {
        document.querySelectorAll('[data-tp-labor-switch]').forEach(function (sw) {
            if (sw.dataset.tpWired) return;
            sw.dataset.tpWired = '1';
            var targetId = sw.getAttribute('data-tp-labor-switch');
            function sync() {
                var section = document.getElementById(targetId);
                if (!section) return;
                var enabled = sw.checked;
                section.classList.toggle('d-none', !enabled);
                section.querySelectorAll('input, textarea, select').forEach(function (el) {
                    el.disabled = !enabled;
                });
                sw.dispatchEvent(new Event('change', { bubbles: true }));
            }
            sw.addEventListener('change', function (e) {
                if (e.isTrusted) {
                    sync();
                }
            });
            sync();
        });
    })();
    </script>
<?php } ?>
