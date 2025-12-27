<?php
/**
 * Admin Attendance Management
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
        $totalHoursDecimal = 0;
        if (!empty($checkInTime) && !empty($checkOutTime)) {
            try {
                // Parse times as HH:MM format and calculate difference
                $checkInParts = explode(':', $checkInTime);
                $checkOutParts = explode(':', $checkOutTime);
                
                $checkInMinutes = intval($checkInParts[0]) * 60 + intval($checkInParts[1]);
                $checkOutMinutes = intval($checkOutParts[0]) * 60 + intval($checkOutParts[1]);
                
                if ($checkOutMinutes >= $checkInMinutes) {
                    $diffMinutes = $checkOutMinutes - $checkInMinutes;
                } else {
                    // Handle case where check out is next day
                    $diffMinutes = (24 * 60 - $checkInMinutes) + $checkOutMinutes;
                }
                
                // Calculate decimal hours for storage
                $totalHoursDecimal = round($diffMinutes / 60, 2);
                
                // Convert to TIME format (HH:MM:SS) for database storage
                $hours = floor($diffMinutes / 60);
                $minutes = $diffMinutes % 60;
                $totalHours = sprintf('%02d:%02d:00', $hours, $minutes);
            } catch (Exception $e) {
                $totalHours = '00:00:00';
                $totalHoursDecimal = 0;
            }
        } else {
            $totalHours = '00:00:00';
        }
        
        // Store times as full DATETIME (date + time in HH:MM:SS format)
        $checkInTimeFormatted = !empty($checkInTime) ? $attendanceDate . ' ' . $checkInTime . ':00' : null;
        $checkOutTimeFormatted = !empty($checkOutTime) ? $attendanceDate . ' ' . $checkOutTime . ':00' : null;
        
        // Check if attendance already exists for this date
        $existing = fetchOne(
            "SELECT id FROM attendance WHERE employee_id = ? AND date = ?",
            "is",
            [$employeeId, $attendanceDate]
        );
        
        if ($existing) {
            // Update existing record
            $result = executeQuery(
                "UPDATE attendance SET check_in_time = ?, check_out_time = ?, total_hours = ?, status = ? WHERE employee_id = ? AND date = ?",
                "sssssi",
                [$checkInTimeFormatted, $checkOutTimeFormatted, $totalHours, $status, $employeeId, $attendanceDate]
            );
            
            if ($result) {
                $message = 'Attendance updated successfully!';
                $messageType = 'success';
                
                // Log activity
                $employee = fetchOne("SELECT name FROM employees WHERE id = ?", "i", [$employeeId]);
                logActivity('manual_attendance', "Manual attendance updated for {$employee['name']} on {$attendanceDate}");
            } else {
                $message = 'Failed to update attendance!';
                $messageType = 'danger';
            }
        } else {
            // Insert new record
            $result = executeQuery(
                "INSERT INTO attendance (employee_id, date, check_in_time, check_out_time, total_hours, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                "isssss",
                [$employeeId, $attendanceDate, $checkInTimeFormatted, $checkOutTimeFormatted, $totalHours, $status]
            );
            
            if ($result) {
                $message = 'Attendance posted successfully!';
                $messageType = 'success';
                
                // Log activity
                $employee = fetchOne("SELECT name FROM employees WHERE id = ?", "i", [$employeeId]);
                logActivity('manual_attendance', "Manual attendance posted for {$employee['name']} on {$attendanceDate}");
            } else {
                $message = 'Failed to post attendance!';
                $messageType = 'danger';
            }
        }
    } else {
        $message = 'Please fill in all required fields!';
        $messageType = 'danger';
    }
}

// Get filters
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
     ORDER BY a.date DESC, e.name ASC",
    $types,
    $params
);

$employees = fetchAll("SELECT id, name, employee_id FROM employees WHERE status = 'active' ORDER BY name");

// Get summary
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

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Attendance Management</h1>
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
                    <li><a class="dropdown-item" href="<?php echo url('admin/profile'); ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo url('logout'); ?>"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <div class="content-wrapper">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Manual Attendance Entry -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Post Manual Attendance</h5>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#manualAttendanceForm">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div class="collapse" id="manualAttendanceForm">
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>">
                                    <?php echo $emp['name']; ?> (<?php echo $emp['employee_id']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="attendance_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Check In Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="check_in_time" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Check Out Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="check_out_time" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status">
                                <option value="approved" selected>Approved</option>
                                <option value="pending">Pending</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="post_attendance" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Post Attendance
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-6 col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon primary"><i class="bi bi-calendar-check"></i></div>
                    <div class="stats-info">
                        <h3><?php echo $summary['total'] ?? 0; ?></h3>
                        <p>Total Records</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon success"><i class="bi bi-check-circle"></i></div>
                    <div class="stats-info">
                        <h3><?php echo $summary['approved'] ?? 0; ?></h3>
                        <p>Approved</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon warning"><i class="bi bi-clock"></i></div>
                    <div class="stats-info">
                        <h3><?php echo $summary['pending'] ?? 0; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon danger"><i class="bi bi-x-circle"></i></div>
                    <div class="stats-info">
                        <h3><?php echo $summary['rejected'] ?? 0; ?></h3>
                        <p>Rejected</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter & Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Attendance Records</h5>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <form class="row g-3 mb-4" method="GET">
                    <div class="col-md-2">
                        <select class="form-select" name="month">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $month == $i ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="year">
                            <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $year == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="employee">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo $employeeFilter == $emp['id'] ? 'selected' : ''; ?>>
                                <?php echo $emp['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="<?php echo url('admin/reports'); ?>?type=attendance&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-outline-success w-100">
                            <i class="bi bi-download me-1"></i>Export
                        </a>
                    </div>
                </form>
                
                <?php if (count($attendance) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Hours</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance as $att): ?>
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
                                <td><?php echo $att['check_in_time'] ? formatTime($att['check_in_time']) : '-'; ?></td>
                                <td><?php echo $att['check_out_time'] ? formatTime($att['check_out_time']) : '-'; ?></td>
                                <td><?php 
                                    if ($att['total_hours'] !== null && $att['total_hours'] !== '') {
                                        $totalHours = $att['total_hours'];
                                        // Check if it's in TIME format (HH:MM:SS) or decimal
                                        if (is_numeric($totalHours) && strpos($totalHours, ':') === false) {
                                            // Already a decimal number
                                            echo number_format((float)$totalHours, 2) . ' hrs';
                                        } else if (strpos($totalHours, ':') !== false) {
                                            // TIME format - convert to decimal hours
                                            $timeParts = explode(':', $totalHours);
                                            $hours = intval($timeParts[0]) + intval($timeParts[1]) / 60 + intval($timeParts[2]) / 3600;
                                            echo number_format($hours, 2) . ' hrs';
                                        } else {
                                            echo '-';
                                        }
                                    } else {
                                        echo '-';
                                    }
                                ?></td>
                                <td><span class="badge badge-<?php echo $att['status']; ?>"><?php echo ucfirst($att['status']); ?></span></td>
                                <td>
                                    <a href="<?php echo url('admin/approvals'); ?>?action=view&id=<?php echo $att['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state py-5">
                    <i class="bi bi-calendar-x"></i>
                    <h4>No Records Found</h4>
                    <p>No attendance records for the selected period.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
