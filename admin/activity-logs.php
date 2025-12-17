<?php
/**
 * Admin Activity Logs
 * Employee Management System
 */

$pageTitle = 'Activity Logs';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$logType = $_GET['type'] ?? 'activity';
$search = $_GET['search'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
if ($logType === 'login') {
    $baseQuery = "FROM login_logs l LEFT JOIN employees e ON l.user_id = e.id AND l.user_type = 'employee'";
    $where = "WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($search) {
        $where .= " AND (l.ip_address LIKE ? OR e.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "ss";
    }
    
    if ($dateFilter) {
        $where .= " AND DATE(l.login_time) = ?";
        $params[] = $dateFilter;
        $types .= "s";
    }
    
    $countQuery = "SELECT COUNT(*) as total $baseQuery $where";
    $dataQuery = "SELECT l.*, e.name as employee_name $baseQuery $where ORDER BY l.login_time DESC LIMIT $perPage OFFSET $offset";
} else {
    $baseQuery = "FROM activity_logs a LEFT JOIN employees e ON a.user_id = e.id AND a.user_type = 'employee'";
    $where = "WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($search) {
        $where .= " AND (a.action LIKE ? OR a.description LIKE ? OR e.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "sss";
    }
    
    if ($dateFilter) {
        $where .= " AND DATE(a.created_at) = ?";
        $params[] = $dateFilter;
        $types .= "s";
    }
    
    $countQuery = "SELECT COUNT(*) as total $baseQuery $where";
    $dataQuery = "SELECT a.*, e.name as employee_name $baseQuery $where ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset";
}

// Get total count
if (!empty($params)) {
    $totalResult = fetchOne($countQuery, $types, $params);
} else {
    $totalResult = fetchOne($countQuery);
}
$totalRecords = $totalResult['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get data
if (!empty($params)) {
    $logs = fetchAll($dataQuery, $types, $params);
} else {
    $logs = fetchAll($dataQuery);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Activity Logs</h1>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="Avatar" class="user-avatar">
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <div class="content-wrapper">
        <!-- Log Type Tabs -->
        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $logType === 'activity' ? 'active' : ''; ?>" href="?type=activity">
                    <i class="bi bi-activity me-1"></i>Activity Logs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $logType === 'login' ? 'active' : ''; ?>" href="?type=login">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Login Logs
                </a>
            </li>
        </ul>
        
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="type" value="<?php echo $logType; ?>">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="search" placeholder="Search..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <input type="date" class="form-control" name="date" value="<?php echo $dateFilter; ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a href="?type=<?php echo $logType; ?>" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Logs Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-<?php echo $logType === 'login' ? 'box-arrow-in-right' : 'activity'; ?> me-2"></i>
                    <?php echo $logType === 'login' ? 'Login History' : 'Activity History'; ?>
                </h5>
                <span class="badge bg-primary"><?php echo $totalRecords; ?> records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <?php if ($logType === 'login'): ?>
                                <th>User</th>
                                <th>Type</th>
                                <th>IP Address</th>
                                <th>Device/Browser</th>
                                <th>Status</th>
                                <th>Time</th>
                                <?php else: ?>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>Time</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                    No logs found
                                </td>
                            </tr>
                            <?php elseif ($logType === 'login'): ?>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <?php if ($log['user_type'] === 'admin'): ?>
                                            <span class="badge bg-primary">Admin</span>
                                        <?php else: ?>
                                            <?php echo $log['employee_name'] ?? 'Unknown'; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($log['user_type']); ?></span></td>
                                    <td><code><?php echo $log['ip_address']; ?></code></td>
                                    <td>
                                        <small class="text-muted" style="max-width: 200px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($log['user_agent']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($log['status'] === 'success'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check"></i> Success</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="bi bi-x"></i> Failed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span title="<?php echo $log['login_time']; ?>">
                                            <?php echo formatTimestamp($log['login_time'], 'M j, Y g:i A'); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <?php if ($log['user_type'] === 'admin'): ?>
                                            <span class="badge bg-primary">Admin</span>
                                        <?php else: ?>
                                            <?php echo $log['employee_name'] ?? 'Unknown'; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $actionColors = [
                                            'login' => 'success',
                                            'logout' => 'secondary',
                                            'check_in' => 'info',
                                            'check_out' => 'info',
                                            'task_update' => 'warning',
                                            'profile_update' => 'primary',
                                            'password_change' => 'danger',
                                            'settings_update' => 'dark',
                                        ];
                                        $color = $actionColors[$log['action']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo str_replace('_', ' ', ucfirst($log['action'])); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['description']); ?></td>
                                    <td><code><?php echo $log['ip_address']; ?></code></td>
                                    <td>
                                        <span title="<?php echo $log['created_at']; ?>">
                                            <?php echo formatTimestamp($log['created_at'], 'M j, Y g:i A'); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?type=<?php echo $logType; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo $dateFilter; ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?type=<?php echo $logType; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo $dateFilter; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?type=<?php echo $logType; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo $dateFilter; ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
