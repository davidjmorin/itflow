<?php
require_once '../../../includes/modal_header.php';

$contract_id = intval($_GET['contract_id'] ?? 0);
$sql_contract = mysqli_query($mysqli, "SELECT * FROM contracts WHERE contract_id = $contract_id LIMIT 1");
$contract = mysqli_fetch_assoc($sql_contract);
$client_id = intval($contract['contract_client_id'] ?? 0);

$sql_unlinked_vendors = mysqli_query($mysqli, "
    SELECT * FROM vendors
    WHERE (vendor_client_id = $client_id OR vendor_client_id = 0)
    AND vendor_archived_at IS NULL
    AND vendor_id NOT IN (
        SELECT vendor_id FROM contract_vendors WHERE contract_id = $contract_id
    )
    ORDER BY vendor_name ASC
");

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-building mr-2"></i>Link Upstream Vendor to Contract</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>
<form action="post.php" method="POST" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contract_id" value="<?= $contract_id ?>">

    <div class="modal-body">
        <?php if (mysqli_num_rows($sql_unlinked_vendors) > 0) { ?>
            <div class="form-group">
                <label>Select Upstream Vendors</label>
                <select class="form-control select2" name="vendor_ids[]" multiple="multiple" data-placeholder="Choose vendors..." required style="width: 100%;">
                    <?php while ($vendor = mysqli_fetch_assoc($sql_unlinked_vendors)) { ?>
                        <option value="<?= $vendor['vendor_id'] ?>">
                            <?= escapeHtml($vendor['vendor_name']) ?> <?= !empty($vendor['vendor_description']) ? '('.escapeHtml($vendor['vendor_description']).')' : '' ?>
                        </option>
                    <?php } ?>
                </select>
                <small class="form-text text-muted">Link underlying suppliers, cloud CSPs, or backup vendors for this contract.</small>
            </div>
        <?php } else { ?>
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle mr-2"></i>All vendors are already linked to this contract or none exist.
            </div>
        <?php } ?>
    </div>

    <div class="modal-footer bg-white">
        <?php if (mysqli_num_rows($sql_unlinked_vendors) > 0) { ?>
            <button type="submit" name="link_contract_vendor" class="btn btn-primary text-bold">
                <i class="fas fa-link mr-2"></i>Link Vendors
            </button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fas fa-times mr-2"></i>Cancel
        </button>
    </div>
</form>

<?php require_once '../../../includes/modal_footer.php'; ?>
