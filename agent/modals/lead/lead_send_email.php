<?php

require_once __DIR__ . '/../../../includes/modal_header.php';

$lead_id = intval($_GET['lead_id'] ?? 0);
$lead_sql = mysqli_query($mysqli, "SELECT * FROM leads WHERE lead_id = $lead_id LIMIT 1");
if (mysqli_num_rows($lead_sql) === 0) {
    echo json_encode(['error' => 'Lead not found.']);
    exit();
}
$lead = mysqli_fetch_assoc($lead_sql);

$tpl_query = mysqli_query($mysqli, "SELECT * FROM lead_email_templates ORDER BY lead_email_template_name ASC");
$tpl_data = [];
while ($tpl = mysqli_fetch_assoc($tpl_query)) {
    $t_id = intval($tpl['lead_email_template_id']);
    $t_name = escapeHtml($tpl['lead_email_template_name']);
    $subject_raw = $tpl['lead_email_template_subject'];
    $body_raw = $tpl['lead_email_template_body'];

    $replace_vars = [
        '{lead_name}' => $lead['lead_name'] ?? '',
        '{contact_name}' => $lead['lead_contact_name'] ?: ($lead['lead_name'] ?? ''),
        '{contact_email}' => $lead['lead_contact_email'] ?? '',
        '{contact_phone}' => $lead['lead_contact_phone'] ?? '',
        '{company_name}' => $session_company_name ?? 'Our Company',
        '{sender_name}' => $session_name ?? '',
        '{sender_email}' => $session_email ?? '',
        '{sender_phone}' => $session_company_phone ?? ''
    ];

    $parsed_subject = str_replace(array_keys($replace_vars), array_values($replace_vars), $subject_raw);
    $parsed_body = str_replace(array_keys($replace_vars), array_values($replace_vars), $body_raw);

    $tpl_data[$t_id] = [
        'name' => $t_name,
        'subject' => $parsed_subject,
        'body' => $parsed_body
    ];
}

ob_start();

?>

<div class="modal-header bg-primary text-white">
    <h5 class="modal-title"><i class="fas fa-fw fa-paper-plane mr-2"></i>Send Email to Lead</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off" style="display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="lead_id" value="<?= $lead_id ?>">
    <div class="modal-body" style="overflow-y: auto; max-height: calc(75vh - 120px);">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Recipient Name</label>
                    <input type="text" class="form-control" name="recipient_name" value="<?= escapeHtml($lead['lead_contact_name'] ?: $lead['lead_name']) ?>" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Recipient Email <strong class="text-danger">*</strong></label>
                    <input type="email" class="form-control" name="recipient_email" value="<?= escapeHtml($lead['lead_contact_email'] ?? '') ?>" required>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label>Select Outreach Template</label>
                    <div class="input-group">
                        <select class="form-control select2" id="lead_template_select" onchange="applyLeadTemplate()">
                            <option value="">- Custom Message (No Template) -</option>
                            <?php foreach ($tpl_data as $t_id => $tpl_item) { ?>
                                <option value="<?= $t_id ?>"><?= $tpl_item['name'] ?></option>
                            <?php } ?>
                        </select>
                        <div class="input-group-append">
                            <a href="lead_email_templates.php" target="_blank" class="btn btn-outline-secondary" title="Manage Templates">
                                <i class="fas fa-cog"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Subject <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="email_subject" id="lead_email_subject" placeholder="Subject line" required>
        </div>

        <div class="form-group">
            <label>Email Body <strong class="text-danger">*</strong></label>
            <textarea class="form-control tinymce" name="email_body" id="lead_email_body" rows="10"></textarea>
        </div>

    </div>
    <div class="modal-footer bg-white border-top">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" name="send_lead_email" class="btn btn-primary"><i class="fas fa-paper-plane mr-2"></i>Send Email</button>
    </div>
</form>

<script>
    var leadTemplateStore = <?= json_encode($tpl_data) ?>;
    function applyLeadTemplate() {
        var select = document.getElementById('lead_template_select');
        var selectedId = select.value;
        if (selectedId && leadTemplateStore[selectedId]) {
            document.getElementById('lead_email_subject').value = leadTemplateStore[selectedId].subject;
            if (typeof tinymce !== 'undefined' && tinymce.get('lead_email_body')) {
                tinymce.get('lead_email_body').setContent(leadTemplateStore[selectedId].body);
            } else {
                document.getElementById('lead_email_body').value = leadTemplateStore[selectedId].body;
            }
        }
    }
</script>

<?php
require_once __DIR__ . '/../../../includes/modal_footer.php';
?>
