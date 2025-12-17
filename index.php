<?php
/**
 * Index Page - Landing page or redirect to dashboard
 * Employee Management System
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// If user is logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'employee';
    switch ($role) {
        case 'admin':
            header("Location: " . APP_URL . "/admin/dashboard.php");
            break;
        case 'manager':
            header("Location: " . APP_URL . "/manager/dashboard.php");
            break;
        case 'team_lead':
            header("Location: " . APP_URL . "/teamlead/dashboard.php");
            break;
        default:
            header("Location: " . APP_URL . "/employee/dashboard.php");
    }
    exit;
}

// Show landing page for guests
include __DIR__ . '/home.php';
?>
