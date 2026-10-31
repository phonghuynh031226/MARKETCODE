-- Fresh-install baseline schema only: no credentials, accounts, balances or history.
-- Existing installations: use database/migrate_security.php instead of importing this file.
-- After baseline creation, run the CLI security migration before using the application.
SET NAMES utf8mb4;

CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fullname` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'assets/images/default-avatar.png',
  `balance` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Số dư khả dụng hiện tại',
  `total_deposited` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Tổng tiền đã nạp tích lũy',
  `role` enum('user','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `status` enum('active','banned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `oauth_provider` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'google, facebook, zalo',
  `oauth_uid` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_oauth` (`oauth_provider`,`oauth_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'fas fa-code' COMMENT 'FontAwesome class hoặc SVG name',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1: Hiện, 0: Ẩn',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categories_slug` (`slug`),
  KEY `idx_categories_status_sort` (`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(280) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_desc` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Mô tả chi tiết và hướng dẫn cài đặt',
  `price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `sale_price` decimal(15,2) DEFAULT NULL COMMENT 'Giá khuyến mãi (nếu có)',
  `thumbnail` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assets/images/default-thumbnail.jpg',
  `gallery` text COLLATE utf8mb4_unicode_ci COMMENT 'Danh sách JSON ảnh demo bổ sung',
  `demo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Link trải nghiệm live demo',
  `download_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Link lưu trữ file (Drive/Mega/Local file)',
  `file_size` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '15 MB',
  `download_count` int unsigned NOT NULL DEFAULT '0',
  `views` int unsigned DEFAULT '0',
  `view_count` int unsigned NOT NULL DEFAULT '0',
  `rating` decimal(3,2) NOT NULL DEFAULT '5.00',
  `status` enum('active','hidden','draft') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_slug` (`slug`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_price` (`price`),
  KEY `idx_products_status` (`status`),
  KEY `idx_products_downloads` (`download_count`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Mã đơn dạng ORD-xxxxxx',
  `user_id` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `download_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Token dùng để tải file an toàn',
  `download_expires_at` datetime DEFAULT NULL COMMENT 'Thời hạn tải token (NULL = vĩnh viễn)',
  `status` enum('completed','refunded','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_orders_code` (`order_code`),
  UNIQUE KEY `uk_orders_download_token` (`download_token`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_product` (`product_id`),
  KEY `idx_orders_status` (`status`),
  CONSTRAINT `fk_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `balance_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `admin_id` int unsigned DEFAULT NULL COMMENT 'ID admin nếu là can thiệp thủ công',
  `amount_before` decimal(15,2) NOT NULL,
  `amount` decimal(15,2) NOT NULL COMMENT 'Số tiền biến động (+ hoặc -)',
  `amount_after` decimal(15,2) NOT NULL,
  `type` enum('deposit_card','deposit_bank','deposit_momo','buy_code','admin_adjust','refund') COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_balance_user` (`user_id`),
  KEY `idx_balance_type` (`type`),
  CONSTRAINT `fk_balance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bank_transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `trans_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Mã giao dịch nội bộ',
  `user_id` int unsigned NOT NULL,
  `bank_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MBBANK',
  `account_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transfer_content` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Cú pháp chuyển khoản: NAPTIEN ID123',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','success','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `ref_transaction_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mã giao dịch đối soát từ ngân hàng',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bank_trans_id` (`trans_id`),
  KEY `idx_bank_user` (`user_id`),
  KEY `idx_bank_transfer_content` (`transfer_content`),
  KEY `idx_bank_status` (`status`),
  CONSTRAINT `fk_bank_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `card_transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `trans_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Mã giao dịch trên hệ thống',
  `request_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mã gửi sang cổng gạch thẻ',
  `user_id` int unsigned NOT NULL,
  `telco` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'VIETTEL, VINAPHONE, MOBIFONE, ZING...',
  `declared_amount` int NOT NULL COMMENT 'Mệnh giá khai báo',
  `real_amount` int NOT NULL DEFAULT '0' COMMENT 'Mệnh giá thực tế nhận được',
  `received_amount` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Số tiền thực nhận sau chiết khấu',
  `serial` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pin` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','success','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `response_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_card_trans_id` (`trans_id`),
  KEY `idx_card_user` (`user_id`),
  KEY `idx_card_status` (`status`),
  CONSTRAINT `fk_card_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_views` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `viewed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_ip` (`product_id`,`ip_address`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` longtext COLLATE utf8mb4_unicode_ci,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `momo_transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `status` enum('pending','success','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `momo_trans_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result_code` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_momo_order_id` (`order_id`),
  UNIQUE KEY `uk_momo_request_id` (`request_id`),
  KEY `idx_momo_user_created` (`user_id`,`created_at`),
  KEY `idx_momo_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
