(function () {
    'use strict';

    if (window.__itflowAddressAutocompleteLoaded) {
        return;
    }
    window.__itflowAddressAutocompleteLoaded = true;

    const COUNTRY_ALIASES = {
        'US': 'United States',
        'USA': 'United States',
        'GB': 'United Kingdom',
        'UK': 'United Kingdom',
        'CA': 'Canada',
        'AU': 'Australia',
        'DE': 'Germany',
        'FR': 'France',
        'ES': 'Spain',
        'IT': 'Italy',
        'MX': 'Mexico',
        'BR': 'Brazil',
        'IN': 'India',
        'JP': 'Japan',
        'CN': 'China',
        'NL': 'Netherlands',
        'NZ': 'New Zealand',
        'IE': 'Ireland',
        'CH': 'Switzerland',
        'SE': 'Sweden',
        'NO': 'Norway',
        'DK': 'Denmark',
        'FI': 'Finland',
        'BE': 'Belgium',
        'AT': 'Austria',
        'ZA': 'South Africa',
        'SG': 'Singapore'
    };

    const AUTOCOMPLETE_SELECTOR = [
        'input[name="address"]',
        'input[name="lead_address"]',
        'input[name="company_address"]',
        '.address-autocomplete',
        'input[name="name"]#client_name',
        'input[name="name"].company-autocomplete',
        'input[name="lead_name"]',
        '.company-autocomplete'
    ].join(', ');

    let activeDropdown = null;
    let activeInput = null;
    let debounceTimer = null;
    let isSelecting = false;

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function isDarkMode() {
        return document.documentElement.getAttribute('data-bs-theme') === 'dark'
            || (document.body && document.body.classList.contains('dark-mode'));
    }

    function notifyToast(icon, title) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                toast: true,
                position: 'top-end',
                icon: icon,
                title: title,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
            return;
        }
        if (window.toastr && typeof window.toastr[icon] === 'function') {
            window.toastr[icon](title);
        }
    }

    function highlightField(el) {
        if (!el || !el.classList) {
            return;
        }
        el.classList.add('is-autocomplete-filled');
        setTimeout(function () {
            el.classList.remove('is-autocomplete-filled');
        }, 1500);
    }

    function setInputValue(el, val) {
        if (!el) {
            return;
        }
        el.value = val;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        highlightField(el);
    }

    function setCountrySelect(selectEl, countryLongName, countryShortName) {
        if (!selectEl) {
            return;
        }
        const longNameNorm = (countryLongName || '').trim().toLowerCase();
        const shortNameNorm = (countryShortName || '').trim().toUpperCase();
        const mappedName = (COUNTRY_ALIASES[shortNameNorm] || '').toLowerCase();

        let matchedVal = null;
        const options = selectEl.querySelectorAll('option');
        for (let i = 0; i < options.length; i++) {
            const opt = options[i];
            const optVal = (opt.value || '').trim();
            const optText = (opt.textContent || '').trim();
            const optValLower = optVal.toLowerCase();
            const optTextLower = optText.toLowerCase();

            if (
                optValLower === longNameNorm
                || optTextLower === longNameNorm
                || (mappedName && (optValLower === mappedName || optTextLower === mappedName))
                || (shortNameNorm && (optVal.toUpperCase() === shortNameNorm || optText.toUpperCase() === shortNameNorm))
            ) {
                matchedVal = optVal;
                break;
            }
        }

        if (matchedVal !== null) {
            selectEl.value = matchedVal;
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
            const nextWrap = selectEl.nextElementSibling;
            if (nextWrap && (nextWrap.classList.contains('itflow-select') || nextWrap.classList.contains('select2-container'))) {
                highlightField(nextWrap);
            } else {
                highlightField(selectEl);
            }
        }
    }

    function matchesAutocomplete(el) {
        if (!el || el.nodeType !== 1 || typeof el.matches !== 'function') {
            return false;
        }
        if (el.matches(AUTOCOMPLETE_SELECTOR)) {
            return true;
        }
        return !!(el.matches('input[name="name"]') && el.form && el.form.querySelector('input[name="update_primary_address"]'));
    }

    function closeActiveDropdown() {
        document.querySelectorAll('.itflow-autocomplete-dropdown').forEach(function (node) {
            if (node.parentNode) {
                node.parentNode.removeChild(node);
            }
        });
        activeDropdown = null;
        activeInput = null;
        clearTimeout(debounceTimer);
    }

    function positionDropdown() {
        if (!activeDropdown || !activeInput) {
            return;
        }
        if (!activeInput.isConnected) {
            closeActiveDropdown();
            return;
        }
        const rect = activeInput.getBoundingClientRect();
        if (rect.width === 0 && rect.height === 0) {
            closeActiveDropdown();
            return;
        }
        activeDropdown.style.top = (rect.bottom + 2) + 'px';
        activeDropdown.style.left = rect.left + 'px';
        activeDropdown.style.width = rect.width + 'px';
    }

    function findField(container, selectors) {
        for (let i = 0; i < selectors.length; i++) {
            const el = container.querySelector(selectors[i]);
            if (el) {
                return el;
            }
        }
        return null;
    }

    function renderPendingBanner(container, details) {
        container.querySelectorAll('.primary-address-pending-banner').forEach(function (el) {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        });

        const dark = isDarkMode();
        const bannerBg = dark ? '#1e384d' : '#d4edda';
        const bannerBorder = dark ? '#2c5d82' : '#c3e6cb';
        const bannerTitleColor = dark ? '#58a6ff' : '#155724';
        const bannerTextColor = dark ? '#e6edf3' : '#155724';

        const banner = document.createElement('div');
        banner.className = 'alert fade show p-2 mt-2 mb-3 primary-address-pending-banner shadow-sm';
        banner.style.borderLeft = '4px solid #28a745';
        banner.style.backgroundColor = bannerBg;
        banner.style.borderColor = bannerBorder;
        banner.style.color = bannerTextColor;

        banner.innerHTML = ''
            + '<div class="d-flex align-items-center justify-content-between">'
            + '<div>'
            + '<div class="fw-bold" style="color:' + bannerTitleColor + ';"><i class="fas fa-check-circle me-1"></i>Primary Address will be updated on save:</div>'
            + '<div class="small mt-1" style="color:' + bannerTextColor + ';font-weight:500;">' + escapeHtml(details.fullAddress) + '</div>'
            + (details.phone ? '<div class="small mt-1" style="opacity:0.85;"><i class="fas fa-phone me-1"></i>' + escapeHtml(details.phone) + '</div>' : '')
            + '</div>'
            + '<button type="button" class="btn btn-sm btn-outline-secondary ms-2 btn-cancel-primary-update" title="Undo address update"><i class="fas fa-undo me-1"></i>Undo</button>'
            + '</div>';

        const pendingContainer = container.querySelector('[id^="primaryAddressPendingContainer"]');
        if (pendingContainer) {
            pendingContainer.innerHTML = '';
            pendingContainer.appendChild(banner);
        } else {
            const nameInput = container.querySelector('input[name="name"]');
            const wrapper = nameInput ? (nameInput.closest('.mb-3, .form-group') || nameInput) : null;
            if (wrapper && wrapper.parentNode) {
                wrapper.parentNode.insertBefore(banner, wrapper.nextSibling);
            }
        }

        const undoBtn = banner.querySelector('.btn-cancel-primary-update');
        if (undoBtn) {
            undoBtn.addEventListener('click', function () {
                const resetNames = ['primary_address', 'primary_city', 'primary_state', 'primary_zip', 'primary_country', 'primary_phone'];
                const updField = container.querySelector('input[name="update_primary_address"]');
                if (updField) {
                    updField.value = '0';
                }
                resetNames.forEach(function (nm) {
                    const fld = container.querySelector('input[name="' + nm + '"]');
                    if (fld) {
                        fld.value = '';
                    }
                });
                if (banner.parentNode) {
                    banner.parentNode.removeChild(banner);
                }
                notifyToast('info', 'Primary address update cancelled.');
            });
        }
    }

    function promptPrimaryAddressUpdate(container, details) {
        document.querySelectorAll('.itflow-address-prompt-overlay').forEach(function (node) {
            if (node.parentNode) {
                node.parentNode.removeChild(node);
            }
        });

        const dark = isDarkMode();
        const modalBg = dark ? '#343a40' : '#ffffff';
        const textColor = dark ? '#f8f9fa' : '#212529';
        const mutedColor = dark ? '#adb5bd' : '#6c757d';
        const cardBg = dark ? '#2b3035' : '#f8f9fa';
        const cardBorder = dark ? '#4b545c' : '#e9ecef';
        const newAddrBg = dark ? '#1e384d' : '#e8f4fd';
        const newAddrBorder = dark ? '#2c5d82' : '#b6dffe';

        const modalTarget = container.closest('.modal-content') || container.querySelector('.modal-content') || container;
        modalTarget.style.position = 'relative';

        const overlay = document.createElement('div');
        overlay.className = 'itflow-address-prompt-overlay';
        overlay.style.position = 'absolute';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.right = '0';
        overlay.style.bottom = '0';
        overlay.style.background = 'rgba(0, 0, 0, 0.65)';
        overlay.style.zIndex = '1055';
        overlay.style.display = 'flex';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.padding = '16px';
        overlay.style.borderRadius = '4px';

        overlay.innerHTML = ''
            + '<div class="card shadow-lg" style="max-width:490px;width:100%;background-color:' + modalBg + ';color:' + textColor + ';border:1px solid ' + cardBorder + ';border-radius:8px;">'
            + '<div class="card-header bg-primary text-white d-flex align-items-center justify-content-between py-2 px-3">'
            + '<h6 class="mb-0 fw-bold" style="font-size:1rem;"><i class="fas fa-map-marker-alt me-2"></i>Update Primary Address?</h6>'
            + '<button type="button" class="btn-close btn-close-white itflow-prompt-btn-no" aria-label="Close"></button>'
            + '</div>'
            + '<div class="card-body p-3">'
            + '<p class="mb-3" style="font-size:0.9rem;">Google Places found an address for <strong>' + escapeHtml(details.companyName || 'this business') + '</strong>. Would you like to update the client\'s <strong>Primary Address</strong> with this new address?</p>'
            + '<div class="p-2 mb-2 rounded" style="background-color:' + newAddrBg + ';border:1px solid ' + newAddrBorder + ';">'
            + '<div class="small text-uppercase fw-bold text-primary mb-1"><i class="fas fa-arrow-circle-right me-1"></i>New Address from Google Places</div>'
            + '<div class="fw-bold" style="font-size:0.92rem;color:' + textColor + ';">' + escapeHtml(details.fullAddress) + '</div>'
            + (details.phone ? '<div class="mt-1 small" style="color:' + mutedColor + ';"><i class="fas fa-phone me-1"></i>' + escapeHtml(details.phone) + '</div>' : '')
            + '</div>'
            + '<div class="p-2 mb-3 rounded" style="background-color:' + cardBg + ';border:1px solid ' + cardBorder + ';">'
            + '<div class="small text-uppercase fw-bold mb-1" style="color:' + mutedColor + ';"><i class="fas fa-history me-1"></i>Current Primary Address</div>'
            + '<div style="font-size:0.88rem;color:' + (details.currentAddress ? textColor : mutedColor) + ';font-style:' + (details.currentAddress ? 'normal' : 'italic') + ';">'
            + (details.currentAddress ? escapeHtml(details.currentAddress) : 'None currently set')
            + '</div>'
            + '</div>'
            + '<div class="d-flex justify-content-end align-items-center mt-3 pt-2" style="border-top:1px solid ' + cardBorder + ';">'
            + '<button type="button" class="btn btn-sm btn-outline-secondary itflow-prompt-btn-no me-2"><i class="fas fa-times me-1"></i>No, Keep Existing</button>'
            + '<button type="button" class="btn btn-sm btn-success itflow-prompt-btn-yes fw-bold"><i class="fas fa-check me-1"></i>Yes, Update Address</button>'
            + '</div>'
            + '</div>'
            + '</div>';

        modalTarget.appendChild(overlay);

        const yesBtn = overlay.querySelector('.itflow-prompt-btn-yes');
        if (yesBtn) {
            yesBtn.addEventListener('click', function () {
                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
                const fieldMap = {
                    update_primary_address: '1',
                    primary_address: details.streetAddress || '',
                    primary_city: details.city || '',
                    primary_state: details.state || '',
                    primary_zip: details.zip || '',
                    primary_country: details.country || '',
                    primary_phone: details.phone || ''
                };
                Object.keys(fieldMap).forEach(function (nm) {
                    const fld = container.querySelector('input[name="' + nm + '"]');
                    if (fld) {
                        fld.value = fieldMap[nm];
                    }
                });
                renderPendingBanner(container, details);
                notifyToast('success', 'Primary address will be updated when you save changes.');
            });
        }

        overlay.querySelectorAll('.itflow-prompt-btn-no').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
                notifyToast('info', 'Primary address kept unchanged.');
            });
        });
    }

    function applyPlaceDetails(data, inputEl) {
        if (!data || !data.addressComponents) {
            return;
        }

        let streetNumber = '';
        let route = '';
        let subpremise = '';
        let locality = '';
        let sublocality = '';
        let postalTown = '';
        let adminAreaLevel1Short = '';
        let adminAreaLevel1Long = '';
        let adminAreaLevel2 = '';
        let postalCode = '';
        let countryLong = '';
        let countryShort = '';

        data.addressComponents.forEach(function (comp) {
            const types = comp.types || [];
            const longText = comp.longText || comp.long_name || '';
            const shortText = comp.shortText || comp.short_name || '';

            if (types.indexOf('street_number') !== -1) {
                streetNumber = longText || shortText;
            }
            if (types.indexOf('route') !== -1) {
                route = longText || shortText;
            }
            if (types.indexOf('subpremise') !== -1) {
                subpremise = longText || shortText;
            }
            if (types.indexOf('locality') !== -1) {
                locality = longText;
            }
            if (types.indexOf('sublocality_level_1') !== -1 || types.indexOf('sublocality') !== -1) {
                sublocality = longText;
            }
            if (types.indexOf('postal_town') !== -1) {
                postalTown = longText;
            }
            if (types.indexOf('administrative_area_level_2') !== -1) {
                adminAreaLevel2 = longText;
            }
            if (types.indexOf('administrative_area_level_1') !== -1) {
                adminAreaLevel1Short = shortText;
                adminAreaLevel1Long = longText;
            }
            if (types.indexOf('postal_code') !== -1) {
                postalCode = longText || shortText;
            }
            if (types.indexOf('country') !== -1) {
                countryLong = longText;
                countryShort = shortText;
            }
        });

        let streetAddress = '';
        if (streetNumber && route) {
            streetAddress = streetNumber + ' ' + route;
        } else if (route) {
            streetAddress = route;
        } else if (data.displayName && data.displayName.text) {
            streetAddress = data.displayName.text;
        } else if (data.formattedAddress) {
            streetAddress = data.formattedAddress.split(',')[0];
        }

        if (subpremise) {
            streetAddress += (streetAddress ? ', ' : '') + 'Ste ' + subpremise;
        }

        const city = locality || postalTown || sublocality || adminAreaLevel2 || '';
        const state = adminAreaLevel1Short || adminAreaLevel1Long || '';
        const companyName = (data.displayName && data.displayName.text) ? data.displayName.text : '';
        const phone = data.nationalPhoneNumber || data.internationalPhoneNumber || '';
        const website = data.websiteUri ? data.websiteUri.replace(/^https?:\/\//, '').replace(/\/$/, '') : '';

        const container = inputEl.closest('form, .modal-content, .modal, .card') || document.body;
        const inputName = inputEl.getAttribute('name') || '';
        const isLead = inputName.indexOf('lead_') === 0;
        const isCompanySettings = inputName.indexOf('company_') === 0;
        const isCompanyInput = inputName === 'name' || inputName === 'lead_name';

        if (isCompanyInput && companyName) {
            setInputValue(inputEl, companyName);
        }

        const addressSelectors = isLead
            ? ['input[name="lead_address"]']
            : (isCompanySettings ? ['input[name="company_address"]'] : ['input[name="address"]']);
        const addressEl = isCompanyInput ? findField(container, addressSelectors) : inputEl;
        if (addressEl && streetAddress) {
            setInputValue(addressEl, streetAddress);
        }

        const cityEl = findField(container, isLead
            ? ['input[name="lead_city"]']
            : (isCompanySettings ? ['input[name="company_city"]'] : ['input[name="city"]']));
        if (cityEl && city) {
            setInputValue(cityEl, city);
        }

        const stateEl = findField(container, isLead
            ? ['input[name="lead_state"]']
            : (isCompanySettings ? ['input[name="company_state"]'] : ['input[name="state"]']));
        if (stateEl && state) {
            setInputValue(stateEl, state);
        }

        const zipEl = findField(container, isLead
            ? ['input[name="lead_zip"]']
            : (isCompanySettings ? ['input[name="company_zip"]'] : ['input[name="zip"]']));
        if (zipEl && postalCode) {
            setInputValue(zipEl, postalCode);
        }

        const countryEl = findField(container, isLead
            ? ['select[name="lead_country"]', 'input[name="lead_country"]']
            : (isCompanySettings
                ? ['select[name="company_country"]', 'input[name="company_country"]']
                : ['select[name="country"]', 'input[name="country"]']));
        if (countryEl && (countryLong || countryShort)) {
            if (countryEl.tagName === 'SELECT') {
                setCountrySelect(countryEl, countryLong, countryShort);
            } else {
                setInputValue(countryEl, countryLong || countryShort);
            }
        }

        if (phone) {
            const phoneEl = findField(container, isLead
                ? ['input[name="lead_contact_phone"]', 'input[name="lead_contact_mobile"]']
                : (isCompanySettings ? ['input[name="company_phone"]'] : ['input[name="location_phone"]', 'input[name="phone"]']));
            if (phoneEl) {
                setInputValue(phoneEl, phone);
            }
        }

        if (website) {
            const websiteEl = findField(container, isLead
                ? ['input[name="lead_website"]']
                : (isCompanySettings ? ['input[name="company_website"]'] : ['input[name="website"]']));
            if (websiteEl) {
                setInputValue(websiteEl, website);
            }
        }

        const hasVisibleAddress = isCompanyInput ? !!findField(container, addressSelectors) : true;
        const hasClientId = !!container.querySelector('input[name="client_id"]');
        const hasNewAddress = !!(streetAddress || locality || postalTown || sublocality || adminAreaLevel2 || companyName || data.formattedAddress);

        if (isCompanyInput && !hasVisibleAddress && hasClientId && hasNewAddress) {
            const fullNewAddress = [
                streetAddress,
                city,
                [adminAreaLevel1Short || adminAreaLevel1Long, postalCode].filter(Boolean).join(' '),
                countryLong || countryShort
            ].filter(Boolean).join(', ') || data.formattedAddress || '';

            const currentPrimaryAddress = (inputEl.getAttribute('data-current-primary-address') || '').trim();

            promptPrimaryAddressUpdate(container, {
                streetAddress: streetAddress,
                city: city,
                state: adminAreaLevel1Short || adminAreaLevel1Long,
                zip: postalCode,
                country: countryLong || countryShort,
                phone: phone,
                fullAddress: fullNewAddress,
                currentAddress: currentPrimaryAddress,
                companyName: companyName || inputEl.value
            });
        }
    }

    function selectSuggestion(placeId, mainText, inputEl) {
        isSelecting = true;
        closeActiveDropdown();

        if (mainText) {
            inputEl.value = mainText;
        }

        if (!placeId) {
            setTimeout(function () {
                isSelecting = false;
            }, 400);
            return;
        }

        fetch('/agent/ajax.php?address_place_details=1&place_id=' + encodeURIComponent(placeId), {
            credentials: 'same-origin'
        })
            .then(function (resp) {
                if (!resp.ok) {
                    throw new Error('HTTP ' + resp.status);
                }
                return resp.json();
            })
            .then(function (data) {
                applyPlaceDetails(data, inputEl);
                closeActiveDropdown();
                setTimeout(function () {
                    isSelecting = false;
                }, 400);
            })
            .catch(function (err) {
                console.error('[ITFlow Address Autocomplete] Failed to fetch place details:', err);
                closeActiveDropdown();
                setTimeout(function () {
                    isSelecting = false;
                }, 400);
            });
    }

    function renderDropdown(suggestions, inputEl) {
        closeActiveDropdown();

        if (isSelecting || !suggestions || !suggestions.length || !inputEl.isConnected) {
            return;
        }

        const dark = isDarkMode();
        const bgColor = dark ? '#343a40' : '#ffffff';
        const borderColor = dark ? '#4b545c' : '#ced4da';
        const itemBorderColor = dark ? '#3f474e' : '#f1f3f5';
        const mainTextColor = dark ? '#ffffff' : '#212529';
        const secTextColor = dark ? '#adb5bd' : '#6c757d';
        const hoverBgColor = dark ? '#3f474e' : '#e8f0fe';
        const footerBgColor = dark ? '#2b3035' : '#f8f9fa';

        const dropdown = document.createElement('div');
        dropdown.className = 'itflow-autocomplete-dropdown';
        dropdown.style.position = 'fixed';
        dropdown.style.backgroundColor = bgColor;
        dropdown.style.border = '1px solid ' + borderColor;
        dropdown.style.borderRadius = '4px';
        dropdown.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.25)';
        dropdown.style.maxHeight = '280px';
        dropdown.style.overflowY = 'auto';
        dropdown.style.padding = '4px 0';
        dropdown.style.zIndex = '100000';

        suggestions.forEach(function (s, index) {
            const pred = s.placePrediction || {};
            const placeId = pred.placeId || pred.place || '';
            const mainText = (pred.structuredFormat && pred.structuredFormat.mainText && pred.structuredFormat.mainText.text)
                ? pred.structuredFormat.mainText.text
                : (pred.text ? pred.text.text : '');
            const secondaryText = (pred.structuredFormat && pred.structuredFormat.secondaryText && pred.structuredFormat.secondaryText.text)
                ? pred.structuredFormat.secondaryText.text
                : '';

            const types = pred.types || [];
            const isEstablishment = types.indexOf('establishment') !== -1
                || types.indexOf('point_of_interest') !== -1
                || types.indexOf('corporate_office') !== -1
                || types.indexOf('store') !== -1
                || types.indexOf('company') !== -1;

            const iconHtml = isEstablishment
                ? '<i class="fas fa-building text-info me-2" style="font-size:1rem;flex-shrink:0;" title="Business / Company"></i>'
                : '<i class="fas fa-map-marker-alt text-primary me-2" style="font-size:1rem;flex-shrink:0;" title="Address"></i>';

            const item = document.createElement('div');
            item.className = 'itflow-autocomplete-item';
            item.setAttribute('data-place-id', placeId);
            item.setAttribute('data-index', String(index));
            item.style.padding = '9px 12px';
            item.style.cursor = 'pointer';
            item.style.display = 'flex';
            item.style.alignItems = 'center';
            item.style.borderBottom = '1px solid ' + itemBorderColor;
            item.style.backgroundColor = bgColor;
            item.style.lineHeight = '1.4';
            item.style.fontSize = '0.875rem';
            item.innerHTML = iconHtml
                + '<div style="flex-grow:1;">'
                + '<span class="item-main" style="font-weight:600;color:' + mainTextColor + ';">' + escapeHtml(mainText) + '</span>'
                + (secondaryText ? '<span class="item-secondary" style="color:' + secTextColor + ';font-size:0.8rem;margin-left:6px;">' + escapeHtml(secondaryText) + '</span>' : '')
                + '</div>';

            item.addEventListener('mouseenter', function () {
                item.style.backgroundColor = hoverBgColor;
            });
            item.addEventListener('mouseleave', function () {
                if (!item.classList.contains('active')) {
                    item.style.backgroundColor = bgColor;
                }
            });

            const chooseHandler = function (e) {
                e.preventDefault();
                e.stopPropagation();
                selectSuggestion(placeId, mainText, inputEl);
            };
            item.addEventListener('mousedown', chooseHandler);
            item.addEventListener('touchstart', chooseHandler, { passive: false });

            dropdown.appendChild(item);
        });

        const footer = document.createElement('div');
        footer.style.padding = '5px 12px';
        footer.style.fontSize = '0.75rem';
        footer.style.color = secTextColor;
        footer.style.backgroundColor = footerBgColor;
        footer.style.borderTop = '1px solid ' + itemBorderColor;
        footer.style.textAlign = 'right';
        footer.innerHTML = '<i class="fab fa-google me-1"></i>powered by Google';
        dropdown.appendChild(footer);

        document.body.appendChild(dropdown);
        activeDropdown = dropdown;
        activeInput = inputEl;
        positionDropdown();
    }

    function doSearch(inputEl) {
        if (isSelecting) {
            return;
        }
        const query = (inputEl.value || '').trim();
        clearTimeout(debounceTimer);

        if (query.length < 2) {
            closeActiveDropdown();
            return;
        }

        debounceTimer = setTimeout(function () {
            if (isSelecting) {
                return;
            }
            fetch('/agent/ajax.php?address_autocomplete=1&query=' + encodeURIComponent(query), {
                credentials: 'same-origin'
            })
                .then(function (resp) {
                    if (!resp.ok) {
                        throw new Error('HTTP ' + resp.status);
                    }
                    return resp.json();
                })
                .then(function (response) {
                    if (isSelecting) {
                        return;
                    }
                    if (response && response.suggestions && response.suggestions.length) {
                        renderDropdown(response.suggestions, inputEl);
                    } else {
                        closeActiveDropdown();
                    }
                })
                .catch(function (err) {
                    console.error('[ITFlow Address Autocomplete] Ajax error:', err);
                    closeActiveDropdown();
                });
        }, 150);
    }

    document.addEventListener('input', function (e) {
        if (isSelecting) {
            return;
        }
        if (matchesAutocomplete(e.target)) {
            doSearch(e.target);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (!matchesAutocomplete(e.target) || !activeDropdown) {
            return;
        }
        const items = Array.prototype.slice.call(activeDropdown.querySelectorAll('.itflow-autocomplete-item'));
        if (!items.length) {
            return;
        }
        let currentIndex = -1;
        for (let i = 0; i < items.length; i++) {
            if (items[i].classList.contains('active')) {
                currentIndex = i;
                break;
            }
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const nextIndex = (currentIndex + 1) >= items.length ? 0 : currentIndex + 1;
            items.forEach(function (it) { it.classList.remove('active'); });
            items[nextIndex].classList.add('active');
            items[nextIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prevIndex = (currentIndex - 1) < 0 ? items.length - 1 : currentIndex - 1;
            items.forEach(function (it) { it.classList.remove('active'); });
            items[prevIndex].classList.add('active');
            items[prevIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            if (currentIndex >= 0 && items[currentIndex]) {
                e.preventDefault();
                e.stopPropagation();
                const current = items[currentIndex];
                const placeId = current.getAttribute('data-place-id') || '';
                const mainSpan = current.querySelector('.item-main');
                const mainText = mainSpan ? mainSpan.textContent : '';
                selectSuggestion(placeId, mainText, e.target);
            }
        } else if (e.key === 'Escape') {
            closeActiveDropdown();
        }
    });

    document.addEventListener('focusout', function (e) {
        const target = e.target;
        if (!matchesAutocomplete(target)) {
            return;
        }
        setTimeout(function () {
            if (activeInput === target) {
                closeActiveDropdown();
            }
        }, 200);
    });

    document.addEventListener('click', function (e) {
        if (!activeDropdown) {
            return;
        }
        const t = e.target;
        if (t && t.nodeType === 1 && !t.closest('.itflow-autocomplete-dropdown') && t !== activeInput) {
            closeActiveDropdown();
        }
    });

    window.addEventListener('resize', positionDropdown);
    document.addEventListener('scroll', positionDropdown, true);

    window.initAddressAutocomplete = function () {};
})();
