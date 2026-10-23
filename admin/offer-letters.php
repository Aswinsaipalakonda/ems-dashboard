<?php
/**
 * Offer Letters Management - SaaS Document Hub
 * View, search, and issue dynamic offer letters matching template.pdf & offer.pdf
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . url('login'));
    exit;
}

$pageTitle = 'Offer Letters';
$successMsg = '';
$errorMsg = '';

// Handle Status Changes / Revoke
if (isset($_GET['action']) && $_GET['action'] === 'revoke' && isset($_GET['id'])) {
    $offerId = (int)$_GET['id'];
    executeQuery("UPDATE offer_letters SET status = 'revoked' WHERE id = ?", "i", [$offerId]);
    $successMsg = 'Offer letter revoked successfully.';
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delId = (int)$_GET['id'];
    $offerToDel = fetchOne("SELECT * FROM offer_letters WHERE id = ?", "i", [$delId]);
    if ($offerToDel) {
        if (!empty($offerToDel['file_path']) && file_exists(__DIR__ . '/../' . $offerToDel['file_path'])) {
            @unlink(__DIR__ . '/../' . $offerToDel['file_path']);
        }
        $altExt = str_ends_with($offerToDel['file_path'] ?? '', '.html') ? '.pdf' : '.html';
        $altPath = preg_replace('/\.(html|pdf)$/', $altExt, $offerToDel['file_path'] ?? '');
        if (!empty($altPath) && file_exists(__DIR__ . '/../' . $altPath)) {
            @unlink(__DIR__ . '/../' . $altPath);
        }
        executeQuery("DELETE FROM offer_letters WHERE id = ?", "i", [$delId]);
        logActivity('delete_offer_letter', "Deleted offer letter #{$offerToDel['offer_id']} for {$offerToDel['candidate_name']}");
        $successMsg = 'Offer letter deleted successfully.';
    }
}

// Handle Direct PDF Download
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['id'])) {
    $offerId = (int)$_GET['id'];
    $offer = fetchOne("SELECT * FROM offer_letters WHERE id = ?", "i", [$offerId]);
    if ($offer) {
        require_once __DIR__ . '/../includes/offer-pdf-builder.php';
        $filePath = $offer['file_path'];
        $htmlRelPath = preg_replace('/\.pdf$/', '.html', $filePath);
        $pdfRelPath = preg_replace('/\.html$/', '.pdf', $filePath);
        
        $htmlAbsPath = __DIR__ . '/../' . $htmlRelPath;
        $pdfAbsPath = __DIR__ . '/../' . $pdfRelPath;
        
        // If PDF doesn't exist but HTML does, generate exact 1-page PDF
        if ((!file_exists($pdfAbsPath) || filesize($pdfAbsPath) < 1000) && file_exists($htmlAbsPath)) {
            generateOfferLetterPDFFromHtml($htmlAbsPath, $pdfAbsPath);
        }
        
        if (file_exists($pdfAbsPath) && filesize($pdfAbsPath) > 1000) {
            $cleanFilename = 'Offer_Letter_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $offer['candidate_name']) . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $cleanFilename . '"');
            header('Content-Length: ' . filesize($pdfAbsPath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            readfile($pdfAbsPath);
            exit;
        } else if (file_exists($htmlAbsPath)) {
            header("Location: " . url($htmlRelPath . '?download=1'));
            exit;
        }
    }
}

// Search and filter
$search = trim($_GET['search'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');

$sql = "SELECT o.*, e.employee_id as emp_code 
        FROM offer_letters o
        LEFT JOIN employees e ON o.employee_id = e.id
        WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (o.candidate_name LIKE ? OR o.offer_id LIKE ? OR o.designation LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= "sss";
}

if (!empty($typeFilter)) {
    $sql .= " AND o.employment_type = ?";
    $params[] = $typeFilter;
    $types .= "s";
}

$sql .= " ORDER BY o.id DESC";
$offerLetters = fetchAll($sql, $types, $params);

// Stats
$totalOffers = fetchOne("SELECT COUNT(*) as c FROM offer_letters")['c'] ?? 0;
$internOffers = fetchOne("SELECT COUNT(*) as c FROM offer_letters WHERE employment_type = 'internship'")['c'] ?? 0;
$fulltimeOffers = fetchOne("SELECT COUNT(*) as c FROM offer_letters WHERE employment_type = 'fulltime'")['c'] ?? 0;
$activeOffers = fetchOne("SELECT COUNT(*) as c FROM offer_letters WHERE status != 'revoked'")['c'] ?? 0;
$issuanceRate = $totalOffers > 0 ? round(($activeOffers / $totalOffers) * 100) : 100;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Candidate Offer Letters</h1>
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
                <div class="col-xl-7 col-lg-6">
                    <div class="saas-hero-content">
                        <h2 class="saas-hero-title">Candidate Offer Letters</h2>
                        <p class="saas-hero-subtitle">
                            Generate and manage corporate internship and full-time employment agreements with dynamic letterhead formatting matching the official Clientura standards.
                        </p>
                        <div class="saas-hero-pills">
                            <span class="saas-pill live-pulse">
                                <span class="pulse-dot"></span>
                                <?php echo $totalOffers; ?> Letters Issued
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('briefcase', '', 14); ?>
                                <?php echo $internOffers; ?> Internships
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('award', '', 14); ?>
                                <?php echo $fulltimeOffers; ?> Full-Time
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-5 col-lg-6 mt-3 mt-lg-0 text-lg-end">
                    <div class="saas-hero-actions">
                        <a href="<?php echo url('admin/generate-offer'); ?>" class="btn btn-saas-dark">
                            <?php echo pika_icon('plus', '', 16); ?>
                            <span>Issue New Offer Letter</span>
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
                            <?php echo pika_icon('offer-letter', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active">Cumulative</span>
                    </div>
                    <div class="metric-value"><?php echo $totalOffers; ?></div>
                    <div class="metric-label">Total Offers Generated</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge amber">
                            <?php echo pika_icon('briefcase', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill warning">Interns</span>
                    </div>
                    <div class="metric-value"><?php echo $internOffers; ?></div>
                    <div class="metric-label">Internship Agreements</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge emerald">
                            <?php echo pika_icon('users', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active">Full-Time</span>
                    </div>
                    <div class="metric-value"><?php echo $fulltimeOffers; ?></div>
                    <div class="metric-label">Full-Time Roles</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge sky">
                            <?php echo pika_icon('check-circle', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill sky"><?php echo $issuanceRate; ?>%</span>
                    </div>
                    <div class="metric-value"><?php echo $activeOffers; ?></div>
                    <div class="metric-label">Active / Valid Offers</div>
                </div>
            </div>
        </div>

        <!-- Filter SaaS Toolbar -->
        <div class="saas-toolbar">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-lg-6 col-md-5">
                    <div class="saas-input-group">
                        <span class="saas-input-icon"><?php echo pika_icon('search', '', 16); ?></span>
                        <input type="text" name="search" class="saas-input" placeholder="Search by candidate name, offer ID, or position..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-lg-4 col-md-4">
                    <select name="type" class="form-select saas-select">
                        <option value="">All Employment Positions</option>
                        <option value="internship" <?php echo $typeFilter === 'internship' ? 'selected' : ''; ?>>Internship</option>
                        <option value="fulltime" <?php echo $typeFilter === 'fulltime' ? 'selected' : ''; ?>>Full-Time</option>
                        <option value="parttime" <?php echo $typeFilter === 'parttime' ? 'selected' : ''; ?>>Part-Time</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-saas-dark flex-fill">
                        <?php echo pika_icon('filter', '', 15); ?>
                        <span>Apply</span>
                    </button>
                    <?php if (!empty($search) || !empty($typeFilter)): ?>
                    <a href="<?php echo url('admin/offer-letters'); ?>" class="btn btn-saas-light" title="Reset Filters">
                        <?php echo pika_icon('refresh', '', 15); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Offer Letters Table Card -->
        <div class="saas-card">
            <div class="saas-card-header">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="saas-card-title">
                        <?php echo pika_icon('offer-letter', 'text-primary', 20); ?>
                        Issued Offer Letters Log
                    </h5>
                    <span class="badge bg-light text-dark border px-2 py-1 rounded-pill small">
                        <?php echo count($offerLetters); ?> Documents Found
                    </span>
                </div>
                <a href="<?php echo url('admin/generate-offer'); ?>" class="btn btn-sm btn-saas-dark">
                    <?php echo pika_icon('plus', '', 14); ?>
                    <span>Issue Offer</span>
                </a>
            </div>

            <div class="table-responsive">
                <table class="saas-table">
                    <thead>
                        <tr>
                            <th>Offer ID</th>
                            <th>Candidate</th>
                            <th>Designation Role</th>
                            <th>Category</th>
                            <th>Tenure Period</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($offerLetters)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <?php echo pika_icon('offer-letter', 'opacity-25 d-block mx-auto mb-3', 48); ?>
                                <h6 class="fw-bold text-dark">No Offer Letters Generated Yet</h6>
                                <p class="text-muted small mb-3">Deploy your first official candidate offer letter with Clientura letterhead.</p>
                                <a href="<?php echo url('admin/generate-offer'); ?>" class="btn btn-primary btn-sm">
                                    <?php echo pika_icon('plus', 'me-1', 14); ?> Generate First Offer
                                </a>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($offerLetters as $offer): ?>
                            <tr>
                                <td>
                                    <span class="saas-id-badge"><?php echo htmlspecialchars($offer['offer_id']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($offer['candidate_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($offer['candidate_email']); ?></small>
                                </td>
                                <td>
                                    <span class="saas-tag primary">
                                        <?php echo htmlspecialchars($offer['designation']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border rounded-pill px-2 py-1 small"><?php echo ucfirst($offer['employment_type']); ?></span>
                                    <small class="d-block text-muted mt-1"><?php echo htmlspecialchars($offer['duration_text']); ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark small"><?php echo date('d M Y', strtotime($offer['start_date'])); ?></div>
                                    <small class="text-muted">to <?php echo date('d M Y', strtotime($offer['end_date'])); ?></small>
                                </td>
                                <td>
                                    <span class="saas-tag sky"><?php echo htmlspecialchars($offer['location']); ?></span>
                                </td>
                                <td>
                                    <span class="saas-tag <?php 
                                        echo match($offer['status']) {
                                            'issued' => 'emerald',
                                            'accepted' => 'sky',
                                            default => 'rose'
                                        };
                                    ?>">
                                        <?php echo ucfirst($offer['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <?php
                                            $viewPath = $offer['file_path'];
                                            $htmlCandidate = preg_replace('/\.pdf$/', '.html', $viewPath);
                                            if (file_exists(__DIR__ . '/../' . $htmlCandidate)) {
                                                $viewPath = $htmlCandidate;
                                            }
                                        ?>
                                        <a href="<?php echo url($viewPath); ?>" target="_blank" class="saas-action-btn" title="View Document">
                                            <?php echo pika_icon('eye', '', 15); ?>
                                        </a>
                                        <a href="<?php echo url('admin/offer-letters?action=download&id=' . $offer['id']); ?>" class="saas-action-btn success" title="Download PDF Document">
                                            <?php echo pika_icon('download', '', 15); ?>
                                        </a>
                                        <?php if ($offer['status'] !== 'revoked'): ?>
                                        <a href="<?php echo url('admin/offer-letters?action=revoke&id=' . $offer['id']); ?>" 
                                           class="saas-action-btn warning" title="Revoke Offer"
                                           onclick="return confirm('Revoke official offer letter for <?php echo addslashes($offer['candidate_name']); ?>?');">
                                            <?php echo pika_icon('x', '', 15); ?>
                                        </a>
                                        <?php endif; ?>
                                        <a href="<?php echo url('admin/offer-letters?action=delete&id=' . $offer['id']); ?>" 
                                           class="saas-action-btn delete" title="Delete Offer Letter"
                                           onclick="return confirm('Permanently delete offer letter for <?php echo addslashes($offer['candidate_name']); ?>? This cannot be undone.');">
                                            <?php echo pika_icon('trash', '', 15); ?>
                                        </a>
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
