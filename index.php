<?php
// Harden session cookie and start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
session_start();
require_once './src/app/models/user-functions-db.php';
require_once './src/app/security/csrf.php';
csrf_ensure_initialized();
$isLoggedIn = isset($_SESSION['user']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login Page</title>
	<link rel="icon" type="image/png" href="./src/public/images/logo.png">
	<link rel="stylesheet" href="./src/public/css/style.css">
	<script src="./src/public/js/heartbeat.js" defer></script>
</head>
<body>
	<button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
	<img src="./src/public/images/logo.png" alt="Logo" class="logo-website-top-left">
    <div class="logo-shadow"></div>
	<div class="container page">
		<div class="card">
			<?php if ($isLoggedIn): ?>
				<?php 
				// PFP based on a user role (fallback to login for backward compatibility)
				$userRole = $_SESSION['user']['role'] ?? ($_SESSION['user']['login'] === 'admin' ? 'admin' : 'user');
				$profilePic = ($userRole === 'admin') ? 'admin-pfp.jpg' : 'user-pfp.jpg';
				$imagePath = "./src/public/images/" . $profilePic;
				?>
				<img src="<?php echo $imagePath; ?>" alt="Profile Picture" class="profile-picture">
			<?php endif; ?>
			<h1 class="welcome-text">WELCOME</h1>
			<?php if ($isLoggedIn): ?>
				<p>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['user']['login']); ?></strong></p>
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
	</div>
	<div class="demo-warning">*This is a demo version of the website</div>
    <script>
    (function() {
        const CSRF_TOKEN = '<?php echo htmlspecialchars(csrf_token()); ?>';
        const IS_LOGGED_IN = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
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
    })();
    </script>
</body>
</html>
