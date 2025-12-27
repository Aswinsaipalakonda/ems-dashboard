<?php
/**
 * Logout Script
 * Employee Management System
 */

require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    // Log the logout
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'Logout', 'User logged out');
        
        // Update login log
        executeQuery(
            "UPDATE login_logs SET logout_time = NOW(), status = 'logged_out' WHERE user_id = ? AND user_type = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
            "is",
            [$_SESSION['user_id'], $_SESSION['role']]
        );
    }
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login
header("Location: " . url('login') . "?logout=1");
exit;
?>
