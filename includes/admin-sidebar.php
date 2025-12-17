<?php
/**
 * Admin Sidebar Navigation
 * Employee Management System
 */

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay"></div>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="sidebar-logo">
            <img src="<?php echo APP_URL; ?>/assets/img/clientura-logo.png" alt="<?php echo APP_NAME; ?>" style="height: 40px; object-fit: contain;">
            <span><?php echo APP_NAME; ?></span>
        </a>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li class="sidebar-section-title">Management</li>
        
        <li>
            <a href="<?php echo APP_URL; ?>/admin/employees.php" class="<?php echo $currentPage === 'employees.php' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i>
                <span>Employees</span>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/admin/domains.php" class="<?php echo $currentPage === 'domains.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid"></i>
                <span>Domains</span>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/admin/attendance.php" class="<?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check"></i>
                <span>Attendance</span>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/admin/approvals.php" class="<?php echo $currentPage === 'approvals.php' ? 'active' : ''; ?>">
                <i class="bi bi-check-circle"></i>
                <span>Approvals</span>
                <?php
                $pendingCount = fetchOne("SELECT COUNT(*) as count FROM attendance WHERE status = 'pending'")['count'] ?? 0;
                if ($pendingCount > 0):
                ?>
                <span class="badge bg-danger ms-auto"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/admin/tasks.php" class="<?php echo $currentPage === 'tasks.php' ? 'active' : ''; ?>">
                <i class="bi bi-list-task"></i>
                <span>Tasks</span>
            </a>
        </li>
        
        <li class="sidebar-section-title">Reports</li>
        
        <li>
            <a href="<?php echo APP_URL; ?>/admin/reports.php" class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Reports</span>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/admin/activity-logs.php" class="<?php echo $currentPage === 'activity-logs.php' ? 'active' : ''; ?>">
                <i class="bi bi-clock-history"></i>
                <span>Activity Logs</span>
            </a>
        </li>
        
        <li class="sidebar-section-title">Settings</li>
        
        <li>
            <a href="<?php echo APP_URL; ?>/admin/settings.php" class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/admin/profile.php" class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-circle"></i>
                <span>Profile</span>
            </a>
        </li>
        
        <div class="sidebar-divider"></div>
        
        <li>
            <a href="<?php echo APP_URL; ?>/logout.php" class="text-danger">
                <i class="bi bi-box-arrow-left"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>
