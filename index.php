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
require_once "./src/app/security/headers.php";
csrf_ensure_initialized();
apply_default_security_headers();
require_once "./src/config/database.php";
$isLoggedIn = isset($_SESSION["user"]);

// If logged in, fetch fresh user data to get an updated profile picture
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
    <meta name="author" content="Sulfur">
    <meta name="theme-color" content="#124e66">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Sulfur • Home">
    <meta property="og:description" content="Professional authentication platform with email verification and user management">
    <meta property="og:image" content="./src/public/images/logo.png">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Sulfur • Home">
    <meta name="twitter:description" content="Professional authentication platform with email verification and user management">
    <meta name="twitter:image" content="./src/public/images/logo.png">

    <title>Sulfur • Home</title>

    <!-- Favicons -->
    <link rel="icon" type="image/png" sizes="32x32" href="./src/public/images/logo.png">
    <link rel="apple-touch-icon" href="./src/public/images/logo.png">

    <link rel="stylesheet" href="src/public/css/style.css?v=<?php echo filemtime(__DIR__ . "/src/public/css/style.css"); ?>">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
    <meta name="is-logged-in" content="<?php echo $isLoggedIn ? '1' : '0'; ?>">
    <script src="./src/public/js/heartbeat.js?v=<?php echo filemtime(__DIR__ . "/src/public/js/heartbeat.js"); ?>" defer></script>
    <script src="./src/public/js/toast.js?v=<?php echo filemtime(__DIR__ . "/src/public/js/toast.js"); ?>" defer></script>
    <script src="./src/public/js/form-utils.js?v=<?php echo filemtime(__DIR__ . "/src/public/js/form-utils.js"); ?>" defer></script>
    <script src="./src/public/js/home.js?v=<?php echo filemtime(__DIR__ . "/src/public/js/home.js"); ?>" defer></script>
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

      /* New Welcome Hero card */
      .welcome-hero { position: relative; overflow: hidden; padding: 28px 24px; min-height: 180px; }
      .welcome-hero .wh-grid { display:grid; grid-template-columns: 1.8fr .4fr; gap:16px; align-items: center; }
      @media (max-width: 820px) { .welcome-hero .wh-grid { grid-template-columns: 1fr; } .welcome-hero { text-align:center; } }
      .wh-chip { display:inline-flex; align-items:center; padding:6px 12px; border:1px solid var(--border); border-radius:999px; background: var(--glass); font-weight:900; font-size:12px; color: var(--muted); }
      .wh-title { margin:10px 0 0; font-size: clamp(24px, 4vw, 36px); line-height:1.1; }
      .wh-greeting { color: var(--text); }
      .wh-name { color: var(--primary); font-weight:1000; display:block; position: relative; }
      .wh-name::after { content:''; display:block; height:6px; border-radius:999px; margin-top:8px; background: linear-gradient(90deg, var(--primary-400), var(--accent-400)); filter: blur(1px); opacity:.85; }
      .wh-meta { margin-top:10px; color: var(--muted); font-weight:800; }
      .wh-center { margin: 0 auto; width: 100%; max-width: min(600px, calc(100% - 220px)); text-align: center; position: relative; z-index: 2; }
      .wh-row { position: relative; display: flex; justify-content: center; }
      .wh-right { position: absolute; top: 50%; right: 40px; transform: translateY(-50%); display:flex; align-items:center; justify-content:center; width: auto; z-index: 1; }
      .wh-orb { position: relative; width:120px; height:120px; border-radius:50%; background: radial-gradient(circle at 35% 35%, rgba(255,255,255,.06), rgba(255,255,255,0) 60% ); border:1px solid var(--border); box-shadow: var(--shadow-md); }
      .wh-avatar { position:absolute; inset:8px; width: calc(100% - 16px); height: calc(100% - 16px); border-radius:50%; object-fit:cover; }
      .wh-ring { position:absolute; inset:-4px; border-radius:50%; background: conic-gradient(from 0deg, var(--primary-400), transparent 30%, var(--accent-400), transparent 60%, var(--primary-400)); opacity:.35; animation: spin-slow 10s linear infinite; }
      @media (prefers-reduced-motion: reduce) { .wh-ring { animation: none; } }
      @keyframes spin-slow { to { transform: rotate(360deg); } }
      .wh-accent { position:absolute; filter: blur(14px); opacity:.18; pointer-events:none; }
      .wh-accent-1 { width:260px; height:260px; top:-80px; left:-40px; background: radial-gradient(circle, var(--primary-400), transparent 60%); }
      .wh-accent-2 { width:240px; height:240px; bottom:-100px; right:-60px; background: radial-gradient(circle, var(--accent-400), transparent 60%); }
      @media (max-width: 1279px) { .wh-row { flex-direction: column; padding-right: 0; } .wh-right { position: static; transform: none; margin: 16px auto 0; justify-content: center; } .wh-center { max-width: 600px; } }
      @media (max-width: 540px) { .wh-orb { width: 96px; height: 96px; } }
    </style>
    <div class="card welcome-hero">
        <div class="wh-accent wh-accent-1"></div>
        <div class="wh-accent wh-accent-2"></div>
        <?php if ($isLoggedIn): ?>
            <?php // Build profile image path (user-specific or fallback by role)
            if (!empty($_SESSION["user"]["profile_picture"]) && file_exists("./src/public/images/profile-pictures/" . $_SESSION["user"]["profile_picture"])) {
                $imagePath = "./src/public/images/profile-pictures/" . htmlspecialchars($_SESSION["user"]["profile_picture"]);
            } else {
                $userRole = $_SESSION["user"]["role"] ?? (($_SESSION["user"]["login"] ?? "") === "admin" ? "admin" : "user");
                $profilePic = $userRole === "admin" ? "admin-pfp.jpg" : "user-pfp.jpg";
                $imagePath = "./src/public/images/" . $profilePic;
            } ?>

            <div class="wh-row">
                <div class="wh-center">
                    <h2 class="wh-title wh-greeting">Hi <span class="wh-name"><?php echo htmlspecialchars($_SESSION["user"]["name"] ?: $_SESSION["user"]["login"]); ?></span></h2>
                    <div class="wh-meta">@<?php echo htmlspecialchars($_SESSION["user"]["login"]); ?> • <?php echo htmlspecialchars($_SESSION["user"]["role"] ?? 'user'); ?></div>
                </div>
                <div class="wh-right">
                    <div class="wh-orb">
                        <div class="wh-ring"></div>
                        <img class="wh-avatar" src="<?php echo $imagePath; ?>" alt="Profile picture">
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="wh-row">
                <div class="wh-center">
                    <h2 class="wh-title">Discover Sulfur</h2>
                    <div class="wh-meta">Secure • Fast • Polished</div>
                </div>
                <div class="wh-right">
                    <div class="wh-orb">
                        <div class="wh-ring"></div>
                        <img class="wh-avatar" src="./src/public/images/logo.png" alt="Sulfur">
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
</body>
</html>
