<?php
/**
 * Admin Domains Management
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
    
    // Validate code is 2-3 characters
    if (strlen($code) < 2 || strlen($code) > 3) {
        $error = 'Domain code must be 2-3 characters';
    } else {
        // Check if code already exists
        $existing = fetchOne("SELECT id FROM domains WHERE code = ?", "s", [$code]);
        if ($existing) {
            $error = 'Domain code already exists';
        } else {
            $result = executeQuery(
                "INSERT INTO domains (name, code, description) VALUES (?, ?, ?)",
                "sss",
                [$name, $code, $description]
            );
            
            if ($result) {
                $message = 'Domain added successfully';
                logActivity('domain_add', "Added domain: $name ($code)");
            } else {
                $error = 'Failed to add domain';
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
    
    // Check if code already exists for other domains
    $existing = fetchOne("SELECT id FROM domains WHERE code = ? AND id != ?", "si", [$code, $id]);
    if ($existing) {
        $error = 'Domain code already exists';
    } else {
        $result = executeQuery(
            "UPDATE domains SET name = ?, code = ?, description = ?, status = ? WHERE id = ?",
            "ssssi",
            [$name, $code, $description, $status, $id]
        );
        
        if ($result) {
            $message = 'Domain updated successfully';
        } else {
            $error = 'Failed to update domain';
        }
    }
}

// Handle Delete Domain (POST method for security)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_domain'])) {
    $id = intval($_POST['delete_domain']);
    
    // Check if domain is in use
    $inUse = fetchOne("SELECT COUNT(*) as count FROM employees WHERE domain_id = ?", "i", [$id]);
    if ($inUse['count'] > 0) {
        $_SESSION['flash_message'] = 'Cannot delete domain. It is assigned to ' . $inUse['count'] . ' employee(s)';
        $_SESSION['flash_type'] = 'danger';
    } else {
        $result = executeQuery("DELETE FROM domains WHERE id = ?", "i", [$id]);
        if ($result) {
            $_SESSION['flash_message'] = 'Domain deleted successfully';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to delete domain';
            $_SESSION['flash_type'] = 'danger';
        }
    }
    header("Location: domains.php");
    exit;
}

// Get all domains
$domains = fetchAll("SELECT d.*, (SELECT COUNT(*) FROM employees WHERE domain_id = d.id) as employee_count FROM domains d ORDER BY d.name");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Manage Domains</h1>
        </div>
        <div class="header-right">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDomainModal">
                <i class="bi bi-plus-lg me-1"></i>Add Domain
            </button>
        </div>
    </header>
    
    <div class="content-wrapper">
        <?php 
        // Handle flash messages (PRG pattern)
        if (isset($_SESSION['flash_message'])): 
            $flashMessage = $_SESSION['flash_message'];
            $flashType = $_SESSION['flash_type'] ?? 'info';
            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        ?>
        <div class="alert alert-<?php echo $flashType; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $flashType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i><?php echo $flashMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Info Card -->
        <div class="alert alert-info mb-4">
            <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Employee ID Format</h6>
            <p class="mb-0">Employee IDs are auto-generated as: <strong>[DOMAIN_CODE]-[DESIGNATION_CODE]-[NUMBER]</strong></p>
            <small>Example: WD-INT-001 (Web Development - Intern - Employee #1)</small>
        </div>
        
        <!-- Domains Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-grid me-2"></i>All Domains</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Domain Name</th>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Employees</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($domains)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                    No domains found. Add your first domain!
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($domains as $domain): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo $domain['name']; ?></div>
                                    </td>
                                    <td><span class="badge bg-primary"><?php echo $domain['code']; ?></span></td>
                                    <td><?php echo $domain['description'] ?: '-'; ?></td>
                                    <td><span class="badge bg-secondary"><?php echo $domain['employee_count']; ?></span></td>
                                    <td>
                                        <span class="badge badge-<?php echo $domain['status']; ?>">
                                            <?php echo ucfirst($domain['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editDomain(<?php echo htmlspecialchars(json_encode($domain)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($domain['employee_count'] == 0): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this domain?')">
                                            <input type="hidden" name="delete_domain" value="<?php echo $domain['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Designation Codes Reference -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-bookmark me-2"></i>Designation Codes Reference</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <span class="badge bg-info me-2">INT</span> Intern
                    </div>
                    <div class="col-md-3 mb-2">
                        <span class="badge bg-info me-2">TRN</span> Trainee
                    </div>
                    <div class="col-md-3 mb-2">
                        <span class="badge bg-info me-2">JR</span> Junior
                    </div>
                    <div class="col-md-3 mb-2">
                        <span class="badge bg-info me-2">SR</span> Senior
                    </div>
                    <div class="col-md-3 mb-2">
                        <span class="badge bg-info me-2">TL</span> Team Lead
                    </div>
                    <div class="col-md-3 mb-2">
                        <span class="badge bg-info me-2">MGR</span> Manager
                    </div>
                    <div class="col-md-3 mb-2">
                        <span class="badge bg-info me-2">DIR</span> Director
                    </div>
                    <div class="col-md-3 mb-2">
                        <span class="badge bg-info me-2">EMP</span> Employee (Default)
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Domain Modal -->
<div class="modal fade" id="addDomainModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Domain</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Domain Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required placeholder="e.g., Web Development">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Domain Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code" required maxlength="3" minlength="2" 
                               placeholder="e.g., WD" style="text-transform: uppercase;">
                        <small class="text-muted">2-3 characters, will be used in Employee ID</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Brief description of this domain"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_domain" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Add Domain
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Domain Modal -->
<div class="modal fade" id="editDomainModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Domain</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="domain_id" id="edit_domain_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Domain Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Domain Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code" id="edit_code" required maxlength="3" minlength="2" style="text-transform: uppercase;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_domain" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
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
