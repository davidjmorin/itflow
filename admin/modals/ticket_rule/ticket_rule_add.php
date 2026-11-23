<?php
require_once '../../includes/modal_header.php';

$clients_sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
$users_sql = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_archived_at IS NULL AND user_status = 1 ORDER BY user_name ASC");
$categories_sql = mysqli_query($mysqli, "SELECT category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL ORDER BY category_name ASC");
$statuses_sql = mysqli_query($mysqli, "SELECT ticket_status_id, ticket_status_name FROM ticket_statuses WHERE ticket_status_active = 1 ORDER BY ticket_status_order ASC");

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-filter mr-2"></i>New Inbound Ticket Rule</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <div class="row">
            <div class="col-md-8">
                <div class="form-group">
                    <label>Rule Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Block Vendor Spam, Route Backup Alerts" maxlength="255" required autofocus>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Order</label>
                    <input type="number" class="form-control" name="order" value="0" min="0">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group text-center">
                    <label>Active</label>
                    <div class="custom-control custom-switch mt-2">
                        <input type="checkbox" class="custom-control-input" id="ruleActiveSwitch" name="active" value="1" checked>
                        <label class="custom-control-label" for="ruleActiveSwitch"></label>
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
                        <option value="all">Match ALL specified conditions (AND)</option>
                        <option value="any">Match ANY specified condition (OR)</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Sender Email</label>
                            <input type="text" class="form-control" name="sender_email" placeholder="e.g. alerts@domain.com, *@spammer.com">
                            <small class="text-muted">Comma-separated emails or wildcards</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Sender Domain</label>
                            <input type="text" class="form-control" name="sender_domain" placeholder="e.g. spammer.com, alerts.backup.com">
                            <small class="text-muted">Domain or sub-domain</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Subject Contains</label>
                    <input type="text" class="form-control" name="subject" placeholder="e.g. [CRITICAL], Backup Failed, Newsletter">
                </div>

                <div class="form-group">
                    <label>Body Contains</label>
                    <textarea class="form-control" name="body" rows="2" placeholder="Keyword or phrase in email message body"></textarea>
                </div>

                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="ruleRegexCheck" name="use_regex" value="1">
                    <label class="custom-control-label" for="ruleRegexCheck">Evaluate subject and body patterns as Regular Expressions</label>
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
                        <label class="btn btn-outline-success active flex-fill">
                            <input type="radio" name="action" id="actionRoute" value="route" checked onchange="toggleRuleActionFields()">
                            <i class="fas fa-route mr-1"></i> Route / Create Ticket
                        </label>
                        <label class="btn btn-outline-danger flex-fill">
                            <input type="radio" name="action" id="actionIgnore" value="ignore" onchange="toggleRuleActionFields()">
                            <i class="fas fa-ban mr-1"></i> Ignore / Mark as Spam (No Ticket)
                        </label>
                    </div>
                </div>

                <div id="routeActionFields">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Assign to Client</label>
                                <select class="form-control select2" name="client_id" style="width: 100%;">
                                    <option value="0">- Auto-Detect / Default -</option>
                                    <?php while ($c = mysqli_fetch_assoc($clients_sql)) { ?>
                                        <option value="<?= intval($c['client_id']) ?>"><?= escapeHtml($c['client_name']) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Priority</label>
                                <select class="form-control" name="priority">
                                    <option value="">- Default (Low) -</option>
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                    <option value="Critical">Critical</option>
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
                                        <option value="<?= escapeHtml($cat['category_name']) ?>"><?= escapeHtml($cat['category_name']) ?></option>
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
                                        <option value="<?= intval($u['user_id']) ?>"><?= escapeHtml($u['user_name']) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Initial Status</label>
                        <select class="form-control" name="status">
                            <?php while ($s = mysqli_fetch_assoc($statuses_sql)) { ?>
                                <option value="<?= intval($s['ticket_status_id']) ?>"><?= escapeHtml($s['ticket_status_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="custom-control custom-switch mt-2">
                    <input type="checkbox" class="custom-control-input" id="ruleStopSwitch" name="stop_processing" value="1" checked>
                    <label class="custom-control-label" for="ruleStopSwitch">Stop evaluating further rules when this rule matches</label>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="add_ticket_rule" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create Rule</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<script>
function toggleRuleActionFields() {
    const isIgnore = document.getElementById('actionIgnore').checked;
    const fields = document.getElementById('routeActionFields');
    if (fields) {
        fields.style.display = isIgnore ? 'none' : 'block';
    }
}
toggleRuleActionFields();
$('.select2').select2({
    theme: 'bootstrap4'
});
</script>

<?php
require_once '../../../includes/modal_footer.php';
