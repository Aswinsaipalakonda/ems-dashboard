<?php
/**
 * URL Debug Tool
 * Check if APP_URL is correctly detected
 * DELETE THIS FILE AFTER DEBUGGING
 */

require_once __DIR__ . '/config/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Debug - Clientura EMS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #c9a227; border-bottom: 3px solid #c9a227; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        td, th { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f9f9f9; font-weight: bold; width: 30%; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
        .test-links { margin: 20px 0; }
        .test-links a { display: block; margin: 10px 0; padding: 10px; background: #e7f3ff; border-left: 4px solid #2196F3; text-decoration: none; color: #333; }
        .test-links a:hover { background: #d0e8ff; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 URL Configuration Debug</h1>
        
        <div class="warning">
            <strong>⚠️ Security Warning:</strong> Delete this file (<code>url-debug.php</code>) after checking!
        </div>
        
        <h2>Detected APP_URL</h2>
        <table>
            <tr>
                <th>APP_URL</th>
                <td><strong><?php echo APP_URL; ?></strong></td>
            </tr>
            <tr>
                <th>Expected Format</th>
                <td>
                    http(s)://domain.com or<br>
                    http(s)://domain.com/subfolder
                </td>
            </tr>
        </table>
        
        <h2>Server Variables</h2>
        <table>
            <tr>
                <th>HTTP_HOST</th>
                <td><?php echo $_SERVER['HTTP_HOST'] ?? 'Not set'; ?></td>
            </tr>
            <tr>
                <th>HTTPS</th>
                <td><?php echo isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'Yes (SSL Enabled)' : 'No'; ?></td>
            </tr>
            <tr>
                <th>DOCUMENT_ROOT</th>
                <td><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Not set'; ?></td>
            </tr>
            <tr>
                <th>SCRIPT_NAME</th>
                <td><?php echo $_SERVER['SCRIPT_NAME'] ?? 'Not set'; ?></td>
            </tr>
            <tr>
                <th>PHP_SELF</th>
                <td><?php echo $_SERVER['PHP_SELF'] ?? 'Not set'; ?></td>
            </tr>
            <tr>
                <th>Project Root</th>
                <td><?php echo dirname(__DIR__) . '/config'; ?></td>
            </tr>
        </table>
        
        <h2>Asset URL Tests</h2>
        <p>Click these links to verify they load correctly:</p>
        
        <div class="test-links">
            <a href="<?php echo asset('assets/css/style.css'); ?>" target="_blank">
                ✓ Test CSS: <?php echo asset('assets/css/style.css'); ?>
            </a>
            <a href="<?php echo asset('assets/js/main.js'); ?>" target="_blank">
                ✓ Test JS: <?php echo asset('assets/js/main.js'); ?>
            </a>
            <a href="<?php echo asset('assets/img/clientura-logo.png'); ?>" target="_blank">
                ✓ Test Image: <?php echo asset('assets/img/clientura-logo.png'); ?>
            </a>
        </div>
        
        <h2>What to Check</h2>
        <ol>
            <li><strong>APP_URL</strong> should match your actual domain</li>
            <li>Click the test links above - they should load the actual files</li>
            <li>If CSS doesn't load, check if the file exists at: <code>/path/to/ems-dashboard/assets/css/style.css</code></li>
            <li>Verify file permissions: <code>chmod 644 assets/css/style.css</code></li>
        </ol>
        
        <div class="warning">
            <strong>Manual Override:</strong> If auto-detection fails, manually set APP_URL in <code>config/config.php</code>:
            <pre style="background: #f4f4f4; padding: 10px; border-radius: 5px; margin-top: 10px;">define('APP_URL', 'https://ems.clientura.org');</pre>
            Place this BEFORE the auto-detect code.
        </div>
    </div>
</body>
</html>
