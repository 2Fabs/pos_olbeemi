<?php
include '../config/database.php';
include '../includes/stock_units.php';
include '../includes/material_packages.php';

auth_require_post_csrf('index.php');
if (!auth_has_role('owner')) { header('Location: index.php'); exit; }
ensureMaterialPackageTable($conn);
ensureMaterialPackageChainTable($conn);

$bahan_id = (int) ($_POST['bahan_id'] ?? 0);
$kemasan = strtolower(trim((string) ($_POST['satuan_kemasan'] ?? '')));
$isi = (float) ($_POST['isi_satuan_dasar'] ?? 0);
$verified = !empty($_POST['is_verified']) ? 1 : 0;
$material_query = mysqli_query($conn, "SELECT id FROM bahan_baku WHERE id = $bahan_id LIMIT 1");
if (!$material_query || mysqli_num_rows($material_query) === 0 || !in_array($kemasan, purchaseUnitRules()['packages'], true) || !is_finite($isi) || $isi <= 0) {
    header('Location: index.php?error=' . urlencode('Data kemasan bahan tidak valid.')); exit;
}

$kemasan_safe = mysqli_real_escape_string($conn, $kemasan);
mysqli_begin_transaction($conn);
try {
    if (!mysqli_query($conn, "DELETE FROM bahan_kemasan_rantai WHERE bahan_id = $bahan_id")) throw new Exception(mysqli_error($conn));
    if (!mysqli_query($conn, "UPDATE bahan_kemasan SET is_default = 0 WHERE bahan_id = $bahan_id")) throw new Exception(mysqli_error($conn));
    $save = mysqli_query($conn, "INSERT INTO bahan_kemasan (bahan_id, satuan_kemasan, isi_satuan_dasar, is_default, is_verified) VALUES ($bahan_id, '$kemasan_safe', '$isi', 1, $verified) ON DUPLICATE KEY UPDATE isi_satuan_dasar = VALUES(isi_satuan_dasar), is_default = 1, is_verified = VALUES(is_verified)");
    if (!$save) throw new Exception(mysqli_error($conn));
    mysqli_commit($conn);
    header('Location: index.php?success=' . urlencode('Konversi kemasan bahan berhasil disimpan.'));
} catch (Exception $exception) {
    mysqli_rollback($conn);
    header('Location: index.php?error=' . urlencode('Konversi kemasan gagal disimpan.'));
}
exit;
