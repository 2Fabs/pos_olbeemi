<?php
include '../config/database.php';
include '../includes/pesanan_tables.php';
include '../includes/pesanan_transaksi.php';

ensurePesananTables($conn);

auth_require_post_csrf('index.php');

$id = (int) ($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$allowed_status = ['Diproses', 'Selesai', 'Dibatalkan'];

if ($id <= 0 || !in_array($status, $allowed_status)) {
    header('Location: index.php');
    exit;
}

$status_safe = mysqli_real_escape_string($conn, $status);

if ($status === 'Diproses' && !auth_has_role(['owner', 'barista'])) {
    header("Location: detail.php?id=$id&error=" . urlencode('Anda tidak memiliki akses untuk memproses pesanan.'));
    exit;
}

if (in_array($status, ['Selesai', 'Dibatalkan'], true) && !auth_has_role(['owner', 'kasir'])) {
    header("Location: detail.php?id=$id&error=" . urlencode('Penyelesaian pesanan hanya dapat dilakukan oleh Owner atau Kasir.'));
    exit;
}

if ($status === 'Selesai') {
    $metode_pembayaran = trim($_POST['metode_pembayaran'] ?? 'Cash');
    $bayar = $_POST['bayar'] ?? 0;

    try {
        completePesananAsTransaksi($conn, $id, $metode_pembayaran, $bayar);
        header("Location: detail.php?id=$id&success=selesai");
        exit;
    } catch (Exception $e) {
        header("Location: detail.php?id=$id&error=" . urlencode($e->getMessage()));
        exit;
    }
}

$pesanan_check = mysqli_query($conn, "
    SELECT transaksi_id
    FROM pesanan
    WHERE id = $id
");
$pesanan = mysqli_fetch_assoc($pesanan_check);

if ($pesanan && !empty($pesanan['transaksi_id'])) {
    header("Location: detail.php?id=$id&error=" . urlencode('Pesanan sudah masuk laporan penjualan dan tidak bisa diubah lagi.'));
    exit;
}

mysqli_query($conn, "
    UPDATE pesanan
    SET status = '$status_safe'
    WHERE id = $id
    AND status IN ('Menunggu', 'Diproses')
");

header("Location: detail.php?id=$id");
exit;
?>
