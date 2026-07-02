<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';

header('Content-Type: application/json');

if (!isset($_GET['q'])) {
    echo json_encode([]);
    exit;
}

$q = '%' . $_GET['q'] . '%';
$stmt = mysqli_prepare($conn, "SELECT id, name, sku FROM products WHERE (name LIKE ? OR sku LIKE ?) AND status='active' LIMIT 10");
mysqli_stmt_bind_param($stmt, "ss", $q, $q);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$products = [];
while ($row = mysqli_fetch_assoc($res)) {
    $products[] = $row;
}

echo json_encode($products);
