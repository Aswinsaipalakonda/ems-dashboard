<?php
/**
 * Admin Task Management - Grouped View
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
            header("Location: tasks.php");
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
        
        if (!is_array($assignedToArray)) {
            $assignedToArray = [$assignedToArray];
        }
        $assignedToArray = array_filter(array_map('intval', $assignedToArray));
        
        if (empty($title) || empty($assignedToArray)) {
            $message = 'Title and at least one Employee are required!';
            $messageType = 'danger';
        } else {
            if ($postAction === 'add') {
                $createdCount = 0;
                foreach ($assignedToArray as $assignedTo) {
                    $sql = "INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, deadline, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    executeQuery($sql, "ssiisss", [$title, $description, $assignedTo, $_SESSION['user_id'], $priority, $deadline, $status]);
                    
                    $employee = fetchOne("SELECT name, email FROM employees WHERE id = ?", "i", [$assignedTo]);
                    executeQuery(
                        "INSERT INTO notifications (user_id, user_type, title, message, type, link) VALUES (?, 'employee', ?, ?, 'info', ?)",
                        "isss",
                        [$assignedTo, 'New Task Assigned', 'You have been assigned a new task: ' . $title, '/employee/tasks.php']
                    );
                    
                    $createdCount++;
                }
                
                $_SESSION['flash_message'] = $createdCount . ' task(s) created successfully!';
                $_SESSION['flash_type'] = 'success';
                header("Location: tasks.php");
                exit;
            } else {
                $editId = intval($_POST['id']);
                $assignedTo = $assignedToArray[0];
                $sql = "UPDATE tasks SET title = ?, description = ?, assigned_to = ?, priority = ?, deadline = ?, status = ? WHERE id = ?";
                executeQuery($sql, "ssisssi", [$title, $description, $assignedTo, $priority, $deadline, $status, $editId]);
                
                $_SESSION['flash_message'] = 'Task updated successfully!';
                $_SESSION['flash_type'] = 'success';
                header("Location: tasks.php");
                exit;
            }
        }
    } elseif ($postAction === 'add_employee_to_task') {
        // Add additional employee to existing task group
        $taskGroupKey = $_POST['task_group_key'] ?? '';
        $newAssignees = $_POST['new_assigned_to'] ?? [];
        
        if (empty($taskGroupKey) || empty($newAssignees)) {
            $message = 'Please select at least one employee to add!';
            $messageType = 'danger';
        } else {
            // Get all tasks and find the matching task group by key
            $allTasks = fetchAll("SELECT * FROM tasks ORDER BY created_at DESC");
            $foundTask = null;
            foreach ($allTasks as $t) {
                $taskKey = md5($t['title'] . '|' . $t['description'] . '|' . $t['deadline'] . '|' . $t['priority'] . '|' . $t['assigned_by']);
                if ($taskKey === $taskGroupKey) {
                    $foundTask = $t;
                    break;
                }
            }
            
            if ($foundTask) {
                $successCount = 0;
                foreach ($newAssignees as $assignedTo) {
                    $assignedTo = intval($assignedTo);
                    if ($assignedTo > 0) {
                        // Check if already assigned
                        $existing = fetchOne(
                            "SELECT id FROM tasks WHERE title = ? AND description = ? AND deadline = ? AND priority = ? AND assigned_to = ? AND assigned_by = ?",
                            "ssssii",
                            [$foundTask['title'], $foundTask['description'], $foundTask['deadline'], $foundTask['priority'], $assignedTo, $foundTask['assigned_by']]
                        );
                        
                        if (!$existing) {
                            $sql = "INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, status, deadline) VALUES (?, ?, ?, ?, ?, ?, ?)";
                            executeQuery($sql, "ssiisss", [$foundTask['title'], $foundTask['description'], $assignedTo, $_SESSION['user_id'], $foundTask['priority'], $foundTask['status'], $foundTask['deadline']]);
                            
                            // Notify employee
                            executeQuery(
                                "INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'employee', 'New Task Assigned', ?, 'info')",
                                "is",
                                [$assignedTo, "You have been assigned a new task: {$foundTask['title']}"]
                            );
                            $successCount++;
                        }
                    }
                }
                
                $_SESSION['flash_message'] = "Employee(s) added to task successfully!";
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = 'Task group not found!';
                $_SESSION['flash_type'] = 'danger';
            }
            header("Location: tasks.php");
            exit;
        }
    } elseif ($postAction === 'delete') {
        // Check if we're deleting a single task assignment or entire task
        $taskId = intval($_POST['id']);
        $singleTask = fetchOne("SELECT * FROM tasks WHERE id = ?", "i", [$taskId]);
        
        if ($singleTask) {
            // Count how many employees are assigned to this task's parent group
            $countResult = fetchOne(
                "SELECT COUNT(*) as total FROM tasks WHERE title = ? AND description = ? AND deadline = ? AND priority = ? AND assigned_by = ?",
                "ssssi",
                [$singleTask['title'], $singleTask['description'], $singleTask['deadline'], $singleTask['priority'], $singleTask['assigned_by']]
            );
            
            $totalAssignees = $countResult['total'] ?? 0;
            
            if ($totalAssignees > 1) {
                // Multiple employees assigned - remove only this one
                executeQuery("DELETE FROM tasks WHERE id = ?", "i", [$taskId]);
                $_SESSION['flash_message'] = 'Employee removed from task successfully!';
            } else {
                // Only one employee assigned - delete the entire task
                executeQuery("DELETE FROM tasks WHERE id = ?", "i", [$taskId]);
                $_SESSION['flash_message'] = 'Task deleted successfully!';
            }
        }
        $_SESSION['flash_type'] = 'success';
        header("Location: tasks.php");
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
                    [$existingTask['assigned_to'], "Your task '{$existingTask['title']}' has been approved and marked as completed!"]
                );
                
                $_SESSION['flash_message'] = 'Task approved and marked as completed!';
                $_SESSION['flash_type'] = 'success';
            } elseif ($reviewAction === 'request_changes') {
                executeQuery("UPDATE tasks SET status = 'changes_requested', reviewer_feedback = ? WHERE id = ?", "si", [$reviewerFeedback, $reviewId]);
                
                executeQuery(
                    "INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'employee', 'Changes Requested ⚠️', ?, 'warning')",
                    "is",
                    [$existingTask['assigned_to'], "Changes requested for task '{$existingTask['title']}': $reviewerFeedback"]
                );
                
                $_SESSION['flash_message'] = 'Changes requested. Employee has been notified.';
                $_SESSION['flash_type'] = 'warning';
            }
            header("Location: tasks.php");
            exit;
        }
    }
}

// Get task for editing
$task = null;
if ($action === 'edit' && $id) {
    $task = fetchOne("SELECT * FROM tasks WHERE id = ?", "i", [$id]);
}

// Get all employees for assignment
$employees = fetchAll(
    "SELECT e.id, e.name, e.employee_id, d.name as domain_name 
     FROM employees e 
     LEFT JOIN domains d ON e.domain_id = d.id 
     WHERE e.status = 'active' 
     ORDER BY d.name, e.name"
);

// Get all tasks with filters
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

// Check for overdue tasks
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

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Task Management</h1>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="Avatar" class="user-avatar">
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['user_name']; ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
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
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'x-circle'); ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit Form -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-<?php echo $action === 'add' ? 'plus-circle' : 'pencil'; ?> me-2"></i>
                    <?php echo $action === 'add' ? 'Create New Task' : 'Edit Task'; ?>
                </h5>
                <a href="<?php echo APP_URL; ?>/admin/tasks.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
            <div class="card-body">
                <form method="POST" id="taskForm" onsubmit="return preventDoubleSubmit(this)">
                    <input type="hidden" name="action" value="<?php echo $action; ?>">
                    <input type="hidden" name="form_token" value="<?php echo bin2hex(random_bytes(16)); ?>">
                    <?php if ($task): ?>
                    <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Task Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" required 
                                   value="<?php echo $task['title'] ?? ''; ?>" placeholder="Enter task title">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Enter task description"><?php echo $task['description'] ?? ''; ?></textarea>
                        </div>
                        
                        <!-- Employee Selection with Checkboxes -->
                        <div class="col-12 mb-3">
                            <label class="form-label">
                                Assign To <span class="text-danger">*</span>
                                <?php if ($action === 'add'): ?>
                                <small class="text-muted ms-2">Select one or more employees</small>
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($action === 'add'): ?>
                            <div class="d-flex gap-2 mb-3 flex-wrap">
                                <input type="text" class="form-control" id="employeeSearch" placeholder="Search employees..." style="max-width: 300px;">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllEmployees(true)">
                                    <i class="bi bi-check-all me-1"></i>Select All
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllEmployees(false)">
                                    <i class="bi bi-x-lg me-1"></i>Clear All
                                </button>
                                <span class="badge bg-primary align-self-center" id="selectedCount">0 selected</span>
                            </div>
                            
                            <div class="employee-checkbox-grid">
                                <?php 
                                $groupedEmployees = [];
                                foreach ($employees as $emp) {
                                    $domain = $emp['domain_name'] ?? 'Other';
                                    $groupedEmployees[$domain][] = $emp;
                                }
                                foreach ($groupedEmployees as $domain => $domainEmployees): ?>
                                <div class="employee-domain-group mb-3">
                                    <h6 class="text-muted border-bottom pb-2 mb-2">
                                        <i class="bi bi-folder me-1"></i><?php echo $domain; ?>
                                        <button type="button" class="btn btn-link btn-sm p-0 ms-2" onclick="toggleDomain('<?php echo md5($domain); ?>')">
                                            <small>Toggle All</small>
                                        </button>
                                    </h6>
                                    <div class="row g-2" id="domain-<?php echo md5($domain); ?>">
                                        <?php foreach ($domainEmployees as $emp): ?>
                                        <div class="col-12 col-sm-6 col-lg-4 employee-item" data-name="<?php echo strtolower($emp['name']); ?>">
                                            <label class="employee-checkbox-card">
                                                <input type="checkbox" name="assigned_to[]" value="<?php echo $emp['id']; ?>" 
                                                       class="employee-checkbox domain-<?php echo md5($domain); ?>" onchange="updateSelectedCount()">
                                                <div class="card-body d-flex align-items-center gap-2 p-2">
                                                    <i class="bi bi-person-circle fs-5 text-muted"></i>
                                                    <div class="flex-grow-1 min-w-0">
                                                        <div class="fw-medium text-truncate"><?php echo $emp['name']; ?></div>
                                                        <small class="text-muted"><?php echo $emp['employee_id']; ?></small>
                                                    </div>
                                                    <i class="bi bi-check-circle-fill text-success check-icon"></i>
                                                </div>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <select class="form-select" name="assigned_to[]" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" <?php echo ($task['assigned_to'] ?? '') == $emp['id'] ? 'selected' : ''; ?>>
                                    <?php echo $emp['name']; ?> (<?php echo $emp['employee_id']; ?><?php echo $emp['domain_name'] ? ' - '.$emp['domain_name'] : ''; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="low" <?php echo ($task['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo ($task['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo ($task['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="urgent" <?php echo ($task['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Deadline</label>
                            <input type="date" class="form-control" name="deadline" value="<?php echo $task['deadline'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="not_started" <?php echo ($task['status'] ?? 'not_started') === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                                <option value="in_progress" <?php echo ($task['status'] ?? '') === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo ($task['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i><?php echo $action === 'add' ? 'Create Task' : 'Update Task'; ?>
                        </button>
                        <a href="<?php echo APP_URL; ?>/admin/tasks.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <style>
        .employee-checkbox-grid { max-height: 400px; overflow-y: auto; }
        .employee-checkbox-card {
            display: block;
            cursor: pointer;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.2s;
            background: #fff;
        }
        .employee-checkbox-card:hover {
            border-color: #0d6efd;
            background: #f8f9ff;
        }
        .employee-checkbox-card input { display: none; }
        .employee-checkbox-card .check-icon { display: none; }
        .employee-checkbox-card input:checked + .card-body {
            background: #e8f4ff;
            border-radius: 6px;
        }
        .employee-checkbox-card input:checked + .card-body .check-icon { display: block; }
        </style>
        
        <script>
        function preventDoubleSubmit(form) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn.disabled) return false;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
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
                if (cb.closest('.employee-item').style.display !== 'none') {
                    cb.checked = select;
                }
            });
            updateSelectedCount();
        }
        
        function toggleDomain(domainHash) {
            const checkboxes = document.querySelectorAll('.domain-' + domainHash);
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            const count = document.querySelectorAll('.employee-checkbox:checked').length;
            const badge = document.getElementById('selectedCount');
            if (badge) badge.textContent = count + ' selected';
        }
        </script>
        
        <?php else: ?>
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="not_started" <?php echo $statusFilter === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                            <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="submitted" <?php echo $statusFilter === 'submitted' ? 'selected' : ''; ?>>Submitted (Needs Review)</option>
                            <option value="changes_requested" <?php echo $statusFilter === 'changes_requested' ? 'selected' : ''; ?>>Changes Requested</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="overdue" <?php echo $statusFilter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="priority">
                            <option value="">All Priority</option>
                            <option value="low" <?php echo $priorityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $priorityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $priorityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo $priorityFilter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="employee">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo $employeeFilter == $emp['id'] ? 'selected' : ''; ?>>
                                <?php echo $emp['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Filter</button>
                        <?php if ($statusFilter || $priorityFilter || $employeeFilter): ?>
                        <a href="tasks.php" class="btn btn-outline-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tasks List - Grouped View -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-task me-2"></i>All Tasks (<?php echo count($tasks); ?>)</h5>
                <a href="?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>New Task
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (count($groupedTasks) > 0): ?>
                <div class="accordion accordion-flush" id="tasksAccordion">
                    <?php $taskIndex = 0; foreach ($groupedTasks as $taskKey => $taskGroup): $taskIndex++; ?>
                    <?php 
                        $totalAssignees = count($taskGroup['assignees']);
                        $completedCount = count(array_filter($taskGroup['assignees'], fn($a) => $a['status'] === 'completed'));
                        $submittedCount = count(array_filter($taskGroup['assignees'], fn($a) => $a['status'] === 'submitted'));
                        $changesCount = count(array_filter($taskGroup['assignees'], fn($a) => $a['status'] === 'changes_requested'));
                        $overdueCount = count(array_filter($taskGroup['assignees'], fn($a) => $a['status'] === 'overdue'));
                        
                        $overallProgress = round(($completedCount / $totalAssignees) * 100);
                        $needsReview = $submittedCount > 0;
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#task<?php echo $taskIndex; ?>">
                                <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-2 w-100 me-3">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <strong><?php echo htmlspecialchars($taskGroup['title']); ?></strong>
                                            <?php if ($needsReview): ?>
                                            <span class="badge bg-info"><i class="bi bi-exclamation-circle me-1"></i><?php echo $submittedCount; ?> Needs Review</span>
                                            <?php endif; ?>
                                            <?php if ($changesCount > 0): ?>
                                            <span class="badge bg-warning text-dark"><?php echo $changesCount; ?> Changes Requested</span>
                                            <?php endif; ?>
                                            <?php if ($overdueCount > 0): ?>
                                            <span class="badge bg-danger"><?php echo $overdueCount; ?> Overdue</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            <i class="bi bi-people me-1"></i><?php echo $totalAssignees; ?> assignee(s) • 
                                            <span class="badge priority-<?php echo $taskGroup['priority']; ?> badge-sm">
                                                <?php echo ucfirst($taskGroup['priority']); ?>
                                            </span>
                                            <?php if ($taskGroup['deadline']): ?>
                                            • <i class="bi bi-calendar me-1"></i><?php echo formatDate($taskGroup['deadline']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress" style="width: 100px; height: 8px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $overallProgress; ?>%"></div>
                                        </div>
                                        <span class="badge bg-<?php echo $overallProgress == 100 ? 'success' : 'secondary'; ?>">
                                            <?php echo $completedCount; ?>/<?php echo $totalAssignees; ?>
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="task<?php echo $taskIndex; ?>" class="accordion-collapse collapse" data-bs-parent="#tasksAccordion">
                            <div class="accordion-body bg-light">
                                <?php if ($taskGroup['description']): ?>
                                <div class="mb-3 p-3 bg-white rounded">
                                    <small class="text-muted d-block mb-1">Description:</small>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($taskGroup['description'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <h6 class="mb-3"><i class="bi bi-people me-2"></i>Assigned Employees & Progress</h6>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover bg-white mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Employee</th>
                                                <th>Status</th>
                                                <th>Work Link</th>
                                                <th>Last Updated</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($taskGroup['assignees'] as $assignee): ?>
                                            <tr class="<?php echo $assignee['status'] === 'submitted' ? 'table-info' : ($assignee['status'] === 'overdue' ? 'table-danger' : ''); ?>">
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?php echo getAvatar($assignee['avatar']); ?>" class="avatar avatar-sm" alt="">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($assignee['employee_name']); ?></strong>
                                                            <small class="d-block text-muted"><?php echo $assignee['emp_id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge status-<?php echo $assignee['status']; ?>">
                                                        <?php 
                                                        $statusLabels = [
                                                            'not_started' => 'Not Started',
                                                            'in_progress' => 'In Progress',
                                                            'submitted' => 'Submitted',
                                                            'completed' => 'Completed',
                                                            'changes_requested' => 'Changes Needed',
                                                            'overdue' => 'Overdue'
                                                        ];
                                                        echo $statusLabels[$assignee['status']] ?? ucfirst($assignee['status']);
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($assignee['work_link']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" 
                                                            onclick="showWorkModal('<?php echo addslashes($taskGroup['title']); ?>', '<?php echo addslashes($assignee['employee_name']); ?>', '<?php echo addslashes($assignee['emp_id']); ?>', '<?php echo addslashes($assignee['work_link']); ?>', '<?php echo addslashes($assignee['work_link_type'] ?? 'url'); ?>', '<?php echo addslashes($taskGroup['description'] ?? ''); ?>', '<?php echo $taskGroup['deadline'] ? formatDate($taskGroup['deadline']) : 'No deadline'; ?>', '<?php echo ucfirst($taskGroup['priority']); ?>')">
                                                        <i class="bi bi-link-45deg"></i> View
                                                    </button>
                                                    <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo date('d M Y, h:i A', strtotime($assignee['updated_at'])); ?></small>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($assignee['status'] === 'submitted'): ?>
                                                    <button class="btn btn-success btn-sm" onclick="reviewTask(<?php echo $assignee['id']; ?>, 'approve', '<?php echo addslashes($taskGroup['title']); ?>')" title="Approve">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button class="btn btn-warning btn-sm" onclick="showFeedbackModal(<?php echo $assignee['id']; ?>, '<?php echo addslashes($taskGroup['title']); ?>')" title="Request Changes">
                                                        <i class="bi bi-chat-left-text"></i>
                                                    </button>
                                                    <?php else: ?>
                                                    <a href="?action=edit&id=<?php echo $assignee['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                                            onclick="deleteTask(<?php echo $assignee['id']; ?>, '<?php echo addslashes($taskGroup['title']); ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Add Employee to Task Section -->
                                <div class="mt-4 pt-3 border-top">
                                    <h6 class="mb-3"><i class="bi bi-person-plus me-2"></i>Add Employee to Task</h6>
                                    <form method="POST" id="addEmpForm_<?php echo $taskIndex; ?>" class="row g-3">
                                        <input type="hidden" name="action" value="add_employee_to_task">
                                        <input type="hidden" name="task_group_key" value="<?php echo $taskKey; ?>">
                                        
                                        <div class="col-12">
                                            <div class="d-flex gap-2 mb-2 flex-wrap">
                                                <input type="text" class="form-control" id="empSearch_<?php echo $taskIndex; ?>" placeholder="Search employees..." style="max-width: 250px;">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllNewEmps(true, <?php echo $taskIndex; ?>)">
                                                    <i class="bi bi-check-all me-1"></i>Select All
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllNewEmps(false, <?php echo $taskIndex; ?>)">
                                                    <i class="bi bi-x-lg me-1"></i>Clear
                                                </button>
                                                <span class="badge bg-primary align-self-center" id="empCountAdd_<?php echo $taskIndex; ?>">0 selected</span>
                                            </div>
                                            
                                            <div class="emp-checkbox-grid" style="max-height: 300px; overflow-y: auto;">
                                                <div class="row g-2">
                                                    <?php 
                                                    $existingIds = array_map(fn($a) => $a['employee_id'], $taskGroup['assignees']);
                                                    foreach ($employees as $emp):
                                                        if (!in_array($emp['id'], $existingIds)):
                                                    ?>
                                                    <div class="col-12 col-sm-6 col-lg-4 emp-item-add-<?php echo $taskIndex; ?>" data-name="<?php echo strtolower($emp['name']); ?>">
                                                        <label class="emp-checkbox-card">
                                                            <input type="checkbox" name="new_assigned_to[]" value="<?php echo $emp['id']; ?>" 
                                                                   class="emp-checkbox-add-<?php echo $taskIndex; ?>" onchange="updateEmpCountAdd(<?php echo $taskIndex; ?>)">
                                                            <div class="card-body d-flex align-items-center gap-2 p-2">
                                                                <i class="bi bi-person-circle fs-5 text-muted"></i>
                                                                <div class="flex-grow-1 min-w-0">
                                                                    <div class="fw-medium text-truncate"><?php echo $emp['name']; ?></div>
                                                                    <small class="text-muted"><?php echo $emp['employee_id']; ?></small>
                                                                </div>
                                                                <i class="bi bi-check-circle-fill text-success check-icon"></i>
                                                            </div>
                                                        </label>
                                                    </div>
                                                    <?php endif; endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-plus-lg me-1"></i>Add Selected Employee(s)
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <script>
                                    document.getElementById('empSearch_<?php echo $taskIndex; ?>')?.addEventListener('input', function(e) {
                                        const search = e.target.value.toLowerCase();
                                        document.querySelectorAll('.emp-item-add-<?php echo $taskIndex; ?>').forEach(item => {
                                            item.style.display = item.dataset.name.includes(search) ? '' : 'none';
                                        });
                                    });
                                    
                                    function toggleAllNewEmps(select, taskIndex) {
                                        document.querySelectorAll('.emp-checkbox-add-' + taskIndex).forEach(cb => {
                                            if (cb.closest('.emp-item-add-' + taskIndex)?.style.display !== 'none') cb.checked = select;
                                        });
                                        updateEmpCountAdd(taskIndex);
                                    }
                                    
                                    function updateEmpCountAdd(taskIndex) {
                                        const count = document.querySelectorAll('.emp-checkbox-add-' + taskIndex + ':checked').length;
                                        document.getElementById('empCountAdd_' + taskIndex).textContent = count + ' selected';
                                    }
                                    </script>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-list-task fs-1"></i>
                    <p class="mt-2">No tasks found</p>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Create First Task
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Work Modal -->
<div class="modal fade" id="viewWorkModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-briefcase me-2"></i>Task Work Submission</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label text-muted small mb-1">Task Title</label>
                    <h5 id="workTaskTitle" class="mb-0"></h5>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label text-muted small mb-1">Assigned To</label>
                        <div><i class="bi bi-person me-1"></i><span id="workEmployeeName"></span></div>
                        <small class="text-muted" id="workEmployeeId"></small>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-muted small mb-1">Deadline</label>
                        <div><i class="bi bi-calendar me-1"></i><span id="workDeadline"></span></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small mb-1">Priority</label>
                    <div><span id="workPriority" class="badge"></span></div>
                </div>
                <div class="mb-3" id="workDescriptionSection">
                    <label class="form-label text-muted small mb-1">Description</label>
                    <p id="workDescription" class="mb-0 text-muted"></p>
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label text-muted small mb-1">Work Link Type</label>
                    <div><span id="workLinkType" class="badge bg-secondary"></span></div>
                </div>
                <div class="mb-0">
                    <label class="form-label text-muted small mb-1">Submitted Work</label>
                    <div class="d-grid">
                        <a id="workLink" href="#" target="_blank" class="btn btn-outline-success">
                            <i class="bi bi-box-arrow-up-right me-2"></i>Open Work Link
                        </a>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-chat-left-text me-2"></i>Request Changes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="review">
                    <input type="hidden" name="review_action" value="request_changes">
                    <input type="hidden" name="id" id="feedbackTaskId">
                    <p class="text-muted">Requesting changes for: <strong id="feedbackTaskTitle"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Feedback for Employee <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="reviewer_feedback" rows="4" required 
                                  placeholder="Explain what changes are needed..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-send me-1"></i>Send Feedback</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete task: <strong id="deleteTaskName"></strong>?</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteTaskId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
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

<style>
.status-submitted { background: #0dcaf0; color: #000; }
.status-changes_requested { background: #fd7e14; color: #fff; }
.status-not_started { background: #6c757d; color: #fff; }
.status-in_progress { background: #0d6efd; color: #fff; }
.status-completed { background: #198754; color: #fff; }
.status-overdue { background: #dc3545; color: #fff; }

.priority-low { background: #6c757d; color: #fff; }
.priority-medium { background: #0dcaf0; color: #000; }
.priority-high { background: #ffc107; color: #000; }
.priority-urgent { background: #dc3545; color: #fff; }

.accordion-button:not(.collapsed) { background-color: #f8f9fa; color: #212529; }
.accordion-button:focus { box-shadow: none; border-color: rgba(0,0,0,.125); }
.accordion-item {
    border-left: 4px solid #0d6efd;
    margin-bottom: 0.5rem;
    border-radius: 0.5rem !important;
    overflow: hidden;
}
.accordion-item:has(.table-info) { border-left-color: #0dcaf0; }
.accordion-item:has(.table-danger) { border-left-color: #dc3545; }
.badge-sm { font-size: 0.7rem; padding: 0.2em 0.5em; }
</style>

<script>
function deleteTask(id, title) {
    document.getElementById('deleteTaskId').value = id;
    document.getElementById('deleteTaskName').textContent = title;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function reviewTask(taskId, action, title) {
    if (action === 'approve') {
        if (confirm('Approve task: ' + title + '?')) {
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

function showWorkModal(title, empName, empId, link, linkType, description, deadline, priority) {
    document.getElementById('workTaskTitle').textContent = title;
    document.getElementById('workEmployeeName').textContent = empName;
    document.getElementById('workEmployeeId').textContent = empId;
    document.getElementById('workDeadline').textContent = deadline;
    document.getElementById('workLink').href = link;
    
    const linkTypeLabels = { 'url': 'Website URL', 'github': 'GitHub Repository', 'drive': 'Google Drive', 'dropbox': 'Dropbox', 'other': 'Other Link' };
    document.getElementById('workLinkType').textContent = linkTypeLabels[linkType] || linkType;
    
    const priorityEl = document.getElementById('workPriority');
    priorityEl.textContent = priority;
    priorityEl.className = 'badge';
    if (priority.toLowerCase() === 'urgent') priorityEl.classList.add('bg-danger');
    else if (priority.toLowerCase() === 'high') priorityEl.classList.add('bg-warning', 'text-dark');
    else if (priority.toLowerCase() === 'medium') priorityEl.classList.add('bg-info');
    else priorityEl.classList.add('bg-secondary');
    
    const descSection = document.getElementById('workDescriptionSection');
    const descEl = document.getElementById('workDescription');
    if (description && description.trim()) {
        descEl.textContent = description;
        descSection.style.display = 'block';
    } else {
        descSection.style.display = 'none';
    }
    
    new bootstrap.Modal(document.getElementById('viewWorkModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
