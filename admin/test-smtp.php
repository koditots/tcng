<?php
// admin/test-smtp.php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_smtp'])) {
    header('Content-Type: application/json');
    
    try {
        // Get SMTP settings from POST data
        $smtp_host = $_POST['smtp_host'] ?? '';
        $smtp_port = intval($_POST['smtp_port'] ?? 587);
        $smtp_username = $_POST['smtp_username'] ?? '';
        $smtp_password = $_POST['smtp_password'] ?? '';
        $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';
        $smtp_from_email = $_POST['smtp_from_email'] ?? '';
        $smtp_from_name = $_POST['smtp_from_name'] ?? '';
        $admin_email = $_POST['admin_email'] ?? '';
        
        // Validate required fields
        if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password) || empty($admin_email)) {
            throw new Exception('Please fill all required SMTP fields');
        }
        
        // Create PHPMailer instance
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        $mail->Port = $smtp_port;
        
        // Encryption
        if ($smtp_encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($smtp_encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }
        
        // Email content
        $mail->setFrom($smtp_from_email, $smtp_from_name);
        $mail->addAddress($admin_email);
        $mail->Subject = 'SMTP Test from ' . getSiteSetting($pdo, 'site_name');
        $mail->Body = "This is a test email to verify your SMTP settings.\n\nIf you received this email, your SMTP configuration is working correctly.\n\nSite: " . getSiteSetting($pdo, 'site_name') . "\nTime: " . date('Y-m-d H:i:s');
        
        // Send email
        if ($mail->send()) {
            echo json_encode(['success' => true, 'message' => 'Test email sent successfully']);
        } else {
            throw new Exception('Failed to send test email: ' . $mail->ErrorInfo);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}