// src/public/js/theme.js
(function() {
  function applyTheme(theme) {
    try { localStorage.setItem('theme', theme); } catch (e) {}
    document.documentElement.setAttribute('data-theme', theme);
    if (document.body) document.body.setAttribute('data-theme', theme);
    var btn = document.getElementById('themeToggle');
    if (btn) btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
  }

  function toggleTheme() {
    var current = document.documentElement.getAttribute('data-theme') || 'dark';
    var next = current === 'dark' ? 'light' : 'dark';
    applyTheme(next);
  }

  function initTheme() {
    var pref = 'dark';
    try {
      var stored = localStorage.getItem('theme');
      if (stored === 'light' || stored === 'dark') pref = stored;
      else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) pref = 'light';
    } catch (e) {}
    applyTheme(pref);

    var btn = document.getElementById('themeToggle');
    if (btn) btn.addEventListener('click', function(e){ e.preventDefault(); toggleTheme(); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTheme);
  } else {
    initTheme();
  }
})();
