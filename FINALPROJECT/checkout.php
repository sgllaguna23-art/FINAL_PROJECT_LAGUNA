<?php
// Checks if there is a user logged in.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sets up the session.
$_SESSION['cart'] = $_SESSION['cart'] ?? []; 
$APP_NAME = 'Kingdom Come: Café POS';

$USER_ID = $_GET['user_id'] ?? '0'; 
$USER_NAME = $_GET['user_name'] ?? 'Guest';
$USER_ROLE = $_GET['user_role'] ?? 'cashier'; 
$USER_AUTH = "user_id=$USER_ID&user_name=$USER_NAME&user_role=$USER_ROLE";

if ($USER_ID === '0') {
    header('Location: login.html');
    exit;
}

$serverName = "SGLSYSTEMS\\SQLEXPRESS"; 
$connectionOptions = ["Database" => "DLSU", "Uid" => "", "PWD" => ""];
$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die("Connection Failed: " . print_r(sqlsrv_errors(), true));
}

// Calculates total.
$grand_total = 0;
foreach ($_SESSION['cart'] as $item) {
    $grand_total += ($item['price'] * $item['qty']);
}

$show_receipt = false;
$receipt_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_transaction'])) {
    $payment_method = $_POST['payment_method'];
    $amount_received = (float)($_POST['cash_received'] ?? $grand_total);

    $sql_order = "INSERT INTO ORDERS (ORDER_DATE, ORDER_TOTAL, PAYMENT_METHOD, ACCOUNT_ID) 
                  OUTPUT INSERTED.ORDER_ID VALUES (GETDATE(), ?, ?, ?)";
    $params_order = [$grand_total, $payment_method, $USER_ID];
    $stmt_order = sqlsrv_query($conn, $sql_order, $params_order);
    
    if ($stmt_order && sqlsrv_fetch($stmt_order)) {
        $order_id = sqlsrv_get_field($stmt_order, 0);
        
        foreach ($_SESSION['cart'] as $item) {
            $item_total = $item['price'] * $item['qty'];
            $sql_item = "INSERT INTO ORDER_ITEMS (ORDER_ID, PRODUCT_ID, QUANTITY, TOTAL_PRICE) VALUES (?, ?, ?, ?)";
            sqlsrv_query($conn, $sql_item, [$order_id, $item['id'], $item['qty'], $item_total]);
        }
        
        // Prepares data for the Receipt Modal
        $receipt_data = [
            'id' => $order_id,
            'items' => $_SESSION['cart'],
            'total' => $grand_total,
            'method' => $payment_method,
            'received' => $amount_received,
            'change' => $amount_received - $grand_total
        ];
        
        $_SESSION['cart'] = []; 
        $show_receipt = true;
    }
}

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function format_money($a) { return '₱' . number_format((float)$a, 2); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - <?= e($APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Caudex:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        .brand-title { font-family: 'Caudex', serif; font-weight: 700; }
        .bg-brown { background-color: #5D4037 !important; }
        .btn-brown { background-color: #5D4037; color: white; border: none; }
        .btn-brown:hover { background-color: #4A332C; color: white; }
        .receipt-card { font-family: monospace; background: white; color: black; padding: 20px; border-radius: 8px; }
        .qr-code { max-width: 200px; border: 1px solid #ddd; padding: 5px; border-radius: 5px; }
    </style>
</head>
<body class="background">

<nav class="navbar navbar-expand-lg navbar-dark bg-brown shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand brand-title fw-bold" href="dashboard.php?<?= e($USER_AUTH) ?>"><?= e($APP_NAME) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php?<?= e($USER_AUTH) ?>">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="orders.php?<?= e($USER_AUTH) ?>">Orders</a></li>
                <?php if ($USER_ROLE === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="products.php?<?= e($USER_AUTH) ?>">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="reports.php?<?= e($USER_AUTH) ?>">Reports</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="settings.php?<?= e($USER_AUTH) ?>">Settings</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="text-light small">Cashier: <?= e($USER_NAME) ?></span>
                <button class="btn btn-sm btn-outline-light" id="logout-btn">Logout</button>
            </div>
        </div>
    </div>
</nav>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow-lg border-0" style="background-color: rgba(252, 249, 238, 0.95);">
                <div class="card-body p-4">
                    <h3 class="brand-title text-center mb-4">Complete Transaction</h3>
                    
                    <form method="POST" action="checkout.php?<?= e($USER_AUTH) ?>">
                        <div class="text-center mb-4">
                            <span class="text-muted small">Total Due</span>
                            <h1 class="fw-bold" style="color:#5D4037;"><?= format_money($grand_total) ?></h1>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Payment Method</label>
                            <select name="payment_method" id="payMethod" class="form-select" required>
                                <option value="CASH">Cash</option>
                                <option value="CARD">Card</option>
                                <option value="GCASH">GCash</option>
                            </select>
                        </div>

                        <div id="cashArea">
                            <div class="mb-3">
                                <label class="form-label">Amount Tendered</label>
                                <input type="number" step="0.01" name="cash_received" id="cashInput" class="form-control" placeholder="0.00">
                            </div>
                            <div class="d-flex justify-content-between p-2 bg-light rounded">
                                <span>Change:</span>
                                <span id="changeText" class="fw-bold text-success">₱0.00</span>
                            </div>
                        </div>

                        <div id="cardArea" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Card Number</label>
                                <input type="text" class="form-control" placeholder="XXXX-XXXX-XXXX-XXXX" maxlength="16">
                            </div>
                        </div>

                        <div id="gcashArea" style="display: none;" class="text-center">
                            <p class="mb-2 fw-bold">Scan to Pay via GCash</p>
                            <img src="assets/css/gcashnum.jpg" alt="GCash QR Code" class="qr-code mb-3 shadow-sm">
                        </div>

                        <button type="submit" name="finalize_transaction" class="btn btn-brown w-100 btn-lg mt-4 shadow">Confirm & Log Sale</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php if ($show_receipt): ?>
<div class="modal fade" id="receiptModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content receipt-card shadow-lg">
            <div class="text-center">
                <h5 class="fw-bold mb-0"><?= e($APP_NAME) ?></h5>
                <small>Order #<?= $receipt_data['id'] ?></small>
            </div>
            <hr>
            <?php foreach ($receipt_data['items'] as $item): ?>
                <div class="d-flex justify-content-between small">
                    <span><?= $item['qty'] ?>x <?= e($item['name']) ?></span>
                    <span><?= format_money($item['price'] * $item['qty']) ?></span>
                </div>
            <?php endforeach; ?>
            <hr>
            <div class="d-flex justify-content-between fw-bold">
                <span>TOTAL</span>
                <span><?= format_money($receipt_data['total']) ?></span>
            </div>
            <div class="d-flex justify-content-between small mt-2">
                <span>Paid (<?= $receipt_data['method'] ?>)</span>
                <span><?= format_money($receipt_data['received']) ?></span>
            </div>
            <div class="d-flex justify-content-between small">
                <span>Change</span>
                <span><?= format_money($receipt_data['change']) ?></span>
            </div>
            <hr>
            <p class="small text-center">Patronage Confirmed!</p>
            <button class="btn btn-dark w-100 btn-sm" onclick="window.location.href='orders.php?<?= e($USER_AUTH) ?>'">Next Customer</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const grandTotal = <?= $grand_total ?>;
    const payMethod = document.getElementById('payMethod');
    const cashArea = document.getElementById('cashArea');
    const cardArea = document.getElementById('cardArea');
    const gcashArea = document.getElementById('gcashArea');
    const cashInput = document.getElementById('cashInput');

    // Handle visibility of payment details
    payMethod.addEventListener('change', function() {
        cashArea.style.display = (this.value === 'CASH') ? 'block' : 'none';
        cardArea.style.display = (this.value === 'CARD') ? 'block' : 'none';
        gcashArea.style.display = (this.value === 'GCASH') ? 'block' : 'none';
        
        if(this.value === 'CASH') cashInput.required = true;
        else cashInput.required = false;
    });

    // Real-time change calculation
    cashInput.addEventListener('input', function() {
        const received = parseFloat(this.value) || 0;
        const change = received - grandTotal;
        document.getElementById('changeText').textContent = '₱' + (change > 0 ? change.toFixed(2) : '0.00');
    });

    // Logout and Modal triggers
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('logout-btn').addEventListener('click', function() {
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = 'login.html'; 
            }
        });

        <?php if ($show_receipt): ?>
            const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
            receiptModal.show();
        <?php endif; ?>
    });
</script>
</body>
</html>