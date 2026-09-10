<?php
/*
 * Client Portal
 * Landing / Home page for the client portal
 */

header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data:;");

require_once "includes/inc_all.php";

// Billing Card Queries
 //Add up all the payments for the invoice and get the total amount paid to the invoice
$sql_invoice_amounts = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE invoice_client_id = $session_client_id AND invoice_status != 'Draft' AND invoice_status != 'Cancelled' AND invoice_status != 'Non-Billable'");
$row = mysqli_fetch_assoc($sql_invoice_amounts);

$invoice_amounts = floatval($row['invoice_amounts']);

$sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_client_id = $session_client_id");
$row = mysqli_fetch_assoc($sql_amount_paid);

$amount_paid = floatval($row['amount_paid']);

$balance = $invoice_amounts - $amount_paid;

//Get Monthly Recurring Total
$sql_recurring_monthly_total = mysqli_query($mysqli, "SELECT SUM(recurring_invoice_amount) AS recurring_monthly_total FROM recurring_invoices WHERE recurring_invoice_status = 1 AND recurring_invoice_frequency = 'month' AND recurring_invoice_client_id = $session_client_id");
$row = mysqli_fetch_assoc($sql_recurring_monthly_total);

$recurring_monthly_total = floatval($row['recurring_monthly_total']);

//Get Yearly Recurring Total
$sql_recurring_yearly_total = mysqli_query($mysqli, "SELECT SUM(recurring_invoice_amount) AS recurring_yearly_total FROM recurring_invoices WHERE recurring_invoice_status = 1 AND recurring_invoice_frequency = 'year' AND recurring_invoice_client_id = $session_client_id");
$row = mysqli_fetch_assoc($sql_recurring_yearly_total);

$recurring_yearly_total = floatval($row['recurring_yearly_total']) / 12;

$recurring_monthly = $recurring_monthly_total + $recurring_yearly_total;

// Technical Card Queries
// 8 - 45 Day Warning

// Get Domains Expiring
$sql_domains_expiring = mysqli_query(
    $mysqli,
    "SELECT domain_expire, domain_id, domain_name FROM domains
    WHERE domain_client_id = $session_client_id
        AND domain_expire IS NOT NULL
        AND domain_archived_at IS NULL
        AND domain_expire > CURRENT_DATE
        AND domain_expire < CURRENT_DATE + INTERVAL 45 DAY
    ORDER BY domain_expire ASC"
);

// Get Certificates Expiring
$sql_certificates_expiring = mysqli_query(
    $mysqli,
    "SELECT * FROM certificates
    WHERE certificate_client_id = $session_client_id
        AND certificate_expire IS NOT NULL
        AND certificate_archived_at IS NULL
        AND certificate_expire > CURRENT_DATE
        AND certificate_expire < CURRENT_DATE + INTERVAL 45 DAY
    ORDER BY certificate_expire ASC"
);

// Get Licenses Expiring
$sql_licenses_expiring = mysqli_query(
    $mysqli,
    "SELECT * FROM software
    WHERE software_client_id = $session_client_id
        AND software_expire IS NOT NULL
        AND software_archived_at IS NULL
        AND software_expire > CURRENT_DATE
        AND software_expire < CURRENT_DATE + INTERVAL 45 DAY
    ORDER BY software_expire ASC"
);

// Get Asset Warranties Expiring
$sql_asset_warranties_expiring = mysqli_query(
    $mysqli,
    "SELECT * FROM assets
    WHERE asset_client_id = $session_client_id
        AND asset_warranty_expire IS NOT NULL
        AND asset_archived_at IS NULL
        AND asset_warranty_expire > CURRENT_DATE
        AND asset_warranty_expire < CURRENT_DATE + INTERVAL 45 DAY
    ORDER BY asset_warranty_expire ASC"
);

// Get Assets Retiring 7 Year
$sql_asset_retire = mysqli_query(
    $mysqli,
    "SELECT * FROM assets
    WHERE asset_client_id = $session_client_id
        AND asset_install_date IS NOT NULL
        AND asset_archived_at IS NULL
        AND asset_install_date + INTERVAL 7 YEAR > CURRENT_DATE
        AND asset_install_date + INTERVAL 7 YEAR <= CURRENT_DATE + INTERVAL 45 DAY
    ORDER BY asset_install_date ASC"
);

/*
 * EXPIRED ITEMS
 */

// Get Domains Expired
$sql_domains_expired = mysqli_query(
    $mysqli,
    "SELECT * FROM domains
    WHERE domain_client_id = $session_client_id
        AND domain_expire IS NOT NULL
        AND domain_archived_at IS NULL
        AND domain_expire < CURRENT_DATE
    ORDER BY domain_expire ASC"
);

// Get Certificates Expired
$sql_certificates_expired = mysqli_query(
    $mysqli,
    "SELECT * FROM certificates
    WHERE certificate_client_id = $session_client_id
        AND certificate_expire IS NOT NULL
        AND certificate_archived_at IS NULL
        AND certificate_expire < CURRENT_DATE
    ORDER BY certificate_expire ASC"
);

// Get Licenses Expired
$sql_licenses_expired = mysqli_query(
    $mysqli,
    "SELECT * FROM software
    WHERE software_client_id = $session_client_id
        AND software_expire IS NOT NULL
        AND software_archived_at IS NULL
        AND software_expire < CURRENT_DATE
    ORDER BY software_expire ASC"
);

// Get Asset Warranties Expired
$sql_asset_warranties_expired = mysqli_query(
    $mysqli,
    "SELECT * FROM assets
    WHERE asset_client_id = $session_client_id
        AND asset_warranty_expire IS NOT NULL
        AND asset_archived_at IS NULL
        AND asset_warranty_expire < CURRENT_DATE
    ORDER BY asset_warranty_expire ASC"
);

// Get Retired Assets
$sql_asset_retired = mysqli_query(
    $mysqli,
    "SELECT * FROM assets
    WHERE asset_client_id = $session_client_id
        AND asset_install_date IS NOT NULL
        AND asset_archived_at IS NULL
        AND asset_install_date + INTERVAL 7 YEAR < CURRENT_DATE  -- Assets retired (installed more than 7 years ago)
    ORDER BY asset_install_date ASC"
);

// Assigned Assets
$sql_assigned_assets = mysqli_query(
    $mysqli,
    "SELECT asset_name, asset_type, asset_uri_client FROM assets
    WHERE asset_contact_id = $session_contact_id
        AND asset_archived_at IS NULL
    ORDER BY asset_name ASC"
);

// Ticket Queries
$can_view_all_tickets = ($session_contact_primary == 1 || $session_contact_is_technical_contact);
$ticket_target_url = $can_view_all_tickets ? "ticket_view_all.php" : "tickets.php";

// My Open Tickets
$sql_my_open_tickets = mysqli_query(
    $mysqli,
    "SELECT COUNT(ticket_id) AS total_my_open_tickets FROM tickets
    WHERE ticket_client_id = $session_client_id
        AND ticket_contact_id = $session_contact_id
        AND ticket_closed_at IS NULL"
);
$row = mysqli_fetch_assoc($sql_my_open_tickets);
$my_open_tickets = intval($row['total_my_open_tickets']);

// All Company Open Tickets (if primary or technical contact)
$company_open_tickets = 0;
if ($can_view_all_tickets) {
    $sql_company_open_tickets = mysqli_query(
        $mysqli,
        "SELECT COUNT(ticket_id) AS total_company_open_tickets FROM tickets
        WHERE ticket_client_id = $session_client_id
            AND ticket_closed_at IS NULL"
    );
    $row = mysqli_fetch_assoc($sql_company_open_tickets);
    $company_open_tickets = intval($row['total_company_open_tickets']);
}

// Recent open tickets for dashboard table
$ticket_where_clause = $can_view_all_tickets
    ? "ticket_client_id = $session_client_id AND ticket_closed_at IS NULL"
    : "ticket_client_id = $session_client_id AND ticket_contact_id = $session_contact_id AND ticket_closed_at IS NULL";

$sql_recent_tickets = mysqli_query(
    $mysqli,
    "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_status_name, contact_name, ticket_created_at
    FROM tickets
    LEFT JOIN contacts ON ticket_contact_id = contact_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    WHERE $ticket_where_clause
    ORDER BY ticket_id DESC
    LIMIT 10"
);

?>
<!-- Action Buttons Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
    <div class="d-flex flex-wrap align-items-center">
        <a href="ticket_add.php" class="btn btn-portal-primary mr-2 mb-2">
            <i class="fas fa-plus-circle mr-1"></i> New Ticket
        </a>
        <a href="tickets.php" class="btn btn-portal-secondary mr-2 mb-2">
            <i class="fas fa-ticket-alt mr-1"></i> My Tickets
            <span class="portal-btn-badge"><?= $my_open_tickets ?></span>
        </a>
        <?php if ($can_view_all_tickets) { ?>
        <a href="ticket_view_all.php" class="btn btn-portal-secondary mb-2">
            <i class="fas fa-layer-group mr-1"></i> All Company Tickets
            <span class="portal-btn-badge portal-btn-badge-dark"><?= $company_open_tickets ?></span>
        </a>
        <?php } ?>
    </div>
</div>

<?php
// Calculate how many stat cards are active to determine column width
$num_stat_cards = 1; // My Tickets
if ($can_view_all_tickets) $num_stat_cards++; // Company Tickets
if (mysqli_num_rows($sql_assigned_assets) > 0) $num_stat_cards++; // Assets
if (contactCan('accounting')) {
    if ($balance > 0) $num_stat_cards++;
    if ($recurring_monthly_total > 0) $num_stat_cards++;
}
if (contactCan('itdoc') && mysqli_num_rows($sql_domains_expiring) > 0) $num_stat_cards++;

$card_col_class = ($num_stat_cards <= 3) ? "col-md-4" : "col-sm-6 col-lg-3";
?>

<!-- Unified Modern Stat Cards Grid -->
<div class="row mb-4">
    <?php if ($can_view_all_tickets) { ?>
    <div class="<?= $card_col_class ?> mb-3">
        <a href="ticket_view_all.php" class="portal-stat-card">
            <div class="portal-stat-header">
                <span class="portal-stat-label">Company Open Tickets</span>
                <div class="portal-stat-icon portal-stat-icon-blue">
                    <i class="fas fa-layer-group"></i>
                </div>
            </div>
            <div class="portal-stat-body">
                <div class="portal-stat-value"><?= $company_open_tickets ?></div>
                <div class="portal-stat-trend">
                    <span class="portal-stat-link">View all tickets <i class="fas fa-arrow-right ml-1"></i></span>
                </div>
            </div>
        </a>
    </div>
    <?php } ?>

    <div class="<?= $card_col_class ?> mb-3">
        <a href="tickets.php" class="portal-stat-card">
            <div class="portal-stat-header">
                <span class="portal-stat-label">My Open Tickets</span>
                <div class="portal-stat-icon portal-stat-icon-indigo">
                    <i class="fas fa-ticket-alt"></i>
                </div>
            </div>
            <div class="portal-stat-body">
                <div class="portal-stat-value"><?= $my_open_tickets ?></div>
                <div class="portal-stat-trend">
                    <span class="portal-stat-link">View my tickets <i class="fas fa-arrow-right ml-1"></i></span>
                </div>
            </div>
        </a>
    </div>

    <?php if (mysqli_num_rows($sql_assigned_assets) > 0) { ?>
    <div class="<?= $card_col_class ?> mb-3">
        <a href="assets.php" class="portal-stat-card">
            <div class="portal-stat-header">
                <span class="portal-stat-label">Assigned Equipment</span>
                <div class="portal-stat-icon portal-stat-icon-purple">
                    <i class="fas fa-laptop"></i>
                </div>
            </div>
            <div class="portal-stat-body">
                <div class="portal-stat-value"><?= mysqli_num_rows($sql_assigned_assets) ?></div>
                <div class="portal-stat-trend">
                    <span class="portal-stat-link">View equipment <i class="fas fa-arrow-right ml-1"></i></span>
                </div>
            </div>
        </a>
    </div>
    <?php } ?>

    <?php if (contactCan('accounting') && $balance > 0) { ?>
    <div class="<?= $card_col_class ?> mb-3">
        <a href="unpaid_invoices.php" class="portal-stat-card">
            <div class="portal-stat-header">
                <span class="portal-stat-label">Account Balance</span>
                <div class="portal-stat-icon portal-stat-icon-amber">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
            </div>
            <div class="portal-stat-body">
                <div class="portal-stat-value text-danger"><?= numfmt_format_currency($currency_format, $balance, $session_company_currency) ?></div>
                <div class="portal-stat-trend">
                    <span class="portal-stat-link">Pay invoices <i class="fas fa-arrow-right ml-1"></i></span>
                </div>
            </div>
        </a>
    </div>
    <?php } ?>

    <?php if (contactCan('accounting') && $recurring_monthly_total > 0) { ?>
    <div class="<?= $card_col_class ?> mb-3">
        <a href="recurring_invoices.php" class="portal-stat-card">
            <div class="portal-stat-header">
                <span class="portal-stat-label">Recurring Monthly</span>
                <div class="portal-stat-icon portal-stat-icon-teal">
                    <i class="fas fa-sync-alt"></i>
                </div>
            </div>
            <div class="portal-stat-body">
                <div class="portal-stat-value"><?= numfmt_format_currency($currency_format, $recurring_monthly_total, $session_company_currency) ?></div>
                <div class="portal-stat-trend">
                    <span class="portal-stat-link">View subscriptions <i class="fas fa-arrow-right ml-1"></i></span>
                </div>
            </div>
        </a>
    </div>
    <?php } ?>

    <?php if (contactCan('itdoc') && mysqli_num_rows($sql_domains_expiring) > 0) { ?>
    <div class="<?= $card_col_class ?> mb-3">
        <a href="domains.php" class="portal-stat-card">
            <div class="portal-stat-header">
                <span class="portal-stat-label">Domains Expiring</span>
                <div class="portal-stat-icon portal-stat-icon-rose">
                    <i class="fas fa-globe"></i>
                </div>
            </div>
            <div class="portal-stat-body">
                <div class="portal-stat-value text-warning"><?= mysqli_num_rows($sql_domains_expiring) ?></div>
                <div class="portal-stat-trend">
                    <span class="portal-stat-link">Review domains <i class="fas fa-arrow-right ml-1"></i></span>
                </div>
            </div>
        </a>
    </div>
    <?php } ?>
</div>

<!-- Open Tickets Table Card -->
<div class="portal-table-card mb-4">
    <div class="portal-table-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="portal-header-icon mr-3">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div>
                <h3 class="portal-card-title mb-0"><?= $can_view_all_tickets ? 'Open Company Tickets' : 'My Open Tickets' ?></h3>
                <span class="portal-card-subtitle">Active requests awaiting attention or resolution</span>
            </div>
        </div>
        <a href="<?= $ticket_target_url ?>" class="btn btn-portal-outline btn-sm">
            View All <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    <div class="portal-table-body p-0">
        <?php if (mysqli_num_rows($sql_recent_tickets) > 0) { ?>
            <div class="table-responsive">
                <table class="table portal-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Ticket #</th>
                            <th>Subject</th>
                            <?php if ($can_view_all_tickets) { ?><th style="width: 200px;">Contact</th><?php } ?>
                            <th style="width: 130px;">Status</th>
                            <th style="width: 160px;">Created</th>
                            <th style="width: 60px;" class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ticket = mysqli_fetch_assoc($sql_recent_tickets)) {
                            $ticket_id = intval($ticket['ticket_id']);
                            $ticket_prefix = escapeHtml($ticket['ticket_prefix']);
                            $ticket_number = intval($ticket['ticket_number']);
                            $ticket_subject = escapeHtml($ticket['ticket_subject']);
                            $ticket_status = escapeHtml($ticket['ticket_status_name']);
                            $ticket_contact = escapeHtml($ticket['contact_name']);
                            $ticket_created_at = timeAgo($ticket['ticket_created_at']);

                            // Status badge class
                            $status_class = "status-badge-open";
                            if (stripos($ticket_status, 'closed') !== false || stripos($ticket_status, 'resolved') !== false) {
                                $status_class = "status-badge-closed";
                            } elseif (stripos($ticket_status, 'hold') !== false || stripos($ticket_status, 'waiting') !== false) {
                                $status_class = "status-badge-hold";
                            } elseif (stripos($ticket_status, 'progress') !== false || stripos($ticket_status, 'working') !== false) {
                                $status_class = "status-badge-progress";
                            }
                        ?>
                        <tr>
                            <td>
                                <a href="ticket.php?id=<?= $ticket_id ?>" class="ticket-tag">
                                    <i class="fas fa-hashtag mr-1"></i><?= "$ticket_prefix$ticket_number" ?>
                                </a>
                            </td>
                            <td>
                                <a href="ticket.php?id=<?= $ticket_id ?>" class="ticket-subject-link">
                                    <?= $ticket_subject ?>
                                </a>
                            </td>
                            <?php if ($can_view_all_tickets) { ?>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-chip mr-2"><?= initials($ticket_contact) ?></div>
                                    <span class="contact-name text-truncate"><?= $ticket_contact ?></span>
                                </div>
                            </td>
                            <?php } ?>
                            <td>
                                <span class="status-badge <?= $status_class ?>"><?= $ticket_status ?></span>
                            </td>
                            <td>
                                <span class="ticket-date">
                                    <i class="far fa-clock mr-1"></i><?= $ticket_created_at ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <a href="ticket.php?id=<?= $ticket_id ?>" class="btn-action-arrow" title="View Ticket">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <div class="portal-empty-state">
                <div class="empty-icon"><i class="fas fa-check-circle"></i></div>
                <h4>All caught up!</h4>
                <p>There are no open tickets for your company right now.</p>
                <a href="ticket_add.php" class="btn btn-portal-primary btn-sm mt-2">
                    <i class="fas fa-plus-circle mr-1"></i> Create a Ticket
                </a>
            </div>
        <?php } ?>
    </div>
</div>

<?php if (mysqli_num_rows($sql_assigned_assets) > 0) { ?>
<!-- Assigned Equipment Section -->
<div class="portal-table-card mb-4">
    <div class="portal-table-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="portal-header-icon mr-3" style="background: #f5f3ff; color: #7c3aed;">
                <i class="fas fa-desktop"></i>
            </div>
            <div>
                <h3 class="portal-card-title mb-0">Assigned Equipment</h3>
                <span class="portal-card-subtitle">Hardware and devices linked to your account</span>
            </div>
        </div>
        <a href="assets.php" class="btn btn-portal-outline btn-sm">
            View All Assets <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    <div class="portal-table-body p-3">
        <div class="row">
            <?php
            mysqli_data_seek($sql_assigned_assets, 0);
            while ($row = mysqli_fetch_assoc($sql_assigned_assets)) {
                $asset_name = escapeHtml($row['asset_name']);
                $asset_type = escapeHtml($row['asset_type']);
                $asset_uri_client = escapeUrl($row['asset_uri_client']);

                $icon = "fa-desktop";
                if (stripos($asset_type, 'laptop') !== false) {
                    $icon = "fa-laptop";
                } elseif (stripos($asset_type, 'phone') !== false || stripos($asset_type, 'mobile') !== false) {
                    $icon = "fa-mobile-alt";
                } elseif (stripos($asset_type, 'server') !== false) {
                    $icon = "fa-server";
                }
            ?>
            <div class="col-md-6 col-lg-4 mb-2">
                <div class="portal-asset-card-item">
                    <div class="d-flex align-items-center">
                        <div class="portal-asset-icon-box">
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        <div>
                            <div class="font-weight-bold text-dark"><?= $asset_name ?></div>
                            <div class="small text-muted"><?= $asset_type ?></div>
                        </div>
                    </div>
                    <?php if ($asset_uri_client) { ?>
                        <a href="<?= $asset_uri_client ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary" title="Access device">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php } ?>

<?php require_once "includes/footer.php"; ?>
