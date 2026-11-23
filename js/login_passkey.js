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
        const btn = mfaPasskeyBtn || passkeyBtn;
        let originalHtml = '';
        if (btn) {
            originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verifying passkey...';
        }
        try {
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
            window.location.href = verifyData.redirect || '/';
        } catch (err) {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml || '<i class="fas fa-fingerprint mr-2"></i>Sign In with Passkey';
            }
            alert(err.message || 'Passkey login failed.');
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

    if (window.PublicKeyCredential && PublicKeyCredential.isConditionalMediationAvailable) {
        PublicKeyCredential.isConditionalMediationAvailable().then(available => {
            if (available) {
                fetch('login_passkey.php?action=get_login_args')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.args) {
                            recursiveBase64StrToArrayBuffer(data.args);
                            data.args.mediation = 'conditional';
                            navigator.credentials.get(data.args).then(cred => {
                                if (cred) {
                                    const payload = {
                                        id: arrayBufferToBase64(cred.rawId),
                                        clientDataJSON: arrayBufferToBase64(cred.response.clientDataJSON),
                                        authenticatorData: arrayBufferToBase64(cred.response.authenticatorData),
                                        signature: arrayBufferToBase64(cred.response.signature),
                                        userHandle: cred.response.userHandle ? arrayBufferToBase64(cred.response.userHandle) : null
                                    };
                                    fetch('login_passkey.php?action=verify_login', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify(payload)
                                    }).then(vr => vr.json()).then(vd => {
                                        if (vd.success) {
                                            window.location.href = vd.redirect || '/';
                                        }
                                    });
                                }
                            }).catch(() => {});
                        }
                    }).catch(() => {});
            }
        }).catch(() => {});
    }
});
