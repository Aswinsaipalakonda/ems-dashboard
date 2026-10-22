<?php
/**
 * Attendance Approvals - SaaS Verification Center
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
        $notifMessage = "Your attendance check-in for " . formatDate($attendance['date']) . " has been " . $status . ".";
        if ($remarks) {
            $notifMessage .= " Remarks: " . $remarks;
        }
        
        executeQuery(
            "INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'employee', ?, ?, ?)",
            "isss",
            [$attendance['emp_id'], $notifTitle, $notifMessage, $status === 'approved' ? 'success' : 'danger']
        );
        
        $message = "Attendance check-in has been successfully <strong>" . $status . "</strong>.";
        $messageType = $status === 'approved' ? 'success' : 'warning';
        if ($action === 'view') {
            $action = '';
        }
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

// Status filters
$statusFilter = $_GET['status'] ?? 'pending';
$dateFilter = $_GET['date'] ?? '';

$whereClause = "WHERE 1=1";
$params = [];
$types = "";

if ($statusFilter && $statusFilter !== 'all') {
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
    "SELECT a.*, e.name, e.employee_id, e.avatar, d.name as domain_name 
     FROM attendance a 
     JOIN employees e ON a.employee_id = e.id 
     LEFT JOIN domains d ON e.domain_id = d.id
     $whereClause 
     ORDER BY a.created_at DESC",
    $types,
    $params
);

// Dynamic SaaS Verification Metrics
$pendingCount = fetchOne("SELECT COUNT(*) as count FROM attendance WHERE status = 'pending'")['count'] ?? 0;
$approvedToday = fetchOne("SELECT COUNT(*) as count FROM attendance WHERE status = 'approved' AND date = CURDATE()")['count'] ?? 0;
$rejectedTotal = fetchOne("SELECT COUNT(*) as count FROM attendance WHERE status = 'rejected'")['count'] ?? 0;
$totalReviews = fetchOne("SELECT COUNT(*) as count FROM attendance")['count'] ?? 0;
$approvalRate = $totalReviews > 0 ? round((($totalReviews - $pendingCount - $rejectedTotal) / $totalReviews) * 100) : 100;

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
            <h1 class="page-title">Attendance Verification</h1>
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
    
    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <?php echo pika_icon($messageType === 'success' ? 'check-circle' : 'x', 'flex-shrink-0', 20); ?>
                <div><?php echo $message; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($action === 'view' && $attendanceDetail): ?>
        <!-- Single Attendance Detailed Inspection -->
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="saas-card">
                    <div class="saas-card-header">
                        <h5 class="saas-card-title">
                            <?php echo pika_icon('profile', 'text-primary', 20); ?>
                            Employee Credentials
                        </h5>
                        <a href="<?php echo url('admin/approvals'); ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i> Back to Queue
                        </a>
                    </div>
                    <div class="saas-card-body text-center p-4">
                        <img src="<?php echo getAvatar($attendanceDetail['avatar']); ?>" class="saas-avatar mx-auto mb-3" style="width: 76px; height: 76px; border-radius: 20px;" alt="">
                        <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($attendanceDetail['name']); ?></h5>
                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($attendanceDetail['email']); ?></p>
                        <div class="d-flex justify-content-center gap-2 mb-4">
                            <span class="saas-id-badge"><?php echo htmlspecialchars($attendanceDetail['employee_id']); ?></span>
                            <span class="saas-tag primary"><?php echo htmlspecialchars($attendanceDetail['domain_name'] ?? 'General'); ?></span>
                        </div>

                        <!-- Check-in Selfie Verification Photo -->
                        <div class="border rounded-4 p-3 bg-light text-start">
                            <label class="form-label fw-bold text-dark small mb-2 d-flex align-items-center gap-1">
                                <?php echo pika_icon('shield-check', 'text-primary', 16); ?>
                                Biometric Check-in Selfie
                            </label>
                            <?php if ($attendanceDetail['check_in_photo']): ?>
                            <div class="rounded-3 overflow-hidden shadow-sm border bg-white text-center">
                                <img src="<?php echo APP_URL; ?>/uploads/checkin/<?php echo htmlspecialchars($attendanceDetail['check_in_photo']); ?>" 
                                     class="img-fluid" style="max-height: 280px; width: 100%; object-fit: cover;" alt="Check-in Photo">
                            </div>
                            <?php else: ?>
                            <div class="p-4 text-center text-muted small bg-white rounded-3 border">
                                No check-in photo captured for this session.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="saas-card">
                    <div class="saas-card-header">
                        <h5 class="saas-card-title">
                            <?php echo pika_icon('clock', 'text-primary', 20); ?>
                            Attendance Session & Geolocation
                        </h5>
                        <span class="saas-tag <?php 
                            echo match($attendanceDetail['status']) {
                                'approved' => 'emerald',
                                'rejected' => 'rose',
                                default => 'amber'
                            };
                        ?>">
                            <?php echo ucfirst($attendanceDetail['status']); ?>
                        </span>
                    </div>
                    <div class="saas-card-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <div class="p-3 rounded-4 bg-light border">
                                    <span class="text-muted small fw-semibold d-block mb-1">Calendar Date</span>
                                    <h6 class="fw-bold text-dark mb-0"><?php echo formatDate($attendanceDetail['date'], 'l, F j, Y'); ?></h6>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 rounded-4 bg-light border">
                                    <span class="text-muted small fw-semibold d-block mb-1">Total Duration</span>
                                    <h6 class="fw-bold text-dark mb-0"><?php echo $attendanceDetail['total_hours'] ?: 'In Progress'; ?></h6>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 rounded-4 bg-light border">
                                    <span class="text-muted small fw-semibold d-block mb-1">Check-in Time</span>
                                    <h6 class="fw-bold text-dark mb-0"><?php echo $attendanceDetail['check_in_time'] ? formatTime($attendanceDetail['check_in_time']) : '-'; ?></h6>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 rounded-4 bg-light border">
                                    <span class="text-muted small fw-semibold d-block mb-1">Check-out Time</span>
                                    <h6 class="fw-bold text-dark mb-0"><?php echo $attendanceDetail['check_out_time'] ? formatTime($attendanceDetail['check_out_time']) : 'Active Session'; ?></h6>
                                </div>
                            </div>
                        </div>

                        <!-- Geolocation Details -->
                        <div class="p-3 rounded-4 bg-light border mb-4">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="metric-icon-badge sky" style="width: 40px; height: 40px;">
                                        <?php echo pika_icon('map-pin', '', 18); ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold text-dark">GPS Geolocation Checkpoint</h6>
                                        <small class="text-muted">
                                            <?php if ($attendanceDetail['check_in_latitude'] && $attendanceDetail['check_in_longitude']): ?>
                                            Lat: <?php echo htmlspecialchars($attendanceDetail['check_in_latitude']); ?>, Long: <?php echo htmlspecialchars($attendanceDetail['check_in_longitude']); ?>
                                            <?php else: ?>
                                            Location coordinates unavailable
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                <?php if ($attendanceDetail['check_in_latitude'] && $attendanceDetail['check_in_longitude']): ?>
                                <a href="https://maps.google.com/?q=<?php echo $attendanceDetail['check_in_latitude']; ?>,<?php echo $attendanceDetail['check_in_longitude']; ?>" 
                                   target="_blank" class="btn btn-sm btn-outline-primary">
                                    <?php echo pika_icon('map-pin', 'me-1', 14); ?> Open in Google Maps
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Decision Terminal -->
                        <?php if ($attendanceDetail['status'] === 'pending'): ?>
                        <div class="p-4 rounded-4 bg-white border border-2 border-primary-subtle shadow-sm">
                            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <?php echo pika_icon('shield-check', 'text-primary', 20); ?>
                                Verification Decision Terminal
                            </h6>
                            <form method="POST">
                                <input type="hidden" name="attendance_id" value="<?php echo $attendanceDetail['id']; ?>">
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-dark small">Supervisor Remarks (Optional)</label>
                                    <textarea class="form-control rounded-4 p-3" name="remarks" rows="2" 
                                              placeholder="Provide remarks or reason if rejecting..."></textarea>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="approval_action" value="approve" class="btn btn-primary btn-glow px-4">
                                        <?php echo pika_icon('check', 'me-1', 16); ?> Approve Check-in
                                    </button>
                                    <button type="submit" name="approval_action" value="reject" class="btn btn-outline-danger px-4">
                                        <?php echo pika_icon('x', 'me-1', 16); ?> Reject Check-in
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php elseif ($attendanceDetail['admin_remarks']): ?>
                        <div class="p-3 rounded-4 bg-light border">
                            <span class="text-muted small fw-semibold d-block mb-1">Supervisor Decision Remarks</span>
                            <p class="mb-0 text-dark small"><?php echo htmlspecialchars($attendanceDetail['admin_remarks']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Approvals Hero Banner -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-xl-7 col-lg-6">
                    <div class="saas-hero-content">
                        <h2 class="saas-hero-title">Attendance & Identity Verification Hub</h2>
                        <p class="saas-hero-subtitle">
                            Review employee GPS check-ins, biometric facial selfie records, and daily attendance logs with instant verification workflows.
                        </p>
                        <div class="saas-hero-pills">
                            <span class="saas-pill live-pulse">
                                <span class="pulse-dot"></span>
                                <?php echo $pendingCount; ?> Pending Verification
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('check-circle', '', 14); ?>
                                <?php echo $approvedToday; ?> Approved Today
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('trending-up', '', 14); ?>
                                <?php echo $approvalRate; ?>% Approval Rate
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-5 col-lg-6 mt-3 mt-lg-0 text-lg-end">
                    <div class="saas-hero-actions">
                        <a href="<?php echo url('admin/attendance'); ?>" class="btn btn-saas-light">
                            <?php echo pika_icon('calendar', '', 16); ?>
                            <span>Attendance Log</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Dynamic Verification Metric Cards -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge amber">
                            <?php echo pika_icon('clock', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill warning">Review Queue</span>
                    </div>
                    <div class="metric-value"><?php echo $pendingCount; ?></div>
                    <div class="metric-label">Pending Reviews</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge emerald">
                            <?php echo pika_icon('check-circle', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active">Verified</span>
                    </div>
                    <div class="metric-value"><?php echo $approvedToday; ?></div>
                    <div class="metric-label">Approved Today</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge rose">
                            <?php echo pika_icon('x', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill danger">Disapproved</span>
                    </div>
                    <div class="metric-value"><?php echo $rejectedTotal; ?></div>
                    <div class="metric-label">Total Rejected</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge sky">
                            <?php echo pika_icon('trending-up', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill sky"><?php echo $approvalRate; ?>%</span>
                    </div>
                    <div class="metric-value"><?php echo $approvalRate; ?>%</div>
                    <div class="metric-label">Overall Approval Rate</div>
                </div>
            </div>
        </div>

        <!-- Filter SaaS Toolbar -->
        <div class="saas-toolbar">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-lg-4 col-md-5">
                    <div class="d-flex gap-1 p-1 bg-light rounded-pill border">
                        <a href="<?php echo url('admin/approvals'); ?>?status=pending" 
                           class="btn btn-sm flex-fill <?php echo $statusFilter === 'pending' ? 'btn-primary' : 'btn-light border-0'; ?>">
                            Pending (<?php echo $pendingCount; ?>)
                        </a>
                        <a href="<?php echo url('admin/approvals'); ?>?status=approved" 
                           class="btn btn-sm flex-fill <?php echo $statusFilter === 'approved' ? 'btn-primary' : 'btn-light border-0'; ?>">
                            Approved
                        </a>
                        <a href="<?php echo url('admin/approvals'); ?>?status=rejected" 
                           class="btn btn-sm flex-fill <?php echo $statusFilter === 'rejected' ? 'btn-primary' : 'btn-light border-0'; ?>">
                            Rejected
                        </a>
                        <a href="<?php echo url('admin/approvals'); ?>?status=all" 
                           class="btn btn-sm flex-fill <?php echo $statusFilter === 'all' ? 'btn-primary' : 'btn-light border-0'; ?>">
                            All
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-3">
                    <input type="date" class="form-control rounded-pill px-3 py-2" name="date" 
                           value="<?php echo htmlspecialchars($dateFilter); ?>" placeholder="Filter by date">
                </div>
                <div class="col-lg-3 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-saas-dark flex-fill">
                        <?php echo pika_icon('filter', '', 15); ?>
                        <span>Apply</span>
                    </button>
                    <?php if ($dateFilter || ($statusFilter !== 'pending')): ?>
                    <a href="<?php echo url('admin/approvals'); ?>" class="btn btn-saas-light" title="Reset Filters">
                        <?php echo pika_icon('refresh', '', 15); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Verification Queue Table Card -->
        <div class="saas-card">
            <div class="saas-card-header">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="saas-card-title">
                        <?php echo pika_icon('shield-check', 'text-primary', 20); ?>
                        Verification Queue
                    </h5>
                    <span class="badge bg-light text-dark border px-2 py-1 rounded-pill small">
                        <?php echo count($attendances); ?> Records
                    </span>
                </div>
            </div>

            <div class="table-responsive">
                <?php if (count($attendances) > 0): ?>
                <table class="saas-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Total Time</th>
                            <th>GPS Location</th>
                            <th>Status</th>
                            <th class="text-end">Verification Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendances as $att): 
                            $initials = strtoupper(substr($att['name'], 0, 1) . (strpos($att['name'], ' ') !== false ? substr($att['name'], strpos($att['name'], ' ') + 1, 1) : ''));
                        ?>
                        <tr>
                            <td>
                                <div class="saas-user-cell">
                                    <?php if (!empty($att['avatar'])): ?>
                                    <img src="<?php echo getAvatar($att['avatar']); ?>" class="saas-avatar" alt="">
                                    <?php else: ?>
                                    <div class="saas-avatar-initials"><?php echo $initials ?: 'EM'; ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="saas-user-name"><?php echo htmlspecialchars($att['name']); ?></div>
                                        <span class="saas-id-badge" style="font-size: 0.72rem; padding: 2px 8px;">
                                            <?php echo htmlspecialchars($att['employee_id']); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo formatDate($att['date']); ?></div>
                                <small class="text-muted"><?php echo date('D', strtotime($att['date'])); ?></small>
                            </td>
                            <td>
                                <?php if ($att['check_in_time']): ?>
                                <span class="badge bg-light text-dark border px-2 py-1 rounded-pill fw-medium">
                                    <?php echo formatTime($att['check_in_time']); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($att['check_out_time']): ?>
                                <span class="badge bg-light text-dark border px-2 py-1 rounded-pill fw-medium">
                                    <?php echo formatTime($att['check_out_time']); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted small">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark"><?php echo $att['total_hours'] ?: '—'; ?></span>
                            </td>
                            <td>
                                <?php if ($att['check_in_latitude'] && $att['check_in_longitude']): ?>
                                <a href="https://maps.google.com/?q=<?php echo $att['check_in_latitude']; ?>,<?php echo $att['check_in_longitude']; ?>" 
                                   target="_blank" class="saas-tag sky" title="View Geolocation">
                                    <?php echo pika_icon('map-pin', '', 12); ?> Pin
                                </a>
                                <?php else: ?>
                                <span class="text-muted small">No GPS</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="saas-tag <?php 
                                    echo match($att['status']) {
                                        'approved' => 'emerald',
                                        'rejected' => 'rose',
                                        default => 'amber'
                                    };
                                ?>">
                                    <?php if ($att['status'] === 'pending'): ?>
                                    <span class="pulse-dot" style="width: 6px; height: 6px; border-radius: 50%; background: #F59E0B; display: inline-block;"></span>
                                    <?php endif; ?>
                                    <?php echo ucfirst($att['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?php echo url('admin/approvals'); ?>?action=view&id=<?php echo $att['id']; ?>" 
                                       class="saas-action-btn" title="Inspect Record">
                                        <?php echo pika_icon('eye', '', 15); ?>
                                    </a>
                                    <?php if ($att['status'] === 'pending'): ?>
                                    <button type="button" class="saas-action-btn success" title="Approve"
                                            onclick="quickApprove(<?php echo $att['id']; ?>, '<?php echo addslashes($att['name']); ?>')">
                                        <?php echo pika_icon('check', '', 15); ?>
                                    </button>
                                    <button type="button" class="saas-action-btn delete" title="Reject"
                                            onclick="promptReject(<?php echo $att['id']; ?>, '<?php echo addslashes($att['name']); ?>')">
                                        <?php echo pika_icon('x', '', 15); ?>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3 text-muted">
                        <?php echo pika_icon('check-circle', 'opacity-25', 64); ?>
                    </div>
                    <h5 class="fw-bold text-dark">All Caught Up!</h5>
                    <p class="text-muted small">
                        <?php if ($statusFilter === 'pending'): ?>
                        There are no pending attendance approvals waiting in the queue.
                        <?php else: ?>
                        No attendance records match your filter criteria.
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo url('admin/approvals'); ?>?status=pending" class="btn btn-outline-secondary btn-sm">
                        <?php echo pika_icon('refresh', 'me-1', 14); ?> Refresh Queue
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Action Form (Hidden) -->
<form id="approvalForm" method="POST" style="display: none;">
    <input type="hidden" name="attendance_id" id="approvalFormId">
    <input type="hidden" name="approval_action" id="approvalFormAction" value="approve">
    <input type="hidden" name="remarks" id="approvalFormRemarks" value="">
</form>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white border-0 px-4 py-3">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <?php echo pika_icon('x', 'text-white', 20); ?> Reject Attendance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-3">Rejecting attendance record for <strong id="rejectEmpName" class="text-dark"></strong>.</p>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-dark">Reason / Remarks (Optional)</label>
                    <textarea class="form-control rounded-3 p-3" id="rejectRemarksInput" rows="2" placeholder="e.g. Invalid selfie or location mismatch"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4" onclick="confirmReject()">
                    <?php echo pika_icon('x', 'me-1', 14); ?> Confirm Reject
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentRejectId = null;

function quickApprove(id, name) {
    if (confirm('Approve attendance check-in for ' + name + '?')) {
        document.getElementById('approvalFormId').value = id;
        document.getElementById('approvalFormAction').value = 'approve';
        document.getElementById('approvalFormRemarks').value = 'Approved by administrator';
        document.getElementById('approvalForm').submit();
    }
}

function promptReject(id, name) {
    currentRejectId = id;
    document.getElementById('rejectEmpName').textContent = name;
    document.getElementById('rejectRemarksInput').value = '';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function confirmReject() {
    if (!currentRejectId) return;
    const remarks = document.getElementById('rejectRemarksInput').value.trim();
    document.getElementById('approvalFormId').value = currentRejectId;
    document.getElementById('approvalFormAction').value = 'reject';
    document.getElementById('approvalFormRemarks').value = remarks || 'Rejected by administrator';
    document.getElementById('approvalForm').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
