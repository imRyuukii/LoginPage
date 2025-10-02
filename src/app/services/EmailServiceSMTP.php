<?php
// Enhanced EmailService with SMTP support for real emails
// This version can send real emails via Gmail or other SMTP servers

// Load Composer autoloader
require_once __DIR__ . '/../../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailServiceSMTP {
    private $fromEmail;
    private $fromName;
    private $baseUrl;
    private $smtpEnabled;
    private $smtpConfig;
    
    public function __construct() {
        $this->fromEmail = 'noreply@yoursite.com'; // Change this to your email
        $this->fromName = 'LoginPage System';
        $this->baseUrl = $this->getBaseUrl();
        
        // SMTP Configuration - Set this up for real emails
        $this->smtpEnabled = false; // Set to true to enable real emails
        $this->smtpConfig = [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'your-gmail@gmail.com', // Your Gmail address
            'password' => 'your-app-password',    // Gmail App Password (not regular password)
            'encryption' => PHPMailer::ENCRYPTION_STARTTLS
        ];
    }
    
    /**
     * Send email verification
     */
    public function sendVerificationEmail($userEmail, $userName, $verificationToken) {
        $subject = 'Verify Your Email Address - LoginPage';
        $verificationUrl = 'http://localhost/LoginPage/src/app/controllers/email-verification.php?token=' . urlencode($verificationToken);
        
        // Load email template
        $htmlBody = $this->getVerificationEmailTemplate($userName, $verificationUrl);
        $textBody = $this->getVerificationEmailText($userName, $verificationUrl);
        
        if ($this->smtpEnabled) {
            return $this->sendEmailSMTP($userEmail, $subject, $htmlBody, $textBody);
        } else {
            // Fallback to regular mail() function (for MailHog/local testing)
            return $this->sendEmailLocal($userEmail, $subject, $htmlBody, $textBody);
        }
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($userEmail, $userName, $resetToken) {
        $subject = 'Reset Your Password - LoginPage';
        $resetUrl = 'http://localhost/LoginPage/src/app/controllers/reset-password.php?token=' . urlencode($resetToken);
        
        // Load email template
        $htmlBody = $this->getPasswordResetEmailTemplate($userName, $resetUrl);
        $textBody = $this->getPasswordResetEmailText($userName, $resetUrl);
        
        if ($this->smtpEnabled) {
            return $this->sendEmailSMTP($userEmail, $subject, $htmlBody, $textBody);
        } else {
            // Fallback to regular mail() function (for MailHog/local testing)
            return $this->sendEmailLocal($userEmail, $subject, $htmlBody, $textBody);
        }
    }
    
    /**
     * Send email using PHPMailer SMTP (for real emails)
     */
    private function sendEmailSMTP($to, $subject, $htmlBody, $textBody = null) {
        try {
            // Check if PHPMailer is available
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                error_log('PHPMailer not found. Install it with: composer require phpmailer/phpmailer');
                return false;
            }
            
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtpConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpConfig['username'];
            $mail->Password = $this->smtpConfig['password'];
            $mail->SMTPSecure = $this->smtpConfig['encryption'];
            $mail->Port = $this->smtpConfig['port'];
            
            // Recipients
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($this->fromEmail, $this->fromName);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            if ($textBody) {
                $mail->AltBody = $textBody;
            }
            
            $mail->send();
            error_log("Real email sent successfully to: $to");
            return true;
            
        } catch (Exception $e) {
            error_log("SMTP email failed: {$mail->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Send email using local mail() function (for MailHog/testing)
     */
    private function sendEmailLocal($to, $subject, $htmlBody, $textBody = null) {
        // Create boundary for multipart email
        $boundary = uniqid('boundary_');
        
        // Headers
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromEmail . '>';
        $headers[] = 'Reply-To: ' . $this->fromEmail;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        // Body
        $body = [];
        
        // Text version
        if ($textBody) {
            $body[] = '--' . $boundary;
            $body[] = 'Content-Type: text/plain; charset=UTF-8';
            $body[] = 'Content-Transfer-Encoding: 8bit';
            $body[] = '';
            $body[] = $textBody;
            $body[] = '';
        }
        
        // HTML version
        $body[] = '--' . $boundary;
        $body[] = 'Content-Type: text/html; charset=UTF-8';
        $body[] = 'Content-Transfer-Encoding: 8bit';
        $body[] = '';
        $body[] = $htmlBody;
        $body[] = '';
        $body[] = '--' . $boundary . '--';
        
        $message = implode("\r\n", $body);
        $headersString = implode("\r\n", $headers);
        
        try {
            $result = mail($to, $subject, $message, $headersString);
            error_log("Local email sent to: $to, Result: " . ($result ? 'success' : 'failed'));
            return $result;
        } catch (Exception $e) {
            error_log("Local email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get HTML email template
     */
    private function getVerificationEmailTemplate($userName, $verificationUrl) {
        return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; padding: 30px 20px; text-align: center; }
        .content { padding: 30px 20px; }
        .button { display: inline-block; background: #6366f1; color: white; text-decoration: none; padding: 12px 24px; border-radius: 6px; margin: 20px 0; font-weight: bold; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 14px; }
        .code { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 8px 12px; font-family: monospace; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Email Verification</h1>
        </div>
        <div class="content">
            <h2>Hello, ' . htmlspecialchars($userName) . '!</h2>
            <p>Thank you for registering with LoginPage. To complete your registration and secure your account, please verify your email address.</p>
            <p><strong>Click the button below to verify your email:</strong></p>
            <p style="text-align: center;">
                <a href="' . htmlspecialchars($verificationUrl) . '" class="button">Verify Email Address</a>
            </p>
            <p>If the button doesn\'t work, you can copy and paste this link into your browser:</p>
            <div class="code">' . htmlspecialchars($verificationUrl) . '</div>
            <p><strong>Important:</strong></p>
            <ul>
                <li>This verification link will expire in 24 hours</li>
                <li>You cannot log in until your email is verified</li>
                <li>If you didn\'t create this account, please ignore this email</li>
            </ul>
        </div>
        <div class="footer">
            <p>This is an automated email from LoginPage System. Please do not reply to this email.</p>
            <p>If you need help, please contact support.</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Get plain text email version
     */
    private function getVerificationEmailText($userName, $verificationUrl) {
        return "Hello, $userName!

Thank you for registering with LoginPage. To complete your registration and secure your account, please verify your email address.

Please visit the following link to verify your email:
$verificationUrl

Important:
- This verification link will expire in 24 hours
- You cannot log in until your email is verified
- If you didn't create this account, please ignore this email

This is an automated email from LoginPage System. Please do not reply to this email.";
    }
    
    /**
     * Get HTML password reset email template
     */
    private function getPasswordResetEmailTemplate($userName, $resetUrl) {
        return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; padding: 30px 20px; text-align: center; }
        .content { padding: 30px 20px; }
        .button { display: inline-block; background: #dc2626; color: white; text-decoration: none; padding: 12px 24px; border-radius: 6px; margin: 20px 0; font-weight: bold; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 14px; }
        .code { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 8px 12px; font-family: monospace; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 12px; margin: 15px 0; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Password Reset Request</h1>
        </div>
        <div class="content">
            <h2>Hello, ' . htmlspecialchars($userName) . '!</h2>
            <p>We received a request to reset the password for your LoginPage account.</p>
            <p><strong>Click the button below to reset your password:</strong></p>
            <p style="text-align: center;">
                <a href="' . htmlspecialchars($resetUrl) . '" class="button">Reset My Password</a>
            </p>
            <p>If the button doesn\'t work, you can copy and paste this link into your browser:</p>
            <div class="code">' . htmlspecialchars($resetUrl) . '</div>
            <div class="warning">
                <p><strong>⚠️ Important Security Information:</strong></p>
                <ul>
                    <li>This password reset link will expire in <strong>1 hour</strong></li>
                    <li>If you didn\'t request this password reset, please ignore this email</li>
                    <li>Your password will not be changed unless you click the link above</li>
                    <li>For security, this link can only be used once</li>
                </ul>
            </div>
        </div>
        <div class="footer">
            <p>This is an automated email from LoginPage System. Please do not reply to this email.</p>
            <p>If you need help, please contact support.</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Get plain text password reset email
     */
    private function getPasswordResetEmailText($userName, $resetUrl) {
        return "Hello, $userName!

We received a request to reset the password for your LoginPage account.

Please visit the following link to reset your password:
$resetUrl

IMPORTANT SECURITY INFORMATION:
- This password reset link will expire in 1 hour
- If you didn't request this password reset, please ignore this email
- Your password will not be changed unless you click the link above
- For security, this link can only be used once

This is an automated email from LoginPage System. Please do not reply to this email.";
    }
    
    /**
     * Get base URL for the application
     */
    private function getBaseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // For localhost setup
        $baseUrl = $protocol . '://' . $host . '/LoginPage';
        
        return $baseUrl;
    }
    
    /**
     * Enable real email sending
     */
    public function enableRealEmails($smtpHost, $smtpPort, $username, $password, $fromEmail = null) {
        $this->smtpEnabled = true;
        $this->smtpConfig = [
            'host' => $smtpHost,
            'port' => $smtpPort,
            'username' => $username,
            'password' => $password,
            'encryption' => PHPMailer::ENCRYPTION_STARTTLS
        ];
        
        if ($fromEmail) {
            $this->fromEmail = $fromEmail;
        }
    }
    
    /**
     * Check if real emails are enabled
     */
    public function isRealEmailEnabled() {
        return $this->smtpEnabled;
    }
}