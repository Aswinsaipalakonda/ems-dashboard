-- Migration: Add Offer Letters, Certificates, and Signatories
-- Safe for Production (Hostinger) - Zero Data Loss (CREATE TABLE IF NOT EXISTS)

-- USE `ems_dashboard`; -- (Uncomment if needed, or select database in phpMyAdmin before running)

-- 1. Signatories Table
CREATE TABLE IF NOT EXISTS `signatories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `designation` VARCHAR(100) NOT NULL,
  `signature_path` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Certificates Table
CREATE TABLE IF NOT EXISTS `certificates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `certificate_id` VARCHAR(50) NOT NULL UNIQUE,
  `employee_id` INT NULL,
  `recipient_name` VARCHAR(100) NOT NULL,
  `program_name` VARCHAR(150) NOT NULL,
  `duration_text` VARCHAR(50) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `issue_date` DATE NOT NULL,
  `signatory_id` INT NOT NULL,
  `qr_code_path` VARCHAR(255) DEFAULT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `verification_token` VARCHAR(64) NOT NULL UNIQUE,
  `status` ENUM('active', 'revoked') DEFAULT 'active',
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`certificate_id`),
  INDEX (`employee_id`),
  INDEX (`verification_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Offer Letters Table
CREATE TABLE IF NOT EXISTS `offer_letters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `offer_id` VARCHAR(50) NOT NULL UNIQUE,
  `employee_id` INT NULL,
  `candidate_name` VARCHAR(100) NOT NULL,
  `candidate_email` VARCHAR(100) NOT NULL,
  `designation` VARCHAR(150) NOT NULL,
  `employment_type` ENUM('internship', 'fulltime', 'parttime') DEFAULT 'internship',
  `duration_text` VARCHAR(50) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `location` VARCHAR(100) DEFAULT 'Hybrid',
  `responsibilities` TEXT DEFAULT NULL,
  `terms_conditions` TEXT DEFAULT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `status` ENUM('issued', 'accepted', 'revoked') DEFAULT 'issued',
  `issued_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`offer_id`),
  INDEX (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Address column in employees table (Safe if not exists)
ALTER TABLE `employees` ADD COLUMN IF NOT EXISTS `address` TEXT DEFAULT NULL AFTER `phone`;

-- Default Signatories (Insert if not exists)
INSERT INTO `signatories` (`id`, `name`, `designation`, `signature_path`, `is_active`) VALUES
(1, 'Mr. P. Sowsheel', 'Founder & CEO Clientura', NULL, 1),
(2, 'Mr. P. Aswinsai', 'Co-Founder & CTO Clientura', NULL, 1),
(3, 'Mr. V. K. Viswanadh', 'Co-Founder & CMO Clientura', NULL, 1)
ON DUPLICATE KEY UPDATE `designation` = VALUES(`designation`);

