<?php
include '../config/database.php';
include '../includes/suppliers.php';
include '../includes/pengeluaran.php';

ensureSupplierTables($conn);
ensurePengeluaranTable($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$id = (int) $_POST['id'];
$jumlah = (float) $_POST['jumlah'];
$supplier_id = (int) ($_POST['supplier_id'] ?? 0);
$keterangan_input = trim($_POST['keterangan']);
$total_pembelian = (float) ($_POST['total_pembelian'] ?? 0);
$redirect_to = $_POST['redirect_to'] ?? '';
$pesanan_supplier_id = (int) ($_POST['pesanan_supplier_id'] ?? 0);

if ($id <= 0 || $jumlah <= 0 || $total_pembelian <= 0) {
    header("Location: index.php");
    exit;
}

$data = mysqli_query($conn, "
    SELECT *
    FROM bahan_baku
    WHERE id = $id
");

$bahan = mysqli_fetch_assoc($data);

if (!$bahan) {
    header("Location: index.php");
    exit;
}

$supplier_query = mysqli_query($conn, "
    SELECT supplier.id, supplier.nama_supplier
    FROM supplier_bahan
    JOIN supplier ON supplier.id = supplier_bahan.supplier_id
    WHERE supplier_bahan.bahan_id = $id
    AND supplier.id = $supplier_id
    LIMIT 1
");
$supplier = $supplier_query ? mysqli_fetch_assoc($supplier_query) : null;

if (!$supplier) {
    header("Location: index.php");
    exit;
}

$stok_lama = (float) $bahan['stok'];
$stok_baru = $stok_lama + $jumlah;

mysqli_begin_transaction($conn);

if ($pesanan_supplier_id > 0) {
    $order_query = mysqli_query($conn, "
        SELECT id
        FROM pesanan_supplier
        WHERE id = $pesanan_supplier_id
        AND bahan_id = $id
        AND supplier_id = $supplier_id
        AND status = 'Sudah Dipesan'
        FOR UPDATE
    ");

    if (!$order_query || mysqli_num_rows($order_query) === 0) {
        mysqli_rollback($conn);
        die('Pesanan supplier tidak ditemukan atau sudah diterima.');
    }
}

$update_stok = mysqli_query($conn, "
    UPDATE bahan_baku
    SET stok = '$stok_baru'
    WHERE id = $id
");

if (!$update_stok) {
    mysqli_rollback($conn);
    die(mysqli_error($conn));
}

$nama_bahan = mysqli_real_escape_string($conn, $bahan['nama_bahan']);
$satuan = mysqli_real_escape_string($conn, $bahan['satuan']);
$supplier_nama = $supplier
    ? mysqli_real_escape_string($conn, $supplier['nama_supplier'])
    : '';

$keterangan = !empty($keterangan_input)
    ? mysqli_real_escape_string($conn, $keterangan_input)
    : 'Restock bahan';

$riwayat = mysqli_query($conn, "
    INSERT INTO riwayat_masuk (
        bahan_id,
        supplier_id,
        supplier_nama,
        nama_bahan,
        jumlah_masuk,
        satuan,
        keterangan,
        total_setelah
    ) VALUES (
        '$id',
        " . ($supplier_id > 0 ? "'$supplier_id'" : "NULL") . ",
        " . ($supplier_nama !== '' ? "'$supplier_nama'" : "NULL") . ",
        '$nama_bahan',
        '$jumlah',
        '$satuan',
        '$keterangan',
        '$stok_baru'
    )
");

if (!$riwayat) {
    mysqli_rollback($conn);
    die(mysqli_error($conn));
}

$riwayat_masuk_id = (int) mysqli_insert_id($conn);
$pengeluaran = catatPengeluaranPembelian(
    $conn,
    $riwayat_masuk_id,
    $id,
    $supplier_id,
    'Restock',
    $bahan['nama_bahan'],
    $supplier['nama_supplier'],
    $total_pembelian,
    $keterangan_input !== '' ? $keterangan_input : 'Restock bahan'
);

if (!$pengeluaran) {
    mysqli_rollback($conn);
    die(mysqli_error($conn));
}

if ($pesanan_supplier_id > 0) {
    $update_order = mysqli_query($conn, "
        UPDATE pesanan_supplier
        SET status = 'Diterima', tanggal_diterima = NOW()
        WHERE id = $pesanan_supplier_id
    ");

    if (!$update_order) {
        mysqli_rollback($conn);
        die(mysqli_error($conn));
    }
}

mysqli_commit($conn);

if ($redirect_to === 'pesan_supplier') {
    header('Location: pesan_supplier.php');
    exit;
}

header("Location: index.php");
exit;
?>
