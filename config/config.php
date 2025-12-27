<?php
/**
 * Application Configuration
 * Employee Management System
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (enable for debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Application settings
define('APP_NAME', 'Clientura EMS');
define('APP_VERSION', '1.0.0');

// Auto-detect base URL
if (!defined('APP_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Get the document root relative path
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $scriptPath = str_replace('\\', '/', dirname(__DIR__)); // Get project root directory
    
    if (!empty($docRoot)) {
        $docRoot = str_replace('\\', '/', $docRoot);
        $relativePath = str_replace($docRoot, '', $scriptPath);
    } else {
        // Fallback: try to detect from SCRIPT_NAME
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $parts = explode('/', trim($scriptName, '/'));
        // Remove filename and go back to root
        array_pop($parts); // Remove current file
        // Find the project root (look for common folders)
        $projectRoot = '';
        foreach ($parts as $part) {
            if (in_array($part, ['admin', 'employee', 'manager', 'teamlead', 'config', 'includes'])) {
                break;
            }
            $projectRoot .= '/' . $part;
        }
        $relativePath = $projectRoot;
    }
    
    $baseUrl = $protocol . '://' . $host . rtrim($relativePath, '/');
    define('APP_URL', $baseUrl);
}

define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Ensure upload directories exist and are writable
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!file_exists(UPLOAD_PATH . 'avatars')) {
    mkdir(UPLOAD_PATH . 'avatars', 0755, true);
}
if (!file_exists(UPLOAD_PATH . 'checkin')) {
    mkdir(UPLOAD_PATH . 'checkin', 0755, true);
}
if (!file_exists(UPLOAD_PATH . 'tasks')) {
    mkdir(UPLOAD_PATH . 'tasks', 0755, true);
}

// Session timeout (2 hours in seconds)
define('SESSION_TIMEOUT', 7200);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Include database
require_once __DIR__ . '/database.php';

/**
 * Generate clean URL (without .php extension)
 * @param string $path The path with or without .php extension
 * @return string Clean URL without .php extension
 */
function url($path) {
    // Remove .php extension if present
    $cleanPath = preg_replace('/\.php$/', '', $path);
    return APP_URL . '/' . ltrim($cleanPath, '/');
}

/**
 * Redirect to a clean URL
 * @param string $path The path to redirect to
 * @param bool $permanent Whether to use 301 (permanent) or 302 (temporary) redirect
 */
function redirect($path, $permanent = false) {
    $cleanUrl = url($path);
    header("Location: " . $cleanUrl, true, $permanent ? 301 : 302);
    exit;
}

/**
 * Get asset URL (CSS, JS, images)
 */
function asset($path) {
    return APP_URL . '/' . ltrim($path, '/');
}

/**
 * Get upload URL
 */
function uploadUrl($path) {
    return APP_URL . '/uploads/' . ltrim($path, '/');
}

/**
 * Check session timeout and regenerate session ID
 */
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            header("Location: " . url('login') . "?timeout=1");
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is Manager
 */
function isManager() {
    return isset($_SESSION['user_role_slug']) && $_SESSION['user_role_slug'] === 'manager';
}

/**
 * Check if user is HR
 */
function isHR() {
    return isset($_SESSION['user_role_slug']) && $_SESSION['user_role_slug'] === 'hr';
}

/**
 * Check if user is Team Lead
 */
function isTeamLead() {
    return isset($_SESSION['user_role_slug']) && $_SESSION['user_role_slug'] === 'team_lead';
}

/**
 * Check if user has management access (Admin, Manager, HR)
 */
function hasManagementAccess() {
    return isAdmin() || isManager() || isHR();
}

/**
 * Check if user has team management access (Team Lead or higher)
 */
function hasTeamAccess() {
    return isAdmin() || isManager() || isHR() || isTeamLead();
}

/**
 * Check specific permission
 */
function hasPermission($permission) {
    if (isAdmin()) return true;
    
    $permissions = $_SESSION['user_permissions'] ?? '';
    $permArray = explode(',', $permissions);
    return in_array($permission, $permArray);
}

/**
 * Get team members for a Team Lead
 */
function getTeamMembers($teamLeadId) {
    return fetchAll(
        "SELECT e.*, d.name as domain_name, r.name as role_name 
         FROM employees e 
         LEFT JOIN domains d ON e.domain_id = d.id 
         LEFT JOIN roles r ON e.role_id = r.id 
         WHERE e.team_lead_id = ? AND e.status = 'active'",
        "i",
        [$teamLeadId]
    );
}

/**
 * Get team member IDs for a Team Lead
 */
function getTeamMemberIds($teamLeadId) {
    $members = fetchAll("SELECT id FROM employees WHERE team_lead_id = ?", "i", [$teamLeadId]);
    return array_column($members, 'id');
}

/**
 * Check if employee is in user's team (for Team Leads)
 */
function isInMyTeam($employeeId) {
    if (!isTeamLead()) return false;
    $teamLeadId = $_SESSION['user_id'];
    $member = fetchOne("SELECT id FROM employees WHERE id = ? AND team_lead_id = ?", "ii", [$employeeId, $teamLeadId]);
    return $member !== null;
}

/**
 * Get user role display name
 */
function getUserRoleName() {
    if (isAdmin()) return 'Administrator';
    return $_SESSION['user_role_name'] ?? 'Employee';
}

/**
 * Redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . url('login'));
        exit;
    }
    checkSessionTimeout();
}

/**
 * Redirect if not admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: " . url('employee/dashboard'));
        exit;
    }
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash message helper
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

/**
 * Format time for display
 */
function formatTime($time, $format = 'h:i A') {
    if (empty($time) || $time === '00:00:00' || $time === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    // If it looks like just a time (HH:MM:SS), use simple formatting
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return date($format, strtotime($time));
    }
    
    // For DATETIME fields stored in local timezone, just format as-is
    try {
        $dateTime = new DateTime($time);
        return $dateTime->format($format);
    } catch (Exception $e) {
        return $time;
    }
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = 'd M Y h:i A') {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    try {
        $dateTime = new DateTime($datetime);
        return $dateTime->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Get human-readable time ago string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

/**
 * Calculate time difference
 */
function calculateTimeDiff($start, $end) {
    $diff = strtotime($end) - strtotime($start);
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    return sprintf('%02d:%02d', $hours, $minutes);
}

/**
 * Get user avatar or default
 */
function getAvatar($avatar) {
    if ($avatar) {
        // Normalize the avatar path (remove any directory traversal, normalize slashes)
        $avatar = basename($avatar);
        $avatarPath = UPLOAD_PATH . 'avatars/' . $avatar;
        
        // Check if file exists
        if (file_exists($avatarPath) && is_file($avatarPath)) {
            // Add cache-busting parameter to force browser refresh
            $mtime = filemtime($avatarPath);
            return APP_URL . '/uploads/avatars/' . rawurlencode($avatar) . '?v=' . $mtime;
        }
    }
    return APP_URL . '/assets/img/default-avatar.svg';
}

/**
 * Format timestamp with proper timezone conversion
 * Converts UTC timestamps from database to application timezone
 */
function formatTimestamp($dbTimestamp, $format = 'M j, Y g:i A') {
    if (empty($dbTimestamp) || $dbTimestamp === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    // Create DateTime object from database timestamp (assumed to be UTC for TIMESTAMP fields)
    $dateTime = new DateTime($dbTimestamp, new DateTimeZone('UTC'));
    
    // Convert to application timezone
    $appTimezone = new DateTimeZone(date_default_timezone_get());
    $dateTime->setTimezone($appTimezone);
    
    return $dateTime->format($format);
}

/**
 * Log activity
 */
function logActivity($action, $description = '') {
    $userId = $_SESSION['user_id'] ?? 0;
    $userType = $_SESSION['role'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $sql = "INSERT INTO activity_logs (user_id, user_type, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    executeQuery($sql, "issss", [$userId, $userType, $action, $description, $ip]);
}

/**
 * Get system setting
 */
function getSetting($key, $default = null) {
    $setting = fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", "s", [$key]);
    return $setting ? $setting['setting_value'] : $default;
}

/**
 * Process automatic checkout for employees
 * This function checks all employees who have checked in today but not checked out
 * and automatically checks them out at the configured end time
 */
function processAutoCheckout() {
    // Check if auto-checkout is enabled
    $autoCheckoutEnabled = getSetting('auto_checkout_enabled', '0');
    if ($autoCheckoutEnabled !== '1') {
        return ['processed' => 0, 'message' => 'Auto-checkout is disabled'];
    }
    
    // Get working hours end time
    $workingHoursEnd = getSetting('working_hours_end', '18:00');
    
    // Get current time
    $currentTime = date('H:i');
    $currentDate = date('Y-m-d');
    
    // Only process if current time is past or equal to end time
    if ($currentTime < $workingHoursEnd) {
        return ['processed' => 0, 'message' => 'Not yet end time'];
    }
    
    // Find all attendance records for today where:
    // - Employee has checked in
    // - Employee has NOT checked out
    // - auto_checkout has not been done (check_out_time is NULL)
    $pendingCheckouts = fetchAll(
        "SELECT a.*, e.name as employee_name 
         FROM attendance a 
         JOIN employees e ON a.employee_id = e.id 
         WHERE a.date = ? 
         AND a.check_in_time IS NOT NULL 
         AND a.check_out_time IS NULL",
        "s",
        [$currentDate]
    );
    
    $processedCount = 0;
    $autoCheckoutTime = $currentDate . ' ' . $workingHoursEnd . ':00';
    
    foreach ($pendingCheckouts as $attendance) {
        // Calculate total hours
        $checkInTime = strtotime($attendance['check_in_time']);
        $checkOutTime = strtotime($autoCheckoutTime);
        $totalSeconds = $checkOutTime - $checkInTime;
        
        // Ensure positive total hours (in case check-in was after end time)
        if ($totalSeconds < 0) {
            $totalSeconds = 0;
        }
        
        $totalHours = round($totalSeconds / 3600, 2);
        
        // Update the attendance record with auto-checkout
        $sql = "UPDATE attendance SET 
                check_out_time = ?, 
                total_hours = ?,
                admin_remarks = CONCAT(IFNULL(admin_remarks, ''), ' [Auto-checkout at ', ?, ']')
                WHERE id = ?";
        executeQuery($sql, "sdsi", [$autoCheckoutTime, $totalHours, $workingHoursEnd, $attendance['id']]);
        
        // Create notification for the employee
        executeQuery(
            "INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'employee', ?, ?, 'info')",
            "iss",
            [
                $attendance['employee_id'], 
                'Automatic Check-out', 
                'You have been automatically checked out at ' . date('h:i A', strtotime($autoCheckoutTime)) . '. Total hours: ' . number_format($totalHours, 2)
            ]
        );
        
        $processedCount++;
    }
    
    return [
        'processed' => $processedCount, 
        'message' => $processedCount > 0 ? "Auto-checkout completed for $processedCount employee(s)" : 'No pending checkouts'
    ];
}

/**
 * Check and process auto-checkout (called on page loads)
 * This is throttled to run at most once per minute to avoid performance issues
 */
function checkAutoCheckout() {
    // Use a simple file-based throttle to avoid running too frequently
    $throttleFile = sys_get_temp_dir() . '/ems_auto_checkout_' . date('Y-m-d') . '.lock';
    $lastRun = file_exists($throttleFile) ? (int)file_get_contents($throttleFile) : 0;
    $currentMinute = (int)date('Hi'); // HHMM format
    
    // Only run if we haven't run this minute
    if ($lastRun >= $currentMinute) {
        return null;
    }
    
    // Update throttle file
    file_put_contents($throttleFile, $currentMinute);
    
    // Process auto-checkout
    return processAutoCheckout();
}
?>
