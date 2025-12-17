<?php
// Checks if there is a user logged in.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Sets up the session
$_SESSION['flash'] = $_SESSION['flash'] ?? null;

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
$connectionOptions = [
    "Database" => "DLSU", 
    "Uid" => "", 
    "PWD" => ""  
];

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die("Connection Failed: " . print_r(sqlsrv_errors(), true));
}
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}


// Flash Messages

$flash = $_SESSION['flash'];
$_SESSION['flash'] = null; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?= e($APP_NAME) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Caudex:wght@400;700&family=MedievalSharp&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/theme.css"> 
    
    <style>
        .brand-title, .main-heading { font-family: 'Caudex', serif !important; font-weight: 700 !important; }
        .bg-brown { background-color: #5D4037 !important; }
        .text-brown { color: #5D4037 !important; }
        

        .settings-card {
            background-color: rgba(252, 249, 238, 0.95);
            border: none;
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
                <li class="nav-item"><a class="nav-link" href="orders.php?<?= e($query_string) ?>">Orders</a></li>
                <?php if ($USER_ROLE === 'admin'): ?>
                <li class="nav-item"><a class="nav-link" href="products.php?<?= e($query_string) ?>">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php?<?= e($query_string) ?>">Reports</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link active" href="settings.php?<?= e($query_string) ?>">Settings</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="text-light small">Logged in as: <?= e($USER_NAME) ?></span>
                <button class="btn btn-sm btn-outline-light" id="logout-btn">Logout</button>
            </div>
        </div>
    </div>
</nav>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card settings-card shadow">
                <div class="card-header bg-transparent border-0 pt-4 text-center">
                    <h2 class="main-heading text-brown">Personal Scrolls</h2>
                    <p class="text-muted small">Update your secret credentials</p>
                </div>
                <div class="card-body p-4">
                    <form action="update_password.php?<?= e($query_string) ?>" method="post">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">New Password</label>
                            <input type="password" class="form-control" name="new_password" required minlength="8">
                            <div class="form-text mt-2">The new incantation must be at least 8 symbols long.</div>
                        </div>
                        <button class="btn btn-lg btn-brown w-100 shadow-sm" type="submit">Update Ledger</button>
                    </form>
                </div>
            </div>

            <div class="text-center mt-4">
                <p class="text-muted small">Account ID: #<?= e($USER_ID) ?> &bull; Role: <?= e(ucfirst($USER_ROLE)) ?></p>
            </div>
        </div>
    </div>
</main>

<footer class="text-center text-muted py-4 small">
    Crafted for the King, by the King. &mdash; <?= date('Y') ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('logout-btn').addEventListener('click', function() {
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = 'login.html'; 
            }
        });
    });
</script>
</body>
</html>