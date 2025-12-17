<?php
/**
 * Team Lead Sidebar Navigation
 * Employee Management System
 */

$currentPage = basename($_SERVER['PHP_SELF']);

// Check if current user requires check-in
$teamLeadCheckinRequired = fetchOne(
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
        <a href="<?php echo APP_URL; ?>/teamlead/dashboard.php" class="sidebar-logo">
            <img src="<?php echo APP_URL; ?>/assets/img/clientura-logo.png" alt="<?php echo APP_NAME; ?>" style="height: 40px; object-fit: contain;">
            <span><?php echo APP_NAME; ?></span>
        </a>
    </div>
    
    <div class="sidebar-user-info px-3 py-2 mb-2">
        <div class="d-flex align-items-center">
            <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" class="avatar avatar-sm me-2" alt="">
            <div>
                <div class="fw-semibold text-white small"><?php echo $_SESSION['user_name'] ?? 'User'; ?></div>
                <span class="badge bg-warning text-dark small">Team Lead</span>
            </div>
        </div>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo APP_URL; ?>/teamlead/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li class="sidebar-section-title">My Team</li>
        
        <li>
            <a href="<?php echo APP_URL; ?>/teamlead/team.php" class="<?php echo $currentPage === 'team.php' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i>
                <span>Team Members</span>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/teamlead/team-attendance.php" class="<?php echo $currentPage === 'team-attendance.php' ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check"></i>
                <span>Team Attendance</span>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/teamlead/approvals.php" class="<?php echo $currentPage === 'approvals.php' ? 'active' : ''; ?>">
                <i class="bi bi-check-circle"></i>
                <span>Approvals</span>
                <?php
                $teamMemberIds = getTeamMemberIds($_SESSION['user_id']);
                $pendingCount = 0;
                if (!empty($teamMemberIds)) {
                    $placeholders = implode(',', array_fill(0, count($teamMemberIds), '?'));
                    $pendingCount = fetchOne(
                        "SELECT COUNT(*) as count FROM attendance WHERE status = 'pending' AND employee_id IN ($placeholders)",
                        str_repeat('i', count($teamMemberIds)),
                        $teamMemberIds
                    )['count'] ?? 0;
                }
                if ($pendingCount > 0):
                ?>
                <span class="badge bg-danger ms-auto"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/teamlead/team-tasks.php" class="<?php echo $currentPage === 'team-tasks.php' ? 'active' : ''; ?>">
                <i class="bi bi-list-task"></i>
                <span>Team Tasks</span>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/teamlead/team-reports.php" class="<?php echo $currentPage === 'team-reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Team Reports</span>
            </a>
        </li>
        
        <li class="sidebar-section-title">My Account</li>
        
        <?php if ($teamLeadCheckinRequired): ?>
        <li>
            <a href="<?php echo APP_URL; ?>/teamlead/my-attendance.php" class="<?php echo $currentPage === 'my-attendance.php' ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check"></i>
                <span>My Attendance</span>
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="<?php echo APP_URL; ?>/teamlead/my-tasks.php" class="<?php echo $currentPage === 'my-tasks.php' ? 'active' : ''; ?>">
                <i class="bi bi-check2-square"></i>
                <span>My Tasks</span>
            </a>
        </li>
        <li>
            <a href="<?php echo APP_URL; ?>/teamlead/profile.php" class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
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
