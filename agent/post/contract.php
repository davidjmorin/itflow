<?php
/*
 * ITFlow - GET/POST request handler for Contracts
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_contract'])) {

    validateCSRFToken();

    $client_id = intval($_POST['client_id']);
    
    $client_sql = mysqli_query($mysqli, "
        SELECT client_name, 
        (SELECT location_address FROM locations WHERE location_client_id = $client_id AND location_primary = 1 LIMIT 1) AS location_address,
        (SELECT contact_email FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1 LIMIT 1) AS contact_email,
        (SELECT contact_phone FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1 LIMIT 1) AS contact_phone
        FROM clients WHERE client_id = $client_id LIMIT 1
    ");
    $client_row = mysqli_fetch_assoc($client_sql);
    
    $client_name = escapeSql($client_row['client_name']);
    $client_address = escapeSql($client_row['location_address']);
    $client_email = escapeSql($client_row['contact_email']);
    $client_phone = escapeSql($client_row['contact_phone']);

    $name = escapeSql($_POST['name']);
    $status = escapeSql($_POST['status']);
    $type = escapeSql($_POST['type']);
    
    $start_date = escapeSql($_POST['start_date']);
    if (empty($start_date)) { $start_date = 'NULL'; } else { $start_date = "'$start_date'"; }
    
    $end_date = escapeSql($_POST['end_date']);
    if (empty($end_date)) { $end_date = 'NULL'; } else { $end_date = "'$end_date'"; }

    $renewal_frequency = escapeSql($_POST['renewal_frequency']);
    $support_hours = escapeSql($_POST['support_hours']);
    $details = mysqli_escape_string($mysqli, $_POST['details']);

    $sla_low_resp = intval($_POST['sla_low_response_time']);
    $sla_med_resp = intval($_POST['sla_medium_response_time']);
    $sla_high_resp = intval($_POST['sla_high_response_time']);
    $sla_low_res = intval($_POST['sla_low_resolution_time']);
    $sla_med_res = intval($_POST['sla_medium_resolution_time']);
    $sla_high_res = intval($_POST['sla_high_resolution_time']);
    
    $rate_standard = floatval($_POST['rate_standard']);
    $rate_after_hours = floatval($_POST['rate_after_hours']);
    $net_terms = escapeSql($_POST['net_terms']);

    mysqli_query($mysqli, "
        INSERT INTO contracts SET
        contract_client_id = $client_id,
        contract_client_name = '$client_name',
        contract_client_address = '$client_address',
        contract_client_email = '$client_email',
        contract_client_phone = '$client_phone',
        contract_name = '$name',
        contract_status = '$status',
        contract_type = '$type',
        contract_start_date = $start_date,
        contract_end_date = $end_date,
        contract_renewal_frequency = '$renewal_frequency',
        contract_details = '$details',
        contract_sla_low_response_time = $sla_low_resp,
        contract_sla_medium_response_time = $sla_med_resp,
        contract_sla_high_response_time = $sla_high_resp,
        contract_sla_low_resolution_time = $sla_low_res,
        contract_sla_medium_resolution_time = $sla_med_res,
        contract_sla_high_resolution_time = $sla_high_res,
        contract_rate_standard = $rate_standard,
        contract_rate_after_hours = $rate_after_hours,
        contract_support_hours = '$support_hours',
        contract_net_terms = '$net_terms',
        contract_created_at = NOW()
    ");

    $contract_id = mysqli_insert_id($mysqli);

    logAudit("Contract", "Create", "$session_name created contract $name for client $client_name", $client_id, $contract_id);
    flashAlert("Contract <strong>$name</strong> created for $client_name");
    
    redirect();
}

if (isset($_POST['edit_contract'])) {

    validateCSRFToken();

    $contract_id = intval($_POST['contract_id']);
    

    
    $name = escapeSql($_POST['name']);
    $status = escapeSql($_POST['status']);
    $type = escapeSql($_POST['type']);
    
    $start_date = escapeSql($_POST['start_date']);
    if (empty($start_date)) { $start_date = 'NULL'; } else { $start_date = "'$start_date'"; }
    
    $end_date = escapeSql($_POST['end_date']);
    if (empty($end_date)) { $end_date = 'NULL'; } else { $end_date = "'$end_date'"; }
    
    $renewal_frequency = escapeSql($_POST['renewal_frequency']);
    $support_hours = escapeSql($_POST['support_hours']);
    $details = mysqli_escape_string($mysqli, $_POST['details']);

    $sla_low_resp = intval($_POST['sla_low_response_time']);
    $sla_med_resp = intval($_POST['sla_medium_response_time']);
    $sla_high_resp = intval($_POST['sla_high_response_time']);
    $sla_low_res = intval($_POST['sla_low_resolution_time']);
    $sla_med_res = intval($_POST['sla_medium_resolution_time']);
    $sla_high_res = intval($_POST['sla_high_resolution_time']);
    
    $rate_standard = floatval($_POST['rate_standard']);
    $rate_after_hours = floatval($_POST['rate_after_hours']);
    $net_terms = escapeSql($_POST['net_terms']);

    $client_id = getFieldById('contracts', $contract_id, 'contract_client_id');
    $client_name = getFieldById('contracts', $contract_id, 'contract_client_name');

    mysqli_query($mysqli, "
        UPDATE contracts SET
        contract_name = '$name',
        contract_status = '$status',
        contract_type = '$type',
        contract_start_date = $start_date,
        contract_end_date = $end_date,
        contract_renewal_frequency = '$renewal_frequency',
        contract_details = '$details',
        contract_sla_low_response_time = $sla_low_resp,
        contract_sla_medium_response_time = $sla_med_resp,
        contract_sla_high_response_time = $sla_high_resp,
        contract_sla_low_resolution_time = $sla_low_res,
        contract_sla_medium_resolution_time = $sla_med_res,
        contract_sla_high_resolution_time = $sla_high_res,
        contract_rate_standard = $rate_standard,
        contract_rate_after_hours = $rate_after_hours,
        contract_support_hours = '$support_hours',
        contract_net_terms = '$net_terms'
        WHERE contract_id = $contract_id
    ");

    logAudit("Contract", "Update", "$session_name updated contract $name for client $client_name", $client_id, $contract_id);
    flashAlert("Contract <strong>$name</strong> updated");
    redirect();
}

if (isset($_GET['archive_contract'])) {

    validateCSRFToken();

    $contract_id = intval($_GET['archive_contract']);
    
    $name = escapeSql(getFieldById('contracts', $contract_id, 'contract_name'));
    $client_id = getFieldById('contracts', $contract_id, 'contract_client_id');

    mysqli_query($mysqli, "
        UPDATE contracts SET contract_archived_at = NOW()
        WHERE contract_id = $contract_id
        LIMIT 1
    ");

    logAudit("Contract", "Archive", "$session_name archived contract $name", $client_id, $contract_id);
    flashAlert("Contract <strong>$name</strong> archived", "danger");
    redirect();
}

if (isset($_GET['restore_contract'])) {

    validateCSRFToken();

    $contract_id = intval($_GET['restore_contract']);

    $name = escapeSql(getFieldById('contracts', $contract_id, 'contract_name'));
    $client_id = getFieldById('contracts', $contract_id, 'contract_client_id');

    mysqli_query($mysqli, "
        UPDATE contracts SET contract_archived_at = NULL
        WHERE contract_id = $contract_id
        LIMIT 1
    ");

    logAudit("Contract", "Restore", "$session_name restored contract $name", $client_id, $contract_id);
    flashAlert("Contract <strong>$name</strong> restored");
    redirect();
}

if (isset($_GET['delete_contract'])) {

    validateCSRFToken();
    
    $contract_id = intval($_GET['delete_contract']);

    $name = escapeSql(getFieldById('contracts', $contract_id, 'contract_name'));
    $client_id = getFieldById('contracts', $contract_id, 'contract_client_id');

    mysqli_query($mysqli, "
        DELETE FROM contracts
        WHERE contract_id = $contract_id
        LIMIT 1
    ");

    logAudit("Contract", "Delete", "$session_name deleted contract $name", $client_id, $contract_id);
    flashAlert("Contract <strong>$name</strong> deleted", "danger");
    redirect();
}

// Link Asset to Contract
if (isset($_POST['link_contract_asset'])) {
    validateCSRFToken();

    $contract_id = intval($_POST['contract_id']);
    $asset_ids = isset($_POST['asset_ids']) ? (array)$_POST['asset_ids'] : [];

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `contract_assets` (`contract_id` INT(11) NOT NULL, `asset_id` INT(11) NOT NULL, PRIMARY KEY (`contract_id`, `asset_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    foreach ($asset_ids as $asset_id) {
        $asset_id = intval($asset_id);
        if ($asset_id > 0) {
            mysqli_query($mysqli, "INSERT IGNORE INTO contract_assets (contract_id, asset_id) VALUES ($contract_id, $asset_id)");
        }
    }

    flashAlert("Asset(s) linked to contract");
    redirect();
}

// Unlink Asset from Contract
if (isset($_GET['unlink_contract_asset'])) {
    validateCSRFToken();

    $contract_id = intval($_GET['contract_id']);
    $asset_id = intval($_GET['unlink_contract_asset']);

    mysqli_query($mysqli, "DELETE FROM contract_assets WHERE contract_id = $contract_id AND asset_id = $asset_id LIMIT 1");

    flashAlert("Asset unlinked from contract", "warning");
    redirect();
}

// Link Service to Contract
if (isset($_POST['link_contract_service'])) {
    validateCSRFToken();

    $contract_id = intval($_POST['contract_id']);
    $service_ids = isset($_POST['service_ids']) ? (array)$_POST['service_ids'] : [];

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `contract_services` (`contract_id` INT(11) NOT NULL, `service_id` INT(11) NOT NULL, PRIMARY KEY (`contract_id`, `service_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    foreach ($service_ids as $service_id) {
        $service_id = intval($service_id);
        if ($service_id > 0) {
            mysqli_query($mysqli, "INSERT IGNORE INTO contract_services (contract_id, service_id) VALUES ($contract_id, $service_id)");
        }
    }

    flashAlert("Service(s) linked to contract");
    redirect();
}

// Unlink Service from Contract
if (isset($_GET['unlink_contract_service'])) {
    validateCSRFToken();

    $contract_id = intval($_GET['contract_id']);
    $service_id = intval($_GET['unlink_contract_service']);

    mysqli_query($mysqli, "DELETE FROM contract_services WHERE contract_id = $contract_id AND service_id = $service_id LIMIT 1");

    flashAlert("Service unlinked from contract", "warning");
    redirect();
}

// Link Vendor to Contract
if (isset($_POST['link_contract_vendor'])) {
    validateCSRFToken();

    $contract_id = intval($_POST['contract_id']);
    $vendor_ids = isset($_POST['vendor_ids']) ? (array)$_POST['vendor_ids'] : [];

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `contract_vendors` (`contract_id` INT(11) NOT NULL, `vendor_id` INT(11) NOT NULL, PRIMARY KEY (`contract_id`, `vendor_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    foreach ($vendor_ids as $vendor_id) {
        $vendor_id = intval($vendor_id);
        if ($vendor_id > 0) {
            mysqli_query($mysqli, "INSERT IGNORE INTO contract_vendors (contract_id, vendor_id) VALUES ($contract_id, $vendor_id)");
        }
    }

    flashAlert("Vendor(s) linked to contract");
    redirect();
}

// Unlink Vendor from Contract
if (isset($_GET['unlink_contract_vendor'])) {
    validateCSRFToken();

    $contract_id = intval($_GET['contract_id']);
    $vendor_id = intval($_GET['unlink_contract_vendor']);

    mysqli_query($mysqli, "DELETE FROM contract_vendors WHERE contract_id = $contract_id AND vendor_id = $vendor_id LIMIT 1");

    flashAlert("Vendor unlinked from contract", "warning");
    redirect();
}

?>
