-- Generated from the verified security migration. Fresh installations only.

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `actor_user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `resource_type` varchar(80) NOT NULL,
  `resource_public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `created_at` timestamp(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_audit_public_id` (`public_id`),
  KEY `idx_audit_actor_created` (`actor_user_id`,`created_at`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_resource` (`resource_type`,`resource_public_id`,`created_at`),
  CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `balance_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `admin_id` int(10) unsigned DEFAULT NULL COMMENT 'ID admin nß║┐u l├á can thiß╗çp thß╗º c├┤ng',
  `amount_before` decimal(15,2) NOT NULL,
  `amount` decimal(15,2) NOT NULL COMMENT 'Sß╗æ tiß╗ün biß║┐n ─æß╗Öng (+ hoß║Àc -)',
  `amount_after` decimal(15,2) NOT NULL,
  `type` enum('deposit_card','deposit_bank','deposit_momo','buy_code','admin_adjust','refund') NOT NULL,
  `note` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_balance_user` (`user_id`),
  KEY `idx_balance_type` (`type`),
  CONSTRAINT `fk_balance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trans_id` varchar(64) NOT NULL COMMENT 'M├ú giao dß╗ïch nß╗Öi bß╗Ö',
  `user_id` int(10) unsigned NOT NULL,
  `bank_code` varchar(20) NOT NULL DEFAULT 'MBBANK',
  `account_number` varchar(50) NOT NULL,
  `transfer_content` varchar(100) NOT NULL COMMENT 'C├║ ph├íp chuyß╗ân khoß║ún: NAPTIEN ID123',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','success','cancelled') NOT NULL DEFAULT 'pending',
  `ref_transaction_id` varchar(100) DEFAULT NULL COMMENT 'M├ú giao dß╗ïch ─æß╗æi so├ít tß╗½ ng├ón h├áng',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bank_trans_id` (`trans_id`),
  UNIQUE KEY `uk_security_public_id` (`public_id`),
  KEY `idx_bank_user` (`user_id`),
  KEY `idx_bank_transfer_content` (`transfer_content`),
  KEY `idx_bank_status` (`status`),
  CONSTRAINT `fk_bank_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `card_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trans_id` varchar(64) NOT NULL COMMENT 'M├ú giao dß╗ïch tr├¬n hß╗ç thß╗æng',
  `request_id` varchar(64) DEFAULT NULL COMMENT 'M├ú gß╗¡i sang cß╗òng gß║ích thß║╗',
  `user_id` int(10) unsigned NOT NULL,
  `telco` varchar(20) NOT NULL COMMENT 'VIETTEL, VINAPHONE, MOBIFONE, ZING...',
  `declared_amount` int(11) NOT NULL COMMENT 'Mß╗çnh gi├í khai b├ío',
  `real_amount` int(11) NOT NULL DEFAULT 0 COMMENT 'Mß╗çnh gi├í thß╗▒c tß║┐ nhß║¡n ─æã░ß╗úc',
  `received_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Sß╗æ tiß╗ün thß╗▒c nhß║¡n sau chiß║┐t khß║Ñu',
  `serial` varchar(50) NOT NULL,
  `pin` varchar(50) NOT NULL,
  `status` enum('pending','success','error') NOT NULL DEFAULT 'pending',
  `response_message` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `deleted_at` datetime(6) DEFAULT NULL,
  `pin_digest` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_card_trans_id` (`trans_id`),
  UNIQUE KEY `uk_security_public_id` (`public_id`),
  UNIQUE KEY `uk_card_serial_digest` (`serial`,`pin_digest`),
  KEY `idx_card_user` (`user_id`),
  KEY `idx_card_status` (`status`),
  CONSTRAINT `fk_card_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `icon` varchar(50) DEFAULT 'fas fa-code' COMMENT 'FontAwesome class hoß║Àc SVG name',
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1: Hiß╗çn, 0: ß║¿n',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categories_slug` (`slug`),
  UNIQUE KEY `uk_security_public_id` (`public_id`),
  KEY `idx_categories_status_sort` (`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `momo_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` varchar(64) NOT NULL,
  `request_id` varchar(64) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `amount` bigint(20) unsigned NOT NULL,
  `status` enum('pending','success','failed') NOT NULL DEFAULT 'pending',
  `momo_trans_id` varchar(64) DEFAULT NULL,
  `result_code` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_momo_order_id` (`order_id`),
  UNIQUE KEY `uk_momo_request_id` (`request_id`),
  UNIQUE KEY `uk_security_public_id` (`public_id`),
  KEY `idx_momo_user_created` (`user_id`,`created_at`),
  KEY `idx_momo_status` (`status`),
  CONSTRAINT `fk_momo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_code` varchar(50) NOT NULL COMMENT 'M├ú ─æãín dß║íng ORD-xxxxxx',
  `user_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `download_token` varchar(64) NOT NULL COMMENT 'Token d├╣ng ─æß╗â tß║úi file an to├án',
  `download_expires_at` datetime DEFAULT NULL COMMENT 'Thß╗Øi hß║ín tß║úi token (NULL = v─®nh viß╗àn)',
  `status` enum('completed','refunded','pending') NOT NULL DEFAULT 'completed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_orders_code` (`order_code`),
  UNIQUE KEY `uk_orders_download_token` (`download_token`),
  UNIQUE KEY `uk_security_public_id` (`public_id`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_product` (`product_id`),
  KEY `idx_orders_status` (`status`),
  CONSTRAINT `fk_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_password_resets_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_views` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `viewed_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_ip` (`product_id`,`ip_address`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(280) NOT NULL,
  `short_desc` varchar(500) DEFAULT NULL,
  `content` longtext DEFAULT NULL COMMENT 'M├┤ tß║ú chi tiß║┐t v├á hã░ß╗øng dß║½n c├ái ─æß║Àt',
  `price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(15,2) DEFAULT NULL COMMENT 'Gi├í khuyß║┐n m├úi (nß║┐u c├│)',
  `thumbnail` varchar(255) NOT NULL DEFAULT 'assets/images/default-thumbnail.jpg',
  `gallery` text DEFAULT NULL COMMENT 'Danh s├ích JSON ß║únh demo bß╗ò sung',
  `demo_url` varchar(255) DEFAULT NULL COMMENT 'Link trß║úi nghiß╗çm live demo',
  `download_url` varchar(255) NOT NULL COMMENT 'Link lã░u trß╗» file (Drive/Mega/Local file)',
  `file_size` varchar(50) DEFAULT '15 MB',
  `download_count` int(10) unsigned NOT NULL DEFAULT 0,
  `views` int(10) unsigned DEFAULT 0,
  `view_count` int(10) unsigned NOT NULL DEFAULT 0,
  `rating` decimal(3,2) NOT NULL DEFAULT 5.00,
  `status` enum('active','hidden','draft') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_slug` (`slug`),
  UNIQUE KEY `uk_security_public_id` (`public_id`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_price` (`price`),
  KEY `idx_products_status` (`status`),
  KEY `idx_products_downloads` (`download_count`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `chk_security_price_nonnegative` CHECK (`price` >= 0),
  CONSTRAINT `chk_security_sale_price_nonnegative` CHECK (`sale_price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(254) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'assets/images/default-avatar.png',
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Sß╗æ dã░ khß║ú dß╗Ñng hiß╗çn tß║íi',
  `total_deposited` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Tß╗òng tiß╗ün ─æ├ú nß║íp t├¡ch l┼®y',
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `status` enum('active','banned') NOT NULL DEFAULT 'active',
  `oauth_provider` varchar(30) DEFAULT NULL COMMENT 'google, facebook, zalo',
  `oauth_uid` varchar(100) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `public_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `deleted_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`),
  UNIQUE KEY `uk_security_public_id` (`public_id`),
  UNIQUE KEY `uk_users_oauth_identity` (`oauth_provider`,`oauth_uid`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_oauth` (`oauth_provider`,`oauth_uid`),
  CONSTRAINT `chk_security_balance_nonnegative` CHECK (`balance` >= 0),
  CONSTRAINT `chk_security_total_deposited_nonnegative` CHECK (`total_deposited` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

