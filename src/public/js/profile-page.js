// src/public/js/profile-page.js
(function() {
  function getCsrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function installHeartbeat() {
    var token = getCsrf();
    if (window.Heartbeat && typeof window.Heartbeat.installHeartbeatOnLoad === 'function') {
      window.Heartbeat.installHeartbeatOnLoad({ url: '/LoginPage/src/public/api/heartbeat.php', csrf: token });
    }
  }

  function initProfileUpload() {
    var img = document.getElementById('profilePictureImg');
    var input = document.getElementById('profilePictureInput');
    var form = document.getElementById('profilePictureForm');
    if (!img || !input || !form) return;
    img.addEventListener('click', function() { input.click(); });
    input.addEventListener('change', function() {
      if (!this.files || !this.files[0]) return;
      var file = this.files[0];
      var maxSize = 2 * 1024 * 1024; // 2MB
      var allowed = ['image/jpeg','image/png','image/gif','image/webp'];
      if (file.size > maxSize) { alert('File size exceeds 2MB limit.'); this.value=''; return; }
      if (allowed.indexOf(file.type) === -1) { alert('Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.'); this.value=''; return; }
      if (confirm('Upload this image as your profile picture?')) { form.submit(); }
    });
  }

  function initFormUtils() {
    if (!window.FormUtils) return;
    var ep = document.getElementById('editProfileForm');
    var un = document.getElementById('username');
    if (ep) { FormUtils.preventDoubleSubmit(ep); }
    if (un) { FormUtils.setupUsernameValidation(un); }
    var cp = document.getElementById('changePasswordForm');
    var np = document.getElementById('new_password');
    var cf = document.getElementById('confirm_password');
    if (cp) { FormUtils.preventDoubleSubmit(cp); }
    if (np) { FormUtils.setupPasswordValidation(np, true); }
    if (np && cf) { FormUtils.setupPasswordConfirmation(np, cf); }
  }

  function initToasts() {
    try {
      var ok = document.querySelector('.alert.success');
      var err = document.querySelector('.alert.error');
      if (ok && window.Toast) { Toast.success(ok.textContent.trim(), 3500); }
      if (err && window.Toast) { Toast.error(err.textContent.trim(), 4500); }
    } catch (e) {}
  }

  function initLastActive() {
    // Only run if admin user list is present
    if (!document.querySelector('.user-item[data-user-id]')) return;
    var ENDPOINT = '/LoginPage/src/public/api/users/last-activity.php';

    function visibleIds() {
      return Array.prototype.slice.call(document.querySelectorAll('.user-item[data-user-id]'))
        .map(function(el){ return el.getAttribute('data-user-id'); })
        .filter(function(v){ return v && /^\d+$/.test(v); })
        .join(',');
    }

    function apply(data) {
      if (!data) return;
      var arr = Array.isArray(data) ? data : (data.users || data.data || []);
      if (!Array.isArray(arr)) return;
      arr.forEach(function(u){
        var root = document.querySelector('.user-item[data-user-id="' + u.id + '"]');
        if (!root) return;
        var el = root.querySelector('.last-active-time');
        if (el && (u.last_active_text || u.lastActiveText)) {
          el.textContent = u.last_active_text || u.lastActiveText;
        }
        var avatar = root.querySelector('.user-avatar');
        if (avatar && typeof u.online !== 'undefined') {
          avatar.classList.toggle('online', !!u.online);
        }
      });
    }

    function tick() {
      var url = ENDPOINT + '?t=' + Date.now() + '&ids=' + encodeURIComponent(visibleIds());
      fetch(url, { headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' }, cache: 'no-store' })
        .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
        .then(apply)
        .catch(function(){});
    }

    window.__refreshLastActive = tick;
    if (window.__deferLastActiveRefresh) { tick(); window.__deferLastActiveRefresh = false; }
    tick();
    setInterval(tick, 10000);
  }

  function init() {
    installHeartbeat();
    initProfileUpload();
    initFormUtils();
    initToasts();
    initLastActive();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
