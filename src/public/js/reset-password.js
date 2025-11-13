// src/public/js/reset-password.js
(function(){
  function init() {
    var passwordField = document.getElementById('password');
    var confirmField = document.getElementById('confirm_password');
    var strengthDiv = document.getElementById('passwordStrength');
    var strengthBar = document.getElementById('strengthBar');
    var strengthText = document.getElementById('strengthText');
    if (!passwordField || !strengthDiv || !strengthBar || !strengthText) return;

    function compute() {
      var password = passwordField.value || '';
      if (password.length === 0) {
        strengthDiv.style.display = 'none';
        return;
      }
      strengthDiv.style.display = 'block';

      var score = 0; var feedback = [];
      if (password.length >= 8) score += 1; else feedback.push('Use at least 8 characters');
      if (/[a-z]/.test(password)) score += 1; else feedback.push('Add lowercase letters');
      if (/[A-Z]/.test(password)) score += 1; else feedback.push('Add uppercase letters');
      if (/\d/.test(password)) score += 1; else feedback.push('Add numbers');
      if (/[^A-Za-z0-9]/.test(password)) score += 1; else feedback.push('Add special characters');

      var strength = 'Very Weak'; var color = '#d47474';
      if (score >= 4) { strength = 'Strong'; color = '#4a9d7e'; }
      else if (score >= 3) { strength = 'Good'; color = '#d4a574'; }
      else if (score >= 2) { strength = 'Fair'; color = '#748d92'; }

      strengthBar.style.width = (score * 20) + '%';
      strengthBar.style.backgroundColor = color;
      strengthText.textContent = strength;
      strengthText.style.color = color;
      if (feedback.length > 0) strengthText.textContent += ' - ' + feedback.join(', ');
    }

    function checkMatch() {
      if (confirmField && confirmField.value && passwordField.value !== confirmField.value) {
        confirmField.setCustomValidity('Passwords do not match');
      } else if (confirmField) {
        confirmField.setCustomValidity('');
      }
    }

    passwordField.addEventListener('input', function(){ compute(); checkMatch(); });
    if (confirmField) confirmField.addEventListener('input', checkMatch);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
