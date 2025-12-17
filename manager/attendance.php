<?php
/**
 * Manager - Attendance Records
 * Employee Management System
 */

$pageTitle = 'Attendance Records';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (!isManager() && !isHR()) {
    header("Location: " . APP_URL . "/employee/dashboard.php");
    exit;
}

// Filters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$employeeFilter = $_GET['employee'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$whereClause = "WHERE a.date BETWEEN ? AND ?";
$params = [$dateFrom, $dateTo];
$types = "ss";

if ($employeeFilter) {
    $whereClause .= " AND a.employee_id = ?";
    $params[] = $employeeFilter;
    $types .= "i";
}
if ($statusFilter) {
    $whereClause .= " AND a.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$attendances = fetchAll(
    "SELECT a.*, e.name, e.employee_id as emp_id, d.name as domain_name, e.avatar
     FROM attendance a 
     JOIN employees e ON a.employee_id = e.id 
     LEFT JOIN domains d ON e.domain_id = d.id
     $whereClause 
     ORDER BY a.date DESC, a.check_in_time DESC",
    $types,
    $params
);

$employees = fetchAll("SELECT id, name, employee_id FROM employees WHERE status = 'active' ORDER BY name");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/manager-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Attendance Records</h1>
        </div>
    </header>

    <div class="content-wrapper">
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Employee</label>
                        <select name="employee" class="form-select">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo $employeeFilter == $emp['id'] ? 'selected' : ''; ?>>
                                <?php echo $emp['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search me-1"></i>Filter
                        </button>
                        <a href="attendance.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Attendance List</h5>
                <span class="badge bg-primary"><?php echo count($attendances); ?> records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
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
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo getAvatar($att['avatar']); ?>" class="avatar avatar-sm me-2" alt="">
                                        <div>
                                            <div class="fw-semibold"><?php echo $att['name']; ?></div>
                                            <small class="text-muted"><?php echo $att['emp_id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo formatDate($att['date']); ?></td>
                                <td><?php echo formatTime($att['check_in_time']); ?></td>
                                <td><?php echo $att['check_out_time'] ? formatTime($att['check_out_time']) : '-'; ?></td>
                                <td><?php echo $att['total_hours'] ?? '-'; ?></td>
                                <td><span class="badge badge-<?php echo $att['status']; ?>"><?php echo ucfirst($att['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No records found</td>
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
