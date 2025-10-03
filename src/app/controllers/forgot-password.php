<?php
// Forgot password controller
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
require_once '../services/EmailServiceSMTP.php';
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
        try {
            // Create password reset token
            $result = createPasswordResetToken($email);
            
            if ($result['success']) {
                // Send password reset email
                $emailService = new EmailServiceSMTP();
                
                // Use the same SMTP configuration as registration
                $emailService->enableRealEmails(
                    'smtp.gmail.com',
                    587,
                    // Note: These should match your registration email configuration
                    // In a production environment, these would be in environment variables
                    $_ENV['SMTP_USERNAME'] ?? 'zsplitt014@gmail.com',
                    $_ENV['SMTP_PASSWORD'] ?? 'jusb keps jlag xpis',
                    $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@yoursite.com'
                );
                
                $emailSent = $emailService->sendPasswordResetEmail(
                    $email,
                    $result['user']['name'],
                    $result['token']
                );
                
                if ($emailSent) {
                    $success = 'Password reset instructions have been sent to ' . htmlspecialchars($email) . '. Please check your email and follow the instructions to reset your password.';
                } else {
                    $error = 'We could not send the password reset email. Please try again later or contact support.';
                }
            } else {
                // For security, don't reveal if email exists or not
                // Always show success message even if email doesn't exist
                $success = 'If an account with that email address exists, password reset instructions have been sent to ' . htmlspecialchars($email) . '.';
            }
        } catch (Exception $e) {
            error_log('Password reset request failed: ' . $e->getMessage());
            $error = 'An error occurred while processing your password reset request. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - LoginPage</title>
    <link rel="icon" type="image/png" href="../../public/images/logo.png">
    <link rel="stylesheet" href="src/public/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
    <img src="../../public/images/logo.png" alt="Logo" class="logo-website-top-left">
    <div class="logo-shadow"></div>
    <div class="container page">
        <div class="card">
            <h1>🔐 Forgot Password</h1>
            
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
                    <a class="button primary" href="./login.php">Back to Login</a>
                    <a class="button" href="../../../index.php">Go to Home</a>
                </div>
            <?php else: ?>
                <p class="mt-3">
                    Enter your email address below and we'll send you instructions on how to reset your password.
                </p>
                
                <form method="post" action="">
                    <?php echo csrf_field(); ?>
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="Enter your email address">

                    <div class="link-row centered mt-4">
                        <button class="button primary" type="submit">📧 Send Reset Instructions</button>
                        <a class="button" href="./login.php">Back to Login</a>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="mt-6">
                <h3>💡 Password Reset Tips</h3>
                <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
                    <li><strong>Check your email:</strong> The reset link will arrive within a few minutes</li>
                    <li><strong>Check spam folder:</strong> Sometimes emails go to spam</li>
                    <li><strong>Link expires:</strong> Reset links are valid for 1 hour</li>
                    <li><strong>One-time use:</strong> Each reset link can only be used once</li>
                    <li><strong>Security:</strong> Your password won't change until you complete the reset</li>
                </ul>
            </div>
            
            <div class="mt-6">
                <h3>📞 Need Help?</h3>
                <p style="text-align: center;">
                    Remember your password? <a href="./login.php">Login here</a><br>
                    Don't have an account? <a href="./register.php">Register here</a><br>
                    Need to resend verification? <a href="./resend-verification.php">Resend verification</a>
                </p>
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
    })();
    </script>
</body>
</html>