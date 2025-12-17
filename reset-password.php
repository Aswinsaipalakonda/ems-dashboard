<?php
/**
 * Reset Password Page
 * Employee Management System
 */

require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: " . APP_URL . "/employee/dashboard.php");
    exit;
}

$error = '';
$success = '';
$validToken = false;
$tokenData = null;

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'Invalid or missing reset token. Please request a new password reset link.';
} else {
    // Validate token
    $tokenData = fetchOne(
        "SELECT * FROM password_reset_tokens WHERE token = ? AND used = 0 AND expires_at > NOW()",
        "s",
        [$token]
    );
    
    if (!$tokenData) {
        $error = 'This password reset link has expired or is invalid. Please request a new one.';
    } else {
        $validToken = true;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirmPassword)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Hash new password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $email = $tokenData['email'];
        
        // Check if admin or employee
        $admin = fetchOne("SELECT id FROM admin WHERE email = ?", "s", [$email]);
        $employee = fetchOne("SELECT id FROM employees WHERE email = ?", "s", [$email]);
        
        if ($admin) {
            executeQuery("UPDATE admin SET password = ? WHERE email = ?", "ss", [$hashedPassword, $email]);
        } elseif ($employee) {
            executeQuery("UPDATE employees SET password = ? WHERE email = ?", "ss", [$hashedPassword, $email]);
        }
        
        // Mark token as used
        executeQuery("UPDATE password_reset_tokens SET used = 1 WHERE token = ?", "s", [$token]);
        
        // Log activity
        logActivity('password_reset', "Password reset completed for: $email");
        
        $success = 'Your password has been reset successfully! You can now sign in with your new password.';
        $validToken = false; // Hide the form
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-container {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border-radius: 24px;
            padding: 50px 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .icon-circle {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        
        .icon-circle i {
            font-size: 32px;
            color: #fff;
        }
        
        .icon-circle.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .icon-circle.error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        
        h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
            text-align: center;
            margin-bottom: 8px;
        }
        
        .subtitle {
            color: #64748b;
            font-size: 0.95rem;
            text-align: center;
            margin-bottom: 32px;
            line-height: 1.5;
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .alert i {
            margin-top: 2px;
        }
        
        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group i.input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1.1rem;
        }
        
        .input-group input {
            width: 100%;
            padding: 14px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            color: #1e293b;
            transition: all 0.2s ease;
            background: #f9fafb;
        }
        
        .input-group input::placeholder {
            color: #9ca3af;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 4px;
            transition: color 0.2s ease;
        }
        
        .toggle-password:hover {
            color: #667eea;
        }
        
        .password-requirements {
            margin-top: 8px;
            font-size: 0.8rem;
            color: #64748b;
        }
        
        .password-requirements li {
            margin-bottom: 4px;
        }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: #fff;
            border: 2px solid #667eea;
            border-radius: 12px;
            color: #667eea;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            margin-top: 16px;
        }
        
        .btn-login:hover {
            background: #667eea;
            color: #fff;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 24px;
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .footer-text a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .footer-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <?php if ($success): ?>
        <div class="icon-circle success">
            <i class="bi bi-check-lg"></i>
        </div>
        
        <h2>Password Reset!</h2>
        
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i>
            <span><?php echo $success; ?></span>
        </div>
        
        <a href="<?php echo APP_URL; ?>/login.php" class="btn-submit">
            <i class="bi bi-box-arrow-in-right"></i>
            Sign In Now
        </a>
        
        <?php elseif ($error && !$validToken): ?>
        <div class="icon-circle error">
            <i class="bi bi-x-lg"></i>
        </div>
        
        <h2>Link Expired</h2>
        
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        
        <a href="<?php echo APP_URL; ?>/forgot-password.php" class="btn-submit">
            <i class="bi bi-arrow-repeat"></i>
            Request New Link
        </a>
        
        <p class="footer-text">
            Remember your password? <a href="<?php echo APP_URL; ?>/login.php">Sign in</a>
        </p>
        
        <?php elseif ($validToken): ?>
        <div class="icon-circle">
            <i class="bi bi-shield-lock"></i>
        </div>
        
        <h2>Create New Password</h2>
        <p class="subtitle">Your new password must be different from your previous passwords.</p>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="password">New Password</label>
                <div class="input-group">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" id="password" name="password" placeholder="Enter new password" required
                           minlength="6" autocomplete="new-password">
                    <button type="button" class="toggle-password" onclick="togglePassword('password', 'toggleIcon1')">
                        <i class="bi bi-eye" id="toggleIcon1"></i>
                    </button>
                </div>
                <ul class="password-requirements">
                    <li>At least 6 characters long</li>
                </ul>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="input-group">
                    <i class="bi bi-lock-fill input-icon"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required
                           minlength="6" autocomplete="new-password">
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                        <i class="bi bi-eye" id="toggleIcon2"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-submit">
                <i class="bi bi-check-lg"></i>
                Reset Password
            </button>
        </form>
        
        <p class="footer-text">
            Remember your password? <a href="<?php echo APP_URL; ?>/login.php">Sign in</a>
        </p>
        <?php endif; ?>
    </div>
    
    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>
