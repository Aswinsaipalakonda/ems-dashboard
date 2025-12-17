<?php
/**
 * Employee Attendance History
 * Employee Management System
 */

$pageTitle = 'My Attendance';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (isAdmin()) {
    header("Location: " . APP_URL . "/admin/attendance.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Get filter parameters
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// Get attendance records for selected month
$attendance = fetchAll(
    "SELECT * FROM attendance 
     WHERE employee_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
     ORDER BY date DESC",
    "iii",
    [$userId, $month, $year]
);

// Get monthly summary
$summary = fetchOne(
    "SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SEC_TO_TIME(SUM(TIME_TO_SEC(total_hours))) as total_hours
     FROM attendance 
     WHERE employee_id = ? AND MONTH(date) = ? AND YEAR(date) = ?",
    "iii",
    [$userId, $month, $year]
);

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
            <h1 class="page-title">My Attendance</h1>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="Avatar" class="user-avatar">
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['user_name']; ?></div>
                        <div class="user-role">Employee</div>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/employee/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <!-- Content -->
    <div class="content-wrapper">
        <!-- Month Selector -->
        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-end" method="GET">
                    <div class="col-md-3">
                        <label class="form-label">Month</label>
                        <select class="form-select" name="month">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $month == $i ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <select class="form-select" name="year">
                            <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $year == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>View
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Summary Stats -->
        <div class="row mb-4">
            <div class="col-6 col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon primary">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?php echo $summary['total_days'] ?? 0; ?></h3>
                        <p>Total Days</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?php echo $summary['approved'] ?? 0; ?></h3>
                        <p>Approved</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon warning">
                        <i class="bi bi-clock"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?php echo $summary['pending'] ?? 0; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon info">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?php echo $summary['total_hours'] ? substr($summary['total_hours'], 0, 5) : '00:00'; ?></h3>
                        <p>Total Hours</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Attendance Records -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-check me-2"></i>
                    Attendance for <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($attendance) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Total Hours</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance as $att): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo formatDate($att['date'], 'D, M j'); ?></div>
                                </td>
                                <td>
                                    <?php if ($att['check_in_time']): ?>
                                    <span class="text-success">
                                        <i class="bi bi-box-arrow-in-right me-1"></i>
                                        <?php echo formatTime($att['check_in_time']); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($att['check_out_time']): ?>
                                    <span class="text-danger">
                                        <i class="bi bi-box-arrow-right me-1"></i>
                                        <?php echo formatTime($att['check_out_time']); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($att['total_hours']): ?>
                                    <span class="badge bg-light text-dark">
                                        <i class="bi bi-clock me-1"></i><?php echo $att['total_hours']; ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $att['status']; ?>">
                                        <?php echo ucfirst($att['status']); ?>
                                    </span>
                                    <?php if ($att['admin_remarks']): ?>
                                    <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($att['admin_remarks']); ?>"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state py-5">
                    <i class="bi bi-calendar-x"></i>
                    <h4>No Records Found</h4>
                    <p>No attendance records for <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
