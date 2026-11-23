#!/usr/bin/env php
<?php

// Change to the directory of this script so that all shell commands run here
chdir(__DIR__);

// Ensure script is run only from the CLI
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

// Ensure the script is run by the owner of the file
$fileOwner = fileowner(__FILE__);
$currentUser = posix_geteuid(); // Get the current effective user ID

if ($currentUser !== $fileOwner) {
    $ownerInfo = posix_getpwuid($fileOwner);
    $ownerName = $ownerInfo['name'] ?? 'unknown';
    fwrite(STDERR, "Error: This script must be run by the file owner ($ownerName) to proceed.\nYou could try sudo -u $ownerName php update_cli.php\n");
    exit(1);
}

require_once "../config.php";
require_once "../functions.php";

/*
 * Run with no options this updates the application and then the database, in that order,
 * because new code against an old schema is what breaks an install mid-upgrade.
 *
 * The application update is a hard reset onto the branch this install tracks, so local
 * modifications to tracked files are discarded. Untracked files are left alone, which is
 * everything an install keeps for itself: config.php, uploads/ and the custom/ directories.
 *
 * --update_db does the database half ON ITS OWN and never touches git. That matters on a box
 * carrying work that is not pushed: the full run would hard-reset it away, and the database
 * half is usually all that is wanted after a manual checkout or a restore.
 */

function printUsage($stream = STDOUT) {
    fwrite($stream, "Usage: php update_cli.php [--update_db]\n\n");
    fwrite($stream, "With no options, updates the application to the latest code on the branch this\n");
    fwrite($stream, "install tracks, discarding local changes to tracked files, and then applies any\n");
    fwrite($stream, "outstanding database updates.\n\n");
    fwrite($stream, "  --update_db   Apply outstanding database updates only. The application files\n");
    fwrite($stream, "                are left exactly as they are - git is not run at all.\n");
}

/*
 * Set on the child process this script starts for the database phase - see the comment
 * further down. It is not part of the interface, and it is what stops that child from
 * starting a child of its own.
 */
$database_phase_only = getenv('ITFLOW_UPDATE_PHASE') === 'database';

/*
 * --db_update is accepted alongside --update_db on purpose. The two spellings have been used
 * interchangeably in this project's own notes and release steps for years, and the cost of a
 * typo here is the opposite of what was asked for - a hard reset instead of a database update.
 * Anything else on the command line is still REFUSED rather than ignored, for the same reason.
 */
$database_only_requested = false;

$arguments = array_slice($argv, 1);

if (!$database_phase_only && count($arguments) > 0) {

    if (count($arguments) === 1 && in_array($arguments[0], ['--help', '-h', 'help'], true)) {
        printUsage();
        exit;
    }

    if (count($arguments) === 1 && in_array($arguments[0], ['--update_db', '--db_update', '--database'], true)) {

        $database_phase_only = true;
        $database_only_requested = true;

    } else {

        fwrite(STDERR, "Error: unrecognised or disabled option on this custom fork.\nUse --update_db to apply database updates, and use Git CLI (git fetch origin && git merge origin/master) for code updates.\n\n");
        printUsage(STDERR);
        exit(1);

    }

} elseif (!$database_phase_only && count($arguments) === 0) {

    fwrite(STDERR, "Error: Running update_cli.php without --update_db is disabled on this custom fork to protect local customizations.\nMerge upstream updates via git CLI (git fetch origin && git merge origin/master), then run php update_cli.php --update_db.\n");
    exit(1);

}

if ($database_only_requested) {
    echo "Database updates only - the application files will not be touched.\n";
}

$app_updated = false;

require_once "../includes/database_version.php";

$latest_db_version = LATEST_DATABASE_VERSION;

// Fetch the current version from the database
$result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings LIMIT 1");
$row = $result ? mysqli_fetch_assoc($result) : null;
$old_db_version = trim((string) ($row['config_current_database_version'] ?? ''));

// No version to work from means no way to tell which migrations have already run, and
// starting from the first one against a partly built database breaks it
if ($old_db_version === '') {
    fwrite(STDERR, "Error: could not read the current database version from the settings table.\n");
    fwrite(STDERR, "This install looks incomplete - finish it at /setup before updating the database.\n");
    exit(1);
}

DEFINE("CURRENT_DATABASE_VERSION", $old_db_version);

// Run the migrations - populates $database_updates_applied and $database_updates_error
require_once "../admin/database_updates.php";

foreach ($database_updates_applied as $applied_version) {
    echo "Applied database update $applied_version\n";
}

// The collations the runner checked after them (admin/database_updates.php)
if (!empty($database_updates_collations['changed'])) {
    echo "Gave " . (count($database_updates_collations['changed']) === 1 ? '1 table' : count($database_updates_collations['changed']) . ' tables') . " the collation db.sql has: " . implode(', ', $database_updates_collations['changed']) . "\n";
}
foreach ($database_updates_collations['failed'] ?? [] as $collation_table => $collation_why) {
    echo "Warning: the table $collation_table doesn't have the collation db.sql has and could not be given it ($collation_why) - Admin > Debug lists it.\n";
}

if ($database_updates_error) {
    $stopped_at_version = count($database_updates_applied) > 0 ? end($database_updates_applied) : $old_db_version;
    fwrite(STDERR, "Error: database update failed at $database_updates_error\n");
    fwrite(STDERR, "The database is at version $stopped_at_version - re-running will resume at the failed update.\n");
    exit(1);
}

if (count($database_updates_applied) > 0) {
    if ($old_db_version !== $latest_db_version) {
        echo "Database updated from version $old_db_version to $latest_db_version.\n";
    } else {
        echo "Database updates applied (" . count($database_updates_applied) . "); upstream schema remains at $latest_db_version.\n";
    }
} else {
    echo "Database is already at the latest version ($latest_db_version). No updates were applied.\n";
}
