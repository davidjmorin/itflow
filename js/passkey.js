function recursiveBase64StrToArrayBuffer(obj) {
    let prefix = '=?BINARY?B?';
    let suffix = '?=';
    if (typeof obj === 'object' && obj !== null) {
        for (let key in obj) {
            if (typeof obj[key] === 'string') {
                let str = obj[key];
                if (str.substring(0, prefix.length) === prefix && str.substring(str.length - suffix.length) === suffix) {
                    str = str.substring(prefix.length, str.length - suffix.length);
                    let binary_string = window.atob(str);
                    let len = binary_string.length;
                    let bytes = new Uint8Array(len);
                    for (let i = 0; i < len; i++) {
                        bytes[i] = binary_string.charCodeAt(i);
                    }
                    obj[key] = bytes.buffer;
                }
            } else {
                recursiveBase64StrToArrayBuffer(obj[key]);
            }
        }
    }
}

function arrayBufferToBase64(buffer) {
    let binary = '';
    let bytes = new Uint8Array(buffer);
    let len = bytes.byteLength;
    for (let i = 0; i < len; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary);
}

async function registerPasskey(passkeyName) {
    if (!window.isSecureContext || !navigator.credentials || !navigator.credentials.create) {
        throw new Error('WebAuthn is not supported in this browser or context (HTTPS required).');
    }
    const res = await fetch('ajax_passkey.php?action=get_create_args');
    const data = await res.json();
    if (!data.success) {
        throw new Error(data.error || 'Failed to fetch registration parameters');
    }
    const createArgs = data.args;
    recursiveBase64StrToArrayBuffer(createArgs);
    const cred = await navigator.credentials.create(createArgs);
    const payload = {
        name: passkeyName,
        clientDataJSON: arrayBufferToBase64(cred.response.clientDataJSON),
        attestationObject: arrayBufferToBase64(cred.response.attestationObject)
    };
    const saveRes = await fetch('ajax_passkey.php?action=process_create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const saveJson = await saveRes.json();
    if (!saveJson.success) {
        throw new Error(saveJson.error || 'Failed to save passkey');
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    const regBtn = document.getElementById('start_passkey_register_btn');
    if (regBtn) {
        regBtn.addEventListener('click', async function() {
            const btn = this;
            const nameInput = document.getElementById('passkey_name_input');
            const alertDiv = document.getElementById('passkey_error_alert');
            if (alertDiv) {
                alertDiv.classList.add('d-none');
                alertDiv.textContent = '';
            }
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Waiting for device...';
            try {
                await registerPasskey(nameInput ? (nameInput.value || 'My Passkey') : 'My Passkey');
                window.location.reload();
            } catch (err) {
                if (alertDiv) {
                    alertDiv.textContent = err.message || 'Passkey registration failed.';
                    alertDiv.classList.remove('d-none');
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-shield-alt mr-1"></i>Register Device';
            }
        });
    }
});
