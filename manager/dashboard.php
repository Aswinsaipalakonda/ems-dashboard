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
        <!-- SaaS Hero -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-xl-8 col-lg-7">
                    <div class="saas-hero-content">
                        <div class="saas-hero-pills">
                            <span class="saas-pill">
                                <?php echo pika_icon('sparkles', '', 14); ?>
                                Management Hub &bull; <?php echo htmlspecialchars($roleName); ?>
                            </span>
                        </div>
                        <h2 class="saas-hero-title">
                            Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </h2>
                        <p class="saas-hero-subtitle">
                            Monitor organizational operations, review daily check-in approvals, and oversee workforce activity across domains.
                        </p>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-5 mt-3 mt-lg-0 text-lg-end">
                    <div class="d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                        <a href="<?php echo url('manager/approvals'); ?>" class="btn btn-saas-light">
                            <?php echo pika_icon('check-circle', '', 15); ?>
                            <span>Approvals (<?php echo $pendingApprovals; ?>)</span>
                        </a>
                        <a href="<?php echo url('manager/tasks'); ?>" class="btn btn-saas-dark">
                            <?php echo pika_icon('tasks', '', 15); ?>
                            <span>Assign Tasks</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($requiresCheckin): ?>
        <!-- My Check-in Status Card -->
        <div class="saas-card mb-4">
            <div class="p-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="metric-icon-badge <?php echo $myAttendance ? ($myAttendance['check_out_time'] ? 'green' : 'blue') : 'amber'; ?>" style="width: 44px; height: 44px;">
                        <?php echo pika_icon($myAttendance ? ($myAttendance['check_out_time'] ? 'check-circle' : 'clock') : 'login', '', 20); ?>
                    </div>
                    <div>
                        <?php if (!$myAttendance): ?>
                            <h6 class="fw-bold text-dark mb-0">Daily Check-In Pending</h6>
                            <small class="text-muted">You haven't logged your geofenced check-in for today yet.</small>
                        <?php elseif (!$myAttendance['check_out_time']): ?>
                            <h6 class="fw-bold text-dark mb-0">Active Shift Logged</h6>
                            <small class="text-muted">Working since <?php echo formatTime($myAttendance['check_in_time']); ?> &bull; Remember to check out before shift end.</small>
                        <?php else: ?>
                            <h6 class="fw-bold text-success mb-0">Shift Completed Today</h6>
                            <small class="text-muted">Logged out at <?php echo formatTime($myAttendance['check_out_time']); ?>.</small>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="<?php echo url('manager/checkin'); ?>" class="btn btn-saas-dark btn-sm">
                    <?php echo pika_icon('camera', '', 14); ?>
                    <span><?php echo !$myAttendance ? 'Check In Now' : (!$myAttendance['check_out_time'] ? 'Check Out' : 'View Shift Log'); ?></span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 4 Metric Cards -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">TOTAL STAFF</span>
                        <div class="metric-icon-badge blue">
                            <?php echo pika_icon('users', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $totalEmployees; ?></div>
                    <div class="metric-label">Active Team Members</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">PRESENT TODAY</span>
                        <div class="metric-icon-badge green">
                            <?php echo pika_icon('check-circle', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $todayPresent; ?></div>
                    <div class="metric-label">Checked-In Workforce</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">PENDING REVIEWS</span>
                        <div class="metric-icon-badge amber">
                            <?php echo pika_icon('clock', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $pendingApprovals; ?></div>
                    <div class="metric-label">Check-Ins Requiring Review</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">ACTIVE TASKS</span>
                        <div class="metric-icon-badge purple">
                            <?php echo pika_icon('tasks', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $activeTasks; ?></div>
                    <div class="metric-label">In-Progress Assignments</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Pending Approvals -->
            <div class="col-lg-6">
                <div class="saas-card h-100">
                    <div class="saas-card-header">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="saas-card-title">
                                <?php echo pika_icon('clock', 'text-warning', 18); ?>
                                Pending Check-In Approvals
                            </h5>
                            <?php if ($pendingApprovals > 0): ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 small">
                                <?php echo $pendingApprovals; ?> pending
                            </span>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo url('manager/approvals'); ?>" class="btn btn-sm btn-saas-light">
                            <span>Review All</span>
                            <?php echo pika_icon('arrow-right', '', 12); ?>
                        </a>
                    </div>
                    <div class="p-0">
                        <?php if (count($pendingList) > 0): ?>
                        <div class="list-group list-group-flush border-0">
                            <?php foreach ($pendingList as $pending): ?>
                            <div class="list-group-item p-3 px-4 d-flex align-items-center justify-content-between border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?php echo getAvatar($pending['avatar'] ?? ''); ?>" class="saas-user-avatar" alt="">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($pending['name']); ?></div>
                                        <small class="text-muted d-flex align-items-center gap-2">
                                            <span class="saas-id-badge" style="font-size: 0.68rem; padding: 2px 8px;"><?php echo htmlspecialchars($pending['emp_id']); ?></span>
                                            <span><?php echo formatDate($pending['date']); ?> at <?php echo formatTime($pending['check_in_time']); ?></span>
                                        </small>
                                    </div>
                                </div>
                                <a href="<?php echo url('manager/approvals'); ?>?action=view&id=<?php echo $pending['id']; ?>" class="btn btn-sm btn-saas-dark">
                                    <?php echo pika_icon('eye', '', 14); ?>
                                    <span>Review</span>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <?php echo pika_icon('check-circle', 'opacity-25 d-block mx-auto mb-2 text-success', 40); ?>
                            <p class="mb-0 fw-semibold text-dark">All Caught Up!</p>
                            <small class="text-muted">No pending employee check-ins currently awaiting review.</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Attendance Chart -->
            <div class="col-lg-6">
                <div class="saas-card h-100">
                    <div class="saas-card-header">
                        <h5 class="saas-card-title">
                            <?php echo pika_icon('chart', 'text-primary', 18); ?>
                            Workforce Attendance Trend
                        </h5>
                        <span class="text-muted small">Last 7 Days</span>
                    </div>
                    <div class="saas-card-body">
                        <div style="height: 240px; position: relative;">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Employees Table -->
        <div class="saas-card">
            <div class="saas-card-header">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="saas-card-title">
                        <?php echo pika_icon('users', 'text-primary', 18); ?>
                        Recent Workforce Joiners
                    </h5>
                    <span class="badge bg-light text-dark border px-2 py-1 rounded-pill small">
                        <?php echo count($recentEmployees); ?> records
                    </span>
                </div>
                <a href="<?php echo url('manager/employees'); ?>" class="btn btn-sm btn-saas-light">
                    <span>Directory</span>
                    <?php echo pika_icon('arrow-right', '', 12); ?>
                </a>
            </div>
            <div class="table-responsive">
                <table class="saas-table">
                    <thead>
                        <tr>
                            <th>Team Member</th>
                            <th>Employee ID</th>
                            <th>Domain / Dept</th>
                            <th>Designation</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recentEmployees) > 0): ?>
                            <?php foreach ($recentEmployees as $emp): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?php echo getAvatar($emp['avatar'] ?? ''); ?>" class="saas-user-avatar" alt="">
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($emp['name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($emp['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="saas-id-badge"><?php echo htmlspecialchars($emp['employee_id']); ?></span>
                                </td>
                                <td>
                                    <span class="saas-tag primary">
                                        <?php echo htmlspecialchars($emp['domain_name'] ?? 'General'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark fw-semibold small"><?php echo htmlspecialchars($emp['designation'] ?? $emp['role_name'] ?? 'Employee'); ?></span>
                                </td>
                                <td>
                                    <span class="saas-tag <?php echo $emp['status'] === 'active' ? 'emerald' : 'rose'; ?>">
                                        <?php echo ucfirst($emp['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <?php echo pika_icon('users', 'opacity-25 d-block mx-auto mb-2', 40); ?>
                                    <p class="mb-0">No employees registered yet.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
