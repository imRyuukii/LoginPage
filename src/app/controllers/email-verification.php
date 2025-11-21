<?php
// Email verification controller
// Harden session cookie and start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
session_start();
require_once '../models/user-functions-db.php';
require_once __DIR__ . '/../security/headers.php';
apply_default_security_headers();
apply_sensitive_nocache();

$error = '';
$success = '';
$user = null;

// Handle verification
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    $result = verifyEmailToken($token);
    
    if ($result['success']) {
        $success = $result['message'];
        $user = $result['user'];
        
        // Clean up expired tokens while we're here
        cleanupExpiredTokens();
    } else {
        $error = $result['message'];
    }
} else {
    $error = 'No verification token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sulfur • Email Verification</title>
    <link rel="icon" type="image/png" href="../../public/images/logo-sulfur.png">
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo filemtime(__DIR__ . "/../../public/css/style.css"); ?>">
    <script src="../../public/js/toast.js?v=<?php echo filemtime(__DIR__ . "/../../public/js/toast.js"); ?>" defer></script>
    <script src="../../public/js/auth-ui.js?v=<?php echo filemtime(__DIR__ . "/../../public/js/auth-ui.js"); ?>" defer></script>
</head>
<body>
    <?php $NAV_BASE='../../'; include __DIR__ . '/../../public/partials/navbar.php'; ?>
    <div class="container page">
        <div class="card">
            <h1>🔐 Email Verification</h1>
            
            <?php if (!empty($error)): ?>
                <div class="alert error mt-3">
                    <strong>❌ Verification Failed</strong><br>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <div class="link-row centered mt-4">
                    <a class="button" href="./login.php">Back to Login</a>
                    <a class="button" href="./register.php">Register New Account</a>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success) && $user): ?>
                <div class="alert success mt-3">
                    <strong>✅ Email Verified Successfully!</strong><br>
                    <?php echo htmlspecialchars($success); ?>
                </div>
                
                <div class="mt-4">
                    <h3>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h3>
                    <p>Your account has been successfully verified. You can now:</p>
                    <ul>
                        <li>✅ Log in to your account</li>
                        <li>✅ Access all website features</li>
                        <li>✅ Update your profile</li>
                    </ul>
                </div>
                
                <div class="link-row centered mt-4">
                    <a class="button primary" href="./login.php">Login Now</a>
                    <a class="button" href="../../../index.php">Go to Home</a>
                </div>
            <?php endif; ?>
            
            <div class="mt-6">
                <h3>📧 Email Verification Tips</h3>
                <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
                    <li><strong>Didn't receive the email?</strong> Check your spam/junk folder</li>
                    <li><strong>Link expired?</strong> Register a new account or contact support</li>
                    <li><strong>Need help?</strong> Make sure you're clicking the correct verification link</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="demo-warning">*This is a demo version of the website</div>
    
</body>
</html>