-- Password reset functionality database update
-- Run this in phpMyAdmin after backing up your database

USE login_system;

-- Create password_resets table for secure token storage
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

-- Clean up expired tokens (optional - can be done via cron job)
-- DELETE FROM password_resets WHERE expires_at < NOW() OR used_at IS NOT NULL;

-- Add password reset tracking to users table (optional)
ALTER TABLE users ADD COLUMN password_reset_at TIMESTAMP NULL DEFAULT NULL AFTER email_verified;