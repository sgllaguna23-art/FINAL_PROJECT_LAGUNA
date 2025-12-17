<?php
//Checks if there is a user logged in.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['flash'] = $_SESSION['flash'] ?? null;

$USER_ID = $_GET['user_id'] ?? '0'; 
$USER_NAME = $_GET['user_name'] ?? 'Guest';
$USER_ROLE = $_GET['user_role'] ?? 'cashier'; 
$APP_NAME = 'Kingdom Come: Café POS';

$USER_AUTH = 'user_id=' . $USER_ID . '&user_name=' . $USER_NAME . '&user_role=' . $USER_ROLE;
$query_string = $USER_AUTH; 

if ($USER_ID === '0' || ($USER_ROLE !== 'admin')) {
    header('Location: login.html');
    exit;
}

$serverName = "SGLSYSTEMS\\SQLEXPRESS"; 
$connectionOptions = ["Database" => "DLSU", "Uid" => "", "PWD" => ""];
$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}


// Summary Stats
$stats = ['total_sales' => 0, 'order_count' => 0, 'avg_order' => 0];

$sql_stats = "SELECT 
                SUM(ORDER_TOTAL) as total_sales, 
                COUNT(ORDER_ID) as order_count, 
                AVG(ORDER_TOTAL) as avg_order 
              FROM ORDERS";
$res_stats = sqlsrv_query($conn, $sql_stats);
if ($res_stats && $row = sqlsrv_fetch_array($res_stats, SQLSRV_FETCH_ASSOC)) {
    $stats = $row;
}

// Recent Transactions List
$transactions = [];
$sql_tx = "SELECT TOP 50 * FROM ORDERS ORDER BY ORDER_DATE DESC";
$res_tx = sqlsrv_query($conn, $sql_tx);
if ($res_tx) {
    while ($row = sqlsrv_fetch_array($res_tx, SQLSRV_FETCH_ASSOC)) {
        $transactions[] = $row;
    }
}

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function format_money($a) { return '₱' . number_format((float)$a, 2); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - <?= e($APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Caudex:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        .brand-title { font-family: 'Caudex', serif; font-weight: 700; }
        .bg-brown { background-color: #5D4037 !important; }
        .text-brown { color: #5D4037 !important; }
        .btn-outline-brown { color: #5D4037; border-color: #5D4037; }
        .stat-card { background: rgba(255, 255, 255, 0.9); border: none; border-radius: 10px; }
    </style>
</head>
<body class="background">

<nav class="navbar navbar-expand-lg navbar-dark bg-brown shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand brand-title fw-bold" href="dashboard.php?<?= e($query_string) ?>"><?= e($APP_NAME) ?></a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php?<?= e($query_string) ?>">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="orders.php?<?= e($query_string) ?>">Orders</a></li>
                <li class="nav-item"><a class="nav-link" href="products.php?<?= e($query_string) ?>">Products</a></li>
                <li class="nav-item"><a class="nav-link active" href="reports.php?<?= e($query_string) ?>">Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="settings.php?<?= e($query_string) ?>">Settings</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="text-light small">Logged in as: <?= e($USER_NAME) ?></span>
                <button class="btn btn-sm btn-outline-light" id="logout-btn">Logout</button>
            </div>
        </div>
    </div>
</nav>

<main class="container py-5">
    <h2 class="brand-title mb-4">Financial Ledger</h2>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card stat-card shadow-sm p-3 text-center">
                <small class="text-muted text-uppercase fw-bold">Total Revenue</small>
                <h2 class="text-brown mb-0"><?= format_money($stats['total_sales']) ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card shadow-sm p-3 text-center">
                <small class="text-muted text-uppercase fw-bold">Orders Processed</small>
                <h2 class="text-brown mb-0"><?= number_format($stats['order_count']) ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card shadow-sm p-3 text-center">
                <small class="text-muted text-uppercase fw-bold">Average Order</small>
                <h2 class="text-brown mb-0"><?= format_money($stats['avg_order']) ?></h2>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 brand-title">Recent Transactions</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order ID</th>
                            <th>Date & Time</th>
                            <th class="text-end">Amount</th>
                            <th>Method</th>
                            <th>Account ID</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr><td colspan="6" class="text-center py-4">No transactions found in the records.</td></tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <?php 
                                    $date_string = "N/A";
                                    if ($tx['ORDER_DATE'] instanceof DateTime) {
                                        $date_string = $tx['ORDER_DATE']->format('Y-m-d H:i');
                                    }
                                ?>
                                <tr>
                                    <td>#<?= e($tx['ORDER_ID']) ?></td>
                                    <td><?= e($date_string) ?></td>
                                    <td class="text-end fw-bold text-brown"><?= format_money($tx['ORDER_TOTAL']) ?></td>
                                    <td><span class="badge bg-secondary opacity-75"><?= e($tx['PAYMENT_METHOD']) ?></span></td>
                                    <td><?= e($tx['ACCOUNT_ID']) ?></td>
                                    <td><button class="btn btn-sm btn-outline-brown" disabled>View Details</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<footer class="text-center text-muted py-4 small">
    Crafted for the King, by the King. &mdash; <?= date('Y') ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('logout-btn').addEventListener('click', function() {
        if (confirm('Are you sure you want to log out?')) {
            window.location.href = 'login.html'; 
        }
    });
</script>
</body>
</html>