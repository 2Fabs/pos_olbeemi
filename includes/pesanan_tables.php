<?php
function ensurePesananColumn($conn, $column, $definition) {
    $column_safe = mysqli_real_escape_string($conn, $column);
    $column_check = mysqli_query($conn, "
        SHOW COLUMNS FROM pesanan LIKE '$column_safe'
    ");

    if (!$column_check) {
        die('Gagal memeriksa kolom pesanan: ' . mysqli_error($conn));
    }

    if (mysqli_num_rows($column_check) === 0) {
        $alter = mysqli_query($conn, "
            ALTER TABLE pesanan
            ADD COLUMN $definition
        ");

        if (!$alter) {
            die('Gagal menambah kolom pesanan: ' . mysqli_error($conn));
        }
    }
}

function ensurePesananTables($conn) {
    $pesanan_table = mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS pesanan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kode_pesanan VARCHAR(30) NOT NULL UNIQUE,
            nama_pelanggan VARCHAR(120) NOT NULL,
            nomor_wa VARCHAR(30) NULL,
            metode_pembayaran ENUM('Cash', 'QRIS') NOT NULL DEFAULT 'Cash',
            sumber_pesanan VARCHAR(40) NOT NULL DEFAULT 'QR Menu',
            catatan TEXT NULL,
            status ENUM('Menunggu', 'Diproses', 'Selesai', 'Dibatalkan') NOT NULL DEFAULT 'Menunggu',
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            bayar DECIMAL(12,2) NOT NULL DEFAULT 0,
            kembalian DECIMAL(12,2) NOT NULL DEFAULT 0,
            transaksi_id INT NULL DEFAULT NULL,
            selesai_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (!$pesanan_table) {
        die('Gagal membuat tabel pesanan: ' . mysqli_error($conn));
    }

    $detail_table = mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS pesanan_detail (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pesanan_id INT NOT NULL,
            menu_id INT NOT NULL,
            nama_menu VARCHAR(160) NOT NULL,
            harga_satuan DECIMAL(12,2) NOT NULL DEFAULT 0,
            qty INT NOT NULL DEFAULT 1,
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (pesanan_id),
            CONSTRAINT fk_pesanan_detail_pesanan
                FOREIGN KEY (pesanan_id)
                REFERENCES pesanan(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (!$detail_table) {
        die('Gagal membuat tabel detail pesanan: ' . mysqli_error($conn));
    }

    ensurePesananColumn($conn, 'transaksi_id', 'transaksi_id INT NULL DEFAULT NULL AFTER subtotal');
    ensurePesananColumn($conn, 'selesai_at', 'selesai_at DATETIME NULL AFTER transaksi_id');
    ensurePesananColumn($conn, 'metode_pembayaran', "metode_pembayaran ENUM('Cash', 'QRIS') NOT NULL DEFAULT 'Cash' AFTER nomor_wa");
    ensurePesananColumn($conn, 'sumber_pesanan', "sumber_pesanan VARCHAR(40) NOT NULL DEFAULT 'QR Menu' AFTER metode_pembayaran");
    ensurePesananColumn($conn, 'bayar', 'bayar DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subtotal');
    ensurePesananColumn($conn, 'kembalian', 'kembalian DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER bayar');
}
?>
