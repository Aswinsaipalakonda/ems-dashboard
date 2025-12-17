<?php
/**
 * Manager - My Tasks
 * Employee Management System
 */

$pageTitle = 'My Tasks';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (!isManager() && !isHR()) {
    header("Location: " . APP_URL . "/employee/dashboard.php");
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskId = intval($_POST['task_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? '');
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    if ($taskId && $status) {
        $completedAt = $status === 'completed' ? 'NOW()' : 'NULL';
        executeQuery(
            "UPDATE tasks SET status = ?, remarks = ?, completed_at = " . ($status === 'completed' ? 'NOW()' : 'NULL') . " WHERE id = ? AND assigned_to = ?",
            "ssii",
            [$status, $remarks, $taskId, $userId]
        );
        $message = 'Task updated successfully!';
        $messageType = 'success';
    }
}

// Get my tasks
$statusFilter = $_GET['status'] ?? '';
$whereClause = "WHERE t.assigned_to = ?";
$params = [$userId];
$types = "i";

if ($statusFilter) {
    $whereClause .= " AND t.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$tasks = fetchAll(
    "SELECT t.*, a.name as assigned_by_name 
     FROM tasks t 
     LEFT JOIN employees a ON t.assigned_by = a.id
     $whereClause 
     ORDER BY t.deadline ASC, t.priority DESC",
    $types,
    $params
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/manager-sidebar.php';
?>

<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">My Tasks</h1>
        </div>
    </header>

    <div class="content-wrapper">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="not_started" <?php echo $statusFilter === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                            <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tasks -->
        <div class="row">
            <?php if (count($tasks) > 0): ?>
            <?php foreach ($tasks as $task): ?>
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?php echo $task['title']; ?></h6>
                        <span class="badge bg-<?php 
                            echo $task['priority'] === 'urgent' ? 'danger' : 
                                ($task['priority'] === 'high' ? 'warning' : 
                                ($task['priority'] === 'medium' ? 'info' : 'secondary')); 
                        ?>">
                            <?php echo ucfirst($task['priority']); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted"><?php echo $task['description'] ?: 'No description'; ?></p>
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="bi bi-person me-1"></i>Assigned by: <?php echo $task['assigned_by_name'] ?? 'Admin'; ?>
                            </small>
                        </div>
                        <?php if ($task['deadline']): ?>
                        <div class="mb-2">
                            <small class="text-<?php echo strtotime($task['deadline']) < time() && $task['status'] !== 'completed' ? 'danger' : 'muted'; ?>">
                                <i class="bi bi-calendar me-1"></i>Deadline: <?php echo formatDate($task['deadline']); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <span class="badge bg-<?php 
                                echo $task['status'] === 'completed' ? 'success' : 
                                    ($task['status'] === 'in_progress' ? 'primary' : 'secondary'); 
                            ?>">
                                <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?>
                            </span>
                        </div>
                        
                        <?php if ($task['status'] !== 'completed'): ?>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $task['id']; ?>">
                            <i class="bi bi-pencil me-1"></i>Update Status
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Update Modal -->
            <div class="modal fade" id="updateModal<?php echo $task['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                            <div class="modal-header">
                                <h5 class="modal-title">Update Task Status</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="not_started" <?php echo $task['status'] === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                                        <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Remarks</label>
                                    <textarea name="remarks" class="form-control" rows="3"><?php echo $task['remarks']; ?></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5 text-muted">
                        <i class="bi bi-check-circle display-4"></i>
                        <p class="mt-2">No tasks assigned to you</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
