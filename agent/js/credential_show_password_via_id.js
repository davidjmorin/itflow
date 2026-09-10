function parseCredentialResponse(data) {
    if (typeof data === 'object' && data !== null) {
        return data;
    }
    try {
        return JSON.parse(data);
    } catch (e) {
        console.error('Error parsing credential response:', e, data);
        return {};
    }
}

function escapeHtmlJs(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function copyTextToClipboard(button, text) {
    if (!text) {
        if (typeof flashTooltip === 'function') {
            flashTooltip(button, 'Empty');
        }
        return;
    }
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            if (typeof flashTooltip === 'function') {
                flashTooltip(button, 'Copied!');
            }
        }).catch(function() {
            fallbackCopyText(button, text);
        });
    } else {
        fallbackCopyText(button, text);
    }
}

function fallbackCopyText(button, text) {
    const tempInput = document.createElement('textarea');
    tempInput.value = text;
    tempInput.style.position = 'fixed';
    tempInput.style.left = '-9999px';
    tempInput.style.top = '-9999px';
    document.body.appendChild(tempInput);
    tempInput.focus();
    tempInput.select();
    try {
        const successful = document.execCommand('copy');
        if (typeof flashTooltip === 'function') {
            flashTooltip(button, successful ? 'Copied!' : 'Failed');
        }
    } catch (err) {
        if (typeof flashTooltip === 'function') {
            flashTooltip(button, 'Failed');
        }
    }
    document.body.removeChild(tempInput);
}

function showWifiPasscodeViaCredentialID(button, credential_id) {
    const $btn = jQuery(button);

    // If already revealed, toggle back to hidden dots
    if ($btn.data('revealed') === true) {
        $btn.html('<i class="fas fa-2x fa-ellipsis-h text-info"></i><i class="fas fa-2x fa-ellipsis-h text-info"></i>');
        $btn.data('revealed', false);
        return;
    }

    jQuery.get(
        "ajax.php", {
            get_credential_via_id: 'true',
            credential_id: credential_id
        },
        function(data) {
            const credential = parseCredentialResponse(data);
            const passcode = credential.wifi_passcode || credential.password || '(No Passcode)';

            // Directly reveal the password text in the table!
            $btn.html('<span class="badge badge-light border border-info text-info p-1 text-monospace" style="font-size: 0.95rem;" title="Click to hide"><i class="fa fa-eye mr-1"></i>' + escapeHtmlJs(passcode) + '</span>');
            $btn.data('revealed', true);
        }
    ).fail(function(xhr, status, error) {
        console.error("Failed to load Wi-Fi passcode:", error);
    });
}

function copyWifiPasscodeViaCredentialID(button, credential_id) {
    jQuery.get(
        "ajax.php", {
            get_credential_via_id: 'true',
            credential_id: credential_id
        },
        function(data) {
            const credential = parseCredentialResponse(data);
            const passcode = credential.wifi_passcode || credential.password || '';

            copyTextToClipboard(button, passcode);
        }
    );
}

function showPasswordViaCredentialID(button, credential_id) {
    const $btn = jQuery(button);

    // If already revealed, toggle back to hidden dots
    if ($btn.data('revealed') === true) {
        $btn.html('<i class="fas fa-2x fa-ellipsis-h text-secondary"></i><i class="fas fa-2x fa-ellipsis-h text-secondary"></i>');
        $btn.data('revealed', false);
        return;
    }

    jQuery.get(
        "ajax.php", {
            get_credential_via_id: 'true',
            credential_id: credential_id
        },
        function(data) {
            const credential = parseCredentialResponse(data);
            let password = credential.password;
            if (credential.type === 'Wi-Fi') {
                password = credential.wifi_passcode || credential.password || '(No Passcode)';
            }
            if (!password) {
                password = '(Empty)';
            }

            // Directly reveal the password text in the table!
            $btn.html('<span class="badge badge-light border border-secondary text-dark p-1 text-monospace" style="font-size: 0.95rem;" title="Click to hide"><i class="fa fa-eye mr-1"></i>' + escapeHtmlJs(password) + '</span>');
            $btn.data('revealed', true);
        }
    ).fail(function(xhr, status, error) {
        console.error("Failed to load password:", error);
    });
}

function copyPasswordViaCredentialID(button, credential_id) {
    jQuery.get(
        "ajax.php", {
            get_credential_via_id: 'true',
            credential_id: credential_id
        },
        function(data) {
            const credential = parseCredentialResponse(data);
            let textToCopy = credential.password;
            if (credential.type === 'Wi-Fi') {
                textToCopy = credential.wifi_passcode || credential.password || '';
            }

            copyTextToClipboard(button, textToCopy);
        }
    );
}