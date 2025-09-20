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
require_once '../models/user-functions-db.php';
require_once '../security/csrf.php';
csrf_ensure_initialized();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	csrf_require_post();
	$login = isset($_POST['login']) ? trim($_POST['login']) : '';
	$password = isset($_POST['password']) ? trim($_POST['password']) : '';

	$user = loginUser($login, $password);
	
	if ($user) {
		// Update last active timestamp
		updateLastActive($user['id']);
		
		// Regenerate session ID on login to prevent fixation
		session_regenerate_id(true);
		$_SESSION['user'] = [
			'id' => $user['id'],
			'login' => $user['username'],
			'name' => $user['name'],
			'email' => $user['email'],
			'role' => $user['role'],
		];
		header('Location: ./profile.php');
		exit;
	} else {
		$error = 'Invalid login or password.';
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login</title>
	<link rel="icon" type="image/png" href="../../public/images/logo.png">
	<link rel="stylesheet" href="../../public/css/style.css">
</head>
<body>
	<button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
	<img src="../../public/images/logo.png" alt="Logo" class="logo-website-top-left">
	<div class="logo-shadow"></div>
	<div class="container page">
		<div class="card">
			<?php if (!empty($error)): ?>
				<p class="alert error mt-3"><?php echo htmlspecialchars($error); ?></p>
			<?php endif; ?>
			<form method="post" action="">
				<?php echo csrf_field(); ?>
				<label for="login">Login</label>
				<input type="text" id="login" name="login" required>

				<label for="password">Password</label>
				<input type="password" id="password" name="password" required>

				<div class="link-row centered mt-4">
					<button class="button primary" type="submit">Login</button>
					<a class="button" href="../../../index.php">Back to home</a>
				</div>
			</form>
			<p class="footer mt-6">
				Don't have an account? <a href="./register.php">Register here</a>
			</p>
		</div>
	</div>
	<div class="demo-warning">*This is a demo version of the website</div>
    <script>
    (function() {
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
    })();
    </script>
</body>
</html>
