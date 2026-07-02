<?php
// ============================================
// includes/header.php
// Top navigation bar
// ============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISDN - <?= $page_title ?? 'Dashboard' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ---- Global Layout ---- */
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; margin: 0; }

        /* ---- Sidebar ---- */
        #sidebar {
            width: 255px;
            height: 100vh;
            background: #0d1f35;
            position: fixed;
            top: 0; left: 0;
            z-index: 1000;
            transition: all 0.3s;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 20px 20px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-brand h5 {
            color: #fff;
            font-weight: 700;
            letter-spacing: 2px;
            margin: 0;
            font-size: 1.1rem;
        }
        .sidebar-brand small { color: rgba(255,255,255,0.45); font-size: 0.72rem; }

        .sidebar-section {
            padding: 15px 15px 5px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.35);
            font-weight: 600;
        }
        #sidebar .nav-link {
            color: rgba(255,255,255,0.65);
            padding: 9px 20px;
            font-size: 0.875rem;
            border-radius: 5px;
            margin: 1px 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }
        #sidebar .nav-link:hover,
        #sidebar .nav-link.active {
            background: #1a3a5c;
            color: #fff;
        }
        #sidebar .nav-link i { font-size: 1rem; width: 18px; }

        /* ---- Top Navbar ---- */
        #topbar {
            margin-left: 255px;
            background: #fff;
            border-bottom: 1px solid #e0e4ea;
            padding: 0 25px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .topbar-title { font-weight: 600; color: #1a3a5c; font-size: 1rem; }
        .user-badge {
            background: #1a3a5c;
            color: #fff;
            border-radius: 50%;
            width: 34px; height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .role-badge {
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 20px;
            background: #e8f0fe;
            color: #1a3a5c;
            font-weight: 600;
            text-transform: capitalize;
        }

        /* ---- Content Area ---- */
        #content {
            margin-left: 255px;
            padding: 25px;
            min-height: calc(100vh - 58px);
        }

        /* ---- KPI Cards ---- */
        .kpi-card {
            background: #fff;
            border-radius: 8px;
            padding: 20px 22px;
            border-left: 4px solid #1a3a5c;
            box-shadow: 0 1px 6px rgba(0,0,0,0.06);
        }
        .kpi-card.green { border-color: #198754; }
        .kpi-card.orange { border-color: #fd7e14; }
        .kpi-card.red { border-color: #dc3545; }
        .kpi-card.blue { border-color: #1a3a5c; }
        .kpi-label { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; font-weight: 600; }
        .kpi-value { font-size: 1.9rem; font-weight: 700; color: #1a3a5c; line-height: 1.2; }
        .kpi-icon { font-size: 1.8rem; opacity: 0.15; }

        /* ---- Page Card ---- */
        .page-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.06);
            padding: 22px 25px;
            margin-bottom: 20px;
        }
        .page-card-header {
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 14px;
            margin-bottom: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-card-header h5 { margin: 0; font-weight: 700; color: #1a3a5c; font-size: 1rem; }

        /* ---- Table ---- */
        .table thead th {
            background: #1a3a5c;
            color: #fff;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            padding: 10px 14px;
        }
        .table tbody td { font-size: 0.875rem; padding: 10px 14px; vertical-align: middle; }
        .table-hover tbody tr:hover { background: #f5f8ff; }

        /* ---- Status Badges ---- */
        .badge-pending           { background: #fef3cd; color: #856404; border: 1px solid #ffeeba; }
        .badge-active            { background: #d1e7dd; color: #0a3622; border: 1px solid #badbcc; }
        .badge-approved          { background: #cfe2ff; color: #084298; border: 1px solid #b8daff; }
        .badge-shipped           { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .badge-delivered         { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .badge-cancelled         { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .badge-out_for_delivery  { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
        .badge-delayed           { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .badge-processing        { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .badge-rejected          { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .badge-completed         { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }


        /* ---- Buttons ---- */
        .btn-primary { background: #1a3a5c; border-color: #1a3a5c; }
        .btn-primary:hover { background: #14304e; border-color: #14304e; }
        .btn-sm { font-size: 0.78rem; }

        /* Responsive */
        @media (max-width: 768px) {
            #sidebar { width: 0; overflow: hidden; }
            #topbar, #content { margin-left: 0; }
        }
        /* ---- Product Catalog Cards ---- */
        .product-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #eee !important;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }
        .section-header h5 {
            font-weight: 800;
            color: #0d1f35;
            position: relative;
            padding-bottom: 10px;
        }
        .section-header h5::after {
            content: '';
            position: absolute;
            left: 0; bottom: 0;
            width: 40px; height: 3px;
            background: #1a3a5c;
            border-radius: 2px;
        }
    </style>
</head>
<body>
