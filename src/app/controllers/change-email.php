<?php
// src/app/controllers/change-email.php
// Logged-in users can request to change their email; requires confirmation link sent to new address

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
require_once __DIR__ . '/../services/EmailServiceSMTP.php';
csrf_ensure_initialized();
apply_default_security_headers();
apply_sensitive_nocache();

if (!isset($_SESSION['user']['id'])) {
    header('Location: ./login.php');
    exit;
}

$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require_post();
    $newEmail = trim($_POST['new_email'] ?? '');
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        try {
            $userId = (int)$_SESSION['user']['id'];
            $res = createEmailChangeRequest($userId, $newEmail);
            if ($res['success']) {
                $token = $res['token'];
                $emailService = new EmailServiceSMTP();
                // Apply SMTP env if available (same pattern as register)
                $get = function($k) {
                    $v = getenv($k); if ($v === false || $v === '' || $v === null) { $v = $_ENV[$k] ?? null; }
                    if ($v === null || $v === '') { $v = $_SERVER[$k] ?? null; }
                    return $v !== '' ? $v : null;
                };
                $smtpHost = $get('SMTP_HOST');
                $smtpPort = $get('SMTP_PORT');
                $smtpUser = $get('SMTP_USERNAME');
                $smtpPass = $get('SMTP_PASSWORD');
                $smtpFrom = $get('SMTP_FROM_EMAIL') ?: $smtpUser;
                if ($smtpHost && $smtpPort && $smtpUser && $smtpPass && $smtpFrom) {
                    $emailService->enableRealEmails($smtpHost, (int)$smtpPort, $smtpUser, $smtpPass, $smtpFrom);
                }
                $ok = $emailService->sendEmailChangeEmail($newEmail, $_SESSION['user']['name'] ?? 'User', $token);
                if ($ok) {
                    $success = 'We sent a confirmation link to ' . htmlspecialchars($newEmail) . '. Please click it to complete the change.';
                } else {
                    $error = 'Could not send confirmation email. Try again later.';
                }
            } else {
                $error = $res['message'] ?? 'Failed to start email change.';
            }
        } catch (Exception $e) {
            error_log('change-email failed: ' . $e->getMessage());
            $error = 'Unexpected error.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sulfur • Change Email</title>
  <link rel="icon" type="image/png" href="../../public/images/logo.png">
  <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo filemtime(__DIR__ . "/../../public/css/style.css"); ?>">
</head>
<body>
<?php $NAV_BASE='../../'; include __DIR__ . '/../../public/partials/navbar.php'; ?>
<div class="container page">
  <div class="card auth-card">
    <h1 class="auth-title">Change Email</h1>
    <?php if (!empty($error)): ?><div class="alert error mt-3"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert success mt-3"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <form method="post" action="">
      <?php echo csrf_field(); ?>
      <label for="new_email">New email</label>
      <input type="email" id="new_email" name="new_email" required>
      <div class="link-row centered mt-4">
        <button class="button primary" type="submit">Send confirmation link</button>
        <a class="button" href="./profile.php">Cancel</a>
      </div>
    </form>
  </div>
</div>
<div class="demo-warning">*This is a demo version of the website</div>
</body>
</html>
