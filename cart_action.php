<?php
// ============================================
// modules/orders/cart_action.php
// Session-based cart management
// ============================================
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_GET['action'] ?? '';
$pid    = (int)($_GET['id'] ?? 0);
$qty    = (int)($_GET['qty'] ?? 1);

if ($pid > 0) {
    switch ($action) {
        case 'add':
            if (isset($_SESSION['cart'][$pid])) {
                $_SESSION['cart'][$pid] += $qty;
            } else {
                $_SESSION['cart'][$pid] = $qty;
            }
            header("Location: ../../dashboards/customer_dashboard.php?msg=added");
            break;

        case 'update':
            if ($qty > 0) {
                $_SESSION['cart'][$pid] = $qty;
            } else {
                unset($_SESSION['cart'][$pid]);
            }
            header("Location: cart.php?msg=updated");
            break;

        case 'remove':
            unset($_SESSION['cart'][$pid]);
            header("Location: cart.php?msg=removed");
            break;

        case 'clear':
            $_SESSION['cart'] = [];
            header("Location: cart.php?msg=cleared");
            break;
    }
    exit();
}

header("Location: ../../dashboards/customer_dashboard.php");
exit();
