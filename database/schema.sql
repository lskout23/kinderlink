-- KinderLink - complete schema for a NEW, EMPTY database only.
-- Import exactly once. Do not apply parent-project migrations or school seeds.
-- No users or school records are seeded; provision the first admin via CLI.
-- Character set: utf8mb4 for full Greek + emoji support

SET NAMES utf8mb4;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(100) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,          -- bcrypt hash
  `temp_password_expires` DATETIME NULL DEFAULT NULL,
  `name`       VARCHAR(200) NOT NULL,
  `email`      VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','teacher','parent') NOT NULL DEFAULT 'teacher',
  `active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CHILDREN
-- ============================================================
CREATE TABLE `children` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name`     VARCHAR(100) NOT NULL,
  `last_name`      VARCHAR(100) NOT NULL,
  `dob`            DATE,
  `mother_mobile`  VARCHAR(20),
  `father_mobile`  VARCHAR(20),
  `email1`         VARCHAR(255),              -- Primary parent email
  `email2`         VARCHAR(255),              -- Secondary parent email
  `send_email1`    TINYINT(1) NOT NULL DEFAULT 1,
  `send_email2`    TINYINT(1) NOT NULL DEFAULT 0,
  `active`         TINYINT(1) NOT NULL DEFAULT 1,
  `parent_user_id` INT UNSIGNED,              -- Optional linked parent user
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`parent_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CHILD ATTENDANCE (independent of messages, one state per child/day)
-- ============================================================
CREATE TABLE `child_attendance` (
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

-- ============================================================
-- GROUPS (CLASSES / ΤΜΗΜΑΤΑ)
-- ============================================================
CREATE TABLE `groups` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(200) NOT NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,   -- Βασικό (active/current year)
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CHILDREN <-> GROUPS (pivot)
-- ============================================================
CREATE TABLE `children_groups` (
  `child_id` INT UNSIGNED NOT NULL,
  `group_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`child_id`, `group_id`),
  FOREIGN KEY (`child_id`) REFERENCES `children`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TEACHER <-> GROUPS (which teacher manages which groups)
-- ============================================================
CREATE TABLE `teacher_groups` (
  `user_id`  INT UNSIGNED NOT NULL,
  `group_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `group_id`),
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ACTIVITIES (ΔΡΑΣΤΗΡΙΟΤΗΤΕΣ)
-- type: 'both' = shown in email; 'email_only' = observations only for email
-- ============================================================
CREATE TABLE `activities` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255) NOT NULL,
  `type`       ENUM('both','email_only') NOT NULL DEFAULT 'both',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FINANCIAL ACTIVITIES (ΔΡΑΣΤΗΡΙΟΤΗΤΕΣ ΟΙΚΟΝΟΜΙΚΩΝ)
-- ============================================================
CREATE TABLE `financial_activities` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EMAIL TEMPLATE
-- ============================================================
CREATE TABLE `email_template` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_html` MEDIUMTEXT NOT NULL,
  `subject`      VARCHAR(255) NOT NULL DEFAULT 'Καθημερινή Δραστηριότητα',
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PARAMETERS / SETTINGS
-- ============================================================
CREATE TABLE `parameters` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `param_key`   VARCHAR(100) NOT NULL UNIQUE,
  `param_value` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AUTH LOGIN ATTEMPTS (persistent brute-force throttling)
-- ============================================================
CREATE TABLE `auth_login_attempts` (
  `key_hash`      CHAR(40) NOT NULL,
  `ip_address`    VARCHAR(45) NOT NULL,
  `username_norm` VARCHAR(100) NOT NULL,
  `fail_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `first_fail_at` DATETIME NOT NULL,
  `last_fail_at`  DATETIME NOT NULL,
  `locked_until`  DATETIME NULL DEFAULT NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key_hash`),
  KEY `idx_auth_attempts_locked_until` (`locked_until`),
  KEY `idx_auth_attempts_username` (`username_norm`, `last_fail_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MESSAGES (daily activity records)
-- ============================================================
CREATE TABLE `messages` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `child_id`       INT UNSIGNED NOT NULL,
  `group_id`       INT UNSIGNED NOT NULL,
  `message_date`   DATE NOT NULL,
  `breakfast`      TINYINT UNSIGNED NOT NULL DEFAULT 4,  -- 1-4 scale
  `lunch`          TINYINT UNSIGNED NOT NULL DEFAULT 4,  -- 1-4 scale
  `mood`           TINYINT UNSIGNED NOT NULL DEFAULT 4,  -- 1-4 scale
  `sleep_minutes`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `wc`             TINYINT(1) NOT NULL DEFAULT 0,
  `activities`     TEXT,                                  -- comma-separated activity names
  `comments`       TEXT,                                  -- observations / free text
  `email_status`   ENUM('pending','sent','failed','virtual') NOT NULL DEFAULT 'pending',
  `email_sent_at`  DATETIME,
  `created_by`     INT UNSIGNED,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_child_date` (`child_id`, `message_date`),
  FOREIGN KEY (`child_id`)   REFERENCES `children`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`group_id`)   REFERENCES `groups`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MESSAGE PHOTOS (uploaded images linked to child/day messages)
-- ============================================================
CREATE TABLE `message_photos` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `child_id`      INT UNSIGNED NOT NULL,
  `group_id`      INT UNSIGNED NOT NULL,
  `message_date`  DATE NOT NULL,
  `stored_name`   VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type`     VARCHAR(120) NOT NULL,
  `size_bytes`    INT UNSIGNED NOT NULL,
  `access_token`  CHAR(48) NOT NULL UNIQUE,
  `hidden_at`     DATETIME NULL DEFAULT NULL,
  `created_by`    INT UNSIGNED,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msg_photos_lookup` (`child_id`, `group_id`, `message_date`),
  KEY `idx_msg_photos_created_at` (`created_at`),
  FOREIGN KEY (`child_id`) REFERENCES `children`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FREE EMAILS (group/bulk announcements)
-- ============================================================
CREATE TABLE `free_emails` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id`     INT UNSIGNED,                 -- NULL = all groups
  `subject`      VARCHAR(255) NOT NULL,
  `body`         MEDIUMTEXT NOT NULL,
  `sent_at`      DATETIME,
  `email_status` ENUM('pending','sent','failed','virtual') NOT NULL DEFAULT 'pending',
  `created_by`   INT UNSIGNED,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`group_id`)   REFERENCES `groups`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INCOME (ΕΣΟΔΑ)
-- ============================================================
CREATE TABLE `income` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `child_id`        INT UNSIGNED NOT NULL,
  `activity_id`     INT UNSIGNED,
  `description`     TEXT,
  `entry_date`      DATE NOT NULL,
  `collection_date` DATE,
  `amount`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `recurrence`      INT NOT NULL DEFAULT 1,
  `period_type`     ENUM('Μέρες','Εβδομάδες','Μήνες') NOT NULL DEFAULT 'Μέρες',
  `notes`           TEXT,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`child_id`)    REFERENCES `children`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`activity_id`) REFERENCES `financial_activities`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EXPENSES (ΕΞΟΔΑ)
-- ============================================================
CREATE TABLE `expenses` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_id` INT UNSIGNED,
  `entry_date`  DATE NOT NULL,
  `amount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notes`       TEXT,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`activity_id`) REFERENCES `financial_activities`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PARENT THREADS (private parent/teacher conversations)
-- ============================================================
CREATE TABLE `parent_threads` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `child_id`       INT UNSIGNED NOT NULL,
  `parent_user_id` INT UNSIGNED NOT NULL,
  `group_id`       INT UNSIGNED NOT NULL,
  `subject`        VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`child_id`)       REFERENCES `children`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`group_id`)       REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- read_at records when the recipient read the message.
CREATE TABLE `parent_thread_messages` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `thread_id`  INT UNSIGNED NOT NULL,
  `sender_id`  INT UNSIGNED NOT NULL,
  `body`       TEXT NOT NULL,
  `read_at`    DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`thread_id`) REFERENCES `parent_threads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- GENERIC DEFAULTS ONLY (no users, children, groups or school data)
-- ============================================================

-- Default parameters
INSERT INTO `parameters` (`param_key`, `param_value`) VALUES
('email_mode', 'virtual'),
('school_name', 'Το σχολείο μου'),
('school_logo', ''),
('photo_hidden_grace_days', '15'),
('photo_retention_months', '6'),
('photo_retention_days', '180');

-- Default email template
INSERT INTO `email_template` (`template_html`, `subject`) VALUES
('<table style="width:600px;font-family:Arial,sans-serif;border-collapse:collapse;">
  <tr>
    <td style="background:#4a7ab5;padding:15px;color:#fff;">
      <h2 style="margin:0;">Καθημερινή Δραστηριότητα</h2>
    </td>
  </tr>
  <tr>
    <td style="padding:15px;">
      <p>Παρακάτω θα βρείτε πληροφορίες σχετικά με την δραστηριότητα του παιδιού σας για σήμερα.</p>
      <table style="width:100%;border:1px solid #ccc;border-collapse:collapse;">
        <tr><td style="padding:6px;background:#eef3fb;font-weight:bold;width:40%;">Όνομα:</td><td style="padding:6px;">|*name*|</td></tr>
        <tr><td style="padding:6px;background:#eef3fb;font-weight:bold;">Πρωινό:</td><td style="padding:6px;">|*breakfast*|</td></tr>
        <tr><td style="padding:6px;background:#eef3fb;font-weight:bold;">Μεσημεριανό:</td><td style="padding:6px;">|*lunch*|</td></tr>
        <tr><td style="padding:6px;background:#eef3fb;font-weight:bold;">Διάθεση:</td><td style="padding:6px;">|*mood*|</td></tr>
        <tr><td style="padding:6px;background:#eef3fb;font-weight:bold;">Ύπνος:</td><td style="padding:6px;">|*sleep*| λεπτά</td></tr>
        <tr><td style="padding:6px;background:#eef3fb;font-weight:bold;">WC:</td><td style="padding:6px;">|*WC_txt*|</td></tr>
        <tr><td style="padding:6px;background:#eef3fb;font-weight:bold;">Δραστηριότητες:</td><td style="padding:6px;">|*activity*|</td></tr>
        <tr><td style="padding:6px;background:#eef3fb;font-weight:bold;">Παρατηρήσεις:</td><td style="padding:6px;">|*comments*|</td></tr>
      </table>
      <br>
      <table style="width:100%;text-align:center;border:1px solid #ccc;">
        <tr>
          <td style="padding:6px;background:#5cb85c;color:#fff;">4 Πολύ Καλά</td>
          <td style="padding:6px;background:#5bc0de;color:#fff;">3 Καλά</td>
          <td style="padding:6px;background:#f0ad4e;color:#fff;">2 Μέτρια</td>
          <td style="padding:6px;background:#d9534f;color:#fff;">1 Καθόλου</td>
        </tr>
      </table>
      <p style="margin-top:15px;">Με φιλικούς χαιρετισμούς,</p>
    </td>
  </tr>
</table>',
'Καθημερινή Δραστηριότητα - |*name*|');

-- Sample activities
INSERT INTO `activities` (`name`, `type`, `sort_order`) VALUES
('ΚΟΥΚΛΟΘΕΑΤΡΟ', 'both', 1),
('ΘΕΑΤΡΙΚΟ ΠΑΙΧΝΙΔΙ', 'both', 2),
('ΑΓΓΛΙΚΑ', 'both', 3),
('ΜΟΥΣΙΚΟΚ. ΑΓΩΓΗ', 'both', 4),
('ΡΟΜΠΟΤΙΚΗ', 'both', 5),
('ΚΗΠΟΣ', 'both', 6),
('ΕΛΕΥΘ.ΠΑΙΧΝΙΔΙ - ΓΩΝΙΕΣ', 'both', 7),
('ΣΥΝΑΙΣΘ. ΑΓΩΓΗ', 'both', 8),
('ΜΟΥΣΕΙΟΣΚΕΥΗ', 'both', 9),
('ΣΥΖΗΤΗΣΗ/ΚΥΚΛΟΣ', 'both', 10),
('ΠΑΡΑΜΥΘΙ', 'both', 11),
('ΕΚΠ. ΕΚΔΡΟΜΗ', 'both', 12),
('ΦΥΛΛΑ ΕΡΓΑΣΙΑΣ', 'both', 13),
('ΓΛΩΣΣΑ', 'both', 14),
('ΜΑΘΗΜΑΤΙΚΑ', 'both', 15);
