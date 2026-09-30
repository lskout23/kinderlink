-- KinderLink — API tokens for the Android/iOS app (Capacitor).
-- Apply once on installations created before 2026-09-28.
-- New installations already contain this table via database/schema.sql.
--
-- Take a database backup before running this file.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `token_hash`   CHAR(64) NOT NULL,
  `device_name`  VARCHAR(100) NOT NULL DEFAULT '',
  `platform`     ENUM('android','ios','web','other') NOT NULL DEFAULT 'other',
  `last_used_at` DATETIME NULL DEFAULT NULL,
  `expires_at`   DATETIME NOT NULL,
  `revoked_at`   DATETIME NULL DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_api_token_hash` (`token_hash`),
  KEY `idx_api_tokens_user` (`user_id`, `revoked_at`),
  KEY `idx_api_tokens_expiry` (`expires_at`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
