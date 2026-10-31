-- Migration: Add Exit Feedback & Intern Registration Features
-- Safe for Hostinger Production & Local XAMPP (CREATE TABLE IF NOT EXISTS)

-- 1. Exit Feedbacks Table
CREATE TABLE IF NOT EXISTS `exit_feedbacks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `overall_rating` TINYINT NOT NULL DEFAULT 5,
  `mentorship_rating` TINYINT NOT NULL DEFAULT 5,
  `culture_rating` TINYINT NOT NULL DEFAULT 5,
  `learning_rating` TINYINT NOT NULL DEFAULT 5,
  `management_rating` TINYINT NOT NULL DEFAULT 5,
  `recommend_score` TINYINT NOT NULL DEFAULT 10,
  `key_learnings` TEXT DEFAULT NULL,
  `positive_highlights` TEXT DEFAULT NULL,
  `improvement_suggestions` TEXT DEFAULT NULL,
  `reason_for_leaving` VARCHAR(255) DEFAULT NULL,
  `personal_email` VARCHAR(150) DEFAULT NULL,
  `personal_phone` VARCHAR(30) DEFAULT NULL,
  `linkedin_profile` VARCHAR(255) DEFAULT NULL,
  `future_contact_consent` TINYINT(1) DEFAULT 1,
  `status` ENUM('submitted', 'reviewed') DEFAULT 'submitted',
  `reviewed_by` INT DEFAULT NULL,
  `admin_remarks` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`employee_id`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Intern Registrations Table
CREATE TABLE IF NOT EXISTS `intern_registrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration_id` VARCHAR(50) NOT NULL UNIQUE,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `college_name` VARCHAR(200) NOT NULL,
  `degree_branch` VARCHAR(150) NOT NULL,
  `graduation_year` VARCHAR(10) NOT NULL,
  `cgpa_percentage` VARCHAR(20) DEFAULT NULL,
  `domain_id` INT DEFAULT NULL,
  `other_domain` VARCHAR(150) DEFAULT NULL,
  `preferred_duration` VARCHAR(50) NOT NULL,
  `start_date` DATE DEFAULT NULL,
  `internship_mode` ENUM('remote', 'hybrid', 'onsite') DEFAULT 'hybrid',
  `linkedin_url` VARCHAR(255) DEFAULT NULL,
  `github_portfolio_url` VARCHAR(255) DEFAULT NULL,
  `resume_path` VARCHAR(255) DEFAULT NULL,
  `cover_note` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'under_review', 'approved', 'rejected') DEFAULT 'pending',
  `converted_employee_id` INT DEFAULT NULL,
  `admin_remarks` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`registration_id`),
  INDEX (`email`),
  INDEX (`domain_id`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Update existing legacy static "Six months" in certificates to true elapsed tenure
UPDATE `certificates` 
SET `duration_text` = CONCAT(
  GREATEST(1, ROUND(DATEDIFF(end_date, start_date) / 30.44)),
  IF(ROUND(DATEDIFF(end_date, start_date) / 30.44) <= 1, ' Month', ' Months')
)
WHERE `duration_text` = 'Six months' AND `start_date` IS NOT NULL AND `end_date` IS NOT NULL;

-- 4. Ensure other_domain column exists if table was already created
ALTER TABLE `intern_registrations` ADD COLUMN IF NOT EXISTS `other_domain` VARCHAR(150) DEFAULT NULL AFTER `domain_id`;

-- 5. Update domain tracks (Replace AD and HR with Software Developer Intern and Video Editors)
UPDATE `domains` SET `name` = 'Software Developer Intern', `code` = 'SDE', `description` = 'Software development and engineering internship track' WHERE `code` = 'AD' OR `id` = 2;
UPDATE `domains` SET `name` = 'Video Editors', `code` = 'VE', `description` = 'Video editing and multimedia content production track' WHERE `code` = 'HR' OR `id` = 5;


