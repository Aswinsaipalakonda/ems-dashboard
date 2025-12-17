<?php
/**
 * Unified Login Page
 * Employee Management System
 */

$pageTitle = 'Login';
$bodyClass = 'login-page';

require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: " . APP_URL . "/admin/dashboard.php");
    } else {
        $roleSlug = $_SESSION['user_role_slug'] ?? 'employee';
        if ($roleSlug === 'manager' || $roleSlug === 'hr') {
            header("Location: " . APP_URL . "/manager/dashboard.php");
        } elseif ($roleSlug === 'team_lead') {
            header("Location: " . APP_URL . "/teamlead/dashboard.php");
        } else {
            header("Location: " . APP_URL . "/employee/dashboard.php");
        }
    }
    exit;
}

$error = '';
$success = '';

// Check for timeout message
if (isset($_GET['timeout'])) {
    $error = 'Your session has expired. Please login again.';
}

// Check for logout message
if (isset($_GET['logout'])) {
    $success = 'You have been logged out successfully.';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // First check if admin
        $admin = fetchOne("SELECT * FROM admin WHERE email = ?", "s", [$email]);
        
        if ($admin && password_verify($password, $admin['password'])) {
            // Admin login
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['user_name'] = $admin['name'];
            $_SESSION['user_email'] = $admin['email'];
            $_SESSION['role'] = 'admin';
            $_SESSION['avatar'] = $admin['avatar'] ?? '';
            $_SESSION['last_activity'] = time();
            $_SESSION['created'] = time();
            
            // Log the login
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            executeQuery(
                "INSERT INTO login_logs (user_id, user_type, login_time, ip_address, user_agent, status) VALUES (?, ?, NOW(), ?, ?, 'success')",
                "isss",
                [$admin['id'], 'admin', $ip, $userAgent]
            );
            
            logActivity('login', 'Admin logged in successfully');
            
            header("Location: " . APP_URL . "/admin/dashboard.php");
            exit;
        }
        
        // Check employee login
        $user = fetchOne(
            "SELECT e.*, r.name as role_name, r.slug as role_slug, r.permissions 
             FROM employees e 
             LEFT JOIN roles r ON e.role_id = r.id 
             WHERE e.email = ? AND e.status = 'active'", 
            "s", 
            [$email]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            $userRole = $user['role_slug'] ?? 'employee';
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = 'employee';
            $_SESSION['avatar'] = $user['avatar'] ?? '';
            $_SESSION['last_activity'] = time();
            $_SESSION['created'] = time();
            $_SESSION['user_role_id'] = $user['role_id'] ?? 5;
            $_SESSION['user_role_name'] = $user['role_name'] ?? 'Employee';
            $_SESSION['user_role_slug'] = $user['role_slug'] ?? 'employee';
            $_SESSION['user_permissions'] = $user['permissions'] ?? '';
            $_SESSION['employee_id'] = $user['employee_id'] ?? '';
            $_SESSION['domain_id'] = $user['domain_id'] ?? null;
            $_SESSION['team_lead_id'] = $user['team_lead_id'] ?? null;
            
            // Update last login
            executeQuery("UPDATE employees SET last_login = NOW(), last_activity = NOW() WHERE id = ?", "i", [$user['id']]);
            
            // Log the login
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            executeQuery(
                "INSERT INTO login_logs (user_id, user_type, login_time, ip_address, user_agent, status) VALUES (?, ?, NOW(), ?, ?, 'success')",
                "isss",
                [$user['id'], 'employee', $ip, $userAgent]
            );
            
            logActivity('login', 'User logged in successfully');
            
            // Redirect based on role
            if ($userRole === 'manager' || $userRole === 'hr') {
                header("Location: " . APP_URL . "/manager/dashboard.php");
            } elseif ($userRole === 'team_lead') {
                header("Location: " . APP_URL . "/teamlead/dashboard.php");
            } else {
                header("Location: " . APP_URL . "/employee/dashboard.php");
            }
            exit;
        }
        
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/img/clientura-logo.png">
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
        
        .login-container {
            width: 100%;
            max-width: 1000px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        /* Left Side - Branding */
        .login-brand {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-brand::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -100px;
            right: -100px;
        }
        
        .login-brand::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            bottom: -50px;
            left: -50px;
        }
        
        .brand-content {
            position: relative;
            z-index: 1;
            color: #fff;
        }
        
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 40px;
        }
        
        .brand-logo-icon {
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .brand-logo-icon img {
            width: 56px;
            height: 56px;
            object-fit: contain;
        }
        
        .brand-logo-text {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .brand-content h1 {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 16px;
        }
        
        .brand-content p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.7;
            margin-bottom: 40px;
        }
        
        .features {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        
        .feature-item i {
            font-size: 1.25rem;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .feature-item span {
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        /* Right Side - Login Form */
        .login-form-container {
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-form-container h2 {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .login-form-container .subtitle {
            color: #64748b;
            font-size: 1rem;
            margin-bottom: 32px;
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .remember-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
            cursor: pointer;
        }
        
        .checkbox-wrapper label {
            color: #64748b;
            font-size: 0.9rem;
            cursor: pointer;
        }
        
        .forgot-link {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .forgot-link:hover {
            color: #764ba2;
        }
        
        .btn-login {
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
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .footer-text {
            text-align: center;
            margin-top: 32px;
            color: #94a3b8;
            font-size: 0.85rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 420px;
            }
            
            .login-brand {
                display: none;
            }
            
            .login-form-container {
                padding: 40px 30px;
            }
        }
        
        /* Loading animation */
        .btn-login.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .btn-login.loading::after {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Brand Side -->
        <div class="login-brand">
            <div class="brand-content">
                <div class="brand-logo">
                    <div class="brand-logo-icon">
                        <img src="assets/img/clientura-logo.png" alt="<?php echo APP_NAME; ?>" style="filter: drop-shadow(0 2px 8px rgba(0,0,0,0.2));">
                    </div>
                    <span class="brand-logo-text"><?php echo APP_NAME; ?></span>
                </div>
                
                <h1>Welcome to Your Workspace</h1>
                <p>Manage your attendance, tasks, and team collaboration all in one powerful platform.</p>
                
                <div class="features">
                    <div class="feature-item">
                        <i class="bi bi-camera-video"></i>
                        <span>Photo & Location Check-in</span>
                    </div>
                    <div class="feature-item">
                        <i class="bi bi-kanban"></i>
                        <span>Task Management & Tracking</span>
                    </div>
                    <div class="feature-item">
                        <i class="bi bi-graph-up-arrow"></i>
                        <span>Reports & Analytics</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Login Form Side -->
        <div class="login-form-container">
            <h2>Sign In</h2>
            <p class="subtitle">Enter your credentials to access your account</p>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               autocomplete="email">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required
                               autocomplete="current-password">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="remember-row">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="<?php echo APP_URL; ?>/forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Sign In
                </button>
            </form>
            
            <p class="footer-text">
                © <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
            </p>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
        
        // Loading state on form submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.innerHTML = '<span>Signing in...</span>';
        });
    </script>
</body>
</html>
