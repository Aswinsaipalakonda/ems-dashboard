<?php
/**
 * Employee Dashboard
 * Employee Management System
 */

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../config/config.php';
requireLogin();

// Process auto-checkout if applicable
checkAutoCheckout();

// Redirect admin to admin dashboard
if (isAdmin()) {
    header("Location: " . url("admin/dashboard"));
    exit;
}

$userId = $_SESSION['user_id'];

// Get employee details
$employee = fetchOne("SELECT * FROM employees WHERE id = ?", "i", [$userId]);

// Check if employee requires check-in
$requiresCheckin = $employee['requires_checkin'] ?? 1;

// Get today's attendance (only if requires check-in)
$todayAttendance = null;
$monthStats = null;
$recentAttendance = [];

if ($requiresCheckin) {
    $todayAttendance = fetchOne(
        "SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()",
        "i",
        [$userId]
    );

    // Get this month's attendance stats
    $monthStats = fetchOne(
        "SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_days,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_days,
            SEC_TO_TIME(SUM(TIME_TO_SEC(total_hours))) as total_hours
         FROM attendance 
         WHERE employee_id = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())",
        "i",
        [$userId]
    );
    
    // Get recent attendance
    $recentAttendanceResult = fetchAll(
        "SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 7",
        "i",
        [$userId]
    );
    $recentAttendance = is_array($recentAttendanceResult) ? $recentAttendanceResult : [];
}

// Get pending tasks
$pendingTasksResult = fetchAll(
    "SELECT * FROM tasks WHERE assigned_to = ? AND status IN ('not_started', 'in_progress', 'changes_requested') ORDER BY deadline ASC LIMIT 5",
    "i",
    [$userId]
);
$pendingTasks = is_array($pendingTasksResult) ? $pendingTasksResult : [];

// Get unread notifications
$notificationsResult = fetchAll(
    "SELECT * FROM notifications WHERE user_id = ? AND user_type = 'employee' ORDER BY created_at DESC LIMIT 5",
    "i",
    [$userId]
);
$notifications = is_array($notificationsResult) ? $notificationsResult : [];

$unreadCount = fetchOne(
    "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND user_type = 'employee' AND is_read = 0",
    "i",
    [$userId]
)['count'] ?? 0;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/employee-sidebar.php';
?>

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
                    <?php if ($unreadCount > 0): ?>
                    <span class="notification-badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notifications-dropdown">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Notifications</h6>
                        <?php if ($unreadCount > 0): ?>
                        <span class="badge bg-primary"><?php echo $unreadCount; ?> new</span>
                        <?php endif; ?>
                    </div>
                    <?php if (is_array($notifications) && count($notifications) > 0): ?>
                        <?php foreach (array_slice($notifications, 0, 3) as $notif): ?>
                        <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                            <div class="notification-icon bg-<?php echo $notif['type']; ?> text-white">
                                <i class="bi bi-bell"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title"><?php echo $notif['title']; ?></div>
                                <div class="notification-text"><?php echo substr($notif['message'], 0, 50); ?>...</div>
                                <div class="notification-time"><?php echo formatDateTime($notif['created_at']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="p-2 text-center border-top">
                            <a href="<?php echo url('employee/notifications'); ?>" class="text-primary">View All Notifications</a>
                        </div>
                    <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-bell-slash fs-1"></i>
                        <p class="mb-0 mt-2">No notifications</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="Avatar" class="user-avatar">
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['user_name']; ?></div>
                        <div class="user-role">Employee</div>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo url('employee/profile'); ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo url('logout'); ?>"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <!-- Content -->
    <div class="content-wrapper">
        <!-- SaaS Hero -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-xl-8 col-lg-7">
                    <div class="saas-hero-content">
                        <div class="saas-hero-pills">
                            <span class="saas-pill">
                                <?php echo pika_icon('sparkles', '', 14); ?>
                                Workspace &bull; <?php echo htmlspecialchars($employee['designation'] ?? 'Team Member'); ?>
                            </span>
                        </div>
                        <h2 class="saas-hero-title">
                            Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </h2>
                        <p class="saas-hero-subtitle">
                            Welcome to your personal Clientura workspace. Track work hours, view active assignments, and review performance.
                        </p>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-5 mt-3 mt-lg-0 text-lg-end">
                    <div class="d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                        <?php if ($requiresCheckin): ?>
                        <a href="<?php echo url('employee/checkin'); ?>" class="btn btn-saas-dark">
                            <?php echo pika_icon('camera', '', 15); ?>
                            <span><?php echo !$todayAttendance ? 'Log Check-In' : (!$todayAttendance['check_out_time'] ? 'Check Out' : 'Attendance Log'); ?></span>
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo url('employee/tasks'); ?>" class="btn btn-saas-light">
                            <?php echo pika_icon('tasks', '', 15); ?>
                            <span>My Tasks (<?php echo is_array($pendingTasks) ? count($pendingTasks) : 0; ?>)</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-4 mb-4">
            <?php if ($requiresCheckin): ?>
            <!-- Check-in Action Card -->
            <div class="col-lg-4">
                <div class="saas-card h-100 p-4 text-center d-flex flex-column justify-content-between">
                    <div>
                        <div class="metric-icon-badge <?php echo $todayAttendance ? ($todayAttendance['check_out_time'] ? 'green' : 'blue') : 'amber'; ?> mx-auto mb-3" style="width: 52px; height: 52px;">
                            <?php echo pika_icon($todayAttendance ? ($todayAttendance['check_out_time'] ? 'check-circle' : 'clock') : 'login', '', 24); ?>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">
                            <?php if (!$todayAttendance || !$todayAttendance['check_in_time']): ?>
                                Ready to Begin?
                            <?php elseif (!$todayAttendance['check_out_time']): ?>
                                Working Shift
                            <?php else: ?>
                                Day Complete!
                            <?php endif; ?>
                        </h5>
                        <div class="time-display fs-3 fw-bold font-monospace text-dark my-2">00:00:00</div>
                        <div class="date-display text-muted small mb-3"><?php echo date('l, d M Y'); ?></div>
                    </div>
                    
                    <div>
                        <?php if (!$todayAttendance || !$todayAttendance['check_in_time']): ?>
                            <a href="<?php echo url('employee/checkin'); ?>" class="btn btn-saas-dark w-100 py-2">
                                <?php echo pika_icon('camera', '', 16); ?>
                                <span>Check In Now</span>
                            </a>
                        <?php elseif (!$todayAttendance['check_out_time']): ?>
                            <a href="<?php echo url('employee/checkin'); ?>?action=checkout" class="btn btn-saas-dark w-100 py-2" style="background: #E11D48;">
                                <?php echo pika_icon('logout', '', 16); ?>
                                <span>Check Out Shift</span>
                            </a>
                        <?php else: ?>
                            <div class="p-2 rounded-3 bg-light border text-center">
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-semibold">
                                    <?php echo pika_icon('check', 'me-1', 12); ?> Total Hours: <?php echo $todayAttendance['total_hours'] ?? 'N/A'; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 4 Metric Cards -->
            <div class="col-lg-8">
                <div class="row g-4">
                    <div class="col-sm-6">
                        <div class="metric-card">
                            <div class="metric-card-top">
                                <span class="metric-tiled-title">DAYS THIS MONTH</span>
                                <div class="metric-icon-badge green">
                                    <?php echo pika_icon('calendar', '', 20); ?>
                                </div>
                            </div>
                            <div class="metric-value"><?php echo $monthStats['approved_days'] ?? 0; ?></div>
                            <div class="metric-label">Approved Shift Days</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="metric-card">
                            <div class="metric-card-top">
                                <span class="metric-tiled-title">PENDING REVIEWS</span>
                                <div class="metric-icon-badge amber">
                                    <?php echo pika_icon('clock', '', 20); ?>
                                </div>
                            </div>
                            <div class="metric-value"><?php echo $monthStats['pending_days'] ?? 0; ?></div>
                            <div class="metric-label">Awaiting Supervisor</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="metric-card">
                            <div class="metric-card-top">
                                <span class="metric-tiled-title">LOGGED HOURS</span>
                                <div class="metric-icon-badge blue">
                                    <?php echo pika_icon('hourglass', '', 20); ?>
                                </div>
                            </div>
                            <div class="metric-value"><?php echo $monthStats['total_hours'] ? substr($monthStats['total_hours'], 0, 5) : '00:00'; ?></div>
                            <div class="metric-label">Monthly Cumulative</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="metric-card">
                            <div class="metric-card-top">
                                <span class="metric-tiled-title">ACTIVE TASKS</span>
                                <div class="metric-icon-badge purple">
                                    <?php echo pika_icon('tasks', '', 20); ?>
                                </div>
                            </div>
                            <div class="metric-value"><?php echo is_array($pendingTasks) ? count($pendingTasks) : 0; ?></div>
                            <div class="metric-label">Assigned Work Items</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Non-checkin employee: Full-width stats -->
            <div class="col-12">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="metric-card">
                            <div class="metric-card-top">
                                <span class="metric-tiled-title">PENDING TASKS</span>
                                <div class="metric-icon-badge blue">
                                    <?php echo pika_icon('tasks', '', 20); ?>
                                </div>
                            </div>
                            <div class="metric-value"><?php echo is_array($pendingTasks) ? count($pendingTasks) : 0; ?></div>
                            <div class="metric-label">To-Do Items</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-card">
                            <div class="metric-card-top">
                                <span class="metric-tiled-title">COMPLETED TASKS</span>
                                <div class="metric-icon-badge green">
                                    <?php echo pika_icon('check-circle', '', 20); ?>
                                </div>
                            </div>
                            <?php
                            $completedTasks = fetchOne(
                                "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'completed'",
                                "i",
                                [$userId]
                            )['count'] ?? 0;
                            ?>
                            <div class="metric-value"><?php echo $completedTasks; ?></div>
                            <div class="metric-label">Finished Deliverables</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-card">
                            <div class="metric-card-top">
                                <span class="metric-tiled-title">NOTIFICATIONS</span>
                                <div class="metric-icon-badge amber">
                                    <?php echo pika_icon('bell', '', 20); ?>
                                </div>
                            </div>
                            <div class="metric-value"><?php echo $unreadCount; ?></div>
                            <div class="metric-label">Unread Alerts</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="row g-4">
            <!-- My Tasks -->
            <div class="col-lg-6">
                <div class="saas-card h-100">
                    <div class="saas-card-header">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="saas-card-title">
                                <?php echo pika_icon('tasks', 'text-primary', 18); ?>
                                My Active Tasks
                            </h5>
                            <?php if (is_array($pendingTasks) && count($pendingTasks) > 0): ?>
                            <span class="badge bg-light text-dark border px-2 py-1 rounded-pill small">
                                <?php echo count($pendingTasks); ?> pending
                            </span>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo url('employee/tasks'); ?>" class="btn btn-sm btn-saas-light">
                            <span>All Tasks</span>
                            <?php echo pika_icon('arrow-right', '', 12); ?>
                        </a>
                    </div>
                    <div class="p-0">
                        <?php if (is_array($pendingTasks) && count($pendingTasks) > 0): ?>
                        <div class="list-group list-group-flush border-0">
                            <?php foreach ($pendingTasks as $task): ?>
                            <div class="list-group-item p-3 px-4 d-flex align-items-center justify-content-between border-bottom">
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($task['title']); ?></div>
                                    <small class="text-muted d-flex align-items-center gap-2">
                                        <span>Due: <?php echo $task['deadline'] ? formatDate($task['deadline']) : 'No deadline'; ?></span>
                                    </small>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="saas-tag <?php 
                                        echo match($task['priority']) {
                                            'urgent' => 'rose',
                                            'high' => 'amber',
                                            default => 'primary'
                                        };
                                    ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <?php echo pika_icon('check-circle', 'opacity-25 d-block mx-auto mb-2 text-success', 40); ?>
                            <p class="mb-0 fw-semibold text-dark">All Caught Up!</p>
                            <small class="text-muted">You have no pending assignments on your plate.</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($requiresCheckin): ?>
            <!-- Recent Attendance -->
            <div class="col-lg-6">
                <div class="saas-card h-100">
                    <div class="saas-card-header">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="saas-card-title">
                                <?php echo pika_icon('calendar', 'text-primary', 18); ?>
                                Recent Attendance Log
                            </h5>
                        </div>
                        <a href="<?php echo url('employee/attendance'); ?>" class="btn btn-sm btn-saas-light">
                            <span>View Full Log</span>
                            <?php echo pika_icon('arrow-right', '', 12); ?>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="saas-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (is_array($recentAttendance) && count($recentAttendance) > 0): ?>
                                    <?php foreach ($recentAttendance as $att): ?>
                                    <tr>
                                        <td class="fw-semibold text-dark"><?php echo formatDate($att['date'], 'D, M j'); ?></td>
                                        <td><?php echo $att['check_in_time'] ? formatTime($att['check_in_time']) : '-'; ?></td>
                                        <td><?php echo $att['check_out_time'] ? formatTime($att['check_out_time']) : '-'; ?></td>
                                        <td>
                                            <span class="saas-tag <?php 
                                                echo match($att['status']) {
                                                    'approved' => 'emerald',
                                                    'pending' => 'amber',
                                                    default => 'rose'
                                                };
                                            ?>">
                                                <?php echo ucfirst($att['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <?php echo pika_icon('calendar', 'opacity-25 d-block mx-auto mb-2', 40); ?>
                                            <p class="mb-0">No attendance records found.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Notifications for non-checkin employees -->
            <div class="col-lg-6">
                <div class="saas-card h-100">
                    <div class="saas-card-header">
                        <h5 class="saas-card-title">
                            <?php echo pika_icon('bell', 'text-primary', 18); ?>
                            Recent Alerts
                        </h5>
                        <a href="<?php echo url('employee/notifications'); ?>" class="btn btn-sm btn-saas-light">
                            <span>All Alerts</span>
                            <?php echo pika_icon('arrow-right', '', 12); ?>
                        </a>
                    </div>
                    <div class="p-0">
                        <?php if (is_array($notifications) && count($notifications) > 0): ?>
                        <div class="list-group list-group-flush border-0">
                            <?php foreach (array_slice($notifications, 0, 5) as $notif): ?>
                            <div class="list-group-item p-3 px-4 border-bottom">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($notif['title']); ?></div>
                                <p class="text-muted small mb-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                <small class="text-secondary"><?php echo timeAgo($notif['created_at']); ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <?php echo pika_icon('bell', 'opacity-25 d-block mx-auto mb-2', 40); ?>
                            <p class="mb-0">No alerts or notifications.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
