// src/public/js/navbar.js (remade with fallbacks)
(function() {
  function initNav() {
    var navbar = document.getElementById('navbar') || document.querySelector('.modern-navbar, .navbar');
    var hamburger = document.getElementById('hamburger') || document.getElementById('navToggle');
    var panel = document.getElementById('navContent') || document.getElementById('primary-menu');
    var backdrop = document.getElementById('mobileBackdrop') || document.getElementById('navOverlay');
    var themeBtn = document.getElementById('themeBtn') || document.getElementById('themeToggle');
    if (!navbar || !hamburger || !panel) return;

    var mq = window.matchMedia ? window.matchMedia('(max-width: 820px)') : null;
    var isMobile = function(){ return mq ? mq.matches : window.innerWidth <= 820; };

    // Sync theme button state on init
    var currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
    if (themeBtn) themeBtn.setAttribute('aria-pressed', currentTheme === 'dark' ? 'true' : 'false');
    if (document.body) document.body.setAttribute('data-theme', currentTheme);

    function applyPanelState(open) {
      if (!isMobile()) {
        panel.style.maxHeight = '';
        panel.style.opacity = '';
        panel.style.pointerEvents = '';
        panel.style.transform = '';
        document.documentElement.classList.remove('no-scroll');
        return;
      }
      panel.style.setProperty('--menu-height', open ? (panel.scrollHeight + 'px') : '0px');
      panel.style.maxHeight = open ? (panel.scrollHeight + 'px') : '0px';
      panel.style.opacity = open ? '1' : '0';
      panel.style.pointerEvents = open ? 'auto' : 'none';
      panel.style.transform = open ? 'translateY(0)' : 'translateY(-6px)';
      document.documentElement.classList.toggle('no-scroll', !!open);
      document.body && document.body.classList && document.body.classList.toggle('no-scroll', !!open);
    }

    function setOpen(open) {
      navbar.classList.toggle('open', !!open);
      hamburger.setAttribute('aria-expanded', open ? 'true' : 'false');
      hamburger.setAttribute('aria-label', open ? 'Close menu' : 'Menu');
      applyPanelState(!!open);
    }

    hamburger.addEventListener('click', function(e){ e.preventDefault(); setOpen(!navbar.classList.contains('open')); });

    // Close when clicking any link inside panel on mobile
    panel.addEventListener('click', function(e){ var a = e.target.closest('a'); if (a && isMobile()) setOpen(false); });

    // Backdrop click closes
    if (backdrop) backdrop.addEventListener('click', function(){ if (isMobile()) setOpen(false); });

    // ESC to close
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && navbar.classList.contains('open')) setOpen(false); });

    // Resize handling
    window.addEventListener('resize', function(){ if (!isMobile()) { navbar.classList.remove('open'); applyPanelState(false); } else { applyPanelState(navbar.classList.contains('open')); } });

  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initNav); else initNav();
})();
