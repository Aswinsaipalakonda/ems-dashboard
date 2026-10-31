<?php
/**
 * Admin Exit Feedbacks Module
 * Review employee and intern exit feedback, ratings, and alumni records.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . url('login'));
    exit;
}

$pageTitle = 'Exit Feedbacks';

// Export CSV handler
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $feedbacks = fetchAll(
        "SELECT ef.*, e.name AS emp_name, e.employee_id AS emp_code, e.designation, d.name AS domain_name
         FROM exit_feedbacks ef
         JOIN employees e ON ef.employee_id = e.id
         LEFT JOIN domains d ON e.domain_id = d.id
         ORDER BY ef.created_at DESC"
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Exit_Feedbacks_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'ID', 'Emp ID', 'Name', 'Designation', 'Domain', 'Overall (1-5)', 
        'Mentorship (1-5)', 'Culture (1-5)', 'Learning (1-5)', 'Management (1-5)', 
        'Recommend (1-10)', 'Reason for Leaving', 'Key Learnings', 'Highlights', 
        'Improvements', 'Personal Email', 'Personal Phone', 'LinkedIn', 'Consent', 'Submitted Date'
    ]);

    foreach ($feedbacks as $fb) {
        fputcsv($output, [
            $fb['id'],
            $fb['emp_code'],
            $fb['emp_name'],
            $fb['designation'],
            $fb['domain_name'] ?? 'General',
            $fb['overall_rating'],
            $fb['mentorship_rating'],
            $fb['culture_rating'],
            $fb['learning_rating'],
            $fb['management_rating'],
            $fb['recommend_score'],
            $fb['reason_for_leaving'],
            $fb['key_learnings'],
            $fb['positive_highlights'],
            $fb['improvement_suggestions'],
            $fb['personal_email'],
            $fb['personal_phone'],
            $fb['linkedin_profile'],
            $fb['future_contact_consent'] ? 'Yes' : 'No',
            $fb['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// Fetch domain list for filter dropdown
$domains = fetchAll("SELECT id, name FROM domains WHERE status = 'active' ORDER BY name ASC");

// Build Query Filters
$domainFilter = isset($_GET['domain']) ? (int)$_GET['domain'] : 0;
$ratingFilter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$search = trim($_GET['search'] ?? '');

$sql = "SELECT ef.*, e.name AS emp_name, e.employee_id AS emp_code, e.designation, d.name AS domain_name
        FROM exit_feedbacks ef
        JOIN employees e ON ef.employee_id = e.id
        LEFT JOIN domains d ON e.domain_id = d.id
        WHERE 1=1";
$params = [];
$types = "";

if ($domainFilter > 0) {
    $sql .= " AND e.domain_id = ?";
    $types .= "i";
    $params[] = $domainFilter;
}

if ($ratingFilter > 0) {
    $sql .= " AND ef.overall_rating = ?";
    $types .= "i";
    $params[] = $ratingFilter;
}

if (!empty($search)) {
    $sql .= " AND (e.name LIKE ? OR e.employee_id LIKE ? OR ef.key_learnings LIKE ?)";
    $types .= "sss";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY ef.created_at DESC";

$feedbacks = !empty($params) ? fetchAll($sql, $types, $params) : fetchAll($sql);

// Compute Summary Metrics
$stats = fetchOne(
    "SELECT 
        COUNT(*) as total_count,
        AVG(overall_rating) as avg_overall,
        AVG(mentorship_rating) as avg_mentorship,
        AVG(recommend_score) as avg_recommend
     FROM exit_feedbacks"
);

$totalCount = (int)($stats['total_count'] ?? 0);
$avgOverall = ($totalCount > 0 && $stats['avg_overall'] !== null) ? number_format((float)$stats['avg_overall'], 1) : '0.0';
$avgMentorship = ($totalCount > 0 && $stats['avg_mentorship'] !== null) ? number_format((float)$stats['avg_mentorship'], 1) : '0.0';
$avgRecommend = ($totalCount > 0 && $stats['avg_recommend'] !== null) ? number_format((float)$stats['avg_recommend'], 1) : '0.0';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h3 font-weight-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Exit & Offboarding Feedbacks</h1>
                <p class="text-muted mb-0">Gain actionable workforce insights, analyze internship satisfaction, and manage alumni connections.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?php echo url('admin/exit-feedbacks?action=export_csv'); ?>" class="btn btn-saas-light">
                    <i class="bi bi-download me-1"></i>
                    <span>Export CSV</span>
                </a>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">TOTAL FEEDBACKS</span>
                        <div class="metric-icon-badge purple">
                            <i class="bi bi-chat-square-text"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $totalCount; ?></div>
                    <div class="metric-label">Submissions Logged</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">OVERALL SATISFACTION</span>
                        <div class="metric-icon-badge green">
                            <i class="bi bi-star-fill"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $avgOverall; ?> <span class="fs-6 text-muted">/ 5.0</span></div>
                    <div class="metric-label">Average Experience Rating</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">MENTORSHIP QUALITY</span>
                        <div class="metric-icon-badge blue">
                            <i class="bi bi-person-check-fill"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $avgMentorship; ?> <span class="fs-6 text-muted">/ 5.0</span></div>
                    <div class="metric-label">Technical Guidance Score</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span class="metric-tiled-title">NET ADVOCACY (NPS)</span>
                        <div class="metric-icon-badge amber">
                            <i class="bi bi-hand-thumbs-up-fill"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $avgRecommend; ?> <span class="fs-6 text-muted">/ 10</span></div>
                    <div class="metric-label">Alumni Recommendation Rate</div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-3">
                <form method="GET" action="<?php echo url('admin/exit-feedbacks'); ?>" class="row g-2 align-items-center">
                    <div class="col-lg-5 col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0 text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Search by recipient name, ID, or keyword..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-3">
                        <select name="domain" class="form-select">
                            <option value="0">All Domains</option>
                            <?php foreach ($domains as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo $domainFilter === (int)$d['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-3">
                        <select name="rating" class="form-select">
                            <option value="0">All Ratings</option>
                            <option value="5" <?php echo $ratingFilter === 5 ? 'selected' : ''; ?>>5 Stars (Outstanding)</option>
                            <option value="4" <?php echo $ratingFilter === 4 ? 'selected' : ''; ?>>4 Stars (Very Good)</option>
                            <option value="3" <?php echo $ratingFilter === 3 ? 'selected' : ''; ?>>3 Stars or Lower</option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-saas-dark flex-grow-1">Filter</button>
                        <?php if (!empty($search) || $domainFilter > 0 || $ratingFilter > 0): ?>
                            <a href="<?php echo url('admin/exit-feedbacks'); ?>" class="btn btn-light" title="Clear Filters">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Feedbacks Log Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table saas-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Domain / Role</th>
                            <th>Overall Rating</th>
                            <th>Mentorship</th>
                            <th>Culture</th>
                            <th>Recommend (NPS)</th>
                            <th>Submitted Date</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($feedbacks)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="bi bi-chat-square-text text-muted fs-1 opacity-25 d-block mb-2"></i>
                                <h6 class="fw-bold text-dark">No Exit Feedbacks Found</h6>
                                <p class="text-muted small mb-0">Outgoing interns and employees will appear here once they complete the exit form.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($feedbacks as $fb): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($fb['emp_name']); ?></div>
                                    <small class="font-monospace text-muted"><?php echo htmlspecialchars($fb['emp_code']); ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark small"><?php echo htmlspecialchars($fb['domain_name'] ?? 'General'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($fb['designation']); ?></small>
                                </td>
                                <td>
                                    <div class="text-warning small">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?php echo $i <= $fb['overall_rating'] ? '-fill' : ''; ?>"></i>
                                        <?php endfor; ?>
                                        <span class="text-dark fw-bold ms-1"><?php echo $fb['overall_rating']; ?>/5</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-person-check me-1 text-primary"></i> <?php echo $fb['mentorship_rating']; ?>/5
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-heart me-1 text-danger"></i> <?php echo $fb['culture_rating']; ?>/5
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $fb['recommend_score'] >= 8 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'; ?> font-weight-bold">
                                        <?php echo $fb['recommend_score']; ?> / 10
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo date('d M Y', strtotime($fb['created_at'])); ?></small>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#feedbackModal_<?php echo $fb['id']; ?>">
                                        <i class="bi bi-eye me-1"></i> View Full
                                    </button>
                                </td>
                            </tr>

                            <!-- Detail Modal for Feedback -->
                            <div class="modal fade" id="feedbackModal_<?php echo $fb['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content rounded-4 border-0 shadow">
                                        <div class="modal-header border-bottom py-3">
                                            <h5 class="modal-title font-weight-bold text-dark">
                                                Exit Feedback: <?php echo htmlspecialchars($fb['emp_name']); ?> (<?php echo htmlspecialchars($fb['emp_code']); ?>)
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <!-- Rating Metrics Pill Row -->
                                            <div class="row g-2 mb-4 text-center">
                                                <div class="col">
                                                    <div class="p-2 rounded-3 bg-light border">
                                                        <small class="text-muted d-block" style="font-size: 11px;">Overall</small>
                                                        <strong class="text-warning"><?php echo $fb['overall_rating']; ?>/5</strong>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="p-2 rounded-3 bg-light border">
                                                        <small class="text-muted d-block" style="font-size: 11px;">Mentorship</small>
                                                        <strong class="text-primary"><?php echo $fb['mentorship_rating']; ?>/5</strong>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="p-2 rounded-3 bg-light border">
                                                        <small class="text-muted d-block" style="font-size: 11px;">Culture</small>
                                                        <strong class="text-success"><?php echo $fb['culture_rating']; ?>/5</strong>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="p-2 rounded-3 bg-light border">
                                                        <small class="text-muted d-block" style="font-size: 11px;">Learning</small>
                                                        <strong class="text-info"><?php echo $fb['learning_rating']; ?>/5</strong>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="p-2 rounded-3 bg-light border">
                                                        <small class="text-muted d-block" style="font-size: 11px;">NPS</small>
                                                        <strong class="text-dark"><?php echo $fb['recommend_score']; ?>/10</strong>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <h6 class="fw-bold text-dark small text-uppercase">Reason for Exit / Tenure Status</h6>
                                                <div class="p-3 bg-light rounded-3 text-dark"><?php echo htmlspecialchars($fb['reason_for_leaving'] ?? 'Completed Scheduled Internship Tenure'); ?></div>
                                            </div>

                                            <div class="mb-3">
                                                <h6 class="fw-bold text-dark small text-uppercase">Key Skills Acquired</h6>
                                                <div class="p-3 bg-light rounded-3 text-dark"><?php echo nl2br(htmlspecialchars($fb['key_learnings'])); ?></div>
                                            </div>

                                            <div class="mb-3">
                                                <h6 class="fw-bold text-dark small text-uppercase">Highlights & Positive Experiences</h6>
                                                <div class="p-3 bg-light rounded-3 text-dark"><?php echo nl2br(htmlspecialchars($fb['positive_highlights'])); ?></div>
                                            </div>

                                            <?php if (!empty($fb['improvement_suggestions'])): ?>
                                            <div class="mb-3">
                                                <h6 class="fw-bold text-dark small text-uppercase">Suggestions for Improvement</h6>
                                                <div class="p-3 bg-light rounded-3 text-dark"><?php echo nl2br(htmlspecialchars($fb['improvement_suggestions'])); ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Contact & Alumni Info -->
                                            <div class="p-3 rounded-3 border bg-light-subtle">
                                                <h6 class="fw-bold text-dark small mb-2"><i class="bi bi-people-fill me-1 text-primary"></i> Alumni & Post-Tenure Contact Details</h6>
                                                <div class="row g-2 small">
                                                    <div class="col-sm-6">
                                                        <span class="text-muted d-block">Personal Email:</span>
                                                        <strong><?php echo htmlspecialchars($fb['personal_email'] ?: 'N/A'); ?></strong>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <span class="text-muted d-block">Phone / WhatsApp:</span>
                                                        <strong><?php echo htmlspecialchars($fb['personal_phone'] ?: 'N/A'); ?></strong>
                                                    </div>
                                                    <div class="col-12 mt-2">
                                                        <span class="text-muted d-block">LinkedIn Profile:</span>
                                                        <?php if (!empty($fb['linkedin_profile'])): ?>
                                                            <a href="<?php echo htmlspecialchars($fb['linkedin_profile']); ?>" target="_blank" class="text-primary text-decoration-none">
                                                                <?php echo htmlspecialchars($fb['linkedin_profile']); ?> <i class="bi bi-box-arrow-up-right ms-1"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">Not provided</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-top py-2">
                                            <button type="button" class="btn btn-saas-light" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

