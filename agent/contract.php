<?php

if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_url = '';
}

$contract_id = intval($_GET['contract_id'] ?? $_GET['id'] ?? 0);

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `contract_assets` (`contract_id` INT(11) NOT NULL, `asset_id` INT(11) NOT NULL, PRIMARY KEY (`contract_id`, `asset_id`), KEY `contract_id` (`contract_id`), KEY `asset_id` (`asset_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `contract_services` (`contract_id` INT(11) NOT NULL, `service_id` INT(11) NOT NULL, PRIMARY KEY (`contract_id`, `service_id`), KEY `contract_id` (`contract_id`), KEY `service_id` (`service_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `contract_vendors` (`contract_id` INT(11) NOT NULL, `vendor_id` INT(11) NOT NULL, PRIMARY KEY (`contract_id`, `vendor_id`), KEY `contract_id` (`contract_id`), KEY `vendor_id` (`vendor_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$sql = mysqli_query($mysqli, "
    SELECT * FROM contracts
    LEFT JOIN clients ON contract_client_id = client_id
    WHERE contract_id = $contract_id
    LIMIT 1
");

if (!$sql || mysqli_num_rows($sql) == 0) {
    echo "<center><h3 class='text-secondary mt-5'>Contract not found</h3><a class='btn btn-secondary mt-3' href='contracts.php'><i class='fa fa-arrow-left mr-1'></i> Back to Contracts</a></center>";
} else {
    $row = mysqli_fetch_assoc($sql);

    $contract_id = intval($row['contract_id']);
    $contract_name = escapeHtml($row['contract_name']);
    $contract_status = escapeHtml($row['contract_status']);
    $contract_type = escapeHtml($row['contract_type']);
    $contract_details = $row['contract_details'];
    $contract_client_id = intval($row['contract_client_id']);
    $client_id = $contract_client_id > 0 ? $contract_client_id : ($client_id ?? 0);
    $client_name = escapeHtml($row['client_name'] ?? $row['contract_client_name'] ?? 'Client');

    $start_date = escapeHtml($row['contract_start_date']);
    $end_date = escapeHtml($row['contract_end_date']);
    $renewal_frequency = escapeHtml($row['contract_renewal_frequency'] ?: 'Manual');

    $sla_low_resp = intval($row['contract_sla_low_response_time']);
    $sla_med_resp = intval($row['contract_sla_medium_response_time']);
    $sla_high_resp = intval($row['contract_sla_high_response_time']);
    $sla_low_res = intval($row['contract_sla_low_resolution_time']);
    $sla_med_res = intval($row['contract_sla_medium_resolution_time']);
    $sla_high_res = intval($row['contract_sla_high_resolution_time']);

    $rate_standard = floatval($row['contract_rate_standard']);
    $rate_after_hours = floatval($row['contract_rate_after_hours']);
    $support_hours = escapeHtml($row['contract_support_hours'] ?: 'N/A');
    $net_terms = intval($row['contract_net_terms'] ?: 0);

    // Safe Currency Formatter
    $currency_code_display = !empty($session_company_currency) ? $session_company_currency : 'USD';
    if (isset($currency_format) && $currency_format instanceof NumberFormatter) {
        $rate_standard_display = numfmt_format_currency($currency_format, $rate_standard, $currency_code_display);
        $rate_after_hours_display = numfmt_format_currency($currency_format, $rate_after_hours, $currency_code_display);
    } else {
        $rate_standard_display = '$' . number_format($rate_standard, 2);
        $rate_after_hours_display = '$' . number_format($rate_after_hours, 2);
    }

    // Status Badge
    if ($contract_status == 'Active') {
        $status_badge = 'badge-success';
    } elseif ($contract_status == 'Expired') {
        $status_badge = 'badge-danger';
    } elseif ($contract_status == 'Pending') {
        $status_badge = 'badge-warning';
    } else {
        $status_badge = 'badge-secondary';
    }

    $sql_assets = mysqli_query($mysqli, "
        SELECT a.* FROM assets a
        JOIN contract_assets ca ON a.asset_id = ca.asset_id
        WHERE ca.contract_id = $contract_id AND a.asset_archived_at IS NULL
        ORDER BY a.asset_name ASC
    ");
    $linked_asset_count = ($sql_assets && $sql_assets !== true) ? mysqli_num_rows($sql_assets) : 0;

    $sql_services = mysqli_query($mysqli, "
        SELECT s.* FROM services s
        JOIN contract_services cs ON s.service_id = cs.service_id
        WHERE cs.contract_id = $contract_id
        ORDER BY s.service_name ASC
    ");
    $linked_service_count = ($sql_services && $sql_services !== true) ? mysqli_num_rows($sql_services) : 0;

    $sql_vendors = mysqli_query($mysqli, "
        SELECT v.* FROM vendors v
        JOIN contract_vendors cv ON v.vendor_id = cv.vendor_id
        WHERE cv.contract_id = $contract_id AND v.vendor_archived_at IS NULL
        ORDER BY v.vendor_name ASC
    ");
    $linked_vendor_count = ($sql_vendors && $sql_vendors !== true) ? mysqli_num_rows($sql_vendors) : 0;

    $sql_tickets = mysqli_query($mysqli, "
        SELECT * FROM tickets
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_client_id = $contract_client_id AND ticket_archived_at IS NULL
        ORDER BY ticket_id DESC LIMIT 10
    ");
    $ticket_count = ($sql_tickets && $sql_tickets !== true) ? mysqli_num_rows($sql_tickets) : 0;
?>

<div class="row">
    <div class="col-md-4 col-lg-3">
        <div class="card card-dark">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa fa-fw fa-file-contract mr-2"></i>Contract Summary
                </div>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool ajax-modal"
                        data-modal-size="xl"
                        data-modal-url="modals/contract/contract_edit.php?id=<?= $contract_id ?>">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <h4><?= $contract_name ?></h4>
                <p><span class="badge <?= $status_badge ?> p-1"><?= $contract_status ?></span> <span class="badge badge-info p-1"><?= $contract_type ?></span></p>

                <hr>
                <div class="text-muted small mb-1">CLIENT</div>
                <div><strong><a href="client_overview.php?client_id=<?= $client_id ?>"><i class="fa fa-building mr-1"></i><?= $client_name ?></a></strong></div>

                <hr>
                <div class="text-muted small mb-1">CONTRACT DATES</div>
                <div><i class="fa fa-calendar-alt mr-1"></i> <?= $start_date ?: 'Open' ?> to <?= $end_date ?: 'Open' ?></div>
                <div class="small text-secondary">Renewal: <?= $renewal_frequency ?></div>

                <hr>
                <div class="text-muted small mb-1">RATES & BILLING</div>
                <div>Standard Rate: <strong><?= $rate_standard_display ?> /hr</strong></div>
                <div>After Hours: <strong><?= $rate_after_hours_display ?> /hr</strong></div>
                <div>Included Support: <strong><?= $support_hours ?></strong></div>
                <div>Net Terms: <strong><?= $net_terms ?> Days</strong></div>

                <hr>
                <div class="text-muted small mb-1">SLA RESPONSE TARGETS</div>
                <table class="table table-sm table-bordered text-center small mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Low</th>
                            <th>Med</th>
                            <th>High</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= $sla_low_resp ?>m</td>
                            <td><?= $sla_med_resp ?>m</td>
                            <td><?= $sla_high_resp ?>m</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white">
                <a href="contracts.php?client_id=<?= $client_id ?>" class="btn btn-secondary btn-block btn-sm">
                    <i class="fa fa-arrow-left mr-1"></i> All Contracts
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-8 col-lg-9">
        <div class="card card-dark card-outline card-tabs">
            <div class="card-header p-0 pt-1 border-bottom-0">
                <ul class="nav nav-tabs" id="contractTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="tab-scope-link" data-toggle="pill" href="#tab-scope" role="tab">
                            <i class="fa fa-align-left mr-1"></i> Scope of Work
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-assets-link" data-toggle="pill" href="#tab-assets" role="tab">
                            <i class="fa fa-desktop mr-1"></i> Covered Assets <span class="badge badge-secondary ml-1"><?= $linked_asset_count ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-services-link" data-toggle="pill" href="#tab-services" role="tab">
                            <i class="fa fa-cogs mr-1"></i> Covered Services <span class="badge badge-secondary ml-1"><?= $linked_service_count ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-vendors-link" data-toggle="pill" href="#tab-vendors" role="tab">
                            <i class="fa fa-building mr-1"></i> Upstream Vendors <span class="badge badge-secondary ml-1"><?= $linked_vendor_count ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-tickets-link" data-toggle="pill" href="#tab-tickets" role="tab">
                            <i class="fa fa-life-ring mr-1"></i> Client Tickets <span class="badge badge-secondary ml-1"><?= $ticket_count ?></span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="contractTabsContent">

                    <div class="tab-pane fade show active" id="tab-scope" role="tabpanel">
                        <?php if (!empty($contract_details)) { ?>
                            <div class="p-3 bg-light border rounded">
                                <?= $contract_details ?>
                            </div>
                        <?php } else { ?>
                            <div class="text-center text-muted p-5">
                                <i class="fa fa-file-alt fa-3x mb-3 text-secondary"></i>
                                <p>No detailed scope of work added yet.</p>
                                <button type="button" class="btn btn-outline-primary btn-sm ajax-modal"
                                    data-modal-size="xl"
                                    data-modal-url="modals/contract/contract_edit.php?id=<?= $contract_id ?>">
                                    <i class="fa fa-edit mr-1"></i> Add Contract Details
                                </button>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="tab-pane fade" id="tab-assets" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Covered Infrastructure & Devices</h5>
                            <button type="button" class="btn btn-primary btn-sm ajax-modal"
                                data-modal-url="modals/contract/contract_link_asset.php?contract_id=<?= $contract_id ?>">
                                <i class="fa fa-plus mr-1"></i> Link Asset
                            </button>
                        </div>
                        <?php if ($linked_asset_count > 0) { ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Asset Name</th>
                                            <th>Type</th>
                                            <th>Make/Model</th>
                                            <th>Serial Number</th>
                                            <th class="text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($asset = mysqli_fetch_assoc($sql_assets)) { ?>
                                            <tr>
                                                <td>
                                                    <a href="asset.php?asset_id=<?= $asset['asset_id'] ?>" class="text-bold">
                                                        <i class="fa fa-desktop mr-1"></i> <?= escapeHtml($asset['asset_name']) ?>
                                                    </a>
                                                </td>
                                                <td><?= escapeHtml($asset['asset_type']) ?></td>
                                                <td><?= escapeHtml($asset['asset_make'] ?? '') ?> <?= escapeHtml($asset['asset_model'] ?? '') ?></td>
                                                <td><?= escapeHtml($asset['asset_serial'] ?? '') ?></td>
                                                <td class="text-right">
                                                    <a href="post.php?unlink_contract_asset=<?= $asset['asset_id'] ?>&contract_id=<?= $contract_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                       class="btn btn-outline-danger btn-xs confirm-link" title="Unlink Asset">
                                                        <i class="fa fa-unlink"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="alert alert-light text-center border p-4">
                                <i class="fa fa-desktop fa-2x text-muted mb-2"></i>
                                <p class="text-secondary mb-2">No assets are currently linked to this contract.</p>
                                <button type="button" class="btn btn-outline-primary btn-sm ajax-modal"
                                    data-modal-url="modals/contract/contract_link_asset.php?contract_id=<?= $contract_id ?>">
                                    <i class="fa fa-plus mr-1"></i> Link Covered Assets
                                </button>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- Covered Services Tab -->
                    <div class="tab-pane fade" id="tab-services" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Covered Services & Software Licenses</h5>
                            <button type="button" class="btn btn-primary btn-sm ajax-modal"
                                data-modal-url="modals/contract/contract_link_service.php?contract_id=<?= $contract_id ?>">
                                <i class="fa fa-plus mr-1"></i> Link Service
                            </button>
                        </div>
                        <?php if ($linked_service_count > 0) { ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Service Name</th>
                                            <th>Category</th>
                                            <th class="text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($service = mysqli_fetch_assoc($sql_services)) { ?>
                                            <tr>
                                                <td><strong><?= escapeHtml($service['service_name']) ?></strong></td>
                                                <td><?= escapeHtml($service['service_category'] ?? 'Service') ?></td>
                                                <td class="text-right">
                                                    <a href="post.php?unlink_contract_service=<?= $service['service_id'] ?>&contract_id=<?= $contract_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                       class="btn btn-outline-danger btn-xs confirm-link" title="Unlink Service">
                                                        <i class="fa fa-unlink"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="alert alert-light text-center border p-4">
                                <i class="fa fa-cogs fa-2x text-muted mb-2"></i>
                                <p class="text-secondary mb-2">No services are currently linked to this contract.</p>
                                <button type="button" class="btn btn-outline-primary btn-sm ajax-modal"
                                    data-modal-url="modals/contract/contract_link_service.php?contract_id=<?= $contract_id ?>">
                                    <i class="fa fa-plus mr-1"></i> Link Services
                                </button>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="tab-pane fade" id="tab-vendors" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Upstream Vendors & Backing Agreements</h5>
                            <button type="button" class="btn btn-primary btn-sm ajax-modal"
                                data-modal-url="modals/contract/contract_link_vendor.php?contract_id=<?= $contract_id ?>">
                                <i class="fa fa-plus mr-1"></i> Link Vendor
                            </button>
                        </div>
                        <?php if ($linked_vendor_count > 0) { ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Vendor Name</th>
                                            <th>Description / Role</th>
                                            <th>Contact Phone</th>
                                            <th class="text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($vendor = mysqli_fetch_assoc($sql_vendors)) { ?>
                                            <tr>
                                                <td><strong><?= escapeHtml($vendor['vendor_name']) ?></strong></td>
                                                <td><?= escapeHtml($vendor['vendor_description'] ?? '') ?></td>
                                                <td><?= escapeHtml($vendor['vendor_phone'] ?? '') ?></td>
                                                <td class="text-right">
                                                    <a href="post.php?unlink_contract_vendor=<?= $vendor['vendor_id'] ?>&contract_id=<?= $contract_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                       class="btn btn-outline-danger btn-xs confirm-link" title="Unlink Vendor">
                                                        <i class="fa fa-unlink"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="alert alert-light text-center border p-4">
                                <i class="fa fa-building fa-2x text-muted mb-2"></i>
                                <p class="text-secondary mb-2">No upstream vendors linked to this contract.</p>
                                <button type="button" class="btn btn-outline-primary btn-sm ajax-modal"
                                    data-modal-url="modals/contract/contract_link_vendor.php?contract_id=<?= $contract_id ?>">
                                    <i class="fa fa-plus mr-1"></i> Link Vendor
                                </button>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="tab-pane fade" id="tab-tickets" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Recent Client Tickets (SLA Monitored)</h5>
                            <a href="tickets.php?client_id=<?= $client_id ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-external-link-alt mr-1"></i> View All Tickets
                            </a>
                        </div>
                        <?php if ($ticket_count > 0) { ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Subject</th>
                                            <th>Status</th>
                                            <th>Priority</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($ticket = mysqli_fetch_assoc($sql_tickets)) { ?>
                                            <tr>
                                                <td><a href="ticket.php?ticket_id=<?= $ticket['ticket_id'] ?>">#<?= $ticket['ticket_prefix'] ?><?= $ticket['ticket_number'] ?></a></td>
                                                <td><a href="ticket.php?ticket_id=<?= $ticket['ticket_id'] ?>"><?= escapeHtml($ticket['ticket_subject']) ?></a></td>
                                                <td><span class="badge badge-light border"><?= escapeHtml($ticket['ticket_status_name'] ?? 'Open') ?></span></td>
                                                <td><span class="badge badge-info"><?= escapeHtml($ticket['ticket_priority']) ?></span></td>
                                                <td><?= escapeHtml($ticket['ticket_created_at']) ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="alert alert-light text-center border p-4">
                                <i class="fa fa-life-ring fa-2x text-muted mb-2"></i>
                                <p class="text-secondary mb-0">No tickets logged for this client yet.</p>
                            </div>
                        <?php } ?>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php 
} // End

require_once "../includes/footer.php"; 
?>
