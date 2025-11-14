<?php
// src/public/api/webauthn/begin-register.php
// Returns WebAuthn registration (creation) options for the logged-in user.

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

require_once __DIR__ . '/../../../app/security/headers.php';
require_once __DIR__ . '/../../../app/security/csrf.php';
require_once __DIR__ . '/../../../app/models/user-functions-db.php';

csrf_ensure_initialized();
apply_default_security_headers();
apply_sensitive_nocache();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Optional CSRF check for POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_require_post();
}

$user = $_SESSION['user'];
$userId = (string)$user['id'];
$username = (string)($user['login'] ?? $user['username'] ?? ('user'.$userId));
$displayName = (string)($user['name'] ?? $username);

// Load WebAuthn library (lbuchs/WebAuthn) from local copy
$libPath = __DIR__ . '/../../../app/security/WebAuthn-master/src';
set_include_path($libPath . PATH_SEPARATOR . get_include_path());
require_once $libPath . '/WebAuthn.php';

use lbuchs\WebAuthn\WebAuthn;

$rpId   = 'app.theloginpage.me';
$rpName = 'Sulfur';

try {
    // useBase64UrlEncoding=true so binary fields become base64url strings in JSON
    $webauthn = new WebAuthn($rpName, $rpId, null, true);

    // In future we could pass existing credential IDs to exclude; for now, allow first registration
    $createArgs = $webauthn->getCreateArgs($userId, $username, $displayName);

    // Persist challenge (binary) in session for later verification
    $challenge = $webauthn->getChallenge();
    $_SESSION['webauthn_register_challenge'] = $challenge->getBinaryString();

    echo json_encode($createArgs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('webauthn begin-register failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to start WebAuthn registration']);
}
