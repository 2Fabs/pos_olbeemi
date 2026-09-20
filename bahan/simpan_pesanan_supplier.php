<?php
include '../config/database.php';
include '../includes/suppliers.php';
include '../includes/stock_units.php';
include '../includes/material_packages.php';

ensureSupplierTables($conn);
ensureMaterialPackageTable($conn);
ensureMaterialPackageChainTable($conn);

function save_new_material_package_conversion($conn, $material_id, $input_unit, $base_unit, $conversion_json) {
    $input_unit = strtolower(trim((string) $input_unit));
    if (!in_array($input_unit, purchaseUnitRules()['packages'], true)) return;

    $levels = is_string($conversion_json) ? json_decode($conversion_json, true) : null;
    $factor = purchaseConversionFactor($input_unit, $base_unit, $conversion_json);
    if (!is_array($levels) || !in_array(count($levels), [1, 2], true) || $factor === false) {
        throw new Exception('Konversi kemasan bahan baru tidak valid.');
    }

    $material_id = (int) $material_id;
    $outer_safe = mysqli_real_escape_string($conn, $input_unit);
    if (!mysqli_query($conn, "UPDATE bahan_kemasan SET is_default = 0 WHERE bahan_id = $material_id")) throw new Exception(mysqli_error($conn));

    if (count($levels) === 1) {
        if (!mysqli_query($conn, "DELETE FROM bahan_kemasan_rantai WHERE bahan_id = $material_id")) throw new Exception(mysqli_error($conn));
        if (!mysqli_query($conn, "INSERT INTO bahan_kemasan (bahan_id, satuan_kemasan, isi_satuan_dasar, is_default, is_verified) VALUES ($material_id, '$outer_safe', '$factor', 1, 0) ON DUPLICATE KEY UPDATE isi_satuan_dasar = VALUES(isi_satuan_dasar), is_default = 1, is_verified = 0")) throw new Exception(mysqli_error($conn));
        return;
    }

    $middle = strtolower(trim((string) ($levels[0]['unit'] ?? '')));
    $per_outer = (float) ($levels[0]['quantity'] ?? 0);
    $per_middle = (float) ($levels[1]['quantity'] ?? 0);
    if (!in_array($middle, purchaseUnitRules()['packages'], true) || $middle === $input_unit || !is_finite($per_outer) || !is_finite($per_middle) || $per_outer <= 0 || $per_middle <= 0) {
        throw new Exception('Konversi kemasan bertingkat bahan baru tidak valid.');
    }
    $middle_safe = mysqli_real_escape_string($conn, $middle);
    if (!mysqli_query($conn, "INSERT INTO bahan_kemasan_rantai (bahan_id, satuan_luar, jumlah_satuan_tengah, satuan_tengah, isi_satuan_dasar, is_verified) VALUES ($material_id, '$outer_safe', '$per_outer', '$middle_safe', '$per_middle', 0) ON DUPLICATE KEY UPDATE satuan_luar = VALUES(satuan_luar), jumlah_satuan_tengah = VALUES(jumlah_satuan_tengah), satuan_tengah = VALUES(satuan_tengah), isi_satuan_dasar = VALUES(isi_satuan_dasar), is_verified = 0")) throw new Exception(mysqli_error($conn));
    if (!mysqli_query($conn, "INSERT INTO bahan_kemasan (bahan_id, satuan_kemasan, isi_satuan_dasar, is_default, is_verified) VALUES ($material_id, '$outer_safe', '$factor', 1, 0) ON DUPLICATE KEY UPDATE isi_satuan_dasar = VALUES(isi_satuan_dasar), is_default = 1, is_verified = 0")) throw new Exception(mysqli_error($conn));
}

function create_bulk_new_material($conn, $supplier_id, $name, $base_unit, $minimum_stock) {
    $name = trim((string) $name);
    $base_unit = strtolower(trim((string) $base_unit));
    $minimum_stock = (float) $minimum_stock;

    if ($name === '' || !in_array($base_unit, ['g', 'ml', 'pcs'], true) || !is_finite($minimum_stock) || $minimum_stock < 0) {
        throw new Exception('Data bahan baru tidak lengkap.');
    }

    $name_safe = mysqli_real_escape_string($conn, $name);
    $duplicate = mysqli_query($conn, "SELECT id FROM bahan_baku WHERE LOWER(nama_bahan) = LOWER('$name_safe') LIMIT 1 FOR UPDATE");
    if (!$duplicate) throw new Exception(mysqli_error($conn));
    if (mysqli_num_rows($duplicate) > 0) throw new Exception("Bahan \"$name\" sudah ada, pilih dari daftar bahan yang sudah ada.");

    $base_unit_safe = mysqli_real_escape_string($conn, $base_unit);
    $insert_material = mysqli_query($conn, "
        INSERT INTO bahan_baku (nama_bahan, stok, satuan, minimum_stok)
        VALUES ('$name_safe', 0, '$base_unit_safe', '$minimum_stock')
    ");
    if (!$insert_material) throw new Exception(mysqli_error($conn));

    $material_id = (int) mysqli_insert_id($conn);
    $insert_link = mysqli_query($conn, "
        INSERT INTO supplier_bahan (supplier_id, bahan_id, harga) VALUES ($supplier_id, $material_id, 0)
    ");
    if (!$insert_link) throw new Exception(mysqli_error($conn));

    return $material_id;
}

auth_require_post_csrf('pesan_supplier.php');

$action = $_POST['action'] ?? 'create_order';
if ($action === 'upload_note') {
    $upload_redirect = ($_POST['source'] ?? '') === 'pengeluaran' ? 'pengeluaran.php' : 'pesan_supplier.php?tahap=selesai';
    if (!auth_has_role('owner')) {
        header('Location: ' . $upload_redirect . (strpos($upload_redirect, '?') === false ? '?' : '&') . 'error=' . urlencode('Anda tidak memiliki akses untuk mengelola nota.'));
        exit;
    }
    $order_id = (int) ($_POST['order_id'] ?? 0);
    $nota = $_FILES['foto_nota'] ?? null;
    if ($order_id <= 0 || !$nota || (int) ($nota['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        header('Location: ' . $upload_redirect . (strpos($upload_redirect, '?') === false ? '?' : '&') . 'error=' . urlencode('Foto nota wajib dipilih.'));
        exit;
    }
    if ((int) $nota['size'] > 5 * 1024 * 1024) {
        header('Location: ' . $upload_redirect . (strpos($upload_redirect, '?') === false ? '?' : '&') . 'error=' . urlencode('Ukuran foto nota maksimal 5 MB.'));
        exit;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($nota['tmp_name']);
    $allowed_notes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($allowed_notes[$mime])) {
        header('Location: ' . $upload_redirect . (strpos($upload_redirect, '?') === false ? '?' : '&') . 'error=' . urlencode('Foto nota harus berformat JPG, JPEG, atau PNG.'));
        exit;
    }

    $order_query = mysqli_query($conn, "SELECT foto_nota FROM pesanan_supplier WHERE id = $order_id AND status = 'Diterima' LIMIT 1");
    $order = $order_query ? mysqli_fetch_assoc($order_query) : null;
    if (!$order) {
        header('Location: ' . $upload_redirect . (strpos($upload_redirect, '?') === false ? '?' : '&') . 'error=' . urlencode('Pesanan selesai tidak ditemukan.'));
        exit;
    }

    $note_directory = __DIR__ . '/uploads/notas';
    if (!is_dir($note_directory) && !mkdir($note_directory, 0755, true)) {
        header('Location: ' . $upload_redirect . (strpos($upload_redirect, '?') === false ? '?' : '&') . 'error=' . urlencode('Folder foto nota tidak dapat dibuat.'));
        exit;
    }
    $note_filename = 'nota-' . $order_id . '-' . bin2hex(random_bytes(8)) . '.' . $allowed_notes[$mime];
    $note_path = $note_directory . '/' . $note_filename;
    if (!move_uploaded_file($nota['tmp_name'], $note_path)) {
        header('Location: ' . $upload_redirect . (strpos($upload_redirect, '?') === false ? '?' : '&') . 'error=' . urlencode('Foto nota gagal disimpan.'));
        exit;
    }

    $note_safe = mysqli_real_escape_string($conn, $note_filename);
    $update = mysqli_query($conn, "UPDATE pesanan_supplier SET foto_nota = '$note_safe' WHERE id = $order_id AND status = 'Diterima'");
    if (!$update) {
        unlink($note_path);
        header('Location: ' . $upload_redirect . (strpos($upload_redirect, '?') === false ? '?' : '&') . 'error=' . urlencode('Foto nota gagal dicatat.'));
        exit;
    }

    $old_note = basename((string) ($order['foto_nota'] ?? ''));
    if ($old_note !== '' && $old_note !== $note_filename) {
        $old_path = $note_directory . '/' . $old_note;
        if (is_file($old_path)) unlink($old_path);
    }
    header('Location: ' . $upload_redirect . (strpos($upload_redirect, '?') === false ? '?' : '&') . 'success=' . urlencode('Foto nota berhasil disimpan sebagai arsip.'));
    exit;
}

if ($action === 'batch_order') {
    $supplier_id = (int) ($_POST['supplier_id'] ?? 0);
    $material_ids = array_map('intval', (array) ($_POST['bahan_id'] ?? []));
    $amounts = (array) ($_POST['jumlah'] ?? []);
    $input_units = (array) ($_POST['satuan_input'] ?? []);
    $package_contents = (array) ($_POST['isi_per_kemasan'] ?? []);
    $totals = (array) ($_POST['total_pembelian'] ?? []);
    $unit_prices = (array) ($_POST['harga_satuan'] ?? []);
    $conversion_levels = (array) ($_POST['konversi'] ?? []);
    $new_flags = (array) ($_POST['is_bahan_baru'] ?? []);
    $new_names = (array) ($_POST['nama_bahan_baru'] ?? []);
    $new_units = (array) ($_POST['satuan_baru'] ?? []);
    $new_minimums = (array) ($_POST['minimum_stok_baru'] ?? []);
    $item_count = count($material_ids);
    $existing_ids = array_filter($material_ids, function ($id) { return $id > 0; });

    $count_issues = [];
    if ($supplier_id <= 0) $count_issues[] = 'supplier belum dipilih';
    if ($item_count < 1) $count_issues[] = 'pilih minimal satu bahan';
    if ($item_count > 20) $count_issues[] = 'jumlah bahan lebih dari 20';
    if (count(array_unique($existing_ids)) !== count($existing_ids)) $count_issues[] = 'ada bahan yang sama dipilih dua kali';
    if (count($amounts) !== $item_count) $count_issues[] = 'jumlah (jumlah[]) tidak sesuai: ' . count($amounts) . '/' . $item_count;
    if (count($input_units) !== $item_count) $count_issues[] = 'satuan_input tidak sesuai: ' . count($input_units) . '/' . $item_count;
    if (count($package_contents) !== $item_count) $count_issues[] = 'isi_per_kemasan tidak sesuai: ' . count($package_contents) . '/' . $item_count;
    if (count($unit_prices) !== $item_count) $count_issues[] = 'harga_satuan tidak sesuai: ' . count($unit_prices) . '/' . $item_count;
    if (count($conversion_levels) !== $item_count) $count_issues[] = 'konversi tidak sesuai: ' . count($conversion_levels) . '/' . $item_count;
    if (count($new_flags) !== $item_count) $count_issues[] = 'is_bahan_baru tidak sesuai: ' . count($new_flags) . '/' . $item_count;
    if (count($new_names) !== $item_count) $count_issues[] = 'nama_bahan_baru tidak sesuai: ' . count($new_names) . '/' . $item_count;
    if (count($new_units) !== $item_count) $count_issues[] = 'satuan_baru tidak sesuai: ' . count($new_units) . '/' . $item_count;
    if (count($new_minimums) !== $item_count) $count_issues[] = 'minimum_stok_baru tidak sesuai: ' . count($new_minimums) . '/' . $item_count;

    if ($count_issues) {
        header('Location: pesan_supplier.php?tahap=siap&error=' . urlencode('Data pesanan beberapa bahan tidak lengkap (' . implode(', ', $count_issues) . ').'));
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        $order_code = createSupplierOrderCode($conn, $supplier_id);
        foreach ($material_ids as $index => $material_id) {
            $is_new = (int) $new_flags[$index] === 1;
            $amount = (float) $amounts[$index];
            $input_unit = strtolower(trim((string) $input_units[$index]));
            $package_content = (float) $package_contents[$index];
            $price = (float) $unit_prices[$index];
            $total = round($amount * $price, 2);
            if ((!$is_new && $material_id <= 0) || !is_finite($amount) || $amount <= 0 || !is_finite($price) || $price < 0 || !is_finite($total)) {
                throw new Exception('Jumlah dan harga setiap bahan wajib diisi.');
            }

            if ($is_new) {
                $material_id = create_bulk_new_material($conn, $supplier_id, $new_names[$index], $new_units[$index], $new_minimums[$index]);
                $material = ['satuan' => strtolower(trim((string) $new_units[$index]))];
            } else {
                $material_query = mysqli_query($conn, "
                    SELECT b.satuan
                    FROM supplier_bahan sb
                    JOIN bahan_baku b ON b.id = sb.bahan_id
                    WHERE sb.supplier_id = $supplier_id AND sb.bahan_id = $material_id
                    LIMIT 1 FOR UPDATE
                ");
                $material = $material_query ? mysqli_fetch_assoc($material_query) : null;
                if (!$material) {
                    throw new Exception('Supplier tidak menyediakan semua bahan yang dipilih.');
                }
            }

            $active_query = mysqli_query($conn, "
                SELECT id FROM pesanan_supplier
                WHERE bahan_id = $material_id
                  AND status IN ('Sudah Dipesan', 'Menunggu Persetujuan')
                LIMIT 1 FOR UPDATE
            ");
            if (!$active_query || mysqli_num_rows($active_query) > 0) {
                throw new Exception('Salah satu bahan sudah memiliki pesanan aktif.');
            }

            $factor = purchaseConversionFactor($input_unit, $material['satuan'], $conversion_levels[$index]);
            $package_content = in_array($input_unit, purchaseUnitRules()['packages'], true) ? (float) $factor : 0;
            $converted = $factor === false ? false : convertStockInput($amount, $input_unit, $material['satuan'], $package_content);
            if ($converted === false) {
                throw new Exception('Satuan pembelian tidak sesuai dengan satuan stok bahan.');
            }
            if ($is_new) {
                save_new_material_package_conversion($conn, $material_id, $input_unit, $material['satuan'], $conversion_levels[$index]);
            }
            $base_unit_safe = mysqli_real_escape_string($conn, $material['satuan']);
            $input_unit_safe = mysqli_real_escape_string($conn, $input_unit);
            $insert = mysqli_query($conn, "
                INSERT INTO pesanan_supplier (
                    kode_pesanan, permintaan_stok_id, bahan_id, supplier_id, jumlah, jumlah_input,
                    satuan_input, isi_per_kemasan, satuan, total_pembelian, status
                ) VALUES (
                    '$order_code', NULL, $material_id, $supplier_id, '$converted', '$amount',
                    '$input_unit_safe', '$package_content', '$base_unit_safe', '$total', 'Sudah Dipesan'
                )
            ");
            if (!$insert) throw new Exception(mysqli_error($conn));
            if ($is_new) {
                $new_order_id = (int) mysqli_insert_id($conn);
                if (!mysqli_query($conn, "UPDATE pesanan_supplier SET is_bahan_baru = 1 WHERE id = $new_order_id")) throw new Exception(mysqli_error($conn));
            }
        }
        mysqli_commit($conn);
        header('Location: pesan_supplier.php?tahap=dipesan&success=' . urlencode($item_count . ' bahan berhasil dipesan.'));
        exit;
    } catch (Exception $exception) {
        mysqli_rollback($conn);
        header('Location: pesan_supplier.php?tahap=siap&error=' . urlencode($exception->getMessage()));
        exit;
    }
}

$bahan_id = (int) ($_POST['bahan_id'] ?? 0);
$supplier_id = (int) ($_POST['supplier_id'] ?? 0);
$request_id = (int) ($_POST['request_id'] ?? 0);
$jumlah_input = (float) ($_POST['jumlah'] ?? 0);
$satuan_input = strtolower(trim($_POST['satuan_input'] ?? ''));
$isi_per_kemasan = (float) ($_POST['isi_per_kemasan'] ?? 0);
$harga_satuan = (float) ($_POST['harga_satuan'] ?? -1);
$total_pembelian = round($jumlah_input * $harga_satuan, 2);

if ($bahan_id <= 0 || $supplier_id <= 0 || !is_finite($jumlah_input) || $jumlah_input <= 0 || !is_finite($harga_satuan) || $harga_satuan < 0 || !is_finite($total_pembelian)) {
    die('Data pemesanan supplier tidak lengkap.');
}

$data_query = mysqli_query($conn, "
    SELECT
        bahan_baku.nama_bahan,
        bahan_baku.satuan,
        supplier.nama_supplier
    FROM supplier_bahan
    JOIN bahan_baku ON bahan_baku.id = supplier_bahan.bahan_id
    JOIN supplier ON supplier.id = supplier_bahan.supplier_id
    WHERE supplier_bahan.bahan_id = $bahan_id
    AND supplier_bahan.supplier_id = $supplier_id
    LIMIT 1
");
$data = $data_query ? mysqli_fetch_assoc($data_query) : null;

if (!$data) {
    die('Supplier tidak terhubung dengan bahan yang dipilih.');
}

$factor = purchaseConversionFactor($satuan_input, $data['satuan'], $_POST['konversi'] ?? '');
$isi_per_kemasan = in_array($satuan_input, purchaseUnitRules()['packages'], true) ? (float) $factor : 0;
$jumlah = $factor === false ? false : convertStockInput($jumlah_input, $satuan_input, $data['satuan'], $isi_per_kemasan);
if ($jumlah === false) {
    die('Satuan pembelian tidak sesuai dengan satuan stok bahan.');
}

$satuan_safe = mysqli_real_escape_string($conn, $data['satuan']);
$satuan_input_safe = mysqli_real_escape_string($conn, $satuan_input);

mysqli_begin_transaction($conn);

$active_query = mysqli_query($conn, "
    SELECT id
    FROM pesanan_supplier
    WHERE bahan_id = $bahan_id
    AND status IN ('Sudah Dipesan', 'Menunggu Persetujuan')
    LIMIT 1
    FOR UPDATE
");

if (!$active_query) {
    mysqli_rollback($conn);
    die(mysqli_error($conn));
}

if (mysqli_num_rows($active_query) > 0) {
    mysqli_rollback($conn);
    die('Bahan ini sudah dipesan dan masih menunggu diterima.');
}

if ($request_id > 0) {
    $request_query = mysqli_query($conn, "
        SELECT id FROM permintaan_stok
        WHERE id = $request_id
          AND bahan_id = $bahan_id
          AND jenis = 'Restock'
          AND status = 'Disetujui'
        LIMIT 1
        FOR UPDATE
    ");
    if (!$request_query || mysqli_num_rows($request_query) === 0) {
        mysqli_rollback($conn);
        die('Pengajuan restock sudah diproses atau tidak ditemukan.');
    }
}

$request_value = $request_id > 0 ? (string) $request_id : 'NULL';
try {
    $order_code = createSupplierOrderCode($conn, $supplier_id);
} catch (Exception $exception) {
    mysqli_rollback($conn);
    die($exception->getMessage());
}


$insert = mysqli_query($conn, "
    INSERT INTO pesanan_supplier (
        kode_pesanan, permintaan_stok_id, bahan_id, supplier_id, jumlah, jumlah_input,
        satuan_input, isi_per_kemasan, satuan, total_pembelian, status
    ) VALUES (
        '$order_code', $request_value, $bahan_id, $supplier_id, '$jumlah', '$jumlah_input',
        '$satuan_input_safe', '$isi_per_kemasan', '$satuan_safe', '$total_pembelian',
        'Sudah Dipesan'
    )
");

if (!$insert) {
    mysqli_rollback($conn);
    die(mysqli_error($conn));
}

if ($request_id > 0) {
    $update_request = mysqli_query($conn, "
        UPDATE permintaan_stok SET status = 'Dipesan'
        WHERE id = $request_id
    ");
    if (!$update_request) {
        mysqli_rollback($conn);
        die(mysqli_error($conn));
    }
}

mysqli_commit($conn);

header('Location: pesan_supplier.php?tahap=dipesan');
exit;
?>
