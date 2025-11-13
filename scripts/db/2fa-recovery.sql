-- scripts/db/2fa-recovery.sql
-- Recovery codes for 2FA (hashed)

CREATE TABLE IF NOT EXISTS twofa_recovery_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id),
  INDEX (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;