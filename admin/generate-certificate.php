<?php
/**
 * Dynamic Certificate Generator
 * Real-time canvas preview, QR code generation, dynamic signatories (CEO, CTO, CMO), and high-res issuance.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . url('login'));
    exit;
}

$pageTitle = 'Issue Completion Certificate';

// Fetch active employees and interns
$employees = fetchAll("SELECT id, employee_id, name, email, designation, date_of_joining FROM employees WHERE status = 'active' ORDER BY name ASC");

// Fetch signatories
$signatories = fetchAll("SELECT id, name, designation, signature_path FROM signatories WHERE is_active = 1 ORDER BY id ASC");

// Handle POST Issue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_certificate') {
    $empId = !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
    $recipientName = trim($_POST['recipient_name'] ?? '');
    $programName = trim($_POST['program_name'] ?? '');
    $durationText = trim($_POST['duration_text'] ?? 'Six months');
    $startDate = $_POST['start_date'] ?? date('Y-m-d');
    $endDate = $_POST['end_date'] ?? date('Y-m-d');
    $issueDate = $_POST['issue_date'] ?? date('Y-m-d');
    $certificateId = trim($_POST['certificate_id'] ?? '');
    $signatoryId = (int)($_POST['signatory_id'] ?? 1);
    $imageData = $_POST['certificate_image_data'] ?? '';

    if (empty($recipientName) || empty($programName) || empty($certificateId) || empty($imageData)) {
        echo json_encode(['success' => false, 'message' => 'Required fields missing or certificate canvas not generated.']);
        exit;
    }

    // Check duplicate certificate ID
    $existing = fetchOne("SELECT id FROM certificates WHERE certificate_id = ?", "s", [$certificateId]);
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Certificate ID already exists. Please choose a unique ID.']);
        exit;
    }

    // Save certificate image from canvas base64
    $imageData = str_replace('data:image/png;base64,', '', $imageData);
    $imageData = str_replace(' ', '+', $imageData);
    $decodedImage = base64_decode($imageData);

    $safeFileName = 'cert_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $certificateId) . '_' . time() . '.png';
    $saveDir = UPLOAD_PATH . 'certificates/';
    if (!is_dir($saveDir)) {
        mkdir($saveDir, 0755, true);
    }
    $saveFilePath = $saveDir . $safeFileName;
    file_put_contents($saveFilePath, $decodedImage);

    $relFilePath = 'uploads/certificates/' . $safeFileName;
    $verificationToken = bin2hex(random_bytes(16));

    // Insert certificate
    $certDbId = insert(
        "INSERT INTO certificates (certificate_id, employee_id, recipient_name, program_name, duration_text, start_date, end_date, issue_date, signatory_id, file_path, verification_token, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)",
        "sissssssissi",
        [$certificateId, $empId, $recipientName, $programName, $durationText, $startDate, $endDate, $issueDate, $signatoryId, $relFilePath, $verificationToken, $_SESSION['user_id']]
    );

    if ($certDbId) {
        logActivity('issue_certificate', "Issued certificate #{$certificateId} to {$recipientName}");
        echo json_encode(['success' => true, 'message' => 'Certificate issued successfully!', 'redirect' => url('admin/certificates')]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error while saving certificate record.']);
    }
    exit;
}

// Generate default unique certificate ID
$randNum = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
$defaultCertId = 'CL-CERT-' . date('Y') . '-' . $randNum;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 font-weight-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Issue Completion Certificate</h1>
                <p class="text-muted mb-0">Live interactive preview with dynamic signatures and production QR verification link.</p>
            </div>
            <div>
                <a href="<?php echo url('admin/certificates'); ?>" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Certificates
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Generator Form -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <span class="font-weight-bold text-dark">Certificate Details</span>
                        <span class="badge bg-primary-subtle text-primary">Live Sync</span>
                    </div>
                    <div class="card-body p-4">
                        <form id="certForm">
                            <!-- Select User -->
                            <div class="mb-3">
                                <label class="form-label font-weight-bold text-dark small">Select Employee / Intern (Optional)</label>
                                <select class="form-select" id="empSelector" style="border-radius: 9999px;">
                                    <option value="">-- Choose Existing User or Enter Below --</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($emp['name']); ?>"
                                                data-role="<?php echo htmlspecialchars($emp['designation'] ?? 'Software Development Intern'); ?>"
                                                data-code="<?php echo htmlspecialchars($emp['employee_id']); ?>"
                                                data-joining="<?php echo $emp['date_of_joining'] ? date('Y-m-d', strtotime($emp['date_of_joining'])) : date('Y-m-d'); ?>">
                                            <?php echo htmlspecialchars($emp['name']); ?> (<?php echo htmlspecialchars($emp['employee_id']); ?> - <?php echo htmlspecialchars($emp['designation'] ?? 'Intern'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <input type="hidden" id="selectedEmpId" name="employee_id" value="">

                            <!-- Recipient Name -->
                            <div class="mb-3">
                                <label class="form-label font-weight-bold text-dark small">Recipient Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="recipientName" name="recipient_name" placeholder="e.g. JOHN DOE" value="" required style="border-radius: 12px; font-weight: 600;">
                            </div>

                            <!-- Program / Role -->
                            <div class="mb-3">
                                <label class="form-label font-weight-bold text-dark small">Program / Designation Role <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="programName" name="program_name" placeholder="e.g. Data analyst or Software Development Engineer" value="" required style="border-radius: 12px;">
                            </div>

                            <!-- Duration Text -->
                            <div class="mb-3">
                                <label class="form-label font-weight-bold text-dark small">Program Duration Text</label>
                                <input type="text" class="form-control" id="durationText" name="duration_text" placeholder="e.g. Six months or 45 days" value="" style="border-radius: 12px;">
                            </div>

                            <!-- Dates Row -->
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label font-weight-bold text-dark small">Start Date</label>
                                    <input type="date" class="form-control" id="startDate" name="start_date" value="" style="border-radius: 12px;">
                                </div>
                                <div class="col-6">
                                    <label class="form-label font-weight-bold text-dark small">End Date</label>
                                    <input type="date" class="form-control" id="endDate" name="end_date" value="" style="border-radius: 12px;">
                                </div>
                            </div>

                            <!-- Certificate ID & Issue Date -->
                            <div class="row g-2 mb-3">
                                <div class="col-7">
                                    <label class="form-label font-weight-bold text-dark small">Certificate ID <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control font-monospace" id="certificateId" name="certificate_id" value="<?php echo $defaultCertId; ?>" required style="border-radius: 12px; font-weight: 600; color: #0A1138;">
                                </div>
                                <div class="col-5">
                                    <label class="form-label font-weight-bold text-dark small">Issue Date</label>
                                    <input type="date" class="form-control" id="issueDate" name="issue_date" value="<?php echo date('Y-m-d'); ?>" style="border-radius: 12px;">
                                </div>
                            </div>

                            <!-- Dynamic Signatory Selection -->
                            <div class="mb-4">
                                <label class="form-label font-weight-bold text-dark small">Authorized Signatory <span class="text-danger">*</span></label>
                                <select class="form-select" id="signatorySelect" name="signatory_id" required style="border-radius: 9999px;">
                                    <?php foreach ($signatories as $idx => $sig): ?>
                                        <option value="<?php echo $sig['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($sig['name']); ?>"
                                                data-title="<?php echo htmlspecialchars($sig['designation']); ?>"
                                                data-sig="<?php echo !empty($sig['signature_path']) ? url($sig['signature_path']) : ''; ?>"
                                                <?php echo $idx === 0 ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sig['name']); ?> (<?php echo htmlspecialchars($sig['designation']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="mt-2 d-flex justify-content-between align-items-center">
                                    <small class="text-muted" id="sigStatusLabel">Signature loaded</small>
                                    <a href="<?php echo url('admin/signatories'); ?>" target="_blank" class="small text-primary text-decoration-none">Upload / Edit Signatures <i class="bi bi-box-arrow-up-right"></i></a>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <button type="button" class="btn btn-primary w-100 py-3 font-weight-bold" id="btnIssueCert">
                                <i class="bi bi-award me-1"></i> Issue & Generate Verified Certificate
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Live Interactive Canvas Preview -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm sticky-top" style="top: 90px;">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <span class="font-weight-bold text-dark">Live Certificate Preview</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success-subtle text-success">
                                <i class="bi bi-shield-check me-1"></i> QR Verified
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnDownloadPreview">
                                <i class="bi bi-download"></i> Download PNG
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-3 text-center" style="background: #F1F5F9; border-radius: 0 0 20px 20px;">
                        <div class="certificate-preview-container" style="box-shadow: 0 10px 30px rgba(0,0,0,0.12); border-radius: 12px; overflow: hidden; background: #FAF8EE; display: inline-block; max-width: 100%;">
                            <canvas id="certCanvas" width="2000" height="1414" style="width: 100%; height: auto; display: block;"></canvas>
                        </div>
                        <div class="mt-2 text-muted small">
                            <i class="bi bi-info-circle me-1"></i> QR Code encodes: <code class="text-primary font-weight-bold">https://ems.clientura.in/verify-certificate?id=<span id="qrUrlId"><?php echo $defaultCertId; ?></span></code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Local Standalone QR Code Library & Canvas Engine -->
<script src="<?php echo asset('assets/js/qrcode.min.js'); ?>"></script>

<script>
// Base Template & Assets
const templateImg = new Image();
templateImg.src = "<?php echo url('assets/img/certificate-canvas-base.png'); ?>";

const canvas = document.getElementById('certCanvas');
const ctx = canvas.getContext('2d');

let signatureImg = new Image();
let qrHolder = document.createElement('div');
let qrImageElement = null;

// Wait for template to load
templateImg.onload = function() {
    updateSignatureAndRender();
};

function getProductionVerifyUrl(certId) {
    return 'https://ems.clientura.in/verify-certificate?id=' + encodeURIComponent(certId);
}

function updateSignatureAndRender() {
    const sigSelect = document.getElementById('signatorySelect');
    const selectedOption = sigSelect.options[sigSelect.selectedIndex];
    const sigSrc = selectedOption.getAttribute('data-sig');
    const statusLabel = document.getElementById('sigStatusLabel');

    if (sigSrc && sigSrc.trim() !== '') {
        signatureImg = new Image();
        signatureImg.crossOrigin = 'anonymous';
        signatureImg.src = sigSrc;
        signatureImg.onload = function() {
            statusLabel.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Signature verified & attached</span>';
            renderCertificate();
        };
        signatureImg.onerror = function() {
            signatureImg = null;
            statusLabel.innerHTML = '<span class="text-muted"><i class="bi bi-dash-circle"></i> Text signature placeholder</span>';
            renderCertificate();
        };
    } else {
        signatureImg = null;
        statusLabel.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-circle"></i> No signature uploaded yet</span>';
        renderCertificate();
    }
}

// Generate dynamic QR Code and then draw onto canvas
function renderCertificate() {
    const certId = document.getElementById('certificateId').value.trim() || 'CL-CERT-000';
    document.getElementById('qrUrlId').innerText = certId;
    const verifyUrl = getProductionVerifyUrl(certId);

    qrHolder.innerHTML = '';
    try {
        new QRCode(qrHolder, {
            text: verifyUrl,
            width: 140,
            height: 140,
            colorDark: "#0A1138",
            colorLight: "#F5F5DC",
            correctLevel: QRCode.CorrectLevel.M
        });

        // davidshimjs creates an img or canvas
        setTimeout(function() {
            const c = qrHolder.querySelector('canvas');
            if (c) {
                qrImageElement = c;
            } else {
                const img = qrHolder.querySelector('img');
                if (img) qrImageElement = img;
            }
            drawCanvasContent();
        }, 50);
    } catch (e) {
        console.error("QR Code generation error:", e);
        drawCanvasContent();
    }
}

function drawCanvasContent() {
    if (!templateImg.complete) return;

    // 1. Draw base template (2000 x 1414)
    ctx.drawImage(templateImg, 0, 0, 2000, 1414);

    // Dynamic Values
    const rawName = document.getElementById('recipientName').value.trim();
    const name = (rawName || '[RECIPIENT NAME]').toUpperCase();
    const rawRole = document.getElementById('programName').value.trim();
    const role = rawRole || '[Program / Role]';
    const rawDuration = document.getElementById('durationText').value.trim();
    const duration = rawDuration || 'Six months';
    const sDateRaw = document.getElementById('startDate').value;
    const eDateRaw = document.getElementById('endDate').value;
    const sDate = sDateRaw ? formatDate(sDateRaw) : '[Start Date]';
    const eDate = eDateRaw ? formatDate(eDateRaw) : '[End Date]';
    const iDate = formatDate(document.getElementById('issueDate').value) || formatDate(new Date().toISOString().split('T')[0]);
    const certId = document.getElementById('certificateId').value.trim() || 'CL-CERT-000';

    const sigSelect = document.getElementById('signatorySelect');
    const sigOpt = sigSelect.options[sigSelect.selectedIndex];
    const sigName = (sigOpt ? sigOpt.getAttribute('data-name') : 'MR. P. SOWSHEEL').toUpperCase();
    const sigTitle = (sigOpt ? sigOpt.getAttribute('data-title') : 'FOUNDER & CEO CLIENTURA').toUpperCase();

    // 2. Headings (Using Inter font with calibrated reference baselines)
    ctx.textAlign = 'center';
    
    // "CERTIFICATE"
    ctx.font = '800 68px "Inter", sans-serif';
    ctx.fillStyle = '#0A1138';
    ctx.fillText('CERTIFICATE', 1000, 450);

    // "OF COMPLETION"
    ctx.font = '600 34px "Inter", sans-serif';
    ctx.letterSpacing = '7px';
    ctx.fillText('OF COMPLETION', 1000, 525);
    ctx.letterSpacing = '0px';

    // "THIS CERTIFICATE IS PRESENTED TO"
    ctx.font = '700 24px "Inter", sans-serif';
    ctx.letterSpacing = '2.5px';
    ctx.fillText('THIS CERTIFICATE IS PRESENTED TO', 1000, 615);
    ctx.letterSpacing = '0px';

    // 3. Recipient Name (Golden / Amber Serif)
    ctx.font = '700 70px "Playfair Display", "Times New Roman", "Inter", serif';
    ctx.fillStyle = rawName ? '#DF9E1D' : 'rgba(223, 158, 29, 0.45)';
    ctx.fillText(name, 1000, 750);

    // 4. Recognition Paragraph
    ctx.font = '400 26px "Inter", sans-serif';
    ctx.fillStyle = '#334155';
    const p1 = 'In recognition of her efforts and achievement in completing the';
    ctx.fillText(p1, 1000, 875);

    // Line 2: "{Duration} internship program as {Role}." perfectly centered
    const p2Prefix = duration + ' internship program as ';
    const p2Role = role + '.';
    
    ctx.font = '400 26px "Inter", sans-serif';
    const prefixW = ctx.measureText(p2Prefix).width;
    ctx.font = '700 26px "Inter", sans-serif';
    const roleW = ctx.measureText(p2Role).width;
    const totalW = prefixW + roleW;
    const startX = 1000 - (totalW / 2);

    ctx.textAlign = 'left';
    ctx.font = '400 26px "Inter", sans-serif';
    ctx.fillStyle = '#334155';
    ctx.fillText(p2Prefix, startX, 925);

    ctx.font = '700 26px "Inter", sans-serif';
    ctx.fillStyle = '#0A1138';
    ctx.fillText(p2Role, startX + prefixW, 925);

    // Line 3: "Conducted From START DATE - END DATE"
    ctx.textAlign = 'center';
    ctx.font = '700 26px "Inter", sans-serif';
    ctx.fillStyle = '#0A1138';
    ctx.fillText('Conducted From ' + sDate + ' - ' + eDate, 1000, 990);

    // 5. Bottom Left: Date of Issue & Certificate ID
    ctx.textAlign = 'left';
    ctx.fillStyle = '#0A1138';
    ctx.font = '500 25px "Inter", sans-serif';
    ctx.fillText('Date of Issue: ' + iDate, 180, 1210);
    ctx.font = '700 28px "Inter", sans-serif';
    ctx.fillText('ID: ' + certId, 180, 1255);

    // Draw QR Code on Bottom Left next to ID
    if (qrImageElement) {
        try {
            ctx.drawImage(qrImageElement, 540, 1145, 115, 115);
        } catch (err) {
            // In case image hasn't loaded yet
        }
    }

    // 6. Bottom Right: Signature, Line, Name, and Title
    ctx.textAlign = 'center';
    const sigCenterX = 1520;

    // Signature image if available
    if (signatureImg && signatureImg.complete && signatureImg.naturalWidth > 0) {
        const sigW = 200;
        const sigH = (signatureImg.naturalHeight / signatureImg.naturalWidth) * sigW;
        ctx.drawImage(signatureImg, sigCenterX - (sigW / 2), 1140 - sigH, sigW, Math.min(sigH, 90));
    } else {
        // Cursive script placeholder
        ctx.font = 'italic 34px "Brush Script MT", cursive, serif';
        ctx.fillStyle = '#0A1138';
        ctx.fillText(sigName, sigCenterX, 1130);
    }

    // Horizontal signature line
    ctx.beginPath();
    ctx.moveTo(sigCenterX - 180, 1150);
    ctx.lineTo(sigCenterX + 180, 1150);
    ctx.strokeStyle = '#0A1138';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    // Signatory Name & Title
    ctx.font = '800 26px "Inter", sans-serif';
    ctx.fillStyle = '#0A1138';
    ctx.fillText(sigName, sigCenterX, 1195);

    ctx.font = '600 20px "Inter", sans-serif';
    ctx.letterSpacing = '1px';
    ctx.fillText(sigTitle, sigCenterX, 1235);
    ctx.letterSpacing = '0px';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length === 3) {
        return parts[2] + '-' + parts[1] + '-' + parts[0];
    }
    return dateStr;
}

// Event Listeners for live form changes
['recipientName', 'programName', 'durationText', 'startDate', 'endDate', 'issueDate', 'certificateId'].forEach(id => {
    document.getElementById(id).addEventListener('input', renderCertificate);
});

document.getElementById('signatorySelect').addEventListener('change', updateSignatureAndRender);

// Employee Selector Auto-fill
document.getElementById('empSelector').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    if (this.value) {
        document.getElementById('selectedEmpId').value = this.value;
        document.getElementById('recipientName').value = selected.getAttribute('data-name') || '';
        const role = selected.getAttribute('data-role') || 'Software Development Engineer';
        document.getElementById('programName').value = role;
        const joining = selected.getAttribute('data-joining');
        const start = joining ? new Date(joining) : new Date(Date.now() - 180*24*60*60*1000);
        document.getElementById('startDate').value = start.toISOString().split('T')[0];
        document.getElementById('endDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('durationText').value = 'Six months';
        const empCode = selected.getAttribute('data-code');
        if (empCode) {
            document.getElementById('certificateId').value = empCode + '-CERT';
        }
        renderCertificate();
    } else {
        document.getElementById('selectedEmpId').value = '';
        document.getElementById('recipientName').value = '';
        document.getElementById('programName').value = '';
        document.getElementById('durationText').value = '';
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        renderCertificate();
    }
});

// Download PNG Preview
document.getElementById('btnDownloadPreview').addEventListener('click', function() {
    const link = document.createElement('a');
    link.download = (document.getElementById('certificateId').value.trim() || 'certificate') + '.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
});

// Submit and Issue Certificate
document.getElementById('btnIssueCert').addEventListener('click', function() {
    const btn = this;
    const certId = document.getElementById('certificateId').value.trim();
    const recipientName = document.getElementById('recipientName').value.trim();

    if (!recipientName || !certId) {
        alert('Please fill in the Recipient Name and Certificate ID.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Generating & Issuing...';

    // Get Base64 PNG from canvas
    const canvasData = canvas.toDataURL('image/png');

    const formData = new FormData();
    formData.append('action', 'save_certificate');
    formData.append('employee_id', document.getElementById('selectedEmpId').value);
    formData.append('recipient_name', recipientName);
    formData.append('program_name', document.getElementById('programName').value.trim());
    formData.append('duration_text', document.getElementById('durationText').value.trim());
    formData.append('start_date', document.getElementById('startDate').value);
    formData.append('end_date', document.getElementById('endDate').value);
    formData.append('issue_date', document.getElementById('issueDate').value);
    formData.append('certificate_id', certId);
    formData.append('signatory_id', document.getElementById('signatorySelect').value);
    formData.append('certificate_image_data', canvasData);

    fetch('<?php echo url("admin/generate-certificate"); ?>', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-award me-1"></i> Issue & Generate Verified Certificate';

        if (data.success) {
            alert('Certificate issued successfully!');
            window.location.href = data.redirect;
        } else {
            alert(data.message || 'Error issuing certificate.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-award me-1"></i> Issue & Generate Verified Certificate';
        alert('Server communication error. Check console logs.');
        console.error(err);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
