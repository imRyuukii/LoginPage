<?php
// Reset password controller
// Handles the actual password reset when users click the email link
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
csrf_ensure_initialized();

$error = '';
$success = '';
$token = '';
$validToken = false;
$userInfo = null;

// Get token from URL
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    // Validate token
    if (!empty($token)) {
        $tokenValidation = validatePasswordResetToken($token);
        if ($tokenValidation['success']) {
            $validToken = true;
            $userInfo = $tokenValidation['user'];
        } else {
            $error = $tokenValidation['message'];
        }
    } else {
        $error = 'Invalid password reset link.';
    }
} else {
    $error = 'No reset token provided.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    csrf_require_post();
    
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword)) {
        $error = 'Please enter a new password.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Attempt to reset password
        $resetResult = resetUserPassword($token, $newPassword);
        
        if ($resetResult['success']) {
            $success = $resetResult['message'];
            $validToken = false; // Token is now used
        } else {
            $error = $resetResult['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - LoginPage</title>
    <link rel="icon" type="image/png" href="../../public/images/logo.png">
    <link rel="stylesheet" href="../../public/css/style.css">
</head>
<body>
    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
    <img src="../../public/images/logo.png" alt="Logo" class="logo-website-top-left">
    <div class="logo-shadow"></div>
    <div class="container page">
        <div class="card">
            <h1>🔐 Reset Password</h1>
            
            <?php if (!empty($error)): ?>
                <div class="alert error mt-3">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                
                <?php if (!$validToken): ?>
                    <div class="mt-4">
                        <h3>❌ Invalid or Expired Link</h3>
                        <p>This password reset link is invalid, expired, or has already been used.</p>
                        
                        <div class="link-row centered mt-4">
                            <a class="button primary" href="./forgot-password.php">Request New Reset Link</a>
                            <a class="button" href="./login.php">Back to Login</a>
                        </div>
                        
                        <div class="mt-6">
                            <h3>💡 Why might this happen?</h3>
                            <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
                                <li><strong>Link expired:</strong> Reset links are valid for only 1 hour</li>
                                <li><strong>Already used:</strong> Each reset link can only be used once</li>
                                <li><strong>Malformed link:</strong> The link may have been copied incorrectly</li>
                                <li><strong>Security measure:</strong> Links become invalid after successful password reset</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert success mt-3">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                
                <div class="mt-4">
                    <h3>✅ Password Successfully Reset!</h3>
                    <p>Your password has been changed successfully. You can now log in with your new password.</p>
                    
                    <div class="link-row centered mt-4">
                        <a class="button primary" href="./login.php">Login Now</a>
                        <a class="button" href="../../../index.php">Go to Home</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($validToken && empty($success)): ?>
                <div class="mt-3">
                    <div class="alert info">
                        <p><strong>👋 Hello, <?php echo htmlspecialchars($userInfo['name']); ?>!</strong></p>
                        <p>You're resetting the password for: <strong><?php echo htmlspecialchars($userInfo['email']); ?></strong></p>
                    </div>
                </div>
                
                <form method="post" action="" id="resetForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required 
                           minlength="6" placeholder="Enter your new password"
                           autocomplete="new-password">
                    
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           minlength="6" placeholder="Confirm your new password"
                           autocomplete="new-password">
                    
                    <div class="password-strength" id="passwordStrength" style="display: none;">
                        <div class="strength-meter">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>
                    
                    <div class="link-row centered mt-4">
                        <button class="button primary" type="submit">🔒 Update Password</button>
                        <a class="button" href="./login.php">Cancel</a>
                    </div>
                </form>
                
                <div class="mt-6">
                    <h3>🔒 Password Security Tips</h3>
                    <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
                        <li><strong>Length:</strong> Use at least 8-12 characters</li>
                        <li><strong>Mix:</strong> Combine letters, numbers, and symbols</li>
                        <li><strong>Unique:</strong> Don't reuse passwords from other sites</li>
                        <li><strong>Secure:</strong> This link expires in 1 hour for security</li>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (empty($success) && empty($error)): ?>
                <div class="mt-6">
                    <h3>📞 Need Help?</h3>
                    <p style="text-align: center;">
                        Remember your password? <a href="./login.php">Login here</a><br>
                        Don't have an account? <a href="./register.php">Register here</a><br>
                        Need a new reset link? <a href="./forgot-password.php">Request reset</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="demo-warning">*This is a demo version of the website</div>
    
    <script>
    (function() {
        // Theme toggle functionality
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
        
        // Password strength checker
        const passwordField = document.getElementById('password');
        const confirmField = document.getElementById('confirm_password');
        const strengthDiv = document.getElementById('passwordStrength');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        
        if (passwordField) {
            passwordField.addEventListener('input', function() {
                const password = this.value;
                if (password.length === 0) {
                    strengthDiv.style.display = 'none';
                    return;
                }
                
                strengthDiv.style.display = 'block';
                
                let score = 0;
                let feedback = [];
                
                // Length check
                if (password.length >= 8) score += 1;
                else feedback.push('Use at least 8 characters');
                
                // Complexity checks
                if (/[a-z]/.test(password)) score += 1;
                else feedback.push('Add lowercase letters');
                
                if (/[A-Z]/.test(password)) score += 1;
                else feedback.push('Add uppercase letters');
                
                if (/\d/.test(password)) score += 1;
                else feedback.push('Add numbers');
                
                if (/[^A-Za-z0-9]/.test(password)) score += 1;
                else feedback.push('Add special characters');
                
                // Update strength display
                let strength = 'Very Weak';
                let color = '#dc2626';
                
                if (score >= 4) {
                    strength = 'Strong';
                    color = '#16a34a';
                } else if (score >= 3) {
                    strength = 'Good';
                    color = '#ca8a04';
                } else if (score >= 2) {
                    strength = 'Fair';
                    color = '#ea580c';
                }
                
                strengthBar.style.width = (score * 20) + '%';
                strengthBar.style.backgroundColor = color;
                strengthText.textContent = strength;
                strengthText.style.color = color;
                
                if (feedback.length > 0) {
                    strengthText.textContent += ' - ' + feedback.join(', ');
                }
            });
            
            // Password confirmation check
            function checkPasswordMatch() {
                if (confirmField.value && passwordField.value !== confirmField.value) {
                    confirmField.setCustomValidity('Passwords do not match');
                } else {
                    confirmField.setCustomValidity('');
                }
            }
            
            passwordField.addEventListener('input', checkPasswordMatch);
            confirmField.addEventListener('input', checkPasswordMatch);
        }
    })();
    </script>
    
    <style>
    .alert.info {
        background-color: #dbeafe;
        border-color: #3b82f6;
        color: #1e40af;
    }
    
    .password-strength {
        margin: 10px 0;
    }
    
    .strength-meter {
        width: 100%;
        height: 6px;
        background-color: #e5e7eb;
        border-radius: 3px;
        overflow: hidden;
        margin-bottom: 5px;
    }
    
    .strength-bar {
        height: 100%;
        width: 0%;
        transition: width 0.3s ease, background-color 0.3s ease;
    }
    
    .strength-text {
        font-size: 14px;
        font-weight: 500;
    }
    </style>
</body>
</html>