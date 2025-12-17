<?php
/**
 * Team Lead - My Attendance
 * Employee Management System
 */

$pageTitle = 'My Attendance';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (!isTeamLead()) {
    header("Location: " . APP_URL . "/employee/dashboard.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Filters
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$startDate = "$year-$month-01";
$endDate = date('Y-m-t', strtotime($startDate));

// Get attendance records for the month
$attendances = fetchAll(
    "SELECT * FROM attendance WHERE employee_id = ? AND date BETWEEN ? AND ? ORDER BY date DESC",
    "iss",
    [$userId, $startDate, $endDate]
);

// Calculate statistics
$stats = [
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'half_day' => 0,
    'total_hours' => 0
];

foreach ($attendances as $att) {
    if ($att['status'] === 'present') $stats['present']++;
    elseif ($att['status'] === 'absent') $stats['absent']++;
    elseif ($att['status'] === 'late') $stats['late']++;
    elseif ($att['status'] === 'half-day') $stats['half_day']++;
    $stats['total_hours'] += $att['total_hours'] ?? 0;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/teamlead-sidebar.php';
?>

<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">My Attendance</h1>
        </div>
    </header>

    <div class="content-wrapper">
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Month</label>
                        <select name="month" class="form-select">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo sprintf('%02d', $m); ?>" <?php echo $month == sprintf('%02d', $m) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Year</label>
                        <select name="year" class="form-select">
                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Filter</button>
                        <a href="my-attendance.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-success-subtle text-success mx-auto mb-2">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h3 class="mb-0"><?php echo $stats['present']; ?></h3>
                        <small class="text-muted">Present Days</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-danger-subtle text-danger mx-auto mb-2">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <h3 class="mb-0"><?php echo $stats['absent']; ?></h3>
                        <small class="text-muted">Absent Days</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-warning-subtle text-warning mx-auto mb-2">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <h3 class="mb-0"><?php echo $stats['late']; ?></h3>
                        <small class="text-muted">Late Days</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="stat-icon bg-primary-subtle text-primary mx-auto mb-2">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <h3 class="mb-0"><?php echo round($stats['total_hours'], 1); ?></h3>
                        <small class="text-muted">Total Hours</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Records -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Attendance Records - <?php echo date('F Y', strtotime($startDate)); ?></h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Total Hours</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($attendances) > 0): ?>
                            <?php foreach ($attendances as $att): ?>
                            <tr>
                                <td><?php echo formatDate($att['date']); ?></td>
                                <td><?php echo date('l', strtotime($att['date'])); ?></td>
                                <td><?php echo formatTime($att['check_in_time']); ?></td>
                                <td><?php echo $att['check_out_time'] ? formatTime($att['check_out_time']) : '-'; ?></td>
                                <td><?php echo $att['total_hours'] ?? '-'; ?></td>
                                <td><span class="badge badge-<?php echo $att['status']; ?>"><?php echo ucfirst($att['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No attendance records found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
