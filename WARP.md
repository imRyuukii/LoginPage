# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

Project overview
- Update (2025-09-20): Storage now uses MySQL via PDO. Legacy JSON files under src/data have been removed. See scripts/db/schema.sql for the current schema.
- Stack: Plain PHP (no Composer), session-based auth, MySQL via PDO (see src/config/database.php). Static assets under src/public. No Node/Composer tooling detected.
- Entry points:
  - index.php (root) is the home page and dispatcher to auth flows
  - Controllers: src/app/controllers/{login.php, register.php, profile.php, logout.php}
  - Model utilities: src/app/models/user-functions.php (JSON-backed user store)
  - API endpoint: src/public/api/heartbeat.php (updates last_activity for online status)
  - Styles: src/public/css/style.css

Common commands (pwsh examples)
- Serve locally with PHP built-in server (from repo root):
  - php -S 127.0.0.1:8080 -t .
  - Then open http://127.0.0.1:8080/
- If using XAMPP/Apache (this repo path suggests htdocs):
  - Browse to http://localhost/mb/LoginPage/
- Quick smoke checks:
  - Home page loads:
    - iwr -UseBasicParsing http://127.0.0.1:8080/ | select -ExpandProperty StatusCode
  - Heartbeat endpoint (unauthenticated) returns 401:
    - iwr -Method Post -UseBasicParsing http://127.0.0.1:8080/src/public/api/heartbeat.php -SkipHttpErrorCheck:$true | select -ExpandProperty StatusCode
  - Health check (DB + app):
    - iwr -UseBasicParsing http://localhost/mb/LoginPage/scripts/health.php | select -ExpandProperty Content
  - Admin live activity JSON (requires session; run from browser, not terminal):
    - http://localhost/mb/LoginPage/src/public/api/users/last-activity.php
- Environment variables (optional): set DB_HOST, DB_NAME, DB_USER, DB_PASS in the shell before launching Apache/PHP.
- Notes:
  - Logout is POST-only and CSRF-protected
  - Heartbeat requires CSRF; Profile and Home both send it automatically
- There is no configured linter or test runner in this repo. If you add one (e.g., phpcs, phpunit), update this file with the commands.

High-level architecture and flows
- MVC-lite structure
  - Controllers (src/app/controllers) handle requests and render inline HTML
  - Model (src/app/models/user-functions.php) encapsulates JSON-backed CRUD: getUsersData/saveUsersData, findUserByUsername/findUserByEmail, loginUser/registerUser, updateLastActive/updateUserActivity, getLastActiveFormatted
  - Views are embedded within controller PHP files (no template engine)
- Authentication
  - login.php: POSTs credentials; loginUser() verifies via password_verify; sets $_SESSION['user'] with id/username/name/email/role and redirects to profile
  - register.php: validates unique username/email, password rules; persists to users.json via registerUser(); optional delayed redirect to login
  - logout.php: clears and destroys session, redirects home
- Profile and roles
  - profile.php requires session; shows user details; admin sees an "All Users" list
  - Role detection is resilient (falls back to username === 'admin' when role is absent)
  - Profile pictures are chosen by role from src/public/images/{admin-pfp.jpg,user-pfp.jpg}
- Online/heartbeat system
  - profile.php JS posts every 30s to src/public/api/heartbeat.php while page is visible
  - heartbeat.php requires session and updates last_activity via updateUserActivity($userId)
  - getLastActiveFormatted() shows "Online" if activity within 2 minutes, else a friendly "x minutes/hours/days ago"

Path/include conventions (important when moving files)
- From controllers: require_once '../models/user-functions.php'
- From API endpoint: require_once '../../app/models/user-functions.php'
- Public assets referenced from HTML in controllers: ../../public/... (relative to controller location)
- Root page (index.php) references assets at ./src/public/...

Data model (JSON-backed)
- users.json contains an array of users with fields: id, username, password_hash, name, email, role, created_at, last_active, last_activity. ID is computed as max(id)+1 when registering.
- Writes are done via saveUsersData(); errors are not thrown but saveUsersData returns a boolean.

Database migration (from docs/src/docs/database-implementation-guide.md)
- The codebase is prepared to migrate from JSON to MariaDB/MySQL.
- Proposed additions:
  - src/config/database.php: PDO connection wrapper (host, dbname, username, password, charset=utf8mb4)
  - src/includes/user-functions-db.php: DB-backed versions of the user functions
- Schema highlights (users table): id, username (unique), password_hash, name, email (unique), role ENUM('admin','user'), created_at, last_active, last_activity, updated_at; recommended indexes on username, email, role, created_at, last_activity
- Switch-over strategy:
  - Keep JSON functions for backup
  - Update require_once targets in controllers to the DB-backed functions when ready
  - Migrate initial users from users.json to DB (SQL examples are in the guide)

What to be careful about when editing
- Relative paths are hardcoded in controllers/API. If you move files, update require_once paths and asset href/src accordingly.
- Session keys: code expects $_SESSION['user'] with id, login/username, name, email, role. Maintain this shape if you refactor.
- Time fields: last_active (on login) and last_activity (heartbeat) are distinct; keep both semantics if you change storage.

Missing tooling (as of now)
- No Composer (composer.json) detected
- No PHPUnit/phpunit.xml detected
- No linters or static analyzers configured

If you add tooling
- Document exact commands here (install, run, single-test, formatting/lint rules)
- Keep commands compatible with PowerShell on Windows
