<?php

require_once dirname(__DIR__) . "/config.php";
require_once dirname(__DIR__) . "/functions.php";
require_once dirname(__DIR__) . "/includes/check_login.php";

header('Content-Type: application/json');

if (empty($session_is_admin) && (empty($session_user_role) || $session_user_role < 2)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied: Restricted to Technicians and Administrators']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? $_REQUEST['action'] ?? '';

if ($action === 'get_clients_and_categories') {
    $clients = [];
    $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql_clients)) {
        $clients[] = [
            'client_id' => intval($row['client_id']),
            'client_name' => $row['client_name']
        ];
    }

    $categories = [];
    $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Income' ORDER BY category_name ASC");
    while ($row = mysqli_fetch_assoc($sql_categories)) {
        $categories[] = [
            'category_id' => intval($row['category_id']),
            'category_name' => $row['category_name']
        ];
    }

    echo json_encode([
        'success' => true,
        'clients' => $clients,
        'categories' => $categories,
        'currency' => $session_company_currency ?? 'USD'
    ]);
    exit;
}

if ($action === 'get_inventory') {
    $q = trim($_GET['q'] ?? '');
    $category_id = intval($_GET['category'] ?? 0);
    $tracking_type = $_GET['tracking_type'] ?? 'all';
    $status_filter = $_GET['status'] ?? 'all';

    $where = ["product_archived_at IS NULL", "product_type = 'product'"];

    if (!empty($category_id)) {
        $where[] = "product_category_id = $category_id";
    }

    if ($tracking_type === 'serialized') {
        $where[] = "product_tracking_type = 'serialized'";
    } elseif ($tracking_type === 'bulk') {
        $where[] = "product_tracking_type = 'bulk'";
    }

    if (!empty($q)) {
        $escaped_q = escapeSql($q);
        $where[] = "(product_name LIKE '%$escaped_q%' OR product_code LIKE '%$escaped_q%' OR product_description LIKE '%$escaped_q%' OR product_location LIKE '%$escaped_q%' OR EXISTS (SELECT 1 FROM inventory_items WHERE item_product_id = products.product_id AND (item_serial LIKE '%$escaped_q%' OR item_mac LIKE '%$escaped_q%')))";
    }

    $where_clause = implode(' AND ', $where);

    $sql_products = mysqli_query($mysqli, "SELECT products.*, categories.category_name,
        COALESCE((SELECT SUM(stock_qty) FROM product_stock WHERE stock_product_id = products.product_id), 0) AS bulk_qty,
        (SELECT COUNT(*) FROM inventory_items WHERE item_product_id = products.product_id AND item_archived_at IS NULL AND item_status = 'In Stock') AS serialized_in_stock,
        (SELECT COUNT(*) FROM inventory_items WHERE item_product_id = products.product_id AND item_archived_at IS NULL AND item_status = 'Reserved') AS serialized_reserved,
        (SELECT COUNT(*) FROM inventory_items WHERE item_product_id = products.product_id AND item_archived_at IS NULL AND item_status = 'Sold') AS serialized_sold,
        (SELECT COUNT(*) FROM inventory_items WHERE item_product_id = products.product_id AND item_archived_at IS NULL AND item_status = 'RMA') AS serialized_rma,
        (SELECT COUNT(*) FROM inventory_items WHERE item_product_id = products.product_id AND item_archived_at IS NULL) AS serialized_total
        FROM products
        LEFT JOIN categories ON product_category_id = category_id
        WHERE $where_clause
        ORDER BY product_name ASC"
    );

    $products = [];
    $total_in_stock_units = 0;
    $total_inventory_cost = 0.0;
    $total_retail_value = 0.0;
    $low_stock_count = 0;

    while ($row = mysqli_fetch_assoc($sql_products)) {
        $pid = intval($row['product_id']);
        $p_type = $row['product_tracking_type'] ?? 'serialized';
        $cost = floatval($row['product_cost']);
        $price = floatval($row['product_price']);
        $min_stock = intval($row['product_min_stock']);

        $in_stock = 0;
        $reserved = 0;
        $sold = 0;
        $rma = 0;
        $total_units = 0;

        if ($p_type === 'bulk') {
            $in_stock = max(0, intval($row['bulk_qty']));
            $total_units = $in_stock;
        } else {
            $in_stock = intval($row['serialized_in_stock']);
            $reserved = intval($row['serialized_reserved']);
            $sold = intval($row['serialized_sold']);
            $rma = intval($row['serialized_rma']);
            $total_units = intval($row['serialized_total']);
        }

        $is_low = false;
        if ($in_stock == 0 || ($min_stock > 0 && $in_stock <= $min_stock)) {
            $is_low = true;
            $low_stock_count++;
        }

        if ($status_filter === 'in_stock' && $in_stock <= 0) {
            continue;
        } elseif ($status_filter === 'low_stock' && !$is_low) {
            continue;
        } elseif ($status_filter === 'out_of_stock' && $in_stock > 0) {
            continue;
        }

        $total_in_stock_units += $in_stock;
        $total_inventory_cost += ($in_stock * $cost);
        $total_retail_value += ($in_stock * $price);

        $margin_percent = 0;
        if ($price > 0) {
            $margin_percent = round((($price - $cost) / $price) * 100, 1);
        }

        $products[] = [
            'product_id' => $pid,
            'product_name' => $row['product_name'],
            'product_code' => $row['product_code'] ?? '',
            'product_description' => $row['product_description'] ?? '',
            'product_category_id' => intval($row['product_category_id']),
            'category_name' => $row['category_name'] ?? 'Uncategorized',
            'product_tracking_type' => $p_type,
            'product_price' => $price,
            'product_cost' => $cost,
            'product_min_stock' => $min_stock,
            'product_location' => $row['product_location'] ?? '',
            'in_stock_qty' => $in_stock,
            'reserved_qty' => $reserved,
            'sold_qty' => $sold,
            'rma_qty' => $rma,
            'total_units' => $total_units,
            'is_low_stock' => $is_low,
            'margin_percent' => $margin_percent
        ];
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_products' => count($products),
            'total_in_stock_units' => $total_in_stock_units,
            'total_inventory_cost' => round($total_inventory_cost, 2),
            'total_retail_value' => round($total_retail_value, 2),
            'low_stock_count' => $low_stock_count
        ],
        'products' => $products
    ]);
    exit;
}

if ($action === 'get_product') {
    $product_id = intval($_GET['product_id'] ?? 0);
    $sql = mysqli_query($mysqli, "SELECT products.*, categories.category_name,
        COALESCE((SELECT SUM(stock_qty) FROM product_stock WHERE stock_product_id = products.product_id), 0) AS bulk_qty
        FROM products
        LEFT JOIN categories ON product_category_id = category_id
        WHERE product_id = $product_id AND product_archived_at IS NULL LIMIT 1"
    );

    if (mysqli_num_rows($sql) === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }

    $product = mysqli_fetch_assoc($sql);
    $tracking_type = $product['product_tracking_type'] ?? 'serialized';

    $items = [];
    $stock_history = [];

    if ($tracking_type === 'serialized') {
        $sql_items = mysqli_query($mysqli, "SELECT inventory_items.*, clients.client_name
            FROM inventory_items
            LEFT JOIN clients ON item_client_id = client_id
            WHERE item_product_id = $product_id AND item_archived_at IS NULL
            ORDER BY FIELD(item_status, 'In Stock', 'Reserved', 'Sold', 'RMA', 'Defective'), item_created_at DESC"
        );
        while ($row = mysqli_fetch_assoc($sql_items)) {
            $items[] = [
                'item_id' => intval($row['item_id']),
                'item_serial' => $row['item_serial'],
                'item_status' => $row['item_status'],
                'item_location' => $row['item_location'] ?? '',
                'item_mac' => $row['item_mac'] ?? '',
                'item_cost' => floatval($row['item_cost']),
                'item_price' => floatval($row['item_price']),
                'item_client_id' => intval($row['item_client_id']),
                'client_name' => $row['client_name'] ?? '',
                'item_notes' => $row['item_notes'] ?? '',
                'item_created_at' => $row['item_created_at']
            ];
        }
    } else {
        $sql_history = mysqli_query($mysqli, "SELECT * FROM product_stock
            WHERE stock_product_id = $product_id
            ORDER BY stock_created_at DESC LIMIT 50"
        );
        while ($row = mysqli_fetch_assoc($sql_history)) {
            $stock_history[] = [
                'stock_id' => intval($row['stock_id']),
                'stock_qty' => intval($row['stock_qty']),
                'stock_note' => $row['stock_note'] ?? '',
                'stock_created_at' => $row['stock_created_at']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'product' => [
            'product_id' => intval($product['product_id']),
            'product_name' => $product['product_name'],
            'product_code' => $product['product_code'] ?? '',
            'product_description' => $product['product_description'] ?? '',
            'product_category_id' => intval($product['product_category_id']),
            'category_name' => $product['category_name'] ?? '',
            'product_tracking_type' => $tracking_type,
            'product_price' => floatval($product['product_price']),
            'product_cost' => floatval($product['product_cost']),
            'product_min_stock' => intval($product['product_min_stock']),
            'product_location' => $product['product_location'] ?? '',
            'bulk_qty' => intval($product['bulk_qty'])
        ],
        'items' => $items,
        'stock_history' => $stock_history
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();

    if ($action === 'save_product') {
        $product_id = intval($_POST['product_id'] ?? 0);
        $name = escapeSql(trim($_POST['name'] ?? ''));
        $code = escapeSql(trim($_POST['code'] ?? ''));
        $category = intval($_POST['category'] ?? 0);
        $tracking_type = in_array($_POST['tracking_type'] ?? '', ['serialized', 'bulk']) ? $_POST['tracking_type'] : 'serialized';
        $price = floatval($_POST['price'] ?? 0);
        $cost = floatval($_POST['cost'] ?? 0);
        $min_stock = intval($_POST['min_stock'] ?? 0);
        $location = escapeSql(trim($_POST['location'] ?? ''));
        $description = escapeSql(trim($_POST['description'] ?? ''));
        $currency = escapeSql($session_company_currency ?? 'USD');

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Product name is required']);
            exit;
        }

        if ($product_id > 0) {
            mysqli_query($mysqli, "UPDATE products SET
                product_name = '$name',
                product_code = '$code',
                product_category_id = $category,
                product_tracking_type = '$tracking_type',
                product_price = $price,
                product_cost = $cost,
                product_min_stock = $min_stock,
                product_location = '$location',
                product_description = '$description'
                WHERE product_id = $product_id"
            );

            logAudit("Product", "Edit", "$session_name edited product $name in inventory", 0, $product_id);

            echo json_encode([
                'success' => true,
                'product_id' => $product_id,
                'tracking_type' => $tracking_type,
                'message' => "Product $name updated successfully"
            ]);
            exit;
        } else {
            mysqli_query($mysqli, "INSERT INTO products SET
                product_name = '$name',
                product_type = 'product',
                product_code = '$code',
                product_category_id = $category,
                product_tracking_type = '$tracking_type',
                product_price = $price,
                product_cost = $cost,
                product_currency_code = '$currency',
                product_min_stock = $min_stock,
                product_location = '$location',
                product_description = '$description'"
            );

            $new_product_id = mysqli_insert_id($mysqli);

            logAudit("Product", "Create", "$session_name created saleable product $name in inventory", 0, $new_product_id);

            echo json_encode([
                'success' => true,
                'product_id' => $new_product_id,
                'product_name' => $name,
                'tracking_type' => $tracking_type,
                'product_cost' => $cost,
                'product_price' => $price,
                'product_location' => $location,
                'message' => "Product $name created successfully"
            ]);
            exit;
        }
    }

    if ($action === 'add_serialized_items') {
        $product_id = intval($_POST['product_id'] ?? 0);
        $raw_serials = trim($_POST['serials'] ?? '');
        $location = escapeSql(trim($_POST['location'] ?? ''));
        $mac = escapeSql(trim($_POST['mac'] ?? ''));
        $cost = floatval($_POST['cost'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['In Stock', 'Reserved', 'Sold', 'RMA', 'Defective']) ? $_POST['status'] : 'In Stock';
        $client_id = intval($_POST['client_id'] ?? 0);
        $notes = escapeSql(trim($_POST['notes'] ?? ''));

        if ($product_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid product ID']);
            exit;
        }

        if (empty($raw_serials)) {
            http_response_code(400);
            echo json_encode(['error' => 'At least one serial number is required']);
            exit;
        }

        $lines = preg_split('/[\r\n,]+/', $raw_serials);
        $clean_serials = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed)) {
                $clean_serials[] = $trimmed;
            }
        }

        if (empty($clean_serials)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid serial numbers found']);
            exit;
        }

        $added_count = 0;
        $duplicates = [];

        foreach ($clean_serials as $serial) {
            $escaped_serial = escapeSql($serial);

            $check = mysqli_query($mysqli, "SELECT item_id FROM inventory_items WHERE item_product_id = $product_id AND item_serial = '$escaped_serial' AND item_archived_at IS NULL LIMIT 1");
            if (mysqli_num_rows($check) > 0) {
                $duplicates[] = $serial;
                continue;
            }

            mysqli_query($mysqli, "INSERT INTO inventory_items SET
                item_product_id = $product_id,
                item_serial = '$escaped_serial',
                item_status = '$status',
                item_location = '$location',
                item_mac = '$mac',
                item_cost = $cost,
                item_price = $price,
                item_client_id = $client_id,
                item_notes = '$notes'"
            );
            $added_count++;
        }

        $p_name = escapeSql(getFieldById('products', $product_id, 'product_name'));
        logAudit("Inventory", "Create", "$session_name added $added_count item(s) for $p_name", $client_id, $product_id);

        echo json_encode([
            'success' => true,
            'added_count' => $added_count,
            'duplicates' => $duplicates,
            'message' => "Added $added_count serialized item(s)" . (!empty($duplicates) ? " (" . count($duplicates) . " duplicate(s) skipped)" : "")
        ]);
        exit;
    }

    if ($action === 'update_serialized_item') {
        $item_id = intval($_POST['item_id'] ?? 0);
        $serial = escapeSql(trim($_POST['serial'] ?? ''));
        $status = in_array($_POST['status'] ?? '', ['In Stock', 'Reserved', 'Sold', 'RMA', 'Defective']) ? $_POST['status'] : 'In Stock';
        $location = escapeSql(trim($_POST['location'] ?? ''));
        $mac = escapeSql(trim($_POST['mac'] ?? ''));
        $cost = floatval($_POST['cost'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $client_id = intval($_POST['client_id'] ?? 0);
        $notes = escapeSql(trim($_POST['notes'] ?? ''));

        if ($item_id <= 0 || empty($serial)) {
            http_response_code(400);
            echo json_encode(['error' => 'Item ID and Serial are required']);
            exit;
        }

        mysqli_query($mysqli, "UPDATE inventory_items SET
            item_serial = '$serial',
            item_status = '$status',
            item_location = '$location',
            item_mac = '$mac',
            item_cost = $cost,
            item_price = $price,
            item_client_id = $client_id,
            item_notes = '$notes'
            WHERE item_id = $item_id"
        );

        logAudit("Inventory", "Edit", "$session_name updated serialized item $serial ($status)", $client_id, $item_id);

        echo json_encode([
            'success' => true,
            'message' => "Item $serial updated successfully"
        ]);
        exit;
    }

    if ($action === 'delete_serialized_item') {
        $item_id = intval($_POST['item_id'] ?? 0);

        if ($item_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Item ID']);
            exit;
        }

        $serial = escapeSql(getFieldById('inventory_items', $item_id, 'item_serial'));
        mysqli_query($mysqli, "DELETE FROM inventory_items WHERE item_id = $item_id");

        logAudit("Inventory", "Delete", "$session_name deleted serialized item $serial", 0, $item_id);

        echo json_encode([
            'success' => true,
            'message' => "Item $serial removed"
        ]);
        exit;
    }

    if ($action === 'delete_product') {
        $product_id = intval($_POST['product_id'] ?? 0);

        if ($product_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid product ID']);
            exit;
        }

        $check_sql = mysqli_query($mysqli, "SELECT product_name FROM products WHERE product_id = $product_id LIMIT 1");
        if (mysqli_num_rows($check_sql) === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            exit;
        }

        $prod_row = mysqli_fetch_assoc($check_sql);
        $product_name = $prod_row['product_name'];
        $escaped_name = escapeSql($product_name);

        mysqli_query($mysqli, "DELETE FROM inventory_items WHERE item_product_id = $product_id");
        mysqli_query($mysqli, "DELETE FROM product_stock WHERE stock_product_id = $product_id");
        mysqli_query($mysqli, "DELETE FROM products WHERE product_id = $product_id");

        logAudit("Product", "Delete", "$session_name deleted product $escaped_name from inventory", 0, $product_id);

        echo json_encode([
            'success' => true,
            'message' => "Product \"$product_name\" deleted successfully"
        ]);
        exit;
    }

    if ($action === 'bulk_delete_products') {
        $raw_ids = $_POST['product_ids'] ?? [];
        if (is_string($raw_ids)) {
            $decoded = json_decode($raw_ids, true);
            if (is_array($decoded)) {
                $raw_ids = $decoded;
            } else {
                $raw_ids = explode(',', $raw_ids);
            }
        }

        $product_ids = [];
        if (is_array($raw_ids)) {
            foreach ($raw_ids as $id) {
                $id_int = intval($id);
                if ($id_int > 0) {
                    $product_ids[] = $id_int;
                }
            }
        }

        $product_ids = array_unique($product_ids);

        if (empty($product_ids)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid products selected for deletion']);
            exit;
        }

        $deleted_count = 0;
        foreach ($product_ids as $product_id) {
            $check_sql = mysqli_query($mysqli, "SELECT product_name FROM products WHERE product_id = $product_id LIMIT 1");
            if (mysqli_num_rows($check_sql) > 0) {
                $row = mysqli_fetch_assoc($check_sql);
                $escaped_name = escapeSql($row['product_name']);

                mysqli_query($mysqli, "DELETE FROM inventory_items WHERE item_product_id = $product_id");
                mysqli_query($mysqli, "DELETE FROM product_stock WHERE stock_product_id = $product_id");
                mysqli_query($mysqli, "DELETE FROM products WHERE product_id = $product_id");

                logAudit("Product", "Delete", "$session_name deleted product $escaped_name from inventory", 0, $product_id);
                $deleted_count++;
            }
        }

        logAudit("Product", "Bulk Delete", "$session_name deleted $deleted_count product(s) from inventory");

        echo json_encode([
            'success' => true,
            'deleted_count' => $deleted_count,
            'message' => "Successfully deleted $deleted_count product(s)"
        ]);
        exit;
    }

    if ($action === 'adjust_bulk_stock') {
        $product_id = intval($_POST['product_id'] ?? 0);
        $adjustment_type = $_POST['adjustment_type'] ?? 'add';
        $qty = intval($_POST['qty'] ?? 0);
        $note = escapeSql(trim($_POST['note'] ?? ''));

        if ($product_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid product ID']);
            exit;
        }

        $cur_sql = mysqli_query($mysqli, "SELECT COALESCE(SUM(stock_qty), 0) AS total_qty FROM product_stock WHERE stock_product_id = $product_id");
        $cur_row = mysqli_fetch_assoc($cur_sql);
        $current_qty = intval($cur_row['total_qty']);

        $stock_delta = 0;
        if ($adjustment_type === 'add') {
            if ($qty <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Quantity to add must be greater than 0']);
                exit;
            }
            $stock_delta = $qty;
            if (empty($note)) {
                $note = "Stock added: +$qty";
            }
        } elseif ($adjustment_type === 'deduct') {
            if ($qty <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Quantity to deduct must be greater than 0']);
                exit;
            }
            $stock_delta = -$qty;
            if (empty($note)) {
                $note = "Stock deducted: -$qty";
            }
        } elseif ($adjustment_type === 'set') {
            $stock_delta = $qty - $current_qty;
            if (empty($note)) {
                $note = "Inventory count audit set to $qty";
            }
        }

        if ($stock_delta !== 0) {
            mysqli_query($mysqli, "INSERT INTO product_stock SET
                stock_qty = $stock_delta,
                stock_note = '$note',
                stock_product_id = $product_id"
            );
        }

        $new_qty = $current_qty + $stock_delta;
        $p_name = escapeSql(getFieldById('products', $product_id, 'product_name'));
        logAudit("Inventory", "Stock", "$session_name adjusted stock for $p_name ($stock_delta units, now $new_qty)", 0, $product_id);

        echo json_encode([
            'success' => true,
            'new_qty' => $new_qty,
            'message' => "Stock updated to $new_qty units"
        ]);
        exit;
    }

    if ($action === 'import_inventory') {
        $raw_rows = $_POST['rows'] ?? '';
        $rows = json_decode($raw_rows, true);

        if (!is_array($rows) || empty($rows)) {
            http_response_code(400);
            echo json_encode(['error' => 'No rows provided for import']);
            exit;
        }

        $default_category_id = intval($_POST['default_category_id'] ?? 0);
        $default_tracking_type = in_array($_POST['default_tracking_type'] ?? '', ['serialized', 'bulk']) ? $_POST['default_tracking_type'] : 'serialized';
        $default_status = in_array($_POST['default_status'] ?? '', ['In Stock', 'Reserved', 'Sold', 'RMA', 'Defective']) ? $_POST['default_status'] : 'In Stock';
        $currency = escapeSql($session_company_currency ?? 'USD');

        $categories_cache = [];
        $sql_cat = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Income'");
        while ($c_row = mysqli_fetch_assoc($sql_cat)) {
            $categories_cache[strtolower(trim($c_row['category_name']))] = intval($c_row['category_id']);
        }

        $clients_cache = [];
        $sql_cli = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL");
        while ($cl_row = mysqli_fetch_assoc($sql_cli)) {
            $clients_cache[strtolower(trim($cl_row['client_name']))] = intval($cl_row['client_id']);
        }

        $products_created = 0;
        $products_updated = 0;
        $items_created = 0;
        $bulk_units_added = 0;
        $duplicates_skipped = 0;

        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');
            if (empty($name)) {
                continue;
            }
            $escaped_name = escapeSql($name);
            $code = trim($row['code'] ?? '');
            $escaped_code = escapeSql($code);
            $location = escapeSql(trim($row['location'] ?? ''));
            $description = escapeSql(trim($row['description'] ?? ''));
            $cost = floatval($row['cost'] ?? 0);
            $price = floatval($row['price'] ?? 0);
            $min_stock = intval($row['min_stock'] ?? 0);

            $category_id = $default_category_id;
            if (!empty($row['category'])) {
                $cat_lookup = strtolower(trim($row['category']));
                if (isset($categories_cache[$cat_lookup])) {
                    $category_id = $categories_cache[$cat_lookup];
                }
            }

            $tracking_type = $default_tracking_type;
            if (!empty($row['tracking_type'])) {
                $t_lookup = strtolower(trim($row['tracking_type']));
                if (in_array($t_lookup, ['serialized', 'bulk'])) {
                    $tracking_type = $t_lookup;
                }
            } elseif (!empty($row['serial'])) {
                $tracking_type = 'serialized';
            } elseif (isset($row['qty']) && intval($row['qty']) > 0 && empty($row['serial'])) {
                $tracking_type = 'bulk';
            }

            $product_id = 0;
            $check_product = mysqli_query($mysqli, "SELECT product_id, product_tracking_type FROM products WHERE product_archived_at IS NULL AND ((product_code = '$escaped_code' AND product_code != '') OR product_name = '$escaped_name') LIMIT 1");
            if (mysqli_num_rows($check_product) > 0) {
                $p_row = mysqli_fetch_assoc($check_product);
                $product_id = intval($p_row['product_id']);
                $tracking_type = $p_row['product_tracking_type'];

                $update_fields = [];
                if ($cost > 0) $update_fields[] = "product_cost = $cost";
                if ($price > 0) $update_fields[] = "product_price = $price";
                if (!empty($location)) $update_fields[] = "product_location = '$location'";
                if (!empty($description)) $update_fields[] = "product_description = '$description'";
                if (!empty($code)) $update_fields[] = "product_code = '$escaped_code'";
                if ($min_stock > 0) $update_fields[] = "product_min_stock = $min_stock";

                if (!empty($update_fields)) {
                    mysqli_query($mysqli, "UPDATE products SET " . implode(', ', $update_fields) . " WHERE product_id = $product_id");
                    $products_updated++;
                }
            } else {
                mysqli_query($mysqli, "INSERT INTO products SET
                    product_name = '$escaped_name',
                    product_type = 'product',
                    product_code = '$escaped_code',
                    product_category_id = $category_id,
                    product_tracking_type = '$tracking_type',
                    product_price = $price,
                    product_cost = $cost,
                    product_currency_code = '$currency',
                    product_min_stock = $min_stock,
                    product_location = '$location',
                    product_description = '$description'"
                );
                $product_id = mysqli_insert_id($mysqli);
                $products_created++;
            }

            if ($tracking_type === 'serialized') {
                $serial = trim($row['serial'] ?? '');
                if (!empty($serial)) {
                    $escaped_serial = escapeSql($serial);
                    $check_serial = mysqli_query($mysqli, "SELECT item_id FROM inventory_items WHERE item_product_id = $product_id AND item_serial = '$escaped_serial' AND item_archived_at IS NULL LIMIT 1");
                    if (mysqli_num_rows($check_serial) > 0) {
                        $duplicates_skipped++;
                    } else {
                        $status = $default_status;
                        if (!empty($row['status'])) {
                            $st_trim = trim($row['status']);
                            if (in_array($st_trim, ['In Stock', 'Reserved', 'Sold', 'RMA', 'Defective'])) {
                                $status = $st_trim;
                            }
                        }

                        $client_id = 0;
                        if (!empty($row['client'])) {
                            $cli_lookup = strtolower(trim($row['client']));
                            if (isset($clients_cache[$cli_lookup])) {
                                $client_id = $clients_cache[$cli_lookup];
                            }
                        }

                        $mac = escapeSql(trim($row['mac'] ?? ''));
                        $item_notes = escapeSql(trim($row['notes'] ?? ''));
                        $item_location = !empty($location) ? $location : escapeSql(trim($row['item_location'] ?? ''));

                        mysqli_query($mysqli, "INSERT INTO inventory_items SET
                            item_product_id = $product_id,
                            item_serial = '$escaped_serial',
                            item_status = '$status',
                            item_location = '$item_location',
                            item_mac = '$mac',
                            item_cost = $cost,
                            item_price = $price,
                            item_client_id = $client_id,
                            item_notes = '$item_notes'"
                        );
                        $items_created++;
                    }
                }
            } else {
                $qty = intval($row['qty'] ?? 0);
                if ($qty > 0) {
                    mysqli_query($mysqli, "INSERT INTO product_stock SET
                        stock_product_id = $product_id,
                        stock_qty = $qty,
                        stock_note = 'Bulk import'"
                    );
                    $bulk_units_added += $qty;
                }
            }
        }

        logAudit("Inventory", "Import", "$session_name imported $products_created product(s), $items_created serialized item(s), $bulk_units_added bulk units", 0, 0);

        echo json_encode([
            'success' => true,
            'products_created' => $products_created,
            'products_updated' => $products_updated,
            'items_created' => $items_created,
            'bulk_units_added' => $bulk_units_added,
            'duplicates_skipped' => $duplicates_skipped,
            'message' => "Import complete: $products_created product(s) added, $items_created serialized unit(s) created, $bulk_units_added bulk unit(s) stocked" . ($duplicates_skipped > 0 ? " ($duplicates_skipped duplicate serials skipped)" : "")
        ]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request action']);
exit;
