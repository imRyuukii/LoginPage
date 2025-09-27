<?php
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
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirmPassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $result = registerUser($username, $password, $name, $email);
        if ($result['success']) {
            // Get the newly created user to send verification email
            $newUser = findUserByEmail($email);
            
            if ($newUser) {
                try {
                    // Create verification token
                    $token = createEmailVerificationToken($newUser['id']);

                    // Use the SMTP version instead
                    require_once '../services/EmailServiceSMTP.php';
                    $emailService = new EmailServiceSMTP();

                    // Configure real email sending
                    // ⚠️ IMPORTANT: Replace these with your actual Gmail credentials!
                    $emailService->enableRealEmails(
                            'smtp.gmail.com',
                            587,
                            'zsplitt014@gmail.com',
                            'jusb keps jlag xpis',
                            ''
                    );

                    $emailSent = $emailService->sendVerificationEmail($email, $name, $token);
                    
                    if ($emailSent) {
                        $success = 'Registration successful! Please check your email (' . htmlspecialchars($email) . ') for a verification link to activate your account.';
                    } else {
                        $success = 'Registration successful, but we couldn\'t send the verification email. Please contact support.';
                    }
                } catch (Exception $e) {
                    error_log('Email verification setup failed: ' . $e->getMessage());
                    $success = 'Registration successful, but there was an issue with email verification. Please contact support.';
                }
            } else {
                $success = 'Registration successful! Please contact support to verify your email.';
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="icon" type="image/png" href="../../public/images/logo.png">
    <link rel="stylesheet" href="../../public/css/style.css">
</head>
<body>
    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
    <img src="../../public/images/logo.png" alt="Logo" class="logo-website-top-left">
    <div class="logo-shadow"></div>
    <div class="container page">
        <div class="card">
            <h1>Register</h1>
            <?php if (!empty($error)): ?>
                <p class="alert error mt-3"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <p class="alert success mt-3"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <form method="post" action="">
                <?php echo csrf_field(); ?>
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">

                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">

                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>

                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>

                <div class="link-row centered mt-4">
                    <button class="button primary" type="submit">Register</button>
                    <a class="button" href="./login.php">Back to Login</a>
                </div>
            </form>
            <div class="mt-6">
                <div class="alert info" style="background: rgba(99,102,241,0.1); border-color: rgba(99,102,241,0.25); color: var(--text);">
                    <strong>📧 Email Verification Required</strong><br>
                    After registration, you'll receive a verification email. You must click the verification link before you can log in.
                </div>
            </div>
            <p class="footer mt-6">Already have an account? <a href="./login.php">Login here</a></p>
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
