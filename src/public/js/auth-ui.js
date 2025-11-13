// src/public/js/auth-ui.js
(function(){
  function togglePassword(btn) {
    var input = btn && btn.previousElementSibling;
    if (!input || input.tagName !== 'INPUT') return;
    if (input.type !== 'password' && input.type !== 'text') return;
    var toType = input.type === 'password' ? 'text' : 'password';
    input.type = toType;
    // Swap icons inside the button if present
    var eyes = btn.querySelectorAll('i');
    if (eyes && eyes.length >= 1) {
      for (var i=0;i<eyes.length;i++) {
        var el = eyes[i];
        if (el.classList.contains('fa-eye') || el.classList.contains('fa-eye-slash')) {
          if (toType === 'text') { el.classList.toggle('fa-eye', false); el.classList.toggle('fa-eye-slash', true); }
          else { el.classList.toggle('fa-eye', true); el.classList.toggle('fa-eye-slash', false); }
        }
      }
    }
    btn.setAttribute('aria-label', toType === 'text' ? 'Hide password' : 'Show password');
  }

  function initPasswordToggles() {
    document.addEventListener('click', function(e){
      var btn = e.target.closest('.input-action');
      if (!btn) return;
      togglePassword(btn);
    });
  }

  function initToasts() {
    try {
      var ok = document.querySelector('.alert.success');
      var err = document.querySelector('.alert.error');
      if (ok && window.Toast) { Toast.success(ok.textContent.trim(), 4500); }
      if (err && window.Toast) { Toast.error(err.textContent.trim(), 6000); }
    } catch (e) {}
  }

  function initForms() {
    if (!window.FormUtils) return;
    var forms = document.querySelectorAll('form');
    Array.prototype.forEach.call(forms, function(f){
      try { FormUtils.preventDoubleSubmit(f); } catch (e) {}
    });
  }

  function init(){
    initPasswordToggles();
    initToasts();
    initForms();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
