<?php
/**
 * Manager/HR Dashboard
 * Employee Management System
 */

$pageTitle = 'Manager Dashboard';
require_once __DIR__ . '/../config/config.php';
requireLogin();

// Process auto-checkout if applicable
checkAutoCheckout();

// Check if user has management access
if (!isManager() && !isHR()) {
    if (isAdmin()) {
        header("Location: " . url("admin/dashboard"));
    } elseif (isTeamLead()) {
        header("Location: " . url("teamlead/dashboard"));
    } else {
        header("Location: " . url("employee/dashboard"));
    }
    exit;
}

// Get statistics
$totalEmployees = fetchOne("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")['count'] ?? 0;
$pendingApprovals = fetchOne("SELECT COUNT(*) as count FROM attendance WHERE status = 'pending'")['count'] ?? 0;
$todayPresent = fetchOne("SELECT COUNT(*) as count FROM attendance WHERE date = CURDATE()")['count'] ?? 0;
$activeTasks = fetchOne("SELECT COUNT(*) as count FROM tasks WHERE status IN ('not_started', 'in_progress')")['count'] ?? 0;

// Get current user's employee record to check requires_checkin
$currentEmployee = fetchOne("SELECT * FROM employees WHERE id = ?", "i", [$_SESSION['user_id']]);
$requiresCheckin = $currentEmployee['requires_checkin'] ?? 1;

// Get recent employees
$recentEmployees = fetchAll(
    "SELECT e.*, d.name as domain_name, r.name as role_name 
     FROM employees e 
     LEFT JOIN domains d ON e.domain_id = d.id 
     LEFT JOIN roles r ON e.role_id = r.id 
     ORDER BY e.created_at DESC LIMIT 5"
);

// Get pending approvals
$pendingList = fetchAll(
    "SELECT a.*, e.name, e.employee_id as emp_id, d.name as domain_name, e.avatar
     FROM attendance a 
     JOIN employees e ON a.employee_id = e.id 
     LEFT JOIN domains d ON e.domain_id = d.id
     WHERE a.status = 'pending' 
     ORDER BY a.created_at DESC LIMIT 5"
);

// Get attendance trend (last 7 days)
$attendanceTrend = fetchAll(
    "SELECT DATE(date) as date, COUNT(*) as count 
     FROM attendance 
     WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
     GROUP BY DATE(date) 
     ORDER BY date ASC"
);

// Get my own attendance for today (only if requires check-in)
$myAttendance = null;
if ($requiresCheckin) {
    $myAttendance = fetchOne(
        "SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()",
        "i",
        [$_SESSION['user_id']]
    );
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/manager-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="page-title"><?php echo isHR() ? 'HR' : 'Manager'; ?> Dashboard</h1>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <button class="header-icon-btn" data-bs-toggle="dropdown">
                    <i class="bi bi-bell"></i>
                    <?php if ($pendingApprovals > 0): ?>
                    <span class="notification-badge"><?php echo $pendingApprovals; ?></span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <?php if ($pendingApprovals > 0): ?>
                    <li><a class="dropdown-item" href="<?php echo url('manager/approvals'); ?>">
                        <i class="bi bi-clock-history me-2"></i><?php echo $pendingApprovals; ?> pending approvals
                    </a></li>
                    <?php else: ?>
                    <li><span class="dropdown-item text-muted">No new notifications</span></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="dropdown">
                <button class="header-user-btn" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="User" class="avatar avatar-sm">
                    <span class="d-none d-md-inline ms-2"><?php echo $_SESSION['user_name']; ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo url('manager/profile'); ?>">
                        <i class="bi bi-person me-2"></i>Profile
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo url('logout'); ?>">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Dashboard Content -->
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
                        You have <strong><?php echo $totalEmployees; ?></strong> employees and <strong><?php echo $pendingApprovals; ?></strong> pending approvals to review.
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <p class="mb-0 text-white-50"><?php echo date('l, F j, Y'); ?></p>
                </div>
            </div>
        </div>

        <?php if ($requiresCheckin): ?>
        <!-- My Status Card -->
        <div class="attendance-status-card mb-4">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="status-icon <?php echo $myAttendance ? ($myAttendance['check_out_time'] ? 'completed' : 'working') : 'pending'; ?>">
                        <i class="bi bi-<?php echo $myAttendance ? ($myAttendance['check_out_time'] ? 'check-circle-fill' : 'clock-fill') : 'box-arrow-in-right'; ?>"></i>
                    </div>
                    <div class="ms-3">
                        <?php if (!$myAttendance): ?>
                            <h6 class="mb-0">You haven't checked in today</h6>
                            <small class="text-muted">Click the button to check in</small>
                        <?php elseif (!$myAttendance['check_out_time']): ?>
                            <h6 class="mb-0">Working since <?php echo formatTime($myAttendance['check_in_time']); ?></h6>
                            <small class="text-muted">Don't forget to check out!</small>
                        <?php else: ?>
                            <h6 class="mb-0 text-success">Day Complete!</h6>
                            <small class="text-muted">Checked out at <?php echo formatTime($myAttendance['check_out_time']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="<?php echo url('manager/checkin'); ?>" class="btn btn-gradient btn-sm">
                    <i class="bi bi-camera-video me-1"></i>
                    <?php echo !$myAttendance ? 'Check In' : (!$myAttendance['check_out_time'] ? 'Check Out' : 'View'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stats-info">
                            <h3><?php echo $totalEmployees; ?></h3>
                            <p>Total Employees</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stats-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-person-check"></i>
                        </div>
                        <div class="stats-info">
                            <h3><?php echo $todayPresent; ?></h3>
                            <p>Present Today</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stats-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="stats-info">
                            <h3><?php echo $pendingApprovals; ?></h3>
                            <p>Pending Approvals</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stats-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-list-task"></i>
                        </div>
                        <div class="stats-info">
                            <h3><?php echo $activeTasks; ?></h3>
                            <p>Active Tasks</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Pending Approvals -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Pending Approvals</h5>
                        <a href="<?php echo url('manager/approvals'); ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($pendingList) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($pendingList as $pending): ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo getAvatar($pending['avatar']); ?>" class="avatar avatar-sm me-3" alt="">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"><?php echo $pending['name']; ?></div>
                                        <small class="text-muted">
                                            <?php echo formatDate($pending['date']); ?> at <?php echo formatTime($pending['check_in_time']); ?>
                                        </small>
                                    </div>
                                    <a href="<?php echo url('manager/approvals'); ?>?action=view&id=<?php echo $pending['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle display-4"></i>
                            <p class="mt-2">No pending approvals</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Attendance Chart -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Attendance Trend (Last 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Employees -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0"><i class="bi bi-people me-2"></i>Recent Employees</h5>
                        <a href="<?php echo url('manager/employees'); ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($recentEmployees) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Domain</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentEmployees as $emp): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo getAvatar($emp['avatar']); ?>" class="avatar avatar-sm me-2" alt="">
                                                <div>
                                                    <div class="fw-semibold"><?php echo $emp['name']; ?></div>
                                                    <small class="text-muted"><?php echo $emp['email']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $emp['domain_name'] ?? 'N/A'; ?></td>
                                        <td><span class="badge bg-light text-dark"><?php echo $emp['role_name'] ?? 'Employee'; ?></span></td>
                                        <td>
                                            <span class="badge badge-<?php echo $emp['status']; ?>">
                                                <?php echo ucfirst($emp['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-people display-4"></i>
                            <p class="mt-2">No employees found</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Attendance Chart - Modern Bar Chart Style
const attendanceData = <?php echo json_encode($attendanceTrend); ?>;
const labels = attendanceData.map(item => {
    const date = new Date(item.date);
    return date.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric' });
});
const data = attendanceData.map(item => parseInt(item.count));

const ctx = document.getElementById('attendanceChart').getContext('2d');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels.length > 0 ? labels : ['No data'],
        datasets: [{
            label: 'Check-ins',
            data: data.length > 0 ? data : [0],
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
