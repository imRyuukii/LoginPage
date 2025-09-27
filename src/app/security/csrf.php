<?php
// src/app/security/csrf.php

function csrf_ensure_initialized(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return; // require caller to start session
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function csrf_token(): string {
    return $_SESSION['csrf'] ?? '';
}

function csrf_field(): string {
    $token = htmlspecialchars(csrf_token());
    return '<input type="hidden" name="csrf" value="' . $token . '">';
}

function csrf_require_post(): void {
    // Only enforce on POST requests
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $sent = $_POST['csrf'] ?? '';
    $sess = $_SESSION['csrf'] ?? '';
    $ok = is_string($sent) && is_string($sess) && hash_equals($sess, $sent);
    if (!$ok) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}
