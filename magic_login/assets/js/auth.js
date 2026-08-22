(function (window, document) {
    'use strict';

    var formState = new WeakMap();
    var cooldownTimers = {};
    var cooldownEnds = {};

    function cooldownStorageKey(key) {
        return 'magic-login-cooldown-' + key;
    }

    function cooldownEnd(key) {
        var stored = 0;
        try {
            stored = parseInt(window.localStorage.getItem(cooldownStorageKey(key)) || '0', 10);
        } catch (error) {
            stored = 0;
        }
        return Math.max(cooldownEnds[key] || 0, stored);
    }

    function saveCooldown(key, end) {
        cooldownEnds[key] = end;
        try {
            window.localStorage.setItem(cooldownStorageKey(key), String(end));
        } catch (error) {
            // The in-page timer still works when storage is unavailable.
        }
    }

    function formatCooldown(seconds) {
        var safeSeconds = Math.max(0, seconds);
        var minutes = Math.floor(safeSeconds / 60);
        return minutes + ':' + String(safeSeconds % 60).padStart(2, '0');
    }

    function renderCooldown(key) {
        var remaining = Math.max(0, Math.ceil((cooldownEnd(key) - Date.now()) / 1000));
        var form = document.querySelector('form[data-magic-login-cooldown="' + key + '"]');
        var submit = form && form.querySelector('button[type="submit"], input[type="submit"]');

        if (submit) {
            var label = submit.tagName === 'BUTTON' ? submit.querySelector('span') : null;
            if (label && !label.getAttribute('data-magic-login-label')) {
                label.setAttribute('data-magic-login-label', label.textContent);
            }
            submit.disabled = remaining > 0;
            submit.classList.toggle('is-cooling-down', remaining > 0);
            if (label) {
                label.textContent = remaining > 0
                    ? 'Try again in ' + formatCooldown(remaining)
                    : label.getAttribute('data-magic-login-label');
            }
        }

        document.querySelectorAll('[data-magic-login-cooldown-display="' + key + '"]').forEach(function (element) {
            var label = element.querySelector('span') || element;
            if (!element.getAttribute('data-magic-login-label')) {
                element.setAttribute('data-magic-login-label', label.textContent.trim());
            }
            label.textContent = remaining > 0
                ? 'Request another code in ' + formatCooldown(remaining)
                : element.getAttribute('data-magic-login-label');
            element.setAttribute('aria-disabled', remaining > 0 ? 'true' : 'false');
            element.classList.toggle('is-disabled', remaining > 0);
        });

        if (remaining < 1 && cooldownTimers[key]) {
            window.clearInterval(cooldownTimers[key]);
            delete cooldownTimers[key];
        }
        return remaining;
    }

    function startCooldown(key, seconds) {
        if (!key || !seconds) {
            return;
        }
        var end = Math.max(cooldownEnd(key), Date.now() + (parseInt(seconds, 10) * 1000));
        saveCooldown(key, end);
        renderCooldown(key);
        if (!cooldownTimers[key]) {
            cooldownTimers[key] = window.setInterval(function () {
                renderCooldown(key);
            }, 1000);
        }
    }

    function feedbackFor(form) {
        var feedback = form.querySelector('[data-magic-login-feedback]');
        if (feedback) {
            return feedback;
        }

        feedback = document.createElement('div');
        feedback.className = 'airix-auth-feedback';
        feedback.setAttribute('data-magic-login-feedback', '');
        feedback.setAttribute('role', 'status');
        feedback.setAttribute('aria-live', 'polite');
        feedback.hidden = true;

        var submit = form.querySelector('.airix-auth-submit, button[type="submit"], input[type="submit"]');
        if (submit && submit.parentNode) {
            submit.parentNode.insertBefore(feedback, submit);
        } else {
            form.appendChild(feedback);
        }

        return feedback;
    }

    function showFeedback(form, type, message) {
        var feedback = feedbackFor(form);
        feedback.className = 'airix-auth-feedback airix-auth-feedback--' + type;
        feedback.textContent = message;
        feedback.hidden = false;
        feedback.setAttribute('role', type === 'error' ? 'alert' : 'status');
    }

    function clearFeedback(form) {
        var feedback = form.querySelector('[data-magic-login-feedback]');
        if (!feedback) {
            return;
        }
        feedback.hidden = true;
        feedback.textContent = '';
        feedback.className = 'airix-auth-feedback';
    }

    function setBusy(form, busy) {
        var submit = form.querySelector('button[type="submit"], input[type="submit"]');
        form.setAttribute('aria-busy', busy ? 'true' : 'false');
        if (!submit) {
            return;
        }

        submit.disabled = busy;
        submit.classList.toggle('is-loading', busy);
        if (submit.tagName === 'BUTTON') {
            var label = submit.querySelector('span');
            if (label) {
                if (!label.getAttribute('data-magic-login-label')) {
                    label.setAttribute('data-magic-login-label', label.textContent);
                }
                label.textContent = busy ? 'Checking securely…' : label.getAttribute('data-magic-login-label');
            }
        }
    }

    function updateCsrf(form, csrf) {
        if (!csrf || !csrf.name || !csrf.hash) {
            return;
        }
        var input = form.querySelector('input[name="' + window.CSS.escape(csrf.name) + '"]');
        if (input) {
            input.value = csrf.hash;
        }
    }

    function resetWidget(widget) {
        if (widget && typeof widget.reset === 'function') {
            widget.reset();
        }
    }

    function sameOriginUrl(value) {
        var target = new window.URL(value, window.location.origin);
        if ((target.protocol !== 'http:' && target.protocol !== 'https:')
            || target.origin !== window.location.origin) {
            throw new Error('The server returned an unsafe redirect. Please refresh the page and try again.');
        }
        return target.href;
    }

    function sha256Ascii(value) {
        var roundConstants = [
            0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
            0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
            0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
            0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
            0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
            0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
            0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
            0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
        ];
        var state = [0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19];
        var bytes = [];
        var bitLength = value.length * 8;
        var offset;
        var index;

        for (index = 0; index < value.length; index += 1) {
            bytes.push(value.charCodeAt(index) & 0xff);
        }
        bytes.push(0x80);
        while ((bytes.length % 64) !== 56) {
            bytes.push(0);
        }
        for (index = 7; index >= 0; index -= 1) {
            bytes.push(index < 4 ? (bitLength >>> (index * 8)) & 0xff : 0);
        }

        function rotateRight(number, distance) {
            return (number >>> distance) | (number << (32 - distance));
        }

        for (offset = 0; offset < bytes.length; offset += 64) {
            var words = new Array(64);
            for (index = 0; index < 16; index += 1) {
                var byteOffset = offset + (index * 4);
                words[index] = ((bytes[byteOffset] << 24) | (bytes[byteOffset + 1] << 16)
                    | (bytes[byteOffset + 2] << 8) | bytes[byteOffset + 3]) | 0;
            }
            for (index = 16; index < 64; index += 1) {
                var previous = words[index - 15];
                var prior = words[index - 2];
                var sigma0 = rotateRight(previous, 7) ^ rotateRight(previous, 18) ^ (previous >>> 3);
                var sigma1 = rotateRight(prior, 17) ^ rotateRight(prior, 19) ^ (prior >>> 10);
                words[index] = (words[index - 16] + sigma0 + words[index - 7] + sigma1) | 0;
            }

            var a = state[0];
            var b = state[1];
            var c = state[2];
            var d = state[3];
            var e = state[4];
            var f = state[5];
            var g = state[6];
            var h = state[7];

            for (index = 0; index < 64; index += 1) {
                var sum1 = rotateRight(e, 6) ^ rotateRight(e, 11) ^ rotateRight(e, 25);
                var choose = (e & f) ^ ((~e) & g);
                var temporary1 = (h + sum1 + choose + roundConstants[index] + words[index]) | 0;
                var sum0 = rotateRight(a, 2) ^ rotateRight(a, 13) ^ rotateRight(a, 22);
                var majority = (a & b) ^ (a & c) ^ (b & c);
                var temporary2 = (sum0 + majority) | 0;
                h = g;
                g = f;
                f = e;
                e = (d + temporary1) | 0;
                d = c;
                c = b;
                b = a;
                a = (temporary1 + temporary2) | 0;
            }

            state[0] = (state[0] + a) | 0;
            state[1] = (state[1] + b) | 0;
            state[2] = (state[2] + c) | 0;
            state[3] = (state[3] + d) | 0;
            state[4] = (state[4] + e) | 0;
            state[5] = (state[5] + f) | 0;
            state[6] = (state[6] + g) | 0;
            state[7] = (state[7] + h) | 0;
        }

        return state.map(function (word) {
            return (word >>> 0).toString(16).padStart(8, '0');
        }).join('');
    }

    function solveWithoutWebCrypto(widget) {
        var challengeUrl = widget.getAttribute('challengeurl');
        if (!challengeUrl) {
            return Promise.reject(new Error('The security challenge is unavailable.'));
        }

        return window.fetch(challengeUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('The security challenge could not be loaded.');
            }
            return response.json();
        }).then(function (challenge) {
            return new Promise(function (resolve, reject) {
                var number = 0;
                var maximum = parseInt(challenge.maxnumber || '0', 10);

                function work() {
                    var end = Math.min(maximum + 1, number + 1500);
                    for (; number < end; number += 1) {
                        if (sha256Ascii(challenge.salt + number) === challenge.challenge) {
                            resolve(window.btoa(JSON.stringify({
                                algorithm: challenge.algorithm,
                                challenge: challenge.challenge,
                                number: number,
                                salt: challenge.salt,
                                signature: challenge.signature
                            })));
                            return;
                        }
                    }
                    if (number > maximum) {
                        reject(new Error('The security challenge could not be solved.'));
                        return;
                    }
                    window.setTimeout(work, 0);
                }

                work();
            });
        });
    }

    function setAltchaPayload(form, payload) {
        var input = form.querySelector('input[name="altcha"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'altcha';
            form.appendChild(input);
        }
        input.value = payload;
    }

    function submitAjax(form, widget) {
        return window.fetch(form.action, {
            method: (form.method || 'POST').toUpperCase(),
            body: new window.FormData(form),
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error('The server returned an unexpected response. Please try again.');
            }).then(function (payload) {
                if (!response.ok && !payload.message) {
                    throw new Error('The request could not be completed. Please try again.');
                }
                return payload;
            });
        }).then(function (payload) {
            updateCsrf(form, payload.csrf);
            if (!payload.ok) {
                var requestError = new Error(payload.message || 'The request could not be completed. Please try again.');
                requestError.cooldown = payload.cooldown || 0;
                throw requestError;
            }

            showFeedback(form, 'success', payload.message || 'Done.');
            var cooldownKey = form.getAttribute('data-magic-login-cooldown');
            if (payload.redirect) {
                var redirectUrl = sameOriginUrl(payload.redirect);
                startCooldown(cooldownKey, payload.cooldown);
                window.setTimeout(function () {
                    window.location.assign(redirectUrl);
                }, 650);
                return;
            }

            setBusy(form, false);
            resetWidget(widget);
            startCooldown(cooldownKey, payload.cooldown);
        });
    }

    function submitNative(form) {
        showFeedback(form, 'success', 'Security check complete. Signing you in…');
        window.HTMLFormElement.prototype.submit.call(form);
    }

    function validateVisibleFields(form) {
        var fields = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
        for (var index = 0; index < fields.length; index += 1) {
            var field = fields[index];
            // ALTCHA renders an unnamed, required checkbox into the form. It is
            // the proof-of-work control itself, not a user-editable field.
            if (!field.name || field.disabled || typeof field.checkValidity !== 'function' || field.checkValidity()) {
                continue;
            }
            if (typeof field.reportValidity === 'function') {
                field.reportValidity();
            }
            return false;
        }
        return true;
    }

    function solveAndSubmit(form) {
        var state = formState.get(form) || { busy: false };
        if (state.busy) {
            return;
        }

        if (!validateVisibleFields(form)) {
            return;
        }

        var widget = form.querySelector('altcha-widget');
        if (!widget) {
            return;
        }

        state.busy = true;
        formState.set(form, state);
        clearFeedback(form);
        setBusy(form, true);
        showFeedback(form, 'progress', 'Running a private security check…');

        var settled = false;
        var timeout = window.setTimeout(function () {
            if (settled) {
                return;
            }
            settled = true;
            state.busy = false;
            setBusy(form, false);
            showFeedback(form, 'error', 'The security check took too long. Please try again.');
            resetWidget(widget);
        }, 30000);

        function finishWithError(message) {
            if (settled) {
                return;
            }
            settled = true;
            window.clearTimeout(timeout);
            state.busy = false;
            setBusy(form, false);
            showFeedback(form, 'error', message || 'The security check failed. Please try again.');
            resetWidget(widget);
        }

        function submitAfterAltchaPayload() {
            var attempts = 0;
            var maxAttempts = 20;

            function attempt() {
                var payload = form.querySelector('input[name="altcha"]');
                if (payload && payload.value) {
                    if (form.getAttribute('data-magic-login-ajax') === 'true') {
                        submitAjax(form, widget).catch(function (error) {
                            settled = false;
                            finishWithError(error.message);
                            startCooldown(form.getAttribute('data-magic-login-cooldown'), error.cooldown);
                        });
                        return;
                    }
                    submitNative(form);
                    return;
                }

                attempts += 1;
                if (attempts >= maxAttempts) {
                    settled = false;
                    finishWithError('The security check could not be verified. Please try again.');
                    return;
                }
                window.setTimeout(attempt, 50);
            }

            attempt();
        }

        function onStateChange(event) {
            var detail = event.detail || {};
            if (detail.state === 'error' || detail.state === 'expired') {
                widget.removeEventListener('statechange', onStateChange);
                finishWithError('The security check failed. Please try again.');
                return;
            }
            if (detail.state !== 'verified' || settled) {
                return;
            }

            settled = true;
            window.clearTimeout(timeout);
            widget.removeEventListener('statechange', onStateChange);
            showFeedback(form, 'progress', 'Security check complete. Sending…');
            submitAfterAltchaPayload();
        }

        widget.addEventListener('statechange', onStateChange);

        if (!window.crypto || !window.crypto.subtle) {
            solveWithoutWebCrypto(widget).then(function (payload) {
                setAltchaPayload(form, payload);
                onStateChange({ detail: { state: 'verified' } });
            }).catch(function (error) {
                finishWithError(error.message);
            });
            return;
        }

        window.customElements.whenDefined('altcha-widget').then(function () {
            if (typeof widget.getState === 'function' && widget.getState() === 'verified') {
                onStateChange({ detail: { state: 'verified' } });
                return;
            }
            if (typeof widget.verify !== 'function') {
                finishWithError('The security check could not start. Refresh the page and try again.');
                return;
            }
            return widget.verify();
        }).catch(function () {
            finishWithError('The security check could not start. Refresh the page and try again.');
        });
    }

    function bindForm(form) {
        if (!form || form.getAttribute('data-magic-login-bound') === 'true') {
            return;
        }
        if (!form.querySelector('altcha-widget')) {
            return;
        }

        form.setAttribute('data-magic-login-bound', 'true');
        // ALTCHA's internal required checkbox is intentionally off-screen. Disable
        // native form-wide validation and validate the real form fields ourselves.
        form.noValidate = true;
        feedbackFor(form);
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            solveAndSubmit(form);
        }, true);

        var submit = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submit) {
            submit.addEventListener('click', function (event) {
                // ALTCHA exposes an internal required checkbox. Browsers can stop
                // the submit event before it reaches the form while that hidden
                // control is unresolved, so start the check from the submit click.
                event.preventDefault();
                event.stopImmediatePropagation();
                solveAndSubmit(form);
            }, true);
        }

        form.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' || event.target.tagName === 'TEXTAREA') {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            solveAndSubmit(form);
        }, true);
    }

    function bindAll() {
        document.querySelectorAll('form').forEach(bindForm);

        document.querySelectorAll('[data-airix-phone-code]').forEach(function (code) {
            var form = code.closest('form');
            var country = form && form.querySelector('.airix-country-select');
            if (!country || country.getAttribute('data-magic-login-country-bound') === 'true') {
                return;
            }

            function updateCallingCode() {
                var option = country.options[country.selectedIndex];
                var trigger = country.closest('.airix-country-trigger');
                var iso = trigger && trigger.querySelector('[data-airix-country-iso]');
                code.textContent = option ? '+' + option.getAttribute('data-calling-code') : '';
                if (iso) {
                    iso.textContent = option ? option.getAttribute('data-iso') : '';
                }
            }

            country.setAttribute('data-magic-login-country-bound', 'true');
            country.addEventListener('change', updateCallingCode);
            updateCallingCode();
        });

        document.querySelectorAll('[data-magic-login-cooldown], [data-magic-login-cooldown-display]').forEach(function (element) {
            var key = element.getAttribute('data-magic-login-cooldown')
                || element.getAttribute('data-magic-login-cooldown-display');
            if (key && renderCooldown(key) > 0 && !cooldownTimers[key]) {
                cooldownTimers[key] = window.setInterval(function () {
                    renderCooldown(key);
                }, 1000);
            }
        });
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-magic-login-cooldown-display].is-disabled');
        if (link) {
            event.preventDefault();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindAll, { once: true });
    } else {
        bindAll();
    }
    document.addEventListener('magic-login:widgets-ready', bindAll);
}(window, document));
