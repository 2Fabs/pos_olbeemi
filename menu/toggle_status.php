<?php
include '../config/database.php';

if (!auth_has_role('owner')) {
    header("Location: index.php");
    exit;
}

auth_require_post_csrf('index.php');

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$query = mysqli_query($conn, "
    SELECT status, harga_jual
    FROM menu
    WHERE id = $id
");

$menu = mysqli_fetch_assoc($query);

if (!$menu) {
    header("Location: index.php");
    exit;
}

$new_status = ($menu['status'] === 'Aktif')
    ? 'Nonaktif'
    : 'Aktif';

if ($new_status === 'Aktif' && (float) $menu['harga_jual'] <= 0) {
    header("Location: index.php");
    exit;
}

$update = mysqli_query($conn, "
    UPDATE menu
    SET status = '$new_status'
    WHERE id = $id
");

if (!$update) {
    die(mysqli_error($conn));
}

header("Location: index.php");
exit;
?>
