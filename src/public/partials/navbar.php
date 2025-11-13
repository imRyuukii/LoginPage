<?php
// Complete Navbar Remake - all fresh code
// Use absolute site base to ensure correct paths across all pages
$BASE_PATH = '/LoginPage/';

// Session detection
if (!isset($isLoggedIn)) { $isLoggedIn = isset($_SESSION['user']); }
$isAdmin = isset($_SESSION['user']) && ((($_SESSION['user']['role'] ?? '') === 'admin') || (($_SESSION['user']['login'] ?? '') === 'admin'));

// Helper to build paths
if (!function_exists('buildPath')) {
    function buildPath($path) {
        global $BASE_PATH;
        return rtrim($BASE_PATH, '/') . '/' . ltrim($path, '/');
    }
}
?>
<nav class="modern-navbar navbar" id="navbar" role="navigation">
    <div class="navbar-wrapper navbar-container container">
        <!-- Brand section -->
        <div class="brand-section">
            <a href="<?= htmlspecialchars(buildPath('index.php')) ?>" class="brand brand-link">
                <img src="<?= htmlspecialchars(buildPath('src/public/images/logo.png')) ?>" alt="Logo" class="brand-icon brand-logo">
                <span class="brand-name brand-title">Sulfur</span>
            </a>
        </div>

        <!-- Desktop navigation -->
        <div class="nav-content nav-group" id="navContent" aria-label="Primary" role="menubar">
            <!-- Navigation links -->
            <ul class="nav-menu nav-links" role="menu">
                <li role="none">
<a href="<?= htmlspecialchars(buildPath('index.php')) ?>" class="nav-item nav-link" role="menuitem">
                        <i class="fa-solid fa-compass" aria-hidden="true"></i>
                        <span class="nav-text">Home</span>
                    </a>
                </li>
                <li role="none">
                    <a href="<?= htmlspecialchars(buildPath('demo-features.php')) ?>" class="nav-item nav-link" role="menuitem">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        <span class="nav-text">Demo</span>
                    </a>
                </li>
                <?php if ($isLoggedIn && $isAdmin): ?>
                <li role="none">
                    <a href="<?= htmlspecialchars(buildPath('src/app/controllers/admin-audit.php')) ?>" class="nav-item nav-link" role="menuitem">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span class="nav-text">Audit</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Action buttons -->
            <div class="nav-actions">
                <?php if ($isLoggedIn): ?>
                    <a href="<?= htmlspecialchars(buildPath('src/app/controllers/profile.php')) ?>" class="action-btn button">
                        <i class="fa-regular fa-user"></i>
                        <span>Profile</span>
                    </a>
                    <form method="post" action="<?= htmlspecialchars(buildPath('src/app/controllers/logout.php')) ?>" class="logout-form inline">
                        <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                        <button type="submit" class="action-btn button logout-btn">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(buildPath('src/app/controllers/login.php')) ?>" class="action-btn button">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <span>Login</span>
                    </a>
                    <a href="<?= htmlspecialchars(buildPath('src/app/controllers/register.php')) ?>" class="action-btn button primary">
                        <i class="fa-solid fa-user-plus"></i>
                        <span>Register</span>
                    </a>
                <?php endif; ?>
                
                <!-- Theme toggle -->
                <button class="theme-btn theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <i class="fa-solid fa-moon moon-icon"></i>
                    <i class="fa-solid fa-sun sun-icon"></i>
                </button>
            </div>
        </div>

        <!-- Mobile hamburger -->
<button class="hamburger nav-toggle" id="navToggle" aria-label="Menu" aria-expanded="false" aria-controls="navContent">
            <i class="fa-solid fa-bars icon-bars-vert" aria-hidden="true"></i>
            <i class="fa-solid fa-xmark icon-close" aria-hidden="true"></i>
        </button>
    </div>

    <!-- Mobile backdrop -->
    <div class="mobile-backdrop nav-overlay" id="navOverlay" aria-hidden="true"></div>
</nav>

<script src="<?= htmlspecialchars(buildPath('src/public/js/theme.js')) ?>?v=<?= filemtime(__DIR__ . '/../js/theme.js') ?>" defer></script>
<script src="<?= htmlspecialchars(buildPath('src/public/js/navbar.js')) ?>?v=<?= filemtime(__DIR__ . '/../js/navbar.js') ?>" defer></script>
