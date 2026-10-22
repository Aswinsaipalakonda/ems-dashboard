<?php
/**
 * Admin Reports - SaaS Executive Analytics Center
 * Employee Management System
 */

$pageTitle = 'Reports & Analytics';
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

// Attendance summary
$attendanceSummary = fetchAll(
    "SELECT e.name, e.employee_id, e.avatar,
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

// Task summary
$taskSummary = fetchAll(
    "SELECT e.name, e.employee_id, e.avatar,
        COUNT(t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN t.status = 'overdue' THEN 1 ELSE 0 END) as overdue
     FROM employees e
     LEFT JOIN tasks t ON e.id = t.assigned_to
     WHERE e.status = 'active'
     GROUP BY e.id
     ORDER BY e.name"
);

// Global Stats for Analytics
$staffCount = fetchOne("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")['count'] ?? 0;
$totalHoursLogged = fetchOne("SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(total_hours))) as total FROM attendance WHERE MONTH(date) = ? AND YEAR(date) = ? AND status = 'approved'", "ii", [$month, $year])['total'] ?? '00:00:00';
$totalTasksIssued = fetchOne("SELECT COUNT(*) as count FROM tasks")['count'] ?? 0;
$totalTasksDone = fetchOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'completed'")['count'] ?? 0;
$taskSuccessRate = $totalTasksIssued > 0 ? round(($totalTasksDone / $totalTasksIssued) * 100) : 100;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Executive Reports & Analytics</h1>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="Avatar" class="user-avatar">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo url('admin/profile'); ?>"><?php echo pika_icon('profile', 'me-2', 16); ?>Profile</a></li>
                    <li><a class="dropdown-item" href="<?php echo url('admin/settings'); ?>"><?php echo pika_icon('settings', 'me-2', 16); ?>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo url('logout'); ?>"><?php echo pika_icon('logout', 'me-2 text-danger', 16); ?>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <div class="content-wrapper">
        <!-- Hero Hub -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="saas-hero-content">
                        <h2 class="saas-hero-title">Executive Intelligence & Auditing</h2>
                        <p class="saas-hero-subtitle">
                            Extract aggregated attendance records, task completion velocities, and organizational workforce rosters in presentation-ready PDF or CSV format.
                        </p>
                        <div class="saas-hero-pills">
                            <span class="saas-pill live-pulse">
                                <span class="pulse-dot"></span>
                                Live Analytics Active
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('calendar', '', 14); ?>
                                Period: <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('trending-up', '', 14); ?>
                                <?php echo $taskSuccessRate; ?>% Task Velocity
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mt-3 mt-lg-0 text-lg-end">
                    <div class="saas-hero-actions">
                        <a href="?type=<?php echo $reportType; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>&export=csv" class="btn btn-outline-light">
                            <?php echo pika_icon('download', 'me-1', 16); ?> Export CSV
                        </a>
                        <a href="?type=<?php echo $reportType; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>&export=pdf" class="btn btn-primary btn-glow">
                            <?php echo pika_icon('file-text', 'me-1', 16); ?> Download PDF Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Dynamic Metric Cards -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge navy">
                            <?php echo pika_icon('users', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active">Workforce</span>
                    </div>
                    <div class="metric-value"><?php echo $staffCount; ?></div>
                    <div class="metric-label">Active Team Members</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge emerald">
                            <?php echo pika_icon('clock', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active">Hours</span>
                    </div>
                    <div class="metric-value"><?php echo substr($totalHoursLogged, 0, 5); ?>h</div>
                    <div class="metric-label">Approved Hours Logged</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge sky">
                            <?php echo pika_icon('tasks', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill sky">Deliverables</span>
                    </div>
                    <div class="metric-value"><?php echo $totalTasksDone; ?>/<?php echo $totalTasksIssued; ?></div>
                    <div class="metric-label">Completed Tasks</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge amber">
                            <?php echo pika_icon('trending-up', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill warning"><?php echo $taskSuccessRate; ?>%</span>
                    </div>
                    <div class="metric-value"><?php echo $taskSuccessRate; ?>%</div>
                    <div class="metric-label">Execution Velocity</div>
                </div>
            </div>
        </div>

        <!-- SaaS Navigation Pills for Report Modes -->
        <div class="saas-toolbar mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex gap-2 p-1 bg-light rounded-pill border">
                    <a href="?type=attendance&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                       class="btn btn-sm <?php echo $reportType === 'attendance' ? 'btn-primary' : 'btn-light border-0'; ?>">
                        <?php echo pika_icon('calendar', 'me-1', 14); ?> Attendance Audit
                    </a>
                    <a href="?type=tasks" 
                       class="btn btn-sm <?php echo $reportType === 'tasks' ? 'btn-primary' : 'btn-light border-0'; ?>">
                        <?php echo pika_icon('tasks', 'me-1', 14); ?> Task Velocity
                    </a>
                    <a href="?type=employees" 
                       class="btn btn-sm <?php echo $reportType === 'employees' ? 'btn-primary' : 'btn-light border-0'; ?>">
                        <?php echo pika_icon('users', 'me-1', 14); ?> Workforce Roster
                    </a>
                </div>

                <?php if ($reportType === 'attendance'): ?>
                <form class="d-flex gap-2 align-items-center" method="GET">
                    <input type="hidden" name="type" value="attendance">
                    <select class="form-select saas-select py-1" name="month" style="width: 140px;">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $month == $i ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                    <select class="form-select saas-select py-1" name="year" style="width: 110px;">
                        <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                        <option value="<?php echo $i; ?>" <?php echo $year == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <?php echo pika_icon('filter', '', 14); ?> Filter
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($reportType === 'attendance'): ?>
        <!-- Attendance Report Table -->
        <div class="saas-card">
            <div class="saas-card-header">
                <h5 class="saas-card-title">
                    <?php echo pika_icon('calendar', 'text-primary', 20); ?>
                    Monthly Attendance Audit (<?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>)
                </h5>
                <div class="d-flex gap-2">
                    <a href="?type=attendance&month=<?php echo $month; ?>&year=<?php echo $year; ?>&export=csv" class="btn btn-sm btn-outline-success">
                        <?php echo pika_icon('download', 'me-1', 13); ?> CSV
                    </a>
                    <a href="?type=attendance&month=<?php echo $month; ?>&year=<?php echo $year; ?>&export=pdf" class="btn btn-sm btn-outline-danger">
                        <?php echo pika_icon('file-text', 'me-1', 13); ?> PDF
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="saas-table">
                    <thead>
                        <tr>
                            <th>Team Member</th>
                            <th>Employee ID</th>
                            <th>Days Present</th>
                            <th>Verified Sessions</th>
                            <th>Total Logged Hours</th>
                            <th>Punctuality Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceSummary as $row): 
                            $initials = strtoupper(substr($row['name'], 0, 1));
                            $present = intval($row['total_days'] ?? 0);
                            $approved = intval($row['approved'] ?? 0);
                            $approvalPct = $present > 0 ? round(($approved / $present) * 100) : 100;
                        ?>
                        <tr>
                            <td>
                                <div class="saas-user-cell">
                                    <?php if (!empty($row['avatar'])): ?>
                                    <img src="<?php echo getAvatar($row['avatar']); ?>" class="saas-avatar" style="width: 38px; height: 38px;" alt="">
                                    <?php else: ?>
                                    <div class="saas-avatar-initials" style="width: 38px; height: 38px; font-size: 13px;"><?php echo $initials; ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold text-dark small"><?php echo htmlspecialchars($row['name']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="saas-id-badge"><?php echo htmlspecialchars($row['employee_id']); ?></span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark"><?php echo $present; ?></span> <small class="text-muted">days</small>
                            </td>
                            <td>
                                <span class="saas-tag emerald"><?php echo $approved; ?> verified</span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark"><?php echo $row['total_hours'] ? substr($row['total_hours'], 0, 5) . ' hrs' : '00:00 hrs'; ?></span>
                            </td>
                            <td>
                                <span class="saas-tag <?php echo $approvalPct >= 80 ? 'emerald' : ($approvalPct >= 50 ? 'amber' : 'rose'); ?>">
                                    <?php echo $approvalPct; ?>% Verified
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($reportType === 'tasks'): ?>
        <!-- Task Report Table -->
        <div class="saas-card">
            <div class="saas-card-header">
                <h5 class="saas-card-title">
                    <?php echo pika_icon('tasks', 'text-primary', 20); ?>
                    Employee Deliverable Velocity & Task Summary
                </h5>
                <div class="d-flex gap-2">
                    <a href="?type=tasks&export=csv" class="btn btn-sm btn-outline-success">
                        <?php echo pika_icon('download', 'me-1', 13); ?> CSV
                    </a>
                    <a href="?type=tasks&export=pdf" class="btn btn-sm btn-outline-danger">
                        <?php echo pika_icon('file-text', 'me-1', 13); ?> PDF
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="saas-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Employee ID</th>
                            <th>Total Assigned</th>
                            <th>Finished</th>
                            <th>Overdue</th>
                            <th>Completion Velocity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($taskSummary as $row): 
                            $rate = $row['total_tasks'] > 0 ? round(($row['completed'] / $row['total_tasks']) * 100) : 0;
                            $initials = strtoupper(substr($row['name'], 0, 1));
                        ?>
                        <tr>
                            <td>
                                <div class="saas-user-cell">
                                    <?php if (!empty($row['avatar'])): ?>
                                    <img src="<?php echo getAvatar($row['avatar']); ?>" class="saas-avatar" style="width: 38px; height: 38px;" alt="">
                                    <?php else: ?>
                                    <div class="saas-avatar-initials" style="width: 38px; height: 38px; font-size: 13px;"><?php echo $initials; ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold text-dark small"><?php echo htmlspecialchars($row['name']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="saas-id-badge"><?php echo htmlspecialchars($row['employee_id']); ?></span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark"><?php echo $row['total_tasks'] ?? 0; ?></span>
                            </td>
                            <td>
                                <span class="saas-tag emerald"><?php echo $row['completed'] ?? 0; ?> Done</span>
                            </td>
                            <td>
                                <?php if (($row['overdue'] ?? 0) > 0): ?>
                                <span class="saas-tag rose"><?php echo $row['overdue']; ?> Overdue</span>
                                <?php else: ?>
                                <span class="text-muted small">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress rounded-pill flex-grow-1" style="height: 8px; min-width: 80px;">
                                        <div class="progress-bar <?php echo $rate >= 70 ? 'bg-success' : ($rate >= 40 ? 'bg-warning' : 'bg-danger'); ?>" 
                                             style="width: <?php echo $rate; ?>%"></div>
                                    </div>
                                    <span class="badge bg-light text-dark border rounded-pill px-2 py-1 small"><?php echo $rate; ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <!-- Employee Roster Report Table -->
        <div class="saas-card">
            <div class="saas-card-header">
                <h5 class="saas-card-title">
                    <?php echo pika_icon('users', 'text-primary', 20); ?>
                    Master Employee Directory Roster
                </h5>
                <div class="d-flex gap-2">
                    <a href="?type=employees&export=csv" class="btn btn-sm btn-outline-success">
                        <?php echo pika_icon('download', 'me-1', 13); ?> CSV
                    </a>
                    <a href="?type=employees&export=pdf" class="btn btn-sm btn-outline-danger">
                        <?php echo pika_icon('file-text', 'me-1', 13); ?> PDF
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <?php $employeesRoster = fetchAll(
                    "SELECT e.*, d.name as domain_name, d.code as domain_code 
                     FROM employees e 
                     LEFT JOIN domains d ON e.domain_id = d.id 
                     ORDER BY e.name"
                ); ?>
                <table class="saas-table">
                    <thead>
                        <tr>
                            <th>Team Member</th>
                            <th>Employee ID</th>
                            <th>Domain / Dept</th>
                            <th>Designation</th>
                            <th>Status</th>
                            <th>Date of Joining</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employeesRoster as $emp): 
                            $initials = strtoupper(substr($emp['name'], 0, 1));
                        ?>
                        <tr>
                            <td>
                                <div class="saas-user-cell">
                                    <?php if (!empty($emp['avatar'])): ?>
                                    <img src="<?php echo getAvatar($emp['avatar']); ?>" class="saas-avatar" style="width: 38px; height: 38px;" alt="">
                                    <?php else: ?>
                                    <div class="saas-avatar-initials" style="width: 38px; height: 38px; font-size: 13px;"><?php echo $initials; ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold text-dark small"><?php echo htmlspecialchars($emp['name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($emp['email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="saas-id-badge"><?php echo htmlspecialchars($emp['employee_id']); ?></span>
                            </td>
                            <td>
                                <?php if ($emp['domain_name']): ?>
                                <span class="saas-tag primary">
                                    <?php echo htmlspecialchars($emp['domain_name']); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark small"><?php echo htmlspecialchars($emp['designation'] ?? 'Employee'); ?></span>
                            </td>
                            <td>
                                <span class="saas-tag <?php echo $emp['status'] === 'active' ? 'emerald' : 'amber'; ?>">
                                    <?php echo ucfirst($emp['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-dark small"><?php echo $emp['date_of_joining'] ? formatDate($emp['date_of_joining']) : '—'; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
