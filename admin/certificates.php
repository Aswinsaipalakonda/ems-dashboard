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

        <!-- Hero Hub -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="saas-hero-content">
                        <h2 class="saas-hero-title">Credential Verification Hub</h2>
                        <p class="saas-hero-subtitle">
                            Issue cryptographic certificates of completion with central QR codes linking directly to the live authentication verification portal on Clientura.
                        </p>
                        <div class="saas-hero-pills">
                            <span class="saas-pill live-pulse">
                                <span class="pulse-dot"></span>
                                <?php echo $totalCerts; ?> Total Certificates Issued
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('shield-check', '', 14); ?>
                                <?php echo $activeCerts; ?> Authenticated Credentials
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('qr-code', '', 14); ?>
                                Live QR Engine
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mt-3 mt-lg-0 text-lg-end">
                    <div class="saas-hero-actions">
                        <a href="<?php echo url('admin/signatories'); ?>" class="btn btn-outline-light">
                            <?php echo pika_icon('signature', 'me-1', 16); ?> Signatories
                        </a>
                        <a href="<?php echo url('admin/generate-certificate'); ?>" class="btn btn-primary btn-glow">
                            <?php echo pika_icon('plus', 'me-1', 16); ?> Issue Certificate
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
                            <?php echo pika_icon('certificate', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active">Total Issued</span>
                    </div>
                    <div class="metric-value"><?php echo $totalCerts; ?></div>
                    <div class="metric-label">Completed Certifications</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar navy" style="width: 100%;"></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge emerald">
                            <?php echo pika_icon('shield-check', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active"><?php echo $authRate; ?>% Valid</span>
                    </div>
                    <div class="metric-value"><?php echo $activeCerts; ?></div>
                    <div class="metric-label">Authenticated & Verifiable</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar emerald" style="width: <?php echo $authRate; ?>%;"></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge rose">
                            <?php echo pika_icon('x', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill danger">Revoked</span>
                    </div>
                    <div class="metric-value"><?php echo $revokedCerts; ?></div>
                    <div class="metric-label">Revoked Credentials</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar rose" style="width: <?php echo min(100, $revokedCerts * 15); ?>%;"></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge sky">
                            <?php echo pika_icon('qr-code', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill sky">Online</span>
                    </div>
                    <div class="metric-value">100%</div>
                    <div class="metric-label">QR Verification Integrity</div>
                    <div class="metric-progress">
                        <div class="metric-progress-bar sky" style="width: 100%;"></div>
                    </div>
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
                    <button type="submit" class="btn btn-primary flex-fill">
                        <?php echo pika_icon('filter', '', 15); ?> Apply
                    </button>
                    <?php if (!empty($search) || !empty($statusFilter)): ?>
                    <a href="<?php echo url('admin/certificates'); ?>" class="btn btn-outline-secondary" title="Reset Filters">
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
                        Issued Certificates Registry
                    </h5>
                    <span class="badge bg-light text-dark border px-2 py-1 rounded-pill small">
                        <?php echo count($certificates); ?> Credentials
                    </span>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?php echo url('admin/signatories'); ?>" class="btn btn-sm btn-outline-primary">
                        <?php echo pika_icon('signature', 'me-1', 14); ?> Manage Signatories
                    </a>
                    <a href="<?php echo url('admin/generate-certificate'); ?>" class="btn btn-sm btn-primary">
                        <?php echo pika_icon('plus', 'me-1', 14); ?> Issue Certificate
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
                            <th>Tenure Period</th>
                            <th>Signatory Executive</th>
                            <th>Status</th>
                            <th>Live Verification</th>
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
                                <a href="<?php echo url('admin/generate-certificate'); ?>" class="btn btn-primary btn-sm">
                                    <?php echo pika_icon('plus', 'me-1', 14); ?> Issue First Certificate
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
                                        <?php echo ucfirst($cert['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo url('verify-certificate/' . urlencode($cert['certificate_id'])); ?>" target="_blank" class="saas-tag sky text-decoration-none">
                                        <?php echo pika_icon('qr-code', '', 12); ?> Verify Online
                                    </a>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="<?php echo url($cert['file_path']); ?>" target="_blank" class="saas-action-btn" title="View Document">
                                            <?php echo pika_icon('eye', '', 15); ?>
                                        </a>
                                        <a href="<?php echo url($cert['file_path']); ?>" download class="saas-action-btn success" title="Download Document">
                                            <?php echo pika_icon('download', '', 15); ?>
                                        </a>
                                        <?php if ($cert['status'] === 'active'): ?>
                                        <a href="<?php echo url('admin/certificates?action=revoke&id=' . $cert['id']); ?>" 
                                           class="saas-action-btn delete" title="Revoke Certificate"
                                           onclick="return confirm('Revoke certificate credentials for <?php echo addslashes($cert['recipient_name']); ?>?');">
                                            <?php echo pika_icon('x', '', 15); ?>
                                        </a>
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
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
