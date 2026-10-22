<?php
/**
 * Public Certificate Verification Portal
 * URL: https://ems.clientura.in/verify-certificate?id={certificate_id}
 * Or:  https://ems.clientura.in/verify-certificate/{certificate_id}
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/pikaicons.php';

$certId = trim($_GET['id'] ?? '');

// Also check path parameter if rewritten
if (empty($certId) && isset($_SERVER['REQUEST_URI'])) {
    $parts = explode('/verify-certificate/', $_SERVER['REQUEST_URI']);
    if (isset($parts[1])) {
        $certId = trim(explode('?', $parts[1])[0], '/');
    }
}

$certificate = null;
if (!empty($certId)) {
    $certificate = fetchOne(
        "SELECT c.*, s.name as signatory_name, s.designation as signatory_title, s.signature_path, e.employee_id as emp_code
         FROM certificates c
         LEFT JOIN signatories s ON c.signatory_id = s.id
         LEFT JOIN employees e ON c.employee_id = e.id
         WHERE c.certificate_id = ?",
        "s",
        [$certId]
    );
}

$pageTitle = 'Certificate Verification';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $certificate ? htmlspecialchars($certificate['recipient_name']) . ' - Verified Certificate' : 'Verify Certificate'; ?> | Clientura EMS</title>
    <link rel="icon" type="image/png" href="<?php echo asset('assets/img/clientura-with-bg.png'); ?>">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts: Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
    
    <style>
        body {
            background-color: #F8FAFC;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .verify-header {
            background: #FFFFFF;
            border-bottom: 1px solid rgba(10, 17, 56, 0.08);
            padding: 16px 0;
        }
        .seal-card {
            background: #FFFFFF;
            border-radius: 24px;
            border: 1px solid rgba(10, 17, 56, 0.08);
            box-shadow: 0 16px 40px -10px rgba(10, 17, 56, 0.06);
            overflow: hidden;
        }
        .verified-badge-banner {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .revoked-badge-banner {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .cert-info-label {
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #64748B;
            margin-bottom: 2px;
        }
        .cert-info-val {
            font-size: 16px;
            font-weight: 600;
            color: #0A1138;
        }
        .cert-preview-img {
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            max-width: 100%;
            height: auto;
            border: 1px solid rgba(10, 17, 56, 0.08);
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <header class="verify-header">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="https://ems.clientura.in/" class="d-flex align-items-center gap-2 text-decoration-none">
                <img src="<?php echo asset('assets/img/clientura-logo.png'); ?>" alt="Clientura" style="height: 42px; object-fit: contain;">
                <span style="font-family: 'Outfit', sans-serif; font-size: 20px; font-weight: 700; color: #0A1138;">Clientura EMS</span>
            </a>
            <div>
                <a href="<?php echo url('login'); ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Portal
                </a>
            </div>
        </div>
    </header>

    <!-- Main Verification Content -->
    <main class="container my-5 flex-grow-1">
        <?php if ($certificate): ?>
            <div class="seal-card mb-5">
                <?php if ($certificate['status'] === 'active'): ?>
                    <div class="verified-badge-banner">
                        <div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; color: #10b981; flex-shrink: 0;">
                            <i class="bi bi-patch-check-fill fs-3"></i>
                        </div>
                        <div>
                            <h4 class="mb-0 font-weight-bold" style="font-family: 'Outfit', sans-serif;">Verified Authentic Credential</h4>
                            <p class="mb-0 opacity-90 small">This certificate was officially issued and authenticated by Clientura.</p>
                        </div>
                        <div class="ms-auto d-none d-md-block">
                            <span class="badge bg-white text-success px-3 py-2 font-monospace fs-6">ID: <?php echo htmlspecialchars($certificate['certificate_id']); ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="revoked-badge-banner">
                        <div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; color: #ef4444; flex-shrink: 0;">
                            <i class="bi bi-exclamation-triangle-fill fs-3"></i>
                        </div>
                        <div>
                            <h4 class="mb-0 font-weight-bold">Revoked Credential</h4>
                            <p class="mb-0 opacity-90 small">This certificate has been revoked by the issuing authority and is no longer valid.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="p-4 p-md-5">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-7">
                            <h2 class="h3 font-weight-bold mb-1" style="font-family: 'Outfit', sans-serif; color: #0A1138;">
                                <?php echo htmlspecialchars($certificate['recipient_name']); ?>
                            </h2>
                            <p class="text-muted mb-4 fs-6">
                                Successfully completed the <strong class="text-dark"><?php echo htmlspecialchars($certificate['duration_text']); ?></strong> internship program as <strong class="text-primary"><?php echo htmlspecialchars($certificate['program_name']); ?></strong>.
                            </p>

                            <div class="row g-3 mb-4">
                                <div class="col-sm-6">
                                    <div class="cert-info-label">Emp ID</div>
                                    <div class="cert-info-val font-monospace text-dark"><?php echo htmlspecialchars($certificate['emp_code'] ?: 'N/A'); ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="cert-info-label">Date of Issue</div>
                                    <div class="cert-info-val"><?php echo date('d F Y', strtotime($certificate['issue_date'])); ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="cert-info-label">Certificate ID</div>
                                    <div class="cert-info-val font-monospace text-primary"><?php echo htmlspecialchars($certificate['certificate_id']); ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="cert-info-label">Program Period</div>
                                    <div class="cert-info-val"><?php echo date('d M Y', strtotime($certificate['start_date'])); ?> - <?php echo date('d M Y', strtotime($certificate['end_date'])); ?></div>
                                </div>
                                <div class="col-12">
                                    <div class="cert-info-label">Issuing Authority</div>
                                    <div class="cert-info-val"><?php echo htmlspecialchars($certificate['signatory_name'] ?? 'Authorized Signatory'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($certificate['signatory_title'] ?? ''); ?></small>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-3">
                                <?php if (!empty($certificate['file_path']) && file_exists(__DIR__ . '/' . $certificate['file_path'])): ?>
                                    <a href="<?php echo url($certificate['file_path']); ?>" download class="btn btn-primary px-4">
                                        <i class="bi bi-download me-1"></i> Download Original Certificate
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-secondary px-4" onclick="window.print()">
                                    <i class="bi bi-printer me-1"></i> Print Verification
                                </button>
                            </div>
                        </div>

                        <div class="col-lg-5 text-center">
                            <?php if (!empty($certificate['file_path']) && file_exists(__DIR__ . '/' . $certificate['file_path'])): ?>
                                <img src="<?php echo url($certificate['file_path']); ?>" alt="Certificate Preview" class="cert-preview-img">
                            <?php else: ?>
                                <div class="p-5 border rounded-3 bg-light text-muted">
                                    <i class="bi bi-file-earmark-check fs-1"></i>
                                    <div class="mt-2 font-weight-bold">Digital Certificate Verified</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="seal-card p-5 text-center max-w-700 mx-auto my-5">
                <div class="rounded-circle bg-warning-subtle d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px; color: #d97706;">
                    <i class="bi bi-search fs-2"></i>
                </div>
                <h3 class="font-weight-bold text-dark mb-2" style="font-family: 'Outfit', sans-serif;">Verify a Completion Certificate</h3>
                <p class="text-muted mb-4">Enter a Certificate ID to verify its authenticity and view official credentials issued by Clientura.</p>

                <form method="GET" action="<?php echo url('verify-certificate'); ?>" class="row g-2 justify-content-center">
                    <div class="col-md-7">
                        <input type="text" name="id" class="form-control" placeholder="Enter certificate ID" required style="border-radius: 9999px; padding: 12px 20px;">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-shield-check me-1"></i> Verify Now
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-top py-4 text-center text-muted small mt-auto">
        <div class="container">
            <p class="mb-1 font-weight-bold" style="color: #0A1138;">© <?php echo date('Y'); ?> Clientura EMS. All Rights Reserved.</p>
            <p class="mb-0 text-muted">Credential Authentication System & QR Verification Service</p>
        </div>
    </footer>
</body>
</html>
