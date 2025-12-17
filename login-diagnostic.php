<?php
/**
 * Employee Login Diagnostic Tool
 * Test password hashing and login functionality
 * DELETE THIS FILE AFTER TESTING
 */

require_once __DIR__ . '/config/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Diagnostic - Clientura EMS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #c9a227; border-bottom: 3px solid #c9a227; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; background: #f9f9f9; padding: 10px; border-left: 4px solid #c9a227; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        td, th { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f9f9f9; font-weight: bold; width: 30%; }
        .code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 12px; }
        .test-section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #2196F3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Employee Login Diagnostic</h1>
        
        <div class="info">
            <strong>⚠️ Security Warning:</strong> Delete this file after testing!
        </div>

        <?php
        // Test 1: PHP Password Hashing
        echo '<h2>1. Password Hashing Test</h2>';
        echo '<div class="test-section">';
        
        $testPassword = "password123";
        $hash = password_hash($testPassword, PASSWORD_DEFAULT);
        $verify = password_verify($testPassword, $hash);
        
        echo '<table>';
        echo '<tr><td><strong>Test Password</strong></td><td>' . htmlspecialchars($testPassword) . '</td></tr>';
        echo '<tr><td><strong>Hash Generated</strong></td><td><code>' . substr($hash, 0, 50) . '...</code></td></tr>';
        echo '<tr><td><strong>Hash Length</strong></td><td>' . strlen($hash) . ' (should be 60)</td></tr>';
        echo '<tr><td><strong>Hash Verify</strong></td><td><span class="' . ($verify ? 'success' : 'error') . '">' . ($verify ? '✓ SUCCESS' : '✗ FAILED') . '</span></td></tr>';
        echo '</table>';
        
        if (!$verify) {
            echo '<div class="info" style="background: #f8d7da; border-color: #dc3545;">';
            echo '<strong>⚠️ ERROR:</strong> Password hashing is broken on this server!';
            echo '</div>';
        }
        echo '</div>';

        // Test 2: Database Password Storage
        echo '<h2>2. Database Password Storage Check</h2>';
        echo '<div class="test-section">';
        
        try {
            $employees = fetchAll("SELECT id, email, password FROM employees LIMIT 5", "");
            
            if (empty($employees)) {
                echo '<p><span class="warning">⚠️ No employees found in database</span></p>';
            } else {
                echo '<table>';
                echo '<tr>';
                echo '<th>Email</th>';
                echo '<th>Password Hash Length</th>';
                echo '<th>Hash Format</th>';
                echo '<th>Status</th>';
                echo '</tr>';
                
                foreach ($employees as $emp) {
                    $pwdLength = strlen($emp['password']);
                    $pwdStart = substr($emp['password'], 0, 4);
                    $isHashed = ($pwdLength === 60 && $pwdStart === '$2y$');
                    
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($emp['email']) . '</td>';
                    echo '<td>' . $pwdLength . '</td>';
                    echo '<td><code>' . htmlspecialchars($pwdStart) . '...</code></td>';
                    echo '<td><span class="' . ($isHashed ? 'success' : 'error') . '">' . ($isHashed ? '✓ Hashed' : '✗ NOT Hashed') . '</span></td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                // Check if any password is not hashed
                $unhashed = array_filter($employees, fn($e) => strlen($e['password']) < 60);
                if (!empty($unhashed)) {
                    echo '<div class="info" style="background: #f8d7da; border-color: #dc3545;">';
                    echo '<strong>🚨 CRITICAL:</strong> ' . count($unhashed) . ' password(s) are NOT hashed!<br>';
                    echo 'Employee login will fail. See EMPLOYEE-LOGIN-TROUBLESHOOTING.md for fix.';
                    echo '</div>';
                }
            }
        } catch (Exception $e) {
            echo '<p><span class="error">✗ Database Error: ' . htmlspecialchars($e->getMessage()) . '</span></p>';
        }
        echo '</div>';

        // Test 3: Employee Status
        echo '<h2>3. Active Employee Status Check</h2>';
        echo '<div class="test-section">';
        
        try {
            $statusCounts = fetchAll(
                "SELECT status, COUNT(*) as count FROM employees GROUP BY status",
                ""
            );
            
            echo '<table>';
            echo '<tr><th>Status</th><th>Count</th><th>Can Login?</th></tr>';
            
            $activeCount = 0;
            foreach ($statusCounts as $stat) {
                $canLogin = $stat['status'] === 'active' ? '✓ Yes' : '✗ No';
                $color = $stat['status'] === 'active' ? 'success' : 'warning';
                echo '<tr>';
                echo '<td><strong>' . ucfirst($stat['status']) . '</strong></td>';
                echo '<td>' . $stat['count'] . '</td>';
                echo '<td><span class="' . $color . '">' . $canLogin . '</span></td>';
                echo '</tr>';
                
                if ($stat['status'] === 'active') {
                    $activeCount = $stat['count'];
                }
            }
            echo '</table>';
            
            if ($activeCount === 0) {
                echo '<div class="info" style="background: #f8d7da; border-color: #dc3545;">';
                echo '<strong>⚠️ WARNING:</strong> No active employees! All are set to inactive or terminated.';
                echo '</div>';
            }
        } catch (Exception $e) {
            echo '<p><span class="error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</span></p>';
        }
        echo '</div>';

        // Test 4: Login Simulation
        echo '<h2>4. Login Simulation Test</h2>';
        echo '<div class="test-section">';
        echo '<form method="POST" style="background: #f9f9f9; padding: 15px; border-radius: 5px;">';
        echo '<div style="margin-bottom: 10px;">';
        echo '<label>Employee Email:</label><br>';
        echo '<input type="email" name="test_email" placeholder="employee@example.com" required>';
        echo '</div>';
        echo '<div style="margin-bottom: 10px;">';
        echo '<label>Password:</label><br>';
        echo '<input type="password" name="test_password" placeholder="password" required>';
        echo '</div>';
        echo '<button type="submit" name="test_login" style="background: #c9a227; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">Test Login</button>';
        echo '</form>';
        
        if (isset($_POST['test_login'])) {
            $testEmail = sanitize($_POST['test_email'] ?? '');
            $testPassword = $_POST['test_password'] ?? '';
            
            echo '<br><div class="info">';
            echo '<strong>Test Result:</strong><br>';
            
            $employee = fetchOne(
                "SELECT * FROM employees WHERE email = ?",
                "s",
                [$testEmail]
            );
            
            if (!$employee) {
                echo '<span class="error">✗ Employee not found with email: ' . htmlspecialchars($testEmail) . '</span>';
            } elseif ($employee['status'] !== 'active') {
                echo '<span class="error">✗ Employee status is: ' . htmlspecialchars($employee['status']) . ' (must be "active")</span>';
            } elseif (!password_verify($testPassword, $employee['password'])) {
                echo '<span class="error">✗ Password incorrect for: ' . htmlspecialchars($testEmail) . '</span>';
                echo '<br><br><strong>Debug Info:</strong>';
                echo '<br>Stored Hash Length: ' . strlen($employee['password']);
                echo '<br>Hash Starts With: ' . substr($employee['password'], 0, 4);
            } else {
                echo '<span class="success">✓ LOGIN SUCCESSFUL!</span>';
                echo '<br>Employee: ' . htmlspecialchars($employee['name']);
                echo '<br>Email: ' . htmlspecialchars($employee['email']);
                echo '<br>Status: ' . htmlspecialchars($employee['status']);
            }
            echo '</div>';
        }
        echo '</div>';

        // Test 5: PHP Configuration
        echo '<h2>5. PHP Configuration</h2>';
        echo '<div class="test-section">';
        echo '<table>';
        echo '<tr><td><strong>PHP Version</strong></td><td>' . phpversion() . ' (need 7.4+)</td></tr>';
        echo '<tr><td><strong>Session Status</strong></td><td>' . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not started') . '</td></tr>';
        echo '<tr><td><strong>OpenSSL</strong></td><td>' . (extension_loaded('openssl') ? '✓ Enabled' : '✗ Disabled') . '</td></tr>';
        echo '<tr><td><strong>Hash</strong></td><td>' . (extension_loaded('hash') ? '✓ Enabled' : '✗ Disabled') . '</td></tr>';
        echo '</table>';
        echo '</div>';

        // Recommendations
        echo '<h2>6. What to Check</h2>';
        echo '<div class="test-section">';
        echo '<ol>';
        echo '<li>✓ Password Hashing Test: Should show SUCCESS</li>';
        echo '<li>✓ Database Passwords: Should show "Hashed" status</li>';
        echo '<li>✓ Active Employees: Should have count > 0</li>';
        echo '<li>✓ Login Simulation: Should be successful with correct credentials</li>';
        echo '<li>✓ PHP Version: Should be 7.4 or higher</li>';
        echo '</ol>';
        echo '</div>';

        echo '<div class="info" style="background: #fff3cd; border-color: #ff9800; margin-top: 30px;">';
        echo '<strong>🔒 IMPORTANT:</strong> Delete this <code>login-diagnostic.php</code> file immediately after testing to prevent security risks!';
        echo '</div>';
        ?>
    </div>
</body>
</html>
