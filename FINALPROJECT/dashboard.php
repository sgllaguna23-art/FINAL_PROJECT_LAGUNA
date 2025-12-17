<?php

// Checks if there is a user logged in.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$USER_ID = $_GET['user_id'] ?? '0'; 
$USER_NAME = $_GET['user_name'] ?? 'Guest';
$USER_ROLE = $_GET['user_role'] ?? 'cashier'; 
$APP_NAME = 'Kingdom Come: Café POS';
$USER_AUTH = "user_id=$USER_ID&user_name=$USER_NAME&user_role=$USER_ROLE";

if ($USER_ID === '0') {
    header('Location: login.html');
    exit;
}

$serverName = "SGLSYSTEMS\\SQLEXPRESS"; 
$connectionOptions = ["Database" => "DLSU", "Uid" => "", "PWD" => ""];
$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// DATA FETCHING

// 1. Total Sales (All Time)
$res_total = sqlsrv_query($conn, "SELECT SUM(ORDER_TOTAL) as total FROM ORDERS");
$total_sales = sqlsrv_fetch_array($res_total, SQLSRV_FETCH_ASSOC)['total'] ?? 0;

// 2. Today's Stats
$res_today = sqlsrv_query($conn, "SELECT COUNT(ORDER_ID) as count, SUM(ORDER_TOTAL) as total FROM ORDERS WHERE CAST(ORDER_DATE AS DATE) = CAST(GETDATE() AS DATE)");
$row_today = sqlsrv_fetch_array($res_today, SQLSRV_FETCH_ASSOC);
$tx_today = $row_today['count'] ?? 0;
$rev_today = $row_today['total'] ?? 0;

// 3. This Week's Stats (Last 7 Days)
$res_week = sqlsrv_query($conn, "SELECT COUNT(ORDER_ID) as count, SUM(ORDER_TOTAL) as total FROM ORDERS WHERE ORDER_DATE >= DATEADD(day, -7, GETDATE())");
$row_week = sqlsrv_fetch_array($res_week, SQLSRV_FETCH_ASSOC);
$tx_week = $row_week['count'] ?? 0;
$rev_week = $row_week['total'] ?? 0;

// 4. This Month's Stats
$res_month = sqlsrv_query($conn, "SELECT COUNT(ORDER_ID) as count, SUM(ORDER_TOTAL) as total FROM ORDERS WHERE MONTH(ORDER_DATE) = MONTH(GETDATE()) AND YEAR(ORDER_DATE) = YEAR(GETDATE())");
$row_month = sqlsrv_fetch_array($res_month, SQLSRV_FETCH_ASSOC);
$tx_month = $row_month['count'] ?? 0;
$rev_month = $row_month['total'] ?? 0;

// 5. Top Selling Brew/Bite
$sql_top = "SELECT TOP 1 p.PRODUCT_NAME, SUM(oi.QUANTITY) as total_qty 
            FROM ORDER_ITEMS oi 
            JOIN CAFE_PRODUCTS p ON oi.PRODUCT_ID = p.PRODUCT_ID 
            GROUP BY p.PRODUCT_NAME 
            ORDER BY total_qty DESC";
$res_top = sqlsrv_query($conn, $sql_top);
$top_item = sqlsrv_fetch_array($res_top, SQLSRV_FETCH_ASSOC)['PRODUCT_NAME'] ?? 'None yet';

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function format_money($a) { return '₱' . number_format((float)$a, 2); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - <?= e($APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Caudex:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        .brand-title { font-family: 'Caudex', serif; font-weight: 700; }
        .bg-brown { background-color: #5D4037 !important; }
        .text-brown { color: #5D4037 !important; }
        .stat-card { border: none; border-radius: 15px; background: rgba(255, 255, 255, 0.9); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body class="background">

<nav class="navbar navbar-expand-lg navbar-dark bg-brown shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand brand-title fw-bold" href="dashboard.php?<?= e($USER_AUTH) ?>"><?= e($APP_NAME) ?></a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link active" href="dashboard.php?<?= e($USER_AUTH) ?>">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="orders.php?<?= e($USER_AUTH) ?>">Orders</a></li>
                <?php if ($USER_ROLE === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="products.php?<?= e($USER_AUTH) ?>">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="reports.php?<?= e($USER_AUTH) ?>">Reports</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="settings.php?<?= e($USER_AUTH) ?>">Settings</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="text-light small">Logged in as: <?= e($USER_NAME) ?></span>
                <button class="btn btn-sm btn-outline-light" onclick="location.href='login.html'">Logout</button>
            </div>
        </div>
    </div>
</nav>

<main class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="brand-title">Kingdom Overview</h2>
        <button class="btn btn-brown px-4" onclick="location.href='orders.php?<?= e($USER_AUTH) ?>'">New Order</button>
    </div>

    <div class="row g-4">
        <div class="col-md-12">
            <div class="card stat-card shadow-sm p-4 text-center">
                <p class="text-muted text-uppercase small fw-bold mb-1">Total Sales (Lifetime)</p>
                <h1 class="display-4 fw-bold text-brown"><?= format_money($total_sales) ?></h1>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card shadow-sm p-4 h-100">
                <h5 class="brand-title border-bottom pb-2 mb-3">Today</h5>
                <div class="d-flex justify-content-between">
                    <span>Transactions:</span>
                    <span class="fw-bold"><?= number_format($tx_today) ?></span>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <span>Revenue:</span>
                    <span class="fw-bold text-success"><?= format_money($rev_today) ?></span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card shadow-sm p-4 h-100">
                <h5 class="brand-title border-bottom pb-2 mb-3">This Week</h5>
                <div class="d-flex justify-content-between">
                    <span>Transactions:</span>
                    <span class="fw-bold"><?= number_format($tx_week) ?></span>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <span>Revenue:</span>
                    <span class="fw-bold text-success"><?= format_money($rev_week) ?></span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card shadow-sm p-4 h-100">
                <h5 class="brand-title border-bottom pb-2 mb-3">This Month</h5>
                <div class="d-flex justify-content-between">
                    <span>Transactions:</span>
                    <span class="fw-bold"><?= number_format($tx_month) ?></span>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <span>Revenue:</span>
                    <span class="fw-bold text-success"><?= format_money($rev_month) ?></span>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card stat-card shadow-sm p-4 bg-brown text-white">
                <div class="row align-items-center">
                    <div class="col-md-8 text-center text-md-start">
                        <p class="small text-uppercase mb-1 opacity-75">Most Popular Selection</p>
                        <h3 class="brand-title mb-0"><?= e($top_item) ?></h3>
                    </div>
                    <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                        <i class="bi bi-trophy" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="text-center text-muted py-5 small">
    Crafted for the King, by the King. &mdash; <?= date('Y') ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>