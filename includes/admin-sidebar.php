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
        <a href="<?php echo url('admin/dashboard'); ?>" class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <img src="<?php echo APP_URL; ?>/assets/img/clientura-logo.png" alt="<?php echo APP_NAME; ?>">
            </div>
            <div class="sidebar-brand-text">
                <div class="brand-title">Clientura EMS</div>
                <div class="brand-subtitle">Employee Management</div>
            </div>
        </a>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo url('admin/dashboard'); ?>" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('dashboard'); ?>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li>
            <a href="<?php echo url('admin/employees'); ?>" class="<?php echo $currentPage === 'employees.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('users'); ?>
                <span>Employees</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('admin/domains'); ?>" class="<?php echo $currentPage === 'domains.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('domains'); ?>
                <span>Domains</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('admin/attendance'); ?>" class="<?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('calendar'); ?>
                <span>Attendance</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('admin/approvals'); ?>" class="<?php echo $currentPage === 'approvals.php' ? 'active' : ''; ?>">
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
            <a href="<?php echo url('admin/tasks'); ?>" class="<?php echo $currentPage === 'tasks.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('tasks'); ?>
                <span>Tasks</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('admin/offer-letters'); ?>" class="<?php echo in_array($currentPage, ['offer-letters.php', 'generate-offer.php']) ? 'active' : ''; ?>">
                <?php echo pika_icon('offer-letter'); ?>
                <span>Offer Letters</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('admin/certificates'); ?>" class="<?php echo in_array($currentPage, ['certificates.php', 'generate-certificate.php']) ? 'active' : ''; ?>">
                <?php echo pika_icon('certificate'); ?>
                <span>Certificates</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('admin/signatories'); ?>" class="<?php echo $currentPage === 'signatories.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('signature'); ?>
                <span>Signatories</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('admin/reports'); ?>" class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('reports'); ?>
                <span>Reports</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('admin/activity-logs'); ?>" class="<?php echo $currentPage === 'activity-logs.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('activity'); ?>
                <span>Activity Logs</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('admin/settings'); ?>" class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
                <?php echo pika_icon('settings'); ?>
                <span>Settings</span>
            </a>
        </li>
        <li>
            <a href="<?php echo url('admin/profile'); ?>" class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
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
