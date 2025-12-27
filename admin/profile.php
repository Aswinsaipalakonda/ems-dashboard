<?php
/**
 * Admin Profile
 * Employee Management System
 */

$pageTitle = 'My Profile';
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$message = '';
$error = '';

// Get admin data
$admin = fetchOne("SELECT * FROM admin WHERE id = ?", "i", [$_SESSION['user_id']]);

// Handle avatar removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_avatar'])) {
    $uploadDir = __DIR__ . '/../uploads/avatars/';
    if ($admin['avatar'] && file_exists($uploadDir . $admin['avatar'])) {
        unlink($uploadDir . $admin['avatar']);
    }
    
    $result = executeQuery(
        "UPDATE admin SET avatar = NULL, updated_at = NOW() WHERE id = ?",
        "i",
        [$_SESSION['user_id']]
    );
    
    if ($result) {
        $_SESSION['avatar'] = null;
        $message = 'Profile image removed successfully';
        $admin = fetchOne("SELECT * FROM admin WHERE id = ?", "i", [$_SESSION['user_id']]);
    } else {
        $error = 'Failed to remove profile image';
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    
    // Check if email already exists
    $existing = fetchOne("SELECT id FROM admin WHERE email = ? AND id != ?", "si", [$email, $_SESSION['user_id']]);
    
    if ($existing) {
        $error = 'Email already in use';
    } else {
        // Handle avatar upload
        $avatar = $admin['avatar'];
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $extension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($extension, $allowed)) {
                $filename = 'admin_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $filename)) {
                    // Delete old avatar
                    if ($avatar && file_exists($uploadDir . $avatar)) {
                        unlink($uploadDir . $avatar);
                    }
                    $avatar = $filename;
                }
            }
        }
        
        $result = executeQuery(
            "UPDATE admin SET name = ?, email = ?, avatar = ?, updated_at = NOW() WHERE id = ?",
            "sssi",
            [$name, $email, $avatar, $_SESSION['user_id']]
        );
        
        if ($result) {
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            $_SESSION['avatar'] = $avatar;
            $message = 'Profile updated successfully';
            $admin = fetchOne("SELECT * FROM admin WHERE id = ?", "i", [$_SESSION['user_id']]);
        } else {
            $error = 'Failed to update profile';
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (!password_verify($currentPassword, $admin['password'])) {
        $error = 'Current password is incorrect';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $result = executeQuery(
            "UPDATE admin SET password = ?, updated_at = NOW() WHERE id = ?",
            "si",
            [$hashedPassword, $_SESSION['user_id']]
        );
        
        if ($result) {
            $message = 'Password changed successfully';
            logActivity('password_change', 'Admin password changed');
        } else {
            $error = 'Failed to change password';
        }
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
            <h1 class="page-title">My Profile</h1>
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
        
        <div class="row">
            <!-- Profile Card -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <img src="<?php echo getAvatar($admin['avatar']); ?>" class="rounded-circle mb-3" alt="Avatar" 
                             style="width: 120px; height: 120px; object-fit: cover; border: 4px solid var(--primary-color);">
                        <h4 class="mb-1"><?php echo $admin['name']; ?></h4>
                        <p class="text-muted mb-3">Administrator</p>
                        <span class="badge bg-primary px-3 py-2">
                            <i class="bi bi-shield-check me-1"></i>System Admin
                        </span>
                        <hr class="my-4">
                        <div class="text-start">
                            <p class="mb-2">
                                <i class="bi bi-envelope text-primary me-2"></i>
                                <?php echo $admin['email']; ?>
                            </p>
                            <p class="mb-2">
                                <i class="bi bi-calendar text-primary me-2"></i>
                                Member since <?php echo date('M Y', strtotime($admin['created_at'])); ?>
                            </p>
                            <p class="mb-0">
                                <i class="bi bi-clock text-primary me-2"></i>
                                Last updated <?php echo $admin['updated_at'] ? formatDate($admin['updated_at']) : 'Never'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit Forms -->
            <div class="col-lg-8">
                <!-- Profile Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person-gear me-2"></i>Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" value="<?php echo $admin['name']; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" value="<?php echo $admin['email']; ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Profile Photo</label>
                                <input type="file" class="form-control" name="avatar" accept="image/*">
                                <small class="text-muted">Accepted formats: JPG, PNG, GIF</small>
                                <?php if ($admin['avatar']): ?>
                                <div class="mt-2">
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="remove_avatar" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove profile image?')">
                                            <i class="bi bi-trash me-1"></i>Remove Current Image
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Password Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="new_password" required minlength="6">
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="bi bi-key me-1"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
