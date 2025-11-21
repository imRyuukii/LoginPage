<?php
// src/app/controllers/change-password.php
// Handle password change for logged-in user

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
require_once __DIR__ . '/../security/session_guard.php';
require_once '../models/user-functions-db.php';
csrf_ensure_initialized();
apply_default_security_headers();
apply_sensitive_nocache();
session_enforce_password_rotation();
session_enforce_device_session();
csrf_require_post();

if (!isset($_SESSION['user']['id'])) {
    header('Location: /LoginPage/src/app/controllers/login.php');
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$currentPassword = isset($_POST['current_password']) ? (string)$_POST['current_password'] : '';
$newPassword = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
$confirm = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

if ($newPassword !== $confirm) {
    $_SESSION['password_error'] = 'Passwords do not match';
    header('Location: profile.php');
    exit;
}

$result = changeUserPassword($userId, $currentPassword, $newPassword);

if ($result['success']) {
    $_SESSION['password_success'] = $result['message'];
} else {
    $_SESSION['password_error'] = $result['message'];
}

header('Location: profile.php');
exit;