<?php
// ============================================
// index.php
// Root entry point - redirect based on role
// ============================================
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

switch ($_SESSION['role']) {
    case 'admin':           header("Location: dashboards/admin_dashboard.php"); break;
    case 'ho_manager':      header("Location: dashboards/ho_dashboard.php"); break;
    case 'rdc_staff':       header("Location: dashboards/rdc_dashboard.php"); break;
    case 'logistics_staff': header("Location: dashboards/logistics_dashboard.php"); break;
    case 'customer':        header("Location: dashboards/customer_dashboard.php"); break;
    default:                header("Location: auth/login.php");
}
exit();
