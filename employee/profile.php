<?php
/**
 * Employee Profile
 * Employee Management System
 */

$pageTitle = 'My Profile';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (isAdmin()) {
    header("Location: " . APP_URL . "/admin/profile.php");
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Get employee data
$employee = fetchOne(
    "SELECT e.*, d.name as domain_name 
     FROM employees e 
     LEFT JOIN domains d ON e.domain_id = d.id 
     WHERE e.id = ?", 
    "i", 
    [$userId]
);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        
        if (empty($name)) {
            $message = 'Name is required!';
            $messageType = 'danger';
        } else {
            // Handle avatar upload
            $avatar = $employee['avatar'];
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxSize = 2 * 1024 * 1024; // 2MB
                $fileType = $_FILES['avatar']['type'];
                
                if (!in_array($fileType, $allowedTypes)) {
                    $message = 'Invalid file type. Only JPG, PNG, GIF allowed.';
                    $messageType = 'danger';
                } elseif ($_FILES['avatar']['size'] > $maxSize) {
                    $message = 'File too large. Maximum size is 2MB.';
                    $messageType = 'danger';
                } elseif (in_array($fileType, $allowedTypes)) {
                    $uploadDir = __DIR__ . '/../uploads/avatars/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    
                    $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                    $newFileName = 'emp_' . $userId . '_' . time() . '.' . $extension;
                    
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $newFileName)) {
                        // Delete old avatar
                        if ($avatar && file_exists($uploadDir . $avatar)) {
                            unlink($uploadDir . $avatar);
                        }
                        $avatar = $newFileName;
                    }
                }
            }
            
            $sql = "UPDATE employees SET name = ?, phone = ?, address = ?, avatar = ? WHERE id = ?";
            executeQuery($sql, "ssssi", [$name, $phone, $address, $avatar, $userId]);
            
            $_SESSION['user_name'] = $name;
            $_SESSION['avatar'] = $avatar;
            
            $message = 'Profile updated successfully!';
            $messageType = 'success';
            
            // Refresh employee data
            $employee = fetchOne("SELECT * FROM employees WHERE id = ?", "i", [$userId]);
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $message = 'All password fields are required!';
            $messageType = 'danger';
        } elseif (!password_verify($currentPassword, $employee['password'])) {
            $message = 'Current password is incorrect!';
            $messageType = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'New passwords do not match!';
            $messageType = 'danger';
        } elseif (strlen($newPassword) < 6) {
            $message = 'Password must be at least 6 characters!';
            $messageType = 'danger';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            executeQuery("UPDATE employees SET password = ? WHERE id = ?", "si", [$hashedPassword, $userId]);
            
            $message = 'Password changed successfully!';
            $messageType = 'success';
        }
    } elseif ($action === 'remove_avatar') {
        $uploadDir = __DIR__ . '/../uploads/avatars/';
        if ($employee['avatar'] && file_exists($uploadDir . $employee['avatar'])) {
            unlink($uploadDir . $employee['avatar']);
        }
        
        executeQuery("UPDATE employees SET avatar = NULL WHERE id = ?", "i", [$userId]);
        $_SESSION['avatar'] = null;
        
        $message = 'Profile image removed successfully!';
        $messageType = 'success';
        
        $employee = fetchOne("SELECT * FROM employees WHERE id = ?", "i", [$userId]);
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/employee-sidebar.php';
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
                    <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <div class="content-wrapper">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'x-circle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Profile Card -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-body text-center py-4">
                        <img src="<?php echo getAvatar($employee['avatar']); ?>" class="avatar avatar-lg mb-3" alt="">
                        <h4 class="mb-1"><?php echo $employee['name']; ?></h4>
                        <p class="text-muted mb-2"><?php echo $employee['designation'] ?? 'Employee'; ?></p>
                        <span class="badge bg-light text-dark"><?php echo $employee['employee_id']; ?></span>
                        <hr>
                        <div class="text-start">
                            <p class="mb-2"><i class="bi bi-envelope me-2 text-muted"></i><?php echo $employee['email']; ?></p>
                            <p class="mb-2"><i class="bi bi-phone me-2 text-muted"></i><?php echo $employee['phone'] ?? 'Not provided'; ?></p>
                            <p class="mb-2"><i class="bi bi-building me-2 text-muted"></i><?php echo $employee['domain_name'] ?? 'Not assigned'; ?></p>
                            <p class="mb-0"><i class="bi bi-calendar me-2 text-muted"></i>Joined: <?php echo $employee['date_of_joining'] ? formatDate($employee['date_of_joining']) : 'N/A'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit Forms -->
            <div class="col-lg-8">
                <!-- Update Profile -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person me-2"></i>Update Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" required value="<?php echo $employee['name']; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo $employee['email']; ?>" disabled>
                                    <small class="text-muted">Contact admin to change email</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" value="<?php echo $employee['phone'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Profile Photo</label>
                                    <input type="file" class="form-control" name="avatar" accept="image/*">
                                    <small class="text-muted d-block mt-1">Max size: 2MB (JPG, PNG, GIF)</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <?php if ($employee['avatar']): ?>
                                    <label class="form-label">&nbsp;</label>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="remove_avatar">
                                        <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Remove profile image?')">
                                            <i class="bi bi-trash me-1"></i>Remove Image
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="2"><?php echo $employee['address'] ?? ''; ?></textarea>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Update Profile
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-lock me-2"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" required minlength="6">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
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
