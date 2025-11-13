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
require_once "../models/user-functions-db.php";
require_once "../security/csrf.php";
require_once __DIR__ . "/../security/headers.php";
require_once __DIR__ . "/../security/session_guard.php";
csrf_ensure_initialized();
apply_default_security_headers();
session_enforce_password_rotation();

if (!isset($_SESSION["user"])) {
    header(
        "Location: /LoginPage/src/app/controllers/login.php?redirect=profile",
    );
    exit();
}

$user = $_SESSION["user"];

// Fetch fresh user data from database to get updated profile picture
try {
    $stmt = $db->query("SELECT * FROM users WHERE id = ?", [$user["id"]]);
    $dbUser = $stmt->fetch();
    if ($dbUser) {
        // Update session with fresh data
        $_SESSION["user"] = array_merge($_SESSION["user"], $dbUser);
        $user = $_SESSION["user"];
    }
} catch (Exception $e) {
    // If database fetch fails, continue with session data
    error_log("Failed to fetch user data: " . $e->getMessage());
}

// Optional admin search/filter inputs via GET
$searchQ = isset($_GET["q"]) ? trim((string) $_GET["q"]) : "";
$filterRole = isset($_GET["role"]) ? trim((string) $_GET["role"]) : "";

if ($filterRole === "all") {
    $filterRole = "";
}

if (
    $searchQ !== "" ||
    ($filterRole !== "" && in_array($filterRole, ["admin", "user"], true))
) {
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
	<title>Profile • Your Account</title>
	<link rel="icon" type="image/png" href="/LoginPage/src/public/images/logo.png">
    <link rel="stylesheet" href="/LoginPage/src/public/css/style.css?v=<?php echo filemtime(__DIR__ . '/../../public/css/style.css'); ?>">
    <link rel="stylesheet" href="/LoginPage/src/public/css/profile-page.css?v=<?php echo filemtime(__DIR__ . '/../../public/css/profile-page.css'); ?>">
	<script src="/LoginPage/src/public/js/toast.js?v=<?php echo filemtime(__DIR__ . '/../../public/js/toast.js'); ?>" defer></script>
	<script src="/LoginPage/src/public/js/form-utils.js?v=<?php echo filemtime(__DIR__ . '/../../public/js/form-utils.js'); ?>" defer></script>
	<script src="/LoginPage/src/public/js/heartbeat.js?v=<?php echo filemtime(__DIR__ . '/../../public/js/heartbeat.js'); ?>" defer></script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
    <script src="/LoginPage/src/public/js/profile-page.js?v=<?php echo filemtime(__DIR__ . '/../../public/js/profile-page.js'); ?>" defer></script>
</head>
<body>
	<?php
 $NAV_BASE = "../../";
 include __DIR__ . "/../../public/partials/navbar.php";
 ?>
	<div class="container page">
		<div class="card">
			<?php
   // Compute profile image path
   if (
       !empty($user["profile_picture"]) &&
       file_exists(
           __DIR__ .
               "/../../public/images/profile-pictures/" .
               $user["profile_picture"],
       )
   ) {
       $imagePath =
           "/LoginPage/src/public/images/profile-pictures/" .
           htmlspecialchars($user["profile_picture"]);
   } else {
       $userRole =
           $user["role"] ??
           (($user["login"] ?? "") === "admin" ? "admin" : "user");
       $profilePic = $userRole === "admin" ? "admin-pfp.jpg" : "user-pfp.jpg";
       $imagePath = "/LoginPage/src/public/images/" . $profilePic;
   }
   $joined = isset($user["created_at"])
       ? date("M j, Y", strtotime($user["created_at"]))
       : "Unknown";
   $lastActiveText = "";
   try {
       $lastActiveText = getLastActiveFormatted(
           $user["last_active"] ?? null,
           $user["last_activity"] ?? null,
       );
   } catch (Exception $e) {
       $lastActiveText = "—";
   }
   $isVerified = isset($user["email_verified"])
       ? (bool) $user["email_verified"]
       : false;
   $isAdmin =
       ($user["role"] ??
           (($user["login"] ?? "") === "admin" ? "admin" : "user")) ===
       "admin";
   ?>

			<div class="profile-header">
				<div>
					<div class="profile-picture-container" title="Change profile photo">
						<img src="<?php echo $imagePath; ?>" alt="Profile picture" class="avatar-lg" id="profilePictureImg">
						<div class="upload-overlay"><span>Change photo</span></div>
					</div>
				</div>
				<div>
					<h1 class="profile-title"><?php echo htmlspecialchars($user["name"]); ?></h1>
					<p class="profile-subtitle">@<?php echo htmlspecialchars($user["login"]); ?></p>
					<div class="badges">
						<span class="badge <?php echo $isAdmin
          ? "admin"
          : "user"; ?>"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> <?php echo $isAdmin
    ? "Admin"
    : "User"; ?></span>
<span class="badge <?php echo $isVerified ? "verified" : "unverified"; ?>">
							<i class="fa-solid fa-circle-check" aria-hidden="true"></i>
							<?php echo $isVerified ? "Email verified" : "Email not verified"; ?>
						</span>
					</div>
				</div>
			</div>

			<div class="meta-grid">
				<div class="meta"><div class="label">Email</div><div class="value"><?php echo htmlspecialchars(
        $user["email"],
    ); ?></div></div>
				<div class="meta"><div class="label">Joined</div><div class="value"><?php echo htmlspecialchars(
        $joined,
    ); ?></div></div>
				<div class="meta"><div class="label">Last active</div><div class="value"><?php echo htmlspecialchars(
        $lastActiveText,
    ); ?></div></div>
			</div>

			<!-- Hidden upload form -->
			<form id="profilePictureForm" method="post" action="/LoginPage/src/app/controllers/upload-profile-picture.php" enctype="multipart/form-data" style="display: none;">
				<?php echo csrf_field(); ?>
				<input type="file" id="profilePictureInput" name="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp">
			</form>

			<?php if (
       !empty($_SESSION["upload_success"])
   ): ?><div class="alert success mt-3"><?php
echo htmlspecialchars($_SESSION["upload_success"]);
unset($_SESSION["upload_success"]);
?></div><?php endif; ?>
			<?php if (
       !empty($_SESSION["upload_error"])
   ): ?><div class="alert error mt-3"><?php
echo htmlspecialchars($_SESSION["upload_error"]);
unset($_SESSION["upload_error"]);
?></div><?php endif; ?>
			<?php if (
       !empty($_SESSION["profile_success"])
   ): ?><div class="alert success mt-3"><?php
echo htmlspecialchars($_SESSION["profile_success"]);
unset($_SESSION["profile_success"]);
?></div><?php endif; ?>
			<?php if (
       !empty($_SESSION["profile_error"])
   ): ?><div class="alert error mt-3"><?php
echo htmlspecialchars($_SESSION["profile_error"]);
unset($_SESSION["profile_error"]);
?></div><?php endif; ?>
			<?php if (
       !empty($_SESSION["password_success"])
   ): ?><div class="alert success mt-3"><?php
echo htmlspecialchars($_SESSION["password_success"]);
unset($_SESSION["password_success"]);
?></div><?php endif; ?>
			<?php if (
       !empty($_SESSION["password_error"])
   ): ?><div class="alert error mt-3"><?php
echo htmlspecialchars($_SESSION["password_error"]);
unset($_SESSION["password_error"]);
?></div><?php endif; ?>

				<div class="profile-grid">
					<div>
						<h3>Edit profile</h3>
						<form method="post" action="/LoginPage/src/app/controllers/update-profile.php" id="editProfileForm">
							<?php echo csrf_field(); ?>
							<label for="name">Display name</label>
							<input type="text" id="name" name="name" value="<?php echo htmlspecialchars(
          $user["name"],
      ); ?>" minlength="2" maxlength="60" required>
							<label for="username">Username</label>
							<input type="text" id="username" name="username" value="<?php echo htmlspecialchars(
          $user["login"],
      ); ?>" pattern="^[A-Za-z0-9_]{3,20}$" required>
<div class="profile-action-row mt-3">
							<button class="button primary" type="submit">Save changes</button>
							<a class="button" href="/LoginPage/src/app/controllers/change-email.php">Change email</a>
							<?php if (empty($user['twofa_enabled'])): ?>
								<a class="button" href="/LoginPage/src/app/controllers/enable-2fa.php">Enable 2FA</a>
							<?php else: ?>
								<a class="button" href="/LoginPage/src/app/controllers/disable-2fa.php">Disable 2FA</a>
								<a class="button" href="/LoginPage/src/app/controllers/generate-2fa-codes.php">Recovery codes</a>
							<?php endif; ?>
						</div>
						</form>
					</div>
					<div>
						<h3>Change password</h3>
						<form method="post" action="/LoginPage/src/app/controllers/change-password.php" id="changePasswordForm">
							<?php echo csrf_field(); ?>
							<label for="current_password">Current password</label>
							<input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
							<label for="new_password">New password</label>
							<input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
							<label for="confirm_password">Confirm new password</label>
							<input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
							<div class="mt-3">
								<button class="button" type="submit">Update password</button>
							</div>
						</form>
					</div>
				</div>
		</div>
	</div>

	<?php
 // Check if the user is admin (fallback to login for backward compatibility)
 $isAdmin =
     ($user["role"] ?? ($user["login"] === "admin" ? "admin" : "user")) ===
     "admin";
 if ($isAdmin): ?>
<div class="container page card-closer">
		<div class="card">
			<h2>All Users</h2>
                <?php
                // Pagination setup
                $perPage = 10;
                $page = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;
                $total = countUsersFiltered(
                    $searchQ !== "" ? $searchQ : null,
                    $filterRole !== "" ? $filterRole : null,
                );
                $totalPages = max(1, (int) ceil($total / $perPage));
                if ($page > $totalPages) {
                    $page = $totalPages;
                }
                $offset = ($page - 1) * $perPage;

                // Fetch current page
                $users = getUsersFilteredPaginated(
                    $searchQ !== "" ? $searchQ : null,
                    $filterRole !== "" ? $filterRole : null,
                    $perPage,
                    $offset,
                );

                // Helper for query strings
                function qp(array $extra = [])
                {
                    $base = [
                        "q" => isset($_GET["q"]) ? (string) $_GET["q"] : "",
                        "role" => isset($_GET["role"])
                            ? (string) $_GET["role"]
                            : "all",
                    ];
                    $q = array_merge($base, $extra);
                    // Clean defaults
                    if ($q["role"] === "" || $q["role"] === "all") {
                        unset($q["role"]);
                    }
                    if ($q["q"] === "") {
                        unset($q["q"]);
                    }
                    return http_build_query($q);
                }
                ?>
                <form method="get" action="/LoginPage/src/app/controllers/profile.php" class="mt-3" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <input type="hidden" name="page" value="1" />
                    <input type="search" name="q" placeholder="Search name, username, email" value="<?php echo htmlspecialchars(
                        $searchQ,
                    ); ?>" style="flex:1; min-width:220px;" />
                    <select name="role" aria-label="Filter by role">
                        <?php
                        $roleOptions = [
                            "all" => "All roles",
                            "admin" => "Admins",
                            "user" => "Users",
                        ];
                        $currentRole = $filterRole === "" ? "all" : $filterRole;
                        foreach ($roleOptions as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo $currentRole ===
$val
    ? "selected"
    : ""; ?>><?php echo $label; ?></option>
                        <?php endforeach;
                        ?>
                    </select>
                    <button class="button" type="submit">Apply</button>
                    <a class="button" href="/LoginPage/src/app/controllers/profile.php">Reset</a>
                </form>
                <!--suppress CssUnresolvedCustomProperty -->
            <div class="mt-2" style="font-size: 0.95em; color: var(--muted-text);">
                    <?php
                    $showingStart = $total ? $offset + 1 : 0;
                    $showingEnd = min($offset + $perPage, $total);
                    echo "Showing " .
                        $showingStart .
                        "–" .
                        $showingEnd .
                        " of " .
                        $total;
                    ?>
                </div>
                <?php if (
                    !empty($_GET["msg"]) &&
                    $_GET["msg"] === "deleted"
                ): ?>
                    <p class="alert success mt-3">User deleted.</p>
                <?php endif; ?>
                <?php if (
                    !empty($_GET["msg"]) &&
                    $_GET["msg"] === "promoted"
                ): ?>
                    <p class="alert success mt-3">User promoted to admin.</p>
                <?php endif; ?>
                <?php if (
                    !empty($_GET["msg"]) &&
                    $_GET["msg"] === "demoted"
                ): ?>
                    <p class="alert success mt-3">User demoted to user.</p>
                <?php endif; ?>
                <?php if (!empty($_GET["error"])): ?>
                    <p class="alert error mt-3"><?php echo htmlspecialchars(
                        $_GET["error"],
                    ); ?></p>
                <?php endif; ?>
                <?php if (empty($users)): ?>
                    <p class="mt-3">No users found for the current filter.</p>
                <?php endif; ?>
				<div class="users-list">
					<?php foreach ($users as $userData): ?>
                        <div class="user-item" data-user-id="<?php echo (int) ($userData[
                            "id"
                        ] ?? 0); ?>">
                        <div class="user-avatar">
                            <?php // Check if user has custom profile picture
                            if (
                                !empty($userData["profile_picture"]) &&
                                file_exists(
                                    __DIR__ .
                                        "/../../public/images/profile-pictures/" .
                                        $userData["profile_picture"],
                                )
                            ) {
                                $userImagePath =
                                    "/LoginPage/src/public/images/profile-pictures/" .
                                    htmlspecialchars(
                                        $userData["profile_picture"],
                                    );
                            } else {
                                // Fallback to default based on role
                                $userProfilePic =
                                    $userData["role"] === "admin"
                                        ? "admin-pfp.jpg"
                                        : "user-pfp.jpg";
                                $userImagePath =
                                    "/LoginPage/src/public/images/" .
                                    $userProfilePic;
                            } ?>
                            <img src="<?php echo $userImagePath; ?>" alt="<?php echo htmlspecialchars(
    $userData["name"],
); ?>" class="user-avatar-img">
                            <span class="online-dot" aria-label="Online" title="Online"></span>
                        </div>
						<div class="user-info">
							<div class="user-name">
								<h3><?php echo htmlspecialchars($userData["name"]); ?></h3>
								<p class="user-login">@<?php echo htmlspecialchars(
            $userData["username"],
        ); ?></p>
							</div>
							<div class="user-email">
								<p><?php echo htmlspecialchars($userData["email"]); ?></p>
							</div>
						</div>
							<div class="user-last-active">
								<p class="last-active-label">Last Active:</p>
								<p class="last-active-time"><?php try {
            echo getLastActiveFormatted(
                $userData["last_active"] ?? null,
                $userData["last_activity"] ?? null,
            );
        } catch (Exception $e) {
            error_log("getLastActiveFormatted error: " . $e->getMessage());
        } ?></p>
							</div>
                            <div class="user-actions">
                                <?php if (
                                    ($userData["id"] ?? null) !==
                                    ($user["id"] ?? null)
                                ): ?>
                                    <?php if (
                                        ($userData["role"] ?? "user") !==
                                        "admin"
                                    ): ?>
                                    <form method="post" action="/LoginPage/src/app/controllers/make-admin.php" onsubmit="return confirm('Promote this user to admin?');" style="display:inline; margin-top: 0; margin-right: 8px;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo (int) ($userData[
                                            "id"
                                        ] ?? 0); ?>">
                                        <button class="button" type="submit">Make Admin</button>
                                    </form>
                                    <?php else: ?>
                                    <form method="post" action="/LoginPage/src/app/controllers/make-user.php" onsubmit="return confirm('Demote this admin to user?');" style="display:inline; margin-top: 0; margin-right: 8px;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo (int) ($userData[
                                            "id"
                                        ] ?? 0); ?>">
                                        <button class="button" type="submit">Make User</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="post" action="/LoginPage/src/app/controllers/delete-user.php" onsubmit="return confirm('Delete this user?');" style="display:inline; margin-top: 0;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo (int) ($userData[
                                            "id"
                                        ] ?? 0); ?>">
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
                    <a class="button" href="/LoginPage/src/app/controllers/profile.php?<?php echo qp(
                        ["page" => 1],
                    ); ?>" aria-label="First page">« First</a>
                    <a class="button" href="/LoginPage/src/app/controllers/profile.php?<?php echo qp(
                        ["page" => $prev],
                    ); ?>" aria-label="Previous page">‹ Prev</a>
                    <?php
                    // Windowed page numbers
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a class="button<?php echo $i === $page
                            ? " active"
                            : ""; ?>" href="/LoginPage/src/app/controllers/profile.php?<?php echo qp(
    ["page" => $i],
); ?>"><?php echo $i; ?></a>
                    <?php endfor;
                    ?>
                    <a class="button" href="/LoginPage/src/app/controllers/profile.php?<?php echo qp(
                        ["page" => $next],
                    ); ?>" aria-label="Next page">Next ›</a>
                    <a class="button" href="/LoginPage/src/app/controllers/profile.php?<?php echo qp(
                        ["page" => $totalPages],
                    ); ?>" aria-label="Last page">Last »</a>
                </nav>
                <?php endif; ?>
		</div> <!-- .card -->
	</div> <!-- .container -->
	<?php endif;
 ?>
	<div class="demo-warning">*This is a demo version of the website</div>
</body>
</html>
