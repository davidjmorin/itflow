<?php

function itflowEnsureCustomMigrationsTable(mysqli $mysqli): void
{
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `custom_migrations_applied` (
        `migration_name` varchar(255) NOT NULL,
        `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`migration_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function itflowGetCustomMigrationStatus(mysqli $mysqli): array
{
    itflowEnsureCustomMigrationsTable($mysqli);

    $applied = [];
    $res = mysqli_query($mysqli, "SELECT `migration_name` FROM `custom_migrations_applied` ORDER BY `migration_name` ASC");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $applied[$row['migration_name']] = true;
        }
    }

    $files = [];
    $dir = __DIR__ . '/custom_migrations';
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.php') ?: [] as $path) {
            $files[basename($path)] = $path;
        }
    }
    ksort($files, SORT_NATURAL);

    $pending = [];
    foreach ($files as $name => $path) {
        if (!isset($applied[$name])) {
            $pending[$name] = $path;
        }
    }

    return [
        'applied' => array_keys($applied),
        'pending' => $pending,
    ];
}

function itflowRunCustomMigrations(mysqli $mysqli, array &$database_updates_applied, ?string &$database_updates_error): void
{
    if ($database_updates_error) {
        return;
    }

    if (!defined('FROM_DB_UPDATER')) {
        define('FROM_DB_UPDATER', true);
    }

    $status = itflowGetCustomMigrationStatus($mysqli);
    foreach ($status['pending'] as $name => $file) {
        try {
            require $file;
        } catch (Throwable $e) {
            $database_updates_error = "custom:$name: " . $e->getMessage();
            error_log("ITFlow custom migration $name failed: " . $e->getMessage());
            break;
        }

        $escaped = mysqli_real_escape_string($mysqli, $name);
        mysqli_query($mysqli, "INSERT IGNORE INTO `custom_migrations_applied` (`migration_name`) VALUES ('$escaped')");
        $database_updates_applied[] = "custom:$name";
    }
}
