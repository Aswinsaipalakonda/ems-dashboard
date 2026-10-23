<?php
/**
 * Dynamic Offer Letter Generator
 * Generates official Clientura Offer Letters on branded letterhead matching template.pdf & offer.pdf
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . url('login'));
    exit;
}

$pageTitle = 'Generate Offer Letter';

// Fetch active employees/interns for quick auto-fill
$employees = fetchAll("SELECT id, employee_id, name, email, designation, date_of_joining FROM employees WHERE status = 'active' ORDER BY name ASC");

// Handle POST Issue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_offer') {
    $empId = !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
    $candidateName = trim($_POST['candidate_name'] ?? '');
    $candidateEmail = trim($_POST['candidate_email'] ?? '');
    $designation = trim($_POST['designation'] ?? 'Software Development Engineer (SDE) Intern');
    $roleCode = trim($_POST['role_code'] ?? 'SDE');
    $empType = $_POST['employment_type'] ?? 'internship';
    $durationText = trim($_POST['duration_text'] ?? '45 days');
    $startDate = $_POST['start_date'] ?? date('Y-m-d');
    $endDate = $_POST['end_date'] ?? date('Y-m-d', strtotime('+45 days'));
    $location = trim($_POST['location'] ?? 'Hybrid');
    $offerId = trim($_POST['offer_id'] ?? '');
    $responsibilities = trim($_POST['responsibilities'] ?? '');
    $terms = trim($_POST['terms'] ?? '');

    if (empty($candidateName) || empty($designation) || empty($offerId)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // Check duplicate offer ID
    $existing = fetchOne("SELECT id FROM offer_letters WHERE offer_id = ?", "s", [$offerId]);
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Offer ID already exists. Please choose a unique ID.']);
        exit;
    }

    // Render standalone branded HTML document with letterhead and printable styles
    $issueDateFormatted = date('d-m-Y');
    $startDateFormatted = date('d-m-Y', strtotime($startDate));
    $endDateFormatted = date('d-m-Y', strtotime($endDate));

    $respItems = array_filter(array_map('trim', explode("\n", $responsibilities)));
    $termsItems = array_filter(array_map('trim', explode("\n", $terms)));

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($candidateName); ?> - Offer Letter | Clientura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="<?php echo asset('assets/js/html2pdf.bundle.min.js'); ?>"></script>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 0;
            background: #E2E8F0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .offer-page {
            width: 210mm;
            height: 296mm;
            max-height: 296mm;
            margin: 20px auto;
            position: relative;
            background-color: #FFFFFF;
            background-image: url('<?php echo asset('assets/img/offer-letterhead-template.png'); ?>');
            background-size: 100% 100%;
            background-repeat: no-repeat;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            padding: 130px 42px 35px 42px;
            color: #000000;
            font-size: 12.8px;
            line-height: 1.55;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            box-sizing: border-box;
            overflow: hidden;
            page-break-inside: avoid !important;
            page-break-after: avoid !important;
        }
        .offer-title {
            text-align: center;
            font-size: 19px;
            font-weight: 800;
            margin: 0 0 20px 0;
            color: #000000;
            letter-spacing: -0.2px;
        }
        .meta-line {
            font-weight: 700;
            margin-bottom: 4px;
        }
        .bold-text {
            font-weight: 700;
        }
        p {
            margin: 8px 0;
            text-align: justify;
            text-justify: inter-word;
        }
        ul {
            margin: 4px 0 10px 22px;
            padding: 0;
            list-style-type: disc;
            text-align: justify;
            text-justify: inter-word;
        }
        li {
            margin-bottom: 3px;
        }
        .section-title {
            font-weight: 700;
            margin-top: 14px;
            margin-bottom: 4px;
        }
        .sign-row {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-bottom: 8px;
            padding-right: 10px;
        }
        .cand-sign {
            font-weight: 700;
            font-size: 12.5px;
        }
        @media print {
            body {
                background: transparent;
            }
            .no-print {
                display: none !important;
            }
            .offer-page {
                margin: 0 !important;
                box-shadow: none !important;
                width: 210mm !important;
                height: 296mm !important;
                max-height: 296mm !important;
                overflow: hidden !important;
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="background: #0A1138; color: white; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 999;">
        <div style="font-weight: 600;">Clientura Official Offer Letter</div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <button id="btnDownloadOfferPDF" onclick="downloadOfferLetterPDF()" style="background: #2563EB; color: #FFFFFF; border: none; padding: 8px 22px; font-weight: 700; border-radius: 9999px; cursor: pointer; transition: all 0.2s;">
                Download PDF
            </button>
            <button onclick="window.print()" style="background: #FFA500; color: #0A1138; border: none; padding: 8px 22px; font-weight: 700; border-radius: 9999px; cursor: pointer;">
                Print / Save as PDF
            </button>
        </div>
    </div>

    <div class="offer-page">
        <div class="offer-title"><?php echo ucfirst($empType); ?> Offer Letter<?php echo !empty(trim($roleCode)) ? ' - ' . htmlspecialchars(trim($roleCode)) : ''; ?></div>

        <div class="meta-line">Date: <?php echo $issueDateFormatted; ?></div>
        <div class="meta-line">Subject: Offer Letter for <?php echo htmlspecialchars($designation); ?></div>

        <p style="margin-top: 10px;"><span class="bold-text">Dear <?php echo htmlspecialchars($candidateName); ?>,</span></p>

        <p>
            We are pleased to offer you the position of <span class="bold-text"><?php echo htmlspecialchars($designation); ?></span> at Clientura, for a duration of <span class="bold-text"><?php echo htmlspecialchars($durationText); ?></span>, starting from <span class="bold-text"><?php echo $startDateFormatted; ?></span> to <span class="bold-text"><?php echo $endDateFormatted; ?></span>. This <?php echo htmlspecialchars($empType); ?> is part of our commitment to nurturing talented individuals and providing practical exposure to modern software engineering practices.
        </p>

        <p class="bold-text" style="margin-top: 10px;">
            During your <?php echo htmlspecialchars($empType); ?>, you will work closely with our Engineering Team on real-world software development projects involving:
        </p>
        <ul>
            <?php foreach ($respItems as $item): ?>
                <li><?php echo htmlspecialchars($item); ?></li>
            <?php endforeach; ?>
        </ul>

        <p>
            You are expected to maintain a high level of professionalism, meet project deadlines, actively collaborate with team members, and contribute to the successful delivery of software solutions.
        </p>

        <div class="section-title"><?php echo ucfirst($empType); ?> Details:</div>
        <p style="margin: 2px 0;"><span class="bold-text">Position:</span> <?php echo htmlspecialchars($designation); ?></p>
        <p style="margin: 2px 0;"><span class="bold-text">Location:</span> <?php echo htmlspecialchars($location); ?></p>
        <p style="margin: 2px 0;"><span class="bold-text">Start Date:</span> <?php echo $startDateFormatted; ?></p>
        <p style="margin: 2px 0;"><span class="bold-text">End Date:</span> <?php echo $endDateFormatted; ?></p>

        <div class="section-title" style="margin-top: 12px;">Terms & Conditions:</div>
        <ul>
            <?php foreach ($termsItems as $term): ?>
                <li><?php echo htmlspecialchars($term); ?></li>
            <?php endforeach; ?>
        </ul>

        <p>
            To confirm your acceptance of this offer, please reply to this email. Upon receiving your confirmation, we will proceed with the onboarding process and provide further details regarding your <?php echo htmlspecialchars($empType); ?> responsibilities and project assignments.
        </p>

        <p>
            We look forward to welcoming you to our team and supporting your professional growth as a <?php echo htmlspecialchars($designation); ?>.
        </p>

        <div class="sign-row">
            <div>
                <p style="margin-bottom: 2px;"><span class="bold-text">Warm regards,</span></p>
                <p style="margin-top: 0;"><span class="bold-text">Clientura</span></p>
            </div>
            <div class="cand-sign">
                Signature of candidate
            </div>
        </div>
    </div>

    <script>
    function downloadOfferLetterPDF() {
        const btn = document.getElementById('btnDownloadOfferPDF');
        if (btn) {
            btn.disabled = true;
            btn.innerText = 'Generating PDF...';
        }
        const element = document.querySelector('.offer-page');
        const opt = {
            margin: 0,
            filename: '<?php echo preg_replace('/[^a-zA-Z0-9_-]/', '_', $candidateName); ?>_Offer_Letter.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, scrollY: 0, scrollX: 0 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };
        html2pdf()
            .set(opt)
            .from(element)
            .toPdf()
            .get('pdf')
            .then(function(pdf) {
                const totalPages = pdf.internal.getNumberOfPages();
                if (totalPages > 1) {
                    for (let i = totalPages; i > 1; i--) {
                        pdf.deletePage(i);
                    }
                }
            })
            .save()
            .then(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerText = 'Download PDF';
                }
            })
            .catch(err => {
                console.error(err);
                if (btn) {
                    btn.disabled = false;
                    btn.innerText = 'Download PDF';
                }
                window.print();
            });
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('download') === '1') {
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(downloadOfferLetterPDF, 400);
        });
    }
    </script>
</body>
</html>
    <?php
    $htmlContent = ob_get_clean();

    $safeBaseName = 'offer_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $offerId) . '_' . time();
    $saveDir = UPLOAD_PATH . 'offers/';
    if (!is_dir($saveDir)) {
        mkdir($saveDir, 0755, true);
    }
    
    // Save HTML version as the authentic web document
    $htmlFilePath = $saveDir . $safeBaseName . '.html';
    file_put_contents($htmlFilePath, $htmlContent);
    $relFilePath = 'uploads/offers/' . $safeBaseName . '.html';

    // Generate matching 1-page PDF
    require_once __DIR__ . '/../includes/offer-pdf-builder.php';
    $pdfFullPath = $saveDir . $safeBaseName . '.pdf';
    $pdfData = [
        'employment_type' => $empType,
        'candidate_name'  => $candidateName,
        'designation'     => $designation,
        'role_code'       => $roleCode,
        'duration_text'   => $durationText,
        'start_date'      => $startDateFormatted,
        'end_date'        => $endDateFormatted,
        'issue_date'      => $issueDateFormatted,
        'location'        => $location,
        'responsibilities'=> array_values($respItems),
        'terms'           => array_values($termsItems)
    ];
    buildOfferLetterPDF($pdfData, $pdfFullPath, 'F');

    // Save record to database
    $offerDbId = insert(
        "INSERT INTO offer_letters (offer_id, employee_id, candidate_name, candidate_email, designation, employment_type, duration_text, start_date, end_date, location, responsibilities, terms_conditions, file_path, status, issued_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'issued', ?)",
        "sisssssssssssi",
        [$offerId, $empId, $candidateName, $candidateEmail, $designation, $empType, $durationText, $startDate, $endDate, $location, $responsibilities, $terms, $relFilePath, $_SESSION['user_id']]
    );

    if ($offerDbId) {
        logActivity('issue_offer_letter', "Generated offer letter #{$offerId} for {$candidateName}");
        echo json_encode(['success' => true, 'message' => 'Offer letter generated successfully!', 'redirect' => url('admin/offer-letters')]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error while saving offer letter.']);
    }
    exit;
}

// Generate default unique offer ID
$defaultOfferId = 'CL-OFR-' . date('Y') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 font-weight-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Generate Offer Letter</h1>
                <p class="text-muted mb-0">Dynamic letterhead generation matching Clientura official template and terms.</p>
            </div>
            <div>
                <a href="<?php echo url('admin/offer-letters'); ?>" class="btn btn-saas-light">
                    <i class="bi bi-arrow-left me-1"></i>
                    <span>Back to Offer Letters</span>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Form Column -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <span class="font-weight-bold text-dark">Offer Details</span>
                        <span class="badge bg-primary-subtle text-primary">Live Sync</span>
                    </div>
                    <div class="card-body p-4">
                        <form id="offerForm">
                            <!-- Select User Auto-fill -->
                            <div class="mb-3">
                                <label class="form-label font-weight-bold text-dark small">Select Existing User (Optional)</label>
                                <select class="form-select" id="empSelector" style="border-radius: 9999px;">
                                    <option value="">-- Choose Existing Intern/Employee or Enter Below --</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($emp['name']); ?>"
                                                data-email="<?php echo htmlspecialchars($emp['email']); ?>"
                                                data-role="<?php echo htmlspecialchars($emp['designation'] ?? 'Software Development Engineer (SDE) Intern'); ?>"
                                                data-code="<?php echo htmlspecialchars($emp['employee_id']); ?>"
                                                data-joining="<?php echo $emp['date_of_joining'] ? date('Y-m-d', strtotime($emp['date_of_joining'])) : date('Y-m-d'); ?>">
                                            <?php echo htmlspecialchars($emp['name']); ?> (<?php echo htmlspecialchars($emp['employee_id']); ?> - <?php echo htmlspecialchars($emp['designation'] ?? 'Intern'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <input type="hidden" id="selectedEmpId" name="employee_id" value="">

                            <!-- Candidate Name & Email -->
                            <div class="row g-2 mb-3">
                                <div class="col-7">
                                    <label class="form-label font-weight-bold text-dark small">Candidate Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="candName" name="candidate_name" value="" placeholder="Enter full name" required style="border-radius: 12px; font-weight: 600;">
                                </div>
                                <div class="col-5">
                                    <label class="form-label font-weight-bold text-dark small">Offer ID <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control font-monospace" id="offerId" name="offer_id" value="<?php echo $defaultOfferId; ?>" required style="border-radius: 12px; font-weight: 600; color: #0A1138;">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label font-weight-bold text-dark small">Candidate Email</label>
                                <input type="email" class="form-control" id="candEmail" name="candidate_email" value="" placeholder="Enter email" style="border-radius: 12px;">
                            </div>

                            <!-- Position & Code -->
                            <div class="row g-2 mb-3">
                                <div class="col-8">
                                    <label class="form-label font-weight-bold text-dark small">Position Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="posTitle" name="designation" value="" placeholder="Enter job designation" required style="border-radius: 12px;">
                                </div>
                                <div class="col-4">
                                    <label class="form-label font-weight-bold text-dark small">Role Code</label>
                                    <input type="text" class="form-control" id="roleCode" name="role_code" value="" placeholder="Enter role code" style="border-radius: 12px;">
                                </div>
                            </div>

                            <!-- Type & Duration -->
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label font-weight-bold text-dark small">Employment Type</label>
                                    <select class="form-select" id="empType" name="employment_type" style="border-radius: 12px;">
                                        <option value="internship" selected>Internship</option>
                                        <option value="fulltime">Full-Time</option>
                                        <option value="parttime">Part-Time</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label font-weight-bold text-dark small">Duration Text</label>
                                    <input type="text" class="form-control" id="durationText" name="duration_text" value="45 days" style="border-radius: 12px;">
                                </div>
                            </div>

                            <!-- Dates & Location -->
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <label class="form-label font-weight-bold text-dark small">Start Date</label>
                                    <input type="date" class="form-control" id="startDate" name="start_date" value="" style="border-radius: 12px;">
                                </div>
                                <div class="col-4">
                                    <label class="form-label font-weight-bold text-dark small">End Date</label>
                                    <input type="date" class="form-control" id="endDate" name="end_date" value="" style="border-radius: 12px;">
                                </div>
                                <div class="col-4">
                                    <label class="form-label font-weight-bold text-dark small">Location</label>
                                    <input type="text" class="form-control" id="loc" name="location" value="Hybrid" style="border-radius: 12px;">
                                </div>
                            </div>

                            <!-- Responsibilities List -->
                            <div class="mb-3">
                                <label class="form-label font-weight-bold text-dark small">Project Responsibilities (1 per line)</label>
                                <textarea class="form-control" id="respArea" name="responsibilities" rows="5" style="border-radius: 12px; font-size: 13px;">Software Design and Development
Data Structures and Algorithms Implementation
Backend Development and API Integration
Database Design and Management
Code Review, Testing, and Debugging
Version Control and Collaborative Development</textarea>
                            </div>

                            <!-- Terms & Conditions -->
                            <div class="mb-4">
                                <label class="form-label font-weight-bold text-dark small">Terms & Conditions (1 per line)</label>
                                <textarea class="form-control" id="termsArea" name="terms" rows="4" style="border-radius: 12px; font-size: 13px;">Maintain confidentiality of all company code, software architectures, project information, and business data.
Follow development standards, documentation practices, and security guidelines established by the organization.
Submit a final report and presentation outlining your work, achievements, and learning outcomes at the end of the internship.</textarea>
                            </div>

                            <button type="button" class="btn btn-saas-dark w-100 py-3" id="btnIssueOffer">
                                <i class="bi bi-file-earmark-pdf me-1"></i>
                                <span>Generate & Issue Official Offer Letter</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Live Letterhead Visual Preview -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm sticky-top" style="top: 90px;">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <span class="font-weight-bold text-dark">Live Document Preview</span>
                        <button type="button" class="btn btn-sm btn-saas-dark" onclick="openLivePreviewTab()">
                            <i class="bi bi-box-arrow-up-right me-1"></i>
                            <span>Live Preview</span>
                        </button>
                    </div>
                    <div class="card-body p-3" style="background: #F1F5F9; border-radius: 0 0 20px 20px; overflow-y: auto; max-height: 800px;">
                        <!-- Interactive Letter Paper with Authentic Letterhead -->
                        <div id="offerLetterPreview" style="background-color: #FFFFFF; background-image: url('<?php echo asset('assets/img/offer-letterhead-template.png'); ?>'); background-size: 100% 100%; background-repeat: no-repeat; width: 100%; max-width: 650px; min-height: 920px; aspect-ratio: 210 / 297; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.12); border-radius: 4px; padding: 110px 32px 36px 32px; position: relative; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-size: 10.8px; line-height: 1.54; color: #000000; display: flex; flex-direction: column; justify-content: flex-start;">
                            
                            <h3 id="prevTitle" style="text-align: center; font-size: 16px; font-weight: 800; color: #000000; margin: 0 0 16px; letter-spacing: -0.2px; font-family: 'Inter', sans-serif;">
                                Internship Offer Letter
                            </h3>

                            <div style="font-weight: 700; margin-bottom: 3px;" id="prevDate">Date: <?php echo date('d-m-Y'); ?></div>
                            <div style="font-weight: 700; margin-bottom: 10px;" id="prevSubject">Subject: Offer Letter for Software Development Engineer (SDE) Intern</div>

                            <p style="margin-bottom: 7px;"><strong>Dear <span id="prevName">[Candidate Name]</span>,</strong></p>

                            <p style="margin-bottom: 8px; text-align: justify; text-justify: inter-word;">
                                We are pleased to offer you the position of <strong id="prevPosInline">[Position Title]</strong> at Clientura, for a duration of <strong id="prevDurationInline">45 days</strong>, starting from <strong id="prevDatesInline">[Start Date] to [End Date]</strong>. This <span id="prevEmpTypeInline">internship</span> is part of our commitment to nurturing talented individuals and providing practical exposure to modern software engineering practices.
                            </p>

                            <p style="font-weight: 700; margin-bottom: 4px; margin-top: 8px; text-align: justify; text-justify: inter-word;">
                                During your <span id="prevEmpTypeInline2">internship</span>, you will work closely with our Engineering Team on real-world software development projects involving:
                            </p>
                            <ul id="prevRespList" style="margin: 2px 0 8px 18px; padding: 0; line-height: 1.5; text-align: justify; text-justify: inter-word;">
                                <li>Software Design and Development</li>
                                <li>Data Structures and Algorithms Implementation</li>
                                <li>Backend Development and API Integration</li>
                                <li>Database Design and Management</li>
                                <li>Code Review, Testing, and Debugging</li>
                                <li>Version Control and Collaborative Development</li>
                            </ul>

                            <p style="margin-bottom: 8px; text-align: justify; text-justify: inter-word;">
                                You are expected to maintain a high level of professionalism, meet project deadlines, actively collaborate with team members, and contribute to the successful delivery of software solutions.
                            </p>

                            <div style="font-weight: 700; margin-top: 10px; margin-bottom: 2px;"><span id="prevDetailsTitle">Internship</span> Details:</div>
                            <p style="margin: 1px 0;"><strong>Position:</strong> <span id="prevPosBlock">[Position Title]</span></p>
                            <p style="margin: 1px 0;"><strong>Location:</strong> <span id="prevLoc">Hybrid</span></p>
                            <p style="margin: 1px 0;"><strong>Start Date:</strong> <span id="prevStart">--</span></p>
                            <p style="margin: 1px 0;"><strong>End Date:</strong> <span id="prevEnd">--</span></p>

                            <div style="font-weight: 700; margin-top: 10px; margin-bottom: 2px;">Terms & Conditions:</div>
                            <ul id="prevTermsList" style="margin: 2px 0 8px 18px; padding: 0; line-height: 1.5; text-align: justify; text-justify: inter-word;">
                                <li>Maintain confidentiality of all company code, software architectures, project information, and business data.</li>
                                <li>Follow development standards, documentation practices, and security guidelines established by the organization.</li>
                                <li>Submit a final report and presentation outlining your work, achievements, and learning outcomes at the end of the internship.</li>
                            </ul>

                            <p style="margin-bottom: 7px; margin-top: 8px; text-align: justify; text-justify: inter-word;">
                                To confirm your acceptance of this offer, please reply to this letter or sign and return. Upon receiving your confirmation, we will proceed with the onboarding process and provide further details regarding your project assignments.
                            </p>

                            <p style="margin-bottom: 10px; text-align: justify; text-justify: inter-word;">
                                We look forward to welcoming you to our team and supporting your professional growth as a <span id="prevRoleClosing">[Position Title]</span>.
                            </p>

                            <div id="prevSignRow" style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: auto; padding-bottom: 18px; padding-right: 10px;">
                                <div>
                                    <p style="margin-bottom: 2px;"><strong>Warm regards,</strong></p>
                                    <p style="font-weight: 700; margin: 0;">Clientura</p>
                                </div>
                                <div style="font-weight: 700; font-size: 11px;">
                                    Signature of candidate
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function syncPreview() {
    const rawName = document.getElementById('candName').value.trim();
    const rawPos = document.getElementById('posTitle').value.trim();
    const rawCode = document.getElementById('roleCode').value.trim();
    const type = document.getElementById('empType').value;
    const dur = document.getElementById('durationText').value.trim() || '45 days';
    const sDateRaw = document.getElementById('startDate').value;
    const eDateRaw = document.getElementById('endDate').value;
    const sDate = sDateRaw ? formatDate(sDateRaw) : '[Start Date]';
    const eDate = eDateRaw ? formatDate(eDateRaw) : '[End Date]';
    const loc = document.getElementById('loc').value.trim() || 'Hybrid';

    const name = rawName || '[Candidate Name]';
    const pos = rawPos || '[Position Title]';
    const cleanCode = rawCode.trim();
    const code = cleanCode ? ' - ' + cleanCode : '';
    const capType = type.charAt(0).toUpperCase() + type.slice(1);

    document.getElementById('prevTitle').innerText = capType + ' Offer Letter' + code;
    document.getElementById('prevSubject').innerText = 'Subject: Offer Letter for ' + pos;
    document.getElementById('prevName').innerText = name;
    document.getElementById('prevPosInline').innerText = pos;
    document.getElementById('prevDurationInline').innerText = dur;
    document.getElementById('prevDatesInline').innerText = sDate + ' to ' + eDate;
    
    if (document.getElementById('prevEmpTypeInline')) {
        document.getElementById('prevEmpTypeInline').innerText = type;
    }
    if (document.getElementById('prevEmpTypeInline2')) {
        document.getElementById('prevEmpTypeInline2').innerText = type;
    }
    if (document.getElementById('prevDetailsTitle')) {
        document.getElementById('prevDetailsTitle').innerText = capType;
    }

    document.getElementById('prevPosBlock').innerText = pos;
    document.getElementById('prevLoc').innerText = loc;
    document.getElementById('prevStart').innerText = sDateRaw ? sDate : '--';
    document.getElementById('prevEnd').innerText = eDateRaw ? eDate : '--';
    document.getElementById('prevRoleClosing').innerText = pos;

    // Sync Responsibilities List
    const respLines = document.getElementById('respArea').value.split('\n').filter(l => l.trim() !== '');
    const respUl = document.getElementById('prevRespList');
    respUl.innerHTML = '';
    respLines.forEach(l => {
        const li = document.createElement('li');
        li.innerText = l;
        respUl.appendChild(li);
    });

    // Sync Terms List
    const termLines = document.getElementById('termsArea').value.split('\n').filter(l => l.trim() !== '');
    const termUl = document.getElementById('prevTermsList');
    termUl.innerHTML = '';
    termLines.forEach(l => {
        const li = document.createElement('li');
        li.innerText = l;
        termUl.appendChild(li);
    });
}

function formatDate(d) {
    if (!d) return '';
    const parts = d.split('-');
    if (parts.length === 3) return parts[2] + '-' + parts[1] + '-' + parts[0];
    return d;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Attach listeners
['candName', 'posTitle', 'roleCode', 'empType', 'durationText', 'startDate', 'endDate', 'loc', 'respArea', 'termsArea'].forEach(id => {
    document.getElementById(id).addEventListener('input', syncPreview);
    document.getElementById(id).addEventListener('change', syncPreview);
});

// Employee Selector Auto-fill
document.getElementById('empSelector').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    if (this.value) {
        document.getElementById('selectedEmpId').value = this.value;
        const name = selected.getAttribute('data-name') || '';
        const email = selected.getAttribute('data-email') || '';
        const role = selected.getAttribute('data-role') || '';
        document.getElementById('candName').value = name;
        document.getElementById('candEmail').value = email;
        document.getElementById('posTitle').value = role;

        // Extract role code without trailing whitespace or leave empty
        const match = role.match(/\(([^)]+)\)/);
        if (match && match[1]) {
            document.getElementById('roleCode').value = match[1].trim();
        } else {
            document.getElementById('roleCode').value = '';
        }

        const joining = selected.getAttribute('data-joining');
        const start = joining ? new Date(joining) : new Date();
        const startIso = start.toISOString().split('T')[0];
        document.getElementById('startDate').value = startIso;

        // Default 45 days forward
        const end = new Date(start);
        end.setDate(end.getDate() + 45);
        document.getElementById('endDate').value = end.toISOString().split('T')[0];
        document.getElementById('durationText').value = '45 days';
        document.getElementById('loc').value = 'Hybrid';

        syncPreview();
    } else {
        // Deselected - clear details
        document.getElementById('selectedEmpId').value = '';
        document.getElementById('candName').value = '';
        document.getElementById('candEmail').value = '';
        document.getElementById('posTitle').value = '';
        document.getElementById('roleCode').value = '';
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        syncPreview();
    }
});

// Initial sync
syncPreview();

// Live Preview Function - Opens authentic full A4 letterhead document matching offer_CL-OFR-2026-597_1792688627.html
function openLivePreviewTab() {
    const previewWindow = window.open('', '_blank');
    if (!previewWindow) {
        alert('Please allow popups to open the live document preview.');
        return;
    }

    const rawName = document.getElementById('candName').value.trim() || 'Candidate Name';
    const rawPos = document.getElementById('posTitle').value.trim() || 'Software Engineer';
    const rawCode = document.getElementById('roleCode').value.trim();
    const type = document.getElementById('empType').value || 'internship';
    const dur = document.getElementById('durationText').value.trim() || '45 days';
    const sDateRaw = document.getElementById('startDate').value;
    const eDateRaw = document.getElementById('endDate').value;
    const sDate = sDateRaw ? formatDate(sDateRaw) : '<?php echo date('d-m-Y'); ?>';
    const eDate = eDateRaw ? formatDate(eDateRaw) : '<?php echo date('d-m-Y', strtotime('+45 days')); ?>';
    const loc = document.getElementById('loc').value.trim() || 'Hybrid';
    const issueDate = '<?php echo date('d-m-Y'); ?>';

    const capType = type.charAt(0).toUpperCase() + type.slice(1);
    const cleanCode = rawCode.trim();
    const codeSuffix = cleanCode ? ' - ' + escapeHtml(cleanCode) : '';
    const offerTitle = capType + ' Offer Letter' + codeSuffix;

    const respLines = document.getElementById('respArea').value.split('\n').filter(l => l.trim() !== '');
    const termLines = document.getElementById('termsArea').value.split('\n').filter(l => l.trim() !== '');

    const respHtml = respLines.map(l => `<li>${escapeHtml(l)}</li>`).join('\n        ');
    const termsHtml = termLines.map(l => `<li>${escapeHtml(l)}</li>`).join('\n        ');
    const cleanFilename = rawName.replace(/[^a-zA-Z0-9_-]/g, '_') + '_Offer_Letter.pdf';
    const bgUrl = '<?php echo asset("assets/img/offer-letterhead-template.png"); ?>';

    previewWindow.document.write(`<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>${escapeHtml(rawName)} - Offer Letter | Clientura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <script src="<?php echo asset('assets/js/html2pdf.bundle.min.js'); ?>"><\/script>
    <style>
      @page {
        size: A4 portrait;
        margin: 0;
      }
      * {
        box-sizing: border-box;
      }
      body {
        margin: 0;
        padding: 0;
        background: #e2e8f0;
        font-family:
          "Inter",
          -apple-system,
          BlinkMacSystemFont,
          sans-serif;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .offer-page {
        width: 210mm;
        height: 296mm;
        max-height: 296mm;
        margin: 20px auto;
        position: relative;
        background-color: #ffffff;
        background-image: url('${bgUrl}');
        background-size: 100% 100%;
        background-repeat: no-repeat;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        padding: 130px 42px 35px 42px;
        color: #000000;
        font-size: 12.8px;
        line-height: 1.55;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        box-sizing: border-box;
        overflow: hidden;
        page-break-inside: avoid !important;
        page-break-after: avoid !important;
      }
      .offer-title {
        text-align: center;
        font-size: 19px;
        font-weight: 800;
        margin: 0 0 20px 0;
        color: #000000;
        letter-spacing: -0.2px;
      }
      .meta-line {
        font-weight: 700;
        margin-bottom: 4px;
      }
      .bold-text {
        font-weight: 700;
      }
      p {
        margin: 8px 0;
        text-align: justify;
        text-justify: inter-word;
      }
      ul {
        margin: 4px 0 10px 22px;
        padding: 0;
        list-style-type: disc;
        text-align: justify;
        text-justify: inter-word;
      }
      li {
        margin-bottom: 3px;
      }
      .section-title {
        font-weight: 700;
        margin-top: 14px;
        margin-bottom: 4px;
      }
      .sign-row {
        margin-top: auto;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        padding-bottom: 8px;
        padding-right: 10px;
      }
      .cand-sign {
        font-weight: 700;
        font-size: 12.5px;
      }
      @media print {
        body {
          background: transparent;
        }
        .no-print {
          display: none !important;
        }
        .offer-page {
          margin: 0 !important;
          box-shadow: none !important;
          width: 210mm !important;
          height: 296mm !important;
          max-height: 296mm !important;
          overflow: hidden !important;
          page-break-inside: avoid !important;
          page-break-after: avoid !important;
        }
      }
    </style>
  </head>
  <body>
    <div
      class="no-print"
      style="
        background: #0a1138;
        color: white;
        padding: 12px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 999;
      "
    >
      <div style="font-weight: 600">Clientura Official Offer Letter</div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <button
          id="btnDownloadOfferPDF"
          onclick="downloadOfferLetterPDF()"
          style="
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 8px 22px;
            font-weight: 700;
            border-radius: 9999px;
            cursor: pointer;
            transition: all 0.2s;
          "
        >
          Download PDF
        </button>
        <button
          onclick="window.print()"
          style="
            background: #ffa500;
            color: #0a1138;
            border: none;
            padding: 8px 22px;
            font-weight: 700;
            border-radius: 9999px;
            cursor: pointer;
          "
        >
          Print / Save as PDF
        </button>
        <button
          onclick="window.close()"
          style="
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border: none;
            padding: 8px 18px;
            font-weight: 700;
            border-radius: 9999px;
            cursor: pointer;
          "
        >
          Close
        </button>
      </div>
    </div>

    <div class="offer-page">
      <div class="offer-title">${offerTitle}</div>

      <div class="meta-line">Date: ${escapeHtml(issueDate)}</div>
      <div class="meta-line">Subject: Offer Letter for ${escapeHtml(rawPos)}</div>

      <p style="margin-top: 10px">
        <span class="bold-text">Dear ${escapeHtml(rawName)},</span>
      </p>

      <p>
        We are pleased to offer you the position of
        <span class="bold-text">${escapeHtml(rawPos)}</span> at Clientura, for a
        duration of <span class="bold-text">${escapeHtml(dur)}</span>, starting from
        <span class="bold-text">${escapeHtml(sDate)}</span> to
        <span class="bold-text">${escapeHtml(eDate)}</span>. This ${escapeHtml(type)} is part of
        our commitment to nurturing talented individuals and providing practical
        exposure to modern software engineering practices.
      </p>

      <p class="bold-text" style="margin-top: 10px">
        During your ${escapeHtml(type)}, you will work closely with our Engineering Team
        on real-world software development projects involving:
      </p>
      <ul>
        ${respHtml}
      </ul>

      <p>
        You are expected to maintain a high level of professionalism, meet
        project deadlines, actively collaborate with team members, and
        contribute to the successful delivery of software solutions.
      </p>

      <div class="section-title">${escapeHtml(capType)} Details:</div>
      <p style="margin: 2px 0">
        <span class="bold-text">Position:</span> ${escapeHtml(rawPos)}
      </p>
      <p style="margin: 2px 0">
        <span class="bold-text">Location:</span> ${escapeHtml(loc)}
      </p>
      <p style="margin: 2px 0">
        <span class="bold-text">Start Date:</span> ${escapeHtml(sDate)}
      </p>
      <p style="margin: 2px 0">
        <span class="bold-text">End Date:</span> ${escapeHtml(eDate)}
      </p>

      <div class="section-title" style="margin-top: 12px">
        Terms & Conditions:
      </div>
      <ul>
        ${termsHtml}
      </ul>

      <p>
        To confirm your acceptance of this offer, please reply to this email.
        Upon receiving your confirmation, we will proceed with the onboarding
        process and provide further details regarding your ${escapeHtml(type)}
        responsibilities and project assignments.
      </p>

      <p>
        We look forward to welcoming you to our team and supporting your
        professional growth as a ${escapeHtml(rawPos)}.
      </p>

      <div class="sign-row">
        <div>
          <p style="margin-bottom: 2px">
            <span class="bold-text">Warm regards,</span>
          </p>
          <p style="margin-top: 0"><span class="bold-text">Clientura</span></p>
        </div>
        <div class="cand-sign">Signature of candidate</div>
      </div>
    </div>

    <script>
      function downloadOfferLetterPDF() {
        const btn = document.getElementById('btnDownloadOfferPDF');
        if (btn) {
          btn.disabled = true;
          btn.innerText = 'Generating PDF...';
        }
        const element = document.querySelector('.offer-page');
        const opt = {
          margin: 0,
          filename: '${cleanFilename}',
          image: { type: 'jpeg', quality: 0.98 },
          html2canvas: { scale: 2, useCORS: true, scrollY: 0, scrollX: 0 },
          jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
          pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };
        html2pdf()
          .set(opt)
          .from(element)
          .toPdf()
          .get('pdf')
          .then(function(pdf) {
            const totalPages = pdf.internal.getNumberOfPages();
            if (totalPages > 1) {
              for (let i = totalPages; i > 1; i--) {
                pdf.deletePage(i);
              }
            }
          })
          .save()
          .then(() => {
            if (btn) {
              btn.disabled = false;
              btn.innerText = 'Download PDF';
            }
          })
          .catch((err) => {
            console.error(err);
            if (btn) {
              btn.disabled = false;
              btn.innerText = 'Download PDF';
            }
            window.print();
          });
      }
    <\/script>
  </body>
</html>`);
}

// Issue Offer Letter
document.getElementById('btnIssueOffer').addEventListener('click', function() {
    const btn = this;
    const name = document.getElementById('candName').value.trim();
    const offerId = document.getElementById('offerId').value.trim();

    if (!name || !offerId) {
        alert('Please fill in candidate name and offer ID.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Generating...';

    const formData = new FormData(document.getElementById('offerForm'));
    formData.append('action', 'save_offer');

    fetch('<?php echo url("admin/generate-offer"); ?>', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-1"></i> Generate & Issue Official Offer Letter';

        if (data.success) {
            alert('Offer letter generated successfully!');
            window.location.href = data.redirect;
        } else {
            alert(data.message || 'Error generating offer letter.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-1"></i> Generate & Issue Official Offer Letter';
        alert('Server communication error.');
        console.error(err);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
