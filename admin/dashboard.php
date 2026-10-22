<?php
/**
 * Admin Dashboard - SaaS Command Center
 * Employee Management System
 */

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Process auto-checkout if applicable
checkAutoCheckout();

// Core Statistics
$totalEmployees = fetchOne("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")['count'] ?? 0;
$pendingApprovals = fetchOne("SELECT COUNT(*) as count FROM attendance WHERE status = 'pending'")['count'] ?? 0;
$todayAttendance = fetchOne("SELECT COUNT(*) as count FROM attendance WHERE date = CURDATE()")['count'] ?? 0;
$activeTasks = fetchOne("SELECT COUNT(*) as count FROM tasks WHERE status IN ('not_started', 'in_progress')")['count'] ?? 0;
$totalDomains = fetchOne("SELECT COUNT(*) as count FROM domains")['count'] ?? 0;
$completedTasks = fetchOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'completed'")['count'] ?? 0;

// Offer Letters & Certificates Counts
$totalCertificates = fetchOne("SELECT COUNT(*) as count FROM completion_certificates WHERE status = 'active'")['count'] ?? 0;
$totalOffers = fetchOne("SELECT COUNT(*) as count FROM offer_letters")['count'] ?? 0;

// Calculate Rates
$attendanceRate = $totalEmployees > 0 ? round(($todayAttendance / $totalEmployees) * 100) : 0;
$taskTotal = $activeTasks + $completedTasks;
$taskRate = $taskTotal > 0 ? round(($completedTasks / $taskTotal) * 100) : 0;

// Get recent pending approvals
$recentPending = fetchAll(
    "SELECT a.*, e.name, e.employee_id, e.avatar, d.name as domain_name
     FROM attendance a 
     JOIN employees e ON a.employee_id = e.id 
     LEFT JOIN domains d ON e.domain_id = d.id
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
    "SELECT d.name, d.code, COUNT(e.id) as count 
     FROM domains d 
     LEFT JOIN employees e ON d.id = e.domain_id AND e.status = 'active'
     GROUP BY d.id, d.name, d.code
     ORDER BY count DESC 
     LIMIT 5"
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<style>
/* SaaS Command Center Design System */
.main-content {
    background: #F8FAFC;
    min-height: 100vh;
}

.content-wrapper {
    padding: 28px 32px;
}

/* Hero Command Hub */
.saas-hero {
    background: linear-gradient(135deg, #0A1138 0%, #121E52 55%, #1A2B75 100%);
    border-radius: 24px;
    padding: 34px 38px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    margin-bottom: 28px;
    box-shadow: 0 16px 40px rgba(10, 17, 56, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.saas-hero::before {
    content: '';
    position: absolute;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(255, 165, 0, 0.22) 0%, transparent 68%);
    border-radius: 50%;
    top: -120px;
    right: -60px;
    pointer-events: none;
}

.saas-hero::after {
    content: '';
    position: absolute;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
    border-radius: 50%;
    bottom: -100px;
    left: 20%;
    pointer-events: none;
}

.saas-hero-content {
    position: relative;
    z-index: 2;
}

.saas-hero-title {
    font-size: 1.85rem;
    font-weight: 800;
    font-family: 'Outfit', sans-serif;
    letter-spacing: -0.4px;
    margin-bottom: 6px;
    color: #FFFFFF;
}

.saas-hero-subtitle {
    font-size: 0.98rem;
    color: rgba(255, 255, 255, 0.82);
    margin-bottom: 22px;
    max-width: 650px;
}

.saas-hero-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.saas-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.16);
    padding: 7px 16px;
    border-radius: 9999px;
    font-size: 0.85rem;
    font-weight: 500;
    color: #FFFFFF;
    backdrop-filter: blur(10px);
}

.saas-pill i, .saas-pill svg {
    color: #FFA500;
}

.saas-pill.live-pulse {
    background: rgba(16, 185, 129, 0.2);
    border-color: rgba(16, 185, 129, 0.4);
    color: #A7F3D0;
}

.saas-pill.live-pulse .pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10B981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.35);
    animation: pulseGlow 1.8s infinite;
}

@keyframes pulseGlow {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.3); opacity: 0.6; }
}

.saas-hero-actions {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: flex-end;
}

/* Dynamic SaaS Metric Cards */
.metric-card {
    background: #FFFFFF;
    border-radius: 20px;
    padding: 24px;
    border: 1px solid rgba(10, 17, 56, 0.06);
    box-shadow: 0 4px 20px -2px rgba(10, 17, 56, 0.04);
    transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
    text-decoration: none !important;
}

.metric-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 36px -6px rgba(10, 17, 56, 0.12);
    border-color: rgba(255, 165, 0, 0.35);
}

.metric-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.metric-icon-badge {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.25s ease;
}

.metric-card:hover .metric-icon-badge {
    transform: scale(1.08);
}

.metric-icon-badge.navy {
    background: rgba(10, 17, 56, 0.07);
    color: #0A1138;
}

.metric-icon-badge.emerald {
    background: rgba(16, 185, 129, 0.12);
    color: #10B981;
}

.metric-icon-badge.amber {
    background: rgba(255, 165, 0, 0.14);
    color: #FFA500;
}

.metric-icon-badge.sky {
    background: rgba(14, 165, 233, 0.12);
    color: #0284C7;
}

.metric-trend-pill {
    font-size: 0.75rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 9999px;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.metric-trend-pill.active {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.metric-trend-pill.warning {
    background: rgba(255, 165, 0, 0.14);
    color: #D97706;
}

.metric-trend-pill.sky {
    background: rgba(14, 165, 233, 0.12);
    color: #0284C7;
}

.metric-value {
    font-size: 2.2rem;
    font-weight: 800;
    color: #0A1138;
    line-height: 1.1;
    font-family: 'Outfit', sans-serif;
    letter-spacing: -0.5px;
    margin-bottom: 4px;
}

.metric-label {
    font-size: 0.88rem;
    font-weight: 600;
    color: #64748B;
    margin-bottom: 14px;
}

.metric-progress {
    height: 6px;
    border-radius: 9999px;
    background: #F1F5F9;
    overflow: hidden;
    margin-top: auto;
}

.metric-progress-bar {
    height: 100%;
    border-radius: 9999px;
}

.metric-progress-bar.navy { background: #0A1138; }
.metric-progress-bar.emerald { background: #10B981; }
.metric-progress-bar.amber { background: #FFA500; }
.metric-progress-bar.sky { background: #0284C7; }

/* Interactive SaaS Command Hub / Quick Actions */
.saas-action-card {
    background: #FFFFFF;
    border-radius: 18px;
    padding: 20px 22px;
    border: 1px solid rgba(10, 17, 56, 0.06);
    box-shadow: 0 4px 18px -2px rgba(10, 17, 56, 0.04);
    display: flex;
    align-items: center;
    gap: 16px;
    text-decoration: none !important;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    height: 100%;
}

.saas-action-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 30px -4px rgba(10, 17, 56, 0.1);
    border-color: rgba(255, 165, 0, 0.35);
}

.saas-action-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform 0.25s ease;
}

.saas-action-card:hover .saas-action-icon {
    transform: scale(1.1);
}

.saas-action-text {
    flex: 1;
    min-width: 0;
}

.saas-action-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0A1138;
    margin-bottom: 2px;
    font-family: 'Outfit', sans-serif;
}

.saas-action-desc {
    font-size: 0.8rem;
    color: #64748B;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.saas-action-arrow {
    color: #94A3B8;
    transition: all 0.25s ease;
}

.saas-action-card:hover .saas-action-arrow {
    color: #0A1138;
    transform: translateX(4px);
}

/* Modern Card Layouts */
.saas-card {
    background: #FFFFFF;
    border-radius: 20px;
    border: 1px solid rgba(10, 17, 56, 0.06);
    box-shadow: 0 4px 20px -2px rgba(10, 17, 56, 0.04);
    margin-bottom: 24px;
    overflow: visible;
}

.saas-card-header {
    padding: 20px 26px;
    border-bottom: 1px solid rgba(10, 17, 56, 0.06);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.saas-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0A1138;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    font-family: 'Outfit', sans-serif;
}

.saas-card-title-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.saas-card-title-icon.navy { background: rgba(10, 17, 56, 0.07); color: #0A1138; }
.saas-card-title-icon.amber { background: rgba(255, 165, 0, 0.14); color: #FFA500; }
.saas-card-title-icon.emerald { background: rgba(16, 185, 129, 0.12); color: #10B981; }
.saas-card-title-icon.sky { background: rgba(14, 165, 233, 0.12); color: #0284C7; }

.saas-card-body {
    padding: 24px 26px;
}

/* User & Employee Avatar Cells */
.saas-user-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.saas-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    object-fit: cover;
    border: 1.5px solid rgba(10, 17, 56, 0.08);
}

.saas-user-name {
    font-weight: 700;
    font-size: 0.92rem;
    color: #0A1138;
    line-height: 1.25;
}

.saas-user-sub {
    font-size: 0.78rem;
    color: #64748B;
    font-weight: 500;
}

/* Mini Action Buttons */
.saas-btn-action {
    width: 34px;
    height: 34px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    transition: all 0.2s ease;
    text-decoration: none !important;
}

.saas-btn-action.approve {
    background: rgba(16, 185, 129, 0.12);
    color: #10B981;
}

.saas-btn-action.approve:hover {
    background: #10B981;
    color: #FFFFFF;
    transform: scale(1.08);
}

.saas-btn-action.reject {
    background: rgba(239, 68, 68, 0.12);
    color: #EF4444;
}

.saas-btn-action.reject:hover {
    background: #EF4444;
    color: #FFFFFF;
    transform: scale(1.08);
}

.saas-btn-action.view {
    background: rgba(10, 17, 56, 0.08);
    color: #0A1138;
}

.saas-btn-action.view:hover {
    background: #0A1138;
    color: #FFFFFF;
    transform: scale(1.08);
}

/* Domain Progress Row */
.domain-row {
    margin-bottom: 18px;
}

.domain-row:last-child {
    margin-bottom: 0;
}

.domain-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 6px;
}

.domain-progress {
    height: 8px;
    border-radius: 9999px;
    background: #F1F5F9;
    overflow: hidden;
}

.domain-progress-bar {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.6s ease;
}

/* Empty State */
.saas-empty-state {
    padding: 48px 24px;
    text-align: center;
}

.saas-empty-icon {
    width: 64px;
    height: 64px;
    border-radius: 18px;
    background: rgba(16, 185, 129, 0.1);
    color: #10B981;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 1.8rem;
}

/* Responsive Fixes */
@media (max-width: 991.98px) {
    .content-wrapper {
        padding: 20px 16px;
    }
    
    .saas-hero {
        padding: 26px 20px;
    }
    
    .saas-hero-actions {
        justify-content: flex-start;
        margin-top: 16px;
    }
}
</style>

<!-- Main Content Area -->
<div class="main-content">
    <!-- Top Sticky Header -->
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle" aria-label="Toggle Sidebar">
                <i class="bi bi-list"></i>
            </button>
            <div>
                <h1 class="page-title mb-0">Dashboard</h1>
            </div>
        </div>
        
        <div class="header-right">
            <!-- Header Quick Action Shortcuts -->
            <div class="d-none d-md-flex align-items-center gap-2 me-2">
                <a href="<?php echo url('admin/generate-offer'); ?>" class="btn btn-outline-primary btn-sm rounded-pill d-inline-flex align-items-center gap-2">
                    <?php echo pika_icon('offer-letter', '', 16); ?>
                    <span>Issue Offer</span>
                </a>
                <a href="<?php echo url('admin/generate-certificate'); ?>" class="btn btn-navy btn-sm rounded-pill d-inline-flex align-items-center gap-2">
                    <?php echo pika_icon('certificate', '', 16); ?>
                    <span>Issue Certificate</span>
                </a>
            </div>

            <!-- Notifications Dropdown -->
            <div class="dropdown">
                <button class="header-icon-btn" data-bs-toggle="dropdown" aria-label="Notifications">
                    <?php echo pika_icon('bell', '', 18); ?>
                    <?php if ($pendingApprovals > 0): ?>
                        <span class="notification-badge"><?php echo $pendingApprovals; ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notifications-dropdown shadow-lg border-0" style="border-radius: 16px; min-width: 320px;">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 font-weight-bold text-dark">Notifications</h6>
                        <?php if ($pendingApprovals > 0): ?>
                            <span class="badge bg-danger-subtle text-danger font-monospace"><?php echo $pendingApprovals; ?> New</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($pendingApprovals > 0): ?>
                        <a href="<?php echo url('admin/approvals'); ?>" class="notification-item unread d-flex align-items-center gap-3 p-3 text-decoration-none">
                            <div class="notification-icon bg-warning-subtle text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0;">
                                <?php echo pika_icon('clock', '', 18); ?>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title font-weight-600 text-dark"><?php echo $pendingApprovals; ?> Check-in Approvals</div>
                                <div class="notification-text text-muted small">Employee check-in requests awaiting administrative review</div>
                            </div>
                        </a>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <div class="mb-2 opacity-50"><?php echo pika_icon('check-circle', '', 36); ?></div>
                            <p class="mb-0 small font-weight-500">All notifications caught up!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- User Profile Dropdown -->
            <div class="dropdown">
                <div class="user-dropdown d-flex align-items-center gap-2" data-bs-toggle="dropdown" style="cursor: pointer;">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="Avatar" class="user-avatar" style="width: 38px; height: 38px; border-radius: 10px;">
                    <div class="user-info d-none d-sm-block text-start">
                        <div class="user-name font-weight-700 text-dark" style="font-size: 13.5px;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                        <div class="user-role text-muted" style="font-size: 11px;">Administrator</div>
                    </div>
                    <i class="bi bi-chevron-down text-muted small ms-1"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 14px; min-width: 200px;">
                    <li><a class="dropdown-item py-2" href="<?php echo url('admin/profile'); ?>"><i class="bi bi-person me-2 text-primary"></i>My Profile</a></li>
                    <li><a class="dropdown-item py-2" href="<?php echo url('admin/settings'); ?>"><i class="bi bi-gear me-2 text-muted"></i>System Settings</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item py-2 text-danger" href="<?php echo url('logout'); ?>"><i class="bi bi-box-arrow-left me-2"></i>Sign Out</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <!-- Main Content Container -->
    <div class="content-wrapper">
        <!-- Hero Command Hub -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-lg-8 saas-hero-content">
                    <h2 class="saas-hero-title">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 👋</h2>
                    <p class="saas-hero-subtitle">
                        Here's what's happening across Clientura EMS today. Monitor active personnel, verify attendance requests, and manage corporate credentialing.
                    </p>
                    
                    <div class="saas-hero-pills">
                        <div class="saas-pill">
                            <i class="bi bi-calendar3"></i>
                            <span><?php echo date('l, M j, Y'); ?></span>
                        </div>
                        <div class="saas-pill">
                            <?php echo pika_icon('clock', '', 14); ?>
                            <span id="current-time"><?php echo date('h:i A'); ?></span>
                        </div>
                        <div class="saas-pill live-pulse">
                            <span class="pulse-dot"></span>
                            <span>EMS Cloud Online</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 mt-4 mt-lg-0 text-lg-end saas-hero-content">
                    <div class="bg-white bg-opacity-10 p-3 rounded-4 backdrop-blur border border-white border-opacity-10 d-inline-block text-start" style="min-width: 260px;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-white-50 small font-weight-500">Today's Turnout</span>
                            <span class="font-weight-700 text-white font-monospace"><?php echo $attendanceRate; ?>%</span>
                        </div>
                        <div class="progress" style="height: 6px; background: rgba(255,255,255,0.15); border-radius: 9999px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $attendanceRate; ?>%; border-radius: 9999px;"></div>
                        </div>
                        <div class="mt-2 text-white-50 small">
                            <span class="text-white font-weight-600"><?php echo $todayAttendance; ?></span> of <span class="text-white font-weight-600"><?php echo $totalEmployees; ?></span> employees checked in
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Primary Metrics Row -->
        <div class="row g-4 mb-4">
            <!-- Metric 1: Total Employees -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="<?php echo url('admin/employees'); ?>" class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge navy">
                            <?php echo pika_icon('users', '', 22); ?>
                        </div>
                        <span class="metric-trend-pill active">Active</span>
                    </div>
                    <div class="metric-value"><?php echo number_format($totalEmployees); ?></div>
                    <div class="metric-label">Total Active Workforce</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar navy" style="width: 100%;"></div>
                    </div>
                </a>
            </div>
            
            <!-- Metric 2: Today's Check-ins -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="<?php echo url('admin/attendance'); ?>" class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge emerald">
                            <?php echo pika_icon('calendar', '', 22); ?>
                        </div>
                        <span class="metric-trend-pill active">Today</span>
                    </div>
                    <div class="metric-value"><?php echo number_format($todayAttendance); ?></div>
                    <div class="metric-label">Today's Check-ins (<?php echo $attendanceRate; ?>%)</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar emerald" style="width: <?php echo max(5, $attendanceRate); ?>%;"></div>
                    </div>
                </a>
            </div>
            
            <!-- Metric 3: Pending Approvals -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="<?php echo url('admin/approvals'); ?>" class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge amber">
                            <?php echo pika_icon('clock', '', 22); ?>
                        </div>
                        <span class="metric-trend-pill <?php echo $pendingApprovals > 0 ? 'warning' : 'active'; ?>">
                            <?php echo $pendingApprovals > 0 ? 'Needs Review' : 'All Clear'; ?>
                        </span>
                    </div>
                    <div class="metric-value"><?php echo number_format($pendingApprovals); ?></div>
                    <div class="metric-label">Pending Approval Requests</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar amber" style="width: <?php echo min(100, $pendingApprovals * 20); ?>%;"></div>
                    </div>
                </a>
            </div>
            
            <!-- Metric 4: Active Tasks -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="<?php echo url('admin/tasks'); ?>" class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge sky">
                            <?php echo pika_icon('tasks', '', 22); ?>
                        </div>
                        <span class="metric-trend-pill sky"><?php echo $completedTasks; ?> Done</span>
                    </div>
                    <div class="metric-value"><?php echo number_format($activeTasks); ?></div>
                    <div class="metric-label">Active Team Tasks</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar sky" style="width: <?php echo max(8, $taskRate); ?>%;"></div>
                    </div>
                </a>
            </div>
        </div>

        <!-- SaaS Interactive Action Command Hub -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-4">
                <a href="<?php echo url('admin/generate-offer'); ?>" class="saas-action-card">
                    <div class="saas-action-icon" style="background: rgba(10, 17, 56, 0.08); color: #0A1138;">
                        <?php echo pika_icon('offer-letter', '', 22); ?>
                    </div>
                    <div class="saas-action-text">
                        <div class="saas-action-title">Issue Offer Letter</div>
                        <p class="saas-action-desc">Generate A4 candidate letterhead with PDF export</p>
                    </div>
                    <div class="saas-action-arrow"><?php echo pika_icon('arrow-right', '', 18); ?></div>
                </a>
            </div>
            
            <div class="col-12 col-md-6 col-xl-4">
                <a href="<?php echo url('admin/generate-certificate'); ?>" class="saas-action-card">
                    <div class="saas-action-icon" style="background: rgba(255, 165, 0, 0.14); color: #FFA500;">
                        <?php echo pika_icon('certificate', '', 22); ?>
                    </div>
                    <div class="saas-action-text">
                        <div class="saas-action-title">Issue Certificate</div>
                        <p class="saas-action-desc">Generate authenticated completion credentials & QR</p>
                    </div>
                    <div class="saas-action-arrow"><?php echo pika_icon('arrow-right', '', 18); ?></div>
                </a>
            </div>
            
            <div class="col-12 col-md-6 col-xl-4">
                <a href="<?php echo url('admin/signatories'); ?>" class="saas-action-card">
                    <div class="saas-action-icon" style="background: rgba(16, 185, 129, 0.12); color: #10B981;">
                        <?php echo pika_icon('signature', '', 22); ?>
                    </div>
                    <div class="saas-action-text">
                        <div class="saas-action-title">Manage Signatories</div>
                        <p class="saas-action-desc">Configure CEO & CTO authorized e-signatures</p>
                    </div>
                    <div class="saas-action-arrow"><?php echo pika_icon('arrow-right', '', 18); ?></div>
                </a>
            </div>
            
            <div class="col-12 col-md-6 col-xl-4">
                <a href="<?php echo url('admin/employees'); ?>?action=add" class="saas-action-card">
                    <div class="saas-action-icon" style="background: rgba(14, 165, 233, 0.12); color: #0284C7;">
                        <?php echo pika_icon('user-plus', '', 22); ?>
                    </div>
                    <div class="saas-action-text">
                        <div class="saas-action-title">Onboard Employee</div>
                        <p class="saas-action-desc">Register new hire, domain assignment & credentials</p>
                    </div>
                    <div class="saas-action-arrow"><?php echo pika_icon('arrow-right', '', 18); ?></div>
                </a>
            </div>
            
            <div class="col-12 col-md-6 col-xl-4">
                <a href="<?php echo url('admin/attendance'); ?>" class="saas-action-card">
                    <div class="saas-action-icon" style="background: rgba(139, 92, 246, 0.12); color: #8B5CF6;">
                        <?php echo pika_icon('calendar', '', 22); ?>
                    </div>
                    <div class="saas-action-text">
                        <div class="saas-action-title">Attendance Log</div>
                        <p class="saas-action-desc">Live timestamps, work hours & geolocation audit</p>
                    </div>
                    <div class="saas-action-arrow"><?php echo pika_icon('arrow-right', '', 18); ?></div>
                </a>
            </div>
            
            <div class="col-12 col-md-6 col-xl-4">
                <a href="<?php echo url('admin/reports'); ?>" class="saas-action-card">
                    <div class="saas-action-icon" style="background: rgba(245, 158, 11, 0.12); color: #D97706;">
                        <?php echo pika_icon('reports', '', 22); ?>
                    </div>
                    <div class="saas-action-text">
                        <div class="saas-action-title">Analytics & Reports</div>
                        <p class="saas-action-desc">Export workforce intelligence, payroll & task stats</p>
                    </div>
                    <div class="saas-action-arrow"><?php echo pika_icon('arrow-right', '', 18); ?></div>
                </a>
            </div>
        </div>

        <!-- Main Operational Grid -->
        <div class="row g-4">
            <!-- Left Primary Operations Column -->
            <div class="col-lg-8">
                <!-- Section 1: Pending Approvals Queue -->
                <div class="saas-card card-table">
                    <div class="saas-card-header">
                        <div class="saas-card-title">
                            <div class="saas-card-title-icon amber">
                                <?php echo pika_icon('clock', '', 18); ?>
                            </div>
                            <span>Pending Attendance Approvals</span>
                            <?php if ($pendingApprovals > 0): ?>
                                <span class="badge bg-warning text-dark font-monospace ms-2"><?php echo $pendingApprovals; ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo url('admin/approvals'); ?>" class="btn btn-navy btn-sm rounded-pill d-inline-flex align-items-center gap-1 font-weight-600">
                            <span>Review Queue</span>
                            <?php echo pika_icon('arrow-right', '', 14); ?>
                        </a>
                    </div>
                    
                    <?php if (count($recentPending) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Check-in Time</th>
                                        <th>Location</th>
                                        <th class="text-end">Quick Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentPending as $pending): ?>
                                        <tr>
                                            <td>
                                                <div class="saas-user-cell">
                                                    <img src="<?php echo getAvatar($pending['avatar']); ?>" alt="" class="saas-user-avatar">
                                                    <div>
                                                        <div class="saas-user-name"><?php echo htmlspecialchars($pending['name']); ?></div>
                                                        <div class="saas-user-sub font-monospace"><?php echo htmlspecialchars($pending['employee_id']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary">
                                                    <?php echo htmlspecialchars($pending['domain_name'] ?? 'General'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="font-weight-600 text-dark"><?php echo formatTime($pending['check_in_time'], 'h:i A'); ?></div>
                                                <small class="text-muted"><?php echo formatDate($pending['check_in_time'], 'M d, Y'); ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($pending['check_in_latitude']) && !empty($pending['check_in_longitude'])): ?>
                                                    <a href="https://maps.google.com/?q=<?php echo $pending['check_in_latitude']; ?>,<?php echo $pending['check_in_longitude']; ?>" target="_blank" class="badge bg-light text-primary border text-decoration-none d-inline-flex align-items-center gap-1">
                                                        <?php echo pika_icon('map-pin', '', 12); ?> Map Pin
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">Standard IP</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex align-items-center gap-2">
                                                    <a href="<?php echo url('admin/approvals'); ?>?action=approve&id=<?php echo $pending['id']; ?>" class="saas-btn-action approve" title="Quick Approve">
                                                        <?php echo pika_icon('check', '', 16); ?>
                                                    </a>
                                                    <a href="<?php echo url('admin/approvals'); ?>?action=reject&id=<?php echo $pending['id']; ?>" class="saas-btn-action reject" title="Quick Reject">
                                                        <?php echo pika_icon('x', '', 16); ?>
                                                    </a>
                                                    <a href="<?php echo url('admin/approvals'); ?>?action=view&id=<?php echo $pending['id']; ?>" class="saas-btn-action view" title="View Details">
                                                        <?php echo pika_icon('eye', '', 16); ?>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="saas-empty-state">
                            <div class="saas-empty-icon">
                                <?php echo pika_icon('check-circle', '', 32); ?>
                            </div>
                            <h5 class="font-weight-700 text-dark mb-1">All Caught Up!</h5>
                            <p class="text-muted small mb-0">No pending check-in requests awaiting review at this time.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Section 2: Recent Employees & New Joiners -->
                <div class="saas-card card-table">
                    <div class="saas-card-header">
                        <div class="saas-card-title">
                            <div class="saas-card-title-icon navy">
                                <?php echo pika_icon('users', '', 18); ?>
                            </div>
                            <span>Recent Workforce Joiners</span>
                        </div>
                        <a href="<?php echo url('admin/employees'); ?>" class="btn btn-light btn-sm rounded-pill font-weight-600">
                            Directory
                        </a>
                    </div>
                    
                    <?php if (count($recentEmployees) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Role / Designation</th>
                                        <th>Domain</th>
                                        <th>Joined Date</th>
                                        <th class="text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentEmployees as $emp): ?>
                                        <tr>
                                            <td>
                                                <div class="saas-user-cell">
                                                    <img src="<?php echo getAvatar($emp['avatar']); ?>" alt="" class="saas-user-avatar">
                                                    <div>
                                                        <div class="saas-user-name"><?php echo htmlspecialchars($emp['name']); ?></div>
                                                        <div class="saas-user-sub"><?php echo htmlspecialchars($emp['email']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="font-weight-600 text-dark small d-block"><?php echo htmlspecialchars($emp['designation'] ?? 'Team Member'); ?></span>
                                                <span class="text-muted font-monospace" style="font-size: 11px;"><?php echo htmlspecialchars($emp['employee_id']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?php echo htmlspecialchars($emp['domain_name'] ?? 'General'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo formatDate($emp['created_at'], 'M d, Y'); ?></small>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge <?php echo $emp['status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>">
                                                    <?php echo ucfirst($emp['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="saas-empty-state">
                            <div class="saas-empty-icon" style="background: rgba(10, 17, 56, 0.08); color: #0A1138;">
                                <?php echo pika_icon('users', '', 32); ?>
                            </div>
                            <h5 class="font-weight-700 text-dark mb-1">No Employees Found</h5>
                            <p class="text-muted small mb-0">Add your first employee to get started with Clientura EMS.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Pulse, Analytics & Credentials -->
            <div class="col-lg-4">
                <!-- Section 3: 7-Day Attendance Trend Analytics -->
                <div class="saas-card">
                    <div class="saas-card-header">
                        <div class="saas-card-title">
                            <div class="saas-card-title-icon emerald">
                                <?php echo pika_icon('trending-up', '', 18); ?>
                            </div>
                            <span>7-Day Attendance Trend</span>
                        </div>
                    </div>
                    <div class="saas-card-body">
                        <div style="height: 190px; position: relative;">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Domain & Department Allocation -->
                <div class="saas-card">
                    <div class="saas-card-header">
                        <div class="saas-card-title">
                            <div class="saas-card-title-icon sky">
                                <?php echo pika_icon('domains', '', 18); ?>
                            </div>
                            <span>Department Allocation</span>
                        </div>
                        <a href="<?php echo url('admin/domains'); ?>" class="small text-decoration-none text-primary font-weight-600">
                            Manage
                        </a>
                    </div>
                    <div class="saas-card-body">
                        <?php if (count($domainStats) > 0): ?>
                            <?php foreach ($domainStats as $idx => $dom): ?>
                                <?php 
                                    $share = $totalEmployees > 0 ? round(($dom['count'] / $totalEmployees) * 100) : 0;
                                    $colors = ['#0A1138', '#10B981', '#FFA500', '#0284C7', '#8B5CF6'];
                                    $color = $colors[$idx % count($colors)];
                                ?>
                                <div class="domain-row">
                                    <div class="domain-meta">
                                        <span class="text-dark"><?php echo htmlspecialchars($dom['name']); ?></span>
                                        <span class="text-muted font-monospace"><?php echo $dom['count']; ?> (<?php echo $share; ?>%)</span>
                                    </div>
                                    <div class="domain-progress">
                                        <div class="domain-progress-bar" style="width: <?php echo max(5, $share); ?>%; background: <?php echo $color; ?>;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted small mb-0 text-center py-3">No department metrics available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 5: Public Credential Verification Card -->
                <div class="saas-card">
                    <div class="saas-card-body text-center p-4">
                        <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 56px; height: 56px; background: rgba(10, 17, 56, 0.08); color: #0A1138;">
                            <?php echo pika_icon('shield-check', '', 28); ?>
                        </div>
                        <h6 class="font-weight-700 text-dark mb-1">Public Verification Portal</h6>
                        <p class="text-muted small mb-3">All offer letters & completion certificates issued by Clientura are instantly verifiable on the web.</p>
                        <div class="bg-light p-2 rounded-3 font-monospace small text-primary mb-3 text-truncate border">
                            https://ems.clientura.in/verify-certificate
                        </div>
                        <a href="<?php echo url('verify-certificate'); ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill w-100 font-weight-600">
                            Launch Verification Portal <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Setup for Attendance Trend -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Real-Time Clock
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        const timeElem = document.getElementById('current-time');
        if (timeElem) timeElem.textContent = timeString;
    }
    setInterval(updateClock, 1000);
    
    // Attendance Trend Chart (Clientura Navy & Amber Theme)
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
                    backgroundColor: '#0A1138',
                    hoverBackgroundColor: '#FFA500',
                    borderRadius: 6,
                    barThickness: 16
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0A1138',
                        titleColor: '#FFFFFF',
                        bodyColor: '#FFA500',
                        titleFont: { size: 13, weight: '700' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return `${context.parsed.y} check-in${context.parsed.y !== 1 ? 's' : ''}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(10, 17, 56, 0.05)', drawBorder: false },
                        border: { display: false },
                        ticks: { stepSize: 1, color: '#94A3B8', font: { size: 10, weight: '600' } }
                    },
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { color: '#94A3B8', font: { size: 10, weight: '600' } }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
