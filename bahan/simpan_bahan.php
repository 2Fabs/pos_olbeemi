<?php
include '../config/database.php';
include '../includes/suppliers.php';
include '../includes/pengeluaran.php';
include '../includes/stock_units.php';

ensureSupplierTables($conn);
ensurePengeluaranTable($conn);
ensureStockInputColumns($conn, 'riwayat_masuk');

auth_require_post_csrf('index.php');

$names = $_POST['nama_bahan'] ?? [];
$stocks = $_POST['stok'] ?? [];
$units = $_POST['satuan'] ?? [];
$minimums = $_POST['minimum_stok'] ?? [];
$supplier_prices = $_POST['harga_supplier'] ?? [];
$purchase_totals = $_POST['total_pembelian'] ?? [];
$supplier_id_input = $_POST['supplier_id'] ?? '';
$supplier_name = trim($_POST['nama_supplier'] ?? '');
$supplier_phone = trim($_POST['no_telepon_supplier'] ?? '');
$supplier_address = trim($_POST['alamat_supplier'] ?? '');

if (!is_array($names) || count($names) === 0) {
    header('Location: index.php');
    exit;
}

$row_count = count($names);
$same_length = count($stocks) === $row_count
    && count($units) === $row_count
    && count($minimums) === $row_count
    && count($supplier_prices) === $row_count
    && count($purchase_totals) === $row_count;

if (!$same_length || $supplier_id_input === '') {
    header('Location: index.php');
    exit;
}

$is_new_supplier = $supplier_id_input === 'new';
if ($is_new_supplier && ($supplier_name === '' || $supplier_phone === '' || $supplier_address === '')) {
    header('Location: index.php');
    exit;
}

$materials = [];
$normalized_names = [];

foreach ($names as $index => $name_input) {
    $name = trim((string) $name_input);
    $stock = (float) $stocks[$index];
    $unit = trim((string) $units[$index]);
    $input_stock = $stock;
    $input_unit = trim((string) ($_POST['satuan_pembelian'][$index] ?? $unit));
    $unit = purchaseBaseUnit($input_unit, $_POST['konversi'][$index] ?? '');
    $factor = purchaseConversionFactor($input_unit, $unit, $_POST['konversi'][$index] ?? '');
    $package_content = in_array($input_unit, purchaseUnitRules()['packages'], true) ? (float) $factor : 0;
    $stock = convertStockInput($input_stock, $input_unit, $unit, $package_content);
    $minimum = (float) $minimums[$index];
    $supplier_price = (float) $supplier_prices[$index];
    $purchase_total = round($input_stock * $supplier_price, 2);
    $normalized_name = strtolower($name);

    if (
        $name === '' || $factor === false || $stock === false || !is_finite($stock) || $stock <= 0 || !is_finite($minimum) || $minimum < 0
        || !is_finite($supplier_price) || $supplier_price < 0 || !is_finite($purchase_total) || $purchase_total < 0
        || in_array($normalized_name, $normalized_names, true)
    ) {
        header('Location: index.php');
        exit;
    }

    $normalized_names[] = $normalized_name;
    $materials[] = compact('name', 'stock', 'unit', 'minimum', 'supplier_price', 'purchase_total', 'input_stock', 'input_unit', 'package_content');
}

mysqli_begin_transaction($conn);

try {
    foreach ($materials as $material) {
        $name_safe = mysqli_real_escape_string($conn, $material['name']);
        $duplicate = mysqli_query($conn, "SELECT id FROM bahan_baku WHERE LOWER(nama_bahan) = LOWER('$name_safe') LIMIT 1");
        if (!$duplicate || mysqli_num_rows($duplicate) > 0) {
            throw new Exception('Nama bahan sudah tersedia atau gagal diperiksa.');
        }
    }

    if (!$is_new_supplier) {
        $supplier_id = (int) $supplier_id_input;
        $supplier_query = mysqli_query($conn, "SELECT id, nama_supplier FROM supplier WHERE id = $supplier_id LIMIT 1");
        $supplier = $supplier_query ? mysqli_fetch_assoc($supplier_query) : null;
        if (!$supplier) {
            throw new Exception('Supplier tidak ditemukan.');
        }
        $supplier_id = (int) $supplier['id'];
        $supplier_name_raw = $supplier['nama_supplier'];
        $supplier_name_for_history = mysqli_real_escape_string($conn, $supplier_name_raw);
    } else {
        $supplier_name_safe = mysqli_real_escape_string($conn, $supplier_name);
        $supplier_phone_safe = mysqli_real_escape_string($conn, $supplier_phone);
        $supplier_address_safe = mysqli_real_escape_string($conn, $supplier_address);
        $supplier_duplicate = mysqli_query($conn, "SELECT id FROM supplier WHERE nama_supplier = '$supplier_name_safe' LIMIT 1");
        if ($supplier_duplicate && mysqli_num_rows($supplier_duplicate) > 0) {
            throw new Exception('Supplier sudah tersedia. Pilih supplier tersebut dari daftar.');
        }
        $supplier_insert = mysqli_query($conn, "
            INSERT INTO supplier (nama_supplier, no_telepon, alamat)
            VALUES ('$supplier_name_safe', '$supplier_phone_safe', '$supplier_address_safe')
        ");
        if (!$supplier_insert) {
            throw new Exception(mysqli_error($conn));
        }
        $supplier_id = (int) mysqli_insert_id($conn);
        $supplier_name_raw = $supplier_name;
        $supplier_name_for_history = $supplier_name_safe;
    }

    foreach ($materials as $material) {
        $name_safe = mysqli_real_escape_string($conn, $material['name']);
        $unit_safe = mysqli_real_escape_string($conn, $material['unit']);
        $stock = $material['stock'];
        $minimum = $material['minimum'];
        $supplier_price = $material['supplier_price'];
        $input_stock = $material['input_stock'];
        $input_unit_safe = mysqli_real_escape_string($conn, $material['input_unit']);
        $package_content = $material['package_content'];

        $insert_material = mysqli_query($conn, "
            INSERT INTO bahan_baku (nama_bahan, stok, satuan, minimum_stok)
            VALUES ('$name_safe', '$stock', '$unit_safe', '$minimum')
        ");
        if (!$insert_material) {
            throw new Exception(mysqli_error($conn));
        }
        $material_id = (int) mysqli_insert_id($conn);

        $link = mysqli_query($conn, "
            INSERT INTO supplier_bahan (supplier_id, bahan_id, harga)
            VALUES ($supplier_id, $material_id, '$supplier_price')
        ");
        if (!$link) {
            throw new Exception(mysqli_error($conn));
        }

        $history = mysqli_query($conn, "
            INSERT INTO riwayat_masuk (
                bahan_id, supplier_id, supplier_nama, nama_bahan,
                jumlah_masuk, satuan, keterangan, total_setelah,
                jumlah_input, satuan_input, isi_per_kemasan
            ) VALUES (
                $material_id, $supplier_id, '$supplier_name_for_history', '$name_safe',
                '$stock', '$unit_safe', 'Stok Awal', '$stock',
                '$input_stock', '$input_unit_safe', '$package_content'
            )
        ");
        if (!$history) {
            throw new Exception(mysqli_error($conn));
        }

        $history_id = (int) mysqli_insert_id($conn);
        $expense = catatPengeluaranPembelian(
            $conn,
            $history_id,
            $material_id,
            $supplier_id,
            'Stok Awal',
            $material['name'],
            $supplier_name_raw,
            $material['purchase_total'],
            'Pembelian stok awal'
        );
        if (!$expense) {
            throw new Exception(mysqli_error($conn));
        }

    }

    mysqli_commit($conn);
    header('Location: index.php');
    exit;
} catch (Exception $exception) {
    mysqli_rollback($conn);
    die($exception->getMessage());
}
?>
