<?php
// 1. DATABASE CONNECTION
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
// 2. GET POST DATA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email']; 
    $password = $_POST['password'];
} else {
    $email = ''; 
    $password = '';
}
// 3. SQL QUERY
$sql = "SELECT ACCOUNT_ID, FULLNAME, EMAIL, ROLE FROM dbo.USER_ACCOUNTS 
        WHERE EMAIL = N'$email' AND PASSWORD = N'$password'";
$result = sqlsrv_query($conn, $sql);

if ($result === false) {
    die("Query Error: " . print_r(sqlsrv_errors(), true));
}

// 4. CHECK RESULTS AND FETCH
if ($row = sqlsrv_fetch_array($result)) {
    
    $ACCOUNT_ID = $row['ACCOUNT_ID'];
    $FULLNAME = $row['FULLNAME'];
    $ROLE = $row['ROLE'];
    
    // ROLE-BASED REDIRECT 
    if ($ROLE === 'cashier') {
        header('Location: orders.php?' . 'user_id=' . $ACCOUNT_ID . '&user_name=' . $FULLNAME . '&user_role=' . $ROLE); 
        exit;
    } else if ($ROLE === 'admin') {
        header('Location: dashboard.php?' . 'user_id=' . $ACCOUNT_ID . '&user_name=' . $FULLNAME . '&user_role=' . $ROLE);
        exit;
    } else {
        header('Location: failed_login.html'); 
        exit;
    }
    
} else {
    // FAILED LOGIN
    header('Location: failed_login.html'); 
    exit;
}

sqlsrv_close($conn);
?>