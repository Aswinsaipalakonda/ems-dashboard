<?php
/**
 * Employee Tasks
 * Employee Management System
 */

$pageTitle = 'My Tasks';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (isAdmin()) {
    header("Location: " . url("admin/tasks"));
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskId = intval($_POST['task_id'] ?? 0);
    $newStatus = sanitize($_POST['status'] ?? '');
    $remarks = sanitize($_POST['remarks'] ?? '');
    $workLink = sanitize($_POST['work_link'] ?? '');
    $workLinkType = sanitize($_POST['work_link_type'] ?? '');
    
    // Verify task belongs to employee
    $task = fetchOne("SELECT * FROM tasks WHERE id = ? AND assigned_to = ?", "ii", [$taskId, $userId]);
    
    // Employees can only set: not_started, in_progress, submitted (for review)
    if ($task && in_array($newStatus, ['not_started', 'in_progress', 'submitted'])) {
        
        // Validate work link is required for submission
        if ($newStatus === 'submitted' && empty($workLink)) {
            $message = 'Please provide a work link (GitHub, Drive, etc.) to submit the task for review.';
            $messageType = 'warning';
        } else {
            $sql = "UPDATE tasks SET status = ?, remarks = ?, work_link = ?, work_link_type = ? WHERE id = ?";
            executeQuery($sql, "ssssi", [$newStatus, $remarks, $workLink ?: null, $workLinkType ?: null, $taskId]);
            
            // Notify the assigner when task is submitted for review
            if ($newStatus === 'submitted') {
                // Get assigner info
                $assigner = fetchOne("SELECT e.id, e.name FROM employees e WHERE e.id = ?", "i", [$task['assigned_by']]);
                
                // Notify the assigner (team lead/manager)
                if ($assigner && $task['assigned_by'] != $userId) {
                    executeQuery(
                        "INSERT INTO notifications (user_id, user_type, title, message, type, link) VALUES (?, 'employee', ?, ?, 'info', ?)",
                        "isss",
                        [$task['assigned_by'], 'Task Submitted for Review', $_SESSION['user_name'] . ' has submitted task for review: ' . $task['title'], '/teamlead/team-tasks.php']
                    );
                }
                
                // Also notify admin
                executeQuery(
                    "INSERT INTO notifications (user_id, user_type, title, message, type, link) VALUES (?, 'admin', ?, ?, 'info', ?)",
                    "isss",
                    [1, 'Task Submitted for Review', $_SESSION['user_name'] . ' has submitted: ' . $task['title'] . ($workLink ? ' - Work: ' . $workLink : ''), '/admin/tasks.php']
                );
            }
            
            $_SESSION['flash_message'] = $newStatus === 'submitted' ? 'Task submitted for review!' : 'Task updated successfully!';
            $_SESSION['flash_type'] = 'success';
            header("Location: tasks");
            exit;
        }
    }
}

// Get tasks with filters
$statusFilter = $_GET['status'] ?? '';

$whereClause = "WHERE assigned_to = ?";
$params = [$userId];
$types = "i";

if ($statusFilter) {
    $whereClause .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$tasks = fetchAll(
    "SELECT * FROM tasks $whereClause ORDER BY 
     CASE priority 
         WHEN 'urgent' THEN 1 
         WHEN 'high' THEN 2 
         WHEN 'medium' THEN 3 
         WHEN 'low' THEN 4 
     END,
     deadline ASC",
    $types,
    $params
);

// Get task counts
$taskCounts = fetchOne(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'not_started' THEN 1 ELSE 0 END) as not_started,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue
     FROM tasks WHERE assigned_to = ?",
    "i",
    [$userId]
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/employee-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="page-title">My Tasks</h1>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="Avatar" class="user-avatar">
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['user_name']; ?></div>
                        <div class="user-role">Employee</div>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo url('employee/profile'); ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo url('logout'); ?>"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <!-- Content -->
    <div class="content-wrapper">
        <?php 
        // Handle flash messages (PRG pattern)
        if (isset($_SESSION['flash_message'])): 
            $message = $_SESSION['flash_message'];
            $messageType = $_SESSION['flash_type'] ?? 'info';
            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        endif;
        ?>
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Task Stats -->
        <div class="row mb-4">
            <div class="col-6 col-md mb-3">
                <div class="stats-card">
                    <div class="stats-icon primary">
                        <i class="bi bi-list-task"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?php echo $taskCounts['total'] ?? 0; ?></h3>
                        <p>Total</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md mb-3">
                <div class="stats-card">
                    <div class="stats-icon warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?php echo $taskCounts['in_progress'] ?? 0; ?></h3>
                        <p>In Progress</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md mb-3">
                <div class="stats-card">
                    <div class="stats-icon info">
                        <i class="bi bi-send-check"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?php echo $taskCounts['submitted'] ?? 0; ?></h3>
                        <p>Submitted</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md mb-3">
                <div class="stats-card">
                    <div class="stats-icon success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?php echo $taskCounts['completed'] ?? 0; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tasks List -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-6 mb-2 mb-md-0">
                        <h5 class="mb-0"><i class="bi bi-list-task me-2"></i>My Tasks</h5>
                    </div>
                    <div class="col-md-6">
                        <form class="d-flex gap-2 justify-content-md-end" method="GET">
                            <select class="form-select form-select-sm" name="status" style="width: auto;" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="not_started" <?php echo $statusFilter === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                                <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="submitted" <?php echo $statusFilter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (count($tasks) > 0): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($tasks as $task): ?>
                    <div class="list-group-item p-3">
                        <div class="d-flex flex-column flex-md-row gap-3">
                            <!-- Task Info -->
                            <div class="flex-grow-1">
                                <h6 class="mb-1 <?php echo $task['status'] === 'completed' ? 'text-decoration-line-through text-muted' : ''; ?>">
                                    <?php echo $task['title']; ?>
                                </h6>
                                <?php if ($task['description']): ?>
                                <p class="mb-2 text-muted small"><?php echo $task['description']; ?></p>
                                <?php endif; ?>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <span class="badge priority-<?php echo $task['priority']; ?>">
                                        <i class="bi bi-flag me-1"></i><?php echo ucfirst($task['priority']); ?>
                                    </span>
                                    <?php if ($task['deadline']): ?>
                                    <span class="badge <?php echo strtotime($task['deadline']) < time() && !in_array($task['status'], ['completed', 'submitted']) ? 'bg-danger' : 'bg-light text-dark'; ?>">
                                        <i class="bi bi-calendar me-1"></i><?php echo formatDate($task['deadline']); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($task['attachment'])): ?>
                                    <a href="<?php echo APP_URL . '/uploads/tasks/' . htmlspecialchars($task['attachment'], ENT_QUOTES); ?>" 
                                       target="_blank" class="badge bg-primary text-decoration-none" title="Download Attachment">
                                        <i class="bi bi-file-earmark-arrow-down me-1"></i><?php 
                                            $ext = strtolower(pathinfo($task['attachment'], PATHINFO_EXTENSION));
                                            echo strtoupper($ext) . ' File';
                                        ?>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (!empty($task['work_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($task['work_link'], ENT_QUOTES); ?>" target="_blank" class="badge bg-success text-decoration-none">
                                        <i class="bi bi-<?php 
                                            echo match($task['work_link_type'] ?? 'link') {
                                                'github' => 'github',
                                                'drive' => 'google',
                                                'canva' => 'palette',
                                                'figma' => 'vector-pen',
                                                'notion' => 'journal-text',
                                                default => 'link-45deg'
                                            };
                                        ?> me-1"></i><?php echo ucfirst($task['work_link_type'] ?? 'Link'); ?>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (!empty($task['reviewer_feedback'])): ?>
                                    <span class="badge bg-warning text-dark" title="Feedback from reviewer">
                                        <i class="bi bi-chat-dots me-1"></i>Has Feedback
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($task['reviewer_feedback'])): ?>
                                <div class="alert alert-warning mt-2 mb-0 py-2 px-3 small">
                                    <strong><i class="bi bi-chat-left-text me-1"></i>Reviewer Feedback:</strong>
                                    <?php echo $task['reviewer_feedback']; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Status & Actions -->
                            <div class="d-flex flex-row flex-md-column align-items-center gap-2" style="min-width: 140px;">
                                <span class="badge status-<?php echo $task['status']; ?> fs-6 w-100 text-center py-2">
                                    <?php 
                                    $statusLabels = [
                                        'not_started' => 'Not Started',
                                        'in_progress' => 'In Progress',
                                        'submitted' => 'Submitted',
                                        'completed' => 'Completed',
                                        'changes_requested' => 'Changes Needed'
                                    ];
                                    echo $statusLabels[$task['status']] ?? ucfirst($task['status']);
                                    ?>
                                </span>
                                <?php if (!in_array($task['status'], ['completed', 'submitted'])): ?>
                                <button class="btn btn-sm btn-outline-primary w-100" 
                                        onclick="showTaskModal(<?php echo htmlspecialchars(json_encode($task)); ?>)">
                                    <i class="bi bi-pencil me-1"></i>Update
                                </button>
                                <?php elseif ($task['status'] === 'submitted'): ?>
                                <span class="text-muted small text-center"><i class="bi bi-hourglass me-1"></i>Awaiting Review</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state py-5">
                    <i class="bi bi-check-circle"></i>
                    <h4>No Tasks Found</h4>
                    <p>
                        <?php if ($statusFilter): ?>
                            No tasks match the selected filter.
                        <?php else: ?>
                            You don't have any tasks assigned yet.
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Update Task Modal -->
<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Update Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="task_id" id="modalTaskId">
                    
                    <!-- Task Info -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <h6 class="fw-bold mb-1" id="modalTaskTitle"></h6>
                        <p class="text-muted small mb-2" id="modalTaskDescription"></p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-secondary" id="modalTaskPriority"></span>
                            <span class="badge bg-secondary" id="modalTaskDeadline"></span>
                        </div>
                    </div>
                    
                    <!-- Reviewer Feedback Alert -->
                    <div class="alert alert-warning mb-3" id="feedbackAlert" style="display: none;">
                        <strong><i class="bi bi-chat-left-text me-1"></i>Reviewer Feedback:</strong>
                        <p class="mb-0 mt-1" id="modalFeedback"></p>
                    </div>
                    
                    <!-- Status -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-flag me-1"></i>Status</label>
                        <select class="form-select" name="status" id="modalTaskStatus" onchange="toggleWorkLinkRequired(this.value)">
                            <option value="not_started">Not Started</option>
                            <option value="in_progress">In Progress</option>
                            <option value="submitted">📤 Submit for Review</option>
                        </select>
                        <small class="text-muted">Select "Submit for Review" when your work is ready to be reviewed</small>
                    </div>
                    
                    <!-- Work Link Type -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-link-45deg me-1"></i>Work Link Type</label>
                        <select class="form-select" name="work_link_type" id="modalWorkLinkType">
                            <option value="">Select Type</option>
                            <option value="github">🐙 GitHub</option>
                            <option value="drive">📁 Google Drive</option>
                            <option value="canva">🎨 Canva</option>
                            <option value="figma">✏️ Figma</option>
                            <option value="notion">📝 Notion</option>
                            <option value="other">🔗 Other</option>
                        </select>
                    </div>
                    
                    <!-- Work Link URL -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-link me-1"></i>Work Link / Submission URL
                            <span class="text-danger" id="workLinkRequired" style="display: none;">*</span>
                        </label>
                        <input type="url" class="form-control" name="work_link" id="modalWorkLink" 
                               placeholder="https://github.com/... or https://drive.google.com/...">
                        <small class="text-muted">Required when submitting for review</small>
                    </div>
                    
                    <!-- Remarks -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-chat-text me-1"></i>Remarks / Notes</label>
                        <textarea class="form-control" name="remarks" id="modalTaskRemarks" rows="3" 
                                  placeholder="Add any notes or comments about your work..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Update Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showTaskModal(task) {
    document.getElementById('modalTaskId').value = task.id;
    document.getElementById('modalTaskTitle').textContent = task.title;
    document.getElementById('modalTaskDescription').textContent = task.description || 'No description provided';
    document.getElementById('modalTaskStatus').value = task.status === 'changes_requested' ? 'in_progress' : task.status;
    document.getElementById('modalTaskRemarks').value = task.remarks || '';
    document.getElementById('modalWorkLink').value = task.work_link || '';
    document.getElementById('modalWorkLinkType').value = task.work_link_type || '';
    
    // Set priority and deadline badges
    document.getElementById('modalTaskPriority').textContent = task.priority ? task.priority.charAt(0).toUpperCase() + task.priority.slice(1) + ' Priority' : '';
    document.getElementById('modalTaskDeadline').textContent = task.deadline ? 'Due: ' + task.deadline : 'No deadline';
    
    // Show reviewer feedback if exists
    const feedbackAlert = document.getElementById('feedbackAlert');
    const modalFeedback = document.getElementById('modalFeedback');
    if (task.reviewer_feedback) {
        feedbackAlert.style.display = 'block';
        modalFeedback.textContent = task.reviewer_feedback;
    } else {
        feedbackAlert.style.display = 'none';
    }
    
    // Toggle work link required indicator
    toggleWorkLinkRequired(task.status);
    
    new bootstrap.Modal(document.getElementById('taskModal')).show();
}

function toggleWorkLinkRequired(status) {
    const requiredIndicator = document.getElementById('workLinkRequired');
    const workLinkInput = document.getElementById('modalWorkLink');
    
    if (status === 'submitted') {
        requiredIndicator.style.display = 'inline';
        workLinkInput.required = true;
    } else {
        requiredIndicator.style.display = 'none';
        workLinkInput.required = false;
    }
}
</script>

<style>
.status-submitted { background: #0dcaf0; color: #000; }
.status-changes_requested { background: #fd7e14; color: #fff; }
.stats-icon.info { background: rgba(13, 202, 240, 0.1); color: #0dcaf0; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
