<?php

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
} else {
    require_once "includes/inc_all.php";
}

// Perms
enforceUserPermission('module_sales');

if (isset($_GET['quote_id'])) {

    $quote_id = intval($_GET['quote_id']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT client_currency_code, client_id, client_name, client_net_terms, client_website,
            contact_email, contact_extension, contact_mobile, contact_mobile_country_code,
            contact_phone, contact_phone_country_code, location_address, location_city,
            location_country, location_state, location_zip, quote_amount, quote_category_id,
            quote_created_at, quote_currency_code, quote_date, quote_discount_amount, quote_expire,
            quote_id, quote_note, quote_number, quote_prefix, quote_scope, quote_status, quote_url_key,
            quote_lead_id, leads.lead_name, leads.lead_contact_name, leads.lead_contact_title,
            leads.lead_contact_email, leads.lead_contact_phone, leads.lead_contact_mobile,
            leads.lead_address, leads.lead_city, leads.lead_state, leads.lead_zip,
            leads.lead_country, leads.lead_website, leads.lead_status FROM quotes
        LEFT JOIN clients ON quote_client_id = client_id
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        LEFT JOIN leads ON quote_lead_id = lead_id
        WHERE quote_id = $quote_id
        " . clientScopeSql('quote_client_id') . "
        LIMIT 1"
    );

    if (mysqli_num_rows($sql) == 0) {
        if (isset($_GET['client_id'])) {
            $backlink_append = "?client_id=$client_id";
        } else {
            $backlink_append = '';
        }
        echo "<h1 class='text-secondary pt-5' style='text-align: center'>There is no Quote here<br><small><a href='quotes.php$backlink_append'><i class='fas fa-arrow-left mr-2'></i>Back to Quotes</a></small></h1>";
        require_once "../includes/footer.php";

        exit();
    }

    $row = mysqli_fetch_assoc($sql);
    $quote_id = intval($row['quote_id']);
    $quote_prefix = escapeHtml($row['quote_prefix']);
    $quote_number = intval($row['quote_number']);
    $quote_scope = escapeHtml($row['quote_scope']);
    $quote_status = escapeHtml($row['quote_status']);
    $quote_date = escapeHtml($row['quote_date']);
    $quote_expire = escapeHtml($row['quote_expire']);
    $quote_amount = floatval($row['quote_amount']);
    $quote_discount = floatval($row['quote_discount_amount']);
    $quote_currency_code = escapeHtml($row['quote_currency_code']);
    $quote_note = escapeHtml($row['quote_note']);
    $quote_url_key = escapeHtml($row['quote_url_key']);
    $quote_created_at = escapeHtml($row['quote_created_at']);
    $category_id = intval($row['quote_category_id']);
    $quote_lead_id = intval($row['quote_lead_id']);
    $client_id = intval($row['client_id']);

    if ($client_id > 0) {
        $client_name = escapeHtml($row['client_name']);
        $location_address = escapeHtml($row['location_address']);
        $location_city = escapeHtml($row['location_city']);
        $location_state = escapeHtml($row['location_state']);
        $location_zip = escapeHtml($row['location_zip']);
        $location_country = escapeHtml($row['location_country']);
        $contact_name = escapeHtml($row['contact_name'] ?? '');
        $contact_email = escapeHtml($row['contact_email']);
        $contact_phone_country_code = escapeHtml($row['contact_phone_country_code']);
        $contact_phone = escapeHtml(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
        $contact_extension = escapeHtml($row['contact_extension']);
        $contact_mobile_country_code = escapeHtml($row['contact_mobile_country_code']);
        $contact_mobile = escapeHtml(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
        $client_website = escapeHtml($row['client_website']);
        $client_currency_code = escapeHtml($row['client_currency_code']);
        $client_net_terms = intval($row['client_net_terms']);
        if ($client_net_terms == 0) {
            $client_net_terms = $config_default_net_terms;
        }
        $tab_title = $row['client_name'];
        $is_lead_quote = false;
    } else {
        $client_name = escapeHtml($row['lead_name']);
        $location_address = escapeHtml($row['lead_address']);
        $location_city = escapeHtml($row['lead_city']);
        $location_state = escapeHtml($row['lead_state']);
        $location_zip = escapeHtml($row['lead_zip']);
        $location_country = escapeHtml($row['lead_country']);
        $contact_name = escapeHtml($row['lead_contact_name'] ?? '');
        $contact_email = escapeHtml($row['lead_contact_email']);
        $contact_phone_country_code = '';
        $contact_phone = escapeHtml(formatPhoneNumber($row['lead_contact_phone'], ''));
        $contact_extension = '';
        $contact_mobile_country_code = '';
        $contact_mobile = escapeHtml(formatPhoneNumber($row['lead_contact_mobile'], ''));
        $client_website = escapeHtml($row['lead_website']);
        $client_currency_code = $session_company_currency;
        $client_net_terms = $config_default_net_terms;
        $tab_title = !empty($row['lead_name']) ? "Lead: {$row['lead_name']}" : "Quote";
        $is_lead_quote = true;
    }

    $page_title = "{$row['quote_prefix']}{$row['quote_number']}";

    $sql = mysqli_query($mysqli, "SELECT company_address, company_city, company_country, company_email, settings.company_id,
        company_logo, company_name, company_phone, company_phone_country_code, company_state,
        company_website, company_zip FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");
    $row = mysqli_fetch_assoc($sql);

    $company_id = intval($row['company_id']);
    $company_name = escapeHtml($row['company_name']);
    $company_country = escapeHtml($row['company_country']);
    $company_address = escapeHtml($row['company_address']);
    $company_city = escapeHtml($row['company_city']);
    $company_state = escapeHtml($row['company_state']);
    $company_zip = escapeHtml($row['company_zip']);
    $company_phone_country_code = escapeHtml($row['company_phone_country_code']);
    $company_phone = escapeHtml(formatPhoneNumber($row['company_phone'], $company_phone_country_code));
    $company_email = escapeHtml($row['company_email']);
    $company_website = escapeHtml($row['company_website']);
    $company_logo = escapeHtml($row['company_logo']);

    $sql_history = mysqli_query($mysqli, "SELECT history_created_at, history_description, history_status FROM history WHERE history_quote_id = $quote_id ORDER BY history_id DESC");

    //Set Badge color based off of quote status
    if ($quote_status == "Sent") {
        $quote_badge_color = "warning text-white";
    } elseif ($quote_status == "Viewed") {
        $quote_badge_color = "primary";
    } elseif ($quote_status == "Accepted") {
        $quote_badge_color = "success";
    } elseif ($quote_status == "Declined") {
        $quote_badge_color = "danger";
    } elseif ($quote_status == "Invoiced") {
        $quote_badge_color = "info";
    } else {
        $quote_badge_color = "secondary";
    }

    $products = [];
    $products_sql = mysqli_query($mysqli, "
        SELECT
            p.product_id AS prod_id,
            p.product_name,
            p.product_name AS label,
            p.product_code,
            p.product_type AS type,
            p.product_tracking_type,
            p.product_description AS description,
            p.product_price AS price,
            p.product_tax_id AS tax,
            c.category_name,
            t.tax_percent,
            COALESCE(s.bulk_stock, 0) AS bulk_stock,
            COALESCE(i.serialized_stock, 0) AS serialized_stock,
            CASE
                WHEN p.product_type = 'service' THEN NULL
                WHEN p.product_tracking_type = 'serialized' THEN COALESCE(i.serialized_stock, 0)
                ELSE COALESCE(s.bulk_stock, 0)
            END AS available_stock
        FROM products p
        LEFT JOIN categories c ON p.product_category_id = c.category_id
        LEFT JOIN taxes t ON p.product_tax_id = t.tax_id
        LEFT JOIN (
            SELECT stock_product_id, SUM(stock_qty) AS bulk_stock
            FROM product_stock
            GROUP BY stock_product_id
        ) s ON p.product_id = s.stock_product_id
        LEFT JOIN (
            SELECT item_product_id, COUNT(*) AS serialized_stock
            FROM inventory_items
            WHERE item_status = 'In Stock' AND item_archived_at IS NULL
            GROUP BY item_product_id
        ) i ON p.product_id = i.item_product_id
        WHERE p.product_archived_at IS NULL
        ORDER BY p.product_name ASC
    ");

    if (mysqli_num_rows($products_sql) > 0) {
        while ($row = mysqli_fetch_assoc($products_sql)) {
            $products[] = $row;
        }
        $json_products = json_encode($products);
    }

    $inventory_units = [];
    $inv_sql = mysqli_query($mysqli, "
        SELECT
            ii.item_id,
            ii.item_product_id,
            ii.item_serial,
            ii.item_mac,
            ii.item_location,
            ii.item_price,
            p.product_name,
            p.product_code,
            p.product_description,
            p.product_tax_id AS tax,
            t.tax_percent
        FROM inventory_items ii
        JOIN products p ON ii.item_product_id = p.product_id
        LEFT JOIN taxes t ON p.product_tax_id = t.tax_id
        WHERE ii.item_status = 'In Stock' AND ii.item_archived_at IS NULL AND p.product_archived_at IS NULL
        ORDER BY p.product_name ASC, ii.item_serial ASC
    ");
    while ($row = mysqli_fetch_assoc($inv_sql)) {
        $inventory_units[] = $row;
    }
    $json_inventory_units = json_encode($inventory_units);

    // Quote File Attachments
    $sql_quote_files = mysqli_query(
        $mysqli,
        "SELECT file_reference_name, file_name, file_created_at FROM quote_files LEFT JOIN files ON quote_files.file_id = files.file_id WHERE quote_id = $quote_id"
    );

?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="quotes.php">All Quotes</a>
        </li>
        <?php if ($client_id > 0) { ?>
            <li class="breadcrumb-item">
                <a href="quotes.php?client_id=<?= $client_id ?>"><?= $client_name ?> Quotes</a>
            </li>
        <?php } elseif ($quote_lead_id > 0) { ?>
            <li class="breadcrumb-item">
                <a href="lead.php?lead_id=<?= $quote_lead_id ?>"><?= $client_name ?> (Lead)</a>
            </li>
        <?php } ?>
        <li class="breadcrumb-item active"><?= "$quote_prefix$quote_number" ?></li>
    </ol>

    <div class="card">
        <div class="card-header d-print-none">

            <?php if (lookupUserPermission("module_sales") >= 2) { ?>
                <div class="row">

                <div class="col-8">
                <?php if ($quote_status == 'Draft') { ?>
                    <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                        <i class="fas fa-fw fa-paper-plane mr-2"></i>Send
                    </button>
                    <div class="dropdown-menu">
                        <?php if (!empty($config_smtp_provider) && !empty($contact_email)) { ?>
                            <a class="dropdown-item" href="post.php?email_quote=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                <i class="fas fa-fw fa-paper-plane mr-2"></i>Send Email
                            </a>
                            <div class="dropdown-divider"></div>
                        <?php } ?>
                        <a class="dropdown-item" href="post.php?mark_quote_sent=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                            <i class="fas fa-fw fa-check mr-2"></i>Mark Sent
                        </a>
                    </div>
                <?php } ?>

                <?php if ($quote_status == 'Sent' || $quote_status == 'Viewed') { ?>
                    <a class="btn btn-primary confirm-link" href="post.php?accept_quote=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-thumbs-up mr-2"></i>Accept
                    </a>
                    <a class="btn btn-default confirm-link" href="post.php?decline_quote=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-thumbs-down mr-2"></i>Decline
                    </a>
                <?php } ?>

                <?php if ($quote_status == 'Accepted') { ?>
                    <div class="btn-group fix-quote-dropdown">
                        <?php if ($client_id > 0) { ?>
                            <button type="button" class="btn btn-primary ajax-modal"
                                data-modal-url="modals/quote/quote_to_invoice.php?quote_id=<?= $quote_id ?>">
                                <i class="fas fa-check mr-2"></i>Invoice
                            </button>
                            <button type="button" class="btn btn-primary dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="post.php?mark_quote_invoiced=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-check mr-2"></i>Mark Invoiced
                                </a>
                            </div>
                        <?php } else { ?>
                            <button type="button" class="btn btn-success ajax-modal"
                                data-modal-url="modals/lead/lead_convert.php?lead_id=<?= $quote_lead_id ?>">
                                <i class="fas fa-user-check mr-2"></i>Convert Lead to Invoice
                            </button>
                        <?php } ?>
                    </div>
                <?php } ?>

                </div>

                <div class="col-4">
                    <div class="dropdown dropleft text-center float-right">
                        <button class="btn btn-secondary" type="button" data-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item ajax-modal" href="#"
                                data-modal-url="modals/quote/quote_edit.php?id=<?= $quote_id ?>">
                                <i class="fa fa-fw fa-edit text-secondary mr-2"></i>Edit
                            </a>
                            <?php if (lookupUserPermission("module_sales") >= 2) { ?>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/quote/quote_copy.php?id=<?= $quote_id ?>">
                                    <i class="fa fa-fw fa-copy text-secondary mr-2"></i>Copy
                                </a>
                            <?php } ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#" onclick="window.print();">
                                <i class="fa fa-fw fa-print text-secondary mr-2"></i>Print
                            </a>
                            <a class="dropdown-item" href="post.php?export_quote_pdf=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" target="_blank">
                                <i class="fa fa-fw fa-download text-secondary mr-2"></i>Download PDF
                            </a>
                            <?php if (!empty($config_smtp_provider) && !empty($contact_email)) { ?>
                                <a class="dropdown-item" href="post.php?email_quote=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fa fa-fw fa-paper-plane text-secondary mr-2"></i>Send Email
                                </a>
                            <?php } ?>
                            <a class="dropdown-item clipboardjs" href="#" data-clipboard-text="https://<?= $config_base_url ?>/guest/guest_view_quote.php?quote_id=<?= "$quote_id&url_key=$quote_url_key" ?>">
                                <i class="fa fa-fw fa-copy text-secondary mr-2"></i>Copy Guest URL
                            </a>
                            <?php if (lookupUserPermission("module_sales") >= 3) { ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_quote=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fa fa-fw fa-times mr-2"></i>Delete
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

        <div class="card-body">

            <div class="row mb-3">
                <?php if (file_exists("../uploads/settings/$company_logo")) { ?>
                <div class="col-sm-2">
                    <img class="img-fluid" src="<?= "../uploads/settings/$company_logo" ?>" alt="Company logo">
                </div>
                <?php } ?>
                <div class="col-sm-6 <?php if (!file_exists("../uploads/settings/$company_logo")) { echo "col-sm-8"; } ?>">
                    <ul class="list-unstyled">
                        <li><h4><strong><?= $company_name ?></strong></h4></li>
                        <li><?= formatAddress($company_address, $company_city, $company_state, $company_zip, $company_country, '<br>') ?></li>
                        <li><?= "$company_email | $company_phone" ?></li>
                        <li><?= $company_website ?></li>
                    </ul>
                </div>

                <div class="col-sm-4">
                    <h3 class="text-right"><strong>QUOTE</strong></h3>
                    <h5 class="badge badge-<?= $quote_badge_color ?> p-2 float-right">
                        <?= "$quote_status" ?>
                    </h5>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th>Quote #:</th>
                            <td class="text-right"><?= "$quote_prefix$quote_number" ?></td>
                        </tr>
                        <tr>
                            <th>Date:</th>
                            <td class="text-right"><?= $quote_date ?></td>
                        </tr>
                        <tr>
                            <th>Expires:</th>
                            <td class="text-right"><?= $quote_expire ?></td>
                        </tr>
                    </table>
                </div>

            </div>
            <div class="row mb-3 bg-light p-3">
                <div class="col">
                    <h6><strong>To:</strong></h6>
                    <ul class="list-unstyled mb-0">
                        <li>
                            <?= $client_name ?>
                            <?php if ($is_lead_quote) { ?>
                                <span class="badge badge-info ml-1">Lead</span>
                            <?php } ?>
                        </li>
                        <?php if (!empty($contact_name)) { ?>
                            <li><strong>Attn:</strong> <?= $contact_name ?></li>
                        <?php } ?>
                        <li><?= formatAddress($location_address, $location_city, $location_state, $location_zip, $location_country, '<br>') ?></li>
                        <li><?= "$contact_email | $contact_phone $contact_extension" ?></li>
                    </ul>
                </div>
            </div>

            <?php $sql_items = mysqli_query($mysqli, "SELECT item_created_at, item_description, item_id, item_name, item_price, item_quantity, item_tax,
                item_tax_id, item_total FROM quote_items WHERE item_quote_id = $quote_id ORDER BY item_order ASC"); ?>

            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="items">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="d-print-none"></th>
                                        <th>Item</th>
                                        <th>Description</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-right">Unit Price</th>
                                        <th class="text-right">Tax</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php

                                    $total_tax = 0.00;
                                    $sub_total = 0.00;

                                    while ($row = mysqli_fetch_assoc($sql_items)) {
                                        $item_id = intval($row['item_id']);
                                        $item_name = escapeHtml($row['item_name']);
                                        $item_description = escapeHtml($row['item_description']);
                                        $item_quantity = floatval($row['item_quantity']);
                                        $item_price = floatval($row['item_price']);
                                        $item_tax = floatval($row['item_tax']);
                                        $item_total = floatval($row['item_total']);
                                        $item_created_at = escapeHtml($row['item_created_at']);
                                        $tax_id = intval($row['item_tax_id']);
                                        $total_tax = $item_tax + $total_tax;
                                        $sub_total = $item_price * $item_quantity + $sub_total;
                                        ?>

                                        <tr data-item-id="<?= $item_id ?>">
                                            <td class="d-print-none">
                                                <?php if ($quote_status !== "Invoiced" && $quote_status !== "Accepted" && $quote_status !== "Declined" && lookupUserPermission("module_sales") >= 2) { ?>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-link drag-handle">
                                                            <i class="fas fa-bars text-muted"></i>
                                                        </button>

                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-light" type="button" data-toggle="dropdown">
                                                                <i class="fas fa-ellipsis-v"></i>
                                                            </button>
                                                            <div class="dropdown-menu">
                                                                <a class="dropdown-item ajax-modal" href="#"
                                                                    data-modal-url="modals/quote/quote_item_edit.php?id=<?= $item_id ?>">
                                                                    <i class="fa fa-fw fa-edit mr-2"></i>Edit
                                                                </a>
                                                                <div class="dropdown-divider"></div>
                                                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_quote_item=<?= $item_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                                    <i class="fa fa-fw fa-trash mr-2"></i>Delete
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </td>
                                            <td><?= $item_name ?></td>
                                            <td><?= nl2br($item_description) ?></td>
                                            <td class="text-center"><?= number_format($item_quantity, 2) ?></td>
                                            <td class="text-right"><?= numfmt_format_currency($currency_format, $item_price, $quote_currency_code) ?></td>
                                            <td class="text-right"><?= numfmt_format_currency($currency_format, $item_tax, $quote_currency_code) ?></td>
                                            <td class="text-right"><?= numfmt_format_currency($currency_format, $item_total, $quote_currency_code) ?></td>
                                        </tr>

                                    <?php

                                    }

                                    ?>

                                    <tr class="d-print-none" <?php if ($quote_status == "Invoiced" || $quote_status == "Accepted" || $quote_status == "Declined" || lookupUserPermission("module_sales") <= 1) {
                                                                    echo "hidden";
                                                                } ?>>
                                        <form action="post.php" method="post" autocomplete="off">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="quote_id" value="<?= $quote_id ?>">
                                            <input type="hidden" name="item_order" value="<?php
                                            //find largest order number and add 1
                                            $sql = mysqli_query($mysqli, "SELECT MAX(item_order) AS item_order FROM quote_items WHERE item_quote_id = $quote_id");
                                            $row = mysqli_fetch_assoc($sql);
                                            $item_order = intval($row['item_order']) + 1;
                                            echo $item_order;
                                            ?>">
                                            <td></td>
                                            <td>
                                                <input type="hidden" id="product_id" name="product_id" value="0">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="name" id="name" placeholder="Item name or type to search..." maxlength="200" required>
                                                    <div class="input-group-append">
                                                        <button class="btn btn-outline-primary" type="button" id="btn-browse-inventory" data-toggle="modal" data-target="#modal-quote-inventory-picker" title="Select from Inventory / Products">
                                                            <i class="fas fa-boxes mr-1"></i>Browse
                                                        </button>
                                                    </div>
                                                </div>
                                                <div id="product-link-status" class="small mt-1" style="display: none;">
                                                    <span class="badge badge-light border text-primary">
                                                        <i class="fas fa-link mr-1"></i><span id="product-link-name"></span>
                                                    </span>
                                                    <button type="button" class="btn btn-xs btn-link text-muted p-0 ml-1" id="btn-clear-product-link" title="Clear link (write in manually)">
                                                        <i class="fas fa-times"></i> Write in manually
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <textarea class="form-control" rows="2" name="description" id="desc" placeholder="Enter a Description"></textarea>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" id="qty" style="text-align: center;" name="qty" placeholder="Qty">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" id="price" style="text-align: right;" name="price" placeholder="Price (<?= $quote_currency_code ?>)">
                                            </td>
                                            <td>
                                                <select class="form-control select2" id="tax" name="tax_id" required>
                                                    <option value="0">No Tax</option>
                                                    <?php

                                                    $taxes_sql = mysqli_query($mysqli, "SELECT tax_id, tax_name, tax_percent FROM taxes WHERE tax_archived_at IS NULL ORDER BY tax_name ASC");
                                                    while ($row = mysqli_fetch_assoc($taxes_sql)) {
                                                        $tax_id = intval($row['tax_id']);
                                                        $tax_name = escapeHtml($row['tax_name']);
                                                        $tax_percent = floatval($row['tax_percent']);
                                                    ?>
                                                        <option value="<?= $tax_id ?>"><?= "$tax_name $tax_percent%" ?></option>

                                                    <?php
                                                    }
                                                    ?>
                                                </select>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-light text-success" type="submit" name="add_quote_item">
                                                    <i class="fa fa-check"></i>
                                                </button>
                                            </td>
                                        </form>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-sm-7">
                    <div class="card">
                        <div class="card-header text-bold">
                            Notes:
                            <div class="card-tools d-print-none">
                                <?php if (lookupUserPermission("module_sales") >= 2) { ?>
                                    <a href="#" class="btn btn-light btn-tool" data-toggle="modal" data-target="#quoteNoteModal">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <?= nl2br($quote_note) ?>
                        </div>
                    </div>
                </div>

                <div class="col-sm-3 offset-sm-2">

                    <table class="table table-hover mb-0">
                        <tbody>
                            <tr>
                                <td>Subtotal:</td>
                                <td class="text-right"><?= numfmt_format_currency($currency_format, $sub_total, $quote_currency_code) ?></td>
                            </tr>
                            <?php if ($quote_discount > 0) { ?>
                                <tr>
                                    <td>Discount:</td>
                                    <td class="text-right">-<?= numfmt_format_currency($currency_format, $quote_discount, $quote_currency_code) ?></td>
                                </tr>
                            <?php } ?>
                            <?php if ($total_tax > 0) { ?>
                                <tr>
                                    <td>Tax:</td>
                                    <td class="text-right"><?= numfmt_format_currency($currency_format, $total_tax, $quote_currency_code) ?></td>
                                </tr>
                            <?php } ?>
                            <tr class="h5 text-bold">
                                <td>Total:</td>
                                <td class="text-right"><?= numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code) ?></td>
                            </tr>
                        </tbody>
                    </table>

                </div>
            </div>

            <hr class="d-none d-print-block mt-5">

            <div class="d-none d-print-block text-center"><?= nl2br(escapeHtml($config_quote_footer)) ?></div>
        </div>
    </div>

    <?php if (mysqli_num_rows($sql_quote_files) > 0) { ?>
        <div class="row mb-3">
        <div class="col-sm d-print-none">
            <div class="card">
                <div class="card-header text-bold">
                    <i class="fa fa-paperclip mr-2"></i>Attachments
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-tool" data-card-widget="remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Upload date</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($quote_file = mysqli_fetch_assoc($sql_quote_files)) {
                            $name = escapeHtml($quote_file['file_name']);
                            $ref_name = escapeHtml($quote_file['file_reference_name']);
                            $created = escapeHtml($quote_file['file_created_at']);

                            ?>
                            <tr>
                                <td><a target="_blank" href="../uploads/clients/<?= $client_id ?>/<?= $ref_name ?>"><?= $name ?></a></td>
                                <td><?= $created ?></td>
                            </tr>
                            <?php
                        }
                        ?>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>

    <div class="row mb-3">
        <div class="col-sm d-print-none">
            <div class="card">
                <div class="card-header text-bold">
                    <i class="fa fa-history mr-2"></i>History
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-tool" data-card-widget="remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php

                            while ($row = mysqli_fetch_assoc($sql_history)) {
                                $history_created_at = escapeHtml($row['history_created_at']);
                                $history_status = escapeHtml($row['history_status']);
                                $history_description = escapeHtml($row['history_description']);

                            ?>
                                <tr>
                                    <td><?= $history_created_at ?></td>
                                    <td><?= $history_status ?></td>
                                    <td><?= $history_description ?></td>
                                </tr>
                            <?php
                            }
                            ?>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php
    require_once "modals/quote/quote_note.php";
?>

<div class="modal fade" id="modal-quote-inventory-picker" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-boxes mr-2 text-primary"></i>Select Item from Inventory / Products
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="row mb-3">
                    <div class="col-md-7">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" class="form-control" id="modal-item-search" placeholder="Filter by item name, SKU, serial number...">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" id="btn-modal-clear-search">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <ul class="nav nav-pills nav-justified" id="picker-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active font-weight-bold" id="tab-in-stock" data-toggle="pill" href="#pane-in-stock" role="tab">
                                    <i class="fas fa-warehouse mr-1"></i>In-Stock Inventory (<span id="in-stock-count">0</span>)
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link font-weight-bold" id="tab-all-products" data-toggle="pill" href="#pane-all-products" role="tab">
                                    <i class="fas fa-th-list mr-1"></i>All Catalog (<span id="all-products-count">0</span>)
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pane-in-stock" role="tabpanel">
                        <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                            <table class="table table-hover table-striped table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 32%;">Item / Serial / SKU</th>
                                        <th style="width: 16%;">Category</th>
                                        <th style="width: 14%;">Tracking</th>
                                        <th style="width: 14%;">Stock On Hand</th>
                                        <th style="width: 12%;" class="text-right">Price</th>
                                        <th style="width: 12%;" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-in-stock"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="pane-all-products" role="tabpanel">
                        <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                            <table class="table table-hover table-striped table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 34%;">Product / Service</th>
                                        <th style="width: 16%;">Category</th>
                                        <th style="width: 14%;">Type</th>
                                        <th style="width: 12%;">Stock</th>
                                        <th style="width: 12%;" class="text-right">Price</th>
                                        <th style="width: 12%;" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-all-products"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <span class="text-muted small">
                    <i class="fas fa-info-circle mr-1"></i>
                    Click Select to populate this quote line item, or close to write in manually.
                </span>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
}

require_once "../includes/footer.php";

?>

<!-- JSON Autocomplete / type ahead -->
<!-- //TODO: Move to js/ -->
<link rel="stylesheet" href="../libs/jquery-ui/jquery-ui.min.css">
<script src="../libs/jquery-ui/jquery-ui.min.js"></script>
<script>
    $(function() {
        var availableProducts = <?= $json_products ?? '[]' ?>;
        var inventoryUnits = <?= $json_inventory_units ?? '[]' ?>;

        var autocompleteSource = [];

        availableProducts.forEach(function(p) {
            autocompleteSource.push({
                label: p.product_name + (p.product_code ? ' (' + p.product_code + ')' : ''),
                product_name: p.product_name,
                product_code: p.product_code || '',
                description: p.description || '',
                price: p.price,
                tax: p.tax,
                tax_percent: p.tax_percent,
                prod_id: p.prod_id,
                type: p.type,
                product_tracking_type: p.product_tracking_type,
                available_stock: p.available_stock,
                is_unit: false
            });
        });

        inventoryUnits.forEach(function(u) {
            autocompleteSource.push({
                label: u.product_name + ' [S/N: ' + u.item_serial + ']' + (u.item_location ? ' - ' + u.item_location : ''),
                product_name: u.product_name,
                product_code: u.product_code || '',
                description: (u.product_description ? u.product_description + "\n" : "") + "Serial Number: " + u.item_serial + (u.item_mac ? " (MAC: " + u.item_mac + ")" : "") + (u.item_location ? " | Location: " + u.item_location : ""),
                price: u.item_price > 0 ? u.item_price : 0,
                tax: u.tax,
                tax_percent: u.tax_percent,
                prod_id: u.item_product_id,
                type: 'serialized',
                serial: u.item_serial,
                available_stock: 1,
                is_unit: true
            });
        });

        function esc(value) {
            return $("<div>").text(value == null ? "" : value).html();
        }

        function applySelectedItem(data) {
            $("#name").val(data.name);
            $("#desc").val(data.description || '');
            $("#qty").val(1);
            $("#price").val(data.price != null && data.price !== '' ? parseFloat(data.price).toFixed(2) : '0.00');
            if (data.tax != null) {
                $("#tax").val(data.tax).trigger('change');
            }
            $("#product_id").val(data.prod_id || 0);

            if (data.prod_id > 0) {
                $("#product-link-name").text(data.name + (data.serial ? ' (S/N: ' + data.serial + ')' : ''));
                $("#product-link-status").show();
            } else {
                $("#product-link-status").hide();
            }

            $("#modal-quote-inventory-picker").modal('hide');
            $("#qty").focus().select();
        }

        $("#name").autocomplete({
            minLength: 1,
            delay: 0,
            source: function(request, response) {
                var term = $.ui.autocomplete.escapeRegex(request.term.toLowerCase());
                var matcher = new RegExp(term, "i");
                var matches = $.grep(autocompleteSource, function(item) {
                    return matcher.test(item.label || "") ||
                           matcher.test(item.product_name || "") ||
                           matcher.test(item.product_code || "") ||
                           matcher.test(item.serial || "") ||
                           matcher.test(item.description || "");
                });
                response(matches.slice(0, 30));
            },
            select: function(event, ui) {
                applySelectedItem({
                    name: ui.item.product_name || ui.item.label,
                    description: ui.item.description,
                    price: ui.item.price,
                    tax: ui.item.tax,
                    prod_id: ui.item.prod_id,
                    serial: ui.item.serial
                });
                return false;
            }
        });

        if ($("#name").data("ui-autocomplete")) {
            $("#name").autocomplete("instance")._renderItem = function(ul, item) {
                var badgeType = "";
                if (item.is_unit) {
                    badgeType = "<span class='badge badge-info mr-1'>S/N: " + esc(item.serial) + "</span>";
                } else if (item.type === 'service') {
                    badgeType = "<span class='badge badge-secondary mr-1'>Service</span>";
                } else if (item.product_tracking_type === 'serialized') {
                    badgeType = "<span class='badge badge-primary mr-1'>Serialized</span>";
                } else {
                    badgeType = "<span class='badge badge-warning text-dark mr-1'>Bulk</span>";
                }

                var stockBadge = "";
                if (item.available_stock !== null && item.available_stock !== undefined) {
                    var stockNum = parseInt(item.available_stock, 10);
                    stockBadge = stockNum > 0
                        ? "<span class='badge badge-success mr-1'>" + stockNum + " in stock</span>"
                        : "<span class='badge badge-danger mr-1'>0 in stock</span>";
                }

                var priceDisplay = item.price != null && item.price !== '' ? '$' + parseFloat(item.price).toFixed(2) : '';

                var content =
                    "<div class='d-flex justify-content-between align-items-start py-1'>" +
                        "<div class='flex-fill pr-2'>" +
                            "<div class='font-weight-bold text-dark'>" + esc(item.label) + "</div>" +
                            "<div class='mt-1'>" + badgeType + stockBadge + (item.product_code ? "<span class='badge badge-light border mr-1'>" + esc(item.product_code) + "</span>" : "") + "</div>" +
                        "</div>" +
                        "<div class='text-right font-weight-bold text-primary'>" + esc(priceDisplay) + "</div>" +
                    "</div>";

                return $("<li>")
                    .append($("<div class='ui-menu-item-wrapper'>").append(content))
                    .appendTo(ul);
            };
        }

        $("#name").on("input", function() {
            $("#product_id").val(0);
            $("#product-link-status").hide();
        });

        $("#btn-clear-product-link").on("click", function() {
            $("#product_id").val(0);
            $("#product-link-status").hide();
        });

        function populateModalTables() {
            var $inStockTbody = $("#tbody-in-stock");
            var $allTbody = $("#tbody-all-products");
            $inStockTbody.empty();
            $allTbody.empty();

            var inStockProducts = availableProducts.filter(function(p) {
                return (p.available_stock != null && parseInt(p.available_stock, 10) > 0);
            });

            $("#in-stock-count").text(inStockProducts.length + inventoryUnits.length);
            $("#all-products-count").text(availableProducts.length);

            if (inStockProducts.length === 0 && inventoryUnits.length === 0) {
                $inStockTbody.html("<tr><td colspan='6' class='text-center py-4 text-muted'>No items currently in stock</td></tr>");
            } else {
                inventoryUnits.forEach(function(u) {
                    var priceStr = u.item_price > 0 ? '$' + parseFloat(u.item_price).toFixed(2) : '$0.00';
                    var row =
                        "<tr class='picker-row' data-search='" + esc(u.product_name + " " + u.item_serial + " " + (u.product_code || '') + " " + (u.item_location || '')).toLowerCase() + "'>" +
                            "<td>" +
                                "<div class='font-weight-bold'>" + esc(u.product_name) + "</div>" +
                                "<div class='small text-info'><i class='fas fa-barcode mr-1'></i>S/N: <strong>" + esc(u.item_serial) + "</strong>" + (u.item_location ? " (" + esc(u.item_location) + ")" : "") + "</div>" +
                            "</td>" +
                            "<td class='align-middle text-muted'>Hardware</td>" +
                            "<td class='align-middle'><span class='badge badge-info'>Serialized Unit</span></td>" +
                            "<td class='align-middle'><span class='badge badge-success font-weight-bold'>1 Unit Available</span></td>" +
                            "<td class='align-middle text-right font-weight-bold'>" + esc(priceStr) + "</td>" +
                            "<td class='align-middle text-center'>" +
                                "<button type='button' class='btn btn-xs btn-primary btn-select-modal-item font-weight-bold' " +
                                    "data-name='" + esc(u.product_name) + "' " +
                                    "data-desc='" + esc((u.product_description ? u.product_description + "\n" : "") + "Serial Number: " + u.item_serial + (u.item_mac ? " (MAC: " + u.item_mac + ")" : "") + (u.item_location ? " | Location: " + u.item_location : "")) + "' " +
                                    "data-price='" + esc(u.item_price) + "' " +
                                    "data-tax='" + esc(u.tax) + "' " +
                                    "data-prod-id='" + esc(u.item_product_id) + "' " +
                                    "data-serial='" + esc(u.item_serial) + "'>" +
                                    "<i class='fas fa-check mr-1'></i>Select" +
                                "</button>" +
                            "</td>" +
                        "</tr>";
                    $inStockTbody.append(row);
                });

                inStockProducts.forEach(function(p) {
                    var priceStr = p.price != null && p.price !== '' ? '$' + parseFloat(p.price).toFixed(2) : '$0.00';
                    var trackingBadge = p.product_tracking_type === 'serialized'
                        ? "<span class='badge badge-info'>Serialized</span>"
                        : "<span class='badge badge-warning text-dark'>Bulk</span>";
                    var stockBadge = "<span class='badge badge-success font-weight-bold'>" + parseInt(p.available_stock, 10) + " available</span>";

                    var row =
                        "<tr class='picker-row' data-search='" + esc(p.product_name + " " + (p.product_code || '') + " " + (p.category_name || '')).toLowerCase() + "'>" +
                            "<td>" +
                                "<div class='font-weight-bold'>" + esc(p.product_name) + "</div>" +
                                (p.product_code ? "<small class='text-muted font-monospace'>" + esc(p.product_code) + "</small>" : "") +
                            "</td>" +
                            "<td class='align-middle text-muted'>" + esc(p.category_name || '-') + "</td>" +
                            "<td class='align-middle'>" + trackingBadge + "</td>" +
                            "<td class='align-middle'>" + stockBadge + "</td>" +
                            "<td class='align-middle text-right font-weight-bold'>" + esc(priceStr) + "</td>" +
                            "<td class='align-middle text-center'>" +
                                "<button type='button' class='btn btn-xs btn-primary btn-select-modal-item font-weight-bold' " +
                                    "data-name='" + esc(p.product_name) + "' " +
                                    "data-desc='" + esc(p.description || '') + "' " +
                                    "data-price='" + esc(p.price) + "' " +
                                    "data-tax='" + esc(p.tax) + "' " +
                                    "data-prod-id='" + esc(p.prod_id) + "'>" +
                                    "<i class='fas fa-check mr-1'></i>Select" +
                                "</button>" +
                            "</td>" +
                        "</tr>";
                    $inStockTbody.append(row);
                });
            }

            if (availableProducts.length === 0) {
                $allTbody.html("<tr><td colspan='6' class='text-center py-4 text-muted'>No products found</td></tr>");
            } else {
                availableProducts.forEach(function(p) {
                    var priceStr = p.price != null && p.price !== '' ? '$' + parseFloat(p.price).toFixed(2) : '$0.00';
                    var typeBadge = "";
                    if (p.type === 'service') {
                        typeBadge = "<span class='badge badge-secondary'>Service</span>";
                    } else if (p.product_tracking_type === 'serialized') {
                        typeBadge = "<span class='badge badge-info'>Serialized</span>";
                    } else {
                        typeBadge = "<span class='badge badge-warning text-dark'>Bulk</span>";
                    }

                    var stockBadge = "-";
                    if (p.available_stock !== null && p.available_stock !== undefined) {
                        var sNum = parseInt(p.available_stock, 10);
                        stockBadge = sNum > 0
                            ? "<span class='badge badge-success'>" + sNum + " on hand</span>"
                            : "<span class='badge badge-light border text-muted'>0 on hand</span>";
                    }

                    var row =
                        "<tr class='picker-row' data-search='" + esc(p.product_name + " " + (p.product_code || '') + " " + (p.category_name || '') + " " + (p.description || '')).toLowerCase() + "'>" +
                            "<td>" +
                                "<div class='font-weight-bold'>" + esc(p.product_name) + "</div>" +
                                (p.product_code ? "<small class='text-muted font-monospace'>" + esc(p.product_code) + "</small>" : "") +
                            "</td>" +
                            "<td class='align-middle text-muted'>" + esc(p.category_name || '-') + "</td>" +
                            "<td class='align-middle'>" + typeBadge + "</td>" +
                            "<td class='align-middle'>" + stockBadge + "</td>" +
                            "<td class='align-middle text-right font-weight-bold'>" + esc(priceStr) + "</td>" +
                            "<td class='align-middle text-center'>" +
                                "<button type='button' class='btn btn-xs btn-primary btn-select-modal-item font-weight-bold' " +
                                    "data-name='" + esc(p.product_name) + "' " +
                                    "data-desc='" + esc(p.description || '') + "' " +
                                    "data-price='" + esc(p.price) + "' " +
                                    "data-tax='" + esc(p.tax) + "' " +
                                    "data-prod-id='" + esc(p.prod_id) + "'>" +
                                    "<i class='fas fa-check mr-1'></i>Select" +
                                "</button>" +
                            "</td>" +
                        "</tr>";
                    $allTbody.append(row);
                });
            }
        }

        populateModalTables();

        $("#modal-item-search").on("input", function() {
            var q = $(this).val().toLowerCase().trim();
            $(".picker-row").each(function() {
                var hay = $(this).data("search") || "";
                if (!q || hay.indexOf(q) !== -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        $("#btn-modal-clear-search").on("click", function() {
            $("#modal-item-search").val("").trigger("input");
        });

        $(document).on("click", ".btn-select-modal-item", function() {
            applySelectedItem({
                name: $(this).data("name"),
                description: $(this).data("desc"),
                price: $(this).data("price"),
                tax: $(this).data("tax"),
                prod_id: $(this).data("prod-id"),
                serial: $(this).data("serial")
            });
        });
    });
</script>

<script src="../libs/SortableJS/Sortable.min.js"></script>
<script>
new Sortable(document.querySelector('table#items tbody'), {
    handle: '.drag-handle',
    animation: 150,
    onEnd: function (evt) {
        const rows = document.querySelectorAll('table#items tbody tr');
        const positions = Array.from(rows).map((row, index) => ({
            id: row.dataset.itemId,
            order: index
        }));

        $.post('ajax.php', {
            update_quote_items_order: true,
            csrf_token: '<?= $_SESSION['csrf_token'] ?>',
            quote_id: <?= $quote_id ?>,
            positions: positions
        });
    }
});
</script>
<link rel="stylesheet" href="css/quote_dropdowns_fix.css">
