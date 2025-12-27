<?php
/**
 * Team Lead - Team Attendance Records
 * Employee Management System
 */

$pageTitle = 'Team Attendance';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (!isTeamLead()) {
    header("Location: " . url("employee/dashboard"));
    exit;
}

$userId = $_SESSION['user_id'];
$teamMemberIds = getTeamMemberIds($userId);

// Filters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$attendances = [];
if (!empty($teamMemberIds)) {
    $placeholders = implode(',', array_fill(0, count($teamMemberIds), '?'));
    $params = array_merge([$dateFrom, $dateTo], $teamMemberIds);
    $types = "ss" . str_repeat('i', count($teamMemberIds));
    
    $attendances = fetchAll(
        "SELECT a.*, e.name, e.employee_id as emp_id, d.name as domain_name, e.avatar
         FROM attendance a 
         JOIN employees e ON a.employee_id = e.id 
         LEFT JOIN domains d ON e.domain_id = d.id
         WHERE a.date BETWEEN ? AND ? AND a.employee_id IN ($placeholders)
         ORDER BY a.date DESC, e.name ASC",
        $types,
        $params
    );
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/teamlead-sidebar.php';
?>

<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Team Attendance Records</h1>
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
                    <div class="col-md-4">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Filter</button>
                        <a href="team-attendance" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Team Attendance</h5>
                <span class="badge bg-primary"><?php echo count($attendances); ?> records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Team Member</th>
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
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
