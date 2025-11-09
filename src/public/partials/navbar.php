<?php
// Reusable Navbar Partial
// Usage example:
//   $NAV_BASE = './'; // for root level pages
//   $NAV_BASE = '../'; // for pages one level deep
//   $NAV_BASE = '../../'; // for pages two levels deep
//   include __DIR__ . '/navbar.php';

if (!isset($NAV_BASE)) { $NAV_BASE = './'; }
if (!isset($isLoggedIn)) { $isLoggedIn = isset($_SESSION['user']); }

// Small helper to safely join base with a path
$__join = function(string $base, string $path): string {
    $base = rtrim($base, '/') . '/';
    $path = ltrim($path, '/');
    return $base . $path;
};
?>
<nav class="navbar" role="navigation" aria-label="Main">
    <div class="navbar-container container">
        <a class="brand-link" href="<?php echo htmlspecialchars($__join($NAV_BASE, 'index.php')); ?>">
            <img class="brand-logo" src="<?php echo htmlspecialchars($__join($NAV_BASE, 'assets/images/logo.png')); ?>" alt="Sulfur logo">
            <span class="brand-title">Sulfur</span>
        </a>

        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="primary-menu">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>

        <div class="nav-group" id="primary-menu">
            <ul class="nav-links" role="menubar">
                <li role="none"><a role="menuitem" class="nav-link" href="<?php echo htmlspecialchars($__join($NAV_BASE, 'index.php')); ?>">Home</a></li>
                <li role="none"><a role="menuitem" class="nav-link" href="<?php echo htmlspecialchars($__join($NAV_BASE, 'demo-features.html')); ?>">Demo</a></li>
            </ul>

            <div class="nav-actions">
                <?php if ($isLoggedIn): ?>
                    <a class="button" href="<?php echo htmlspecialchars($__join($NAV_BASE, 'app/controllers/profile.php')); ?>">Profile</a>
                    <form method="post" action="<?php echo htmlspecialchars($__join($NAV_BASE, 'app/controllers/logout.php')); ?>" class="inline">
                        <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                        <button type="submit" class="button">Logout</button>
                    </form>
                <?php else: ?>
                    <a class="button" href="<?php echo htmlspecialchars($__join($NAV_BASE, 'app/controllers/login.php')); ?>">Login</a>
                    <a class="button primary" href="<?php echo htmlspecialchars($__join($NAV_BASE, 'app/controllers/register.php')); ?>">Register</a>
                <?php endif; ?>
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
            </div>
        </div>
    </div>
</nav>
