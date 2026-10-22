<?php
/**
 * Manager/HR Sidebar Navigation
 * Employee Management System
 */

$currentPage = basename($_SERVER['PHP_SELF']);
$userRoleSlug = $_SESSION['user_role_slug'] ?? 'manager';
$roleName = $_SESSION['user_role_name'] ?? 'Manager';

// Check if current user requires check-in
$managerCheckinRequired = fetchOne(
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
        <a href="<?php echo url('manager/dashboard'); ?>" class="sidebar-logo">
            <img src="<?php echo APP_URL; ?>/assets/img/clientura-logo.png" alt="<?php echo APP_NAME; ?>" style="height: 40px; object-fit: contain;">
            <span><?php echo APP_NAME; ?></span>
        </a>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo url('manager/dashboard'); ?>" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('dashboard'); ?>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li>
            <a href="<?php echo url('manager/employees'); ?>" class="<?php echo $currentPage === 'employees.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('users'); ?>
                <span>Employees</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('manager/attendance'); ?>" class="<?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('calendar'); ?>
                <span>Attendance</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('manager/approvals'); ?>" class="<?php echo $currentPage === 'approvals.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('check-circle'); ?>
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
            <a href="<?php echo url('manager/tasks'); ?>" class="<?php echo $currentPage === 'tasks.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('tasks'); ?>
                <span>Tasks</span>
            </a>
        </li>
        
        <li>
            <a href="<?php echo url('manager/reports'); ?>" class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('reports'); ?>
                <span>Reports</span>
            </a>
        </li>
        
        <?php if ($managerCheckinRequired): ?>
        <li>
            <a href="<?php echo url('manager/my-attendance'); ?>" class="<?php echo $currentPage === 'my-attendance.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('calendar'); ?>
                <span>My Attendance</span>
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="<?php echo url('manager/my-tasks'); ?>" class="<?php echo $currentPage === 'my-tasks.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('tasks'); ?>
                <span>My Tasks</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('manager/profile'); ?>" class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
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

    <!-- Bottom User Profile Card -->
    <div class="sidebar-user-card">
        <img src="<?php echo !empty($_SESSION['avatar']) ? url('uploads/avatars/' . $_SESSION['avatar']) : url('assets/img/default-avatar.png'); ?>" alt="Avatar" class="sidebar-user-avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'Manager'); ?>&background=0A1138&color=FFA500';">
        <div class="sidebar-user-details">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Manager'); ?></div>
            <div class="sidebar-user-role"><?php echo htmlspecialchars($roleName); ?></div>
        </div>
    </div>
</aside>
