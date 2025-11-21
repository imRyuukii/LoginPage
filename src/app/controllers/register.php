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
require_once "../services/EmailService.php";
require_once __DIR__ . "/../services/RateLimiter.php";
csrf_ensure_initialized();
apply_default_security_headers();
apply_sensitive_nocache();

$error = "";
$success = "";

$rateLimiter = new RateLimiter();

if ($rateLimiter->isBlocked("register")) {
    $timeRemaining = $rateLimiter->getBlockedTimeRemaining("register");
    $error =
        "Too many registration attempts. Please try again in " .
        RateLimiter::formatTimeRemaining($timeRemaining) .
        ".";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($error)) {
    csrf_require_post();
    $username = isset($_POST["username"]) ? trim($_POST["username"]) : "";
    $password = isset($_POST["password"]) ? trim($_POST["password"]) : "";
    $confirmPassword = isset($_POST["confirm_password"])
        ? trim($_POST["confirm_password"])
        : "";
    $name = isset($_POST["name"]) ? trim($_POST["name"]) : "";
    $email = isset($_POST["email"]) ? trim($_POST["email"]) : "";

    if ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
        $rateLimiter->recordAttempt("register");
    } else {
        $result = registerUser($username, $password, $name, $email);
        if ($result["success"]) {
            $rateLimiter->clearAttempts("register");
            // Get the newly created user to send verification email
            $newUser = findUserByEmail($email);

            if ($newUser) {
                try {
                    // Create verification token
                    $token = createEmailVerificationToken($newUser["id"]);

                    // Use the SMTP version instead (configured in EmailServiceSMTP::__construct)
                    require_once "../services/EmailServiceSMTP.php";
                    $emailService = new EmailServiceSMTP();

                    // Send verification email using Gmail SMTP or local fallback as configured
                    $emailSent = $emailService->sendVerificationEmail(
                        $email,
                        $name,
                        $token,
                    );

                    if ($emailSent) {
                        $success =
                            "Registration successful! Please check your email (" .
                            htmlspecialchars($email) .
                            ") for a verification link to activate your account.";
                    } else {
                        $success =
                            'Registration successful, but we couldn\'t send the verification email. Please contact support.';
                    }
                } catch (Exception $e) {
                    error_log(
                        "Email verification setup failed: " . $e->getMessage(),
                    );
                    $success =
                        "Registration successful, but there was an issue with email verification. Please contact support.";
                }
            } else {
                $success =
                    "Registration successful! Please contact support to verify your email.";
            }
        } else {
            $error = $result["message"];
            $rateLimiter->recordAttempt("register");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create your account - Secure registration with email verification">
    <meta name="robots" content="noindex, nofollow">
    	<meta name="theme-color" content="#124e66">
    <title>Sulfur • Register</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../../public/images/logo-sulfur.png">
    <link rel="apple-touch-icon" href="../../public/images/logo-sulfur.png">
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo filemtime(__DIR__ . "/../../public/css/style.css"); ?>">
    <script src="../../public/js/toast.js?v=<?php echo filemtime(__DIR__ . "/../../public/js/toast.js"); ?>" defer></script>
    <script src="../../public/js/form-utils.js?v=<?php echo filemtime(__DIR__ . "/../../public/js/form-utils.js"); ?>" defer></script>
    <script src="../../public/js/auth-ui.js?v=<?php echo filemtime(__DIR__ . "/../../public/js/auth-ui.js"); ?>" defer></script>
</head>
<body>
    <?php $NAV_BASE='../../'; include __DIR__ . '/../../public/partials/navbar.php'; ?>
    <div class="container page">
        <div class="card auth-card">
            <h1 class="auth-title">Create your account</h1>
            <p class="auth-subtitle">Join in a minute — verify email to sign in</p>
            <?php if (!empty($error)): ?>
                <p class="alert error mt-3"><?php echo htmlspecialchars(
                    $error,
                ); ?></p>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <p class="alert success mt-3"><?php echo htmlspecialchars(
                    $success,
                ); ?></p>
            <?php endif; ?>
            <div class="auth-layout">
              <div>
                <form method="post" action="">
                    <?php echo csrf_field(); ?>
                    <label for="username">Username</label>
                    <div class="input-wrap">
                      <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars(
                        $_POST["username"] ?? "",
                      ); ?>">
                    </div>

                    <label for="name">Full Name</label>
                    <div class="input-wrap">
                      <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars(
                        $_POST["name"] ?? "",
                      ); ?>">
                    </div>

                    <label for="email">Email</label>
                    <div class="input-wrap">
                      <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars(
                        $_POST["email"] ?? "",
                      ); ?>">
                    </div>

                    <label for="password">Password</label>
                    <div class="input-wrap">
                      <input type="password" id="password" name="password" required minlength="8">
                      <button type="button" class="input-action" id="togglePasswordReg" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                    </div>

                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-wrap">
                      <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                      <button type="button" class="input-action" id="toggleConfirmReg" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                    </div>

                    <div class="form-actions mt-4">
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
              <div class="auth-side">
                <h3><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Quick tips</h3>
                <div class="auth-tips">
                  <div class="tip"><i class="fa-solid fa-key" aria-hidden="true"></i> Use 8+ chars with upper/lowercase and a number</div>
                  <div class="tip"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Check spam for the verification email</div>
                  <div class="tip"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> You must verify email before login</div>
                </div>
              </div>
            </div>
        </div>
    </div>
    <div class="demo-warning">*This is a demo version of the website</div>
</body>
</html>
