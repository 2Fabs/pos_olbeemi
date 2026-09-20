<?php

function ensurePengeluaranTable($conn) {
    $query = mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS pengeluaran (
            id INT AUTO_INCREMENT PRIMARY KEY,
            riwayat_masuk_id INT NULL,
            bahan_id INT NULL,
            supplier_id INT NULL,
            jenis VARCHAR(40) NOT NULL,
            nama_bahan VARCHAR(120) NULL,
            nama_supplier VARCHAR(120) NULL,
            nominal DECIMAL(14,2) NOT NULL DEFAULT 0,
            keterangan VARCHAR(255) NULL,
            tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_riwayat_pengeluaran (riwayat_masuk_id),
            KEY index_pengeluaran_tanggal (tanggal),
            KEY index_pengeluaran_jenis (jenis)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (!$query) {
        die('Gagal menyiapkan tabel pengeluaran: ' . mysqli_error($conn));
    }

    $order_column = mysqli_query($conn, "SHOW COLUMNS FROM pengeluaran LIKE 'pesanan_supplier_id'");
    if ($order_column && mysqli_num_rows($order_column) === 0) {
        $alter = mysqli_query($conn, "ALTER TABLE pengeluaran ADD COLUMN pesanan_supplier_id INT NULL AFTER supplier_id, ADD INDEX index_pengeluaran_pesanan (pesanan_supplier_id)");
        if (!$alter) die('Gagal menyiapkan hubungan nota pengeluaran: ' . mysqli_error($conn));
    }

    $order_table = mysqli_query($conn, "SHOW TABLES LIKE 'pesanan_supplier'");
    if ($order_table && mysqli_num_rows($order_table) > 0) {
        mysqli_query($conn, "
            UPDATE pengeluaran e
            JOIN pesanan_supplier p
              ON p.bahan_id = e.bahan_id
             AND p.supplier_id = e.supplier_id
             AND p.tanggal_diterima = e.tanggal
             AND p.status = 'Diterima'
            SET e.pesanan_supplier_id = p.id
            WHERE e.pesanan_supplier_id IS NULL
              AND e.jenis = 'Restock'
        ");
    }
}

function catatPengeluaranPembelian(
    $conn,
    $riwayat_masuk_id,
    $bahan_id,
    $supplier_id,
    $jenis,
    $nama_bahan,
    $nama_supplier,
    $nominal,
    $keterangan,
    $pesanan_supplier_id = 0
) {
    $riwayat_masuk_id = (int) $riwayat_masuk_id;
    $bahan_id = (int) $bahan_id;
    $supplier_id = (int) $supplier_id;
    $jenis_safe = mysqli_real_escape_string($conn, $jenis);
    $nama_bahan_safe = mysqli_real_escape_string($conn, $nama_bahan);
    $nama_supplier_safe = mysqli_real_escape_string($conn, $nama_supplier);
    $nominal = (float) $nominal;
    $keterangan_safe = mysqli_real_escape_string($conn, $keterangan);
    $pesanan_supplier_id = (int) $pesanan_supplier_id;
    $pesanan_supplier_value = $pesanan_supplier_id > 0 ? (string) $pesanan_supplier_id : 'NULL';

    return mysqli_query($conn, "
        INSERT INTO pengeluaran (
            riwayat_masuk_id,
            bahan_id,
            supplier_id,
            pesanan_supplier_id,
            jenis,
            nama_bahan,
            nama_supplier,
            nominal,
            keterangan
        ) VALUES (
            '$riwayat_masuk_id',
            '$bahan_id',
            '$supplier_id',
            $pesanan_supplier_value,
            '$jenis_safe',
            '$nama_bahan_safe',
            '$nama_supplier_safe',
            '$nominal',
            '$keterangan_safe'
        )
    ");
}
