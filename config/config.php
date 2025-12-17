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

// Session timeout (2 hours in seconds)
define('SESSION_TIMEOUT', 7200);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Include database
require_once __DIR__ . '/database.php';

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
            header("Location: " . APP_URL . "/login.php?timeout=1");
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
        header("Location: " . APP_URL . "/login.php");
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
        header("Location: " . APP_URL . "/employee/dashboard.php");
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
    if ($avatar && file_exists(UPLOAD_PATH . 'avatars/' . $avatar)) {
        return APP_URL . '/uploads/avatars/' . $avatar;
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
?>
