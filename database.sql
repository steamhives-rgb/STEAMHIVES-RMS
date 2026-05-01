-- ============================================================
-- STEAMhives RMS v3.0 — MySQL Database Schema
-- Import this into cPanel phpMyAdmin or via SSH:
--   mysql -u user -p dbname < database.sql
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================
-- COUPONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `sh_coupons` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`          VARCHAR(80)  NOT NULL UNIQUE,
  `type`          VARCHAR(30)  NOT NULL DEFAULT 'unlimited',
  `student_limit` INT          NULL COMMENT 'NULL = unlimited',
  `plan_label`    VARCHAR(60)  NOT NULL DEFAULT 'Unlimited Students',
  `used`          TINYINT(1)   NOT NULL DEFAULT 0,
  `used_by`       VARCHAR(40)  NULL,
  `used_by_name`  VARCHAR(200) NULL,
  `generated_by`  VARCHAR(40)  NULL,
  `generated_date` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_date`     DATETIME     NULL,
  INDEX `idx_code` (`code`),
  INDEX `idx_used` (`used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SCHOOLS
-- ============================================================
CREATE TABLE IF NOT EXISTS `sh_schools` (
  `id`            VARCHAR(40)  NOT NULL PRIMARY KEY,
  `name`          VARCHAR(200) NOT NULL,
  `pass_hash`     VARCHAR(40)  NOT NULL,
  `coupon_code`   VARCHAR(80)  NULL,
  `student_limit` INT          NULL COMMENT 'NULL = unlimited',
  `plan_label`    VARCHAR(60)  NOT NULL DEFAULT 'Unlimited Students',
  `created_date`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SCHOOL SETTINGS  (logo, colours, signatures stored as mediumtext/base64)
-- ============================================================
CREATE TABLE IF NOT EXISTS `sh_settings` (
  `school_id`       VARCHAR(40)  NOT NULL PRIMARY KEY,
  `name`            VARCHAR(200) NULL,
  `motto`           VARCHAR(300) NULL,
  `address`         TEXT         NULL,
  `phone`           VARCHAR(30)  NULL,
  `email`           VARCHAR(120) NULL,
  `color1`          VARCHAR(10)  NOT NULL DEFAULT '#0a6640',
  `color2`          VARCHAR(10)  NOT NULL DEFAULT '#c8960c',
  `school_logo`     MEDIUMTEXT   NULL,
  `sig_principal`   MEDIUMTEXT   NULL,
  `head_signature`  MEDIUMTEXT   NULL,
  `school_stamp`    MEDIUMTEXT   NULL,
  `class_teachers`  MEDIUMTEXT   NULL COMMENT 'JSON',
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`school_id`) REFERENCES `sh_schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STUDENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `sh_students` (
  `id`          VARCHAR(40)  NOT NULL PRIMARY KEY,
  `school_id`   VARCHAR(40)  NOT NULL,
  `adm`         VARCHAR(40)  NOT NULL,
  `name`        VARCHAR(200) NOT NULL,
  `gender`      VARCHAR(10)  NULL,
  `level`       VARCHAR(50)  NULL,
  `class`       VARCHAR(60)  NULL,
  `department`  VARCHAR(60)  NULL,
  `arm`         VARCHAR(20)  NULL,
  `term`        VARCHAR(20)  NULL,
  `session`     VARCHAR(20)  NULL,
  `dob`         DATE         NULL,
  `house`       VARCHAR(60)  NULL,
  `passport`    MEDIUMTEXT   NULL COMMENT 'base64 image',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_school_adm` (`school_id`, `adm`),
  INDEX `idx_class` (`school_id`, `class`),
  INDEX `idx_term` (`school_id`, `term`, `session`),
  FOREIGN KEY (`school_id`) REFERENCES `sh_schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- RESULTS  (full-term)
-- ============================================================
CREATE TABLE IF NOT EXISTS `sh_results` (
  `id`               VARCHAR(40) NOT NULL PRIMARY KEY,
  `school_id`        VARCHAR(40) NOT NULL,
  `student_id`       VARCHAR(40) NOT NULL,
  `student_adm`      VARCHAR(40) NULL,
  `student_name`     VARCHAR(200) NULL,
  `class`            VARCHAR(60) NULL,
  `term`             VARCHAR(20) NULL,
  `session`          VARCHAR(20) NULL,
  `subjects`         MEDIUMTEXT  NULL COMMENT 'JSON array',
  `overall_total`    DECIMAL(8,2) NULL,
  `avg`              DECIMAL(6,2) NULL,
  `grade`            VARCHAR(5)  NULL,
  `position`         INT         NULL,
  `out_of`           INT         NULL,
  `affective`        TEXT        NULL COMMENT 'JSON',
  `teacher_name`     VARCHAR(200) NULL,
  `teacher_signature` MEDIUMTEXT NULL,
  `teacher_comment`  TEXT        NULL,
  `principal_comment` TEXT       NULL,
  `student_passport` MEDIUMTEXT  NULL,
  `sig_principal`    MEDIUMTEXT  NULL,
  `result_date`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_result` (`school_id`, `student_id`, `term`, `session`),
  INDEX `idx_class_term` (`school_id`, `class`, `term`, `session`),
  FOREIGN KEY (`school_id`) REFERENCES `sh_schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MID-TERM RESULTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `sh_midterm_results` (
  `id`               VARCHAR(60) NOT NULL PRIMARY KEY,
  `school_id`        VARCHAR(40) NOT NULL,
  `student_id`       VARCHAR(40) NOT NULL,
  `student_adm`      VARCHAR(40) NULL,
  `student_name`     VARCHAR(200) NULL,
  `class`            VARCHAR(60) NULL,
  `term`             VARCHAR(20) NULL,
  `session`          VARCHAR(20) NULL,
  `subjects`         MEDIUMTEXT  NULL COMMENT 'JSON array',
  `overall_total`    DECIMAL(8,2) NULL,
  `avg`              DECIMAL(6,2) NULL,
  `grade`            VARCHAR(5)  NULL,
  `affective`        TEXT        NULL COMMENT 'JSON',
  `teacher_comment`  TEXT        NULL,
  `principal_comment` TEXT       NULL,
  `student_passport` MEDIUMTEXT  NULL,
  `sig_principal`    MEDIUMTEXT  NULL,
  `result_date`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_mid` (`school_id`, `student_id`, `term`, `session`),
  INDEX `idx_mid_class` (`school_id`, `class`, `term`, `session`),
  FOREIGN KEY (`school_id`) REFERENCES `sh_schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ATTENDANCE  (per-week, per-student, per-day)
-- ============================================================
CREATE TABLE IF NOT EXISTS `sh_attendance` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `school_id`  VARCHAR(40) NOT NULL,
  `student_id` VARCHAR(40) NOT NULL,
  `term`       VARCHAR(20) NOT NULL,
  `session`    VARCHAR(20) NOT NULL,
  `week`       VARCHAR(20) NOT NULL,
  `monday`     VARCHAR(10) NULL,
  `tuesday`    VARCHAR(10) NULL,
  `wednesday`  VARCHAR(10) NULL,
  `thursday`   VARCHAR(10) NULL,
  `friday`     VARCHAR(10) NULL,
  `updated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_att` (`school_id`, `student_id`, `term`, `session`, `week`),
  INDEX `idx_att_class` (`school_id`, `term`, `session`, `week`),
  FOREIGN KEY (`school_id`) REFERENCES `sh_schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- RESULT ACCESS PINS
-- ============================================================
CREATE TABLE IF NOT EXISTS `sh_result_pins` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `pin`          VARCHAR(60)  NOT NULL UNIQUE,
  `school_id`    VARCHAR(40)  NOT NULL,
  `student_id`   VARCHAR(40)  NOT NULL,
  `duration`     VARCHAR(20)  NOT NULL DEFAULT 'term',
  `result_type`  VARCHAR(20)  NOT NULL DEFAULT 'both',
  `term`         VARCHAR(20)  NULL,
  `specific_term` VARCHAR(20) NULL,
  `cost`         INT          NOT NULL DEFAULT 500,
  `used`         TINYINT(1)   NOT NULL DEFAULT 0,
  `revoked`      TINYINT(1)   NOT NULL DEFAULT 0,
  `used_date`    DATETIME     NULL,
  `generated_date` DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pin` (`pin`),
  INDEX `idx_student_pin` (`school_id`, `student_id`),
  FOREIGN KEY (`school_id`) REFERENCES `sh_schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AUDIT LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS `sh_audit_log` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `school_id`  VARCHAR(40)  NULL,
  `action`     VARCHAR(100) NOT NULL,
  `details`    TEXT         NULL,
  `ip`         VARCHAR(45)  NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_log_school` (`school_id`),
  INDEX `idx_log_date`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
