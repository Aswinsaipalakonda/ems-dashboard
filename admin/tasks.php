<?php
/**
 * Admin Task Management - SaaS Project Operations
 * Employee Management System
 */

$pageTitle = 'Task Management';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$message = '';
$messageType = '';

// Handle flash messages (PRG pattern)
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    // Check for duplicate submission using form token
    $formToken = $_POST['form_token'] ?? '';
    $isValidSubmission = true;
    
    if ($postAction === 'add') {
        if (empty($formToken) || (isset($_SESSION['last_form_token']) && $_SESSION['last_form_token'] === $formToken)) {
            $isValidSubmission = false;
            header("Location: tasks");
            exit;
        }
        $_SESSION['last_form_token'] = $formToken;
    }
    
    if ($isValidSubmission && ($postAction === 'add' || $postAction === 'edit')) {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $assignedToArray = $_POST['assigned_to'] ?? [];
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $deadline = $_POST['deadline'] ?? null;
        $status = sanitize($_POST['status'] ?? 'not_started');
        
        // Handle file attachment
        $attachmentFile = null;
        if (!empty($_FILES['task_attachment']['name'])) {
            $allowedExtensions = ['pdf', 'csv', 'xls', 'xlsx', 'doc', 'docx'];
            $fileExt = strtolower(pathinfo($_FILES['task_attachment']['name'], PATHINFO_EXTENSION));
            $fileSize = $_FILES['task_attachment']['size'];
            $maxSize = 10 * 1024 * 1024; // 10MB
            
            if (!in_array($fileExt, $allowedExtensions)) {
                $message = 'Invalid file type. Allowed: PDF, CSV, XLS, XLSX, DOC, DOCX';
                $messageType = 'danger';
            } elseif ($fileSize > $maxSize) {
                $message = 'File size too large. Maximum 10MB allowed.';
                $messageType = 'danger';
            } else {
                $attachmentFile = 'task_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
                $uploadPath = UPLOAD_PATH . 'tasks/' . $attachmentFile;
                
                if (!move_uploaded_file($_FILES['task_attachment']['tmp_name'], $uploadPath)) {
                    $message = 'Failed to upload attachment.';
                    $messageType = 'danger';
                    $attachmentFile = null;
                }
            }
        }
        
        if (!is_array($assignedToArray)) {
            $assignedToArray = [$assignedToArray];
        }
        $assignedToArray = array_filter(array_map('intval', $assignedToArray));
        
        if (empty($title) || empty($assignedToArray)) {
            $message = 'Task Title and at least one Assigned Employee are required!';
            $messageType = 'danger';
        } elseif (empty($message)) {
            if ($postAction === 'add') {
                $createdCount = 0;
                foreach ($assignedToArray as $assignedTo) {
                    $sql = "INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, deadline, status, attachment) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    executeQuery($sql, "ssiissss", [$title, $description, $assignedTo, $_SESSION['user_id'], $priority, $deadline, $status, $attachmentFile]);
                    
                    $employee = fetchOne("SELECT name, email FROM employees WHERE id = ?", "i", [$assignedTo]);
                    executeQuery(
                        "INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'employee', 'New Task Assigned 📋', ?, 'info')",
                        "is",
                        [$assignedTo, "You have been assigned to task: '$title'"]
                    );
                    $createdCount++;
                }
                
                $_SESSION['flash_message'] = "Task deployed to $createdCount team member(s) successfully!";
                $_SESSION['flash_type'] = 'success';
                header("Location: tasks");
                exit;
            } else {
                $editId = intval($_POST['id']);
                $assignedTo = $assignedToArray[0];
                
                if ($attachmentFile) {
                    $sql = "UPDATE tasks SET title = ?, description = ?, assigned_to = ?, priority = ?, deadline = ?, status = ?, attachment = ? WHERE id = ?";
                    executeQuery($sql, "ssissssi", [$title, $description, $assignedTo, $priority, $deadline, $status, $attachmentFile, $editId]);
                } else {
                    $sql = "UPDATE tasks SET title = ?, description = ?, assigned_to = ?, priority = ?, deadline = ?, status = ? WHERE id = ?";
                    executeQuery($sql, "ssisssi", [$title, $description, $assignedTo, $priority, $deadline, $status, $editId]);
                }
                
                $_SESSION['flash_message'] = 'Task updated successfully!';
                $_SESSION['flash_type'] = 'success';
                header("Location: tasks");
                exit;
            }
        }
    } elseif ($postAction === 'delete') {
        $taskId = intval($_POST['id']);
        $singleTask = fetchOne("SELECT * FROM tasks WHERE id = ?", "i", [$taskId]);
        if ($singleTask) {
            executeQuery("DELETE FROM tasks WHERE id = ?", "i", [$taskId]);
            $_SESSION['flash_message'] = 'Task removed successfully!';
            $_SESSION['flash_type'] = 'success';
        }
        header("Location: tasks");
        exit;
    } elseif ($postAction === 'review') {
        $reviewId = intval($_POST['id']);
        $reviewAction = sanitize($_POST['review_action'] ?? '');
        $reviewerFeedback = sanitize($_POST['reviewer_feedback'] ?? '');
        
        $existingTask = fetchOne("SELECT * FROM tasks WHERE id = ?", "i", [$reviewId]);
        if ($existingTask) {
            if ($reviewAction === 'approve') {
                executeQuery("UPDATE tasks SET status = 'completed', completed_at = NOW(), reviewer_feedback = NULL WHERE id = ?", "i", [$reviewId]);
                executeQuery(
                    "INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'employee', 'Task Approved! ✅', ?, 'success')",
                    "is",
                    [$existingTask['assigned_to'], "Your submission for task '{$existingTask['title']}' has been officially approved!"]
                );
                $_SESSION['flash_message'] = 'Task approved and marked as completed!';
                $_SESSION['flash_type'] = 'success';
            } elseif ($reviewAction === 'request_changes') {
                executeQuery("UPDATE tasks SET status = 'changes_requested', reviewer_feedback = ? WHERE id = ?", "si", [$reviewerFeedback, $reviewId]);
                executeQuery(
                    "INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'employee', 'Changes Requested ⚠️', ?, 'warning')",
                    "is",
                    [$existingTask['assigned_to'], "Changes requested on task '{$existingTask['title']}': $reviewerFeedback"]
                );
                $_SESSION['flash_message'] = 'Feedback sent to employee. Status marked as Changes Requested.';
                $_SESSION['flash_type'] = 'warning';
            }
            header("Location: tasks");
            exit;
        }
    }
}

// Get task for editing
$task = null;
if ($action === 'edit' && $id) {
    $task = fetchOne("SELECT * FROM tasks WHERE id = ?", "i", [$id]);
}

// Active Employees
$employees = fetchAll(
    "SELECT e.id, e.name, e.employee_id, d.name as domain_name 
     FROM employees e 
     LEFT JOIN domains d ON e.domain_id = d.id 
     WHERE e.status = 'active' 
     ORDER BY d.name, e.name"
);

// Filters
$statusFilter = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';
$employeeFilter = $_GET['employee'] ?? '';

$whereClause = "WHERE 1=1";
$params = [];
$types = "";

if ($statusFilter) {
    $whereClause .= " AND t.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if ($priorityFilter) {
    $whereClause .= " AND t.priority = ?";
    $params[] = $priorityFilter;
    $types .= "s";
}

if ($employeeFilter) {
    $whereClause .= " AND t.assigned_to = ?";
    $params[] = $employeeFilter;
    $types .= "i";
}

$tasks = fetchAll(
    "SELECT t.*, e.name as employee_name, e.employee_id as emp_id, e.avatar 
     FROM tasks t 
     JOIN employees e ON t.assigned_to = e.id 
     $whereClause 
     ORDER BY t.created_at DESC",
    $types,
    $params
);

// Auto-flag overdue tasks
foreach ($tasks as $t) {
    if ($t['deadline'] && $t['status'] !== 'completed' && strtotime($t['deadline']) < time()) {
        executeQuery("UPDATE tasks SET status = 'overdue' WHERE id = ? AND status NOT IN ('completed', 'overdue')", "i", [$t['id']]);
    }
}

// Group tasks by title + description + deadline + priority + assigned_by
$groupedTasks = [];
foreach ($tasks as $t) {
    $taskKey = md5($t['title'] . '|' . $t['description'] . '|' . $t['deadline'] . '|' . $t['priority'] . '|' . $t['assigned_by']);
    if (!isset($groupedTasks[$taskKey])) {
        $groupedTasks[$taskKey] = [
            'title' => $t['title'],
            'description' => $t['description'],
            'priority' => $t['priority'],
            'deadline' => $t['deadline'],
            'assigned_by' => $t['assigned_by'],
            'created_at' => $t['created_at'],
            'attachment' => $t['attachment'] ?? null,
            'assignees' => []
        ];
    }
    $groupedTasks[$taskKey]['assignees'][] = [
        'id' => $t['id'],
        'employee_id' => $t['assigned_to'],
        'employee_name' => $t['employee_name'],
        'emp_id' => $t['emp_id'],
        'avatar' => $t['avatar'],
        'status' => $t['status'],
        'work_link' => $t['work_link'] ?? '',
        'work_link_type' => $t['work_link_type'] ?? '',
        'updated_at' => $t['updated_at'] ?? $t['created_at']
    ];
}

// Global Task Statistics
$activeTasksCount = fetchOne("SELECT COUNT(*) as count FROM tasks WHERE status IN ('not_started', 'in_progress')")['count'] ?? 0;
$submittedCountGlobal = fetchOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'submitted'")['count'] ?? 0;
$completedCountGlobal = fetchOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'completed'")['count'] ?? 0;
$overdueCountGlobal = fetchOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'overdue'")['count'] ?? 0;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Task Operations</h1>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="Avatar" class="user-avatar">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo url('admin/profile'); ?>"><?php echo pika_icon('profile', 'me-2', 16); ?>Profile</a></li>
                    <li><a class="dropdown-item" href="<?php echo url('admin/settings'); ?>"><?php echo pika_icon('settings', 'me-2', 16); ?>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo url('logout'); ?>"><?php echo pika_icon('logout', 'me-2 text-danger', 16); ?>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <div class="content-wrapper">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <?php echo pika_icon($messageType === 'success' ? 'check-circle' : 'x', 'flex-shrink-0', 20); ?>
                <div><?php echo $message; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($action !== 'add' && $action !== 'edit'): ?>
        <!-- Hero Hub -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="saas-hero-content">
                        <h2 class="saas-hero-title">Task & Project Operations Hub</h2>
                        <p class="saas-hero-subtitle">
                            Delegate assignments, review employee deliverables, inspect GitHub repositories or live URLs, and track milestone velocity.
                        </p>
                        <div class="saas-hero-pills">
                            <span class="saas-pill live-pulse">
                                <span class="pulse-dot"></span>
                                <?php echo $activeTasksCount; ?> Active Assignments
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('eye', '', 14); ?>
                                <?php echo $submittedCountGlobal; ?> Deliverables To Review
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('check-circle', '', 14); ?>
                                <?php echo $completedCountGlobal; ?> Completed
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mt-3 mt-lg-0 text-lg-end">
                    <div class="saas-hero-actions">
                        <a href="?action=add" class="btn btn-primary btn-glow">
                            <?php echo pika_icon('plus', 'me-1', 16); ?> Create New Task
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Dynamic Metric Cards -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge navy">
                            <?php echo pika_icon('tasks', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active">In Flight</span>
                    </div>
                    <div class="metric-value"><?php echo $activeTasksCount; ?></div>
                    <div class="metric-label">Active / In Progress</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge amber">
                            <?php echo pika_icon('eye', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill warning">Needs Audit</span>
                    </div>
                    <div class="metric-value"><?php echo $submittedCountGlobal; ?></div>
                    <div class="metric-label">Submitted Deliverables</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge emerald">
                            <?php echo pika_icon('check-circle', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active">Finished</span>
                    </div>
                    <div class="metric-value"><?php echo $completedCountGlobal; ?></div>
                    <div class="metric-label">Completed Deliverables</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge rose">
                            <?php echo pika_icon('clock', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill danger">Overdue</span>
                    </div>
                    <div class="metric-value"><?php echo $overdueCountGlobal; ?></div>
                    <div class="metric-label">Missed Deadlines</div>
                </div>
            </div>
        </div>

        <!-- Filter SaaS Toolbar -->
        <div class="saas-toolbar">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-lg-3 col-md-4">
                    <select class="form-select saas-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="not_started" <?php echo $statusFilter === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                        <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="submitted" <?php echo $statusFilter === 'submitted' ? 'selected' : ''; ?>>Submitted (Needs Review)</option>
                        <option value="changes_requested" <?php echo $statusFilter === 'changes_requested' ? 'selected' : ''; ?>>Changes Requested</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="overdue" <?php echo $statusFilter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-3">
                    <select class="form-select saas-select" name="priority">
                        <option value="">All Priorities</option>
                        <option value="low" <?php echo $priorityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $priorityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $priorityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="urgent" <?php echo $priorityFilter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-3">
                    <select class="form-select saas-select" name="employee">
                        <option value="">All Assignees</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php echo $employeeFilter == $emp['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($emp['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <?php echo pika_icon('filter', '', 15); ?> Apply
                    </button>
                    <?php if ($statusFilter || $priorityFilter || $employeeFilter): ?>
                    <a href="tasks" class="btn btn-outline-secondary" title="Reset Filters">
                        <?php echo pika_icon('refresh', '', 15); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Grouped Tasks Accordion -->
        <div class="saas-card">
            <div class="saas-card-header">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="saas-card-title">
                        <?php echo pika_icon('tasks', 'text-primary', 20); ?>
                        Project Task Workstreams
                    </h5>
                    <span class="badge bg-light text-dark border px-2 py-1 rounded-pill small">
                        <?php echo count($groupedTasks); ?> Workstreams (<?php echo count($tasks); ?> Assignees)
                    </span>
                </div>
                <a href="?action=add" class="btn btn-sm btn-primary">
                    <?php echo pika_icon('plus', 'me-1', 14); ?> New Task
                </a>
            </div>

            <div class="p-3">
                <?php if (count($groupedTasks) > 0): ?>
                <div class="accordion accordion-flush" id="tasksAccordion">
                    <?php $taskIndex = 0; foreach ($groupedTasks as $taskKey => $taskGroup): $taskIndex++; 
                        $totalAssignees = count($taskGroup['assignees']);
                        $completedCount = count(array_filter($taskGroup['assignees'], fn($a) => $a['status'] === 'completed'));
                        $submittedCount = count(array_filter($taskGroup['assignees'], fn($a) => $a['status'] === 'submitted'));
                        $changesCount = count(array_filter($taskGroup['assignees'], fn($a) => $a['status'] === 'changes_requested'));
                        $overdueCount = count(array_filter($taskGroup['assignees'], fn($a) => $a['status'] === 'overdue'));
                        $overallProgress = round(($completedCount / $totalAssignees) * 100);
                    ?>
                    <div class="accordion-item mb-3 rounded-4 border overflow-hidden shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed p-3 bg-white" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#task<?php echo $taskIndex; ?>">
                                <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3 w-100 me-3">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                            <span class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($taskGroup['title']); ?></span>
                                            <span class="saas-tag <?php 
                                                echo match($taskGroup['priority']) {
                                                    'urgent' => 'rose',
                                                    'high' => 'amber',
                                                    'medium' => 'sky',
                                                    default => 'primary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($taskGroup['priority']); ?> Priority
                                            </span>
                                            <?php if ($submittedCount > 0): ?>
                                            <span class="saas-tag amber">
                                                <?php echo pika_icon('eye', '', 12); ?> <?php echo $submittedCount; ?> Ready For Review
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($overdueCount > 0): ?>
                                            <span class="saas-tag rose"><?php echo $overdueCount; ?> Overdue</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-3 text-muted small flex-wrap">
                                            <span><?php echo pika_icon('users', 'me-1', 14); ?><?php echo $totalAssignees; ?> Assigned</span>
                                            <?php if ($taskGroup['deadline']): ?>
                                            <span><?php echo pika_icon('clock', 'me-1', 14); ?>Due: <?php echo formatDate($taskGroup['deadline']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($taskGroup['attachment'])): ?>
                                            <span class="text-primary"><?php echo pika_icon('file-text', 'me-1', 14); ?>Attachment Included</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress rounded-pill" style="width: 110px; height: 8px;">
                                            <div class="progress-bar <?php echo $overallProgress === 100 ? 'bg-success' : 'bg-primary'; ?>" 
                                                 style="width: <?php echo $overallProgress; ?>%"></div>
                                        </div>
                                        <span class="badge bg-light text-dark border rounded-pill px-2 py-1 small">
                                            <?php echo $completedCount; ?>/<?php echo $totalAssignees; ?> Done (<?php echo $overallProgress; ?>%)
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="task<?php echo $taskIndex; ?>" class="accordion-collapse collapse" data-bs-parent="#tasksAccordion">
                            <div class="accordion-body bg-light p-4">
                                <?php if ($taskGroup['description']): ?>
                                <div class="p-3 bg-white rounded-4 border mb-3">
                                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Briefing & Scope:</span>
                                    <p class="mb-0 text-dark small"><?php echo nl2br(htmlspecialchars($taskGroup['description'])); ?></p>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($taskGroup['attachment'])): ?>
                                <div class="mb-3 d-flex align-items-center gap-2">
                                    <span class="small text-muted">Briefing Document:</span>
                                    <a href="<?php echo APP_URL . '/uploads/tasks/' . htmlspecialchars($taskGroup['attachment']); ?>" 
                                       target="_blank" class="saas-tag sky text-decoration-none">
                                        <?php echo pika_icon('download', '', 12); ?> <?php echo htmlspecialchars($taskGroup['attachment']); ?>
                                    </a>
                                </div>
                                <?php endif; ?>

                                <!-- Assignees Table -->
                                <div class="rounded-4 overflow-hidden border bg-white shadow-sm mb-3">
                                    <table class="saas-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Assignee</th>
                                                <th>Current Progress</th>
                                                <th>Deliverable Link</th>
                                                <th>Last Active</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($taskGroup['assignees'] as $assignee): 
                                                $initials = strtoupper(substr($assignee['employee_name'], 0, 1));
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="saas-user-cell">
                                                        <?php if (!empty($assignee['avatar'])): ?>
                                                        <img src="<?php echo getAvatar($assignee['avatar']); ?>" class="saas-avatar" style="width: 36px; height: 36px;" alt="">
                                                        <?php else: ?>
                                                        <div class="saas-avatar-initials" style="width: 36px; height: 36px; font-size: 12px;"><?php echo $initials; ?></div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <div class="fw-bold text-dark small"><?php echo htmlspecialchars($assignee['employee_name']); ?></div>
                                                            <span class="saas-id-badge" style="font-size: 0.7rem; padding: 1px 7px;">
                                                                <?php echo htmlspecialchars($assignee['emp_id']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="saas-tag <?php 
                                                        echo match($assignee['status']) {
                                                            'completed' => 'emerald',
                                                            'submitted' => 'amber',
                                                            'changes_requested' => 'rose',
                                                            'in_progress' => 'sky',
                                                            'overdue' => 'rose',
                                                            default => 'primary'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $assignee['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($assignee['work_link']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 view-work-btn" 
                                                            data-title="<?php echo htmlspecialchars($taskGroup['title'], ENT_QUOTES); ?>"
                                                            data-employee="<?php echo htmlspecialchars($assignee['employee_name'], ENT_QUOTES); ?>"
                                                            data-empid="<?php echo htmlspecialchars($assignee['emp_id'], ENT_QUOTES); ?>"
                                                            data-link="<?php echo htmlspecialchars($assignee['work_link'], ENT_QUOTES); ?>"
                                                            data-linktype="<?php echo htmlspecialchars($assignee['work_link_type'] ?? 'url', ENT_QUOTES); ?>"
                                                            data-description="<?php echo htmlspecialchars($taskGroup['description'] ?? '', ENT_QUOTES); ?>"
                                                            data-deadline="<?php echo $taskGroup['deadline'] ? formatDate($taskGroup['deadline']) : 'No deadline'; ?>"
                                                            data-priority="<?php echo ucfirst($taskGroup['priority']); ?>">
                                                        <?php echo pika_icon('eye', 'me-1', 13); ?> Review Deliverable
                                                    </button>
                                                    <?php else: ?>
                                                    <span class="text-muted small">No submission yet</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo date('d M Y, h:i A', strtotime($assignee['updated_at'])); ?></small>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-inline-flex gap-1">
                                                        <?php if ($assignee['status'] === 'submitted'): ?>
                                                        <button class="saas-action-btn success" onclick="reviewTask(<?php echo $assignee['id']; ?>, 'approve', '<?php echo addslashes($taskGroup['title']); ?>')" title="Approve Work">
                                                            <?php echo pika_icon('check', '', 15); ?>
                                                        </button>
                                                        <button class="saas-action-btn amber" onclick="showFeedbackModal(<?php echo $assignee['id']; ?>, '<?php echo addslashes($taskGroup['title']); ?>')" title="Request Revision">
                                                            <?php echo pika_icon('edit', '', 15); ?>
                                                        </button>
                                                        <?php else: ?>
                                                        <a href="?action=edit&id=<?php echo $assignee['id']; ?>" class="saas-action-btn" title="Edit Task">
                                                            <?php echo pika_icon('edit', '', 15); ?>
                                                        </a>
                                                        <button type="button" class="saas-action-btn delete" title="Delete Task"
                                                                onclick="deleteTask(<?php echo $assignee['id']; ?>, '<?php echo addslashes($taskGroup['title']); ?>')">
                                                            <?php echo pika_icon('trash', '', 15); ?>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <?php echo pika_icon('tasks', 'opacity-25 d-block mx-auto mb-3', 48); ?>
                    <h5 class="fw-bold text-dark">No Tasks Match Query</h5>
                    <p class="text-muted small">Adjust your filter options or create a new assignment.</p>
                    <a href="?action=add" class="btn btn-primary btn-sm">
                        <?php echo pika_icon('plus', 'me-1', 14); ?> Add Task
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <!-- Add / Edit Task Form Workspace -->
        <div class="saas-card">
            <div class="saas-card-header">
                <h5 class="saas-card-title">
                    <?php echo pika_icon($action === 'add' ? 'plus' : 'edit', 'text-primary', 20); ?>
                    <?php echo $action === 'add' ? 'Deploy New Task Assignment' : 'Modify Task Details'; ?>
                </h5>
                <a href="tasks" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Tasks
                </a>
            </div>
            <div class="saas-card-body p-4">
                <form method="POST" id="taskForm" enctype="multipart/form-data" onsubmit="return preventDoubleSubmit(this)">
                    <input type="hidden" name="action" value="<?php echo $action; ?>">
                    <input type="hidden" name="form_token" value="<?php echo bin2hex(random_bytes(16)); ?>">
                    <?php if ($task): ?>
                    <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark small">Task Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-pill px-3 py-2" name="title" required 
                                   value="<?php echo htmlspecialchars($task['title'] ?? ''); ?>" placeholder="e.g. Implement Clientura Cloud Certificate Verification Portal">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark small">Briefing & Specifications</label>
                            <textarea class="form-control rounded-4 p-3" name="description" rows="3" placeholder="Describe the task scope, key deliverables, and documentation requirements..."><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea>
                        </div>

                        <!-- Assignees Selector -->
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark small">
                                Assign To <span class="text-danger">*</span>
                                <?php if ($action === 'add'): ?>
                                <small class="text-muted fw-normal ms-2">(Select one or multiple employees)</small>
                                <?php endif; ?>
                            </label>

                            <?php if ($action === 'add'): ?>
                            <div class="d-flex gap-2 mb-3 flex-wrap">
                                <input type="text" class="form-control rounded-pill px-3 py-2" id="employeeSearch" placeholder="Search team members..." style="max-width: 280px;">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllEmployees(true)">
                                    <?php echo pika_icon('check', 'me-1', 14); ?> Select All
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllEmployees(false)">
                                    <?php echo pika_icon('x', 'me-1', 14); ?> Clear
                                </button>
                                <span class="badge bg-primary align-self-center px-3 py-2 rounded-pill" id="selectedCount">0 selected</span>
                            </div>

                            <div class="p-3 bg-light rounded-4 border" style="max-height: 340px; overflow-y: auto;">
                                <div class="row g-2">
                                    <?php foreach ($employees as $emp): ?>
                                    <div class="col-12 col-sm-6 col-lg-4 employee-item" data-name="<?php echo strtolower($emp['name']); ?>">
                                        <label class="d-flex align-items-center gap-2 p-2 bg-white rounded-3 border cursor-pointer hover-shadow" style="cursor: pointer;">
                                            <input type="checkbox" name="assigned_to[]" value="<?php echo $emp['id']; ?>" 
                                                   class="form-check-input employee-checkbox m-0" onchange="updateSelectedCount()">
                                            <div class="min-w-0 flex-grow-1">
                                                <div class="fw-semibold text-dark small text-truncate"><?php echo htmlspecialchars($emp['name']); ?></div>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($emp['employee_id']); ?> • <?php echo htmlspecialchars($emp['domain_name'] ?? 'Gen'); ?></small>
                                            </div>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <select class="form-select rounded-pill px-3 py-2" name="assigned_to[]" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" <?php echo ($task['assigned_to'] ?? '') == $emp['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['name']); ?> (<?php echo htmlspecialchars($emp['employee_id']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark small">Priority Level</label>
                            <select class="form-select rounded-pill px-3 py-2" name="priority">
                                <option value="low" <?php echo ($task['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo ($task['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo ($task['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="urgent" <?php echo ($task['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark small">Milestone Deadline</label>
                            <input type="date" class="form-control rounded-pill px-3 py-2" name="deadline" value="<?php echo htmlspecialchars($task['deadline'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark small">Task State</label>
                            <select class="form-select rounded-pill px-3 py-2" name="status">
                                <option value="not_started" <?php echo ($task['status'] ?? 'not_started') === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                                <option value="in_progress" <?php echo ($task['status'] ?? '') === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo ($task['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold text-dark small">Reference Attachment</label>
                            <?php if ($task && !empty($task['attachment'])): ?>
                            <div class="small mb-2">
                                Current file: <a href="<?php echo APP_URL . '/uploads/tasks/' . htmlspecialchars($task['attachment']); ?>" target="_blank" class="fw-bold"><?php echo htmlspecialchars($task['attachment']); ?></a>
                            </div>
                            <?php endif; ?>
                            <input type="file" class="form-control rounded-pill px-3 py-2" name="task_attachment" accept=".pdf,.csv,.xls,.xlsx,.doc,.docx">
                            <small class="text-muted">PDF, CSV, XLS, DOCX (Max 10MB)</small>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4 pt-2">
                        <button type="submit" class="btn btn-primary btn-glow px-4">
                            <?php echo pika_icon('check', 'me-1', 16); ?>
                            <?php echo $action === 'add' ? 'Deploy Task' : 'Save Changes'; ?>
                        </button>
                        <a href="tasks" class="btn btn-outline-secondary px-3">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function preventDoubleSubmit(form) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn.disabled) return false;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deploying...';
            return true;
        }

        document.getElementById('employeeSearch')?.addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            document.querySelectorAll('.employee-item').forEach(item => {
                item.style.display = item.dataset.name.includes(search) ? '' : 'none';
            });
        });

        function toggleAllEmployees(select) {
            document.querySelectorAll('.employee-checkbox').forEach(cb => {
                if (cb.closest('.employee-item').style.display !== 'none') cb.checked = select;
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const count = document.querySelectorAll('.employee-checkbox:checked').length;
            const badge = document.getElementById('selectedCount');
            if (badge) badge.textContent = count + ' selected';
        }
        </script>
        <?php endif; ?>
    </div>
</div>

<!-- View Work Modal -->
<div class="modal fade" id="viewWorkModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-navy text-white border-0 px-4 py-3" style="background: #0A1138;">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
                    <?php echo pika_icon('briefcase', 'text-white', 20); ?> Deliverable Inspection
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <h5 id="workTaskTitle" class="fw-bold text-dark mb-1"></h5>
                <p class="text-muted small mb-3">Submitted by <strong id="workEmployeeName" class="text-dark"></strong> (<span id="workEmployeeId"></span>)</p>

                <div class="p-3 bg-light rounded-4 border mb-3">
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Work Description:</span>
                    <p id="workDescription" class="mb-0 text-dark small"></p>
                </div>

                <div class="d-grid mt-4">
                    <a id="workLink" href="#" target="_blank" class="btn btn-primary btn-glow">
                        <i class="bi bi-box-arrow-up-right me-2"></i> Open Submitted Work Link
                    </a>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Request Changes Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-warning text-dark border-0 px-4 py-3">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <?php echo pika_icon('edit', 'text-dark', 20); ?> Request Revision
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="review">
                <input type="hidden" name="review_action" value="request_changes">
                <input type="hidden" name="id" id="feedbackTaskId">
                <div class="modal-body p-4">
                    <p class="mb-2">Requesting changes for: <strong id="feedbackTaskTitle" class="text-dark"></strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Revision Feedback <span class="text-danger">*</span></label>
                        <textarea class="form-control rounded-3 p-3" name="reviewer_feedback" rows="3" required 
                                  placeholder="Specify requirements or changes required before final sign-off..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning px-4">
                        <?php echo pika_icon('check', 'me-1', 14); ?> Send Revision Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white border-0 px-4 py-3">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <?php echo pika_icon('trash', 'text-white', 20); ?> Remove Task
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-0">Are you sure you want to delete <strong id="deleteTaskName" class="text-dark"></strong>?</p>
            </div>
            <div class="modal-footer bg-light border-0 px-4 py-3">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteTaskId">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Quick Approve Form -->
<form id="approveForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="review">
    <input type="hidden" name="review_action" value="approve">
    <input type="hidden" name="id" id="approveTaskId">
</form>

<script>
function deleteTask(id, title) {
    document.getElementById('deleteTaskId').value = id;
    document.getElementById('deleteTaskName').textContent = title;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function reviewTask(taskId, action, title) {
    if (action === 'approve') {
        if (confirm('Approve task deliverable for: ' + title + '?')) {
            document.getElementById('approveTaskId').value = taskId;
            document.getElementById('approveForm').submit();
        }
    }
}

function showFeedbackModal(taskId, title) {
    document.getElementById('feedbackTaskId').value = taskId;
    document.getElementById('feedbackTaskTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('feedbackModal')).show();
}

function showWorkModal(title, empName, empId, link, linkType, description) {
    document.getElementById('workTaskTitle').textContent = title;
    document.getElementById('workEmployeeName').textContent = empName;
    document.getElementById('workEmployeeId').textContent = empId;
    document.getElementById('workLink').href = link;
    document.getElementById('workDescription').textContent = description || 'No extra description notes provided.';
    new bootstrap.Modal(document.getElementById('viewWorkModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-work-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            showWorkModal(
                this.dataset.title,
                this.dataset.employee,
                this.dataset.empid,
                this.dataset.link,
                this.dataset.linktype,
                this.dataset.description
            );
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
