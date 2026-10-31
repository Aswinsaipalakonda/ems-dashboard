<?php
/**
 * Intern Registration & Application Portal
 * Public portal for prospective interns to apply, register academic background, select domain, and upload CV.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/pikaicons.php';

$pageTitle = 'Internship Registration & Application';

// Fetch active domains for dropdown
$domains = fetchAll("SELECT id, name, code FROM domains WHERE status = 'active' ORDER BY id ASC");

$submissionSuccess = false;
$regData = null;
$error = '';

// Handle POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_intern') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $collegeName = trim($_POST['college_name'] ?? '');
    $degreeBranch = trim($_POST['degree_branch'] ?? '');
    $gradYear = trim($_POST['graduation_year'] ?? '');
    $cgpa = trim($_POST['cgpa_percentage'] ?? '');
    $domainRaw = trim($_POST['domain_id'] ?? '');
    $isOtherDomain = ($domainRaw === 'other');
    $domainId = $isOtherDomain ? null : (!empty($domainRaw) ? (int)$domainRaw : null);
    $otherDomain = $isOtherDomain ? trim($_POST['other_domain'] ?? '') : null;
    $duration = trim($_POST['preferred_duration'] ?? '3 Months');
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
    $mode = in_array($_POST['internship_mode'] ?? '', ['remote', 'hybrid', 'onsite']) ? $_POST['internship_mode'] : 'hybrid';
    $linkedin = trim($_POST['linkedin_url'] ?? '');
    $github = trim($_POST['github_portfolio_url'] ?? '');
    $coverNote = trim($_POST['cover_note'] ?? '');

    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

    // Validation
    if (empty($fullName) || empty($email) || empty($phone) || empty($collegeName) || empty($degreeBranch) || empty($gradYear)) {
        $error = 'Please fill in all required fields marked with an asterisk (*).';
    } elseif (!$isOtherDomain && (empty($domainId) || $domainId <= 0)) {
        $error = 'Please select your target technical domain.';
    } elseif ($isOtherDomain && empty($otherDomain)) {
        $error = 'Please specify your target technical domain / role.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($cleanPhone) !== 10) {
        $error = 'Please provide a valid 10-digit mobile number.';
    } else {
        // Check for existing pending registration with same email
        $existing = fetchOne("SELECT id, registration_id FROM intern_registrations WHERE email = ? AND status = 'pending'", "s", [$email]);
        if ($existing) {
            $error = 'You already have an active pending application (Reg ID: ' . htmlspecialchars($existing['registration_id']) . '). Our team will get in touch with you shortly.';
        } else {
            // Handle resume file upload
            $resumeRelPath = null;
            if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['resume'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExts = ['pdf', 'doc', 'docx'];

                if (!in_array($ext, $allowedExts)) {
                    $error = 'Invalid resume format. Only PDF, DOC, and DOCX files are permitted.';
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $error = 'Resume file exceeds the 5MB maximum allowed size.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/resumes/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $safeCandidateName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fullName);
                    $newFileName = 'resume_' . $safeCandidateName . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                    $destPath = $uploadDir . $newFileName;

                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        $resumeRelPath = 'uploads/resumes/' . $newFileName;
                    } else {
                        $error = 'Failed to upload resume file. Please try again.';
                    }
                }
            }

            if (empty($error)) {
                // Generate unique Registration ID: CL-INT-YYYY-XXXXX
                $randNum = mt_rand(10000, 99999);
                $regId = 'CL-INT-' . date('Y') . '-' . $randNum;

                $inserted = insert(
                    "INSERT INTO intern_registrations (
                        registration_id, full_name, email, phone, college_name, 
                        degree_branch, graduation_year, cgpa_percentage, domain_id, other_domain, 
                        preferred_duration, start_date, internship_mode, linkedin_url, 
                        github_portfolio_url, resume_path, cover_note, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
                    "ssssssssissssssss",
                    [
                        $regId, $fullName, $email, $phone, $collegeName,
                        $degreeBranch, $gradYear, $cgpa, $domainId, $otherDomain,
                        $duration, $startDate, $mode, $linkedin,
                        $github, $resumeRelPath, $coverNote
                    ]
                );

                if ($inserted) {
                    // Notify Admin users
                    $adminUsers = fetchAll("SELECT id FROM admin");
                    foreach ($adminUsers as $adm) {
                        insert(
                            "INSERT INTO notifications (user_id, user_type, title, message, type, link) VALUES (?, 'admin', 'New Intern Registration', ?, 'info', ?)",
                            "iss",
                            [$adm['id'], "New application: {$fullName} ({$regId}) for {$duration}.", url('admin/intern-registrations')]
                        );
                    }

                    $submissionSuccess = true;
                    $regData = [
                        'registration_id' => $regId,
                        'full_name' => $fullName,
                        'email' => $email,
                        'preferred_duration' => $duration,
                        'internship_mode' => ucfirst($mode)
                    ];
                } else {
                    $error = 'An error occurred while saving your registration. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | Clientura</title>
    <link rel="icon" type="image/png" href="<?php echo asset('assets/img/clientura-with-bg.png'); ?>">

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-navy: #0A1138;
            --brand-navy-light: #151f5c;
            --brand-gold: #DF9E1D;
            --brand-emerald: #059669;
            --bg-canvas: #F8FAFC;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-canvas);
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar-brand img {
            height: 38px;
        }

        .hero-banner {
            background: linear-gradient(135deg, var(--brand-navy) 0%, #151f5c 50%, #0d1645 100%);
            color: white;
            padding: 50px 0 60px;
            position: relative;
            overflow: hidden;
        }

        .hero-banner::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 24px;
            background: var(--bg-canvas);
            border-radius: 24px 24px 0 0;
        }

        .form-card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 10px 30px -5px rgba(10, 17, 56, 0.07);
        }

        .form-section-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--brand-navy);
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 20px;
        }

        .form-section-title .section-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #EEF2FF;
            color: #4F46E5;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
        }

        .form-control, .form-select {
            border-radius: 12px;
            border: 1.5px solid #E2E8F0;
            padding: 10px 14px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #6366F1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        }

        .btn-register-submit {
            background: linear-gradient(135deg, var(--brand-navy) 0%, #1e295d 100%);
            color: white;
            border: none;
            border-radius: 14px;
            padding: 14px 28px;
            font-weight: 600;
            font-size: 1.05rem;
            transition: all 0.25s ease;
        }

        .btn-register-submit:hover {
            background: linear-gradient(135deg, #151f5c 0%, #2b397d 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -3px rgba(10, 17, 56, 0.25);
        }

        .file-upload-box {
            border: 2px dashed #CBD5E1;
            border-radius: 14px;
            padding: 24px;
            text-align: center;
            background: #F8FAFC;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .file-upload-box:hover {
            border-color: #6366F1;
            background: #F5F7FF;
        }

        .success-card {
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 20px 40px -10px rgba(10, 17, 56, 0.12);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo url(''); ?>">
                <img src="<?php echo asset('assets/img/clientura-bg.png'); ?>" alt="Clientura EMS">
                <span class="fw-bold fs-5 text-dark" style="font-family: 'Outfit', sans-serif;">Clientura <span class="text-primary">EMS</span></span>
            </a>
            <div class="ms-auto d-flex align-items-center gap-3">
                <a href="<?php echo url('login'); ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Portal
                </a>
            </div>
        </div>
    </nav>

    <?php if ($submissionSuccess && $regData): ?>
        <!-- Success Confirmation State -->
        <div class="container my-auto py-5">
            <div class="row justify-content-center">
                <div class="col-lg-7 col-md-9">
                    <div class="success-card p-4 p-md-5 text-center">
                        <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px; font-size: 38px;">
                            <i class="bi bi-check-lg"></i>
                        </div>

                        <h2 class="font-weight-bold text-dark mb-2" style="font-family: 'Outfit', sans-serif;">Application Registered Successfully!</h2>
                        <p class="text-muted mb-4">
                            Congratulations <strong><?php echo htmlspecialchars($regData['full_name']); ?></strong>! Your internship registration has been officially recorded in our workforce onboarding system.
                        </p>

                        <div class="p-4 rounded-4 bg-light text-start mb-4 border">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Application / Reg ID</span>
                                    <span class="fs-5 fw-bold font-monospace text-primary"><?php echo htmlspecialchars($regData['registration_id']); ?></span>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Registered Email</span>
                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($regData['email']); ?></span>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Preferred Duration</span>
                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($regData['preferred_duration']); ?></span>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Internship Mode</span>
                                    <span class="badge bg-primary-subtle text-primary"><?php echo htmlspecialchars($regData['internship_mode']); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info text-start small rounded-3 mb-4">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            <strong>Next Steps:</strong> Our technical team will review your application and resume. Upon selection, an official Offer Letter and onboarding credentials will be issued directly to your email.
                        </div>

                        <div class="d-flex justify-content-center gap-3">
                            <a href="<?php echo url('intern-registration'); ?>" class="btn btn-outline-primary rounded-pill px-4">
                                <i class="bi bi-plus-circle me-1"></i> Submit Another Registration
                            </a>
                            <a href="<?php echo url('login'); ?>" class="btn btn-saas-dark rounded-pill px-4">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Go to Portal Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>

        <!-- Hero Section -->
        <section class="hero-banner text-center">
            <div class="container">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill font-weight-bold mb-3">
                    <i class="bi bi-stars me-1"></i> Clientura Internship Program <?php echo date('Y'); ?>
                </span>
                <h1 class="display-6 font-weight-bold text-white mb-2" style="font-family: 'Outfit', sans-serif;">
                    Intern Registration & Application Form
                </h1>
                <p class="text-white-50 mx-auto mb-0" style="max-width: 650px;">
                    Gain immersive hands-on experience on live client projects, receive 1-on-1 industry mentorship, and earn verified credentials.
                </p>
            </div>
        </section>

        <!-- Main Form Container -->
        <div class="container my-5">
            <div class="row justify-content-center">
                <div class="col-lg-9">

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="form-card p-4 p-md-5">
                        <form method="POST" action="<?php echo url('intern-registration'); ?>" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="register_intern">

                            <!-- Section 1: Candidate Personal Info -->
                            <div class="form-section-title">
                                <span class="section-num">1</span>
                                <span>Candidate Information</span>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label font-weight-bold text-dark small">Full Legal Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="full_name" placeholder="Enter your full name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-bold text-dark small">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" placeholder="Enter your email address" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-bold text-dark small">Mobile / WhatsApp Number (10 Digits) <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" name="phone" placeholder="Enter 10-digit mobile number" pattern="[0-9]{10}" maxlength="10" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-bold text-dark small">Current City / Location</label>
                                    <input type="text" class="form-control" name="location" placeholder="Enter your current city" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Section 2: Academic Background -->
                            <div class="form-section-title">
                                <span class="section-num">2</span>
                                <span>Academic Background</span>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-8">
                                    <label class="form-label font-weight-bold text-dark small">College / Institute Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="college_name" placeholder="Enter your college / university name" value="<?php echo htmlspecialchars($_POST['college_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label font-weight-bold text-dark small">Graduation / Passing Year <span class="text-danger">*</span></label>
                                    <select class="form-select" name="graduation_year" required>
                                        <option value="">Select Year</option>
                                        <?php for ($y = date('Y') + 3; $y >= date('Y') - 2; $y--): ?>
                                            <option value="<?php echo $y; ?>" <?php echo (isset($_POST['graduation_year']) && $_POST['graduation_year'] == $y) ? 'selected' : ''; ?>>
                                                <?php echo $y; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label font-weight-bold text-dark small">Degree & Branch / Major <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="degree_branch" placeholder="Enter your degree and branch" value="<?php echo htmlspecialchars($_POST['degree_branch'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label font-weight-bold text-dark small">Current CGPA / Percentage (Optional)</label>
                                    <input type="text" class="form-control" name="cgpa_percentage" placeholder="Enter your CGPA or percentage" value="<?php echo htmlspecialchars($_POST['cgpa_percentage'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Section 3: Internship Preferences -->
                            <div class="form-section-title">
                                <span class="section-num">3</span>
                                <span>Internship Track & Preferences</span>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label font-weight-bold text-dark small">Target Technical Domain <span class="text-danger">*</span></label>
                                    <select class="form-select" name="domain_id" id="domainSelect" onchange="toggleOtherDomain(this)" required>
                                        <option value="">-- Choose Tech Track --</option>
                                        <?php foreach ($domains as $dm): ?>
                                            <option value="<?php echo $dm['id']; ?>" <?php echo (isset($_POST['domain_id']) && (string)$_POST['domain_id'] === (string)$dm['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dm['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="other" <?php echo (isset($_POST['domain_id']) && $_POST['domain_id'] === 'other') ? 'selected' : ''; ?>>Other (Please specify)</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="otherDomainContainer" style="<?php echo (isset($_POST['domain_id']) && $_POST['domain_id'] === 'other') ? 'display: block;' : 'display: none;'; ?>">
                                    <label class="form-label font-weight-bold text-dark small">Specify Technical Domain / Role <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="other_domain" id="otherDomainInput" placeholder="Enter your target domain or role (e.g. AI / ML, DevOps, Cyber Security)..." value="<?php echo htmlspecialchars($_POST['other_domain'] ?? ''); ?>" <?php echo (isset($_POST['domain_id']) && $_POST['domain_id'] === 'other') ? 'required' : ''; ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-bold text-dark small">Preferred Duration <span class="text-danger">*</span></label>
                                    <select class="form-select" name="preferred_duration" required>
                                        <option value="1 Month">1 Month</option>
                                        <option value="2 Months">2 Months</option>
                                        <option value="3 Months" selected>3 Months</option>
                                        <option value="4 Months">4 Months</option>
                                        <option value="6 Months">6 Months</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-bold text-dark small">Earliest Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-bold text-dark small">Work Mode <span class="text-danger">*</span></label>
                                    <select class="form-select" name="internship_mode" required>
                                        <option value="hybrid" selected>Hybrid (Remote + In-office sprints)</option>
                                        <option value="remote">Fully Remote</option>
                                        <option value="onsite">On-Site</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Section 4: Profiles & Resume -->
                            <div class="form-section-title">
                                <span class="section-num">4</span>
                                <span>Profiles & Resume Attachment</span>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label font-weight-bold text-dark small">LinkedIn Profile URL</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-primary border-end-0"><i class="bi bi-linkedin"></i></span>
                                        <input type="url" class="form-control border-start-0" name="linkedin_url" placeholder="Enter your LinkedIn profile URL" value="<?php echo htmlspecialchars($_POST['linkedin_url'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-weight-bold text-dark small">GitHub / Portfolio URL</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-dark border-end-0"><i class="bi bi-github"></i></span>
                                        <input type="url" class="form-control border-start-0" name="github_portfolio_url" placeholder="Enter your GitHub / portfolio URL" value="<?php echo htmlspecialchars($_POST['github_portfolio_url'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label font-weight-bold text-dark small">Upload Resume / CV (PDF or DOCX, max 5MB) <span class="text-danger">*</span></label>
                                    <div class="file-upload-box" onclick="document.getElementById('resumeFileInput').click();">
                                        <i class="bi bi-cloud-arrow-up fs-1 text-primary d-block mb-2"></i>
                                        <span class="fw-semibold text-dark d-block">Click to select or drop resume here</span>
                                        <small class="text-muted" id="fileChosenLabel">Supported formats: PDF, DOC, DOCX (Max 5MB)</small>
                                        <input type="file" id="resumeFileInput" name="resume" accept=".pdf,.doc,.docx" class="d-none" required onchange="handleFileChange(this)">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label font-weight-bold text-dark small">Why do you want to intern at Clientura? (Optional)</label>
                                    <textarea class="form-control" name="cover_note" rows="3" placeholder="Enter a brief introduction or expectations (optional)..."><?php echo htmlspecialchars($_POST['cover_note'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="mt-4">
                                <button type="submit" class="btn btn-register-submit w-100 py-3 d-flex align-items-center justify-content-center gap-2">
                                    <i class="bi bi-send-check"></i>
                                    <span>Submit Internship Application</span>
                                </button>
                                <p class="text-muted text-center small mt-3 mb-0">
                                    <i class="bi bi-shield-check text-success me-1"></i> By submitting, you confirm that the information provided is accurate and verifiable.
                                </p>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>

    <?php endif; ?>

    <!-- Footer -->
    <footer class="bg-white border-top py-4 mt-auto">
        <div class="container text-center text-muted small">
            &copy; <?php echo date('Y'); ?> Clientura EMS. All rights reserved. &bull; <a href="<?php echo url('login'); ?>" class="text-muted text-decoration-none">Employee Portal Login</a>
        </div>
    </footer>

    <script>
    function toggleOtherDomain(sel) {
        const container = document.getElementById('otherDomainContainer');
        const input = document.getElementById('otherDomainInput');
        if (sel.value === 'other') {
            container.style.display = 'block';
            input.required = true;
            input.focus();
        } else {
            container.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    }

    function handleFileChange(input) {
        const label = document.getElementById('fileChosenLabel');
        if (input.files && input.files[0]) {
            const f = input.files[0];
            const sizeMB = (f.size / (1024 * 1024)).toFixed(2);
            label.innerHTML = '<strong class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Selected: ' + f.name + ' (' + sizeMB + ' MB)</strong>';
        }
    }
    </script>
</body>
</html>

