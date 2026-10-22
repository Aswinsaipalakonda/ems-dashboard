CREATE DATABASE IF NOT EXISTS `ems_dashboard` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ems_dashboard`;

CREATE TABLE IF NOT EXISTS `admin` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `permissions` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT NOT NULL,
  `permission` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `domains` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(10) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `domain_id` INT DEFAULT NULL,
  `designation` VARCHAR(100) DEFAULT NULL,
  `date_of_joining` DATE DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
  `role_id` INT DEFAULT NULL,
  `team_lead_id` INT DEFAULT NULL,
  `requires_checkin` TINYINT(1) DEFAULT 1,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `last_login` DATETIME DEFAULT NULL,
  `last_activity` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`domain_id`),
  INDEX (`role_id`),
  INDEX (`team_lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `check_in_time` DATETIME DEFAULT NULL,
  `check_out_time` DATETIME DEFAULT NULL,
  `check_in_photo` VARCHAR(255) DEFAULT NULL,
  `check_in_latitude` DECIMAL(10, 8) DEFAULT NULL,
  `check_in_longitude` DECIMAL(11, 8) DEFAULT NULL,
  `check_out_latitude` DECIMAL(10, 8) DEFAULT NULL,
  `check_out_longitude` DECIMAL(11, 8) DEFAULT NULL,
  `total_hours` VARCHAR(20) DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `admin_remarks` TEXT DEFAULT NULL,
  `approved_by` INT DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`employee_id`),
  INDEX (`date`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `assigned_to` INT NOT NULL,
  `assigned_by` INT NOT NULL,
  `priority` ENUM('low', 'medium', 'high', 'urgent', 'critical') DEFAULT 'medium',
  `status` ENUM('not_started', 'in_progress', 'submitted', 'completed', 'changes_requested', 'overdue') DEFAULT 'not_started',
  `deadline` DATE DEFAULT NULL,
  `attachment` VARCHAR(255) DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  `work_link` VARCHAR(255) DEFAULT NULL,
  `work_link_type` VARCHAR(50) DEFAULT NULL,
  `reviewer_feedback` TEXT DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`assigned_to`),
  INDEX (`assigned_by`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `user_type` ENUM('admin', 'employee') DEFAULT 'employee',
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` VARCHAR(50) DEFAULT 'info',
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`user_type`),
  INDEX (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `user_type` VARCHAR(50) DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `user_type` VARCHAR(50) NOT NULL,
  `login_time` DATETIME NOT NULL,
  `logout_time` DATETIME DEFAULT NULL,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`user_type`),
  INDEX (`login_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(100) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`email`),
  INDEX (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admin` (`id`, `name`, `email`, `password`) VALUES
(1, 'Administrator', 'admin@ems.com', '$2y$10$0FLHNLbOIawBqPJPK4tRR.rvJXeJTcLUc.QpTv2C29tKn5GcRiIOG')
ON DUPLICATE KEY UPDATE `email` = `email`;

INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `permissions`, `status`) VALUES
(1, 'Administrator', 'admin', 'Full system access', 'all', 'active'),
(2, 'Manager', 'manager', 'Department and employee management', 'view_attendance,approve_attendance,manage_tasks,view_reports,manage_employees', 'active'),
(3, 'HR', 'hr', 'Human resource management', 'view_attendance,approve_attendance,manage_tasks,view_reports,manage_employees', 'active'),
(4, 'Team Lead', 'team_lead', 'Team oversight and task management', 'view_attendance,approve_attendance,manage_tasks,view_team_reports', 'active'),
(5, 'Employee', 'employee', 'Standard employee access', 'view_attendance,mark_attendance,view_tasks', 'active')
ON DUPLICATE KEY UPDATE `slug` = `slug`;

INSERT INTO `domains` (`id`, `name`, `code`, `description`, `status`) VALUES
(1, 'Web Development', 'WD', 'Website and web application development', 'active'),
(2, 'App Development', 'AD', 'Mobile application development', 'active'),
(3, 'UI/UX Design', 'UI', 'User interface and experience design', 'active'),
(4, 'Quality Assurance', 'QA', 'Software testing and quality assurance', 'active'),
(5, 'Human Resources', 'HR', 'HR and talent management', 'active')
ON DUPLICATE KEY UPDATE `code` = `code`;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('company_name', 'Clientura EMS'),
('company_email', 'contact@clientura.in'),
('company_phone', '+91 9876543210'),
('company_address', 'Hyderabad, India'),
('working_hours_start', '09:00'),
('working_hours_end', '18:00'),
('late_threshold', '15'),
('auto_approve_attendance', '0'),
('auto_checkout_enabled', '0'),
('enable_geolocation', '1'),
('enable_photo_capture', '1')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

INSERT INTO `employees` (`id`, `employee_id`, `name`, `email`, `password`, `phone`, `domain_id`, `designation`, `date_of_joining`, `status`, `role_id`, `team_lead_id`, `requires_checkin`) VALUES
(1, 'WD-MGR-001', 'John Manager', 'manager@ems.com', '$2y$10$0FLHNLbOIawBqPJPK4tRR.rvJXeJTcLUc.QpTv2C29tKn5GcRiIOG', '+91 9876543211', 1, 'Manager', CURDATE(), 'active', 2, NULL, 0),
(2, 'WD-TL-001', 'Sarah Lead', 'teamlead@ems.com', '$2y$10$0FLHNLbOIawBqPJPK4tRR.rvJXeJTcLUc.QpTv2C29tKn5GcRiIOG', '+91 9876543212', 1, 'Team Lead', CURDATE(), 'active', 4, 1, 1),
(3, 'WD-EMP-001', 'Alex Developer', 'employee@ems.com', '$2y$10$0FLHNLbOIawBqPJPK4tRR.rvJXeJTcLUc.QpTv2C29tKn5GcRiIOG', '+91 9876543213', 1, 'Junior Developer', CURDATE(), 'active', 5, 2, 1)
ON DUPLICATE KEY UPDATE `email` = `email`;