<?php
/**
 * Team Lead - Profile
 * Employee Management System
 */

$pageTitle = 'My Profile';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (!isTeamLead()) {
    header("Location: " . url("employee/dashboard"));
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        
        $result = update(
            "UPDATE employees SET phone = ?, address = ?, updated_at = NOW() WHERE id = ?",
            "ssi",
            [$phone, $address, $userId]
        );
        
        if ($result) {
            $message = "Profile updated successfully!";
            $messageType = 'success';
        } else {
            $message = "Failed to update profile.";
            $messageType = 'danger';
        }
    }
    
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Verify current password
        $employee = fetchOne("SELECT password FROM employees WHERE id = ?", "i", [$userId]);
        
        if (!password_verify($currentPassword, $employee['password'])) {
            $message = "Current password is incorrect.";
            $messageType = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $message = "New passwords do not match.";
            $messageType = 'danger';
        } elseif (strlen($newPassword) < 6) {
            $message = "New password must be at least 6 characters.";
            $messageType = 'danger';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $result = update("UPDATE employees SET password = ?, updated_at = NOW() WHERE id = ?", "si", [$hashedPassword, $userId]);
            
            if ($result) {
                $message = "Password changed successfully!";
                $messageType = 'success';
            } else {
                $message = "Failed to change password.";
                $messageType = 'danger';
            }
        }
    }
    
    // Handle avatar upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
            $message = "Invalid file type. Only JPG, PNG, GIF allowed.";
            $messageType = 'danger';
        } elseif ($_FILES['avatar']['size'] > $maxSize) {
            $message = "File too large. Maximum size is 2MB.";
            $messageType = 'danger';
        } else {
            $uploadDir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $filepath)) {
                $avatarPath = 'uploads/avatars/' . $filename;
                update("UPDATE employees SET avatar = ? WHERE id = ?", "si", [$avatarPath, $userId]);
                $_SESSION['avatar'] = $avatarPath;
                $message = "Avatar updated successfully!";
                $messageType = 'success';
            } else {
                $message = "Failed to upload avatar.";
                $messageType = 'danger';
            }
        }
    }
    
    // Handle avatar removal
    if (isset($_POST['remove_avatar'])) {
        $uploadDir = __DIR__ . '/../uploads/avatars/';
        $employee = fetchOne("SELECT avatar FROM employees WHERE id = ?", "i", [$userId]);
        
        if ($employee['avatar'] && file_exists($uploadDir . basename($employee['avatar']))) {
            unlink($uploadDir . basename($employee['avatar']));
        }
        
        $result = update("UPDATE employees SET avatar = NULL WHERE id = ?", "i", [$userId]);
        
        if ($result) {
            $_SESSION['avatar'] = null;
            $message = "Profile image removed successfully!";
            $messageType = 'success';
        } else {
            $message = "Failed to remove profile image.";
            $messageType = 'danger';
        }
    }
}

// Fetch employee data
$employee = fetchOne(
    "SELECT e.*, d.name as domain_name, r.name as role_name
     FROM employees e
     LEFT JOIN domains d ON e.domain_id = d.id
     LEFT JOIN roles r ON e.role_id = r.id
     WHERE e.id = ?",
    "i",
    [$userId]
);

// Get team member count
$teamMemberCount = fetchOne(
    "SELECT COUNT(*) as count FROM employees WHERE team_lead_id = ?",
    "i",
    [$userId]
)['count'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/teamlead-sidebar.php';
?>

<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">My Profile</h1>
        </div>
    </header>

    <div class="content-wrapper">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Card -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="<?php echo getAvatar($employee['avatar']); ?>" class="avatar avatar-xl mb-3" alt="Avatar">
                        <h4 class="mb-1"><?php echo htmlspecialchars($employee['name']); ?></h4>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($employee['designation'] ?? 'Team Lead'); ?></p>
                        <span class="badge bg-info"><?php echo $employee['role_name'] ?? 'Team Lead'; ?></span>
                        
                        <hr>
                        
                        <div class="text-start">
                            <p><strong>Employee ID:</strong> <?php echo $employee['employee_id']; ?></p>
                            <p><strong>Email:</strong> <?php echo $employee['email']; ?></p>
                            <p><strong>Domain:</strong> <?php echo $employee['domain_name'] ?? 'N/A'; ?></p>
                            <p><strong>Team Members:</strong> <?php echo $teamMemberCount; ?></p>
                            <p><strong>Joined:</strong> <?php echo $employee['date_of_joining'] ? formatDate($employee['date_of_joining']) : 'N/A'; ?></p>
                        </div>
                        
                        <!-- Avatar Upload -->
                        <form method="POST" enctype="multipart/form-data" class="mt-3">
                            <div class="input-group">
                                <input type="file" name="avatar" class="form-control form-control-sm" accept="image/*" title="Max 2MB (JPG, PNG, GIF)">
                                <button type="submit" class="btn btn-sm btn-primary">Upload</button>
                            </div>
                            <small class="text-muted d-block mt-1">Max: 2MB (JPG, PNG, GIF)</small>
                        </form>
                        <?php 
                        $employee = fetchOne("SELECT avatar FROM employees WHERE id = ?", "i", [$userId]);
                        if ($employee['avatar']): 
                        ?>
                        <form method="POST" class="mt-2">
                            <button type="submit" name="remove_avatar" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Remove profile image?')">
                                <i class="bi bi-trash me-1"></i>Remove Image
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Edit Profile -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee['name']); ?>" readonly>
                                    <small class="text-muted">Contact admin to change name</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($employee['email']); ?>" readonly>
                                    <small class="text-muted">Contact admin to change email</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gender</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst($employee['gender'] ?? 'N/A'); ?>" readonly>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" required minlength="6">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                                </div>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="bi bi-key me-2"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
