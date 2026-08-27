<?php
require_once '../../../includes/modal_header.php';

$contract_id = intval($_GET['contract_id'] ?? 0);
$sql_contract = mysqli_query($mysqli, "SELECT * FROM contracts WHERE contract_id = $contract_id LIMIT 1");
$contract = mysqli_fetch_assoc($sql_contract);
$client_id = intval($contract['contract_client_id'] ?? 0);

$sql_unlinked_services = mysqli_query($mysqli, "
    SELECT * FROM services
    WHERE service_client_id = $client_id
    AND service_id NOT IN (
        SELECT service_id FROM contract_services WHERE contract_id = $contract_id
    )
    ORDER BY service_name ASC
");

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-cogs mr-2"></i>Link Covered Services to Contract</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>
<form action="post.php" method="POST" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contract_id" value="<?= $contract_id ?>">

    <div class="modal-body">
        <?php if (mysqli_num_rows($sql_unlinked_services) > 0) { ?>
            <div class="form-group">
                <label>Select Services to Cover</label>
                <select class="form-control select2" name="service_ids[]" multiple="multiple" data-placeholder="Choose services..." required style="width: 100%;">
                    <?php while ($service = mysqli_fetch_assoc($sql_unlinked_services)) { ?>
                        <option value="<?= $service['service_id'] ?>">
                            <?= escapeHtml($service['service_name']) ?> (<?= escapeHtml($service['service_category'] ?? 'Service') ?>)
                        </option>
                    <?php } ?>
                </select>
            </div>
        <?php } else { ?>
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle mr-2"></i>All active services for this client are already linked to this contract or none exist.
            </div>
        <?php } ?>
    </div>

    <div class="modal-footer bg-white">
        <?php if (mysqli_num_rows($sql_unlinked_services) > 0) { ?>
            <button type="submit" name="link_contract_service" class="btn btn-primary text-bold">
                <i class="fas fa-link mr-2"></i>Link Services
            </button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fas fa-times mr-2"></i>Cancel
        </button>
    </div>
</form>

<?php require_once '../../../includes/modal_footer.php'; ?>
