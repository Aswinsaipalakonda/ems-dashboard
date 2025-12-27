<?php
/**
 * Admin Login Page
 * Employee Management System
 */

$pageTitle = 'Admin Login';
$bodyClass = 'login-page';

require_once __DIR__ . '/../config/config.php';

// Redirect if already logged in as admin
if (isLoggedIn() && isAdmin()) {
    header("Location: " . url("admin/dashboard"));
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
        // Admin login
        $user = fetchOne("SELECT * FROM admin WHERE email = ? AND status = 'active'", "s", [$email]);
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = 'admin';
            $_SESSION['avatar'] = $user['avatar'] ?? '';
            $_SESSION['last_activity'] = time();
            $_SESSION['created'] = time();
            
            // Update last login
            executeQuery("UPDATE admin SET last_login = NOW() WHERE id = ?", "i", [$user['id']]);
            
            // Log the login
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            executeQuery(
                "INSERT INTO login_logs (user_id, user_type, login_time, ip_address, user_agent, status) VALUES (?, ?, NOW(), ?, ?, 'success')",
                "isss",
                [$user['id'], 'admin', $ip, $userAgent]
            );
            
            // Log activity
            logActivity('login', 'Admin logged in successfully');
            
            // Redirect to admin dashboard
            header("Location: " . url("admin/dashboard"));
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
.admin-login-wrapper {
    min-height: 100vh;
    display: flex;
    background: linear-gradient(135deg, #1a1c2e 0%, #16182a 50%, #0d0e17 100%);
    position: relative;
    overflow: hidden;
}
.admin-login-wrapper::before {
    content: '';
    position: absolute;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
    top: -200px;
    right: -200px;
    border-radius: 50%;
}
.admin-login-wrapper::after {
    content: '';
    position: absolute;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(236, 72, 153, 0.1) 0%, transparent 70%);
    bottom: -100px;
    left: -100px;
    border-radius: 50%;
}
.login-container {
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px;
    position: relative;
    z-index: 1;
}
.login-card {
    width: 100%;
    max-width: 440px;
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 24px;
    padding: 50px 40px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
.admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 30px;
}
.admin-badge i {
    font-size: 1rem;
}
.login-card h1 {
    color: #fff;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 10px;
}
.login-card .subtitle {
    color: rgba(255, 255, 255, 0.5);
    font-size: 1rem;
    margin-bottom: 35px;
}
.form-group {
    margin-bottom: 24px;
}
.form-group label {
    display: block;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    font-weight: 500;
    margin-bottom: 10px;
}
.input-wrapper {
    position: relative;
}
.input-wrapper i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.4);
    font-size: 1.1rem;
}
.input-wrapper input {
    width: 100%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 16px 16px 16px 50px;
    font-size: 1rem;
    color: #fff;
    transition: all 0.3s ease;
}
.input-wrapper input::placeholder {
    color: rgba(255, 255, 255, 0.3);
}
.input-wrapper input:focus {
    outline: none;
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.1);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}
.input-wrapper .toggle-password {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    padding: 5px;
}
.input-wrapper .toggle-password:hover {
    color: rgba(255, 255, 255, 0.7);
}
.btn-admin-login {
    width: 100%;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border: none;
    border-radius: 12px;
    padding: 16px;
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 10px;
}
.btn-admin-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 35px -5px rgba(99, 102, 241, 0.5);
}
.btn-admin-login:active {
    transform: translateY(0);
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
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}
.alert-success {
    background: rgba(34, 197, 94, 0.15);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}
.back-link {
    display: flex;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, 0.5);
    text-decoration: none;
    font-size: 0.9rem;
    margin-top: 30px;
    justify-content: center;
    transition: color 0.3s ease;
}
.back-link:hover {
    color: #fff;
}
.security-note {
    margin-top: 30px;
    padding-top: 25px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    text-align: center;
}
.security-note p {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.security-note i {
    color: #22c55e;
}
</style>

<div class="admin-login-wrapper">
    <div class="login-container">
        <div class="login-card">
            <div class="admin-badge">
                <i class="bi bi-shield-lock-fill"></i>
                Admin Portal
            </div>
            
            <h1>Welcome Back</h1>
            <p class="subtitle">Sign in to access the admin dashboard</p>
            
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
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <i class="bi bi-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="bi bi-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-admin-login">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Sign In to Dashboard
                </button>
            </form>
            
            <a href="<?php echo url('login'); ?>" class="back-link">
                <i class="bi bi-arrow-left"></i>
                Back to Employee Login
            </a>
            
            <div class="security-note">
                <p><i class="bi bi-shield-check"></i> Secured with enterprise-grade encryption</p>
            </div>
        </div>
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
