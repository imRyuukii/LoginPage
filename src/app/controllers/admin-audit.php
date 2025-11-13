<?php
// src/app/controllers/admin-audit.php
// View recent admin audit events (admin-only)

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

if (!isset($_SESSION['user'])) {
    header('Location: ./login.php');
    exit;
}
$u = $_SESSION['user'];
$role = $u['role'] ?? (($u['login'] ?? '') === 'admin' ? 'admin' : 'user');
if ($role !== 'admin') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Fetch recent events
$rows = [];
try {
    global $db;
    $stmt = $db->query('SELECT id, actor_id, action, target_user_id, created_at, meta FROM admin_audit ORDER BY id DESC LIMIT 200');
    $rows = $stmt->fetchAll();
} catch (Exception $e) {
    $rows = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sulfur • Admin Audit Log</title>
  <link rel="icon" type="image/png" href="../../public/images/logo.png">
  <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo filemtime(__DIR__ . "/../../public/css/style.css"); ?>">
</head>
<body>
<?php $NAV_BASE='../../'; include __DIR__ . '/../../public/partials/navbar.php'; ?>
<div class="container page">
  <div class="card">
    <h1>Admin Audit Log</h1>
    <?php if (empty($rows)): ?>
      <p class="mt-3">No events.</p>
    <?php else: ?>
      <div class="mt-3" style="overflow-x:auto;">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>When</th>
              <th>Actor</th>
              <th>Action</th>
              <th>Target</th>
              <th>Meta</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?php echo (int)$r['id']; ?></td>
                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                <td><?php echo (int)$r['actor_id']; ?></td>
                <td><?php echo htmlspecialchars($r['action']); ?></td>
                <td><?php echo htmlspecialchars((string)($r['target_user_id'] ?? '')); ?></td>
                <td><code style="font-size: 12px;"><?php echo htmlspecialchars((string)($r['meta'] ?? '')); ?></code></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<div class="demo-warning">*This is a demo version of the website</div>
</body>
</html>
