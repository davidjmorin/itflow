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

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-edit mr-2"></i>Edit Lead: <strong><?= escapeHtml($lead['lead_name']) ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off" style="display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="lead_id" value="<?= $lead_id ?>">

    <div class="p-2 bg-light border-bottom">
        <ul class="nav nav-pills nav-justified">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#lead-edit-general"><i class="fas fa-building mr-1"></i>General</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#lead-edit-pipeline"><i class="fas fa-funnel-dollar mr-1"></i>Pipeline</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#lead-edit-location"><i class="fas fa-map-marker-alt mr-1"></i>Address & Notes</a>
            </li>
        </ul>
    </div>

    <div class="modal-body" style="overflow-y: auto; max-height: calc(75vh - 120px);">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="lead-edit-general">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Company / Lead Name <strong class="text-danger">*</strong></label>
                            <input type="text" class="form-control" name="lead_name" value="<?= escapeHtml($lead['lead_name']) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="lead_status">
                                <option value="Open" <?= $lead['lead_status'] == 'Open' ? 'selected' : '' ?>>Open</option>
                                <option value="Converted" <?= $lead['lead_status'] == 'Converted' ? 'selected' : '' ?>>Converted</option>
                                <option value="Lost" <?= $lead['lead_status'] == 'Lost' ? 'selected' : '' ?>>Lost</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Primary Contact Name</label>
                            <input type="text" class="form-control" name="lead_contact_name" value="<?= escapeHtml($lead['lead_contact_name'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Job Title</label>
                            <input type="text" class="form-control" name="lead_contact_title" value="<?= escapeHtml($lead['lead_contact_title'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" class="form-control" name="lead_contact_email" value="<?= escapeHtml($lead['lead_contact_email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" class="form-control" name="lead_contact_phone" value="<?= escapeHtml($lead['lead_contact_phone'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Mobile Phone</label>
                            <input type="text" class="form-control" name="lead_contact_mobile" value="<?= escapeHtml($lead['lead_contact_mobile'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Website</label>
                            <input type="text" class="form-control" name="lead_website" value="<?= escapeHtml($lead['lead_website'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="lead-edit-pipeline">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Pipeline Stage <strong class="text-danger">*</strong></label>
                            <select class="form-control" name="lead_stage_id" required>
                                <?php
                                $stages_query = mysqli_query($mysqli, "SELECT * FROM lead_stages WHERE lead_stage_active = 1 ORDER BY lead_stage_order ASC");
                                while ($stage = mysqli_fetch_assoc($stages_query)) {
                                    $s_id = intval($stage['lead_stage_id']);
                                    $s_name = escapeHtml($stage['lead_stage_name']);
                                    $selected = ($s_id == $lead['lead_stage_id']) ? 'selected' : '';
                                    echo "<option value='$s_id' $selected>$s_name</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Lead Source</label>
                            <input type="text" class="form-control" name="lead_source" value="<?= escapeHtml($lead['lead_source'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Assigned Agent</label>
                            <select class="form-control" name="lead_assigned_user_id">
                                <option value="0">- Unassigned -</option>
                                <?php
                                $users_query = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
                                while ($user = mysqli_fetch_assoc($users_query)) {
                                    $u_id = intval($user['user_id']);
                                    $u_name = escapeHtml($user['user_name']);
                                    $selected = ($u_id == $lead['lead_assigned_user_id']) ? 'selected' : '';
                                    echo "<option value='$u_id' $selected>$u_name</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Next Follow-Up Date</label>
                            <input type="date" class="form-control" name="lead_next_follow_up" value="<?= escapeHtml($lead['lead_next_follow_up'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Estimated MRR (<?= escapeHtml($session_company_currency ?? '$') ?>)</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><?= escapeHtml($session_company_currency ?? '$') ?></span>
                                </div>
                                <input type="number" step="0.01" class="form-control" name="lead_estimated_mrr" value="<?= escapeHtml($lead['lead_estimated_mrr']) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Estimated Seats / Endpoints</label>
                            <input type="number" class="form-control" name="lead_estimated_seats" value="<?= intval($lead['lead_estimated_seats']) ?>" min="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="lead-edit-location">
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" class="form-control" name="lead_address" value="<?= escapeHtml($lead['lead_address'] ?? '') ?>">
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" class="form-control" name="lead_city" value="<?= escapeHtml($lead['lead_city'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>State / Province</label>
                            <input type="text" class="form-control" name="lead_state" value="<?= escapeHtml($lead['lead_state'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Zip / Postal Code</label>
                            <input type="text" class="form-control" name="lead_zip" value="<?= escapeHtml($lead['lead_zip'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Country</label>
                    <input type="text" class="form-control" name="lead_country" value="<?= escapeHtml($lead['lead_country'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Notes / Scope</label>
                    <textarea class="form-control" name="lead_notes" rows="4"><?= escapeHtml($lead['lead_notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer bg-white border-top">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" name="edit_lead" class="btn btn-primary"><i class="fas fa-check mr-2"></i>Save Changes</button>
    </div>
</form>

<?php
require_once __DIR__ . '/../../../includes/modal_footer.php';
?>
