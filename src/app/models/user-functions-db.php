<?php
// src/app/models/user-functions-db.php
require_once __DIR__ . '/../../config/database.php';

function getUsersData(): array {
    global $db;
    // Show oldest first: order by created_at ASC, fallback to id ASC
    $stmt = $db->query('SELECT * FROM users ORDER BY created_at ASC, id ASC');
    return $stmt->fetchAll();
}

function findUserByUsername(string $username): ?array {
    global $db;
    $stmt = $db->query('SELECT * FROM users WHERE username = ?', [$username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function findUserByEmail(string $email): ?array {
    global $db;
    $stmt = $db->query('SELECT * FROM users WHERE email = ?', [$email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function loginUser(string $username, string $password) {
    $user = findUserByUsername($username);
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user; // Controllers expect id, username, name, email, role
    }
    return false;
}

function registerUser(string $username, string $password, string $name, string $email): array {
    if (empty($username) || empty($password) || empty($name) || empty($email)) {
        return ['success' => false, 'message' => 'All fields are required'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }
    if (findUserByUsername($username)) {
        return ['success' => false, 'message' => 'Username already exists'];
    }
    if (findUserByEmail($email)) {
        return ['success' => false, 'message' => 'Email already exists'];
    }

    try {
        global $db;
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $now = date('Y-m-d H:i:s');
        $db->query(
            "INSERT INTO users (username, password_hash, name, email, role, created_at, last_active) VALUES (?, ?, ?, ?, 'user', ?, ?)",
            [$username, $passwordHash, $name, $email, $now, $now]
        );
        return ['success' => true, 'message' => 'User registered successfully'];
    } catch (Exception $e) {
        error_log('registerUser failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to register user'];
    }
}

function getAllUsers(): array {
    return getUsersData();
}

function updateLastActive(int $userId): bool {
    try {
        global $db;
        $stmt = $db->query('UPDATE users SET last_active = NOW() WHERE id = ?', [$userId]);
        return $stmt->rowCount() >= 0;
    } catch (Exception $e) {
        error_log('updateLastActive failed: ' . $e->getMessage());
        return false;
    }
}

function updateUserActivity(int $userId): bool {
    try {
        global $db;
        $stmt = $db->query('UPDATE users SET last_activity = NOW() WHERE id = ?', [$userId]);
        return $stmt->rowCount() >= 0;
    } catch (Exception $e) {
        error_log('updateUserActivity failed: ' . $e->getMessage());
        return false;
    }
}

function deleteUser(int $userId): bool {
    try {
        global $db;
        $stmt = $db->query('DELETE FROM users WHERE id = ?', [$userId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log('deleteUser failed: ' . $e->getMessage());
        return false;
    }
}

// Presentation helper remains identical
function getLastActiveFormatted($lastActive, $lastActivity = null): string {
    $timeToCheck = $lastActivity ?: $lastActive;
    if (!$timeToCheck) return 'Never';

    $lastActiveTime = new DateTime($timeToCheck);
    $now = new DateTime();
    $diff = $now->diff($lastActiveTime);

    if ($diff->days == 0 && $diff->h == 0 && $diff->i <= 2) return 'Online';
    if ($diff->days > 0)       return $diff->days . ' day'   . ($diff->days > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0)          return $diff->h    . ' hour'  . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0)          return $diff->i    . ' minute'. ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}
