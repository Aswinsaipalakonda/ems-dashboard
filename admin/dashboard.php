<?php
/**
 * Admin Dashboard
 * Employee Management System
 */

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Process auto-checkout if applicable
checkAutoCheckout();

// Get statistics
$totalEmployees = fetchOne("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")['count'] ?? 0;
$pendingApprovals = fetchOne("SELECT COUNT(*) as count FROM attendance WHERE status = 'pending'")['count'] ?? 0;
$todayAttendance = fetchOne("SELECT COUNT(*) as count FROM attendance WHERE date = CURDATE()")['count'] ?? 0;
$activeTasks = fetchOne("SELECT COUNT(*) as count FROM tasks WHERE status IN ('not_started', 'in_progress')")['count'] ?? 0;
$totalDomains = fetchOne("SELECT COUNT(*) as count FROM domains")['count'] ?? 0;
$completedTasks = fetchOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'completed'")['count'] ?? 0;

// Get recent pending approvals
$recentPending = fetchAll(
    "SELECT a.*, e.name, e.employee_id, e.avatar 
     FROM attendance a 
     JOIN employees e ON a.employee_id = e.id 
     WHERE a.status = 'pending' 
     ORDER BY a.created_at DESC 
     LIMIT 5"
);

// Get recent employees
$recentEmployees = fetchAll(
    "SELECT e.*, d.name as domain_name 
     FROM employees e 
     LEFT JOIN domains d ON e.domain_id = d.id 
     ORDER BY e.created_at DESC LIMIT 5"
);

// Get attendance trend (last 7 days)
$attendanceTrend = fetchAll(
    "SELECT DATE(date) as date, COUNT(*) as count 
     FROM attendance 
     WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
     GROUP BY DATE(date) 
     ORDER BY date ASC"
);

// Get domain-wise employee count
$domainStats = fetchAll(
    "SELECT d.name, COUNT(e.id) as count 
     FROM domains d 
     LEFT JOIN employees e ON d.id = e.domain_id AND e.status = 'active'
     GROUP BY d.id, d.name 
     ORDER BY count DESC 
     LIMIT 5"
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<style>
/* Modern Dashboard Styles */
.main-content {
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
    min-height: 100vh;
}

.content-wrapper {
    padding: 30px;
}

/* Hero Welcome Section */
.welcome-hero {
    background: linear-gradient(135deg, #0A1138 0%, #16246F 60%, #1E3A8A 100%);
    border-radius: 24px;
    padding: 38px 40px;
    color: white;
    position: relative;
    overflow: hidden;
    margin-bottom: 28px;
    box-shadow: 0 16px 40px rgba(10, 17, 56, 0.22);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.welcome-hero::before {
    content: '';
    position: absolute;
    width: 340px;
    height: 340px;
    background: radial-gradient(circle, rgba(255, 165, 0, 0.2) 0%, transparent 70%);
    border-radius: 50%;
    top: -80px;
    right: -40px;
    pointer-events: none;
}

.welcome-hero::after {
    content: '';
    position: absolute;
    width: 240px;
    height: 240px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    border-radius: 50%;
    bottom: -80px;
    left: 120px;
    pointer-events: none;
}

.welcome-content {
    position: relative;
    z-index: 1;
}

.welcome-hero h2 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 8px;
    font-family: 'Outfit', sans-serif;
    letter-spacing: -0.3px;
}

.welcome-hero p {
    opacity: 0.9;
    font-size: 1.05rem;
    margin-bottom: 0;
}

.welcome-meta {
    display: flex;
    gap: 16px;
    margin-top: 22px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255, 255, 255, 0.18);
    padding: 8px 18px;
    border-radius: 9999px;
    backdrop-filter: blur(10px);
    font-size: 0.92rem;
    font-weight: 500;
}

.meta-item i {
    font-size: 1.15rem;
    color: #FFA500;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
    margin-bottom: 28px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 24px 26px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 20px -2px rgba(10, 17, 56, 0.05);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1px solid rgba(10, 17, 56, 0.06);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 36px -6px rgba(10, 17, 56, 0.12);
    border-color: rgba(255, 165, 0, 0.35);
}

.stat-card .stat-icon {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.45rem;
    margin-bottom: 16px;
    transition: transform 0.25s ease;
}

.stat-card:hover .stat-icon {
    transform: scale(1.08);
}

.stat-card .stat-icon.primary {
    background: rgba(10, 17, 56, 0.08);
    color: #0A1138;
}

.stat-card .stat-icon.success {
    background: rgba(16, 185, 129, 0.12);
    color: #10B981;
}

.stat-card .stat-icon.warning {
    background: rgba(255, 165, 0, 0.14);
    color: #FFA500;
}

.stat-card .stat-icon.info {
    background: rgba(14, 165, 233, 0.12);
    color: #0284C7;
}

.stat-card h3 {
    font-size: 2.2rem;
    font-weight: 800;
    color: #0A1138;
    margin-bottom: 4px;
    font-family: 'Outfit', sans-serif;
    letter-spacing: -0.5px;
}

.stat-card p {
    color: #64748B;
    font-size: 0.9rem;
    font-weight: 500;
    margin: 0;
}

.stat-card .stat-trend {
    position: absolute;
    top: 22px;
    right: 22px;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.stat-card .stat-trend.up {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.stat-card .stat-trend.down {
    background: rgba(239, 68, 68, 0.12);
    color: #DC2626;
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

/* Modern Cards */
.modern-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.05);
    border: 1px solid rgba(0,0,0,0.03);
    overflow: hidden;
}

.modern-card .card-header {
    padding: 24px 28px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: transparent;
}

.modern-card .card-header h5 {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1a1a2e;
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
}

.modern-card .card-header h5 .header-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.modern-card .card-header h5 .header-icon.purple {
    background: rgba(10, 17, 56, 0.08);
    color: #0A1138;
}

.modern-card .card-header h5 .header-icon.orange {
    background: rgba(255, 165, 0, 0.14);
    color: #FFA500;
}

.modern-card .card-header h5 .header-icon.blue {
    background: rgba(14, 165, 233, 0.12);
    color: #0284C7;
}

.modern-card .card-header h5 .header-icon.green {
    background: rgba(16, 185, 129, 0.12);
    color: #10B981;
}

.btn-view-all {
    background: #0A1138;
    color: white;
    border: none;
    padding: 7px 18px;
    border-radius: 9999px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-view-all:hover {
    background: #16246F;
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(10, 17, 56, 0.25);
    color: white;
}

/* Modern Table */
.modern-table {
    width: 100%;
}

.modern-table th {
    background: #f8fafc;
    padding: 16px 24px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    border: none;
}

.modern-table td {
    padding: 18px 24px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.modern-table tr:last-child td {
    border-bottom: none;
}

.modern-table tr:hover {
    background: #f8fafc;
}

.employee-cell {
    display: flex;
    align-items: center;
    gap: 14px;
}

.employee-cell img {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    object-fit: cover;
    border: 2px solid #e2e8f0;
}

.employee-cell .emp-info h6 {
    font-weight: 600;
    color: #1a1a2e;
    margin: 0 0 3px 0;
    font-size: 0.95rem;
}

.employee-cell .emp-info span {
    color: #94a3b8;
    font-size: 0.8rem;
}

.badge-modern {
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-modern.success {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.badge-modern.warning {
    background: rgba(234, 179, 8, 0.1);
    color: #ca8a04;
}

.badge-modern.danger {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.location-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    padding: 6px 12px;
    border-radius: 8px;
    background: rgba(59, 130, 246, 0.08);
    transition: all 0.3s ease;
}

.location-link:hover {
    background: rgba(59, 130, 246, 0.15);
    color: #2563eb;
}

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: #0A1138;
    color: white;
    transition: all 0.2s ease;
}

.action-btn:hover {
    background: #16246F;
    transform: scale(1.08);
    box-shadow: 0 4px 12px rgba(10, 17, 56, 0.3);
}

/* Quick Actions */
.quick-actions-grid {
    padding: 24px;
    display: grid;
    gap: 14px;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 22px;
    border-radius: 14px;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.quick-action-btn .action-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.quick-action-btn .action-text h6 {
    margin: 0 0 3px 0;
    font-weight: 600;
    font-size: 0.95rem;
}

.quick-action-btn .action-text span {
    font-size: 0.8rem;
    opacity: 0.7;
}

.quick-action-btn.primary {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
    color: #667eea;
}

.quick-action-btn.primary .action-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.quick-action-btn.primary:hover {
    border-color: #667eea;
    transform: translateX(5px);
}

.quick-action-btn.success {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.08) 0%, rgba(22, 163, 74, 0.08) 100%);
    color: #22c55e;
}

.quick-action-btn.success .action-icon {
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
    color: white;
}

.quick-action-btn.success:hover {
    border-color: #22c55e;
    transform: translateX(5px);
}

.quick-action-btn.warning {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(234, 88, 12, 0.08) 100%);
    color: #f59e0b;
}

.quick-action-btn.warning .action-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
    color: white;
}

.quick-action-btn.warning:hover {
    border-color: #f59e0b;
    transform: translateX(5px);
}

.quick-action-btn.info {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(99, 102, 241, 0.08) 100%);
    color: #3b82f6;
}

.quick-action-btn.info .action-icon {
    background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
    color: white;
}

.quick-action-btn.info:hover {
    border-color: #3b82f6;
    transform: translateX(5px);
}

/* Chart Container */
.chart-container {
    padding: 24px;
    height: 300px;
}

/* Empty State */
.empty-state-modern {
    padding: 50px 30px;
    text-align: center;
}

.empty-state-modern .empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2rem;
    color: #667eea;
}

.empty-state-modern h4 {
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 8px;
}

.empty-state-modern p {
    color: #6b7280;
    margin: 0;
}

/* Second Row Grid */
.dashboard-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-grid-2 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .welcome-hero {
        padding: 30px;
    }
    
    .welcome-meta {
        flex-direction: column;
        gap: 15px;
    }
    
    .content-wrapper {
        padding: 20px;
    }
}
</style>

<!-- Main Content -->
<div class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="page-title">Dashboard</h1>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <button class="header-icon-btn" data-bs-toggle="dropdown">
                    <i class="bi bi-bell"></i>
                    <?php if ($pendingApprovals > 0): ?>
                    <span class="notification-badge"><?php echo $pendingApprovals; ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notifications-dropdown">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0">Notifications</h6>
                    </div>
                    <?php if ($pendingApprovals > 0): ?>
                    <a href="<?php echo url('admin/approvals'); ?>" class="notification-item unread">
                        <div class="notification-icon bg-warning text-white">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title"><?php echo $pendingApprovals; ?> Pending Approvals</div>
                            <div class="notification-text">Check-in requests awaiting approval</div>
                        </div>
                    </a>
                    <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-bell-slash fs-1"></i>
                        <p class="mb-0 mt-2">No new notifications</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="Avatar" class="user-avatar">
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['user_name']; ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo url('admin/profile'); ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="<?php echo url('admin/settings'); ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo url('logout'); ?>"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <!-- Content -->
    <div class="content-wrapper">
        <!-- Welcome Hero -->
        <div class="welcome-hero">
            <div class="welcome-content">
                <h2>Welcome back, <?php echo $_SESSION['user_name']; ?>! 👋</h2>
                <p>Here's what's happening with your organization today.</p>
                <div class="welcome-meta">
                    <div class="meta-item">
                        <i class="bi bi-calendar3"></i>
                        <span><?php echo date('l, F j, Y'); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="bi bi-clock"></i>
                        <span id="current-time"><?php echo date('h:i A'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <h3><?php echo $totalEmployees; ?></h3>
                <p>Total Employees</p>
                <span class="stat-trend up"><i class="bi bi-arrow-up"></i> Active</span>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
                <h3><?php echo $todayAttendance; ?></h3>
                <p>Today's Check-ins</p>
                <span class="stat-trend up"><i class="bi bi-check-circle"></i> Today</span>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <h3><?php echo $pendingApprovals; ?></h3>
                <p>Pending Approvals</p>
                <span class="stat-trend <?php echo $pendingApprovals > 0 ? 'down' : 'up'; ?>">
                    <i class="bi bi-<?php echo $pendingApprovals > 0 ? 'exclamation-circle' : 'check-circle'; ?>"></i> 
                    <?php echo $pendingApprovals > 0 ? 'Needs Review' : 'All Clear'; ?>
                </span>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="bi bi-list-task"></i>
                </div>
                <h3><?php echo $activeTasks; ?></h3>
                <p>Active Tasks</p>
                <span class="stat-trend up"><i class="bi bi-check2-all"></i> <?php echo $completedTasks; ?> Done</span>
            </div>
        </div>
        
        <!-- Main Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Pending Approvals -->
            <div class="modern-card">
                <div class="card-header">
                    <h5>
                        <span class="header-icon orange"><i class="bi bi-clock-history"></i></span>
                        Pending Approvals
                    </h5>
                    <a href="<?php echo url('admin/approvals'); ?>" class="btn-view-all">View All</a>
                </div>
                <?php if (count($recentPending) > 0): ?>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Check-in Time</th>
                                <th>Location</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPending as $pending): ?>
                            <tr>
                                <td>
                                    <div class="employee-cell">
                                        <img src="<?php echo getAvatar($pending['avatar']); ?>" alt="">
                                        <div class="emp-info">
                                            <h6><?php echo $pending['name']; ?></h6>
                                            <span><?php echo $pending['employee_id']; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo formatTime($pending['check_in_time'], 'h:i A'); ?></strong>
                                    <br><small class="text-muted"><?php echo formatDate($pending['check_in_time'], 'M d, Y'); ?></small>
                                </td>
                                <td>
                                    <?php if ($pending['check_in_latitude'] && $pending['check_in_longitude']): ?>
                                    <a href="https://maps.google.com/?q=<?php echo $pending['check_in_latitude']; ?>,<?php echo $pending['check_in_longitude']; ?>" target="_blank" class="location-link">
                                        <i class="bi bi-geo-alt-fill"></i> View Map
                                    </a>
                                    <?php else: ?>
                                    <span class="badge-modern warning">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo url('admin/approvals'); ?>?action=view&id=<?php echo $pending['id']; ?>" class="action-btn">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state-modern">
                    <div class="empty-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <h4>All Caught Up!</h4>
                    <p>No pending approvals at the moment.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Actions -->
            <div class="modern-card">
                <div class="card-header">
                    <h5>
                        <span class="header-icon purple"><i class="bi bi-lightning-charge"></i></span>
                        Quick Actions
                    </h5>
                </div>
                <div class="quick-actions-grid">
                    <a href="<?php echo url('admin/employees'); ?>?action=add" class="quick-action-btn primary">
                        <div class="action-icon">
                            <i class="bi bi-person-plus"></i>
                        </div>
                        <div class="action-text">
                            <h6>Add Employee</h6>
                            <span>Create new employee record</span>
                        </div>
                    </a>
                    
                    <a href="<?php echo url('admin/tasks'); ?>?action=add" class="quick-action-btn success">
                        <div class="action-icon">
                            <i class="bi bi-plus-circle"></i>
                        </div>
                        <div class="action-text">
                            <h6>Create Task</h6>
                            <span>Assign new task to team</span>
                        </div>
                    </a>
                    
                    <a href="<?php echo url('admin/approvals'); ?>" class="quick-action-btn warning">
                        <div class="action-icon">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <div class="action-text">
                            <h6>Review Approvals</h6>
                            <span><?php echo $pendingApprovals; ?> pending reviews</span>
                        </div>
                    </a>
                    
                    <a href="<?php echo url('admin/reports'); ?>" class="quick-action-btn info">
                        <div class="action-icon">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <div class="action-text">
                            <h6>View Reports</h6>
                            <span>Analytics & insights</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Second Row -->
        <div class="dashboard-grid-2">
            <!-- Recent Employees -->
            <div class="modern-card">
                <div class="card-header">
                    <h5>
                        <span class="header-icon blue"><i class="bi bi-people"></i></span>
                        Recent Employees
                    </h5>
                    <a href="<?php echo url('admin/employees'); ?>" class="btn-view-all">View All</a>
                </div>
                <?php if (count($recentEmployees) > 0): ?>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Domain</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentEmployees as $emp): ?>
                            <tr>
                                <td>
                                    <div class="employee-cell">
                                        <img src="<?php echo getAvatar($emp['avatar']); ?>" alt="">
                                        <div class="emp-info">
                                            <h6><?php echo $emp['name']; ?></h6>
                                            <span><?php echo $emp['email']; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge-modern success"><?php echo $emp['domain_name'] ?? 'N/A'; ?></span></td>
                                <td>
                                    <span class="badge-modern <?php echo $emp['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($emp['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state-modern">
                    <div class="empty-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <h4>No Employees Yet</h4>
                    <p>Add your first employee to get started.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Attendance Chart -->
            <div class="modern-card">
                <div class="card-header">
                    <h5>
                        <span class="header-icon green"><i class="bi bi-bar-chart"></i></span>
                        Attendance Trend
                    </h5>
                </div>
                <div class="chart-container">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Live Clock
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        document.getElementById('current-time').textContent = timeString;
    }
    setInterval(updateClock, 1000);
    
    // Attendance Chart - Modern Bar Chart Style
    const canvas = document.getElementById('attendanceChart');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        const data = <?php echo json_encode($attendanceTrend); ?>;
        const labels = data.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric' });
        });
        const values = data.map(item => parseInt(item.count));
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels.length > 0 ? labels : ['No data'],
                datasets: [{
                    label: 'Check-ins',
                    data: values.length > 0 ? values : [0],
                    backgroundColor: '#6366f1',
                    borderRadius: 6,
                    barThickness: 20,
                    hoverBackgroundColor: '#4f46e5'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#e5e7eb',
                        titleFont: { size: 14, weight: '600' },
                        bodyFont: { size: 13 },
                        padding: 14,
                        cornerRadius: 10,
                        displayColors: false,
                        callbacks: {
                            title: function(items) {
                                return items[0].label;
                            },
                            label: function(context) {
                                return `${context.parsed.y} employee${context.parsed.y !== 1 ? 's' : ''} checked in`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(156, 163, 175, 0.12)',
                            drawBorder: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            stepSize: 1,
                            color: '#9ca3af',
                            font: { size: 11, weight: '500' },
                            padding: 10
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            color: '#9ca3af',
                            font: { size: 11, weight: '500' },
                            padding: 10
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
