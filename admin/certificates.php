<?php
/**
 * Certificates Management - SaaS Credential Hub
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
    $successMsg = 'Certificate credential has been revoked successfully.';
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $certId = (int)$_GET['id'];
    $cert = fetchOne("SELECT file_path FROM certificates WHERE id = ?", "i", [$certId]);
    if ($cert && !empty($cert['file_path']) && file_exists(__DIR__ . '/../' . $cert['file_path'])) {
        @unlink(__DIR__ . '/../' . $cert['file_path']);
    }
    executeQuery("DELETE FROM certificates WHERE id = ?", "i", [$certId]);
    $successMsg = 'Certificate credential has been permanently deleted.';
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
$authRate = $totalCerts > 0 ? round(($activeCerts / $totalCerts) * 100) : 100;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Completion Certificates</h1>
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
        <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <?php echo pika_icon('check-circle', 'flex-shrink-0', 20); ?>
                <div><?php echo htmlspecialchars($successMsg); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Light SaaS Hero -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="saas-hero-content">
                        <div class="saas-hero-pills">
                            <span class="saas-pill">
                                <?php echo pika_icon('sparkles', '', 14); ?>
                                Credential Governance Center
                            </span>
                        </div>
                        <h2 class="saas-hero-title">Completion Certificates</h2>
                        <p class="saas-hero-subtitle">
                            Generate, issue, and manage authenticated internship & employee completion certificates with QR verification.
                        </p>
                    </div>
                </div>
                <div class="col-lg-5 mt-3 mt-lg-0 text-lg-end">
                    <div class="saas-hero-actions">
                        <a href="<?php echo url('admin/signatories'); ?>" class="btn btn-saas-light">
                            <?php echo pika_icon('edit', '', 15); ?> Manage Signatories
                        </a>
                        <a href="<?php echo url('admin/generate-certificate'); ?>" class="btn btn-saas-dark">
                            <?php echo pika_icon('plus', '', 15); ?> Issue New Certificate
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Dynamic Metric Cards (Tiled Text, Squircle Icon, No Progress Bars) -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">TOTAL ISSUED</span>
                        <div class="metric-icon-badge purple">
                            <?php echo pika_icon('award', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $totalCerts; ?></div>
                    <div class="metric-label">Total Certificates Issued</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">ACTIVE & VERIFIED</span>
                        <div class="metric-icon-badge emerald">
                            <?php echo pika_icon('check-circle', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $activeCerts; ?></div>
                    <div class="metric-label">Active & Verified Credentials</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">REVOKED STATUS</span>
                        <div class="metric-icon-badge rose">
                            <?php echo pika_icon('x', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $revokedCerts; ?></div>
                    <div class="metric-label">Revoked Credentials</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">AUTHENTICATION</span>
                        <div class="metric-icon-badge sky">
                            <?php echo pika_icon('qr-code', '', 20); ?>
                        </div>
                    </div>
                    <div class="metric-value">100%</div>
                    <div class="metric-label">QR Verification Integrity</div>
                </div>
            </div>
        </div>

        <!-- Filter SaaS Toolbar -->
        <div class="saas-toolbar">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-lg-6 col-md-5">
                    <div class="saas-input-group">
                        <span class="saas-input-icon"><?php echo pika_icon('search', '', 16); ?></span>
                        <input type="text" name="search" class="saas-input" placeholder="Search by recipient name, certificate ID, or program..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-lg-4 col-md-4">
                    <select name="status" class="form-select saas-select">
                        <option value="">All Credential Statuses</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active & Verifiable</option>
                        <option value="revoked" <?php echo $statusFilter === 'revoked' ? 'selected' : ''; ?>>Revoked Credentials</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-saas-dark flex-fill">
                        <?php echo pika_icon('filter', '', 15); ?>
                        <span>Apply</span>
                    </button>
                    <?php if (!empty($search) || !empty($statusFilter)): ?>
                    <a href="<?php echo url('admin/certificates'); ?>" class="btn btn-saas-light" title="Reset Filters">
                        <?php echo pika_icon('refresh', '', 15); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Certificates Table Card -->
        <div class="saas-card">
            <div class="saas-card-header">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="saas-card-title">
                        <?php echo pika_icon('certificate', 'text-primary', 20); ?>
                        Issued Certificates Log
                    </h5>
                    <span class="badge bg-light text-dark border px-2 py-1 rounded-pill small">
                        <?php echo count($certificates); ?> records found
                    </span>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?php echo url('admin/signatories'); ?>" class="btn btn-sm btn-saas-light">
                        <?php echo pika_icon('signature', '', 14); ?>
                        <span>Manage Signatories</span>
                    </a>
                    <a href="<?php echo url('admin/generate-certificate'); ?>" class="btn btn-sm btn-saas-dark">
                        <?php echo pika_icon('plus', '', 14); ?>
                        <span>Issue Certificate</span>
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="saas-table">
                    <thead>
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
                            <td colspan="8" class="text-center py-5">
                                <?php echo pika_icon('certificate', 'opacity-25 d-block mx-auto mb-3', 48); ?>
                                <h6 class="fw-bold text-dark">No Certificates Issued Yet</h6>
                                <p class="text-muted small mb-3">Generate completion certificates with official executive signature and QR authentication.</p>
                                <a href="<?php echo url('admin/generate-certificate'); ?>" class="btn btn-saas-dark btn-sm">
                                    <?php echo pika_icon('plus', '', 14); ?>
                                    <span>Issue First Certificate</span>
                                </a>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($certificates as $cert): ?>
                            <tr>
                                <td>
                                    <span class="saas-id-badge"><?php echo htmlspecialchars($cert['certificate_id']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($cert['recipient_name']); ?></div>
                                    <?php if (!empty($cert['emp_code'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($cert['emp_code']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="saas-tag primary">
                                        <?php echo htmlspecialchars($cert['program_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark small"><?php echo htmlspecialchars($cert['duration_text']); ?></div>
                                    <small class="text-muted"><?php echo date('d M Y', strtotime($cert['start_date'])); ?> - <?php echo date('d M Y', strtotime($cert['end_date'])); ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark small"><?php echo htmlspecialchars($cert['signatory_name'] ?? 'Authorized Signatory'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($cert['signatory_title'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <span class="saas-tag <?php echo $cert['status'] === 'active' ? 'emerald' : 'rose'; ?>">
                                        <?php echo strtoupper($cert['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo url('verify-certificate/' . urlencode($cert['certificate_id'])); ?>" target="_blank" class="saas-tag sky text-decoration-none">
                                        <?php echo pika_icon('qr-code', '', 12); ?> Live Verification <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 10px;"></i>
                                    </a>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-light border-0 rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;">
                                            <?php echo pika_icon('more-vertical', 'text-secondary', 16); ?>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 py-2" style="border-radius: 14px; min-width: 200px; z-index: 99999;">
                                            <li>
                                                <a class="dropdown-item py-2" href="<?php echo url($cert['file_path']); ?>" target="_blank">
                                                    <?php echo pika_icon('eye', 'me-2 text-primary', 15); ?> View Document
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item py-2" href="<?php echo url($cert['file_path']); ?>" download>
                                                    <?php echo pika_icon('download', 'me-2 text-success', 15); ?> Download PDF
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item py-2" href="<?php echo url('verify-certificate/' . urlencode($cert['certificate_id'])); ?>" target="_blank">
                                                    <?php echo pika_icon('qr-code', 'me-2 text-info', 15); ?> Verify Online
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <?php if ($cert['status'] === 'active'): ?>
                                            <li>
                                                <a class="dropdown-item py-2 text-warning" href="<?php echo url('admin/certificates?action=revoke&id=' . $cert['id']); ?>" 
                                                   onclick="return confirm('Revoke credentials for <?php echo addslashes($cert['recipient_name']); ?>?');">
                                                    <?php echo pika_icon('x', 'me-2 text-warning', 15); ?> Revoke Credential
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                            <li>
                                                <a class="dropdown-item py-2 text-danger" href="<?php echo url('admin/certificates?action=delete&id=' . $cert['id']); ?>" 
                                                   onclick="return confirm('Are you sure you want to permanently DELETE certificate <?php echo addslashes($cert['certificate_id']); ?> for <?php echo addslashes($cert['recipient_name']); ?>? This action cannot be undone.');">
                                                    <?php echo pika_icon('trash', 'me-2 text-danger', 15); ?> Delete Certificate
                                                </a>
                                            </li>
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
