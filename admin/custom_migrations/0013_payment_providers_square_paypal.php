<?php

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

$col_mode = mysqli_query($mysqli, "SHOW COLUMNS FROM `payment_providers` LIKE 'payment_provider_mode'");
if ($col_mode && mysqli_num_rows($col_mode) === 0) {
    mysqli_query($mysqli, "ALTER TABLE `payment_providers` ADD COLUMN `payment_provider_mode` VARCHAR(20) DEFAULT NULL AFTER `payment_provider_private_key`");
}

$col_extra = mysqli_query($mysqli, "SHOW COLUMNS FROM `payment_providers` LIKE 'payment_provider_extra'");
if ($col_extra && mysqli_num_rows($col_extra) === 0) {
    mysqli_query($mysqli, "ALTER TABLE `payment_providers` ADD COLUMN `payment_provider_extra` TEXT DEFAULT NULL AFTER `payment_provider_mode`");
}
