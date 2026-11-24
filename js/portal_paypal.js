(function () {
    'use strict';

    var box = document.querySelector('[data-paypal-pay]');
    if (!box) {
        return;
    }

    var errorBox = box.querySelector('[data-paypal-error]');
    var csrf = box.getAttribute('data-csrf-token');
    var payToken = box.getAttribute('data-pay-token');

    function showError(msg) {
        if (errorBox) {
            errorBox.textContent = msg;
            errorBox.hidden = false;
        }
    }

    if (typeof window.paypal !== 'object' || typeof window.paypal.Buttons !== 'function') {
        showError('PayPal buttons could not load. Use the link below to continue on PayPal.');
        return;
    }

    window.paypal.Buttons({
        style: { layout: 'vertical', label: 'pay' },
        createOrder: function () {
            if (errorBox) {
                errorBox.hidden = true;
            }
            var fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('pay_token', payToken);
            return fetch('/client/post.php?portal_paypal_create=1', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok || !data.id) {
                        throw new Error(data.error || 'Could not create PayPal order.');
                    }
                    return data.id;
                });
        },
        onApprove: function (approveData) {
            var fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('order_id', approveData.orderID);
            return fetch('/client/post.php?portal_paypal_capture=1', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
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
