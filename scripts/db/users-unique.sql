-- scripts/db/users-unique.sql
-- Add unique indexes for username and email (run only after fixing duplicates)
ALTER TABLE users ADD UNIQUE KEY uniq_users_username (username);
ALTER TABLE users ADD UNIQUE KEY uniq_users_email (email);
