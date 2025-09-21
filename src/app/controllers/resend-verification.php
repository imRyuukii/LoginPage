<?php
// Resend email verification controller
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
require_once '../security/csrf.php';
require_once '../services/EmailService.php';
csrf_ensure_initialized();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Find user by email
        $user = findUserByEmail($email);
        
        if (!$user) {
            // Don't reveal whether email exists for security
            $success = 'If an account with that email exists and is unverified, a new verification email has been sent.';
        } elseif ($user['email_verified']) {
            $error = 'This email address is already verified. You can log in normally.';
        } else {
            try {
                // Create new verification token
                $token = createEmailVerificationToken($user['id']);
                
                // Send verification email
                $emailService = new EmailService();
                $emailSent = $emailService->sendVerificationEmail($email, $user['name'], $token);
                
                if ($emailSent) {
                    $success = 'A new verification email has been sent to ' . htmlspecialchars($email) . '. Please check your email and click the verification link.';
                } else {
                    $error = 'Failed to send verification email. Please try again later or contact support.';
                }
            } catch (Exception $e) {
                error_log('Resend verification failed: ' . $e->getMessage());
                $error = 'An error occurred while sending the verification email. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Email Verification</title>
    <link rel="icon" type="image/png" href="../../public/images/logo.png">
    <link rel="stylesheet" href="../../public/css/style.css">
</head>
<body>
    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
    <img src="../../public/images/logo.png" alt="Logo" class="logo-website-top-left">
    <div class="logo-shadow"></div>
    <div class="container page">
        <div class="card">
            <h1>📧 Resend Email Verification</h1>
            
            <?php if (!empty($error)): ?>
                <div class="alert error mt-3">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert success mt-3">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <div class="link-row centered mt-4">
                    <a class="button primary" href="./login.php">Go to Login</a>
                    <a class="button" href="../../../index.php">Go to Home</a>
                </div>
            <?php else: ?>
                <p class="mt-3">
                    If you didn't receive your email verification link or it has expired, 
                    enter your email address below to receive a new verification email.
                </p>
                
                <form method="post" action="">
                    <?php echo csrf_field(); ?>
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="Enter your email address">

                    <div class="link-row centered mt-4">
                        <button class="button primary" type="submit">📧 Resend Verification Email</button>
                        <a class="button" href="./login.php">Back to Login</a>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="mt-6">
                <h3>💡 Verification Tips</h3>
                <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
                    <li><strong>Check spam folder:</strong> Verification emails sometimes go to spam</li>
                    <li><strong>Wait a few minutes:</strong> Email delivery can sometimes be delayed</li>
                    <li><strong>Correct email:</strong> Make sure you're using the same email from registration</li>
                    <li><strong>Link expires:</strong> Verification links expire after 24 hours</li>
                </ul>
            </div>
            
            <p class="footer mt-6">
                Remember your password? <a href="./login.php">Try logging in</a><br>
                Don't have an account? <a href="./register.php">Register here</a>
            </p>
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
    })();
    </script>
</body>
</html>