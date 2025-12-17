<?php
/**
 * Team Lead - Team Reports
 * Employee Management System
 */

$pageTitle = 'Team Reports';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (!isTeamLead()) {
    header("Location: " . APP_URL . "/employee/dashboard.php");
    exit;
}

$userId = $_SESSION['user_id'];
$teamMemberIds = getTeamMemberIds($userId);

// Filters
$reportType = $_GET['report_type'] ?? 'attendance';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$teamMemberId = isset($_GET['team_member']) && !empty($_GET['team_member']) ? (int)$_GET['team_member'] : null;

// Get team members for dropdown
$teamMembers = [];
if (!empty($teamMemberIds)) {
    $placeholders = implode(',', array_fill(0, count($teamMemberIds), '?'));
    $types = str_repeat('i', count($teamMemberIds));
    $teamMembers = fetchAll(
        "SELECT id, name, employee_id FROM employees WHERE id IN ($placeholders) ORDER BY name ASC",
        $types,
        $teamMemberIds
    );
}

// Generate report data
$reportData = [];
$reportTitle = '';
$selectedIds = $teamMemberId ? [$teamMemberId] : $teamMemberIds;

if (!empty($selectedIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    
    if ($reportType === 'attendance') {
        $reportTitle = 'Attendance Report';
        $params = array_merge([$dateFrom, $dateTo], $selectedIds);
        $types = "ss" . str_repeat('i', count($selectedIds));
        
        $reportData = fetchAll(
            "SELECT e.name, e.employee_id as emp_id,
                    COUNT(a.id) as total_days,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
                    SUM(CASE WHEN a.status = 'half-day' THEN 1 ELSE 0 END) as half_day,
                    ROUND(AVG(a.total_hours), 2) as avg_hours
             FROM employees e
             LEFT JOIN attendance a ON e.id = a.employee_id AND a.date BETWEEN ? AND ?
             WHERE e.id IN ($placeholders)
             GROUP BY e.id, e.name, e.employee_id
             ORDER BY e.name ASC",
            $types,
            $params
        );
    } elseif ($reportType === 'tasks') {
        $reportTitle = 'Tasks Report';
        $params = array_merge([$dateFrom, $dateTo], $selectedIds);
        $types = "ss" . str_repeat('i', count($selectedIds));
        
        $reportData = fetchAll(
            "SELECT e.name, e.employee_id as emp_id,
                    COUNT(t.id) as total_tasks,
                    SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN t.status = 'in-progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    ROUND(SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(t.id), 0), 1) as completion_rate
             FROM employees e
             LEFT JOIN tasks t ON e.id = t.assigned_to AND t.created_at BETWEEN ? AND ?
             WHERE e.id IN ($placeholders)
             GROUP BY e.id, e.name, e.employee_id
             ORDER BY e.name ASC",
            $types,
            $params
        );
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/teamlead-sidebar.php';
?>

<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Team Reports</h1>
        </div>
    </header>

    <div class="content-wrapper">
        <?php if (empty($teamMemberIds)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>No team members assigned to you yet.
        </div>
        <?php else: ?>
        
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" class="form-select">
                            <option value="attendance" <?php echo $reportType === 'attendance' ? 'selected' : ''; ?>>Attendance Report</option>
                            <option value="tasks" <?php echo $reportType === 'tasks' ? 'selected' : ''; ?>>Tasks Report</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Team Member</label>
                        <select name="team_member" class="form-select">
                            <option value="">All Team</option>
                            <?php foreach ($teamMembers as $member): ?>
                            <option value="<?php echo $member['id']; ?>" <?php echo $teamMemberId == $member['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Generate</button>
                        <a href="team-reports.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Report Results -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo $reportTitle; ?></h5>
                <small class="text-muted"><?php echo formatDate($dateFrom); ?> - <?php echo formatDate($dateTo); ?></small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if ($reportType === 'attendance'): ?>
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th class="text-center">Total Days</th>
                                <th class="text-center">Present</th>
                                <th class="text-center">Absent</th>
                                <th class="text-center">Late</th>
                                <th class="text-center">Half Day</th>
                                <th class="text-center">Avg Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                    <small class="text-muted d-block"><?php echo $row['emp_id']; ?></small>
                                </td>
                                <td class="text-center"><?php echo $row['total_days'] ?? 0; ?></td>
                                <td class="text-center"><span class="badge bg-success"><?php echo $row['present'] ?? 0; ?></span></td>
                                <td class="text-center"><span class="badge bg-danger"><?php echo $row['absent'] ?? 0; ?></span></td>
                                <td class="text-center"><span class="badge bg-warning"><?php echo $row['late'] ?? 0; ?></span></td>
                                <td class="text-center"><span class="badge bg-info"><?php echo $row['half_day'] ?? 0; ?></span></td>
                                <td class="text-center"><?php echo $row['avg_hours'] ?? '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php elseif ($reportType === 'tasks'): ?>
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th class="text-center">Total Tasks</th>
                                <th class="text-center">Completed</th>
                                <th class="text-center">In Progress</th>
                                <th class="text-center">Pending</th>
                                <th class="text-center">Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                    <small class="text-muted d-block"><?php echo $row['emp_id']; ?></small>
                                </td>
                                <td class="text-center"><?php echo $row['total_tasks'] ?? 0; ?></td>
                                <td class="text-center"><span class="badge bg-success"><?php echo $row['completed'] ?? 0; ?></span></td>
                                <td class="text-center"><span class="badge bg-primary"><?php echo $row['in_progress'] ?? 0; ?></span></td>
                                <td class="text-center"><span class="badge bg-warning"><?php echo $row['pending'] ?? 0; ?></span></td>
                                <td class="text-center">
                                    <div class="progress" style="width: 80px; height: 20px;">
                                        <div class="progress-bar" style="width: <?php echo $row['completion_rate'] ?? 0; ?>%">
                                            <?php echo $row['completion_rate'] ?? 0; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
