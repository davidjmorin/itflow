<?php
require_once '../../includes/modal_header.php';

$rule_id = intval($_GET['id'] ?? $_GET['ticket_rule_id'] ?? 0);
$rule_sql = mysqli_query($mysqli, "SELECT * FROM ticket_rules WHERE ticket_rule_id = $rule_id LIMIT 1");
$rule = mysqli_fetch_assoc($rule_sql);

if (!$rule) {
    echo json_encode(['error' => 'Rule not found']);
    exit;
}

$clients_sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
$users_sql = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_archived_at IS NULL AND user_status = 1 ORDER BY user_name ASC");
$categories_sql = mysqli_query($mysqli, "SELECT category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL ORDER BY category_name ASC");
$statuses_sql = mysqli_query($mysqli, "SELECT ticket_status_id, ticket_status_name FROM ticket_statuses WHERE ticket_status_active = 1 ORDER BY ticket_status_order ASC");

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-filter mr-2"></i>Edit Inbound Ticket Rule: <strong><?= escapeHtml($rule['ticket_rule_name']) ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_rule_id" value="<?= $rule_id ?>">

    <div class="modal-body">
        <div class="row">
            <div class="col-md-8">
                <div class="form-group">
                    <label>Rule Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" value="<?= escapeHtml($rule['ticket_rule_name']) ?>" required autofocus>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Order</label>
                    <input type="number" class="form-control" name="order" value="<?= intval($rule['ticket_rule_order']) ?>" min="0">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group text-center">
                    <label>Active</label>
                    <div class="custom-control custom-switch mt-2">
                        <input type="checkbox" class="custom-control-input" id="editRuleActiveSwitch" name="active" value="1" <?= $rule['ticket_rule_active'] ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="editRuleActiveSwitch"></label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-secondary mb-3">
            <div class="card-header py-2">
                <h6 class="card-title font-weight-bold mb-0"><i class="fas fa-search mr-2"></i>Conditions (Email Matching)</h6>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Match Logic</label>
                    <select class="form-control" name="match_type">
                        <option value="all" <?= $rule['ticket_rule_match_type'] === 'all' ? 'selected' : '' ?>>Match ALL specified conditions (AND)</option>
                        <option value="any" <?= $rule['ticket_rule_match_type'] === 'any' ? 'selected' : '' ?>>Match ANY specified condition (OR)</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Sender Email</label>
                            <input type="text" class="form-control" name="sender_email" value="<?= escapeHtml($rule['ticket_rule_sender_email'] ?? '') ?>" placeholder="e.g. alerts@domain.com, *@spammer.com">
                            <small class="text-muted">Comma-separated emails or wildcards</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Sender Domain</label>
                            <input type="text" class="form-control" name="sender_domain" value="<?= escapeHtml($rule['ticket_rule_sender_domain'] ?? '') ?>" placeholder="e.g. spammer.com, alerts.backup.com">
                            <small class="text-muted">Domain or sub-domain</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Subject Contains</label>
                    <input type="text" class="form-control" name="subject" value="<?= escapeHtml($rule['ticket_rule_subject'] ?? '') ?>" placeholder="e.g. [CRITICAL], Backup Failed, Newsletter">
                </div>

                <div class="form-group">
                    <label>Body Contains</label>
                    <textarea class="form-control" name="body" rows="2" placeholder="Keyword or phrase in email message body"><?= escapeHtml($rule['ticket_rule_body'] ?? '') ?></textarea>
                </div>

                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="editRuleRegexCheck" name="use_regex" value="1" <?= !empty($rule['ticket_rule_use_regex']) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="editRuleRegexCheck">Evaluate subject and body patterns as Regular Expressions</label>
                </div>
            </div>
        </div>

        <div class="card card-outline card-primary mb-3">
            <div class="card-header py-2">
                <h6 class="card-title font-weight-bold mb-0"><i class="fas fa-cogs mr-2"></i>Action (What to Do)</h6>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Action Type</label>
                    <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons">
                        <label class="btn btn-outline-success <?= $rule['ticket_rule_action'] === 'route' ? 'active' : '' ?> flex-fill">
                            <input type="radio" name="action" id="editActionRoute" value="route" <?= $rule['ticket_rule_action'] === 'route' ? 'checked' : '' ?> onchange="toggleEditRuleActionFields()">
                            <i class="fas fa-route mr-1"></i> Route / Create Ticket
                        </label>
                        <label class="btn btn-outline-danger <?= $rule['ticket_rule_action'] === 'ignore' ? 'active' : '' ?> flex-fill">
                            <input type="radio" name="action" id="editActionIgnore" value="ignore" <?= $rule['ticket_rule_action'] === 'ignore' ? 'checked' : '' ?> onchange="toggleEditRuleActionFields()">
                            <i class="fas fa-ban mr-1"></i> Ignore / Mark as Spam (No Ticket)
                        </label>
                    </div>
                </div>

                <div id="editRouteActionFields">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Assign to Client</label>
                                <select class="form-control select2" name="client_id" style="width: 100%;">
                                    <option value="0">- Auto-Detect / Default -</option>
                                    <?php while ($c = mysqli_fetch_assoc($clients_sql)) { ?>
                                        <option value="<?= intval($c['client_id']) ?>" <?= intval($rule['ticket_rule_client_id']) === intval($c['client_id']) ? 'selected' : '' ?>><?= escapeHtml($c['client_name']) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Priority</label>
                                <select class="form-control" name="priority">
                                    <option value="">- Default (Low) -</option>
                                    <option value="Low" <?= $rule['ticket_rule_priority'] === 'Low' ? 'selected' : '' ?>>Low</option>
                                    <option value="Medium" <?= $rule['ticket_rule_priority'] === 'Medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="High" <?= $rule['ticket_rule_priority'] === 'High' ? 'selected' : '' ?>>High</option>
                                    <option value="Critical" <?= $rule['ticket_rule_priority'] === 'Critical' ? 'selected' : '' ?>>Critical</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Category</label>
                                <select class="form-control select2" name="category" style="width: 100%;">
                                    <option value="">- None / Default -</option>
                                    <?php while ($cat = mysqli_fetch_assoc($categories_sql)) { ?>
                                        <option value="<?= escapeHtml($cat['category_name']) ?>" <?= $rule['ticket_rule_category'] === $cat['category_name'] ? 'selected' : '' ?>><?= escapeHtml($cat['category_name']) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Assign Technician</label>
                                <select class="form-control select2" name="assigned_to" style="width: 100%;">
                                    <option value="0">- Unassigned -</option>
                                    <?php while ($u = mysqli_fetch_assoc($users_sql)) { ?>
                                        <option value="<?= intval($u['user_id']) ?>" <?= intval($rule['ticket_rule_assigned_to']) === intval($u['user_id']) ? 'selected' : '' ?>><?= escapeHtml($u['user_name']) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Initial Status</label>
                        <select class="form-control" name="status">
                            <?php while ($s = mysqli_fetch_assoc($statuses_sql)) { ?>
                                <option value="<?= intval($s['ticket_status_id']) ?>" <?= intval($rule['ticket_rule_status']) === intval($s['ticket_status_id']) ? 'selected' : '' ?>><?= escapeHtml($s['ticket_status_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="custom-control custom-switch mt-2">
                    <input type="checkbox" class="custom-control-input" id="editRuleStopSwitch" name="stop_processing" value="1" <?= !empty($rule['ticket_rule_stop_processing']) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="editRuleStopSwitch">Stop evaluating further rules when this rule matches</label>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket_rule" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save Changes</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<script>
function toggleEditRuleActionFields() {
    const isIgnore = document.getElementById('editActionIgnore').checked;
    const fields = document.getElementById('editRouteActionFields');
    if (fields) {
        fields.style.display = isIgnore ? 'none' : 'block';
    }
}
toggleEditRuleActionFields();
$('.select2').select2({
    theme: 'bootstrap4'
});
</script>

<?php
require_once '../../../includes/modal_footer.php';
