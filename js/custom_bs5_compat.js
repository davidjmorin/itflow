(function (window, document) {
    'use strict';

    function syncDarkMode() {
        if (!document.body) {
            return;
        }
        if (document.documentElement.getAttribute('data-bs-theme') === 'dark') {
            document.body.classList.add('dark-mode');
        } else {
            document.body.classList.remove('dark-mode');
        }
    }

    function upgradeBs4Attrs(root) {
        if (!root || root.nodeType !== 1 && root.nodeType !== 9) {
            return;
        }
        const pairs = [
            ['data-toggle', 'data-bs-toggle'],
            ['data-target', 'data-bs-target'],
            ['data-dismiss', 'data-bs-dismiss'],
            ['data-parent', 'data-bs-parent']
        ];
        pairs.forEach(function (pair) {
            const oldAttr = pair[0];
            const newAttr = pair[1];
            if (root.nodeType === 1 && root.hasAttribute(oldAttr) && !root.hasAttribute(newAttr)) {
                root.setAttribute(newAttr, root.getAttribute(oldAttr));
            }
            if (typeof root.querySelectorAll === 'function') {
                root.querySelectorAll('[' + oldAttr + ']').forEach(function (el) {
                    if (!el.hasAttribute(newAttr)) {
                        el.setAttribute(newAttr, el.getAttribute(oldAttr));
                    }
                });
            }
        });
    }

    syncDarkMode();

    function onReady() {
        syncDarkMode();
        upgradeBs4Attrs(document);
        if (document.body && typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1) {
                            upgradeBs4Attrs(node);
                        }
                    });
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState !== 'loading') {
        onReady();
    } else {
        document.addEventListener('DOMContentLoaded', onReady);
    }

    if (window.jQuery) {
        const $ = window.jQuery;

        $.fn.modal = function (action) {
            return this.each(function () {
                if (!window.bootstrap || !window.bootstrap.Modal) {
                    return;
                }
                const opts = (typeof action === 'object' && action) ? action : {};
                const inst = window.bootstrap.Modal.getOrCreateInstance(this, opts);
                if (action === 'hide') {
                    inst.hide();
                } else if (action === 'toggle') {
                    inst.toggle();
                } else if (action === 'dispose') {
                    inst.dispose();
                } else if (action === 'show' || action === undefined || (typeof action === 'object' && action.show !== false)) {
                    inst.show();
                }
            });
        };

        $.fn.collapse = function (action) {
            return this.each(function () {
                if (!window.bootstrap || !window.bootstrap.Collapse) {
                    return;
                }
                const inst = window.bootstrap.Collapse.getOrCreateInstance(this, { toggle: false });
                if (action === 'show') {
                    inst.show();
                } else if (action === 'hide') {
                    inst.hide();
                } else {
                    inst.toggle();
                }
            });
        };

        $.fn.tab = function () {
            return this.each(function () {
                if (!window.bootstrap || !window.bootstrap.Tab) {
                    return;
                }
                window.bootstrap.Tab.getOrCreateInstance(this).show();
            });
        };

        $.fn.dropdown = function (action) {
            return this.each(function () {
                if (!window.bootstrap || !window.bootstrap.Dropdown) {
                    return;
                }
                const inst = window.bootstrap.Dropdown.getOrCreateInstance(this);
                if (action === 'show') {
                    inst.show();
                } else if (action === 'hide') {
                    inst.hide();
                } else {
                    inst.toggle();
                }
            });
        };
    }

    if (!window.toastr) {
        const notify = function (type, msg) {
            const text = String(msg == null ? '' : msg);
            if (window.Swal && typeof window.Swal.fire === 'function') {
                window.Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: type,
                    title: text,
                    showConfirmButton: false,
                    timer: type === 'error' ? 4500 : 3000,
                    timerProgressBar: true
                });
                return;
            }
            const alertType = type === 'error' ? 'danger' : type;
            const toastEl = document.createElement('div');
            toastEl.className = 'alert alert-' + alertType + ' shadow-sm position-fixed';
            toastEl.style.top = '1rem';
            toastEl.style.right = '1rem';
            toastEl.style.zIndex = '1090';
            toastEl.style.maxWidth = '360px';
            toastEl.textContent = text;
            document.body.appendChild(toastEl);
            setTimeout(function () {
                if (toastEl.parentNode) {
                    toastEl.parentNode.removeChild(toastEl);
                }
            }, type === 'error' ? 4500 : 3000);
        };
        window.toastr = {
            success: function (msg) { notify('success', msg); },
            error: function (msg) { notify('error', msg); },
            warning: function (msg) { notify('warning', msg); },
            info: function (msg) { notify('info', msg); }
        };
    }
})(window, document);
