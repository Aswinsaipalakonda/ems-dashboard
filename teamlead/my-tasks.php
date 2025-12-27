<?php
/**
 * Team Lead - My Tasks
 * Employee Management System
 */

$pageTitle = 'My Tasks';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (!isTeamLead()) {
    header("Location: " . url("employee/dashboard"));
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $taskId = (int)$_POST['task_id'];
    $newStatus = sanitize($_POST['new_status']);
    
    // Verify task belongs to user
    $task = fetchOne("SELECT id FROM tasks WHERE id = ? AND assigned_to = ?", "ii", [$taskId, $userId]);
    
    if ($task) {
        $result = update("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?", "si", [$newStatus, $taskId]);
        if ($result) {
            $message = "Task status updated successfully!";
            $messageType = 'success';
        } else {
            $message = "Failed to update task status.";
            $messageType = 'danger';
        }
    }
}

// Filter
$statusFilter = $_GET['status'] ?? '';

// Get tasks
$sql = "SELECT t.*, e.name as assigned_by_name 
        FROM tasks t 
        LEFT JOIN employees e ON t.assigned_by = e.id 
        WHERE t.assigned_to = ?";
$params = [$userId];
$types = "i";

if ($statusFilter) {
    $sql .= " AND t.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql .= " ORDER BY 
    CASE t.priority 
        WHEN 'urgent' THEN 1 
        WHEN 'high' THEN 2 
        WHEN 'medium' THEN 3 
        WHEN 'low' THEN 4 
    END,
    t.deadline ASC";

$tasks = fetchAll($sql, $types, $params);

// Stats
$taskStats = [
    'pending' => fetchOne("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'pending'", "i", [$userId])['count'],
    'in_progress' => fetchOne("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'in-progress'", "i", [$userId])['count'],
    'completed' => fetchOne("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'completed'", "i", [$userId])['count'],
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/teamlead-sidebar.php';
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

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-warning bg-opacity-10 border-warning">
                    <div class="card-body text-center">
                        <h3 class="text-warning"><?php echo $taskStats['pending']; ?></h3>
                        <small>Pending</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-primary bg-opacity-10 border-primary">
                    <div class="card-body text-center">
                        <h3 class="text-primary"><?php echo $taskStats['in_progress']; ?></h3>
                        <small>In Progress</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success bg-opacity-10 border-success">
                    <div class="card-body text-center">
                        <h3 class="text-success"><?php echo $taskStats['completed']; ?></h3>
                        <small>Completed</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in-progress" <?php echo $statusFilter === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary me-2">Filter</button>
                        <a href="my-tasks" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tasks List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Tasks Assigned to Me</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Priority</th>
                                <th>Due Date</th>
                                <th>Assigned By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($tasks) > 0): ?>
                            <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                    <?php if ($task['description']): ?>
                                    <p class="text-muted small mb-0"><?php echo htmlspecialchars(substr($task['description'], 0, 50)); ?>...</p>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-<?php echo $task['priority']; ?>"><?php echo ucfirst($task['priority']); ?></span></td>
                                <td>
                                    <?php 
                                    $dueDate = $task['deadline'];
                                    $isOverdue = $dueDate && strtotime($dueDate) < strtotime('today') && $task['status'] !== 'completed';
                                    ?>
                                    <span class="<?php echo $isOverdue ? 'text-danger fw-bold' : ''; ?>">
                                        <?php echo $dueDate ? formatDate($dueDate) : '-'; ?>
                                        <?php if ($isOverdue): ?><i class="bi bi-exclamation-triangle-fill"></i><?php endif; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($task['assigned_by_name'] ?? 'System'); ?></td>
                                <td><span class="badge badge-<?php echo $task['status']; ?>"><?php echo ucfirst($task['status']); ?></span></td>
                                <td>
                                    <?php if ($task['status'] !== 'completed'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <?php if ($task['status'] === 'pending'): ?>
                                        <input type="hidden" name="new_status" value="in-progress">
                                        <button type="submit" class="btn btn-sm btn-primary" title="Start Task">
                                            <i class="bi bi-play-fill"></i>
                                        </button>
                                        <?php elseif ($task['status'] === 'in-progress'): ?>
                                        <input type="hidden" name="new_status" value="completed">
                                        <button type="submit" class="btn btn-sm btn-success" title="Complete Task">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <?php endif; ?>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-success"><i class="bi bi-check-circle-fill"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No tasks found</td>
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
