<?php

function ensureMaterialPackageTable($conn) {
    $query = mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS bahan_kemasan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bahan_id INT NOT NULL,
            satuan_kemasan VARCHAR(20) NOT NULL,
            isi_satuan_dasar DECIMAL(14,3) NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 1,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_bahan_kemasan (bahan_id, satuan_kemasan),
            INDEX idx_bahan_kemasan_default (bahan_id, is_default)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    if (!$query) die('Gagal menyiapkan data kemasan bahan: ' . mysqli_error($conn));
    $column = mysqli_query($conn, "SHOW COLUMNS FROM bahan_kemasan LIKE 'is_verified'");
    if ($column && mysqli_num_rows($column) === 0 && !mysqli_query($conn, "ALTER TABLE bahan_kemasan ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER is_default")) {
        die('Gagal menyiapkan status verifikasi kemasan: ' . mysqli_error($conn));
    }
}

function ensureMaterialPackageChainTable($conn) {
    $query = mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS bahan_kemasan_rantai (
            bahan_id INT PRIMARY KEY,
            satuan_luar VARCHAR(20) NOT NULL,
            jumlah_satuan_tengah DECIMAL(14,3) NOT NULL,
            satuan_tengah VARCHAR(20) NOT NULL,
            isi_satuan_dasar DECIMAL(14,3) NOT NULL,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    if (!$query) die('Gagal menyiapkan rantai kemasan bahan: ' . mysqli_error($conn));
}

function materialPackageLabel($unit) {
    $labels = ['dus' => 'dus', 'pack' => 'pack', 'botol' => 'botol', 'galon' => 'galon', 'sachet' => 'sachet', 'kaleng' => 'kaleng', 'bag' => 'bag', 'kantong' => 'kantong', 'kotak' => 'kotak'];
    $unit = strtolower(trim((string) $unit));
    return $labels[$unit] ?? $unit;
}

function formatMaterialPackageQuantity($number) {
    return rtrim(rtrim(number_format((float) $number, 3, ',', '.'), '0'), ',');
}

function formatStockPackageBreakdown($stock, $package_content, $package_unit, $base_unit) {
    $stock = (float) $stock;
    $package_content = (float) $package_content;
    if (!is_finite($stock) || !is_finite($package_content) || $stock < 0 || $package_content <= 0) return '-';

    $whole_packages = (int) floor($stock / $package_content);
    $remainder = $stock - ($whole_packages * $package_content);
    // Avoid display noise caused by decimal arithmetic, such as 999.999999 ml.
    if (abs($remainder) < 0.0005) $remainder = 0;

    $parts = [];
    if ($whole_packages > 0) $parts[] = $whole_packages . ' ' . materialPackageLabel($package_unit);
    if ($remainder > 0 || !$parts) {
        $remainder_label = formatMaterialPackageQuantity($remainder) . ' ' . stockInputUnitLabel($base_unit);
        $parts[] = $remainder_label;
    }
    return implode(' + ', $parts);
}

function formatStockPackageChainBreakdown($stock, $middle_per_outer, $base_per_middle, $outer_unit, $middle_unit, $base_unit) {
    $stock = (float) $stock;
    $middle_per_outer = (float) $middle_per_outer;
    $base_per_middle = (float) $base_per_middle;
    if (!is_finite($stock) || !is_finite($middle_per_outer) || !is_finite($base_per_middle) || $stock < 0 || $middle_per_outer <= 0 || $base_per_middle <= 0) return '-';
    $base_per_outer = $middle_per_outer * $base_per_middle;
    $outer = (int) floor($stock / $base_per_outer);
    $remainder = $stock - ($outer * $base_per_outer);
    $middle = (int) floor($remainder / $base_per_middle);
    $base = $remainder - ($middle * $base_per_middle);
    if (abs($base) < 0.0005) $base = 0;
    $parts = [];
    if ($outer > 0) $parts[] = $outer . ' ' . materialPackageLabel($outer_unit);
    if ($middle > 0) $parts[] = $middle . ' ' . materialPackageLabel($middle_unit);
    if ($base > 0 || !$parts) {
        $base_label = formatMaterialPackageQuantity($base) . ' ' . stockInputUnitLabel($base_unit);
        $parts[] = $base_label;
    }
    return implode(' + ', $parts);
}
