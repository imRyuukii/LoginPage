-- scripts/db/migrations/003_add_webauthn_credentials.sql
-- Add WebAuthn credential storage for passkeys / security keys

USE login_system;

CREATE TABLE IF NOT EXISTS webauthn_credentials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  -- Raw credential ID as binary (as returned by WebAuthn)
  credential_id VARBINARY(255) NOT NULL,
  -- PEM encoded public key from authenticator
  public_key TEXT NOT NULL,
  sign_count INT UNSIGNED NOT NULL DEFAULT 0,
  nickname VARCHAR(100) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at TIMESTAMP NULL,
  CONSTRAINT fk_webauthn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_credential_id (credential_id),
  KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
