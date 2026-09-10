<?php

require_once __DIR__ . '/../../../includes/modal_header.php';

$lead_id = intval($_GET['lead_id'] ?? 0);
$lead_sql = mysqli_query($mysqli, "SELECT * FROM leads WHERE lead_id = $lead_id LIMIT 1");
if (mysqli_num_rows($lead_sql) === 0) {
    echo json_encode(['error' => 'Lead not found.']);
    exit();
}
$lead = mysqli_fetch_assoc($lead_sql);

ob_start();

?>

<div class="modal-header bg-success text-white">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-check mr-2"></i>Convert Lead to Client</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off" style="display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="lead_id" value="<?= $lead_id ?>">

    <div class="modal-body" style="overflow-y: auto; max-height: calc(75vh - 120px);">
        <div class="alert alert-info">
            <i class="fas fa-info-circle mr-2"></i>Converting this lead will create an active <strong>Client</strong>, primary <strong>Contact</strong>, and primary <strong>Location</strong> in ITFlow.
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="form-group">
                    <label>Client Name <strong class="text-danger">*</strong></label>
                    <input type="text" class="form-control" name="client_name" value="<?= escapeHtml($lead['lead_name']) ?>" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Client Type</label>
                    <input type="text" class="form-control" name="client_type" value="Commercial">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Primary Contact Name</label>
                    <input type="text" class="form-control" name="contact_name" value="<?= escapeHtml($lead['lead_contact_name'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Contact Title</label>
                    <input type="text" class="form-control" name="contact_title" value="<?= escapeHtml($lead['lead_contact_title'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Contact Email</label>
                    <input type="email" class="form-control" name="contact_email" value="<?= escapeHtml($lead['lead_contact_email'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Contact Phone</label>
                    <input type="text" class="form-control" name="contact_phone" value="<?= escapeHtml($lead['lead_contact_phone'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Hourly Rate</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><?= escapeHtml($session_company_currency ?? '$') ?></span>
                        </div>
                        <input type="number" step="0.01" class="form-control" name="client_rate" value="<?= escapeHtml($config_default_hourly_rate ?? '0.00') ?>">
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Payment Net Terms (Days)</label>
                    <input type="number" class="form-control" name="client_net_terms" value="<?= intval($config_default_net_terms ?? 30) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Referral / Lead Source</label>
                    <input type="text" class="form-control" name="client_referral" value="<?= escapeHtml($lead['lead_source'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label>Website</label>
                    <input type="text" class="form-control" name="client_website" value="<?= escapeHtml($lead['lead_website'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label>Primary Address</label>
                    <input type="text" class="form-control" name="address" value="<?= escapeHtml($lead['lead_address'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>City</label>
                    <input type="text" class="form-control" name="city" value="<?= escapeHtml($lead['lead_city'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>State / Province</label>
                    <input type="text" class="form-control" name="state" value="<?= escapeHtml($lead['lead_state'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Zip / Postal Code</label>
                    <input type="text" class="form-control" name="zip" value="<?= escapeHtml($lead['lead_zip'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label>Country</label>
                    <input type="text" class="form-control" name="country" value="<?= escapeHtml($lead['lead_country'] ?? $session_company_country ?? '') ?>">
                </div>
            </div>
        </div>

    </div>
    <div class="modal-footer bg-white border-top">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" name="convert_lead" class="btn btn-success"><i class="fas fa-user-check mr-2"></i>Complete Conversion</button>
    </div>
</form>

<?php
require_once __DIR__ . '/../../../includes/modal_footer.php';
?>
