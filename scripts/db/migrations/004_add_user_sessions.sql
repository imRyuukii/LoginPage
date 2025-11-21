-- scripts/db/migrations/004_add_user_sessions.sql
-- Add user_sessions table to track active login sessions/devices

USE login_system;

CREATE TABLE IF NOT EXISTS user_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  session_id VARCHAR(128) NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at TIMESTAMP NULL,
  revoked_at TIMESTAMP NULL,
  INDEX idx_user_created (user_id, created_at),
  INDEX idx_user_last_seen (user_id, last_seen_at),
  UNIQUE KEY uniq_session_id (session_id),
  CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
