<?php

$portal_payment = true;

require_once "includes/inc_all.php";

portalBillingGate();

if (portalViewingAs()) {
    flashAlert("Paying isn't open while you view the portal as someone - nothing can be changed", 'error');
    redirect('unpaid_invoices.php');
}

$checkout = $_SESSION['portal_checkout'] ?? null;
$error = '';
$selection = null;
$pi = null;
$requested_provider = trim((string) ($checkout['provider'] ?? ''));
$provider = $requested_provider !== '' ? billingProviderByName($requested_provider) : billingAnyOnlineProvider();
$checkout_provider = $provider ? (string) $provider['payment_provider_name'] : '';

if (!$provider) {
    $error = 'Online payments are not set up';
} elseif (!$checkout || empty($checkout['ids'])) {
    $error = 'Choose the invoices to pay first';
} else {
    $selection = billingPaySelection($session_client_id, (array) $checkout['ids'], $error, $provider);
}

$square_location_id = '';
if ($selection && $checkout_provider === 'Stripe') {
    try {
        $pi = billingStripeCheckoutIntent($selection, $session_client_id, ['name' => (string) ($contact['contact_name'] ?? '')], (string) ($checkout['pi'] ?? ''));
        $_SESSION['portal_checkout']['pi'] = (string) $pi->id;
    } catch (Throwable $e) {
        logApp("Stripe", "error", "Could not set up a card payment for client $session_client_id: " . $e->getMessage());
        $error = 'The card form could not be set up just now - nothing has been charged. Try again in a moment';
        $selection = null;
    }
} elseif ($selection && $checkout_provider === 'Square') {
    $square_location_id = squareResolveLocationId($provider);
    if ($square_location_id === '') {
        $error = 'Square checkout is not ready yet. Please try another method or contact support.';
        $selection = null;
    }
}

$portal_load_stripe = $selection && $checkout_provider === 'Stripe';
$portal_load_square = $selection && $checkout_provider === 'Square';
$portal_load_paypal = $selection && $checkout_provider === 'PayPal';
$portal_paypal_currency = $selection ? $selection['currency'] : 'USD';
$pay_token = bin2hex(random_bytes(16));
$_SESSION['portal_checkout']['token'] = $pay_token;

$back = 'unpaid_invoices.php' . ($checkout && !empty($checkout['ids']) ? '?' . http_build_query(['select' => implode(',', array_map('intval', (array) $checkout['ids']))]) : '');
?>

<a class="itflow-portal-back" href="<?= escapeHtml($back) ?>"><i class="fas fa-chevron-left"></i>Pay Invoices</a>

<?php if (!$selection) { ?>

    <div class="itflow-portal-page-head">
        <div>
            <h1>Pay Online</h1>
        </div>
    </div>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => escapeHtml($error), 'text' => 'Nothing has been charged.', 'icon' => 'fa-credit-card', 'filtered' => false,
            'actions' => [['label' => 'Back to Pay Invoices', 'href' => $back, 'icon' => 'fa-arrow-left']]]) ?>
    </section>

<?php } else {
    $amount = billingMoney($selection['charge'], $selection['currency']);
    $count = count($selection['invoices']); ?>

    <div class="itflow-portal-page-head">
        <div>
            <h1>Pay <?= escapeHtml($amount) ?></h1>
            <p><?= $count ?> <?= $count === 1 ? 'invoice' : 'invoices' ?> · paid securely through <?= escapeHtml($checkout_provider) ?></p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <section class="itflow-portal-panel">
                <div class="itflow-portal-panel-head">
                    <h2><i class="<?= $checkout_provider === 'PayPal' ? 'fab fa-paypal' : 'fas fa-credit-card' ?>"></i><?= $checkout_provider === 'PayPal' ? 'PayPal checkout' : 'Card details' ?></h2>
                    <span class="small text-body-secondary"><i class="fas fa-lock me-1"></i>Secure</span>
                </div>

                <?php if ($checkout_provider === 'Stripe') { ?>
                    <form class="itflow-portal-panel-body" id="portalPayForm" data-stripe-pay
                        data-stripe-key="<?= escapeHtml($provider['payment_provider_public_key']) ?>"
                        data-client-secret="<?= escapeHtml((string) $pi->client_secret) ?>"
                        data-return-url="<?= escapeHtml("https://$config_base_url/client/post.php?portal_pay_return=1") ?>">
                        <div class="itflow-portal-stripe-element" id="payment-element">
                            <div class="text-body-secondary small" data-stripe-loading><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading the card form...</div>
                        </div>
                        <div class="alert alert-warning small mt-3 mb-0" data-stripe-unavailable hidden>
                            The card form did not load - check your connection and reload the page. Nothing has been charged.
                        </div>
                        <div class="alert alert-danger small mt-3 mb-0" data-pay-error hidden></div>
                        <button type="submit" class="btn btn-success btn-lg w-100 mt-3" data-stripe-submit disabled>
                            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true" data-stripe-busy hidden></span><i class="fas fa-lock me-2" data-stripe-idle></i>Pay <?= escapeHtml($amount) ?>
                        </button>
                        <p class="small text-body-secondary text-center mt-2 mb-0">Your card details go straight to Stripe and never reach us.</p>
                    </form>

                <?php } elseif ($checkout_provider === 'Square') { ?>
                    <form class="itflow-portal-panel-body" id="portalSquarePayForm" data-square-pay
                        data-square-app-id="<?= escapeHtml($provider['payment_provider_public_key']) ?>"
                        data-square-location-id="<?= escapeHtml($square_location_id) ?>"
                        data-csrf-token="<?= escapeHtml($_SESSION['csrf_token']) ?>"
                        data-pay-token="<?= escapeHtml($pay_token) ?>"
                        data-amount="<?= escapeHtml(number_format($selection['charge'], 2, '.', '')) ?>"
                        data-currency="<?= escapeHtml($selection['currency']) ?>">
                        <div id="square-card-container">
                            <div class="text-body-secondary small" data-square-loading><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading the card form...</div>
                        </div>
                        <div class="alert alert-warning small mt-3 mb-0" data-square-unavailable hidden>
                            The card form did not load - check your connection and reload the page. Nothing has been charged.
                        </div>
                        <div class="alert alert-danger small mt-3 mb-0" data-square-error hidden></div>
                        <button type="submit" class="btn btn-success btn-lg w-100 mt-3" data-square-submit disabled>
                            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true" data-square-busy hidden></span><i class="fas fa-lock me-2" data-square-idle></i>Pay <?= escapeHtml($amount) ?>
                        </button>
                        <p class="small text-body-secondary text-center mt-2 mb-0">Your card details go straight to Square and never reach us.</p>
                    </form>

                <?php } elseif ($checkout_provider === 'PayPal') { ?>
                    <div class="itflow-portal-panel-body" data-paypal-pay
                        data-csrf-token="<?= escapeHtml($_SESSION['csrf_token']) ?>"
                        data-pay-token="<?= escapeHtml($pay_token) ?>">
                        <div id="paypal-button-container" class="mb-3"></div>
                        <div class="alert alert-danger small mt-3 mb-3" data-paypal-error hidden></div>
                        <div class="text-center">
                            <a class="btn btn-outline-primary btn-sm" href="post.php?portal_paypal_redirect=1&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                <i class="fab fa-paypal me-1"></i>Or continue on PayPal website
                            </a>
                        </div>
                        <p class="small text-body-secondary text-center mt-3 mb-0">Complete your payment securely through PayPal.</p>
                    </div>
                <?php } ?>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="itflow-portal-panel">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-file-invoice-dollar"></i>Paying</h2>
                    <a href="<?= escapeHtml($back) ?>">Change</a>
                </div>
                <ul class="itflow-portal-list">
                    <?php foreach ($selection['invoices'] as $id => $row) { ?>
                        <li class="itflow-portal-todo">
                            <span class="itflow-portal-todo-main">
                                <div><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?></div>
                                <div><?= trim((string) $row['invoice_scope']) !== '' ? escapeHtml($row['invoice_scope']) : 'Due ' . escapeHtml(date('M j, Y', strtotime($row['invoice_due']))) ?></div>
                            </span>
                            <span class="itflow-money"><?= escapeHtml(billingMoney($selection['cents'][$id] / 100, $selection['currency'])) ?></span>
                        </li>
                    <?php } ?>
                    <?php if ($selection['fee'] > 0) { ?>
                        <li class="itflow-portal-todo" data-pay-fee-line>
                            <span class="itflow-portal-todo-main">
                                <div>Processing fee</div>
                                <div><?= escapeHtml(billingCardFeeTerms($selection['fee_rates'], $selection['currency'])) ?></div>
                            </span>
                            <span class="itflow-money"><?= escapeHtml(billingMoney($selection['fee'], $selection['currency'])) ?></span>
                        </li>
                    <?php } ?>
                </ul>
                <div class="itflow-portal-paybar d-flex justify-content-between align-items-baseline">
                    <span class="text-body-secondary">Total</span>
                    <span class="itflow-portal-big-amount itflow-money"><?= escapeHtml($amount) ?></span>
                </div>
            </section>
        </div>
    </div>

<?php } ?>

<?php
require_once "includes/footer.php";
