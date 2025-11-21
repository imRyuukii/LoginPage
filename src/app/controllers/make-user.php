<?php
// src/app/controllers/make-user.php
// Harden session cookie and start session
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
session_enforce_password_rotation();
session_enforce_device_session();
apply_sensitive_nocache();

// Only allow POST requests
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Validate CSRF
csrf_require_post();

// Require admin role
$currentUser = $_SESSION['user'] ?? null;
$role = $currentUser['role'] ?? (($currentUser['login'] ?? '') === 'admin' ? 'admin' : 'user');
if ($role !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}
if (empty($currentUser['twofa_enabled'])) {
    header('Location: /LoginPage/src/app/controllers/enable-2fa.php');
    exit;
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
if (!$userId) {
    header('Location: /LoginPage/src/app/controllers/profile.php?error=' . urlencode('Invalid user id'));
    exit;
}

// Prevent demoting yourself from this UI path
if (!empty($currentUser['id']) && (int)$currentUser['id'] === (int)$userId) {
    header('Location: /LoginPage/src/app/controllers/profile.php?error=' . urlencode("You can't change your own role here"));
    exit;
}

if (updateUserRole((int)$userId, 'user')) {
    recordAdminAudit((int)$currentUser['id'], 'make_user', (int)$userId, [ 'ip' => $_SERVER['REMOTE_ADDR'] ?? null ]);
    header('Location: /LoginPage/src/app/controllers/profile.php?msg=demoted');
    exit;
} else {
    header('Location: /LoginPage/src/app/controllers/profile.php?error=' . urlencode('Demote failed'));
    exit;
}
