<?php

chdir(dirname(__FILE__));

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$cron_lock_script = __FILE__;
require_once "includes/cron_lock.php";

require_once "../config.php";
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

$cli_opts = !defined('ITFLOW_CRON_DISPATCHER') ? getopt('', ['force', 'dry-run', 'date:', 'user_id:']) : [];
$is_force = isset($cli_opts['force']);
$is_dry_run = isset($cli_opts['dry-run']);
$only_user_id = isset($cli_opts['user_id']) ? intval($cli_opts['user_id']) : 0;

$sql_settings = mysqli_query($mysqli, "SELECT config_enable_cron, config_tech_weekly_summary_enable, config_tech_weekly_summary_time, config_tech_weekly_summary_last_sent_week FROM settings WHERE company_id = 1");
$settings_row = mysqli_fetch_assoc($sql_settings);

$config_enable_cron = intval($settings_row['config_enable_cron'] ?? 0);
$weekly_enable = intval($settings_row['config_tech_weekly_summary_enable'] ?? 0);
$last_sent_week = $settings_row['config_tech_weekly_summary_last_sent_week'] ?? '';

if ($config_enable_cron === 0 && !$is_force && !$is_dry_run) {
    cronJobStop("Cron is not enabled");
}

if ($weekly_enable === 0 && !$is_force && !$is_dry_run) {
    cronJobStop("Weekly technician summary is disabled in settings");
}

if (isset($cli_opts['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $cli_opts['date'])) {
    $range = smsgateGetPriorWeekRange(strtotime($cli_opts['date']), true);
} else {
    if (!$is_force && !$is_dry_run && (int) date('N') !== 1) {
        cronJobStop("Skipped: Weekly summary dispatches on Mondays");
    }
    $range = smsgateGetPriorWeekRange();
}

$week_start = $range['week_start'];
$week_end = $range['week_end'];

if (!$is_force && !$is_dry_run && $last_sent_week === $week_start) {
    cronJobStop("Already sent for week of $week_start");
}

$dispatch_result = smsgateDispatchTechWeeklySummaries($mysqli, [
    'week_start' => $week_start,
    'week_end' => $week_end,
    'force' => true,
    'dry_run' => $is_dry_run,
    'user_id' => $only_user_id
]);

if (!$is_dry_run && $only_user_id === 0) {
    $week_start_esc = escapeSql($week_start);
    mysqli_query($mysqli, "UPDATE settings SET config_tech_weekly_summary_last_sent_week = '$week_start_esc' WHERE company_id = 1");
}

$summary_line = "Period $week_start to $week_end: SMS sent for {$dispatch_result['sent_sms_techs']} tech(s) ({$dispatch_result['sent_sms_numbers']} number(s)), queued {$dispatch_result['queued_emails']} email(s), skipped {$dispatch_result['skipped_zero']} tech(s) with 0 activity.";
echo $summary_line . "\n";

if (!$is_dry_run) {
    logApp("Cron-Tech-Weekly-Summary", "info", $summary_line);
}

