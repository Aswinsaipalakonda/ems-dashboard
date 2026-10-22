<?php
/**
 * Signatories Management
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
                                        $pixelColor = imagecolorallocatealpha($transImg, $r, $g, $b, 0);
                                        imagesetpixel($transImg, $x, $y, $pixelColor);
                                    }
                                }
                            }
                            imagepng($transImg, $destPath);
                            imagedestroy($transImg);
                            imagedestroy($rawImg);
                            $bgHandled = true;
                            $signaturePath = 'uploads/signatures/' . $newFileName;
                        }
                    }
                    if (!$bgHandled) {
                        if (move_uploaded_file($fileTmp, $destPath)) {
                            $signaturePath = 'uploads/signatures/' . $newFileName;
                        }
                    }
                } else {
                    $errorMsg = 'Only PNG, WebP or JPG signature images allowed.';
                }
            }

            if (empty($errorMsg)) {
                if ($signaturePath) {
                    executeQuery("UPDATE signatories SET name = ?, designation = ?, signature_path = ? WHERE id = ?", "sssi", [$name, $designation, $signaturePath, $signatoryId]);
                } else {
                    executeQuery("UPDATE signatories SET name = ?, designation = ? WHERE id = ?", "ssi", [$name, $designation, $signatoryId]);
                }
                $successMsg = 'Signatory updated successfully!';
            }
        }
    }
}

$signatories = fetchAll("SELECT * FROM signatories ORDER BY id ASC");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 font-weight-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Authorized Signatories</h1>
                <p class="text-muted mb-0">Manage authorized signatories and official signatures for Completion Certificates.</p>
            </div>
            <div>
                <a href="<?php echo url('admin/certificates'); ?>" class="btn btn-outline-primary">
                    <?php echo pika_icon('certificate'); ?> Back to Certificates
                </a>
            </div>
        </div>

        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-pill px-4" role="alert">
                <i class="bi bi-check-circle me-2"></i> <?php echo htmlspecialchars($successMsg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-pill px-4" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($errorMsg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Signatories Cards Grid -->
        <div class="row g-4">
            <?php foreach ($signatories as $sig): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                            <span class="badge bg-light text-primary border">Signatory #<?php echo $sig['id']; ?></span>
                            <span class="badge bg-success-subtle text-success">Active</span>
                        </div>
                        <div class="card-body text-center p-4">
                            <div class="mb-3 d-flex justify-content-center align-items-center" style="height: 120px; background: #FFF9F2; border: 1.5px dashed #FFD8A8; border-radius: 16px; overflow: hidden; padding: 10px;">
                                <?php if (!empty($sig['signature_path']) && file_exists(__DIR__ . '/../' . $sig['signature_path'])): ?>
                                    <img src="<?php echo url($sig['signature_path']); ?>" alt="Signature" style="max-height: 100px; max-width: 100%; object-fit: contain;">
                                <?php else: ?>
                                    <div class="text-muted small">
                                        <i class="bi bi-pen fs-3 d-block text-warning mb-1"></i>
                                        No Signature Uploaded
                                    </div>
                                <?php endif; ?>
                            </div>

                            <h5 class="font-weight-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;"><?php echo htmlspecialchars($sig['name']); ?></h5>
                            <p class="text-muted small mb-3"><?php echo htmlspecialchars($sig['designation']); ?></p>

                            <button type="button" class="btn btn-outline-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#editSigModal<?php echo $sig['id']; ?>">
                                <i class="bi bi-upload me-1"></i> Edit & Upload Signature
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div class="modal fade" id="editSigModal<?php echo $sig['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden;">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_signatory">
                                <input type="hidden" name="signatory_id" value="<?php echo $sig['id']; ?>">
                                
                                <div class="modal-header border-0 bg-light p-4">
                                    <h5 class="modal-title font-weight-bold text-dark">Update <?php echo htmlspecialchars($sig['designation']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="mb-3">
                                        <label class="form-label font-weight-bold text-dark small">Full Name</label>
                                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($sig['name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label font-weight-bold text-dark small">Official Title / Designation</label>
                                        <input type="text" name="designation" class="form-control" value="<?php echo htmlspecialchars($sig['designation']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label font-weight-bold text-dark small">Upload Signature (Transparent PNG Recommended)</label>
                                        <input type="file" name="signature_image" class="form-control" accept="image/png,image/webp,image/jpeg">
                                        <div class="form-text small">Upload high-contrast signature on transparent or white background.</div>
                                    </div>
                                </div>
                                <div class="modal-footer border-0 p-4 pt-0">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
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
