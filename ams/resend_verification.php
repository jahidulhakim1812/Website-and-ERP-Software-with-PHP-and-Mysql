<?php
// resend_verification.php
session_start();

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database configuration
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';

// Email configuration
$COMPANY_NAME = "ALI HAIR WIGS";
$ADMIN_EMAIL = "admin@alihairwigs.com";

// SMTP Configuration
$SMTP_HOST = 'smtp.gmail.com';
$SMTP_PORT = 587;
$SMTP_USERNAME = 'rapidtechnologyservicesbd@gmail.com';
$SMTP_PASSWORD = 'opzh eeny usnl xlhh ';
$SMTP_SECURE = 'tls';
$SMTP_AUTH = true;

header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    if (!isset($_SESSION['password_reset_user_id']) || !isset($_SESSION['password_reset_email'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
        exit;
    }
    
    $user_id = $_SESSION['password_reset_user_id'];
    $email = $_SESSION['password_reset_email'];
    $username = $_SESSION['password_reset_username'];
    
    // Generate new 6-digit verification code
    $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Store new verification code in database
    $stmt = $pdo->prepare('INSERT INTO password_reset_codes (user_id, code, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$user_id, $verification_code, $expires_at]);
    
    // Send email with PHPMailer
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $SMTP_HOST;
        $mail->Port = $SMTP_PORT;
        $mail->SMTPAuth = $SMTP_AUTH;
        $mail->Username = $SMTP_USERNAME;
        $mail->Password = $SMTP_PASSWORD;
        $mail->SMTPSecure = $SMTP_SECURE;
        
        // Recipients
        $mail->setFrom($SMTP_USERNAME, $COMPANY_NAME);
        $mail->addAddress($email, $username);
        $mail->addReplyTo($ADMIN_EMAIL, $COMPANY_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "New Verification Code - $COMPANY_NAME";
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .code-box { background: #fff; border: 2px dashed #667eea; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; color: #667eea; margin: 20px 0; border-radius: 8px; letter-spacing: 5px; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>$COMPANY_NAME</h2>
                    <h3>New Verification Code</h3>
                </div>
                <div class='content'>
                    <p>Hello $username,</p>
                    <p>You requested a new verification code. Here is your new 6-digit code:</p>
                    
                    <div class='code-box'>$verification_code</div>
                    
                    <p>This code will expire in 15 minutes.</p>
                    <p>If you didn't request this code, please ignore this email.</p>
                    
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                        <p>© " . date('Y') . " $COMPANY_NAME. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->send();
        
        echo json_encode([
            'success' => true,
            'message' => 'New verification code sent successfully!'
        ]);
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email. Please try again.'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error. Please try again.'
    ]);
}