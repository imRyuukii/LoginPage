<?php
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
require_once '../security/csrf.php';
require_once __DIR__ . '/../security/headers.php';
require_once '../models/user-functions-db.php';
csrf_ensure_initialized();
apply_default_security_headers();
apply_sensitive_nocache();

// Allow only POST requests with CSRF token
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
csrf_require_post();

// Best-effort: mark this session as revoked in user_sessions
if (!empty($_SESSION['user']['id'] ?? null)) {
    revokeSessionBySessionId((int) $_SESSION['user']['id'], session_id());
}

// Delete user data from session and end session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

header('Location: /LoginPage/index.php');
exit;
