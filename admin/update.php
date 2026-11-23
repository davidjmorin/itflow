<?php
require_once "includes/inc_all_admin.php";

require_once "../includes/database_version.php";
require_once __DIR__ . "/custom_migrations_runner.php";

$repo_branch = getRepoBranch();
$remote_ref = escapeshellarg("origin/$repo_branch");

$updates = checkForUpdates();

$current_version = $updates->current_version;
$fetch_ok = $updates->result === 0;

$pending_commits = [];

$git_log = shell_exec("git log HEAD..$remote_ref --pretty=format:'%h%x1f%ar%x1f%s'");

foreach (explode("\n", trim((string) $git_log)) as $commit_line) {

    if ($commit_line === '') {
        continue;
    }

    $commit_fields = explode("\x1f", $commit_line, 3);

    if (count($commit_fields) === 3) {
        $pending_commits[] = $commit_fields;
    }

}

$custom_migration_status = itflowGetCustomMigrationStatus($mysqli);
$pending_custom_migrations = $custom_migration_status['pending'];
$applied_custom_migrations = $custom_migration_status['applied'];

$upstream_db_update_available = version_compare(LATEST_DATABASE_VERSION, CURRENT_DATABASE_VERSION, '>');
$db_update_available = $upstream_db_update_available || !empty($pending_custom_migrations);
$app_update_available = !empty($pending_commits);

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-download mr-2"></i>Update</h3>
    </div>
    <div class="card-body">

        <?php if (!$fetch_ok) { ?>
            <div class="alert alert-danger">
                <h5><i class="fas fa-fw fa-exclamation-triangle mr-2"></i>Cannot reach the Git remote</h5>
                ITFlow updates itself with Git, so nothing below is current until this is fixed.
                <?php if (!empty($updates->output)) { ?>
                    <pre class="bg-dark text-white p-2 mt-2 mb-2"><?= escapeHtml(implode("\n", $updates->output)) ?></pre>
                <?php } ?>
                Check that Git is installed, that the remote is reachable from this server, and that the web server
                user can write to the ITFlow directory. The
                <a href="https://forum.itflow.org" class="alert-link" target="_blank">forum</a> can help - include
                your PHP error log and the output above.
            </div>
        <?php } ?>

        <div class="row">
            <div class="col-md-3 col-6 mb-3">
                <small class="text-secondary text-uppercase">Release</small>
                <div class="h5 mb-0"><?= escapeHtml(APP_VERSION) ?></div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <small class="text-secondary text-uppercase">Branch</small>
                <div class="h5 mb-0"><?= escapeHtml($repo_branch) ?></div>
                <?php if ($repo_branch !== 'master') { ?>
                    <small class="text-warning">Not the release branch</small>
                <?php } ?>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <small class="text-secondary text-uppercase">Database</small>
                <div class="h5 mb-0">
                    <?= escapeHtml(CURRENT_DATABASE_VERSION) ?>
                    <?php if ($upstream_db_update_available) { ?>
                        <small class="text-danger">&rarr; <?= escapeHtml(LATEST_DATABASE_VERSION) ?></small>
                    <?php } ?>
                </div>
                <small class="text-muted">
                    <?php if (!empty($pending_custom_migrations)) { ?>
                        <span class="text-warning"><?= count($pending_custom_migrations) ?> custom migration<?= count($pending_custom_migrations) === 1 ? '' : 's' ?> pending</span>
                    <?php } else { ?>
                        Custom migrations: <?= count($applied_custom_migrations) ?> applied
                    <?php } ?>
                </small>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <small class="text-secondary text-uppercase">Commit</small>
                <div class="h5 mb-0"><code><?= escapeHtml(substr((string) $current_version, 0, 7)) ?></code></div>
            </div>
        </div>

        <hr>

        <?php if (!$app_update_available && !$db_update_available) { ?>

            <div class="text-center py-3">
                <i class="far fa-3x fa-smile-wink text-dark"></i>
                <p class="mt-3 mb-0"><strong>You are up to date</strong></p>
                <p class="text-muted">Everything is going to be alright.</p>
            </div>

            <?php if (rand(1, 10) == 1) { ?>
                <div class="alert alert-info alert-dismissible fade show mb-0" role="alert">
                    You are up to date, but when did you last check that your backup restores?
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php } ?>

        <?php } else { ?>

            <div class="alert alert-danger">
                <h5><i class="fas fa-fw fa-exclamation-triangle mr-2"></i>Do not update without a backup</h5>
                A VM snapshot is the safest option - other methods are covered in the
                <a href="https://docs.itflow.org/backups" class="alert-link" target="_blank">docs</a>. Read the
                <a href="https://github.com/itflow-org/itflow/blob/master/CHANGELOG.md" class="alert-link" target="_blank">changelog</a>
                first: some releases need manual steps, and this page will not do them for you.
            </div>

            <?php if ($app_update_available && $db_update_available) { ?>
                <p class="text-muted">
                    <i class="fas fa-fw fa-info-circle mr-1"></i>Both are pending. Update the application files first -
                    they bring the database migrations that the second step then applies.
                </p>
            <?php } ?>

            <?php if ($app_update_available) { ?>
                <div class="mb-4">
                    <h6 class="text-uppercase text-secondary">Application files</h6>
                    <p class="mb-2">
                        <?= count($pending_commits) ?> upstream commit<?= count($pending_commits) === 1 ? '' : 's' ?>
                        available in <code><?= escapeHtml("origin/$repo_branch") ?></code>.
                    </p>
                    <div class="alert alert-warning mb-0">
                        <h5><i class="fas fa-fw fa-shield-alt mr-2"></i>Web App Update &amp; Force Update Disabled</h5>
                        <p class="mb-2">
                            This installation runs on a custom fork. Web-based <strong>Update App</strong> and
                            <strong>Force Update</strong> buttons are disabled so your custom features are never overwritten.
                        </p>
                        <p class="mb-1">To merge upstream ITFlow updates safely via Git CLI:</p>
                        <pre class="bg-dark text-white p-2 mb-0">git fetch origin
git merge origin/master
su -s /bin/bash www-data -c "php scripts/update_cli.php --update_db"
git push fork dev</pre>
                    </div>
                </div>
            <?php } ?>

            <?php if ($db_update_available) { ?>
                <div class="mb-4">
                    <h6 class="text-uppercase text-secondary">Database</h6>
                    <p class="mb-2">
                        <?php if ($upstream_db_update_available) { ?>
                            Upstream schema is at <strong><?= escapeHtml(CURRENT_DATABASE_VERSION) ?></strong> and code expects
                            <strong><?= escapeHtml(LATEST_DATABASE_VERSION) ?></strong>.
                        <?php } ?>
                        <?php if (!empty($pending_custom_migrations)) { ?>
                            <strong><?= count($pending_custom_migrations) ?></strong> pending custom migration<?= count($pending_custom_migrations) === 1 ? '' : 's' ?>.
                        <?php } ?>
                        Parts of the app will error until this is applied.
                    </p>
                    <a class="btn btn-dark confirm-link" href="post.php?update_db&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-fw fa-database mr-2"></i>Update Database
                    </a>
                    <p class="text-muted mt-2 mb-0">
                        <small>
                            A large instance can take a minute or more. If it fails part way it stops without advancing
                            the recorded version, so it is safe to run again after fixing the cause.
                        </small>
                    </p>
                </div>
            <?php } ?>

        <?php } ?>

        <?php if ($app_update_available) { ?>
            <h6 class="text-uppercase text-secondary mt-4">Pending commits</h6>
            <div class="table-responsive-sm">
                <table class="table table-borderless table-hover">
                    <thead class="text-secondary">
                        <tr>
                            <th>Commit</th>
                            <th>When</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_commits as $commit) { ?>
                            <tr>
                                <td><code><?= escapeHtml($commit[0]) ?></code></td>
                                <td class="text-nowrap"><?= escapeHtml($commit[1]) ?></td>
                                <td><?= escapeHtml($commit[2]) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

    </div>
</div>

<?php

require_once "../includes/footer.php";
