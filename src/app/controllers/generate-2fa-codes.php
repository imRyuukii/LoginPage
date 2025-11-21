<?php
// src/app/controllers/generate-2fa-codes.php
// Generate and display TOTP recovery codes (shows once)

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
if (!$user || empty($user['twofa_enabled'])) {
    header('Location: ./enable-2fa.php');
    exit;
}

$codes = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require_post();
    $codes = generateTwoFactorRecoveryCodes($userId, 10);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sulfur • 2FA Recovery Codes</title>
  <link rel="icon" type="image/png" href="../../public/images/logo-sulfur.png">
  <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo filemtime(__DIR__ . "/../../public/css/style.css"); ?>">
</head>
<body>
<?php $NAV_BASE='../../'; include __DIR__ . '/../../public/partials/navbar.php'; ?>
<div class="container page">
  <div class="card auth-card">
    <h1 class="auth-title">2FA Recovery Codes</h1>
    <p class="auth-subtitle">Keep these codes somewhere safe. Each code can be used once.</p>

    <?php if (empty($codes)): ?>
      <form method="post">
        <?php echo csrf_field(); ?>
        <div class="alert info">Generating new codes will invalidate any existing unused codes.</div>
        <div class="link-row centered mt-4">
          <button class="button primary" type="submit">Generate new codes</button>
          <a class="button" href="./profile.php">Back to profile</a>
        </div>
      </form>
    <?php else: ?>
      <div class="meta">
        <ul style="list-style:none; padding:0; margin:0; text-align:center;">
          <?php foreach ($codes as $c): ?>
            <li style="font-family: monospace; font-weight: 900; font-size: 1.1em; margin: 6px 0;"><?php echo htmlspecialchars($c); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <p class="mt-3" style="text-align:center;">Store these codes securely. You won’t be able to see them again.</p>
      <div class="link-row centered mt-4"><a class="button" href="./profile.php">Done</a></div>
    <?php endif; ?>
  </div>
</div>
<div class="demo-warning">*This is a demo version of the website</div>
</body>
</html>
