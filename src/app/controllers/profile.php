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

if (!isset($_SESSION['user'])) {
	header('Location: login.php?redirect=profile');
	exit;
}

$user = $_SESSION['user'];

// Optional admin search/filter inputs via GET
$searchQ = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$filterRole = isset($_GET['role']) ? trim((string)$_GET['role']) : '';

if ($filterRole === 'all') { $filterRole = ''; }

if ($searchQ !== '' || ($filterRole !== '' && in_array($filterRole, ['admin', 'user'], true))) {
    $users = getUsersFiltered($searchQ, $filterRole);
} else {
    $users = getAllUsers();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>User Profile</title>
	<link rel="icon" type="image/png" href="../../public/images/logo.png">
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo time(); ?>">
	<script src="../../public/js/heartbeat.js" defer></script>
</head>
<body>
	<button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
	<img src="../../public/images/logo.png" alt="Logo" class="logo-website-top-left">
    <div class="logo-shadow"></div>
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
				<div class="link-row mt-4">
					<a class="button" href="../../../index.php">Home</a>
					<form method="post" action="./logout.php" style="display:inline;">
						<?php echo csrf_field(); ?>
						<button class="button" type="submit">Logout</button>
					</form>
				</div>
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
                <?php 
                    // Pagination setup
                    $perPage = 10;
                    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                    $total = countUsersFiltered($searchQ !== '' ? $searchQ : null, $filterRole !== '' ? $filterRole : null);
                    $totalPages = max(1, (int)ceil($total / $perPage));
                    if ($page > $totalPages) { $page = $totalPages; }
                    $offset = ($page - 1) * $perPage;

                    // Fetch current page
                    $users = getUsersFilteredPaginated($searchQ !== '' ? $searchQ : null, $filterRole !== '' ? $filterRole : null, $perPage, $offset);

                    // Helper for query strings
                    function qp(array $extra = []) {
                        $base = [
                            'q' => isset($_GET['q']) ? (string)$_GET['q'] : '',
                            'role' => isset($_GET['role']) ? (string)$_GET['role'] : 'all',
                        ];
                        $q = array_merge($base, $extra);
                        // Clean defaults
                        if ($q['role'] === '' || $q['role'] === 'all') unset($q['role']);
                        if ($q['q'] === '') unset($q['q']);
                        return http_build_query($q);
                    }
                ?>
                <form method="get" action="profile.php" class="mt-3" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <input type="hidden" name="page" value="1" />
                    <input type="search" name="q" placeholder="Search name, username, email" value="<?php echo htmlspecialchars($searchQ); ?>" style="flex:1; min-width:220px;" />
                    <select name="role" aria-label="Filter by role">
                        <?php $roleOptions = ['all' => 'All roles', 'admin' => 'Admins', 'user' => 'Users'];
                        $currentRole = ($filterRole === '' ? 'all' : $filterRole);
                        foreach ($roleOptions as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo $currentRole === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button" type="submit">Apply</button>
                    <a class="button" href="profile.php">Reset</a>
                </form>
                <div class="mt-2" style="font-size: 0.95em; color: var(--muted-text);">
                    <?php 
                        $showingStart = $total ? ($offset + 1) : 0; 
                        $showingEnd = min($offset + $perPage, $total);
                        echo 'Showing ' . $showingStart . '–' . $showingEnd . ' of ' . $total;
                    ?>
                </div>
                <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                    <p class="alert success mt-3">User deleted.</p>
                <?php endif; ?>
                <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'promoted'): ?>
                    <p class="alert success mt-3">User promoted to admin.</p>
                <?php endif; ?>
                <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'demoted'): ?>
                    <p class="alert success mt-3">User demoted to user.</p>
                <?php endif; ?>
                <?php if (!empty($_GET['error'])): ?>
                    <p class="alert error mt-3"><?php echo htmlspecialchars($_GET['error']); ?></p>
                <?php endif; ?>
                <?php if (empty($users)): ?>
                    <p class="mt-3">No users found for the current filter.</p>
                <?php endif; ?>
				<div class="users-list">
					<?php foreach ($users as $userData): ?>
						<div class="user-item" data-user-id="<?php echo (int)($userData['id'] ?? 0); ?>">
							<div class="user-avatar">
							<?php 
							$userProfilePic = ($userData['role'] === 'admin') ? 'admin-pfp.jpg' : 'user-pfp.jpg';
							?>
							<img src="../../public/images/<?php echo $userProfilePic; ?>" alt="<?php echo htmlspecialchars($userData['name']); ?>" class="user-avatar-img">
                                <span class="online-dot" aria-label="Online" title="Online"></span>
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
                                    error_log('getLastActiveFormatted error: ' . $e->getMessage());
                                } ?></p>
							</div>
                            <div class="user-actions">
                                <?php if (($userData['id'] ?? null) !== ($user['id'] ?? null)): ?>
                                    <?php if (($userData['role'] ?? 'user') !== 'admin'): ?>
                                    <form method="post" action="./make-admin.php" onsubmit="return confirm('Promote this user to admin?');" style="display:inline; margin-right: 8px;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo (int)($userData['id'] ?? 0); ?>">
                                        <button class="button" type="submit">Make Admin</button>
                                    </form>
                                    <?php else: ?>
                                    <form method="post" action="./make-user.php" onsubmit="return confirm('Demote this admin to user?');" style="display:inline; margin-right: 8px;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo (int)($userData['id'] ?? 0); ?>">
                                        <button class="button" type="submit">Make User</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="post" action="./delete-user.php" onsubmit="return confirm('Delete this user?');" style="display:inline;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo (int)($userData['id'] ?? 0); ?>">
                                        <button class="button" type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
			</div>
			</div> <!-- .user-item -->
			<?php endforeach; ?>
</div> <!-- .users-list -->
                <?php if ($totalPages > 1): ?>
                <nav class="mt-3" aria-label="Pagination" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                    <?php 
                        $prev = max(1, $page - 1);
                        $next = min($totalPages, $page + 1);
                    ?>
                    <a class="button" href="profile.php?<?php echo qp(['page' => 1]); ?>" aria-label="First page">« First</a>
                    <a class="button" href="profile.php?<?php echo qp(['page' => $prev]); ?>" aria-label="Previous page">‹ Prev</a>
                    <?php 
                        // Windowed page numbers
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a class="button<?php echo $i === $page ? ' active' : ''; ?>" href="profile.php?<?php echo qp(['page' => $i]); ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <a class="button" href="profile.php?<?php echo qp(['page' => $next]); ?>" aria-label="Next page">Next ›</a>
                    <a class="button" href="profile.php?<?php echo qp(['page' => $totalPages]); ?>" aria-label="Last page">Last »</a>
                </nav>
                <?php endif; ?>
		</div> <!-- .card -->
	</div> <!-- .container -->
	<?php endif; ?>
	<div class="demo-warning">*This is a demo version of the website</div>
    <script>
    (function() {
        const CSRF_TOKEN = '<?php echo htmlspecialchars(csrf_token()); ?>';
        window.addEventListener('DOMContentLoaded', function(){
            if (window.Heartbeat) {
                window.Heartbeat.installHeartbeatOnLoad({ url: '../../public/api/heartbeat.php', csrf: CSRF_TOKEN });
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
    })();
    
    // Heartbeat system for real-time online status
    (function() {
        let heartbeatInterval;
        let isPageVisible = true;

        function sendHeartbeat() {
            if (!isPageVisible) return Promise.resolve();
            return fetch('../../public/api/heartbeat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'csrf=' + encodeURIComponent(CSRF_TOKEN)
            }).catch(function(error) {
                console.log('Heartbeat failed:', error);
            });
        }
        
        // Send heartbeat every 30 seconds
        function startHeartbeat() {
            if (heartbeatInterval) clearInterval(heartbeatInterval);
            // After every heartbeat, trigger a live refresh so "Online" stays fresh even without navigation
            heartbeatInterval = setInterval(function(){
                sendHeartbeat().then(function(){
                    if (window.__refreshLastActive) { window.__refreshLastActive(); }
                });
            }, 30000);
            // Send initial heartbeat and immediately refresh the user list when it succeeds
            sendHeartbeat().then(function(){
                // Flag in case the live-update script hasn't loaded yet
                window.__deferLastActiveRefresh = true;
                if (window.__refreshLastActive) { window.__refreshLastActive(); }
            });
        }
        // Stop heartbeat when the page is hidden
        document.addEventListener('visibilitychange', function() {
            isPageVisible = !document.hidden;
            if (isPageVisible) {
                startHeartbeat();
            } else {
                if (heartbeatInterval) clearInterval(heartbeatInterval);
            }
        });
        
        // Start a heartbeat system
        startHeartbeat();
    })();
    </script>
    <script>
    // Live update "Last Active" for admin user list every 30s without page refresh
    (function() {
        const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
        if (!IS_ADMIN) return;
const ENDPOINT = '../../public/api/users/last-activity.php';
        function visibleIds() {
            return Array.from(document.querySelectorAll('.user-item[data-user-id]'))
                .map(function(el){ return el.getAttribute('data-user-id'); })
                .filter(function(v){ return v && /^\d+$/.test(v); })
                .join(',');
        }
        function apply(data) {
            if (!Array.isArray(data)) return;
            data.forEach(function(u){
                const root = document.querySelector('.user-item[data-user-id="' + u.id + '"]');
                if (!root) return;
                const el = root.querySelector('.last-active-time');
                if (el) { el.textContent = u.last_active_text; }
                const avatar = root.querySelector('.user-avatar');
                if (avatar) { avatar.classList.toggle('online', !!u.online); }
            });
        }
        function tick(){
const url = ENDPOINT + '?t=' + Date.now() + '&ids=' + encodeURIComponent(visibleIds());
            fetch(url, { headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' }, cache: 'no-store' })
                .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
                .then(apply)
                .catch(function(){});
        }
        // Expose a one-shot refresh so heartbeat can trigger an immediate UI update after login
        window.__refreshLastActive = tick;
        // If heartbeat already requested a refresh before this script loaded, run it now
        if (window.__deferLastActiveRefresh) { tick(); window.__deferLastActiveRefresh = false; }
        tick();
        setInterval(tick, 10000); // 10s for snappier updates
    })();
    </script>
</body>
</html>
