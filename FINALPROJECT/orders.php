<?php


// Checks if there is a user logged in.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Checks and sets up the cart.
$_SESSION['flash'] = $_SESSION['flash'] ?? null;
$_SESSION['cart'] = $_SESSION['cart'] ?? []; 
$cart_items = $_SESSION['cart']; 

// Checks which user is logged in.
$USER_ID = $_GET['user_id'] ?? '0'; 
$USER_NAME = $_GET['user_name'] ?? 'Guest';
$USER_ROLE = $_GET['user_role'] ?? 'cashier'; 
$APP_NAME = 'Kingdom Come: Café POS';

$USER_AUTH = 'user_id=' . $USER_ID . '&user_name=' . $USER_NAME . '&user_role=' . $USER_ROLE;
$query_string = $USER_AUTH; 

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

function e($value) { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function format_money($amount) { return '₱' . number_format((float)$amount, 2); }

// Adds/removes items to/from the cart.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect_url = 'orders.php?' . $query_string;

    if ($action === 'add_item') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 1);

        $sql = "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.PRODUCT_PRICE, 
                (SELECT TOP 1 IMAGE_URL FROM PRODUCT_IMAGES WHERE PRODUCT_ID = p.PRODUCT_ID ORDER BY IMAGE_ID ASC) as IMAGE_URL
                FROM CAFE_PRODUCTS p WHERE p.PRODUCT_ID = ?";
        $res = sqlsrv_query($conn, $sql, [$pid]);
        
        if ($res && $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
            $item_id = $row['PRODUCT_ID'];
            
            $raw_path = (!empty($row['IMAGE_URL'])) ? $row['IMAGE_URL'] : 'assets/images/default.png';
            $img_path = str_replace('\\', '/', $raw_path);
            
            if (isset($_SESSION['cart'][$item_id])) {
                $_SESSION['cart'][$item_id]['qty'] += $qty;
            } else {
                $_SESSION['cart'][$item_id] = [
                    'id'    => $row['PRODUCT_ID'],
                    'name'  => $row['PRODUCT_NAME'],
                    'price' => (float)$row['PRODUCT_PRICE'],
                    'qty'   => $qty,
                    'img'   => $img_path
                ];
            }
        }
    } elseif ($action === 'remove_item') {
        $pid = (int)($_POST['product_id'] ?? 0);
        unset($_SESSION['cart'][$pid]);
    } elseif ($action === 'clear_cart') {
        $_SESSION['cart'] = [];
    }

    header('Location: ' . $redirect_url);
    exit;
}

// Fetches data from tables.
$products = [];
$sql_prod = "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.PRODUCT_TYPE, p.PRODUCT_PRICE,
             (SELECT TOP 1 IMAGE_URL FROM PRODUCT_IMAGES WHERE PRODUCT_ID = p.PRODUCT_ID ORDER BY IMAGE_ID ASC) AS IMAGE_URL
             FROM CAFE_PRODUCTS p WHERE p.IS_ACTIVE = 1 ORDER BY p.PRODUCT_TYPE, p.PRODUCT_NAME";

$res_prod = sqlsrv_query($conn, $sql_prod);
if ($res_prod) {
    while ($row = sqlsrv_fetch_array($res_prod, SQLSRV_FETCH_ASSOC)) {
        $raw_path = (!empty($row['IMAGE_URL'])) ? $row['IMAGE_URL'] : 'assets/images/default.png';
        $row['IMAGE_URL'] = str_replace('\\', '/', $raw_path);
        
        $products[] = $row;
    }
}

$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += ($item['price'] * $item['qty']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders <?= e($APP_NAME) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Caudex:wght@400;700&family=MedievalSharp&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/theme.css"> 
    
    <style>
        .brand-title, .main-heading { font-family: 'Caudex', serif !important; font-weight: 700 !important; }
        .bg-brown { background-color: #5D4037 !important; }
        .text-brown { color: #5D4037 !important; }
        
        .product-card { 
            cursor: pointer; 
            transition: transform 0.2s, box-shadow 0.2s; 
            border: none;
            background-color: rgba(252, 249, 238, 0.9);
        }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .product-card img { height: 160px; object-fit: cover; border-top-left-radius: 8px; border-top-right-radius: 8px; }
        
        .cart-container {
            position: sticky;
            top: 20px;
            background-color: rgba(252, 249, 238, 0.95);
            border-radius: 12px;
        }
    </style>
</head>

<body class="background">

<nav class="navbar navbar-expand-lg navbar-dark bg-brown shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand brand-title fw-bold" href="dashboard.php?<?= e($query_string) ?>"><?= e($APP_NAME) ?></a> 
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php?<?= e($query_string) ?>">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active" href="orders.php?<?= e($query_string) ?>">Orders</a></li>
                
                <?php if ($USER_ROLE === 'admin'): ?>
                <li class="nav-item"><a class="nav-link" href="products.php?<?= e($query_string) ?>">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php?<?= e($query_string) ?>">Reports</a></li>
                <?php endif; ?>
                
                <li class="nav-item"><a class="nav-link" href="settings.php?<?= e($query_string) ?>">Settings</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="text-light small">Logged in as: <?= e($USER_NAME) ?></span>
                <button class="btn btn-sm btn-outline-light" id="logout-btn">Logout</button>
            </div>
        </div>
    </div>
</nav>

<main class="container-fluid py-4 px-4">
    <div class="row g-4">
        <div class="col-lg-8">
            <h2 class="main-heading mb-4">Today's Provisions</h2>
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php foreach ($products as $p): ?>
                <div class="col">
                    <div class="card h-100 product-card shadow-sm" data-product-id="<?= e($p['PRODUCT_ID']) ?>">
                        <img src="<?= e($p['IMAGE_URL']) ?>" class="card-img-top" alt="<?= e($p['PRODUCT_NAME']) ?>">
                        <div class="card-body">
                            <h5 class="card-title text-brown fw-bold"><?= e($p['PRODUCT_NAME']) ?></h5>
                            <p class="card-text text-muted small"><?= e($p['PRODUCT_TYPE']) ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-5 fw-bold"><?= format_money($p['PRODUCT_PRICE']) ?></span>
                                <span class="badge bg-brown">Add to Bill</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="cart-container shadow-lg p-4">
                <h3 class="brand-title text-brown border-bottom pb-2 mb-3">Current Bill</h3>
                
                <div class="cart-items mb-4" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($_SESSION['cart'])): ?>
                        <p class="text-center text-muted py-5">The scroll is empty. Select a provision to begin.</p>
                    <?php else: ?>
                        <?php foreach ($_SESSION['cart'] as $item): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                            <div>
                                <h6 class="mb-0 fw-bold"><?= e($item['name']) ?></h6>
                                <small class="text-muted"><?= format_money($item['price']) ?> x <?= e($item['qty']) ?></small>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="fw-bold"><?= format_money($item['price'] * $item['qty']) ?></span>
                                <form method="post" action="orders.php?<?= e($query_string) ?>">
                                    <input type="hidden" name="action" value="remove_item">
                                    <input type="hidden" name="product_id" value="<?= e($item['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0">Remove</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="cart-summary bg-light p-3 rounded mb-3">
                    <div class="d-flex justify-content-between fs-5 fw-bold">
                        <span>Total Price</span>
                        <span class="text-brown"><?= format_money($subtotal) ?></span>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-8">
                        <a href="checkout.php?<?= e($query_string) ?>" class="btn btn-brown btn-lg w-100 <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>">Proceed to Payment</a>
                    </div>
                    <div class="col-4">
                        <form method="post" action="orders.php?<?= e($query_string) ?>">
                            <input type="hidden" name="action" value="clear_cart">
                            <button type="submit" class="btn btn-outline-danger btn-lg w-100" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>Clear</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="add-to-cart-form" method="post" action="orders.php?<?= e($query_string) ?>" style="display: none;">
        <input type="hidden" name="action" value="add_item">
        <input type="hidden" name="product_id" id="add-product-id">
        <input type="hidden" name="quantity" value="1"> 
    </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('logout-btn').addEventListener('click', function() {
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = 'login.html';
            }
        });

        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                if (productId) {
                    document.getElementById('add-product-id').value = productId;
                    document.getElementById('add-to-cart-form').submit();
                }
            });
        });
    });
</script>
</body>
</html>