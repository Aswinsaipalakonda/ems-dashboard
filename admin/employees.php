<?php
/**
 * Employee Management - SaaS Workforce Directory
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
                        $message = 'Email already exists in the workforce directory!';
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
                        
                        $message = "Employee registered successfully with ID: <strong>$employeeId</strong>";
                        $messageType = 'success';
                        $action = '';
                    }
                } else {
                    // Edit employee
                    $editId = intval($_POST['id']);
                    
                    // Check if email exists for other users
                    $exists = fetchOne("SELECT id FROM employees WHERE email = ? AND id != ?", "si", [$email, $editId]);
                    if ($exists) {
                        $message = 'Email already in use by another team member!';
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
                        
                        $message = 'Employee profile updated successfully!';
                        $messageType = 'success';
                        $action = '';
                    }
                }
            }
        } elseif ($postAction === 'delete') {
            $deleteId = intval($_POST['id']);
            executeQuery("DELETE FROM employees WHERE id = ?", "i", [$deleteId]);
            $message = 'Employee record removed from workforce system.';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
        error_log('Employee operation error: ' . $e->getMessage());
    }
}

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

// Calculate Directory SaaS Metrics
$totalStaff = fetchOne("SELECT COUNT(*) as count FROM employees")['count'] ?? 0;
$activeStaff = fetchOne("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")['count'] ?? 0;
$activeDomainsCount = fetchOne("SELECT COUNT(DISTINCT domain_id) as count FROM employees WHERE status = 'active' AND domain_id IS NOT NULL")['count'] ?? 0;
$checkinRequiredCount = fetchOne("SELECT COUNT(*) as count FROM employees WHERE requires_checkin = 1 AND status = 'active'")['count'] ?? 0;
$activeRate = $totalStaff > 0 ? round(($activeStaff / $totalStaff) * 100) : 100;
$checkinRate = $activeStaff > 0 ? round(($checkinRequiredCount / $activeStaff) * 100) : 0;

// Filter and Search logic
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
            <h1 class="page-title">Workforce Directory</h1>
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
    
    <!-- Content Wrapper -->
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
        <!-- SaaS Hero Header -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="saas-hero-content">
                        <h2 class="saas-hero-title">Workforce & Talent Directory</h2>
                        <p class="saas-hero-subtitle">
                            Monitor organizational hierarchy, domain deployments, role access credentials, and real-time attendance mandates across Clientura.
                        </p>
                        <div class="saas-hero-pills">
                            <span class="saas-pill live-pulse">
                                <span class="pulse-dot"></span>
                                <?php echo $activeStaff; ?> Active Members (<?php echo $activeRate; ?>%)
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('domains', '', 14); ?>
                                <?php echo count($domains); ?> Active Domains
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('clock', '', 14); ?>
                                <?php echo $checkinRequiredCount; ?> Check-in Enforced
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mt-3 mt-lg-0 text-lg-end">
                    <div class="saas-hero-actions">
                        <a href="<?php echo url('admin/domains'); ?>" class="btn btn-outline-light">
                            <?php echo pika_icon('domains', 'me-1', 16); ?>Manage Domains
                        </a>
                        <a href="<?php echo url('admin/employees'); ?>?action=add" class="btn btn-primary btn-glow">
                            <?php echo pika_icon('user-plus', 'me-1', 16); ?>Add Employee
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
                            <?php echo pika_icon('users', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active">Workforce</span>
                    </div>
                    <div class="metric-value"><?php echo $totalStaff; ?></div>
                    <div class="metric-label">Total Registered Staff</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar navy" style="width: 100%;"></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge emerald">
                            <?php echo pika_icon('check-circle', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active"><?php echo $activeRate; ?>% Rate</span>
                    </div>
                    <div class="metric-value"><?php echo $activeStaff; ?></div>
                    <div class="metric-label">Active Employees</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar emerald" style="width: <?php echo $activeRate; ?>%;"></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge amber">
                            <?php echo pika_icon('building', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill warning">Units</span>
                    </div>
                    <div class="metric-value"><?php echo $activeDomainsCount; ?></div>
                    <div class="metric-label">Assigned Departments</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar amber" style="width: 85%;"></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge sky">
                            <?php echo pika_icon('clock', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill sky"><?php echo $checkinRate; ?>% Policy</span>
                    </div>
                    <div class="metric-value"><?php echo $checkinRequiredCount; ?></div>
                    <div class="metric-label">Check-in Monitored</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar sky" style="width: <?php echo $checkinRate; ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search SaaS Toolbar -->
        <div class="saas-toolbar">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-lg-4 col-md-5">
                    <div class="saas-input-group">
                        <span class="saas-input-icon"><?php echo pika_icon('search', '', 16); ?></span>
                        <input type="text" class="saas-input" name="search" 
                               placeholder="Search by name, email, or employee ID..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-lg-3 col-md-3">
                    <select class="form-select saas-select" name="domain">
                        <option value="">All Domains & Teams</option>
                        <?php foreach ($domains as $d): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo $domainFilter == $d['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['code']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-2">
                    <select class="form-select saas-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="terminated" <?php echo $statusFilter === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <?php echo pika_icon('filter', '', 15); ?> Apply
                    </button>
                    <?php if ($search || $statusFilter || $domainFilter): ?>
                    <a href="<?php echo url('admin/employees'); ?>" class="btn btn-outline-secondary" title="Clear Filters">
                        <?php echo pika_icon('refresh', '', 15); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Employee Directory Table Card -->
        <div class="saas-card">
            <div class="saas-card-header">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="saas-card-title">
                        <?php echo pika_icon('users', 'text-primary', 20); ?>
                        Workforce Directory
                    </h5>
                    <span class="badge bg-light text-dark border px-2 py-1 rounded-pill small">
                        <?php echo count($employees); ?> Results
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="<?php echo url('admin/generate-offer'); ?>" class="btn btn-sm btn-outline-primary">
                        <?php echo pika_icon('offer-letter', 'me-1', 14); ?> Issue Offer
                    </a>
                    <a href="<?php echo url('admin/generate-certificate'); ?>" class="btn btn-sm btn-outline-primary">
                        <?php echo pika_icon('certificate', 'me-1', 14); ?> Issue Certificate
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <?php if (count($employees) > 0): ?>
                <table class="saas-table">
                    <thead>
                        <tr>
                            <th>Team Member</th>
                            <th>Employee ID</th>
                            <th>Domain / Dept</th>
                            <th>Role & Access</th>
                            <th>Status</th>
                            <th>Team Lead</th>
                            <th class="text-center">Check-in</th>
                            <th class="text-end">Quick Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): 
                            $initials = strtoupper(substr($emp['name'], 0, 1) . (strpos($emp['name'], ' ') !== false ? substr($emp['name'], strpos($emp['name'], ' ') + 1, 1) : ''));
                        ?>
                        <tr>
                            <td>
                                <div class="saas-user-cell">
                                    <?php if (!empty($emp['avatar'])): ?>
                                    <img src="<?php echo getAvatar($emp['avatar']); ?>" class="saas-avatar" alt="">
                                    <?php else: ?>
                                    <div class="saas-avatar-initials"><?php echo $initials ?: 'EM'; ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="saas-user-name"><?php echo htmlspecialchars($emp['name']); ?></div>
                                        <div class="saas-user-sub"><?php echo htmlspecialchars($emp['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="saas-id-badge"><?php echo htmlspecialchars($emp['employee_id']); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($emp['domain_name'])): ?>
                                <span class="saas-tag primary">
                                    <?php echo pika_icon('building', '', 12); ?>
                                    <?php echo htmlspecialchars($emp['domain_name']); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted small">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($emp['role_name'])): ?>
                                <span class="saas-tag <?php 
                                    echo match($emp['role_slug']) {
                                        'super_admin', 'admin' => 'rose',
                                        'manager' => 'amber',
                                        'hr' => 'sky',
                                        'team_lead' => 'emerald',
                                        default => 'primary'
                                    };
                                ?>">
                                    <?php echo htmlspecialchars($emp['role_name']); ?>
                                </span>
                                <?php else: ?>
                                <span class="saas-tag primary"><?php echo htmlspecialchars($emp['designation'] ?: 'Employee'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($emp['status'] === 'active'): ?>
                                <span class="saas-tag emerald">
                                    <span class="pulse-dot" style="width: 6px; height: 6px; border-radius: 50%; background: #10B981; display: inline-block;"></span>
                                    Active
                                </span>
                                <?php elseif ($emp['status'] === 'inactive'): ?>
                                <span class="saas-tag amber">Inactive</span>
                                <?php else: ?>
                                <span class="saas-tag rose">Terminated</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($emp['team_lead_name'])): ?>
                                <div class="d-flex align-items-center gap-1 text-secondary small fw-medium">
                                    <?php echo pika_icon('user-check', 'text-muted', 14); ?>
                                    <?php echo htmlspecialchars($emp['team_lead_name']); ?>
                                </div>
                                <?php else: ?>
                                <span class="text-muted small">None</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($emp['requires_checkin']): ?>
                                <span class="badge bg-emerald-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small" title="Attendance check-in enforced">
                                    <?php echo pika_icon('check', 'text-success', 12); ?> Enforced
                                </span>
                                <?php else: ?>
                                <span class="badge bg-light text-muted border rounded-pill px-2 py-1 small" title="Attendance check-in exempted">
                                    Exempt
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?php echo url('admin/generate-offer'); ?>?employee_id=<?php echo $emp['id']; ?>" 
                                       class="saas-action-btn" title="Issue Offer Letter">
                                        <?php echo pika_icon('offer-letter', '', 15); ?>
                                    </a>
                                    <a href="<?php echo url('admin/generate-certificate'); ?>?employee_id=<?php echo $emp['id']; ?>" 
                                       class="saas-action-btn amber" title="Issue Certificate">
                                        <?php echo pika_icon('certificate', '', 15); ?>
                                    </a>
                                    <a href="<?php echo url('admin/employees'); ?>?action=edit&id=<?php echo $emp['id']; ?>" 
                                       class="saas-action-btn" title="Edit Member">
                                        <?php echo pika_icon('edit', '', 15); ?>
                                    </a>
                                    <button type="button" class="saas-action-btn delete" title="Remove Member"
                                            onclick="deleteEmployee(<?php echo $emp['id']; ?>, '<?php echo addslashes($emp['name']); ?>')">
                                        <?php echo pika_icon('trash', '', 15); ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3 text-muted">
                        <?php echo pika_icon('users', 'opacity-25', 64); ?>
                    </div>
                    <h5 class="fw-bold text-dark">No Employees Match Filter</h5>
                    <p class="text-muted small">Try broadening your search term or domain filters.</p>
                    <a href="<?php echo url('admin/employees'); ?>" class="btn btn-outline-secondary btn-sm">
                        <?php echo pika_icon('refresh', 'me-1', 14); ?> Reset Filters
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <!-- Add / Edit Employee SaaS Workspace -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="saas-card">
                    <div class="saas-card-header">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="saas-card-title">
                                <?php echo pika_icon($action === 'add' ? 'user-plus' : 'edit', 'text-primary', 20); ?>
                                <?php echo $action === 'add' ? 'Register New Team Member' : 'Edit Member Profile'; ?>
                            </h5>
                        </div>
                        <a href="<?php echo url('admin/employees'); ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i> Back to Directory
                        </a>
                    </div>
                    <div class="saas-card-body">
                        <?php if ($action === 'add' && empty($domains)): ?>
                        <div class="alert alert-warning border-0 rounded-4 p-3 d-flex gap-3 align-items-center mb-4">
                            <?php echo pika_icon('alert-triangle', 'text-warning', 24); ?>
                            <div>
                                <strong>No domains configured!</strong> Please <a href="<?php echo url('admin/domains'); ?>" class="fw-bold text-decoration-underline">add domains</a> first before registering team members.
                            </div>
                        </div>
                        <?php else: ?>
                        <form method="POST" id="employeeForm">
                            <input type="hidden" name="action" value="<?php echo $action; ?>">
                            <?php if ($employee): ?>
                            <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">
                            <?php endif; ?>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control rounded-pill px-3 py-2" name="name" id="empNameInput" 
                                           placeholder="e.g. Rahul Sharma" required 
                                           value="<?php echo htmlspecialchars($employee['name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control rounded-pill px-3 py-2" name="email" id="empEmailInput"
                                           placeholder="e.g. rahul@clientura.in" required 
                                           value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Phone Number</label>
                                    <input type="text" class="form-control rounded-pill px-3 py-2" name="phone" id="empPhoneInput"
                                           placeholder="+91 98765 43210"
                                           value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">
                                        Password <?php echo $action === 'add' ? '<span class="text-muted fw-normal">(Default: password123)</span>' : '<span class="text-muted fw-normal">(Leave blank to keep current)</span>'; ?>
                                    </label>
                                    <input type="password" class="form-control rounded-pill px-3 py-2" name="password" placeholder="••••••••">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Domain / Department <span class="text-danger">*</span></label>
                                    <select class="form-select rounded-pill px-3 py-2" name="domain_id" id="domainSelect" required>
                                        <option value="">Select Domain</option>
                                        <?php foreach ($domains as $d): ?>
                                        <option value="<?php echo $d['id']; ?>" 
                                                data-code="<?php echo htmlspecialchars($d['code']); ?>"
                                                data-name="<?php echo htmlspecialchars($d['name']); ?>"
                                                <?php echo ($employee['domain_id'] ?? '') == $d['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['code']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Designation <span class="text-danger">*</span></label>
                                    <select class="form-select rounded-pill px-3 py-2" name="designation" id="designationSelect" required>
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

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Date of Joining</label>
                                    <input type="date" class="form-control rounded-pill px-3 py-2" name="date_of_joining" 
                                           value="<?php echo htmlspecialchars($employee['date_of_joining'] ?? date('Y-m-d')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Account Status</label>
                                    <select class="form-select rounded-pill px-3 py-2" name="status" id="empStatusSelect">
                                        <option value="active" <?php echo ($employee['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($employee['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="terminated" <?php echo ($employee['status'] ?? '') === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">RBAC System Role</label>
                                    <select class="form-select rounded-pill px-3 py-2" name="role_id">
                                        <option value="">Standard Employee Access</option>
                                        <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo $r['id']; ?>" <?php echo ($employee['role_id'] ?? '') == $r['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($r['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Reporting Team Lead / Manager</label>
                                    <select class="form-select rounded-pill px-3 py-2" name="team_lead_id">
                                        <option value="">No Direct Lead Assigned</option>
                                        <?php foreach ($teamLeads as $tl): ?>
                                        <?php if (($employee['id'] ?? 0) != $tl['id']): ?>
                                        <option value="<?php echo $tl['id']; ?>" <?php echo ($employee['team_lead_id'] ?? '') == $tl['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tl['name']); ?> (<?php echo htmlspecialchars($tl['employee_id']); ?>)
                                        </option>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Attendance Policy Toggle Card -->
                                <div class="col-12 mt-3">
                                    <div class="p-3 rounded-4 bg-light border d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="metric-icon-badge navy" style="width: 42px; height: 42px;">
                                                <?php echo pika_icon('clock', '', 20); ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold text-dark">Requires Geofenced Check-In & Attendance</h6>
                                                <small class="text-muted">Enforces daily camera & GPS location logging for this employee.</small>
                                            </div>
                                        </div>
                                        <div class="form-check form-switch m-0">
                                            <input class="form-check-input" type="checkbox" role="switch" 
                                                   id="requiresCheckin" name="requires_checkin" 
                                                   style="width: 2.8em; height: 1.4em;"
                                                   <?php echo ($employee['requires_checkin'] ?? 1) == 1 ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4 pt-2">
                                <button type="submit" class="btn btn-primary btn-glow px-4">
                                    <?php echo pika_icon('check', 'me-1', 16); ?>
                                    <?php echo $action === 'add' ? 'Confirm & Create Employee' : 'Save Changes'; ?>
                                </button>
                                <a href="<?php echo url('admin/employees'); ?>" class="btn btn-outline-secondary px-3">Cancel</a>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Live Employee Identity Card Preview -->
            <div class="col-lg-4">
                <div class="saas-card sticky-top" style="top: 90px;">
                    <div class="saas-card-header">
                        <h6 class="saas-card-title mb-0">
                            <?php echo pika_icon('sparkles', 'text-warning', 18); ?> Live Profile Preview
                        </h6>
                    </div>
                    <div class="saas-card-body text-center p-4">
                        <div class="mx-auto mb-3" style="width: 80px; height: 80px;">
                            <div class="saas-avatar-initials w-100 h-100 rounded-circle fs-3 shadow" id="previewInitials">
                                <?php echo $employee ? strtoupper(substr($employee['name'], 0, 2)) : 'EM'; ?>
                            </div>
                        </div>
                        <h5 class="fw-bold text-dark mb-1" id="previewName"><?php echo htmlspecialchars($employee['name'] ?? 'Employee Name'); ?></h5>
                        <p class="text-muted small mb-3" id="previewEmail"><?php echo htmlspecialchars($employee['email'] ?? 'employee@clientura.in'); ?></p>

                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <span class="saas-id-badge" id="previewEmpId">
                                <?php echo htmlspecialchars($employee['employee_id'] ?? 'DOMAIN-ROLE-XXX'); ?>
                            </span>
                            <span class="saas-tag emerald" id="previewStatus">Active</span>
                        </div>

                        <div class="border-top pt-3 text-start small">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Department:</span>
                                <span class="fw-semibold text-dark" id="previewDomain">-</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Designation:</span>
                                <span class="fw-semibold text-dark" id="previewDesignation"><?php echo htmlspecialchars($employee['designation'] ?? 'Intern'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Attendance Policy:</span>
                                <span class="fw-semibold text-success" id="previewPolicy">Enforced</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        // Live Preview Reactive Script
        const designationCodes = {
            'Intern': 'INT',
            'Trainee': 'TRN',
            'Junior': 'JR',
            'Senior': 'SR',
            'Team Lead': 'TL',
            'Manager': 'MGR',
            'Director': 'DIR'
        };

        function updateLivePreview() {
            const name = document.getElementById('empNameInput')?.value.trim() || 'Employee Name';
            const email = document.getElementById('empEmailInput')?.value.trim() || 'employee@clientura.in';
            const domainSelect = document.getElementById('domainSelect');
            const designationSelect = document.getElementById('designationSelect');
            const domainCode = domainSelect?.options[domainSelect.selectedIndex]?.dataset?.code || 'GEN';
            const domainName = domainSelect?.options[domainSelect.selectedIndex]?.dataset?.name || 'Department';
            const designation = designationSelect?.value || 'Intern';
            const desigCode = designationCodes[designation] || 'EMP';
            const isAdd = '<?php echo $action; ?>' === 'add';

            // Initials
            const words = name.split(' ');
            let initials = words[0] ? words[0][0].toUpperCase() : 'E';
            if (words.length > 1 && words[1]) initials += words[1][0].toUpperCase();

            document.getElementById('previewInitials').textContent = initials;
            document.getElementById('previewName').textContent = name;
            document.getElementById('previewEmail').textContent = email;
            document.getElementById('previewDomain').textContent = domainName;
            document.getElementById('previewDesignation').textContent = designation;

            if (isAdd) {
                document.getElementById('previewEmpId').textContent = domainCode + '-' + desigCode + '-XXX';
            }

            const checkin = document.getElementById('requiresCheckin')?.checked;
            document.getElementById('previewPolicy').textContent = checkin ? 'Enforced' : 'Exempt';
            document.getElementById('previewPolicy').className = checkin ? 'fw-semibold text-success' : 'fw-semibold text-secondary';
        }

        ['empNameInput', 'empEmailInput', 'domainSelect', 'designationSelect', 'requiresCheckin'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', updateLivePreview);
                el.addEventListener('change', updateLivePreview);
            }
        });
        updateLivePreview();
        </script>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation SaaS Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white border-0 px-4 py-3">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <?php echo pika_icon('trash', 'text-white', 20); ?> Confirm Removal
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-2">Are you sure you want to remove <strong id="deleteEmployeeName" class="text-dark"></strong> from Clientura Workforce?</p>
                <p class="text-danger small mb-0">This operation deletes associated attendance and task records permanently.</p>
            </div>
            <div class="modal-footer bg-light border-0 px-4 py-3">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteEmployeeId">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <?php echo pika_icon('trash', 'me-1', 14); ?> Delete Record
                    </button>
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
