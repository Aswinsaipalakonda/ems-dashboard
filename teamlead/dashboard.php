<?php
/**
 * Team Lead Dashboard
 * Employee Management System
 */

$pageTitle = 'Team Lead Dashboard';
require_once __DIR__ . '/../config/config.php';
requireLogin();

// Process auto-checkout if applicable
checkAutoCheckout();

// Check if user is Team Lead
if (!isTeamLead()) {
    if (isAdmin()) {
        header("Location: " . url('admin/dashboard'));
    } elseif (isManager() || isHR()) {
        header("Location: " . url('manager/dashboard'));
    } else {
        header("Location: " . url('employee/dashboard'));
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
                    <li><a class="dropdown-item" href="<?php echo url('teamlead/approvals'); ?>">
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
                    <li><a class="dropdown-item" href="<?php echo url('teamlead/profile'); ?>">
                        <i class="bi bi-person me-2"></i>Profile
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo url('logout'); ?>">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="content-wrapper">
        <!-- SaaS Hero -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-xl-8 col-lg-7">
                    <div class="saas-hero-content">
                        <div class="saas-hero-pills">
                            <span class="saas-pill">
                                <?php echo pika_icon('sparkles', '', 14); ?>
                                Squad Oversight &bull; Team Lead
                            </span>
                        </div>
                        <h2 class="saas-hero-title">
                            Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </h2>
                        <p class="saas-hero-subtitle">
                            Oversee your team sprint, review direct report check-ins, and manage task assignments in real-time.
                        </p>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-5 mt-3 mt-lg-0 text-lg-end">
                    <div class="d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                        <a href="<?php echo url('teamlead/approvals'); ?>" class="btn btn-saas-light">
                            <?php echo pika_icon('check-circle', '', 15); ?>
                            <span>Reviews (<?php echo $pendingApprovals; ?>)</span>
                        </a>
                        <a href="<?php echo url('teamlead/team-tasks'); ?>" class="btn btn-saas-dark">
                            <?php echo pika_icon('tasks', '', 15); ?>
                            <span>Team Tasks</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($requiresCheckin): ?>
        <!-- My Check-in Status Card -->
        <div class="saas-card mb-4">
            <div class="p-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="metric-icon-badge <?php echo $myAttendance ? ($myAttendance['check_out_time'] ? 'green' : 'blue') : 'amber'; ?>" style="width: 44px; height: 44px;">
                        <?php echo pika_icon($myAttendance ? ($myAttendance['check_out_time'] ? 'check-circle' : 'clock') : 'login', '', 20); ?>
                    </div>
                    <div>
                        <?php if (!$myAttendance): ?>
                            <h6 class="fw-bold text-dark mb-0">Daily Check-In Pending</h6>
                            <small class="text-muted">You haven't logged your geofenced check-in for today yet.</small>
                        <?php elseif (!$myAttendance['check_out_time']): ?>
                            <h6 class="fw-bold text-dark mb-0">Active Shift Logged</h6>
                            <small class="text-muted">Working since <?php echo formatTime($myAttendance['check_in_time']); ?> &bull; Remember to check out before shift end.</small>
                        <?php else: ?>
                            <h6 class="fw-bold text-success mb-0">Shift Completed Today</h6>
                            <small class="text-muted">Logged out at <?php echo formatTime($myAttendance['check_out_time']); ?>.</small>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="<?php echo url('teamlead/checkin'); ?>" class="btn btn-saas-dark btn-sm">
                    <?php echo pika_icon('camera', '', 14); ?>
                    <span><?php echo !$myAttendance ? 'Check In Now' : (!$myAttendance['check_out_time'] ? 'Check Out' : 'View Shift Log'); ?></span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 4 Metric Cards -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">SQUAD MEMBERS</span>
                        <div class="metric-icon-badge blue">
                            <?php echo pika_icon('users', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $teamSize; ?></div>
                    <div class="metric-label">Assigned Teammates</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">PRESENT TODAY</span>
                        <div class="metric-icon-badge green">
                            <?php echo pika_icon('check-circle', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $todayPresent; ?></div>
                    <div class="metric-label">Checked-In Today</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">PENDING REVIEWS</span>
                        <div class="metric-icon-badge amber">
                            <?php echo pika_icon('clock', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $pendingApprovals; ?></div>
                    <div class="metric-label">Awaiting Lead Approval</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">ACTIVE TASKS</span>
                        <div class="metric-icon-badge purple">
                            <?php echo pika_icon('tasks', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $activeTasks; ?></div>
                    <div class="metric-label">Squad Assignments</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Pending Approvals -->
            <div class="col-lg-6">
                <div class="saas-card h-100">
                    <div class="saas-card-header">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="saas-card-title">
                                <?php echo pika_icon('clock', 'text-warning', 18); ?>
                                Team Pending Approvals
                            </h5>
                            <?php if ($pendingApprovals > 0): ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 small">
                                <?php echo $pendingApprovals; ?> pending
                            </span>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo url('teamlead/approvals'); ?>" class="btn btn-sm btn-saas-light">
                            <span>Review All</span>
                            <?php echo pika_icon('arrow-right', '', 12); ?>
                        </a>
                    </div>
                    <div class="p-0">
                        <?php if (count($pendingList) > 0): ?>
                        <div class="list-group list-group-flush border-0">
                            <?php foreach ($pendingList as $pending): ?>
                            <div class="list-group-item p-3 px-4 d-flex align-items-center justify-content-between border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?php echo getAvatar($pending['avatar'] ?? ''); ?>" class="saas-user-avatar" alt="">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($pending['name']); ?></div>
                                        <small class="text-muted d-flex align-items-center gap-2">
                                            <span class="saas-id-badge" style="font-size: 0.68rem; padding: 2px 8px;"><?php echo htmlspecialchars($pending['emp_id']); ?></span>
                                            <span><?php echo formatDate($pending['date']); ?> at <?php echo formatTime($pending['check_in_time']); ?></span>
                                        </small>
                                    </div>
                                </div>
                                <a href="<?php echo url('teamlead/approvals'); ?>?action=view&id=<?php echo $pending['id']; ?>" class="btn btn-sm btn-saas-dark">
                                    <?php echo pika_icon('eye', '', 14); ?>
                                    <span>Review</span>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <?php echo pika_icon('check-circle', 'opacity-25 d-block mx-auto mb-2 text-success', 40); ?>
                            <p class="mb-0 fw-semibold text-dark">All Team Caught Up!</p>
                            <small class="text-muted">No pending team check-in requests awaiting review.</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Team Members -->
            <div class="col-lg-6">
                <div class="saas-card h-100">
                    <div class="saas-card-header">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="saas-card-title">
                                <?php echo pika_icon('users', 'text-primary', 18); ?>
                                My Squad Members
                            </h5>
                            <span class="badge bg-light text-dark border px-2 py-1 rounded-pill small">
                                <?php echo count($teamMembers); ?> active
                            </span>
                        </div>
                        <a href="<?php echo url('teamlead/team'); ?>" class="btn btn-sm btn-saas-light">
                            <span>All Members</span>
                            <?php echo pika_icon('arrow-right', '', 12); ?>
                        </a>
                    </div>
                    <div class="p-0">
                        <?php if (count($teamMembers) > 0): ?>
                        <div class="list-group list-group-flush border-0">
                            <?php foreach (array_slice($teamMembers, 0, 5) as $member): ?>
                            <div class="list-group-item p-3 px-4 d-flex align-items-center justify-content-between border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?php echo getAvatar($member['avatar'] ?? ''); ?>" class="saas-user-avatar" alt="">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($member['name']); ?></div>
                                        <small class="text-muted d-flex align-items-center gap-2">
                                            <span class="saas-id-badge" style="font-size: 0.68rem; padding: 2px 8px;"><?php echo htmlspecialchars($member['employee_id']); ?></span>
                                            <span><?php echo htmlspecialchars($member['designation'] ?? 'Employee'); ?></span>
                                        </small>
                                    </div>
                                </div>
                                <span class="saas-tag <?php echo $member['status'] === 'active' ? 'emerald' : 'rose'; ?>">
                                    <?php echo ucfirst($member['status']); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <?php echo pika_icon('users', 'opacity-25 d-block mx-auto mb-2', 40); ?>
                            <p class="mb-0 fw-semibold text-dark">No Teammates Assigned</p>
                            <small class="text-muted">No employees are assigned under your direct leadership.</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
