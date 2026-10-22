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
        <a href="<?php echo url('teamlead/dashboard'); ?>" class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <img src="<?php echo APP_URL; ?>/assets/img/clientura-logo.png" alt="<?php echo APP_NAME; ?>">
            </div>
            <div class="sidebar-brand-text">
                <div class="brand-title">Clientura EMS</div>
                <div class="brand-subtitle">Team Lead Portal</div>
            </div>
        </a>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo url('teamlead/dashboard'); ?>" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('dashboard'); ?>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li>
            <a href="<?php echo url('teamlead/team'); ?>" class="<?php echo $currentPage === 'team.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('users'); ?>
                <span>Team Members</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('teamlead/team-attendance'); ?>" class="<?php echo $currentPage === 'team-attendance.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('calendar'); ?>
                <span>Team Attendance</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('teamlead/approvals'); ?>" class="<?php echo $currentPage === 'approvals.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('check-circle'); ?>
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
            <a href="<?php echo url('teamlead/team-tasks'); ?>" class="<?php echo $currentPage === 'team-tasks.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('tasks'); ?>
                <span>Team Tasks</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('teamlead/team-reports'); ?>" class="<?php echo $currentPage === 'team-reports.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('reports'); ?>
                <span>Team Reports</span>
            </a>
        </li>
        
        <?php if ($teamLeadCheckinRequired): ?>
        <li>
            <a href="<?php echo url('teamlead/my-attendance'); ?>" class="<?php echo $currentPage === 'my-attendance.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('calendar'); ?>
                <span>My Attendance</span>
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="<?php echo url('teamlead/my-tasks'); ?>" class="<?php echo $currentPage === 'my-tasks.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('tasks'); ?>
                <span>My Tasks</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('teamlead/profile'); ?>" class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
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
