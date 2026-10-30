-- MySQL dump 10.13  Distrib 8.0.45, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: web_ban_code
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `balance_logs`
--

DROP TABLE IF EXISTS `balance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `balance_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `admin_id` int unsigned DEFAULT NULL COMMENT 'ID admin nếu là can thiệp thủ công',
  `amount_before` decimal(15,2) NOT NULL,
  `amount` decimal(15,2) NOT NULL COMMENT 'Số tiền biến động (+ hoặc -)',
  `amount_after` decimal(15,2) NOT NULL,
  `type` enum('deposit_card','deposit_bank','buy_code','admin_adjust','refund') COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_balance_user` (`user_id`),
  KEY `idx_balance_type` (`type`),
  CONSTRAINT `fk_balance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `balance_logs`
--

LOCK TABLES `balance_logs` WRITE;
/*!40000 ALTER TABLE `balance_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `balance_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_transactions`
--

DROP TABLE IF EXISTS `bank_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_transactions`
--

LOCK TABLES `bank_transactions` WRITE;
/*!40000 ALTER TABLE `bank_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `card_transactions`
--

DROP TABLE IF EXISTS `card_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `card_transactions`
--

LOCK TABLES `card_transactions` WRITE;
/*!40000 ALTER TABLE `card_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `card_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'PHP & Laravel','php-laravel','fa-brands fa-php','Hệ thống website PHP thuần, CodeIgniter, Laravel hoàn chỉnh',1,1,'2026-10-25 13:48:40','2026-10-25 13:48:40'),(2,'Node.js & React','nodejs-react','fa-brands fa-node-js','Full-stack MERN, Next.js, API backend hiệu năng cao',2,1,'2026-10-25 13:48:40','2026-10-25 13:48:40'),(3,'Python & Django/FastAPI','python-django','fa-brands fa-python','Tools tự động hóa, AI script, Backend API Django/FastAPI',3,1,'2026-10-25 13:48:40','2026-10-25 13:48:40'),(4,'Mobile Apps (Flutter/React Native)','mobile-apps','fa-solid fa-mobile-screen','Ứng dụng di động iOS/Android đa nền tảng',4,1,'2026-10-25 13:48:40','2026-10-25 13:48:40'),(5,'Game & Unity','game-unity','fa-solid fa-gamepad','Source code game 2D/3D Unity, HTML5 mini game',5,1,'2026-10-25 13:48:40','2026-10-25 13:48:40'),(6,'WordPress Plugins & Themes','wordpress','fa-brands fa-wordpress','Giao diện & tiện ích mở rộng WordPress chuẩn SEO',6,1,'2026-10-25 13:48:40','2026-10-25 13:48:40');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_views`
--

DROP TABLE IF EXISTS `product_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_views` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `viewed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_ip` (`product_id`,`ip_address`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_views`
--

LOCK TABLES `product_views` WRITE;
/*!40000 ALTER TABLE `product_views` DISABLE KEYS */;
INSERT INTO `product_views` VALUES (1,1,'::1','2026-10-25 15:58:36'),(2,1,'127.0.0.1','2026-10-25 15:58:50'),(4,4,'::1','2026-10-25 16:00:32'),(10,2,'::1','2026-10-27 10:07:51'),(11,3,'::1','2026-10-27 10:11:47');
/*!40000 ALTER TABLE `product_views` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,1,'Hệ Thống Website Bán Mã Nguồn Tự Động PHP Thuần PDO','he-thong-web-ban-ma-nguon-tu-dong-php','Hệ thống sàn mua bán source code tự động tích hợp nạp thẻ cào, VietQR tự động, quản trị toàn diện.','<p>Bộ source code được xây dựng bằng <strong>PHP 8.x</strong> thuần hướng đối tượng, bảo mật PDO, giao diện Tailwind CSS hiện đại.</p><h3>Tính năng nổi bật:</h3><ul><li>Nạp tiền tự động qua VietQR và Thẻ cào</li><li>Bảo mật CSRF, XSS, mã hóa BCRYPT</li><li>Admin Dashboard trực quan với Chart.js</li><li>Hỗ trợ tải mã nguồn qua secure token</li></ul>',499000.00,350000.00,'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&auto=format&fit=crop&q=80',NULL,'https://demo.marketcode.vn','https://drive.google.com/file/d/sample1/view','15 MB',128,1421,1420,4.95,'active','2026-10-25 13:48:40','2026-10-25 15:58:36'),(2,2,'Full-Stack E-Commerce Next.js 14 & Node.js Microservices','full-stack-ecommerce-nextjs-nodejs','Nền tảng thương mại điện tử hiện đại tích hợp Stripe, PayPal, Redux Toolkit, Tailwind CSS.','<p>Dự án thương mại điện tử cấp doanh nghiệp với Next.js 14 App Router, Server Actions và Node.js API bảo mật cao.</p>',890000.00,650000.00,'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800&auto=format&fit=crop&q=80',NULL,'https://next-ecommerce.demo.vn','https://drive.google.com/file/d/sample2/view','15 MB',89,981,980,4.90,'active','2026-10-25 13:48:40','2026-10-27 10:07:51'),(3,4,'Flutter Food Delivery App (iOS & Android) + Admin Panel','flutter-food-delivery-app-ios-android','Ứng dụng đặt đồ ăn hoàn chỉnh kèm bản đồ thời gian thực, thông báo Firebase và CMS quản trị.','<p>Source code Flutter 3.x chuẩn clean architecture, hỗ trợ cả 2 nền tảng iOS & Android kèm Backend Laravel CMS.</p>',1200000.00,890000.00,'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=800&auto=format&fit=crop&q=80',NULL,'https://flutter-food.demo.vn','https://drive.google.com/file/d/sample3/view','15 MB',215,2301,2300,5.00,'active','2026-10-25 13:48:40','2026-10-27 10:11:47'),(4,3,'Python Bot Tự Động Hóa Quét Dữ Liệu & Gửi Báo Cáo Telegram','python-bot-auto-crawl-telegram','Script tự động thu thập giá sản phẩm từ sàn TMĐT và gửi báo cáo phân tích về Telegram hàng ngày.','<p>Viết bằng Python 3.11, sử dụng Playwright, BeautifulSoup4 và Telegram Bot API.</p>',250000.00,199000.00,'https://images.unsplash.com/photo-1526379095098-d400fd0bf935?w=800&auto=format&fit=crop&q=80',NULL,'https://t.me/demo_crawler_bot','https://drive.google.com/file/d/sample4/view','15 MB',340,3101,3100,4.88,'active','2026-10-25 13:48:40','2026-10-25 16:00:32');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` longtext COLLATE utf8mb4_unicode_ci,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=212 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'site_name','MARKETCODE.VN - Sàn Giao Dịch Mã Nguồn Số 1','Tên website chính thức','2026-10-29 13:29:18'),(2,'site_logo','assets/images/logo_1793255358.png','Đường dẫn file logo website','2026-10-29 13:29:18'),(3,'site_favicon','assets/images/favicon_1793255358.png','Đường dẫn favicon website','2026-10-29 13:29:18'),(4,'site_description','Chợ mua bán mã nguồn chất lượng cao, chia sẻ template website, ứng dụng mobile, bảo hành và cài đặt nhanh chóng.','Mô tả SEO website','2026-10-29 13:29:18'),(5,'site_keywords','','Từ khóa SEO','2026-10-29 13:29:18'),(6,'contact_hotline','0988.888.888','Hotline hỗ trợ kỹ thuật','2026-10-29 13:29:18'),(7,'contact_email','support@marketcode.vn','Email hỗ trợ','2026-10-29 13:29:18'),(8,'contact_telegram','https://t.me/admin_marketcode','Telegram hỗ trợ','2026-10-29 13:29:18'),(9,'bank_name','MB Bank (Quân Đội)','Tên ngân hàng nhận tiền','2026-10-25 13:48:40'),(10,'bank_account_name','NGUYEN VAN A','Chủ tài khoản ngân hàng','2026-10-25 13:48:40'),(11,'bank_account_number','999988889999','Số tài khoản ngân hàng','2026-10-25 13:48:40'),(12,'bank_bin','970422','Mã BIN ngân hàng MBBank để gen VietQR','2026-10-25 13:48:40'),(13,'bank_prefix','NAPTIEN','Cú pháp nạp tiền trước ID user (VD: NAPTIEN 1)','2026-10-25 13:48:40'),(14,'card_partner_id','','Partner ID cổng gạch thẻ','2026-10-29 13:29:18'),(15,'card_partner_key','','Partner Key cổng gạch thẻ','2026-10-29 13:29:18'),(16,'card_api_url','https://thesieure.com/chargingws/v2','Endpoint API gạch thẻ cào','2026-10-29 13:29:18'),(17,'oauth_google_client_id','','Google OAuth Client ID','2026-10-29 13:29:18'),(18,'oauth_google_client_secret','','Google OAuth Client Secret','2026-10-29 13:29:18'),(19,'oauth_google_redirect_uri','/auth/google.php','Google OAuth Redirect URI','2026-10-29 13:29:18'),(20,'oauth_facebook_app_id','','Facebook App ID','2026-10-29 13:29:18'),(21,'oauth_facebook_app_secret','','Facebook App Secret','2026-10-29 13:29:18'),(22,'oauth_facebook_redirect_uri','/auth/facebook.php','Facebook OAuth Redirect URI','2026-10-29 13:29:18'),(23,'recaptcha_site_key','','Google reCAPTCHA v2 Site Key','2026-10-29 13:29:18'),(24,'recaptcha_secret_key','','Google reCAPTCHA v2 Secret Key','2026-10-29 13:29:18'),(25,'popup_enabled','1','Bật tắt popup thông báo nổi trang chủ (1: Bật, 0: Tắt)','2026-10-29 13:29:18'),(26,'popup_title','📢 THÔNG BÁO HỆ THỐNG & KHUYẾN MÃI MỚI NHẤT','Tiêu đề popup thông báo trang chủ','2026-10-29 13:29:18'),(27,'popup_content','<p class=\"mb-3 font-semibold text-brand-400\">Chào mừng quý khách đến với MARKETCODE.VN - Chợ Mã Nguồn Số 1 Việt Nam!</p><ul class=\"space-y-2 list-disc pl-5 text-xs text-gray-300\"><li><strong>Khuyến Mãi Nạp Tiền:</strong> Tặng ngay <strong>+10% giá trị</strong> cho tất cả giao dịch nạp qua VietQR Ngân hàng hôm nay.</li><li><strong>Cam Kết Chất Lượng:</strong> Toàn bộ source code được kiểm duyệt nghiêm ngặt, sạch 100% không mã độc và cam kết hỗ trợ cài đặt chạy thành công.</li><li><strong>Kênh Hỗ Trợ Kỹ Thuật:</strong> Tham gia nhóm Telegram <a href=\"https://t.me/admin_marketcode\" target=\"_blank\" class=\"text-indigo-400 underline font-bold\">@marketcode_support</a> để được hỗ trợ 24/7.</li></ul>','Nội dung HTML popup thông báo trang chủ','2026-10-29 13:29:18'),(28,'momo_partner_code','MOMOM7RQ20250327','MoMo Partner Code','2026-10-29 13:29:18'),(29,'momo_access_key','ZL6mPDuL5SrXA1MQ','MoMo Access Key','2026-10-29 13:29:18'),(30,'momo_secret_key','ltpIRmVqaLOeTwJiQG2jJxthTM42EPoF','MoMo Secret Key','2026-10-29 13:29:18'),(31,'momo_api_endpoint','https://payment.momo.vn/v2/gateway/api/create','MoMo API Endpoint','2026-10-29 13:29:18'),(56,'oauth_zalo_app_id','','','2026-10-29 13:29:18'),(57,'oauth_zalo_secret_key','','','2026-10-29 13:29:18'),(58,'oauth_zalo_redirect_uri','http://localhost:8000/oauth_zalo.php','','2026-10-29 13:29:18'),(61,'cron_security_key','marketcode_cron_secret','','2026-10-29 13:29:18');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@marketcode.vn','$2y$10$/sfCh8nJt1a25D6puGjO/uL2hcHuxa1FxR0j23brD382Vesv.EvP2','Hệ Thống Quản Trị','assets/uploads/avatars/avatar_1_1793079545.png',10000000.00,0.00,'admin','active',NULL,NULL,NULL,'2026-10-25 13:48:40','2026-10-27 12:39:05'),(2,'phonghuynhuuu','phonghuynh031226@gmail.com','$2y$10$ekIWwIJOSni0Pa4m/iQ7Lew2S61wFnYmDnY/K99nbc1EvOxkYP7uS','Thanh Phong Huỳnh','assets/images/default-avatar.png',0.00,0.00,'user','active','google','102442427074218340919',NULL,'2026-10-25 14:09:15','2026-10-29 13:29:48');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'web_ban_code'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-30 15:23:27
