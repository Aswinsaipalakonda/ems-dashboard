<?php
/**
 * Manager - My Attendance
 * Employee Management System
 */

$pageTitle = 'My Attendance';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (!isManager() && !isHR()) {
    header("Location: " . APP_URL . "/employee/dashboard.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Filters
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$startDate = "$year-$month-01";
$endDate = date('Y-m-t', strtotime($startDate));

$attendances = fetchAll(
    "SELECT * FROM attendance WHERE employee_id = ? AND date BETWEEN ? AND ? ORDER BY date DESC",
    "iss",
    [$userId, $startDate, $endDate]
);

// Stats
$totalDays = count($attendances);
$approvedDays = count(array_filter($attendances, fn($a) => $a['status'] === 'approved'));
$pendingDays = count(array_filter($attendances, fn($a) => $a['status'] === 'pending'));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/manager-sidebar.php';
?>

<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">My Attendance</h1>
        </div>
    </header>

    <div class="content-wrapper">
        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?php echo $totalDays; ?></h3>
                        <p class="mb-0">Total Days</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?php echo $approvedDays; ?></h3>
                        <p class="mb-0">Approved</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?php echo $pendingDays; ?></h3>
                        <p class="mb-0">Pending</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <select name="month" class="form-select">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select name="year" class="form-select">
                            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance List -->
        <div class="card">
            <div class="card-body p-0">
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
                            <?php if (count($attendances) > 0): ?>
                            <?php foreach ($attendances as $att): ?>
                            <tr>
                                <td><?php echo formatDate($att['date'], 'D, d M Y'); ?></td>
                                <td><?php echo formatTime($att['check_in_time']); ?></td>
                                <td><?php echo $att['check_out_time'] ? formatTime($att['check_out_time']) : '-'; ?></td>
                                <td><?php echo $att['total_hours'] ?? '-'; ?></td>
                                <td><span class="badge badge-<?php echo $att['status']; ?>"><?php echo ucfirst($att['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No attendance records found</td>
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
