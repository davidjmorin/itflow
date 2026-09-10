<?php

/*
 * ITFlow - Database update to version 2.3.7 (from 2.3.6)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Create New Contract Templates Table
    mysqli_query($mysqli, "CREATE TABLE `contract_templates` (
      `contract_template_id` INT(11) AUTO_INCREMENT PRIMARY KEY,
      `contract_template_name` VARCHAR(255) NOT NULL,
      `contract_template_description` TEXT NULL DEFAULT NULL,
      `contract_template_type` VARCHAR(50) NULL DEFAULT NULL,

      `contract_template_sla_low_response_time` INT(11) NULL DEFAULT NULL,
      `contract_template_sla_low_resolution_time` INT(11) NULL DEFAULT NULL,
      `contract_template_sla_medium_response_time` INT(11) NULL DEFAULT NULL,
      `contract_template_sla_medium_resolution_time` INT(11) NULL DEFAULT NULL,
      `contract_template_sla_high_response_time` INT(11) NULL DEFAULT NULL,
      `contract_template_sla_high_resolution_time` INT(11) NULL DEFAULT NULL,

      `contract_template_rate_standard` DECIMAL(10,2) NULL DEFAULT NULL,
      `contract_template_rate_after_hours` DECIMAL(10,2) NULL DEFAULT NULL,

      `contract_template_net_terms` VARCHAR(50) NULL DEFAULT NULL,
      `contract_template_support_hours` VARCHAR(100) NULL DEFAULT NULL,
      `contract_template_renewal_frequency` VARCHAR(50) NULL DEFAULT NULL,

      `contract_template_details` TEXT NULL DEFAULT NULL,

      `contract_template_created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      `contract_template_updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
      `contract_template_archived_at` DATETIME NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");


    // Create New Contracts Table
    mysqli_query($mysqli, "CREATE TABLE `contracts` (
        `contract_id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `contract_name` VARCHAR(255) NOT NULL,
        `contract_status` VARCHAR(50) NOT NULL,
        `contract_type` VARCHAR(50) NOT NULL,

        `contract_sla_low_response_time` INT(11) NULL DEFAULT NULL,
        `contract_sla_low_resolution_time` INT(11) NULL DEFAULT NULL,
        `contract_sla_medium_response_time` INT(11) NULL DEFAULT NULL,
        `contract_sla_medium_resolution_time` INT(11) NULL DEFAULT NULL,
        `contract_sla_high_response_time` INT(11) NULL DEFAULT NULL,
        `contract_sla_high_resolution_time` INT(11) NULL DEFAULT NULL,

        `contract_details` TEXT NULL DEFAULT NULL,

        `contract_client_id` INT(11) NULL DEFAULT NULL,
        `contract_client_name` VARCHAR(255) NULL DEFAULT NULL,
        `contract_client_address` TEXT NULL DEFAULT NULL,
        `contract_client_email` VARCHAR(255) NULL DEFAULT NULL,
        `contract_client_phone` VARCHAR(100) NULL DEFAULT NULL,

        `contract_contact_name` VARCHAR(255) NULL DEFAULT NULL,
        `contract_contact_signature` TEXT NULL DEFAULT NULL,
        `contract_contact_signature_date` DATETIME NULL DEFAULT NULL,

        `contract_agent_name` VARCHAR(255) NULL DEFAULT NULL,
        `contract_agent_signature` TEXT NULL DEFAULT NULL,
        `contract_agent_signature_date` DATETIME NULL DEFAULT NULL,

        `contract_rate_standard` DECIMAL(10,2) NULL DEFAULT NULL,
        `contract_rate_after_hours` DECIMAL(10,2) NULL DEFAULT NULL,

        `contract_net_terms` VARCHAR(50) NULL DEFAULT NULL,
        `contract_support_hours` VARCHAR(100) NULL DEFAULT NULL,

        `contract_start_date` DATE NULL DEFAULT NULL,
        `contract_end_date` DATE NULL DEFAULT NULL,
        `contract_renewal_frequency` VARCHAR(50) NULL DEFAULT NULL,

        `contract_created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `contract_updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        `contract_archived_at` DATETIME NULL DEFAULT NULL,

        FOREIGN KEY (`contract_client_id`) REFERENCES `clients`(`client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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
