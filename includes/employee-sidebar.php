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
            <a href="<?php echo url('logout'); ?>" class="text-danger">
                <?php echo pika_icon('logout'); ?>
                <span>Logout</span>
            </a>
        </li>
    </ul>

    <!-- Inset User Profile at Sidebar Bottom -->
    <div class="sidebar-user-card">
        <img src="<?php echo !empty($_SESSION['avatar']) ? url('uploads/avatars/' . $_SESSION['avatar']) : url('assets/img/default-avatar.png'); ?>" alt="Avatar" class="sidebar-user-avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'Employee'); ?>&background=0A1138&color=FFA500';">
        <div class="sidebar-user-details">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Employee'); ?></div>
            <div class="sidebar-user-role"><?php echo htmlspecialchars($_SESSION['user_role_name'] ?? 'Team Member'); ?></div>
        </div>
    </div>
</aside>
