# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

LoginPage is a production-ready PHP authentication system with email verification, password reset, and real-time user management. The application uses **pure PHP with MySQL** (no frameworks), following an MVC-like pattern with clear separation of concerns.

**Key characteristics:**
- **Stack**: PHP 8.x + MySQL + Apache (or PHP-FPM)
- **Dependencies**: PHPMailer (via Composer), zero JavaScript dependencies
- **Architecture**: MVC-style pattern with controllers, models, services, and public assets
- **Security**: CSRF protection, password hashing, prepared statements, email verification required for login
- **Deployment**: Arch Linux production + XAMPP local development

## Essential Commands

### Database Setup
```fish
# Initial database setup (run once)
mysql -u root -p < scripts/db/schema.sql

# Add email verification tables
mysql -u root -p login_system < scripts/db/email-verification-update.sql

# Add password reset tables
mysql -u root -p login_system < scripts/db/password-reset-update.sql
```

### Composer Dependencies
```fish
# Install PHPMailer (required for email functionality)
php composer.phar install

# Or if composer is globally available
composer install
```

### Development Server
```fish
# Using PHP built-in server (for quick testing)
php -S localhost:8000 -t .

# Apache/XAMPP: Place in htdocs/LoginPage/
# Access at: http://localhost/LoginPage
```

### Health Checks
```fish
# Check database connection and app status
curl http://localhost/LoginPage/scripts/health.php

# Production
curl https://app.theloginpage.me/LoginPage/scripts/health.php
```

### Database Operations
```fish
# Create admin user (after registration)
mysql -u root -p login_system -e "UPDATE users SET role='admin' WHERE email='your@email.com';"

# View all users
mysql -u root -p login_system -e "SELECT id, username, email, role, created_at FROM users;"

# Clean expired tokens (maintenance)
mysql -u root -p login_system -e "DELETE FROM email_verifications WHERE expires_at < NOW();"
mysql -u root -p login_system -e "DELETE FROM password_resets WHERE expires_at < NOW();"
```

## Architecture & Code Organization

### MVC-Style Structure

**Controllers** (`src/app/controllers/`)
- Handle HTTP requests and responses
- Manage session state and CSRF tokens
- Render views (inline HTML templates)
- Key files: `login.php`, `register.php`, `profile.php`, `email-verification.php`, `reset-password.php`

**Models** (`src/app/models/`)
- `user-functions-db.php`: All user data operations (CRUD, authentication, token management)
- Uses global `$db` object (defined in `src/config/database.php`)
- All queries use prepared statements for SQL injection protection

**Services** (`src/app/services/`)
- `EmailServiceSMTP.php`: Production email via Gmail SMTP (PHPMailer)
- `EmailService.php`: Local development email service (MailHog)
- `RateLimiter.php`: Rate limiting for login attempts
- Business logic layer between controllers and models

**Views**
- Inline HTML templates within controller files
- Shared navbar: `src/public/partials/navbar.php`
- CSS: `src/public/css/style.css` (dark/light themes, responsive)

**Public Assets** (`src/public/`)
- `api/`: REST endpoints (`heartbeat.php`, `users/last-activity.php`)
- `js/`: Pure JavaScript modules (`heartbeat.js`, `toast.js`, `form-utils.js`)
- `css/`: Stylesheets with theme support
- `images/`: Profile pictures and site assets

**Configuration** (`src/config/`)
- `database.php`: Database connection class with PDO
- Environment variables: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`

**Security** (`src/app/security/`)
- `csrf.php`: CSRF token generation and validation
- Always call `csrf_ensure_initialized()` at session start
- Use `csrf_field()` in forms, `csrf_validate()` to verify

### Database Schema

Primary tables:
- `users`: User accounts (username, password_hash, email, role, timestamps)
- `email_verifications`: Email verification tokens (64-char random, 24h expiry)
- `password_resets`: Password reset tokens (64-char random, 1h expiry)
- `login_events`: Login tracking for statistics and security audit

### Path Conventions

When writing code, use these relative path patterns:

**From controllers** (`src/app/controllers/`):
```php
require_once '../models/user-functions-db.php';
require_once '../services/EmailServiceSMTP.php';
require_once '../security/csrf.php';
require_once '../../config/database.php';
```

**From models** (`src/app/models/`):
```php
require_once __DIR__ . '/../../config/database.php';
```

**From API endpoints** (`src/public/api/`):
```php
require_once '../../app/models/user-functions-db.php';
require_once '../../app/security/csrf.php';
```

**From root** (`index.php`):
```php
require_once './src/app/models/user-functions-db.php';
require_once './src/config/database.php';
```

### Authentication Flow

1. **Registration**: `register.php` → creates user → sends verification email → redirects to login
2. **Email Verification**: User clicks link → `email-verification.php` validates token → marks email verified
3. **Login**: `login.php` → checks credentials → verifies email verified → creates session → records login event
4. **Password Reset**: `forgot-password.php` → sends reset email → `reset-password.php` validates token → updates password

**Important**: Users CANNOT login until email is verified. The `login.php` controller checks `email_verified` status.

### Real-Time Features

**Heartbeat System**: JavaScript sends POST to `api/heartbeat.php` every 30 seconds to update `last_activity` timestamp. Used for online status indicators in admin panel.

- Automatic on page load
- Pauses when tab is hidden (visibility API)
- Stops on page unload
- Requires CSRF token

### Security Patterns

When adding new features, always follow these patterns:

1. **CSRF Protection**: All forms and AJAX requests must include and validate CSRF token
2. **Input Validation**: Sanitize with `htmlspecialchars()`, validate email with `filter_var()`
3. **SQL Queries**: Always use prepared statements via `$db->query($sql, $params)`
4. **Passwords**: Use `password_hash()` for storage, `password_verify()` for checking
5. **Tokens**: 64-character random tokens for email verification and password reset
6. **Sessions**: Call `session_regenerate_id(true)` after login to prevent fixation

### Email Configuration

Production SMTP (Gmail):
1. Enable 2FA on Gmail account
2. Generate App Password (not regular password)
3. Update credentials in `EmailServiceSMTP.php` constructor:
   ```php
   $this->mailer->Username = 'your-email@gmail.com';
   $this->mailer->Password = 'your-app-password';
   ```
4. Set sender: `$this->mailer->setFrom('no-reply@yourdomain.com', 'Your App Name')`

Email templates are defined inline in `EmailServiceSMTP.php` methods (`sendVerificationEmail`, `sendPasswordResetEmail`).

## Testing & Debugging

### Manual Testing Flow
```fish
# 1. Register new user
# Visit: http://localhost/LoginPage/src/app/controllers/register.php

# 2. Check email_verifications table for token
mysql -u root -p login_system -e "SELECT * FROM email_verifications ORDER BY created_at DESC LIMIT 1;"

# 3. Verify email by visiting link or manually:
mysql -u root -p login_system -e "UPDATE users SET email_verified=1 WHERE id=X;"

# 4. Test login
# Visit: http://localhost/LoginPage/src/app/controllers/login.php

# 5. Test password reset
# Visit: http://localhost/LoginPage/src/app/controllers/forgot-password.php
```

### Debug Files
- `debug-time.php`: Timezone debugging
- `test-rate-limit.php`: Rate limiter testing
- `test-timestamps.php`: Database timestamp testing
- `testdb.php`: Database connection test

### Common Issues

**"Email not verified"**: User must click verification link before login. Check `email_verified` column in users table.

**Database connection failed**: Verify credentials in `src/config/database.php` match your MySQL setup. XAMPP default user is `root` with empty password.

**CSRF token mismatch**: Ensure `csrf_ensure_initialized()` is called before rendering forms and `csrf_validate()` is called on form submission.

**Emails not sending**: 
- Local dev: Use MailHog or similar SMTP catcher
- Production: Verify Gmail App Password is correct in `EmailServiceSMTP.php`

## Production Deployment

The app is deployed at `https://app.theloginpage.me/LoginPage` using:
- Cloudflare Tunnel for HTTPS
- Apache with PHP-FPM on Arch Linux
- Systemd service for persistent tunnel
- Namecheap domain with Cloudflare DNS

Email verification and password reset links are configured to use the production domain.

## File Naming Patterns

- Controllers: `{action}.php` (login.php, register.php)
- Models: `{entity}-functions-db.php` (user-functions-db.php)
- Services: `{Service}Service.php` (EmailService.php)
- API endpoints: `{endpoint}.php` (heartbeat.php)
- Scripts: `{purpose}.php` or `{table}.sql`

## Important Global Objects

- `$db`: Global Database instance (from `src/config/database.php`)
- `$_SESSION['user']`: Current logged-in user data (id, username, name, email, role)
- `$_SESSION['csrf_token']`: CSRF token (auto-initialized)

## Adding New Features

### New Controller
1. Create file in `src/app/controllers/`
2. Include session start, CSRF init, database config
3. Require models/services as needed
4. Implement request handling logic
5. Render HTML or redirect

### New API Endpoint
1. Create file in `src/public/api/`
2. Set `Content-Type: application/json` header
3. Validate CSRF for POST requests
4. Return JSON with `json_encode()`

### New Database Table
1. Add schema to `scripts/db/schema.sql` or new migration file
2. Create model functions in `user-functions-db.php` or new model file
3. Use prepared statements for all queries

### Email Template
1. Modify methods in `src/app/services/EmailServiceSMTP.php`
2. HTML emails with inline CSS (for email client compatibility)
3. Include plaintext alternative with `$mailer->AltBody`
