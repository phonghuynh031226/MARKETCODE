
-- ========================================================
-- DỰ ÁN: SOURCE CODE MARKETPLACE (WEBSITE BÁN MÃ NGUỒN)
-- DATABASE SCHEMA: MySQL 8.x+ / MariaDB 10.5+
-- CHARSET: utf8mb4 / COLLATION: utf8mb4_unicode_ci

-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `momo_transactions`;
DROP TABLE IF EXISTS `balance_logs`;
DROP TABLE IF EXISTS `bank_transactions`;
DROP TABLE IF EXISTS `card_transactions`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- 1. BẢNG USERS (Người dùng & Quản trị viên)
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(100) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT 'assets/images/default-avatar.png',
  `balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Số dư khả dụng hiện tại',
  `total_deposited` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Tổng tiền đã nạp tích lũy',
  `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  `status` ENUM('active', 'banned') NOT NULL DEFAULT 'active',
  `oauth_provider` VARCHAR(30) DEFAULT NULL COMMENT 'google, facebook, zalo',
  `oauth_uid` VARCHAR(100) DEFAULT NULL,
  `remember_token` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_oauth` (`oauth_provider`, `oauth_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- BẢNG PASSWORD_RESETS (Mã xác thực khôi phục mật khẩu)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(10) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_pwd_reset_email` (`email`),
  KEY `idx_pwd_reset_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. BẢNG CATEGORIES (Danh mục mã nguồn)
-- --------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `icon` VARCHAR(50) DEFAULT 'fas fa-code' COMMENT 'FontAwesome class hoặc SVG name',
  `description` VARCHAR(255) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1: Hiện, 0: Ẩn',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categories_slug` (`slug`),
  KEY `idx_categories_status_sort` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. BẢNG PRODUCTS (Sản phẩm mã nguồn)
-- --------------------------------------------------------
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(280) NOT NULL,
  `short_desc` VARCHAR(500) DEFAULT NULL,
  `content` LONGTEXT DEFAULT NULL COMMENT 'Mô tả chi tiết và hướng dẫn cài đặt',
  `price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `sale_price` DECIMAL(15,2) DEFAULT NULL COMMENT 'Giá khuyến mãi (nếu có)',
  `thumbnail` VARCHAR(255) NOT NULL DEFAULT 'assets/images/default-thumbnail.jpg',
  `gallery` TEXT DEFAULT NULL COMMENT 'Danh sách JSON ảnh demo bổ sung',
  `demo_url` VARCHAR(255) DEFAULT NULL COMMENT 'Link trải nghiệm live demo',
  `download_url` VARCHAR(255) NOT NULL COMMENT 'Link lưu trữ file (Drive/Mega/Local file)',
  `file_size` VARCHAR(50) DEFAULT '15 MB',
  `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('active', 'hidden', 'draft') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_slug` (`slug`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_price` (`price`),
  KEY `idx_products_status` (`status`),
  KEY `idx_products_downloads` (`download_count`),
  KEY `idx_products_views` (`views`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3.1 BẢNG PRODUCT_VIEWS (Theo dõi lượt xem theo IP duy nhất)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_views` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `viewed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_ip` (`product_id`, `ip_address`),
  KEY `idx_product_views_product` (`product_id`),
  CONSTRAINT `fk_product_views_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. BẢNG ORDERS (Đơn hàng mua mã nguồn)
-- --------------------------------------------------------
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_code` VARCHAR(50) NOT NULL COMMENT 'Mã đơn dạng ORD-xxxxxx',
  `user_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `download_token` VARCHAR(64) NOT NULL COMMENT 'Token dùng để tải file an toàn',
  `download_expires_at` DATETIME DEFAULT NULL COMMENT 'Thời hạn tải token (NULL = vĩnh viễn)',
  `status` ENUM('completed', 'refunded', 'pending') NOT NULL DEFAULT 'completed',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_orders_code` (`order_code`),
  UNIQUE KEY `uk_orders_download_token` (`download_token`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_product` (`product_id`),
  KEY `idx_orders_status` (`status`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. BẢNG CARD_TRANSACTIONS (Lịch sử nạp thẻ cào)
-- --------------------------------------------------------
CREATE TABLE `card_transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trans_id` VARCHAR(64) NOT NULL COMMENT 'Mã giao dịch trên hệ thống',
  `request_id` VARCHAR(64) DEFAULT NULL COMMENT 'Mã gửi sang cổng gạch thẻ',
  `user_id` INT UNSIGNED NOT NULL,
  `telco` VARCHAR(20) NOT NULL COMMENT 'VIETTEL, VINAPHONE, MOBIFONE, ZING...',
  `declared_amount` INT NOT NULL COMMENT 'Mệnh giá khai báo',
  `real_amount` INT NOT NULL DEFAULT 0 COMMENT 'Mệnh giá thực tế nhận được',
  `received_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Số tiền thực nhận sau chiết khấu',
  `serial` VARCHAR(50) NOT NULL,
  `pin` VARCHAR(50) NOT NULL,
  `status` ENUM('pending', 'success', 'error') NOT NULL DEFAULT 'pending',
  `response_message` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_card_trans_id` (`trans_id`),
  KEY `idx_card_user` (`user_id`),
  KEY `idx_card_status` (`status`),
  CONSTRAINT `fk_card_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. BẢNG BANK_TRANSACTIONS (Lịch sử nạp qua Ngân hàng / VietQR)
-- --------------------------------------------------------
CREATE TABLE `bank_transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trans_id` VARCHAR(64) NOT NULL COMMENT 'Mã giao dịch nội bộ',
  `user_id` INT UNSIGNED NOT NULL,
  `bank_code` VARCHAR(20) NOT NULL DEFAULT 'MBBANK',
  `account_number` VARCHAR(50) NOT NULL,
  `transfer_content` VARCHAR(100) NOT NULL COMMENT 'Cú pháp chuyển khoản: NAPTIEN ID123',
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending', 'success', 'cancelled') NOT NULL DEFAULT 'pending',
  `ref_transaction_id` VARCHAR(100) DEFAULT NULL COMMENT 'Mã giao dịch đối soát từ ngân hàng',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bank_trans_id` (`trans_id`),
  KEY `idx_bank_user` (`user_id`),
  KEY `idx_bank_transfer_content` (`transfer_content`),
  KEY `idx_bank_status` (`status`),
  CONSTRAINT `fk_bank_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. BẢNG BALANCE_LOGS (Nhật ký biến động số dư)
-- --------------------------------------------------------
CREATE TABLE `balance_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `admin_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID admin nếu là can thiệp thủ công',
  `amount_before` DECIMAL(15,2) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL COMMENT 'Số tiền biến động (+ hoặc -)',
  `amount_after` DECIMAL(15,2) NOT NULL,
  `type` ENUM('deposit_card', 'deposit_bank', 'deposit_momo', 'buy_code', 'admin_adjust', 'refund') NOT NULL,
  `note` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_balance_user` (`user_id`),
  KEY `idx_balance_type` (`type`),
  CONSTRAINT `fk_balance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- --------------------------------------------------------
-- MoMo Gateway top-up transactions
-- --------------------------------------------------------
CREATE TABLE `momo_transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` VARCHAR(64) NOT NULL,
  `request_id` VARCHAR(64) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
  `momo_trans_id` VARCHAR(64) DEFAULT NULL,
  `result_code` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_momo_order_id` (`order_id`),
  UNIQUE KEY `uk_momo_request_id` (`request_id`),
  KEY `idx_momo_user_created` (`user_id`, `created_at`),
  KEY `idx_momo_status` (`status`),
  CONSTRAINT `fk_momo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- 8. BẢNG SETTINGS (Cài đặt hệ thống)
-- --------------------------------------------------------
CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` LONGTEXT DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ========================================================
-- DỮ LIỆU KHỞI TẠO BAN ĐẦU (SEED DATA)

-- ========================================================

-- 1. Tài khoản Quản trị viên mặc định: admin / admin@123
-- Hash Bcrypt chuẩn của: admin@123
INSERT INTO `users` (`id`, `username`, `email`, `password`, `fullname`, `balance`, `role`, `status`) VALUES
(1, 'admin', 'admin@marketcode.vn', '$2y$10$/sfCh8nJt1a25D6puGjO/uL2hcHuxa1FxR0j23brD382Vesv.EvP2', 'Hệ Thống Quản Trị', 10000000.00, 'admin', 'active');

-- 2. Danh mục mã nguồn mẫu
INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `description`, `sort_order`, `status`) VALUES
(1, 'PHP & Laravel', 'php-laravel', 'fa-brands fa-php', 'Hệ thống website PHP thuần, CodeIgniter, Laravel hoàn chỉnh', 1, 1),
(2, 'Node.js & React', 'nodejs-react', 'fa-brands fa-node-js', 'Full-stack MERN, Next.js, API backend hiệu năng cao', 2, 1),
(3, 'Python & Django/FastAPI', 'python-django', 'fa-brands fa-python', 'Tools tự động hóa, AI script, Backend API Django/FastAPI', 3, 1),
(4, 'Mobile Apps (Flutter/React Native)', 'mobile-apps', 'fa-solid fa-mobile-screen', 'Ứng dụng di động iOS/Android đa nền tảng', 4, 1),
(5, 'Game & Unity', 'game-unity', 'fa-solid fa-gamepad', 'Source code game 2D/3D Unity, HTML5 mini game', 5, 1),
(6, 'WordPress Plugins & Themes', 'wordpress', 'fa-brands fa-wordpress', 'Giao diện & tiện ích mở rộng WordPress chuẩn SEO', 6, 1);

-- 3. Cài đặt hệ thống mặc định (Settings)
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('site_name', 'MARKETCODE.VN - Sàn Giao Dịch Mã Nguồn Số 1', 'Tên website chính thức'),
('site_logo', 'assets/images/logo.png', 'Đường dẫn file logo website'),
('site_favicon', 'assets/images/favicon.ico', 'Đường dẫn favicon website'),
('site_description', 'Chợ mua bán mã nguồn chất lượng cao, chia sẻ template website, ứng dụng mobile, bảo hành và cài đặt nhanh chóng.', 'Mô tả SEO website'),
('site_keywords', 'source code, mua ban ma nguon, code php, web ban code, share code, script', 'Từ khóa SEO'),
('contact_hotline', '0988.888.888', 'Hotline hỗ trợ kỹ thuật'),
('contact_email', 'support@marketcode.vn', 'Email hỗ trợ'),
('contact_telegram', 'https://t.me/admin_marketcode', 'Telegram hỗ trợ'),
('bank_name', 'MB Bank (Quân Đội)', 'Tên ngân hàng nhận tiền'),
('bank_account_name', 'NGUYEN VAN A', 'Chủ tài khoản ngân hàng'),
('bank_account_number', '999988889999', 'Số tài khoản ngân hàng'),
('bank_bin', '970422', 'Mã BIN ngân hàng MBBank để gen VietQR'),
('bank_prefix', 'NAPTIEN', 'Cú pháp nạp tiền trước ID user (VD: NAPTIEN 1)'),
('momo_partner_code', '', 'MoMo Partner Code'),
('momo_access_key', '', 'MoMo Access Key'),
('momo_secret_key', '', 'MoMo Secret Key'),
('momo_api_endpoint', 'https://payment.momo.vn/v2/gateway/api/create', 'MoMo API Endpoint'),
('card_partner_id', '', 'Partner ID cổng gạch thẻ'),
('card_partner_key', '', 'Partner Key cổng gạch thẻ'),
('card_api_url', 'https://thesieure.com/chargingws/v2', 'Endpoint API gạch thẻ cào'),
('oauth_google_client_id', '', 'Google OAuth Client ID'),
('oauth_google_client_secret', '', 'Google OAuth Client Secret'),
('oauth_google_redirect_uri', '/auth/google.php', 'Google OAuth Redirect URI'),
('oauth_facebook_app_id', '', 'Facebook App ID'),
('oauth_facebook_app_secret', '', 'Facebook App Secret'),
('oauth_facebook_redirect_uri', '/auth/facebook.php', 'Facebook OAuth Redirect URI'),
('recaptcha_site_key', '', 'Google reCAPTCHA v2 Site Key'),
('recaptcha_secret_key', '', 'Google reCAPTCHA v2 Secret Key'),
('popup_enabled', '1', 'Bật tắt popup thông báo trang chủ'),
('popup_title', '📢 THÔNG BÁO HỆ THỐNG & KHUYẾN MÃI MỚI NHẤT', 'Tiêu đề popup thông báo trang chủ'),
('popup_content', '<p class=\"mb-3 font-semibold text-brand-400\">Chào mừng quý khách đến với MARKETCODE.VN - Chợ Mã Nguồn Số 1 Việt Nam!</p><ul class=\"space-y-2 list-disc pl-5 text-xs text-gray-300\"><li><strong>Khuyến Mãi Nạp Tiền:</strong> Tặng ngay <strong>+10% giá trị</strong> cho tất cả giao dịch nạp qua VietQR Ngân hàng hôm nay.</li><li><strong>Cam Kết Chất Lượng:</strong> Toàn bộ source code được kiểm duyệt nghiêm ngặt, sạch 100% không mã độc và cam kết hỗ trợ cài đặt chạy thành công.</li><li><strong>Kênh Hỗ Trợ Kỹ Thuật:</strong> Tham gia nhóm Telegram <a href=\"https://t.me/admin_marketcode\" target=\"_blank\" class=\"text-indigo-400 underline font-bold\">@marketcode_support</a> để được hỗ trợ 24/7.</li></ul>', 'Nội dung popup thông báo');

-- 4. Một số sản phẩm mã nguồn mẫu (Demo Products)
INSERT INTO `products` (`id`, `category_id`, `title`, `slug`, `short_desc`, `content`, `price`, `sale_price`, `thumbnail`, `demo_url`, `download_url`, `download_count`, `view_count`, `rating`, `status`) VALUES
(1, 1, 'Hệ Thống Website Bán Mã Nguồn Tự Động PHP Thuần PDO', 'he-thong-web-ban-ma-nguon-tu-dong-php', 'Hệ thống sàn mua bán source code tự động tích hợp nạp thẻ cào, VietQR tự động, quản trị toàn diện.', '<p>Bộ source code được xây dựng bằng <strong>PHP 8.x</strong> thuần hướng đối tượng, bảo mật PDO, giao diện Tailwind CSS hiện đại.</p><h3>Tính năng nổi bật:</h3><ul><li>Nạp tiền tự động qua VietQR và Thẻ cào</li><li>Bảo mật CSRF, XSS, mã hóa BCRYPT</li><li>Admin Dashboard trực quan với Chart.js</li><li>Hỗ trợ tải mã nguồn qua secure token</li></ul>', 499000.00, 350000.00, 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&auto=format&fit=crop&q=80', 'https://demo.marketcode.vn', 'https://drive.google.com/file/d/sample1/view', 128, 1420, 4.95, 'active'),
(2, 2, 'Full-Stack E-Commerce Next.js 14 & Node.js Microservices', 'full-stack-ecommerce-nextjs-nodejs', 'Nền tảng thương mại điện tử hiện đại tích hợp Stripe, PayPal, Redux Toolkit, Tailwind CSS.', '<p>Dự án thương mại điện tử cấp doanh nghiệp với Next.js 14 App Router, Server Actions và Node.js API bảo mật cao.</p>', 890000.00, 650000.00, 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800&auto=format&fit=crop&q=80', 'https://next-ecommerce.demo.vn', 'https://drive.google.com/file/d/sample2/view', 89, 980, 4.90, 'active'),
(3, 4, 'Flutter Food Delivery App (iOS & Android) + Admin Panel', 'flutter-food-delivery-app-ios-android', 'Ứng dụng đặt đồ ăn hoàn chỉnh kèm bản đồ thời gian thực, thông báo Firebase và CMS quản trị.', '<p>Source code Flutter 3.x chuẩn clean architecture, hỗ trợ cả 2 nền tảng iOS & Android kèm Backend Laravel CMS.</p>', 1200000.00, 890000.00, 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=800&auto=format&fit=crop&q=80', 'https://flutter-food.demo.vn', 'https://drive.google.com/file/d/sample3/view', 215, 2300, 5.00, 'active'),
(4, 3, 'Python Bot Tự Động Hóa Quét Dữ Liệu & Gửi Báo Cáo Telegram', 'python-bot-auto-crawl-telegram', 'Script tự động thu thập giá sản phẩm từ sàn TMĐT và gửi báo cáo phân tích về Telegram hàng ngày.', '<p>Viết bằng Python 3.11, sử dụng Playwright, BeautifulSoup4 và Telegram Bot API.</p>', 250000.00, 199000.00, 'https://images.unsplash.com/photo-1526379095098-d400fd0bf935?w=800&auto=format&fit=crop&q=80', 'https://t.me/demo_crawler_bot', 'https://drive.google.com/file/d/sample4/view', 340, 3100, 4.88, 'active');
