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
        <!-- Welcome Banner -->
        <div class="welcome-banner mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <span class="welcome-wave">👋</span>
                        Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>, <?php echo $_SESSION['user_name']; ?>!
                    </h2>
                    <p class="mb-0 opacity-75">
                        <?php if ($requiresCheckin): ?>
                            <?php if ($todayAttendance && $todayAttendance['check_in_time']): ?>
                                You checked in at <?php echo formatTime($todayAttendance['check_in_time']); ?>
                                <?php if ($todayAttendance['check_out_time']): ?>
                                    and checked out at <?php echo formatTime($todayAttendance['check_out_time']); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                Don't forget to check in for today!
                            <?php endif; ?>
                        <?php else: ?>
                            Welcome back! You have <?php echo is_array($pendingTasks) ? count($pendingTasks) : 0; ?> pending task(s).
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <p class="mb-0 text-white-50"><?php echo date('l, F j, Y'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <?php if ($requiresCheckin): ?>
            <!-- Check-in Card -->
            <div class="col-lg-4 mb-4">
                <div class="checkin-card">
                    <h2>
                        <?php if (!$todayAttendance || !$todayAttendance['check_in_time']): ?>
                            Ready to Start?
                        <?php elseif (!$todayAttendance['check_out_time']): ?>
                            Working...
                        <?php else: ?>
                            Day Complete!
                        <?php endif; ?>
                    </h2>
                    <div class="time-display">00:00:00</div>
                    <div class="date-display"></div>
                    
                    <?php if (!$todayAttendance || !$todayAttendance['check_in_time']): ?>
                        <a href="<?php echo url('employee/checkin'); ?>" class="btn btn-checkin">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Check In
                        </a>
                    <?php elseif (!$todayAttendance['check_out_time']): ?>
                        <a href="<?php echo url('employee/checkin'); ?>?action=checkout" class="btn btn-checkout">
                            <i class="bi bi-box-arrow-right me-2"></i>Check Out
                        </a>
                    <?php else: ?>
                        <div class="mt-2">
                            <i class="bi bi-check-circle-fill fs-3"></i>
                            <p class="mb-0 mt-2">Total: <?php echo $todayAttendance['total_hours'] ?? 'N/A'; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="col-lg-8">
                <div class="row">
                    <div class="col-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon success">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div class="stats-info">
                                <h3><?php echo $monthStats['approved_days'] ?? 0; ?></h3>
                                <p>Days This Month</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon warning">
                                <i class="bi bi-clock"></i>
                            </div>
                            <div class="stats-info">
                                <h3><?php echo $monthStats['pending_days'] ?? 0; ?></h3>
                                <p>Pending Approvals</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon primary">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div class="stats-info">
                                <h3><?php echo $monthStats['total_hours'] ? substr($monthStats['total_hours'], 0, 5) : '00:00'; ?></h3>
                                <p>Hours This Month</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon info">
                                <i class="bi bi-list-task"></i>
                            </div>
                            <div class="stats-info">
                                <h3><?php echo is_array($pendingTasks) ? count($pendingTasks) : 0; ?></h3>
                                <p>Pending Tasks</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Non-checkin employee: Full-width stats -->
            <div class="col-12">
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon info">
                                <i class="bi bi-list-task"></i>
                            </div>
                            <div class="stats-info">
                                <h3><?php echo is_array($pendingTasks) ? count($pendingTasks) : 0; ?></h3>
                                <p>Pending Tasks</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon success">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="stats-info">
                                <?php
                                $completedTasks = fetchOne(
                                    "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'completed'",
                                    "i",
                                    [$userId]
                                )['count'] ?? 0;
                                ?>
                                <h3><?php echo $completedTasks; ?></h3>
                                <p>Completed Tasks</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon warning">
                                <i class="bi bi-bell"></i>
                            </div>
                            <div class="stats-info">
                                <h3><?php echo $unreadCount; ?></h3>
                                <p>Notifications</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="row">
            <!-- My Tasks -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list-task me-2"></i>My Tasks</h5>
                        <a href="<?php echo url('employee/tasks'); ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (is_array($pendingTasks) && count($pendingTasks) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($pendingTasks as $task): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold"><?php echo $task['title']; ?></div>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar me-1"></i>Due: <?php echo $task['deadline'] ? formatDate($task['deadline']) : 'No deadline'; ?>
                                    </small>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge priority-<?php echo $task['priority']; ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                    <span class="badge status-<?php echo $task['status']; ?>">
                                        <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?>
                                    </span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div class="empty-state py-5">
                            <i class="bi bi-check-circle"></i>
                            <h4>No Pending Tasks</h4>
                            <p>You're all caught up!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($requiresCheckin): ?>
            <!-- Recent Attendance -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Recent Attendance</h5>
                        <a href="<?php echo url('employee/attendance'); ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (is_array($recentAttendance) && count($recentAttendance) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAttendance as $att): ?>
                                    <tr>
                                        <td><?php echo formatDate($att['date'], 'D, M j'); ?></td>
                                        <td><?php echo $att['check_in_time'] ? formatTime($att['check_in_time']) : '-'; ?></td>
                                        <td><?php echo $att['check_out_time'] ? formatTime($att['check_out_time']) : '-'; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $att['status']; ?>">
                                                <?php echo ucfirst($att['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty-state py-5">
                            <i class="bi bi-calendar-x"></i>
                            <h4>No Attendance Records</h4>
                            <p>Start by checking in today!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Notifications for non-checkin employees -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-bell me-2"></i>Recent Notifications</h5>
                        <a href="<?php echo url('employee/notifications'); ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (is_array($notifications) && count($notifications) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach (array_slice($notifications, 0, 5) as $notif): ?>
                            <li class="list-group-item <?php echo !$notif['is_read'] ? 'bg-light' : ''; ?>">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0 me-3">
                                        <span class="badge bg-<?php echo $notif['type'] === 'success' ? 'success' : ($notif['type'] === 'warning' ? 'warning' : 'info'); ?> p-2">
                                            <i class="bi bi-bell"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"><?php echo htmlspecialchars($notif['title']); ?></div>
                                        <small class="text-muted"><?php echo timeAgo($notif['created_at']); ?></small>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div class="empty-state py-5">
                            <i class="bi bi-bell-slash"></i>
                            <h4>No Notifications</h4>
                            <p>You're all caught up!</p>
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
