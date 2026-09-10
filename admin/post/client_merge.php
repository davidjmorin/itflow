<?php

if (isset($_POST['merge_clients'])) {

    validateCSRFToken();

    enforceAdminPermission();

    $source_client_id = intval($_POST['source_client_id'] ?? 0);
    $target_client_id = intval($_POST['target_client_id'] ?? 0);

    if ($source_client_id <= 0 || $target_client_id <= 0) {
        flashAlert("Invalid client selection.", "error");
        redirect();
    }

    if ($source_client_id === $target_client_id) {
        flashAlert("Source and target clients cannot be the same.", "error");
        redirect();
    }

    $sql_source = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $source_client_id");
    $sql_target = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $target_client_id");

    if (mysqli_num_rows($sql_source) === 0 || mysqli_num_rows($sql_target) === 0) {
        flashAlert("One or both clients could not be found.", "error");
        redirect();
    }

    $source_client = mysqli_fetch_assoc($sql_source);
    $target_client = mysqli_fetch_assoc($sql_target);
    $source_name = $source_client['client_name'];
    $target_name = $target_client['client_name'];

    mysqli_begin_transaction($mysqli);

    try {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_client_id = $target_client_id WHERE ticket_client_id = $source_client_id");

        $target_has_primary_contact = mysqli_num_rows(mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_client_id = $target_client_id AND contact_primary = 1")) > 0;
        if ($target_has_primary_contact) {
            mysqli_query($mysqli, "UPDATE contacts SET contact_primary = 0 WHERE contact_client_id = $source_client_id");
        }
        mysqli_query($mysqli, "UPDATE contacts SET contact_client_id = $target_client_id WHERE contact_client_id = $source_client_id");

        $target_has_primary_location = mysqli_num_rows(mysqli_query($mysqli, "SELECT location_id FROM locations WHERE location_client_id = $target_client_id AND location_primary = 1")) > 0;
        if ($target_has_primary_location) {
            mysqli_query($mysqli, "UPDATE locations SET location_primary = 0 WHERE location_client_id = $source_client_id");
        }
        mysqli_query($mysqli, "UPDATE locations SET location_client_id = $target_client_id WHERE location_client_id = $source_client_id");

        mysqli_query($mysqli, "UPDATE assets SET asset_client_id = $target_client_id WHERE asset_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE invoices SET invoice_client_id = $target_client_id WHERE invoice_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE recurring_invoices SET recurring_invoice_client_id = $target_client_id WHERE recurring_invoice_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE quotes SET quote_client_id = $target_client_id WHERE quote_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE credits SET credit_client_id = $target_client_id WHERE credit_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE revenues SET revenue_client_id = $target_client_id WHERE revenue_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE expenses SET expense_client_id = $target_client_id WHERE expense_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE recurring_expenses SET recurring_expense_client_id = $target_client_id WHERE recurring_expense_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE trips SET trip_client_id = $target_client_id WHERE trip_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE services SET service_client_id = $target_client_id WHERE service_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_client_id = $target_client_id WHERE recurring_ticket_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE contracts SET contract_client_id = $target_client_id WHERE contract_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE software SET software_client_id = $target_client_id WHERE software_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE vendors SET vendor_client_id = $target_client_id WHERE vendor_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE networks SET network_client_id = $target_client_id WHERE network_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE racks SET rack_client_id = $target_client_id WHERE rack_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE domains SET domain_client_id = $target_client_id WHERE domain_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE certificates SET certificate_client_id = $target_client_id WHERE certificate_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE credentials SET credential_client_id = $target_client_id WHERE credential_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE folders SET folder_client_id = $target_client_id WHERE folder_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE files SET file_client_id = $target_client_id WHERE file_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE documents SET document_client_id = $target_client_id WHERE document_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE calendar_events SET event_client_id = $target_client_id WHERE event_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE projects SET project_client_id = $target_client_id WHERE project_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE shared_items SET item_client_id = $target_client_id WHERE item_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE leads SET lead_converted_client_id = $target_client_id WHERE lead_converted_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE client_notes SET client_note_client_id = $target_client_id WHERE client_note_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE logs SET log_client_id = $target_client_id WHERE log_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE notifications SET notification_client_id = $target_client_id WHERE notification_client_id = $source_client_id");
        mysqli_query($mysqli, "UPDATE client_saved_payment_methods SET saved_payment_client_id = $target_client_id WHERE saved_payment_client_id = $source_client_id");

        mysqli_query($mysqli, "DELETE FROM sla_assignments WHERE sla_assignment_client_id = $source_client_id AND sla_assignment_priority IN (SELECT sla_assignment_priority FROM (SELECT sla_assignment_priority FROM sla_assignments WHERE sla_assignment_client_id = $target_client_id) AS tmp)");
        mysqli_query($mysqli, "UPDATE sla_assignments SET sla_assignment_client_id = $target_client_id WHERE sla_assignment_client_id = $source_client_id");

        mysqli_query($mysqli, "INSERT IGNORE INTO client_tags (client_id, tag_id) SELECT $target_client_id, tag_id FROM client_tags WHERE client_id = $source_client_id");
        mysqli_query($mysqli, "DELETE FROM client_tags WHERE client_id = $source_client_id");

        mysqli_query($mysqli, "INSERT IGNORE INTO user_client_permissions (user_id, client_id) SELECT user_id, $target_client_id FROM user_client_permissions WHERE client_id = $source_client_id");
        mysqli_query($mysqli, "DELETE FROM user_client_permissions WHERE client_id = $source_client_id");

        mysqli_query($mysqli, "INSERT IGNORE INTO client_payment_provider (client_id, payment_provider_id, payment_provider_client, client_payment_provider_created_at) SELECT $target_client_id, payment_provider_id, payment_provider_client, client_payment_provider_created_at FROM client_payment_provider WHERE client_id = $source_client_id");
        mysqli_query($mysqli, "DELETE FROM client_payment_provider WHERE client_id = $source_client_id");

        $source_notes = trim($source_client['client_notes'] ?? '');
        $target_notes = trim($target_client['client_notes'] ?? '');
        if (!empty($source_notes)) {
            $combined_notes = empty($target_notes) ? $source_notes : $target_notes . "\n\n--- Merged from " . $source_name . " ---\n" . $source_notes;
            mysqli_query($mysqli, "UPDATE clients SET client_notes = '" . escapeSql($combined_notes) . "' WHERE client_id = $target_client_id");
        }

        $meta_updates = [];
        if (empty($target_client['client_website']) && !empty($source_client['client_website'])) {
            $meta_updates[] = "client_website = '" . escapeSql($source_client['client_website']) . "'";
        }
        if (empty($target_client['client_referral']) && !empty($source_client['client_referral'])) {
            $meta_updates[] = "client_referral = '" . escapeSql($source_client['client_referral']) . "'";
        }
        if (empty($target_client['client_tax_id_number']) && !empty($source_client['client_tax_id_number'])) {
            $meta_updates[] = "client_tax_id_number = '" . escapeSql($source_client['client_tax_id_number']) . "'";
        }
        if (empty($target_client['client_abbreviation']) && !empty($source_client['client_abbreviation'])) {
            $meta_updates[] = "client_abbreviation = '" . escapeSql($source_client['client_abbreviation']) . "'";
        }
        if ((floatval($target_client['client_rate']) == 0) && floatval($source_client['client_rate']) > 0) {
            $meta_updates[] = "client_rate = " . floatval($source_client['client_rate']);
        }
        if (!empty($meta_updates)) {
            mysqli_query($mysqli, "UPDATE clients SET " . implode(", ", $meta_updates) . " WHERE client_id = $target_client_id");
        }

        $source_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/clients/$source_client_id";
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/clients/$target_client_id";
        if (is_dir($source_dir)) {
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0775, true);
            }
            $items = scandir($source_dir);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $src_path = "$source_dir/$item";
                $dest_path = "$target_dir/$item";
                if (is_file($src_path)) {
                    if ($item === 'index.php') {
                        if (!file_exists($dest_path)) {
                            copy($src_path, $dest_path);
                        }
                        unlink($src_path);
                        continue;
                    }
                    if (file_exists($dest_path)) {
                        $new_item = "merged_" . uniqid() . "_" . $item;
                        $new_dest_path = "$target_dir/$new_item";
                        if (rename($src_path, $new_dest_path)) {
                            mysqli_query($mysqli, "UPDATE files SET file_reference_name = '$new_item' WHERE file_client_id = $target_client_id AND file_reference_name = '$item'");
                            mysqli_query($mysqli, "UPDATE contacts SET contact_photo = '$new_item' WHERE contact_client_id = $target_client_id AND contact_photo = '$item'");
                            mysqli_query($mysqli, "UPDATE assets SET asset_photo = '$new_item' WHERE asset_client_id = $target_client_id AND asset_photo = '$item'");
                            mysqli_query($mysqli, "UPDATE racks SET rack_photo = '$new_item' WHERE rack_client_id = $target_client_id AND rack_photo = '$item'");
                            mysqli_query($mysqli, "UPDATE locations SET location_photo = '$new_item' WHERE location_client_id = $target_client_id AND location_photo = '$item'");
                        }
                    } else {
                        rename($src_path, $dest_path);
                    }
                }
            }
            removeDirectory($source_dir);
        }

        mysqli_query($mysqli, "DELETE FROM clients WHERE client_id = $source_client_id");

        mysqli_commit($mysqli);

        logAudit("Client", "Merge", "$session_name merged Client $source_name ($source_client_id) into $target_name ($target_client_id)", $target_client_id);

        flashAlert("Client <strong>" . escapeHtml($source_name) . "</strong> successfully merged into <strong>" . escapeHtml($target_name) . "</strong>");

        redirect("/agent/client_overview.php?client_id=$target_client_id");

    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        logApp("Client Merge", "error", "Failed to merge client $source_client_id into $target_client_id: " . $e->getMessage());
        flashAlert("Failed to merge clients: " . escapeHtml($e->getMessage()), "error");
        redirect();
    }
}
