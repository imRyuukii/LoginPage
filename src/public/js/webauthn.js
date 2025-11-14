// src/public/js/webauthn.js
// Helper for WebAuthn (passkey) registration and login.

(function(){
  function b64urlToArrayBuffer(b64url) {
    var padding = '='.repeat((4 - (b64url.length % 4)) % 4);
    var base64 = (b64url + padding).replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(base64);
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out.buffer;
  }

  function arrayBufferToB64url(buf) {
    var bytes = new Uint8Array(buf);
    var bin = '';
    for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
    var base64 = btoa(bin);
    return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
  }

  function getCsrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  // --- Registration ---
  async function beginRegister() {
    var resp = await fetch('/LoginPage/src/public/api/webauthn/begin-register.php', {
      method: 'GET',
      credentials: 'include',
      headers: { 'Accept': 'application/json' }
    });
    if (!resp.ok) throw new Error('begin-register failed: ' + resp.status);
    return resp.json();
  }

  async function finishRegister(attResp) {
    var csrf = getCsrf();
    var payload = {
      id: attResp.id,
      clientDataJSON: arrayBufferToB64url(attResp.response.clientDataJSON),
      attestationObject: arrayBufferToB64url(attResp.response.attestationObject),
      csrf: csrf
    };
    var resp = await fetch('/LoginPage/src/public/api/webauthn/finish-register.php', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    });
    if (!resp.ok) throw new Error('finish-register failed: ' + resp.status);
    return resp.json();
  }

  async function registerPasskey() {
    if (!window.PublicKeyCredential || !navigator.credentials || !navigator.credentials.create) {
      alert('WebAuthn (passkeys) is not supported in this browser.');
      return;
    }
    try {
      var options = await beginRegister();
      if (!options || !options.publicKey) throw new Error('Invalid creation options');

      var pubKey = options.publicKey;
      // Decode base64url fields into ArrayBuffers
      pubKey.challenge = b64urlToArrayBuffer(pubKey.challenge);
      if (pubKey.user && typeof pubKey.user.id === 'string') {
        pubKey.user.id = b64urlToArrayBuffer(pubKey.user.id);
      }
      if (Array.isArray(pubKey.excludeCredentials)) {
        pubKey.excludeCredentials = pubKey.excludeCredentials.map(function(cred){
          return Object.assign({}, cred, { id: b64urlToArrayBuffer(cred.id) });
        });
      }

      var credential = await navigator.credentials.create({ publicKey: pubKey });
      if (!credential) throw new Error('No credential returned');

      var result = await finishRegister(credential);
      if (result && result.ok) {
        if (window.Toast) {
          Toast.success('Passkey registered successfully.', 5000);
        } else {
          alert('Passkey registered successfully.');
        }
      } else {
        throw new Error(result && result.error ? result.error : 'Unknown registration error');
      }
    } catch (e) {
      console.error('registerPasskey error', e);
      if (window.Toast) {
        Toast.error('Failed to register passkey: ' + (e.message || e), 6000);
      } else {
        alert('Failed to register passkey: ' + (e.message || e));
      }
    }
  }

  // --- Login ---
  async function beginLogin(loginValue) {
    var csrf = getCsrf();
    var resp = await fetch('/LoginPage/src/public/api/webauthn/begin-login.php', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ login: loginValue, csrf: csrf })
    });
    if (!resp.ok) {
      var text;
      try { text = await resp.text(); } catch (_) { text = ''; }
      throw new Error('begin-login failed: ' + resp.status + (text ? ' ' + text : ''));
    }
    return resp.json();
  }

  async function finishLogin(assertion) {
    var csrf = getCsrf();
    var payload = {
      id: assertion.id,
      clientDataJSON: arrayBufferToB64url(assertion.response.clientDataJSON),
      authenticatorData: arrayBufferToB64url(assertion.response.authenticatorData),
      signature: arrayBufferToB64url(assertion.response.signature),
      csrf: csrf
    };

    var resp = await fetch('/LoginPage/src/public/api/webauthn/finish-login.php', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    });
    if (!resp.ok) {
      var errBody;
      try { errBody = await resp.json(); } catch (_) { errBody = null; }
      var msg = (errBody && errBody.error) ? errBody.error : ('finish-login failed: ' + resp.status);
      throw new Error(msg);
    }
    return resp.json();
  }

  async function loginWithPasskey() {
    if (!window.PublicKeyCredential || !navigator.credentials || !navigator.credentials.get) {
      alert('WebAuthn (passkeys) is not supported in this browser.');
      return;
    }

    var loginInput = document.getElementById('login');
    var loginValue = loginInput ? loginInput.value.trim() : '';
    if (!loginValue) {
      if (window.Toast) {
        Toast.error('Enter your username or email first.', 4000);
      } else {
        alert('Enter your username or email first.');
      }
      return;
    }

    try {
      var options = await beginLogin(loginValue);
      if (!options || !options.publicKey) throw new Error('Invalid assertion options');

      var pubKey = options.publicKey;
      pubKey.challenge = b64urlToArrayBuffer(pubKey.challenge);
      if (Array.isArray(pubKey.allowCredentials)) {
        pubKey.allowCredentials = pubKey.allowCredentials.map(function(cred){
          return Object.assign({}, cred, { id: b64urlToArrayBuffer(cred.id) });
        });
      }

      var assertion = await navigator.credentials.get({ publicKey: pubKey });
      if (!assertion) throw new Error('No assertion returned');

      var result = await finishLogin(assertion);
      if (result && result.ok) {
        if (window.Toast) {
          Toast.success('Logged in with passkey.', 3000);
        }
        // Redirect to profile (same as password login)
        window.location.href = './profile.php';
      } else {
        throw new Error(result && result.error ? result.error : 'Unknown login error');
      }
    } catch (e) {
      console.error('loginWithPasskey error', e);
      if (window.Toast) {
        Toast.error('Failed to login with passkey: ' + (e.message || e), 6000);
      } else {
        alert('Failed to login with passkey: ' + (e.message || e));
      }
    }
  }

  // Attach handlers when DOM is ready
  function attachUI() {
    var btn = document.getElementById('registerPasskeyBtn');
    if (btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        registerPasskey();
      });
    }

    var loginBtn = document.getElementById('passkeyLoginBtn');
    if (loginBtn) {
      loginBtn.addEventListener('click', function(e) {
        e.preventDefault();
        loginWithPasskey();
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachUI);
  } else {
    attachUI();
  }

  // Expose globally (optional)
  window.WebAuthnHelper = {
    registerPasskey: registerPasskey,
    loginWithPasskey: loginWithPasskey
  };
})();
