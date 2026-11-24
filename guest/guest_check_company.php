<?php

if (!function_exists('guestFindClientByEmail')) {
    function guestFindClientByEmail($mysqli, string $email): array {
        $email = trim($email);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['matched' => false, 'client_id' => 0, 'client_name' => '', 'contact_id' => 0, 'match_type' => ''];
        }

        $email_esc = mysqli_real_escape_string($mysqli, $email);
        $contact_sql = mysqli_query($mysqli, "
            SELECT contacts.contact_id, clients.client_id, clients.client_name
            FROM contacts
            JOIN clients ON contacts.contact_client_id = clients.client_id
            WHERE LOWER(contacts.contact_email) = LOWER('$email_esc')
              AND contacts.contact_archived_at IS NULL
              AND clients.client_archived_at IS NULL
            LIMIT 1
        ");
        if ($contact_sql && mysqli_num_rows($contact_sql) === 1) {
            $row = mysqli_fetch_assoc($contact_sql);
            return [
                'matched' => true,
                'client_id' => intval($row['client_id']),
                'client_name' => (string)$row['client_name'],
                'contact_id' => intval($row['contact_id']),
                'match_type' => 'contact',
            ];
        }

        $domain_parts = explode('@', $email);
        $domain = strtolower(trim((string)end($domain_parts)));
        $public_providers = [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com',
            'msn.com', 'aol.com', 'icloud.com', 'me.com', 'mail.com',
            'zoho.com', 'proton.me', 'protonmail.com', 'gmx.com'
        ];
        if ($domain !== '' && !in_array($domain, $public_providers, true)) {
            $domain_esc = mysqli_real_escape_string($mysqli, $domain);
            $domain_sql = mysqli_query($mysqli, "
                SELECT clients.client_id, clients.client_name
                FROM domains
                JOIN clients ON domains.domain_client_id = clients.client_id
                WHERE LOWER(domains.domain_name) = '$domain_esc'
                  AND domains.domain_archived_at IS NULL
                  AND clients.client_archived_at IS NULL
                LIMIT 1
            ");
            if ($domain_sql && mysqli_num_rows($domain_sql) === 1) {
                $row = mysqli_fetch_assoc($domain_sql);
                return [
                    'matched' => true,
                    'client_id' => intval($row['client_id']),
                    'client_name' => (string)$row['client_name'],
                    'contact_id' => 0,
                    'match_type' => 'domain',
                ];
            }

            $contact_domain_sql = mysqli_query($mysqli, "
                SELECT DISTINCT clients.client_id, clients.client_name
                FROM contacts
                JOIN clients ON contacts.contact_client_id = clients.client_id
                WHERE LOWER(SUBSTRING_INDEX(contacts.contact_email, '@', -1)) = '$domain_esc'
                  AND contacts.contact_archived_at IS NULL
                  AND clients.client_archived_at IS NULL
                LIMIT 2
            ");
            if ($contact_domain_sql && mysqli_num_rows($contact_domain_sql) === 1) {
                $row = mysqli_fetch_assoc($contact_domain_sql);
                return [
                    'matched' => true,
                    'client_id' => intval($row['client_id']),
                    'client_name' => (string)$row['client_name'],
                    'contact_id' => 0,
                    'match_type' => 'contact_domain',
                ];
            }
        }

        return ['matched' => false, 'client_id' => 0, 'client_name' => '', 'contact_id' => 0, 'match_type' => ''];
    }
}

if (isset($_GET['check_guest_company'])) {
    header('Content-Type: application/json');
    $email = trim($_GET['email'] ?? '');
    $res = guestFindClientByEmail($mysqli, $email);
    $support_email = !empty($config_ticket_from_email) ? $config_ticket_from_email : ($config_mail_from_email ?? '');
    if (empty($support_email)) {
        $s_res = mysqli_query($mysqli, "SELECT config_ticket_from_email, config_mail_from_email FROM settings WHERE company_id = 1 LIMIT 1");
        if ($s_res && ($s_row = mysqli_fetch_assoc($s_res))) {
            $support_email = !empty($s_row['config_ticket_from_email']) ? $s_row['config_ticket_from_email'] : ($s_row['config_mail_from_email'] ?? '');
        }
    }
    echo json_encode([
        'matched' => $res['matched'],
        'client_name' => $res['client_name'],
        'match_type' => $res['match_type'],
        'support_email' => $support_email,
    ]);
    exit;
}
