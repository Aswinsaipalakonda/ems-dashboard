<?php
/**
 * Email Configuration & Mailer Class
 * Employee Management System
 * 
 * Uses PHPMailer for sending emails
 * Download PHPMailer: https://github.com/PHPMailer/PHPMailer
 */

// Email Configuration - Update these settings for deployment
// IMPORTANT: do NOT commit real passwords to the repository. Prefer environment variables.
define('MAIL_ENABLED', true); // Set to true after configuring SMTP
// Using Hostinger mailbox: noreply@clientura.org
define('MAIL_HOST', 'smtp.hostinger.com');
define('MAIL_PORT', 465);
define('MAIL_USERNAME', 'noreply@clientura.org');
define('MAIL_PASSWORD', 'Workteam@2025'); // Replace with mailbox password (or use env var)
define('MAIL_FROM_EMAIL', 'noreply@clientura.org');
define('MAIL_FROM_NAME', 'Clientura EMS');
define('MAIL_ENCRYPTION', 'ssl'); // Use 'ssl' for port 465

/**
 * Simple Mailer Class
 * Works without PHPMailer using PHP's mail() function
 * For production, install PHPMailer for better reliability
 */
class Mailer {
    
    /**
     * Send email using PHP mail() or PHPMailer if available
     */
    public static function send($to, $subject, $body, $isHtml = true) {
        if (!MAIL_ENABLED) {
            self::logEmail($to, $subject, $body, 'disabled');
            return ['success' => false, 'message' => 'Email is disabled'];
        }
        
        // Check if PHPMailer is available
        $phpmailerPath = __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
        
        if (file_exists($phpmailerPath)) {
            return self::sendWithPHPMailer($to, $subject, $body, $isHtml);
        } else {
            return self::sendWithMail($to, $subject, $body, $isHtml);
        }
    }
    
    /**
     * Send using PHP mail() function
     */
    private static function sendWithMail($to, $subject, $body, $isHtml) {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = $isHtml ? 'Content-type: text/html; charset=UTF-8' : 'Content-type: text/plain; charset=UTF-8';
        $headers[] = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . '>';
        $headers[] = 'Reply-To: ' . MAIL_FROM_EMAIL;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        $success = @mail($to, $subject, $body, implode("\r\n", $headers));
        
        self::logEmail($to, $subject, $body, $success ? 'sent' : 'failed');
        
        return [
            'success' => $success,
            'message' => $success ? 'Email sent successfully' : 'Failed to send email'
        ];
    }
    
    /**
     * Send using PHPMailer (if installed)
     * @suppress PHP1009
     */
    private static function sendWithPHPMailer($to, $subject, $body, $isHtml) {
        require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
        
        // Prefer Composer autoload if available (vendor/autoload.php)
        $composerAutoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($composerAutoload)) {
            require_once $composerAutoload;
        } else {
            // If composer autoload is not present, include PHPMailer files directly (legacy layout)
            require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
            require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
            require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
        }

        // Instantiate PHPMailer (will exist if vendor files were loaded)
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION;
            $mail->Port = MAIL_PORT;
            
            // Recipients
            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            if ($isHtml) {
                $mail->AltBody = strip_tags($body);
            }
            
            $mail->send();
            
            self::logEmail($to, $subject, $body, 'sent');
            
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (\Exception $e) {
            self::logEmail($to, $subject, $body, 'failed: ' . $mail->ErrorInfo);
            return ['success' => false, 'message' => $mail->ErrorInfo];
        }
    }
    
    /**
     * Log email to database
     */
    private static function logEmail($to, $subject, $body, $status) {
        global $conn;
        
        // Create email_logs table if not exists
        $conn->query("CREATE TABLE IF NOT EXISTS email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipient VARCHAR(255),
            subject VARCHAR(255),
            body TEXT,
            status VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $stmt = $conn->prepare("INSERT INTO email_logs (recipient, subject, body, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $to, $subject, $body, $status);
        $stmt->execute();
    }
    
    /**
     * Send attendance approval notification
     */
    public static function sendAttendanceApproval($employeeEmail, $employeeName, $status, $date) {
        $statusText = ucfirst($status);
        $statusColor = $status === 'approved' ? '#28a745' : '#dc3545';
        
        $subject = "Attendance $statusText - " . date('M d, Y', strtotime($date));
        
        $body = self::getEmailTemplate([
            'title' => "Attendance $statusText",
            'greeting' => "Hello $employeeName,",
            'content' => "Your attendance for <strong>" . date('F d, Y', strtotime($date)) . "</strong> has been <span style='color: $statusColor; font-weight: bold;'>$statusText</span>.",
            'action_url' => APP_URL . '/employee/attendance.php',
            'action_text' => 'View Attendance'
        ]);
        
        return self::send($employeeEmail, $subject, $body);
    }
    
    /**
     * Send task assignment notification
     */
    public static function sendTaskAssignment($employeeEmail, $employeeName, $taskTitle, $deadline, $priority) {
        $subject = "New Task Assigned: $taskTitle";
        
        $priorityColors = [
            'high' => '#dc3545',
            'medium' => '#ffc107',
            'low' => '#28a745'
        ];
        $priorityColor = $priorityColors[$priority] ?? '#6c757d';
        
        $body = self::getEmailTemplate([
            'title' => 'New Task Assigned',
            'greeting' => "Hello $employeeName,",
            'content' => "You have been assigned a new task:<br><br>
                <strong>Task:</strong> $taskTitle<br>
                <strong>Priority:</strong> <span style='color: $priorityColor;'>" . ucfirst($priority) . "</span><br>
                <strong>Deadline:</strong> " . date('F d, Y', strtotime($deadline)),
            'action_url' => APP_URL . '/employee/tasks.php',
            'action_text' => 'View Task'
        ]);
        
        return self::send($employeeEmail, $subject, $body);
    }
    
    /**
     * Get email HTML template
     */
    private static function getEmailTemplate($data) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <!-- Header -->
                <tr>
                    <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;">
                        <h1 style="color: #ffffff; margin: 0; font-size: 24px;">' . APP_NAME . '</h1>
                    </td>
                </tr>
                
                <!-- Content -->
                <tr>
                    <td style="padding: 40px 30px;">
                        <h2 style="color: #333; margin: 0 0 20px 0; font-size: 22px;">' . $data['title'] . '</h2>
                        <p style="color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;">' . $data['greeting'] . '</p>
                        <p style="color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">' . $data['content'] . '</p>
                        
                        <table cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                            <tr>
                                <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 5px;">
                                    <a href="' . $data['action_url'] . '" style="display: inline-block; padding: 15px 30px; color: #ffffff; text-decoration: none; font-weight: bold; font-size: 16px;">' . $data['action_text'] . '</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                
                <!-- Footer -->
                <tr>
                    <td style="background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #eee;">
                        <p style="color: #999; font-size: 12px; margin: 0;">
                            This is an automated message from ' . APP_NAME . '.<br>
                            Please do not reply to this email.
                        </p>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }
}
