<?php

$sort = "ticket_rule_order";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM ticket_rules
    LEFT JOIN clients ON ticket_rule_client_id = client_id
    LEFT JOIN users ON ticket_rule_assigned_to = user_id
    LEFT JOIN ticket_statuses ON ticket_rule_status = ticket_status_id
    WHERE ticket_rule_name LIKE '%$q%'
       OR ticket_rule_sender_email LIKE '%$q%'
       OR ticket_rule_sender_domain LIKE '%$q%'
       OR ticket_rule_subject LIKE '%$q%'
    ORDER BY $sort $order, ticket_rule_id ASC LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-filter mr-2"></i>Inbound Ticket Rules (Email Routing & Spam Filter)</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/ticket_rule/ticket_rule_add.php">
                <i class="fas fa-plus mr-2"></i>New Rule
            </button>
        </div>
    </div>

    <div class="card-body">
        <div class="row">
            <div class="col-sm-4 mb-2">
                <form autocomplete="off">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Inbound Rules">
                        <div class="input-group-append">
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-sm-8 text-right">
                <small class="text-muted">Rules are evaluated sequentially from lowest order number to highest. When a rule matches with "Stop processing", remaining rules are skipped.</small>
            </div>
        </div>

        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th style="width: 70px;">
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ticket_rule_order&order=<?= $disp ?>">
                            Order <?php if ($sort == 'ticket_rule_order') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ticket_rule_name&order=<?= $disp ?>">
                            Rule Name <?php if ($sort == 'ticket_rule_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>Conditions</th>
                    <th>Action</th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ticket_rule_active&order=<?= $disp ?>">
                            Status <?php if ($sort == 'ticket_rule_active') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_assoc($sql)) {
                    $rule_id = intval($row['ticket_rule_id']);
                    $rule_order = intval($row['ticket_rule_order']);
                    $rule_name = escapeHtml($row['ticket_rule_name']);
                    $rule_active = intval($row['ticket_rule_active']);
                    $rule_match_type = escapeHtml($row['ticket_rule_match_type']);
                    $rule_action = escapeHtml($row['ticket_rule_action']);
                    $sender_email = escapeHtml($row['ticket_rule_sender_email'] ?? '');
                    $sender_domain = escapeHtml($row['ticket_rule_sender_domain'] ?? '');
                    $subject = escapeHtml($row['ticket_rule_subject'] ?? '');
                    $body = escapeHtml($row['ticket_rule_body'] ?? '');
                    $priority = escapeHtml($row['ticket_rule_priority'] ?? '');
                    $category = escapeHtml($row['ticket_rule_category'] ?? '');
                    $client_name = escapeHtml($row['client_name'] ?? '');
                    $tech_name = escapeHtml($row['user_name'] ?? '');

                    ?>
                    <tr>
                        <td class="font-weight-bold text-muted"><?= $rule_order ?></td>
                        <td>
                            <a href="#" class="ajax-modal font-weight-bold" data-modal-url="modals/ticket_rule/ticket_rule_edit.php?id=<?= $rule_id ?>">
                                <?= $rule_name ?>
                            </a>
                            <?php if (!empty($row['ticket_rule_stop_processing'])) { ?>
                                <br><small class="badge badge-light border text-muted">Stop on match</small>
                            <?php } ?>
                        </td>
                        <td>
                            <small class="d-block text-uppercase text-muted font-weight-bold mb-1">
                                Match: <?= $rule_match_type === 'any' ? 'ANY (OR)' : 'ALL (AND)' ?>
                            </small>
                            <ul class="list-unstyled mb-0 small">
                                <?php if (!empty($sender_email)) { ?>
                                    <li><i class="fas fa-envelope mr-1 text-secondary"></i>From: <code><?= $sender_email ?></code></li>
                                <?php } ?>
                                <?php if (!empty($sender_domain)) { ?>
                                    <li><i class="fas fa-globe mr-1 text-secondary"></i>Domain: <code>@<?= $sender_domain ?></code></li>
                                <?php } ?>
                                <?php if (!empty($subject)) { ?>
                                    <li><i class="fas fa-heading mr-1 text-secondary"></i>Subject: "<em><?= $subject ?></em>"</li>
                                <?php } ?>
                                <?php if (!empty($body)) { ?>
                                    <li><i class="fas fa-align-left mr-1 text-secondary"></i>Body: "<em><?= $body ?></em>"</li>
                                <?php } ?>
                            </ul>
                        </td>
                        <td>
                            <?php if ($rule_action === 'ignore') { ?>
                                <span class="badge badge-danger px-2 py-1"><i class="fas fa-ban mr-1"></i>Ignore / Spam</span>
                                <div class="small text-muted mt-1">Do not create ticket</div>
                            <?php } else { ?>
                                <span class="badge badge-success px-2 py-1"><i class="fas fa-route mr-1"></i>Route Ticket</span>
                                <ul class="list-unstyled mb-0 small mt-1">
                                    <?php if (!empty($client_name)) { ?>
                                        <li><strong>Client:</strong> <?= $client_name ?></li>
                                    <?php } ?>
                                    <?php if (!empty($priority)) { ?>
                                        <li><strong>Priority:</strong> <?= $priority ?></li>
                                    <?php } ?>
                                    <?php if (!empty($category)) { ?>
                                        <li><strong>Category:</strong> <?= $category ?></li>
                                    <?php } ?>
                                    <?php if (!empty($tech_name)) { ?>
                                        <li><strong>Tech:</strong> <?= $tech_name ?></li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                        </td>
                        <td>
                            <?php if ($rule_active) { ?>
                                <a href="post.php?toggle_ticket_rule=<?= $rule_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="badge badge-success p-1">Active</a>
                            <?php } else { ?>
                                <a href="post.php?toggle_ticket_rule=<?= $rule_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="badge badge-secondary p-1">Inactive</a>
                            <?php } ?>
                        </td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket_rule/ticket_rule_edit.php?id=<?= $rule_id ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <a class="dropdown-item" href="post.php?toggle_ticket_rule=<?= $rule_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-power-off mr-2"></i><?= $rule_active ? 'Disable' : 'Enable' ?>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_ticket_rule=<?= $rule_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php
                }

                ?>
                </tbody>
            </table>
        </div>
        <?php require_once "../includes/filter_footer.php"; ?>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/agent/includes/custom_legacy_js.php';
require_once "../includes/footer.php";

