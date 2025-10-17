/**
 * Toast Notification System
 * Modern, accessible toast notifications with animations
 */
(function(global) {
    'use strict';

    // Toast container - created once
    let toastContainer = null;

    /**
     * Initialize toast container
     */
    function initContainer() {
        if (toastContainer) return;

        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container';
        toastContainer.setAttribute('aria-live', 'polite');
        toastContainer.setAttribute('aria-atomic', 'true');
        document.body.appendChild(toastContainer);
    }

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - Type of toast: 'success', 'error', 'warning', 'info'
     * @param {number} duration - Duration in milliseconds (default: 4000)
     */
    function showToast(message, type, duration) {
        type = type || 'info';
        duration = duration || 4000;

        initContainer();

        // Create toast element
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.setAttribute('role', 'alert');

        // Icon based on type
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        const icon = icons[type] || icons.info;

        // Build toast HTML
        toast.innerHTML =
            '<div class="toast-icon">' + icon + '</div>' +
            '<div class="toast-message">' + escapeHtml(message) + '</div>' +
            '<button class="toast-close" aria-label="Close notification">×</button>';

        // Add to container
        toastContainer.appendChild(toast);

        // Trigger animation
        setTimeout(function() {
            toast.classList.add('toast-show');
        }, 10);

        // Close button handler
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', function() {
            removeToast(toast);
        });

        // Auto remove after duration
        setTimeout(function() {
            removeToast(toast);
        }, duration);

        return toast;
    }

    /**
     * Remove a toast with animation
     */
    function removeToast(toast) {
        if (!toast || !toast.parentNode) return;

        toast.classList.add('toast-hide');
        toast.classList.remove('toast-show');

        setTimeout(function() {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Convenience methods
     */
    function success(message, duration) {
        return showToast(message, 'success', duration);
    }

    function error(message, duration) {
        return showToast(message, 'error', duration);
    }

    function warning(message, duration) {
        return showToast(message, 'warning', duration);
    }

    function info(message, duration) {
        return showToast(message, 'info', duration);
    }

    /**
     * Clear all toasts
     */
    function clearAll() {
        if (!toastContainer) return;

        const toasts = toastContainer.querySelectorAll('.toast');
        toasts.forEach(function(toast) {
            removeToast(toast);
        });
    }

    // Expose public API
    global.Toast = {
        show: showToast,
        success: success,
        error: error,
        warning: warning,
        info: info,
        clearAll: clearAll
    };

})(window);
