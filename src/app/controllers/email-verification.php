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
    <title>Email Verification</title>
    <link rel="icon" type="image/png" href="../../public/images/logo.png">
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
    <img src="../../public/images/logo.png" alt="Logo" class="logo-website-top-left">
    <div class="logo-shadow"></div>
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
    
    <script>
    (function() {
        const root = document.documentElement;
        const stored = localStorage.getItem('theme');
        const prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
        const initial = stored || (prefersLight ? 'light' : 'dark');
        if (initial === 'light') {
            root.setAttribute('data-theme', 'light');
        } else {
            root.removeAttribute('data-theme');
        }
        const btn = document.getElementById('themeToggle');

        function setIcon() {
            const isLight = root.getAttribute('data-theme') === 'light';
            btn.textContent = isLight ? '☀️' : '🌙';
            btn.title = isLight ? 'Switch to dark mode' : 'Switch to light mode';
        }
        setIcon();
        btn.addEventListener('click', function() {
            document.body.classList.add('theme-transition');
            const isLight = root.getAttribute('data-theme') === 'light';
            if (isLight) {
                root.removeAttribute('data-theme');
                localStorage.setItem('theme', 'dark');
            } else {
                root.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
            }
            setIcon();
            window.setTimeout(function(){
                document.body.classList.remove('theme-transition');
            }, 320);
        });
        
        // Auto-redirect to login after successful verification
        <?php if (!empty($success)): ?>
        setTimeout(function() {
            if (confirm('Verification successful! Would you like to go to the login page now?')) {
                window.location.href = './login.php';
            }
        }, 3000);
        <?php endif; ?>
    })();
    </script>
</body>
</html>