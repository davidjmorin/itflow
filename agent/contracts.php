<?php

$sort = "contract_name";
$order = "ASC";

if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND contract_client_id = $client_id";
    $client_url = "client_id=$client_id&";
    
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "contract_archived_at IS NOT NULL";
    } else {
        $archived = 0;
        $archive_query = "contract_archived_at IS NULL";
    }
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
    
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "(client_archived_at IS NOT NULL OR contract_archived_at IS NOT NULL)";
    } else {
        $archived = 0;
        $archive_query = "(client_archived_at IS NULL AND contract_archived_at IS NULL)";
    }
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM contracts 
    LEFT JOIN clients ON contract_client_id = client_id
    WHERE (contract_name LIKE '%$q%' OR contract_status LIKE '%$q%' OR contract_type LIKE '%$q%') 
    AND $archive_query $client_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-file-contract mr-2"></i>Contracts</h3>
        <div class="card-tools">
            <a href="/admin/contract_templates.php" class="btn btn-outline-light btn-sm mr-2">
                <i class="fas fa-layer-group mr-2"></i>Templates
            </a>
            <button type="button" class="btn btn-primary btn-sm ajax-modal" data-modal-url="modals/contract/contract_add.php?client_id=<?= $client_id ?? 0 ?>" data-modal-size="lg">
                <i class="fas fa-plus mr-2"></i>New Contract
            </button>
        </div>
    </div>
    <div class="card-body">

        <form autocomplete="off">
            <?php if (isset($_GET['client_id'])) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <div class="input-group">
                <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search contracts">
                <div class="input-group-append">
                    <button class="btn btn-secondary"><i class="fa fa-search"></i></button>
                </div>
            </div>
        </form>
        <hr>

        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th>Contract Name</th>
                        <?php if (!isset($_GET['client_id'])) { echo "<th>Client</th>"; } ?>
                        <th>Status</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>SLA (Response)</th>
                        <th>Hourly Rate</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $contract_id = intval($row['contract_id']);
                            $contract_name = escapeHtml($row['contract_name']);
                            $contract_status = escapeHtml($row['contract_status']);
                            $contract_type = escapeHtml($row['contract_type']);
                            $start_date = escapeHtml($row['contract_start_date']);
                            $end_date = escapeHtml($row['contract_end_date']);
                            
                            $sla_low = escapeHtml($row['contract_sla_low_response_time']);
                            $sla_med = escapeHtml($row['contract_sla_medium_response_time']);
                            $sla_high = escapeHtml($row['contract_sla_high_response_time']);
                            
                            $rate = escapeHtml($row['contract_rate_standard']);
                            
                            $client_id_row = intval($row['contract_client_id']);
                            $client_name = escapeHtml($row['contract_client_name']);
                            
                            // Badges for status
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
                        <td>
                            <a class="text-bold text-dark" href="contract.php?<?= $client_url ?>contract_id=<?= $contract_id ?>">
                                <i class="fas fa-fw fa-file-contract mr-1"></i> <?= $contract_name ?>
                            </a>
                        </td>
                        <?php if (!isset($_GET['client_id'])) { ?>
                            <td><a href="client_overview.php?client_id=<?= $client_id_row ?>"><?= $client_name ?></a></td>
                        <?php } ?>
                        <td><span class="badge <?= $status_badge ?> p-1"><?= $contract_status ?></span></td>
                        <td><?= $contract_type ?></td>
                        <td><?= $start_date ?> to <?= $end_date ?></td>
                        <td><?= "$sla_low / $sla_med / $sla_high" ?></td>
                        <td><?= numfmt_format_currency($currency_format, floatval($rate), $session_company_currency) ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-size="xl"
                                        data-modal-url="modals/contract/contract_edit.php?id=<?= $contract_id ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <?php if ($archived == 0) { ?>
                                        <a class="dropdown-item text-warning confirm-link" href="post.php?archive_contract=<?= $contract_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_contract=<?= $contract_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    <?php } else { ?>
                                        <a class="dropdown-item text-success text-bold confirm-link" href="post.php?restore_contract=<?= $contract_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-undo-alt mr-2"></i>Restore
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_contract=<?= $contract_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <br>
        </div>
        <?php require_once "../includes/filter_footer.php"; ?>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
