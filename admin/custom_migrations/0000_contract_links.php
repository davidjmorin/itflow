<?php

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `contract_assets` (
  `contract_id` INT(11) NOT NULL,
  `asset_id` INT(11) NOT NULL,
  PRIMARY KEY (`contract_id`, `asset_id`),
  KEY `contract_id` (`contract_id`),
  KEY `asset_id` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `contract_services` (
  `contract_id` INT(11) NOT NULL,
  `service_id` INT(11) NOT NULL,
  PRIMARY KEY (`contract_id`, `service_id`),
  KEY `contract_id` (`contract_id`),
  KEY `service_id` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `contract_vendors` (
  `contract_id` INT(11) NOT NULL,
  `vendor_id` INT(11) NOT NULL,
  PRIMARY KEY (`contract_id`, `vendor_id`),
  KEY `contract_id` (`contract_id`),
  KEY `vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
