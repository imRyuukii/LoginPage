/**
 * Form Utilities
 * Loading states, validation feedback, and form enhancements
 */
(function(global) {
    'use strict';

    /**
     * Add loading state to a form
     * @param {HTMLFormElement} form - The form element
     * @param {HTMLButtonElement} button - The submit button (optional, will find it if not provided)
     */
    function setFormLoading(form, button) {
        if (!form) return;

        // Find submit button if not provided
        if (!button) {
            button = form.querySelector('button[type="submit"]');
        }

        if (!button) return;

        // Store original text
        if (!button.dataset.originalText) {
            button.dataset.originalText = button.innerHTML;
        }

        // Disable form inputs
        const inputs = form.querySelectorAll('input, textarea, select, button');
        inputs.forEach(function(input) {
            input.disabled = true;
        });

        // Add loading state to button
        button.classList.add('button-loading');
        button.innerHTML = '<span class="spinner"></span><span>Processing...</span>';
    }

    /**
     * Remove loading state from a form
     * @param {HTMLFormElement} form - The form element
     * @param {HTMLButtonElement} button - The submit button (optional)
     */
    function removeFormLoading(form, button) {
        if (!form) return;

        // Find submit button if not provided
        if (!button) {
            button = form.querySelector('button[type="submit"]');
        }

        if (!button) return;

        // Enable form inputs
        const inputs = form.querySelectorAll('input, textarea, select, button');
        inputs.forEach(function(input) {
            input.disabled = false;
        });

        // Restore button
        button.classList.remove('button-loading');
        if (button.dataset.originalText) {
            button.innerHTML = button.dataset.originalText;
        }
    }

    /**
     * Auto-add loading state to all forms on submit
     */
    function autoLoadingForms() {
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form.tagName === 'FORM' && !form.classList.contains('no-auto-loading')) {
                setFormLoading(form);
            }
        });
    }

    /**
     * Validate email format
     * @param {string} email - Email address to validate
     * @returns {boolean}
     */
    function isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    /**
     * Validate password strength
     * @param {string} password - Password to validate
     * @returns {object} - {score: number, feedback: string, valid: boolean}
     */
    function validatePasswordStrength(password) {
        let score = 0;
        const feedback = [];

        if (!password) {
            return { score: 0, feedback: 'Password is required', valid: false };
        }

        // Length check
        if (password.length >= 8) {
            score += 1;
        } else {
            feedback.push('At least 8 characters');
        }

        // Uppercase check
        if (/[A-Z]/.test(password)) {
            score += 1;
        } else {
            feedback.push('One uppercase letter');
        }

        // Lowercase check
        if (/[a-z]/.test(password)) {
            score += 1;
        } else {
            feedback.push('One lowercase letter');
        }

        // Number check
        if (/[0-9]/.test(password)) {
            score += 1;
        } else {
            feedback.push('One number');
        }

        // Special character check
        if (/[^A-Za-z0-9]/.test(password)) {
            score += 1;
        }

        // Determine strength
        let strength = 'weak';
        let valid = false;

        if (score >= 5) {
            strength = 'strong';
            valid = true;
        } else if (score >= 3) {
            strength = 'medium';
            valid = true;
        } else {
            strength = 'weak';
            valid = false;
        }

        return {
            score: score,
            strength: strength,
            feedback: feedback.length > 0 ? 'Missing: ' + feedback.join(', ') : 'Strong password!',
            valid: valid
        };
    }

    /**
     * Show validation feedback on an input field
     * @param {HTMLInputElement} input - The input field
     * @param {boolean} isValid - Whether the input is valid
     * @param {string} message - Feedback message
     */
    function showValidationFeedback(input, isValid, message) {
        if (!input) return;

        // Remove existing feedback
        const existingFeedback = input.parentNode.querySelector('.validation-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }

        // Remove existing classes
        input.classList.remove('input-valid', 'input-invalid');

        if (message) {
            // Add appropriate class
            input.classList.add(isValid ? 'input-valid' : 'input-invalid');

            // Create feedback element
            const feedback = document.createElement('div');
            feedback.className = 'validation-feedback validation-' + (isValid ? 'valid' : 'invalid');
            feedback.textContent = message;

            // Insert after input
            input.parentNode.insertBefore(feedback, input.nextSibling);
        }
    }

    /**
     * Setup real-time email validation
     * @param {HTMLInputElement} emailInput - The email input field
     */
    function setupEmailValidation(emailInput) {
        if (!emailInput) return;

        let timeout;
        emailInput.addEventListener('input', function() {
            clearTimeout(timeout);
            const email = emailInput.value.trim();

            if (email.length === 0) {
                showValidationFeedback(emailInput, true, '');
                return;
            }

            timeout = setTimeout(function() {
                if (isValidEmail(email)) {
                    showValidationFeedback(emailInput, true, '✓ Valid email format');
                } else {
                    showValidationFeedback(emailInput, false, '✗ Invalid email format');
                }
            }, 500);
        });
    }

    /**
     * Setup real-time password strength validation
     * @param {HTMLInputElement} passwordInput - The password input field
     * @param {boolean} showIndicator - Whether to show strength indicator
     */
    function setupPasswordValidation(passwordInput, showIndicator) {
        if (!passwordInput) return;
        showIndicator = showIndicator !== false; // Default true

        let timeout;
        passwordInput.addEventListener('input', function() {
            clearTimeout(timeout);
            const password = passwordInput.value;

            if (password.length === 0) {
                showValidationFeedback(passwordInput, true, '');
                return;
            }

            timeout = setTimeout(function() {
                const result = validatePasswordStrength(password);

                if (showIndicator) {
                    // Create or update strength indicator
                    let indicator = passwordInput.parentNode.querySelector('.password-strength-indicator');

                    if (!indicator) {
                        indicator = document.createElement('div');
                        indicator.className = 'password-strength-indicator';
                        passwordInput.parentNode.insertBefore(indicator, passwordInput.nextSibling);
                    }

                    // Update indicator
                    indicator.className = 'password-strength-indicator strength-' + result.strength;
                    indicator.innerHTML =
                        '<div class="strength-bar">' +
                        '<div class="strength-bar-fill" style="width: ' + (result.score * 20) + '%"></div>' +
                        '</div>' +
                        '<div class="strength-text">' + result.feedback + '</div>';
                } else {
                    showValidationFeedback(passwordInput, result.valid, result.feedback);
                }
            }, 300);
        });
    }

    /**
     * Setup password confirmation validation
     * @param {HTMLInputElement} passwordInput - The password input field
     * @param {HTMLInputElement} confirmInput - The confirmation input field
     */
    function setupPasswordConfirmation(passwordInput, confirmInput) {
        if (!passwordInput || !confirmInput) return;

        function checkMatch() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;

            if (confirm.length === 0) {
                showValidationFeedback(confirmInput, true, '');
                return;
            }

            if (password === confirm) {
                showValidationFeedback(confirmInput, true, '✓ Passwords match');
            } else {
                showValidationFeedback(confirmInput, false, '✗ Passwords do not match');
            }
        }

        let timeout;
        confirmInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(checkMatch, 300);
        });

        passwordInput.addEventListener('input', function() {
            if (confirmInput.value.length > 0) {
                clearTimeout(timeout);
                timeout = setTimeout(checkMatch, 300);
            }
        });
    }

    /**
     * Setup username validation (alphanumeric and underscore only)
     * @param {HTMLInputElement} usernameInput - The username input field
     */
    function setupUsernameValidation(usernameInput) {
        if (!usernameInput) return;

        let timeout;
        usernameInput.addEventListener('input', function() {
            clearTimeout(timeout);
            const username = usernameInput.value.trim();

            if (username.length === 0) {
                showValidationFeedback(usernameInput, true, '');
                return;
            }

            timeout = setTimeout(function() {
                const regex = /^[a-zA-Z0-9_]{3,20}$/;

                if (username.length < 3) {
                    showValidationFeedback(usernameInput, false, '✗ At least 3 characters required');
                } else if (username.length > 20) {
                    showValidationFeedback(usernameInput, false, '✗ Maximum 20 characters');
                } else if (!regex.test(username)) {
                    showValidationFeedback(usernameInput, false, '✗ Only letters, numbers, and underscores allowed');
                } else {
                    showValidationFeedback(usernameInput, true, '✓ Valid username format');
                }
            }, 500);
        });
    }

    /**
     * Prevent double form submission
     * @param {HTMLFormElement} form - The form element
     */
    function preventDoubleSubmit(form) {
        if (!form) return;

        let submitted = false;
        form.addEventListener('submit', function(e) {
            if (submitted) {
                e.preventDefault();
                return false;
            }
            submitted = true;

            // Reset after 5 seconds (in case submission fails)
            setTimeout(function() {
                submitted = false;
            }, 5000);
        });
    }

    // Expose public API
    global.FormUtils = {
        setLoading: setFormLoading,
        removeLoading: removeFormLoading,
        autoLoadingForms: autoLoadingForms,
        isValidEmail: isValidEmail,
        validatePasswordStrength: validatePasswordStrength,
        showValidationFeedback: showValidationFeedback,
        setupEmailValidation: setupEmailValidation,
        setupPasswordValidation: setupPasswordValidation,
        setupPasswordConfirmation: setupPasswordConfirmation,
        setupUsernameValidation: setupUsernameValidation,
        preventDoubleSubmit: preventDoubleSubmit
    };

})(window);
