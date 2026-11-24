document.addEventListener('DOMContentLoaded', function() {
    const emailInput = document.getElementById('login_email');
    const passwordInput = document.getElementById('login_password');
    const passkeyContainer = document.getElementById('passkey_detect_container');
    const passwordContainer = document.getElementById('password_container');
    const passkeyBtn = document.getElementById('passkey_login_btn');
    const fallbackLink = document.getElementById('fallback_password_link');
    const mfaPasskeyBtn = document.getElementById('mfa_passkey_btn');

    let passkeyCheckedEmail = '';
    let manualPasswordMode = false;
    let conditionalAbortController = null;
    let conditionalRequestPromise = null;
    let authInProgress = false;

    async function abortConditionalAuth() {
        if (conditionalAbortController) {
            conditionalAbortController.abort();
            conditionalAbortController = null;
        }
        if (conditionalRequestPromise) {
            await Promise.race([
                conditionalRequestPromise.catch(() => {}),
                new Promise(resolve => setTimeout(resolve, 150))
            ]);
            conditionalRequestPromise = null;
        }
    }

    async function checkPasskey(email) {
        if (!email || email === passkeyCheckedEmail || manualPasswordMode) return;
        try {
            const res = await fetch('login_passkey.php?action=check_email&email=' + encodeURIComponent(email));
            const data = await res.json();
            if (data.success && data.has_passkey) {
                passkeyCheckedEmail = email;
                if (!manualPasswordMode) {
                    if (passkeyContainer) passkeyContainer.classList.remove('d-none');
                    if (passwordContainer) passwordContainer.classList.add('d-none');
                    if (passwordInput) passwordInput.removeAttribute('required');
                }
            } else {
                if (passkeyContainer) passkeyContainer.classList.add('d-none');
                if (passwordContainer) passwordContainer.classList.remove('d-none');
                if (passwordInput) passwordInput.setAttribute('required', 'required');
            }
        } catch (e) {}
    }

    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            if (this.value && this.value.includes('@')) {
                checkPasskey(this.value.trim());
            }
        });

        emailInput.addEventListener('change', function() {
            if (this.value && this.value.includes('@')) {
                checkPasskey(this.value.trim());
            }
        });

        emailInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter' || (this.value && this.value.includes('@') && this.value.includes('.'))) {
                checkPasskey(this.value.trim());
            }
        });

        if (emailInput.value && emailInput.value.includes('@')) {
            checkPasskey(emailInput.value.trim());
        }
    }

    if (fallbackLink) {
        fallbackLink.addEventListener('click', function(e) {
            e.preventDefault();
            manualPasswordMode = true;
            if (passkeyContainer) passkeyContainer.classList.add('d-none');
            if (passwordContainer) passwordContainer.classList.remove('d-none');
            if (passwordInput) {
                passwordInput.setAttribute('required', 'required');
                passwordInput.focus();
            }
        });
    }

    async function triggerPasskeyAuth(email) {
        if (authInProgress) return;
        authInProgress = true;
        const btn = mfaPasskeyBtn || passkeyBtn;
        let originalHtml = '';
        if (btn) {
            originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying passkey...';
        }
        let redirecting = false;
        try {
            await abortConditionalAuth();
            const query = email ? ('&email=' + encodeURIComponent(email)) : '';
            const res = await fetch('login_passkey.php?action=get_login_args' + query);
            const data = await res.json();
            if (!data.success) {
                throw new Error(data.error || 'Failed to start passkey verification');
            }
            const getArgs = data.args;
            recursiveBase64StrToArrayBuffer(getArgs);
            const cred = await navigator.credentials.get(getArgs);
            const payload = {
                id: arrayBufferToBase64(cred.rawId),
                clientDataJSON: arrayBufferToBase64(cred.response.clientDataJSON),
                authenticatorData: arrayBufferToBase64(cred.response.authenticatorData),
                signature: arrayBufferToBase64(cred.response.signature),
                userHandle: cred.response.userHandle ? arrayBufferToBase64(cred.response.userHandle) : null
            };
            const verifyRes = await fetch('login_passkey.php?action=verify_login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const verifyData = await verifyRes.json();
            if (!verifyData.success) {
                throw new Error(verifyData.error || 'Passkey verification failed');
            }
            redirecting = true;
            window.location.href = verifyData.redirect || '/';
        } catch (err) {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml || '<i class="fas fa-fingerprint me-2"></i>Sign In with Passkey';
            }
            if (!err || err.name !== 'AbortError') {
                alert((err && err.message) || 'Passkey login failed.');
            }
        } finally {
            if (!redirecting) {
                authInProgress = false;
            }
        }
    }

    if (passkeyBtn) {
        passkeyBtn.addEventListener('click', function() {
            triggerPasskeyAuth(emailInput ? emailInput.value.trim() : '');
        });
    }

    if (mfaPasskeyBtn) {
        mfaPasskeyBtn.addEventListener('click', function() {
            triggerPasskeyAuth('');
        });
    }

    if (!mfaPasskeyBtn && window.PublicKeyCredential && PublicKeyCredential.isConditionalMediationAvailable) {
        PublicKeyCredential.isConditionalMediationAvailable().then(available => {
            if (!available || authInProgress) return;
            fetch('login_passkey.php?action=get_login_args')
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.args || authInProgress) return;
                    recursiveBase64StrToArrayBuffer(data.args);
                    conditionalAbortController = new AbortController();
                    data.args.mediation = 'conditional';
                    data.args.signal = conditionalAbortController.signal;
                    conditionalRequestPromise = navigator.credentials.get(data.args)
                        .then(cred => {
                            conditionalAbortController = null;
                            conditionalRequestPromise = null;
                            if (cred) {
                                const payload = {
                                    id: arrayBufferToBase64(cred.rawId),
                                    clientDataJSON: arrayBufferToBase64(cred.response.clientDataJSON),
                                    authenticatorData: arrayBufferToBase64(cred.response.authenticatorData),
                                    signature: arrayBufferToBase64(cred.response.signature),
                                    userHandle: cred.response.userHandle ? arrayBufferToBase64(cred.response.userHandle) : null
                                };
                                return fetch('login_passkey.php?action=verify_login', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify(payload)
                                }).then(vr => vr.json()).then(vd => {
                                    if (vd.success) {
                                        window.location.href = vd.redirect || '/';
                                    }
                                });
                            }
                        })
                        .catch(err => {
                            conditionalAbortController = null;
                            conditionalRequestPromise = null;
                            if (err && err.name !== 'AbortError') {
                                return;
                            }
                        });
                }).catch(() => {});
        }).catch(() => {});
    }
});
