<?php

if (isset($_GET['get_client_counts'])) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/check_login.php';

    enforceAdminPermission();

    header('Content-Type: application/json');

    $client_id = intval($_GET['client_id'] ?? 0);

    $counts = [
        'tickets' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM tickets WHERE ticket_client_id = $client_id"))['c'] ?? 0),
        'contacts' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM contacts WHERE contact_client_id = $client_id"))['c'] ?? 0),
        'assets' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM assets WHERE asset_client_id = $client_id"))['c'] ?? 0),
        'locations' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM locations WHERE location_client_id = $client_id"))['c'] ?? 0),
        'invoices' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM invoices WHERE invoice_client_id = $client_id"))['c'] ?? 0),
        'recurring_invoices' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id"))['c'] ?? 0),
        'quotes' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM quotes WHERE quote_client_id = $client_id"))['c'] ?? 0),
        'credentials' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM credentials WHERE credential_client_id = $client_id"))['c'] ?? 0),
        'documents' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM documents WHERE document_client_id = $client_id"))['c'] ?? 0),
        'files' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM files WHERE file_client_id = $client_id"))['c'] ?? 0),
        'networks' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM networks WHERE network_client_id = $client_id"))['c'] ?? 0),
        'services' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM services WHERE service_client_id = $client_id"))['c'] ?? 0),
        'contracts' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM contracts WHERE contract_client_id = $client_id"))['c'] ?? 0),
        'software' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM software WHERE software_client_id = $client_id"))['c'] ?? 0),
        'vendors' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM vendors WHERE vendor_client_id = $client_id"))['c'] ?? 0),
        'revenues' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM revenues WHERE revenue_client_id = $client_id"))['c'] ?? 0),
        'expenses' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM expenses WHERE expense_client_id = $client_id"))['c'] ?? 0),
        'calendar_events' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM calendar_events WHERE event_client_id = $client_id"))['c'] ?? 0),
        'client_notes' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM client_notes WHERE client_note_client_id = $client_id"))['c'] ?? 0),
        'racks' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM racks WHERE rack_client_id = $client_id"))['c'] ?? 0),
        'domains' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM domains WHERE domain_client_id = $client_id"))['c'] ?? 0),
        'certificates' => intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM certificates WHERE certificate_client_id = $client_id"))['c'] ?? 0)
    ];

    echo json_encode($counts);
    exit();
}

require_once "includes/inc_all_admin.php";

$selected_source_id = intval($_GET['source_client_id'] ?? 0);
$selected_target_id = intval($_GET['target_client_id'] ?? 0);

$clients_result = mysqli_query($mysqli, "SELECT client_id, client_name, client_archived_at FROM clients ORDER BY client_name ASC");
$clients = [];
while ($row = mysqli_fetch_assoc($clients_result)) {
    $clients[] = $row;
}

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-code-branch mr-2"></i>Merge Clients</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <h5><i class="icon fas fa-info-circle"></i> About Client Merging</h5>
            Merging allows consolidating duplicate client records into a single primary client. All associated data—including tickets, contacts, assets, invoices, quotes, credentials, documents, files, and notes—will be reassigned to the target client. Once the merge completes, the source client will be permanently removed.
        </div>

        <form action="post.php" method="post" id="clientMergeForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="row">
                <div class="col-md-6">
                    <div class="card card-outline card-danger">
                        <div class="card-header">
                            <h4 class="card-title text-danger"><i class="fas fa-arrow-right mr-2"></i>Source Client <small class="text-muted">(Will be merged & deleted)</small></h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="source_client_id">Select Duplicate Client <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <select class="form-control select2" id="source_client_id" name="source_client_id" required>
                                        <option value="">- Select Source Client -</option>
                                        <?php foreach ($clients as $client) { ?>
                                            <option value="<?= intval($client['client_id']) ?>" <?= ($selected_source_id === intval($client['client_id'])) ? 'selected' : '' ?>>
                                                <?= escapeHtml($client['client_name']) ?> <?= $client['client_archived_at'] ? '(Archived)' : '' ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div id="source_counts_box" class="mt-3">
                                <div class="text-muted small">Select a source client above to inspect the records that will be moved.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card-outline card-success">
                        <div class="card-header">
                            <h4 class="card-title text-success"><i class="fas fa-check mr-2"></i>Target Client <small class="text-muted">(Primary - will receive all data)</small></h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="target_client_id">Select Primary Destination Client <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                                    </div>
                                    <select class="form-control select2" id="target_client_id" name="target_client_id" required>
                                        <option value="">- Select Target Client -</option>
                                        <?php foreach ($clients as $client) { ?>
                                            <option value="<?= intval($client['client_id']) ?>" <?= ($selected_target_id === intval($client['client_id'])) ? 'selected' : '' ?>>
                                                <?= escapeHtml($client['client_name']) ?> <?= $client['client_archived_at'] ? '(Archived)' : '' ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div id="target_counts_box" class="mt-3">
                                <div class="text-muted small">Select a target client above to inspect its existing records.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="callout callout-warning">
                <h5><i class="fas fa-exclamation-circle text-warning mr-2"></i>Conflict Resolution Rules</h5>
                <ul class="mb-0 pl-3">
                    <li><strong>Contacts:</strong> If the target client already has a primary contact, contacts moved from the source client become non-primary contacts to prevent conflicts.</li>
                    <li><strong>Locations:</strong> If the target client already has a primary location, locations moved from the source client become secondary locations.</li>
                    <li><strong>Client Notes:</strong> Any notes recorded on the source client profile are appended to the target client's notes.</li>
                    <li><strong>Tags & Permissions:</strong> Duplicate tags, SLA priorities, and user permissions are automatically deduplicated.</li>
                    <li><strong>Files & Uploads:</strong> Files stored in the source client folder are safely transferred to the target client folder.</li>
                </ul>
            </div>

            <div class="alert alert-danger">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="confirm_irreversible" name="confirm_irreversible" required>
                    <label class="custom-control-label font-weight-bold" for="confirm_irreversible">
                        I understand that this action is irreversible. All items from the source client will be merged into the target client, and the source client record will be permanently deleted.
                    </label>
                </div>
            </div>

            <div id="same_client_warning" class="alert alert-warning d-none">
                <i class="fas fa-exclamation-triangle mr-2"></i> Source client and target client cannot be the same.
            </div>

            <button type="submit" name="merge_clients" id="mergeSubmitBtn" class="btn btn-danger font-weight-bold px-4 py-2">
                <i class="fas fa-code-branch mr-2"></i>Execute Client Merge
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sourceSelect = document.getElementById('source_client_id');
    const targetSelect = document.getElementById('target_client_id');
    const sourceBox = document.getElementById('source_counts_box');
    const targetBox = document.getElementById('target_counts_box');
    const submitBtn = document.getElementById('mergeSubmitBtn');
    const sameWarning = document.getElementById('same_client_warning');
    const form = document.getElementById('clientMergeForm');

    function renderCountBadges(data) {
        let totalItems = 0;
        const labels = {
            tickets: 'Tickets',
            contacts: 'Contacts',
            assets: 'Assets',
            locations: 'Locations',
            invoices: 'Invoices',
            recurring_invoices: 'Recurring Invoices',
            quotes: 'Quotes',
            credentials: 'Credentials',
            documents: 'Documents',
            files: 'Files',
            networks: 'Networks',
            services: 'Services',
            contracts: 'Contracts',
            software: 'Software',
            vendors: 'Vendors',
            revenues: 'Revenues',
            expenses: 'Expenses',
            calendar_events: 'Events',
            client_notes: 'Notes',
            racks: 'Racks',
            domains: 'Domains',
            certificates: 'Certificates'
        };

        let html = '<div class="table-responsive"><table class="table table-sm table-striped table-bordered mb-0"><tbody>';
        let rows = [];
        for (const [key, label] of Object.entries(labels)) {
            const count = data[key] || 0;
            totalItems += count;
            if (count > 0) {
                rows.push(`<tr><td>${label}</td><td class="text-right font-weight-bold"><span class="badge badge-primary">${count}</span></td></tr>`);
            }
        }

        if (rows.length === 0) {
            html += '<tr><td class="text-muted text-center py-2">No associated records found for this client.</td></tr>';
        } else {
            html += rows.join('');
            html += `<tr class="bg-light font-weight-bold"><td>Total Items</td><td class="text-right"><span class="badge badge-dark">${totalItems}</span></td></tr>`;
        }
        html += '</tbody></table></div>';
        return html;
    }

    function loadClientCounts(clientId, container, label) {
        if (!clientId) {
            container.innerHTML = `<div class="text-muted small">Select a ${label} client above to inspect records.</div>`;
            return;
        }

        container.innerHTML = '<div class="text-muted small"><i class="fas fa-spinner fa-spin mr-2"></i>Loading record counts...</div>';

        fetch(`client_merge.php?get_client_counts=1&client_id=${clientId}`)
            .then(res => res.json())
            .then(data => {
                container.innerHTML = renderCountBadges(data);
            })
            .catch(() => {
                container.innerHTML = '<div class="text-danger small">Failed to load counts.</div>';
            });
    }

    function validateSelection() {
        const sourceVal = sourceSelect.value;
        const targetVal = targetSelect.value;

        if (sourceVal && targetVal && sourceVal === targetVal) {
            sameWarning.classList.remove('d-none');
            submitBtn.disabled = true;
        } else {
            sameWarning.classList.add('d-none');
            submitBtn.disabled = false;
        }
    }

    sourceSelect.addEventListener('change', function() {
        loadClientCounts(this.value, sourceBox, 'source');
        validateSelection();
    });

    targetSelect.addEventListener('change', function() {
        loadClientCounts(this.value, targetBox, 'target');
        validateSelection();
    });

    if (sourceSelect.value) {
        loadClientCounts(sourceSelect.value, sourceBox, 'source');
    }
    if (targetSelect.value) {
        loadClientCounts(targetSelect.value, targetBox, 'target');
    }
    validateSelection();

    form.addEventListener('submit', function(e) {
        const sourceVal = sourceSelect.value;
        const targetVal = targetSelect.value;
        if (!sourceVal || !targetVal) {
            e.preventDefault();
            alert('Please select both a source client and a target client.');
            return;
        }
        if (sourceVal === targetVal) {
            e.preventDefault();
            alert('Source and target client cannot be the same.');
            return;
        }
        const sourceText = sourceSelect.options[sourceSelect.selectedIndex].text.trim();
        const targetText = targetSelect.options[targetSelect.selectedIndex].text.trim();
        const confirmed = confirm(`Are you sure you want to merge "${sourceText}" into "${targetText}"?\n\nThis will reassign ALL tickets, contacts, assets, and records to "${targetText}".\n"${sourceText}" will be permanently deleted.`);
        if (!confirmed) {
            e.preventDefault();
        }
    });
});
</script>

<?php
require_once "../includes/footer.php";
