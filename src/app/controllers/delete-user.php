<?php
// src/app/controllers/delete-user.php
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
csrf_ensure_initialized();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Require admin role
csrf_require_post();

$currentUser = $_SESSION['user'] ?? null;
$role = $currentUser['role'] ?? (($currentUser['login'] ?? '') === 'admin' ? 'admin' : 'user');
if ($role !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
if (!$userId) {
    header('Location: ./profile.php?error=' . urlencode('Invalid user id'));
    exit;
}

// Prevent deleting yourself
if (!empty($currentUser['id']) && (int)$currentUser['id'] === (int)$userId) {
    header('Location: ./profile.php?error=' . urlencode("You can't delete your own account"));
    exit;
}

if (deleteUser((int)$userId)) {
    header('Location: ./profile.php?msg=deleted');
    exit;
} else {
    header('Location: ./profile.php?error=' . urlencode('Delete failed'));
    exit;
}
