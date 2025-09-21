(function(global){
  function postWithBeacon(url, csrf){
    try {
      if (!url || !csrf) return false;
      if (navigator.sendBeacon) {
        var fd = new FormData();
        fd.append('csrf', csrf);
        return navigator.sendBeacon(url, fd);
      }
    } catch (e) {}
    return false;
  }

  function postWithFetch(url, csrf){
    try {
      var fd = new FormData();
      fd.append('csrf', csrf);
      fetch(url, { method: 'POST', credentials: 'same-origin', body: fd }).catch(function(){});
    } catch (e) {}
  }

  function sendImmediateHeartbeat(url, csrf){
    if (!url || !csrf) return;
    var ok = postWithBeacon(url, csrf);
    if (!ok) postWithFetch(url, csrf);
    // Nudge the admin user list to refresh soon after pinging
    try {
      if (typeof window !== 'undefined' && window.__refreshLastActive) {
        setTimeout(function(){ window.__refreshLastActive(); }, 350);
      }
    } catch (e) {}
  }

  function installHeartbeatOnLoad(opts){
    opts = opts || {};
    var url = opts.url;
    var csrf = opts.csrf;
    if (!url || !csrf) return;

    // Send as early as possible on initial load
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
      sendImmediateHeartbeat(url, csrf);
    } else {
      document.addEventListener('DOMContentLoaded', function(){ sendImmediateHeartbeat(url, csrf); }, { once: true });
    }
    // Also send on pageshow (covers BFCache restores)
    window.addEventListener('pageshow', function(){ sendImmediateHeartbeat(url, csrf); }, { once: true });
  }

  global.Heartbeat = {
    installHeartbeatOnLoad: installHeartbeatOnLoad,
    pingNow: sendImmediateHeartbeat
  };
})(window);
