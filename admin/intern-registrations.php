<?php
/**
 * Admin Intern Registrations Module
 * Manage candidate applications, review CVs, and convert applicants to active employees/interns.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . url('login'));
    exit;
}

$pageTitle = 'Intern Registrations';
$message = '';
$messageType = '';

// Handle Status Updates or Convert to Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $regId = (int)($_POST['id'] ?? 0);

    if ($action === 'update_status' && $regId > 0) {
        $newStatus = $_POST['status'] ?? 'pending';
        if (in_array($newStatus, ['pending', 'under_review', 'approved', 'rejected'])) {
            executeQuery("UPDATE intern_registrations SET status = ? WHERE id = ?", "si", [$newStatus, $regId]);
            logActivity('intern_status_update', "Updated intern application #{$regId} status to {$newStatus}");
            $message = "Application status updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . ".";
            $messageType = "success";
        }
    }

    if ($action === 'convert_to_employee' && $regId > 0) {
        $reg = fetchOne("SELECT * FROM intern_registrations WHERE id = ?", "i", [$regId]);
        if ($reg) {
            // Check if email already exists in employees
            $existingEmp = fetchOne("SELECT id, employee_id FROM employees WHERE email = ?", "s", [$reg['email']]);
            if ($existingEmp) {
                $message = "An employee with email '{$reg['email']}' already exists (ID: {$existingEmp['employee_id']}).";
                $messageType = "warning";
            } else {
                // Generate employee ID using domain and Intern designation
                $domainId = (int)($reg['domain_id'] ?: 1);
                $domain = fetchOne("SELECT name FROM domains WHERE id = ?", "i", [$domainId]);
                $domainName = $domain ? $domain['name'] : 'Software Development';
                $designation = !empty($reg['other_domain']) ? trim($reg['other_domain']) . ' Intern' : $domainName . ' Intern';

                $empCode = generateEmployeeId($domainId, 'Intern');
                $defaultPass = password_hash('password123', PASSWORD_DEFAULT);
                $joiningDate = !empty($reg['start_date']) ? $reg['start_date'] : date('Y-m-d');

                $newEmpId = insert(
                    "INSERT INTO employees (
                        employee_id, name, email, password, phone, domain_id, 
                        designation, date_of_joining, status, requires_checkin
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 1)",
                    "sssssiss",
                    [
                        $empCode, $reg['full_name'], $reg['email'], $defaultPass,
                        $reg['phone'], $domainId, $designation, $joiningDate
                    ]
                );

                if ($newEmpId) {
                    // Update registration status
                    executeQuery(
                        "UPDATE intern_registrations SET status = 'approved', converted_employee_id = ? WHERE id = ?",
                        "ii",
                        [$newEmpId, $regId]
                    );

                    logActivity('convert_intern', "Converted intern application #{$reg['registration_id']} ({$reg['full_name']}) to Employee #{$empCode}");
                    $message = "Successfully converted {$reg['full_name']} to Employee {$empCode}! Default password is 'password123'.";
                    $messageType = "success";
                } else {
                    $message = "Database error while creating employee account.";
                    $messageType = "danger";
                }
            }
        }
    }

    if ($action === 'delete_registration' && $regId > 0) {
        $reg = fetchOne("SELECT * FROM intern_registrations WHERE id = ?", "i", [$regId]);
        if ($reg) {
            if (!empty($reg['resume_path'])) {
                $absResume = __DIR__ . '/../' . $reg['resume_path'];
                if (file_exists($absResume)) {
                    @unlink($absResume);
                }
            }
            executeQuery("DELETE FROM intern_registrations WHERE id = ?", "i", [$regId]);
            logActivity('delete_intern_registration', "Deleted intern application #{$reg['registration_id']} ({$reg['full_name']})");
            $message = "Application #{$reg['registration_id']} ({$reg['full_name']}) was permanently deleted.";
            $messageType = "success";
        }
    }
}

// Export CSV handler
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $registrations = fetchAll(
        "SELECT ir.*, d.name AS domain_name, e.employee_id AS emp_code
         FROM intern_registrations ir
         LEFT JOIN domains d ON ir.domain_id = d.id
         LEFT JOIN employees e ON ir.converted_employee_id = e.id
         ORDER BY ir.created_at DESC"
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Intern_Registrations_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'ID', 'Reg ID', 'Full Name', 'Email', 'Phone', 'College Name', 
        'Degree & Branch', 'Grad Year', 'CGPA', 'Domain', 'Duration', 
        'Start Date', 'Mode', 'LinkedIn', 'GitHub', 'Status', 'Converted Emp ID', 'Applied At'
    ]);

    foreach ($registrations as $r) {
        fputcsv($output, [
            $r['id'],
            $r['registration_id'],
            $r['full_name'],
            $r['email'],
            $r['phone'],
            $r['college_name'],
            $r['degree_branch'],
            $r['graduation_year'],
            $r['cgpa_percentage'],
            !empty($r['other_domain']) ? $r['other_domain'] . ' (Custom)' : ($r['domain_name'] ?? 'General'),
            $r['preferred_duration'],
            $r['start_date'],
            $r['internship_mode'],
            $r['linkedin_url'],
            $r['github_portfolio_url'],
            $r['status'],
            $r['emp_code'] ?? 'N/A',
            $r['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// Fetch domains for filter
$domains = fetchAll("SELECT id, name FROM domains WHERE status = 'active' ORDER BY name ASC");

// Filters & Search
$statusFilter = trim($_GET['status'] ?? '');
$domainFilter = (int)($_GET['domain'] ?? 0);
$search = trim($_GET['search'] ?? '');

$sql = "SELECT ir.*, d.name AS domain_name, e.employee_id AS emp_code
        FROM intern_registrations ir
        LEFT JOIN domains d ON ir.domain_id = d.id
        LEFT JOIN employees e ON ir.converted_employee_id = e.id
        WHERE 1=1";
$params = [];
$types = "";

if (!empty($statusFilter)) {
    $sql .= " AND ir.status = ?";
    $types .= "s";
    $params[] = $statusFilter;
}

if ($domainFilter > 0) {
    $sql .= " AND ir.domain_id = ?";
    $types .= "i";
    $params[] = $domainFilter;
}

if (!empty($search)) {
    $sql .= " AND (ir.full_name LIKE ? OR ir.email LIKE ? OR ir.registration_id LIKE ? OR ir.college_name LIKE ?)";
    $types .= "ssss";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY ir.created_at DESC";

$applications = !empty($params) ? fetchAll($sql, $types, $params) : fetchAll($sql);

// Compute Summary Metrics
$stats = fetchOne(
    "SELECT 
        COUNT(*) as total_apps,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_apps,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_apps,
        SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as review_apps
     FROM intern_registrations"
);

$totalApps = $stats['total_apps'] ?? 0;
$pendingApps = $stats['pending_apps'] ?? 0;
$approvedApps = $stats['approved_apps'] ?? 0;
$reviewApps = $stats['review_apps'] ?? 0;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h3 font-weight-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Intern Registrations & Applicants</h1>
                <p class="text-muted mb-0">Review candidate submissions, assess technical CVs, and onboard interns into the active workforce with one click.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?php echo url('intern-registration'); ?>" target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>
                    <span>Public Registration Form</span>
                </a>
                <a href="<?php echo url('admin/intern-registrations?action=export_csv'); ?>" class="btn btn-saas-light">
                    <i class="bi bi-download me-1"></i>
                    <span>Export CSV</span>
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show rounded-4 shadow-sm" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">TOTAL APPLICANTS</span>
                        <div class="metric-icon-badge purple">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $totalApps; ?></div>
                    <div class="metric-label">All-time Registrations</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">PENDING ACTION</span>
                        <div class="metric-icon-badge amber">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $pendingApps; ?></div>
                    <div class="metric-label">Awaiting Initial Screening</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">UNDER REVIEW</span>
                        <div class="metric-icon-badge blue">
                            <i class="bi bi-search"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $reviewApps; ?></div>
                    <div class="metric-label">In Technical Evaluation</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">APPROVED & ONBOARDED</span>
                        <div class="metric-icon-badge green">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $approvedApps; ?></div>
                    <div class="metric-label">Converted to Employees</div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-3">
                <form method="GET" action="<?php echo url('admin/intern-registrations'); ?>" class="row g-2 align-items-center">
                    <div class="col-lg-5 col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0 text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, email, college, or Reg ID..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-3">
                        <select name="domain" class="form-select">
                            <option value="0">All Domains</option>
                            <?php foreach ($domains as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo $domainFilter === (int)$d['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="under_review" <?php echo $statusFilter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved / Converted</option>
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-saas-dark flex-grow-1">Filter</button>
                        <?php if (!empty($search) || $domainFilter > 0 || !empty($statusFilter)): ?>
                            <a href="<?php echo url('admin/intern-registrations'); ?>" class="btn btn-light" title="Clear Filters">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table of Registrations -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table saas-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Applicant / Reg ID</th>
                            <th>College & Degree</th>
                            <th>Domain Track</th>
                            <th>Duration & Mode</th>
                            <th>Resume</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="bi bi-person-lines-fill text-muted fs-1 opacity-25 d-block mb-2"></i>
                                <h6 class="fw-bold text-dark">No Applications Found</h6>
                                <p class="text-muted small mb-0">Candidates will appear here as soon as they submit their registration form.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($app['full_name']); ?></div>
                                    <small class="font-monospace text-primary"><?php echo htmlspecialchars($app['registration_id']); ?></small>
                                    <div class="text-muted small"><?php echo htmlspecialchars($app['email']); ?> &bull; <?php echo htmlspecialchars($app['phone']); ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark small"><?php echo htmlspecialchars($app['college_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($app['degree_branch']); ?> (Class of <?php echo htmlspecialchars($app['graduation_year']); ?>)</small>
                                </td>
                                <td>
                                    <?php if (!empty($app['other_domain'])): ?>
                                        <span class="badge rounded-pill px-2 py-1" style="background: rgba(124, 58, 237, 0.12); color: #6d28d9; border: 1px solid rgba(124, 58, 237, 0.25);" title="Custom track specified by candidate">
                                            <i class="bi bi-stars me-1"></i><?php echo htmlspecialchars($app['other_domain']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="saas-tag primary">
                                            <?php echo htmlspecialchars($app['domain_name'] ?? 'General'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark small"><?php echo htmlspecialchars($app['preferred_duration']); ?></div>
                                    <span class="badge bg-light text-muted border text-capitalize small"><?php echo htmlspecialchars($app['internship_mode']); ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($app['resume_path']) && file_exists(__DIR__ . '/../' . $app['resume_path'])): ?>
                                        <a href="<?php echo url($app['resume_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1 small">
                                            <i class="bi bi-file-earmark-pdf me-1"></i> View CV
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">No file</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusMap = [
                                        'pending' => ['label' => 'Pending', 'class' => 'bg-warning-subtle text-warning border-warning-subtle'],
                                        'under_review' => ['label' => 'Under Review', 'class' => 'bg-info-subtle text-info border-info-subtle'],
                                        'approved' => ['label' => 'Approved', 'class' => 'bg-success-subtle text-success border-success-subtle'],
                                        'rejected' => ['label' => 'Rejected', 'class' => 'bg-danger-subtle text-danger border-danger-subtle'],
                                    ];
                                    $currBadge = $statusMap[$app['status']] ?? $statusMap['pending'];
                                    ?>
                                    <span class="badge border <?php echo $currBadge['class']; ?> rounded-pill px-2 py-1">
                                        <?php echo $currBadge['label']; ?>
                                    </span>
                                    <?php if (!empty($app['emp_code'])): ?>
                                        <div class="mt-1 font-monospace small text-success fw-bold">
                                            <i class="bi bi-person-check-fill me-1"></i><?php echo htmlspecialchars($app['emp_code']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo date('d M Y', strtotime($app['created_at'])); ?></small>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1 align-items-center">
                                        <button type="button" class="btn btn-sm btn-light rounded-pill px-2" data-bs-toggle="modal" data-bs-target="#applicantModal_<?php echo $app['id']; ?>" title="View Full Details">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <?php if ($app['status'] !== 'approved'): ?>
                                        <!-- One-click Convert Form -->
                                        <form method="POST" action="<?php echo url('admin/intern-registrations'); ?>" class="d-inline" onsubmit="return confirm('Convert <?php echo htmlspecialchars(addslashes($app['full_name'])); ?> to an active employee/intern?');">
                                            <input type="hidden" name="action" value="convert_to_employee">
                                            <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-2" title="Approve & Onboard as Employee">
                                                <i class="bi bi-person-plus-fill me-1"></i> Onboard
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <a href="<?php echo url('admin/generate-offer'); ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Issue Offer Letter">
                                            <i class="bi bi-file-earmark-text me-1"></i> Offer
                                        </a>
                                        <?php endif; ?>

                                        <!-- Delete Application Form -->
                                        <form method="POST" action="<?php echo url('admin/intern-registrations'); ?>" class="d-inline" onsubmit="return confirm('Permanently delete application <?php echo htmlspecialchars(addslashes($app['registration_id'])); ?> (<?php echo htmlspecialchars(addslashes($app['full_name'])); ?>)? This cannot be undone.');">
                                            <input type="hidden" name="action" value="delete_registration">
                                            <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Delete Application">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <!-- Applicant Details Modal -->
                            <div class="modal fade" id="applicantModal_<?php echo $app['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content rounded-4 border-0 shadow">
                                        <div class="modal-header border-bottom py-3">
                                            <div>
                                                <h5 class="modal-title font-weight-bold text-dark mb-0">
                                                    <?php echo htmlspecialchars($app['full_name']); ?>
                                                </h5>
                                                <small class="font-monospace text-primary"><?php echo htmlspecialchars($app['registration_id']); ?></small>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="row g-3 mb-4">
                                                <div class="col-sm-6">
                                                    <span class="text-muted small d-block">Email Address</span>
                                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($app['email']); ?></span>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="text-muted small d-block">Phone / WhatsApp</span>
                                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($app['phone']); ?></span>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="text-muted small d-block">College / Institute</span>
                                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($app['college_name']); ?></span>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="text-muted small d-block">Degree & Specialization</span>
                                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($app['degree_branch']); ?> (Class of <?php echo htmlspecialchars($app['graduation_year']); ?>)</span>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="text-muted small d-block">Target Domain Track</span>
                                                    <?php if (!empty($app['other_domain'])): ?>
                                                        <span class="badge rounded-pill px-2 py-1" style="background: rgba(124, 58, 237, 0.12); color: #6d28d9; border: 1px solid rgba(124, 58, 237, 0.25);">
                                                            <i class="bi bi-stars me-1"></i><?php echo htmlspecialchars($app['other_domain']); ?> <small>(Custom)</small>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary-subtle text-primary"><?php echo htmlspecialchars($app['domain_name'] ?? 'General'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="text-muted small d-block">Desired Duration & Mode</span>
                                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($app['preferred_duration']); ?> &bull; <?php echo ucfirst($app['internship_mode']); ?></span>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="text-muted small d-block">Earliest Joining Date</span>
                                                    <span class="fw-semibold text-dark"><?php echo $app['start_date'] ? date('d M Y', strtotime($app['start_date'])) : 'Immediate'; ?></span>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="text-muted small d-block">CGPA / Score</span>
                                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($app['cgpa_percentage'] ?: 'Not specified'); ?></span>
                                                </div>
                                            </div>

                                            <!-- Profiles & Resume -->
                                            <div class="p-3 rounded-3 bg-light border mb-4">
                                                <h6 class="fw-bold text-dark small mb-2">Professional Profiles & Resume</h6>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php if (!empty($app['resume_path']) && file_exists(__DIR__ . '/../' . $app['resume_path'])): ?>
                                                        <a href="<?php echo url($app['resume_path']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-download me-1"></i> Download Resume CV
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if (!empty($app['linkedin_url'])): ?>
                                                        <a href="<?php echo htmlspecialchars($app['linkedin_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-linkedin me-1"></i> LinkedIn Profile
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if (!empty($app['github_portfolio_url'])): ?>
                                                        <a href="<?php echo htmlspecialchars($app['github_portfolio_url']); ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                                                            <i class="bi bi-github me-1"></i> GitHub / Portfolio
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if (!empty($app['cover_note'])): ?>
                                            <div class="mb-4">
                                                <h6 class="fw-bold text-dark small text-uppercase">Applicant Note / Expectations</h6>
                                                <div class="p-3 bg-light rounded-3 text-dark"><?php echo nl2br(htmlspecialchars($app['cover_note'])); ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Status Update Form -->
                                            <form method="POST" action="<?php echo url('admin/intern-registrations'); ?>" class="p-3 border rounded-3 bg-light-subtle">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                                <div class="d-flex align-items-center justify-content-between gap-3">
                                                    <label class="form-label font-weight-bold text-dark small mb-0">Application Status:</label>
                                                    <select name="status" class="form-select form-select-sm w-auto">
                                                        <option value="pending" <?php echo $app['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="under_review" <?php echo $app['status'] === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                                        <option value="approved" <?php echo $app['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                        <option value="rejected" <?php echo $app['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-saas-dark">Update Status</button>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="modal-footer border-top py-2">
                                            <button type="button" class="btn btn-saas-light" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
