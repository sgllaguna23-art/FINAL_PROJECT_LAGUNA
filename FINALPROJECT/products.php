<?php
// Checks if there is a user logged in.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['flash'] = $_SESSION['flash'] ?? null;

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
if ($USER_ROLE !== 'admin') {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Access Denied: Only Admins can view this page.'];
    header('Location: dashboard.php?' . $query_string);
    exit;
}

$serverName = "SGLSYSTEMS\\SQLEXPRESS"; 
$connectionOptions = ["Database" => "DLSU", "Uid" => "", "PWD" => ""];
$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Deletes Products.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product_id'])) {
    $del_id = $_POST['delete_product_id'];
    
    sqlsrv_query($conn, "DELETE FROM PRODUCT_IMAGES WHERE PRODUCT_ID = ?", [$del_id]);
    
    $sql_del = "DELETE FROM CAFE_PRODUCTS WHERE PRODUCT_ID = ?";
    $stmt_del = sqlsrv_query($conn, $sql_del, [$del_id]);
    
    if ($stmt_del) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Product successfully removed from the kingdom.'];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to remove product.'];
    }
    header("Location: products.php?$query_string");
    exit;
}

// Opens a widget to add products.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_name']) && !isset($_POST['delete_product_id'])) {
    $p_id    = $_POST['product_id'] ?? ''; 
    $p_name  = $_POST['product_name'];
    $p_type  = $_POST['product_type'];
    $p_price = $_POST['product_price'];
    $p_active= isset($_POST['is_active']) ? 1 : 0;

    if (!empty($p_id)) {
        $sql = "UPDATE CAFE_PRODUCTS SET PRODUCT_NAME = ?, PRODUCT_TYPE = ?, PRODUCT_PRICE = ?, IS_ACTIVE = ? WHERE PRODUCT_ID = ?";
        $params = [$p_name, $p_type, $p_price, $p_active, $p_id];
        $stmt = sqlsrv_query($conn, $sql, $params);
    } else {
        $sql = "INSERT INTO CAFE_PRODUCTS (PRODUCT_NAME, PRODUCT_TYPE, PRODUCT_PRICE, IS_ACTIVE) OUTPUT INSERTED.PRODUCT_ID VALUES (?, ?, ?, ?)";
        $params = [$p_name, $p_type, $p_price, $p_active];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt && sqlsrv_fetch($stmt)) { $p_id = sqlsrv_get_field($stmt, 0); }
    }
    if ($stmt && !empty($_FILES['product_image']['name'])) {
        $target_dir = "assets/images/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_name = time() . '_' . basename($_FILES["product_image"]["name"]);
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
            sqlsrv_query($conn, "DELETE FROM PRODUCT_IMAGES WHERE PRODUCT_ID = ?", [$p_id]);
            sqlsrv_query($conn, "INSERT INTO PRODUCT_IMAGES (PRODUCT_ID, IMAGE_URL) VALUES (?, ?)", [$p_id, $target_file]);
        }
    }

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Registry updated successfully.'];
    header("Location: products.php?$query_string");
    exit;
}

$tsql = "SELECT p.*, i.IMAGE_URL FROM CAFE_PRODUCTS p LEFT JOIN PRODUCT_IMAGES i ON p.PRODUCT_ID = i.PRODUCT_ID ORDER BY p.PRODUCT_NAME";
$getProducts = sqlsrv_query($conn, $tsql);

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - <?= e($APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Caudex:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        .brand-title { font-family: 'Caudex', serif; font-weight: 700; }
        .bg-brown { background-color: #5D4037 !important; }
        .btn-brown { background-color: #5D4037; color: #fff; border: none; }
        .btn-brown:hover { background-color: #4E342E; color: #fff; }
        .table-registry { background: rgba(255,255,255,0.9); border-radius: 12px; overflow: hidden; }
        .product-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 5px; border: 1px solid #ddd; }
    </style>
</head>
<body class="background">

<nav class="navbar navbar-expand-lg navbar-dark bg-brown shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand brand-title fw-bold" href="dashboard.php?<?= e($query_string) ?>"><?= e($APP_NAME) ?></a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php?<?= e($query_string) ?>">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="orders.php?<?= e($query_string) ?>">Orders</a></li>
                <li class="nav-item"><a class="nav-link active" href="products.php?<?= e($query_string) ?>">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php?<?= e($query_string) ?>">Reports</a></li>
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
    <?php if ($_SESSION['flash']): ?>
        <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['flash']['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php $_SESSION['flash'] = null; ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="brand-title text-dark m-0">Royal Banquet Supplies</h2>
        <button class="btn btn-brown shadow-sm" onclick="openAddModal()">
            <i class="bi bi-plus-circle me-2"></i>Declare New Item
        </button>
    </div>

    <div class="table-registry shadow-lg">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Item</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = sqlsrv_fetch_array($getProducts, SQLSRV_FETCH_ASSOC)): ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= $row['IMAGE_URL'] ? e($row['IMAGE_URL']) : 'assets/images/no-image.png' ?>" class="product-thumb" alt="Product">
                            <span class="fw-bold text-dark"><?= e($row['PRODUCT_NAME']) ?></span>
                        </div>
                    </td>
                    <td><span class="badge bg-outline-secondary border text-secondary text-uppercase" style="font-size: 0.7rem;"><?= e($row['PRODUCT_TYPE']) ?></span></td>
                    <td class="fw-bold text-brown">₱<?= number_format($row['PRODUCT_PRICE'], 2) ?></td>
                    <td>
                        <?php if ($row['IS_ACTIVE']): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3">Available</span>
                        <?php else: ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3">Hidden</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-secondary" onclick='openEditModal(<?= json_encode($row) ?>)'>
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Banish this item forever?');">
                                <input type="hidden" name="delete_product_id" value="<?= $row['PRODUCT_ID'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-brown text-white">
                <h5 class="modal-title brand-title" id="modalTitle">New Declaration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="productForm" method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="product_id" id="formProductId">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Name of Item</label>
                        <input type="text" class="form-control" name="product_name" id="formProductName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Category</label>
                        <select class="form-select" name="product_type" id="formProductType" required>
                            <option value="Drink">Drink</option>
                            <option value="Bread">Bread</option>
                            <option value="Pastry">Pastry</option>
                            <option value="Misc">Misc</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Price</label>
                        <input type="number" step="0.01" class="form-control" name="product_price" id="formProductPrice" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Illustration (Image)</label>
                        <input class="form-control" type="file" name="product_image" accept="image/*">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActiveSwitch" checked>
                        <label class="form-check-label" for="isActiveSwitch">List in Market (Active)</label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-brown px-4">Save Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<footer class="text-center text-muted py-4 small">Crafted for the King, by the King. &mdash; <?= date('Y') ?></footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const modal = new bootstrap.Modal(document.getElementById('productModal'));
    
    function openAddModal() {
        document.getElementById('productForm').reset();
        document.getElementById('formProductId').value = '';
        document.getElementById('modalTitle').innerText = 'New Declaration';
        modal.show();
    }

    function openEditModal(data) {
        document.getElementById('formProductId').value = data.PRODUCT_ID;
        document.getElementById('formProductName').value = data.PRODUCT_NAME;
        document.getElementById('formProductType').value = data.PRODUCT_TYPE;
        document.getElementById('formProductPrice').value = data.PRODUCT_PRICE;
        document.getElementById('isActiveSwitch').checked = data.IS_ACTIVE == 1;
        document.getElementById('modalTitle').innerText = 'Re-declare Item';
        modal.show();
    }

    document.getElementById('logout-btn').addEventListener('click', function() {
        if (confirm('Leave the registry?')) window.location.href = 'login.html';
    });
</script>
</body>
</html>