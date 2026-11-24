<?php

function paypalApiBase($mode) {
    return $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}

function paypalJsSdkBase($mode) {
    return 'https://www.paypal.com/sdk/js';
}

function paypalProviderMode(array $provider) {
    $mode = trim((string) ($provider['payment_provider_mode'] ?? ''));
    return $mode === 'test' ? 'test' : 'live';
}

function paypalAccessToken($client_id, $client_secret, $mode) {
    static $cache = [];

    if (function_exists('devModeOn') && devModeOn()) {
        return ['ok' => false, 'token' => '', 'error' => 'Development mode - PayPal payments are off on this server.'];
    }

    $client_id = trim((string) $client_id);
    $client_secret = trim((string) $client_secret);
    $cache_key = hash('sha256', "$mode|$client_id|$client_secret");
    if (isset($cache[$cache_key]) && $cache[$cache_key]['exp'] > time() + 30) {
        return ['ok' => true, 'token' => $cache[$cache_key]['token'], 'error' => ''];
    }

    $ch = curl_init(paypalApiBase($mode) . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_USERPWD => $client_id . ':' . $client_secret,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $curl_err = curl_error($ch);
    $status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    curl_close($ch);

    if ($errno !== 0 || $raw === false) {
        return ['ok' => false, 'token' => '', 'error' => 'PayPal could not be reached - ' . ($curl_err ?: 'connection failed')];
    }

    $data = json_decode((string) $raw, true);
    if ($status >= 200 && $status < 300 && !empty($data['access_token'])) {
        $expires_in = max(60, intval($data['expires_in'] ?? 300));
        $cache[$cache_key] = [
            'token' => (string) $data['access_token'],
            'exp' => time() + $expires_in,
        ];
        return ['ok' => true, 'token' => (string) $data['access_token'], 'error' => ''];
    }

    $msg = trim((string) ($data['error_description'] ?? ($data['error'] ?? '')));
    if ($msg === '') {
        $msg = "PayPal returned HTTP $status";
    }
    return ['ok' => false, 'token' => '', 'error' => $msg];
}

function paypalApiRequest($method, $path, $client_id, $client_secret, $mode, $body = null, array $extra_headers = []) {
    $auth = paypalAccessToken($client_id, $client_secret, $mode);
    if (!$auth['ok']) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => $auth['error']];
    }

    $headers = array_merge([
        'Authorization: Bearer ' . $auth['token'],
        'Content-Type: application/json',
        'Accept: application/json',
    ], $extra_headers);

    $ch = curl_init(paypalApiBase($mode) . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper((string) $method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = is_string($body) ? $body : json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $curl_err = curl_error($ch);
    $status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    curl_close($ch);

    if ($errno !== 0 || $raw === false) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'PayPal could not be reached - ' . ($curl_err ?: 'connection failed')];
    }

    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        $data = [];
    }
    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => ''];
    }

    $msg = trim((string) ($data['message'] ?? ''));
    if (!empty($data['details'][0]['description'])) {
        $msg = trim((string) $data['details'][0]['description']);
    }
    if ($msg === '') {
        $msg = trim((string) ($data['name'] ?? "PayPal returned HTTP $status"));
    }
    return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => $msg];
}

function paypalTestConnection($client_id, $client_secret, $mode) {
    $auth = paypalAccessToken($client_id, $client_secret, $mode);
    if (!$auth['ok']) {
        return [
            'ok' => false,
            'error' => 'PayPal did not accept those credentials for ' . ($mode === 'live' ? 'Live' : 'Sandbox') . ': ' . $auth['error'],
            'account' => [],
        ];
    }
    return [
        'ok' => true,
        'error' => '',
        'account' => [
            'name' => 'PayPal (' . ($mode === 'live' ? 'Live' : 'Sandbox') . ')',
            'email' => '',
            'country' => '',
            'id' => substr(trim((string) $client_id), 0, 12),
        ],
    ];
}

function paypalCreateOrder(array $provider, $amount_cents, $currency, $description, $custom_id, $reference_id, $return_url = '', $cancel_url = '') {
    $amount_cents = intval($amount_cents);
    if ($amount_cents <= 0) {
        return ['ok' => false, 'order_id' => '', 'approve_url' => '', 'order' => [], 'error' => 'Invalid payment amount'];
    }
    $currency = strtoupper((string) $currency);
    $payload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => mb_substr((string) $reference_id, 0, 256),
            'custom_id' => mb_substr((string) $custom_id, 0, 127),
            'description' => mb_substr((string) $description, 0, 127),
            'amount' => [
                'currency_code' => $currency,
                'value' => number_format($amount_cents / 100, 2, '.', ''),
            ],
        ]],
        'application_context' => [
            'shipping_preference' => 'NO_SHIPPING',
            'user_action' => 'PAY_NOW',
        ],
    ];
    if ($return_url !== '' && $cancel_url !== '') {
        $payload['application_context']['return_url'] = $return_url;
        $payload['application_context']['cancel_url'] = $cancel_url;
    }

    $res = paypalApiRequest(
        'POST',
        '/v2/checkout/orders',
        $provider['payment_provider_public_key'],
        $provider['payment_provider_private_key'],
        paypalProviderMode($provider),
        $payload
    );
    if (!$res['ok'] || empty($res['data']['id'])) {
        return ['ok' => false, 'order_id' => '', 'approve_url' => '', 'order' => [], 'error' => $res['error'] ?: 'Could not create PayPal order'];
    }

    $approve_url = '';
    foreach ((array) ($res['data']['links'] ?? []) as $link) {
        if (($link['rel'] ?? '') === 'approve' && !empty($link['href'])) {
            $approve_url = (string) $link['href'];
            break;
        }
    }

    return [
        'ok' => true,
        'order_id' => (string) $res['data']['id'],
        'approve_url' => $approve_url,
        'order' => $res['data'],
        'error' => '',
    ];
}

function paypalCaptureOrder(array $provider, $order_id) {
    $order_id = trim((string) $order_id);
    if ($order_id === '') {
        return ['ok' => false, 'error' => 'Missing PayPal order ID', 'capture_id' => '', 'amount_cents' => 0, 'currency' => '', 'fee_amount' => 0.0, 'fee_currency' => '', 'date' => date('Y-m-d')];
    }

    $res = paypalApiRequest(
        'POST',
        '/v2/checkout/orders/' . rawurlencode($order_id) . '/capture',
        $provider['payment_provider_public_key'],
        $provider['payment_provider_private_key'],
        paypalProviderMode($provider),
        '{}'
    );
    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error'] ?: 'PayPal capture failed', 'capture_id' => '', 'amount_cents' => 0, 'currency' => '', 'fee_amount' => 0.0, 'fee_currency' => '', 'date' => date('Y-m-d')];
    }

    $data = $res['data'];
    $capture = $data['purchase_units'][0]['payments']['captures'][0] ?? null;
    $status = strtoupper((string) ($capture['status'] ?? ($data['status'] ?? '')));
    if (!$capture || $status !== 'COMPLETED') {
        return ['ok' => false, 'error' => "PayPal payment was not completed (status: $status)", 'capture_id' => '', 'amount_cents' => 0, 'currency' => '', 'fee_amount' => 0.0, 'fee_currency' => '', 'date' => date('Y-m-d')];
    }

    $amount_cents = intval(round(floatval($capture['amount']['value'] ?? 0) * 100));
    $currency = strtoupper((string) ($capture['amount']['currency_code'] ?? ''));
    $fee_node = $capture['seller_receivable_breakdown']['paypal_fee'] ?? null;
    $fee_amount = $fee_node ? round(floatval($fee_node['value'] ?? 0), 2) : 0.0;
    $fee_currency = $fee_node ? strtoupper((string) ($fee_node['currency_code'] ?? $currency)) : $currency;
    $created = !empty($capture['create_time']) ? strtotime((string) $capture['create_time']) : time();

    return [
        'ok' => true,
        'error' => '',
        'capture_id' => (string) ($capture['id'] ?? ''),
        'order_id' => (string) ($data['id'] ?? $order_id),
        'amount_cents' => $amount_cents,
        'currency' => $currency,
        'fee_amount' => $fee_amount,
        'fee_currency' => $fee_currency,
        'date' => date('Y-m-d', $created ?: time()),
        'capture' => $capture,
    ];
}

function paypalGetCapture(array $provider, $capture_id) {
    $res = paypalApiRequest(
        'GET',
        '/v2/payments/captures/' . rawurlencode((string) $capture_id),
        $provider['payment_provider_public_key'],
        $provider['payment_provider_private_key'],
        paypalProviderMode($provider)
    );
    if (!$res['ok'] || empty($res['data']['id'])) {
        return ['ok' => false, 'capture' => [], 'error' => $res['error'] ?: 'Could not retrieve PayPal capture'];
    }
    return ['ok' => true, 'capture' => $res['data'], 'error' => ''];
}

function paypalRefundCapture(array $provider, $capture_id, $amount_cents, $currency) {
    $payload = [
        'amount' => [
            'value' => number_format(intval($amount_cents) / 100, 2, '.', ''),
            'currency_code' => strtoupper((string) $currency),
        ],
    ];
    $res = paypalApiRequest(
        'POST',
        '/v2/payments/captures/' . rawurlencode((string) $capture_id) . '/refund',
        $provider['payment_provider_public_key'],
        $provider['payment_provider_private_key'],
        paypalProviderMode($provider),
        $payload
    );
    if (!$res['ok'] || empty($res['data']['id'])) {
        return ['ok' => false, 'refund' => [], 'error' => $res['error'] ?: 'PayPal refund failed'];
    }
    return ['ok' => true, 'refund' => $res['data'], 'error' => ''];
}
