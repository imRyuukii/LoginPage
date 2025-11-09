<?php
// Reusable Navbar Partial (root-relative for stable links under /LoginPage/)
// Deployed base path for the app. Adjust if the app is hosted under a different subdirectory.
$BASE_PATH = '/LoginPage/';

// Backward compatibility: if caller sets $isLoggedIn, honor it; otherwise detect from session
if (!isset($isLoggedIn)) { $isLoggedIn = isset($_SESSION['user']); }

// Helper to safely join BASE_PATH with a path
$__abs = function(string $path) use ($BASE_PATH): string {
    $base = rtrim($BASE_PATH, '/') . '/';
    $path = ltrim($path, '/');
    return $base . $path;
};
?>
<nav class="navbar" role="navigation" aria-label="Main">
    <div class="navbar-container container">
        <a class="brand-link" href="<?php echo htmlspecialchars($__abs('index.php')); ?>">
            <img class="brand-logo" src="<?php echo htmlspecialchars($__abs('src/public/images/logo.png')); ?>" alt="Sulfur logo">
            <span class="brand-title">Sulfur</span>
        </a>

        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="primary-menu">
            <i class="fa-solid fa-bars icon-bars" aria-hidden="true"></i>
            <i class="fa-solid fa-xmark icon-close" aria-hidden="true"></i>
        </button>

        <div class="nav-group" id="primary-menu">
            <ul class="nav-links" role="menubar">
                <li role="none"><a role="menuitem" class="nav-link" href="<?php echo htmlspecialchars($__abs('index.php')); ?>">Home</a></li>
                <li role="none"><a role="menuitem" class="nav-link" href="<?php echo htmlspecialchars($__abs('demo-features.html')); ?>">Demo</a></li>
            </ul>

            <div class="nav-actions">
                <?php if ($isLoggedIn): ?>
                    <a class="button" href="<?php echo htmlspecialchars($__abs('src/app/controllers/profile.php')); ?>">Profile</a>
                    <form method="post" action="<?php echo htmlspecialchars($__abs('src/app/controllers/logout.php')); ?>" class="inline">
                        <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                        <button type="submit" class="button">Logout</button>
                    </form>
                <?php else: ?>
                    <a class="button" href="<?php echo htmlspecialchars($__abs('src/app/controllers/login.php')); ?>">Login</a>
                    <a class="button primary" href="<?php echo htmlspecialchars($__abs('src/app/controllers/register.php')); ?>">Register</a>
                <?php endif; ?>
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
            </div>
        </div>
    </div>
</nav>
<script>
// Minimal, self-contained hamburger toggle for mobile
(function() {
  function initNav() {
    var navbar = document.querySelector('.navbar');
    var toggle = document.getElementById('navToggle');
    var panel = document.getElementById('primary-menu');
    if (!navbar || !toggle || !panel) return;

    var mq = window.matchMedia ? window.matchMedia('(max-width: 820px)') : null;

    function isMobile() {
      return mq ? mq.matches : window.innerWidth <= 820;
    }

    function setOpen(open) {
      navbar.classList.toggle('open', !!open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function(e) {
      e.preventDefault();
      var nowOpen = !navbar.classList.contains('open');
      setOpen(nowOpen);
    });

    // Close when a nav link is clicked (mobile only)
    panel.addEventListener('click', function(e) {
      var link = e.target.closest('a');
      if (link && isMobile()) {
        setOpen(false);
      }
    });

    // Click outside to close (mobile only)
    document.addEventListener('click', function(e) {
      if (!isMobile()) return;
      if (!navbar.classList.contains('open')) return;
      if (!e.target.closest('.navbar')) {
        setOpen(false);
      }
    });

    // ESC to close
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && navbar.classList.contains('open')) {
        setOpen(false);
      }
    });

    // On resize across breakpoint, ensure proper state
    window.addEventListener('resize', function() {
      if (!isMobile()) {
        setOpen(false);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNav);
  } else {
    initNav();
  }
})();
</script>
