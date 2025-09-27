<?php
// src/public/api/users/last-activity.php
// Returns last activity labels for all users (admin-only)

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

require_once '../../app/models/user-functions-db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Require logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Require admin role (with same fallback as elsewhere)
$u = $_SESSION['user'];
$role = $u['role'] ?? (($u['login'] ?? '') === 'admin' ? 'admin' : 'user');
if ($role !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// If client passes a comma-separated list of ids, restrict to those
$idsParam = isset($_GET['ids']) ? trim((string)$_GET['ids']) : '';
if ($idsParam !== '') {
    $ids = array_filter(array_map('intval', explode(',', $idsParam)), function($v){ return $v > 0; });
    $users = getUsersByIds($ids);
} else {
    $users = getAllUsers();
}
$out = [];
foreach ($users as $usr) {
    try {
        $label = getLastActiveFormatted($usr['last_active'] ?? null, $usr['last_activity'] ?? null);
    } catch (Throwable $e) {
        $label = 'Unknown';
    }
    $out[] = [
        'id' => (int)($usr['id'] ?? 0),
        'username' => $usr['username'] ?? '',
        'last_active' => $usr['last_active'] ?? null,
        'last_activity' => $usr['last_activity'] ?? null,
'last_active_text' => $label,
        'online' => (function() use ($usr) {
            $la = $usr['last_active'] ?? null;
            $lact = $usr['last_activity'] ?? null;
            $time = null;
            if ($la && $lact) {
                $time = (strtotime($lact) >= strtotime($la)) ? $lact : $la;
            } else {
                $time = $lact ?: $la;
            }
            if (!$time) return false;
            try { $last = new DateTime($time); $now = new DateTime(); } catch (Throwable $e) { return false; }
            $diff = $now->getTimestamp() - $last->getTimestamp();
            return $diff <= 120; // same threshold as getLastActiveFormatted
        })(),
    ];
}

echo json_encode($out);
