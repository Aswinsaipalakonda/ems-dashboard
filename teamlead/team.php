<?php
/**
 * Team Lead - Team Members View
 * Employee Management System
 */

$pageTitle = 'Team Members';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (!isTeamLead()) {
    header("Location: " . url("employee/dashboard"));
    exit;
}

$userId = $_SESSION['user_id'];
$teamMembers = getTeamMembers($userId);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/teamlead-sidebar.php';
?>

<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">My Team Members</h1>
        </div>
    </header>

    <div class="content-wrapper">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Team Members (<?php echo count($teamMembers); ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Employee ID</th>
                                <th>Domain</th>
                                <th>Designation</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($teamMembers) > 0): ?>
                            <?php foreach ($teamMembers as $member): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo getAvatar($member['avatar']); ?>" class="avatar avatar-sm me-2" alt="">
                                        <div>
                                            <div class="fw-semibold"><?php echo $member['name']; ?></div>
                                            <small class="text-muted"><?php echo $member['email']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark"><?php echo $member['employee_id']; ?></span></td>
                                <td><?php echo $member['domain_name'] ?? 'N/A'; ?></td>
                                <td><?php echo $member['designation'] ?? 'Employee'; ?></td>
                                <td><span class="badge badge-<?php echo $member['status']; ?>"><?php echo ucfirst($member['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-people display-4"></i>
                                    <p class="mt-2">No team members assigned to you yet</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
