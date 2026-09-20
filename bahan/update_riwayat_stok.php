<?php
include '../config/database.php';
include '../includes/stock_history.php';
include '../includes/stock_units.php';
include '../includes/material_packages.php';

ensureStockInputColumns($conn, 'riwayat_masuk');
ensureMaterialPackageTable($conn);
ensureMaterialPackageChainTable($conn);

auth_require_post_csrf('index.php');

$riwayat_id = (int) $_POST['riwayat_id'];
$jumlah_input = (float) $_POST['jumlah_masuk'];
$satuan_input = strtolower(trim((string) ($_POST['satuan_input'] ?? '')));
$isi_input = (float) ($_POST['isi_per_kemasan'] ?? 0);
$keterangan = trim($_POST['keterangan']);
$redirect_to = $_POST['redirect_to'] ?? '';

if (
    $riwayat_id <= 0 ||
    $jumlah_input < 0 ||
    empty($keterangan)
) {
    header("Location: index.php");
    exit;
}

$data = mysqli_query($conn, "
    SELECT *
    FROM riwayat_masuk
    WHERE id = $riwayat_id
");

$riwayat = mysqli_fetch_assoc($data);

if (!$riwayat) {
    header("Location: index.php");
    exit;
}

$bahan_id = (int) $riwayat['bahan_id'];
$bahan_query = mysqli_query($conn, "SELECT satuan FROM bahan_baku WHERE id = $bahan_id LIMIT 1");
$bahan = $bahan_query ? mysqli_fetch_assoc($bahan_query) : null;
if (!$bahan) die('Bahan baku tidak ditemukan.');

$is_package_input = in_array($satuan_input, purchaseUnitRules()['packages'], true);
$jumlah_baru = $is_package_input && $isi_input > 0
    ? $jumlah_input * $isi_input
    : convertStockInput($jumlah_input, $satuan_input, $bahan['satuan']);
$isi_per_kemasan = $is_package_input && $isi_input > 0 ? $isi_input : 0;
if ($jumlah_baru === false && $is_package_input) {
    $package_query = mysqli_query($conn, "SELECT satuan_kemasan, isi_satuan_dasar FROM bahan_kemasan WHERE bahan_id = $bahan_id AND is_default = 1 ORDER BY id DESC LIMIT 1");
    $package = $package_query ? mysqli_fetch_assoc($package_query) : null;
    $chain_query = mysqli_query($conn, "SELECT * FROM bahan_kemasan_rantai WHERE bahan_id = $bahan_id LIMIT 1");
    $chain = $chain_query ? mysqli_fetch_assoc($chain_query) : null;

    if ($chain && $satuan_input === strtolower($chain['satuan_luar'])) {
        $isi_per_kemasan = (float) $chain['jumlah_satuan_tengah'] * (float) $chain['isi_satuan_dasar'];
    } elseif ($chain && $satuan_input === strtolower($chain['satuan_tengah'])) {
        $isi_per_kemasan = (float) $chain['isi_satuan_dasar'];
    } elseif ($package && $satuan_input === strtolower($package['satuan_kemasan'])) {
        $isi_per_kemasan = (float) $package['isi_satuan_dasar'];
    }
    $jumlah_baru = $isi_per_kemasan > 0 ? $jumlah_input * $isi_per_kemasan : false;
}
if ($jumlah_baru === false || !is_finite($jumlah_baru) || $jumlah_baru < 0) die('Satuan input atau konversi kemasan tidak valid.');

$keterangan = mysqli_real_escape_string($conn, $keterangan);
$satuan_input_safe = mysqli_real_escape_string($conn, $satuan_input);

$tanggal_riwayat = mysqli_real_escape_string($conn, $riwayat['tanggal']);
$pemakaian = mysqli_query($conn, "
    SELECT id
    FROM riwayat_keluar
    WHERE bahan_id = $bahan_id
    AND tanggal >= '$tanggal_riwayat'
    LIMIT 1
");
if ($pemakaian && mysqli_num_rows($pemakaian) > 0) {
    die('Riwayat tidak dapat diedit karena stok sudah digunakan.');
}

mysqli_begin_transaction($conn);

try {
    $update = mysqli_query($conn, "
        UPDATE riwayat_masuk
        SET jumlah_masuk = '$jumlah_baru', jumlah_input = '$jumlah_input', satuan_input = '$satuan_input_safe', isi_per_kemasan = '$isi_per_kemasan', keterangan = '$keterangan'
        WHERE id = $riwayat_id
    ");
    if (!$update) {
        throw new Exception(mysqli_error($conn));
    }

    recalculateMaterialStockHistory($conn, $bahan_id);
    mysqli_commit($conn);
} catch (Exception $exception) {
    mysqli_rollback($conn);
    die($exception->getMessage());
}

if ($redirect_to === 'index') {
    header("Location: index.php");
    exit;
}

header("Location: edit_stok.php?id=$bahan_id");
exit;
?>
