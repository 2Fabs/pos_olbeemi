<?php
include '../config/database.php';

auth_require_post_csrf('index.php');

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0 || !auth_has_role('owner')) {
    header("Location: index.php");
    exit;
}

$query = mysqli_query($conn, "
    SELECT foto
    FROM menu
    WHERE id = $id
");

$menu = mysqli_fetch_assoc($query);

if (!$menu) {
    header("Location: index.php");
    exit;
}

$upload_dir = __DIR__ . '/uploads/';

if (
    !empty($menu['foto']) &&
    file_exists($upload_dir . $menu['foto'])
) {
    unlink($upload_dir . $menu['foto']);
}

$delete = mysqli_query($conn, "
    DELETE FROM menu
    WHERE id = $id
");

if (!$delete) {
    die(mysqli_error($conn));
}

header("Location: index.php");
exit;
?>
