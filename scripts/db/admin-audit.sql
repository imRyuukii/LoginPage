-- scripts/db/admin-audit.sql
-- Record admin actions for accountability

CREATE TABLE IF NOT EXISTS admin_audit (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_id INT NOT NULL,
  action VARCHAR(64) NOT NULL,
  target_user_id INT NULL,
  meta JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (actor_id),
  INDEX (target_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
