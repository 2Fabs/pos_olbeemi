<?php

function ensureStockInputColumns($conn, $table) {
    $table_safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $columns = [
        'jumlah_input' => 'jumlah_input DECIMAL(12,2) NULL',
        'satuan_input' => 'satuan_input VARCHAR(20) NULL',
        'isi_per_kemasan' => 'isi_per_kemasan DECIMAL(12,2) NULL',
    ];

    foreach ($columns as $name => $definition) {
        $column = mysqli_query($conn, "SHOW COLUMNS FROM `$table_safe` LIKE '$name'");
        if ($column && mysqli_num_rows($column) === 0) {
            $alter = mysqli_query($conn, "ALTER TABLE `$table_safe` ADD COLUMN $definition");
            if (!$alter) {
                die('Gagal menyiapkan data konversi stok: ' . mysqli_error($conn));
            }
        }
    }
}

/*
 * Stok selalu dihitung dalam satuan dasar. Satuan seperti kg dan liter hanya
 * boleh dipakai sebagai satuan input pembelian, bukan sebagai satuan stok.
 */
function stockCanonicalUnit($unit) {
    $unit = strtolower(trim((string) $unit));
    $aliases = [
        'g' => 'g', 'gram' => 'g',
        'ml' => 'ml',
        'pcs' => 'pcs',
    ];
    return $aliases[$unit] ?? '';
}

function stockBaseCategory($unit) {
    $unit = strtolower(trim((string) $unit));
    if (in_array($unit, ['gram', 'g', 'kg'], true)) return 'weight';
    if (in_array($unit, ['ml', 'liter', 'l'], true)) return 'volume';
    if ($unit === 'pcs') return 'count';
    return '';
}

function stockRequestCountExpression($conn) {
    static $expression;
    if ($expression === null) {
        $column = mysqli_query($conn, "SHOW COLUMNS FROM permintaan_stok LIKE 'batch_key'");
        $expression = $column && mysqli_num_rows($column)
            ? "COUNT(DISTINCT CONCAT(user_id, ':', COALESCE(NULLIF(batch_key, ''), CONCAT('single:', id))))"
            : 'COUNT(*)';
    }
    return $expression;
}

function purchaseUnitRules() {
    return [
        'packages' => ['dus', 'pack', 'botol', 'galon', 'sachet', 'kaleng', 'bag', 'kantong', 'kotak'],
        'children' => ['dus' => ['pack', 'botol', 'sachet', 'kaleng', 'bag', 'kantong', 'kotak'], 'pack' => ['botol', 'sachet', 'kaleng', 'bag', 'kantong', 'kotak']],
        'measures' => ['g' => ['base' => 'g', 'factor' => 1, 'label' => 'Gram (g)'], 'kg' => ['base' => 'g', 'factor' => 1000, 'label' => 'Kilogram (kg)'], 'ml' => ['base' => 'ml', 'factor' => 1, 'label' => 'Mililiter (ml)'], 'liter' => ['base' => 'ml', 'factor' => 1000, 'label' => 'Liter (L)'], 'pcs' => ['base' => 'pcs', 'factor' => 1, 'label' => 'Pcs']],
    ];
}

function purchaseBaseUnit($input, $json) {
    $rules = purchaseUnitRules();
    $input = strtolower(trim((string) $input));
    if (in_array($input, $rules['packages'], true)) {
        $levels = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($levels) || !$levels) return '';
        $last = end($levels);
        $input = is_array($last) ? ($last['unit'] ?? '') : '';
    }
    return is_string($input) ? ($rules['measures'][strtolower(trim($input))]['base'] ?? '') : '';
}

function convertStockInput($quantity, $input_unit, $base_unit, $package_content = 0) {
    $quantity = (float) $quantity;
    $input_unit = strtolower(trim((string) $input_unit));
    $base_unit = strtolower(trim((string) $base_unit));
    $package_content = (float) $package_content;
    $packages = purchaseUnitRules()['packages'];

    if (!is_finite($quantity) || !is_finite($package_content) || $quantity <= 0 || stockBaseCategory($base_unit) === '') return false;

    if (in_array($input_unit, $packages, true)) {
        $result = $quantity * $package_content;
        return $package_content > 0 && is_finite($result) ? $result : false;
    }

    $factors = [
        'gram' => ['g' => 1, 'gram' => 1, 'kg' => 1000],
        'g' => ['g' => 1, 'gram' => 1, 'kg' => 1000],
        'kg' => ['g' => 0.001, 'gram' => 0.001, 'kg' => 1],
        'ml' => ['ml' => 1, 'liter' => 1000, 'l' => 1000],
        'liter' => ['ml' => 0.001, 'liter' => 1, 'l' => 1],
        'l' => ['ml' => 0.001, 'liter' => 1, 'l' => 1],
        'pcs' => ['pcs' => 1],
    ];

    $result = isset($factors[$base_unit][$input_unit])
        ? $quantity * $factors[$base_unit][$input_unit]
        : false;
    return $result !== false && is_finite($result) ? $result : false;
}

function purchaseConversionFactor($input, $base, $json) {
    $input = strtolower(trim((string) $input));
    $base = strtolower(trim((string) $base));
    if ($base === 'gram') $base = 'g';
    if (!in_array($base, ['g', 'ml', 'pcs'], true)) return false;
    $rules = purchaseUnitRules();
    $packages = $rules['packages'];
    if (!in_array($input, $packages, true)) return convertStockInput(1, $input, $base);
    $levels = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($levels) || count($levels) < 1 || count($levels) > 2) return false;
    $factor = 1;
    $source = $input;
    foreach ($levels as $index => $level) {
        if (!is_array($level) || !is_numeric($level['quantity'] ?? null)) return false;
        $quantity = (float) $level['quantity'];
        $target = $level['unit'] ?? '';
        if ($target === 'gram') $target = 'g';
        if (!is_finite($quantity) || $quantity <= 0) return false;
        $factor *= $quantity;
        if (!is_finite($factor)) return false;
        if (!is_string($target)) return false;
        $measure = $rules['measures'][$target] ?? null;
        if ($measure) {
            $result = $factor * $measure['factor'];
            return $measure['base'] === $base && $index === count($levels) - 1 && is_finite($result) ? $result : false;
        }
        $allowed = $rules['children'][$source] ?? [];
        if ($index !== 0 || count($levels) !== 2 || !in_array($target, $allowed, true)) return false;
        $source = $target;
    }
    return false;
}

function stockInputUnitLabel($unit) {
    $labels = [
        'g' => 'g', 'gram' => 'g', 'kg' => 'kg', 'ml' => 'ml',
        'liter' => 'L', 'l' => 'L', 'pcs' => 'pcs', 'dus' => 'dus',
        'pack' => 'pack', 'botol' => 'botol', 'galon' => 'galon',
        'sachet' => 'sachet', 'kaleng' => 'kaleng', 'bag' => 'bag', 'kantong' => 'kantong', 'kotak' => 'kotak',
    ];
    $key = strtolower(trim((string) $unit));
    return $labels[$key] ?? $unit;
}
// Show the conversion recorded for this purchase, independent of current stock.
function stockPurchaseConversionLabel($quantity, $input_unit, $total, $base_unit, $package_content = 0) {
    $quantity = (float) $quantity;
    $total = (float) $total;
    $input_unit = trim((string) $input_unit);
    if ($quantity <= 0 || $input_unit === '' || !is_finite($quantity) || !is_finite($total)) return '-';
    $factor = in_array($input_unit, purchaseUnitRules()['packages'], true) && (float) $package_content > 0
        ? (float) $package_content : $total / $quantity;
    if (!is_finite($factor) || $factor <= 0) return '-';
    $number = rtrim(rtrim(number_format($factor, 6, ',', '.'), '0'), ',');
    return '1 ' . stockInputUnitLabel($input_unit) . ' = ' . $number . ' ' . stockInputUnitLabel($base_unit);
}
