<?php
// src/app/controllers/twofactor.php
// TOTP challenge after password step

global $db;
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
require_once __DIR__ . '/../security/headers.php';
require_once __DIR__ . '/../security/totp.php';
require_once __DIR__ . '/../services/RateLimiter.php';
csrf_ensure_initialized();
apply_default_security_headers();
apply_sensitive_nocache();

$rateLimiter = new RateLimiter();

// Require pending 2FA session
$pendingId = $_SESSION['2fa_user_id'] ?? null;
$expiresAt = $_SESSION['2fa_expires_at'] ?? 0;
if (!$pendingId || time() > (int)$expiresAt) {
    header('Location: ./login.php');
    exit;
}

$user = getUserById((int)$pendingId);
if (!$user || empty($user['twofa_enabled']) || empty($user['totp_secret'])) {
    // No longer requires 2FA; fallback
    header('Location: ./login.php');
    exit;
}

function finalize_after_2fa_success(array $user, RateLimiter $rateLimiter): void {
    $rateLimiter->clearAttempts('login');
    updateLastActive($user['id']);
    updateUserActivity($user['id']);
    recordLoginEvent((int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => $user['id'],
        'login' => $user['username'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'totp_secret' => $user['totp_secret'],
        'twofa_enabled' => (int)$user['twofa_enabled'],
    ];
    $_SESSION['issued_at'] = time();
    unset($_SESSION['2fa_user_id'], $_SESSION['2fa_expires_at'], $_SESSION['2fa_context']);
    header('Location: ./profile.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require_post();
    $code = trim($_POST['code'] ?? '');

    if ($code === '') {
        $error = 'Enter your 6-digit code.';
    } else {
        if (totp_verify($user['totp_secret'], $code, 1)) {
            // Success: finalize login
            finalize_after_2fa_success($user, $rateLimiter);
        } else {
            // Try recovery codes as fallback
            if (verifyTwoFactorRecoveryCode((int)$user['id'], $code)) {
                finalize_after_2fa_success($user, $rateLimiter);
            } else {
                $error = 'Invalid authentication code.';
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
    <title>Sulfur • Two‑Factor Authentication</title>
    <link rel="icon" type="image/png" href="../../public/images/logo.png">
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo filemtime(__DIR__ . "/../../public/css/style.css"); ?>">
</head>
<body>
<?php $NAV_BASE='../../'; include __DIR__ . '/../../public/partials/navbar.php'; ?>
<div class="container page">
  <div class="card auth-card">
    <h1 class="auth-title">Two‑Factor Authentication</h1>
    <p class="auth-subtitle">Enter the 6‑digit code from your authenticator app</p>

    <?php if (!empty($error)): ?>
      <div class="alert error mt-3"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <?php echo csrf_field(); ?>
      <label for="code">Authentication code</label>
      <input type="text" id="code" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="000000" required>
      <div class="link-row centered mt-4">
        <button class="button primary" type="submit">Verify</button>
        <a class="button" href="./login.php">Cancel</a>
      </div>
    </form>
  </div>
</div>
<div class="demo-warning">*This is a demo version of the website</div>
</body>
</html>
