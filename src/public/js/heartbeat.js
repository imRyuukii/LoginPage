
(function(global){
    var heartbeatInterval = null;

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
        // Only send if page is visible
        if (document.hidden) return;

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

        // Send immediately on page load
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            sendImmediateHeartbeat(url, csrf);
        } else {
            document.addEventListener('DOMContentLoaded', function(){ sendImmediateHeartbeat(url, csrf); }, { once: true });
        }

        // ⚡ START CONTINUOUS HEARTBEAT - sends every 30 seconds
        if (heartbeatInterval) {
            clearInterval(heartbeatInterval);
        }
        heartbeatInterval = setInterval(function(){
            sendImmediateHeartbeat(url, csrf);
        }, 30000); // 30 seconds

        // Handle page visibility changes
        document.addEventListener('visibilitychange', function(){
            if (!document.hidden && url && csrf) {
                // Page became visible - send heartbeat immediately
                sendImmediateHeartbeat(url, csrf);
            }
        });

        // Stop heartbeat when page unloads (logout, close tab, navigate away)
        window.addEventListener('beforeunload', function(){
            if (heartbeatInterval) {
                clearInterval(heartbeatInterval);
                heartbeatInterval = null;
            }
        });

        // Also handle pagehide event for better mobile support
        window.addEventListener('pagehide', function(){
            if (heartbeatInterval) {
                clearInterval(heartbeatInterval);
                heartbeatInterval = null;
            }
        });
    }

    global.Heartbeat = {
        installHeartbeatOnLoad: installHeartbeatOnLoad,
        pingNow: sendImmediateHeartbeat
    };
})(window);