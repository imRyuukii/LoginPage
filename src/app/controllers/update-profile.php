<?php
// src/app/controllers/update-profile.php
// Handle profile updates (name, username)

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

require_once '../security/csrf.php';
require_once __DIR__ . '/../security/headers.php';
require_once '../models/user-functions-db.php';
csrf_ensure_initialized();
apply_default_security_headers();
apply_sensitive_nocache();
csrf_require_post();

if (!isset($_SESSION['user']['id'])) {
    header('Location: /LoginPage/src/app/controllers/login.php');
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
$username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';

$result = updateUserProfile($userId, $username, $name);

if ($result['success']) {
    // Refresh session values that UI uses
    $_SESSION['user']['name'] = $name;
    $_SESSION['user']['login'] = $username; // legacy key used around the app
    // Also update canonical username field in session if present
    $_SESSION['user']['username'] = $username;
    $_SESSION['profile_success'] = $result['message'];
} else {
    $_SESSION['profile_error'] = $result['message'];
}

header('Location: profile.php');
exit;