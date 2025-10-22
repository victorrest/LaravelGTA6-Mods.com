(function () {
    'use strict';

    const onReady = (callback) => {
        if (document.readyState !== 'loading') {
            callback();
        } else {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        }
    };

    const ensureValidity = (form) => {
        if (form && typeof form.reportValidity === 'function') {
            return form.reportValidity();
        }
        return true;
    };

    onReady(() => {
        const config = window.GTA6Auth || {};
        const authMessage = document.getElementById('auth-message');
        const tabContainer = document.getElementById('auth-tab-container');
        const loginTabBtn = document.getElementById('login-tab-btn');
        const registerTabBtn = document.getElementById('register-tab-btn');
        const showRegisterLink = document.getElementById('show-register');
        const showLoginLink = document.getElementById('show-login');
        const showLostLink = document.getElementById('show-lost-password');
        const lostBackLink = document.getElementById('lost-password-back');
        const resetBackLink = document.getElementById('reset-password-back');

        const viewElements = {
            login: document.getElementById('login-form'),
            register: document.getElementById('register-form'),
            lost: document.getElementById('lost-password-form'),
            reset: document.getElementById('reset-password-form'),
        };

        const toggleElements = {
            login: document.getElementById('login-toggle-text'),
            register: document.getElementById('register-toggle-text'),
            lost: document.getElementById('lost-password-toggle-text'),
            reset: document.getElementById('reset-password-toggle-text'),
        };

        if (!authMessage) {
            return;
        }

        const resolveMessage = (primary, fallbackKey) => {
            if (primary) {
                return primary;
            }
            if (fallbackKey && config.messages && config.messages[fallbackKey]) {
                return config.messages[fallbackKey];
            }
            if (config.messages && config.messages.genericError) {
                return config.messages.genericError;
            }
            return 'Something went wrong. Please try again.';
        };

        const resetMessageClasses = () => {
            authMessage.classList.remove(
                'hidden',
                'text-emerald-300',
                'bg-emerald-900/40',
                'text-rose-300',
                'bg-rose-900/40',
                'px-4',
                'py-3',
                'rounded-lg'
            );
        };

        const setMessage = (message, type = 'success', fallbackKey = 'genericError') => {
            const resolved = resolveMessage(message, fallbackKey);
            resetMessageClasses();
            authMessage.textContent = resolved;
            authMessage.classList.remove('hidden');
            authMessage.classList.add('px-4', 'py-3', 'rounded-lg');
            if (type === 'error') {
                authMessage.classList.add('text-rose-300', 'bg-rose-900/40');
            } else {
                authMessage.classList.add('text-emerald-300', 'bg-emerald-900/40');
            }
        };

        const clearMessage = () => {
            resetMessageClasses();
            authMessage.classList.add('hidden');
            authMessage.textContent = '';
        };

        const toggleSubmitState = (form, disabled) => {
            if (!form) {
                return;
            }
            const submitButton = form.querySelector('button[type="submit"]');
            if (!submitButton) {
                return;
            }
            submitButton.disabled = disabled;
            submitButton.classList.toggle('opacity-70', disabled);
            submitButton.classList.toggle('cursor-not-allowed', disabled);
        };

        const viewUrls = config.viewUrls || {};

        const updateHistory = (view) => {
            if (!window.history || typeof window.history.replaceState !== 'function') {
                return;
            }
            let targetUrl = viewUrls.login || window.location.href;
            if (view === 'register' && viewUrls.register) {
                targetUrl = viewUrls.register;
            } else if (view === 'lost' && viewUrls.lost) {
                targetUrl = viewUrls.lost;
            } else if (view === 'reset' && config.resetUrl) {
                targetUrl = config.resetUrl;
            }
            if (targetUrl) {
                window.history.replaceState(null, '', targetUrl);
            }
        };

        const isTabbedView = (view) => view === 'login' || view === 'register';

        let currentView = typeof config.initialView === 'string' ? config.initialView : 'login';
        if (!viewElements[currentView]) {
            currentView = 'login';
        }

        const applyView = (view, options = {}) => {
            const targetView = viewElements[view] ? view : 'login';
            currentView = targetView;

            Object.entries(viewElements).forEach(([key, element]) => {
                if (!element) {
                    return;
                }
                element.classList.toggle('hidden', key !== targetView);
            });

            if (tabContainer) {
                tabContainer.classList.toggle('hidden', !isTabbedView(targetView));
            }
            if (loginTabBtn) {
                loginTabBtn.classList.toggle('active', targetView === 'login');
            }
            if (registerTabBtn) {
                registerTabBtn.classList.toggle('active', targetView === 'register');
            }

            Object.entries(toggleElements).forEach(([key, element]) => {
                if (!element) {
                    return;
                }
                element.classList.toggle('hidden', key !== targetView);
            });

            const shouldClearMessage = options.clearMessage !== false;
            if (shouldClearMessage) {
                clearMessage();
            }

            const shouldUpdateHistory = options.updateHistory !== false;
            if (shouldUpdateHistory) {
                updateHistory(targetView);
            }
        };

        applyView(currentView, { clearMessage: false, updateHistory: false });

        const attachViewHandler = (element, view) => {
            if (!element) {
                return;
            }
            element.addEventListener('click', (event) => {
                event.preventDefault();
                applyView(view);
            });
        };

        if (loginTabBtn) {
            loginTabBtn.addEventListener('click', (event) => {
                event.preventDefault();
                applyView('login');
            });
        }
        if (registerTabBtn) {
            registerTabBtn.addEventListener('click', (event) => {
                event.preventDefault();
                applyView('register');
            });
        }

        attachViewHandler(showRegisterLink, 'register');
        attachViewHandler(showLoginLink, 'login');
        attachViewHandler(showLostLink, 'lost');
        attachViewHandler(lostBackLink, 'login');
        attachViewHandler(resetBackLink, 'login');

        const fetchJSON = async (url, payload) => {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.restNonce || '',
                },
                body: JSON.stringify(payload),
            });

            let data;
            try {
                data = await response.json();
            } catch (error) {
                data = null;
            }

            if (!response.ok) {
                const message = (data && (data.message || (data.data && data.data.message))) || (config.messages && config.messages.genericError) || 'Request failed.';
                const error = new Error(message);
                error.response = response;
                error.data = data;
                throw error;
            }

            return data || {};
        };

        const redirectAfterAuth = (target) => {
            const fallback = config.redirectUrl || viewUrls.login || window.location.href;
            window.setTimeout(() => {
                window.location.assign(target || fallback);
            }, 800);
        };

        const loginForm = document.getElementById('gta6mods-login');
        const registerForm = document.getElementById('gta6mods-register');
        const lostForm = document.getElementById('gta6mods-lost-password-request');
        const resetForm = document.getElementById('gta6mods-reset-password');

        if (loginForm) {
            loginForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!ensureValidity(loginForm)) {
                    return;
                }
                clearMessage();
                toggleSubmitState(loginForm, true);
                try {
                    const payload = {
                        login: loginForm.login ? loginForm.login.value.trim() : '',
                        password: loginForm.password ? loginForm.password.value : '',
                        remember: loginForm.remember ? Boolean(loginForm.remember.checked) : false,
                        nonce: config.nonce || '',
                    };
                    const response = await fetchJSON(config.loginUrl, payload);
                    setMessage(response.message, 'success', 'loginSuccess');
                    redirectAfterAuth(response.redirect || config.redirectUrl);
                } catch (error) {
                    setMessage(error.message, 'error');
                } finally {
                    toggleSubmitState(loginForm, false);
                }
            });
        }

        if (registerForm) {
            registerForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!ensureValidity(registerForm)) {
                    return;
                }
                if (registerForm.password && registerForm.password_confirmation && registerForm.password.value !== registerForm.password_confirmation.value) {
                    toggleSubmitState(registerForm, false);
                    setMessage('', 'error', 'passwordMismatch');
                    return;
                }
                clearMessage();
                toggleSubmitState(registerForm, true);
                try {
                    const payload = {
                        username: registerForm.username ? registerForm.username.value.trim() : '',
                        email: registerForm.email ? registerForm.email.value.trim() : '',
                        password: registerForm.password ? registerForm.password.value : '',
                        password_confirmation: registerForm.password_confirmation ? registerForm.password_confirmation.value : '',
                        terms: registerForm.terms ? Boolean(registerForm.terms.checked) : false,
                        nonce: config.nonce || '',
                    };
                    const response = await fetchJSON(config.registerUrl, payload);
                    setMessage(response.message, 'success', 'registerSuccess');
                    redirectAfterAuth(response.redirect || config.redirectUrl);
                } catch (error) {
                    setMessage(error.message, 'error');
                } finally {
                    toggleSubmitState(registerForm, false);
                }
            });
        }

        if (lostForm) {
            lostForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!ensureValidity(lostForm)) {
                    return;
                }
                clearMessage();
                toggleSubmitState(lostForm, true);
                try {
                    const payload = {
                        email: lostForm.email ? lostForm.email.value.trim() : '',
                        nonce: config.nonce || '',
                    };
                    const response = await fetchJSON(config.lostPasswordRequestUrl, payload);
                    setMessage(response.message, 'success', 'lostRequestSuccess');
                    lostForm.reset();
                    window.setTimeout(() => {
                        applyView('login', { clearMessage: false });
                    }, 1500);
                } catch (error) {
                    setMessage(error.message, 'error');
                } finally {
                    toggleSubmitState(lostForm, false);
                }
            });
        }

        if (resetForm) {
            resetForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!ensureValidity(resetForm)) {
                    return;
                }
                if (resetForm.password && resetForm.password_confirmation && resetForm.password.value !== resetForm.password_confirmation.value) {
                    toggleSubmitState(resetForm, false);
                    setMessage('', 'error', 'passwordMismatch');
                    return;
                }
                clearMessage();
                toggleSubmitState(resetForm, true);
                try {
                    const payload = {
                        login: resetForm.login ? resetForm.login.value : config.resetLogin || '',
                        key: resetForm.key ? resetForm.key.value : config.resetKey || '',
                        password: resetForm.password ? resetForm.password.value : '',
                        password_confirmation: resetForm.password_confirmation ? resetForm.password_confirmation.value : '',
                        nonce: config.nonce || '',
                    };
                    const response = await fetchJSON(config.lostPasswordResetUrl, payload);
                    setMessage(response.message, 'success', 'resetSuccess');
                    resetForm.reset();
                    const hiddenLogin = resetForm.querySelector('input[name="login"]');
                    const hiddenKey = resetForm.querySelector('input[name="key"]');
                    if (hiddenLogin) {
                        hiddenLogin.value = '';
                    }
                    if (hiddenKey) {
                        hiddenKey.value = '';
                    }
                    window.setTimeout(() => {
                        applyView('login', { clearMessage: false });
                    }, 1500);
                } catch (error) {
                    setMessage(error.message, 'error');
                } finally {
                    toggleSubmitState(resetForm, false);
                }
            });
        }
    });
})();
