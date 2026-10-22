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
        <a href="<?php echo url('employee/dashboard'); ?>" class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <img src="<?php echo APP_URL; ?>/assets/img/clientura-logo.png" alt="<?php echo APP_NAME; ?>">
            </div>
            <div class="sidebar-brand-text">
                <div class="brand-title">Clientura EMS</div>
                <div class="brand-subtitle">Employee Portal</div>
            </div>
        </a>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo url('employee/dashboard'); ?>" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('dashboard'); ?>
                <span>Dashboard</span>
            </a>
        </li>
        
        <?php if ($employeeCheckinRequired): ?>
        <li>
            <a href="<?php echo url('employee/checkin'); ?>" class="<?php echo $currentPage === 'checkin.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('check-circle'); ?>
                <span>Check In/Out</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('employee/attendance'); ?>" class="<?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('calendar'); ?>
                <span>My Attendance</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li>
            <a href="<?php echo url('employee/tasks'); ?>" class="<?php echo $currentPage === 'tasks.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('tasks'); ?>
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
        <li>
            <a href="<?php echo url('employee/my-documents'); ?>" class="<?php echo $currentPage === 'my-documents.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('certificate'); ?>
                <span>My Documents</span>
            </a>
        </li>
        
        <li>
            <a href="<?php echo url('employee/notifications'); ?>" class="<?php echo $currentPage === 'notifications.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('bell'); ?>
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
                <?php echo pika_icon('profile'); ?>
                <span>Profile</span>
            </a>
        </li>
        
        <div class="sidebar-divider"></div>
        
        <li>
            <a href="<?php echo url('logout'); ?>" class="sidebar-logout-link">
                <?php echo pika_icon('logout'); ?>
                <span>Sign Out</span>
            </a>
        </li>
    </ul>
</aside>
