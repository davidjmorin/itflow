/**
 * ITFlow - Google Places Address Autocomplete (Places API New)
 * Automatically suggests and auto-populates address fields (street address,
 * city, state/province, postal code, country) across client, location,
 * lead, and company forms.
 * Version: 2.2.0
 */

(function () {
    'use strict';

    console.log('[ITFlow Address Autocomplete] Loaded v2.2.0');

    /**
     * Map country aliases/short codes to full country names used in ITFlow
     */
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

    /**
     * Flash highlight an element to show it was auto-populated
     */
    function highlightField($el) {
        if (!$el || !$el.length) return;
        $el.addClass('is-autocomplete-filled');
        setTimeout(function () {
            $el.removeClass('is-autocomplete-filled');
        }, 1500);
    }

    /**
     * Select a country in a <select> dropdown (including Select2)
     */
    function setCountrySelect($select, countryLongName, countryShortName) {
        if (!$select.length) return;

        const longNameNorm = (countryLongName || '').trim().toLowerCase();
        const shortNameNorm = (countryShortName || '').trim().toUpperCase();
        const mappedName = (COUNTRY_ALIASES[shortNameNorm] || '').toLowerCase();

        let matchedVal = null;

        $select.find('option').each(function () {
            const optVal = ($(this).val() || '').trim();
            const optText = ($(this).text() || '').trim();
            const optValLower = optVal.toLowerCase();
            const optTextLower = optText.toLowerCase();

            if (
                optValLower === longNameNorm ||
                optTextLower === longNameNorm ||
                optValLower === mappedName ||
                optTextLower === mappedName ||
                optVal.toUpperCase() === shortNameNorm ||
                optText.toUpperCase() === shortNameNorm
            ) {
                matchedVal = optVal;
                return false; // break
            }
        });

        if (matchedVal !== null) {
            $select.val(matchedVal).trigger('change');
            const $select2Container = $select.siblings('.select2-container');
            if ($select2Container.length) {
                highlightField($select2Container.find('.select2-selection'));
            } else {
                highlightField($select);
            }
        }
    }

    /**
     * Active dropdown tracking
     */
    let $activeDropdown = null;
    let $activeInput = null;
    let debounceTimer = null;
    let isSelecting = false;

    function closeActiveDropdown() {
        $('.itflow-autocomplete-dropdown').remove();
        $activeDropdown = null;
        $activeInput = null;
        clearTimeout(debounceTimer);
    }

    // Close dropdown on click outside
    $(document).on('click', function (e) {
        if ($activeDropdown && !$(e.target).closest('.itflow-autocomplete-dropdown, input[name="address"], input[name="lead_address"], input[name="company_address"]').length) {
            closeActiveDropdown();
        }
    });

    /**
     * Handle place details response and populate form fields
     */
    function applyPlaceDetails(data, $input) {
        if (!data || !data.addressComponents) return;

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

            if (types.includes('street_number')) {
                streetNumber = longText || shortText;
            }
            if (types.includes('route')) {
                route = longText || shortText;
            }
            if (types.includes('subpremise')) {
                subpremise = longText || shortText;
            }
            if (types.includes('locality')) {
                locality = longText;
            }
            if (types.includes('sublocality_level_1') || types.includes('sublocality')) {
                sublocality = longText;
            }
            if (types.includes('postal_town')) {
                postalTown = longText;
            }
            if (types.includes('administrative_area_level_2')) {
                adminAreaLevel2 = longText;
            }
            if (types.includes('administrative_area_level_1')) {
                adminAreaLevel1Short = shortText;
                adminAreaLevel1Long = longText;
            }
            if (types.includes('postal_code')) {
                postalCode = longText || shortText;
            }
            if (types.includes('country')) {
                countryLong = longText;
                countryShort = shortText;
            }
        });

        // Compute street address
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

        // Container search across the form or modal
        const $container = $input.closest('form, .modal-content, .modal, .card, body');
        const inputName = $input.attr('name') || '';
        const isLead = inputName.indexOf('lead_') === 0;
        const isCompanyInput = inputName === 'name' || inputName === 'lead_name';

        // 1. Company / Client Name
        if (isCompanyInput) {
            if (companyName) {
                $input.val(companyName).trigger('change');
                highlightField($input);
            }
        }

        // 2. Street Address
        const addressSelector = isLead ? 'input[name="lead_address"]' : 'input[name="address"]';
        const $addressInput = isCompanyInput ? $container.find(addressSelector) : $input;
        if ($addressInput.length && streetAddress) {
            $addressInput.val(streetAddress).trigger('change');
            highlightField($addressInput);
        }

        // 3. City
        const citySelector = isLead ? 'input[name="lead_city"]' : 'input[name="city"]';
        const $cityInput = $container.find(citySelector);
        if ($cityInput.length && city) {
            $cityInput.val(city).trigger('input').trigger('change');
            highlightField($cityInput);
        }

        // 4. State / Province
        const stateSelector = isLead ? 'input[name="lead_state"]' : 'input[name="state"]';
        const $stateInput = $container.find(stateSelector);
        if ($stateInput.length && state) {
            $stateInput.val(state).trigger('input').trigger('change');
            highlightField($stateInput);
        }

        // 5. Postal Code
        const zipSelector = isLead ? 'input[name="lead_zip"]' : 'input[name="zip"]';
        const $zipInput = $container.find(zipSelector);
        if ($zipInput.length && postalCode) {
            $zipInput.val(postalCode).trigger('input').trigger('change');
            highlightField($zipInput);
        }

        // 6. Country
        const countrySelector = isLead
            ? 'select[name="lead_country"], input[name="lead_country"]'
            : 'select[name="country"], input[name="country"]';
        const $countryField = $container.find(countrySelector);

        if ($countryField.length && (countryLong || countryShort)) {
            if ($countryField.is('select')) {
                setCountrySelect($countryField, countryLong, countryShort);
            } else {
                $countryField.val(countryLong || countryShort).trigger('change');
                highlightField($countryField);
            }
        }

        // 7. Phone Number
        if (phone) {
            const phoneSelector = isLead
                ? 'input[name="lead_contact_phone"], input[name="lead_contact_mobile"]'
                : 'input[name="location_phone"], input[name="phone"]';
            const $phoneInput = $container.find(phoneSelector).first();
            if ($phoneInput.length) {
                $phoneInput.val(phone).trigger('change');
                highlightField($phoneInput);
            }
        }

        // 8. Website
        if (website) {
            const websiteSelector = isLead ? 'input[name="lead_website"]' : 'input[name="website"]';
            const $websiteInput = $container.find(websiteSelector);
            if ($websiteInput.length) {
                $websiteInput.val(website).trigger('change');
                highlightField($websiteInput);
            }
        }

        // 9. Prompt to update Primary Address if this is an edit form without visible address fields
        const hasVisibleAddress = isCompanyInput ? $container.find(addressSelector).length > 0 : true;
        const hasClientId = $container.find('input[name="client_id"]').length > 0;
        const hasNewAddress = streetAddress || locality || postalTown || sublocality || adminAreaLevel2 || (data.displayName && data.displayName.text) || data.formattedAddress;

        if (isCompanyInput && !hasVisibleAddress && hasClientId && hasNewAddress) {
            const fullNewAddress = [
                streetAddress,
                city,
                [adminAreaLevel1Short || adminAreaLevel1Long, postalCode].filter(Boolean).join(' '),
                countryLong || countryShort
            ].filter(Boolean).join(', ') || data.formattedAddress || '';

            const currentPrimaryAddress = ($input.data('current-primary-address') || '').trim();

            promptPrimaryAddressUpdate($container, {
                streetAddress: streetAddress,
                city: city,
                state: adminAreaLevel1Short || adminAreaLevel1Long,
                zip: postalCode,
                country: countryLong || countryShort,
                phone: phone,
                fullAddress: fullNewAddress,
                currentAddress: currentPrimaryAddress,
                companyName: companyName || $input.val()
            });
        }
    }

    /**
     * Render the pending update banner on the edit form
     */
    function renderPendingBanner($container, details) {
        $container.find('.primary-address-pending-banner').remove();

        const isDarkMode = $('body').hasClass('dark-mode');
        const bannerBg = isDarkMode ? '#1e384d' : '#d4edda';
        const bannerBorder = isDarkMode ? '#2c5d82' : '#c3e6cb';
        const bannerTitleColor = isDarkMode ? '#58a6ff' : '#155724';
        const bannerTextColor = isDarkMode ? '#e6edf3' : '#155724';

        const bannerHtml = `
            <div class="alert alert-dismissible fade show p-2 mt-2 mb-3 primary-address-pending-banner shadow-sm" style="
                border-left: 4px solid #28a745 !important;
                background-color: ${bannerBg};
                border-color: ${bannerBorder};
                color: ${bannerTextColor};
            ">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="font-weight-bold" style="color: ${bannerTitleColor};">
                            <i class="fas fa-check-circle mr-1"></i>Primary Address will be updated on save:
                        </div>
                        <div class="text-sm mt-1" style="color: ${bannerTextColor}; font-weight: 500;">
                            ${$('<div>').text(details.fullAddress).html()}
                        </div>
                        ${details.phone ? `<div class="text-xs mt-1" style="opacity: 0.85;"><i class="fas fa-phone mr-1"></i>${$('<div>').text(details.phone).html()}</div>` : ''}
                    </div>
                    <button type="button" class="btn btn-xs btn-outline-secondary ml-2 btn-cancel-primary-update" title="Undo address update">
                        <i class="fas fa-undo mr-1"></i>Undo
                    </button>
                </div>
            </div>
        `;

        const $pendingContainer = $container.find('[id^="primaryAddressPendingContainer"]');
        if ($pendingContainer.length) {
            $pendingContainer.html(bannerHtml);
        } else {
            $container.find('input[name="name"]').closest('.form-group').after(bannerHtml);
        }

        // Ensure modal body scrolls and footer stays pinned
        const $form = $container.is('form') ? $container : $container.closest('form');
        if ($form.length) {
            $form.css({
                'display': 'flex',
                'flex-direction': 'column',
                'flex': '1 1 auto',
                'min-height': '0',
                'overflow': 'hidden'
            });
            $form.find('.modal-body').css({
                'overflow-y': 'auto',
                'max-height': 'calc(75vh - 120px)',
                'flex': '1 1 auto',
                'min-height': '0'
            });
            $form.find('.modal-header, .nav-pills, .modal-footer').css({
                'flex-shrink': '0'
            });
            $form.closest('.modal-content').css({
                'max-height': 'calc(100vh - 40px)',
                'display': 'flex',
                'flex-direction': 'column'
            });
        }

        $container.find('.btn-cancel-primary-update').off('click').on('click', function () {
            $container.find('input[name="update_primary_address"]').val('0');
            $container.find('input[name="primary_address"]').val('');
            $container.find('input[name="primary_city"]').val('');
            $container.find('input[name="primary_state"]').val('');
            $container.find('input[name="primary_zip"]').val('');
            $container.find('input[name="primary_country"]').val('');
            $container.find('input[name="primary_phone"]').val('');
            $container.find('.primary-address-pending-banner').slideUp(200, function () { $(this).remove(); });
            if (typeof toastr !== 'undefined') {
                toastr.info('Primary address update cancelled.', 'Cancelled');
            }
        });
    }

    /**
     * Prompt user to update Primary Address on forms without visible address fields (e.g. client_edit.php)
     */
    function promptPrimaryAddressUpdate($container, details) {
        $('.itflow-address-prompt-overlay').remove();

        const isDarkMode = $('body').hasClass('dark-mode');
        const modalBg = isDarkMode ? '#343a40' : '#ffffff';
        const textColor = isDarkMode ? '#f8f9fa' : '#212529';
        const mutedColor = isDarkMode ? '#adb5bd' : '#6c757d';
        const cardBg = isDarkMode ? '#2b3035' : '#f8f9fa';
        const cardBorder = isDarkMode ? '#4b545c' : '#e9ecef';
        const newAddrBg = isDarkMode ? '#1e384d' : '#e8f4fd';
        const newAddrBorder = isDarkMode ? '#2c5d82' : '#b6dffe';

        const $modalTarget = $container.closest('.modal-content').length
            ? $container.closest('.modal-content')
            : $container;

        $modalTarget.css('position', 'relative');

        const promptHtml = `
            <div class="itflow-address-prompt-overlay" style="
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0, 0, 0, 0.65);
                z-index: 1055;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 16px;
                border-radius: 4px;
            ">
                <div class="card shadow-lg" style="
                    max-width: 490px;
                    width: 100%;
                    background-color: ${modalBg};
                    color: ${textColor};
                    border: 1px solid ${cardBorder};
                    border-radius: 8px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important;
                ">
                    <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between py-2 px-3">
                        <h6 class="mb-0 font-weight-bold" style="font-size: 1rem;">
                            <i class="fas fa-map-marker-alt mr-2"></i>Update Primary Address?
                        </h6>
                        <button type="button" class="close text-white itflow-prompt-btn-no" style="font-size: 1.25rem; opacity: 0.9;" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="card-body p-3">
                        <p class="mb-3" style="font-size: 0.9rem;">
                            Google Places found an address for <strong>${$('<div>').text(details.companyName || 'this business').html()}</strong>.
                            Would you like to update the client's <strong>Primary Address</strong> with this new address?
                        </p>

                        <div class="p-2 mb-2 rounded" style="background-color: ${newAddrBg}; border: 1px solid ${newAddrBorder};">
                            <div class="text-xs text-uppercase font-weight-bold text-primary mb-1">
                                <i class="fas fa-arrow-circle-right mr-1"></i>New Address from Google Places
                            </div>
                            <div class="font-weight-bold" style="font-size: 0.92rem; color: ${textColor};">
                                ${$('<div>').text(details.fullAddress).html()}
                            </div>
                            ${details.phone ? `
                                <div class="mt-1 text-xs" style="color: ${mutedColor};">
                                    <i class="fas fa-phone mr-1"></i>${$('<div>').text(details.phone).html()}
                                </div>
                            ` : ''}
                        </div>

                        <div class="p-2 mb-3 rounded" style="background-color: ${cardBg}; border: 1px solid ${cardBorder};">
                            <div class="text-xs text-uppercase font-weight-bold mb-1" style="color: ${mutedColor};">
                                <i class="fas fa-history mr-1"></i>Current Primary Address
                            </div>
                            <div style="font-size: 0.88rem; color: ${details.currentAddress ? textColor : mutedColor}; font-style: ${details.currentAddress ? 'normal' : 'italic'};">
                                ${details.currentAddress ? $('<div>').text(details.currentAddress).html() : 'None currently set'}
                            </div>
                        </div>

                        <div class="d-flex justify-content-end align-items-center mt-3 pt-2" style="border-top: 1px solid ${cardBorder};">
                            <button type="button" class="btn btn-sm btn-outline-secondary itflow-prompt-btn-no mr-2">
                                <i class="fas fa-times mr-1"></i>No, Keep Existing
                            </button>
                            <button type="button" class="btn btn-sm btn-success itflow-prompt-btn-yes font-weight-bold">
                                <i class="fas fa-check mr-1"></i>Yes, Update Address
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const $overlay = $(promptHtml);
        $modalTarget.append($overlay);

        // On Yes
        $overlay.find('.itflow-prompt-btn-yes').on('click', function () {
            $overlay.remove();

            // Populate hidden inputs in the form
            $container.find('input[name="update_primary_address"]').val('1');
            $container.find('input[name="primary_address"]').val(details.streetAddress || '');
            $container.find('input[name="primary_city"]').val(details.city || '');
            $container.find('input[name="primary_state"]').val(details.state || '');
            $container.find('input[name="primary_zip"]').val(details.zip || '');
            $container.find('input[name="primary_country"]').val(details.country || '');
            $container.find('input[name="primary_phone"]').val(details.phone || '');

            // Render pending update banner
            renderPendingBanner($container, details);

            if (typeof toastr !== 'undefined') {
                toastr.success('Primary address will be updated when you save changes.', 'Address Queued');
            }
        });

        // On No / Dismiss
        $overlay.find('.itflow-prompt-btn-no').on('click', function () {
            $overlay.remove();
            if (typeof toastr !== 'undefined') {
                toastr.info('Primary address kept unchanged.', 'No Changes');
            }
        });
    }

    /**
     * Select a suggestion
     */
    function selectSuggestion(placeId, mainText, $input) {
        isSelecting = true;
        closeActiveDropdown();

        if (mainText) {
            $input.val(mainText);
        }

        if (!placeId) {
            setTimeout(function () { isSelecting = false; }, 400);
            return;
        }

        console.log('[ITFlow Address Autocomplete] Selected place:', placeId);

        // Fetch place details via ajax.php
        $.getJSON('/agent/ajax.php', {
            address_place_details: 1,
            place_id: placeId
        }, function (data) {
            applyPlaceDetails(data, $input);
            closeActiveDropdown();
            setTimeout(function () { isSelecting = false; }, 400);
        }).fail(function (err) {
            console.error('[ITFlow Address Autocomplete] Failed to fetch place details:', err);
            closeActiveDropdown();
            setTimeout(function () { isSelecting = false; }, 400);
        });
    }

    /**
     * Render the suggestions dropdown directly under the input
     */
    function renderDropdown(suggestions, $input) {
        closeActiveDropdown();

        if (isSelecting || !suggestions || !suggestions.length) {
            return;
        }

        const isDarkMode = $('body').hasClass('dark-mode');
        const bgColor = isDarkMode ? '#343a40' : '#ffffff';
        const borderColor = isDarkMode ? '#4b545c' : '#ced4da';
        const itemBorderColor = isDarkMode ? '#3f474e' : '#f1f3f5';
        const mainTextColor = isDarkMode ? '#ffffff' : '#212529';
        const secTextColor = isDarkMode ? '#adb5bd' : '#6c757d';
        const hoverBgColor = isDarkMode ? '#3f474e' : '#e8f0fe';
        const footerBgColor = isDarkMode ? '#2b3035' : '#f8f9fa';

        const $dropdown = $(`
            <div class="itflow-autocomplete-dropdown" style="
                background-color: ${bgColor} !important;
                border: 1px solid ${borderColor} !important;
                border-radius: 4px !important;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25) !important;
                max-height: 280px;
                overflow-y: auto;
                padding: 4px 0;
                margin-top: 2px;
                z-index: 99999 !important;
            "></div>
        `);

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
            const isEstablishment = types.includes('establishment') || types.includes('point_of_interest') || types.includes('corporate_office') || types.includes('store') || types.includes('company');
            const iconHtml = isEstablishment
                ? '<i class="fas fa-building text-info mr-2" style="font-size: 1rem; flex-shrink: 0;" title="Business / Company"></i>'
                : '<i class="fas fa-map-marker-alt text-primary mr-2" style="font-size: 1rem; flex-shrink: 0;" title="Address"></i>';

            const $item = $(`
                <div class="itflow-autocomplete-item" data-place-id="${$('<div>').text(placeId).html()}" data-index="${index}" style="
                    padding: 9px 12px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    border-bottom: 1px solid ${itemBorderColor};
                    background-color: ${bgColor};
                    line-height: 1.4;
                    font-size: 0.875rem;
                ">
                    ${iconHtml}
                    <div style="flex-grow: 1;">
                        <span class="item-main" style="font-weight: 600; color: ${mainTextColor};">${$('<div>').text(mainText).html()}</span>
                        ${secondaryText ? `<span class="item-secondary" style="color: ${secTextColor}; font-size: 0.8rem; margin-left: 6px;">${$('<div>').text(secondaryText).html()}</span>` : ''}
                    </div>
                </div>
            `);

            $item.on('mouseenter', function () {
                $(this).css('background-color', hoverBgColor);
            });
            $item.on('mouseleave', function () {
                if (!$(this).hasClass('active')) {
                    $(this).css('background-color', bgColor);
                }
            });

            $item.on('mousedown touchstart click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                selectSuggestion(placeId, mainText, $input);
                return false;
            });

            $dropdown.append($item);
        });

        // Google branding footer
        $dropdown.append(`
            <div style="
                padding: 5px 12px;
                font-size: 0.75rem;
                color: ${secTextColor};
                background-color: ${footerBgColor};
                border-top: 1px solid ${itemBorderColor};
                text-align: right;
            "><i class="fab fa-google mr-1"></i>powered by Google</div>
        `);

        // Position dropdown:
        // Position directly under the input group inside the form group
        const $parentGroup = $input.closest('.form-group');
        if ($parentGroup.length) {
            $parentGroup.css('position', 'relative').append($dropdown);
            const $inputGroup = $input.closest('.input-group');
            const topPos = $inputGroup.length ? ($inputGroup.position().top + $inputGroup.outerHeight() + 2) : ($input.position().top + $input.outerHeight() + 2);
            $dropdown.css({
                top: topPos + 'px',
                left: '0',
                right: '0',
                width: '100%',
                position: 'absolute'
            });
        } else {
            const offset = $input.offset();
            $dropdown.css({
                top: (offset.top + $input.outerHeight() + 2) + 'px',
                left: offset.left + 'px',
                width: $input.outerWidth() + 'px',
                position: 'absolute'
            });
            $('body').append($dropdown);
        }

        $activeDropdown = $dropdown;
        $activeInput = $input;
    }

    /**
     * Perform the autocomplete search request
     */
    function doSearch($input) {
        if (isSelecting) return;
        const query = $input.val().trim();
        clearTimeout(debounceTimer);

        if (query.length < 2) {
            closeActiveDropdown();
            return;
        }

        debounceTimer = setTimeout(function () {
            if (isSelecting) return;
            console.log('[ITFlow Address Autocomplete] Querying:', query);
            $.getJSON('/agent/ajax.php', {
                address_autocomplete: 1,
                query: query
            }, function (response) {
                if (isSelecting) return;
                if (response && response.suggestions && response.suggestions.length) {
                    renderDropdown(response.suggestions, $input);
                } else {
                    closeActiveDropdown();
                }
            }).fail(function (err) {
                console.error('[ITFlow Address Autocomplete] Ajax error:', err);
                closeActiveDropdown();
            });
        }, 150);
    }


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

    $(document).on('input', AUTOCOMPLETE_SELECTOR, function () {
        if (isSelecting) return;
        doSearch($(this));
    });

    $(document).on('keydown', AUTOCOMPLETE_SELECTOR, function (e) {
        if (!$activeDropdown || !$activeDropdown.is(':visible')) {
            return;
        }

        const $items = $activeDropdown.find('.itflow-autocomplete-item');
        const $current = $items.filter('.active');
        let currentIndex = $items.index($current);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            let nextIndex = currentIndex + 1;
            if (nextIndex >= $items.length) nextIndex = 0;
            $items.removeClass('active');
            $items.eq(nextIndex).addClass('active');
            $items.eq(nextIndex)[0].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            let prevIndex = currentIndex - 1;
            if (prevIndex < 0) prevIndex = $items.length - 1;
            $items.removeClass('active');
            $items.eq(prevIndex).addClass('active');
            $items.eq(prevIndex)[0].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            if ($current.length) {
                e.preventDefault();
                e.stopPropagation();
                const placeId = $current.data('place-id');
                const mainText = $current.find('.item-main').text();
                selectSuggestion(placeId, mainText, $(this));
                return false;
            }
        } else if (e.key === 'Escape') {
            closeActiveDropdown();
        }
    });

    $(document).on('blur', AUTOCOMPLETE_SELECTOR, function () {
        const input = this;
        setTimeout(function () {
            if ($activeInput && $activeInput[0] === input) {
                closeActiveDropdown();
            }
        }, 250);
    });

    // Global helper
    window.initAddressAutocomplete = function () {
        console.log('[ITFlow Address Autocomplete] Ready');
    };

})();
