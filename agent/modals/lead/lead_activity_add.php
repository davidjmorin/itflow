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
    <h5 class="modal-title"><i class="fas fa-fw fa-plus-circle mr-2"></i>Log Lead Activity</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="lead_id" value="<?= $lead_id ?>">
    <div class="modal-body">
        <div class="form-group">
            <label>Activity Type <strong class="text-danger">*</strong></label>
            <select class="form-control" name="lead_activity_type" required>
                <option value="Note">General Note</option>
                <option value="Call">Phone Call</option>
                <option value="Meeting">Meeting / Discovery</option>
                <option value="Assessment">IT / Security Assessment</option>
                <option value="Proposal">Proposal Review</option>
                <option value="Follow-up">Follow-Up</option>
            </select>
        </div>

        <div class="form-group">
            <label>Subject / Title <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="lead_activity_title" placeholder="e.g. Discovery Call with CEO" required>
        </div>

        <div class="form-group">
            <label>Details / Summary</label>
            <textarea class="form-control" name="lead_activity_details" rows="4" placeholder="Enter key points, next steps, or discussion summary..."></textarea>
        </div>

        <div class="form-group">
            <label>Update Next Follow-Up Date (Optional)</label>
            <input type="date" class="form-control" name="lead_next_follow_up" value="<?= escapeHtml($lead['lead_next_follow_up'] ?? '') ?>">
        </div>

    </div>
    <div class="modal-footer bg-white">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" name="add_lead_activity" class="btn btn-primary"><i class="fas fa-check mr-2"></i>Log Activity</button>
    </div>
</form>

<?php
require_once __DIR__ . '/../../../includes/modal_footer.php';
?>
