<?php
echo "<!DOCTYPE html><html><head><title>Setup Real Email Sending</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:0 auto;padding:20px;}";
echo ".section{background:#f8f9fa;padding:20px;margin:20px 0;border-radius:8px;}";
echo ".success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;}";
echo ".error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;}";
echo ".warning{background:#fff3cd;border:1px solid #ffeaa7;color:#856404;}";
echo "code{background:#e9ecef;padding:2px 4px;border-radius:3px;}";
echo "pre{background:#f8f9fa;padding:15px;border-radius:5px;overflow-x:auto;}";
echo ".step{margin:20px 0;padding:15px;border-left:4px solid #6366f1;background:#f8f9ff;}";
echo "</style></head><body>";

echo "<h1>🚀 Setup Real Email Sending</h1>";

echo "<div class='section warning'>";
echo "<h2>📧 Current Status</h2>";
echo "<p><strong>Right now:</strong> Your email verification works perfectly but only sends to MailHog (local testing)</p>";
echo "<p><strong>To send real emails:</strong> Follow the steps below to configure Gmail SMTP or other email providers</p>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>🎯 Option 1: Gmail SMTP (Recommended)</h2>";
echo "<p>This will send real emails through your Gmail account.</p>";

echo "<div class='step'>";
echo "<h3>Step 1: Install PHPMailer</h3>";
echo "<p>Open Command Prompt in your LoginPage folder and run:</p>";
echo "<pre>composer require phpmailer/phpmailer</pre>";
echo "<p>If you don't have Composer installed, <a href='https://getcomposer.org/download/' target='_blank'>download it here</a></p>";
echo "</div>";

echo "<div class='step'>";
echo "<h3>Step 2: Get Gmail App Password</h3>";
echo "<ol>";
echo "<li>Go to your Google Account settings: <a href='https://myaccount.google.com' target='_blank'>https://myaccount.google.com</a></li>";
echo "<li>Click on <strong>Security</strong> in the left sidebar</li>";
echo "<li>Enable <strong>2-Step Verification</strong> (required for app passwords)</li>";
echo "<li>After enabling 2FA, go back to Security and click <strong>App passwords</strong></li>";
echo "<li>Select <strong>Mail</strong> as the app type</li>";
echo "<li>Generate the password and <strong>save it</strong> (you won't be able to see it again)</li>";
echo "</ol>";
echo "</div>";

echo "<div class='step'>";
echo "<h3>Step 3: Update Your Registration Code</h3>";
echo "<p>Replace the EmailService line in your register.php with:</p>";
echo "<pre>// Replace this line:\n\$emailService = new EmailService();\n\n// With this:\nrequire_once '../services/EmailServiceSMTP.php';\n\$emailService = new EmailServiceSMTP();\n\n// Enable real emails with your Gmail credentials\n\$emailService->enableRealEmails(\n    'smtp.gmail.com',\n    587,\n    'your-gmail@gmail.com',     // Your Gmail address\n    'your-app-password-here',   // The app password from Step 2\n    'noreply@yoursite.com'      // From email (can be different)\n);</pre>";
echo "</div>";

echo "<div class='step'>";
echo "<h3>Step 4: Test Real Email</h3>";
echo "<p>Register a new test account with your real email address and check if you receive the verification email!</p>";
echo "</div>";

echo "</div>";

echo "<div class='section'>";
echo "<h2>🎯 Option 2: Other Email Providers</h2>";
echo "<p>You can also use other email services:</p>";
echo "<ul>";
echo "<li><strong>Outlook/Hotmail:</strong> smtp-mail.outlook.com, port 587</li>";
echo "<li><strong>Yahoo:</strong> smtp.mail.yahoo.com, port 587</li>";
echo "<li><strong>Custom SMTP:</strong> Use your hosting provider's SMTP settings</li>";
echo "</ul>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>🔧 Quick Setup Script</h2>";
echo "<p>Want me to help you set it up quickly? Fill in your details:</p>";

if ($_POST) {
    echo "<div class='section success'>";
    echo "<h3>✅ Configuration Code Generated!</h3>";
    echo "<p>Add this code to your register.php file:</p>";
    
    $gmail = htmlspecialchars($_POST['gmail']);
    $appPassword = htmlspecialchars($_POST['app_password']);
    $fromEmail = htmlspecialchars($_POST['from_email'] ?: $gmail);
    
    echo "<pre>// Add after: require_once '../services/EmailService.php';
require_once '../services/EmailServiceSMTP.php';

// Replace: \$emailService = new EmailService();
// With:
\$emailService = new EmailServiceSMTP();
\$emailService->enableRealEmails(
    'smtp.gmail.com',
    587,
    '$gmail',
    '$appPassword',
    '$fromEmail'
);</pre>";
    
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Make sure PHPMailer is installed: <code>composer require phpmailer/phpmailer</code></li>";
    echo "<li>Update your register.php and resend-verification.php files with the code above</li>";
    echo "<li>Test with a real email address!</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<form method='post' style='background:white;padding:20px;border-radius:8px;'>";
    echo "<p><label>Your Gmail Address:<br><input type='email' name='gmail' required style='width:100%;padding:8px;margin:5px 0;'></label></p>";
    echo "<p><label>Gmail App Password:<br><input type='text' name='app_password' required style='width:100%;padding:8px;margin:5px 0;' placeholder='16-character app password'></label></p>";
    echo "<p><label>From Email (optional):<br><input type='email' name='from_email' style='width:100%;padding:8px;margin:5px 0;' placeholder='Leave blank to use Gmail address'></label></p>";
    echo "<p><button type='submit' style='background:#28a745;color:white;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;'>Generate Configuration Code</button></p>";
    echo "</form>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>🔍 Troubleshooting</h2>";
echo "<ul>";
echo "<li><strong>\"Less secure app access\":</strong> Not needed if you use App Passwords</li>";
echo "<li><strong>\"Authentication failed\":</strong> Double-check your Gmail address and App Password</li>";
echo "<li><strong>\"Connection refused\":</strong> Make sure port 587 is not blocked by your ISP</li>";
echo "<li><strong>\"PHPMailer not found\":</strong> Run <code>composer require phpmailer/phpmailer</code></li>";
echo "</ul>";
echo "</div>";

echo "<div class='section success'>";
echo "<h2>✅ Summary</h2>";
echo "<p><strong>Current system:</strong> Fully functional email verification (local testing with MailHog)</p>";
echo "<p><strong>To send real emails:</strong> Install PHPMailer + configure Gmail SMTP + update register.php</p>";
echo "<p><strong>Security:</strong> All the security, token validation, and database handling is already perfect!</p>";
echo "</div>";

echo "<p><a href='../index.php'>← Back to LoginPage</a></p>";
echo "</body></html>";
?>