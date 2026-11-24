(function () {
    'use strict';

    function show(el, on) {
        if (el) {
            el.hidden = !on;
        }
    }

    var payForm = document.querySelector('form[data-square-pay]');
    if (payForm) {
        var submit = payForm.querySelector('[data-square-submit]');
        var errorBox = payForm.querySelector('[data-square-error]');
        var setBusy = function (on) {
            submit.disabled = on;
            show(payForm.querySelector('[data-square-busy]'), on);
            show(payForm.querySelector('[data-square-idle]'), !on);
        };

        if (typeof window.Square !== 'object' || typeof window.Square.payments !== 'function') {
            show(payForm.querySelector('[data-square-loading]'), false);
            show(payForm.querySelector('[data-square-unavailable]'), true);
        } else {
            var payments = window.Square.payments(payForm.getAttribute('data-square-app-id'), payForm.getAttribute('data-square-location-id'));
            var card = null;
            payments.card().then(function (c) {
                card = c;
                return card.attach('#square-card-container');
            }).then(function () {
                show(payForm.querySelector('[data-square-loading]'), false);
                submit.disabled = false;
            }).catch(function () {
                show(payForm.querySelector('[data-square-loading]'), false);
                show(payForm.querySelector('[data-square-unavailable]'), true);
            });

            payForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!card) {
                    return;
                }
                show(errorBox, false);
                setBusy(true);
                card.tokenize({
                    amount: payForm.getAttribute('data-amount'),
                    currencyCode: payForm.getAttribute('data-currency'),
                    intent: 'CHARGE',
                    customerInitiated: true,
                    sellerKeyedIn: false
                }).then(function (result) {
                    if (result.status !== 'OK' || !result.token) {
                        var msg = result.errors && result.errors[0] ? result.errors[0].message : 'Please check your card details.';
                        throw new Error(msg);
                    }
                    var fd = new FormData();
                    fd.append('csrf_token', payForm.getAttribute('data-csrf-token'));
                    fd.append('pay_token', payForm.getAttribute('data-pay-token'));
                    fd.append('source_id', result.token);
                    fd.append('verification_token', result.verificationToken || '');
                    return fetch('/client/post.php?portal_square_pay=1', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: fd
                    }).then(function (r) { return r.json(); });
                }).then(function (data) {
                    if (data && data.ok && data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    errorBox.textContent = (data && data.error) ? data.error : 'Card payment failed - nothing was charged.';
                    show(errorBox, true);
                    setBusy(false);
                }).catch(function (err) {
                    errorBox.textContent = err && err.message ? err.message : 'Something went wrong taking the payment.';
                    show(errorBox, true);
                    setBusy(false);
                });
            });
        }
    }

    var saveHolder = document.querySelector('[data-square-save-card]');
    if (saveHolder) {
        var started = false;
        var saveCard = null;
        var saveBtn = saveHolder.querySelector('[data-square-save-btn]');
        var saveError = saveHolder.querySelector('[data-square-error]');
        var setSaveBusy = function (on) {
            saveBtn.disabled = on;
            show(saveHolder.querySelector('[data-square-busy]'), on);
            show(saveHolder.querySelector('[data-square-idle]'), !on);
        };

        var startSave = function () {
            if (started) {
                return;
            }
            started = true;
            show(saveHolder, true);
            document.querySelectorAll('[data-square-add-card]').forEach(function (btn) {
                show(btn, false);
            });
            if (typeof window.Square !== 'object' || typeof window.Square.payments !== 'function') {
                show(saveHolder.querySelector('[data-square-loading]'), false);
                show(saveHolder.querySelector('[data-square-unavailable]'), true);
                return;
            }
            var payments = window.Square.payments(saveHolder.getAttribute('data-square-app-id'), saveHolder.getAttribute('data-square-location-id'));
            payments.card().then(function (c) {
                saveCard = c;
                return saveCard.attach('#square-save-card-container');
            }).then(function () {
                show(saveHolder.querySelector('[data-square-loading]'), false);
                saveBtn.disabled = false;
            }).catch(function () {
                show(saveHolder.querySelector('[data-square-loading]'), false);
                show(saveHolder.querySelector('[data-square-unavailable]'), true);
            });
        };

        document.querySelectorAll('[data-square-add-card]').forEach(function (btn) {
            btn.addEventListener('click', startSave);
        });

        saveBtn.addEventListener('click', function () {
            if (!saveCard) {
                return;
            }
            show(saveError, false);
            setSaveBusy(true);
            saveCard.tokenize({
                intent: 'STORE',
                customerInitiated: true,
                sellerKeyedIn: false
            }).then(function (result) {
                if (result.status !== 'OK' || !result.token) {
                    var msg = result.errors && result.errors[0] ? result.errors[0].message : 'Please check your card details.';
                    throw new Error(msg);
                }
                var fd = new FormData();
                fd.append('csrf_token', saveHolder.getAttribute('data-csrf-token'));
                fd.append('source_id', result.token);
                return fetch('/client/post.php?square_save_card=1', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                }).then(function (r) { return r.json(); });
            }).then(function (data) {
                if (data && data.ok && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                saveError.textContent = (data && data.error) ? data.error : 'Could not save card. Please try again.';
                show(saveError, true);
                setSaveBusy(false);
            }).catch(function (err) {
                saveError.textContent = err && err.message ? err.message : 'Could not save card.';
                show(saveError, true);
                setSaveBusy(false);
            });
        });
    }
})();
