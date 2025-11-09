<?php
// Harden session cookie and start session
global $db;
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "domain" => "",
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Lax",
    ]);
}
session_start();
require_once "./src/app/models/user-functions-db.php";
require_once "./src/app/security/csrf.php";
csrf_ensure_initialized();
require_once "./src/config/database.php";
$isLoggedIn = isset($_SESSION["user"]);

// If logged in, fetch fresh user data to get updated profile picture
if ($isLoggedIn) {
    try {
        $stmt = $db->query("SELECT * FROM users WHERE id = ?", [
            $_SESSION["user"]["id"],
        ]);
        $dbUser = $stmt->fetch();
        if ($dbUser) {
            // Update session with fresh data
            $_SESSION["user"] = array_merge($_SESSION["user"], $dbUser);
        }
    } catch (Exception $e) {
        // If database fetch fails, continue with session data
        error_log("Failed to fetch user data: " . $e->getMessage());
    }
}

// Get today's login count
$todayLoginCount = 0;
if (!isset($db)) {
    require_once "./src/config/database.php";
}
try {
    // Count successful login events from today (UTC, based on login_events table)
    $stmt = $db->query(
        "SELECT COUNT(*) AS count
         FROM login_events
         WHERE created_at >= CURDATE() AND created_at < (CURDATE() + INTERVAL 1 DAY)",
    );
    $result = $stmt->fetch();
    $todayLoginCount = (int) ($result["count"] ?? 0);
} catch (Exception $e) {
    error_log(
        "Failed to fetch today login count from login_events: " .
            $e->getMessage(),
    );
    // Fallback: try to approximate using users' last_activity updated today (may include non-login activity)
    try {
        $stmt = $db->query(
            "SELECT COUNT(*) AS count
             FROM users
             WHERE last_activity >= CURDATE() AND last_activity < (CURDATE() + INTERVAL 1 DAY)",
        );
        $result = $stmt->fetch();
        $todayLoginCount = (int) ($result["count"] ?? 0);
    } catch (Exception $e2) {
        error_log("Fallback login count query failed: " . $e2->getMessage());
        $todayLoginCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Secure authentication system with email verification, password reset, and real-time user management. Professional login and registration platform.">
    <meta name="keywords" content="login, authentication, registration, secure login, email verification, password reset, user management">
    <meta name="author" content="LoginPage System">
    <meta name="theme-color" content="#124e66">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="LoginPage - Secure Authentication System">
    <meta property="og:description" content="Professional authentication platform with email verification and user management">
    <meta property="og:image" content="./src/public/images/logo.png">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="LoginPage - Secure Authentication System">
    <meta name="twitter:description" content="Professional authentication platform with email verification and user management">
    <meta name="twitter:image" content="./src/public/images/logo.png">

    <title>LoginPage - Secure Authentication System</title>

    <!-- Favicons -->
    <link rel="icon" type="image/png" sizes="32x32" href="./src/public/images/logo.png">
    <link rel="apple-touch-icon" href="./src/public/images/logo.png">

    <link rel="stylesheet" href="src/public/css/style.css?v=<?php echo time(); ?>">
    <script src="./src/public/js/heartbeat.js" defer></script>
    <script src="./src/public/js/toast.js" defer></script>
    <script src="./src/public/js/form-utils.js" defer></script>
</head>
<body>
<?php
$NAV_BASE = "./";
include __DIR__ . "/src/public/partials/navbar.php";
?>
<div class="container page">
    <!-- Hero Section -->
    <div class="card glow" style="text-align:center; padding: 56px 36px; margin-bottom: 28px; position: relative; overflow: hidden;">
        <div class="badge-pill" aria-hidden="true" style="display:inline-flex; align-items:center; gap:8px; padding:6px 12px; border-radius:999px; border:1px solid var(--border); background: var(--glass); font-weight:900; font-size:12px; color: var(--muted); margin: 0 auto 14px;">
            <i class="fa-solid fa-bolt" style="color: var(--primary);"></i> Fast • Secure • Fun
        </div>
        <h1 class="welcome-text" style="margin-bottom: 8px;">Secure. Simple. A bit of sparkle.</h1>
        <p style="max-width: 760px; margin: 0 auto 4px; color: var(--muted); font-size: 1.1rem;">
            A modern authentication platform with email verification, password resets, and real-time user activity.
            Built for performance and security—with a friendly, polished vibe.
        </p>
        <!-- Floating accents (decorative) -->
        <div aria-hidden="true" style="pointer-events:none; position:absolute; inset:0;">
            <span class="sparkle" style="position:absolute; top:8%; left:8%; width:10px; height:10px; border-radius:50%; background: radial-gradient(circle, rgba(116,141,146,0.9), rgba(116,141,146,0)); filter: blur(0.5px); opacity:0.7;"></span>
            <span class="sparkle" style="position:absolute; bottom:14%; right:12%; width:12px; height:12px; border-radius:50%; background: radial-gradient(circle, rgba(18,78,102,0.9), rgba(18,78,102,0)); filter: blur(0.5px); opacity:0.7;"></span>
        </div>
    </div>

    <!-- Page-specific lightweight styles -->
    <style>
      @media (prefers-reduced-motion: no-preference) {
        .sparkle { animation: floatY 6s ease-in-out infinite; }
        .sparkle:nth-child(2) { animation-duration: 8s; animation-delay: 0.6s; }
        @keyframes floatY { 0% { transform: translateY(0); } 50% { transform: translateY(8px); } 100% { transform: translateY(0); } }
      }
      .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
      .feature { display:flex; gap:12px; align-items:flex-start; text-align:left; }
      .feature .icon { width:38px; height:38px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; background: rgba(18,78,102,0.18); color:#fff; box-shadow: var(--shadow-sm); }
      [data-theme="light"] .feature .icon { background: rgba(61,82,160,0.18); color: var(--primary-700); }
      .feature h3 { margin:0; text-align:left; }
      .feature p { margin:4px 0 0 0; color: var(--muted); font-size: 0.96rem; }
      .trust-strip { display:grid; grid-auto-flow: column; gap: 10px; align-items:center; justify-content:center; text-align:center; }
      .trust-pill { display:inline-flex; gap:8px; align-items:center; padding:8px 12px; border-radius:999px; border:1px solid var(--border); background: var(--glass); font-weight:800; font-size: 0.9rem; }
      @media (max-width: 820px) { .features-grid { grid-template-columns: 1fr; } .trust-strip { grid-auto-flow: row; } }
    </style>
    <div class="card">
        <?php if ($isLoggedIn): ?>
            <?php // Check if user has custom profile picture
            if (
                !empty($_SESSION["user"]["profile_picture"]) &&
                file_exists(
                    "./src/public/images/profile-pictures/" .
                        $_SESSION["user"]["profile_picture"],
                )
            ) {
                $imagePath =
                    "./src/public/images/profile-pictures/" .
                    htmlspecialchars($_SESSION["user"]["profile_picture"]);
            } else {
                // Fallback to default based on role
                $userRole =
                    $_SESSION["user"]["role"] ??
                    ($_SESSION["user"]["login"] === "admin" ? "admin" : "user");
                $profilePic =
                    $userRole === "admin" ? "admin-pfp.jpg" : "user-pfp.jpg";
                $imagePath = "./src/public/images/" . $profilePic;
            } ?>
            <img src="<?php echo $imagePath; ?>" alt="Profile Picture" class="profile-picture">
        <?php endif; ?>
        <h1 class="welcome-text">WELCOME</h1>
        <?php if ($isLoggedIn): ?>
            <p>Logged in as: <strong><?php echo htmlspecialchars(
                $_SESSION["user"]["login"],
            ); ?></strong></p>
            <div class="link-row mt-3">
                <a class="button" href="./src/app/controllers/profile.php">Go to profile</a>
                <form method="post" action="./src/app/controllers/logout.php" style="display:inline;">
                    <?php echo csrf_field(); ?>
                    <button class="button" type="submit">Logout</button>
                </form>
            </div>
        <?php else: ?>
            <p>You are not logged in. Please log in to use our website.</p>
            <p class="link-row mt-3">
                <a class="button primary" href="./src/app/controllers/login.php">Login</a>
            </p>
        <?php endif; ?>
        <p class="footer mt-6">Demo auth flow with in-memory users.</p>
    </div>

    <!-- Info Cards Row -->
    <div class="cards-row mt-4">
        <div class="card">
            <div class="stats-number" data-target="<?php echo (int)$todayLoginCount; ?>">0</div>
            <div class="stats-label">Logins Today</div>
        </div>
        <div class="card">
            <div class="typewriter-text" id="typewriter"></div>
        </div>
    </div>

    <!-- Feature Highlights -->
    <div class="card" style="margin-top: 10px;">
      <div class="features-grid">
        <div class="feature">
          <span class="icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
          <div>
            <h3>Serious security</h3>
            <p>Email verification, rate‑limited login, CSRF protection and more—built in.</p>
          </div>
        </div>
        <div class="feature">
          <span class="icon" aria-hidden="true"><i class="fa-solid fa-bolt"></i></span>
          <div>
            <h3>Lightning‑fast</h3>
            <p>Optimized SQL calls and lightweight UI so everything feels instant.</p>
          </div>
        </div>
        <div class="feature">
          <span class="icon" aria-hidden="true"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
          <div>
            <h3>Polished & fun</h3>
            <p>Clean, professional design with just enough personality to feel friendly.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Trust / Credibility Strip -->
    <div class="card" style="padding: 18px;">
      <div class="trust-strip" aria-label="Highlights">
        <span class="trust-pill"><i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i> Verified emails</span>
        <span class="trust-pill"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Rate‑limited logins</span>
        <span class="trust-pill"><i class="fa-solid fa-lock" aria-hidden="true"></i> Secure sessions</span>
      </div>
    </div>
</div>
<div class="demo-warning">*This is a demo version of the website</div>
<script>
    (function() {
        const CSRF_TOKEN = '<?php echo htmlspecialchars(csrf_token()); ?>';
        const IS_LOGGED_IN = <?php echo $isLoggedIn ? "true" : "false"; ?>;
        window.addEventListener('DOMContentLoaded', function(){
            if (IS_LOGGED_IN && window.Heartbeat) {
                window.Heartbeat.installHeartbeatOnLoad({ url: './src/public/api/heartbeat.php', csrf: CSRF_TOKEN });
            }
        });
        const root = document.documentElement;
        const stored = localStorage.getItem('theme');
        const prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
        const initial = stored || (prefersLight ? 'light' : 'dark');
        if (initial === 'light') {
            root.setAttribute('data-theme', 'light');
        } else {
            root.removeAttribute('data-theme');
        }
        const btn = document.getElementById('themeToggle');

        function setIcon() {
            const isLight = root.getAttribute('data-theme') === 'light';
            btn.textContent = isLight ? '☀️' : '🌙';
            btn.title = isLight ? 'Switch to dark mode' : 'Switch to light mode';
        }
        setIcon();
        btn.addEventListener('click', function() {
            document.body.classList.add('theme-transition');
            const isLight = root.getAttribute('data-theme') === 'light';
            if (isLight) {
                root.removeAttribute('data-theme');
                localStorage.setItem('theme', 'dark');
            } else {
                root.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
            }
            setIcon();
            window.setTimeout(function(){
                document.body.classList.remove('theme-transition');
            }, 320);
        });

        // Heartbeat on home as well, so online status works anywhere when logged in
        if (IS_LOGGED_IN) {
            (function() {
                let interval;
                function send() {
                    fetch('./src/public/api/heartbeat.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'csrf=' + encodeURIComponent(CSRF_TOKEN)
                    }).catch(function(e){ /* silent */ });
                }
                function start(){ interval = setInterval(send, 30000); send(); }
                function stop(){ if (interval) clearInterval(interval); }
                document.addEventListener('visibilitychange', function(){
                    if (!document.hidden) start(); else stop();
                });
                start();
            })();
        }

        // Typewriter Effect
        (function() {
            const messages = [
                "Try Logging in.",
                "Share Your Thoughts.",
                "Making an account is really simple."
            ];
            const typewriterElement = document.getElementById('typewriter');
            let messageIndex = 0;
            let charIndex = 0;
            let isDeleting = false;
            let typingSpeed = 100;
            const deletingSpeed = 50;
            const pauseBeforeDelete = 2000;
            const pauseBeforeNext = 500;

            function type() {
                const currentMessage = messages[messageIndex];

                if (isDeleting) {
                    // Remove characters
                    typewriterElement.textContent = currentMessage.substring(0, charIndex - 1);
                    charIndex--;

                    if (charIndex === 0) {
                        isDeleting = false;
                        messageIndex = (messageIndex + 1) % messages.length;
                        setTimeout(type, pauseBeforeNext);
                        return;
                    }
                    setTimeout(type, deletingSpeed);
                } else {
                    // Add characters
                    typewriterElement.textContent = currentMessage.substring(0, charIndex + 1);
                    charIndex++;

                    if (charIndex === currentMessage.length) {
                        isDeleting = true;
                        setTimeout(type, pauseBeforeDelete);
                        return;
                    }
                    setTimeout(type, typingSpeed);
                }
            }

            // Start the typewriter effect
            setTimeout(type, 500);
        })();

        // Count-up for Logins Today
        (function(){
          const el = document.querySelector('.stats-number[data-target]');
          if (!el) return;
          const target = parseInt(el.getAttribute('data-target') || '0', 10);
          if (!Number.isFinite(target)) return;
          const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
          if (reduce || target <= 0) { el.textContent = target.toLocaleString(); return; }
          const duration = 900; // ms
          const start = performance.now();
          function tick(now){
            const p = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - p, 3);
            const val = Math.floor(eased * target);
            el.textContent = val.toLocaleString();
            if (p < 1) requestAnimationFrame(tick); else el.textContent = target.toLocaleString();
          }
          requestAnimationFrame(tick);
        })();

    })();
</script>
</body>
</html>
