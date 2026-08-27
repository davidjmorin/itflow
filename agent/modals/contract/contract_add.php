<?php
require_once '../../../includes/modal_header.php';

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

$templates_sql = mysqli_query($mysqli, "SELECT * FROM contract_templates ORDER BY contract_template_name ASC");

ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-contract mr-2"></i>New Contract</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<form action="post.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <?php if ($client_id > 0) { ?>
                    <input type="hidden" name="return_to_client" value="1">
                <?php } ?>
                <div class="modal-body">
                    
                    <div class="form-group">
                        <label>Select Template <small class="text-muted">(Optional - Will auto-fill form)</small></label>
                        <a href="/admin/contract_templates.php" target="_blank" class="float-right text-sm">
                            <i class="fas fa-cog mr-1"></i>Manage Templates
                        </a>
                        <select class="form-control select2" id="contractTemplateSelect">
                            <option value="">- None / Manual -</option>
                            <?php while ($tpl = mysqli_fetch_assoc($templates_sql)) { ?>
                                <option value="<?= $tpl['contract_template_id'] ?>"><?= escapeHtml($tpl['contract_template_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <?php if ($client_id == 0) { ?>
                                <div class="form-group">
                                    <label>Client *</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        </div>
                                        <select class="form-control select2" name="client_id" required>
                                            <option value="">- Select Client -</option>
                                            <?php
                                            $client_sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                                            while ($c_row = mysqli_fetch_assoc($client_sql)) {
                                                echo '<option value="' . $c_row['client_id'] . '">' . escapeHtml($c_row['client_name']) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            <?php } else { ?>
                                <input type="hidden" name="client_id" value="<?= $client_id ?>">
                            <?php } ?>

                            <div class="form-group">
                                <label>Contract Name *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" id="contract_name" placeholder="E.g. Managed IT Services 2024" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Status *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                                    </div>
                                    <select class="form-control select2" name="status" id="contract_status" required>
                                        <option value="Active">Active</option>
                                        <option value="Pending">Pending</option>
                                        <option value="Expired">Expired</option>
                                        <option value="Terminated">Terminated</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Type</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                                    </div>
                                    <select class="form-control select2" name="type" id="contract_type">
                                        <option value="Full Managed">Full Managed</option>
                                        <option value="Partial Managed">Partial Managed</option>
                                        <option value="Break/Fix">Break/Fix</option>
                                        <option value="Block Hours">Block Hours</option>
                                        <option value="Other">Other</option>
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
                                            <input type="date" class="form-control" name="start_date" id="contract_start_date">
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
                                            <input type="date" class="form-control" name="end_date" id="contract_end_date">
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
                                    <select class="form-control select2" name="renewal_frequency" id="contract_renewal_frequency">
                                        <option value="Monthly">Monthly</option>
                                        <option value="Quarterly">Quarterly</option>
                                        <option value="Annually">Annually</option>
                                        <option value="Bi-Annually">Bi-Annually</option>
                                        <option value="Never">Never</option>
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
                                <div class="col-md-4"><input type="number" class="form-control" name="sla_low_response_time" id="sla_low_respt" value="240"></div>
                                <div class="col-md-4"><input type="number" class="form-control" name="sla_low_resolution_time" id="sla_low_rest" value="2880"></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 pt-2">Medium</div>
                                <div class="col-md-4"><input type="number" class="form-control" name="sla_medium_response_time" id="sla_med_respt" value="120"></div>
                                <div class="col-md-4"><input type="number" class="form-control" name="sla_medium_resolution_time" id="sla_med_rest" value="1440"></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 pt-2">High</div>
                                <div class="col-md-4"><input type="number" class="form-control" name="sla_high_response_time" id="sla_high_respt" value="30"></div>
                                <div class="col-md-4"><input type="number" class="form-control" name="sla_high_resolution_time" id="sla_high_rest" value="240"></div>
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
                                            <input type="number" step="0.01" class="form-control" name="rate_standard" id="rate_standard" value="0.00">
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
                                            <input type="number" step="0.01" class="form-control" name="rate_after_hours" id="rate_after_hours" value="0.00">
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
                                            <input type="text" class="form-control" name="support_hours" id="support_hours" placeholder="e.g. Unlimited, or 10">
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
                                            <input type="number" class="form-control" name="net_terms" id="net_terms" value="30">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Contract Details / Scope of Work</label>
                        <textarea class="form-control tinymce" name="details" id="contract_details"></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_contract" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create Contract</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>

<script>
$(document).ready(function() {
    $('#contractTemplateSelect').on('change', function() {
        var templateId = $(this).val();
        if (templateId) {
            $.ajax({
                url: 'ajax.php',
                type: 'GET',
                data: { get_contract_template: templateId },
                dataType: 'json',
                success: function(data) {
                    if (data) {
                        $('#contract_name').val(data.contract_template_name);
                        $('#contract_type').val(data.contract_template_type).trigger('change');
                        $('#contract_renewal_frequency').val(data.contract_template_renewal_frequency).trigger('change');
                        
                        $('#sla_low_respt').val(data.contract_template_sla_low_response_time);
                        $('#sla_med_respt').val(data.contract_template_sla_medium_response_time);
                        $('#sla_high_respt').val(data.contract_template_sla_high_response_time);
                        $('#sla_low_rest').val(data.contract_template_sla_low_resolution_time);
                        $('#sla_med_rest').val(data.contract_template_sla_medium_resolution_time);
                        $('#sla_high_rest').val(data.contract_template_sla_high_resolution_time);
                        
                        $('#rate_standard').val(data.contract_template_rate_standard);
                        $('#rate_after_hours').val(data.contract_template_rate_after_hours);
                        $('#support_hours').val(data.contract_template_support_hours);
                        $('#net_terms').val(data.contract_template_net_terms);
                        
                        if (typeof tinymce !== 'undefined' && tinymce.get('contract_details')) {
                            tinymce.get('contract_details').setContent(data.contract_template_details || '');
                        } else {
                            $('#contract_details').val(data.contract_template_details);
                        }
                    }
                }
            });
        }
    });
});
</script>

<?php require_once '../../../includes/modal_footer.php'; ?>
