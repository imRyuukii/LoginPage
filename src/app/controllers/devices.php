<?php
// src/app/controllers/devices.php
// List and manage active login sessions ("devices") for the current user.

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

// Require logged-in user
if (empty($_SESSION['user']['id'])) {
    header('Location: ./login.php');
    exit;
}

$userId = (int) $_SESSION['user']['id'];

// Flash messages
$flashSuccess = $_SESSION['devices_success'] ?? '';
$flashError = $_SESSION['devices_error'] ?? '';
unset($_SESSION['devices_success'], $_SESSION['devices_error']);

// Handle POST actions (revoke sessions)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'revoke_one') {
        $rowId = filter_input(INPUT_POST, 'session_row_id', FILTER_VALIDATE_INT);
        if ($rowId && $rowId > 0) {
            if (revokeUserSessionById($userId, (int) $rowId)) {
                $_SESSION['devices_success'] = 'That device has been signed out.';
            } else {
                $_SESSION['devices_error'] = 'Unable to sign out that device.';
            }
        } else {
            $_SESSION['devices_error'] = 'Invalid device selection.';
        }
    } elseif ($action === 'revoke_others') {
        $currentSid = session_id();
        $revokedCount = 0;
        if ($currentSid !== '') {
            $revokedCount = revokeOtherSessionsForUser($userId, $currentSid);
        }
        if ($revokedCount > 0) {
            $_SESSION['devices_success'] = 'Signed out of ' . $revokedCount . ' other device' . ($revokedCount > 1 ? 's' : '') . '.';
        } else {
            $_SESSION['devices_success'] = 'There were no other active devices to sign out.';
        }
    }

    header('Location: /LoginPage/src/app/controllers/devices.php');
    exit;
}

// On GET: fetch active sessions
$sessions = getActiveSessionsForUser($userId);
$currentSessionId = session_id();

function devices_format_datetime(?string $ts): string
{
    if (!$ts) {
        return 'Unknown';
    }
    $t = strtotime($ts);
    if ($t === false) {
        return htmlspecialchars($ts, ENT_QUOTES, 'UTF-8');
    }
    return date('Y-m-d H:i', $t);
}

function devices_friendly_device(?string $userAgent): string
{
    $ua = (string) ($userAgent ?? '');
    if ($ua === '') {
        return 'Unknown device';
    }

    $os = 'Device';
    if (stripos($ua, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) {
        $os = 'macOS';
    } elseif (stripos($ua, 'Android') !== false) {
        $os = 'Android';
    } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
        $os = 'iOS';
    } elseif (stripos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }

    $browser = '';
    if (stripos($ua, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($ua, 'Edg/') !== false || stripos($ua, 'Edge') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'Chrome') !== false && stripos($ua, 'Chromium') === false && stripos($ua, 'Edg/') === false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) {
        $browser = 'Safari';
    }

    $label = $os;
    if ($browser !== '') {
        $label .= ' · ' . $browser;
    }

    return $label;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sulfur • Devices &amp; Sessions</title>
  <link rel="icon" type="image/png" href="../../public/images/logo-sulfur.png">
  <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo filemtime(__DIR__ . "/../../public/css/style.css"); ?>">
</head>
<body>
<?php $NAV_BASE='../../'; include __DIR__ . '/../../public/partials/navbar.php'; ?>
<div class="container page">
  <div class="card">
    <h1>Devices &amp; Sessions</h1>
    <p class="mt-1">Review where you're signed in and sign out of other devices.</p>

    <?php if (!empty($flashSuccess)): ?>
      <div class="alert success mt-3"><?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
      <div class="alert error mt-3"><?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (empty($sessions)): ?>
      <p class="mt-3">No active sessions found.</p>
    <?php else: ?>
      <div class="mt-3" style="overflow-x:auto;">
        <table class="table devices-table">
          <thead>
            <tr>
              <th>Device</th>
              <th>IP address</th>
              <th>Signed in</th>
              <th>Last active</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sessions as $row): ?>
              <?php
              $isCurrent = isset($row['session_id']) && ($row['session_id'] === $currentSessionId);
              $deviceLabel = devices_friendly_device($row['user_agent'] ?? '');
              $ip = $row['ip_address'] ?? '';
              $created = devices_format_datetime($row['created_at'] ?? null);
              $lastSeen = devices_format_datetime($row['last_seen_at'] ?? null);
              ?>
              <tr>
                <td><?php echo htmlspecialchars($deviceLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($ip !== '' ? $ip : 'Unknown', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($created, ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($lastSeen, ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <?php if ($isCurrent): ?>
                    <span style="font-size: 0.9em; font-weight: 900; color: var(--success);">This device</span>
                  <?php else: ?>
                    <span style="font-size: 0.9em; color: var(--muted);">Active</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($isCurrent): ?>
                    <span class="meta" style="font-size: 0.9em; color: var(--muted-text);">Current session</span>
                  <?php else: ?>
                    <form method="post" action="" style="display:inline; margin:0;">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="revoke_one">
                      <input type="hidden" name="session_row_id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                      <button class="button" type="submit" onclick="return confirm('Sign out this device?');">Sign out</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if (count($sessions) > 1): ?>
        <form method="post" action="" class="mt-3">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="revoke_others">
          <button class="button" type="submit" onclick="return confirm('Sign out of all other devices?');">Sign out of all other devices</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<div class="demo-warning">*This is a demo version of the website</div>
</body>
</html>
