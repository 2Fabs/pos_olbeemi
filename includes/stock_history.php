<?php
function recalculateMaterialStockHistory($conn, $material_id) {
    $material_id = (int) $material_id;
    $events_query = mysqli_query($conn, "
        SELECT id, 'in' AS event_type, jumlah_masuk AS amount, tanggal
        FROM riwayat_masuk
        WHERE bahan_id = $material_id
        UNION ALL
        SELECT id, 'out' AS event_type, jumlah_keluar AS amount, tanggal
        FROM riwayat_keluar
        WHERE bahan_id = $material_id
        ORDER BY tanggal ASC, event_type ASC, id ASC
    ");

    if (!$events_query) {
        throw new Exception(mysqli_error($conn));
    }

    $running_total = 0;
    while ($event = mysqli_fetch_assoc($events_query)) {
        $amount = (float) $event['amount'];
        $running_total += $event['event_type'] === 'in' ? $amount : -$amount;

        if ($running_total < 0) {
            throw new Exception('Riwayat tidak dapat diubah karena stok sudah digunakan pada transaksi.');
        }

        $event_id = (int) $event['id'];
        $table = $event['event_type'] === 'in' ? 'riwayat_masuk' : 'riwayat_keluar';
        $update = mysqli_query($conn, "
            UPDATE $table
            SET total_setelah = '$running_total'
            WHERE id = $event_id
        ");

        if (!$update) {
            throw new Exception(mysqli_error($conn));
        }
    }

    $update_stock = mysqli_query($conn, "
        UPDATE bahan_baku
        SET stok = '$running_total'
        WHERE id = $material_id
    ");

    if (!$update_stock) {
        throw new Exception(mysqli_error($conn));
    }

    return $running_total;
}
?>
