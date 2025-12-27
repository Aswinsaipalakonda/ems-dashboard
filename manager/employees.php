<?php
/**
 * Manager - Employee View
 * Employee Management System
 */

$pageTitle = 'Employees';
require_once __DIR__ . '/../config/config.php';
requireLogin();

// Check if user has management access
if (!isManager() && !isHR()) {
    header("Location: " . url("employee/dashboard"));
    exit;
}

$message = '';
$messageType = '';

// HR can add/edit employees
if (isHR() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $domainId = intval($_POST['domain_id'] ?? 0);
        $designation = sanitize($_POST['designation'] ?? '');
        $roleId = intval($_POST['role_id'] ?? 5);
        $teamLeadId = intval($_POST['team_lead_id'] ?? 0) ?: null;
        $dateOfJoining = $_POST['date_of_joining'] ?? null;
        $status = sanitize($_POST['status'] ?? 'active');
        $password = $_POST['password'] ?? '';
        
        if (empty($name) || empty($email)) {
            $message = 'Name and Email are required!';
            $messageType = 'danger';
        } elseif ($action === 'add' && $domainId == 0) {
            $message = 'Please select a domain!';
            $messageType = 'danger';
        } else {
            if ($action === 'add') {
                // Check if email exists
                $exists = fetchOne("SELECT id FROM employees WHERE email = ?", "s", [$email]);
                if ($exists) {
                    $message = 'Email already exists!';
                    $messageType = 'danger';
                } else {
                    // Generate employee ID
                    $domain = fetchOne("SELECT code FROM domains WHERE id = ?", "i", [$domainId]);
                    $domainCode = $domain ? $domain['code'] : 'GEN';
                    $desigCodes = ['Intern' => 'INT', 'Trainee' => 'TRN', 'Junior' => 'JR', 'Senior' => 'SR', 'Team Lead' => 'TL', 'Manager' => 'MGR', 'Director' => 'DIR', 'Employee' => 'EMP'];
                    $desigCode = 'EMP';
                    foreach ($desigCodes as $key => $code) {
                        if (stripos($designation, $key) !== false) {
                            $desigCode = $code;
                            break;
                        }
                    }
                    $lastEmp = fetchOne("SELECT employee_id FROM employees ORDER BY id DESC LIMIT 1");
                    $nextNum = 1;
                    if ($lastEmp && preg_match('/(\d+)$/', $lastEmp['employee_id'], $matches)) {
                        $nextNum = intval($matches[1]) + 1;
                    }
                    $employeeId = $domainCode . '-' . $desigCode . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                    
                    $hashedPassword = password_hash($password ?: 'password123', PASSWORD_DEFAULT);
                    
                    $sql = "INSERT INTO employees (employee_id, name, email, password, phone, domain_id, designation, role_id, team_lead_id, date_of_joining, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    executeQuery($sql, "sssssisisis", [$employeeId, $name, $email, $hashedPassword, $phone, $domainId, $designation, $roleId, $teamLeadId, $dateOfJoining, $status]);
                    
                    $message = "Employee added successfully! Employee ID: <strong>$employeeId</strong>";
                    $messageType = 'success';
                }
            } else {
                $editId = intval($_POST['id']);
                $exists = fetchOne("SELECT id FROM employees WHERE email = ? AND id != ?", "si", [$email, $editId]);
                if ($exists) {
                    $message = 'Email already exists!';
                    $messageType = 'danger';
                } else {
                    if (!empty($password)) {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "UPDATE employees SET name = ?, email = ?, password = ?, phone = ?, domain_id = ?, designation = ?, role_id = ?, team_lead_id = ?, date_of_joining = ?, status = ? WHERE id = ?";
                        executeQuery($sql, "ssssissisis", [$name, $email, $hashedPassword, $phone, $domainId, $designation, $roleId, $teamLeadId, $dateOfJoining, $status, $editId]);
                    } else {
                        $sql = "UPDATE employees SET name = ?, email = ?, phone = ?, domain_id = ?, designation = ?, role_id = ?, team_lead_id = ?, date_of_joining = ?, status = ? WHERE id = ?";
                        executeQuery($sql, "ssissisisi", [$name, $email, $phone, $domainId, $designation, $roleId, $teamLeadId, $dateOfJoining, $status, $editId]);
                    }
                    $message = 'Employee updated successfully!';
                    $messageType = 'success';
                }
            }
        }
    }
}

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Get employee for editing
$employee = null;
if ($action === 'edit' && $id && isHR()) {
    $employee = fetchOne("SELECT * FROM employees WHERE id = ?", "i", [$id]);
}

// Get all domains and roles for dropdowns
$domains = fetchAll("SELECT * FROM domains WHERE status = 'active' ORDER BY name");
$roles = fetchAll("SELECT * FROM roles ORDER BY id");
$teamLeads = fetchAll("SELECT e.id, e.name, e.employee_id FROM employees e JOIN roles r ON e.role_id = r.id WHERE r.slug = 'team_lead' AND e.status = 'active' ORDER BY e.name");

// Filters
$search = $_GET['search'] ?? '';
$domainFilter = $_GET['domain'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Build query
$whereClause = "WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $whereClause .= " AND (e.name LIKE ? OR e.email LIKE ? OR e.employee_id LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= "sss";
}
if ($domainFilter) {
    $whereClause .= " AND e.domain_id = ?";
    $params[] = $domainFilter;
    $types .= "i";
}
if ($statusFilter) {
    $whereClause .= " AND e.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$employees = fetchAll(
    "SELECT e.*, d.name as domain_name, r.name as role_name, tl.name as team_lead_name
     FROM employees e 
     LEFT JOIN domains d ON e.domain_id = d.id 
     LEFT JOIN roles r ON e.role_id = r.id
     LEFT JOIN employees tl ON e.team_lead_id = tl.id
     $whereClause 
     ORDER BY e.created_at DESC",
    $types,
    $params
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/manager-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Employees</h1>
        </div>
        <div class="header-right">
            <?php if (isHR()): ?>
            <a href="?action=add" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Add Employee
            </a>
            <?php endif; ?>
        </div>
    </header>

    <div class="content-wrapper">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (($action === 'add' || $action === 'edit') && isHR()): ?>
        <!-- Add/Edit Form -->
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><?php echo $action === 'add' ? 'Add New Employee' : 'Edit Employee'; ?></h5>
                <a href="employees" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $action; ?>">
                    <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" required value="<?php echo $employee['name'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required value="<?php echo $employee['email'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo $employee['phone'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Domain *</label>
                            <select name="domain_id" class="form-select" required>
                                <option value="">Select Domain</option>
                                <?php foreach ($domains as $domain): ?>
                                <option value="<?php echo $domain['id']; ?>" <?php echo (isset($employee['domain_id']) && $employee['domain_id'] == $domain['id']) ? 'selected' : ''; ?>>
                                    <?php echo $domain['name']; ?> (<?php echo $domain['code']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Designation</label>
                            <input type="text" name="designation" class="form-control" value="<?php echo $employee['designation'] ?? ''; ?>" placeholder="e.g. Junior Developer, Senior Analyst">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role_id" class="form-select" required>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" <?php echo (isset($employee['role_id']) && $employee['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                    <?php echo $role['name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Team Lead</label>
                            <select name="team_lead_id" class="form-select">
                                <option value="">No Team Lead</option>
                                <?php foreach ($teamLeads as $tl): ?>
                                <option value="<?php echo $tl['id']; ?>" <?php echo (isset($employee['team_lead_id']) && $employee['team_lead_id'] == $tl['id']) ? 'selected' : ''; ?>>
                                    <?php echo $tl['name']; ?> (<?php echo $tl['employee_id']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Joining</label>
                            <input type="date" name="date_of_joining" class="form-control" value="<?php echo $employee['date_of_joining'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <?php echo $action === 'add' ? '' : '(leave blank to keep current)'; ?></label>
                            <input type="password" name="password" class="form-control" <?php echo $action === 'add' ? '' : ''; ?>>
                            <?php if ($action === 'add'): ?>
                            <small class="text-muted">Default: password123</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo (isset($employee['status']) && $employee['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (isset($employee['status']) && $employee['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="terminated" <?php echo (isset($employee['status']) && $employee['status'] === 'terminated') ? 'selected' : ''; ?>>Terminated</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i><?php echo $action === 'add' ? 'Add Employee' : 'Update Employee'; ?>
                        </button>
                        <a href="employees" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search name, email, ID..." value="<?php echo $search; ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="domain" class="form-select">
                            <option value="">All Domains</option>
                            <?php foreach ($domains as $domain): ?>
                            <option value="<?php echo $domain['id']; ?>" <?php echo $domainFilter == $domain['id'] ? 'selected' : ''; ?>>
                                <?php echo $domain['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="terminated" <?php echo $statusFilter === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Employees Table -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Employee ID</th>
                                <th>Domain</th>
                                <th>Role</th>
                                <th>Team Lead</th>
                                <th>Status</th>
                                <?php if (isHR()): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($employees) > 0): ?>
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
                                <td><span class="badge bg-light text-dark"><?php echo $emp['employee_id']; ?></span></td>
                                <td><?php echo $emp['domain_name'] ?? 'N/A'; ?></td>
                                <td><span class="badge bg-info"><?php echo $emp['role_name'] ?? 'Employee'; ?></span></td>
                                <td><?php echo $emp['team_lead_name'] ?? '-'; ?></td>
                                <td><span class="badge badge-<?php echo $emp['status']; ?>"><?php echo ucfirst($emp['status']); ?></span></td>
                                <?php if (isHR()): ?>
                                <td>
                                    <a href="?action=edit&id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="<?php echo isHR() ? 7 : 6; ?>" class="text-center py-4 text-muted">
                                    No employees found
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
