<?php
include '../config/database.php';

auth_require_post_csrf('index.php');

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0 || !auth_has_role('owner')) {
    header("Location: index.php");
    exit;
}

mysqli_query($conn, "DELETE FROM bahan_baku WHERE id = $id");

header("Location: index.php");
exit;
?>
