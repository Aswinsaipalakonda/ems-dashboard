<?php
/**
 * Employee Notifications
 * Employee Management System
 */

$pageTitle = 'Notifications';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (isAdmin()) {
    header("Location: " . url("admin/dashboard"));
    exit;
}

$userId = $_SESSION['user_id'];

// Mark as read
if (isset($_GET['mark_read'])) {
    $notifId = intval($_GET['mark_read']);
    executeQuery("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?", "ii", [$notifId, $userId]);
    header("Location: " . url("employee/notifications"));
    exit;
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    executeQuery("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_type = 'employee'", "i", [$userId]);
    header("Location: " . url("employee/notifications"));
    exit;
}

// Get notifications
$notifications = fetchAll(
    "SELECT * FROM notifications WHERE user_id = ? AND user_type = 'employee' ORDER BY created_at DESC",
    "i",
    [$userId]
);

$unreadCount = fetchOne(
    "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND user_type = 'employee' AND is_read = 0",
    "i",
    [$userId]
)['count'] ?? 0;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/employee-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Notifications</h1>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="Avatar" class="user-avatar">
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo url('employee/profile'); ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo url('logout'); ?>"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <div class="content-wrapper">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-bell me-2"></i>All Notifications
                    <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-danger ms-2"><?php echo $unreadCount; ?> unread</span>
                    <?php endif; ?>
                </h5>
                <?php if ($unreadCount > 0): ?>
                <a href="?mark_all_read=1" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-check-all me-1"></i>Mark All Read
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (count($notifications) > 0): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notif): ?>
                    <div class="list-group-item p-3 <?php echo !$notif['is_read'] ? 'bg-light' : ''; ?>">
                        <div class="d-flex">
                            <div class="notification-icon bg-<?php echo $notif['type']; ?> text-white me-3">
                                <i class="bi bi-<?php echo $notif['type'] === 'success' ? 'check-circle' : ($notif['type'] === 'danger' ? 'x-circle' : ($notif['type'] === 'warning' ? 'exclamation-circle' : 'info-circle')); ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="mb-1 <?php echo !$notif['is_read'] ? 'fw-bold' : ''; ?>">
                                        <?php echo $notif['title']; ?>
                                    </h6>
                                    <small class="text-muted"><?php echo formatDateTime($notif['created_at']); ?></small>
                                </div>
                                <p class="mb-1 text-muted"><?php echo $notif['message']; ?></p>
                                <?php if (!$notif['is_read']): ?>
                                <a href="?mark_read=<?php echo $notif['id']; ?>" class="btn btn-sm btn-link p-0">Mark as read</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state py-5">
                    <i class="bi bi-bell-slash"></i>
                    <h4>No Notifications</h4>
                    <p>You don't have any notifications yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
