<?php
/*
 * Client Portal
 * New ticket form
 */

require_once 'includes/inc_all.php';

// Allow clients to select a related asset when raising a ticket
$sql_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type FROM assets WHERE asset_contact_id = $session_contact_id AND asset_client_id = $session_client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");

?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="index.php">Home</a>
        </li>
        <li class="breadcrumb-item">
            <a href="tickets.php">Tickets</a>
        </li>
        <li class="breadcrumb-item active">New Ticket</li>
    </ol>

    <h3>Raise a new ticket</h3>

    <div class="col-md-8">
        <form action="post.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label>Subject <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                    </div>
                    <input type="text" class="form-control" name="subject" placeholder="Subject" required>
                </div>
            </div>

            <div class="row">
                <div class="col">
                    <div class="form-group">
                        <label>Priority <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                            </div>
                            <select class="form-control select2" name="priority" required>
                                <option>Low</option>
                                <option>Medium</option>
                                <option>High</option>
                                <option>Urgent</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="form-group">
                    <label>Category</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                        </div>
                        <select class="form-control select2" name="category">
                            <option value="0">- No Category -</option>
                            <?php
                            $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL");
                            while ($row = mysqli_fetch_assoc($sql_categories)) {
                                $category_id = intval($row['category_id']);
                                $category_name = escapeHtml($row['category_name']);

                                ?>
                                <option value="<?= $category_id ?>"><?= $category_name ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>
                </div>
            </div>

            <?php if (mysqli_num_rows($sql_assets) > 0) { ?>
                <div class="form-group">
                    <label>Asset or Device Needing Support</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                        </div>
                        <select class="form-control select2" name="asset">
                            <option value="0">- None -</option>
                            <?php

                            while ($row = mysqli_fetch_assoc($sql_assets)) {
                                $asset_id = intval($row['asset_id']);
                                $asset_name = escapeSql($row['asset_name']);
                                $asset_type = escapeSql($row['asset_type']);
                                ?>
                                <option value="<?= $asset_id ?>"><?= "$asset_name ($asset_type)" ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>
            <?php } ?>


            <div class="form-group">
                <label>Details <strong class="text-danger">*</strong></label>
                <textarea class="form-control tinymce" id="details" name="details"></textarea>
            </div>

            <button class="btn btn-primary" name="add_ticket">Raise ticket</button>

        </form>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const categorySelect = document.querySelector('select[name="category"]');
    const descriptionBox = document.querySelector('textarea[name="details"], textarea[name="description"]');

    const templates = {
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

    if (categorySelect) {
        $(categorySelect).on('change', function () {
            const selected = (this.options[this.selectedIndex] ? this.options[this.selectedIndex].text : '').trim();
            const template = templates[selected] || (Object.keys(templates).find(k => selected.toLowerCase().includes(k.toLowerCase())) ? templates[Object.keys(templates).find(k => selected.toLowerCase().includes(k.toLowerCase()))] : null);

            if (template) {
                if (window.tinymce && (tinymce.get('details') || tinymce.activeEditor)) {
                    const editor = tinymce.get('details') || tinymce.activeEditor;
                    editor.setContent(template);
                } else if (descriptionBox) {
                    const tmp = document.createElement('div');
                    tmp.innerHTML = template;
                    descriptionBox.value = tmp.textContent || tmp.innerText || '';
                }
            }
        });
    }
});
</script>

<?php
require_once 'includes/footer.php';
