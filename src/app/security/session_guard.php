<?php
// src/app/security/session_guard.php
// Enforce re-login if password was changed after session issuance

if (!function_exists('session_enforce_password_rotation')) {
    function session_enforce_password_rotation(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        if (empty($_SESSION['user']['id'])) return;
        $issued = (int)($_SESSION['issued_at'] ?? 0);
        if ($issued <= 0) return;
        try {
            require_once __DIR__ . '/../models/user-functions-db.php';
            $u = getUserById((int)$_SESSION['user']['id']);
            if (!$u) return;
            if (!empty($u['password_reset_at'])) {
                $rotated = strtotime($u['password_reset_at']);
                if ($rotated !== false && $rotated > $issued) {
                    // Invalidate session and force login
                    $_SESSION = [];
                    if (ini_get("session.use_cookies")) {
                        $params = session_get_cookie_params();
                        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
                    }
                    session_destroy();
                    header('Location: /LoginPage/src/app/controllers/login.php?redirect=expired');
                    exit;
                }
            }
        } catch (Throwable $e) {
            // fail open
        }
    }
}
