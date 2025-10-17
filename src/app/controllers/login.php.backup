<?php
// Harden session cookie and start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "domain" => "",
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Lax",
    ]);
}
session_start();
require_once "../models/user-functions-db.php";
require_once "../security/csrf.php";
csrf_ensure_initialized();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_require_post();
    $login = isset($_POST["login"]) ? trim($_POST["login"]) : "";
    $password = isset($_POST["password"]) ? trim($_POST["password"]) : "";

    $user = loginUser($login, $password);

    if ($user) {
        // Check if email is verified
        if (!$user["email_verified"]) {
            $error =
                "Please verify your email address before logging in. Check your email for the verification link.";
        } else {
            // Update last seen timestamps
            updateLastActive($user["id"]);
            updateUserActivity($user["id"]);

            // Regenerate session ID on login to prevent fixation
            session_regenerate_id(true);
            $_SESSION["user"] = [
                "id" => $user["id"],
                "login" => $user["username"],
                "name" => $user["name"],
                "email" => $user["email"],
                "role" => $user["role"],
            ];
            header("Location: ./profile.php");
            exit();
        }
    } else {
        $error = "Invalid login or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="Login to your account - Secure authentication with email verification">
	<meta name="robots" content="noindex, nofollow">
	<meta name="theme-color" content="#8b5cf6">
	<title>Login - LoginPage System</title>
	<link rel="icon" type="image/png" sizes="32x32" href="../../public/images/logo.png">
	<link rel="apple-touch-icon" href="../../public/images/logo.png">
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo time(); ?>">
	<script src="../../public/js/toast.js" defer></script>
	<script src="../../public/js/form-utils.js" defer></script>
</head>
<body>
	<button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
	<img src="../../public/images/logo.png" alt="Logo" class="logo-website-top-left">
	<div class="logo-shadow"></div>
	<div class="container page">
		<div class="card">
			<h1>Login</h1>
			<?php if (!empty($error)): ?>
				<p class="alert error mt-3"><?php echo htmlspecialchars($error); ?></p>
			<?php endif; ?>
			<form method="post" action="" id="loginForm">
				<?php echo csrf_field(); ?>
				<label for="login">Username or Email</label>
				<input type="text" id="login" name="login" required autocomplete="username" placeholder="Enter your username or email">

				<label for="password">Password</label>
				<input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password">

				<div class="link-row centered mt-4">
					<button class="button primary" type="submit" id="loginBtn">Login</button>
					<a class="button" href="../../../index.php">Back to home</a>
				</div>
			</form>
			<p class="footer mt-6">
				Don't have an account? <a href="./register.php">Register here</a><br>
				Forgot your password? <a href="./forgot-password.php">Reset password</a><br>
				Didn't receive verification email? <a href="./resend-verification.php">Resend verification</a>
			</p>
		</div>
	</div>
	<div class="demo-warning">*This is a demo version of the website</div>
    <script>
    (function() {
        // Theme toggle
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

        // Show error as toast if present
        <?php if (!empty($error)): ?>
        window.addEventListener('DOMContentLoaded', function() {
            if (window.Toast) {
                Toast.error(<?php echo json_encode($error); ?>, 5000);
            }
        });
        <?php endif; ?>

        // Form validation and loading state
        window.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const loginInput = document.getElementById('login');
            const passwordInput = document.getElementById('password');

            if (!form || !window.FormUtils) return;

            // Real-time validation
            loginInput.addEventListener('blur', function() {
                const value = loginInput.value.trim();
                if (value.length === 0) {
                    FormUtils.showValidationFeedback(loginInput, false, '✗ Username or email is required');
                } else if (value.length < 3) {
                    FormUtils.showValidationFeedback(loginInput, false, '✗ At least 3 characters required');
                } else {
                    FormUtils.showValidationFeedback(loginInput, true, '✓ Valid');
                }
            });

            passwordInput.addEventListener('blur', function() {
                const value = passwordInput.value;
                if (value.length === 0) {
                    FormUtils.showValidationFeedback(passwordInput, false, '✗ Password is required');
                } else if (value.length < 6) {
                    FormUtils.showValidationFeedback(passwordInput, false, '✗ Password too short');
                } else {
                    FormUtils.showValidationFeedback(passwordInput, true, '✓ Valid');
                }
            });

            // Clear validation on input
            loginInput.addEventListener('input', function() {
                const feedback = loginInput.parentNode.querySelector('.validation-feedback');
                if (feedback) feedback.remove();
                loginInput.classList.remove('input-valid', 'input-invalid');
            });

            passwordInput.addEventListener('input', function() {
                const feedback = passwordInput.parentNode.querySelector('.validation-feedback');
                if (feedback) feedback.remove();
                passwordInput.classList.remove('input-valid', 'input-invalid');
            });

            // Prevent double submission
            FormUtils.preventDoubleSubmit(form);
        });
    })();
    </script>
</body>
</html>
