<?php
/*
 * Client Portal
 * Pay invoices - everything still owed, ticked to pay, paid together in one
 * card payment: with a saved card there and then, or a new card on the
 * checkout page (pay.php). ?select=ID,ID ticks just those - the Pay button
 * on an invoice. The total and the button follow the ticks (js/portal.js),
 * with the card fee when the client pays one (billingCardFeeCents()).
 *
 * SCOPING: billingClientOpenInvoices() keys the list to $session_client_id,
 * and post.php (portal_pay) checks the choice again before any charge.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalBillingGate();

$providers = billingActiveProviders();
$provider = billingAnyOnlineProvider();
$open = billingClientOpenInvoices($session_client_id);
$cards = $provider ? billingSavedCards($session_client_id) : [];
$usable = array_values(array_filter($cards, function ($card) {
    return !$card['card']['expired'];
}));
$default_provider = $usable ? (billingProviderById($usable[0]['saved_payment_provider_id']) ?: $provider) : $provider;

$select = isset($_GET['select']) ? array_filter(array_map('intval', explode(',', (string) $_GET['select']))) : null;
$ticked = [];
$total_cents = 0;
$currency = '';
foreach ($open as $i => $row) {
    $cents = intval(round((floatval($row['invoice_amount']) - floatval($row['paid'])) * 100));
    $open[$i]['cents'] = $cents;
    $currency = $currency ?: strtoupper((string) $row['invoice_currency_code']);
    if ($select === null ? count($ticked) < BILLING_PAY_MAX_INVOICES : in_array(intval($row['invoice_id']), $select, true)) {
        $ticked[intval($row['invoice_id'])] = true;
        $total_cents += $cents;
    }
}
$limit_cents = $default_provider ? intval(round(floatval($default_provider['payment_provider_threshold']) * 100)) : 0;
$fee_rates = $default_provider ? billingCardFeeFor($session_client_id, $default_provider) : null;
$fee_cents = billingCardFeeCents($total_cents, $fee_rates);
$locale = str_replace('_', '-', (string) ($session_company_locale ?: 'en_US'));
$pay_token = bin2hex(random_bytes(16));
$provider_count = count($providers);
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Pay Invoices</h1>
        <p>Tick what to pay - it goes through as one card payment.</p>
    </div>
</div>

<?php if (!$provider) { ?>

    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => 'Card payments are not set up', 'text' => 'To pay, use the payment details on each invoice.', 'icon' => 'fa-credit-card', 'filtered' => false,
            'actions' => [['label' => 'Invoices to Pay', 'href' => 'invoices.php?tab=open', 'icon' => 'fa-file-invoice-dollar']]]) ?>
    </section>

<?php } elseif (!$open) { ?>

    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => 'Nothing to pay', 'text' => 'You are all paid up - thank you.', 'icon' => 'fa-check', 'filtered' => false,
            'actions' => [['label' => 'Your Payments', 'href' => 'payments.php', 'icon' => 'fa-receipt']]]) ?>
    </section>

<?php } else { ?>

    <form action="post.php" method="post" data-portal-pay data-portal-once
        data-pay-currency="<?= escapeHtml($currency) ?>" data-pay-locale="<?= escapeHtml($locale) ?>" data-pay-limit="<?= $limit_cents ?>" data-pay-max="<?= BILLING_PAY_MAX_INVOICES ?>"<?php if ($fee_rates) { ?>
        data-pay-fee-percent="<?= intval(round($fee_rates['percent'] * 100)) ?>" data-pay-fee-fixed="<?= intval(round($fee_rates['fixed'] * 100)) ?>"<?php } ?>>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="pay_token" value="<?= $pay_token ?>">

        <div class="row g-4">
            <div class="col-lg-8">
                <section class="itflow-portal-panel">
                    <div class="itflow-portal-panel-head">
                        <h2><i class="fas fa-file-invoice-dollar"></i>Still to pay</h2>
                        <div class="form-check mb-0 small">
                            <input class="form-check-input" type="checkbox" id="payAll" data-pay-all<?= count($ticked) === count($open) ? ' checked' : '' ?>>
                            <label class="form-check-label" for="payAll">All</label>
                        </div>
                    </div>
                    <ul class="itflow-portal-list">
                        <?php foreach ($open as $row) {
                            $id = intval($row['invoice_id']);
                            $state = portalInvoiceState($row, $row['paid']); ?>
                            <li>
                                <label class="itflow-portal-ticket itflow-portal-payrow" for="payInvoice<?= $id ?>">
                                    <input class="form-check-input" type="checkbox" id="payInvoice<?= $id ?>" name="invoice_ids[]" value="<?= $id ?>" data-pay-cents="<?= intval($row['cents']) ?>"<?= isset($ticked[$id]) ? ' checked' : '' ?>>
                                    <span class="itflow-portal-ticket-main">
                                        <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?><?php if (trim((string) $row['invoice_scope']) !== '') { ?><span class="fw-normal text-body-secondary"> · <?= escapeHtml($row['invoice_scope']) ?></span><?php } ?></span>
                                        <span class="itflow-portal-ticket-meta">
                                            <span>Due <?= escapeHtml(date('M j, Y', strtotime($row['invoice_due']))) ?></span>
                                            <?php if ($state['paid'] > 0) { ?><span><?= escapeHtml(billingMoney($state['paid'], $row['invoice_currency_code'])) ?> of <?= escapeHtml(billingMoney($row['invoice_amount'], $row['invoice_currency_code'])) ?> paid</span><?php } ?>
                                            <a href="invoice.php?id=<?= $id ?>">View</a>
                                        </span>
                                    </span>
                                    <span class="itflow-portal-ticket-side">
                                        <span class="itflow-portal-amount itflow-money"><?= escapeHtml(billingMoney($state['balance'], $row['invoice_currency_code'])) ?></span>
                                        <?= portalStateBadge($state) ?>
                                    </span>
                                </label>
                            </li>
                        <?php } ?>
                    </ul>
                </section>
            </div>

            <div class="col-lg-4">
                <section class="itflow-portal-panel itflow-portal-paybox">
                    <div class="itflow-portal-panel-head">
                        <h2><i class="fas fa-credit-card"></i>Pay with</h2>
                        <a href="saved_payment_methods.php">Your cards</a>
                    </div>
                    <div class="itflow-portal-panel-body">
                        <div class="itflow-portal-methods">
                            <?php foreach ($cards as $i => $card) {
                                $expired = $card['card']['expired'];
                                $c_prov = billingProviderById($card['saved_payment_provider_id']) ?: $provider;
                                $c_limit = intval(round(floatval($c_prov['payment_provider_threshold'] ?? 0) * 100));
                                $c_rates = billingCardFeeFor($session_client_id, $c_prov);
                                $c_fp = $c_rates ? intval(round($c_rates['percent'] * 100)) : -1;
                                $c_ff = $c_rates ? intval(round($c_rates['fixed'] * 100)) : 0; ?>
                                <label class="itflow-portal-method<?= $expired ? ' is-disabled' : '' ?>">
                                    <input class="form-check-input" type="radio" name="method" value="<?= intval($card['saved_payment_id']) ?>"
                                        data-method-limit="<?= $c_limit ?>" data-method-fee-percent="<?= $c_fp ?>" data-method-fee-fixed="<?= $c_ff ?>" data-method-provider="<?= escapeHtml($card['payment_provider_name']) ?>"<?= $expired ? ' disabled' : '' ?><?= $usable && intval($usable[0]['saved_payment_id']) === intval($card['saved_payment_id']) ? ' checked' : '' ?>>
                                    <i class="<?= escapeHtml($card['card']['icon']) ?> fa-fw fa-lg"></i>
                                    <span class="min-w-0">
                                        <span class="d-block text-truncate"><?= escapeHtml($card['card']['label']) ?><?= $provider_count > 1 ? ' (' . escapeHtml($card['payment_provider_name']) . ')' : '' ?></span>
                                        <small class="<?= $expired ? 'text-danger-emphasis' : 'text-body-secondary' ?>"><?= $card['card']['exp'] !== '' ? ($expired ? 'Expired ' : 'Expires ') . escapeHtml($card['card']['exp']) : 'Saved card' ?></small>
                                    </span>
                                </label>
                            <?php } ?>
                            <?php
                            $first_checkout = true;
                            foreach ($providers as $pname => $prow) {
                                $p_limit = intval(round(floatval($prow['payment_provider_threshold'] ?? 0) * 100));
                                $p_rates = billingCardFeeFor($session_client_id, $prow);
                                $p_fp = $p_rates ? intval(round($p_rates['percent'] * 100)) : -1;
                                $p_ff = $p_rates ? intval(round($p_rates['fixed'] * 100)) : 0;
                                $is_checked = !$usable && $first_checkout;
                                $first_checkout = false;
                                if ($pname === 'PayPal') {
                                    $opt_icon = 'fab fa-paypal text-primary';
                                    $opt_title = 'PayPal';
                                    $opt_sub = 'Continue to PayPal on the next page';
                                } else {
                                    $opt_icon = 'fas fa-plus-circle text-body-secondary';
                                    $has_two_cards = isset($providers['Stripe']) && isset($providers['Square']);
                                    $opt_title = $has_two_cards ? "A new card ($pname)" : 'A new card';
                                    $opt_sub = 'Enter the card on the next page';
                                }
                                ?>
                                <label class="itflow-portal-method">
                                    <input class="form-check-input" type="radio" name="method" value="new:<?= escapeHtml($pname) ?>"
                                        data-method-limit="<?= $p_limit ?>" data-method-fee-percent="<?= $p_fp ?>" data-method-fee-fixed="<?= $p_ff ?>" data-method-provider="<?= escapeHtml($pname) ?>"<?= $is_checked ? ' checked' : '' ?>>
                                    <i class="<?= escapeHtml($opt_icon) ?> fa-fw fa-lg"></i>
                                    <span>
                                        <span class="d-block"><?= escapeHtml($opt_title) ?></span>
                                        <small class="text-body-secondary"><?= escapeHtml($opt_sub) ?></small>
                                    </span>
                                </label>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="itflow-portal-paybar">
                        <div data-pay-fee-breakdown<?= $fee_rates ? '' : ' hidden' ?>>
                            <div class="d-flex justify-content-between align-items-baseline small">
                                <span class="text-body-secondary" data-pay-count><?= count($ticked) ?> <?= count($ticked) === 1 ? 'invoice' : 'invoices' ?></span>
                                <span class="itflow-money" data-pay-subtotal><?= escapeHtml(billingMoney($total_cents / 100, $currency)) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline small mb-1">
                                <span class="text-body-secondary" title="<?= $fee_rates ? escapeHtml(billingCardFeeTerms($fee_rates, $currency)) : '' ?>">Processing fee</span>
                                <span class="itflow-money" data-pay-fee><?= escapeHtml(billingMoney($fee_cents / 100, $currency)) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline mb-2">
                                <span class="text-body-secondary">Total</span>
                                <span class="itflow-portal-big-amount itflow-money" data-pay-total><?= escapeHtml(billingMoney(($total_cents + $fee_cents) / 100, $currency)) ?></span>
                            </div>
                        </div>
                        <div data-pay-simple-breakdown<?= $fee_rates ? ' hidden' : '' ?>>
                            <div class="d-flex justify-content-between align-items-baseline mb-2">
                                <span class="text-body-secondary" data-pay-count><?= count($ticked) ?> <?= count($ticked) === 1 ? 'invoice' : 'invoices' ?></span>
                                <span class="itflow-portal-big-amount itflow-money" data-pay-total><?= escapeHtml(billingMoney($total_cents / 100, $currency)) ?></span>
                            </div>
                        </div>
                        <div class="alert alert-warning small py-2" data-pay-limit-note<?= $limit_cents > 0 && $total_cents >= $limit_cents ? '' : ' hidden' ?>>
                            Online payments for this provider are limited to under <?= escapeHtml(billingMoney($limit_cents / 100, $currency)) ?> at a time - untick some invoices, or pay another way.
                        </div>
                        <button type="submit" class="btn btn-success btn-lg w-100" name="portal_pay" data-pay-button<?= !$ticked ? ' disabled' : '' ?>>
                            <i class="fas fa-lock me-2"></i><span data-pay-label><?= $usable ? 'Pay ' : 'Continue to pay ' ?><?= escapeHtml(billingMoney(($total_cents + $fee_cents) / 100, $currency)) ?></span>
                        </button>
                        <?php if ($fee_rates) { ?>
                            <p class="small text-body-secondary text-center mt-2 mb-0">Paying online adds a processing fee: <?= escapeHtml(billingCardFeeTerms($fee_rates, $currency)) ?>.</p>
                        <?php } ?>
                        <p class="small text-body-secondary text-center mt-2 mb-0">Paid securely online - your payment details never reach us.</p>
                    </div>
                </section>
            </div>
        </div>
    </form>

<?php } ?>

<?php
require_once "includes/footer.php";
