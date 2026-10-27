-- MoMo hosted checkout top-up transactions.
-- Run once against the existing web_ban_code database.
CREATE TABLE IF NOT EXISTS `momo_transactions` (
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

ALTER TABLE `balance_logs`
  MODIFY `type` ENUM('deposit_card', 'deposit_bank', 'deposit_momo', 'buy_code', 'admin_adjust', 'refund') NOT NULL;
