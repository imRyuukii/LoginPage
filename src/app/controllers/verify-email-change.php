<?php
// src/app/controllers/verify-email-change.php

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
require_once __DIR__ . '/../security/headers.php';
apply_default_security_headers();
apply_sensitive_nocache();

$error = '';
$success = '';

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
if ($token === '') {
    $error = 'No token provided.';
} else {
    $res = verifyEmailChangeTokenAndApply($token);
    if ($res['success']) {
        // Update session email if this is the same user
        if (!empty($_SESSION['user']['id']) && (int)$_SESSION['user']['id'] === (int)$res['user_id']) {
            $_SESSION['user']['email'] = $res['email'];
        }
        $success = 'Email address updated successfully.';
    } else {
        $error = $res['message'] ?? 'Verification failed.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sulfur • Confirm Email Change</title>
  <link rel="icon" type="image/png" href="../../public/images/logo.png">
  <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo filemtime(__DIR__ . "/../../public/css/style.css"); ?>">
</head>
<body>
<?php $NAV_BASE='../../'; include __DIR__ . '/../../public/partials/navbar.php'; ?>
<div class="container page">
  <div class="card">
    <h1>Confirm Email Change</h1>
    <?php if (!empty($error)): ?><div class="alert error mt-3"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert success mt-3"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <div class="link-row centered mt-4">
      <a class="button" href="./profile.php">Back to profile</a>
      <a class="button" href="./login.php">Go to login</a>
    </div>
  </div>
</div>
<div class="demo-warning">*This is a demo version of the website</div>
</body>
</html>
