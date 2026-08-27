<?php
require_once '../../../includes/modal_header.php';

$contract_id = intval($_GET['id']);
$sql = mysqli_query($mysqli, "SELECT * FROM contracts WHERE contract_id = $contract_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);

$name = escapeHtml($row['contract_name']);
$status = escapeHtml($row['contract_status']);
$type = escapeHtml($row['contract_type']);
$start_date = escapeHtml($row['contract_start_date']);
$end_date = escapeHtml($row['contract_end_date']);
$renewal_frequency = escapeHtml($row['contract_renewal_frequency']);
$support_hours = escapeHtml($row['contract_support_hours']);
$details = $row['contract_details'];

$sla_low_resp = intval($row['contract_sla_low_response_time']);
$sla_med_resp = intval($row['contract_sla_medium_response_time']);
$sla_high_resp = intval($row['contract_sla_high_response_time']);
$sla_low_res = intval($row['contract_sla_low_resolution_time']);
$sla_med_res = intval($row['contract_sla_medium_resolution_time']);
$sla_high_res = intval($row['contract_sla_high_resolution_time']);

$rate_standard = floatval($row['contract_rate_standard']);
$rate_after_hours = floatval($row['contract_rate_after_hours']);
$net_terms = intval($row['contract_net_terms']);

ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-contract mr-2"></i>Edit Contract</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<form action="post.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="contract_id" value="<?= $contract_id ?>">
                <div class="modal-body">
                    
                    <div class="row">
                        <div class="col-md-6">
                            
                            <div class="form-group">
                                <label>Contract Name *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" value="<?= $name ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Status *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                                    </div>
                                    <select class="form-control select2" name="status" required>
                                        <option <?php if ($status == 'Active') { echo "selected"; } ?> value="Active">Active</option>
                                        <option <?php if ($status == 'Pending') { echo "selected"; } ?> value="Pending">Pending</option>
                                        <option <?php if ($status == 'Expired') { echo "selected"; } ?> value="Expired">Expired</option>
                                        <option <?php if ($status == 'Terminated') { echo "selected"; } ?> value="Terminated">Terminated</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Type</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                                    </div>
                                    <select class="form-control select2" name="type">
                                        <option <?php if ($type == 'Full Managed') { echo "selected"; } ?> value="Full Managed">Full Managed</option>
                                        <option <?php if ($type == 'Partial Managed') { echo "selected"; } ?> value="Partial Managed">Partial Managed</option>
                                        <option <?php if ($type == 'Break/Fix') { echo "selected"; } ?> value="Break/Fix">Break/Fix</option>
                                        <option <?php if ($type == 'Block Hours') { echo "selected"; } ?> value="Block Hours">Block Hours</option>
                                        <option <?php if ($type == 'Other') { echo "selected"; } ?> value="Other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Start Date</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                            </div>
                                            <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>End Date</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                            </div>
                                            <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Renewal Frequency</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-sync"></i></span>
                                    </div>
                                    <select class="form-control select2" name="renewal_frequency">
                                        <option <?php if ($renewal_frequency == 'Monthly') { echo "selected"; } ?> value="Monthly">Monthly</option>
                                        <option <?php if ($renewal_frequency == 'Quarterly') { echo "selected"; } ?> value="Quarterly">Quarterly</option>
                                        <option <?php if ($renewal_frequency == 'Annually') { echo "selected"; } ?> value="Annually">Annually</option>
                                        <option <?php if ($renewal_frequency == 'Bi-Annually') { echo "selected"; } ?> value="Bi-Annually">Bi-Annually</option>
                                        <option <?php if ($renewal_frequency == 'Never') { echo "selected"; } ?> value="Never">Never</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h5 class="text-center font-weight-bold">SLA & Billing</h5>
                            <hr>
                            <div class="row">
                                <div class="col-md-4"><label>Priority</label></div>
                                <div class="col-md-4"><label>Response (Mins)</label></div>
                                <div class="col-md-4"><label>Resolution (Mins)</label></div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-md-4 pt-2">Low</div>
                                <div class="col-md-4"><input type="number" class="form-control" name="sla_low_response_time" value="<?= $sla_low_resp ?>"></div>
                                <div class="col-md-4"><input type="number" class="form-control" name="sla_low_resolution_time" value="<?= $sla_low_res ?>"></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 pt-2">Medium</div>
                                <div class="col-md-4"><input type="number" class="form-control" name="sla_medium_response_time" value="<?= $sla_med_resp ?>"></div>
                                <div class="col-md-4"><input type="number" class="form-control" name="sla_medium_resolution_time" value="<?= $sla_med_res ?>"></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 pt-2">High</div>
                                <div class="col-md-4"><input type="number" class="form-control" name="sla_high_response_time" value="<?= $sla_high_resp ?>"></div>
                                <div class="col-md-4"><input type="number" class="form-control" name="sla_high_resolution_time" value="<?= $sla_high_res ?>"></div>
                            </div>

                            <hr>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Standard Rate / Hr</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-money-bill-wave"></i></span>
                                            </div>
                                            <input type="number" step="0.01" class="form-control" name="rate_standard" value="<?= $rate_standard ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>After Hours Rate / Hr</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-money-bill-wave"></i></span>
                                            </div>
                                            <input type="number" step="0.01" class="form-control" name="rate_after_hours" value="<?= $rate_after_hours ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Included Support Hours</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                                            </div>
                                            <input type="text" class="form-control" name="support_hours" value="<?= $support_hours ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Net Terms (Days)</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                                            </div>
                                            <input type="number" class="form-control" name="net_terms" value="<?= $net_terms ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Contract Details / Scope of Work</label>
                        <textarea class="form-control tinymce" name="details" id="contract_edit_details"><?= $details ?></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_contract" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>

<?php require_once '../../../includes/modal_footer.php'; ?>
