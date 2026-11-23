<?php

require_once "includes/inc_all_admin.php";

smsgateEnsureTechWeeklyTable($mysqli);

$sql_weekly_techs = mysqli_query($mysqli, "
    SELECT 
        u.user_id,
        u.user_name,
        u.user_email,
        COALESCE(twsc.enabled, 0) AS enabled,
        COALESCE(twsc.sms_numbers, '') AS sms_numbers,
        COALESCE(twsc.email_enabled, 1) AS email_enabled,
        COALESCE(twsc.extra_emails, '') AS extra_emails
    FROM users u
    LEFT JOIN tech_weekly_summary_configs twsc ON u.user_id = twsc.user_id
    WHERE u.user_type = 1 AND u.user_status = 1 AND u.user_archived_at IS NULL
    ORDER BY u.user_name ASC
");

$prior_range = smsgateGetPriorWeekRange();

?>

<div class="row">
    <div class="col-md-8">
        <div class="card card-dark">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-sms mr-2"></i>SMS Gate Settings</h3>
            </div>
            <div class="card-body">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="config_smsgate_enable" name="config_smsgate_enable" value="1" <?= $config_smsgate_enable ? 'checked' : '' ?>>
                            <label class="custom-control-label font-weight-bold" for="config_smsgate_enable">Enable SMSgate Integration</label>
                        </div>
                        <small class="form-text text-muted">When enabled, SMS notifications will be dispatched via SMSgate.</small>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>API Endpoint URL <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                            </div>
                            <input type="url" class="form-control" name="config_smsgate_url" value="<?= escapeHtml($config_smsgate_url) ?>" placeholder="https://api.sms-gate.app/3rdparty/v1/messages" required>
                        </div>
                        <small class="form-text text-muted">Cloud endpoint: <code>https://api.sms-gate.app/3rdparty/v1/messages</code> or Local Server: <code>http://&lt;phone_ip&gt;:8080/message</code></small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Username / Login</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="config_smsgate_username" value="<?= escapeHtml($config_smsgate_username) ?>" placeholder="Username">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Password / Token</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                                    </div>
                                    <input type="password" class="form-control" name="config_smsgate_password" value="<?= escapeHtml($config_smsgate_password) ?>" placeholder="Password or API Token" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>Emergency Alert Recipients (Urgent Tickets)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-phone-alt"></i></span>
                            </div>
                            <textarea class="form-control" name="config_smsgate_emergency_recipients" rows="3" placeholder="2079290089, (555) 123-4567"><?= escapeHtml($config_smsgate_emergency_recipients) ?></textarea>
                        </div>
                        <small class="form-text text-muted">Comma or newline separated list of phone numbers to receive SMS alerts whenever an <strong>URGENT</strong> ticket is created. 10-digit numbers default to US (+1) automatically.</small>
                    </div>

                    <div class="form-group">
                        <label>Urgent Ticket Alert Message Template</label>
                        <textarea class="form-control" name="config_smsgate_urgent_message" rows="2" placeholder="URGENT Ticket #{ticket_ref} ({client_name}): {ticket_subject}"><?= escapeHtml($config_smsgate_urgent_message) ?></textarea>
                        <small class="form-text text-muted">Placeholders: <code>{ticket_ref}</code>, <code>{ticket_number}</code>, <code>{ticket_prefix}</code>, <code>{ticket_subject}</code>, <code>{client_name}</code>, <code>{contact_name}</code></small>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>Ticket Acknowledgement Message Template</label>
                        <textarea class="form-control" name="config_smsgate_ack_message" rows="2" placeholder="Your ticket #{ticket_ref} ({ticket_subject}) has been received. Our team is reviewing it."><?= escapeHtml($config_smsgate_ack_message) ?></textarea>
                        <small class="form-text text-muted">Sent to the client/submitter whenever any ticket is created, if a phone number is provided. Placeholders: <code>{ticket_ref}</code>, <code>{ticket_number}</code>, <code>{ticket_prefix}</code>, <code>{ticket_subject}</code>, <code>{client_name}</code>, <code>{contact_name}</code></small>
                    </div>

                    <button type="submit" name="edit_smsgate_settings" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save Settings</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-dark">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-paper-plane mr-2"></i>Test SMS</h3>
            </div>
            <div class="card-body">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="form-group">
                        <label>Recipient Phone Number <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                            </div>
                            <input type="text" class="form-control" name="test_number" placeholder="(207) 929-0089 or 2079290089" required>
                        </div>
                        <small class="form-text text-muted">10-digit numbers default to US (+1) automatically.</small>
                    </div>

                    <div class="form-group">
                        <label>Message Content</label>
                        <textarea class="form-control" name="test_message" rows="3" maxlength="160">Test message from ITFlow SMSgate integration.</textarea>
                    </div>

                    <button type="submit" name="test_smsgate" class="btn btn-info btn-block text-bold"><i class="fa fa-paper-plane mr-2"></i>Send Test SMS</button>
                </form>
            </div>
        </div>

        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-fw fa-info-circle mr-2"></i>About SMS Gate</h3>
            </div>
            <div class="card-body p-3">
                <p class="text-sm">SMS Gate for Android turns an Android phone into an SMS gateway, supporting Cloud mode, Local Server mode, and Private Server mode.</p>
                <a href="https://docs.sms-gate.app/integration/api/" target="_blank" class="btn btn-sm btn-outline-secondary btn-block">
                    <i class="fas fa-external-link-alt mr-1"></i> API Documentation
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row mt-2" id="weekly-tech-summary">
    <div class="col-md-12">
        <div class="card card-dark">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-user-clock mr-2"></i>Weekly Technician Time &amp; Mileage Summary (Monday Report)</h3>
                <div class="card-tools">
                    <a href="/agent/reports/tech_weekly_summary.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-fw fa-chart-bar mr-1"></i>View Weekly Report
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="row align-items-center mb-3">
                        <div class="col-md-6">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="config_tech_weekly_summary_enable" name="config_tech_weekly_summary_enable" value="1" <?= !empty($config_tech_weekly_summary_enable) ? 'checked' : '' ?>>
                                <label class="custom-control-label font-weight-bold" for="config_tech_weekly_summary_enable">Enable Monday Weekly Technician Summary (SMS &amp; Email)</label>
                            </div>
                            <small class="form-text text-muted">
                                Automatically sends each selected technician's prior week (Monday&ndash;Sunday) hours and mileage totals via SMS to the configured phone numbers and emails the full breakdown to the technician. Technicians with 0 hours and 0 miles for the week are skipped.
                            </small>
                        </div>
                        <div class="col-md-3 mt-2 mt-md-0">
                            <label class="font-weight-bold mb-1"><i class="far fa-clock mr-1"></i>Monday Send Time</label>
                            <input type="time" class="form-control" name="config_tech_weekly_summary_time" value="<?= escapeHtml($config_tech_weekly_summary_time ?: '08:00') ?>" required>
                        </div>
                        <div class="col-md-3 mt-2 mt-md-0 text-md-right">
                            <?php if (!empty($config_tech_weekly_summary_last_sent_week)) { ?>
                                <span class="badge badge-light border p-2">
                                    <i class="fas fa-check-circle text-success mr-1"></i>Last Auto-Sent Week: <?= escapeHtml($config_tech_weekly_summary_last_sent_week) ?>
                                </span>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-3">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 110px;" class="text-center">Send Report</th>
                                    <th style="width: 240px;">Technician</th>
                                    <th>SMS Phone Numbers (Multiple Allowed)</th>
                                    <th style="width: 300px;">Email Fallback to Technician</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($tech = mysqli_fetch_assoc($sql_weekly_techs)) {
                                    $u_id = (int) $tech['user_id'];
                                    $u_enabled = (int) $tech['enabled'] === 1;
                                    $u_email_enabled = (int) $tech['email_enabled'] === 1;
                                ?>
                                    <tr>
                                        <td class="text-center align-middle">
                                            <input type="hidden" name="tech_ids[]" value="<?= $u_id ?>">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="tech_enabled_<?= $u_id ?>" name="tech_enabled[<?= $u_id ?>]" value="1" <?= $u_enabled ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="tech_enabled_<?= $u_id ?>"></label>
                                            </div>
                                        </td>
                                        <td class="align-middle">
                                            <div class="font-weight-bold"><?= escapeHtml($tech['user_name']) ?></div>
                                            <small class="text-muted"><i class="far fa-envelope mr-1"></i><?= escapeHtml($tech['user_email']) ?></small>
                                        </td>
                                        <td class="align-middle">
                                            <div class="input-group input-group-sm">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-sms"></i></span>
                                                </div>
                                                <input type="text" class="form-control" name="tech_sms_numbers[<?= $u_id ?>]" value="<?= escapeHtml($tech['sms_numbers']) ?>" placeholder="e.g. 2075551212, 2075551213">
                                            </div>
                                            <small class="text-muted">Comma-separated phone numbers to receive <?= escapeHtml($tech['user_name']) ?>'s weekly SMS summary.</small>
                                        </td>
                                        <td class="align-middle">
                                            <div class="custom-control custom-checkbox mb-1">
                                                <input type="checkbox" class="custom-control-input" id="tech_email_enabled_<?= $u_id ?>" name="tech_email_enabled[<?= $u_id ?>]" value="1" <?= $u_email_enabled ? 'checked' : '' ?>>
                                                <label class="custom-control-label small font-weight-bold" for="tech_email_enabled_<?= $u_id ?>">
                                                    Email full report to <?= escapeHtml($tech['user_email']) ?>
                                                </label>
                                            </div>
                                            <input type="text" class="form-control form-control-sm" name="tech_extra_emails[<?= $u_id ?>]" value="<?= escapeHtml($tech['extra_emails']) ?>" placeholder="Optional additional emails (comma-separated)">
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <button type="submit" name="edit_tech_weekly_summary_settings" class="btn btn-primary text-bold">
                            <i class="fa fa-check mr-2"></i>Save Weekly Summary Settings
                        </button>
                        <button type="submit" name="send_tech_weekly_summary_now" class="btn btn-outline-info text-bold" onclick="return confirm('Save settings and immediately dispatch the prior week (<?= $prior_range['week_start'] ?> to <?= $prior_range['week_end'] ?>) summary to all selected technicians with logged hours/miles?');">
                            <i class="fas fa-paper-plane mr-2"></i>Save &amp; Send Prior Week Summary Now (<?= date('M j', strtotime($prior_range['week_start'])) ?> &ndash; <?= date('M j', strtotime($prior_range['week_end'])) ?>)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once "../includes/footer.php";
