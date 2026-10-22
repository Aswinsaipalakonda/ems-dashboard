<?php
/**
 * Offer Letters Management
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

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h1 class="h3 font-weight-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Offer Letters</h1>
                <p class="text-muted mb-0">Generate and issue official, branded candidate and internship offer letters on Clientura letterhead.</p>
            </div>
            <div>
                <a href="<?php echo url('admin/generate-offer'); ?>" class="btn btn-primary">
                    <?php echo pika_icon('plus'); ?> Generate New Offer Letter
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
                        <?php echo pika_icon('offer-letter'); ?>
                    </div>
                    <div class="stats-info">
                        <h3><?php echo number_format($totalOffers); ?></h3>
                        <p>Total Offer Letters Issued</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon accent">
                        <?php echo pika_icon('certificate'); ?>
                    </div>
                    <div class="stats-info">
                        <h3><?php echo number_format($internOffers); ?></h3>
                        <p>Internship Positions</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon success">
                        <?php echo pika_icon('users'); ?>
                    </div>
                    <div class="stats-info">
                        <h3><?php echo number_format($fulltimeOffers); ?></h3>
                        <p>Full-Time Positions</p>
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
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Search by candidate name, offer ID, or role..." value="<?php echo htmlspecialchars($search); ?>" style="border-radius: 0 9999px 9999px 0;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="type" class="form-select" style="border-radius: 9999px;">
                            <option value="">All Employment Types</option>
                            <option value="internship" <?php echo $typeFilter === 'internship' ? 'selected' : ''; ?>>Internship</option>
                            <option value="fulltime" <?php echo $typeFilter === 'fulltime' ? 'selected' : ''; ?>>Full-Time</option>
                            <option value="parttime" <?php echo $typeFilter === 'parttime' ? 'selected' : ''; ?>>Part-Time</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-navy w-100">Filter</button>
                        <?php if (!empty($search) || !empty($typeFilter)): ?>
                            <a href="<?php echo url('admin/offer-letters'); ?>" class="btn btn-light" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Offer Letters Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="font-weight-bold text-dark">Issued Offer Letters Log</span>
                <span class="text-muted small"><?php echo count($offerLetters); ?> records found</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Offer ID</th>
                            <th>Candidate Name</th>
                            <th>Designation Role</th>
                            <th>Type & Duration</th>
                            <th>Tenure Period</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($offerLetters)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-envelope-open fs-1 d-block text-muted opacity-50 mb-2"></i>
                                    No offer letters generated yet.
                                    <div class="mt-2">
                                        <a href="<?php echo url('admin/generate-offer'); ?>" class="btn btn-primary btn-sm">Generate First Offer Letter</a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($offerLetters as $offer): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-primary border font-monospace">
                                            <?php echo htmlspecialchars($offer['offer_id']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold text-dark"><?php echo htmlspecialchars($offer['candidate_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($offer['candidate_email']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary">
                                            <?php echo htmlspecialchars($offer['designation']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?php echo ucfirst($offer['employment_type']); ?></span>
                                        <small class="d-block text-muted mt-1"><?php echo htmlspecialchars($offer['duration_text']); ?></small>
                                    </td>
                                    <td>
                                        <small class="text-dark font-weight-500 d-block"><?php echo date('d M Y', strtotime($offer['start_date'])); ?></small>
                                        <small class="text-muted">to <?php echo date('d M Y', strtotime($offer['end_date'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary"><?php echo htmlspecialchars($offer['location']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($offer['status'] === 'issued'): ?>
                                            <span class="badge bg-success-subtle text-success">Issued</span>
                                        <?php elseif ($offer['status'] === 'accepted'): ?>
                                            <span class="badge bg-primary-subtle text-primary">Accepted</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger">Revoked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 32px; height: 32px; padding: 0 !important;">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 14px;">
                                                <li>
                                                    <a class="dropdown-item" href="<?php echo url($offer['file_path']); ?>" target="_blank" download>
                                                        <i class="bi bi-download me-2 text-primary"></i> Download Offer PDF
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="<?php echo url($offer['file_path']); ?>" target="_blank">
                                                        <i class="bi bi-eye me-2 text-info"></i> View Document
                                                    </a>
                                                </li>
                                                <?php if ($offer['status'] !== 'revoked'): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="<?php echo url('admin/offer-letters?action=revoke&id=' . $offer['id']); ?>" onclick="return confirm('Are you sure you want to revoke this offer letter?');">
                                                            <i class="bi bi-x-circle me-2"></i> Revoke Offer
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
