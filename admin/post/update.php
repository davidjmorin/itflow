<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_GET['update'])) {

    validateCSRFToken();

    enforceAdminPermission();

    flashAlert("Web application updates and Force Update are disabled on this custom fork to protect local customizations. Merge upstream changes via Git CLI instead.", "error");
    redirect();

}

if (isset($_GET['update_db'])) {

    validateCSRFToken();

    // Get the current version
    require_once ('../includes/database_version.php');

    // Perform upgrades, if required - populates $database_updates_applied and $database_updates_error
    require_once ('database_updates.php');

    if ($database_updates_error) {
        logAudit("Database", "Update", "$session_name ran a database update that failed at $database_updates_error");
        flashAlert("Database update failed at $database_updates_error - the version was not advanced past the last successful update, so it is safe to retry", "error");
    } else {
        logAudit("Database", "Update", "$session_name updated the database structure");
        flashAlert("Database structure update successful");
    }

    sleep(1);

    redirect();

}
