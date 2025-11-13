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
require_once __DIR__ . "/../security/headers.php";
require_once __DIR__ . "/../services/RateLimiter.php"; // NEW: Rate limiting
csrf_ensure_initialized();
apply_default_security_headers();
apply_sensitive_nocache();

$error = "";
$rateLimiter = new RateLimiter();

// Check rate limiting BEFORE processing login
if ($rateLimiter->isBlocked("login")) {
    $timeRemaining = $rateLimiter->getBlockedTimeRemaining("login");
    $timeFormatted = RateLimiter::formatTimeRemaining($timeRemaining);
    $error = "Too many login attempts. Please try again in {$timeFormatted}.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($error)) {
    csrf_require_post();
    $login = isset($_POST["login"]) ? trim($_POST["login"]) : "";
    $password = isset($_POST["password"]) ? trim($_POST["password"]) : "";

    $user = loginUser($login, $password);

    if ($user) {
        // Check if email is verified
        if (!$user["email_verified"]) {
            $error = "Please verify your email address before logging in. Check your email for the verification link.";
            $rateLimiter->recordAttempt("login");
        } else {
            // If 2FA is enabled for this user, require TOTP before full login
            if (!empty($user['twofa_enabled']) && !empty($user['totp_secret'])) {
                // Stash pending login state (expires after 5 minutes)
                $_SESSION['2fa_user_id'] = (int)$user['id'];
                $_SESSION['2fa_expires_at'] = time() + 300;
                $_SESSION['2fa_context'] = [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ];
                // Do not clear rate limits yet; only after successful TOTP
                header('Location: ./twofactor.php');
                exit();
            }

            // SUCCESS with no 2FA: Clear rate limits
            $rateLimiter->clearAttempts("login");

            // Update last seen timestamps
            updateLastActive($user["id"]);
            updateUserActivity($user["id"]);

            // Record successful login event (non-blocking)
            recordLoginEvent(
                (int)$user["id"],
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            // Regenerate session ID on login to prevent fixation
            session_regenerate_id(true);
            $_SESSION["user"] = [
                "id" => $user["id"],
                "login" => $user["username"],
                "name" => $user["name"],
                "email" => $user["email"],
                "role" => $user["role"],
                'totp_secret' => $user['totp_secret'] ?? null,
                'twofa_enabled' => (int)($user['twofa_enabled'] ?? 0),
            ];
            $_SESSION['issued_at'] = time();
            header("Location: ./profile.php");
            exit();
        }
    } else {
        // Failed login - record attempt
        $rateLimiter->recordAttempt("login");

        // If this attempt triggered a block, show block message immediately
        if ($rateLimiter->isBlocked("login")) {
            $timeRemaining = $rateLimiter->getBlockedTimeRemaining("login");
            $error =
                "Too many login attempts. Please try again in " .
                RateLimiter::formatTimeRemaining($timeRemaining) .
                ".";
        } else {
            // Show how many attempts remaining
            $remaining = $rateLimiter->getRemainingAttempts("login");
            if ($remaining["remaining"] > 0 && $remaining["remaining"] <= 2) {
                $error = "Invalid login or password. {$remaining["remaining"]} attempt(s) remaining before lockout.";
            } else {
                $error = "Invalid login or password.";
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
	<meta name="description" content="Login to your account - Secure authentication with email verification">
	<meta name="robots" content="noindex, nofollow">
	<meta name="theme-color" content="#124e66">
	<title>Sulfur • Login</title>
	<link rel="icon" type="image/png" sizes="32x32" href="/LoginPage/src/public/images/logo.png">
	<link rel="apple-touch-icon" href="/LoginPage/src/public/images/logo.png">
    <link rel="stylesheet" href="/LoginPage/src/public/css/style.css?v=<?php echo filemtime(__DIR__ . "/../../public/css/style.css"); ?>">
	<script src="/LoginPage/src/public/js/toast.js?v=<?php echo filemtime(__DIR__ . "/../../public/js/toast.js"); ?>" defer></script>
	<script src="/LoginPage/src/public/js/form-utils.js?v=<?php echo filemtime(__DIR__ . "/../../public/js/form-utils.js"); ?>" defer></script>
	<script src="/LoginPage/src/public/js/auth-ui.js?v=<?php echo filemtime(__DIR__ . "/../../public/js/auth-ui.js"); ?>" defer></script>
</head>
<body>
	<?php $NAV_BASE='../../'; include __DIR__ . '/../../public/partials/navbar.php'; ?>
	<div class="container page">
		<div class="card auth-card">
			<h1 class="auth-title">Welcome back</h1>
			<p class="auth-subtitle">Log in to your account to continue</p>
			<?php if (!empty($error)): ?>
				<p class="alert error mt-3"><?php echo htmlspecialchars($error); ?></p>
			<?php endif; ?>

			<div class="auth-layout">
				<div>
					<form method="post" action="" id="loginForm">
						<?php echo csrf_field(); ?>
						<label for="login">Username or Email</label>
						<div class="input-wrap">
							<input type="text" id="login" name="login" required autocomplete="username" placeholder="Enter your username or email" <?php echo $rateLimiter->isBlocked(
        "login",
    )
        ? "disabled"
        : ""; ?>>
						</div>

						<label for="password">Password</label>
						<div class="input-wrap">
							<input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password" <?php echo $rateLimiter->isBlocked(
        "login",
    )
        ? "disabled"
        : ""; ?>>
							<button type="button" class="input-action" id="togglePassword" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
						</div>

						<div class="form-actions mt-4">
							<button class="button primary" type="submit" id="loginBtn" <?php echo $rateLimiter->isBlocked(
         "login",
     )
         ? "disabled"
         : ""; ?>>
								<?php echo $rateLimiter->isBlocked("login") ? "Locked" : "Login"; ?>
							</button>
							<a class="button" href="../../../index.php">Back to home</a>
						</div>
					</form>
					<p class="footer mt-6">
						Don't have an account? <a href="./register.php">Register here</a><br>
						Forgot your password? <a href="./forgot-password.php">Reset password</a><br>
						Didn't receive verification email? <a href="./resend-verification.php">Resend verification</a>
					</p>
				</div>
				<div class="auth-side">
					<h3><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Secure by design</h3>
					<div class="auth-tips">
						<div class="tip"><i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i> Verified emails only</div>
						<div class="tip"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Rate‑limited logins</div>
						<div class="tip"><i class="fa-solid fa-lock" aria-hidden="true"></i> Protected sessions</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="demo-warning">*This is a demo version of the website</div>
</body>
</html>
