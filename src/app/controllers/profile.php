<?php
session_start();
require_once '../models/user-functions.php';

if (!isset($_SESSION['user'])) {
	header('Location: login.php?redirect=profile');
	exit;
}

$user = $_SESSION['user'];
$users = getAllUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>User Profile</title>
	<link rel="icon" type="image/png" href="../../public/images/logo.png">
	<link rel="stylesheet" href="../../public/css/style.css">
</head>
<body>
	<button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
	<img src="../../public/images/logo.png" alt="Logo" class="logo-website-top-left">
	<div class="container page">
		<div class="card">
			<?php 
			// PFP based on a user role (fallback to login for backward compatibility)
			$userRole = $user['role'] ?? ($user['login'] === 'admin' ? 'admin' : 'user');
			$profilePic = ($userRole === 'admin') ? 'admin-pfp.jpg' : 'user-pfp.jpg';
			$imagePath = "../../public/images/" . $profilePic;
			?>
			<img src="<?php echo $imagePath; ?>" alt="Profile Picture" class="profile-picture">
			<h1>User Profile</h1>
			<dl class="data mt-3">
				<dt>Login</dt>
				<dd><?php echo htmlspecialchars($user['login']); ?></dd>
				<dt>Name</dt>
				<dd><?php echo htmlspecialchars($user['name']); ?></dd>
				<dt>Email</dt>
				<dd><?php echo htmlspecialchars($user['email']); ?></dd>
			</dl>
			<p class="link-row mt-4">
				<a class="button" href="../../../index.php">Home</a>
				<a class="button" href="./logout.php">Logout</a>
			</p>
		</div>
	</div>
	
	<?php 
	// Check if the user is admin (fallback to login for backward compatibility)
	$isAdmin = ($user['role'] ?? ($user['login'] === 'admin' ? 'admin' : 'user')) === 'admin';
	if ($isAdmin): 
	?>
	<div class="container page card-closer">
		<div class="card">
			<h2>All Users</h2>
			<div class="users-list">
				<?php foreach ($users as $userData): ?>
					<div class="user-item">
						<div class="user-avatar">
							<?php 
							$userProfilePic = ($userData['role'] === 'admin') ? 'admin-pfp.jpg' : 'user-pfp.jpg';
							?>
							<img src="../../public/images/<?php echo $userProfilePic; ?>" alt="<?php echo htmlspecialchars($userData['name']); ?>" class="user-avatar-img">
						</div>
						<div class="user-info">
							<div class="user-name">
								<h3><?php echo htmlspecialchars($userData['name']); ?></h3>
								<p class="user-login">@<?php echo htmlspecialchars($userData['username']); ?></p>
							</div>
							<div class="user-email">
								<p><?php echo htmlspecialchars($userData['email']); ?></p>
							</div>
						</div>
						<div class="user-last-active">
							<p class="last-active-label">Last Active:</p>
							<p class="last-active-time"><?php try {
                                    echo getLastActiveFormatted($userData['last_active'] ?? null, $userData['last_activity'] ?? null);
                                } catch (Exception $e) {

                                } ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php endif; ?>
	
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
    
    // Heartbeat system for real-time online status
    (function() {
        let heartbeatInterval;
        let isPageVisible = true;

        function sendHeartbeat() {
            if (!isPageVisible) return;
            
            fetch('../../public/api/heartbeat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: ''
            }).catch(function(error) {
                console.log('Heartbeat failed:', error);
            });
        }
        
        // Send heartbeat every 30 seconds
        function startHeartbeat() {
            heartbeatInterval = setInterval(sendHeartbeat, 30000);
            // Send initial heartbeat
            sendHeartbeat();
        }
        // Stop heartbeat when the page is hidden
        document.addEventListener('visibilitychange', function() {
            isPageVisible = !document.hidden;
            if (isPageVisible) {
                startHeartbeat();
            } else {
                clearInterval(heartbeatInterval);
            }
        });
        
        // Start a heartbeat system
        startHeartbeat();
    })();
    </script>
</body>
</html>
