<?php
include '../config/database.php';
include '../includes/suppliers.php';
include '../includes/pengeluaran.php';
include '../includes/stock_units.php';

if (($_GET['mode'] ?? '') === 'perubahan') {
    require __DIR__ . '/pengajuan_perubahan_bahan.php';
    exit;
}

ensureSupplierTables($conn);
ensurePengeluaranTable($conn);

$role = auth_current_role();
$user = auth_current_user();
$is_owner = $role === 'owner';
$user_id = (int) ($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !auth_verify_csrf($_POST['csrf_token'] ?? '')) {
    header('Location: permintaan_stok.php?error=' . urlencode('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.'));
    exit;
}

$create_table = mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS permintaan_stok (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        bahan_id INT NULL,
        stok_sebelum DECIMAL(12,2) NOT NULL,
        stok_usulan DECIMAL(12,2) NOT NULL,
        alasan VARCHAR(255) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Menunggu',
        reviewed_by INT NULL,
        review_note VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        INDEX idx_permintaan_status (status),
        INDEX idx_permintaan_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

if (!$create_table) {
    die('Gagal menyiapkan tabel permintaan stok: ' . mysqli_error($conn));
}

function ensure_request_column($conn, $column, $definition) {
    $column_query = mysqli_query($conn, "SHOW COLUMNS FROM permintaan_stok LIKE '$column'");
    if ($column_query && mysqli_num_rows($column_query) === 0) {
        $alter = mysqli_query($conn, "ALTER TABLE permintaan_stok ADD COLUMN $definition");
        if (!$alter) {
            die('Gagal memperbarui tabel permintaan stok: ' . mysqli_error($conn));
        }
    }
}

ensure_request_column($conn, 'review_note', "review_note VARCHAR(255) NULL AFTER reviewed_by");
ensure_request_column($conn, 'batch_key', "batch_key VARCHAR(32) NULL, ADD INDEX idx_stock_batch (batch_key)");
ensure_request_column($conn, 'jenis', "jenis VARCHAR(20) NOT NULL DEFAULT 'Keluar' AFTER bahan_id");
ensure_request_column($conn, 'jumlah', "jumlah DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER jenis");
ensure_request_column($conn, 'supplier_id', "supplier_id INT NULL AFTER jumlah");
ensure_request_column($conn, 'pesanan_supplier_id', "pesanan_supplier_id INT NULL AFTER supplier_id");
ensure_request_column($conn, 'total_pembelian', "total_pembelian DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER stok_usulan");
ensure_request_column($conn, 'is_bahan_baru', "is_bahan_baru TINYINT(1) NOT NULL DEFAULT 0 AFTER bahan_id");
ensure_request_column($conn, 'nama_bahan_baru', "nama_bahan_baru VARCHAR(120) NULL AFTER is_bahan_baru");
ensure_request_column($conn, 'satuan_baru', "satuan_baru VARCHAR(20) NULL AFTER nama_bahan_baru");
ensure_request_column($conn, 'minimum_stok_baru', "minimum_stok_baru DECIMAL(12,2) NULL AFTER satuan_baru");
ensure_request_column($conn, 'harga_supplier', "harga_supplier DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_pembelian");
ensureStockInputColumns($conn, 'permintaan_stok');
ensureStockInputColumns($conn, 'riwayat_masuk');
$request_material_options = [];
$request_material_query = mysqli_query($conn, "SELECT id, nama_bahan FROM bahan_baku ORDER BY nama_bahan ASC");
while ($request_material_query && $material_option = mysqli_fetch_assoc($request_material_query)) {
    $request_material_options[(int) $material_option['id']] = $material_option['nama_bahan'];
}
ensureStockInputColumns($conn, 'riwayat_keluar');

$bahan_id_column = mysqli_query($conn, "SHOW COLUMNS FROM permintaan_stok LIKE 'bahan_id'");
$bahan_id_definition = $bahan_id_column ? mysqli_fetch_assoc($bahan_id_column) : null;
if ($bahan_id_definition && $bahan_id_definition['Null'] === 'NO') {
    $allow_empty_material = mysqli_query($conn, "ALTER TABLE permintaan_stok MODIFY bahan_id INT NULL");
    if (!$allow_empty_material) {
        die('Gagal menyiapkan pengajuan bahan baru: ' . mysqli_error($conn));
    }
}

mysqli_query($conn, "
    UPDATE permintaan_stok
    SET jumlah = ABS(stok_usulan - stok_sebelum)
    WHERE jumlah = 0 AND stok_usulan <> stok_sebelum
");

function format_request_stock($number) {
    return rtrim(rtrim(number_format((float) $number, 2, ',', '.'), '0'), ',');
}

function redirect_request($type, $message) {
    $action = $_POST['action'] ?? '';
    $tab = $type === 'success' && in_array($action, ['approve', 'reject'], true)
        ? ($action === 'reject' ? 'ditolak' : 'disetujui') : 'menunggu';
    if ($type === 'error' && $action === 'resubmit') $tab = 'ditolak';
    header('Location: permintaan_stok.php?tab=' . $tab . '&' . $type . '=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['delete_pending', 'edit_pending', 'resubmit'], true) && $role === 'barista') {
        $request_id = (int) ($_POST['request_id'] ?? 0);
        $expected_status = $action === 'resubmit' ? 'Ditolak' : 'Menunggu';
        mysqli_begin_transaction($conn);
        try {
            $query = mysqli_query($conn, "SELECT * FROM permintaan_stok WHERE id = $request_id AND user_id = $user_id AND jenis = 'Keluar' AND status = '$expected_status' FOR UPDATE");
            $request = $query ? mysqli_fetch_assoc($query) : null;
            if (!$request) throw new Exception('Pengajuan sudah diproses atau tidak ditemukan.');
            $scope = 'id = ' . $request_id;
            if (!empty($request['batch_key'])) {
                $batch = mysqli_real_escape_string($conn, $request['batch_key']);
                $scope = "batch_key = '$batch'";
            }
            $query = mysqli_query($conn, "SELECT * FROM permintaan_stok WHERE $scope AND user_id = $user_id ORDER BY id FOR UPDATE");
            if (!$query) throw new Exception('Pengajuan gagal dibaca.');
            $items = mysqli_fetch_all($query, MYSQLI_ASSOC);
            foreach ($items as $item) {
                if ($item['status'] !== $expected_status || $item['jenis'] !== 'Keluar') {
                    throw new Exception('Status pengajuan sudah berubah. Muat ulang halaman.');
                }
            }
            if ($action === 'delete_pending') {
                if (!mysqli_query($conn, "DELETE FROM permintaan_stok WHERE $scope AND user_id = $user_id AND status = 'Menunggu'")) {
                    throw new Exception('Pengajuan gagal dihapus.');
                }
            } else {
                $amounts = (array) ($_POST['edit_amount'] ?? []);
                $reasons = (array) ($_POST['edit_reason'] ?? []);
                if (count($amounts) !== count($items) || count($reasons) !== count($items)) {
                    throw new Exception('Lengkapi seluruh bahan pengajuan.');
                }
                foreach ($items as $item) {
                    $id = (int) $item['id'];
                    $amount = (float) ($amounts[$id] ?? 0);
                    $reason = $reasons[$id] ?? '';
                    if (!is_finite($amount) || $amount <= 0 || !in_array($reason, ['Stok Rusak', 'Stok Basi', 'Stok Tumpah', 'Selisih Stok Fisik', 'Dipakai Testing', 'Salah Input'], true)) {
                        throw new Exception('Jumlah dan keterangan setiap bahan wajib diisi.');
                    }
                    $material_id = (int) $item['bahan_id'];
                    $material_query = mysqli_query($conn, "SELECT stok, satuan FROM bahan_baku WHERE id = $material_id FOR UPDATE");
                    $material = $material_query ? mysqli_fetch_assoc($material_query) : null;
                    if (!$material || $amount > (float) $material['stok']) throw new Exception('Jumlah pengurangan melebihi stok tersedia.');
                    $pending = mysqli_query($conn, "SELECT id FROM permintaan_stok WHERE bahan_id = $material_id AND id <> $id AND jenis = 'Keluar' AND status = 'Menunggu' LIMIT 1");
                    if (!$pending || mysqli_num_rows($pending) > 0) throw new Exception('Bahan memiliki pengajuan lain yang masih menunggu.');
                    $before = (float) $material['stok'];
                    $after = $before - $amount;
                    $unit = mysqli_real_escape_string($conn, $material['satuan']);
                    $reason_safe = mysqli_real_escape_string($conn, $reason);
                    $reset = $action === 'resubmit' ? ", status = 'Menunggu', reviewed_by = NULL, review_note = NULL, reviewed_at = NULL, created_at = NOW()" : '';
                    if (!mysqli_query($conn, "UPDATE permintaan_stok SET jumlah = '$amount', jumlah_input = '$amount', satuan_input = '$unit', isi_per_kemasan = 0, stok_sebelum = '$before', stok_usulan = '$after', alasan = '$reason_safe'$reset WHERE id = $id")) {
                        throw new Exception('Pengajuan gagal diperbarui.');
                    }
                }
            }
            mysqli_commit($conn);
            redirect_request('success', $action === 'delete_pending' ? 'Seluruh bahan dalam pengajuan berhasil dihapus.' : 'Seluruh bahan dalam pengajuan berhasil diperbarui.');
        } catch (Exception $exception) {
            mysqli_rollback($conn);
            redirect_request('error', $exception->getMessage());
        }
    }

    if ($action === 'submit' && $role === 'barista') {
        $material_ids = array_map('intval', (array) ($_POST['bahan_id'] ?? []));
        $amounts = array_map('floatval', (array) ($_POST['jumlah'] ?? []));
        $reasons = (array) ($_POST['alasan'] ?? []);
        $valid_reasons = ['Stok Rusak', 'Stok Basi', 'Stok Tumpah', 'Selisih Stok Fisik', 'Dipakai Testing', 'Salah Input'];
        if (!$material_ids || count($material_ids) !== count($amounts) || count($material_ids) !== count($reasons)) {
            redirect_request('error', 'Lengkapi seluruh data pengurangan stok.');
        }

        mysqli_begin_transaction($conn);
        try {
            $used_materials = [];
            $batch_key = bin2hex(random_bytes(16));
            foreach ($material_ids as $index => $bahan_id) {
                $jumlah = $amounts[$index] ?? 0;
                $alasan = trim($reasons[$index] ?? '');
                if ($bahan_id <= 0 || !is_finite($jumlah) || $jumlah <= 0 || !in_array($alasan, $valid_reasons, true) || isset($used_materials[$bahan_id])) {
                    throw new Exception('Setiap bahan harus berbeda dan seluruh kolom wajib diisi.');
                }
                $used_materials[$bahan_id] = true;
                $bahan_query = mysqli_query($conn, "SELECT stok, satuan FROM bahan_baku WHERE id = $bahan_id LIMIT 1 FOR UPDATE");
                $bahan = $bahan_query ? mysqli_fetch_assoc($bahan_query) : null;
                if (!$bahan || $jumlah > (float) $bahan['stok']) throw new Exception('Jumlah pengurangan tidak boleh melebihi stok tersedia.');
                $pending_query = mysqli_query($conn, "SELECT id FROM permintaan_stok WHERE bahan_id = $bahan_id AND status = 'Menunggu' AND jenis = 'Keluar' LIMIT 1");
                if (!$pending_query || mysqli_num_rows($pending_query) > 0) throw new Exception('Salah satu bahan masih memiliki pengajuan dalam proses.');

                $stok_sebelum = (float) $bahan['stok'];
                $stok_usulan = $stok_sebelum - $jumlah;
                $alasan_safe = mysqli_real_escape_string($conn, $alasan);
                $satuan_safe = mysqli_real_escape_string($conn, $bahan['satuan']);
                $insert = mysqli_query($conn, "INSERT INTO permintaan_stok (user_id, bahan_id, jenis, jumlah, stok_sebelum, stok_usulan, alasan, jumlah_input, satuan_input, isi_per_kemasan) VALUES ($user_id, $bahan_id, 'Keluar', '$jumlah', '$stok_sebelum', '$stok_usulan', '$alasan_safe', '$jumlah', '$satuan_safe', 0)");
                if (!$insert) throw new Exception('Pengajuan pengurangan stok gagal disimpan.');
                $inserted_id = (int) mysqli_insert_id($conn);
                if (!mysqli_query($conn, "UPDATE permintaan_stok SET batch_key = '$batch_key' WHERE id = $inserted_id")) {
                    throw new Exception('Pengajuan gagal dikelompokkan.');
                }
            }
            mysqli_commit($conn);
            redirect_request('success', count($material_ids) > 1 ? 'Pengajuan beberapa bahan berhasil dikirim kepada Owner.' : 'Pengajuan pengurangan stok berhasil dikirim kepada Owner.');
        } catch (Exception $exception) {
            mysqli_rollback($conn);
            redirect_request('error', $exception->getMessage());
        }
    }

    if ($action === 'receive_order' && $role === 'barista') {
        $order_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['order_ids'] ?? [$_POST['order_id'] ?? 0])))));
        if (!$order_ids) {
            header('Location: pesan_supplier.php?tahap=dipesan&error=' . urlencode('Pesanan tidak ditemukan.'));
            exit;
        }
        mysqli_begin_transaction($conn);
        try {
            foreach ($order_ids as $order_id) {
            $order_query = mysqli_query($conn, "
                SELECT ps.*, bb.stok, bb.nama_bahan, bb.satuan AS bahan_satuan,
                       s.nama_supplier
                FROM pesanan_supplier ps
                JOIN bahan_baku bb ON bb.id = ps.bahan_id
                JOIN supplier s ON s.id = ps.supplier_id
                WHERE ps.id = $order_id AND ps.status = 'Sudah Dipesan'
                FOR UPDATE
            ");
            $order = $order_query ? mysqli_fetch_assoc($order_query) : null;
            if (!$order) {
                throw new Exception('Pesanan sudah diproses atau tidak ditemukan.');
            }

            $stock_reason = !empty($order['is_bahan_baru']) ? 'Stok Awal' : 'Restock';
            $stok_sebelum = (float) $order['stok'];
            $jumlah = (float) $order['jumlah'];
            $stok_setelah = $stok_sebelum + $jumlah;
            $update_stock = mysqli_query($conn, "
                UPDATE bahan_baku SET stok = '$stok_setelah'
                WHERE id = " . (int) $order['bahan_id'] . "
            ");
            if (!$update_stock) {
                throw new Exception(mysqli_error($conn));
            }

            $nama_bahan_safe = mysqli_real_escape_string($conn, $order['nama_bahan']);
            $supplier_name_safe = mysqli_real_escape_string($conn, $order['nama_supplier']);
            $satuan_safe = mysqli_real_escape_string($conn, $order['bahan_satuan']);
            $satuan_input_safe = mysqli_real_escape_string($conn, (string) $order['satuan_input']);
            $history = mysqli_query($conn, "
                INSERT INTO riwayat_masuk (
                    bahan_id, supplier_id, supplier_nama, nama_bahan,
                    jumlah_masuk, satuan, keterangan, total_setelah,
                    jumlah_input, satuan_input, isi_per_kemasan, tanggal
                ) VALUES (
                    " . (int) $order['bahan_id'] . ", " . (int) $order['supplier_id'] . ",
                    '$supplier_name_safe', '$nama_bahan_safe', '$jumlah', '$satuan_safe',
                    '$stock_reason', '$stok_setelah', '" . (float) $order['jumlah_input'] . "',
                    '$satuan_input_safe', '" . (float) $order['isi_per_kemasan'] . "', NOW()
                )
            ");
            if (!$history) {
                throw new Exception(mysqli_error($conn));
            }

            $history_id = (int) mysqli_insert_id($conn);
            $expense = catatPengeluaranPembelian(
                $conn,
                $history_id,
                (int) $order['bahan_id'],
                (int) $order['supplier_id'],
                $stock_reason,
                $order['nama_bahan'],
                $order['nama_supplier'],
                (float) $order['total_pembelian'],
                $stock_reason === 'Stok Awal' ? 'Pembelian stok awal' : 'Pembelian bahan baku',
                $order_id
            );
            if (!$expense) {
                throw new Exception(mysqli_error($conn));
            }

            $update_order = mysqli_query($conn, "
                UPDATE pesanan_supplier
                SET status = 'Diterima', tanggal_diterima = NOW()
                WHERE id = $order_id
            ");
            if (!$update_order) {
                throw new Exception(mysqli_error($conn));
            }

            if ((int) $order['permintaan_stok_id'] > 0) {
                $finish_old_request = mysqli_query($conn, "
                    UPDATE permintaan_stok SET status = 'Selesai'
                    WHERE id = " . (int) $order['permintaan_stok_id'] . "
                ");
                if (!$finish_old_request) {
                    throw new Exception(mysqli_error($conn));
                }
            }
            }

            mysqli_commit($conn);
            header('Location: pesan_supplier.php?tahap=selesai&success=' . urlencode(count($order_ids) . ' bahan diterima dan stok berhasil diperbarui.'));
            exit;
        } catch (Exception $exception) {
            mysqli_rollback($conn);
            header('Location: pesan_supplier.php?tahap=dipesan&error=' . urlencode($exception->getMessage()));
            exit;
        }
    }

    if (in_array($action, ['approve', 'reject'], true) && $is_owner) {
        $request_id = (int) ($_POST['request_id'] ?? 0);
        if ($request_id <= 0) {
            redirect_request('error', 'Pengajuan tidak ditemukan.');
        }

        mysqli_begin_transaction($conn);
        try {
            $request_query = mysqli_query($conn, "
                SELECT ps.*, COALESCE(bb.nama_bahan, ps.nama_bahan_baru) AS nama_bahan,
                       bb.stok AS stok_sekarang,
                       COALESCE(bb.satuan, ps.satuan_baru) AS satuan,
                       s.nama_supplier
                FROM permintaan_stok ps
                LEFT JOIN bahan_baku bb ON bb.id = ps.bahan_id
                LEFT JOIN supplier s ON s.id = ps.supplier_id
                WHERE ps.id = $request_id AND ps.status = 'Menunggu'
                FOR UPDATE
            ");
            $request = $request_query ? mysqli_fetch_assoc($request_query) : null;
            if (!$request) {
                throw new Exception('Pengajuan sudah diproses atau tidak ditemukan.');
            }

            if ($request['jenis'] === 'Keluar' && !empty($request['batch_key'])) {
                $batch_safe = mysqli_real_escape_string($conn, $request['batch_key']);
                $batch_query = mysqli_query($conn, "SELECT ps.*, bb.nama_bahan, bb.satuan, bb.stok AS stok_sekarang FROM permintaan_stok ps LEFT JOIN bahan_baku bb ON bb.id = ps.bahan_id WHERE ps.batch_key = '$batch_safe' ORDER BY ps.id FOR UPDATE");
                if (!$batch_query) throw new Exception('Detail pengajuan gagal dibaca.');
                $review_note = trim($_POST['review_note'] ?? '');
                if ($action === 'reject' && $review_note === '') throw new Exception('Alasan penolakan wajib diisi.');
                $note_safe = mysqli_real_escape_string($conn, $review_note);
                while ($item = mysqli_fetch_assoc($batch_query)) {
                    $item_id = (int) $item['id'];
                    if ($item['status'] !== 'Menunggu' || $item['jenis'] !== 'Keluar' || $item['user_id'] !== $request['user_id']) throw new Exception('Status kelompok pengajuan sudah berubah. Muat ulang halaman.');
                    if ($action === 'approve') {
                        $stock = (float) $item['stok_sekarang'];
                        $amount = (float) $item['jumlah'];
                        if ($item['stok_sekarang'] === null || !is_finite($amount) || $amount <= 0 || $amount > $stock || abs($stock - (float) $item['stok_sebelum']) > 0.0001) {
                            throw new Exception('Stok ' . $item['nama_bahan'] . ' sudah berubah atau tidak cukup. Seluruh pengajuan belum disetujui.');
                        }
                        $after = $stock - $amount;
                        $material_id = (int) $item['bahan_id'];
                        $name_safe = mysqli_real_escape_string($conn, $item['nama_bahan']);
                        $unit_safe = mysqli_real_escape_string($conn, $item['satuan']);
                        $reason_safe = mysqli_real_escape_string($conn, $item['alasan']);
                        if (!mysqli_query($conn, "UPDATE bahan_baku SET stok = '$after' WHERE id = $material_id") ||
                            !mysqli_query($conn, "INSERT INTO riwayat_keluar (bahan_id, nama_bahan, jumlah_keluar, satuan, total_setelah, keterangan, jumlah_input, satuan_input, isi_per_kemasan, tanggal) VALUES ($material_id, '$name_safe', '$amount', '$unit_safe', '$after', '$reason_safe', '$amount', '$unit_safe', 0, NOW())")) {
                            throw new Exception('Pengurangan stok gagal disimpan.');
                        }
                    }
                    $new_status = $action === 'approve' ? 'Disetujui' : 'Ditolak';
                    if (!mysqli_query($conn, "UPDATE permintaan_stok SET status = '$new_status', reviewed_by = $user_id, review_note = '$note_safe', reviewed_at = NOW() WHERE id = $item_id")) {
                        throw new Exception('Status pengajuan gagal diperbarui.');
                    }
                }
                mysqli_commit($conn);
                redirect_request('success', $action === 'approve' ? 'Seluruh bahan dalam pengajuan disetujui dan stok diperbarui.' : 'Seluruh bahan dalam pengajuan ditolak.');
            }

            if ($action === 'reject') {
                $review_note = trim($_POST['review_note'] ?? '');
                if ($review_note === '') {
                    throw new Exception('Alasan penolakan wajib diisi.');
                }
                $review_note_safe = mysqli_real_escape_string($conn, $review_note);
                $update = mysqli_query($conn, "
                    UPDATE permintaan_stok
                    SET status = 'Ditolak', reviewed_by = $user_id,
                        review_note = '$review_note_safe', reviewed_at = NOW()
                    WHERE id = $request_id
                ");
                if (!$update) {
                    throw new Exception(mysqli_error($conn));
                }
                if ((int) $request['pesanan_supplier_id'] > 0) {
                    $restore_order = mysqli_query($conn, "
                        UPDATE pesanan_supplier SET status = 'Sudah Dipesan'
                        WHERE id = " . (int) $request['pesanan_supplier_id'] . "
                    ");
                    if (!$restore_order) {
                        throw new Exception(mysqli_error($conn));
                    }
                }
                mysqli_commit($conn);
                redirect_request('success', 'Pengajuan stok ditolak.');
            }

            if ($request['jenis'] === 'Restock') {
                $approve_restock = mysqli_query($conn, "
                    UPDATE permintaan_stok
                    SET status = 'Disetujui', reviewed_by = $user_id, reviewed_at = NOW()
                    WHERE id = $request_id
                ");
                if (!$approve_restock) {
                    throw new Exception(mysqli_error($conn));
                }

                mysqli_commit($conn);
                redirect_request('success', 'Pengajuan restock disetujui dan siap dibuatkan pesanan supplier.');
            }

            if ((int) $request['is_bahan_baru'] === 1) {
                $nama_baru = mysqli_real_escape_string($conn, $request['nama_bahan_baru']);
                $duplicate = mysqli_query($conn, "
                    SELECT id FROM bahan_baku
                    WHERE LOWER(nama_bahan) = LOWER('$nama_baru')
                    LIMIT 1
                ");
                if ($duplicate && mysqli_num_rows($duplicate) > 0) {
                    throw new Exception('Nama bahan sudah tersedia. Tolak permintaan dan gunakan bahan yang ada.');
                }

                $satuan_baru = mysqli_real_escape_string($conn, $request['satuan_baru']);
                $jumlah_baru = (float) $request['jumlah'];
                $minimum_baru = (float) $request['minimum_stok_baru'];
                $insert_material = mysqli_query($conn, "
                    INSERT INTO bahan_baku (nama_bahan, stok, satuan, minimum_stok)
                    VALUES ('$nama_baru', '$jumlah_baru', '$satuan_baru', '$minimum_baru')
                ");
                if (!$insert_material) {
                    throw new Exception(mysqli_error($conn));
                }
                $new_material_id = (int) mysqli_insert_id($conn);

                $link_supplier = mysqli_query($conn, "
                    INSERT INTO supplier_bahan (supplier_id, bahan_id, harga)
                    VALUES (" . (int) $request['supplier_id'] . ", $new_material_id, '" . (float) $request['harga_supplier'] . "')
                ");
                if (!$link_supplier) {
                    throw new Exception(mysqli_error($conn));
                }

                $supplier_name = mysqli_real_escape_string($conn, (string) $request['nama_supplier']);
                $history = mysqli_query($conn, "
                    INSERT INTO riwayat_masuk (
                        bahan_id, supplier_id, supplier_nama, nama_bahan,
                        jumlah_masuk, satuan, keterangan, total_setelah,
                        jumlah_input, satuan_input, isi_per_kemasan
                    ) VALUES (
                        $new_material_id, " . (int) $request['supplier_id'] . ", '$supplier_name',
                        '$nama_baru', '$jumlah_baru', '$satuan_baru', 'Stok Awal', '$jumlah_baru',
                        '" . (float) $request['jumlah_input'] . "',
                        '" . mysqli_real_escape_string($conn, $request['satuan_input']) . "',
                        '" . (float) $request['isi_per_kemasan'] . "'
                    )
                ");
                if (!$history) {
                    throw new Exception(mysqli_error($conn));
                }

                $expense = catatPengeluaranPembelian(
                    $conn,
                    (int) mysqli_insert_id($conn),
                    $new_material_id,
                    (int) $request['supplier_id'],
                    'Stok Awal',
                    $request['nama_bahan_baru'],
                    $request['nama_supplier'],
                    (float) $request['total_pembelian'],
                    'Pembelian stok awal'
                );
                if (!$expense) {
                    throw new Exception(mysqli_error($conn));
                }

                $finish_new = mysqli_query($conn, "
                    UPDATE permintaan_stok
                    SET bahan_id = $new_material_id, status = 'Disetujui',
                        reviewed_by = $user_id, reviewed_at = NOW()
                    WHERE id = $request_id
                ");
                if (!$finish_new) {
                    throw new Exception(mysqli_error($conn));
                }

                mysqli_commit($conn);
                redirect_request('success', 'Bahan baru disetujui dan stok awal telah dicatat.');
            }

            $stok_sebelum = (float) $request['stok_sebelum'];
            $stok_sekarang = (float) $request['stok_sekarang'];
            $stok_usulan = (float) $request['stok_usulan'];
            $jumlah = (float) $request['jumlah'];

            if ($request['jenis'] !== 'Masuk' && abs($stok_sekarang - $stok_sebelum) > 0.0001) {
                throw new Exception('Stok sudah berubah. Tolak permintaan ini dan minta pengajuan baru.');
            }

            if ($request['jenis'] === 'Masuk') {
                $stok_usulan = $stok_sekarang + $jumlah;
            }

            if ($request['jenis'] === 'Keluar' && $jumlah > $stok_sekarang) {
                throw new Exception('Jumlah stok keluar melebihi stok saat ini.');
            }

            $update_stock = mysqli_query($conn, "
                UPDATE bahan_baku SET stok = '$stok_usulan'
                WHERE id = " . (int) $request['bahan_id'] . "
            ");
            if (!$update_stock) {
                throw new Exception(mysqli_error($conn));
            }

            $nama_bahan = mysqli_real_escape_string($conn, $request['nama_bahan']);
            $satuan = mysqli_real_escape_string($conn, $request['satuan']);
            $alasan = mysqli_real_escape_string($conn, $request['alasan']);

            if ($request['jenis'] === 'Masuk') {
                $supplier_nama = mysqli_real_escape_string($conn, (string) $request['nama_supplier']);
                $history = mysqli_query($conn, "
                    INSERT INTO riwayat_masuk (
                        bahan_id, supplier_id, supplier_nama, nama_bahan,
                        jumlah_masuk, satuan, keterangan, total_setelah,
                        jumlah_input, satuan_input, isi_per_kemasan
                    ) VALUES (
                        " . (int) $request['bahan_id'] . ", " . (int) $request['supplier_id'] . ",
                        '$supplier_nama', '$nama_bahan', '$jumlah', '$satuan', '$alasan', '$stok_usulan',
                        '" . (float) $request['jumlah_input'] . "',
                        '" . mysqli_real_escape_string($conn, $request['satuan_input']) . "',
                        '" . (float) $request['isi_per_kemasan'] . "'
                    )
                ");
                if (!$history) {
                    throw new Exception(mysqli_error($conn));
                }

                $expense = catatPengeluaranPembelian(
                    $conn,
                    (int) mysqli_insert_id($conn),
                    (int) $request['bahan_id'],
                    (int) $request['supplier_id'],
                    'Restock',
                    $request['nama_bahan'],
                    $request['nama_supplier'],
                    (float) $request['total_pembelian'],
                    $request['alasan']
                );
                if (!$expense) {
                    throw new Exception(mysqli_error($conn));
                }

                $order_condition = (int) $request['pesanan_supplier_id'] > 0
                    ? 'id = ' . (int) $request['pesanan_supplier_id']
                    : 'bahan_id = ' . (int) $request['bahan_id']
                        . ' AND supplier_id = ' . (int) $request['supplier_id']
                        . " AND status IN ('Sudah Dipesan', 'Menunggu Persetujuan')";
                $finish_order = mysqli_query($conn, "
                    UPDATE pesanan_supplier
                    SET status = 'Diterima', tanggal_diterima = NOW()
                    WHERE $order_condition
                    LIMIT 1
                ");
                if (!$finish_order) {
                    throw new Exception(mysqli_error($conn));
                }
                if ((int) $request['pesanan_supplier_id'] > 0) {
                    $finish_restock_request = mysqli_query($conn, "
                        UPDATE permintaan_stok
                        SET status = 'Selesai'
                        WHERE id = (
                            SELECT permintaan_stok_id
                            FROM pesanan_supplier
                            WHERE id = " . (int) $request['pesanan_supplier_id'] . "
                        )
                    ");
                    if (!$finish_restock_request) {
                        throw new Exception(mysqli_error($conn));
                    }
                }
            } else {
                $history = mysqli_query($conn, "
                    INSERT INTO riwayat_keluar (
                        bahan_id, nama_bahan, jumlah_keluar, satuan, total_setelah, keterangan,
                        jumlah_input, satuan_input, isi_per_kemasan, tanggal
                    ) VALUES (
                        " . (int) $request['bahan_id'] . ", '$nama_bahan', '$jumlah',
                        '$satuan', '$stok_usulan', '$alasan',
                        '" . (float) $request['jumlah_input'] . "',
                        '" . mysqli_real_escape_string($conn, $request['satuan_input']) . "',
                        '" . (float) $request['isi_per_kemasan'] . "', NOW()
                    )
                ");
                if (!$history) {
                    throw new Exception(mysqli_error($conn));
                }
            }

            $finish = mysqli_query($conn, "
                UPDATE permintaan_stok
                SET status = 'Disetujui', reviewed_by = $user_id, reviewed_at = NOW()
                WHERE id = $request_id
            ");
            if (!$finish) {
                throw new Exception(mysqli_error($conn));
            }

            mysqli_commit($conn);
            redirect_request('success', 'Pengajuan disetujui dan stok telah diperbarui.');
        } catch (Exception $exception) {
            mysqli_rollback($conn);
            redirect_request('error', $exception->getMessage());
        }
    }

    redirect_request('error', 'Aksi tidak diizinkan untuk role ini.');
}

$materials = mysqli_query($conn, "
    SELECT id, nama_bahan, stok, satuan FROM bahan_baku ORDER BY nama_bahan ASC
");
$materials_for_change = mysqli_query($conn, "SELECT id, nama_bahan, satuan, minimum_stok FROM bahan_baku ORDER BY nama_bahan ASC");
$suppliers_by_material = [];
$all_suppliers = [];
$supplier_query = mysqli_query($conn, "
    SELECT sb.bahan_id, s.id, s.nama_supplier
    FROM supplier_bahan sb
    JOIN supplier s ON s.id = sb.supplier_id
    ORDER BY s.nama_supplier ASC
");
if ($supplier_query) {
    while ($supplier = mysqli_fetch_assoc($supplier_query)) {
        $suppliers_by_material[(int) $supplier['bahan_id']][] = [
            'id' => (int) $supplier['id'],
            'name' => $supplier['nama_supplier'],
        ];
    }
}

$all_supplier_query = mysqli_query($conn, "SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier ASC");
if ($all_supplier_query) {
    while ($supplier = mysqli_fetch_assoc($all_supplier_query)) {
        $all_suppliers[] = [
            'id' => (int) $supplier['id'],
            'name' => $supplier['nama_supplier'],
        ];
    }
}

$request_where = ($is_owner ? '1=1' : 'ps.user_id = ' . $user_id) . " AND ps.jenis = 'Keluar'";
$requests = mysqli_query($conn, "
    SELECT ps.*, COALESCE(bb.nama_bahan, ps.nama_bahan_baru) AS nama_bahan,
           COALESCE(bb.satuan, ps.satuan_baru) AS satuan, u.name AS pemohon,
           reviewer.name AS pemeriksa, s.nama_supplier, po.foto_nota
    FROM permintaan_stok ps
    LEFT JOIN bahan_baku bb ON bb.id = ps.bahan_id
    JOIN users u ON u.id = ps.user_id
    LEFT JOIN users reviewer ON reviewer.id = ps.reviewed_by
    LEFT JOIN supplier s ON s.id = ps.supplier_id
    LEFT JOIN pesanan_supplier po ON po.id = ps.pesanan_supplier_id
    WHERE $request_where
    ORDER BY (ps.status = 'Menunggu') DESC,
             CASE WHEN ps.status = 'Menunggu' THEN ps.created_at ELSE COALESCE(ps.reviewed_at, ps.created_at) END DESC,
             ps.id DESC
");

$success = trim($_GET['success'] ?? '');
$error = trim($_GET['error'] ?? '');
$request_tabs = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'];
$active_tab = $_GET['tab'] ?? 'menunggu';
if (!is_string($active_tab) || !isset($request_tabs[$active_tab])) $active_tab = 'menunggu';
$request_rows = $requests ? mysqli_fetch_all($requests, MYSQLI_ASSOC) : [];
if ($is_owner || $role === 'barista') {
    $grouped_requests = [];
    foreach ($request_rows as $row) {
        $row['satuan'] = stockInputUnitLabel($row['satuan'] ?? '');
        $key = !empty($row['batch_key']) ? $row['user_id'] . ':' . $row['batch_key'] . ':' . $row['status'] : 'single:' . $row['id'];
        if (!isset($grouped_requests[$key])) {
            $grouped_requests[$key] = $row;
            $grouped_requests[$key]['material_ids'] = [];
        }
        $grouped_requests[$key]['material_ids'][] = (int) $row['bahan_id'];
        $grouped_requests[$key]['edit_items'][] = [
            'id' => (int) $row['id'], 'name' => $row['nama_bahan'],
            'amount' => (float) $row['jumlah'], 'unit' => $row['satuan'],
            'reason' => $row['alasan'],
        ];
        $grouped_requests[$key]['batch_items'][] = [
            'Bahan' => $row['nama_bahan'],
            'Stok Sebelum' => format_request_stock($row['stok_sebelum']) . ' ' . $row['satuan'],
            'Pengurangan' => format_request_stock($row['jumlah']) . ' ' . $row['satuan'],
            'Stok Setelah' => format_request_stock($row['stok_usulan']) . ' ' . $row['satuan'],
            'Keterangan' => $row['alasan'],
        ];
    }
    $request_rows = array_values($grouped_requests);
}
$tab_counts = array_fill_keys(array_keys($request_tabs), 0);
foreach ($request_rows as &$request_row) {
    $request_row['tab'] = in_array($request_row['status'], ['Disetujui', 'Dipesan', 'Selesai'], true)
        ? 'disetujui' : strtolower($request_row['status']);
    if (isset($tab_counts[$request_row['tab']])) $tab_counts[$request_row['tab']]++;
}
unset($request_row);
$request_rows = array_filter($request_rows, function ($row) use ($active_tab) {
    return $row['tab'] === $active_tab;
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penyesuaian Stok</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/bahan.css?v=<?= filemtime(__DIR__ . '/../css/bahan.css') ?>">
</head>
<body>
<?php $current_module = 'permintaan_stok'; include '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header">
        <div>
            <h1><?= $is_owner ? 'Pengajuan Penyesuaian Stok' : 'Penyesuaian Stok' ?></h1>
            <p><?= $is_owner ? 'Tinjau pengajuan penyesuaian stok dari Barista' : 'Ajukan penyesuaian stok karena rusak, basi, atau salah input' ?></p>
        </div>
        <?php if ($is_owner): ?><a href="pesan_supplier.php" class="btn-stock">Pesan Bahan Baku</a><?php endif; ?>
    </div>

    <?php if ($success !== ''): ?><div class="request-notice request-notice-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="request-notice request-notice-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (!$is_owner): ?>
        <details class="table-card request-form-card"<?= $error !== '' ? ' open' : '' ?>>
            <summary>+ Ajukan Penyesuaian Stok</summary>
            <form method="POST" class="request-stock-form" id="batchStockReductionForm">
                <?= auth_csrf_input() ?>
                <input type="hidden" name="action" value="submit">
                <div class="form-group"><label>Jenis Perubahan</label><select id="stockRequestKind" name="jenis"><option value="Stok">Penyesuaian Stok</option><option value="Info">Perubahan Info</option><option value="Kemasan">Perubahan Kemasan</option></select></div>
                    <div id="materialChangeFields" hidden>
                    <div class="form-group"><label>Bahan</label><select name="bahan_id" id="materialChangeBahan"><option value="">Pilih bahan</option><?php while ($change_material = mysqli_fetch_assoc($materials_for_change)): ?><option value="<?= (int) $change_material['id'] ?>" data-name="<?= htmlspecialchars($change_material['nama_bahan'], ENT_QUOTES) ?>" data-unit="<?= htmlspecialchars($change_material['satuan'], ENT_QUOTES) ?>" data-minimum="<?= (float) $change_material['minimum_stok'] ?>"><?= htmlspecialchars($change_material['nama_bahan']) ?></option><?php endwhile; ?></select></div>
                    <div id="materialInfoFields"><div class="form-group"><label>Nama Bahan Baru</label><input name="nama_bahan" id="materialChangeName"></div><div class="form-group"><label>Minimum Stok Baru</label><input type="number" step="0.01" min="0" name="minimum_stok" id="materialChangeMinimum"></div><div class="form-group"><label>Satuan Dasar Stok Baru</label><select name="satuan_baru" id="materialChangeBaseUnit"><option value="g">Gram (g)</option><option value="ml">Mililiter (ml)</option><option value="pcs">Pcs</option></select><small class="supplier-form-note">Hanya diajukan bila satuan dasar saat bahan dibuat memang salah.</small></div></div>
                    <div id="materialPackageFields" hidden><div class="form-group"><label>Kemasan</label><select name="satuan_kemasan" id="materialPackageUnit"><option value="botol">Botol</option><option value="pack">Pack</option><option value="dus">Dus</option><option value="kotak">Kotak</option><option value="galon">Galon</option><option value="sachet">Sachet</option><option value="kaleng">Kaleng</option><option value="bag">Bag</option><option value="kantong">Kantong</option></select></div><div class="form-group"><label>Satuan Dasar Stok</label><input id="materialBaseUnit" readonly value="Pilih bahan terlebih dahulu"></div><div class="form-group"><label id="materialPackageContentLabel">Isi 1 Kemasan dalam Satuan Dasar</label><input type="number" step="0.001" min="0.001" name="isi_satuan_dasar" id="materialPackageContent"><small class="supplier-form-note" id="materialPackagePreview">Pilih bahan untuk melihat konversi.</small></div></div>
                    <button type="submit" class="btn-primary">Kirim ke Owner</button>
                </div>
                <div id="stockRequestFields">
                <p class="request-form-help">Tambahkan bahan yang perlu dikurangi. Jumlah otomatis menggunakan satuan bahan.</p>
                <div id="stockReductionRows">
                    <div class="stock-reduction-row">
                        <span class="stock-row-number">1</span>
                        <div class="form-group"><label>Nama Bahan</label><select name="bahan_id[]" class="stock-row-material" required><option value="">Pilih bahan baku</option><?php while ($material = mysqli_fetch_assoc($materials)): ?><option value="<?= (int) $material['id'] ?>" data-stock="<?= (float) $material['stok'] ?>" data-unit="<?= htmlspecialchars($material['satuan'], ENT_QUOTES) ?>"><?= htmlspecialchars($material['nama_bahan']) ?></option><?php endwhile; ?></select><small class="stock-row-info">Stok tersedia: -</small></div>
                        <div class="form-group"><label>Jumlah Pengurangan</label><div class="stock-amount-field"><input type="number" name="jumlah[]" min="0.01" step="0.01" placeholder="0" required><span class="stock-row-unit">-</span></div></div>
                        <div class="form-group"><label>Keterangan</label><select name="alasan[]" required><option value="">Pilih keterangan</option><option value="Stok Rusak">Stok Rusak</option><option value="Stok Basi">Stok Basi</option><option value="Stok Tumpah">Stok Tumpah</option><option value="Selisih Stok Fisik">Selisih Stok Fisik</option><option value="Dipakai Testing">Dipakai Testing</option><option value="Salah Input">Salah Input</option></select></div>
                        <button type="button" class="btn-delete remove-stock-row" aria-label="Hapus bahan" hidden>&times;</button>
                    </div>
                </div>
                <div class="stock-reduction-actions">
                    <button type="button" class="btn-secondary" id="addStockReductionRow">+ Tambah Bahan</button>
                    <button type="submit" class="btn-primary">Ajukan Penyesuaian</button>
                </div>
                <small class="request-form-help">Stok baru berkurang setelah pengajuan disetujui Owner.</small>
                </div>
            </form>
        </details>
    <?php endif; ?>

    <nav class="workflow-tabs" aria-label="Status pengajuan stok">
        <?php foreach ($request_tabs as $key => $label): ?>
            <a href="?tab=<?= $key ?>"<?= $key === $active_tab ? ' aria-current="page"' : '' ?>><?= $label ?><span class="workflow-count"><?= $tab_counts[$key] ?></span></a>
        <?php endforeach; ?>
    </nav>
    <p class="workflow-note"><?= $is_owner ? 'Periksa seluruh bahan sebelum menyetujui atau menolak pengajuan.' : 'Seluruh bahan dalam satu pengajuan diproses sekaligus oleh Owner.' ?></p>
    <div class="table-card">
        <h2><?= $is_owner ? 'Daftar Pengajuan' : 'Riwayat Pengajuan Saya' ?></h2>
        <div class="procurement-filters request-history-filters">
            <div class="search-box bahan-search-box request-history-search">
                <input type="text" id="requestSearch" placeholder="Cari bahan, supplier, atau status...">
            </div>
            <select id="requestMaterialFilter" aria-label="Filter nama bahan">
                <option value="">Semua Bahan</option>
                <?php foreach ($request_material_options as $material_id => $material_name): ?>
                    <option value="<?= $material_id ?>"><?= htmlspecialchars($material_name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="workflow-table-wrap">
            <table>
                <thead><tr>
                    <th>No</th>
                    <?php if ($is_owner): ?><th>Pemohon</th><?php endif; ?>
                    <th>Jenis</th><th>Bahan</th><th>Status</th><th>Tanggal</th><th>Aksi</th>
                </tr></thead>
                <tbody id="requestTableBody">
                    <?php if ($request_rows): $no = 1; ?>
                        <?php foreach ($request_rows as $request): ?>
                            <?php $status_class = in_array($request['status'], ['Disetujui', 'Dipesan', 'Selesai'], true) ? 'badge-success' : ($request['status'] === 'Ditolak' ? 'badge-danger' : 'badge-warning'); ?>
                            <tr data-request-row data-materials="<?= htmlspecialchars(implode(',', $request['material_ids'] ?? [(int) $request['bahan_id']]), ENT_QUOTES) ?>" data-search="<?= htmlspecialchars(($request['nama_supplier'] ?? '') . ' ' . $request['alasan'], ENT_QUOTES) ?>">
                                <td><?= $no++ ?></td>
                                <?php if ($is_owner): ?><td><?= htmlspecialchars($request['pemohon']) ?></td><?php endif; ?>
                                <td>
                                    <?php if ($request['jenis'] === 'Restock'): ?>
                                        <span class="badge badge-warning">Restock</span>
                                    <?php else: ?>
                                        <span class="badge <?= $request['jenis'] === 'Masuk' ? 'badge-success' : 'badge-danger' ?>"><?= $request['jenis'] === 'Masuk' ? 'Barang Masuk' : 'Pengurangan' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= count($request['batch_items'] ?? []) > 1 ? count($request['batch_items']) . ' bahan' : htmlspecialchars($request['nama_bahan']) ?>
                                    <?php if ((int) $request['is_bahan_baru'] === 1): ?>
                                        <small class="request-form-help">Bahan Baru</small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $status_class ?>"><?= htmlspecialchars($request['status']) ?></span></td>
                                <td><?= date('d/m/Y H:i', strtotime($request['status'] !== 'Menunggu' && !empty($request['reviewed_at']) ? $request['reviewed_at'] : $request['created_at'])) ?></td>
                                    <td>
                                        <button type="button" class="btn-stock detail-stock-request"
                                            data-items="<?= htmlspecialchars(json_encode($request['batch_items'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
                                            data-id="<?= (int) $request['id'] ?>"
                                            data-order-id="<?= (int) $request['pesanan_supplier_id'] ?>"
                                            data-kind="<?= htmlspecialchars($request['jenis'], ENT_QUOTES) ?>"
                                            data-name="<?= htmlspecialchars($request['nama_bahan'] ?? '-', ENT_QUOTES) ?>"
                                            data-status="<?= htmlspecialchars($request['status'], ENT_QUOTES) ?>"
                                            data-note-url="<?= $is_owner && !empty($request['foto_nota']) ? 'uploads/notas/' . rawurlencode($request['foto_nota']) : '' ?>"
                                            data-detail="<?= htmlspecialchars(json_encode([
                                                'Pemohon' => $request['pemohon'],
                                                'Jenis Pengajuan' => $request['jenis'] === 'Masuk' ? 'Barang Masuk' : ($request['jenis'] === 'Restock' ? 'Pengajuan Restock' : 'Pengurangan Stok'),
                                                'Nama Bahan' => $request['nama_bahan'],
                                                'Jumlah Input' => (float) $request['jumlah_input'] > 0 ? format_request_stock($request['jumlah_input']) . ' ' . stockInputUnitLabel($request['satuan_input']) : 'Ditentukan Owner',
                                                'Hasil Konversi' => (float) $request['jumlah'] > 0 ? format_request_stock($request['jumlah']) . ' ' . $request['satuan'] : '-',
                                                'Isi per Kemasan' => (float) $request['isi_per_kemasan'] > 0 ? format_request_stock($request['isi_per_kemasan']) . ' ' . $request['satuan'] : '-',
                                                'Stok Saat Pengajuan' => format_request_stock($request['stok_sebelum']) . ' ' . $request['satuan'],
                                                'Usulan Stok Setelah Perubahan' => $request['jenis'] === 'Restock' ? 'Belum mengubah stok' : format_request_stock($request['stok_usulan']) . ' ' . $request['satuan'],
                                                'Supplier' => $request['nama_supplier'] ?: '-',
                                                ...($is_owner ? ['Total Pembelian' => (float) $request['total_pembelian'] > 0 ? 'Rp' . number_format((float) $request['total_pembelian'], 0, ',', '.') : '-'] : []),
                                                'Keterangan' => $request['alasan'],
                                                'Status Pengajuan' => $request['status'],
                                                'Tanggal Pengajuan' => date('d/m/Y H:i', strtotime($request['created_at'])),
                                                ($request['status'] === 'Ditolak' ? 'Tanggal Ditolak' : 'Tanggal Disetujui') => $request['status'] !== 'Menunggu' && !empty($request['reviewed_at']) ? date('d/m/Y H:i', strtotime($request['reviewed_at'])) : '-',
                                                'Diperiksa oleh' => $request['pemeriksa'] ?: '-',
                                                'Alasan Owner' => $request['review_note'] ?: '-',
                                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>">Detail</button>
                                        <?php if (!$is_owner && in_array($request['status'], ['Menunggu', 'Ditolak'], true) && $request['jenis'] === 'Keluar'): ?>
                                            <button type="button" class="<?= $request['status'] === 'Ditolak' ? 'btn-primary' : 'btn-edit' ?> edit-stock-request"
                                                data-items="<?= htmlspecialchars(json_encode($request['edit_items'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
                                                data-id="<?= (int) $request['id'] ?>"
                                                data-action="<?= $request['status'] === 'Ditolak' ? 'resubmit' : 'edit_pending' ?>"
                                                data-material-id="<?= (int) $request['bahan_id'] ?>"
                                                data-name="<?= htmlspecialchars($request['nama_bahan'], ENT_QUOTES) ?>"
                                                data-stock="<?= htmlspecialchars((string) $request['stok_sebelum'], ENT_QUOTES) ?>"
                                                data-unit="<?= htmlspecialchars($request['satuan'], ENT_QUOTES) ?>"
                                                data-amount="<?= htmlspecialchars((string) $request['jumlah_input'], ENT_QUOTES) ?>"
                                                data-input-unit="<?= htmlspecialchars($request['satuan_input'], ENT_QUOTES) ?>"
                                                data-package="<?= htmlspecialchars((string) $request['isi_per_kemasan'], ENT_QUOTES) ?>"
                                                data-reason="<?= htmlspecialchars($request['alasan'], ENT_QUOTES) ?>"><?= $request['status'] === 'Ditolak' ? 'Ajukan Lagi' : 'Edit' ?></button>
                                        <?php endif; ?>
                                        <?php if ($role === 'barista' && $request['status'] === 'Menunggu' && $request['jenis'] === 'Keluar'): ?>
                                            <form method="POST" class="stock-request-delete-form" onsubmit="return confirm('Hapus seluruh bahan dalam pengajuan ini? Stok bahan tidak berubah.');">
                                                <?= auth_csrf_input() ?>
                                                <input type="hidden" name="action" value="delete_pending">
                                                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                                <button type="submit" class="btn-delete">Hapus</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr id="requestEmpty" class="workflow-empty"<?= $request_rows ? ' hidden' : '' ?>><td colspan="<?= $is_owner ? 7 : 6 ?>">Belum ada pengajuan pada status ini.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="stockRequestDetailModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="stockDetailTitle"><div class="modal-content workflow-modal">
    <div class="modal-header"><h2 id="stockDetailTitle">Detail Pengurangan Stok</h2><button type="button" class="close-btn" id="closeStockRequestDetail" aria-label="Tutup detail">&times;</button></div>
    <div class="request-detail-list" id="stockRequestDetail"></div>
    <div id="stockBatchDetail" class="workflow-table-wrap" hidden>
        <table><thead><tr><th>No</th><th>Bahan</th><th>Stok Sebelum</th><th>Pengurangan</th><th>Stok Setelah</th><th>Keterangan</th></tr></thead><tbody id="stockBatchBody"></tbody></table>
    </div>
    <?php if ($is_owner): ?><div class="receipt-photo" id="stockRequestNote" hidden><span>Foto Nota</span><a id="stockRequestNoteLink" href="#" target="_blank" rel="noopener"><img id="stockRequestNoteImage" alt="Foto nota pembelian"></a></div><?php endif; ?>
    <?php if ($is_owner): ?>
    <div class="workflow-review-actions" id="stockReviewActions" hidden>
        <button type="button" class="btn-delete open-reject-modal" id="rejectFromStockDetail">Tolak</button>
        <form method="POST" onsubmit="return confirm('Setujui permintaan stok ini?')">
            <?= auth_csrf_input() ?>
            <input type="hidden" name="action" value="approve"><input type="hidden" name="request_id" id="approve_stock_id">
            <button type="submit" class="btn-primary">Setujui Pengajuan</button>
        </form>
    </div>
    <div class="workflow-review-actions" id="stockNextStep" hidden><a href="pesan_supplier.php?tahap=siap" class="btn-primary">Lanjut ke Pesan Bahan Baku</a></div>
    <?php endif; ?>
</div></div>

<?php if ($is_owner): ?>
<div id="rejectRequestModal" class="modal"><div class="modal-content">
    <div class="modal-header"><h2>Tolak Pengajuan</h2><button type="button" class="close-btn" id="closeRejectModal">&times;</button></div>
    <form method="POST">
        <?= auth_csrf_input() ?>
        <input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" id="reject_request_id">
        <div class="form-group"><label>Bahan</label><input type="text" id="reject_material_name" readonly></div>
        <div class="form-group"><label for="review_note">Alasan Penolakan</label><textarea name="review_note" id="review_note" rows="3" maxlength="255" required></textarea></div>
        <button type="submit" class="btn-delete">Tolak Pengajuan</button>
    </form>
</div></div>
<?php endif; ?>

<?php if (!$is_owner): ?><div id="editStockRequestModal" class="modal"><div class="modal-content">
    <div class="modal-header"><h2>Edit Pengurangan Stok</h2><button type="button" class="close-btn" id="closeEditStockRequest">&times;</button></div>
    <form method="POST">
        <?= auth_csrf_input() ?>
        <input type="hidden" name="action" id="edit_stock_action"><input type="hidden" name="request_id" id="edit_stock_id">
        <p class="request-form-help">Periksa jumlah dan keterangan seluruh bahan sebelum menyimpan.</p>
        <div id="editStockBatchRows"></div>
        <button type="submit" class="btn-primary">Simpan Perubahan</button>
    </form>
</div></div><?php endif; ?>

<script>
if (document.getElementById('material_mode')) {
const suppliersByMaterial = <?= json_encode($suppliers_by_material, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const allSuppliers = <?= json_encode($all_suppliers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const materialMode = document.getElementById('material_mode');
const materialModeButtons = document.querySelectorAll('.material-mode-button');
const changeType = document.getElementById('change_type');
const changeTypeButtons = document.querySelectorAll('.request-type-button[data-type]');
const materialSearch = document.getElementById('material_search');
const materialSelect = document.getElementById('material_id');
const existingMaterialFields = document.getElementById('existingMaterialFields');
const newMaterialFields = document.getElementById('newMaterialFields');
const newMaterialName = document.getElementById('new_material_name');
const newMaterialUnit = document.getElementById('new_material_unit');
const newMaterialMinimum = document.getElementById('new_material_minimum');
const currentStock = document.getElementById('current_stock');
const stockChange = document.getElementById('stock_change');
const stockChangeLabel = document.getElementById('stockChangeLabel');
const inputUnit = document.getElementById('input_unit');
const inputUnitLabel = document.getElementById('inputUnitLabel');
const conversionTarget = document.getElementById('conversion_target');
const inputUnitOptions = Array.from(inputUnit ? inputUnit.options : []).slice(1);
const packageContentField = document.getElementById('packageContentField');
const packageContent = document.getElementById('package_content');
const packageContentHelp = document.getElementById('packageContentHelp');
const conversionPreview = document.getElementById('conversionPreview');
const incomingFields = document.getElementById('incomingFields');
const supplierSelect = document.getElementById('supplier_id');
const purchaseTotal = document.getElementById('purchase_total');
const supplierPriceField = document.getElementById('supplierPriceField');
const supplierPrice = document.getElementById('supplier_price');
const requestReason = document.getElementById('request_reason');

function populateSuppliers(suppliers) {
    supplierSelect.innerHTML = '<option value="">Pilih supplier</option>';
    suppliers.forEach(supplier => {
        const option = document.createElement('option');
        option.value = supplier.id;
        option.textContent = supplier.name;
        supplierSelect.appendChild(option);
    });
    if (suppliers.length === 0) {
        supplierSelect.innerHTML += '<option value="" disabled>Supplier belum tersedia</option>';
    }
}

function getBaseUnit() {
    if (materialMode.value === 'new') return newMaterialUnit.value;
    const selected = materialSelect.options[materialSelect.selectedIndex];
    return selected.dataset.unit || '';
}

function getUnitCategory(unit) {
    unit = unit.toLowerCase();
    if (['gram', 'g', 'kg'].includes(unit)) return 'weight';
    if (['ml', 'liter', 'l'].includes(unit)) return 'volume';
    if (unit === 'pcs') return 'count';
    return '';
}

function updateUnitOptions() {
    if (!inputUnit) return;
    const category = getUnitCategory(getBaseUnit());
    conversionTarget.value = getBaseUnit() || '-';
    inputUnit.value = '';
    inputUnitOptions.forEach(option => {
        option.hidden = !option.dataset.package && option.dataset.category !== category;
    });
    packageContentField.hidden = true;
    packageContent.required = false;
    packageContent.value = '';
    conversionPreview.textContent = category
        ? 'Masukkan jumlah dan pilih satuan untuk melihat hasil konversi.'
        : 'Pilih bahan atau satuan dasar terlebih dahulu.';
}

function updateConversionPreview() {
    if (!inputUnit) return;
    const selected = inputUnit.options[inputUnit.selectedIndex];
    const baseUnit = getBaseUnit();
    const quantity = parseFloat(stockChange.value || '0');
    const content = parseFloat(packageContent.value || '0');
    const isPackage = selected && selected.dataset.package === '1';

    packageContentField.hidden = !isPackage;
    packageContent.required = isPackage;
    packageContentHelp.textContent = isPackage && baseUnit ? `Isi satu ${selected.textContent} dalam ${baseUnit}.` : '';

    if (!baseUnit || !selected || !selected.value || quantity <= 0 || (isPackage && content <= 0)) {
        conversionPreview.textContent = 'Lengkapi jumlah dan satuan untuk melihat konversi.';
        return;
    }

    let factor = 1;
    if (isPackage) {
        factor = content;
    } else if (['gram', 'g'].includes(baseUnit) && selected.value === 'kg') {
        factor = 1000;
    } else if (baseUnit === 'kg' && selected.value === 'g') {
        factor = 0.001;
    } else if (baseUnit === 'ml' && selected.value === 'liter') {
        factor = 1000;
    } else if (['liter', 'l'].includes(baseUnit) && selected.value === 'ml') {
        factor = 0.001;
    }

    const converted = quantity * factor;
    const inputLabel = selected.value === 'liter' ? 'L' : selected.value;
    const packageText = isPackage ? ` x ${content} ${baseUnit}` : '';
    conversionPreview.textContent = `Hasil konversi: ${quantity} ${inputLabel}${packageText} = ${converted.toLocaleString('id-ID')} ${baseUnit}`;
}

function updateIncomingFields() {
    if (!changeType || !stockChange) return;
    const isIncoming = changeType.value === 'tambah';
    const selected = materialSelect.options[materialSelect.selectedIndex];
    incomingFields.hidden = !isIncoming;
    stockChangeLabel.textContent = isIncoming ? 'Jumlah Pembelian' : 'Jumlah Pengurangan';
    inputUnitLabel.textContent = isIncoming ? 'Satuan Pembelian' : 'Satuan Pengurangan';
    stockChange.placeholder = isIncoming ? 'Masukkan jumlah pembelian' : 'Masukkan jumlah pengurangan';
    supplierSelect.required = isIncoming;
    purchaseTotal.required = isIncoming;
    requestReason.required = true;
    requestReason.value = '';
    const reasonType = materialMode.value === 'new' ? 'new' : changeType.value;
    Array.from(requestReason.options).slice(1).forEach(option => {
        option.hidden = !option.dataset.types.includes(reasonType);
    });
    if (isIncoming) {
        stockChange.removeAttribute('max');
    } else if (changeType.value === 'kurang' && selected.dataset.stock) {
        stockChange.max = selected.dataset.stock;
    }
}

function selectChangeType(type) {
    changeType.value = type;
    changeTypeButtons.forEach(item => {
        const isActive = item.dataset.type === type;
        item.classList.toggle('is-active', isActive);
        item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
    stockChange.value = '';
    updateIncomingFields();
    updateConversionPreview();
}

if (materialSelect) {
    const materialOptions = Array.from(materialSelect.options).slice(1);

    materialSearch.addEventListener('input', function () {
        const keyword = this.value.toLowerCase().trim();
        materialOptions.forEach(option => {
            option.hidden = keyword !== '' && !option.textContent.toLowerCase().includes(keyword);
        });
    });

    materialSelect.addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        const unit = selected.dataset.unit || '';
        const suppliers = suppliersByMaterial[this.value] || [];

        currentStock.value = this.value ? `${selected.dataset.stock} ${unit}` : '-';
        stockChange.value = '';
        populateSuppliers(suppliers);
        updateUnitOptions();
        updateIncomingFields();
    });

    changeTypeButtons.forEach(button => button.addEventListener('click', function () {
        selectChangeType(this.dataset.type);
    }));

    materialModeButtons.forEach(button => button.addEventListener('click', function () {
        const isNew = this.dataset.mode === 'new';
        materialMode.value = this.dataset.mode;
        materialModeButtons.forEach(item => {
            const isActive = item === this;
            item.classList.toggle('is-active', isActive);
            item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        existingMaterialFields.hidden = isNew;
        newMaterialFields.hidden = !isNew;
        materialSelect.required = !isNew;
        newMaterialName.required = isNew;
        newMaterialUnit.required = isNew;
        newMaterialMinimum.required = isNew;
        supplierPriceField.hidden = !isNew;
        supplierPrice.required = isNew;
        changeTypeButtons.forEach(item => {
            item.disabled = isNew && item.dataset.type === 'kurang';
        });

        if (isNew) {
            currentStock.value = '0';
            populateSuppliers(allSuppliers);
            selectChangeType('tambah');
            requestReason.value = 'Stok Awal';
            updateUnitOptions();
        } else {
            currentStock.value = materialSelect.value
                ? `${materialSelect.options[materialSelect.selectedIndex].dataset.stock} ${materialSelect.options[materialSelect.selectedIndex].dataset.unit}`
                : '-';
            const suppliers = suppliersByMaterial[materialSelect.value] || [];
            populateSuppliers(suppliers);
            selectChangeType('');
            updateUnitOptions();
        }
    }));

    newMaterialUnit.addEventListener('change', updateUnitOptions);
    inputUnit.addEventListener('change', updateConversionPreview);
    stockChange.addEventListener('input', updateConversionPreview);
    packageContent.addEventListener('input', updateConversionPreview);

    document.querySelector('.request-stock-form').addEventListener('submit', function (event) {
        if (!changeType.value) {
            event.preventDefault();
            alert('Pilih jenis perubahan stok terlebih dahulu.');
        }
    });
}
}

const requestKind = document.getElementById('request_kind');
if (requestKind) {
    const requestKindButtons = document.querySelectorAll('[data-request-kind]');
    const reductionFields = document.getElementById('stockReductionFields');
    const materialSearchField = document.getElementById('material_search');
    const materialSelectField = document.getElementById('material_id');
    const stockChangeField = document.getElementById('stock_change');
    const inputUnitField = document.getElementById('input_unit');
    const packageField = document.getElementById('packageContentField');
    const packageContentField = document.getElementById('package_content');
    const currentStockField = document.getElementById('current_stock');
    const conversionTargetField = document.getElementById('conversion_target');
    const conversionPreviewField = document.getElementById('conversionPreview');
    const reasonField = document.getElementById('request_reason');
    const requestHelp = document.getElementById('requestHelp');
    const materialOptions = Array.from(materialSelectField.options).slice(1);

    function baseUnit() {
        const selected = materialSelectField.options[materialSelectField.selectedIndex];
        return selected ? selected.dataset.unit || '' : '';
    }

    function unitCategory(unit) {
        if (['gram', 'g', 'kg'].includes(unit)) return 'weight';
        if (['ml', 'liter', 'l'].includes(unit)) return 'volume';
        return unit === 'pcs' ? 'count' : '';
    }

    function updateReductionConversion() {
        const selected = inputUnitField.options[inputUnitField.selectedIndex];
        const unit = baseUnit();
        const quantity = parseFloat(stockChangeField.value || '0');
        const content = parseFloat(packageContentField.value || '0');
        const isPackage = selected && selected.dataset.package === '1';
        packageField.hidden = !isPackage;
        packageContentField.required = requestKind.value === 'keluar' && isPackage;
        document.getElementById('packageContentHelp').textContent = isPackage && unit
            ? `Isi satu ${selected.textContent} dalam ${unit}.`
            : '';

        if (!unit || !selected || !selected.value || quantity <= 0 || (isPackage && content <= 0)) {
            conversionPreviewField.textContent = 'Lengkapi jumlah dan satuan untuk melihat konversi.';
            return;
        }

        let factor = isPackage ? content : 1;
        if (['gram', 'g'].includes(unit) && selected.value === 'kg') factor = 1000;
        if (unit === 'kg' && selected.value === 'g') factor = 0.001;
        if (unit === 'ml' && selected.value === 'liter') factor = 1000;
        if (['liter', 'l'].includes(unit) && selected.value === 'ml') factor = 0.001;
        const inputLabel = selected.value === 'liter' ? 'L' : selected.value;
        const packageText = isPackage ? ` x ${content} ${unit}` : '';
        conversionPreviewField.textContent = `Hasil konversi: ${quantity} ${inputLabel}${packageText} = ${(quantity * factor).toLocaleString('id-ID')} ${unit}`;
    }

    function selectRequestKind(kind) {
        requestKind.value = kind;
        requestKindButtons.forEach(button => {
            const active = button.dataset.requestKind === kind;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        const isReduction = kind === 'keluar';
        reductionFields.hidden = !isReduction;
        stockChangeField.required = isReduction;
        inputUnitField.required = isReduction;
        reasonField.required = isReduction;
        packageContentField.required = isReduction && inputUnitField.options[inputUnitField.selectedIndex].dataset.package === '1';
        requestHelp.textContent = isReduction
            ? 'Stok berkurang setelah permintaan disetujui Owner.'
            : 'Owner akan menentukan supplier dan jumlah pembelian setelah permintaan disetujui.';
    }

    requestKindButtons.forEach(button => button.addEventListener('click', () => selectRequestKind(button.dataset.requestKind)));
    materialSearchField.addEventListener('input', function () {
        const keyword = this.value.toLowerCase().trim();
        materialOptions.forEach(option => {
            option.hidden = keyword !== '' && !option.textContent.toLowerCase().includes(keyword);
        });
    });
    materialSelectField.addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        const unit = selected.dataset.unit || '';
        currentStockField.value = this.value ? `${selected.dataset.stock} ${unit}` : '-';
        conversionTargetField.value = unit || '-';
        inputUnitField.value = unit;
        Array.from(inputUnitField.options).slice(1).forEach(option => {
            option.hidden = !option.dataset.package && option.dataset.category !== unitCategory(unit);
        });
        updateReductionConversion();
    });
    inputUnitField.addEventListener('change', updateReductionConversion);
    stockChangeField.addEventListener('input', updateReductionConversion);
    packageContentField.addEventListener('input', updateReductionConversion);
    selectRequestKind('keluar');
}

const reductionRows = document.getElementById('stockReductionRows');
const addReductionRow = document.getElementById('addStockReductionRow');
const reductionForm = document.getElementById('batchStockReductionForm');
function updateReductionRow(row) {
    const select = row.querySelector('.stock-row-material');
    const option = select.options[select.selectedIndex];
    const stock = option?.dataset.stock || '';
    const rawUnit = option?.dataset.unit || '-';
    const unit = ({gram: 'g', liter: 'L', l: 'L'})[rawUnit.toLowerCase()] || rawUnit.toLowerCase();
    row.querySelector('.stock-row-info').textContent = stock ? `Stok tersedia: ${Number(stock).toLocaleString('id-ID')} ${unit}` : 'Stok tersedia: -';
    row.querySelector('.stock-row-unit').textContent = unit;
    const amount = row.querySelector('input[name="jumlah[]"]');
    amount.max = stock || '';
}
function renumberReductionRows() {
    reductionRows.querySelectorAll('.stock-reduction-row').forEach((row, index) => {
        row.querySelector('.stock-row-number').textContent = index + 1;
        row.querySelector('.remove-stock-row').hidden = index === 0 && reductionRows.children.length === 1;
    });
}
if (reductionRows && addReductionRow) {
    reductionRows.addEventListener('change', event => {
        const row = event.target.closest('.stock-reduction-row');
        if (row && event.target.classList.contains('stock-row-material')) updateReductionRow(row);
    });
    reductionRows.addEventListener('click', event => {
        const button = event.target.closest('.remove-stock-row');
        if (!button) return;
        button.closest('.stock-reduction-row').remove();
        renumberReductionRows();
    });
    addReductionRow.addEventListener('click', () => {
        const row = reductionRows.firstElementChild.cloneNode(true);
        row.querySelectorAll('select').forEach(select => select.value = '');
        const amount = row.querySelector('input[name="jumlah[]"]');
        amount.value = '';
        amount.removeAttribute('max');
        row.querySelector('.stock-row-info').textContent = 'Stok tersedia: -';
        row.querySelector('.stock-row-unit').textContent = '-';
        reductionRows.appendChild(row);
        renumberReductionRows();
    });
    reductionForm.addEventListener('submit', event => {
        const selected = Array.from(reductionRows.querySelectorAll('.stock-row-material')).map(select => select.value).filter(Boolean);
        if (new Set(selected).size !== selected.length) {
            event.preventDefault();
            alert('Bahan yang sama tidak boleh dipilih lebih dari sekali.');
        }
    });
}

const requestSearch = document.getElementById('requestSearch');
const requestMaterialFilter = document.getElementById('requestMaterialFilter');
function filterRequestRows() {
        const keyword = requestSearch ? requestSearch.value.toLowerCase().trim() : '';
        const materialId = requestMaterialFilter ? requestMaterialFilter.value : '';
        let visible = 0;
        document.querySelectorAll('[data-request-row]').forEach(row => {
            const matchesText = (row.textContent + ' ' + row.dataset.search + ' ' + (row.querySelector('.detail-stock-request')?.dataset.items || '')).toLowerCase().includes(keyword);
            const materials = (row.dataset.materials || '').split(',');
            const matchesMaterial = !materialId || materials.includes(materialId);
            row.hidden = !matchesText || !matchesMaterial;
            if (!row.hidden) row.cells[0].textContent = ++visible;
        });
        document.getElementById('requestEmpty').hidden = visible > 0;
}
if (requestSearch) requestSearch.addEventListener('input', filterRequestRows);
if (requestMaterialFilter) requestMaterialFilter.addEventListener('change', filterRequestRows);

const stockDetailModal = document.getElementById('stockRequestDetailModal');
bindRequestButtons('.detail-stock-request', function () {
    const detail = JSON.parse(this.dataset.detail || '{}');
    const items = JSON.parse(this.dataset.items || '[]');
    const batchPanel = document.getElementById('stockBatchDetail');
    const batchBody = document.getElementById('stockBatchBody');
    batchBody.replaceChildren();
    batchPanel.hidden = items.length === 0 || this.dataset.kind !== 'Keluar';
    if (!batchPanel.hidden) {
        ['Nama Bahan', 'Jumlah Input', 'Hasil Konversi', 'Isi per Kemasan', 'Stok Saat Pengajuan', 'Usulan Stok Setelah Perubahan', 'Supplier', 'Total Pembelian', 'Keterangan'].forEach(key => delete detail[key]);
        detail['Jumlah Bahan'] = items.length + ' bahan';
        items.forEach((item, index) => {
            const row = document.createElement('tr');
            [index + 1, ...Object.values(item)].forEach(value => {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });
            batchBody.appendChild(row);
        });
    }
    document.getElementById('stockRequestDetail').replaceChildren(...Object.entries(detail).map(([label, value]) => {
        const item = document.createElement('div');
        const title = document.createElement('span');
        const content = document.createElement('strong');
        title.textContent = label;
        content.textContent = value ?? '-';
        item.append(title, content);
        return item;
    }));
    const note = document.getElementById('stockRequestNote');
    const noteUrl = this.dataset.noteUrl;
    if (note) note.hidden = !noteUrl;
    if (note && noteUrl) {
        document.getElementById('stockRequestNoteLink').href = noteUrl;
        document.getElementById('stockRequestNoteImage').src = noteUrl;
    }
    const actions = document.getElementById('stockReviewActions');
    if (actions) {
        actions.hidden = this.dataset.status !== 'Menunggu';
        document.getElementById('stockNextStep').hidden = !(this.dataset.kind === 'Restock' && this.dataset.status === 'Disetujui');
        document.getElementById('approve_stock_id').value = this.dataset.id;
        const reject = document.getElementById('rejectFromStockDetail');
        reject.dataset.id = this.dataset.id;
        reject.dataset.name = this.dataset.name;
    }
    stockDetailModal.style.display = 'flex';
    document.getElementById('closeStockRequestDetail').focus();
});
if (stockDetailModal) {
    document.getElementById('closeStockRequestDetail').addEventListener('click', () => stockDetailModal.style.display = 'none');
    window.addEventListener('click', event => { if (event.target === stockDetailModal) stockDetailModal.style.display = 'none'; });
}

const rejectModal = document.getElementById('rejectRequestModal');
const editStockModal = document.getElementById('editStockRequestModal');
bindRequestButtons('.edit-stock-request', function () {
    document.getElementById('edit_stock_action').value = this.dataset.action;
    document.getElementById('edit_stock_id').value = this.dataset.id;
    const rows = document.getElementById('editStockBatchRows');
    rows.replaceChildren();
    const items = JSON.parse(this.dataset.items || '[]');
    items.forEach((item, index) => {
        const section = document.createElement('section');
        section.className = 'edit-recipe-section';
        const title = document.createElement('h3');
        title.textContent = (index + 1) + '. ' + item.name;
        const grid = document.createElement('div');
        grid.className = 'request-stock-grid';
        const amountLabel = document.createElement('label');
        amountLabel.className = 'form-group';
        amountLabel.textContent = 'Jumlah (' + item.unit + ')';
        const amount = document.createElement('input');
        amount.type = 'number';
        amount.name = 'edit_amount[' + item.id + ']';
        amount.value = item.amount;
        amount.min = '0.01';
        amount.step = '0.01';
        amount.required = true;
        amountLabel.appendChild(amount);
        const reasonLabel = document.createElement('label');
        reasonLabel.className = 'form-group';
        reasonLabel.textContent = 'Keterangan';
        const reason = document.createElement('select');
        reason.name = 'edit_reason[' + item.id + ']';
        reason.required = true;
        ['', 'Stok Rusak', 'Stok Basi', 'Stok Tumpah', 'Selisih Stok Fisik', 'Dipakai Testing', 'Salah Input'].forEach(value => {
            reason.add(new Option(value || 'Pilih keterangan', value));
        });
        reason.value = item.reason;
        reasonLabel.appendChild(reason);
        grid.append(amountLabel, reasonLabel);
        section.append(title, grid);
        rows.appendChild(section);
    });
    editStockModal.style.display = 'flex';
});
if (editStockModal) {
    document.getElementById('closeEditStockRequest').addEventListener('click', () => editStockModal.style.display = 'none');
    window.addEventListener('click', event => { if (event.target === editStockModal) editStockModal.style.display = 'none'; });
}
document.querySelectorAll('.open-reject-modal').forEach(button => {
    button.addEventListener('click', function () {
        stockDetailModal.style.display = 'none';
        document.getElementById('reject_request_id').value = this.dataset.id;
        document.getElementById('reject_material_name').value = this.dataset.name;
        document.getElementById('review_note').value = '';
        rejectModal.style.display = 'flex';
    });
});
if (rejectModal) {
    document.getElementById('closeRejectModal').addEventListener('click', () => rejectModal.style.display = 'none');
    window.addEventListener('click', event => { if (event.target === rejectModal) rejectModal.style.display = 'none'; });
}
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        stockDetailModal.style.display = 'none';
        if (rejectModal) rejectModal.style.display = 'none';
        if (editStockModal) editStockModal.style.display = 'none';
    }
});
const targetOrderId = new URLSearchParams(window.location.search).get('order_id');
if (targetOrderId && /^\d+$/.test(targetOrderId)) {
    const target = Array.from(document.querySelectorAll('.detail-stock-request')).find(button => button.dataset.orderId === targetOrderId);
    if (target) target.click();
}

const stockRequestKind = document.getElementById('stockRequestKind');
if (stockRequestKind) {
    const requestForm = document.getElementById('batchStockReductionForm');
    const stockFields = document.getElementById('stockRequestFields');
    const changeFields = document.getElementById('materialChangeFields');
    const infoFields = document.getElementById('materialInfoFields');
    const packageFields = document.getElementById('materialPackageFields');
    const changeMaterial = document.getElementById('materialChangeBahan');
    const changeName = document.getElementById('materialChangeName');
    const changeMinimum = document.getElementById('materialChangeMinimum');
    const changeBaseUnit = document.getElementById('materialChangeBaseUnit');
    const packageUnit = document.getElementById('materialPackageUnit');
    const packageContent = document.getElementById('materialPackageContent');
    const packageLabel = document.getElementById('materialPackageContentLabel');
    const packagePreview = document.getElementById('materialPackagePreview');
    const baseUnit = document.getElementById('materialBaseUnit');
    const syncRequestKind = () => {
        const isStock = stockRequestKind.value === 'Stok';
        const isInfo = stockRequestKind.value === 'Info';
        stockFields.hidden = !isStock;
        changeFields.hidden = isStock;
        infoFields.hidden = !isInfo;
        packageFields.hidden = stockRequestKind.value !== 'Kemasan';
        stockFields.querySelectorAll('input, select, button').forEach(field => field.disabled = !isStock);
        changeFields.querySelectorAll('input, select, button').forEach(field => field.disabled = isStock);
        infoFields.querySelectorAll('input').forEach(field => field.required = isInfo);
        packageFields.querySelectorAll('input').forEach(field => field.required = stockRequestKind.value === 'Kemasan');
        requestForm.action = isStock ? '' : 'pengajuan_perubahan_bahan.php';
        if (isInfo && changeMaterial.value) {
            const option = changeMaterial.options[changeMaterial.selectedIndex];
            changeName.value = option.dataset.name || '';
            changeMinimum.value = option.dataset.minimum || '';
            changeBaseUnit.value = option.dataset.unit || 'g';
        }
        if (!isStock && changeMaterial.value) {
            const option = changeMaterial.options[changeMaterial.selectedIndex];
            const unit = option.dataset.unit || 'satuan dasar';
            baseUnit.value = unit;
            packageLabel.textContent = `Isi 1 ${packageUnit.value} dalam ${unit}`;
            packagePreview.textContent = packageContent.value ? `Konversi usulan: 1 ${packageUnit.value} = ${packageContent.value} ${unit}` : `Contoh: 1 ${packageUnit.value} = 1.000 ${unit}`;
        }
    };
    stockRequestKind.addEventListener('change', syncRequestKind);
    changeMaterial.addEventListener('change', syncRequestKind);
    packageUnit.addEventListener('change', syncRequestKind);
    packageContent.addEventListener('input', syncRequestKind);
    syncRequestKind();
}
</script>
</body>
</html>
