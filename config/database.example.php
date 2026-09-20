<?php
date_default_timezone_set('Asia/Jakarta');

// Copy this file to database.php and fill in your local database credentials.
$host = 'localhost';
$user = 'YOUR_DATABASE_USER';
$pass = 'YOUR_DATABASE_PASSWORD';
$db = 'YOUR_DATABASE_NAME';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

mysqli_query($conn, "SET time_zone = '+07:00'");

include_once __DIR__ . '/../includes/auth.php';

auth_ensure_users_table($conn);
auth_require_login();
