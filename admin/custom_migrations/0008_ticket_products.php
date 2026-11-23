<?php

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `ticket_products` (
  `ticket_product_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_product_ticket_id` int(11) NOT NULL,
  `ticket_product_product_id` int(11) NOT NULL DEFAULT 0,
  `ticket_product_inventory_item_id` int(11) NOT NULL DEFAULT 0,
  `ticket_product_name` varchar(200) NOT NULL,
  `ticket_product_description` text DEFAULT NULL,
  `ticket_product_qty` decimal(15,2) NOT NULL DEFAULT 1.00,
  `ticket_product_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ticket_product_tax_id` int(11) NOT NULL DEFAULT 0,
  `ticket_product_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ticket_product_subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ticket_product_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ticket_product_asset_type` varchar(200) DEFAULT 'Other',
  `ticket_product_asset_id` int(11) NOT NULL DEFAULT 0,
  `ticket_product_invoiced` tinyint(1) NOT NULL DEFAULT 0,
  `ticket_product_added_by` int(11) NOT NULL DEFAULT 0,
  `ticket_product_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ticket_product_id`),
  KEY `ticket_product_ticket_id` (`ticket_product_ticket_id`),
  KEY `ticket_product_inventory_item_id` (`ticket_product_inventory_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

