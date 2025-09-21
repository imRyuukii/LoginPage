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

function updateUserRole(int $userId, string $role): bool {
    $role = strtolower($role);
    if (!in_array($role, ['admin', 'user'], true)) {
        return false;
    }
    try {
        global $db;
        $stmt = $db->query('UPDATE users SET role = ? WHERE id = ?', [$role, $userId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log('updateUserRole failed: ' . $e->getMessage());
        return false;
    }
}

// Email Verification Functions

/**
 * Create email verification token
 */
function createEmailVerificationToken(int $userId): string {
    try {
        global $db;
        
        // Generate secure random token
        $token = bin2hex(random_bytes(32));
        
        // Set expiration to 24 hours from now
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Remove any existing tokens for this user
        $db->query('DELETE FROM email_verifications WHERE user_id = ?', [$userId]);
        
        // Insert new token
        $db->query(
            'INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, ?)',
            [$userId, $token, $expiresAt]
        );
        
        return $token;
    } catch (Exception $e) {
        error_log('createEmailVerificationToken failed: ' . $e->getMessage());
        throw new Exception('Failed to create verification token');
    }
}

/**
 * Verify email token and mark user as verified
 */
function verifyEmailToken(string $token): array {
    try {
        global $db;
        
        // Find valid token
        $stmt = $db->query(
            'SELECT ev.*, u.username, u.name, u.email FROM email_verifications ev ' .
            'JOIN users u ON ev.user_id = u.id ' .
            'WHERE ev.token = ? AND ev.expires_at > NOW()',
            [$token]
        );
        $verification = $stmt->fetch();
        
        if (!$verification) {
            return ['success' => false, 'message' => 'Invalid or expired verification token'];
        }
        
        // Mark user as verified
        $db->query('UPDATE users SET email_verified = TRUE WHERE id = ?', [$verification['user_id']]);
        
        // Remove the used token
        $db->query('DELETE FROM email_verifications WHERE token = ?', [$token]);
        
        return [
            'success' => true,
            'message' => 'Email verified successfully! You can now log in.',
            'user' => [
                'id' => $verification['user_id'],
                'username' => $verification['username'],
                'name' => $verification['name'],
                'email' => $verification['email']
            ]
        ];
    } catch (Exception $e) {
        error_log('verifyEmailToken failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to verify email'];
    }
}

/**
 * Check if user's email is verified
 */
function isEmailVerified(int $userId): bool {
    try {
        global $db;
        $stmt = $db->query('SELECT email_verified FROM users WHERE id = ?', [$userId]);
        $user = $stmt->fetch();
        return $user && $user['email_verified'];
    } catch (Exception $e) {
        error_log('isEmailVerified failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get user by ID for verification purposes
 */
function getUserById(int $userId): ?array {
    try {
        global $db;
        $stmt = $db->query('SELECT * FROM users WHERE id = ?', [$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Exception $e) {
        error_log('getUserById failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Clean up expired verification tokens
 */
function cleanupExpiredTokens(): int {
    try {
        global $db;
        $stmt = $db->query('DELETE FROM email_verifications WHERE expires_at < NOW()');
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log('cleanupExpiredTokens failed: ' . $e->getMessage());
        return 0;
    }
}

// Password Reset Functions

/**
 * Create password reset token for user
 */
function createPasswordResetToken(string $email): array {
    try {
        global $db;
        
        // Find user by email
        $user = findUserByEmail($email);
        if (!$user) {
            return ['success' => false, 'message' => 'No account found with that email address'];
        }
        
        // Check if user's email is verified
        if (!$user['email_verified']) {
            return ['success' => false, 'message' => 'Please verify your email address first before resetting password'];
        }
        
        // Generate secure random token
        $token = bin2hex(random_bytes(32));
        
        // Set expiration to 1 hour from now
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Remove any existing tokens for this user
        $db->query('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);
        
        // Insert new token
        $db->query(
            'INSERT INTO password_resets (user_id, email, token, expires_at) VALUES (?, ?, ?, ?)',
            [$user['id'], $email, $token, $expiresAt]
        );
        
        return [
            'success' => true,
            'token' => $token,
            'user' => $user
        ];
    } catch (Exception $e) {
        error_log('createPasswordResetToken failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create password reset token'];
    }
}

/**
 * Validate password reset token and get associated user
 */
function validatePasswordResetToken(string $token): array {
    try {
        global $db;
        
        // Find valid token
        $stmt = $db->query(
            'SELECT pr.*, u.username, u.name, u.email FROM password_resets pr ' .
            'JOIN users u ON pr.user_id = u.id ' .
            'WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL',
            [$token]
        );
        $reset = $stmt->fetch();
        
        if (!$reset) {
            return ['success' => false, 'message' => 'Invalid or expired password reset token'];
        }
        
        return [
            'success' => true,
            'user' => [
                'id' => $reset['user_id'],
                'username' => $reset['username'],
                'name' => $reset['name'],
                'email' => $reset['email']
            ],
            'token_data' => $reset
        ];
    } catch (Exception $e) {
        error_log('validatePasswordResetToken failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to validate password reset token'];
    }
}

/**
 * Reset user password using valid token
 */
function resetUserPassword(string $token, string $newPassword): array {
    try {
        global $db;
        
        // Validate token first
        $tokenValidation = validatePasswordResetToken($token);
        if (!$tokenValidation['success']) {
            return $tokenValidation;
        }
        
        $user = $tokenValidation['user'];
        
        // Validate password strength
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters long'];
        }
        
        // Hash new password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update user password
        $db->query(
            'UPDATE users SET password_hash = ?, password_reset_at = NOW() WHERE id = ?',
            [$passwordHash, $user['id']]
        );
        
        // Mark token as used
        $db->query(
            'UPDATE password_resets SET used_at = NOW() WHERE token = ?',
            [$token]
        );
        
        return [
            'success' => true,
            'message' => 'Password reset successful! You can now log in with your new password.',
            'user' => $user
        ];
    } catch (Exception $e) {
        error_log('resetUserPassword failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to reset password'];
    }
}

/**
 * Clean up expired password reset tokens
 */
function cleanupExpiredPasswordResetTokens(): int {
    try {
        global $db;
        $stmt = $db->query('DELETE FROM password_resets WHERE expires_at < NOW() OR used_at IS NOT NULL');
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log('cleanupExpiredPasswordResetTokens failed: ' . $e->getMessage());
        return 0;
    }
}

// Presentation helper chooses the most recent of last_activity and last_active
function getLastActiveFormatted($lastActive, $lastActivity = null): string {
    // Prefer the newer of the two timestamps if both exist
    if ($lastActivity && $lastActive) {
        $timeToCheck = (strtotime($lastActivity) >= strtotime($lastActive)) ? $lastActivity : $lastActive;
    } else {
        $timeToCheck = $lastActivity ?: $lastActive;
    }
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
