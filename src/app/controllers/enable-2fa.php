<?php
// src/app/controllers/enable-2fa.php
// Setup Two-Factor Authentication for logged-in users

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
require_once __DIR__ . '/../security/session_guard.php';
require_once __DIR__ . '/../security/totp.php';
csrf_ensure_initialized();
apply_default_security_headers();
apply_sensitive_nocache();
session_enforce_password_rotation();
session_enforce_device_session();

if (!isset($_SESSION['user']['id'])) {
    header('Location: ./login.php');
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$user = getUserById($userId);
if (!$user) {
    header('Location: ./login.php');
    exit;
}

$error = '';
$success = '';

// If already enabled, just show status
if (!empty($user['twofa_enabled'])) {
    $success = 'Two-factor authentication is already enabled for your account.';
}

// Ensure a secret exists (but not enabled yet)
$secret = $user['totp_secret'] ?? '';
if (empty($secret)) {
    $secret = totp_base32_random(32);
    setUserTwoFactorSecret($userId, $secret);
}

$issuer = rawurlencode('LoginPage');
$label = rawurlencode($user['email'] ?? ($user['username'] ?? 'user'));
$otpauth = "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&period=30&digits=6";

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require_post();
    $code = trim($_POST['code'] ?? '');
    if ($code === '') {
        $error = 'Enter the 6-digit code from your authenticator app';
    } else {
        if (totp_verify($secret, $code, 1)) {
            if (enableUserTwoFactor($userId)) {
                $_SESSION['user']['twofa_enabled'] = 1;
                $_SESSION['user']['totp_secret'] = $secret;
                $success = 'Two-factor authentication has been enabled.';
            } else {
                $error = 'Failed to enable 2FA. Please try again.';
            }
        } else {
            $error = 'Invalid code. Make sure your device time is correct.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sulfur • Enable Two‑Factor Authentication</title>
  <link rel="icon" type="image/png" href="../../public/images/logo-sulfur.png">
  <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo filemtime(__DIR__ . "/../../public/css/style.css"); ?>">
</head>
<body>
<?php $NAV_BASE='../../'; include __DIR__ . '/../../public/partials/navbar.php'; ?>
<div class="container page">
  <div class="card auth-card">
    <h1 class="auth-title">Enable Two‑Factor Authentication</h1>
    <p class="auth-subtitle">Use an authenticator app (e.g., Google Authenticator, Authy)</p>

    <?php if (!empty($error)): ?><div class="alert error mt-3"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert success mt-3"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <?php if (empty($user['twofa_enabled'])): ?>
      <div class="mt-3">
        <h3>Step 1: Add account</h3>
        <p>Manually add this key in your authenticator app:</p>
        <div class="meta"><div class="value" style="font-family: monospace; font-size: 1.1em; word-break: break-all;"><?php echo htmlspecialchars($secret); ?></div></div>
        <p class="mt-2">Account name: <?php echo htmlspecialchars($user['email'] ?? $user['username']); ?> — Issuer: LoginPage</p>
        <p class="mt-2">(Optional) QR URL you can use in any QR generator:</p>
        <div class="meta"><div class="value" style="font-family: monospace; word-break: break-all; font-size: 0.92em;"><?php echo htmlspecialchars($otpauth); ?></div></div>
      </div>
      <div class="mt-4">
        <h3>Step 2: Verify code</h3>
        <form method="post" action="">
          <?php echo csrf_field(); ?>
          <label for="code">6‑digit code</label>
          <input type="text" id="code" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" required>
          <div class="link-row centered mt-4">
            <button class="button primary" type="submit">Enable 2FA</button>
            <a class="button" href="./profile.php">Cancel</a>
          </div>
        </form>
      </div>
    <?php else: ?>
      <p class="mt-3">2FA is already enabled.</p>
      <div class="link-row centered mt-4"><a class="button" href="./disable-2fa.php">Disable 2FA</a></div>
    <?php endif; ?>
  </div>
</div>
<div class="demo-warning">*This is a demo version of the website</div>
</body>
</html>
