<?php
/**
 * Manager - Reports
 * Employee Management System
 */

$pageTitle = 'Reports';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (!isManager() && !isHR()) {
    header("Location: " . APP_URL . "/employee/dashboard.php");
    exit;
}

$reportType = $_GET['type'] ?? 'attendance';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$domainFilter = $_GET['domain'] ?? '';

// Get domains for filter
$domains = fetchAll("SELECT * FROM domains WHERE status = 'active' ORDER BY name");

// Build domain filter
$domainWhereClause = "";
$domainParams = [];
$domainTypes = "";
if ($domainFilter) {
    $domainWhereClause = " AND e.domain_id = ?";
    $domainParams[] = $domainFilter;
    $domainTypes = "i";
}

// Get report data based on type
$reportData = [];

if ($reportType === 'attendance') {
    $reportData = fetchAll(
        "SELECT e.id, e.name, e.employee_id, d.name as domain_name,
                COUNT(a.id) as total_days,
                SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved_days,
                SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_days,
                SEC_TO_TIME(AVG(TIME_TO_SEC(a.total_hours))) as avg_hours
         FROM employees e
         LEFT JOIN domains d ON e.domain_id = d.id
         LEFT JOIN attendance a ON e.id = a.employee_id AND a.date BETWEEN ? AND ?
         WHERE e.status = 'active' $domainWhereClause
         GROUP BY e.id
         ORDER BY e.name",
        "ss" . $domainTypes,
        array_merge([$dateFrom, $dateTo], $domainParams)
    );
} elseif ($reportType === 'tasks') {
    $reportData = fetchAll(
        "SELECT e.id, e.name, e.employee_id, d.name as domain_name,
                COUNT(t.id) as total_tasks,
                SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                SUM(CASE WHEN t.status = 'not_started' THEN 1 ELSE 0 END) as not_started_tasks
         FROM employees e
         LEFT JOIN domains d ON e.domain_id = d.id
         LEFT JOIN tasks t ON e.id = t.assigned_to AND t.created_at BETWEEN ? AND ?
         WHERE e.status = 'active' $domainWhereClause
         GROUP BY e.id
         ORDER BY e.name",
        "ss" . $domainTypes,
        array_merge([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'], $domainParams)
    );
} elseif ($reportType === 'domain') {
    // Fixed: Separate subqueries to avoid duplicate counting from JOINs
    $reportData = fetchAll(
        "SELECT d.id, d.name, d.code,
                (SELECT COUNT(*) FROM employees e WHERE e.domain_id = d.id AND e.status = 'active') as employee_count,
                (SELECT COUNT(*) FROM attendance a 
                 JOIN employees e ON a.employee_id = e.id 
                 WHERE e.domain_id = d.id AND e.status = 'active' AND a.date BETWEEN ? AND ?) as attendance_count,
                (SELECT COUNT(*) FROM tasks t 
                 JOIN employees e ON t.assigned_to = e.id 
                 WHERE e.domain_id = d.id AND e.status = 'active' AND t.created_at BETWEEN ? AND ?) as task_count
         FROM domains d
         WHERE d.status = 'active'
         ORDER BY d.name",
        "ssss",
        [$dateFrom, $dateTo, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
    );
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/manager-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Reports</h1>
        </div>
    </header>

    <div class="content-wrapper">
        <!-- Report Type Tabs -->
        <div class="report-tabs-container mb-4">
            <a href="?type=attendance" class="report-tab <?php echo $reportType === 'attendance' ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check"></i>
                <span>Attendance Report</span>
            </a>
            <a href="?type=tasks" class="report-tab <?php echo $reportType === 'tasks' ? 'active' : ''; ?>">
                <i class="bi bi-list-task"></i>
                <span>Task Report</span>
            </a>
            <a href="?type=domain" class="report-tab <?php echo $reportType === 'domain' ? 'active' : ''; ?>">
                <i class="bi bi-grid"></i>
                <span>Domain Report</span>
            </a>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="filter-container">
                    <form method="GET" class="d-flex gap-3 align-items-center flex-wrap w-100">
                        <input type="hidden" name="type" value="<?php echo $reportType; ?>">
                        
                        <div class="d-flex align-items-center gap-2">
                            <label class="text-muted small">From:</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo $dateFrom; ?>">
                        </div>
                        
                        <div class="d-flex align-items-center gap-2">
                            <label class="text-muted small">To:</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo $dateTo; ?>">
                        </div>

                        <?php if ($reportType !== 'domain'): ?>
                        <div class="d-flex align-items-center gap-2">
                            <label class="text-muted small">Domain:</label>
                            <select name="domain" class="form-select form-select-sm" style="min-width: 150px;">
                                <option value="">All Domains</option>
                                <?php foreach ($domains as $domain): ?>
                                <option value="<?php echo $domain['id']; ?>" <?php echo $domainFilter == $domain['id'] ? 'selected' : ''; ?>>
                                    <?php echo $domain['name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-sm btn-primary px-3 ms-auto">
                            <i class="bi bi-search me-1"></i>Generate
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Report Data -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <?php 
                    echo $reportType === 'attendance' ? 'Attendance Report' : 
                        ($reportType === 'tasks' ? 'Task Report' : 'Domain Report'); 
                    ?>
                    <small class="text-muted ms-2">(<?php echo formatDate($dateFrom); ?> - <?php echo formatDate($dateTo); ?>)</small>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <?php if ($reportType === 'attendance'): ?>
                            <tr>
                                <th>Employee</th>
                                <th>Domain</th>
                                <th>Total Days</th>
                                <th>Approved</th>
                                <th>Pending</th>
                                <th>Avg Hours</th>
                            </tr>
                            <?php elseif ($reportType === 'tasks'): ?>
                            <tr>
                                <th>Employee</th>
                                <th>Domain</th>
                                <th>Total Tasks</th>
                                <th>Completed</th>
                                <th>In Progress</th>
                                <th>Not Started</th>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <th>Domain</th>
                                <th>Code</th>
                                <th>Employees</th>
                                <th>Attendance Records</th>
                                <th>Tasks</th>
                            </tr>
                            <?php endif; ?>
                        </thead>
                        <tbody>
                            <?php if (count($reportData) > 0): ?>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <?php if ($reportType === 'attendance'): ?>
                                <td>
                                    <div class="fw-semibold"><?php echo $row['name']; ?></div>
                                    <small class="text-muted"><?php echo $row['employee_id']; ?></small>
                                </td>
                                <td><?php echo $row['domain_name'] ?? 'N/A'; ?></td>
                                <td><?php echo $row['total_days'] ?? 0; ?></td>
                                <td><span class="badge bg-success"><?php echo $row['approved_days'] ?? 0; ?></span></td>
                                <td><span class="badge bg-warning"><?php echo $row['pending_days'] ?? 0; ?></span></td>
                                <td><?php echo $row['avg_hours'] ?? '00:00:00'; ?></td>
                                <?php elseif ($reportType === 'tasks'): ?>
                                <td>
                                    <div class="fw-semibold"><?php echo $row['name']; ?></div>
                                    <small class="text-muted"><?php echo $row['employee_id']; ?></small>
                                </td>
                                <td><?php echo $row['domain_name'] ?? 'N/A'; ?></td>
                                <td><?php echo $row['total_tasks'] ?? 0; ?></td>
                                <td><span class="badge bg-success"><?php echo $row['completed_tasks'] ?? 0; ?></span></td>
                                <td><span class="badge bg-primary"><?php echo $row['in_progress_tasks'] ?? 0; ?></span></td>
                                <td><span class="badge bg-secondary"><?php echo $row['not_started_tasks'] ?? 0; ?></span></td>
                                <?php else: ?>
                                <td class="fw-semibold"><?php echo $row['name']; ?></td>
                                <td><span class="badge bg-light text-dark"><?php echo $row['code']; ?></span></td>
                                <td><?php echo $row['employee_count'] ?? 0; ?></td>
                                <td><?php echo $row['attendance_count'] ?? 0; ?></td>
                                <td><?php echo $row['task_count'] ?? 0; ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No data found</td>
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
