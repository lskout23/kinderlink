-- Additive migration; does not change messages or import browser-local flags.
CREATE TABLE IF NOT EXISTS `child_attendance` (
  `child_id` INT UNSIGNED NOT NULL,
  `attendance_date` DATE NOT NULL,
  `is_absent` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`child_id`, `attendance_date`),
  KEY `idx_attendance_date` (`attendance_date`, `is_absent`),
  CONSTRAINT `fk_attendance_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;