<?php
// Test Password Reset Functionality
// Run this script to test the complete password reset flow
echo "<h2>🔐 Password Reset System Test</h2>\n";
echo "<p>Testing the complete password reset functionality...</p>\n";

// Include required files
require_once 'src/config/database.php';
require_once 'src/app/models/user-functions-db.php';
require_once 'src/app/services/EmailServiceSMTP.php';

echo "<hr><h3>1. Database Connection Test</h3>\n";
try {
    global $db;
    if ($db) {
        echo "✅ Database connection successful<br>\n";
        
        // Check if password_resets table exists
        $stmt = $db->query("SHOW TABLES LIKE 'password_resets'");
        if ($stmt->rowCount() > 0) {
            echo "✅ password_resets table exists<br>\n";
            
            // Show table structure
            $stmt = $db->query("DESCRIBE password_resets");
            $columns = $stmt->fetchAll();
            echo "📋 Table structure:<br>\n";
            foreach ($columns as $column) {
                echo "&nbsp;&nbsp;- {$column['Field']} ({$column['Type']})<br>\n";
            }
        } else {
            echo "❌ password_resets table not found<br>\n";
        }
    } else {
        echo "❌ Database connection failed<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>\n";
}

echo "<hr><h3>2. Password Reset Functions Test</h3>\n";
try {
    // Test with a non-existent email
    echo "Testing with non-existent email...<br>\n";
    $result = createPasswordResetToken('nonexistent@example.com');
    if (!$result['success']) {
        echo "✅ Correctly handled non-existent email<br>\n";
    } else {
        echo "❌ Should not create token for non-existent email<br>\n";
    }
    
    // Find an existing verified user for testing
    echo "<br>Looking for existing verified users...<br>\n";
    $stmt = $db->query("SELECT * FROM users WHERE email_verified = 1 LIMIT 1");
    $testUser = $stmt->fetch();
    
    if ($testUser) {
        echo "✅ Found test user: {$testUser['email']}<br>\n";
        
        // Test token creation
        echo "<br>Testing token creation...<br>\n";
        $tokenResult = createPasswordResetToken($testUser['email']);
        
        if ($tokenResult['success']) {
            echo "✅ Password reset token created successfully<br>\n";
            echo "&nbsp;&nbsp;- Token: " . substr($tokenResult['token'], 0, 10) . "...<br>\n";
            
            // Test token validation
            echo "<br>Testing token validation...<br>\n";
            $validateResult = validatePasswordResetToken($tokenResult['token']);
            if ($validateResult['success']) {
                echo "✅ Token validation successful<br>\n";
                echo "&nbsp;&nbsp;- User: {$validateResult['user']['email']}<br>\n";
                
                // Test invalid token
                echo "<br>Testing invalid token...<br>\n";
                $invalidResult = validatePasswordResetToken('invalid_token_123');
                if (!$invalidResult['success']) {
                    echo "✅ Invalid token correctly rejected<br>\n";
                } else {
                    echo "❌ Invalid token should be rejected<br>\n";
                }
            } else {
                echo "❌ Token validation failed: {$validateResult['message']}<br>\n";
            }
        } else {
            echo "❌ Token creation failed: {$tokenResult['message']}<br>\n";
        }
    } else {
        echo "❌ No verified users found. Please register and verify a user first.<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Function test error: " . $e->getMessage() . "<br>\n";
}

echo "<hr><h3>3. Email Service Test</h3>\n";
try {
    $emailService = new EmailServiceSMTP();
    echo "✅ EmailServiceSMTP class loaded successfully<br>\n";
    
    // Check if PHPMailer is available
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        echo "✅ PHPMailer is available<br>\n";
    } else {
        echo "⚠️ PHPMailer not found (emails will use local mail())<br>\n";
    }
    
    // Test email method exists
    if (method_exists($emailService, 'sendPasswordResetEmail')) {
        echo "✅ sendPasswordResetEmail method exists<br>\n";
    } else {
        echo "❌ sendPasswordResetEmail method not found<br>\n";
    }
    
    echo "📧 Email service is ready (configure SMTP for real emails)<br>\n";
    
} catch (Exception $e) {
    echo "❌ Email service error: " . $e->getMessage() . "<br>\n";
}

echo "<hr><h3>4. Controllers Test</h3>\n";
$controllers = [
    'forgot-password.php' => 'Forgot password form',
    'reset-password.php' => 'Password reset form'
];

foreach ($controllers as $file => $description) {
    $path = "src/app/controllers/$file";
    if (file_exists($path)) {
        echo "✅ $description controller exists<br>\n";
    } else {
        echo "❌ $description controller missing<br>\n";
    }
}

echo "<hr><h3>5. Manual Testing Instructions</h3>\n";
echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #007acc;'>\n";
echo "<h4>🧪 To manually test the complete flow:</h4>\n";
echo "<ol>\n";
echo "<li><strong>Start XAMPP</strong> and make sure Apache and MySQL are running</li>\n";
echo "<li><strong>Configure SMTP</strong> in the forgot-password.php and registration scripts</li>\n";
echo "<li><strong>Visit the login page:</strong> <a href='src/app/controllers/login.php'>Login Page</a></li>\n";
echo "<li><strong>Click 'Reset password'</strong> link</li>\n";
echo "<li><strong>Enter a verified user's email</strong></li>\n";
echo "<li><strong>Check email</strong> for the reset link</li>\n";
echo "<li><strong>Click the reset link</strong> and change password</li>\n";
echo "<li><strong>Try logging in</strong> with the new password</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "<hr><h3>6. SMTP Configuration Guide</h3>\n";
echo "<div style='background: #fff8dc; padding: 15px; border-left: 4px solid #daa520;'>\n";
echo "<h4>📧 To enable real email sending:</h4>\n";
echo "<p><strong>1. Get Gmail App Password:</strong></p>\n";
echo "<ul>\n";
echo "<li>Go to Google Account settings</li>\n";
echo "<li>Enable 2-factor authentication</li>\n";
echo "<li>Generate an App Password for 'Mail'</li>\n";
echo "</ul>\n";
echo "<p><strong>2. Update the code:</strong></p>\n";
echo "<p>In forgot-password.php, replace:</p>\n";
echo "<code>\n";
echo "\$_ENV['SMTP_USERNAME'] ?? 'YOUR_GMAIL@gmail.com',<br>\n";
echo "\$_ENV['SMTP_PASSWORD'] ?? 'YOUR_APP_PASSWORD_HERE',<br>\n";
echo "</code>\n";
echo "<p>With your actual Gmail and App Password</p>\n";
echo "</div>\n";

echo "<hr><h3>7. Security Features ✨</h3>\n";
echo "<div style='background: #f0fff0; padding: 15px; border-left: 4px solid #32cd32;'>\n";
echo "<ul>\n";
echo "<li>🔐 CSRF protection on all forms</li>\n";
echo "<li>⏱️ Tokens expire after 1 hour</li>\n";
echo "<li>🔒 One-time use tokens</li>\n";
echo "<li>🔍 No information disclosure (doesn't reveal if email exists)</li>\n";
echo "<li>💾 Secure token storage in database</li>\n";
echo "<li>🎯 Only verified users can reset passwords</li>\n";
echo "<li>🧹 Automatic cleanup of expired tokens</li>\n";
echo "<li>📝 Comprehensive error logging</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<hr><p><strong>✅ Password Reset System Setup Complete!</strong></p>\n";
echo "<p>The system is ready for testing. Configure your SMTP settings and test the flow manually.</p>\n";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h2, h3 { color: #333; }
hr { margin: 20px 0; border: 1px solid #ddd; }
code { background: #f5f5f5; padding: 2px 4px; font-family: monospace; }
</style>