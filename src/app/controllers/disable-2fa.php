<?php
// src/app/controllers/disable-2fa.php
// Disable 2FA for logged-in users (requires current TOTP for safety)

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
csrf_ensure_initialized();
apply_default_security_headers();
apply_sensitive_nocache();

if (!isset($_SESSION['user']['id'])) {
    header('Location: ./login.php');
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$user = getUserById($userId);
if (!$user || empty($user['twofa_enabled']) || empty($user['totp_secret'])) {
    header('Location: ./profile.php');
    exit;
}

$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require_post();
    $code = trim($_POST['code'] ?? '');
    if ($code === '') {
        $error = 'Enter your 6-digit code to disable 2FA';
    } else if (!totp_verify($user['totp_secret'], $code, 1)) {
        $error = 'Invalid code.';
    } else {
        if (disableUserTwoFactor($userId)) {
            $_SESSION['user']['twofa_enabled'] = 0;
            $_SESSION['user']['totp_secret'] = null;
            $success = 'Two-factor authentication has been disabled.';
        } else {
            $error = 'Failed to disable 2FA. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sulfur • Disable Two‑Factor Authentication</title>
  <link rel="icon" type="image/png" href="../../public/images/logo.png">
  <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo filemtime(__DIR__ . "/../../public/css/style.css"); ?>">
</head>
<body>
<?php $NAV_BASE='../../'; include __DIR__ . '/../../public/partials/navbar.php'; ?>
<div class="container page">
  <div class="card auth-card">
    <h1 class="auth-title">Disable Two‑Factor Authentication</h1>

    <?php if (!empty($error)): ?><div class="alert error mt-3"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert success mt-3"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <?php if (empty($success)): ?>
      <p class="mt-3">For your security, enter a current code from your authenticator app to confirm.</p>
      <form method="post">
        <?php echo csrf_field(); ?>
        <label for="code">6‑digit code</label>
        <input type="text" id="code" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" required>
        <div class="link-row centered mt-4">
          <button class="button" type="submit">Disable 2FA</button>
          <a class="button" href="./profile.php">Cancel</a>
        </div>
      </form>
    <?php else: ?>
      <div class="link-row centered mt-4"><a class="button" href="./profile.php">Back to profile</a></div>
    <?php endif; ?>
  </div>
</div>
<div class="demo-warning">*This is a demo version of the website</div>
</body>
</html>
