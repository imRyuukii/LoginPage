<?php
// scripts/health.php
// Read-only health check: outputs JSON with DB status and app state.
header('Content-Type: application/json');

$result = [
    'app_ok' => true,
    'db_ok' => false,
    'users_count' => null,
    'admin_present' => null,
    'php_version' => PHP_VERSION,
    'time' => date('c'),
];

try {
    require_once __DIR__ . '/../src/config/database.php';
    // Simple probe
    $db->query('SELECT 1');
    $result['db_ok'] = true;

    // Users count and admin presence
    $countStmt = $db->query('SELECT COUNT(*) AS c FROM users');
    $row = $countStmt->fetch();
    $result['users_count'] = isset($row['c']) ? (int)$row['c'] : null;

    $adminStmt = $db->query("SELECT 1 FROM users WHERE role = 'admin' OR username = 'admin' LIMIT 1");
    $result['admin_present'] = (bool)$adminStmt->fetch();
} catch (Throwable $e) {
    $result['db_ok'] = false;
    $result['error'] = 'Health check error';
    // Log full details to server logs, not to the client
    error_log('health.php error: ' . $e->getMessage());
}

echo json_encode($result, JSON_PRETTY_PRINT);
