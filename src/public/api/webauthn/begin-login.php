<?php
// src/public/api/webauthn/begin-login.php
// Returns WebAuthn assertion (get) options for a given username/email.

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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// CSRF from JSON body
$sentCsrf = $data['csrf'] ?? '';
$sessCsrf = $_SESSION['csrf'] ?? '';
$ok = is_string($sentCsrf) && is_string($sessCsrf) && hash_equals($sessCsrf, $sentCsrf);
if (!$ok) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$login = isset($data['login']) ? trim((string)$data['login']) : '';
if ($login === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Login identifier is required']);
    exit;
}

// Find user by username or email
$user = findUserByUsername($login);
if (!$user) {
    $user = findUserByEmail($login);
}

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'No account found for that username or email']);
    exit;
}

// Load WebAuthn credentials for this user
global $db;
$stmt = $db->query('SELECT credential_id, sign_count FROM webauthn_credentials WHERE user_id = ?', [(int)$user['id']]);
$rows = $stmt->fetchAll();
if (!$rows) {
    http_response_code(404);
    echo json_encode(['error' => 'No passkeys registered for this account']);
    exit;
}

// Load WebAuthn library
$libPath = __DIR__ . '/../../../app/security/WebAuthn-master/src';
set_include_path($libPath . PATH_SEPARATOR . get_include_path());
require_once $libPath . '/WebAuthn.php';

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\Binary\ByteBuffer;

$rpId   = 'app.theloginpage.me';
$rpName = 'Sulfur';

try {
    $webauthn = new WebAuthn($rpName, $rpId, null, true);

    // Build list of credential IDs as ByteBuffers
    $credIds = [];
    foreach ($rows as $row) {
        $credIds[] = new ByteBuffer($row['credential_id']);
    }

    $getArgs = $webauthn->getGetArgs($credIds, 20, true, true, true, true, true, 'preferred');

    // Save challenge and user context for finish step
    $challenge = $webauthn->getChallenge();
    $_SESSION['webauthn_login_challenge'] = $challenge->getBinaryString();
    $_SESSION['webauthn_login_user_id'] = (int)$user['id'];

    echo json_encode($getArgs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('webauthn begin-login failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to start WebAuthn login']);
}
