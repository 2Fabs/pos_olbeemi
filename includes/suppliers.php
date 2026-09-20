<?php

function supplier_escape_identifier($name) {
    return preg_replace('/[^a-zA-Z0-9_]/', '', (string) $name);
}

function supplier_table_exists($conn, $table) {
    $table_safe = supplier_escape_identifier($table);
    $query = mysqli_query($conn, "SHOW TABLES LIKE '$table_safe'");

    return $query && mysqli_num_rows($query) > 0;
}

function supplier_column_exists($conn, $table, $column) {
    $table_safe = supplier_escape_identifier($table);
    $column_safe = supplier_escape_identifier($column);
    $query = mysqli_query($conn, "SHOW COLUMNS FROM `$table_safe` LIKE '$column_safe'");

    return $query && mysqli_num_rows($query) > 0;
}

function ensure_supplier_column($conn, $table, $column, $definition) {
    if (supplier_column_exists($conn, $table, $column)) {
        return;
    }

    $table_safe = supplier_escape_identifier($table);
    $alter = mysqli_query($conn, "ALTER TABLE `$table_safe` ADD COLUMN $definition");

    if (!$alter) {
        die('Gagal menambah kolom supplier: ' . mysqli_error($conn));
    }
}

// Allocate one identity per checkout inside the caller's transaction.
function createSupplierOrderCode($conn, $supplier_id) {
    $supplier_id = (int) $supplier_id;
    if (!mysqli_query($conn, "INSERT INTO pengadaan_pesanan (supplier_id, tanggal) VALUES ($supplier_id, NOW())")) {
        throw new Exception('Kode pesanan gagal dibuat.');
    }
    $id = (int) mysqli_insert_id($conn);
    $query = mysqli_query($conn, "SELECT CONCAT('PB-', DATE_FORMAT(tanggal, '%Y%m%d'), '-', LPAD(id, GREATEST(4, CHAR_LENGTH(id)), '0')) AS kode FROM pengadaan_pesanan WHERE id = $id");
    $row = $query ? mysqli_fetch_assoc($query) : null;
    if (!$row) throw new Exception('Kode pesanan gagal dibaca.');
    return $row['kode'];
}

function ensureSupplierTables($conn) {
    if (!supplier_table_exists($conn, 'supplier')) {
        $create_supplier = mysqli_query($conn, "
            CREATE TABLE supplier (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama_supplier VARCHAR(120) NOT NULL UNIQUE,
                no_telepon VARCHAR(30) NULL,
                alamat TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        if (!$create_supplier) {
            die('Gagal membuat tabel supplier: ' . mysqli_error($conn));
        }
    }

    if (!supplier_table_exists($conn, 'supplier_bahan')) {
        $create_supplier_bahan = mysqli_query($conn, "
            CREATE TABLE supplier_bahan (
                id INT AUTO_INCREMENT PRIMARY KEY,
                supplier_id INT NOT NULL,
                bahan_id INT NOT NULL,
                harga DECIMAL(12,2) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_supplier_bahan (supplier_id, bahan_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        if (!$create_supplier_bahan) {
            die('Gagal membuat tabel supplier_bahan: ' . mysqli_error($conn));
        }
    }

    if (!supplier_table_exists($conn, 'pesanan_supplier')) {
        $create_supplier_order = mysqli_query($conn, "
            CREATE TABLE pesanan_supplier (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bahan_id INT NOT NULL,
                supplier_id INT NOT NULL,
                jumlah DECIMAL(12,2) NOT NULL,
                satuan VARCHAR(20) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'Sudah Dipesan',
                tanggal_pesan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                tanggal_diterima DATETIME NULL,
                INDEX idx_pesanan_supplier_bahan_status (bahan_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        if (!$create_supplier_order) {
            die('Gagal membuat tabel pesanan supplier: ' . mysqli_error($conn));
        }
    }

    if (!mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pengadaan_pesanan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        tanggal DATETIME NOT NULL,
        legacy_key VARCHAR(80) NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) die('Gagal menyiapkan nomor pengadaan.');
    ensure_supplier_column($conn, 'pesanan_supplier', 'kode_pesanan', "kode_pesanan VARCHAR(40) NULL, ADD INDEX idx_kode_pesanan (kode_pesanan)");
    // Historical rows have no checkout identity; preserve the previous supplier/time grouping.
    if (!mysqli_query($conn, "INSERT IGNORE INTO pengadaan_pesanan (supplier_id, tanggal, legacy_key)
        SELECT supplier_id, tanggal_pesan, CONCAT(supplier_id, '|', tanggal_pesan)
        FROM pesanan_supplier WHERE kode_pesanan IS NULL
        GROUP BY supplier_id, tanggal_pesan ORDER BY tanggal_pesan, supplier_id")) die('Gagal memberi nomor pesanan lama.');
    if (!mysqli_query($conn, "UPDATE pesanan_supplier p
        JOIN pengadaan_pesanan g ON g.legacy_key = CONCAT(p.supplier_id, '|', p.tanggal_pesan)
        SET p.kode_pesanan = CONCAT('PB-', DATE_FORMAT(g.tanggal, '%Y%m%d'), '-', LPAD(g.id, GREATEST(4, CHAR_LENGTH(g.id)), '0'))
        WHERE p.kode_pesanan IS NULL")) die('Gagal memperbarui kode pesanan lama.');

    ensure_supplier_column($conn, 'pesanan_supplier', 'permintaan_stok_id', "permintaan_stok_id INT NULL AFTER id");
    ensure_supplier_column($conn, 'pesanan_supplier', 'jumlah_input', "jumlah_input DECIMAL(12,2) NULL AFTER jumlah");
    ensure_supplier_column($conn, 'pesanan_supplier', 'satuan_input', "satuan_input VARCHAR(20) NULL AFTER jumlah_input");
    ensure_supplier_column($conn, 'pesanan_supplier', 'isi_per_kemasan', "isi_per_kemasan DECIMAL(12,2) NULL AFTER satuan_input");
    ensure_supplier_column($conn, 'pesanan_supplier', 'total_pembelian', "total_pembelian DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER satuan");
    ensure_supplier_column($conn, 'pesanan_supplier', 'is_bahan_baru', "is_bahan_baru TINYINT(1) NOT NULL DEFAULT 0");
    ensure_supplier_column($conn, 'pesanan_supplier', 'foto_nota', "foto_nota VARCHAR(255) NULL AFTER total_pembelian");

    ensure_supplier_column(
        $conn,
        'riwayat_masuk',
        'supplier_id',
        "supplier_id INT NULL AFTER bahan_id"
    );

    ensure_supplier_column(
        $conn,
        'riwayat_masuk',
        'supplier_nama',
        "supplier_nama VARCHAR(120) NULL AFTER supplier_id"
    );
}
