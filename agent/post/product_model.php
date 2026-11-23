<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql($_POST['name']);
$description = escapeSql($_POST['description']);
$code = escapeSql($_POST['code']);
$location = escapeSql($_POST['location']);
$price = floatval($_POST['price']);
$cost = floatval($_POST['cost'] ?? 0);
$min_stock = intval($_POST['min_stock'] ?? 0);
$tracking_type = in_array($_POST['tracking_type'] ?? '', ['serialized', 'bulk']) ? $_POST['tracking_type'] : 'serialized';
$category = intval($_POST['category']);
$tax = intval($_POST['tax']);
