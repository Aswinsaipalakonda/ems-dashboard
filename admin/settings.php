<?php
/**
 * Admin Settings - SaaS Platform Configuration
 * Employee Management System
 */

$pageTitle = 'Platform Settings';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$message = '';
$error = '';

// Get current settings
$settings = [];
$settingsData = fetchAll("SELECT * FROM settings");
foreach ($settingsData as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $updates = [
        'company_name' => sanitize($_POST['company_name']),
        'company_email' => sanitize($_POST['company_email']),
        'company_phone' => sanitize($_POST['company_phone']),
        'company_address' => sanitize($_POST['company_address']),
        'working_hours_start' => sanitize($_POST['working_hours_start']),
        'working_hours_end' => sanitize($_POST['working_hours_end']),
        'late_threshold' => sanitize($_POST['late_threshold']),
        'auto_approve_attendance' => isset($_POST['auto_approve_attendance']) ? '1' : '0',
        'auto_checkout_enabled' => isset($_POST['auto_checkout_enabled']) ? '1' : '0',
        'enable_geolocation' => isset($_POST['enable_geolocation']) ? '1' : '0',
        'enable_photo_capture' => isset($_POST['enable_photo_capture']) ? '1' : '0',
    ];
    
    foreach ($updates as $key => $value) {
        $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", "s", [$key]);
        if ($existing) {
            executeQuery("UPDATE settings SET setting_value = ? WHERE setting_key = ?", "ss", [$value, $key]);
        } else {
            executeQuery("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)", "ss", [$key, $value]);
        }
    }
    
    logActivity('settings_update', 'System settings updated');
    $message = 'Platform parameters and organizational policies saved successfully.';
    
    // Refresh settings
    $settings = [];
    $settingsData = fetchAll("SELECT * FROM settings");
    foreach ($settingsData as $s) {
        $settings[$s['setting_key']] = $s['setting_value'];
    }
}

$totalStaff = fetchOne("SELECT COUNT(*) as count FROM employees")['count'] ?? 0;
$totalAttendance = fetchOne("SELECT COUNT(*) as count FROM attendance")['count'] ?? 0;
$totalTasks = fetchOne("SELECT COUNT(*) as count FROM tasks")['count'] ?? 0;
$totalLogs = fetchOne("SELECT COUNT(*) as count FROM activity_logs")['count'] ?? 0;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Platform Configuration</h1>
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
        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <?php echo pika_icon('check-circle', 'flex-shrink-0', 20); ?>
                <div><?php echo $message; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <?php echo pika_icon('x', 'flex-shrink-0', 20); ?>
                <div><?php echo $error; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Hero Hub -->
        <div class="saas-hero">
            <div class="row align-items-center">
                <div class="col-xl-7 col-lg-6">
                    <div class="saas-hero-content">
                        <h2 class="saas-hero-title">Platform Controls & Global Policies</h2>
                        <p class="saas-hero-subtitle">
                            Configure company identifiers, geofencing coordinates, automated shift sign-out schedules, and biometric capture thresholds.
                        </p>
                        <div class="saas-hero-pills">
                            <span class="saas-pill live-pulse">
                                <span class="pulse-dot"></span>
                                Production Environment Active
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('clock', '', 14); ?>
                                Shift: <?php echo htmlspecialchars($settings['working_hours_start'] ?? '09:00'); ?> - <?php echo htmlspecialchars($settings['working_hours_end'] ?? '18:00'); ?>
                            </span>
                            <span class="saas-pill">
                                <?php echo pika_icon('shield-check', '', 14); ?>
                                Strict Security Token Protocol
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-5 col-lg-6 mt-3 mt-lg-0 text-lg-end">
                    <div class="saas-hero-actions">
                        <button type="button" onclick="document.getElementById('settingsSubmitBtn').click();" class="btn btn-saas-dark">
                            <?php echo pika_icon('check', '', 16); ?>
                            <span>Save All Parameters</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Dynamic Metric Cards -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge navy">
                            <?php echo pika_icon('users', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active">Workforce</span>
                    </div>
                    <div class="metric-value"><?php echo $totalStaff; ?></div>
                    <div class="metric-label">Enrolled Employees</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge emerald">
                            <?php echo pika_icon('check-circle', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill active">Telemetry</span>
                    </div>
                    <div class="metric-value"><?php echo $totalAttendance; ?></div>
                    <div class="metric-label">Verified Check-in Sessions</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge sky">
                            <?php echo pika_icon('tasks', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill sky">Projects</span>
                    </div>
                    <div class="metric-value"><?php echo $totalTasks; ?></div>
                    <div class="metric-label">Workstream Tasks Logged</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <div class="metric-icon-badge amber">
                            <?php echo pika_icon('shield-check', '', 24); ?>
                        </div>
                        <span class="metric-trend-pill warning">Audit Trail</span>
                    </div>
                    <div class="metric-value"><?php echo $totalLogs; ?></div>
                    <div class="metric-label">Security Audit Events</div>
                </div>
            </div>
        </div>

        <form method="POST" id="settingsForm">
            <div class="row g-4">
                <!-- Company Identity -->
                <div class="col-lg-6">
                    <div class="saas-card h-100">
                        <div class="saas-card-header">
                            <h5 class="saas-card-title">
                                <?php echo pika_icon('building', 'text-primary', 20); ?>
                                Enterprise Organization Profile
                            </h5>
                        </div>
                        <div class="saas-card-body p-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark small">Company / Brand Name</label>
                                <input type="text" class="form-control rounded-pill px-3 py-2" name="company_name" 
                                       value="<?php echo htmlspecialchars($settings['company_name'] ?? 'Clientura EMS'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark small">Primary Contact Email</label>
                                <input type="email" class="form-control rounded-pill px-3 py-2" name="company_email" 
                                       value="<?php echo htmlspecialchars($settings['company_email'] ?? 'contact@clientura.in'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark small">Corporate Phone Number</label>
                                <input type="text" class="form-control rounded-pill px-3 py-2" name="company_phone" 
                                       value="<?php echo htmlspecialchars($settings['company_phone'] ?? '+91 90000 00000'); ?>">
                            </div>
                            <div class="mb-0">
                                <label class="form-label fw-bold text-dark small">Registered Head Office Address</label>
                                <textarea class="form-control rounded-4 p-3" name="company_address" rows="3"><?php echo htmlspecialchars($settings['company_address'] ?? 'Hyderabad, Telangana, India'); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Working Hours & Automation -->
                <div class="col-lg-6">
                    <div class="saas-card h-100">
                        <div class="saas-card-header">
                            <h5 class="saas-card-title">
                                <?php echo pika_icon('clock', 'text-primary', 20); ?>
                                Shift Schedules & Automation Rules
                            </h5>
                        </div>
                        <div class="saas-card-body p-4">
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold text-dark small">Workday Shift Start</label>
                                    <input type="time" class="form-control rounded-pill px-3 py-2" name="working_hours_start" 
                                           value="<?php echo htmlspecialchars($settings['working_hours_start'] ?? '09:00'); ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold text-dark small">Workday Shift End</label>
                                    <input type="time" class="form-control rounded-pill px-3 py-2" name="working_hours_end" 
                                           value="<?php echo htmlspecialchars($settings['working_hours_end'] ?? '18:00'); ?>">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark small">Late Arrival Tolerance (Minutes)</label>
                                <input type="number" class="form-control rounded-pill px-3 py-2" name="late_threshold" 
                                       value="<?php echo htmlspecialchars($settings['late_threshold'] ?? '15'); ?>" min="0" max="60">
                                <small class="text-muted">Grace period in minutes before check-in is logged as tardy.</small>
                            </div>
                            
                            <!-- Toggle switches -->
                            <div class="p-3 bg-light rounded-4 border mb-3">
                                <div class="form-check form-switch d-flex align-items-center justify-content-between m-0">
                                    <div>
                                        <label class="form-check-label fw-bold text-dark small mb-0" for="autoApprove">
                                            Automate Check-in Approvals
                                        </label>
                                        <div class="text-muted small">Instantly verify check-ins without routing to the pending queue.</div>
                                    </div>
                                    <input class="form-check-input" type="checkbox" name="auto_approve_attendance" id="autoApprove"
                                           style="width: 2.8em; height: 1.4em;"
                                           <?php echo ($settings['auto_approve_attendance'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded-4 border">
                                <div class="form-check form-switch d-flex align-items-center justify-content-between m-0">
                                    <div>
                                        <label class="form-check-label fw-bold text-dark small mb-0" for="autoCheckout">
                                            Automate Shift Sign-Out
                                        </label>
                                        <div class="text-muted small">Automatically trigger check-out when the working hours end time elapses.</div>
                                    </div>
                                    <input class="form-check-input" type="checkbox" name="auto_checkout_enabled" id="autoCheckout"
                                           style="width: 2.8em; height: 1.4em;"
                                           <?php echo ($settings['auto_checkout_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Telemetry & Camera Verification -->
                <div class="col-lg-6">
                    <div class="saas-card h-100">
                        <div class="saas-card-header">
                            <h5 class="saas-card-title">
                                <?php echo pika_icon('map-pin', 'text-primary', 20); ?>
                                Geofence Telemetry & Camera Rules
                            </h5>
                        </div>
                        <div class="saas-card-body p-4">
                            <div class="p-3 bg-light rounded-4 border mb-3">
                                <div class="form-check form-switch d-flex align-items-center justify-content-between m-0">
                                    <div>
                                        <label class="form-check-label fw-bold text-dark small mb-0" for="enableGeo">
                                            Enforce GPS Coordinates
                                        </label>
                                        <div class="text-muted small">Record accurate latitude and longitude pinpoint on every employee session.</div>
                                    </div>
                                    <input class="form-check-input" type="checkbox" name="enable_geolocation" id="enableGeo"
                                           style="width: 2.8em; height: 1.4em;"
                                           <?php echo ($settings['enable_geolocation'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded-4 border">
                                <div class="form-check form-switch d-flex align-items-center justify-content-between m-0">
                                    <div>
                                        <label class="form-check-label fw-bold text-dark small mb-0" for="enablePhoto">
                                            Enforce Biometric Photo Verification
                                        </label>
                                        <div class="text-muted small">Require live selfie capture via camera before attendance validation.</div>
                                    </div>
                                    <input class="form-check-input" type="checkbox" name="enable_photo_capture" id="enablePhoto"
                                           style="width: 2.8em; height: 1.4em;"
                                           <?php echo ($settings['enable_photo_capture'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security & Token Protocol -->
                <div class="col-lg-6">
                    <div class="saas-card h-100">
                        <div class="saas-card-header">
                            <h5 class="saas-card-title">
                                <?php echo pika_icon('lock', 'text-primary', 20); ?>
                                Security Token & Credential Engine
                            </h5>
                        </div>
                        <div class="saas-card-body p-4">
                            <div class="p-3 bg-light rounded-4 border mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted small">Credential Reset Method:</span>
                                    <span class="saas-id-badge">Cryptographic Token</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted small">Token Expiry Window:</span>
                                    <span class="fw-semibold text-dark small">30 minutes</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small">Password Algorithm:</span>
                                    <span class="saas-tag emerald">BCRYPT Standard</span>
                                </div>
                            </div>
                            <p class="text-muted small mb-0">
                                Password resets are securely executed via single-use encrypted tokens generated on-demand without requiring SMTP third-party relays.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Save Bar -->
            <div class="mt-4 pt-2 text-end">
                <button type="submit" name="update_settings" id="settingsSubmitBtn" class="btn btn-saas-dark px-5 py-3">
                    <?php echo pika_icon('check', '', 18); ?>
                    <span>Save Platform Parameters</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
