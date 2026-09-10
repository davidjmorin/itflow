<?php
/*
 * Client Portal
 * Primary contact view: all tickets
 */

header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data:;");

require_once 'includes/inc_all.php';

if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
    header("Location: post.php?logout");
    exit();
}

// Ticket status from GET
$status = $_GET['status'] ?? 'Open';
if ($status === 'Closed') {
    $ticket_status_snippet = "ticket_closed_at IS NOT NULL";
} elseif ($status === '%' || strtolower($status) === 'all') {
    $status = '%';
    $ticket_status_snippet = "1=1";
} else {
    $status = 'Open';
    $ticket_status_snippet = "ticket_closed_at IS NULL";
}

// Counts for filter pills
$sql_count_open = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS cnt FROM tickets WHERE ticket_closed_at IS NULL AND ticket_client_id = $session_client_id");
$count_open = intval(mysqli_fetch_assoc($sql_count_open)['cnt']);

$sql_count_closed = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS cnt FROM tickets WHERE ticket_closed_at IS NOT NULL AND ticket_client_id = $session_client_id");
$count_closed = intval(mysqli_fetch_assoc($sql_count_closed)['cnt']);

$count_all = $count_open + $count_closed;

// Search filter
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_snippet = '';
if (!empty($search_query)) {
    $escaped_q = escapeSql($search_query);
    $search_snippet = "AND (ticket_number LIKE '%$escaped_q%' OR ticket_subject LIKE '%$escaped_q%' OR contacts.contact_name LIKE '%$escaped_q%')";
}

$all_tickets = mysqli_query(
    $mysqli,
    "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_status_name, ticket_priority, contact_name, ticket_created_at, ticket_closed_at
    FROM tickets
    LEFT JOIN contacts ON ticket_contact_id = contact_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    WHERE $ticket_status_snippet AND ticket_client_id = $session_client_id $search_snippet
    ORDER BY ticket_id DESC"
);
$total_matching_tickets = mysqli_num_rows($all_tickets);
?>

<!-- Top Navigation & Action Row -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center mb-2 mb-md-0">
        <a href="index.php" class="btn btn-portal-secondary btn-sm mr-2">
            <i class="fas fa-arrow-left mr-1"></i> Dashboard
        </a>
        <a href="tickets.php" class="btn btn-portal-secondary btn-sm">
            <i class="fas fa-user mr-1"></i> My Tickets
        </a>
    </div>
    <div class="d-flex align-items-center">
        <a href="ticket_add.php" class="btn btn-portal-primary btn-sm">
            <i class="fas fa-plus-circle mr-1"></i> New Ticket
        </a>
    </div>
</div>

<!-- Main Tickets Table Card -->
<div class="portal-table-card mb-4">
    <div class="portal-table-header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
            <div class="d-flex align-items-center mb-3 mb-md-0">
                <div class="portal-header-icon mr-3">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div>
                    <h3 class="portal-card-title mb-0">All Company Tickets</h3>
                    <span class="portal-card-subtitle">Showing tickets across <?= escapeHtml($session_client_name) ?> (<?= $total_matching_tickets ?>)</span>
                </div>
            </div>

            <!-- Search Field -->
            <form method="get" class="portal-search-wrapper">
                <input type="hidden" name="status" value="<?= escapeHtml($status) ?>">
                <i class="fas fa-search"></i>
                <input type="text"
                       id="ticketSearchInput"
                       name="q"
                       class="form-control portal-search-input"
                       placeholder="Filter tickets..."
                       value="<?= escapeHtml($search_query) ?>"
                       autocomplete="off">
            </form>
        </div>

        <!-- Filter Tabs Bar -->
        <div class="d-flex flex-wrap align-items-center justify-content-between mt-3 pt-3 border-top">
            <div class="portal-filter-tabs mb-2 mb-md-0">
                <a href="?status=Open<?= !empty($search_query) ? '&q=' . urlencode($search_query) : '' ?>"
                   class="portal-filter-tab <?= ($status === 'Open') ? 'active' : '' ?>">
                    <i class="fas fa-inbox mr-1"></i> Open
                    <span class="tab-badge"><?= $count_open ?></span>
                </a>
                <a href="?status=Closed<?= !empty($search_query) ? '&q=' . urlencode($search_query) : '' ?>"
                   class="portal-filter-tab <?= ($status === 'Closed') ? 'active' : '' ?>">
                    <i class="fas fa-check mr-1"></i> Closed
                    <span class="tab-badge"><?= $count_closed ?></span>
                </a>
                <a href="?status=%<?= !empty($search_query) ? '&q=' . urlencode($search_query) : '' ?>"
                   class="portal-filter-tab <?= ($status === '%' || strtolower($status) === 'all') ? 'active' : '' ?>">
                    <i class="fas fa-list mr-1"></i> All
                    <span class="tab-badge"><?= $count_all ?></span>
                </a>
            </div>

            <?php if (!empty($search_query)) { ?>
                <div class="small text-muted">
                    Filtering by: <strong>"<?= escapeHtml($search_query) ?>"</strong>
                    <a href="?status=<?= escapeHtml($status) ?>" class="text-danger ml-2 font-weight-bold">
                        <i class="fas fa-times-circle mr-1"></i>Clear
                    </a>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Table Body -->
    <div class="portal-table-body p-0">
        <?php if ($total_matching_tickets > 0) { ?>
            <div class="table-responsive">
                <table class="table portal-table mb-0" id="allTicketsTable">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Ticket #</th>
                            <th>Subject</th>
                            <th style="width: 200px;">Contact</th>
                            <th style="width: 110px;">Priority</th>
                            <th style="width: 130px;">Status</th>
                            <th style="width: 160px;">Created</th>
                            <th style="width: 50px;" class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($all_tickets)) {
                            $ticket_id = intval($row['ticket_id']);
                            $ticket_prefix = escapeHtml($row['ticket_prefix']);
                            $ticket_number = intval($row['ticket_number']);
                            $ticket_subject = escapeHtml($row['ticket_subject']);
                            $ticket_status = escapeHtml($row['ticket_status_name']);
                            $ticket_contact_name = escapeHtml($row['contact_name'] ?? 'Unassigned');
                            $ticket_priority = escapeHtml($row['ticket_priority'] ?? '');
                            $ticket_created_at = timeAgo($row['ticket_created_at']);

                            // Status badge class
                            $status_class = "status-badge-open";
                            if (stripos($ticket_status, 'closed') !== false || stripos($ticket_status, 'resolved') !== false) {
                                $status_class = "status-badge-closed";
                            } elseif (stripos($ticket_status, 'hold') !== false || stripos($ticket_status, 'waiting') !== false) {
                                $status_class = "status-badge-hold";
                            } elseif (stripos($ticket_status, 'progress') !== false || stripos($ticket_status, 'working') !== false) {
                                $status_class = "status-badge-progress";
                            }

                            // Priority badge class
                            $priority_class = "priority-medium";
                            if (stripos($ticket_priority, 'low') !== false) {
                                $priority_class = "priority-low";
                            } elseif (stripos($ticket_priority, 'high') !== false) {
                                $priority_class = "priority-high";
                            } elseif (stripos($ticket_priority, 'urgent') !== false) {
                                $priority_class = "priority-urgent";
                            }
                        ?>
                        <tr class="ticket-row" data-search="<?= strtolower("$ticket_prefix$ticket_number $ticket_subject $ticket_contact_name $ticket_status $ticket_priority") ?>">
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
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-chip mr-2"><?= initials($ticket_contact_name) ?></div>
                                    <span class="contact-name text-truncate"><?= $ticket_contact_name ?></span>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($ticket_priority)) { ?>
                                    <span class="priority-badge <?= $priority_class ?>"><?= $ticket_priority ?></span>
                                <?php } else { ?>
                                    <span class="text-muted small">&mdash;</span>
                                <?php } ?>
                            </td>
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
                <h4>No tickets found</h4>
                <p>There are no <?= ($status === 'Closed') ? 'closed' : (($status === 'Open') ? 'open' : '') ?> tickets matching your criteria.</p>
                <div class="mt-3">
                    <?php if (!empty($search_query) || $status !== 'Open') { ?>
                        <a href="ticket_view_all.php" class="btn btn-portal-secondary btn-sm mr-2">
                            <i class="fas fa-redo mr-1"></i> Reset Filters
                        </a>
                    <?php } ?>
                    <a href="ticket_add.php" class="btn btn-portal-primary btn-sm">
                        <i class="fas fa-plus-circle mr-1"></i> Create a Ticket
                    </a>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<!-- Instant Live Search Client-side Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('ticketSearchInput');
    const table = document.getElementById('allTicketsTable');

    if (searchInput && table) {
        searchInput.addEventListener('input', function () {
            const filter = this.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr.ticket-row');

            rows.forEach(function (row) {
                const searchData = row.getAttribute('data-search') || '';
                if (!filter || searchData.indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php
require_once 'includes/footer.php';
