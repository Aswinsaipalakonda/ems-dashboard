<?php
/**
 * Employee Management
 * Employee Management System
 */

$pageTitle = 'Employees';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$message = '';
$messageType = '';

// Designation codes mapping
$designationCodes = [
    'Intern' => 'INT',
    'Trainee' => 'TRN',
    'Junior' => 'JR',
    'Senior' => 'SR',
    'Team Lead' => 'TL',
    'Manager' => 'MGR',
    'Director' => 'DIR',
    'Employee' => 'EMP'
];

/**
 * Generate Employee ID based on domain and designation
 * Format: [DOMAIN_CODE]-[DESIGNATION_CODE]-[NUMBER]
 * Example: WD-INT-001
 */
function generateEmployeeId($domainId, $designation) {
    global $designationCodes;
    
    // Get domain code
    $domain = fetchOne("SELECT code FROM domains WHERE id = ?", "i", [$domainId]);
    $domainCode = $domain ? $domain['code'] : 'GEN';
    
    // Get designation code
    $desigCode = 'EMP';
    foreach ($designationCodes as $key => $code) {
        if (stripos($designation, $key) !== false) {
            $desigCode = $code;
            break;
        }
    }
    
    // Get next employee number (global counter across all domains)
    $lastEmp = fetchOne("SELECT employee_id FROM employees ORDER BY id DESC LIMIT 1");
    if ($lastEmp && preg_match('/(\d+)$/', $lastEmp['employee_id'], $matches)) {
        $nextNum = intval($matches[1]) + 1;
    } else {
        $nextNum = 1;
    }
    
    return $domainCode . '-' . $desigCode . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $postAction = $_POST['action'] ?? '';
        
        if ($postAction === 'add' || $postAction === 'edit') {
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $domainId = intval($_POST['domain_id'] ?? 0);
            $designation = sanitize($_POST['designation'] ?? '');
            $dateOfJoining = $_POST['date_of_joining'] ?? null;
            $status = sanitize($_POST['status'] ?? 'active');
            $password = $_POST['password'] ?? '';
            $roleId = !empty($_POST['role_id']) ? intval($_POST['role_id']) : null;
            $teamLeadId = !empty($_POST['team_lead_id']) ? intval($_POST['team_lead_id']) : null;
            $requiresCheckin = isset($_POST['requires_checkin']) ? 1 : 0;
        
        if (empty($name) || empty($email)) {
            $message = 'Name and Email are required!';
            $messageType = 'danger';
        } elseif ($postAction === 'add' && $domainId == 0) {
            $message = 'Please select a domain!';
            $messageType = 'danger';
        } else {
            if ($postAction === 'add') {
                // Check if email exists
                $exists = fetchOne("SELECT id FROM employees WHERE email = ?", "s", [$email]);
                if ($exists) {
                    $message = 'Email already exists!';
                    $messageType = 'danger';
                } else {
                    // Generate employee ID
                    $employeeId = generateEmployeeId($domainId, $designation);
                    
                    // Hash password
                    $hashedPassword = password_hash($password ?: 'password123', PASSWORD_DEFAULT);
                    
                    $sql = "INSERT INTO employees (employee_id, name, email, password, phone, domain_id, designation, date_of_joining, status, role_id, team_lead_id, requires_checkin) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    executeQuery($sql, "sssssisssiii", [$employeeId, $name, $email, $hashedPassword, $phone, $domainId, $designation, $dateOfJoining, $status, $roleId, $teamLeadId, $requiresCheckin]);
                    
                    // Create welcome notification
                    $empId = lastInsertId();
                    executeQuery(
                        "INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'employee', 'Welcome!', 'Welcome to the team! Your Employee ID is: $employeeId', 'success')",
                        "i",
                        [$empId]
                    );
                    
                    $message = "Employee added successfully! Employee ID: <strong>$employeeId</strong>";
                    $messageType = 'success';
                    $action = '';
                }
            } else {
                // Edit employee
                $editId = intval($_POST['id']);
                
                // Check if email exists for other users
                $exists = fetchOne("SELECT id FROM employees WHERE email = ? AND id != ?", "si", [$email, $editId]);
                if ($exists) {
                    $message = 'Email already exists!';
                    $messageType = 'danger';
                } else {
                    if (!empty($password)) {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "UPDATE employees SET name = ?, email = ?, password = ?, phone = ?, domain_id = ?, designation = ?, date_of_joining = ?, status = ?, role_id = ?, team_lead_id = ?, requires_checkin = ? WHERE id = ?";
                        executeQuery($sql, "ssssisssiiii", [$name, $email, $hashedPassword, $phone, $domainId, $designation, $dateOfJoining, $status, $roleId, $teamLeadId, $requiresCheckin, $editId]);
                    } else {
                        $sql = "UPDATE employees SET name = ?, email = ?, phone = ?, domain_id = ?, designation = ?, date_of_joining = ?, status = ?, role_id = ?, team_lead_id = ?, requires_checkin = ? WHERE id = ?";
                        executeQuery($sql, "ssissssiiii", [$name, $email, $phone, $domainId, $designation, $dateOfJoining, $status, $roleId, $teamLeadId, $requiresCheckin, $editId]);
                    }
                    
                    $message = 'Employee updated successfully!';
                    $messageType = 'success';
                    $action = '';
                }
            }
        }
    } elseif ($postAction === 'delete') {
        $deleteId = intval($_POST['id']);
        executeQuery("DELETE FROM employees WHERE id = ?", "i", [$deleteId]);
        $message = 'Employee deleted successfully!';
        $messageType = 'success';
    }    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
        error_log('Employee operation error: ' . $e->getMessage());
    }}

// Get employee for editing
$employee = null;
if ($action === 'edit' && $id) {
    $employee = fetchOne("SELECT * FROM employees WHERE id = ?", "i", [$id]);
}

// Get all domains for dropdown
$domains = fetchAll("SELECT * FROM domains WHERE status = 'active' ORDER BY name");

// Get all roles for dropdown
$roles = fetchAll("SELECT * FROM roles WHERE status = 'active' ORDER BY id");

// Get all team leads for dropdown
$teamLeads = fetchAll("SELECT id, name, employee_id FROM employees WHERE designation IN ('Team Lead', 'Manager') OR role_id IN (SELECT id FROM roles WHERE slug IN ('team_lead', 'manager')) ORDER BY name");

// Get all employees
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$domainFilter = $_GET['domain'] ?? '';

$whereClause = "WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $whereClause .= " AND (e.name LIKE ? OR e.email LIKE ? OR e.employee_id LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= "sss";
}

if ($statusFilter) {
    $whereClause .= " AND e.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if ($domainFilter) {
    $whereClause .= " AND e.domain_id = ?";
    $params[] = $domainFilter;
    $types .= "i";
}

$employees = fetchAll(
    "SELECT e.*, d.name as domain_name, d.code as domain_code, r.name as role_name, r.slug as role_slug,
            tl.name as team_lead_name
     FROM employees e 
     LEFT JOIN domains d ON e.domain_id = d.id 
     LEFT JOIN roles r ON e.role_id = r.id
     LEFT JOIN employees tl ON e.team_lead_id = tl.id
     $whereClause 
     ORDER BY e.id ASC", 
    $types, 
    $params
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="page-title">Employees</h1>
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
    
    <!-- Content -->
    <div class="content-wrapper">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'x-circle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit Form -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-<?php echo $action === 'add' ? 'person-plus' : 'pencil'; ?> me-2"></i>
                    <?php echo $action === 'add' ? 'Add New Employee' : 'Edit Employee'; ?>
                </h5>
                <a href="<?php echo APP_URL; ?>/admin/employees.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
            <div class="card-body">
                <?php if ($action === 'add' && empty($domains)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>No domains found!</strong> Please <a href="<?php echo APP_URL; ?>/admin/domains.php">add domains</a> first before creating employees.
                </div>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $action; ?>">
                    <?php if ($employee): ?>
                    <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">
                    <?php endif; ?>
                    
                    <?php if ($action === 'add'): ?>
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-info-circle me-2"></i>
                        Employee ID will be auto-generated based on the selected <strong>Domain</strong> and <strong>Designation</strong>.
                        <br><small>Format: [DOMAIN_CODE]-[DESIGNATION_CODE]-[NUMBER] (e.g., WD-INT-001)</small>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-secondary mb-4">
                        <strong>Employee ID:</strong> <?php echo $employee['employee_id']; ?>
                        <small class="text-muted ms-2">(Cannot be changed)</small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required 
                                   value="<?php echo $employee['name'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required 
                                   value="<?php echo $employee['email'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" 
                                   value="<?php echo $employee['phone'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <?php echo $action === 'add' ? '' : '(Leave blank to keep current)'; ?></label>
                            <input type="password" class="form-control" name="password">
                            <?php if ($action === 'add'): ?>
                            <small class="text-muted">Default: password123</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Domain <span class="text-danger">*</span></label>
                            <select class="form-select" name="domain_id" id="domainSelect" required>
                                <option value="">Select Domain</option>
                                <?php foreach ($domains as $domain): ?>
                                <option value="<?php echo $domain['id']; ?>" 
                                        data-code="<?php echo $domain['code']; ?>"
                                        <?php echo ($employee['domain_id'] ?? '') == $domain['id'] ? 'selected' : ''; ?>>
                                    <?php echo $domain['name']; ?> (<?php echo $domain['code']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">
                                <a href="<?php echo APP_URL; ?>/admin/domains.php" target="_blank">Manage Domains</a>
                            </small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Designation <span class="text-danger">*</span></label>
                            <select class="form-select" name="designation" id="designationSelect" required>
                                <option value="">Select Designation</option>
                                <option value="Intern" <?php echo ($employee['designation'] ?? '') === 'Intern' ? 'selected' : ''; ?>>Intern (INT)</option>
                                <option value="Trainee" <?php echo ($employee['designation'] ?? '') === 'Trainee' ? 'selected' : ''; ?>>Trainee (TRN)</option>
                                <option value="Junior" <?php echo ($employee['designation'] ?? '') === 'Junior' ? 'selected' : ''; ?>>Junior (JR)</option>
                                <option value="Employee" <?php echo ($employee['designation'] ?? '') === 'Employee' ? 'selected' : ''; ?>>Employee (EMP)</option>
                                <option value="Senior" <?php echo ($employee['designation'] ?? '') === 'Senior' ? 'selected' : ''; ?>>Senior (SR)</option>
                                <option value="Team Lead" <?php echo ($employee['designation'] ?? '') === 'Team Lead' ? 'selected' : ''; ?>>Team Lead (TL)</option>
                                <option value="Manager" <?php echo ($employee['designation'] ?? '') === 'Manager' ? 'selected' : ''; ?>>Manager (MGR)</option>
                                <option value="Director" <?php echo ($employee['designation'] ?? '') === 'Director' ? 'selected' : ''; ?>>Director (DIR)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Joining</label>
                            <input type="date" class="form-control" name="date_of_joining" 
                                   value="<?php echo $employee['date_of_joining'] ?? date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?php echo ($employee['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($employee['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="terminated" <?php echo ($employee['status'] ?? '') === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role_id">
                                <option value="">Regular Employee</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" <?php echo ($employee['role_id'] ?? '') == $role['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Assign special roles like Manager, HR, Team Lead</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Team Lead / Manager</label>
                            <select class="form-select" name="team_lead_id">
                                <option value="">No Team Lead Assigned</option>
                                <?php foreach ($teamLeads as $lead): ?>
                                <?php if (($employee['id'] ?? 0) != $lead['id']): ?>
                                <option value="<?php echo $lead['id']; ?>" <?php echo ($employee['team_lead_id'] ?? '') == $lead['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lead['name']); ?> (<?php echo $lead['employee_id']; ?>)
                                </option>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Assign a team lead for this employee</small>
                        </div>
                        
                        <!-- Requires Check-in Toggle -->
                        <div class="col-12 mb-3">
                            <div class="card bg-light border-0">
                                <div class="card-body py-3">
                                    <div class="form-check form-switch d-flex align-items-center gap-3">
                                        <input class="form-check-input" type="checkbox" role="switch" 
                                               id="requiresCheckin" name="requires_checkin" 
                                               style="width: 3em; height: 1.5em;"
                                               <?php echo ($employee['requires_checkin'] ?? 1) == 1 ? 'checked' : ''; ?>>
                                        <div>
                                            <label class="form-check-label fw-bold mb-0" for="requiresCheckin">
                                                <i class="bi bi-camera-video me-1"></i>
                                                Requires Check-in / Check-out
                                            </label>
                                            <div class="text-muted small">
                                                Enable this if the employee needs to track attendance via check-in and check-out. 
                                                When disabled, attendance features will be hidden from their dashboard.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($action === 'add'): ?>
                    <div class="alert alert-light border mt-3" id="previewId" style="display: none;">
                        <strong>Preview Employee ID:</strong> <span id="previewIdValue" class="badge bg-primary fs-6"></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i><?php echo $action === 'add' ? 'Add Employee' : 'Update Employee'; ?>
                        </button>
                        <a href="<?php echo APP_URL; ?>/admin/employees.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($action === 'add'): ?>
        <script>
        // Preview Employee ID
        const designationCodes = {
            'Intern': 'INT',
            'Trainee': 'TRN',
            'Junior': 'JR',
            'Senior': 'SR',
            'Team Lead': 'TL',
            'Manager': 'MGR',
            'Director': 'DIR'
        };
        
        function updatePreview() {
            const domainSelect = document.getElementById('domainSelect');
            const designationSelect = document.getElementById('designationSelect');
            const previewDiv = document.getElementById('previewId');
            const previewValue = document.getElementById('previewIdValue');
            
            const domainCode = domainSelect.options[domainSelect.selectedIndex]?.dataset?.code || '';
            const designation = designationSelect.value;
            const desigCode = designationCodes[designation] || 'EMP';
            
            if (domainCode && designation) {
                previewValue.textContent = domainCode + '-' + desigCode + '-XXX';
                previewDiv.style.display = 'block';
            } else {
                previewDiv.style.display = 'none';
            }
        }
        
        document.getElementById('domainSelect').addEventListener('change', updatePreview);
        document.getElementById('designationSelect').addEventListener('change', updatePreview);
        </script>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- Employee List -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-6 mb-2 mb-md-0">
                        <h5 class="mb-0"><i class="bi bi-people me-2"></i>All Employees (<?php echo count($employees); ?>)</h5>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <a href="<?php echo APP_URL; ?>/admin/domains.php" class="btn btn-outline-primary me-2">
                            <i class="bi bi-grid me-1"></i>Manage Domains
                        </a>
                        <a href="<?php echo APP_URL; ?>/admin/employees.php?action=add" class="btn btn-primary btn-glow">
                            <i class="bi bi-person-plus me-2"></i>Add Employee
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <form class="row g-3 mb-4" method="GET">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" placeholder="Search by name, email, or ID" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="domain">
                            <option value="">All Domains</option>
                            <?php foreach ($domains as $domain): ?>
                            <option value="<?php echo $domain['id']; ?>" <?php echo $domainFilter == $domain['id'] ? 'selected' : ''; ?>>
                                <?php echo $domain['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="terminated" <?php echo $statusFilter === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-search me-1"></i>Filter
                        </button>
                    </div>
                    <?php if ($search || $statusFilter || $domainFilter): ?>
                    <div class="col-md-2">
                        <a href="<?php echo APP_URL; ?>/admin/employees.php" class="btn btn-outline-secondary w-100">Clear</a>
                    </div>
                    <?php endif; ?>
                </form>
                
                <?php if (count($employees) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Employee ID</th>
                                <th>Domain</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Team Lead</th>
                                <th class="text-center" title="Check-in Required"><i class="bi bi-camera-video"></i></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo getAvatar($emp['avatar']); ?>" class="avatar avatar-sm me-2" alt="">
                                        <div>
                                            <div class="fw-semibold"><?php echo $emp['name']; ?></div>
                                            <small class="text-muted"><?php echo $emp['email']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-dark"><?php echo $emp['employee_id']; ?></span></td>
                                <td>
                                    <?php if ($emp['domain_name']): ?>
                                    <span class="badge bg-primary"><?php echo $emp['domain_code']; ?></span>
                                    <small class="text-muted d-block"><?php echo $emp['domain_name']; ?></small>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($emp['role_name']): ?>
                                    <span class="badge bg-<?php 
                                        echo match($emp['role_slug']) {
                                            'super_admin', 'admin' => 'danger',
                                            'manager' => 'warning',
                                            'hr' => 'info',
                                            'team_lead' => 'success',
                                            default => 'secondary'
                                        };
                                    ?>"><?php echo $emp['role_name']; ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-light text-dark"><?php echo $emp['designation'] ?? 'Employee'; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-<?php echo $emp['status']; ?>"><?php echo ucfirst($emp['status']); ?></span></td>
                                <td>
                                    <?php if ($emp['team_lead_name']): ?>
                                    <small><?php echo $emp['team_lead_name']; ?></small>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($emp['requires_checkin']): ?>
                                    <span class="badge bg-success" title="Check-in Required"><i class="bi bi-camera-video"></i></span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary" title="No Check-in"><i class="bi bi-camera-video-off"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?php echo APP_URL; ?>/admin/employees.php?action=edit&id=<?php echo $emp['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                                onclick="deleteEmployee(<?php echo $emp['id']; ?>, '<?php echo $emp['name']; ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-people"></i>
                    <h4>No Employees Found</h4>
                    <p>
                        <?php if ($search || $statusFilter || $domainFilter): ?>
                            No employees match your search criteria.
                        <?php else: ?>
                            Get started by adding your first employee.
                        <?php endif; ?>
                    </p>
                    <?php if (!$search && !$statusFilter && !$domainFilter): ?>
                    <a href="<?php echo APP_URL; ?>/admin/employees.php?action=add" class="btn btn-primary btn-glow">
                        <i class="bi bi-person-plus me-2"></i>Add Employee
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
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
                <p>Are you sure you want to delete <strong id="deleteEmployeeName"></strong>?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteEmployeeId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteEmployee(id, name) {
    document.getElementById('deleteEmployeeId').value = id;
    document.getElementById('deleteEmployeeName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
