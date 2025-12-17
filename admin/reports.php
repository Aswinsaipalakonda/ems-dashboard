<?php
/**
 * Admin Reports
 * Employee Management System
 */

$pageTitle = 'Reports';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/pdf-generator.php';
requireAdmin();

$reportType = $_GET['type'] ?? 'attendance';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$export = $_GET['export'] ?? '';

// Export to PDF
if ($export === 'pdf') {
    switch ($reportType) {
        case 'attendance':
            $pdf = generateAttendanceReportPDF($month, $year);
            break;
        case 'employees':
            $pdf = generateEmployeeReportPDF();
            break;
        case 'tasks':
            $pdf = generateTaskReportPDF();
            break;
        default:
            $pdf = new PDFGenerator('Report');
    }
    $pdf->output($reportType . '_report_' . date('Y-m-d') . '.pdf');
    exit;
}

// Export to CSV
if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $reportType . '_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if ($reportType === 'attendance') {
        fputcsv($output, ['Employee ID', 'Name', 'Date', 'Check In', 'Check Out', 'Total Hours', 'Status']);
        
        $data = fetchAll(
            "SELECT e.employee_id, e.name, a.date, a.check_in_time, a.check_out_time, a.total_hours, a.status
             FROM attendance a
             JOIN employees e ON a.employee_id = e.id
             WHERE MONTH(a.date) = ? AND YEAR(a.date) = ?
             ORDER BY a.date DESC",
            "ii",
            [$month, $year]
        );
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['employee_id'],
                $row['name'],
                $row['date'],
                $row['check_in_time'],
                $row['check_out_time'],
                $row['total_hours'],
                $row['status']
            ]);
        }
    } elseif ($reportType === 'employees') {
        fputcsv($output, ['Employee ID', 'Name', 'Email', 'Domain', 'Designation', 'Status', 'Date of Joining']);
        
        $data = fetchAll(
            "SELECT e.*, d.name as domain_name 
             FROM employees e 
             LEFT JOIN domains d ON e.domain_id = d.id 
             ORDER BY e.name"
        );
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['employee_id'],
                $row['name'],
                $row['email'],
                $row['domain_name'] ?? '-',
                $row['designation'],
                $row['status'],
                $row['date_of_joining']
            ]);
        }
    } elseif ($reportType === 'tasks') {
        fputcsv($output, ['Task', 'Assigned To', 'Priority', 'Deadline', 'Status', 'Created At']);
        
        $data = fetchAll(
            "SELECT t.title, e.name, t.priority, t.deadline, t.status, t.created_at
             FROM tasks t
             JOIN employees e ON t.assigned_to = e.id
             ORDER BY t.created_at DESC"
        );
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['title'],
                $row['name'],
                $row['priority'],
                $row['deadline'],
                $row['status'],
                $row['created_at']
            ]);
        }
    }
    
    fclose($output);
    exit;
}

// Get report data
$attendanceSummary = fetchAll(
    "SELECT e.name, e.employee_id,
        COUNT(a.id) as total_days,
        SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SEC_TO_TIME(SUM(TIME_TO_SEC(a.total_hours))) as total_hours
     FROM employees e
     LEFT JOIN attendance a ON e.id = a.employee_id AND MONTH(a.date) = ? AND YEAR(a.date) = ?
     WHERE e.status = 'active'
     GROUP BY e.id
     ORDER BY e.name",
    "ii",
    [$month, $year]
);

$taskSummary = fetchAll(
    "SELECT e.name, e.employee_id,
        COUNT(t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN t.status = 'overdue' THEN 1 ELSE 0 END) as overdue
     FROM employees e
     LEFT JOIN tasks t ON e.id = t.assigned_to
     WHERE e.status = 'active'
     GROUP BY e.id
     ORDER BY e.name"
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Reports</h1>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="Avatar" class="user-avatar">
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <div class="content-wrapper">
        <!-- Report Type Tabs -->
        <div class="report-tabs-container mb-4">
            <a href="?type=attendance" class="report-tab <?php echo $reportType === 'attendance' ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check"></i>
                <span>Attendance</span>
            </a>
            <a href="?type=tasks" class="report-tab <?php echo $reportType === 'tasks' ? 'active' : ''; ?>">
                <i class="bi bi-list-task"></i>
                <span>Tasks</span>
            </a>
            <a href="?type=employees" class="report-tab <?php echo $reportType === 'employees' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i>
                <span>Employees</span>
            </a>
        </div>
        
        <?php if ($reportType === 'attendance'): ?>
        <!-- Attendance Report -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Monthly Attendance Summary</h5>
                </div>
                
                <div class="filter-container">
                    <form class="d-flex gap-3 align-items-center flex-wrap" method="GET">
                        <input type="hidden" name="type" value="attendance">
                        <div class="d-flex align-items-center gap-2">
                            <label class="text-muted small">Month:</label>
                            <select class="form-select form-select-sm" name="month" style="width: 140px;">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $month == $i ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="text-muted small">Year:</label>
                            <select class="form-select form-select-sm" name="year" style="width: 100px;">
                                <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                                <option value="<?php echo $i; ?>" <?php echo $year == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary px-3">
                            <i class="bi bi-filter me-1"></i> Filter
                        </button>
                    </form>
                    
                    <div class="d-flex gap-2 ms-auto">
                        <a href="?type=attendance&month=<?php echo $month; ?>&year=<?php echo $year; ?>&export=csv" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV
                        </a>
                        <a href="?type=attendance&month=<?php echo $month; ?>&year=<?php echo $year; ?>&export=pdf" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Days Present</th>
                                <th>Approved</th>
                                <th>Total Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendanceSummary as $row): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo $row['name']; ?></div>
                                    <small class="text-muted"><?php echo $row['employee_id']; ?></small>
                                </td>
                                <td><?php echo $row['total_days'] ?? 0; ?></td>
                                <td><span class="badge bg-success"><?php echo $row['approved'] ?? 0; ?></span></td>
                                <td><?php echo $row['total_hours'] ? substr($row['total_hours'], 0, 8) : '00:00:00'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php elseif ($reportType === 'tasks'): ?>
        <!-- Task Report -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-task me-2"></i>Task Performance Summary</h5>
                <div class="d-flex gap-2">
                    <a href="?type=tasks&export=csv" class="btn btn-sm btn-success">
                        <i class="bi bi-download me-1"></i>CSV
                    </a>
                    <a href="?type=tasks&export=pdf" class="btn btn-sm btn-danger">
                        <i class="bi bi-file-pdf me-1"></i>PDF
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Total Tasks</th>
                                <th>Completed</th>
                                <th>Overdue</th>
                                <th>Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($taskSummary as $row): ?>
                            <?php $rate = $row['total_tasks'] > 0 ? round(($row['completed'] / $row['total_tasks']) * 100) : 0; ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo $row['name']; ?></div>
                                    <small class="text-muted"><?php echo $row['employee_id']; ?></small>
                                </td>
                                <td><?php echo $row['total_tasks'] ?? 0; ?></td>
                                <td><span class="badge bg-success"><?php echo $row['completed'] ?? 0; ?></span></td>
                                <td><span class="badge bg-danger"><?php echo $row['overdue'] ?? 0; ?></span></td>
                                <td>
                                    <div class="progress" style="height: 20px; width: 100px;">
                                        <div class="progress-bar bg-<?php echo $rate >= 70 ? 'success' : ($rate >= 40 ? 'warning' : 'danger'); ?>" 
                                             style="width: <?php echo $rate; ?>%"><?php echo $rate; ?>%</div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Employee Report -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Employee Directory</h5>
                <div class="d-flex gap-2">
                    <a href="?type=employees&export=csv" class="btn btn-sm btn-success">
                        <i class="bi bi-download me-1"></i>CSV
                    </a>
                    <a href="?type=employees&export=pdf" class="btn btn-sm btn-danger">
                        <i class="bi bi-file-pdf me-1"></i>PDF
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php $employees = fetchAll(
                    "SELECT e.*, d.name as domain_name, d.code as domain_code 
                     FROM employees e 
                     LEFT JOIN domains d ON e.domain_id = d.id 
                     ORDER BY e.name"
                ); ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Domain</th>
                                <th>Designation</th>
                                <th>Status</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
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
                                <td>
                                    <?php if ($emp['domain_name']): ?>
                                    <span class="badge bg-primary"><?php echo $emp['domain_code']; ?></span>
                                    <?php echo $emp['domain_name']; ?>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $emp['designation'] ?? '-'; ?></td>
                                <td><span class="badge badge-<?php echo $emp['status']; ?>"><?php echo ucfirst($emp['status']); ?></span></td>
                                <td><?php echo $emp['date_of_joining'] ? formatDate($emp['date_of_joining']) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
