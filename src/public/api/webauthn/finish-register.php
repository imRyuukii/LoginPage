<?php
// src/public/api/webauthn/finish-register.php
// Verifies WebAuthn registration response and stores credential.

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

if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Endpoint-specific CSRF check: token is sent in JSON body
$sentCsrf = $data['csrf'] ?? '';
$sessCsrf = $_SESSION['csrf'] ?? '';
$ok = is_string($sentCsrf) && is_string($sessCsrf) && hash_equals($sessCsrf, $sentCsrf);
if (!$ok) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$clientDataJSON     = isset($data['clientDataJSON']) ? base64_decode(strtr($data['clientDataJSON'], '-_', '+/')) : null;
$attestationObject  = isset($data['attestationObject']) ? base64_decode(strtr($data['attestationObject'], '-_', '+/')) : null;
$credentialIdBase64 = isset($data['id']) ? $data['id'] : null; // base64url

if (!$clientDataJSON || !$attestationObject || !$credentialIdBase64) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing WebAuthn response fields']);
    exit;
}

$storedChallenge = $_SESSION['webauthn_register_challenge'] ?? null;
if ($storedChallenge === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing registration challenge in session']);
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

    // processCreate expects the original challenge as ByteBuffer or binary string
    $result = $webauthn->processCreate(
        $clientDataJSON,
        $attestationObject,
        $storedChallenge,
        false, // requireUserVerification
        true,  // requireUserPresent
        false, // failIfRootMismatch (we accept unknown roots)
        false  // requireCtsProfileMatch
    );

    // Clear challenge once used
    unset($_SESSION['webauthn_register_challenge']);

    // Extract credential info
    $credentialId      = $result->credentialId;      // ByteBuffer
    $credentialIdBin   = $credentialId instanceof ByteBuffer ? $credentialId->getBinaryString() : (string)$credentialId;
    $credentialIdB64   = strtr(base64_encode($credentialIdBin), '+/', '-_');
    $publicKeyPem      = (string)$result->credentialPublicKey;
    $signCount         = (int)($result->signatureCounter ?? 0);

    // Store credential
    global $db;
    $userId = (int)$_SESSION['user']['id'];

    // Ensure we don't duplicate the same credential
    $stmt = $db->query('SELECT id FROM webauthn_credentials WHERE credential_id = ?', [$credentialIdBin]);
    $exists = $stmt->fetch();
    if ($exists) {
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'Credential already registered']);
        exit;
    }

    $db->query(
        'INSERT INTO webauthn_credentials (user_id, credential_id, public_key, sign_count) VALUES (?, ?, ?, ?)',
        [$userId, $credentialIdBin, $publicKeyPem, $signCount]
    );

    echo json_encode([
        'ok' => true,
        'credentialId' => $credentialIdB64,
        'signCount' => $signCount,
    ]);
} catch (Throwable $e) {
    error_log('webauthn finish-register failed: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'WebAuthn registration failed', 'detail' => $e->getMessage()]);
}
