<?php
include '../config/database.php';
include '../includes/stock_units.php';

auth_require_post_csrf('index.php');

$id = (int) $_POST['id'];
$nama_bahan = trim($_POST['nama_bahan']);
$minimum_stok = (float) $_POST['minimum_stok'];

if (
    $id <= 0 ||
    empty($nama_bahan) ||
    $minimum_stok < 0
) {
    header("Location: index.php");
    exit;
}

$nama_bahan_escaped = mysqli_real_escape_string($conn, $nama_bahan);
$current_query = mysqli_query($conn, "SELECT satuan FROM bahan_baku WHERE id = $id LIMIT 1");
$current = $current_query ? mysqli_fetch_assoc($current_query) : null;
if (!$current) {
    header("Location: index.php");
    exit;
}

// Mengganti satuan tanpa mengonversi stok akan mengubah arti angka persediaan.
// Karena itu satuan dasar hanya ditetapkan saat bahan dibuat.
$satuan_escaped = mysqli_real_escape_string($conn, $current['satuan']);

$cek = mysqli_query($conn, "
    SELECT id
    FROM bahan_baku
    WHERE nama_bahan = '$nama_bahan_escaped'
    AND id != $id
");

if (mysqli_num_rows($cek) > 0) {
    header("Location: index.php");
    exit;
}

$update = mysqli_query($conn, "
    UPDATE bahan_baku
    SET
        nama_bahan = '$nama_bahan_escaped',
        minimum_stok = '$minimum_stok'
    WHERE id = $id
");

if (!$update) {
    die(mysqli_error($conn));
}

mysqli_query($conn, "
    UPDATE riwayat_masuk
    SET
        nama_bahan = '$nama_bahan_escaped',
        satuan = '$satuan_escaped'
    WHERE bahan_id = $id
");

header("Location: index.php");
exit;
?>
