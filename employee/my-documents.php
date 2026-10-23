<?php
/**
 * Employee Documents Portal
 * View and download official Offer Letters and Completion Certificates with QR Verification
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Check employee authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: " . url('login'));
    exit;
}

$pageTitle = 'My Official Documents';
$userId = (int)$_SESSION['user_id'];
$userEmail = $_SESSION['user_email'] ?? '';

// Handle Direct Offer Letter View / Download for Employee
if (isset($_GET['action']) && in_array($_GET['action'], ['view_offer_pdf', 'download_offer']) && isset($_GET['id'])) {
    $offerId = (int)$_GET['id'];
    $offer = fetchOne(
        "SELECT * FROM offer_letters WHERE id = ? AND (employee_id = ? OR candidate_email = ? OR candidate_name LIKE ?)",
        "iiss",
        [$offerId, $userId, $userEmail, '%' . $_SESSION['user_name'] . '%']
    );
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

        $isDownload = ($_GET['action'] === 'download_offer');

        if (file_exists($pdfAbsPath) && filesize($pdfAbsPath) > 1000) {
            $cleanFilename = 'Offer_Letter_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $offer['candidate_name']) . '.pdf';
            header('Content-Type: application/pdf');
            $disposition = $isDownload ? 'attachment' : 'inline';
            header('Content-Disposition: ' . $disposition . '; filename="' . $cleanFilename . '"');
            header('Content-Length: ' . filesize($pdfAbsPath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            readfile($pdfAbsPath);
            exit;
        } else if (file_exists($htmlAbsPath)) {
            $suffix = $isDownload ? '?download=1' : '';
            header("Location: " . url($htmlRelPath . $suffix));
            exit;
        }
    }
}

// Fetch Certificates issued to this user
$certificates = fetchAll(
    "SELECT c.*, s.name as signatory_name, s.designation as signatory_title 
     FROM certificates c
     LEFT JOIN signatories s ON c.signatory_id = s.id
     WHERE c.employee_id = ? OR c.recipient_name LIKE ?
     ORDER BY c.id DESC",
    "is",
    [$userId, '%' . $_SESSION['user_name'] . '%']
);

// Fetch Offer Letters issued to this user
$offerLetters = fetchAll(
    "SELECT o.* 
     FROM offer_letters o
     WHERE o.employee_id = ? OR o.candidate_email = ? OR o.candidate_name LIKE ?
     ORDER BY o.id DESC",
    "iss",
    [$userId, $userEmail, '%' . $_SESSION['user_name'] . '%']
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/employee-sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 font-weight-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">My Official Documents</h1>
                <p class="text-muted mb-0">Access and download your verified completion certificates and official offer letters.</p>
            </div>
            <div>
                <span class="badge bg-success-subtle text-success px-3 py-2 font-weight-bold fs-6">
                    <i class="bi bi-shield-check me-1"></i> Verified Credentials
                </span>
            </div>
        </div>

        <!-- Section 1: Completion Certificates -->
        <div class="card border-0 shadow-sm mb-5">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <div class="stats-icon accent" style="width: 36px; height: 36px; font-size: 18px; margin: 0;">
                        <?php echo pika_icon('certificate'); ?>
                    </div>
                    <span class="font-weight-bold text-dark fs-5">Verified Completion Certificates</span>
                </div>
                <span class="badge bg-light text-dark border"><?php echo count($certificates); ?> Available</span>
            </div>
            <div class="card-body p-4">
                <?php if (empty($certificates)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-award fs-1 d-block text-muted opacity-50 mb-2"></i>
                        No completion certificates have been issued yet. Once your program completes, your certificate will appear here.
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($certificates as $cert): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 shadow-sm border-0" style="border-radius: 18px; background: #FAF8EE;">
                                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="badge bg-white text-primary border font-monospace"><?php echo htmlspecialchars($cert['certificate_id']); ?></span>
                                                <?php if ($cert['status'] === 'active'): ?>
                                                    <span class="badge bg-success text-white">Active & Verified</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger text-white">Revoked</span>
                                                <?php endif; ?>
                                            </div>

                                            <h5 class="font-weight-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">
                                                <?php echo htmlspecialchars($cert['program_name']); ?>
                                            </h5>
                                            <p class="text-muted small mb-2">Duration: <?php echo htmlspecialchars($cert['duration_text']); ?></p>
                                            <p class="text-muted small mb-3">Issued: <?php echo date('d M Y', strtotime($cert['issue_date'])); ?></p>
                                        </div>

                                        <div class="pt-3 border-top d-flex gap-2">
                                            <a href="<?php echo url($cert['file_path']); ?>" download class="btn btn-primary btn-sm flex-grow-1">
                                                <i class="bi bi-download me-1"></i> Download
                                            </a>
                                            <a href="<?php echo url('verify-certificate/' . urlencode($cert['certificate_id'])); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-qr-code me-1"></i> Verify Link
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section 2: Offer Letters -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <div class="stats-icon primary" style="width: 36px; height: 36px; font-size: 18px; margin: 0;">
                        <?php echo pika_icon('offer-letter'); ?>
                    </div>
                    <span class="font-weight-bold text-dark fs-5">Official Offer Letters</span>
                </div>
                <span class="badge bg-light text-dark border"><?php echo count($offerLetters); ?> Available</span>
            </div>
            <div class="card-body p-4">
                <?php if (empty($offerLetters)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-envelope-open fs-1 d-block text-muted opacity-50 mb-2"></i>
                        No official offer letters on record for your profile.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Offer ID</th>
                                    <th>Position Role</th>
                                    <th>Type</th>
                                    <th>Duration & Dates</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($offerLetters as $offer): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-primary border font-monospace">
                                                <?php echo htmlspecialchars($offer['offer_id']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="font-weight-bold text-dark"><?php echo htmlspecialchars($offer['designation']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?php echo ucfirst($offer['employment_type']); ?></span>
                                        </td>
                                        <td>
                                            <small class="d-block text-dark font-weight-500"><?php echo htmlspecialchars($offer['duration_text']); ?></small>
                                            <small class="text-muted"><?php echo date('d M Y', strtotime($offer['start_date'])); ?> - <?php echo date('d M Y', strtotime($offer['end_date'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary"><?php echo htmlspecialchars($offer['location']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success"><?php echo ucfirst($offer['status']); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <?php
                                                $viewPath = $offer['file_path'];
                                                $htmlCandidate = preg_replace('/\.pdf$/', '.html', $viewPath);
                                                if (file_exists(__DIR__ . '/../' . $htmlCandidate)) {
                                                    $viewPath = $htmlCandidate;
                                                }
                                            ?>
                                            <div class="d-inline-flex gap-2">
                                                <a href="<?php echo url($viewPath); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-eye me-1"></i> View Offer Letter
                                                </a>
                                                <a href="<?php echo url('employee/my-documents?action=download_offer&id=' . $offer['id']); ?>" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-download me-1"></i> Download PDF
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
