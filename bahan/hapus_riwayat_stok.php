<?php
include '../config/database.php';
include '../includes/pengeluaran.php';

ensurePengeluaranTable($conn);

auth_require_post_csrf('index.php');

$history_id = (int) ($_POST['riwayat_id'] ?? 0);
if ($history_id <= 0) {
    header('Location: index.php');
    exit;
}

mysqli_begin_transaction($conn);

try {
    $history_query = mysqli_query($conn, "
        SELECT riwayat_masuk.*, bahan_baku.stok AS stok_sekarang
        FROM riwayat_masuk
        JOIN bahan_baku ON bahan_baku.id = riwayat_masuk.bahan_id
        WHERE riwayat_masuk.id = $history_id
        FOR UPDATE
    ");
    $history = $history_query ? mysqli_fetch_assoc($history_query) : null;

    if (!$history) {
        throw new Exception('Riwayat stok tidak ditemukan.');
    }

    $material_id = (int) $history['bahan_id'];
    $amount = (float) $history['jumlah_masuk'];
    $current_stock = (float) $history['stok_sekarang'];
    $history_date_safe = mysqli_real_escape_string($conn, $history['tanggal']);

    $later_usage_query = mysqli_query($conn, "
        SELECT id
        FROM riwayat_keluar
        WHERE bahan_id = $material_id
        AND tanggal >= '$history_date_safe'
        LIMIT 1
    ");
    if (!$later_usage_query) {
        throw new Exception(mysqli_error($conn));
    }
    if (mysqli_num_rows($later_usage_query) > 0 || $current_stock < $amount) {
        throw new Exception('Riwayat tidak dapat dihapus karena stok setelah penambahan sudah digunakan.');
    }

    $delete_expense = mysqli_query($conn, "
        DELETE FROM pengeluaran
        WHERE riwayat_masuk_id = $history_id
    ");
    if (!$delete_expense) {
        throw new Exception(mysqli_error($conn));
    }

    $delete_history = mysqli_query($conn, "
        DELETE FROM riwayat_masuk
        WHERE id = $history_id
    ");
    if (!$delete_history) {
        throw new Exception(mysqli_error($conn));
    }

    $adjust_later_history = mysqli_query($conn, "
        UPDATE riwayat_masuk
        SET total_setelah = total_setelah - '$amount'
        WHERE bahan_id = $material_id
        AND (
            tanggal > '$history_date_safe'
            OR (tanggal = '$history_date_safe' AND id > $history_id)
        )
    ");
    if (!$adjust_later_history) {
        throw new Exception(mysqli_error($conn));
    }

    $new_stock = $current_stock - $amount;
    $update_stock = mysqli_query($conn, "
        UPDATE bahan_baku
        SET stok = '$new_stock'
        WHERE id = $material_id
    ");
    if (!$update_stock) {
        throw new Exception(mysqli_error($conn));
    }

    mysqli_commit($conn);
    header('Location: index.php');
    exit;
} catch (Exception $exception) {
    mysqli_rollback($conn);
    die($exception->getMessage());
}
?>
