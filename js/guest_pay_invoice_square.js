(function () {
    'use strict';

    var form = document.getElementById('square-payment-form');
    if (!form) {
        return;
    }

    var submitBtn = document.getElementById('square-submit');
    var spinner = document.getElementById('square-spinner');
    var btnText = document.getElementById('square-button-text');
    var msgBox = document.getElementById('square-message');
    var saveCheck = document.getElementById('square-save-card');

    function showError(msg) {
        msgBox.textContent = msg;
        msgBox.classList.remove('d-none');
    }

    function hideError() {
        msgBox.classList.add('d-none');
    }

    function setBusy(busy) {
        submitBtn.disabled = busy;
        spinner.classList.toggle('d-none', !busy);
    }

    if (typeof window.Square !== 'object' || typeof window.Square.payments !== 'function') {
        showError('Square card form could not load. Please reload the page.');
        return;
    }

    var appId = form.getAttribute('data-app-id');
    var locationId = form.getAttribute('data-location-id');
    var payments;
    var card;

    (async function initSquare() {
        try {
            payments = window.Square.payments(appId, locationId);
            card = await payments.card();
            await card.attach('#square-card-container');
            submitBtn.disabled = false;
        } catch (err) {
            showError('Could not initialize Square card entry: ' + (err && err.message ? err.message : 'unavailable'));
        }
    })();

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!card) {
            return;
        }
        hideError();
        setBusy(true);

        try {
            var verificationDetails = {
                amount: form.getAttribute('data-amount'),
                currencyCode: form.getAttribute('data-currency'),
                intent: 'CHARGE',
                customerInitiated: true,
                sellerKeyedIn: false
            };
            var tokenResult = await card.tokenize(verificationDetails);
            if (tokenResult.status !== 'OK' || !tokenResult.token) {
                var detail = tokenResult.errors && tokenResult.errors[0] ? tokenResult.errors[0].message : 'Please check your card details.';
                showError(detail);
                setBusy(false);
                return;
            }

            var resp = await fetch('guest_pay_invoice_square.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    invoice_id: parseInt(form.getAttribute('data-invoice-id'), 10),
                    url_key: form.getAttribute('data-url-key'),
                    pay_token: form.getAttribute('data-pay-token'),
                    source_id: tokenResult.token,
                    verification_token: tokenResult.verificationToken || '',
                    save_card: saveCheck && saveCheck.checked ? 1 : 0
                })
            });
            var data = await resp.json().catch(function () { return {}; });
            if (data.ok && data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            showError(data.error || 'Card payment failed. Nothing was charged.');
        } catch (err) {
            showError(err && err.message ? err.message : 'Could not complete card payment.');
        }
        setBusy(false);
    });
})();
