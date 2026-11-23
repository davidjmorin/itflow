<?php

/*
 * Admin > Settings > Notifications - the alerts ITFlow raises for your team,
 * the emails and notices it sends agents about their tickets, and the emails
 * it sends clients by itself: about their tickets, and the
 * invoices recurring invoices make - and the texts, to those who agreed
 * (functions/sms.php). Payment reminders and statements are set
 * with their schedule in Invoices and billing - shown here, not set. Saved by
 * edit_notification_settings (admin/post/settings_notification.php).
 */

require_once "includes/inc_all_admin.php";

echo settingsPageStart('notification');

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('alerts', 'Alerts for your team', 'In the bell at the top of the page, for everyone who can see the client - raised by the nightly job.') ?>
                <?= settingsSwitch('config_enable_alert_domain_expire', $config_enable_alert_domain_expire, ['help' => '45, 7 and 1 days before a domain expires - saying when one is set to auto-renew, to check it goes through.']) ?>
            <?= settingsSectionEnd() ?>

            <?php if ($config_module_enable_ticketing && version_compare(CURRENT_DATABASE_VERSION, '3.9.1', '>=')) { ?>
                <?= settingsSectionStart('team', 'Emails and notices to your team', 'What ITFlow tells your agents about their tickets by itself. The new-ticket address is set in <a href="settings_ticket.php">Tickets</a>.') ?>
                    <?= settingsSwitch('config_notify_assigned_app', ticketTeamNotifyOn('assigned_app') ? 1 : 0, ['help' => 'In the bell, for the agent a ticket or recurring ticket is assigned to - when someone else assigns it.']) ?>
                    <?= settingsSwitch('config_notify_assigned_email', ticketTeamNotifyOn('assigned_email') ? 1 : 0, ['help' => 'The email to that agent, with the ticket - or a list of them, when several are assigned at once.']) ?>
                    <?= settingsSwitch('config_notify_helper_replies', ticketTeamNotifyOn('helper_replies') ? 1 : 0, ['help' => 'The agent a ticket is assigned to is always emailed the client\'s replies. This is the copy to the other agents helping on it.']) ?>
                <?= settingsSectionEnd() ?>
            <?php } ?>

            <?php if (weeklySummaryReady()) {
                $weekly = weeklySummarySettings(); ?>
                <?= settingsSectionStart('weekly', 'Weekly summary', 'An email on how the last week went beside the week before - tickets opened and resolved, SLAs missed, ratings, time logged, new clients, what was invoiced and paid - and what needs a look: open tickets nobody has, overdue invoices, domains and certificates running out. It goes early on the day you pick.') ?>
                    <?php if (empty($config_smtp_provider)) { ?>
                        <div class="alert alert-warning py-2 small" data-weekly-no-mail><i class="fas fa-exclamation-triangle me-2"></i>Mail sending is off, so it can't go. <a href="settings_mail.php?tab=sending">Set up sending</a></div>
                    <?php } ?>
                    <?= settingsSwitch('config_weekly_summary', $weekly['on'], ['help' => 'Sent by the nightly job in <a href="cron.php">Maintenance &gt; Cron</a>.']) ?>
                    <div data-settings-depends-on="config_weekly_summary">
                        <?= settingsSelect('config_weekly_summary_day', $weekly['day'], WEEKLY_SUMMARY_DAYS, ['icon' => 'fa-calendar-day']) ?>
                        <?= settingsInput('config_weekly_summary_to', $weekly['to'], ['icon' => 'fa-envelope', 'maxlength' => 500, 'placeholder' => 'Every admin',
                            'help' => 'Addresses, separated by commas. Empty, every admin gets it.']) ?>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                        <button type="submit" form="weeklySummaryNow" class="btn btn-sm btn-outline-secondary" <?= empty($config_smtp_provider) ? 'disabled' : '' ?> data-weekly-now><i class="fas fa-paper-plane me-2"></i>Send Me One Now</button>
                        <span class="small text-body-secondary">The last seven days, to <?= escapeHtml($session_email ?? 'you') ?> - to see what it looks like.</span>
                    </div>
                <?= settingsSectionEnd() ?>
            <?php } ?>

            <?= settingsSectionStart('clients', 'Emails to clients', 'What ITFlow emails your clients by itself. What an agent sends - a reply, an invoice, a quote - goes whatever is set here.') ?>
                <?php if (empty($config_smtp_provider)) { ?>
                    <div class="alert alert-warning py-2 small" data-notification-no-mail><i class="fas fa-exclamation-triangle me-2"></i>Mail sending is off, so none of these go. <a href="settings_mail.php?tab=sending">Set up sending</a></div>
                <?php } ?>
                <?php if ($config_module_enable_ticketing) { ?>
                    <?= settingsSwitch('config_ticket_client_general_notifications', $config_ticket_client_general_notifications, ['help' => 'The contact and watchers on a ticket hear when it is raised, scheduled, resolved and closed, and colleagues when one is raised at their company. Off, they still get the replies an agent sends.']) ?>
                <?php } ?>
                <?php if ($config_module_enable_accounting) { ?>
                    <?= settingsSwitch('config_recurring_auto_send_invoice', $config_recurring_auto_send_invoice, ['help' => 'Each recurring invoice set to send itself goes to the client\'s primary and billing contacts. Off, they are made as drafts to check and send.']) ?>
                    <div class="itflow-settings-field" data-setting="config_send_invoice_reminders" data-notification-reminders>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill <?= $config_send_invoice_reminders ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $config_send_invoice_reminders ? 'On' : 'Off' ?></span>
                            <span>Payment reminders and statements</span>
                        </div>
                        <div class="form-text">On their own schedule, in <a href="settings_invoice.php#reminders">Invoices and billing</a>.</div>
                    </div>
                <?php } ?>
            <?= settingsSectionEnd() ?>

            <?php if (smsColumnExists('settings', 'config_sms_client_tickets')) {
                $sms_client = smsClientSettings(); ?>
                <?= settingsSectionStart('texts', 'Texts to clients', 'What ITFlow texts your clients by itself - only to contacts who agreed to texts, on their contact or in the client portal. Texts that would land between ' . date('g A', mktime(SMS_QUIET_FROM, 0)) . ' and ' . date('g A', mktime(SMS_QUIET_UNTIL, 0)) . ' wait for the morning. A reply to one about a ticket goes on the ticket.') ?>
                    <?php if (!smsReady()) { ?>
                        <div class="alert alert-light border py-2 small" data-notification-no-texting><i class="fas fa-info-circle me-2"></i>Texting isn't set up, so none of these go. <a href="text_messaging.php">Set up texting</a></div>
                    <?php } ?>
                    <?php if ($config_module_enable_ticketing) { ?>
                        <?= settingsSelect('config_sms_client_tickets', $sms_client['config_sms_client_tickets'], settingsFields()['config_sms_client_tickets']['options'], ['icon' => 'fa-life-ring']) ?>
                        <?= settingsSwitch('config_sms_client_visits', $sms_client['config_sms_client_visits'], ['help' => 'When a visit or remote work is booked on their ticket, and when it is cancelled.']) ?>
                        <?= settingsSelect('config_sms_visit_reminder_hours', $sms_client['config_sms_visit_reminder_hours'], smsReminderChoices(), ['icon' => 'fa-bell']) ?>
                    <?php } ?>
                    <?php if ($config_module_enable_accounting) { ?>
                        <?= settingsSwitch('config_sms_client_invoices', $sms_client['config_sms_client_invoices'], ['help' => 'Texted with the email, to the people it is emailed to who agreed to texts.']) ?>
                    <?php } ?>
                <?= settingsSectionEnd() ?>
            <?php } ?>

            <?= settingsSaveBar('edit_notification_settings') ?>
        </div>
    </div>
</form>

<?php if (weeklySummaryReady()) { ?>
    <!-- Send me one now - its own form, so the settings above aren't saved with it -->
    <form id="weeklySummaryNow" action="post.php" method="post" hidden>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="send_weekly_summary_now" value="1">
    </form>
<?php } ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-sms mr-2"></i>SMS Notifications &amp; Weekly Technician Summary (SMS Gate)</h3>
            <div class="card-tools">
                <a href="settings_smsgate.php#weekly-tech-summary" class="btn btn-sm btn-info mr-1"><i class="fas fa-user-clock mr-1"></i>Weekly Tech Summary</a>
                <a href="settings_smsgate.php" class="btn btn-sm btn-primary"><i class="fas fa-cog mr-1"></i>Configure SMS Gate</a>
            </div>
        </div>
        <div class="card-body">
            <p class="mb-2">SMS Gate is <?= !empty($config_smsgate_enable) ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-secondary">Disabled</span>' ?>. Emergency urgent ticket notifications and ticket submission acknowledgements are managed in <a href="settings_smsgate.php">SMS Gate Settings</a>.</p>
            <p class="mb-0">Monday Weekly Technician Time &amp; Mileage Summary is <?= !empty($config_tech_weekly_summary_enable) ? '<span class="badge badge-success">Enabled (Mondays at ' . escapeHtml($config_tech_weekly_summary_time ?: '08:00') . ')</span>' : '<span class="badge badge-secondary">Disabled</span>' ?>. Configure technician recipients, multiple SMS numbers, and email fallback in <a href="settings_smsgate.php#weekly-tech-summary">Weekly Technician Summary Settings</a>.</p>
        </div>
    </div>

<?php
require_once "../includes/footer.php";
