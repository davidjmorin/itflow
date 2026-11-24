<?php

function squareApiBase($mode) {
    return $mode === 'live' ? 'https://connect.squareup.com' : 'https://connect.squareupsandbox.com';
}

function squareWebSdkUrl($mode) {
    return $mode === 'live' ? 'https://web.squarecdn.com/v1/square.js' : 'https://sandbox.web.squarecdn.com/v1/square.js';
}

function squareProviderExtra(array $provider) {
    $raw = trim((string) ($provider['payment_provider_extra'] ?? ''));
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function squareProviderMode(array $provider) {
    $mode = trim((string) ($provider['payment_provider_mode'] ?? ''));
    if ($mode === 'live' || $mode === 'test') {
        return $mode;
    }
    $pub = trim((string) ($provider['payment_provider_public_key'] ?? ''));
    if (stripos($pub, 'sandbox-') === 0) {
        return 'test';
    }
    return 'live';
}

function squareIdempotencyKey($raw) {
    $raw = (string) $raw;
    if (strlen($raw) <= 45 && preg_match('/^[A-Za-z0-9_-]+$/', $raw)) {
        return $raw;
    }
    return substr(hash('sha256', $raw), 0, 40);
}

function squareApiRequest($method, $path, $access_token, $mode, $body = null) {
    if (function_exists('devModeOn') && devModeOn()) {
        return [
            'ok' => false,
            'status' => 0,
            'data' => [],
            'error' => 'Development mode - Square and card payments are off on this server.',
            'errors' => [],
        ];
    }

    $url = squareApiBase($mode) . $path;
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . trim((string) $access_token),
        'Square-Version: 2024-10-17',
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper((string) $method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $curl_err = curl_error($ch);
    $status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    curl_close($ch);

    if ($errno !== 0 || $raw === false) {
        return [
            'ok' => false,
            'status' => 0,
            'data' => [],
            'error' => 'Square could not be reached - ' . ($curl_err ?: 'connection failed'),
            'errors' => [],
        ];
    }

    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        $data = [];
    }
    $errors = is_array($data['errors'] ?? null) ? $data['errors'] : [];
    if ($status >= 200 && $status < 300 && !$errors) {
        return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => '', 'errors' => []];
    }

    $msg = '';
    if ($errors) {
        $first = $errors[0];
        $msg = trim((string) ($first['detail'] ?? ($first['code'] ?? 'Square request failed')));
    }
    if ($msg === '') {
        $msg = "Square returned HTTP $status";
    }
    return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => $msg, 'errors' => $errors];
}

function squareTestConnection($access_token, $mode, $location_id = '') {
    $res = squareApiRequest('GET', '/v2/locations', $access_token, $mode);
    if (!$res['ok']) {
        $err = $res['status'] === 401
            ? 'Square did not accept that Access Token for ' . ($mode === 'live' ? 'Production' : 'Sandbox') . '.'
            : $res['error'];
        return ['ok' => false, 'error' => $err, 'account' => []];
    }

    $locations = is_array($res['data']['locations'] ?? null) ? $res['data']['locations'] : [];
    if (!$locations) {
        return ['ok' => false, 'error' => 'No Square business locations were found on that account.', 'account' => []];
    }

    $location_id = trim((string) $location_id);
    $chosen = null;
    if ($location_id !== '') {
        foreach ($locations as $loc) {
            if ((string) ($loc['id'] ?? '') === $location_id) {
                $chosen = $loc;
                break;
            }
        }
        if (!$chosen) {
            return ['ok' => false, 'error' => "Square Location ID '$location_id' was not found on this account.", 'account' => []];
        }
    } else {
        foreach ($locations as $loc) {
            if (strtoupper((string) ($loc['status'] ?? '')) === 'ACTIVE') {
                $chosen = $loc;
                break;
            }
        }
        if (!$chosen) {
            $chosen = $locations[0];
        }
    }

    return [
        'ok' => true,
        'error' => '',
        'location_id' => (string) ($chosen['id'] ?? ''),
        'account' => [
            'name' => (string) ($chosen['business_name'] ?? ($chosen['name'] ?? 'Square')),
            'email' => (string) ($chosen['business_email'] ?? ''),
            'country' => (string) ($chosen['country'] ?? ''),
            'id' => (string) ($chosen['id'] ?? ''),
        ],
    ];
}

function squareResolveLocationId(array &$provider) {
    global $mysqli;
    $extra = squareProviderExtra($provider);
    $location_id = trim((string) ($extra['location_id'] ?? ''));
    if ($location_id !== '') {
        return $location_id;
    }
    $check = squareTestConnection((string) ($provider['payment_provider_private_key'] ?? ''), squareProviderMode($provider));
    if (!$check['ok'] || empty($check['location_id'])) {
        return '';
    }
    $location_id = (string) $check['location_id'];
    $extra['location_id'] = $location_id;
    $provider_id = intval($provider['payment_provider_id'] ?? 0);
    if ($provider_id > 0) {
        $encoded = mysqli_real_escape_string($mysqli, json_encode($extra));
        mysqli_query($mysqli, "UPDATE payment_providers SET payment_provider_extra = '$encoded' WHERE payment_provider_id = $provider_id");
    }
    $provider['payment_provider_extra'] = json_encode($extra);
    return $location_id;
}

function squareEnsureCustomer(array $provider, $client_id, $contact_name = '', $contact_email = '') {
    global $mysqli;
    $client_id = intval($client_id);
    $provider_id = intval($provider['payment_provider_id'] ?? 0);
    if ($client_id <= 0 || $provider_id <= 0) {
        return ['ok' => false, 'customer_id' => '', 'error' => 'Invalid client or provider'];
    }

    $existing = mysqli_fetch_row(mysqli_query($mysqli, "SELECT payment_provider_client FROM client_payment_provider WHERE client_id = $client_id AND payment_provider_id = $provider_id LIMIT 1"));
    if ($existing && trim((string) $existing[0]) !== '') {
        return ['ok' => true, 'customer_id' => trim((string) $existing[0]), 'error' => ''];
    }

    $client_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT c.client_name, ct.contact_name, ct.contact_email
        FROM clients c
        LEFT JOIN contacts ct ON ct.contact_client_id = c.client_id AND ct.contact_primary = 1
        WHERE c.client_id = $client_id LIMIT 1"));
    $company_name = trim((string) ($client_row['client_name'] ?? ''));
    $given_name = trim((string) ($contact_name !== '' ? $contact_name : ($client_row['contact_name'] ?? '')));
    $email = trim((string) ($contact_email !== '' ? $contact_email : ($client_row['contact_email'] ?? '')));

    $payload = [
        'idempotency_key' => squareIdempotencyKey('itflow-cust-' . $provider_id . '-' . $client_id . '-' . uniqid('', true)),
        'reference_id' => "itflow-client-$client_id",
    ];
    if ($company_name !== '') {
        $payload['company_name'] = mb_substr($company_name, 0, 100);
    }
    if ($given_name !== '') {
        $payload['given_name'] = mb_substr($given_name, 0, 100);
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $payload['email_address'] = $email;
    }

    $res = squareApiRequest('POST', '/v2/customers', $provider['payment_provider_private_key'], squareProviderMode($provider), $payload);
    if (!$res['ok'] || empty($res['data']['customer']['id'])) {
        return ['ok' => false, 'customer_id' => '', 'error' => $res['error'] ?: 'Could not create Square customer'];
    }

    $customer_id = (string) $res['data']['customer']['id'];
    $customer_sql = mysqli_real_escape_string($mysqli, $customer_id);
    mysqli_query($mysqli, "INSERT INTO client_payment_provider SET client_id = $client_id, payment_provider_id = $provider_id, payment_provider_client = '$customer_sql', client_payment_provider_created_at = NOW()");
    return ['ok' => true, 'customer_id' => $customer_id, 'error' => ''];
}

function squareSaveCard(array $provider, $client_id, $source_id, $cardholder_name = '', $postal_code = '', $idempotency_key = '') {
    global $mysqli;
    $client_id = intval($client_id);
    $provider_id = intval($provider['payment_provider_id'] ?? 0);
    $source_id = trim((string) $source_id);
    if ($source_id === '') {
        return ['ok' => false, 'error' => 'Missing card token'];
    }

    $cust = squareEnsureCustomer($provider, $client_id, $cardholder_name);
    if (!$cust['ok']) {
        return ['ok' => false, 'error' => $cust['error']];
    }

    $card_payload = ['customer_id' => $cust['customer_id']];
    if (trim((string) $cardholder_name) !== '') {
        $card_payload['cardholder_name'] = mb_substr(trim((string) $cardholder_name), 0, 120);
    }
    if (trim((string) $postal_code) !== '') {
        $card_payload['billing_address'] = ['postal_code' => mb_substr(trim((string) $postal_code), 0, 16)];
    }

    $body = [
        'idempotency_key' => squareIdempotencyKey($idempotency_key !== '' ? $idempotency_key : ('itflow-card-' . $client_id . '-' . bin2hex(random_bytes(8)))),
        'source_id' => $source_id,
        'card' => $card_payload,
    ];

    $mode = squareProviderMode($provider);
    $res = squareApiRequest('POST', '/v2/cards', $provider['payment_provider_private_key'], $mode, $body);
    if (!$res['ok'] && !empty($res['errors']) && ($res['errors'][0]['code'] ?? '') === 'NOT_FOUND') {
        $cust_sql = mysqli_real_escape_string($mysqli, $cust['customer_id']);
        mysqli_query($mysqli, "DELETE FROM client_payment_provider WHERE client_id = $client_id AND payment_provider_id = $provider_id AND payment_provider_client = '$cust_sql'");
        $cust = squareEnsureCustomer($provider, $client_id, $cardholder_name);
        if ($cust['ok']) {
            $body['card']['customer_id'] = $cust['customer_id'];
            $body['idempotency_key'] = squareIdempotencyKey('itflow-card-retry-' . $client_id . '-' . bin2hex(random_bytes(8)));
            $res = squareApiRequest('POST', '/v2/cards', $provider['payment_provider_private_key'], $mode, $body);
        }
    }

    if (!$res['ok'] || empty($res['data']['card']['id'])) {
        return ['ok' => false, 'error' => $res['error'] ?: 'Could not save card in Square'];
    }

    $card = $res['data']['card'];
    $card_id = (string) $card['id'];
    $brand = strtolower((string) ($card['card_brand'] ?? 'card'));
    $brand_map = [
        'american_express' => 'amex',
        'discover_diners' => 'diners',
        'square_gift_card' => 'card',
        'other_brand' => 'card',
    ];
    $brand_key = $brand_map[$brand] ?? $brand;
    $last4 = preg_replace('/[^0-9]/', '', (string) ($card['last_4'] ?? ''));
    $exp_month = intval($card['exp_month'] ?? 0);
    $exp_year = intval($card['exp_year'] ?? 0);
    $description = "$brand_key - $last4 | Exp $exp_month/$exp_year";

    $card_id_sql = mysqli_real_escape_string($mysqli, $card_id);
    $desc_sql = mysqli_real_escape_string($mysqli, $description);
    mysqli_query($mysqli, "INSERT INTO client_saved_payment_methods SET
        saved_payment_provider_method = '$card_id_sql',
        saved_payment_description = '$desc_sql',
        saved_payment_client_id = $client_id,
        saved_payment_provider_id = $provider_id,
        saved_payment_created_at = NOW()");

    return [
        'ok' => true,
        'saved_payment_id' => intval(mysqli_insert_id($mysqli)),
        'card_id' => $card_id,
        'description' => $description,
        'brand' => $brand_key,
        'last4' => $last4,
        'exp_month' => $exp_month,
        'exp_year' => $exp_year,
    ];
}

function squareDisableCard(array $provider, $card_id) {
    $card_id = trim((string) $card_id);
    if ($card_id === '') {
        return ['ok' => true, 'error' => ''];
    }
    $res = squareApiRequest('POST', '/v2/cards/' . rawurlencode($card_id) . '/disable', $provider['payment_provider_private_key'], squareProviderMode($provider), new \stdClass());
    if (!$res['ok'] && ($res['status'] === 404 || (($res['errors'][0]['code'] ?? '') === 'NOT_FOUND'))) {
        return ['ok' => true, 'error' => ''];
    }
    return ['ok' => $res['ok'], 'error' => $res['error']];
}

function squareCreatePayment(array $provider, $source_id, $amount_cents, $currency, $idempotency_key, array $opts = []) {
    $location_id = squareResolveLocationId($provider);
    if ($location_id === '') {
        return ['ok' => false, 'payment' => [], 'error' => 'Square Location ID is not configured'];
    }
    $amount_cents = intval($amount_cents);
    if ($amount_cents <= 0) {
        return ['ok' => false, 'payment' => [], 'error' => 'Invalid payment amount'];
    }

    $body = [
        'source_id' => (string) $source_id,
        'idempotency_key' => squareIdempotencyKey($idempotency_key),
        'amount_money' => [
            'amount' => $amount_cents,
            'currency' => strtoupper((string) $currency),
        ],
        'location_id' => $location_id,
        'autocomplete' => true,
    ];
    if (!empty($opts['customer_id'])) {
        $body['customer_id'] = (string) $opts['customer_id'];
    }
    if (!empty($opts['reference_id'])) {
        $body['reference_id'] = mb_substr((string) $opts['reference_id'], 0, 40);
    }
    if (!empty($opts['note'])) {
        $body['note'] = mb_substr((string) $opts['note'], 0, 500);
    }
    if (!empty($opts['buyer_email_address']) && filter_var($opts['buyer_email_address'], FILTER_VALIDATE_EMAIL)) {
        $body['buyer_email_address'] = (string) $opts['buyer_email_address'];
    }
    if (!empty($opts['verification_token'])) {
        $body['verification_token'] = (string) $opts['verification_token'];
    }

    $res = squareApiRequest('POST', '/v2/payments', $provider['payment_provider_private_key'], squareProviderMode($provider), $body);
    if (!$res['ok'] || empty($res['data']['payment']['id'])) {
        return ['ok' => false, 'payment' => [], 'error' => $res['error'] ?: 'Square card charge failed'];
    }
    $payment = $res['data']['payment'];
    $status = strtoupper((string) ($payment['status'] ?? ''));
    if (!in_array($status, ['COMPLETED', 'APPROVED'], true)) {
        return ['ok' => false, 'payment' => $payment, 'error' => "Square payment status was $status"];
    }
    return ['ok' => true, 'payment' => $payment, 'error' => ''];
}

function squareGetPayment(array $provider, $payment_id) {
    $res = squareApiRequest('GET', '/v2/payments/' . rawurlencode((string) $payment_id), $provider['payment_provider_private_key'], squareProviderMode($provider));
    if (!$res['ok'] || empty($res['data']['payment'])) {
        return ['ok' => false, 'payment' => [], 'error' => $res['error']];
    }
    return ['ok' => true, 'payment' => $res['data']['payment'], 'error' => ''];
}

function squareRefundPayment(array $provider, $payment_id, $amount_cents, $currency, $idempotency_key) {
    $body = [
        'idempotency_key' => squareIdempotencyKey($idempotency_key),
        'payment_id' => (string) $payment_id,
        'amount_money' => [
            'amount' => intval($amount_cents),
            'currency' => strtoupper((string) $currency),
        ],
    ];
    $res = squareApiRequest('POST', '/v2/refunds', $provider['payment_provider_private_key'], squareProviderMode($provider), $body);
    if (!$res['ok'] || empty($res['data']['refund']['id'])) {
        return ['ok' => false, 'refund' => [], 'error' => $res['error'] ?: 'Square refund failed'];
    }
    return ['ok' => true, 'refund' => $res['data']['refund'], 'error' => ''];
}
