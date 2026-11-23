<?php

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `user_passkeys` (
  `passkey_id` int(11) NOT NULL AUTO_INCREMENT,
  `passkey_user_id` int(11) NOT NULL,
  `passkey_name` varchar(100) NOT NULL,
  `passkey_credential_id` text NOT NULL,
  `passkey_credential_id_hash` varchar(64) NOT NULL,
  `passkey_public_key` text NOT NULL,
  `passkey_sign_count` int(10) unsigned NOT NULL DEFAULT 0,
  `passkey_aaguid` varchar(36) DEFAULT NULL,
  `passkey_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `passkey_last_used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`passkey_id`),
  KEY `passkey_user_id` (`passkey_user_id`),
  KEY `passkey_credential_id_hash` (`passkey_credential_id_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
