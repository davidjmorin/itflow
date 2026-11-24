<?php

$portal_payment = true;

require_once "includes/inc_all.php";

portalBillingGate();

$card_providers = billingCardProviders();
$stripe_provider = $card_providers['Stripe'] ?? null;
$square_provider = $card_providers['Square'] ?? null;

$portal_load_stripe = $stripe_provider !== null;
$portal_load_square = $square_provider !== null;

if (!$card_providers) { ?>
    <div class="itflow-portal-page-head">
        <div>
            <h1>Cards &amp; Autopay</h1>
        </div>
    </div>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => 'Card payments are not set up', 'text' => 'To pay, use the payment details on each invoice.', 'icon' => 'fa-credit-card', 'filtered' => false]) ?>
    </section>
    <?php
    require_once "includes/footer.php";
    exit;
}

$stripe_customer = $stripe_provider ? billingStripeCustomer($session_client_id) : '';
$square_location_id = $square_provider ? squareResolveLocationId($square_provider) : '';
$cards = billingSavedCards($session_client_id);
$usable = array_values(array_filter($cards, function ($card) {
    return !$card['card']['expired'];
}));
$recurring = mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_scope, recurring_invoice_amount,
        recurring_invoice_currency_code, recurring_invoice_frequency, recurring_invoice_next_date, recurring_payment_saved_payment_id
    FROM recurring_invoices
    LEFT JOIN recurring_payments ON recurring_payment_recurring_invoice_id = recurring_invoice_id
    WHERE recurring_invoice_client_id = $session_client_id AND recurring_invoice_status = 1
    ORDER BY recurring_invoice_next_date ASC, recurring_invoice_id ASC");
$company = billingCompany();
$autostart = isset($_GET['add']) && $stripe_customer !== '';
$default_card_provider = $stripe_provider ?: $square_provider;
$fee_rates = billingCardFeeFor($session_client_id, $default_card_provider);
$fee_terms = $fee_rates ? ' Each card payment has a card fee added: ' . billingCardFeeTerms($fee_rates) . '.' : '';
$multi_card_providers = $stripe_provider && $square_provider;
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Cards &amp; Autopay</h1>
        <p>Save a card to pay invoices in a click, and have recurring invoices paid on their own.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <section class="itflow-portal-panel">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-credit-card"></i>Your cards</h2>
            </div>
            <?php if (!$cards) { ?>
                <?= emptyStateHtml(['title' => 'No cards saved', 'text' => 'Save one to pay in a click, or to turn on autopay.', 'icon' => 'fa-credit-card', 'filtered' => false]) ?>
            <?php } else { ?>
                <ul class="itflow-portal-list">
                    <?php foreach ($cards as $card) {
                        $c = $card['card']; ?>
                        <li class="itflow-portal-todo">
                            <span class="itflow-portal-card-brand" aria-hidden="true"><i class="<?= escapeHtml($c['icon']) ?>"></i></span>
                            <span class="itflow-portal-todo-main">
                                <div><?= escapeHtml($c['label']) ?><?= $multi_card_providers ? ' <span class="badge rounded-pill text-bg-light border fw-normal ms-1">' . escapeHtml($card['payment_provider_name']) . '</span>' : '' ?></div>
                                <div>
                                    <?php if ($c['expired']) { ?>
                                        <span class="text-danger-emphasis">Expired <?= escapeHtml($c['exp']) ?></span>
                                    <?php } elseif ($c['exp'] !== '') { ?>
                                        Expires <?= escapeHtml($c['exp']) ?>
                                    <?php } ?>
                                    <?php if ($card['autopay']) { ?>
                                        · Pays <?= $card['autopay'] ?> recurring <?= $card['autopay'] === 1 ? 'invoice' : 'invoices' ?> automatically
                                    <?php } ?>
                                </div>
                            </span>
                            <a class="btn btn-sm btn-outline-danger confirm-link" href="post.php?delete_saved_payment=<?= intval($card['saved_payment_id']) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                data-confirm-title="Remove <?= escapeHtml($c['label']) ?>?"
                                data-confirm-text="<?= $card['autopay'] ? 'Autopay with this card stops - those invoices will need paying by hand.' : 'You can save it again any time.' ?>"
                                data-confirm-button="Remove" data-confirm-icon="warning"><i class="fas fa-trash me-1"></i>Remove</a>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
            <div class="itflow-portal-panel-body border-top">
                <?php if ($stripe_provider) { ?>
                    <?php if ($stripe_customer !== '') { ?>
                        <button type="button" class="btn btn-outline-primary me-2 mb-2" data-stripe-add-card<?= $autostart ? ' hidden' : '' ?>><i class="fas fa-plus me-2"></i>Add a Card<?= $multi_card_providers ? ' (Stripe)' : '' ?></button>
                        <div data-stripe-add-card-holder data-stripe-key="<?= escapeHtml($stripe_provider['payment_provider_public_key']) ?>"<?= $autostart ? ' data-autostart' : '' ?> hidden>
                            <p class="small text-body-secondary">By saving a card you agree <?= escapeHtml($company['company_name'] ?? 'we') ?> may charge it for invoices you pay with it, and for the recurring invoices you set to autopay.<?= escapeHtml($fee_terms) ?></p>
                            <div class="text-body-secondary small" data-stripe-loading><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading Stripe...</div>
                            <div class="alert alert-warning small mb-0" data-stripe-unavailable hidden>Stripe's form did not load - check your connection and reload the page.</div>
                            <div id="checkout"></div>
                        </div>
                    <?php } else { ?>
                        <form action="post.php" method="post" class="<?= $square_provider ? 'mb-3' : '' ?>" data-portal-once>
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <p class="small text-body-secondary">By saving a card you agree <?= escapeHtml($company['company_name'] ?? 'we') ?> may charge it for invoices you pay with it, and for the recurring invoices you set to autopay.<?= escapeHtml($fee_terms) ?> Stripe keeps the card details, under its privacy policy and terms.</p>
                            <button type="submit" class="btn btn-outline-primary" name="create_stripe_customer"><i class="fas fa-plus me-2"></i>Add a Card<?= $multi_card_providers ? ' (Stripe)' : '' ?></button>
                        </form>
                    <?php } ?>
                <?php } ?>

                <?php if ($square_provider && $square_location_id !== '') { ?>
                    <button type="button" class="btn btn-outline-primary mb-2" data-square-add-card><i class="fas fa-plus me-2"></i>Add a Card<?= $multi_card_providers ? ' (Square)' : '' ?></button>
                    <div data-square-save-card
                        data-square-app-id="<?= escapeHtml($square_provider['payment_provider_public_key']) ?>"
                        data-square-location-id="<?= escapeHtml($square_location_id) ?>"
                        data-csrf-token="<?= escapeHtml($_SESSION['csrf_token']) ?>"
                        hidden>
                        <p class="small text-body-secondary">By saving a card you agree <?= escapeHtml($company['company_name'] ?? 'we') ?> may charge it for invoices you pay with it, and for the recurring invoices you set to autopay.<?= escapeHtml($fee_terms) ?> Square keeps your card details securely.</p>
                        <div class="text-body-secondary small mb-2" data-square-loading><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading Square...</div>
                        <div class="alert alert-warning small mb-2" data-square-unavailable hidden>Square's card form did not load - check your connection and reload the page.</div>
                        <div id="square-save-card-container" class="mb-2"></div>
                        <div class="alert alert-danger small mb-2" data-square-error hidden></div>
                        <button type="button" class="btn btn-primary" data-square-save-btn disabled>
                            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true" data-square-busy hidden></span><i class="fas fa-save me-2" data-square-idle></i>Save Card
                        </button>
                    </div>
                <?php } ?>
            </div>
        </section>
    </div>

    <div class="col-lg-5">
        <section class="itflow-portal-panel">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-sync-alt"></i>Autopay</h2>
            </div>
            <?php if (!mysqli_num_rows($recurring)) { ?>
                <?= emptyStateHtml(['title' => 'Nothing to autopay', 'text' => 'Recurring invoices - monthly services and the like - can be paid automatically once you have one.', 'icon' => 'fa-sync-alt', 'filtered' => false]) ?>
            <?php } else { ?>
                <div class="itflow-portal-panel-body">
                    <p class="small text-body-secondary mb-0">Autopay charges the card on each invoice's due date, and we email you a receipt every time.<?= escapeHtml($fee_terms) ?><?= !$usable ? ' Add a card to turn it on.' : '' ?></p>
                </div>
                <ul class="itflow-portal-list border-top">
                    <?php while ($row = mysqli_fetch_assoc($recurring)) {
                        $rid = intval($row['recurring_invoice_id']);
                        $on = intval($row['recurring_payment_saved_payment_id']); ?>
                        <li class="itflow-portal-panel-body">
                            <div class="d-flex justify-content-between gap-2 mb-2">
                                <a class="fw-semibold text-body text-decoration-none text-truncate" href="recurring_invoice.php?id=<?= $rid ?>"><?= escapeHtml(trim((string) $row['recurring_invoice_scope']) !== '' ? $row['recurring_invoice_scope'] : $row['recurring_invoice_prefix'] . $row['recurring_invoice_number']) ?></a>
                                <span class="itflow-money small text-body-secondary"><?= escapeHtml(billingMoney($row['recurring_invoice_amount'], $row['recurring_invoice_currency_code'])) ?> / <?= escapeHtml(recurringInvoicePeriodWord($row['recurring_invoice_frequency'])) ?></span>
                            </div>
                            <form action="post.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="set_recurring_payment" value="1">
                                <input type="hidden" name="recurring_invoice_id" value="<?= $rid ?>">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fas fa-fw fa-credit-card"></i></span>
                                    <select class="form-select" name="saved_payment_id" aria-label="Autopay for this recurring invoice" data-autosubmit<?= !$usable && !$on ? ' disabled' : '' ?>>
                                        <option value="0">Off - I'll pay each invoice</option>
                                        <?php foreach ($cards as $card) {
                                            if ($card['card']['expired'] && intval($card['saved_payment_id']) !== $on) {
                                                continue;
                                            } ?>
                                            <option value="<?= intval($card['saved_payment_id']) ?>"<?= intval($card['saved_payment_id']) === $on ? ' selected' : '' ?>>Pay with <?= escapeHtml($card['card']['label']) ?><?= $card['card']['expired'] ? ' (expired)' : '' ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </form>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </section>
    </div>
</div>

<?php
require_once "includes/footer.php";
