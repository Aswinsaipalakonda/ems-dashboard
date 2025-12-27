<?php
/**
 * Team Lead - Team Tasks
 * Employee Management System
 */

$pageTitle = 'Team Tasks';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (!isTeamLead()) {
    header("Location: " . url("employee/dashboard"));
    exit;
}

$userId = $_SESSION['user_id'];
$teamMemberIds = getTeamMemberIds($userId);
$teamMembers = getTeamMembers($userId);

$message = '';
$messageType = '';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    // Check for duplicate submission using form token
    $formToken = $_POST['form_token'] ?? '';
    $isValidSubmission = true;
    
    if ($postAction === 'add') {
        if (empty($formToken) || (isset($_SESSION['last_form_token']) && $_SESSION['last_form_token'] === $formToken)) {
            // Duplicate submission detected
            $isValidSubmission = false;
            header("Location: team-tasks");
            exit;
        }
        $_SESSION['last_form_token'] = $formToken;
    }
    
    if ($isValidSubmission && ($postAction === 'add' || $postAction === 'edit')) {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $assignedToArray = $_POST['assigned_to'] ?? [];
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $status = sanitize($_POST['status'] ?? 'not_started');
        $deadline = $_POST['deadline'] ?? null;
        
        // Handle file upload (optional)
        $attachmentFile = null;
        if (!empty($_FILES['task_attachment']['name'])) {
            $allowedExtensions = ['pdf', 'csv', 'xls', 'xlsx', 'doc', 'docx'];
            $fileExt = strtolower(pathinfo($_FILES['task_attachment']['name'], PATHINFO_EXTENSION));
            $fileSize = $_FILES['task_attachment']['size'];
            $maxSize = 10 * 1024 * 1024; // 10MB max
            
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
                    $message = 'Failed to upload file. Please try again.';
                    $messageType = 'danger';
                    $attachmentFile = null;
                }
            }
        }
        
        // Ensure assigned_to is an array
        if (!is_array($assignedToArray)) {
            $assignedToArray = [$assignedToArray];
        }
        $assignedToArray = array_filter(array_map('intval', $assignedToArray));
        
        // Verify all assigned employees are team members
        $invalidAssignees = array_diff($assignedToArray, $teamMemberIds);
        
        if (!empty($invalidAssignees)) {
            $message = 'You can only assign tasks to your team members!';
            $messageType = 'danger';
        } elseif (empty($title) || empty($assignedToArray)) {
            $message = 'Task title and at least one team member are required!';
            $messageType = 'danger';
        } elseif (empty($message)) {
            if ($postAction === 'add') {
                $createdCount = 0;
                foreach ($assignedToArray as $assignedTo) {
                    $sql = "INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, status, deadline, attachment) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    executeQuery($sql, "ssiissss", [$title, $description, $assignedTo, $userId, $priority, $status, $deadline, $attachmentFile]);
                    
                    $notifyMsg = "Your Team Lead assigned a new task: $title";
                    if ($attachmentFile) {
                        $notifyMsg .= ' (with attachment)';
                    }
                    executeQuery(
                        "INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'employee', 'New Task from Team Lead', ?, 'info')",
                        "is",
                        [$assignedTo, $notifyMsg]
                    );
                    $createdCount++;
                }
                
                $_SESSION['flash_message'] = $createdCount . ' task(s) created successfully!';
                $_SESSION['flash_type'] = 'success';
                header("Location: team-tasks");
                exit;
            } else {
                $editId = intval($_POST['id']);
                $assignedTo = $assignedToArray[0]; // For edit, single assignment
                // Verify task is for team member
                $existingTask = fetchOne("SELECT assigned_to FROM tasks WHERE id = ?", "i", [$editId]);
                if ($existingTask && in_array($existingTask['assigned_to'], $teamMemberIds)) {
                    if ($attachmentFile) {
                        $sql = "UPDATE tasks SET title = ?, description = ?, assigned_to = ?, priority = ?, status = ?, deadline = ?, attachment = ? WHERE id = ?";
                        executeQuery($sql, "ssissssi", [$title, $description, $assignedTo, $priority, $status, $deadline, $attachmentFile, $editId]);
                    } else {
                        $sql = "UPDATE tasks SET title = ?, description = ?, assigned_to = ?, priority = ?, status = ?, deadline = ? WHERE id = ?";
                        executeQuery($sql, "ssisssi", [$title, $description, $assignedTo, $priority, $status, $deadline, $editId]);
                    }
                    $_SESSION['flash_message'] = 'Task updated successfully!';
                    $_SESSION['flash_type'] = 'success';
                    header("Location: team-tasks");
                    exit;
                } else {
                    $message = 'You can only edit tasks assigned to your team members!';
                    $messageType = 'danger';
                }
            }
        }
    } elseif ($postAction === 'delete') {
        $deleteId = intval($_POST['id']);
        $existingTask = fetchOne("SELECT * FROM tasks WHERE id = ?", "i", [$deleteId]);
        if ($existingTask && in_array($existingTask['assigned_to'], $teamMemberIds)) {
            // Check if multiple team members assigned to same task group
            $countResult = fetchOne(
                "SELECT COUNT(*) as total FROM tasks WHERE title = ? AND assigned_by = ?",
                "si",
                [$existingTask['title'], $existingTask['assigned_by']]
            );
            
            $totalAssignees = $countResult['total'] ?? 0;
            
            executeQuery("DELETE FROM tasks WHERE id = ?", "i", [$deleteId]);
            
            if ($totalAssignees > 1) {
                $_SESSION['flash_message'] = 'Employee removed from task successfully!';
            } else {
                $_SESSION['flash_message'] = 'Task deleted successfully!';
            }
            $_SESSION['flash_type'] = 'success';
            header("Location: team-tasks");
            exit;
        } else {
            $message = 'You can only delete tasks assigned to your team members!';
            $messageType = 'danger';
        }
    } elseif ($postAction === 'add_employee_to_task') {
        // Add additional employee to existing task group
        $taskGroupKey = $_POST['task_group_key'] ?? '';
        $newAssignees = $_POST['new_assigned_to'] ?? [];
        
        if (empty($taskGroupKey) || empty($newAssignees)) {
            $message = 'Please select at least one team member to add!';
            $messageType = 'danger';
        } else {
            // Verify all new assignees are team members
            $invalidAssignees = array_diff(array_map('intval', $newAssignees), $teamMemberIds);
            if (!empty($invalidAssignees)) {
                $message = 'You can only assign tasks to your team members!';
                $messageType = 'danger';
            } else {
                // Get all tasks to find the reference task
                $allTasks = fetchAll("SELECT * FROM tasks ORDER BY created_at DESC");
                $foundTask = null;
                foreach ($allTasks as $t) {
                    $taskKey = md5($t['title'] . '|' . $t['description'] . '|' . $t['deadline'] . '|' . $t['priority'] . '|' . $t['assigned_by']);
                    if ($taskKey === $taskGroupKey && in_array($t['assigned_to'], $teamMemberIds)) {
                        $foundTask = $t;
                        break;
                    }
                }
                
                if ($foundTask) {
                    $successCount = 0;
                    foreach ($newAssignees as $assignedTo) {
                        $assignedTo = intval($assignedTo);
                        if ($assignedTo > 0 && in_array($assignedTo, $teamMemberIds)) {
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
                    
                    $_SESSION['flash_message'] = "Team member(s) added to task successfully!";
                    $_SESSION['flash_type'] = 'success';
                    header("Location: team-tasks");
                    exit;
                } else {
                    $message = 'Task group not found!';
                    $messageType = 'danger';
                }
            }
        }
    } elseif ($postAction === 'review') {
        // Handle task review (approve or request changes)
        $reviewId = intval($_POST['id']);
        $reviewAction = sanitize($_POST['review_action'] ?? '');
        $reviewerFeedback = sanitize($_POST['reviewer_feedback'] ?? '');
        
        $existingTask = fetchOne("SELECT * FROM tasks WHERE id = ?", "i", [$reviewId]);
        if ($existingTask && in_array($existingTask['assigned_to'], $teamMemberIds)) {
            if ($reviewAction === 'approve') {
                executeQuery("UPDATE tasks SET status = 'completed', completed_at = NOW(), reviewer_feedback = NULL WHERE id = ?", "i", [$reviewId]);
                
                // Notify employee
                executeQuery(
                    "INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'employee', 'Task Approved! ✅', ?, 'success')",
                    "is",
                    [$existingTask['assigned_to'], "Your task '{$existingTask['title']}' has been approved and marked as completed!"]
                );
                
                $_SESSION['flash_message'] = 'Task approved and marked as completed!';
                $_SESSION['flash_type'] = 'success';
                header("Location: team-tasks");
                exit;
            } elseif ($reviewAction === 'request_changes') {
                executeQuery("UPDATE tasks SET status = 'changes_requested', reviewer_feedback = ? WHERE id = ?", "si", [$reviewerFeedback, $reviewId]);
                
                // Notify employee
                executeQuery(
                    "INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'employee', 'Changes Requested ⚠️', ?, 'warning')",
                    "is",
                    [$existingTask['assigned_to'], "Changes requested for task '{$existingTask['title']}': $reviewerFeedback"]
                );
                
                $_SESSION['flash_message'] = 'Changes requested. Employee has been notified.';
                $_SESSION['flash_type'] = 'warning';
                header("Location: team-tasks");
                exit;
            }
        }
    }
}

// Get task for editing
$task = null;
if ($action === 'edit' && $id && !empty($teamMemberIds)) {
    $placeholders = implode(',', array_fill(0, count($teamMemberIds), '?'));
    $task = fetchOne(
        "SELECT * FROM tasks WHERE id = ? AND assigned_to IN ($placeholders)",
        "i" . str_repeat('i', count($teamMemberIds)),
        array_merge([$id], $teamMemberIds)
    );
}

// Get team tasks - Group by task title, description, priority, deadline
$tasks = [];
$groupedTasks = [];
if (!empty($teamMemberIds)) {
    $statusFilter = $_GET['status'] ?? '';
    $placeholders = implode(',', array_fill(0, count($teamMemberIds), '?'));
    $params = $teamMemberIds;
    $types = str_repeat('i', count($teamMemberIds));
    
    $whereClause = "WHERE t.assigned_to IN ($placeholders)";
    if ($statusFilter) {
        $whereClause .= " AND t.status = ?";
        $params[] = $statusFilter;
        $types .= "s";
    }
    
    $tasks = fetchAll(
        "SELECT t.*, e.name as employee_name, e.employee_id as emp_id
         FROM tasks t 
         JOIN employees e ON t.assigned_to = e.id 
         $whereClause 
         ORDER BY t.created_at DESC",
        $types,
        $params
    );
    
    // Group tasks by title + description + deadline + priority (unique task identifier)
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
            'status' => $t['status'],
            'work_link' => $t['work_link'] ?? '',
            'work_link_type' => $t['work_link_type'] ?? 'url',
            'remarks' => $t['remarks'] ?? '',
            'updated_at' => $t['updated_at'] ?? $t['created_at']
        ];
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/teamlead-sidebar.php';
?>

<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Team Tasks</h1>
        </div>
        <div class="header-right">
            <?php if (!empty($teamMemberIds)): ?>
            <a href="?action=add" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Assign Task
            </a>
            <?php endif; ?>
        </div>
    </header>

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
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (empty($teamMemberIds)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>No team members assigned to you yet.
        </div>
        
        <?php elseif ($action === 'add' || ($action === 'edit' && $task)): ?>
        <!-- Add/Edit Form -->
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><?php echo $action === 'add' ? 'Assign New Task' : 'Edit Task'; ?></h5>
                <a href="team-tasks" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
            <div class="card-body">
                <form method="POST" id="taskForm" enctype="multipart/form-data" onsubmit="return preventDoubleSubmit(this)">
                    <input type="hidden" name="action" value="<?php echo $action; ?>">
                    <input type="hidden" name="form_token" value="<?php echo bin2hex(random_bytes(16)); ?>">
                    <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Task Title *</label>
                            <input type="text" name="title" class="form-control" required value="<?php echo $task['title'] ?? ''; ?>">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo $task['description'] ?? ''; ?></textarea>
                        </div>
                        
                        <!-- Team Member Selection -->
                        <div class="col-12 mb-3">
                            <label class="form-label">
                                Assign To *
                                <?php if ($action === 'add'): ?>
                                <small class="text-muted ms-2">Select team members</small>
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($action === 'add'): ?>
                            <div class="d-flex gap-2 mb-2 flex-wrap">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllMembers(true)">
                                    <i class="bi bi-check-all me-1"></i>Select All
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllMembers(false)">
                                    <i class="bi bi-x-lg me-1"></i>Clear
                                </button>
                                <span class="badge bg-primary align-self-center" id="memberCount">0 selected</span>
                            </div>
                            <div class="row g-2">
                                <?php foreach ($teamMembers as $member): ?>
                                <div class="col-12 col-sm-6 col-lg-4">
                                    <label class="member-checkbox-card">
                                        <input type="checkbox" name="assigned_to[]" value="<?php echo $member['id']; ?>" 
                                               class="member-checkbox" onchange="updateMemberCount()">
                                        <div class="card-body d-flex align-items-center gap-2 p-2">
                                            <i class="bi bi-person-circle fs-5 text-muted"></i>
                                            <div class="flex-grow-1">
                                                <div class="fw-medium"><?php echo $member['name']; ?></div>
                                                <small class="text-muted"><?php echo $member['employee_id']; ?></small>
                                            </div>
                                            <i class="bi bi-check-circle-fill text-success check-icon"></i>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <select name="assigned_to[]" class="form-select" required>
                                <?php foreach ($teamMembers as $member): ?>
                                <option value="<?php echo $member['id']; ?>" <?php echo (isset($task['assigned_to']) && $task['assigned_to'] == $member['id']) ? 'selected' : ''; ?>>
                                    <?php echo $member['name']; ?> (<?php echo $member['employee_id']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low" <?php echo (isset($task['priority']) && $task['priority'] === 'low') ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo (!isset($task['priority']) || $task['priority'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo (isset($task['priority']) && $task['priority'] === 'high') ? 'selected' : ''; ?>>High</option>
                                <option value="urgent" <?php echo (isset($task['priority']) && $task['priority'] === 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Deadline</label>
                            <input type="date" name="deadline" class="form-control" value="<?php echo $task['deadline'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="not_started" <?php echo (!isset($task['status']) || $task['status'] === 'not_started') ? 'selected' : ''; ?>>Not Started</option>
                                <option value="in_progress" <?php echo (isset($task['status']) && $task['status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo (isset($task['status']) && $task['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        
                        <!-- File Attachment (Optional) -->
                        <div class="col-12 mb-3">
                            <label class="form-label">
                                <i class="bi bi-paperclip me-1"></i>Attachment 
                                <small class="text-muted">(Optional - PDF, CSV, XLS, XLSX, DOC, DOCX - Max 10MB)</small>
                            </label>
                            <?php if (isset($task) && !empty($task['attachment'])): ?>
                            <div class="alert alert-info py-2 mb-2">
                                <i class="bi bi-file-earmark me-1"></i>Current file: 
                                <a href="<?php echo APP_URL . '/uploads/tasks/' . htmlspecialchars($task['attachment']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($task['attachment']); ?>
                                </a>
                                <small class="text-muted ms-2">(Upload new file to replace)</small>
                            </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" name="task_attachment" 
                                   accept=".pdf,.csv,.xls,.xlsx,.doc,.docx">
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i><?php echo $action === 'add' ? 'Assign Task' : 'Update Task'; ?>
                        </button>
                        <a href="team-tasks" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <style>
        .member-checkbox-card {
            display: block;
            cursor: pointer;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.2s;
            background: #fff;
        }
        .member-checkbox-card:hover {
            border-color: #0d6efd;
            background: #f8f9ff;
        }
        .member-checkbox-card input { display: none; }
        .member-checkbox-card .check-icon { display: none; }
        .member-checkbox-card input:checked + .card-body {
            background: #e8f4ff;
            border-radius: 6px;
        }
        .member-checkbox-card input:checked + .card-body .check-icon { display: block; }
        </style>
        
        <script>
        function preventDoubleSubmit(form) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn.disabled) {
                return false; // Already submitting
            }
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
            return true;
        }
        
        function toggleAllMembers(select) {
            document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = select);
            updateMemberCount();
        }
        function updateMemberCount() {
            const count = document.querySelectorAll('.member-checkbox:checked').length;
            document.getElementById('memberCount').textContent = count + ' selected';
        }
        </script>
        
        <?php else: ?>
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="not_started" <?php echo ($_GET['status'] ?? '') === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                            <option value="in_progress" <?php echo ($_GET['status'] ?? '') === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="submitted" <?php echo ($_GET['status'] ?? '') === 'submitted' ? 'selected' : ''; ?>>Submitted (Needs Review)</option>
                            <option value="completed" <?php echo ($_GET['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tasks List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-task me-2"></i>Team Tasks</h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($groupedTasks) > 0): ?>
                <div class="accordion accordion-flush" id="tasksAccordion">
                    <?php $taskIndex = 0; foreach ($groupedTasks as $taskKey => $taskGroup): $taskIndex++; ?>
                    <?php 
                        // Calculate overall status
                        $totalAssignees = count($taskGroup['assignees']);
                        $completedCount = count(array_filter($taskGroup['assignees'], fn($a) => $a['status'] === 'completed'));
                        $submittedCount = count(array_filter($taskGroup['assignees'], fn($a) => $a['status'] === 'submitted'));
                        $inProgressCount = count(array_filter($taskGroup['assignees'], fn($a) => $a['status'] === 'in_progress'));
                        $changesCount = count(array_filter($taskGroup['assignees'], fn($a) => $a['status'] === 'changes_requested'));
                        
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
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            <i class="bi bi-people me-1"></i><?php echo $totalAssignees; ?> assignee(s) • 
                                            <span class="badge bg-<?php echo $taskGroup['priority'] === 'urgent' ? 'danger' : ($taskGroup['priority'] === 'high' ? 'warning' : ($taskGroup['priority'] === 'medium' ? 'info' : 'secondary')); ?> badge-sm">
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
                                    <p class="mb-0"><?php echo htmlspecialchars($taskGroup['description']); ?></p>
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
                                            <tr class="<?php echo $assignee['status'] === 'submitted' ? 'table-info' : ''; ?>">
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="bi bi-person-circle text-muted"></i>
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
                                                            'changes_requested' => 'Changes Needed'
                                                        ];
                                                        echo $statusLabels[$assignee['status']] ?? ucfirst($assignee['status']);
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($assignee['work_link']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success view-work-btn" 
                                                            data-title="<?php echo htmlspecialchars($taskGroup['title'], ENT_QUOTES); ?>"
                                                            data-employee="<?php echo htmlspecialchars($assignee['employee_name'], ENT_QUOTES); ?>"
                                                            data-empid="<?php echo htmlspecialchars($assignee['emp_id'], ENT_QUOTES); ?>"
                                                            data-link="<?php echo htmlspecialchars($assignee['work_link'], ENT_QUOTES); ?>"
                                                            data-linktype="<?php echo htmlspecialchars($assignee['work_link_type'] ?? 'url', ENT_QUOTES); ?>"
                                                            data-description="<?php echo htmlspecialchars($taskGroup['description'] ?? '', ENT_QUOTES); ?>"
                                                            data-deadline="<?php echo $taskGroup['deadline'] ? formatDate($taskGroup['deadline']) : 'No deadline'; ?>"
                                                            data-priority="<?php echo ucfirst($taskGroup['priority']); ?>">
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
                                                    <button class="btn btn-success btn-sm" onclick="reviewTask(<?php echo $assignee['id']; ?>, 'approve', '<?php echo addslashes($taskGroup['title']); ?>')">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button class="btn btn-warning btn-sm" onclick="showFeedbackModal(<?php echo $assignee['id']; ?>, '<?php echo addslashes($taskGroup['title']); ?>')">
                                                        <i class="bi bi-chat-left-text"></i>
                                                    </button>
                                                    <?php else: ?>
                                                    <a href="?action=edit&id=<?php echo $assignee['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Remove this employee from task?')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $assignee['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Add Team Member to Task Section -->
                                <div class="mt-4 pt-3 border-top">
                                    <h6 class="mb-3"><i class="bi bi-person-plus me-2"></i>Add Team Member to Task</h6>
                                    <form method="POST" id="addEmpForm_<?php echo $taskIndex; ?>" class="row g-3">
                                        <input type="hidden" name="action" value="add_employee_to_task">
                                        <input type="hidden" name="task_group_key" value="<?php echo $taskKey; ?>">
                                        
                                        <div class="col-12">
                                            <div class="d-flex gap-2 mb-2 flex-wrap">
                                                <input type="text" class="form-control" id="empSearch_<?php echo $taskIndex; ?>" placeholder="Search team members..." style="max-width: 250px;">
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
                                                    foreach ($teamMembers as $member):
                                                        if (!in_array($member['id'], $existingIds)):
                                                    ?>
                                                    <div class="col-12 col-sm-6 col-lg-4 emp-item-add-<?php echo $taskIndex; ?>" data-name="<?php echo strtolower($member['name']); ?>">
                                                        <label class="emp-checkbox-card">
                                                            <input type="checkbox" name="new_assigned_to[]" value="<?php echo $member['id']; ?>" 
                                                                   class="emp-checkbox-add-<?php echo $taskIndex; ?>" onchange="updateEmpCountAdd(<?php echo $taskIndex; ?>)">
                                                            <div class="card-body d-flex align-items-center gap-2 p-2">
                                                                <i class="bi bi-person-circle fs-5 text-muted"></i>
                                                                <div class="flex-grow-1 min-w-0">
                                                                    <div class="fw-medium text-truncate"><?php echo $member['name']; ?></div>
                                                                    <small class="text-muted"><?php echo $member['employee_id']; ?></small>
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
                                                <i class="bi bi-plus-lg me-1"></i>Add Selected Member(s)
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
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-send me-1"></i>Send Feedback
                    </button>
                </div>
            </form>
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

.accordion-button:not(.collapsed) {
    background-color: #f8f9fa;
    color: #212529;
}
.accordion-button:focus {
    box-shadow: none;
    border-color: rgba(0,0,0,.125);
}
.accordion-item {
    border-left: 4px solid #0d6efd;
    margin-bottom: 0.5rem;
    border-radius: 0.5rem !important;
    overflow: hidden;
}
.accordion-item:has(.table-info) {
    border-left-color: #0dcaf0;
}
.badge-sm {
    font-size: 0.7rem;
    padding: 0.2em 0.5em;
}
</style>

<script>
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
    
    // Link type badge
    const linkTypeLabels = {
        'url': 'Website URL',
        'github': 'GitHub Repository',
        'drive': 'Google Drive',
        'dropbox': 'Dropbox',
        'other': 'Other Link'
    };
    document.getElementById('workLinkType').textContent = linkTypeLabels[linkType] || linkType;
    
    // Priority badge
    const priorityEl = document.getElementById('workPriority');
    priorityEl.textContent = priority;
    priorityEl.className = 'badge';
    if (priority.toLowerCase() === 'urgent') priorityEl.classList.add('bg-danger');
    else if (priority.toLowerCase() === 'high') priorityEl.classList.add('bg-warning', 'text-dark');
    else if (priority.toLowerCase() === 'medium') priorityEl.classList.add('bg-info');
    else priorityEl.classList.add('bg-secondary');
    
    // Description
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

// Add event listener for view work buttons using data attributes
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-work-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            showWorkModal(
                this.dataset.title,
                this.dataset.employee,
                this.dataset.empid,
                this.dataset.link,
                this.dataset.linktype,
                this.dataset.description,
                this.dataset.deadline,
                this.dataset.priority
            );
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
