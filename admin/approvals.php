<?php
/**
 * Attendance Approvals
 * Employee Management System
 */

$pageTitle = 'Attendance Approvals';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$message = '';
$messageType = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendanceId = intval($_POST['attendance_id'] ?? 0);
    $postAction = $_POST['approval_action'] ?? '';
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    if ($attendanceId && in_array($postAction, ['approve', 'reject'])) {
        $status = $postAction === 'approve' ? 'approved' : 'rejected';
        
        $sql = "UPDATE attendance SET status = ?, admin_remarks = ?, approved_by = ?, approved_at = NOW() WHERE id = ?";
        executeQuery($sql, "ssii", [$status, $remarks, $_SESSION['user_id'], $attendanceId]);
        
        // Get employee info for notification
        $attendance = fetchOne(
            "SELECT a.*, e.name, e.id as emp_id, e.email FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE a.id = ?",
            "i",
            [$attendanceId]
        );
        
        // Create notification for employee
        $notifTitle = $status === 'approved' ? 'Attendance Approved' : 'Attendance Rejected';
        $notifMessage = "Your check-in for " . formatDate($attendance['date']) . " has been " . $status . ".";
        if ($remarks) {
            $notifMessage .= " Remarks: " . $remarks;
        }
        
        executeQuery(
            "INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'employee', ?, ?, ?)",
            "isss",
            [$attendance['emp_id'], $notifTitle, $notifMessage, $status === 'approved' ? 'success' : 'danger']
        );
        
        $message = "Attendance " . $status . " successfully!";
        $messageType = 'success';
    }
}

// Get attendance details for view
$attendanceDetail = null;
if ($action === 'view' && $id) {
    $attendanceDetail = fetchOne(
        "SELECT a.*, e.name, e.employee_id, e.email, d.name as domain_name, e.avatar 
         FROM attendance a 
         JOIN employees e ON a.employee_id = e.id 
         LEFT JOIN domains d ON e.domain_id = d.id 
         WHERE a.id = ?",
        "i",
        [$id]
    );
}

// Get all pending attendance
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
    "SELECT a.*, e.name, e.employee_id, e.avatar 
     FROM attendance a 
     JOIN employees e ON a.employee_id = e.id 
     $whereClause 
     ORDER BY a.created_at DESC",
    $types,
    $params
);

$pendingCount = fetchOne("SELECT COUNT(*) as count FROM attendance WHERE status = 'pending'")['count'] ?? 0;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="page-title">Attendance Approvals</h1>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="Avatar" class="user-avatar">
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['user_name']; ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <!-- Content -->
    <div class="content-wrapper">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'view' && $attendanceDetail): ?>
        <!-- View Attendance Detail -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-eye me-2"></i>Attendance Details</h5>
                <a href="<?php echo APP_URL; ?>/admin/approvals.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Employee Info -->
                    <div class="col-md-4 mb-4">
                        <div class="text-center">
                            <img src="<?php echo getAvatar($attendanceDetail['avatar']); ?>" class="avatar avatar-lg mb-3" alt="">
                            <h5 class="mb-1"><?php echo $attendanceDetail['name']; ?></h5>
                            <p class="text-muted mb-2"><?php echo $attendanceDetail['employee_id']; ?></p>
                            <span class="badge bg-light text-dark"><?php echo $attendanceDetail['domain_name'] ?? 'N/A'; ?></span>
                        </div>
                    </div>
                    
                    <!-- Attendance Info -->
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
                                    <h6 class="text-muted mb-1"><i class="bi bi-clock me-1"></i>Status</h6>
                                    <span class="badge badge-<?php echo $attendanceDetail['status']; ?> fs-6">
                                        <?php echo ucfirst($attendanceDetail['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-6 mb-4">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="text-muted mb-1"><i class="bi bi-box-arrow-in-right me-1"></i>Check-in Time</h6>
                                    <h5 class="mb-0"><?php echo $attendanceDetail['check_in_time'] ? formatTime($attendanceDetail['check_in_time']) : '-'; ?></h5>
                                </div>
                            </div>
                            <div class="col-6 mb-4">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="text-muted mb-1"><i class="bi bi-box-arrow-right me-1"></i>Check-out Time</h6>
                                    <h5 class="mb-0"><?php echo $attendanceDetail['check_out_time'] ? formatTime($attendanceDetail['check_out_time']) : '-'; ?></h5>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Photo Verification -->
                        <?php if ($attendanceDetail['check_in_photo']): ?>
                        <div class="mb-4">
                            <h6><i class="bi bi-camera me-2"></i>Check-in Photo</h6>
                            <img src="<?php echo APP_URL; ?>/uploads/checkin/<?php echo $attendanceDetail['check_in_photo']; ?>" 
                                 class="img-fluid rounded" style="max-height: 300px;" alt="Check-in Photo">
                        </div>
                        <?php endif; ?>
                        
                        <!-- Location -->
                        <?php if ($attendanceDetail['check_in_latitude'] && $attendanceDetail['check_in_longitude']): ?>
                        <div class="mb-4">
                            <h6><i class="bi bi-geo-alt me-2"></i>Check-in Location</h6>
                            <p class="text-muted">
                                Lat: <?php echo $attendanceDetail['check_in_latitude']; ?>, 
                                Long: <?php echo $attendanceDetail['check_in_longitude']; ?>
                            </p>
                            <a href="https://maps.google.com/?q=<?php echo $attendanceDetail['check_in_latitude']; ?>,<?php echo $attendanceDetail['check_in_longitude']; ?>" 
                               target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-map me-1"></i>View on Google Maps
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Action Form -->
                        <?php if ($attendanceDetail['status'] === 'pending'): ?>
                        <div class="mt-4 p-4 bg-light rounded">
                            <h6><i class="bi bi-check-circle me-2"></i>Take Action</h6>
                            <form method="POST">
                                <input type="hidden" name="attendance_id" value="<?php echo $attendanceDetail['id']; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Remarks (Optional)</label>
                                    <textarea class="form-control" name="remarks" rows="2" placeholder="Add any remarks..."></textarea>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" name="approval_action" value="approve" class="btn btn-success">
                                        <i class="bi bi-check-lg me-2"></i>Approve
                                    </button>
                                    <button type="submit" name="approval_action" value="reject" class="btn btn-danger">
                                        <i class="bi bi-x-lg me-2"></i>Reject
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php elseif ($attendanceDetail['admin_remarks']): ?>
                        <div class="mt-4 p-4 bg-light rounded">
                            <h6><i class="bi bi-chat-left-text me-2"></i>Admin Remarks</h6>
                            <p class="mb-0"><?php echo $attendanceDetail['admin_remarks']; ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Approval List -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-6 mb-2 mb-md-0">
                        <h5 class="mb-0">
                            <i class="bi bi-check-circle me-2"></i>Attendance Approvals
                            <?php if ($pendingCount > 0): ?>
                            <span class="badge bg-warning text-dark ms-2"><?php echo $pendingCount; ?> pending</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <form class="row g-3 mb-4" method="GET">
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" class="form-control" name="date" value="<?php echo $dateFilter; ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-search me-1"></i>Filter
                        </button>
                    </div>
                    <?php if ($statusFilter || $dateFilter): ?>
                    <div class="col-md-2">
                        <a href="<?php echo APP_URL; ?>/admin/approvals.php" class="btn btn-outline-secondary w-100">Clear</a>
                    </div>
                    <?php endif; ?>
                </form>
                
                <?php if (count($attendances) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendances as $att): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo getAvatar($att['avatar']); ?>" class="avatar avatar-sm me-2" alt="">
                                        <div>
                                            <div class="fw-semibold"><?php echo $att['name']; ?></div>
                                            <small class="text-muted"><?php echo $att['employee_id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo formatDate($att['date']); ?></td>
                                <td><?php echo $att['check_in_time'] ? formatTime($att['check_in_time']) : '-'; ?></td>
                                <td><?php echo $att['check_out_time'] ? formatTime($att['check_out_time']) : '-'; ?></td>
                                <td>
                                    <?php if ($att['check_in_latitude'] && $att['check_in_longitude']): ?>
                                    <a href="https://maps.google.com/?q=<?php echo $att['check_in_latitude']; ?>,<?php echo $att['check_in_longitude']; ?>" 
                                       target="_blank" class="text-primary">
                                        <i class="bi bi-geo-alt"></i> View
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $att['status']; ?>">
                                        <?php echo ucfirst($att['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo APP_URL; ?>/admin/approvals.php?action=view&id=<?php echo $att['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <?php if ($att['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-sm btn-success ms-1" 
                                            onclick="quickApprove(<?php echo $att['id']; ?>)">
                                        <i class="bi bi-check"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-check-circle"></i>
                    <h4>No Records Found</h4>
                    <p>
                        <?php if ($statusFilter === 'pending'): ?>
                            All attendance entries have been reviewed.
                        <?php else: ?>
                            No attendance records match your filter.
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Approve Form (Hidden) -->
<form id="quickApproveForm" method="POST" style="display: none;">
    <input type="hidden" name="attendance_id" id="quickApproveId">
    <input type="hidden" name="approval_action" value="approve">
    <input type="hidden" name="remarks" value="">
</form>

<script>
function quickApprove(id) {
    if (confirm('Are you sure you want to approve this attendance?')) {
        document.getElementById('quickApproveId').value = id;
        document.getElementById('quickApproveForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
