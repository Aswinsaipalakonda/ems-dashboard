<?php
/**
 * Certificates Management
 * View, search, download, and issue completion certificates with QR verification
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . url('login'));
    exit;
}

$pageTitle = 'Completion Certificates';
$successMsg = '';
$errorMsg = '';

// Handle Revoke / Delete
if (isset($_GET['action']) && $_GET['action'] === 'revoke' && isset($_GET['id'])) {
    $certId = (int)$_GET['id'];
    executeQuery("UPDATE certificates SET status = 'revoked' WHERE id = ?", "i", [$certId]);
    $successMsg = 'Certificate has been revoked successfully.';
}

// Fetch certificates with employee and signatory details
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT c.*, s.name as signatory_name, s.designation as signatory_title, e.employee_id as emp_code 
        FROM certificates c
        LEFT JOIN signatories s ON c.signatory_id = s.id
        LEFT JOIN employees e ON c.employee_id = e.id
        WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (c.recipient_name LIKE ? OR c.certificate_id LIKE ? OR c.program_name LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= "sss";
}

if (!empty($statusFilter)) {
    $sql .= " AND c.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql .= " ORDER BY c.id DESC";
$certificates = fetchAll($sql, $types, $params);

// Stats
$totalCerts = fetchOne("SELECT COUNT(*) as c FROM certificates")['c'] ?? 0;
$activeCerts = fetchOne("SELECT COUNT(*) as c FROM certificates WHERE status = 'active'")['c'] ?? 0;
$revokedCerts = fetchOne("SELECT COUNT(*) as c FROM certificates WHERE status = 'revoked'")['c'] ?? 0;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h1 class="h3 font-weight-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Completion Certificates</h1>
                <p class="text-muted mb-0">Generate, issue, and manage authenticated internship & employee completion certificates with QR verification.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?php echo url('admin/signatories'); ?>" class="btn btn-outline-primary">
                    <?php echo pika_icon('signature'); ?> Manage Signatories
                </a>
                <a href="<?php echo url('admin/generate-certificate'); ?>" class="btn btn-primary">
                    <?php echo pika_icon('plus'); ?> Issue New Certificate
                </a>
            </div>
        </div>

        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-pill px-4" role="alert">
                <i class="bi bi-check-circle me-2"></i> <?php echo htmlspecialchars($successMsg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Overview Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon primary">
                        <?php echo pika_icon('certificate'); ?>
                    </div>
                    <div class="stats-info">
                        <h3><?php echo number_format($totalCerts); ?></h3>
                        <p>Total Certificates Issued</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon success">
                        <?php echo pika_icon('check-circle'); ?>
                    </div>
                    <div class="stats-info">
                        <h3><?php echo number_format($activeCerts); ?></h3>
                        <p>Active & Verified Credentials</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon danger">
                        <?php echo pika_icon('activity'); ?>
                    </div>
                    <div class="stats-info">
                        <h3><?php echo number_format($revokedCerts); ?></h3>
                        <p>Revoked Credentials</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body p-3">
                <form method="GET" class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0" style="border-radius: 9999px 0 0 9999px;">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Search by recipient name, certificate ID, or role..." value="<?php echo htmlspecialchars($search); ?>" style="border-radius: 0 9999px 9999px 0;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select" style="border-radius: 9999px;">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="revoked" <?php echo $statusFilter === 'revoked' ? 'selected' : ''; ?>>Revoked</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-navy w-100">Filter</button>
                        <?php if (!empty($search) || !empty($statusFilter)): ?>
                            <a href="<?php echo url('admin/certificates'); ?>" class="btn btn-light" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Certificates Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="font-weight-bold text-dark">Issued Certificates Log</span>
                <span class="text-muted small"><?php echo count($certificates); ?> records found</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Certificate ID</th>
                            <th>Recipient Name</th>
                            <th>Program / Role</th>
                            <th>Duration & Dates</th>
                            <th>Signatory</th>
                            <th>Status</th>
                            <th>QR Verify Link</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($certificates)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-award fs-1 d-block text-muted opacity-50 mb-2"></i>
                                    No completion certificates issued yet.
                                    <div class="mt-2">
                                        <a href="<?php echo url('admin/generate-certificate'); ?>" class="btn btn-primary btn-sm">Issue First Certificate</a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($certificates as $cert): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-primary border font-monospace">
                                            <?php echo htmlspecialchars($cert['certificate_id']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold text-dark"><?php echo htmlspecialchars($cert['recipient_name']); ?></div>
                                        <?php if (!empty($cert['emp_code'])): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($cert['emp_code']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary">
                                            <?php echo htmlspecialchars($cert['program_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="d-block text-dark font-weight-500"><?php echo htmlspecialchars($cert['duration_text']); ?></small>
                                        <small class="text-muted"><?php echo date('d M Y', strtotime($cert['start_date'])); ?> - <?php echo date('d M Y', strtotime($cert['end_date'])); ?></small>
                                    </td>
                                    <td>
                                        <small class="text-dark d-block"><?php echo htmlspecialchars($cert['signatory_name'] ?? 'Authorized Signatory'); ?></small>
                                        <small class="text-muted"><?php echo htmlspecialchars($cert['signatory_title'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($cert['status'] === 'active'): ?>
                                            <span class="badge bg-success-subtle text-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger">Revoked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo url('verify-certificate/' . urlencode($cert['certificate_id'])); ?>" target="_blank" class="text-decoration-none small text-primary d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-qr-code"></i> Live Verification <i class="bi bi-box-arrow-up-right small"></i>
                                        </a>
                                    </td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 32px; height: 32px; padding: 0 !important;">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 14px;">
                                                <li>
                                                    <a class="dropdown-menu-item dropdown-item" href="<?php echo url($cert['file_path']); ?>" target="_blank" download>
                                                        <i class="bi bi-download me-2 text-primary"></i> Download Certificate
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-menu-item dropdown-item" href="<?php echo url('verify-certificate/' . urlencode($cert['certificate_id'])); ?>" target="_blank">
                                                        <i class="bi bi-shield-check me-2 text-success"></i> View Verification Page
                                                    </a>
                                                </li>
                                                <?php if ($cert['status'] === 'active'): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-menu-item dropdown-item text-danger" href="<?php echo url('admin/certificates?action=revoke&id=' . $cert['id']); ?>" onclick="return confirm('Are you sure you want to revoke this certificate? The verification page will display it as revoked.');">
                                                            <i class="bi bi-x-circle me-2"></i> Revoke Certificate
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
