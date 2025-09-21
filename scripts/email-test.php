<?php
/**
 * Email Test Script for XAMPP
 * 
 * This script helps test if your email configuration is working.
 * Run this script to test email sending before using the verification system.
 */

// Simple test email function
function testEmailSending() {
    $to = 'zsplitt014@gmail.com'; // Change this to your email
    $subject = 'Test Email from LoginPage';
    $message = 'This is a test email from your LoginPage system. If you receive this, email is working!';
    $headers = 'From: noreply@loginpage.local' . "\r\n" .
               'Reply-To: noreply@loginpage.local' . "\r\n" .
               'X-Mailer: PHP/' . phpversion();

    $result = mail($to, $subject, $message, $headers);
    
    return $result;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .section { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        code { background: #e9ecef; padding: 2px 4px; border-radius: 3px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>📧 Email Configuration Guide for LoginPage</h1>
    
    <div class="section warning">
        <h2>⚠️ Important Note</h2>
        <p><strong>XAMPP doesn't send real emails by default!</strong> You have several options:</p>
        <ul>
            <li><strong>Option 1:</strong> Use MailHog (recommended for development)</li>
            <li><strong>Option 2:</strong> Configure XAMPP with a real SMTP server</li>
            <li><strong>Option 3:</strong> Use a service like PHPMailer with Gmail SMTP</li>
        </ul>
    </div>

    <div class="section">
        <h2>🔧 Option 1: Install MailHog (Recommended)</h2>
        <p>MailHog is a local mail server that captures all emails for testing:</p>
        <ol>
            <li>Download MailHog from: <code>https://github.com/mailhog/MailHog/releases</code></li>
            <li>Run MailHog: <code>MailHog.exe</code></li>
            <li>View emails at: <code>http://localhost:8025</code></li>
            <li>Configure PHP to use MailHog (see below)</li>
        </ol>
        
        <h3>PHP Configuration for MailHog:</h3>
        <p>Add these lines to your <code>php.ini</code> file (in XAMPP/php/php.ini):</p>
        <pre>sendmail_path = "C:\xampp\sendmail\sendmail.exe -t"</pre>
        
        <p>Configure <code>sendmail.ini</code> (in XAMPP/sendmail/sendmail.ini):</p>
        <pre>smtp_server=127.0.0.1
smtp_port=1025
default_domain=localhost
error_logfile=error.log
debug_logfile=debug.log</pre>
    </div>

    <div class="section">
        <h2>🔧 Option 2: Use Real SMTP (Gmail Example)</h2>
        <p>To use Gmail SMTP, you'll need to modify the EmailService.php to use PHPMailer:</p>
        <ol>
            <li>Install PHPMailer via Composer: <code>composer require phpmailer/phpmailer</code></li>
            <li>Update EmailService.php to use SMTP instead of mail()</li>
            <li>Use an App Password for Gmail (not your regular password)</li>
        </ol>
    </div>

    <div class="section">
        <h2>🔧 Option 3: File-based Email Testing</h2>
        <p>For development, you can log emails to files instead of sending them:</p>
        <p>Modify your <code>php.ini</code>:</p>
        <pre>sendmail_path = "C:\xampp\php\extras\sendmail\fake_sendmail.bat"</pre>
        
        <p>Create a batch file that logs emails to a file instead of sending them.</p>
    </div>

    <div class="section">
        <h2>✅ Next Steps</h2>
        <ol>
            <li><strong>Run the database update:</strong> Execute <code>scripts/db/email-verification-update.sql</code> in phpMyAdmin</li>
            <li><strong>Configure email:</strong> Choose one of the options above</li>
            <li><strong>Test registration:</strong> Register a new account and check for verification email</li>
            <li><strong>Test verification:</strong> Click the verification link in the email</li>
            <li><strong>Test login:</strong> Try logging in with verified and unverified accounts</li>
        </ol>
    </div>

    <div class="section">
        <h2>🧪 Email Test</h2>
        <?php
        if (isset($_GET['test']) && $_GET['test'] === '1') {
            echo '<div class="section">';
            if (testEmailSending()) {
                echo '<div class="success">✅ Email sending appears to be working! Check your email.</div>';
            } else {
                echo '<div class="error">❌ Email sending failed. Check your email configuration.</div>';
            }
            echo '</div>';
        }
        ?>
        <p><a href="?test=1">🧪 Click here to test email sending</a></p>
        <p><small>Note: Change the email address in the script first!</small></p>
    </div>

    <div class="section">
        <h2>📝 Email Verification System Overview</h2>
        <p>Your email verification system includes:</p>
        <ul>
            <li>✅ Database schema with verification tokens</li>
            <li>✅ Email service with HTML templates</li>
            <li>✅ Registration process sends verification emails</li>
            <li>✅ Login process checks email verification</li>
            <li>✅ Verification controller handles token validation</li>
            <li>✅ Resend verification functionality</li>
            <li>✅ Proper error handling and security measures</li>
        </ul>
    </div>

    <div class="section">
        <h2>🔍 Troubleshooting</h2>
        <ul>
            <li><strong>Database errors:</strong> Make sure you ran the SQL update script</li>
            <li><strong>Email not sending:</strong> Check your email configuration above</li>
            <li><strong>Base URL issues:</strong> Check the EmailService.php getBaseUrl() method</li>
            <li><strong>Token errors:</strong> Ensure your database has the verification tables</li>
        </ul>
    </div>

    <p><a href="../index.php">← Back to LoginPage</a></p>
</body>
</html>
