<?php

require_once __DIR__ . '/../../../includes/modal_header.php';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-funnel-dollar mr-2"></i>Add Lead</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off" style="display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="p-2 bg-light border-bottom">
        <ul class="nav nav-pills nav-justified">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#lead-add-general"><i class="fas fa-building mr-1"></i>General</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#lead-add-pipeline"><i class="fas fa-funnel-dollar mr-1"></i>Pipeline</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#lead-add-location"><i class="fas fa-map-marker-alt mr-1"></i>Address & Notes</a>
            </li>
        </ul>
    </div>

    <div class="modal-body" style="overflow-y: auto; max-height: calc(75vh - 120px);">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="lead-add-general">
                <div class="form-group">
                    <label>Company / Lead Name <strong class="text-danger">*</strong></label>
                    <input type="text" class="form-control" name="lead_name" placeholder="e.g. Acme Corp" required autofocus>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Primary Contact Name</label>
                            <input type="text" class="form-control" name="lead_contact_name" placeholder="e.g. John Doe">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Job Title</label>
                            <input type="text" class="form-control" name="lead_contact_title" placeholder="e.g. CEO / IT Director">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" class="form-control" name="lead_contact_email" placeholder="john@example.com">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" class="form-control" name="lead_contact_phone" placeholder="e.g. (555) 012-3456">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Mobile Phone</label>
                            <input type="text" class="form-control" name="lead_contact_mobile" placeholder="e.g. (555) 987-6543">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Website</label>
                            <input type="text" class="form-control" name="lead_website" placeholder="e.g. example.com">
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="lead-add-pipeline">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Pipeline Stage <strong class="text-danger">*</strong></label>
                            <select class="form-control select2" name="lead_stage_id" required>
                                <?php
                                $stages_query = mysqli_query($mysqli, "SELECT * FROM lead_stages WHERE lead_stage_active = 1 ORDER BY lead_stage_order ASC");
                                while ($stage = mysqli_fetch_assoc($stages_query)) {
                                    $s_id = intval($stage['lead_stage_id']);
                                    $s_name = escapeHtml($stage['lead_stage_name']);
                                    echo "<option value='$s_id'>$s_name</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Lead Source</label>
                            <input type="text" class="form-control" name="lead_source" placeholder="e.g. Referral, Website, Cold Call">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Assigned Agent</label>
                            <select class="form-control select2" name="lead_assigned_user_id">
                                <option value="0">- Unassigned -</option>
                                <?php
                                $users_query = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
                                while ($user = mysqli_fetch_assoc($users_query)) {
                                    $u_id = intval($user['user_id']);
                                    $u_name = escapeHtml($user['user_name']);
                                    $selected = ($u_id == $session_user_id) ? 'selected' : '';
                                    echo "<option value='$u_id' $selected>$u_name</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Next Follow-Up Date</label>
                            <input type="date" class="form-control" name="lead_next_follow_up">
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
                                <input type="number" step="0.01" class="form-control" name="lead_estimated_mrr" value="0.00">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Estimated Seats / Endpoints</label>
                            <input type="number" class="form-control" name="lead_estimated_seats" value="0" min="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="lead-add-location">
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" class="form-control" name="lead_address" placeholder="Street Address">
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" class="form-control" name="lead_city" placeholder="City">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>State / Province</label>
                            <input type="text" class="form-control" name="lead_state" placeholder="State">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Zip / Postal Code</label>
                            <input type="text" class="form-control" name="lead_zip" placeholder="Zip">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Country</label>
                    <input type="text" class="form-control" name="lead_country" value="<?= escapeHtml($session_company_country ?? '') ?>" placeholder="Country">
                </div>

                <div class="form-group">
                    <label>Initial Notes / Scope</label>
                    <textarea class="form-control" name="lead_notes" rows="4" placeholder="Enter background details, discovery observations, or requirements..."></textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer bg-white border-top">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" name="add_lead" class="btn btn-primary"><i class="fas fa-check mr-2"></i>Save Lead</button>
    </div>
</form>

<?php
require_once __DIR__ . '/../../../includes/modal_footer.php';
?>
