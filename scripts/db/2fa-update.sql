-- scripts/db/2fa-update.sql
-- Add TOTP 2FA fields to users table

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS twofa_enabled TINYINT(1) NOT NULL DEFAULT 0;
