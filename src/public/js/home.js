// src/public/js/home.js
(function(){
  function getMeta(name){ var m = document.querySelector('meta[name="'+name+'"]'); return m ? m.getAttribute('content') : ''; }

  // Ensure page starts at top on refresh (avoid tiny scroll from anchoring/layout shifts)
  (function ensureTop(){
    try { if ('scrollRestoration' in history) history.scrollRestoration = 'manual'; } catch (_) {}
    var toTop = function(){ window.scrollTo(0, 0); };
    if (document.readyState === 'complete') { toTop(); }
    else {
      window.addEventListener('load', function(){ setTimeout(toTop, 0); }, { once: true });
      document.addEventListener('DOMContentLoaded', function(){ setTimeout(toTop, 0); }, { once: true });
    }
  })();

  function installHeartbeat(){
    var isLoggedIn = getMeta('is-logged-in') === '1';
    var csrf = getMeta('csrf-token');
    if (!isLoggedIn) return;
    if (window.Heartbeat && typeof window.Heartbeat.installHeartbeatOnLoad === 'function') {
      window.Heartbeat.installHeartbeatOnLoad({ url: './src/public/api/heartbeat.php', csrf: csrf });
    }
  }

  function typewriter(){
    var messages = ["Try Logging in.", "Share Your Thoughts.", "Making an account is really simple."];
    var el = document.getElementById('typewriter');
    if (!el) return;
    var messageIndex = 0, charIndex = 0, isDeleting = false;
    var typingSpeed = 100, deletingSpeed = 50, pauseBeforeDelete = 2000, pauseBeforeNext = 500;
    function type(){
      var current = messages[messageIndex];
      if (isDeleting) {
        el.textContent = current.substring(0, charIndex - 1);
        charIndex--;
        if (charIndex === 0) { isDeleting = false; messageIndex = (messageIndex + 1) % messages.length; setTimeout(type, pauseBeforeNext); return; }
        setTimeout(type, deletingSpeed);
      } else {
        el.textContent = current.substring(0, charIndex + 1);
        charIndex++;
        if (charIndex === current.length) { isDeleting = true; setTimeout(type, pauseBeforeDelete); return; }
        setTimeout(type, typingSpeed);
      }
    }
    setTimeout(type, 500);
  }

  function countUp(){
    var el = document.querySelector('.stats-number[data-target]');
    if (!el) return;
    var target = parseInt(el.getAttribute('data-target') || '0', 10);
    if (!Number.isFinite(target)) return;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce || target <= 0) { el.textContent = target.toLocaleString(); return; }
    var duration = 900; var start = performance.now();
    function tick(now){
      var p = Math.min(1, (now - start) / duration);
      var eased = 1 - Math.pow(1 - p, 3);
      var val = Math.floor(eased * target);
      el.textContent = val.toLocaleString();
      if (p < 1) requestAnimationFrame(tick); else el.textContent = target.toLocaleString();
    }
    requestAnimationFrame(tick);
  }

  function init(){ installHeartbeat(); typewriter(); countUp(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
