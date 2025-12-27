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
            header("Location: " . url('admin/dashboard'));
            break;
        case 'manager':
            header("Location: " . url('manager/dashboard'));
            break;
        case 'team_lead':
            header("Location: " . url('teamlead/dashboard'));
            break;
        default:
            header("Location: " . url('employee/dashboard'));
    }
    exit;
}

// Show landing page for guests
include __DIR__ . '/home.php';
?>
