<?php
/**
 * Admin Settings
 * Employee Management System
 */

$pageTitle = 'Settings';
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
    $message = 'Settings updated successfully';
    
    // Refresh settings
    $settings = [];
    $settingsData = fetchAll("SELECT * FROM settings");
    foreach ($settingsData as $s) {
        $settings[$s['setting_key']] = $s['setting_value'];
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">System Settings</h1>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="Avatar" class="user-avatar">
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo url('admin/profile'); ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo url('logout'); ?>"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <div class="content-wrapper">
        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <!-- Company Information -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-building me-2"></i>Company Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" class="form-control" name="company_name" 
                                       value="<?php echo $settings['company_name'] ?? 'EMS Dashboard'; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Company Email</label>
                                <input type="email" class="form-control" name="company_email" 
                                       value="<?php echo $settings['company_email'] ?? ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Company Phone</label>
                                <input type="text" class="form-control" name="company_phone" 
                                       value="<?php echo $settings['company_phone'] ?? ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Company Address</label>
                                <textarea class="form-control" name="company_address" rows="3"><?php echo $settings['company_address'] ?? ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Working Hours -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-clock me-2"></i>Working Hours</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">Start Time</label>
                                    <input type="time" class="form-control" name="working_hours_start" 
                                           value="<?php echo $settings['working_hours_start'] ?? '09:00'; ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">End Time</label>
                                    <input type="time" class="form-control" name="working_hours_end" 
                                           value="<?php echo $settings['working_hours_end'] ?? '18:00'; ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Late Threshold (minutes)</label>
                                <input type="number" class="form-control" name="late_threshold" 
                                       value="<?php echo $settings['late_threshold'] ?? '15'; ?>" min="0" max="60">
                                <small class="text-muted">Minutes after start time considered late</small>
                            </div>
                            
                            <hr>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="auto_approve_attendance" id="autoApprove"
                                       <?php echo ($settings['auto_approve_attendance'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="autoApprove">
                                    Auto-approve attendance
                                </label>
                                <small class="d-block text-muted">Automatically approve check-ins without admin review</small>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="auto_checkout_enabled" id="autoCheckout"
                                       <?php echo ($settings['auto_checkout_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="autoCheckout">
                                    <i class="bi bi-clock-history me-1"></i>Auto-checkout at End Time
                                </label>
                                <small class="d-block text-muted">Automatically check out employees at the working hours end time</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Check-in Settings -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-camera me-2"></i>Check-in Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="enable_geolocation" id="enableGeo"
                                       <?php echo ($settings['enable_geolocation'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enableGeo">
                                    <i class="bi bi-geo-alt me-1"></i>Require Geolocation
                                </label>
                                <small class="d-block text-muted">Capture GPS coordinates during check-in</small>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="enable_photo_capture" id="enablePhoto"
                                       <?php echo ($settings['enable_photo_capture'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enablePhoto">
                                    <i class="bi bi-camera me-1"></i>Require Photo Capture
                                </label>
                                <small class="d-block text-muted">Capture selfie during check-in</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>System Statistics</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $totalEmployees = fetchOne("SELECT COUNT(*) as count FROM employees")['count'];
                            $totalAttendance = fetchOne("SELECT COUNT(*) as count FROM attendance")['count'];
                            $totalTasks = fetchOne("SELECT COUNT(*) as count FROM tasks")['count'];
                            $totalLogs = fetchOne("SELECT COUNT(*) as count FROM activity_logs")['count'];
                            ?>
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h3 class="text-primary mb-1"><?php echo $totalEmployees; ?></h3>
                                        <small class="text-muted">Employees</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h3 class="text-success mb-1"><?php echo $totalAttendance; ?></h3>
                                        <small class="text-muted">Attendance Records</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-3">
                                        <h3 class="text-warning mb-1"><?php echo $totalTasks; ?></h3>
                                        <small class="text-muted">Tasks</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-3">
                                        <h3 class="text-info mb-1"><?php echo $totalLogs; ?></h3>
                                        <small class="text-muted">Activity Logs</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Password Reset System -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-key me-2"></i>Password Reset System</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                This system uses <strong>manual token-based password reset</strong>. No email configuration required.
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Reset Method</label>
                                <input type="text" class="form-control" value="Manual Token (No Email)" disabled>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Token Validity</label>
                                <input type="text" class="form-control" value="30 minutes" disabled>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Token Type</label>
                                <input type="text" class="form-control" value="Single-use, Cryptographically Secure" disabled>
                            </div>
                            
                            <p class="text-muted small">
                                <i class="bi bi-shield-lock me-1"></i>
                                Access <code>forgot-password.php</code> to generate reset tokens. Share links securely with users.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" name="update_settings" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-1"></i>Save All Settings
                </button>
            </div>
        </form>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>
