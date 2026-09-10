// Used to populate dynamic content in the ticket and recurring ticket add modals based on selected client

// Not every modal that loads this script has every dropdown, and a modal opened
// from a contact page has no client selector at all - the client arrives as a
// hidden field. Everything below therefore checks that an element exists before
// touching it, rather than assuming the full set is present.

// Client selected listener
//  We seem to have to use jQuery to listen for events, as the client input is a select2 component?

// Wrapped in an IIFE because a modal can be opened, closed and opened again in one
// page load, which re-runs this file - a top-level const would throw on the second
// run and take the whole script with it.

(function () {

    const clientSelectDropdown = document.getElementById("changeClientSelect"); // Define client selector

    if (clientSelectDropdown) {

        // If the client selector is disabled, we must be on a client-specific page instead. Trigger the lists to update.
        if (clientSelectDropdown.disabled) {

            let client_id = $(clientSelectDropdown).find(':selected').val();

            populateLists(client_id);
        }

        // Listener for client selection. Populate select lists when a client is selected
        $(clientSelectDropdown).on('select2:select', function (e) {
            let client_id = $(this).find(':selected').val();

            // Update the dependent dropdown lists
            populateLists(client_id);

        });

    } else {

        // No client selector - the modal was opened from a contact page, where the
        // client is fixed and arrives as a hidden field instead
        const clientIdHiddenField = document.getElementById("clientIdHidden");

        if (clientIdHiddenField && clientIdHiddenField.value) {
            populateLists(clientIdHiddenField.value);
        }

    }

    // Populates dropdowns with dynamic content based on the client ID
    //  Called when the client select dropdown is used or if the client select is disabled
    function populateLists(client_id) {

        populateContactsDropdown(client_id);

        populateAssetsDropdowns(client_id);

        populateLocationsDropdown(client_id);

        populateVendorsDropdown(client_id);

        populateProjectsDropdown(client_id);
    }

    // Empties a dropdown and adds its placeholder, returning the element - or null if
    // this modal doesn't have it. Pass null as the label for a multi-select, which has
    // no placeholder option of its own.
    function resetDropdown(id, placeholderLabel, placeholderValue) {

        const dropdown = document.getElementById(id);

        if (!dropdown) {
            return null;
        }

        // innerHTML rather than removing options one by one, which leaves empty optgroups behind
        dropdown.innerHTML = '';

        // A multi-select keeps showing its old selections until select2 is told the value changed
        $(dropdown).val(null).trigger('change.select2');

        if (placeholderLabel !== null) {
            dropdown[dropdown.length] = new Option(placeholderLabel, placeholderValue);
        }

        return dropdown;
    }

    // Redraws a select2 component after its options have been replaced
    function refreshDropdown(dropdown) {
        if (dropdown) {
            $(dropdown).trigger('change.select2');
        }
    }

    // Re-applies the value the modal was opened with (e.g. ticket_add.php?project_id=4),
    // which can only be selected once the options it refers to exist
    function applyPreselection(dropdown) {

        if (!hasPreselection(dropdown)) {
            return;
        }

        dropdown.value = dropdown.dataset.selected;
    }

    // True when the modal was opened with a specific value for this dropdown.
    // '0' is the "none selected" value every one of these dropdowns uses, and is
    // truthy as a string - so it has to be excluded explicitly.
    function hasPreselection(dropdown) {
        return Boolean(dropdown && dropdown.dataset.selected && dropdown.dataset.selected !== '0');
    }

    // Adds an optgroup to a dropdown and returns it, so options can be appended into it
    function appendOptionGroup(dropdown, label) {

        if (!dropdown) {
            return null;
        }

        const group = document.createElement("optgroup");
        group.label = label;
        dropdown.appendChild(group);

        return group;
    }

    // Adds an option to an optgroup
    function appendGroupedOption(group, label, value) {

        if (!group) {
            return;
        }

        group.appendChild(new Option(label, value));
    }

    // Builds the asset label as "Name - Make Model - (Contact)", matching how assets read elsewhere
    function buildAssetLabel(asset) {

        let label = asset.asset_name;

        if (asset.asset_make) {
            label = label + " - " + asset.asset_make;

            if (asset.asset_model) {
                label = label + " " + asset.asset_model;
            }
        }

        if (asset.contact_name) {
            label = label + " - (" + asset.contact_name + ")";
        }

        return label;
    }

    // Populate client contacts - one request feeds both the contact picker and the
    // watchers list, as both are built from the same set of people
    function populateContactsDropdown(client_id) {

        if (!document.getElementById("contactSelect") && !document.getElementById("watchersSelect")) {
            return;
        }

        // Send a GET request to ajax.php as ajax.php?get_client_contacts=true&client_id=NUM
        jQuery.get(
            "ajax.php",
            {get_client_contacts: 'true', client_id: client_id},
            function(data) {

                // If we get a response from ajax.php, parse it as JSON
                const response = JSON.parse(data);

                // Access the data for contacts (multiple)
                const contacts = response.contacts || [];

                // Contacts dropdown
                const contactSelectDropdown = resetDropdown("contactSelect", '- No One -', '0');

                // Watchers is a tags field - any address can be typed in, these are just the handy ones
                const watchersDropdown = resetDropdown("watchersSelect", null, null);

                // Populate dropdown
                contacts.forEach(contact => {
                    var appendText = "";
                    if (contact.contact_title) {
                        appendText = " - " + contact.contact_title;
                    }
                    if (contact.contact_primary == "1") {
                        appendText = appendText + " (Primary)";
                    } else if (contact.contact_technical == "1") {
                        appendText = appendText + " (Technical)";
                    }

                    if (contactSelectDropdown) {
                        contactSelectDropdown[contactSelectDropdown.length] = new Option(contact.contact_name + appendText, contact.contact_id);
                    }

                    if (watchersDropdown && contact.contact_email) {
                        watchersDropdown[watchersDropdown.length] = new Option(contact.contact_email, contact.contact_email);
                    }
                });

                // Default to the client's primary contact unless the modal was opened for a
                // specific one. Contacts arrive primary-first.
                if (contactSelectDropdown && !hasPreselection(contactSelectDropdown)) {
                    const primaryContact = contacts.find(contact => contact.contact_primary == "1");

                    if (primaryContact) {
                        contactSelectDropdown.value = primaryContact.contact_id;
                    }
                }

                applyPreselection(contactSelectDropdown);

                refreshDropdown(contactSelectDropdown);
                refreshDropdown(watchersDropdown);

            }
        );
    }

    // Populate client assets - feeds both the single asset picker and the additional assets
    // multi-select from one request, as both need the same list
    function populateAssetsDropdowns(client_id) {

        if (!document.getElementById("assetSelect") && !document.getElementById("additionalAssetsSelect")) {
            return;
        }

        jQuery.get(
            "ajax.php",
            {get_client_assets: 'true', client_id: client_id},
            function(data) {

                // If we get a response from ajax.php, parse it as JSON
                const response = JSON.parse(data);

                // Access the data for assets (multiple)
                const assets = response.assets || [];

                const assetSelectDropdown = resetDropdown("assetSelect", '- None -', '0');
                const additionalAssetsDropdown = resetDropdown("additionalAssetsSelect", null, null);

                // Assets arrive ordered by type, so a change of type starts a new group
                let currentType = null;
                let assetGroup = null;
                let additionalAssetGroup = null;

                assets.forEach(asset => {
                    const assetType = asset.asset_type || 'Uncategorized';

                    if (assetType !== currentType) {
                        currentType = assetType;
                        assetGroup = appendOptionGroup(assetSelectDropdown, assetType);
                        additionalAssetGroup = appendOptionGroup(additionalAssetsDropdown, assetType);
                    }

                    const assetLabel = buildAssetLabel(asset);

                    appendGroupedOption(assetGroup, assetLabel, asset.asset_id);
                    appendGroupedOption(additionalAssetGroup, assetLabel, asset.asset_id);
                });

                applyPreselection(assetSelectDropdown);

                refreshDropdown(assetSelectDropdown);
                refreshDropdown(additionalAssetsDropdown);

            }
        );
    }

    // Populate client locations
    function populateLocationsDropdown(client_id) {

        if (!document.getElementById("locationSelect")) {
            return;
        }

        jQuery.get(
            "ajax.php",
            {get_client_locations: 'true', client_id: client_id},
            function(data) {

                // If we get a response from ajax.php, parse it as JSON
                const response = JSON.parse(data);

                // Access the data for locations (multiple)
                const locations = response.locations || [];

                // Locations dropdown
                const locationSelectDropdown = resetDropdown("locationSelect", '- Location -', '0');

                // Populate dropdown
                locations.forEach(location => {
                    locationSelectDropdown[locationSelectDropdown.length] = new Option(location.location_name, location.location_id);
                });

                applyPreselection(locationSelectDropdown);

                refreshDropdown(locationSelectDropdown);

            }
        );
    }

    // Populate client vendors
    function populateVendorsDropdown(client_id) {

        if (!document.getElementById("vendorSelect")) {
            return;
        }

        jQuery.get(
            "ajax.php",
            {get_client_vendors: 'true', client_id: client_id},
            function(data) {

                // If we get a response from ajax.php, parse it as JSON
                const response = JSON.parse(data);

                // Access the data for vendors (multiple)
                const vendors = response.vendors || [];

                // Vendors dropdown
                const vendorSelectDropdown = resetDropdown("vendorSelect", '- Vendor -', '0');

                // Populate dropdown
                vendors.forEach(vendor => {
                    vendorSelectDropdown[vendorSelectDropdown.length] = new Option(vendor.vendor_name, vendor.vendor_id);
                });

                applyPreselection(vendorSelectDropdown);

                refreshDropdown(vendorSelectDropdown);

            }
        );
    }

    // Populate client projects
    function populateProjectsDropdown(client_id) {

        if (!document.getElementById("projectSelect")) {
            return;
        }

        jQuery.get(
            "ajax.php",
            {get_client_projects: 'true', client_id: client_id},
            function(data) {

                // If we get a response from ajax.php, parse it as JSON
                const response = JSON.parse(data);

                // Access the data for projects (multiple)
                const projects = response.projects || [];

                // Projects dropdown
                const projectSelectDropdown = resetDropdown("projectSelect", '- Select Project -', '0');

                // Populate dropdown
                projects.forEach(project => {
                    projectSelectDropdown[projectSelectDropdown.length] = new Option(project.project_name, project.project_id);
                });

                applyPreselection(projectSelectDropdown);

                refreshDropdown(projectSelectDropdown);

            }
        );
    }

    // Category templates for ticket description
    const categoryTemplates = {
        // User Accounts & Access
        'Passwords': "<p><strong>--- PASSWORD RESET / ACCOUNT UNLOCK ---</strong></p><p><strong>User Account / Email:</strong> </p><p><strong>System / Service (Windows / M365 / VPN / App):</strong> </p><p><strong>Is the account locked out? (Yes/No):</strong> </p><p><strong>Best callback number for temporary password:</strong> </p>",
        'New User Setup': "<p><strong>--- NEW USER ONBOARDING / SETUP ---</strong></p><p><strong>Full Name:</strong> </p><p><strong>Start Date & Time:</strong> </p><p><strong>Job Title / Department:</strong> </p><p><strong>Reporting Manager:</strong> </p><p><strong>Hardware Needed (Laptop/Desktop/Monitors/Peripherals):</strong> </p><p><strong>Required Software & License Access:</strong> </p><p><strong>Email / Distribution Groups / Shared Mailboxes:</strong> </p><p><strong>Shared Drive / Folder Permissions:</strong> </p>",
        'Onboarding': "<p><strong>--- NEW USER ONBOARDING / SETUP ---</strong></p><p><strong>Full Name:</strong> </p><p><strong>Start Date & Time:</strong> </p><p><strong>Job Title / Department:</strong> </p><p><strong>Reporting Manager:</strong> </p><p><strong>Hardware Needed (Laptop/Desktop/Monitors/Peripherals):</strong> </p><p><strong>Required Software & License Access:</strong> </p><p><strong>Email / Distribution Groups / Shared Mailboxes:</strong> </p><p><strong>Shared Drive / Folder Permissions:</strong> </p>",
        'Offboarding': "<p><strong>--- USER OFFBOARDING ---</strong></p><p><strong>Full Name:</strong> </p><p><strong>Departure Date & Time:</strong> </p><p><strong>Job Title / Department:</strong> </p><p><strong>Manager:</strong> </p><p><strong>Account Action (Disable / Delete / Forward Email to Whom?):</strong> </p><p><strong>Mailbox / OneDrive Data Transfer Destination:</strong> </p><p><strong>Company Assets to be Returned:</strong> </p>",
        'Permissions & Group Access': "<p><strong>--- ACCESS / PERMISSIONS REQUEST ---</strong></p><p><strong>User Requiring Access:</strong> </p><p><strong>System / Folder / Shared Mailbox / Distribution Group:</strong> </p><p><strong>Access Level Needed (Read-Only / Full Edit / Admin):</strong> </p><p><strong>Manager Approval Confirmed? (Yes/No):</strong> </p><p><strong>Business Justification:</strong> </p>",
        'Permissions': "<p><strong>--- ACCESS / PERMISSIONS REQUEST ---</strong></p><p><strong>User Requiring Access:</strong> </p><p><strong>System / Folder / Shared Mailbox / Distribution Group:</strong> </p><p><strong>Access Level Needed (Read-Only / Full Edit / Admin):</strong> </p><p><strong>Manager Approval Confirmed? (Yes/No):</strong> </p><p><strong>Business Justification:</strong> </p>",
        'Account and Access': "<p><strong>--- ACCESS / PERMISSIONS REQUEST ---</strong></p><p><strong>User Requiring Access:</strong> </p><p><strong>System / Folder / Shared Mailbox / Distribution Group:</strong> </p><p><strong>Access Level Needed (Read-Only / Full Edit / Admin):</strong> </p><p><strong>Manager Approval Confirmed? (Yes/No):</strong> </p><p><strong>Business Justification:</strong> </p>",

        // Hardware & Workstations
        'Workstation Issues': "<p><strong>--- WORKSTATION ISSUE ---</strong></p><p><strong>Asset Tag / Device Name / Serial #:</strong> </p><p><strong>Device Type (Laptop / Desktop):</strong> </p><p><strong>Operating System (Windows / Mac):</strong> </p><p><strong>Exact Symptoms:</strong> </p><p><strong>Is the device completely powered off / unusable? (Yes/No):</strong> </p><p><strong>Troubleshooting / Workaround attempted:</strong> </p>",
        'Workstation': "<p><strong>--- WORKSTATION ISSUE ---</strong></p><p><strong>Asset Tag / Device Name / Serial #:</strong> </p><p><strong>Device Type (Laptop / Desktop):</strong> </p><p><strong>Operating System (Windows / Mac):</strong> </p><p><strong>Exact Symptoms:</strong> </p><p><strong>Is the device completely powered off / unusable? (Yes/No):</strong> </p><p><strong>Troubleshooting / Workaround attempted:</strong> </p>",
        'Hardware Failure': "<p><strong>--- HARDWARE ISSUE / FAILURE ---</strong></p><p><strong>Device Asset Tag / Serial #:</strong> </p><p><strong>Device Type (Laptop / Desktop / Monitor / Dock / Other):</strong> </p><p><strong>Exact Symptoms:</strong> </p><p><strong>Is the device completely powered off? (Yes/No):</strong> </p><p><strong>Troubleshooting / Workaround attempted:</strong> </p>",
        'Hardware': "<p><strong>--- HARDWARE ISSUE / FAILURE ---</strong></p><p><strong>Device Asset Tag / Serial #:</strong> </p><p><strong>Device Type (Laptop / Desktop / Monitor / Dock / Other):</strong> </p><p><strong>Exact Symptoms:</strong> </p><p><strong>Is the device completely powered off? (Yes/No):</strong> </p><p><strong>Troubleshooting / Workaround attempted:</strong> </p>",
        'Hardware Procurement & Upgrades': "<p><strong>--- HARDWARE PROCUREMENT & UPGRADES ---</strong></p><p><strong>Requested Item(s) & Quantity:</strong> </p><p><strong>Intended User / Department:</strong> </p><p><strong>Required Specifications / Needs:</strong> </p><p><strong>Date Needed By:</strong> </p><p><strong>Budget / Approval Confirmed By:</strong> </p>",
        'Procurement': "<p><strong>--- PROCUREMENT REQUEST ---</strong></p><p><strong>Requested Item(s) & Quantity:</strong> </p><p><strong>Intended User / Department:</strong> </p><p><strong>Required Specifications / Needs:</strong> </p><p><strong>Date Needed By:</strong> </p><p><strong>Budget / Approval Confirmed By:</strong> </p>",
        'Mobile Device': "<p><strong>--- MOBILE DEVICE (PHONE / TABLET) ---</strong></p><p><strong>Device Model & OS (iOS / Android):</strong> </p><p><strong>User / Phone Number / Asset Tag:</strong> </p><p><strong>Issue Type (Email setup / MDM / App issue / Lost or Damaged):</strong> </p><p><strong>Symptoms / Details:</strong> </p>",

        // Printers & Peripherals
        'Printers & Scanners': "<p><strong>--- PRINTER / SCANNER ISSUE ---</strong></p><p><strong>Printer Name / Model / IP / Asset Tag:</strong> </p><p><strong>Location (Building / Floor / Room):</strong> </p><p><strong>Issue Type (Not Printing / Paper Jam / Print Quality / Scanning / Offline):</strong> </p><p><strong>Affected Users (Single User / Everyone):</strong> </p><p><strong>Error code or message on printer display:</strong> </p>",
        'Printer': "<p><strong>--- PRINTER / SCANNER ISSUE ---</strong></p><p><strong>Printer Name / Model / IP / Asset Tag:</strong> </p><p><strong>Location (Building / Floor / Room):</strong> </p><p><strong>Issue Type (Not Printing / Paper Jam / Print Quality / Scanning / Offline):</strong> </p><p><strong>Affected Users (Single User / Everyone):</strong> </p><p><strong>Error code or message on printer display:</strong> </p>",

        // Software & Applications
        'Software': "<p><strong>--- SOFTWARE ISSUE / REQUEST ---</strong></p><p><strong>Software Name & Version:</strong> </p><p><strong>Affected User(s):</strong> </p><p><strong>Issue Type (Install Request / Error / Not Opening / License):</strong> </p><p><strong>Exact Error Message:</strong> </p><p><strong>Steps to Reproduce:</strong> </p>",
        'Line of Business Application': "<p><strong>--- LINE OF BUSINESS APPLICATION ---</strong></p><p><strong>Application Name:</strong> </p><p><strong>Affected User(s) or Teams:</strong> </p><p><strong>Severity / Business Impact (Critical / High / Normal):</strong> </p><p><strong>Exact Error Message:</strong> </p><p><strong>Steps to Reproduce:</strong> </p>",
        'Microsoft 365': "<p><strong>--- MICROSOFT 365 ISSUE / REQUEST ---</strong></p><p><strong>Affected User(s) / Account(s):</strong> </p><p><strong>Service (Teams / OneDrive / SharePoint / Outlook / Word / Excel):</strong> </p><p><strong>Issue Type (Sync Error / Sign-in / Sharing / Licensing):</strong> </p><p><strong>Details / Error Message:</strong> </p>",
        'Email': "<p><strong>--- EMAIL & MAIL FLOW ISSUE ---</strong></p><p><strong>Affected Email Address(es):</strong> </p><p><strong>Issue Type (Sending / Receiving / Spam / Delivery Delay / Outlook):</strong> </p><p><strong>Sender / Recipient of failed message:</strong> </p><p><strong>Error Message / Bounce-back (NDR) text:</strong> </p><p><strong>Date & approximate time of email:</strong> </p>",

        // Security & Phishing
        'Phishing & Suspicious Emails': "<p><strong>--- PHISHING / SUSPICIOUS EMAIL REPORT ---</strong></p><p><strong>Sender Email Address:</strong> </p><p><strong>Email Subject Line:</strong> </p><p><strong>Date & Time Received:</strong> </p><p><strong>Did anyone click links or open attachments? (Yes/No):</strong> </p><p><strong>Did anyone enter login credentials? (Yes/No):</strong> </p>",
        'Phishing Report': "<p><strong>--- PHISHING / SUSPICIOUS EMAIL REPORT ---</strong></p><p><strong>Sender Email Address:</strong> </p><p><strong>Email Subject Line:</strong> </p><p><strong>Date & Time Received:</strong> </p><p><strong>Did anyone click links or open attachments? (Yes/No):</strong> </p><p><strong>Did anyone enter login credentials? (Yes/No):</strong> </p>",
        'Security Incident': "<p><strong>--- SECURITY INCIDENT REPORT ---</strong></p><p><strong>Type of Incident (Malware / Ransomware / Account Compromise / Data Leak / Lost Device):</strong> </p><p><strong>Affected Devices / Accounts / Users:</strong> </p><p><strong>Date & Time Detected:</strong> </p><p><strong>Actions Taken So Far (e.g., disconnected network cable / powered off):</strong> </p><p><strong>Urgent Details:</strong> </p>",

        // Infrastructure & Networking
        'Network': "<p><strong>--- NETWORK / CONNECTIVITY ISSUE ---</strong></p><p><strong>Location / Office / Room:</strong> </p><p><strong>Connection Type (Wired Ethernet / LAN / Switch):</strong> </p><p><strong>Scope of Impact (Single User / Multiple / Entire Office):</strong> </p><p><strong>Symptoms (No Internet / Slow Speed / Intermittent Drops):</strong> </p>",
        'Wireless': "<p><strong>--- WIRELESS / WI-FI ISSUE ---</strong></p><p><strong>SSID / Network Name:</strong> </p><p><strong>Location / Area of Issue:</strong> </p><p><strong>Device(s) Affected:</strong> </p><p><strong>Symptoms (Unable to connect / Weak signal / Drops frequently):</strong> </p>",
        'Firewall': "<p><strong>--- FIREWALL / VPN ISSUE / REQUEST ---</strong></p><p><strong>Location / Firewall Device:</strong> </p><p><strong>Request Type (VPN Access / Port Forward / Firewall Rule / Web Filter):</strong> </p><p><strong>Source IP / Destination IP / Port:</strong> </p><p><strong>Business Justification:</strong> </p>",
        'Server': "<p><strong>--- SERVER ISSUE / MAINTENANCE ---</strong></p><p><strong>Server Name / Hostname / IP:</strong> </p><p><strong>Environment (Production / Test / Staging):</strong> </p><p><strong>Operating System / VM:</strong> </p><p><strong>Symptoms / Error Message:</strong> </p><p><strong>Impact (Outage / Degradation / Backup):</strong> </p>",
        'Backup and Recovery': "<p><strong>--- BACKUP & RESTORE REQUEST ---</strong></p><p><strong>Target System / Server / Share / Database:</strong> </p><p><strong>Request Type (Data Restore / Backup Failure / Verification):</strong> </p><p><strong>File Path & Date of data to restore:</strong> </p><p><strong>Urgency / Priority:</strong> </p>",
        'Phone and VoIP': "<p><strong>--- PHONE & VOIP ISSUE ---</strong></p><p><strong>Extension / Phone Number:</strong> </p><p><strong>Phone Model or Softphone App:</strong> </p><p><strong>Issue Type (No Dial Tone / Call Drops / Audio Quality / Routing / Voicemail):</strong> </p><p><strong>Scope (Single Phone / Call Queue / Entire Office):</strong> </p>",
        'Website and DNS': "<p><strong>--- WEBSITE & DNS ISSUE / REQUEST ---</strong></p><p><strong>Domain / URL:</strong> </p><p><strong>Record Type / Target (A / CNAME / MX / TXT / DNS change):</strong> </p><p><strong>Registrar / Host (if known):</strong> </p><p><strong>Requested Changes or Error Details:</strong> </p>",

        // Administration, Maintenance & Projects
        'IT Consultation & Project Requests': "<p><strong>--- IT CONSULTATION & PROJECT REQUEST ---</strong></p><p><strong>Project / Initiative Title:</strong> </p><p><strong>Target Completion Date:</strong> </p><p><strong>Key Stakeholders / Department:</strong> </p><p><strong>Goals & Requirements Summary:</strong> </p><p><strong>Budget / Approval Status:</strong> </p>",
        'Project Work': "<p><strong>--- PROJECT WORK ---</strong></p><p><strong>Project Name / Milestone:</strong> </p><p><strong>Task / Work Summary:</strong> </p><p><strong>Target Date:</strong> </p><p><strong>Stakeholders / Approver:</strong> </p>",
        'Maintenance': "<p><strong>--- SCHEDULED MAINTENANCE REQUEST ---</strong></p><p><strong>System(s) / Services to be Maintained:</strong> </p><p><strong>Proposed Date & Time Window:</strong> </p><p><strong>Expected Downtime / Impact:</strong> </p><p><strong>Rollback Plan:</strong> </p>",
        'Monitoring Alert': "<p><strong>--- MONITORING ALERT ---</strong></p><p><strong>Alert Name / Source Tool:</strong> </p><p><strong>Host / Device / Service Affected:</strong> </p><p><strong>Alert Details / Threshold Breached:</strong> </p><p><strong>Current Status:</strong> </p>",
        'Billing': "<p><strong>--- BILLING & INVOICE INQUIRY ---</strong></p><p><strong>Invoice Number / Reference:</strong> </p><p><strong>Account / Client Name:</strong> </p><p><strong>Question / Dispute Details:</strong> </p><p><strong>Preferred Contact Name & Email:</strong> </p>",
        'Training': "<p><strong>--- TRAINING / HOW-TO REQUEST ---</strong></p><p><strong>Topic / Tool (Teams / SharePoint / Password Manager / etc.):</strong> </p><p><strong>Attendee(s) / Department:</strong> </p><p><strong>Preferred Date & Format (1-on-1 / Group / Written Guide):</strong> </p><p><strong>Specific Questions / Goals:</strong> </p>",
        'Vendor Coordination': "<p><strong>--- VENDOR COORDINATION ---</strong></p><p><strong>Vendor Name & Contact Info:</strong> </p><p><strong>Product / Service / Ticket Reference #:</strong> </p><p><strong>Issue Summary / Work being coordinated:</strong> </p><p><strong>Internal Point of Contact:</strong> </p>",
        'Other': "<p><strong>--- GENERAL SUPPORT REQUEST ---</strong></p><p><strong>Summary of Request / Issue:</strong> </p><p><strong>Affected Users / Equipment:</strong> </p><p><strong>Urgency / Business Impact:</strong> </p><p><strong>Additional Details:</strong> </p>"
    };

    $(document).off('change.categoryTemplate').on('change.categoryTemplate', 'select[name="category_id"]', function () {
        const selected = ($(this).find(':selected').text() || '').trim();
        const template = categoryTemplates[selected] || (Object.keys(categoryTemplates).find(k => selected.toLowerCase().includes(k.toLowerCase())) ? categoryTemplates[Object.keys(categoryTemplates).find(k => selected.toLowerCase().includes(k.toLowerCase()))] : null);

        if (template) {
            if (window.tinymce) {
                const editor = tinymce.get('detailsInput') || tinymce.activeEditor;
                if (editor) {
                    editor.setContent(template);
                } else {
                    $('#detailsInput').val(template);
                }
            } else {
                $('#detailsInput').val(template);
            }
        }
    });

})();
