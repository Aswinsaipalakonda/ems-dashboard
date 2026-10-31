<?php
/**
 * Exit Feedback Form - Employee Portal
 * Capture structured feedback on internship/employment experience, mentorship, culture, and learnings.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: " . url('login'));
    exit;
}

$pageTitle = 'Exit Feedback';
$userId = (int)$_SESSION['user_id'];

// Fetch employee details
$employee = fetchOne(
    "SELECT e.*, d.name AS domain_name 
     FROM employees e 
     LEFT JOIN domains d ON e.domain_id = d.id 
     WHERE e.id = ?",
    "i",
    [$userId]
);

if (!$employee) {
    header("Location: " . url('login'));
    exit;
}

// Check existing submission
$existingFeedback = fetchOne(
    "SELECT * FROM exit_feedbacks WHERE employee_id = ?",
    "i",
    [$userId]
);

// Handle AJAX or POST Submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_exit_feedback') {
    if ($existingFeedback) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => 'You have already submitted your exit feedback. Thank you!']);
            exit;
        }
        $message = 'You have already submitted your exit feedback.';
        $messageType = 'warning';
    } else {
        $overallRating = (int)($_POST['overall_rating'] ?? 0);
        $mentorshipRating = (int)($_POST['mentorship_rating'] ?? 0);
        $cultureRating = (int)($_POST['culture_rating'] ?? 0);
        $learningRating = (int)($_POST['learning_rating'] ?? 0);
        $managementRating = (int)($_POST['management_rating'] ?? 0);
        $recommendScore = max(1, min(10, (int)($_POST['recommend_score'] ?? 8)));

        $keyLearnings = trim($_POST['key_learnings'] ?? '');
        $positiveHighlights = trim($_POST['positive_highlights'] ?? '');
        $improvementSuggestions = trim($_POST['improvement_suggestions'] ?? '');
        $reasonForLeaving = trim($_POST['reason_for_leaving'] ?? 'Completed Scheduled Internship Tenure');
        $personalEmail = trim($_POST['personal_email'] ?? '');
        $personalPhone = trim($_POST['personal_phone'] ?? '');
        $cleanPhone = preg_replace('/[^0-9]/', '', $personalPhone);
        $linkedinProfile = trim($_POST['linkedin_profile'] ?? '');
        $futureConsent = isset($_POST['future_contact_consent']) ? 1 : 0;

        if ($overallRating < 1 || $mentorshipRating < 1 || $cultureRating < 1 || $learningRating < 1 || $managementRating < 1) {
            $msg = 'Please select a star rating for all evaluation categories.';
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $message = $msg;
            $messageType = 'danger';
        } elseif (empty($keyLearnings) || empty($positiveHighlights)) {
            $msg = 'Please complete the feedback reflection questions before submitting.';
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $message = $msg;
            $messageType = 'danger';
        } elseif (empty($personalEmail) || !filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Please enter a valid personal email address.';
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $message = $msg;
            $messageType = 'danger';
        } elseif (strlen($cleanPhone) !== 10) {
            $msg = 'Please enter a valid 10-digit mobile number.';
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $message = $msg;
            $messageType = 'danger';
        } else {
            $insertId = insert(
                "INSERT INTO exit_feedbacks (
                    employee_id, overall_rating, mentorship_rating, culture_rating, 
                    learning_rating, management_rating, recommend_score, key_learnings, 
                    positive_highlights, improvement_suggestions, reason_for_leaving, 
                    personal_email, personal_phone, linkedin_profile, future_contact_consent, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')",
                "iiiiiiisssssssi",
                [
                    $userId, $overallRating, $mentorshipRating, $cultureRating,
                    $learningRating, $managementRating, $recommendScore, $keyLearnings,
                    $positiveHighlights, $improvementSuggestions, $reasonForLeaving,
                    $personalEmail, $personalPhone, $linkedinProfile, $futureConsent
                ]
            );

            if ($insertId) {
                // Log activity
                logActivity('exit_feedback_submitted', "Submitted exit feedback for employee #{$employee['employee_id']} ({$employee['name']})");

                // Notify Admin
                $adminUsers = fetchAll("SELECT id FROM admin");
                foreach ($adminUsers as $adm) {
                    insert(
                        "INSERT INTO notifications (user_id, user_type, title, message, type, link) VALUES (?, 'admin', 'Exit Feedback Submitted', ?, 'info', ?)",
                        "iss",
                        [$adm['id'], "{$employee['name']} ({$employee['employee_id']}) submitted exit feedback.", url('admin/exit-feedbacks')]
                    );
                }

                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_POST['ajax'])) {
                    echo json_encode(['success' => true, 'message' => 'Thank you! Your exit feedback has been submitted successfully.']);
                    exit;
                }

                $message = 'Thank you! Your exit feedback has been submitted successfully.';
                $messageType = 'success';
                // Reload existing feedback
                $existingFeedback = fetchOne("SELECT * FROM exit_feedbacks WHERE employee_id = ?", "i", [$userId]);
            } else {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    echo json_encode(['success' => false, 'message' => 'Database error while saving feedback. Please try again.']);
                    exit;
                }
                $message = 'Failed to submit feedback. Please try again.';
                $messageType = 'danger';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/employee-sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 font-weight-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Exit & Experience Feedback</h1>
                <p class="text-muted mb-0">Help us elevate Clientura by sharing honest insights about your tenure, learning, and team mentorship.</p>
            </div>
            <div>
                <a href="<?php echo url('employee/dashboard'); ?>" class="btn btn-saas-light">
                    <i class="bi bi-arrow-left me-1"></i>
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show rounded-4 shadow-sm" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($existingFeedback): ?>
            <!-- Already Submitted Summary Card -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="p-4 bg-primary text-white" style="background: linear-gradient(135deg, #0A1138 0%, #1a276b 100%) !important;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; font-size: 26px;">
                                <i class="bi bi-check-circle-fill text-success"></i>
                            </div>
                            <div>
                                <h4 class="mb-1 font-weight-bold" style="font-family: 'Outfit', sans-serif;">Feedback Recorded Successfully</h4>
                                <p class="mb-0 text-white-50 small">Submitted on <?php echo date('d F Y, h:i A', strtotime($existingFeedback['created_at'])); ?></p>
                            </div>
                        </div>
                        <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill font-weight-bold">
                            <i class="bi bi-shield-check me-1"></i> Recorded & Archived
                        </span>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">
                    <p class="text-muted mb-4 fs-6">
                        Thank you for taking the time to share your feedback, <strong><?php echo htmlspecialchars($employee['name']); ?></strong>! 
                        Your review has been securely transmitted to the management team. Below is a copy of your submitted evaluation:
                    </p>

                    <!-- Ratings Overview -->
                    <h6 class="font-weight-bold text-dark text-uppercase small tracking-wider mb-3">Your Tenure Ratings</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4 col-sm-6">
                            <div class="p-3 rounded-4 bg-light border border-light-subtle h-100">
                                <span class="text-muted small d-block mb-1">Overall Experience</span>
                                <div class="text-warning fs-5">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?php echo $i <= $existingFeedback['overall_rating'] ? '-fill' : ''; ?>"></i>
                                    <?php endfor; ?>
                                    <span class="text-dark fw-bold ms-2 small"><?php echo $existingFeedback['overall_rating']; ?>/5</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="p-3 rounded-4 bg-light border border-light-subtle h-100">
                                <span class="text-muted small d-block mb-1">Mentorship & Guidance</span>
                                <div class="text-warning fs-5">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?php echo $i <= $existingFeedback['mentorship_rating'] ? '-fill' : ''; ?>"></i>
                                    <?php endfor; ?>
                                    <span class="text-dark fw-bold ms-2 small"><?php echo $existingFeedback['mentorship_rating']; ?>/5</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="p-3 rounded-4 bg-light border border-light-subtle h-100">
                                <span class="text-muted small d-block mb-1">Work Culture & Inclusivity</span>
                                <div class="text-warning fs-5">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?php echo $i <= $existingFeedback['culture_rating'] ? '-fill' : ''; ?>"></i>
                                    <?php endfor; ?>
                                    <span class="text-dark fw-bold ms-2 small"><?php echo $existingFeedback['culture_rating']; ?>/5</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="p-3 rounded-4 bg-light border border-light-subtle h-100">
                                <span class="text-muted small d-block mb-1">Learning & Growth</span>
                                <div class="text-warning fs-5">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?php echo $i <= $existingFeedback['learning_rating'] ? '-fill' : ''; ?>"></i>
                                    <?php endfor; ?>
                                    <span class="text-dark fw-bold ms-2 small"><?php echo $existingFeedback['learning_rating']; ?>/5</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="p-3 rounded-4 bg-light border border-light-subtle h-100">
                                <span class="text-muted small d-block mb-1">Team Leadership & Management</span>
                                <div class="text-warning fs-5">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?php echo $i <= $existingFeedback['management_rating'] ? '-fill' : ''; ?>"></i>
                                    <?php endfor; ?>
                                    <span class="text-dark fw-bold ms-2 small"><?php echo $existingFeedback['management_rating']; ?>/5</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="p-3 rounded-4 bg-light border border-light-subtle h-100">
                                <span class="text-muted small d-block mb-1">Recommendation Score (NPS)</span>
                                <div class="fs-5 fw-bold text-primary">
                                    <i class="bi bi-hand-thumbs-up me-1"></i> <?php echo $existingFeedback['recommend_score']; ?> / 10
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Qualitative Responses -->
                    <div class="mb-4">
                        <h6 class="font-weight-bold text-dark text-uppercase small tracking-wider mb-2">Key Learnings & Project Highlights</h6>
                        <div class="p-3 bg-light rounded-3 text-dark"><?php echo nl2br(htmlspecialchars($existingFeedback['key_learnings'])); ?></div>
                    </div>

                    <div class="mb-4">
                        <h6 class="font-weight-bold text-dark text-uppercase small tracking-wider mb-2">What You Liked Most</h6>
                        <div class="p-3 bg-light rounded-3 text-dark"><?php echo nl2br(htmlspecialchars($existingFeedback['positive_highlights'])); ?></div>
                    </div>

                    <?php if (!empty($existingFeedback['improvement_suggestions'])): ?>
                    <div class="mb-4">
                        <h6 class="font-weight-bold text-dark text-uppercase small tracking-wider mb-2">Suggestions for Improvement</h6>
                        <div class="p-3 bg-light rounded-3 text-dark"><?php echo nl2br(htmlspecialchars($existingFeedback['improvement_suggestions'])); ?></div>
                    </div>
                    <?php endif; ?>

                    <div class="p-3 rounded-3 border bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span class="text-muted small">Stay connected via Alumni Network</span>
                        <div class="d-flex gap-2">
                            <?php if (!empty($existingFeedback['linkedin_profile'])): ?>
                                <a href="<?php echo htmlspecialchars($existingFeedback['linkedin_profile']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-linkedin me-1"></i> LinkedIn
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo url('employee/my-documents'); ?>" class="btn btn-sm btn-saas-dark">
                                <i class="bi bi-file-earmark-check me-1"></i> View Certificates & Documents
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>

            <!-- Exit Feedback Submission Form -->
            <div class="row g-4 align-items-start">
                <!-- Left Sidebar: Employee Context Card (Sticky) -->
                <div class="col-lg-4" style="position: sticky; top: 85px; z-index: 10;">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-transparent py-3">
                            <span class="font-weight-bold text-dark">Employee Information</span>
                        </div>
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3 mb-4">
                                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center font-weight-bold fs-4" style="width: 54px; height: 54px;">
                                    <?php echo strtoupper(substr($employee['name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h5 class="font-weight-bold text-dark mb-0"><?php echo htmlspecialchars($employee['name']); ?></h5>
                                    <span class="badge bg-secondary-subtle text-dark font-monospace"><?php echo htmlspecialchars($employee['employee_id']); ?></span>
                                </div>
                            </div>

                            <ul class="list-unstyled mb-4 small">
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span class="text-muted">Domain</span>
                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($employee['domain_name'] ?? 'General'); ?></span>
                                </li>
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span class="text-muted">Designation</span>
                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($employee['designation'] ?? 'Intern'); ?></span>
                                </li>
                                <li class="d-flex justify-content-between py-2 border-bottom">
                                    <span class="text-muted">Date of Joining</span>
                                    <span class="fw-semibold text-dark"><?php echo $employee['date_of_joining'] ? date('d M Y', strtotime($employee['date_of_joining'])) : 'N/A'; ?></span>
                                </li>
                                <li class="d-flex justify-content-between py-2">
                                    <span class="text-muted">Work Email</span>
                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($employee['email']); ?></span>
                                </li>
                            </ul>

                            <div class="p-3 rounded-3 bg-light-subtle border border-dashed text-muted small">
                                <i class="bi bi-shield-lock-fill text-primary me-1"></i>
                                <strong>Confidentiality Notice:</strong> Your responses help management enhance the internship and work program. Please provide constructive and candid insights.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Interactive Form -->
                <div class="col-lg-8">
                    <form id="exitFeedbackForm" method="POST" action="<?php echo url('employee/exit-feedback'); ?>">
                        <input type="hidden" name="action" value="submit_exit_feedback">

                        <!-- Section 1: Quantitative Experience Ratings -->
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-header bg-transparent py-3">
                                <span class="font-weight-bold text-dark">1. Program & Experience Evaluation</span>
                                <small class="text-muted d-block">Select ratings on a 1 (Needs Improvement) to 5 (Outstanding) scale.</small>
                            </div>
                            <div class="card-body p-4">
                                <?php
                                $ratingCategories = [
                                    'overall_rating' => [
                                        'label' => 'Overall Internship / Employment Experience',
                                        'desc' => 'How would you rate your total journey at Clientura?'
                                    ],
                                    'mentorship_rating' => [
                                        'label' => 'Technical Guidance & Mentorship',
                                        'desc' => 'Quality of support and guidance from your leads and seniors.'
                                    ],
                                    'culture_rating' => [
                                        'label' => 'Work Culture & Collaboration',
                                        'desc' => 'Inclusivity, team spirit, flexibility, and workplace environment.'
                                    ],
                                    'learning_rating' => [
                                        'label' => 'Skill Acquisition & Learning Opportunities',
                                        'desc' => 'Exposure to real-world tasks, industry tools, and career growth.'
                                    ],
                                    'management_rating' => [
                                        'label' => 'Management Transparency & Communication',
                                        'desc' => 'Clarity of expectations, constructive reviews, and leadership communication.'
                                    ],
                                ];

                                foreach ($ratingCategories as $field => $meta):
                                ?>
                                <div class="mb-4 pb-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label font-weight-bold text-dark mb-0"><?php echo $meta['label']; ?> <span class="text-danger">*</span></label>
                                        <span class="badge bg-light text-muted border rating-label-text" id="label_<?php echo $field; ?>">Click to rate</span>
                                    </div>
                                    <p class="text-muted small mb-2"><?php echo $meta['desc']; ?></p>
                                    
                                    <div class="star-rating-group d-flex gap-2" data-target="<?php echo $field; ?>">
                                        <input type="hidden" name="<?php echo $field; ?>" id="input_<?php echo $field; ?>" value="" required>
                                        <?php for ($star = 1; $star <= 5; $star++): ?>
                                            <button type="button" class="btn btn-sm star-btn text-warning border-0 p-0 fs-3" data-val="<?php echo $star; ?>" aria-label="Rate <?php echo $star; ?> stars">
                                                <i class="bi bi-star"></i>
                                            </button>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <!-- NPS Recommend Score -->
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label font-weight-bold text-dark mb-0">How likely are you to recommend Clientura to friends or peers? <span class="text-danger">*</span></label>
                                        <span class="badge bg-primary text-white" id="npsDisplay">10 / 10 (Extremely Likely)</span>
                                    </div>
                                    <p class="text-muted small mb-3">Scale from 1 (Not at all likely) to 10 (Extremely likely).</p>
                                    <input type="range" class="form-range" min="1" max="10" step="1" value="10" id="recommendScore" name="recommend_score">
                                    <div class="d-flex justify-content-between text-muted small" style="font-size: 11px;">
                                        <span>1 - Not Likely</span>
                                        <span>5 - Neutral</span>
                                        <span>10 - Extremely Likely</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Qualitative Insights -->
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-header bg-transparent py-3">
                                <span class="font-weight-bold text-dark">2. Qualitative Insights & Feedback</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="mb-3">
                                    <label class="form-label font-weight-bold text-dark small">Reason for Exit / Tenure Status <span class="text-danger">*</span></label>
                                    <select class="form-select" name="reason_for_leaving" required style="border-radius: 12px;">
                                        <option value="Completed Scheduled Internship Tenure" selected>Completed Scheduled Internship Tenure</option>
                                        <option value="Resuming College / Academic Exams">Resuming College / Academic Semester</option>
                                        <option value="Transitioning to Full-Time Role">Transitioning to Full-Time Role</option>
                                        <option value="Personal / Relocation Reasons">Personal / Relocation Reasons</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label font-weight-bold text-dark small">Key Skills & Practical Learnings <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="key_learnings" rows="3" required placeholder="What programming languages, frameworks, tools, or domain workflows did you master during your tenure?" style="border-radius: 12px;"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label font-weight-bold text-dark small">What did you enjoy most at Clientura? <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="positive_highlights" rows="3" required placeholder="Share the projects, mentorship moments, or cultural highlights you appreciated most." style="border-radius: 12px;"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label font-weight-bold text-dark small">Constructive Suggestions for Clientura (Optional)</label>
                                    <textarea class="form-control" name="improvement_suggestions" rows="3" placeholder="Where can we improve our processes, tools, training sessions, or communication?" style="border-radius: 12px;"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Alumni Network & Contact Details -->
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-header bg-transparent py-3">
                                <span class="font-weight-bold text-dark">3. Alumni Network & Staying Connected</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label font-weight-bold text-dark small">Personal Email (Non-Work) <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="personal_email" placeholder="Enter your email address" value="" required style="border-radius: 12px;">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label font-weight-bold text-dark small">Personal Mobile Number (10 Digits) <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" name="personal_phone" placeholder="Enter 10-digit mobile number" pattern="[0-9]{10}" maxlength="10" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);" value="" required style="border-radius: 12px;">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label font-weight-bold text-dark small">LinkedIn Profile URL</label>
                                        <input type="url" class="form-control" name="linkedin_profile" placeholder="Enter your LinkedIn profile URL" style="border-radius: 12px;">
                                    </div>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="future_contact_consent" value="1" id="consentCheck" checked>
                                    <label class="form-check-label text-muted small" for="consentCheck">
                                        I would love to remain connected through the Clientura Alumni Network and be considered for future full-time roles or project collaborations.
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-saas-dark w-100 py-3 shadow-sm rounded-4" id="btnSubmitFeedback">
                            <i class="bi bi-send-check me-1"></i>
                            <span>Submit Exit Feedback</span>
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const starLabels = {
        1: '1 - Poor',
        2: '2 - Fair',
        3: '3 - Good',
        4: '4 - Very Good',
        5: '5 - Outstanding'
    };

    // Star Rating Click & Hover
    document.querySelectorAll('.star-rating-group').forEach(group => {
        const targetId = group.getAttribute('data-target');
        const input = document.getElementById('input_' + targetId);
        const labelText = document.getElementById('label_' + targetId);
        const buttons = group.querySelectorAll('.star-btn');

        function setStars(val) {
            input.value = val > 0 ? val : '';
            if (labelText) {
                if (val > 0) {
                    labelText.className = 'badge bg-warning-subtle text-dark border rating-label-text';
                    labelText.innerText = starLabels[val] || (val + '/5');
                } else {
                    labelText.className = 'badge bg-light text-muted border rating-label-text';
                    labelText.innerText = 'Click to rate';
                }
            }
            buttons.forEach(btn => {
                const bVal = parseInt(btn.getAttribute('data-val'), 10);
                const icon = btn.querySelector('i');
                if (val > 0 && bVal <= val) {
                    icon.className = 'bi bi-star-fill';
                } else {
                    icon.className = 'bi bi-star';
                }
            });
        }

        buttons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const val = parseInt(this.getAttribute('data-val'), 10);
                setStars(val);
            });

            btn.addEventListener('mouseenter', function() {
                const hoverVal = parseInt(this.getAttribute('data-val'), 10);
                buttons.forEach(b => {
                    const bVal = parseInt(b.getAttribute('data-val'), 10);
                    b.querySelector('i').className = bVal <= hoverVal ? 'bi bi-star-fill' : 'bi bi-star';
                });
                if (labelText) {
                    labelText.className = 'badge bg-warning-subtle text-dark border rating-label-text';
                    labelText.innerText = starLabels[hoverVal] || (hoverVal + '/5');
                }
            });
        });

        group.addEventListener('mouseleave', function() {
            const cur = parseInt(input.value, 10) || 0;
            setStars(cur);
        });
    });

    // NPS Range
    const nps = document.getElementById('recommendScore');
    const npsDisplay = document.getElementById('npsDisplay');
    if (nps && npsDisplay) {
        nps.addEventListener('input', function() {
            const v = parseInt(this.value, 10);
            let desc = 'Extremely Likely';
            if (v <= 4) desc = 'Not Likely';
            else if (v <= 7) desc = 'Somewhat Likely';
            else if (v <= 8) desc = 'Likely';
            npsDisplay.innerText = v + ' / 10 (' + desc + ')';
        });
    }

    // Submit Feedback Form via AJAX
    const form = document.getElementById('exitFeedbackForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Client-side validation: must select all star ratings
            const requiredRatings = ['overall_rating', 'mentorship_rating', 'culture_rating', 'learning_rating', 'management_rating'];
            for (const r of requiredRatings) {
                const v = document.getElementById('input_' + r).value;
                if (!v || parseInt(v, 10) < 1) {
                    alert('Please provide a star rating for all evaluation categories.');
                    const grp = document.querySelector('.star-rating-group[data-target="' + r + '"]');
                    if (grp) grp.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }
            }

            // Phone validation: exactly 10 digits
            const phoneInput = form.querySelector('input[name="personal_phone"]');
            const phoneVal = phoneInput ? phoneInput.value.replace(/[^0-9]/g, '') : '';
            if (phoneVal.length !== 10) {
                alert('Please enter a valid 10-digit mobile number.');
                if (phoneInput) phoneInput.focus();
                return;
            }

            const btn = document.getElementById('btnSubmitFeedback');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Submitting Feedback...';

            const formData = new FormData(form);
            formData.append('ajax', '1');

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Error submitting feedback.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-send-check me-1"></i> Submit Exit Feedback';
                }
            })
            .catch(err => {
                console.error(err);
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send-check me-1"></i> Submit Exit Feedback';
                // Fallback to normal form submit if fetch fails
                form.submit();
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

