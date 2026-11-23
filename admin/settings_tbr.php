<?php

require_once "includes/inc_all_admin.php";

$s = tbrGetSettings();

$roles = [];
$res_roles = mysqli_query($mysqli, "SELECT r.*, p.* FROM user_roles r LEFT JOIN tbr_role_permissions p ON r.role_id = p.role_id WHERE r.role_archived_at IS NULL ORDER BY r.role_is_admin DESC, r.role_name ASC");
while ($res_roles && ($r = mysqli_fetch_assoc($res_roles))) {
    $roles[] = $r;
}
?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-chart-line mr-2"></i>Technology Business Review (TBR) Settings</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off" onsubmit="if (typeof tinymce !== 'undefined') tinymce.triggerSave();">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <h5 class="mb-3">General &amp; PDF Defaults</h5>
            <div class="row">
                <div class="col-md-4 form-group">
                    <label>Default Meeting Duration (minutes)</label>
                    <input type="number" min="15" max="240" class="form-control" name="default_duration_minutes" value="<?= intval($s['default_duration_minutes']) ?>">
                </div>
                <div class="col-md-8 form-group">
                    <label>Client PDF Confidentiality Footer Label</label>
                    <input type="text" class="form-control" name="default_confidentiality_label" maxlength="255" value="<?= escapeHtml($s['default_confidentiality_label']) ?>">
                </div>
            </div>

            <hr>
            <h5 class="mb-3">Automated Reminders &amp; Calendar Invitations</h5>
            <div class="row">
                <div class="col-md-6">
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="sw_ics" name="attach_ics_calendar" value="1" <?= !empty($s['attach_ics_calendar']) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="sw_ics">Attach Calendar Invitation (.ics) on Schedule / Reschedule / Cancel</label>
                    </div>
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="sw_conf" name="enable_confirmation_email" value="1" <?= !empty($s['enable_confirmation_email']) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="sw_conf">Send Initial Confirmation When Scheduled</label>
                    </div>
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="sw_7d" name="enable_7d_reminder" value="1" <?= !empty($s['enable_7d_reminder']) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="sw_7d">Send First Advance Reminder</label>
                    </div>
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="sw_1d" name="enable_1d_reminder" value="1" <?= !empty($s['enable_1d_reminder']) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="sw_1d">Send Second Advance Reminder</label>
                    </div>
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="sw_do" name="enable_day_of_reminder" value="1" <?= !empty($s['enable_day_of_reminder']) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="sw_do">Send Day-of-Meeting Reminder</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Reminder 1 (Days Before)</label>
                            <input type="number" min="1" class="form-control" name="reminder_1_days" value="<?= intval($s['reminder_1_days']) ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Reminder 2 (Days Before)</label>
                            <input type="number" min="1" class="form-control" name="reminder_2_days" value="<?= intval($s['reminder_2_days']) ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Day-Of (Hours Before)</label>
                            <input type="number" min="1" class="form-control" name="day_of_hours_before" value="<?= intval($s['day_of_hours_before']) ?>">
                        </div>
                    </div>
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="sw_int" name="enable_internal_reminders" value="1" <?= !empty($s['enable_internal_reminders']) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="sw_int">Enable Internal MSP Representative Reminders</label>
                    </div>
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="sw_ov" name="enable_overdue_reminders" value="1" <?= !empty($s['enable_overdue_reminders']) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="sw_ov">Notify MSP Representative When TBR Is Overdue</label>
                    </div>
                    <div class="form-inline">
                        <div class="custom-control custom-switch mr-2">
                            <input type="checkbox" class="custom-control-input" id="sw_act" name="enable_action_due_reminders" value="1" <?= !empty($s['enable_action_due_reminders']) ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="sw_act">Notify Assignees When Action Items Are Due Within</label>
                        </div>
                        <input type="number" min="1" class="form-control form-control-sm" style="width:70px;" name="action_due_days_before" value="<?= intval($s['action_due_days_before']) ?>">
                        <span class="ml-1 small">days</span>
                    </div>
                </div>
            </div>

            <div class="table-responsive mt-3">
                <table class="table table-sm table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>Audience</th>
                            <th class="text-center">When Scheduled</th>
                            <th class="text-center">7 Days Before</th>
                            <th class="text-center">1 Day Before</th>
                            <th class="text-center">Day of Meeting</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Selected Client Attendees</strong></td>
                            <td class="text-center"><input type="checkbox" name="send_client_confirmation" value="1" <?= !empty($s['send_client_confirmation']) ? 'checked' : '' ?>></td>
                            <td class="text-center"><input type="checkbox" name="send_client_7d" value="1" <?= !empty($s['send_client_7d']) ? 'checked' : '' ?>></td>
                            <td class="text-center"><input type="checkbox" name="send_client_1d" value="1" <?= !empty($s['send_client_1d']) ? 'checked' : '' ?>></td>
                            <td class="text-center"><input type="checkbox" name="send_client_day_of" value="1" <?= !empty($s['send_client_day_of']) ? 'checked' : '' ?>></td>
                        </tr>
                        <tr>
                            <td><strong>Internal MSP Attendees</strong></td>
                            <td class="text-center"><input type="checkbox" name="send_internal_confirmation" value="1" <?= !empty($s['send_internal_confirmation']) ? 'checked' : '' ?>></td>
                            <td class="text-center"><input type="checkbox" name="send_internal_7d" value="1" <?= !empty($s['send_internal_7d']) ? 'checked' : '' ?>></td>
                            <td class="text-center"><input type="checkbox" name="send_internal_1d" value="1" <?= !empty($s['send_internal_1d']) ? 'checked' : '' ?>></td>
                            <td class="text-center"><input type="checkbox" name="send_internal_day_of" value="1" <?= !empty($s['send_internal_day_of']) ? 'checked' : '' ?>></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <hr>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Default Email Templates</h5>
                <button type="submit" name="reset_tbr_templates" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Reset all 4 TBR email templates back to default formatted HTML?');">
                    <i class="fas fa-undo mr-1"></i>Reset Templates to Default
                </button>
            </div>
            <p class="small text-muted">Available variables: <code>{{client_name}}</code>, <code>{{tbr_title}}</code>, <code>{{meeting_date}}</code>, <code>{{meeting_time}}</code>, <code>{{duration}}</code>, <code>{{location}}</code>, <code>{{location_html}}</code>, <code>{{meeting_details_card}}</code>, <code>{{msp_name}}</code>, <code>{{rep_name}}</code>, <code>{{rep_email}}</code>, <code>{{attendee_name}}</code>, <code>{{reporting_period}}</code>, <code>{{next_review_date}}</code></p>

            <div class="row">
                <div class="col-md-6 form-group">
                    <label>Confirmation Subject</label>
                    <input type="text" class="form-control mb-2" name="tpl_confirmation_subject" value="<?= escapeHtml($s['tpl_confirmation_subject']) ?>">
                    <label>Confirmation Body (HTML)</label>
                    <textarea class="form-control tinymce" rows="6" name="tpl_confirmation_body"><?= escapeHtml($s['tpl_confirmation_body']) ?></textarea>
                </div>
                <div class="col-md-6 form-group">
                    <label>Client Reminder Subject</label>
                    <input type="text" class="form-control mb-2" name="tpl_reminder_subject" value="<?= escapeHtml($s['tpl_reminder_subject']) ?>">
                    <label>Client Reminder Body (HTML)</label>
                    <textarea class="form-control tinymce" rows="6" name="tpl_reminder_body"><?= escapeHtml($s['tpl_reminder_body']) ?></textarea>
                </div>
                <div class="col-md-6 form-group">
                    <label>Client Report Delivery Subject</label>
                    <input type="text" class="form-control mb-2" name="tpl_report_subject" value="<?= escapeHtml($s['tpl_report_subject']) ?>">
                    <label>Client Report Delivery Body (HTML)</label>
                    <textarea class="form-control tinymce" rows="6" name="tpl_report_body"><?= escapeHtml($s['tpl_report_body']) ?></textarea>
                </div>
                <div class="col-md-6 form-group">
                    <label>Internal MSP Reminder Subject</label>
                    <input type="text" class="form-control mb-2" name="tpl_internal_reminder_subject" value="<?= escapeHtml($s['tpl_internal_reminder_subject']) ?>">
                    <label>Internal MSP Reminder Body (HTML)</label>
                    <textarea class="form-control tinymce" rows="6" name="tpl_internal_reminder_body"><?= escapeHtml($s['tpl_internal_reminder_body']) ?></textarea>
                </div>
            </div>

            <hr>
            <h5 class="mb-2">Granular TBR Role Permissions</h5>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>Role</th>
                            <th class="text-center">View</th>
                            <th class="text-center">Schedule</th>
                            <th class="text-center">Edit</th>
                            <th class="text-center">Complete</th>
                            <th class="text-center">Gen PDF</th>
                            <th class="text-center">Email Report</th>
                            <th class="text-center">Internal Notes</th>
                            <th class="text-center">Reopen</th>
                            <th class="text-center">Manage Settings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $perm_cols = ['can_view', 'can_schedule', 'can_edit', 'can_complete', 'can_generate_pdf', 'can_email_report', 'can_view_internal_notes', 'can_reopen', 'can_manage_settings'];
                        foreach ($roles as $rl) {
                            $rid = intval($rl['role_id']);
                            $is_adm = !empty($rl['role_is_admin']) || $rid === 3;
                        ?>
                            <tr>
                                <td><strong><?= escapeHtml($rl['role_name']) ?></strong><?= $is_adm ? ' <span class="badge badge-danger">Admin</span>' : '' ?></td>
                                <?php foreach ($perm_cols as $pc) { ?>
                                    <td class="text-center">
                                        <input type="checkbox" name="perms[<?= $rid ?>][<?= $pc ?>]" value="1" <?= ($is_adm || !empty($rl[$pc])) ? 'checked' : '' ?> <?= $is_adm ? 'disabled' : '' ?>>
                                    </td>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <hr>
            <button type="submit" name="save_tbr_settings" class="btn btn-primary text-bold">
                <i class="fas fa-check mr-2"></i>Save TBR Settings &amp; Permissions
            </button>
        </form>
    </div>
</div>

<?php require_once "../includes/footer.php";
