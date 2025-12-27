<?php
/**
 * Employee Sidebar Navigation
 * Employee Management System
 */

$currentPage = basename($_SERVER['PHP_SELF']);

// Check if employee requires check-in
$employeeCheckinRequired = fetchOne(
    "SELECT requires_checkin FROM employees WHERE id = ?",
    "i",
    [$_SESSION['user_id']]
)['requires_checkin'] ?? 1;
?>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay"></div>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo url('employee/dashboard'); ?>" class="sidebar-logo">
            <img src="<?php echo APP_URL; ?>/assets/img/clientura-logo.png" alt="<?php echo APP_NAME; ?>" style="height: 40px; object-fit: contain;">
            <span><?php echo APP_NAME; ?></span>
        </a>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo url('employee/dashboard'); ?>" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <?php if ($employeeCheckinRequired): ?>
        <li class="sidebar-section-title">Attendance</li>
        
        <li>
            <a href="<?php echo url('employee/checkin'); ?>" class="<?php echo $currentPage === 'checkin.php' ? 'active' : ''; ?>">
                <i class="bi bi-box-arrow-in-right"></i>
                <span>Check In/Out</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('employee/attendance'); ?>" class="<?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check"></i>
                <span>My Attendance</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li class="sidebar-section-title">Work</li>
        
        <li>
            <a href="<?php echo url('employee/tasks'); ?>" class="<?php echo $currentPage === 'tasks.php' ? 'active' : ''; ?>">
                <i class="bi bi-list-task"></i>
                <span>My Tasks</span>
                <?php
                $pendingTasks = fetchOne(
                    "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status IN ('not_started', 'in_progress')",
                    "i",
                    [$_SESSION['user_id']]
                )['count'] ?? 0;
                if ($pendingTasks > 0):
                ?>
                <span class="badge bg-primary ms-auto"><?php echo $pendingTasks; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <li class="sidebar-section-title">Account</li>
        
        <li>
            <a href="<?php echo url('employee/notifications'); ?>" class="<?php echo $currentPage === 'notifications.php' ? 'active' : ''; ?>">
                <i class="bi bi-bell"></i>
                <span>Notifications</span>
                <?php
                $unreadCount = fetchOne(
                    "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND user_type = 'employee' AND is_read = 0",
                    "i",
                    [$_SESSION['user_id']]
                )['count'] ?? 0;
                if ($unreadCount > 0):
                ?>
                <span class="badge bg-danger ms-auto"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="<?php echo url('employee/profile'); ?>" class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-circle"></i>
                <span>Profile</span>
            </a>
        </li>
        
        <div class="sidebar-divider"></div>
        
        <li>
            <a href="<?php echo url('logout'); ?>" class="text-danger">
                <i class="bi bi-box-arrow-left"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>
