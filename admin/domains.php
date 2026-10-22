<?php
/**
 * Admin Domains Management - SaaS Department Hub
 * Employee Management System
 */

$pageTitle = 'Manage Domains';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$message = '';
$error = '';

// Handle Add Domain
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_domain'])) {
    $name = sanitize($_POST['name']);
    $code = strtoupper(sanitize($_POST['code']));
    $description = sanitize($_POST['description']);
    
    if (strlen($code) < 2 || strlen($code) > 3) {
        $error = 'Domain code must be 2-3 characters (e.g. WD, AI, FS)';
    } else {
        $existing = fetchOne("SELECT id FROM domains WHERE code = ?", "s", [$code]);
        if ($existing) {
            $error = 'Domain code already exists! Each department must have a unique identifier.';
        } else {
            $result = executeQuery(
                "INSERT INTO domains (name, code, description) VALUES (?, ?, ?)",
                "sss",
                [$name, $code, $description]
            );
            
            if ($result) {
                $message = "Department <strong>$name ($code)</strong> created successfully.";
                logActivity('domain_add', "Added domain: $name ($code)");
            } else {
                $error = 'Failed to create department. Please try again.';
            }
        }
    }
}

// Handle Edit Domain
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_domain'])) {
    $id = intval($_POST['domain_id']);
    $name = sanitize($_POST['name']);
    $code = strtoupper(sanitize($_POST['code']));
    $description = sanitize($_POST['description']);
    $status = sanitize($_POST['status']);
    
    $existing = fetchOne("SELECT id FROM domains WHERE code = ? AND id != ?", "si", [$code, $id]);
    if ($existing) {
        $error = 'Domain code already in use by another department.';
    } else {
        $result = executeQuery(
            "UPDATE domains SET name = ?, code = ?, description = ?, status = ? WHERE id = ?",
            "ssssi",
            [$name, $code, $description, $status, $id]
        );
        
        if ($result) {
            $message = 'Department settings updated successfully.';
        } else {
            $error = 'Failed to update department.';
        }
    }
}

// Handle Delete Domain
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_domain'])) {
    $id = intval($_POST['delete_domain']);
    
    $inUse = fetchOne("SELECT COUNT(*) as count FROM employees WHERE domain_id = ?", "i", [$id]);
    if ($inUse['count'] > 0) {
        $_SESSION['flash_message'] = 'Cannot delete department. It is assigned to ' . $inUse['count'] . ' active team member(s). Reassign them first.';
        $_SESSION['flash_type'] = 'danger';
    } else {
        $result = executeQuery("DELETE FROM domains WHERE id = ?", "i", [$id]);
        if ($result) {
            $_SESSION['flash_message'] = 'Department removed from organization registry.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to delete department.';
            $_SESSION['flash_type'] = 'danger';
        }
    }
    header("Location: domains");
    exit;
}

// Get all domains with counts
$domains = fetchAll("SELECT d.*, (SELECT COUNT(*) FROM employees WHERE domain_id = d.id) as employee_count FROM domains d ORDER BY d.name");

$totalDomains = count($domains);
$activeDomains = 0;
$totalAssignedStaff = 0;
foreach ($domains as $d) {
    if ($d['status'] === 'active') $activeDomains++;
    $totalAssignedStaff += intval($d['employee_count']);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Domains & Departments</h1>
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
        <?php 
        if (isset($_SESSION['flash_message'])): 
            $flashMessage = $_SESSION['flash_message'];
            $flashType = $_SESSION['flash_type'] ?? 'info';
            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        ?>
        <div class="alert alert-<?php echo $flashType; ?> alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <?php echo pika_icon($flashType === 'success' ? 'check-circle' : 'x', 'flex-shrink-0', 20); ?>
                <div><?php echo $flashMessage; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <?php echo pika_icon('check-circle', 'flex-shrink-0', 20); ?>
                <div><?php echo $message; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <?php echo pika_icon('x', 'flex-shrink-0', 20); ?>
                <div><?php echo $error; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Hero Hub -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="saas-hero-content">
                        <h2 class="saas-hero-title">Organizational Domains & Units</h2>
                        <p class="saas-hero-subtitle">
                            Configure functional departments, standardized business prefix codes, and workforce capacity routing for Clientura EMS.
                        </p>
                        <div class="saas-hero-pills">
                            <span class="saas-pill live-pulse">
                                <span class="pulse-dot"></span>
                                <?php echo $totalDomains; ?> Total Units
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('users', '', 14); ?>
                                <?php echo $totalAssignedStaff; ?> Assigned Team Members
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('shield-check', '', 14); ?>
                                Standard ID Architecture
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mt-3 mt-lg-0 text-lg-end">
                    <div class="saas-hero-actions">
                        <button class="btn btn-primary btn-glow" data-bs-toggle="modal" data-bs-target="#addDomainModal">
                            <?php echo pika_icon('plus', 'me-1', 16); ?> Add New Department
                        </button>
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
                            <?php echo pika_icon('domains', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active">Registry</span>
                    </div>
                    <div class="metric-value"><?php echo $totalDomains; ?></div>
                    <div class="metric-label">Registered Domains</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar navy" style="width: 100%;"></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge emerald">
                            <?php echo pika_icon('building', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active"><?php echo $activeDomains; ?> Active</span>
                    </div>
                    <div class="metric-value"><?php echo $activeDomains; ?></div>
                    <div class="metric-label">Active Deployments</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar emerald" style="width: <?php echo $totalDomains > 0 ? round(($activeDomains / $totalDomains) * 100) : 100; ?>%;"></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge sky">
                            <?php echo pika_icon('users', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill sky">Workforce</span>
                    </div>
                    <div class="metric-value"><?php echo $totalAssignedStaff; ?></div>
                    <div class="metric-label">Staff Members Assigned</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar sky" style="width: 90%;"></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge amber">
                            <?php echo pika_icon('award', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill warning">Prefix Standard</span>
                    </div>
                    <div class="metric-value">2-3</div>
                    <div class="metric-label">Character Code Format</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar amber" style="width: 100%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Domains Table Card -->
        <div class="saas-card">
            <div class="saas-card-header">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="saas-card-title">
                        <?php echo pika_icon('domains', 'text-primary', 20); ?>
                        Department Directory
                    </h5>
                    <span class="badge bg-light text-dark border px-2 py-1 rounded-pill small">
                        <?php echo count($domains); ?> Units
                    </span>
                </div>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDomainModal">
                    <?php echo pika_icon('plus', 'me-1', 14); ?> Add Department
                </button>
            </div>
            <div class="table-responsive">
                <table class="saas-table">
                    <thead>
                        <tr>
                            <th>Department Name</th>
                            <th>Code Prefix</th>
                            <th>Operational Scope</th>
                            <th>Headcount</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($domains)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <?php echo pika_icon('domains', 'opacity-25 d-block mx-auto mb-3', 48); ?>
                                <h6 class="fw-bold text-dark">No Domains Configured</h6>
                                <p class="small text-muted mb-3">Add your first domain to enable structured employee IDs.</p>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDomainModal">
                                    <?php echo pika_icon('plus', 'me-1', 14); ?> Create First Department
                                </button>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($domains as $domain): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="metric-icon-badge navy" style="width: 36px; height: 36px; border-radius: 10px;">
                                            <?php echo pika_icon('building', '', 16); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($domain['name']); ?></div>
                                            <small class="text-muted">ID Prefix: <code><?php echo htmlspecialchars($domain['code']); ?></code></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="saas-id-badge" style="font-size: 0.8rem; padding: 4px 12px;">
                                        <?php echo htmlspecialchars($domain['code']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted small"><?php echo htmlspecialchars($domain['description'] ?: 'General corporate department'); ?></span>
                                </td>
                                <td>
                                    <span class="saas-tag <?php echo $domain['employee_count'] > 0 ? 'emerald' : 'primary'; ?>">
                                        <?php echo pika_icon('users', '', 12); ?>
                                        <?php echo $domain['employee_count']; ?> Staff
                                    </span>
                                </td>
                                <td>
                                    <span class="saas-tag <?php echo $domain['status'] === 'active' ? 'emerald' : 'amber'; ?>">
                                        <?php echo ucfirst($domain['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="saas-action-btn" title="Edit Department"
                                                onclick="editDomain(<?php echo htmlspecialchars(json_encode($domain)); ?>)">
                                            <?php echo pika_icon('edit', '', 15); ?>
                                        </button>
                                        <?php if ($domain['employee_count'] == 0): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Remove <?php echo addslashes($domain['name']); ?>?')">
                                            <input type="hidden" name="delete_domain" value="<?php echo $domain['id']; ?>">
                                            <button type="submit" class="saas-action-btn delete" title="Delete Domain">
                                                <?php echo pika_icon('trash', '', 15); ?>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Designation Reference SaaS Card -->
        <div class="saas-card mt-4">
            <div class="saas-card-header">
                <h5 class="saas-card-title">
                    <?php echo pika_icon('award', 'text-primary', 20); ?>
                    Employee ID Generation Matrix
                </h5>
                <span class="badge bg-light text-dark border px-2 py-1 rounded-pill small">
                    Format: [DOMAIN]-[DESIGNATION]-[NUMBER]
                </span>
            </div>
            <div class="saas-card-body p-4">
                <p class="text-muted small mb-3">
                    Each team member registered is automatically formatted according to their assigned Domain Code and Designation:
                </p>
                <div class="row g-3">
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded-4 border d-flex align-items-center justify-content-between">
                            <span class="fw-semibold text-dark small">Intern</span>
                            <span class="saas-id-badge">INT</span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded-4 border d-flex align-items-center justify-content-between">
                            <span class="fw-semibold text-dark small">Trainee</span>
                            <span class="saas-id-badge">TRN</span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded-4 border d-flex align-items-center justify-content-between">
                            <span class="fw-semibold text-dark small">Junior</span>
                            <span class="saas-id-badge">JR</span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded-4 border d-flex align-items-center justify-content-between">
                            <span class="fw-semibold text-dark small">Senior</span>
                            <span class="saas-id-badge">SR</span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded-4 border d-flex align-items-center justify-content-between">
                            <span class="fw-semibold text-dark small">Team Lead</span>
                            <span class="saas-id-badge">TL</span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded-4 border d-flex align-items-center justify-content-between">
                            <span class="fw-semibold text-dark small">Manager</span>
                            <span class="saas-id-badge">MGR</span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded-4 border d-flex align-items-center justify-content-between">
                            <span class="fw-semibold text-dark small">Director</span>
                            <span class="saas-id-badge">DIR</span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded-4 border d-flex align-items-center justify-content-between">
                            <span class="fw-semibold text-dark small">Employee</span>
                            <span class="saas-id-badge">EMP</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Domain Modal -->
<div class="modal fade" id="addDomainModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-navy text-white border-0 px-4 py-3" style="background: #0A1138;">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
                    <?php echo pika_icon('plus', 'text-white', 20); ?> Add New Department
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-pill px-3 py-2" name="name" required placeholder="e.g. Web Development">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Code Prefix <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-pill px-3 py-2" name="code" required maxlength="3" minlength="2" 
                               placeholder="e.g. WD" style="text-transform: uppercase;">
                        <small class="text-muted">2-3 uppercase characters used in Employee IDs (e.g. WD-INT-001)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Description</label>
                        <textarea class="form-control rounded-4 p-3" name="description" rows="2" placeholder="Brief outline of departmental operations..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_domain" class="btn btn-primary btn-glow px-4">
                        <?php echo pika_icon('check', 'me-1', 14); ?> Create Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Domain Modal -->
<div class="modal fade" id="editDomainModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-navy text-white border-0 px-4 py-3" style="background: #0A1138;">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
                    <?php echo pika_icon('edit', 'text-white', 20); ?> Edit Department
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="domain_id" id="edit_domain_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-pill px-3 py-2" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Code Prefix <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-pill px-3 py-2" name="code" id="edit_code" required maxlength="3" minlength="2" style="text-transform: uppercase;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Description</label>
                        <textarea class="form-control rounded-4 p-3" name="description" id="edit_description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Operational Status</label>
                        <select class="form-select rounded-pill px-3 py-2" name="status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_domain" class="btn btn-primary btn-glow px-4">
                        <?php echo pika_icon('check', 'me-1', 14); ?> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editDomain(domain) {
    document.getElementById('edit_domain_id').value = domain.id;
    document.getElementById('edit_name').value = domain.name;
    document.getElementById('edit_code').value = domain.code;
    document.getElementById('edit_description').value = domain.description || '';
    document.getElementById('edit_status').value = domain.status;
    new bootstrap.Modal(document.getElementById('editDomainModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
