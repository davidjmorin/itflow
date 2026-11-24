(function () {
    'use strict';

    var box = document.getElementById('paypal-checkout-box');
    if (!box) {
        return;
    }

    var msgBox = document.getElementById('paypal-message');
    var invoiceId = parseInt(box.getAttribute('data-invoice-id'), 10);
    var urlKey = box.getAttribute('data-url-key');
    var payToken = box.getAttribute('data-pay-token');

    function showError(msg) {
        msgBox.textContent = msg;
        msgBox.classList.remove('d-none');
    }

    if (typeof window.paypal !== 'object' || typeof window.paypal.Buttons !== 'function') {
        showError('PayPal buttons could not load. Use the link below to pay on PayPal.');
        return;
    }

    window.paypal.Buttons({
        style: { layout: 'vertical', label: 'pay' },
        createOrder: function () {
            msgBox.classList.add('d-none');
            return fetch('guest_pay_invoice_paypal.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    action: 'create_order',
                    invoice_id: invoiceId,
                    url_key: urlKey,
                    pay_token: payToken
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok || !data.id) {
                        throw new Error(data.error || 'Could not start PayPal checkout.');
                    }
                    return data.id;
                });
        },
        onApprove: function (approveData) {
            return fetch('guest_pay_invoice_paypal.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    action: 'capture_order',
                    invoice_id: invoiceId,
                    url_key: urlKey,
                    pay_token: payToken,
                    order_id: approveData.orderID
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok && data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    showError(data.error || 'Could not capture PayPal payment.');
                });
        },
        onError: function (err) {
            showError(err && err.message ? err.message : 'PayPal encountered an error. Nothing was charged.');
        }
    }).render('#paypal-button-container');
})();
