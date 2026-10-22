<?php
/**
 * Admin Attendance Management - SaaS Attendance Console
 * Employee Management System
 */

$pageTitle = 'Attendance Management';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$message = '';
$messageType = '';

// Handle manual attendance posting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_attendance'])) {
    $employeeId = intval($_POST['employee_id']);
    $attendanceDate = sanitize($_POST['attendance_date']);
    $checkInTime = sanitize($_POST['check_in_time'] ?? '');
    $checkOutTime = sanitize($_POST['check_out_time'] ?? '');
    $status = sanitize($_POST['status'] ?? 'approved');
    
    if ($employeeId > 0 && !empty($attendanceDate)) {
        // Calculate total hours if both times provided
        $totalHours = null;
        if (!empty($checkInTime) && !empty($checkOutTime)) {
            try {
                $checkInParts = explode(':', $checkInTime);
                $checkOutParts = explode(':', $checkOutTime);
                
                $checkInMinutes = intval($checkInParts[0]) * 60 + intval($checkInParts[1]);
                $checkOutMinutes = intval($checkOutParts[0]) * 60 + intval($checkOutParts[1]);
                
                if ($checkOutMinutes >= $checkInMinutes) {
                    $diffMinutes = $checkOutMinutes - $checkInMinutes;
                } else {
                    $diffMinutes = (24 * 60 - $checkInMinutes) + $checkOutMinutes;
                }
                
                $hours = floor($diffMinutes / 60);
                $minutes = $diffMinutes % 60;
                $totalHours = sprintf('%02d:%02d:00', $hours, $minutes);
            } catch (Exception $e) {
                $totalHours = '00:00:00';
            }
        } else {
            $totalHours = '00:00:00';
        }
        
        $checkInTimeFormatted = !empty($checkInTime) ? $attendanceDate . ' ' . $checkInTime . ':00' : null;
        $checkOutTimeFormatted = !empty($checkOutTime) ? $attendanceDate . ' ' . $checkOutTime . ':00' : null;
        
        // Check if attendance already exists for this date
        $existing = fetchOne(
            "SELECT id FROM attendance WHERE employee_id = ? AND date = ?",
            "is",
            [$employeeId, $attendanceDate]
        );
        
        if ($existing) {
            $result = executeQuery(
                "UPDATE attendance SET check_in_time = ?, check_out_time = ?, total_hours = ?, status = ? WHERE employee_id = ? AND date = ?",
                "sssssi",
                [$checkInTimeFormatted, $checkOutTimeFormatted, $totalHours, $status, $employeeId, $attendanceDate]
            );
            
            if ($result) {
                $message = 'Attendance record updated successfully!';
                $messageType = 'success';
                $employee = fetchOne("SELECT name FROM employees WHERE id = ?", "i", [$employeeId]);
                logActivity('manual_attendance', "Manual attendance updated for {$employee['name']} on {$attendanceDate}");
            } else {
                $message = 'Failed to update attendance record.';
                $messageType = 'danger';
            }
        } else {
            $result = executeQuery(
                "INSERT INTO attendance (employee_id, date, check_in_time, check_out_time, total_hours, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                "isssss",
                [$employeeId, $attendanceDate, $checkInTimeFormatted, $checkOutTimeFormatted, $totalHours, $status]
            );
            
            if ($result) {
                $message = 'Attendance posted successfully!';
                $messageType = 'success';
                $employee = fetchOne("SELECT name FROM employees WHERE id = ?", "i", [$employeeId]);
                logActivity('manual_attendance', "Manual attendance posted for {$employee['name']} on {$attendanceDate}");
            } else {
                $message = 'Failed to post attendance.';
                $messageType = 'danger';
            }
        }
    } else {
        $message = 'Please specify both Employee and Date!';
        $messageType = 'danger';
    }
}

// Filters
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$employeeFilter = $_GET['employee'] ?? '';

$whereClause = "WHERE MONTH(a.date) = ? AND YEAR(a.date) = ?";
$params = [$month, $year];
$types = "ii";

if ($employeeFilter) {
    $whereClause .= " AND a.employee_id = ?";
    $params[] = $employeeFilter;
    $types .= "i";
}

$attendance = fetchAll(
    "SELECT a.*, e.name, e.employee_id as emp_id, e.avatar, d.name as domain_name 
     FROM attendance a 
     JOIN employees e ON a.employee_id = e.id 
     LEFT JOIN domains d ON e.domain_id = d.id
     $whereClause 
     ORDER BY a.date DESC, a.check_in_time DESC",
    $types,
    $params
);

$employees = fetchAll("SELECT id, name, employee_id FROM employees WHERE status = 'active' ORDER BY name");

// Summary Metrics
$summary = fetchOne(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
     FROM attendance 
     WHERE MONTH(date) = ? AND YEAR(date) = ?",
    "ii",
    [$month, $year]
);

$totalRecords = $summary['total'] ?? 0;
$approvedRecords = $summary['approved'] ?? 0;
$pendingRecords = $summary['pending'] ?? 0;
$rejectedRecords = $summary['rejected'] ?? 0;
$complianceRate = $totalRecords > 0 ? round(($approvedRecords / $totalRecords) * 100) : 100;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Attendance Intelligence</h1>
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
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <?php echo pika_icon($messageType === 'success' ? 'check-circle' : 'x', 'flex-shrink-0', 20); ?>
                <div><?php echo $message; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Attendance Hero Banner -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-xl-6 col-lg-5">
                    <div class="saas-hero-content">
                        <h2 class="saas-hero-title">Workforce Attendance Console</h2>
                        <p class="saas-hero-subtitle">
                            Monitor organizational shift hours, check-in punctuality, geolocation telemetry, and manual attendance adjustments for <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>.
                        </p>
                        <div class="saas-hero-pills">
                            <span class="saas-pill live-pulse">
                                <span class="pulse-dot"></span>
                                <?php echo $totalRecords; ?> Logged Sessions
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('check-circle', '', 14); ?>
                                <?php echo $approvedRecords; ?> Verified (<?php echo $complianceRate; ?>%)
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('clock', '', 14); ?>
                                <?php echo $pendingRecords; ?> Pending Approval
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 col-lg-7 mt-3 mt-lg-0 text-lg-end">
                    <div class="saas-hero-actions">
                        <button class="btn btn-saas-dark" type="button" data-bs-toggle="collapse" data-bs-target="#manualAttendanceCard">
                            <?php echo pika_icon('plus', '', 16); ?>
                            <span>Post Manual Entry</span>
                        </button>
                        <a href="<?php echo url('admin/reports'); ?>?type=attendance&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-saas-light">
                            <?php echo pika_icon('download', '', 16); ?>
                            <span>Export Report</span>
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
                            <?php echo pika_icon('calendar', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active">Monthly</span>
                    </div>
                    <div class="metric-value"><?php echo $totalRecords; ?></div>
                    <div class="metric-label">Total Logged Entries</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge emerald">
                            <?php echo pika_icon('check-circle', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active"><?php echo $complianceRate; ?>% Verified</span>
                    </div>
                    <div class="metric-value"><?php echo $approvedRecords; ?></div>
                    <div class="metric-label">Approved Records</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge amber">
                            <?php echo pika_icon('clock', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill warning">Pending</span>
                    </div>
                    <div class="metric-value"><?php echo $pendingRecords; ?></div>
                    <div class="metric-label">Pending Approval</div>
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
                    <div class="metric-value"><?php echo $rejectedRecords; ?></div>
                    <div class="metric-label">Rejected Records</div>
                </div>
            </div>
        </div>

        <!-- Collapsible Manual Attendance Card -->
        <div class="collapse mb-4" id="manualAttendanceCard">
            <div class="saas-card border-primary border-opacity-25 shadow">
                <div class="saas-card-header bg-light">
                    <h5 class="saas-card-title">
                        <?php echo pika_icon('edit', 'text-primary', 20); ?>
                        Post / Override Manual Attendance Log
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#manualAttendanceCard">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="saas-card-body p-4">
                    <form method="POST" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark small">Employee <span class="text-danger">*</span></label>
                            <select class="form-select rounded-pill px-3 py-2" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>">
                                    <?php echo htmlspecialchars($emp['name']); ?> (<?php echo htmlspecialchars($emp['employee_id']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark small">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control rounded-pill px-3 py-2" name="attendance_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold text-dark small">Check In Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control rounded-pill px-3 py-2" name="check_in_time" required value="09:30">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold text-dark small">Check Out Time</label>
                            <input type="time" class="form-control rounded-pill px-3 py-2" name="check_out_time" value="18:30">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold text-dark small">Status Policy</label>
                            <select class="form-select rounded-pill px-3 py-2" name="status">
                                <option value="approved" selected>Approved</option>
                                <option value="pending">Pending</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-12 mt-3 pt-2">
                            <button type="submit" name="post_attendance" class="btn btn-saas-dark px-4">
                                <?php echo pika_icon('check', '', 16); ?>
                                <span>Submit Record</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Filter SaaS Toolbar -->
        <div class="saas-toolbar">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-lg-3 col-md-3">
                    <select class="form-select saas-select" name="month">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $month == $i ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-2">
                    <select class="form-select saas-select" name="year">
                        <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                        <option value="<?php echo $i; ?>" <?php echo $year == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-lg-4 col-md-4">
                    <select class="form-select saas-select" name="employee">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php echo $employeeFilter == $emp['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($emp['name']); ?> (<?php echo htmlspecialchars($emp['employee_id']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-saas-dark flex-fill">
                        <?php echo pika_icon('filter', '', 15); ?>
                        <span>Apply</span>
                    </button>
                    <?php if ($employeeFilter || $month != date('m') || $year != date('Y')): ?>
                    <a href="<?php echo url('admin/attendance'); ?>" class="btn btn-saas-light" title="Reset Filters">
                        <?php echo pika_icon('refresh', '', 15); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Attendance Records Table Card -->
        <div class="saas-card">
            <div class="saas-card-header">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="saas-card-title">
                        <?php echo pika_icon('calendar', 'text-primary', 20); ?>
                        Attendance Log Directory
                    </h5>
                    <span class="badge bg-light text-dark border px-2 py-1 rounded-pill small">
                        <?php echo count($attendance); ?> Records Found
                    </span>
                </div>
                <a href="<?php echo url('admin/reports'); ?>?type=attendance&month=<?php echo $month; ?>&year=<?php echo $year; ?>&export=csv" 
                   class="btn btn-sm btn-saas-light">
                    <?php echo pika_icon('download', '', 14); ?>
                    <span>Export CSV</span>
                </a>
            </div>

            <div class="table-responsive">
                <?php if (count($attendance) > 0): ?>
                <table class="saas-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Duration</th>
                            <th>GPS Pin</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance as $att): 
                            $initials = strtoupper(substr($att['name'], 0, 1) . (strpos($att['name'], ' ') !== false ? substr($att['name'], strpos($att['name'], ' ') + 1, 1) : ''));
                            
                            // Format Total Hours
                            $durationDisplay = '—';
                            $hourDecimal = 0;
                            if (!empty($att['total_hours'])) {
                                if (strpos($att['total_hours'], ':') !== false) {
                                    $parts = explode(':', $att['total_hours']);
                                    $hourDecimal = intval($parts[0]) + intval($parts[1]) / 60;
                                    $durationDisplay = number_format($hourDecimal, 1) . ' hrs';
                                } else if (is_numeric($att['total_hours'])) {
                                    $hourDecimal = floatval($att['total_hours']);
                                    $durationDisplay = number_format($hourDecimal, 1) . ' hrs';
                                }
                            }
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
                                            <?php echo htmlspecialchars($att['emp_id']); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo formatDate($att['date']); ?></div>
                                <small class="text-muted"><?php echo date('l', strtotime($att['date'])); ?></small>
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
                                <span class="text-muted small">In Session</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hourDecimal >= 8): ?>
                                <span class="saas-tag emerald"><?php echo $durationDisplay; ?></span>
                                <?php elseif ($hourDecimal >= 4): ?>
                                <span class="saas-tag sky"><?php echo $durationDisplay; ?></span>
                                <?php elseif ($hourDecimal > 0): ?>
                                <span class="saas-tag amber"><?php echo $durationDisplay; ?></span>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($att['check_in_latitude'] && $att['check_in_longitude']): ?>
                                <a href="https://maps.google.com/?q=<?php echo $att['check_in_latitude']; ?>,<?php echo $att['check_in_longitude']; ?>" 
                                   target="_blank" class="saas-tag sky" title="View GPS Location">
                                    <?php echo pika_icon('map-pin', '', 12); ?> Pin
                                </a>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
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
                                    <?php echo ucfirst($att['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="<?php echo url('admin/approvals'); ?>?action=view&id=<?php echo $att['id']; ?>" 
                                   class="saas-action-btn" title="Inspect Verification Details">
                                    <?php echo pika_icon('eye', '', 15); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3 text-muted">
                        <?php echo pika_icon('calendar', 'opacity-25', 64); ?>
                    </div>
                    <h5 class="fw-bold text-dark">No Attendance Logs Found</h5>
                    <p class="text-muted small">No attendance records match your month, year, or employee filters.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
