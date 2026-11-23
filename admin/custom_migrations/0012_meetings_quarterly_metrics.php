<?php

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

function mtgAddColIfMissing(mysqli $mysqli, string $table, string $column, string $definition): void {
    $t = mysqli_real_escape_string($mysqli, $table);
    $c = mysqli_real_escape_string($mysqli, $column);
    $chk = mysqli_query($mysqli, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c' LIMIT 1");
    if ($chk && mysqli_num_rows($chk) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

mysqli_query($mysqli, "ALTER TABLE `meeting_metrics` MODIFY COLUMN `series_id` int(11) DEFAULT NULL;");

mtgAddColIfMissing($mysqli, 'meeting_metrics', 'metric_type', "enum('Count','Measurement','Flat') NOT NULL DEFAULT 'Count' AFTER `metric_name`");
mtgAddColIfMissing($mysqli, 'meeting_metrics', 'frequency', "enum('Daily','Weekly','Monthly') NOT NULL DEFAULT 'Monthly' AFTER `metric_type`");
mtgAddColIfMissing($mysqli, 'meeting_metrics', 'work_days', "varchar(40) NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri' AFTER `frequency`");
mtgAddColIfMissing($mysqli, 'meeting_metrics', 'week_start', "varchar(15) NOT NULL DEFAULT 'Monday' AFTER `work_days`");
mtgAddColIfMissing($mysqli, 'meeting_metrics', 'start_target', "decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `goal_direction`");
mtgAddColIfMissing($mysqli, 'meeting_metrics', 'auto_set_status', "tinyint(1) NOT NULL DEFAULT 1 AFTER `target_pace`");
mtgAddColIfMissing($mysqli, 'meeting_metrics', 'super_green_pct', "decimal(6,2) NOT NULL DEFAULT 120.00 AFTER `auto_set_status`");
mtgAddColIfMissing($mysqli, 'meeting_metrics', 'yellow_pct', "decimal(6,2) NOT NULL DEFAULT 80.00 AFTER `super_green_pct`");
mtgAddColIfMissing($mysqli, 'meeting_metrics', 'privacy', "enum('Public','Private') NOT NULL DEFAULT 'Public' AFTER `yellow_pct`");

mysqli_query($mysqli, "ALTER TABLE `meeting_metric_values`
    MODIFY COLUMN `actual_value` decimal(15,2) DEFAULT NULL,
    MODIFY COLUMN `to_date_actual` decimal(15,2) DEFAULT NULL,
    MODIFY COLUMN `status_color` enum('super_green','green','yellow','red','gray') NOT NULL DEFAULT 'gray';");

mtgAddColIfMissing($mysqli, 'meeting_metric_values', 'approved_target', "decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `actual_value`");
mtgAddColIfMissing($mysqli, 'meeting_metric_values', 'rolling_target', "decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `approved_target`");
mtgAddColIfMissing($mysqli, 'meeting_metric_values', 'status_note', "text DEFAULT NULL AFTER `status_color`");

mysqli_query($mysqli, "
    UPDATE meeting_metric_values v
    JOIN meeting_metrics m ON v.metric_id = m.metric_id
    SET v.approved_target = m.period_target,
        v.rolling_target = m.period_target
    WHERE v.approved_target = 0 AND m.period_target > 0
");
