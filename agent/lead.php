<?php

require_once "includes/inc_all.php";

enforceUserPermission('module_client');

$lead_id = intval($_GET['lead_id'] ?? 0);

$lead_sql = mysqli_query($mysqli, "SELECT leads.*, lead_stages.lead_stage_name, lead_stages.lead_stage_color, users.user_name AS assigned_user_name, clients.client_name AS converted_client_name FROM leads LEFT JOIN lead_stages ON leads.lead_stage_id = lead_stages.lead_stage_id LEFT JOIN users ON leads.lead_assigned_user_id = users.user_id LEFT JOIN clients ON leads.lead_converted_client_id = clients.client_id WHERE lead_id = $lead_id AND lead_archived_at IS NULL LIMIT 1");

if (mysqli_num_rows($lead_sql) === 0) {
    echo "<div class='alert alert-warning m-4'><i class='fas fa-exclamation-triangle mr-2'></i>Lead not found or has been archived. <a href='leads.php'>Back to Leads</a></div>";
    require_once "../includes/footer.php";
    exit();
}

$lead = mysqli_fetch_assoc($lead_sql);
$stage_color = escapeHtml($lead['lead_stage_color'] ?: '#007bff');
$mrr_display = floatval($lead['lead_estimated_mrr']) > 0 ? numfmt_format_currency($currency_format, floatval($lead['lead_estimated_mrr']), $session_company_currency) : '-';

?>

<link rel="stylesheet" href="css/leads.css">

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
    <li class="breadcrumb-item"><a href="leads.php">Leads</a></li>
    <li class="breadcrumb-item active"><?= escapeHtml($lead['lead_name']) ?></li>
</ol>

<div class="row mb-3">
    <div class="col-md-6 d-flex align-items-center">
        <h2 class="mb-0 font-weight-bold mr-3"><?= escapeHtml($lead['lead_name']) ?></h2>
        <span class="badge badge-pill text-white p-2" style="background-color: <?= $stage_color ?>; font-size: 0.9rem;">
            <?= escapeHtml($lead['lead_stage_name']) ?>
        </span>
        <?php if ($lead['lead_status'] === 'Converted') { ?>
            <span class="badge badge-pill badge-success ml-2 p-2" style="font-size: 0.9rem;">
                <i class="fas fa-check-circle mr-1"></i>Converted to Client
            </span>
        <?php } elseif ($lead['lead_status'] === 'Lost') { ?>
            <span class="badge badge-pill badge-danger ml-2 p-2" style="font-size: 0.9rem;">
                <i class="fas fa-times-circle mr-1"></i>Lost / Disqualified
            </span>
        <?php } ?>
    </div>
    <div class="col-md-6 text-md-right mt-2 mt-md-0">
        <div class="btn-group">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/lead/lead_send_email.php?lead_id=<?= $lead_id ?>" data-modal-size="xl">
                <i class="fas fa-paper-plane mr-1"></i>Send Email
            </button>
            <?php if ($lead['lead_status'] !== 'Converted') { ?>
                <button type="button" class="btn btn-success ajax-modal" data-modal-url="modals/lead/lead_convert.php?lead_id=<?= $lead_id ?>" data-modal-size="lg">
                    <i class="fas fa-user-check mr-1"></i>Convert to Client
                </button>
            <?php } ?>
            <button type="button" class="btn btn-light ajax-modal" data-modal-url="modals/lead/lead_activity_add.php?lead_id=<?= $lead_id ?>">
                <i class="fas fa-plus mr-1"></i>Log Activity
            </button>
            <button type="button" class="btn btn-light ajax-modal" data-modal-url="modals/lead/lead_edit.php?lead_id=<?= $lead_id ?>" data-modal-size="lg">
                <i class="fas fa-edit mr-1"></i>Edit
            </button>
            <button type="button" class="btn btn-light dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="sr-only">Toggle Dropdown</span>
            </button>
            <div class="dropdown-menu dropdown-menu-right">
                <a class="dropdown-item text-warning confirm-link" href="post.php?archive_lead=<?= $lead_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                    <i class="fas fa-archive mr-2"></i>Archive Lead
                </a>
                <?php if (lookupUserPermission("module_client") >= 3) { ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger confirm-link" href="post.php?delete_lead=<?= $lead_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-trash mr-2"></i>Delete Permanently
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php if ($lead['lead_status'] === 'Converted' && !empty($lead['lead_converted_client_id'])) { ?>
    <div class="alert alert-success d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-check-circle mr-2 fa-lg"></i>
            This lead was successfully converted on <strong><?= escapeHtml($lead['lead_converted_at']) ?></strong> to client <strong><?= escapeHtml($lead['converted_client_name'] ?? 'Client #' . $lead['lead_converted_client_id']) ?></strong>.
        </div>
        <a href="client_overview.php?client_id=<?= intval($lead['lead_converted_client_id']) ?>" class="btn btn-light btn-sm font-weight-bold">
            <i class="fas fa-external-link-alt mr-1"></i>Go to Client Overview
        </a>
    </div>
<?php } ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-building mr-2"></i>Lead Information</h3>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-unbordered mb-3">
                    <li class="list-group-item">
                        <b>Primary Contact</b>
                        <span class="float-right text-dark font-weight-bold">
                            <?= escapeHtml($lead['lead_contact_name'] ?: 'Not set') ?>
                        </span>
                    </li>
                    <?php if (!empty($lead['lead_contact_title'])) { ?>
                        <li class="list-group-item">
                            <b>Title</b> <span class="float-right"><?= escapeHtml($lead['lead_contact_title']) ?></span>
                        </li>
                    <?php } ?>
                    <?php if (!empty($lead['lead_contact_email'])) { ?>
                        <li class="list-group-item">
                            <b>Email</b>
                            <span class="float-right">
                                <a href="mailto:<?= escapeHtml($lead['lead_contact_email']) ?>"><?= escapeHtml($lead['lead_contact_email']) ?></a>
                            </span>
                        </li>
                    <?php } ?>
                    <?php if (!empty($lead['lead_contact_phone'])) { ?>
                        <li class="list-group-item">
                            <b>Phone</b>
                            <span class="float-right">
                                <a href="tel:<?= escapeHtml($lead['lead_contact_phone']) ?>"><?= escapeHtml($lead['lead_contact_phone']) ?></a>
                            </span>
                        </li>
                    <?php } ?>
                    <?php if (!empty($lead['lead_contact_mobile'])) { ?>
                        <li class="list-group-item">
                            <b>Mobile</b>
                            <span class="float-right">
                                <a href="tel:<?= escapeHtml($lead['lead_contact_mobile']) ?>"><?= escapeHtml($lead['lead_contact_mobile']) ?></a>
                            </span>
                        </li>
                    <?php } ?>
                    <?php if (!empty($lead['lead_website'])) { ?>
                        <li class="list-group-item">
                            <b>Website</b>
                            <span class="float-right">
                                <a href="//<?= escapeHtml($lead['lead_website']) ?>" target="_blank"><?= escapeHtml($lead['lead_website']) ?></a>
                            </span>
                        </li>
                    <?php } ?>
                    <li class="list-group-item">
                        <b>Estimated MRR</b>
                        <span class="float-right font-weight-bold text-success"><?= $mrr_display ?>/mo</span>
                    </li>
                    <li class="list-group-item">
                        <b>Estimated Seats</b>
                        <span class="float-right font-weight-bold"><?= intval($lead['lead_estimated_seats']) ?> endpoints</span>
                    </li>
                    <li class="list-group-item">
                        <b>Lead Source</b>
                        <span class="float-right"><?= escapeHtml($lead['lead_source'] ?: 'Direct') ?></span>
                    </li>
                    <li class="list-group-item">
                        <b>Assigned Agent</b>
                        <span class="float-right"><?= escapeHtml($lead['assigned_user_name'] ?: 'Unassigned') ?></span>
                    </li>
                    <li class="list-group-item">
                        <b>Next Follow-Up</b>
                        <span class="float-right font-weight-bold <?= (!empty($lead['lead_next_follow_up']) && strtotime($lead['lead_next_follow_up']) < strtotime('today')) ? 'text-danger' : 'text-primary' ?>">
                            <?= escapeHtml($lead['lead_next_follow_up'] ?: 'None set') ?>
                        </span>
                    </li>
                    <li class="list-group-item">
                        <b>Created</b>
                        <span class="float-right"><?= escapeHtml($lead['lead_created_at']) ?></span>
                    </li>
                </ul>

                <?php if (!empty($lead['lead_address']) || !empty($lead['lead_city'])) { ?>
                    <strong><i class="fas fa-map-marker-alt mr-1"></i> Address</strong>
                    <p class="text-muted mb-3">
                        <?= escapeHtml($lead['lead_address']) ?><br>
                        <?= escapeHtml($lead['lead_city']) ?>, <?= escapeHtml($lead['lead_state']) ?> <?= escapeHtml($lead['lead_zip']) ?><br>
                        <?= escapeHtml($lead['lead_country']) ?>
                    </p>
                <?php } ?>

                <?php if (!empty($lead['lead_notes'])) { ?>
                    <strong><i class="fas fa-sticky-note mr-1"></i> Background Notes</strong>
                    <p class="text-muted"><?= nl2br(escapeHtml($lead['lead_notes'])) ?></p>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit mr-2"></i>Quick Note / Activity Log</h3>
            </div>
            <div class="card-body">
                <form action="post.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="lead_id" value="<?= $lead_id ?>">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-2">
                                <select class="form-control form-control-sm" name="lead_activity_type">
                                    <option value="Note">General Note</option>
                                    <option value="Call">Phone Call</option>
                                    <option value="Meeting">Meeting / Discovery</option>
                                    <option value="Assessment">IT Assessment</option>
                                    <option value="Follow-up">Follow-Up</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group mb-2">
                                <input type="text" class="form-control form-control-sm" name="lead_activity_title" placeholder="Short summary title..." required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-2">
                        <textarea class="form-control form-control-sm" name="lead_activity_details" rows="2" placeholder="Add activity notes or discussion points..."></textarea>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="form-inline">
                            <label class="mr-2 text-muted" style="font-size: 0.85rem;">Update Next Follow-up:</label>
                            <input type="date" class="form-control form-control-sm" name="lead_next_follow_up" value="<?= escapeHtml($lead['lead_next_follow_up'] ?? '') ?>">
                        </div>
                        <button type="submit" name="add_lead_activity" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus mr-1"></i>Post Update
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history mr-2"></i>Activity & Communication Timeline</h3>
            </div>
            <div class="card-body">
                <?php
                $act_sql = mysqli_query($mysqli, "SELECT lead_activities.*, users.user_name FROM lead_activities LEFT JOIN users ON lead_activities.lead_activity_created_by = users.user_id WHERE lead_activity_lead_id = $lead_id ORDER BY lead_activity_id DESC");
                if (mysqli_num_rows($act_sql) === 0) {
                    echo "<div class='text-center text-muted py-4'>No activity recorded yet for this lead.</div>";
                } else {
                ?>
                    <ul class="lead-timeline">
                        <?php while ($act = mysqli_fetch_assoc($act_sql)) {
                            $type = $act['lead_activity_type'];
                            $icon = 'fa-sticky-note';
                            $icon_bg = '#17a2b8';
                            if ($type === 'Call') {
                                $icon = 'fa-phone';
                                $icon_bg = '#28a745';
                            } elseif ($type === 'Meeting') {
                                $icon = 'fa-handshake';
                                $icon_bg = '#6f42c1';
                            } elseif ($type === 'Email') {
                                $icon = 'fa-envelope';
                                $icon_bg = '#007bff';
                            } elseif ($type === 'Stage Change') {
                                $icon = 'fa-random';
                                $icon_bg = '#ffc107';
                            } elseif ($type === 'Conversion') {
                                $icon = 'fa-user-check';
                                $icon_bg = '#28a745';
                            } elseif ($type === 'Assessment') {
                                $icon = 'fa-shield-alt';
                                $icon_bg = '#fd7e14';
                            }
                        ?>
                            <li class="lead-timeline-item">
                                <div class="lead-timeline-icon" style="background-color: <?= $icon_bg ?>;">
                                    <i class="fas <?= $icon ?>"></i>
                                </div>
                                <div class="lead-timeline-content">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h6 class="mb-0 font-weight-bold"><?= escapeHtml($act['lead_activity_title']) ?></h6>
                                        <small class="text-muted"><?= timeAgo($act['lead_activity_created_at']) ?> (<?= escapeHtml($act['lead_activity_created_at']) ?>)</small>
                                    </div>
                                    <?php if (!empty($act['lead_activity_details'])) { ?>
                                        <div class="text-muted" style="font-size: 0.9rem;">
                                            <?= nl2br($act['lead_activity_details']) ?>
                                        </div>
                                    <?php } ?>
                                    <div class="mt-2 text-right" style="font-size: 0.75rem;">
                                        <span class="text-secondary"><i class="fas fa-user mr-1"></i><?= escapeHtml($act['user_name'] ?: 'System') ?></span>
                                    </div>
                                </div>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once "../includes/footer.php";
?>
