# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

Project: LoginPage (PHP + XAMPP + MySQL)

1) Commands and workflows

- Install dependencies (Composer)
  ```bash path=null start=null
  composer install
  ```

- Initialize database (option A: phpMyAdmin)
  - Open http://localhost/phpmyadmin and import, in order:
    1) scripts/db/schema.sql
    2) scripts/db/email-verification-update.sql
    3) scripts/db/password-reset-update.sql

- Initialize database (option B: CLI via MySQL client)
  - If root has an empty password (XAMPP default), omit -p
  ```bash path=null start=null
  # Create DB (if not created by schema.sql)
  mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS login_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

  # Import schema and updates
  mysql -u root -p login_system < scripts/db/schema.sql
  mysql -u root -p login_system < scripts/db/email-verification-update.sql
  mysql -u root -p login_system < scripts/db/password-reset-update.sql
  ```

- Configure runtime DB settings via environment (PowerShell)
  ```powershell path=null start=null
  $env:DB_HOST = '127.0.0.1'
  $env:DB_NAME = 'login_system'
  $env:DB_USER = 'root'
  $env:DB_PASS = ''   # set if your MySQL root user uses a password
  ```
  Notes: These override src/config/database.php at runtime. Set them in the session that launches Apache/PHP.

- Health check (local)
  ```bash path=null start=null
  curl.exe -s http://localhost/mb/LoginPage/scripts/health.php | jq .
  ```

- Local serving options
  - Preferred: XAMPP Apache with DocumentRoot pointing to C:\xampp\htdocs and this repo under /mb/LoginPage
  - Quick preview (PHP built-in server; paths must align with project’s relative links)
    ```bash path=null start=null
    php -S localhost:8080 -t .
    # then open http://localhost:8080/index.php
    ```

- PHP syntax lint (all files)
  ```powershell path=null start=null
  Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
  ```

- Email delivery
  - For real SMTP using PHPMailer: edit src/app/services/EmailServiceSMTP.php, set $this->smtpEnabled = true and configure $this->smtpConfig (host/port/username/password). Use an app password for Gmail.
  - For local testing without SMTP, keep smtpEnabled=false; emails are sent via PHP mail() or a local catcher (e.g., MailHog) depending on your environment.

- Manual endpoint checks (no test suite configured)
  ```bash path=null start=null
  # Verify the health endpoint
  curl.exe -s http://localhost/mb/LoginPage/scripts/health.php | jq .

  # Exercise email verification handler (replace {{TOKEN}})
  curl.exe -s "http://localhost/mb/LoginPage/src/app/controllers/email-verification.php?token={{TOKEN}}"

  # Exercise password reset (replace {{TOKEN}})
  curl.exe -s "http://localhost/mb/LoginPage/src/app/controllers/reset-password.php?token={{TOKEN}}"
  ```
  Note: Heartbeat endpoints require session and CSRF; validate via the browser while logged in.

2) High-level architecture and structure

- Overall pattern: MVC-style organization with a light service layer
  - Controllers: src/app/controllers/*.php handle requests and render HTML. They orchestrate authentication, registration, email verification, password reset, role management, and admin actions.
  - Models: src/app/models/user-functions-db.php encapsulates MySQL data access (PDO) for users and activity updates. All queries use prepared statements.
  - Services: src/app/services/
    - EmailServiceSMTP.php integrates PHPMailer for production SMTP (Gmail or other). It builds verification/reset links to controllers.
    - EmailService.php provides a local mail() fallback with the same templates.
  - Security: src/app/security/csrf.php provides CSRF token generation/validation utilities. Controllers that mutate state should include and enforce these.
  - Configuration: src/config/database.php defines a Database class and a global $db instance. Environment variables DB_HOST/DB_NAME/DB_USER/DB_PASS override defaults.
  - Public API: src/public/api/* exposes AJAX-friendly endpoints, e.g., heartbeat.php for last-activity tracking and users/last-activity.php for admin queries. These expect a valid session (and CSRF where applicable).
  - Scripts: scripts/db/*.sql contain canonical schema and incremental updates for email verification and password reset; scripts/health.php probes DB connectivity and basic app state. scripts/project-overview.php renders a one-off HTML overview and self-deletes on shutdown.
  - Entry: index.php (root) is the landing/home; controllers reside under src/app/controllers and are linked directly by flows and email links.

- Core flows
  - Registration → Email dispatch → Email verification → Login
    - Registration creates a user and issues a 24-hour verification token.
    - EmailServiceSMTP (or EmailService) sends a link to src/app/controllers/email-verification.php?token=...
    - Login checks email_verified prior to granting a session.
  - Password reset
    - forgot-password.php issues a 1-hour reset token; email links to reset-password.php?token=...
    - reset-password.php validates token usage/expiry and updates password.
  - Session activity + online status
    - src/public/js/heartbeat.js posts to src/public/api/heartbeat.php on intervals (page visibility aware) to update last_activity.
    - Admin-only endpoint src/public/api/users/last-activity.php exposes online/last-seen data for UI.

- Database
  - Schema baseline: scripts/db/schema.sql creates users and indexes; email-verification-update.sql and password-reset-update.sql create token tables with expirations and one-time-use semantics.
  - Data access centralization: Use the Database class (src/config/database.php) and user-functions-db.php for CRUD and activity updates to keep controller logic lean and consistent.

- URLs and base path
  - The project assumes a base URL of /mb/LoginPage for constructing links in emails (see EmailService*). If deployed under a different path or host, update the base URL logic or parameterize it via environment.

Cross-file guidance for future changes (repo-specific)

- When adding a feature that sends emails, prefer EmailServiceSMTP (PHPMailer) for production and maintain template parity with the local EmailService.
- When creating new state-changing controllers or API endpoints, include src/app/security/csrf.php and enforce CSRF on POST. Keep SQL in model/service layers.
- Reuse the global $db from src/config/database.php; avoid creating ad-hoc PDO connections in controllers.

Key references from README.md (condensed)

- Quick start: import scripts/db/*.sql; set Gmail SMTP in controllers/services if sending real email; health at /scripts/health.php; admin role can be toggled in DB (role='admin').
- Useful endpoints:
  - GET src/app/controllers/email-verification.php?token=...
  - POST src/public/api/heartbeat.php (requires session + CSRF)
  - GET src/public/api/users/last-activity.php (admin-only)
- Environment overrides (PowerShell): DB_HOST, DB_NAME, DB_USER, DB_PASS

Notes

- No WARP.md existed previously; this file consolidates operational commands and the cross-cutting architecture to reduce ramp-up time.
- No automated test suite or Composer scripts are currently defined. Use the manual endpoint checks above and scripts/health.php for smoke validation until a test harness is added.
