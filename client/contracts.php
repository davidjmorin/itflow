<?php
/*
 * Client Portal
 * Contracts for clients
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

enforceContactCan('accounting');

$contracts_sql = mysqli_query($mysqli, "SELECT * FROM contracts WHERE contract_client_id = $session_client_id AND contract_archived_at IS NULL ORDER BY contract_status ASC, contract_end_date ASC");
?>

<h3>Contracts</h3>
<div class="row">
    <div class="col-md-12">
        <table class="table tabled-bordered border border-dark">
            <thead class="thead-dark">
            <tr>
                <th>Contract Name</th>
                <th>Status</th>
                <th>Type</th>
                <th>Dates</th>
                <th>SLA (Response)</th>
                <th>Rate</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>

            <?php
            while ($row = mysqli_fetch_assoc($contracts_sql)) {
                $contract_id = intval($row['contract_id']);
                $contract_name = escapeHtml($row['contract_name']);
                $contract_status = escapeHtml($row['contract_status']);
                $contract_type = escapeHtml($row['contract_type']);
                $start_date = escapeHtml($row['contract_start_date']);
                $end_date = escapeHtml($row['contract_end_date']);
                
                $sla_low = escapeHtml($row['contract_sla_low_response_time']);
                $sla_med = escapeHtml($row['contract_sla_medium_response_time']);
                $sla_high = escapeHtml($row['contract_sla_high_response_time']);
                
                $rate = floatval($row['contract_rate_standard']);
                $details = $row['contract_details']; // HTML content

                if ($contract_status == 'Active') {
                    $status_badge = 'badge-success';
                } elseif ($contract_status == 'Expired') {
                    $status_badge = 'badge-danger';
                } elseif ($contract_status == 'Pending') {
                    $status_badge = 'badge-warning';
                } else {
                    $status_badge = 'badge-secondary';
                }
                ?>

                <tr>
                    <td class="font-weight-bold"><?= $contract_name ?></td>
                    <td><span class="badge <?= $status_badge ?> p-2"><?= $contract_status ?></span></td>
                    <td><?= $contract_type ?></td>
                    <td><?= $start_date ?> - <?= $end_date ?></td>
                    <td><?= "$sla_low / $sla_med / $sla_high" ?></td>
                    <td><?= numfmt_format_currency($currency_format, floatval($rate), $session_company_currency) ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#viewContractModal<?= $contract_id ?>"><i class="fa fa-eye"></i> View</button>
                    </td>
                </tr>

                <!-- View Modal -->
                <div class="modal fade" id="viewContractModal<?= $contract_id ?>">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fa fa-fw fa-file-contract mr-2"></i><?= $contract_name ?></h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body bg-light">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Status:</strong> <?= $contract_status ?></p>
                                        <p><strong>Type:</strong> <?= $contract_type ?></p>
                                        <p><strong>Dates:</strong> <?= $start_date ?> to <?= $end_date ?></p>
                                        <p><strong>Renewal Frequency:</strong> <?= escapeHtml($row['contract_renewal_frequency']) ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Standard Rate:</strong> <?= numfmt_format_currency($currency_format, floatval($rate), $session_company_currency) ?> /hr</p>
                                        <p><strong>After Hours Rate:</strong> <?= numfmt_format_currency($currency_format, floatval($row['contract_rate_after_hours']), $session_company_currency) ?> /hr</p>
                                        <p><strong>Included Support:</strong> <?= escapeHtml($row['contract_support_hours']) ?></p>
                                        <p><strong>Net Terms:</strong> <?= escapeHtml($row['contract_net_terms']) ?> Days</p>
                                    </div>
                                </div>
                                <hr>
                                <h5>SLA Response Times (Mins)</h5>
                                <table class="table table-sm table-bordered bg-white">
                                    <thead>
                                        <tr>
                                            <th>Low</th>
                                            <th>Medium</th>
                                            <th>High</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?= $sla_low ?></td>
                                            <td><?= $sla_med ?></td>
                                            <td><?= $sla_high ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <hr>
                                <h5>SLA Resolution Times (Mins)</h5>
                                <table class="table table-sm table-bordered bg-white">
                                    <thead>
                                        <tr>
                                            <th>Low</th>
                                            <th>Medium</th>
                                            <th>High</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?= escapeHtml($row['contract_sla_low_resolution_time']) ?></td>
                                            <td><?= escapeHtml($row['contract_sla_medium_resolution_time']) ?></td>
                                            <td><?= escapeHtml($row['contract_sla_high_resolution_time']) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <?php if (!empty(strip_tags($details))) { ?>
                                    <hr>
                                    <h5>Details / Scope of Work</h5>
                                    <div class="p-3 bg-white border">
                                        <?= cleanHtml($details) ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="modal-footer bg-white">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

            <?php } ?>

            </tbody>
        </table>

    </div>

</div>

<?php
require_once "includes/footer.php";
?>
