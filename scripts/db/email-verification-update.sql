-- Email verification schema update
-- Run this in phpMyAdmin after backing up your database

USE login_system;

-- Add email_verified column to users table
ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE AFTER email;

-- Create email_verifications table
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

-- Clean up expired tokens (optional - can be done via cron job later)
-- DELETE FROM email_verifications WHERE expires_at < NOW();