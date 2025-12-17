<?php
/**
 * Team Lead Dashboard
 * Employee Management System
 */

$pageTitle = 'Team Lead Dashboard';
require_once __DIR__ . '/../config/config.php';
requireLogin();

// Check if user is Team Lead
if (!isTeamLead()) {
    if (isAdmin()) {
        header("Location: " . APP_URL . "/admin/dashboard.php");
    } elseif (isManager() || isHR()) {
        header("Location: " . APP_URL . "/manager/dashboard.php");
    } else {
        header("Location: " . APP_URL . "/employee/dashboard.php");
    }
    exit;
}

$userId = $_SESSION['user_id'];

// Get current user's employee record to check requires_checkin
$currentEmployee = fetchOne("SELECT * FROM employees WHERE id = ?", "i", [$userId]);
$requiresCheckin = $currentEmployee['requires_checkin'] ?? 1;

// Get team members
$teamMembers = getTeamMembers($userId);
$teamMemberIds = getTeamMemberIds($userId);

// Get statistics
$teamSize = count($teamMembers);
$pendingApprovals = 0;
$todayPresent = 0;
$activeTasks = 0;

if (!empty($teamMemberIds)) {
    $placeholders = implode(',', array_fill(0, count($teamMemberIds), '?'));
    $types = str_repeat('i', count($teamMemberIds));
    
    $pendingApprovals = fetchOne(
        "SELECT COUNT(*) as count FROM attendance WHERE status = 'pending' AND employee_id IN ($placeholders)",
        $types,
        $teamMemberIds
    )['count'] ?? 0;
    
    $todayPresent = fetchOne(
        "SELECT COUNT(*) as count FROM attendance WHERE date = CURDATE() AND employee_id IN ($placeholders)",
        $types,
        $teamMemberIds
    )['count'] ?? 0;
    
    $activeTasks = fetchOne(
        "SELECT COUNT(*) as count FROM tasks WHERE status IN ('not_started', 'in_progress') AND assigned_to IN ($placeholders)",
        $types,
        $teamMemberIds
    )['count'] ?? 0;
}

// Get pending approvals list
$pendingList = [];
if (!empty($teamMemberIds)) {
    $placeholders = implode(',', array_fill(0, count($teamMemberIds), '?'));
    $pendingList = fetchAll(
        "SELECT a.*, e.name, e.employee_id as emp_id, d.name as domain_name, e.avatar
         FROM attendance a 
         JOIN employees e ON a.employee_id = e.id 
         LEFT JOIN domains d ON e.domain_id = d.id
         WHERE a.status = 'pending' AND a.employee_id IN ($placeholders)
         ORDER BY a.created_at DESC LIMIT 5",
        str_repeat('i', count($teamMemberIds)),
        $teamMemberIds
    );
}

// Get my own attendance for today (only if requires check-in)
$myAttendance = null;
if ($requiresCheckin) {
    $myAttendance = fetchOne(
        "SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()",
        "i",
        [$userId]
    );
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/teamlead-sidebar.php';
?>

<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Team Lead Dashboard</h1>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <button class="header-icon-btn" data-bs-toggle="dropdown">
                    <i class="bi bi-bell"></i>
                    <?php if ($pendingApprovals > 0): ?>
                    <span class="notification-badge"><?php echo $pendingApprovals; ?></span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <?php if ($pendingApprovals > 0): ?>
                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/teamlead/approvals.php">
                        <i class="bi bi-clock-history me-2"></i><?php echo $pendingApprovals; ?> team pending approvals
                    </a></li>
                    <?php else: ?>
                    <li><span class="dropdown-item text-muted">No new notifications</span></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="dropdown">
                <button class="header-user-btn" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="User" class="avatar avatar-sm">
                    <span class="d-none d-md-inline ms-2"><?php echo $_SESSION['user_name']; ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/teamlead/profile.php">
                        <i class="bi bi-person me-2"></i>Profile
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="content-wrapper">
        <!-- Welcome Banner -->
        <div class="welcome-banner mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <span class="welcome-wave">👋</span> 
                        Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>, <?php echo $_SESSION['user_name']; ?>!
                    </h2>
                    <p class="mb-0 opacity-75">
                        You have <strong><?php echo $teamSize; ?></strong> team members and <strong><?php echo $pendingApprovals; ?></strong> pending approvals.
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <p class="mb-0 text-white-50"><?php echo date('l, F j, Y'); ?></p>
                </div>
            </div>
        </div>

        <?php if ($requiresCheckin): ?>
        <!-- My Status -->
        <div class="attendance-status-card mb-4">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="status-icon <?php echo $myAttendance ? ($myAttendance['check_out_time'] ? 'completed' : 'working') : 'pending'; ?>">
                        <i class="bi bi-<?php echo $myAttendance ? ($myAttendance['check_out_time'] ? 'check-circle-fill' : 'clock-fill') : 'box-arrow-in-right'; ?>"></i>
                    </div>
                    <div class="ms-3">
                        <?php if (!$myAttendance): ?>
                            <h6 class="mb-0">You haven't checked in today</h6>
                            <small class="text-muted">Click the button to check in</small>
                        <?php elseif (!$myAttendance['check_out_time']): ?>
                            <h6 class="mb-0">Working since <?php echo formatTime($myAttendance['check_in_time']); ?></h6>
                            <small class="text-muted">Don't forget to check out!</small>
                        <?php else: ?>
                            <h6 class="mb-0 text-success">Day Complete!</h6>
                            <small class="text-muted">Checked out at <?php echo formatTime($myAttendance['check_out_time']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="<?php echo APP_URL; ?>/teamlead/checkin.php" class="btn btn-gradient btn-sm">
                    <i class="bi bi-camera-video me-1"></i>
                    <?php echo !$myAttendance ? 'Check In' : (!$myAttendance['check_out_time'] ? 'Check Out' : 'View'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stats-info">
                            <h3><?php echo $teamSize; ?></h3>
                            <p>Team Members</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stats-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-person-check"></i>
                        </div>
                        <div class="stats-info">
                            <h3><?php echo $todayPresent; ?></h3>
                            <p>Present Today</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stats-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="stats-info">
                            <h3><?php echo $pendingApprovals; ?></h3>
                            <p>Pending Approvals</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stats-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-list-task"></i>
                        </div>
                        <div class="stats-info">
                            <h3><?php echo $activeTasks; ?></h3>
                            <p>Active Tasks</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Pending Approvals -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Pending Approvals</h5>
                        <a href="<?php echo APP_URL; ?>/teamlead/approvals.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($pendingList) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($pendingList as $pending): ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo getAvatar($pending['avatar']); ?>" class="avatar avatar-sm me-3" alt="">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"><?php echo $pending['name']; ?></div>
                                        <small class="text-muted">
                                            <?php echo formatDate($pending['date']); ?> at <?php echo formatTime($pending['check_in_time']); ?>
                                        </small>
                                    </div>
                                    <a href="<?php echo APP_URL; ?>/teamlead/approvals.php?action=view&id=<?php echo $pending['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle display-4"></i>
                            <p class="mt-2">No pending approvals from your team</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Team Members -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0"><i class="bi bi-people me-2"></i>My Team</h5>
                        <a href="<?php echo APP_URL; ?>/teamlead/team.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($teamMembers) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($teamMembers, 0, 5) as $member): ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo getAvatar($member['avatar']); ?>" class="avatar avatar-sm me-3" alt="">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"><?php echo $member['name']; ?></div>
                                        <small class="text-muted"><?php echo $member['employee_id']; ?> - <?php echo $member['designation'] ?? 'Employee'; ?></small>
                                    </div>
                                    <span class="badge badge-<?php echo $member['status']; ?>"><?php echo ucfirst($member['status']); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-people display-4"></i>
                            <p class="mt-2">No team members assigned to you</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
