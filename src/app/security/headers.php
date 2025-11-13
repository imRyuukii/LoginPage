<?php
// src/app/security/headers.php
// Shared security headers and cache helpers

if (!function_exists('apply_default_security_headers')) {
    function apply_default_security_headers(): void {
        // Only set HSTS when serving over HTTPS to avoid accidental lock-in during plain HTTP local dev
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Permissions-Policy: accelerometer=(), ambient-light-sensor=(), autoplay=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), usb=()");
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');

        // CSP tuned to current assets: self-hosted JS/CSS, inline blocks, and Font Awesome via cdnjs
        $csp = [];
        $csp[] = "default-src 'self'";
        $csp[] = "script-src 'self'";
        $csp[] = "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com"; // Font Awesome CSS
        $csp[] = "img-src 'self' data:"; // Allow inline images (profile previews)
        $csp[] = "font-src 'self' https://cdnjs.cloudflare.com data:"; // Font Awesome fonts
        $csp[] = "connect-src 'self'"; // Heartbeat API
        $csp[] = "object-src 'none'"; // Disallow plugins
        $csp[] = "frame-ancestors 'none'"; // Clickjacking protection
        $csp[] = "form-action 'self'";
        $csp[] = "base-uri 'self'";
        header('Content-Security-Policy: ' . implode('; ', $csp));
    }

    // For sensitive pages (auth forms, tokens), prevent browser caching
    function apply_sensitive_nocache(): void {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}
