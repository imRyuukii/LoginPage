<?php
// Debug script to check URL generation
require_once './src/app/services/EmailService.php';

echo "<h1>URL Debug Information</h1>";

// Test EmailService URL generation
$emailService = new EmailService();

// Use reflection to access private method
$reflection = new ReflectionClass($emailService);
$method = $reflection->getMethod('getBaseUrl');
$method->setAccessible(true);
$baseUrl = $method->invoke($emailService);

echo "<h2>Generated Base URL:</h2>";
echo "<p><strong>Base URL:</strong> " . htmlspecialchars($baseUrl) . "</p>";

// Test verification URL
$testToken = 'test123456789';
$verificationUrl = $baseUrl . '/src/app/controllers/email-verification.php?token=' . urlencode($testToken);

echo "<h2>Test Verification URL:</h2>";
echo "<p><strong>Full URL:</strong> " . htmlspecialchars($verificationUrl) . "</p>";

// Check if the verification file exists
$verificationFile = __DIR__ . '/src/app/controllers/email-verification.php';
echo "<h2>File Check:</h2>";
echo "<p><strong>Verification file exists:</strong> " . (file_exists($verificationFile) ? '✅ Yes' : '❌ No') . "</p>";
echo "<p><strong>File path:</strong> " . htmlspecialchars($verificationFile) . "</p>";

// Test link
echo "<h2>Test Link:</h2>";
echo "<p><a href='" . htmlspecialchars($verificationUrl) . "' target='_blank'>Click to test verification URL</a></p>";

echo "<h2>Server Information:</h2>";
echo "<p><strong>HTTP_HOST:</strong> " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'Not set') . "</p>";
echo "<p><strong>SCRIPT_NAME:</strong> " . htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? 'Not set') . "</p>";
echo "<p><strong>REQUEST_URI:</strong> " . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'Not set') . "</p>";
echo "<p><strong>DOCUMENT_ROOT:</strong> " . htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'Not set') . "</p>";

echo "<h2>Expected URL Format:</h2>";
echo "<p>Your verification URL should look like:</p>";
echo "<code>http://localhost/mb/LoginPage/src/app/controllers/email-verification.php?token=abc123...</code>";

echo "<h2>Manual Test:</h2>";
echo "<p>Try accessing this URL manually:</p>";
echo "<a href='http://localhost/mb/LoginPage/src/app/controllers/email-verification.php?token=test' target='_blank'>http://localhost/mb/LoginPage/src/app/controllers/email-verification.php?token=test</a>";