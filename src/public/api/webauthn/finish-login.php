<?php
// src/public/api/webauthn/finish-login.php
// Verifies WebAuthn assertion and logs the user in.

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
require_once __DIR__ . '/../../../app/security/session_guard.php';

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

$storedChallenge = $_SESSION['webauthn_login_challenge'] ?? null;
$userId = $_SESSION['webauthn_login_user_id'] ?? null;
if ($storedChallenge === null || !$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'No pending WebAuthn login']);
    exit;
}

$credentialIdB64 = $data['id'] ?? null;
$clientDataJSON   = isset($data['clientDataJSON']) ? base64_decode(strtr($data['clientDataJSON'], '-_', '+/')) : null;
$authenticatorData = isset($data['authenticatorData']) ? base64_decode(strtr($data['authenticatorData'], '-_', '+/')) : null;
$signature         = isset($data['signature']) ? base64_decode(strtr($data['signature'], '-_', '+/')) : null;

if (!$credentialIdB64 || !$clientDataJSON || !$authenticatorData || !$signature) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing WebAuthn assertion fields']);
    exit;
}

// Decode credential ID
$credentialIdBin = base64_decode(strtr($credentialIdB64, '-_', '+/'));
if ($credentialIdBin === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid credential ID']);
    exit;
}

// Look up stored credential
global $db;
$stmt = $db->query('SELECT id, public_key, sign_count FROM webauthn_credentials WHERE user_id = ? AND credential_id = ?', [(int)$userId, $credentialIdBin]);
$credRow = $stmt->fetch();
if (!$credRow) {
    http_response_code(404);
    echo json_encode(['error' => 'Unknown credential']);
    exit;
}

// Load WebAuthn library
$libPath = __DIR__ . '/../../../app/security/WebAuthn-master/src';
set_include_path($libPath . PATH_SEPARATOR . get_include_path());
require_once $libPath . '/WebAuthn.php';

use lbuchs\WebAuthn\WebAuthn;

$rpId   = 'app.theloginpage.me';
$rpName = 'Sulfur';

try {
    $webauthn = new WebAuthn($rpName, $rpId, null, true);

    $prevSignCount = $credRow['sign_count'] !== null ? (int)$credRow['sign_count'] : null;

    $ok = $webauthn->processGet(
        $clientDataJSON,
        $authenticatorData,
        $signature,
        $credRow['public_key'],
        $storedChallenge,
        $prevSignCount,
        false, // requireUserVerification
        true   // requireUserPresent
    );

    if (!$ok) {
        throw new Exception('WebAuthn verification failed');
    }

    // Update sign_count and last_used_at
    $newCounter = $webauthn->getSignatureCounter();
    if ($newCounter !== null) {
        $db->query('UPDATE webauthn_credentials SET sign_count = ?, last_used_at = NOW() WHERE id = ?', [
            (int)$newCounter,
            (int)$credRow['id'],
        ]);
    } else {
        $db->query('UPDATE webauthn_credentials SET last_used_at = NOW() WHERE id = ?', [
            (int)$credRow['id'],
        ]);
    }

    // Clear one-time login challenge
    unset($_SESSION['webauthn_login_challenge'], $_SESSION['webauthn_login_user_id']);

    // Log the user in using the same session format as password login
    $user = getUserById((int)$userId);
    if (!$user) {
        throw new Exception('User not found for WebAuthn credential');
    }

    // Successful login: set session
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => $user['id'],
        'login' => $user['username'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'totp_secret' => $user['totp_secret'] ?? null,
        'twofa_enabled' => (int)($user['twofa_enabled'] ?? 0),
    ];
    $_SESSION['issued_at'] = time();

    // Update activity + login event
    updateLastActive($user['id']);
    updateUserActivity($user['id']);
    recordLoginEvent((int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('webauthn finish-login failed: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'WebAuthn login failed', 'detail' => $e->getMessage()]);
}
