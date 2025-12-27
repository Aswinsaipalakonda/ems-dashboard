<?php
/**
 * Forgot Password Page - Manual Token Reset
 * Employee Management System
 * 
 * Generates a secure reset token and displays it to admin/authorized personnel
 * No email is sent - token must be shared manually via secure channel
 */

require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: " . url("employee/dashboard"));
    exit;
}

$error = '';
$success = '';
$resetLink = '';
$tokenExpiry = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email exists (check both admin and employees)
        $admin = fetchOne("SELECT id, name, email FROM admin WHERE email = ?", "s", [$email]);
        $employee = fetchOne("SELECT id, name, email FROM employees WHERE email = ? AND status = 'active'", "s", [$email]);
        
        $user = $admin ?: $employee;
        
        if ($user) {
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            
            // Delete any existing tokens for this email
            executeQuery("DELETE FROM password_reset_tokens WHERE email = ?", "s", [$email]);
            
            // Save new token
            executeQuery(
                "INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)",
                "sss",
                [$email, $token, $expiresAt]
            );
            
            // Create reset link
            $resetLink = url('reset-password') . "?token=" . $token;
            $tokenExpiry = date('g:i A, M d, Y', strtotime($expiresAt));
            
            // Show success with the link
            $success = 'Password reset link generated successfully for: <strong>' . htmlspecialchars($email) . '</strong>';
            
            // Log the activity
            logActivity('password_reset_request', "Password reset requested for: $email");
        } else {
            // Don't reveal if email exists or not (security best practice)
            $error = 'No active account found with that email address.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
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
        
        .forgot-container {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border-radius: 24px;
            padding: 50px 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 24px;
            transition: color 0.2s ease;
        }
        
        .back-link:hover {
            color: #667eea;
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
            padding: 14px 16px 14px 48px;
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
    <div class="forgot-container">
        <a href="<?php echo url('login'); ?>" class="back-link">
            <i class="bi bi-arrow-left"></i>
            Back to Login
        </a>
        
        <div class="icon-circle">
            <i class="bi bi-key"></i>
        </div>
        
        <h2>Forgot Password?</h2>
        <p class="subtitle">Enter the email address associated with the account to generate a secure password reset link.</p>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i>
            <span><?php echo $success; ?></span>
        </div>
        
        <?php if ($resetLink): ?>
        <div style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-top: 20px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                <i class="bi bi-link-45deg" style="font-size: 1.2rem; color: #667eea;"></i>
                <strong style="color: #1e293b; font-size: 0.95rem;">Reset Link (Valid for 30 minutes)</strong>
            </div>
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; word-break: break-all; font-size: 0.85rem; color: #475569; margin-bottom: 12px;">
                <?php echo htmlspecialchars($resetLink); ?>
            </div>
            <div style="display: flex; gap: 8px;">
                <button onclick="copyToClipboard('<?php echo addslashes($resetLink); ?>')" style="flex: 1; padding: 10px 16px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <i class="bi bi-clipboard"></i>
                    <span>Copy Link</span>
                </button>
                <a href="<?php echo $resetLink; ?>" target="_blank" style="flex: 1; padding: 10px 16px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none;">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <span>Open Link</span>
                </a>
            </div>
            <p style="color: #64748b; font-size: 0.8rem; margin-top: 12px; margin-bottom: 0;">
                <i class="bi bi-clock"></i> Expires at: <strong><?php echo $tokenExpiry; ?></strong>
            </p>
            <p style="color: #dc2626; font-size: 0.8rem; margin-top: 8px; margin-bottom: 0;">
                <i class="bi bi-shield-exclamation"></i> Share this link securely (phone, encrypted chat, or in-person only).
            </p>
        </div>
        
        <button onclick="location.reload()" style="width: 100%; margin-top: 16px; padding: 12px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; border-radius: 10px; cursor: pointer; font-size: 0.9rem; font-weight: 500;">
            <i class="bi bi-arrow-clockwise"></i> Generate Another Link
        </button>
        <?php endif; ?>
        <?php else: ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-group">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           autocomplete="email">
                </div>
            </div>
            
            <button type="submit" class="btn-submit">
                <i class="bi bi-send"></i>
                Send Reset Link
            </button>
        </form>
        <?php endif; ?>
        
        <p class="footer-text">
            Remember your password? <a href="<?php echo url('login'); ?>">Sign in</a>
        </p>
    </div>
    
    <script>
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Reset link copied to clipboard!');
            }).catch(function(err) {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }
    
    function fallbackCopy(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            alert('Reset link copied to clipboard!');
        } catch (err) {
            alert('Failed to copy. Please copy manually.');
        }
        document.body.removeChild(textArea);
    }
    </script>
</body>
</html>
