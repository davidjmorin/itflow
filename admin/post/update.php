<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/*
 * Queueing is the ONLY update path from the web - files and database both. There used to be
 * an ?update handler here that ran git pull inside this request, and an ?update_db one that
 * ran the migrations; both are gone deliberately. scripts/update_cli.php replaces the files
 * this request is executing from and then applies the migrations that came with them, which
 * is not something to do half way through a page load, and a host where PHP cannot run
 * external commands could never do the first half at all.
 *
 * The row is written as well as the settings column: config_update_queued_at is what
 * cron/app_update.php acts on, and cron_job_run_now is what gets the dispatcher to look
 * before the job's own schedule comes round.
 */
/*
 * Check Now. The check runs git fetch, so it belongs on the command line for the same reason
 * the update does; this only asks the dispatcher to bring the job forward. run_now works on a
 * disabled job too, so turning the daily check off does not take the button with it.
 */
if (isset($_GET['check_update'])) {

    validateCSRFToken();

    enforceAdminPermission();

    if (!settingsColumnExists($mysqli, 'config_update_latest_commit')) {
        flashAlert("Checking needs a schema change this install has not caught up with yet - run php scripts/update_cli.php from a shell once, and it will work from then on.", 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE cron_jobs SET cron_job_run_now = 1 WHERE cron_job_name = 'update_check'");

    logAudit("App", "Update", "$session_name asked cron to check for updates");

    flashAlert("Checking for updates - cron will run the check within a minute.");

    redirect();

}

if (isset($_GET['queue_update'])) {

    validateCSRFToken();

    enforceAdminPermission();

    flashAlert("Web application updates are disabled on this custom fork to protect local customizations. Merge upstream changes via Git CLI instead.", 'error');

    redirect();

}

if (isset($_GET['update_db'])) {

    validateCSRFToken();

    enforceAdminPermission();

    require_once '../includes/database_version.php';
    require_once __DIR__ . '/../database_updates.php';

    if ($database_updates_error) {
        logAudit("Database", "Update", "$session_name ran a database update that failed at $database_updates_error");
        flashAlert("Database update failed at $database_updates_error - re-run after fixing the cause.", 'error');
    } else {
        logAudit("Database", "Update", "$session_name updated the database structure");
        flashAlert("Database structure update successful");
    }

    redirect();

}
