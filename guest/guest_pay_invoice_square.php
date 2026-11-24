<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_global_settings.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_init.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/inc_set_timezone.php';

    header('Content-Type: application/json; charset=utf-8');

    $ip = escapeSql(getIP());
    $user_agent = escapeSql(mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250));
    $os = escapeSql(getOS($user_agent));
    $browser = escapeSql(getWebBrowser($user_agent));
    $session_ip = $ip;
    $session_user_agent = $user_agent;

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $invoice_id = intval($input['invoice_id'] ?? 0);
    $url_key = (string) ($input['url_key'] ?? '');
    $source_id = trim((string) ($input['source_id'] ?? ''));
    $verification_token = trim((string) ($input['verification_token'] ?? ''));
    $pay_token = (string) ($input['pay_token'] ?? '');
    $save_card = !empty($input['save_card']);

    $expected_token = (string) ($_SESSION['guest_sq_pay_token'][$invoice_id] ?? '');
    if ($expected_token === '' || !hash_equals($expected_token, $pay_token)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Checkout session expired. Please reload the page and try again.']);
        exit;
    }

    $provider = billingProviderByName('Square');
    if (!$provider) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Square payments are currently unavailable.']);
        exit;
    }

    $invoice = billingRow('invoice', $invoice_id, false);
    if (!$invoice || (string) $invoice['invoice_url_key'] === '' || !hash_equals((string) $invoice['invoice_url_key'], $url_key)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Invoice not found.']);
        exit;
    }

    $state = invoiceState($invoice, invoicePaid($invoice_id));
    if (!invoiceCanPay($invoice, $state) || !billingOnlinePayAllowed($invoice['invoice_amount'], $provider)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'This invoice cannot be paid online right now.']);
        exit;
    }

    if ($source_id === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Please enter valid card details.']);
        exit;
    }

    $client_id = intval($invoice['client_id']);
    $balance_cents = intval(round($state['balance'] * 100));
    $fee_cents = billingCardFeeCents($balance_cents, billingCardFeeFor($client_id, $provider));
    $total_cents = $balance_cents + $fee_cents;
    $currency = strtoupper((string) $invoice['invoice_currency_code']);
    $number = billingNumber('invoice', $invoice);

    $lock = 'itflow_guest_sq_' . $invoice_id;
    if (intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT GET_LOCK('$lock', 15)"))[0] ?? 0) !== 1) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Another payment attempt is in progress. Please wait a moment.']);
        exit;
    }

    try {
        $fresh_state = invoiceState($invoice, invoicePaid($invoice_id));
        if (intval(round($fresh_state['balance'] * 100)) !== $balance_cents) {
            mysqli_query($mysqli, "SELECT RELEASE_LOCK('$lock')");
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'This invoice balance has changed. Reload the page to continue.']);
            exit;
        }

        $opts = [
            'reference_id' => mb_substr($number, 0, 40),
            'note' => "ITFlow: {$invoice['client_name']} payment for $number",
        ];
        if ($verification_token !== '') {
            $opts['verification_token'] = $verification_token;
        }

        $contact_email = trim((string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT contact_email FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1 LIMIT 1"))[0] ?? ''));
        if ($contact_email !== '') {
            $opts['buyer_email_address'] = $contact_email;
        }

        $cust = squareEnsureCustomer($provider, $client_id);
        if ($cust['ok']) {
            $opts['customer_id'] = $cust['customer_id'];
        }

        $payment_source = $source_id;
        if ($save_card && $cust['ok']) {
            $saved = squareSaveCard($provider, $client_id, $source_id, (string) ($invoice['client_name'] ?? ''), '', 'itflow-guest-save-' . $pay_token);
            if ($saved['ok']) {
                $payment_source = $saved['card_id'];
            }
        }

        $charge = squareCreatePayment(
            $provider,
            $payment_source,
            $total_cents,
            $currency,
            'itflow-guest-sq-' . $invoice_id . '-' . $pay_token,
            $opts
        );

        if (!$charge['ok']) {
            mysqli_query($mysqli, "SELECT RELEASE_LOCK('$lock')");
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $charge['error'] ?: 'Card payment could not be completed.']);
            exit;
        }

        $sq_id = (string) $charge['payment']['id'];
        $created = !empty($charge['payment']['created_at']) ? strtotime((string) $charge['payment']['created_at']) : time();
        $date = date('Y-m-d', $created ?: time());

        $fees_split = $fee_cents > 0 ? [$invoice_id => $fee_cents] : [];
        $booked = billingBookOnlinePayment(
            'Square',
            $provider,
            $sq_id,
            $client_id,
            [$invoice_id => $balance_cents],
            $fees_split,
            $currency,
            $date,
            squareProviderMode($provider) === 'live',
            ['name' => (string) ($invoice['client_name'] ?? ''), 'how' => 'Online card', 'via' => "via guest link ($ip)"]
        );

        mysqli_query($mysqli, "SELECT RELEASE_LOCK('$lock')");

        if (!$booked['ok']) {
            logApp('Square', 'error', "Guest Square payment $sq_id went through for invoice $invoice_id but could not be booked: " . $booked['error']);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Your card was charged, but we could not record it automatically. Please contact us.']);
            exit;
        }

        unset($_SESSION['guest_sq_pay_token'][$invoice_id]);
        echo json_encode([
            'ok' => true,
            'redirect' => 'guest_view_invoice.php?invoice_id=' . $invoice_id . '&url_key=' . rawurlencode($url_key),
        ]);
        exit;
    } catch (Throwable $e) {
        mysqli_query($mysqli, "SELECT RELEASE_LOCK('$lock')");
        logApp('Square', 'error', 'Guest Square payment exception: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'An unexpected error occurred. Nothing was charged.']);
        exit;
    }
}

require_once 'includes/inc_all_guest.php';

$provider = billingProviderByName('Square');
$invoice_id = intval($_GET['invoice_id'] ?? 0);
$url_key = (string) ($_GET['url_key'] ?? '');

if (!$provider) {
    echo guestDeadEndHtml('fa-credit-card', "Paying by card isn't available just now", "Get in touch to pay this invoice another way.");
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
    exit();
}

$invoice = billingRow('invoice', $invoice_id, false);
if (!$invoice || (string) $invoice['invoice_url_key'] === '' || !hash_equals((string) $invoice['invoice_url_key'], $url_key)) {
    echo guestDeadEndHtml('fa-file-invoice-dollar', "This invoice link isn't working", "It may have been mistyped, or the invoice is already paid. Ask whoever sent it for a new link.");
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
    exit();
}

$state = invoiceState($invoice, invoicePaid($invoice_id));
if (!invoiceCanPay($invoice, $state) || !billingOnlinePayAllowed($invoice['invoice_amount'], $provider)) {
    echo guestDeadEndHtml('fa-file-invoice-dollar', "This invoice can't be paid online", "Get in touch to pay it another way.");
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
    exit();
}

$location_id = squareResolveLocationId($provider);
if ($location_id === '') {
    echo guestDeadEndHtml('fa-credit-card', "Square checkout is not ready yet", "Get in touch to pay this invoice another way.");
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
    exit();
}

$client_id = intval($invoice['client_id']);
$currency = (string) $invoice['invoice_currency_code'];
$balance_to_pay = $state['balance'];
$fee_rates = billingCardFeeFor($client_id, $provider);
$card_fee = billingCardFeeCents(intval(round($balance_to_pay * 100)), $fee_rates) / 100;
$total_to_pay = $balance_to_pay + $card_fee;
$items = billingItems('invoice', $invoice_id);
$pay_token = bin2hex(random_bytes(16));
$_SESSION['guest_sq_pay_token'][$invoice_id] = $pay_token;
$mode = squareProviderMode($provider);
$sdk_url = squareWebSdkUrl($mode);
?>

<script data-cfasync="false" src="<?= escapeHtml($sdk_url) ?>"></script>

<div class="row py-5">
    <div class="col-sm">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Payment for Invoice: <strong><?= escapeHtml($invoice['invoice_prefix'] . $invoice['invoice_number']) ?></strong></h3>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item) { ?>
                        <tr>
                            <td><?= escapeHtml($item['item_name']) ?></td>
                            <td class="text-center"><?= floatval($item['item_quantity']) ?></td>
                            <td class="text-end"><?= escapeHtml(billingMoney(floatval($item['item_total']), $currency)) ?></td>
                        </tr>
                    <?php } ?>
                    <?php if (floatval($invoice['invoice_discount_amount']) > 0) { ?>
                        <tr class="text-end">
                            <td colspan="2">Discount</td>
                            <td><?= escapeHtml(billingMoney(floatval($invoice['invoice_discount_amount']), $currency)) ?></td>
                        </tr>
                    <?php } ?>
                    <?php if ($state['paid'] > 0) { ?>
                        <tr class="text-end">
                            <td colspan="2">Paid</td>
                            <td><?= escapeHtml(billingMoney($state['paid'], $currency)) ?></td>
                        </tr>
                    <?php } ?>
                    <?php if ($card_fee > 0) { ?>
                        <tr class="text-end">
                            <td colspan="2">Balance</td>
                            <td><?= escapeHtml(billingMoney($balance_to_pay, $currency)) ?></td>
                        </tr>
                        <tr class="text-end">
                            <td colspan="2">Card fee <div class="small text-body-secondary"><?= escapeHtml(billingCardFeeTerms($fee_rates, $currency)) ?></div></td>
                            <td><?= escapeHtml(billingMoney($card_fee, $currency)) ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-sm offset-sm-1">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Payment Total: <strong><?= escapeHtml(billingMoney($total_to_pay, $currency)) ?></strong></h3>
            </div>
            <div class="card-body">
                <?php if ($card_fee > 0) { ?>
                    <p class="small text-body-secondary">Includes a <?= escapeHtml(billingMoney($card_fee, $currency)) ?> card fee for paying by card.</p>
                <?php } ?>
                <form id="square-payment-form"
                    data-app-id="<?= escapeHtml($provider['payment_provider_public_key']) ?>"
                    data-location-id="<?= escapeHtml($location_id) ?>"
                    data-invoice-id="<?= $invoice_id ?>"
                    data-url-key="<?= escapeHtml($url_key) ?>"
                    data-pay-token="<?= escapeHtml($pay_token) ?>"
                    data-amount="<?= escapeHtml(number_format($total_to_pay, 2, '.', '')) ?>"
                    data-currency="<?= escapeHtml(strtoupper($currency)) ?>">
                    <div id="square-card-container" class="mb-3"></div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="square-save-card">
                        <label class="form-check-label small" for="square-save-card">Save this card for future invoices and autopay</label>
                    </div>
                    <button type="submit" id="square-submit" class="btn btn-success btn-lg w-100 fw-bold" disabled>
                        <span class="spinner-border spinner-border-sm d-none me-2" id="square-spinner" role="status"></span>
                        <span id="square-button-text"><i class="fas fa-lock me-2"></i>Pay <?= escapeHtml(billingMoney($total_to_pay, $currency)) ?></span>
                    </button>
                    <div id="square-message" class="alert alert-danger mt-3 mb-0 d-none" role="alert"></div>
                </form>
            </div>
        </div>
    </div>
</div>

<script data-cfasync="false" src="<?= staticFileUrl('/js/guest_pay_invoice_square.js') ?>"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
