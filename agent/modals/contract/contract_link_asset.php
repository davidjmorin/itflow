<?php
require_once '../../../includes/modal_header.php';

$contract_id = intval($_GET['contract_id'] ?? 0);
$sql_contract = mysqli_query($mysqli, "SELECT * FROM contracts WHERE contract_id = $contract_id LIMIT 1");
$contract = mysqli_fetch_assoc($sql_contract);
$client_id = intval($contract['contract_client_id'] ?? 0);

// Get client assets not already linked to this contract
$sql_unlinked_assets = mysqli_query($mysqli, "
    SELECT * FROM assets
    WHERE asset_client_id = $client_id
    AND asset_archived_at IS NULL
    AND asset_id NOT IN (
        SELECT asset_id FROM contract_assets WHERE contract_id = $contract_id
    )
    ORDER BY asset_name ASC
");

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-desktop mr-2"></i>Link Covered Assets to Contract</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>
<form action="post.php" method="POST" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contract_id" value="<?= $contract_id ?>">

    <div class="modal-body">
        <?php if (mysqli_num_rows($sql_unlinked_assets) > 0) { ?>
            <div class="form-group">
                <label>Select Assets to Cover</label>
                <select class="form-control select2" name="asset_ids[]" multiple="multiple" data-placeholder="Choose assets..." required style="width: 100%;">
                    <?php while ($asset = mysqli_fetch_assoc($sql_unlinked_assets)) { ?>
                        <option value="<?= $asset['asset_id'] ?>">
                            <?= escapeHtml($asset['asset_name']) ?> (<?= escapeHtml($asset['asset_type']) ?>)
                        </option>
                    <?php } ?>
                </select>
                <small class="form-text text-muted">You can select multiple workstations, servers, or devices.</small>
            </div>
        <?php } else { ?>
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle mr-2"></i>All active assets for this client are already linked to this contract or none exist.
            </div>
        <?php } ?>
    </div>

    <div class="modal-footer bg-white">
        <?php if (mysqli_num_rows($sql_unlinked_assets) > 0) { ?>
            <button type="submit" name="link_contract_asset" class="btn btn-primary text-bold">
                <i class="fas fa-link mr-2"></i>Link Assets
            </button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fas fa-times mr-2"></i>Cancel
        </button>
    </div>
</form>

<?php require_once '../../../includes/modal_footer.php'; ?>
