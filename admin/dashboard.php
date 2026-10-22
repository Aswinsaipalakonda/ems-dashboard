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
$totalCertificates = fetchOne("SELECT COUNT(*) as count FROM certificates WHERE status = 'active'")['count'] ?? 0;
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
                <a href="<?php echo url('admin/generate-offer'); ?>" class="btn btn-saas-light">
                    <?php echo pika_icon('offer-letter', '', 16); ?>
                    <span>Issue Offer</span>
                </a>
                <a href="<?php echo url('admin/generate-certificate'); ?>" class="btn btn-saas-dark">
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
        <!-- Light SaaS Hero (Matches Image 3) -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-xl-6 col-lg-5 saas-hero-content">
                    <div class="saas-hero-pills">
                        <span class="saas-pill">
                            <?php echo pika_icon('sparkles', '', 14); ?>
                            Workforce Operations Command
                        </span>
                        <div class="saas-pill live-pulse">
                            <span class="pulse-dot"></span>
                            <span>Live Analytics Active</span>
                        </div>
                    </div>
                    <h2 class="saas-hero-title">Academic Operations Command</h2>
                    <p class="saas-hero-subtitle">
                        Real-time monitoring of workforce attendance, team deliverables, and departmental platform infrastructure.
                    </p>
                </div>
                
                <div class="col-xl-6 col-lg-7 mt-3 mt-lg-0 text-lg-end saas-hero-content">
                    <div class="saas-hero-actions">
                        <a href="<?php echo url('admin/employees'); ?>?action=add" class="btn btn-saas-dark">
                            <?php echo pika_icon('user-plus', '', 16); ?>
                            <span>Onboard Member</span>
                        </a>
                        <a href="<?php echo url('admin/tasks'); ?>" class="btn btn-saas-light">
                            <?php echo pika_icon('tasks', '', 16); ?>
                            <span>Manage Tasks</span>
                        </a>
                        <a href="<?php echo url('admin/reports'); ?>" class="btn btn-saas-light">
                            <?php echo pika_icon('reports', '', 16); ?>
                            <span>Reports</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Primary Metrics Row (Tiled Text, Squircle Icon, No Progress Bars) -->
        <div class="row g-4 mb-4">
            <!-- Metric 1: Total Employees -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="<?php echo url('admin/employees'); ?>" class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">WORKFORCE</span>
                        <div class="metric-icon-badge navy">
                            <?php echo pika_icon('users', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo number_format($totalEmployees); ?></div>
                    <div class="metric-label">Active Team Members</div>
                </a>
            </div>
            
            <!-- Metric 2: Today's Check-ins -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="<?php echo url('admin/attendance'); ?>" class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">HOURS & TURNOUT</span>
                        <div class="metric-icon-badge emerald">
                            <?php echo pika_icon('clock', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $attendanceRate; ?>%</div>
                    <div class="metric-label"><?php echo $todayAttendance; ?> of <?php echo $totalEmployees; ?> Active Today</div>
                </a>
            </div>
            
            <!-- Metric 3: Active Deliverables -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="<?php echo url('admin/tasks'); ?>" class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">DELIVERABLES</span>
                        <div class="metric-icon-badge sky">
                            <?php echo pika_icon('tasks', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $completedTasks; ?>/<?php echo $taskTotal; ?></div>
                    <div class="metric-label">Completed Deliverables</div>
                </a>
            </div>
            
            <!-- Metric 4: Execution Velocity -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="<?php echo url('admin/approvals'); ?>" class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">EXECUTION VELOCITY</span>
                        <div class="metric-icon-badge amber">
                            <?php echo pika_icon('trending-up', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $taskRate; ?>%</div>
                    <div class="metric-label"><?php echo $pendingApprovals; ?> Pending Verifications</div>
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
                        <a href="<?php echo url('admin/approvals'); ?>" class="btn btn-saas-dark btn-sm">
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
                                                        <div class="mt-1"><span class="saas-id-badge"><?php echo htmlspecialchars($pending['employee_id']); ?></span></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="saas-tag primary">
                                                    <?php echo pika_icon('building', '', 12); ?>
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
                        <a href="<?php echo url('admin/employees'); ?>" class="btn btn-saas-light btn-sm">
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
                                                <span class="saas-id-badge mt-1"><?php echo htmlspecialchars($emp['employee_id']); ?></span>
                                            </td>
                                            <td>
                                                <span class="saas-tag primary">
                                                    <?php echo pika_icon('building', '', 12); ?>
                                                    <?php echo htmlspecialchars($emp['domain_name'] ?? 'General'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted font-weight-500"><?php echo formatDate($emp['created_at'], 'M d, Y'); ?></small>
                                            </td>
                                            <td class="text-end">
                                                <span class="saas-tag <?php echo $emp['status'] === 'active' ? 'emerald' : 'rose'; ?>">
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
                    <div class="saas-card-body p-3">
                        <?php if (count($domainStats) > 0): ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($domainStats as $idx => $dom): ?>
                                    <?php 
                                        $share = $totalEmployees > 0 ? round(($dom['count'] / $totalEmployees) * 100) : 0;
                                    ?>
                                    <div class="d-flex align-items-center justify-content-between p-2 rounded-3" style="background: #F8FAFC; border: 1px solid #E2E8F0;">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="d-inline-flex align-items-center justify-content-center rounded-2" style="width: 32px; height: 32px; background: rgba(15, 23, 42, 0.06); color: #0F172A;">
                                                <?php echo pika_icon('building', '', 14); ?>
                                            </div>
                                            <div>
                                                <div class="font-weight-600 text-dark small"><?php echo htmlspecialchars($dom['name']); ?></div>
                                                <span class="saas-id-badge" style="font-size: 10px; padding: 2px 8px;"><?php echo htmlspecialchars($dom['code'] ?? 'DOM'); ?></span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <span class="saas-tag primary" style="font-size: 11px; padding: 3px 10px;">
                                                <?php echo $dom['count']; ?> Staff (<?php echo $share; ?>%)
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
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
                        <a href="<?php echo url('verify-certificate'); ?>" target="_blank" class="btn btn-saas-light w-100">
                            <span>Launch Verification Portal</span>
                            <i class="bi bi-box-arrow-up-right ms-1 small"></i>
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
