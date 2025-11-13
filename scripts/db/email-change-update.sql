-- scripts/db/email-change-update.sql
-- Email change requests table

CREATE TABLE IF NOT EXISTS email_changes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  new_email VARCHAR(255) NOT NULL,
  token CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (token),
  INDEX (user_id),
  INDEX (new_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;