<?php

$action = (string) ($_GET['action'] ?? ($_POST['action'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' || in_array($action, ['redirect_checkout', 'return'], true)) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_global_settings.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_init.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/inc_set_timezone.php';

    $ip = escapeSql(getIP());
    $user_agent = escapeSql(mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250));
    $session_ip = $ip;
    $session_user_agent = $user_agent;

    $provider = billingProviderByName('PayPal');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        $post_action = (string) ($input['action'] ?? $action);
        $invoice_id = intval($input['invoice_id'] ?? 0);
        $url_key = (string) ($input['url_key'] ?? '');
        $pay_token = (string) ($input['pay_token'] ?? '');

        if (!$provider) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'PayPal is currently unavailable.']);
            exit;
        }

        $expected_token = (string) ($_SESSION['guest_pp_pay_token'][$invoice_id] ?? '');
        if ($expected_token === '' || !hash_equals($expected_token, $pay_token)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Checkout session expired. Please reload and try again.']);
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

        $client_id = intval($invoice['client_id']);
        $balance_cents = intval(round($state['balance'] * 100));
        $fee_cents = billingCardFeeCents($balance_cents, billingCardFeeFor($client_id, $provider));
        $total_cents = $balance_cents + $fee_cents;
        $currency = strtoupper((string) $invoice['invoice_currency_code']);
        $number = billingNumber('invoice', $invoice);

        if ($post_action === 'create_order') {
            $order = paypalCreateOrder(
                $provider,
                $total_cents,
                $currency,
                "Invoice $number",
                json_encode(['invoice_id' => $invoice_id, 'client_id' => $client_id, 'cents' => $balance_cents, 'fee_cents' => $fee_cents]),
                "inv-$invoice_id"
            );
            if (!$order['ok']) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => $order['error']]);
                exit;
            }
            $_SESSION['guest_paypal_orders'][$order['order_id']] = [
                'invoice_id' => $invoice_id,
                'url_key' => $url_key,
                'client_id' => $client_id,
                'cents' => $balance_cents,
                'fee_cents' => $fee_cents,
                'total_cents' => $total_cents,
                'currency' => $currency,
            ];
            echo json_encode(['ok' => true, 'id' => $order['order_id']]);
            exit;
        }

        if ($post_action === 'capture_order') {
            $order_id = trim((string) ($input['order_id'] ?? ''));
            $meta = $_SESSION['guest_paypal_orders'][$order_id] ?? null;
            if (!$meta || intval($meta['invoice_id']) !== $invoice_id) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'PayPal order session not found.']);
                exit;
            }
            $cap = paypalCaptureOrder($provider, $order_id);
            if (!$cap['ok']) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => $cap['error']]);
                exit;
            }
            if (intval($cap['amount_cents']) !== intval($meta['total_cents']) || strtoupper($cap['currency']) !== strtoupper($meta['currency'])) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Captured PayPal amount did not match the invoice balance.']);
                exit;
            }

            $fees_split = intval($meta['fee_cents']) > 0 ? [$invoice_id => intval($meta['fee_cents'])] : [];
            $booked = billingBookOnlinePayment(
                'PayPal',
                $provider,
                $cap['capture_id'],
                $client_id,
                [$invoice_id => intval($meta['cents'])],
                $fees_split,
                $meta['currency'],
                $cap['date'],
                paypalProviderMode($provider) === 'live',
                ['name' => (string) ($invoice['client_name'] ?? ''), 'how' => 'PayPal', 'via' => "via guest link ($ip)"]
            );
            if (!$booked['ok']) {
                logApp('PayPal', 'error', "Guest PayPal capture {$cap['capture_id']} succeeded for invoice $invoice_id but could not be booked: " . $booked['error']);
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'Payment succeeded at PayPal but could not be recorded automatically.']);
                exit;
            }

            unset($_SESSION['guest_paypal_orders'][$order_id], $_SESSION['guest_pp_pay_token'][$invoice_id]);
            echo json_encode([
                'ok' => true,
                'redirect' => 'guest_view_invoice.php?invoice_id=' . $invoice_id . '&url_key=' . rawurlencode($url_key),
            ]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid PayPal action.']);
        exit;
    }

    if ($action === 'redirect_checkout' && $provider) {
        $invoice_id = intval($_GET['invoice_id'] ?? 0);
        $url_key = (string) ($_GET['url_key'] ?? '');
        $invoice = billingRow('invoice', $invoice_id, false);
        $state = $invoice ? invoiceState($invoice, invoicePaid($invoice_id)) : null;
        if ($invoice && (string) $invoice['invoice_url_key'] !== '' && hash_equals((string) $invoice['invoice_url_key'], $url_key)
            && invoiceCanPay($invoice, $state) && billingOnlinePayAllowed($invoice['invoice_amount'], $provider)) {
            $client_id = intval($invoice['client_id']);
            $balance_cents = intval(round($state['balance'] * 100));
            $fee_cents = billingCardFeeCents($balance_cents, billingCardFeeFor($client_id, $provider));
            $total_cents = $balance_cents + $fee_cents;
            $currency = strtoupper((string) $invoice['invoice_currency_code']);
            $number = billingNumber('invoice', $invoice);
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? $config_base_url;
            $return_url = "$scheme://$host/guest/guest_pay_invoice_paypal.php?action=return&invoice_id=$invoice_id&url_key=" . rawurlencode($url_key);
            $cancel_url = "$scheme://$host/guest/guest_pay_invoice_paypal.php?invoice_id=$invoice_id&url_key=" . rawurlencode($url_key);
            $order = paypalCreateOrder(
                $provider,
                $total_cents,
                $currency,
                "Invoice $number",
                json_encode(['invoice_id' => $invoice_id, 'client_id' => $client_id, 'cents' => $balance_cents, 'fee_cents' => $fee_cents]),
                "inv-$invoice_id",
                $return_url,
                $cancel_url
            );
            if ($order['ok'] && $order['approve_url'] !== '') {
                $_SESSION['guest_paypal_orders'][$order['order_id']] = [
                    'invoice_id' => $invoice_id,
                    'url_key' => $url_key,
                    'client_id' => $client_id,
                    'cents' => $balance_cents,
                    'fee_cents' => $fee_cents,
                    'total_cents' => $total_cents,
                    'currency' => $currency,
                ];
                header('Location: ' . $order['approve_url']);
                exit;
            }
        }
    }

    if ($action === 'return' && $provider) {
        $order_id = trim((string) ($_GET['token'] ?? ''));
        $meta = $_SESSION['guest_paypal_orders'][$order_id] ?? null;
        if ($meta) {
            $invoice_id = intval($meta['invoice_id']);
            $url_key = (string) $meta['url_key'];
            $cap = paypalCaptureOrder($provider, $order_id);
            if ($cap['ok'] && intval($cap['amount_cents']) === intval($meta['total_cents']) && strtoupper($cap['currency']) === strtoupper($meta['currency'])) {
                $fees_split = intval($meta['fee_cents']) > 0 ? [$invoice_id => intval($meta['fee_cents'])] : [];
                $booked = billingBookOnlinePayment(
                    'PayPal',
                    $provider,
                    $cap['capture_id'],
                    intval($meta['client_id']),
                    [$invoice_id => intval($meta['cents'])],
                    $fees_split,
                    $meta['currency'],
                    $cap['date'],
                    paypalProviderMode($provider) === 'live',
                    ['name' => '', 'how' => 'PayPal', 'via' => "via guest link ($ip)"]
                );
                if ($booked['ok']) {
                    unset($_SESSION['guest_paypal_orders'][$order_id], $_SESSION['guest_pp_pay_token'][$invoice_id]);
                    redirect('guest_view_invoice.php?invoice_id=' . $invoice_id . '&url_key=' . rawurlencode($url_key));
                }
            }
        }
    }
}

require_once 'includes/inc_all_guest.php';

$provider = billingProviderByName('PayPal');
$invoice_id = intval($_GET['invoice_id'] ?? 0);
$url_key = (string) ($_GET['url_key'] ?? '');

if (!$provider) {
    echo guestDeadEndHtml('fab fa-paypal', "PayPal isn't available just now", "Get in touch to pay this invoice another way.");
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

$client_id = intval($invoice['client_id']);
$currency = strtoupper((string) $invoice['invoice_currency_code']);
$balance_to_pay = $state['balance'];
$fee_rates = billingCardFeeFor($client_id, $provider);
$card_fee = billingCardFeeCents(intval(round($balance_to_pay * 100)), $fee_rates) / 100;
$total_to_pay = $balance_to_pay + $card_fee;
$items = billingItems('invoice', $invoice_id);
$pay_token = bin2hex(random_bytes(16));
$_SESSION['guest_pp_pay_token'][$invoice_id] = $pay_token;
$sdk_url = paypalJsSdkBase(paypalProviderMode($provider)) . '?client-id=' . rawurlencode($provider['payment_provider_public_key']) . '&currency=' . rawurlencode($currency) . '&intent=capture';
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
                            <td colspan="2">Processing fee <div class="small text-body-secondary"><?= escapeHtml(billingCardFeeTerms($fee_rates, $currency)) ?></div></td>
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
                    <p class="small text-body-secondary">Includes a <?= escapeHtml(billingMoney($card_fee, $currency)) ?> processing fee.</p>
                <?php } ?>
                <div id="paypal-checkout-box"
                    data-invoice-id="<?= $invoice_id ?>"
                    data-url-key="<?= escapeHtml($url_key) ?>"
                    data-pay-token="<?= escapeHtml($pay_token) ?>">
                    <div id="paypal-button-container" class="mb-3"></div>
                    <div id="paypal-message" class="alert alert-danger d-none mb-3" role="alert"></div>
                    <div class="text-center">
                        <a class="btn btn-outline-primary btn-sm" href="guest_pay_invoice_paypal.php?action=redirect_checkout&invoice_id=<?= $invoice_id ?>&url_key=<?= rawurlencode($url_key) ?>">
                            <i class="fab fa-paypal me-1"></i>Or continue on PayPal website
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script data-cfasync="false" src="<?= staticFileUrl('/js/guest_pay_invoice_paypal.js') ?>"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
