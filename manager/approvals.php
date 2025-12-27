<?php
/**
 * Manager - Attendance Approvals
 * Employee Management System
 */

$pageTitle = 'Attendance Approvals';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (!isManager() && !isHR()) {
    header("Location: " . url("employee/dashboard"));
    exit;
}

$message = '';
$messageType = '';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    $attendanceId = intval($_POST['id'] ?? 0);
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    if ($postAction === 'approve' || $postAction === 'reject') {
        $status = $postAction === 'approve' ? 'approved' : 'rejected';
        executeQuery(
            "UPDATE attendance SET status = ?, admin_remarks = ?, approved_by = ?, approved_at = NOW() WHERE id = ?",
            "ssii",
            [$status, $remarks, $_SESSION['user_id'], $attendanceId]
        );
        
        // Get employee ID for notification
        $attendance = fetchOne("SELECT employee_id FROM attendance WHERE id = ?", "i", [$attendanceId]);
        if ($attendance) {
            $notifTitle = $postAction === 'approve' ? 'Attendance Approved' : 'Attendance Rejected';
            $notifMessage = $postAction === 'approve' 
                ? 'Your attendance has been approved by ' . $_SESSION['user_name'] 
                : 'Your attendance has been rejected. Reason: ' . ($remarks ?: 'No remarks');
            
            executeQuery(
                "INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'employee', ?, ?, ?)",
                "isss",
                [$attendance['employee_id'], $notifTitle, $notifMessage, $postAction === 'approve' ? 'success' : 'danger']
            );
        }
        
        logActivity('attendance_' . $postAction, 'Attendance ID: ' . $attendanceId);
        $message = 'Attendance ' . $status . ' successfully!';
        $messageType = 'success';
        $action = '';
    }
}

// Get attendance details for view
$attendanceDetail = null;
if ($action === 'view' && $id) {
    $attendanceDetail = fetchOne(
        "SELECT a.*, e.name, e.employee_id as emp_id, e.email, d.name as domain_name, e.avatar 
         FROM attendance a 
         JOIN employees e ON a.employee_id = e.id 
         LEFT JOIN domains d ON e.domain_id = d.id 
         WHERE a.id = ?",
        "i",
        [$id]
    );
}

// Filters
$statusFilter = $_GET['status'] ?? 'pending';
$dateFilter = $_GET['date'] ?? '';

$whereClause = "WHERE 1=1";
$params = [];
$types = "";

if ($statusFilter) {
    $whereClause .= " AND a.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}
if ($dateFilter) {
    $whereClause .= " AND a.date = ?";
    $params[] = $dateFilter;
    $types .= "s";
}

$attendances = fetchAll(
    "SELECT a.*, e.name, e.employee_id as emp_id, d.name as domain_name, e.avatar 
     FROM attendance a 
     JOIN employees e ON a.employee_id = e.id 
     LEFT JOIN domains d ON e.domain_id = d.id
     $whereClause 
     ORDER BY a.created_at DESC",
    $types,
    $params
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/manager-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Attendance Approvals</h1>
        </div>
    </header>

    <div class="content-wrapper">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($action === 'view' && $attendanceDetail): ?>
        <!-- View Details -->
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Attendance Details</h5>
                <a href="approvals" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="text-center">
                            <img src="<?php echo getAvatar($attendanceDetail['avatar']); ?>" class="avatar avatar-lg mb-3" alt="">
                            <h5 class="mb-1"><?php echo $attendanceDetail['name']; ?></h5>
                            <p class="text-muted mb-2"><?php echo $attendanceDetail['emp_id']; ?></p>
                            <span class="badge bg-light text-dark"><?php echo $attendanceDetail['domain_name'] ?? 'N/A'; ?></span>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-6 mb-4">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="text-muted mb-1"><i class="bi bi-calendar me-1"></i>Date</h6>
                                    <h5 class="mb-0"><?php echo formatDate($attendanceDetail['date'], 'l, F j, Y'); ?></h5>
                                </div>
                            </div>
                            <div class="col-6 mb-4">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="text-muted mb-1"><i class="bi bi-clock me-1"></i>Check In</h6>
                                    <h5 class="mb-0"><?php echo formatTime($attendanceDetail['check_in_time']); ?></h5>
                                </div>
                            </div>
                            <div class="col-6 mb-4">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="text-muted mb-1"><i class="bi bi-clock-fill me-1"></i>Check Out</h6>
                                    <h5 class="mb-0"><?php echo $attendanceDetail['check_out_time'] ? formatTime($attendanceDetail['check_out_time']) : 'Not yet'; ?></h5>
                                </div>
                            </div>
                            <div class="col-6 mb-4">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="text-muted mb-1"><i class="bi bi-hourglass me-1"></i>Total Hours</h6>
                                    <h5 class="mb-0"><?php echo $attendanceDetail['total_hours'] ?? 'N/A'; ?></h5>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($attendanceDetail['check_in_photo']): ?>
                        <div class="mb-4">
                            <h6><i class="bi bi-camera me-2"></i>Check-in Photo</h6>
                            <img src="<?php echo APP_URL . '/uploads/checkin/' . $attendanceDetail['check_in_photo']; ?>" 
                                 class="img-fluid rounded" style="max-height: 300px;" alt="Check-in Photo">
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($attendanceDetail['check_in_latitude']) && !empty($attendanceDetail['check_in_longitude'])): ?>
                        <div class="mb-4">
                            <h6><i class="bi bi-geo-alt me-2"></i>Check-in Location</h6>
                            <?php if ($attendanceDetail['check_in_address']): ?>
                            <p class="mb-1"><?php echo $attendanceDetail['check_in_address']; ?></p>
                            <?php endif; ?>
                            <p class="text-muted mb-2">
                                Lat: <?php echo $attendanceDetail['check_in_latitude']; ?>, 
                                Long: <?php echo $attendanceDetail['check_in_longitude']; ?>
                            </p>
                            <a href="https://maps.google.com/?q=<?php echo $attendanceDetail['check_in_latitude']; ?>,<?php echo $attendanceDetail['check_in_longitude']; ?>" 
                               target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-map me-1"></i>View on Google Maps
                            </a>
                        </div>
                        <?php elseif ($attendanceDetail['check_in_address']): ?>
                        <div class="mb-4">
                            <h6><i class="bi bi-geo-alt me-2"></i>Location</h6>
                            <p class="mb-0"><?php echo $attendanceDetail['check_in_address']; ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($attendanceDetail['status'] === 'pending'): ?>
                        <hr>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                                <i class="bi bi-check-lg me-1"></i>Approve
                            </button>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="bi bi-x-lg me-1"></i>Reject
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-<?php echo $attendanceDetail['status'] === 'approved' ? 'success' : 'danger'; ?>">
                            <strong>Status:</strong> <?php echo ucfirst($attendanceDetail['status']); ?>
                            <?php if ($attendanceDetail['admin_remarks']): ?>
                            <br><strong>Remarks:</strong> <?php echo $attendanceDetail['admin_remarks']; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approve Modal -->
        <div class="modal fade" id="approveModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="id" value="<?php echo $attendanceDetail['id']; ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Approve Attendance</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Remarks (Optional)</label>
                                <textarea name="remarks" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Approve</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div class="modal fade" id="rejectModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="id" value="<?php echo $attendanceDetail['id']; ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Reject Attendance</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Reason for Rejection</label>
                                <textarea name="remarks" class="form-control" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reject</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <select name="status" class="form-select">
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>All</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="date" name="date" class="form-control" value="<?php echo $dateFilter; ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i>Filter
                        </button>
                        <a href="approvals" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Approvals Table -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Status</th>
                                <th>Actions</th>
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
                                <td><span class="badge badge-<?php echo $att['status']; ?>"><?php echo ucfirst($att['status']); ?></span></td>
                                <td>
                                    <a href="?action=view&id=<?php echo $att['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
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
