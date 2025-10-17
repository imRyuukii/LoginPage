-- Migration: Add Rate Limiting System
-- Date: January 2025
-- Purpose: Prevent brute force attacks and abuse

-- Rate limiting table for tracking attempts
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
    action VARCHAR(50) NOT NULL COMMENT 'login, register, password_reset, etc.',
    attempts INT DEFAULT 1,
    first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL COMMENT 'NULL if not blocked, timestamp if blocked',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_action (ip_address, action),
    INDEX idx_blocked_until (blocked_until),
    INDEX idx_last_attempt (last_attempt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed login attempts (more detailed tracking)
CREATE TABLE IF NOT EXISTS failed_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username_attempt VARCHAR(255) NOT NULL COMMENT 'Username or email attempted',
    user_id INT NULL COMMENT 'If user exists, track their ID',
    user_agent TEXT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason ENUM('invalid_username', 'invalid_password', 'account_locked', 'rate_limited') DEFAULT 'invalid_password',
    INDEX idx_ip (ip_address),
    INDEX idx_user_id (user_id),
    INDEX idx_attempt_time (attempt_time),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add lockout fields to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS failed_attempts INT DEFAULT 0 COMMENT 'Consecutive failed login attempts',
ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP NULL COMMENT 'Account locked until this time',
ADD COLUMN IF NOT EXISTS last_failed_login TIMESTAMP NULL COMMENT 'Last failed login attempt';

-- Add index for locked accounts
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_locked_until (locked_until);

-- Insert some initial comments for documentation
INSERT INTO rate_limits (ip_address, action, attempts, blocked_until) VALUES 
('__SYSTEM__', '__DOCUMENTATION__', 0, NULL)
ON DUPLICATE KEY UPDATE id=id;

-- Cleanup procedure for old rate limit entries (optional, run periodically)
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS cleanup_rate_limits()
BEGIN
    -- Delete entries older than 7 days
    DELETE FROM rate_limits 
    WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND (blocked_until IS NULL OR blocked_until < NOW());
    
    -- Delete old failed login attempts (keep 30 days)
    DELETE FROM failed_login_attempts 
    WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
END //
DELIMITER ;

-- Event to auto-cleanup (requires event scheduler enabled)
-- SET GLOBAL event_scheduler = ON;
-- CREATE EVENT IF NOT EXISTS cleanup_rate_limits_event
-- ON SCHEDULE EVERY 1 DAY
-- DO CALL cleanup_rate_limits();
