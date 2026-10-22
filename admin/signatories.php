<?php
/**
 * Signatories Management - SaaS Credential Sign-off Hub
 * Allows managing Founder & CEO, CTO, CMO signatures for certificates
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . url('login'));
    exit;
}

$pageTitle = 'Signatory Management';
$successMsg = '';
$errorMsg = '';

// Handle Signature Upload / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_signatory') {
        $signatoryId = (int)($_POST['signatory_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $designation = trim($_POST['designation'] ?? '');

        if (empty($name) || empty($designation)) {
            $errorMsg = 'Name and Designation cannot be empty.';
        } else {
            // Check if file uploaded
            $signaturePath = null;
            if (isset($_FILES['signature_image']) && $_FILES['signature_image']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['signature_image']['tmp_name'];
                $fileName = $_FILES['signature_image']['name'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                $allowed = ['png', 'webp', 'jpg', 'jpeg'];
                if (in_array($fileExt, $allowed)) {
                    $newFileName = 'sig_' . $signatoryId . '_' . time() . '.png';
                    $destPath = UPLOAD_PATH . 'signatures/' . $newFileName;
                    
                    // Auto-remove background using GD
                    $bgHandled = false;
                    if (function_exists('imagecreatefromstring')) {
                        $rawImg = @imagecreatefromstring(file_get_contents($fileTmp));
                        if ($rawImg) {
                            $w = imagesx($rawImg);
                            $h = imagesy($rawImg);
                            $transImg = imagecreatetruecolor($w, $h);
                            imagealphablending($transImg, false);
                            imagesavealpha($transImg, true);
                            $transparent = imagecolorallocatealpha($transImg, 0, 0, 0, 127);
                            imagefilledrectangle($transImg, 0, 0, $w, $h, $transparent);
                            
                            for ($x = 0; $x < $w; $x++) {
                                for ($y = 0; $y < $h; $y++) {
                                    $rgba = imagecolorat($rawImg, $x, $y);
                                    $r = ($rgba >> 16) & 0xFF;
                                    $g = ($rgba >> 8) & 0xFF;
                                    $b = $rgba & 0xFF;
                                    if ($r > 205 && $g > 205 && $b > 205) {
                                        imagesetpixel($transImg, $x, $y, $transparent);
                                    } else {
                                        $origColor = imagecolorallocatealpha($transImg, $r, $g, $b, 0);
                                        imagesetpixel($transImg, $x, $y, $origColor);
                                    }
                                }
                            }
                            imagepng($transImg, $destPath);
                            imagedestroy($rawImg);
                            imagedestroy($transImg);
                            $bgHandled = true;
                        }
                    }
                    if (!$bgHandled) {
                        move_uploaded_file($fileTmp, $destPath);
                    }
                    $signaturePath = 'uploads/signatures/' . $newFileName;
                } else {
                    $errorMsg = 'Invalid image format. Allowed: PNG, WEBP, JPG.';
                }
            }

            if (empty($errorMsg)) {
                if ($signaturePath) {
                    executeQuery("UPDATE signatories SET name = ?, designation = ?, signature_path = ? WHERE id = ?", "sssi", [$name, $designation, $signaturePath, $signatoryId]);
                } else {
                    executeQuery("UPDATE signatories SET name = ?, designation = ? WHERE id = ?", "ssi", [$name, $designation, $signatoryId]);
                }
                $successMsg = "Signatory profile for <strong>$name</strong> updated successfully.";
            }
        }
    }
}

// Fetch all signatories
$signatories = fetchAll("SELECT * FROM signatories ORDER BY id ASC");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Executive Signatories</h1>
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
                <div><?php echo $successMsg; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <?php echo pika_icon('x', 'flex-shrink-0', 20); ?>
                <div><?php echo htmlspecialchars($errorMsg); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Hero Hub -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="saas-hero-content">
                        <h2 class="saas-hero-title">Authorized Certificate Signatories</h2>
                        <p class="saas-hero-subtitle">
                            Configure executive officers (CEO, CTO, CMO) with automated background-isolated digital signatures rendered directly onto Clientura Completion Certificates.
                        </p>
                        <div class="saas-hero-pills">
                            <span class="saas-pill live-pulse">
                                <span class="pulse-dot"></span>
                                <?php echo count($signatories); ?> Configured Officers
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('signature', '', 14); ?>
                                Automatic Transparent Alpha Extraction
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mt-3 mt-lg-0 text-lg-end">
                    <div class="saas-hero-actions">
                        <a href="<?php echo url('admin/certificates'); ?>" class="btn btn-outline-light">
                            <?php echo pika_icon('certificate', 'me-1', 16); ?> View Certificates
                        </a>
                        <a href="<?php echo url('admin/generate-certificate'); ?>" class="btn btn-primary btn-glow">
                            <?php echo pika_icon('plus', 'me-1', 16); ?> Issue Certificate
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Signatories Cards Grid -->
        <div class="row g-4">
            <?php foreach ($signatories as $sig): ?>
            <div class="col-lg-4 col-md-6">
                <div class="saas-card h-100">
                    <div class="saas-card-header">
                        <span class="saas-id-badge">Signatory #<?php echo $sig['id']; ?></span>
                        <span class="saas-tag emerald">Active</span>
                    </div>
                    <div class="saas-card-body text-center p-4">
                        <div class="mb-3 d-flex justify-content-center align-items-center rounded-4 border border-dashed p-3" 
                             style="height: 120px; background: rgba(255, 165, 0, 0.05); border-color: rgba(255, 165, 0, 0.35) !important;">
                            <?php if (!empty($sig['signature_path']) && file_exists(__DIR__ . '/../' . $sig['signature_path'])): ?>
                                <img src="<?php echo url($sig['signature_path']); ?>" alt="Signature" style="max-height: 95px; max-width: 100%; object-fit: contain;">
                            <?php else: ?>
                                <div class="text-muted small">
                                    <?php echo pika_icon('signature', 'opacity-25 d-block mx-auto mb-1', 32); ?>
                                    No Signature Uploaded
                                </div>
                            <?php endif; ?>
                        </div>

                        <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($sig['name']); ?></h5>
                        <p class="text-muted small mb-4"><?php echo htmlspecialchars($sig['designation']); ?></p>

                        <button type="button" class="btn btn-outline-primary btn-sm w-100 rounded-pill" data-bs-toggle="modal" data-bs-target="#editSigModal<?php echo $sig['id']; ?>">
                            <?php echo pika_icon('edit', 'me-1', 14); ?> Edit & Upload Signature
                        </button>
                    </div>
                </div>
            </div>

            <!-- Edit Signatory Modal -->
            <div class="modal fade" id="editSigModal<?php echo $sig['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_signatory">
                            <input type="hidden" name="signatory_id" value="<?php echo $sig['id']; ?>">
                            
                            <div class="modal-header bg-navy text-white border-0 px-4 py-3" style="background: #0A1138;">
                                <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
                                    <?php echo pika_icon('signature', 'text-white', 20); ?>
                                    Update <?php echo htmlspecialchars($sig['designation']); ?>
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-dark small">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control rounded-pill px-3 py-2" value="<?php echo htmlspecialchars($sig['name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-dark small">Official Corporate Title / Role <span class="text-danger">*</span></label>
                                    <input type="text" name="designation" class="form-control rounded-pill px-3 py-2" value="<?php echo htmlspecialchars($sig['designation']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-dark small">Signature Image (PNG, JPG, WEBP)</label>
                                    <input type="file" name="signature_image" class="form-control rounded-pill px-3 py-2" accept="image/png,image/webp,image/jpeg">
                                    <small class="text-muted d-block mt-1">White or off-white backgrounds are automatically converted to transparent PNG.</small>
                                </div>
                            </div>
                            <div class="modal-footer bg-light border-0 px-4 py-3">
                                <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary btn-glow px-4">
                                    <?php echo pika_icon('check', 'me-1', 14); ?> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
