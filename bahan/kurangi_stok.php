<?php
include '../config/database.php';

// Pengurangan stok langsung sudah tidak digunakan. Semua penyesuaian harus
// diajukan oleh barista melalui permintaan_stok.php untuk persetujuan owner.
header('Location: permintaan_stok.php');
exit;

auth_require_post_csrf('index.php');

$bahan_id = (int) ($_POST['id'] ?? 0);
$jumlah = (float) ($_POST['jumlah'] ?? 0);
$keterangan = trim($_POST['keterangan'] ?? '');
$redirect_to = $_POST['redirect_to'] ?? '';
$keterangan_valid = ['Stok Basi', 'Stok Rusak', 'Penyesuaian Stok'];

if ($bahan_id <= 0 || $jumlah <= 0 || !in_array($keterangan, $keterangan_valid, true)) {
    header('Location: index.php');
    exit;
}

mysqli_begin_transaction($conn);

try {
    $bahan_query = mysqli_query($conn, "
        SELECT id, nama_bahan, stok, satuan
        FROM bahan_baku
        WHERE id = $bahan_id
        FOR UPDATE
    ");
    $bahan = $bahan_query ? mysqli_fetch_assoc($bahan_query) : null;

    if (!$bahan) {
        throw new Exception('Bahan tidak ditemukan.');
    }

    $stok_sekarang = (float) $bahan['stok'];
    if ($jumlah > $stok_sekarang) {
        throw new Exception('Jumlah stok keluar melebihi stok yang tersedia.');
    }

    $stok_baru = $stok_sekarang - $jumlah;
    $nama_bahan = mysqli_real_escape_string($conn, $bahan['nama_bahan']);
    $satuan = mysqli_real_escape_string($conn, $bahan['satuan']);
    $keterangan_safe = mysqli_real_escape_string($conn, $keterangan);

    $update_stok = mysqli_query($conn, "
        UPDATE bahan_baku
        SET stok = '$stok_baru'
        WHERE id = $bahan_id
    ");
    if (!$update_stok) {
        throw new Exception(mysqli_error($conn));
    }

    $insert_riwayat = mysqli_query($conn, "
        INSERT INTO riwayat_keluar (
            bahan_id,
            nama_bahan,
            jumlah_keluar,
            satuan,
            total_setelah,
            keterangan
        ) VALUES (
            $bahan_id,
            '$nama_bahan',
            '$jumlah',
            '$satuan',
            '$stok_baru',
            '$keterangan_safe'
        )
    ");
    if (!$insert_riwayat) {
        throw new Exception(mysqli_error($conn));
    }

    mysqli_commit($conn);
} catch (Exception $exception) {
    mysqli_rollback($conn);
    die($exception->getMessage());
}

if ($redirect_to === 'penyesuaian_stok') {
    header('Location: penyesuaian_stok.php');
    exit;
}

header('Location: riwayat_keluar.php');
exit;
?>
