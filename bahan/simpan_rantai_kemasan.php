<?php
include '../config/database.php';
include '../includes/stock_units.php';
include '../includes/material_packages.php';
auth_require_post_csrf('index.php');
if (!auth_has_role('owner')) { header('Location: index.php'); exit; }
ensureMaterialPackageTable($conn); ensureMaterialPackageChainTable($conn);
$bahan_id=(int)($_POST['bahan_id']??0); $outer=strtolower(trim((string)($_POST['satuan_luar']??''))); $middle=strtolower(trim((string)($_POST['satuan_tengah']??''))); $per_outer=(float)($_POST['jumlah_satuan_tengah']??0); $per_middle=(float)($_POST['isi_satuan_dasar']??0); $verified=!empty($_POST['is_verified'])?1:0;
$q=mysqli_query($conn,"SELECT satuan FROM bahan_baku WHERE id=$bahan_id LIMIT 1"); $material=$q?mysqli_fetch_assoc($q):null; $packages=purchaseUnitRules()['packages'];
if (!$material || !in_array($outer,$packages,true) || !in_array($middle,$packages,true) || $outer===$middle || !is_finite($per_outer) || !is_finite($per_middle) || $per_outer<=0 || $per_middle<=0) { header('Location: index.php?error='.urlencode('Data rantai kemasan tidak valid.')); exit; }
$outer_safe=mysqli_real_escape_string($conn,$outer); $middle_safe=mysqli_real_escape_string($conn,$middle); $total=$per_outer*$per_middle;
mysqli_begin_transaction($conn);
try {
    $chain=mysqli_query($conn,"INSERT INTO bahan_kemasan_rantai (bahan_id,satuan_luar,jumlah_satuan_tengah,satuan_tengah,isi_satuan_dasar,is_verified) VALUES ($bahan_id,'$outer_safe','$per_outer','$middle_safe','$per_middle',$verified) ON DUPLICATE KEY UPDATE satuan_luar=VALUES(satuan_luar),jumlah_satuan_tengah=VALUES(jumlah_satuan_tengah),satuan_tengah=VALUES(satuan_tengah),isi_satuan_dasar=VALUES(isi_satuan_dasar),is_verified=VALUES(is_verified)");
    if (!$chain) throw new Exception(mysqli_error($conn));
    mysqli_query($conn,"UPDATE bahan_kemasan SET is_default=0 WHERE bahan_id=$bahan_id");
    $direct=mysqli_query($conn,"INSERT INTO bahan_kemasan (bahan_id,satuan_kemasan,isi_satuan_dasar,is_default,is_verified) VALUES ($bahan_id,'$outer_safe','$total',1,$verified) ON DUPLICATE KEY UPDATE isi_satuan_dasar=VALUES(isi_satuan_dasar),is_default=1,is_verified=VALUES(is_verified)");
    if (!$direct) throw new Exception(mysqli_error($conn)); mysqli_commit($conn); header('Location: index.php?success='.urlencode('Rantai kemasan berhasil disimpan.'));
} catch(Exception $e) { mysqli_rollback($conn); header('Location: index.php?error='.urlencode('Rantai kemasan gagal disimpan.')); }
exit;
