<?php
/**
 * Team Lead - Check In/Out
 * Employee Management System
 */

$pageTitle = 'Check In/Out';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (!isTeamLead()) {
    header("Location: " . url("employee/dashboard"));
    exit;
}

$userId = $_SESSION['user_id'];
$today = date('Y-m-d');
$currentTime = date('H:i:s');
$message = '';
$messageType = '';

// Get today's attendance record
$todayAttendance = fetchOne(
    "SELECT * FROM attendance WHERE employee_id = ? AND date = ?",
    "is",
    [$userId, $today]
);

// Handle check-in/check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'checkin' && !$todayAttendance) {
        // Determine status based on check-in time
        $checkInHour = (int)date('H');
        $status = 'present';
        if ($checkInHour >= 10) {
            $status = 'late';
        }
        
        $result = insert(
            "INSERT INTO attendance (employee_id, date, check_in_time, status, created_at) VALUES (?, ?, ?, ?, NOW())",
            "isss",
            [$userId, $today, $currentTime, $status]
        );
        
        if ($result) {
            $message = "Successfully checked in at " . date('h:i A');
            $messageType = 'success';
            $todayAttendance = fetchOne(
                "SELECT * FROM attendance WHERE employee_id = ? AND date = ?",
                "is",
                [$userId, $today]
            );
        } else {
            $message = "Failed to check in. Please try again.";
            $messageType = 'danger';
        }
    } elseif ($action === 'checkout' && $todayAttendance && !$todayAttendance['check_out_time']) {
        // Calculate total hours
        $checkIn = new DateTime($todayAttendance['check_in_time']);
        $checkOut = new DateTime($currentTime);
        $interval = $checkIn->diff($checkOut);
        $totalHours = round($interval->h + ($interval->i / 60), 2);
        
        $result = update(
            "UPDATE attendance SET check_out_time = ?, total_hours = ?, updated_at = NOW() WHERE id = ?",
            "sdi",
            [$currentTime, $totalHours, $todayAttendance['id']]
        );
        
        if ($result) {
            $message = "Successfully checked out at " . date('h:i A') . ". Total hours: " . $totalHours;
            $messageType = 'success';
            $todayAttendance = fetchOne(
                "SELECT * FROM attendance WHERE employee_id = ? AND date = ?",
                "is",
                [$userId, $today]
            );
        } else {
            $message = "Failed to check out. Please try again.";
            $messageType = 'danger';
        }
    }
}

// Get recent attendance records
$recentAttendance = fetchAll(
    "SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 7",
    "i",
    [$userId]
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/teamlead-sidebar.php';
?>

<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Check In/Out</h1>
        </div>
    </header>

    <div class="content-wrapper">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-6">
                <!-- Check In/Out Card -->
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <h2 id="current-time" class="display-4 fw-bold text-primary"><?php echo date('h:i:s A'); ?></h2>
                            <p class="text-muted"><?php echo date('l, F d, Y'); ?></p>
                        </div>

                        <?php if (!$todayAttendance): ?>
                        <!-- Not checked in yet -->
                        <div class="attendance-status mb-4">
                            <span class="badge bg-warning fs-6 px-4 py-2">Not Checked In</span>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="checkin">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Check In
                            </button>
                        </form>
                        
                        <?php elseif (!$todayAttendance['check_out_time']): ?>
                        <!-- Checked in, not checked out -->
                        <div class="attendance-status mb-4">
                            <span class="badge bg-success fs-6 px-4 py-2">Checked In</span>
                            <p class="text-muted mt-2">Check-in time: <?php echo formatTime($todayAttendance['check_in_time']); ?></p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="checkout">
                            <button type="submit" class="btn btn-danger btn-lg px-5">
                                <i class="bi bi-box-arrow-right me-2"></i>Check Out
                            </button>
                        </form>
                        
                        <?php else: ?>
                        <!-- Completed for today -->
                        <div class="attendance-status mb-4">
                            <span class="badge bg-primary fs-6 px-4 py-2">Completed</span>
                        </div>
                        <div class="d-flex justify-content-center gap-4">
                            <div>
                                <small class="text-muted d-block">Check In</small>
                                <strong><?php echo formatTime($todayAttendance['check_in_time']); ?></strong>
                            </div>
                            <div>
                                <small class="text-muted d-block">Check Out</small>
                                <strong><?php echo formatTime($todayAttendance['check_out_time']); ?></strong>
                            </div>
                            <div>
                                <small class="text-muted d-block">Total Hours</small>
                                <strong><?php echo $todayAttendance['total_hours']; ?></strong>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <!-- Recent Attendance -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Attendance</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Hours</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAttendance as $att): ?>
                                    <tr>
                                        <td><?php echo formatDate($att['date']); ?></td>
                                        <td><?php echo formatTime($att['check_in_time']); ?></td>
                                        <td><?php echo $att['check_out_time'] ? formatTime($att['check_out_time']) : '-'; ?></td>
                                        <td><?php echo $att['total_hours'] ?? '-'; ?></td>
                                        <td><span class="badge badge-<?php echo $att['status']; ?>"><?php echo ucfirst($att['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update clock in real-time
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: true 
    });
    document.getElementById('current-time').textContent = timeString;
}
setInterval(updateClock, 1000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
