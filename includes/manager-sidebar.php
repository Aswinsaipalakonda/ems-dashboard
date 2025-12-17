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
        <a href="<?php echo APP_URL; ?>/manager/dashboard.php" class="sidebar-logo">
            <img src="<?php echo APP_URL; ?>/assets/img/clientura-logo.png" alt="<?php echo APP_NAME; ?>" style="height: 40px; object-fit: contain;">
            <span><?php echo APP_NAME; ?></span>
        </a>
    </div>
    
    <div class="sidebar-user-info px-3 py-2 mb-2">
        <div class="d-flex align-items-center">
            <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" class="avatar avatar-sm me-2" alt="">
            <div>
                <div class="fw-semibold text-white small"><?php echo $_SESSION['user_name'] ?? 'User'; ?></div>
                <span class="badge bg-info small"><?php echo $roleName; ?></span>
            </div>
        </div>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo APP_URL; ?>/manager/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li class="sidebar-section-title">Management</li>
        
        <li>
            <a href="<?php echo APP_URL; ?>/manager/employees.php" class="<?php echo $currentPage === 'employees.php' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i>
                <span>Employees</span>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/manager/attendance.php" class="<?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check"></i>
                <span>Attendance</span>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/manager/approvals.php" class="<?php echo $currentPage === 'approvals.php' ? 'active' : ''; ?>">
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
            <a href="<?php echo APP_URL; ?>/manager/tasks.php" class="<?php echo $currentPage === 'tasks.php' ? 'active' : ''; ?>">
                <i class="bi bi-list-task"></i>
                <span>Tasks</span>
            </a>
        </li>
        
        <li class="sidebar-section-title">Reports</li>
        
        <li>
            <a href="<?php echo APP_URL; ?>/manager/reports.php" class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Reports</span>
            </a>
        </li>
        
        <li class="sidebar-section-title">My Account</li>
        
        <?php if ($managerCheckinRequired): ?>
        <li>
            <a href="<?php echo APP_URL; ?>/manager/my-attendance.php" class="<?php echo $currentPage === 'my-attendance.php' ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check"></i>
                <span>My Attendance</span>
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="<?php echo APP_URL; ?>/manager/my-tasks.php" class="<?php echo $currentPage === 'my-tasks.php' ? 'active' : ''; ?>">
                <i class="bi bi-check2-square"></i>
                <span>My Tasks</span>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/manager/profile.php" class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
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
