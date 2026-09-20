<?php
include '../config/database.php';
include '../includes/menu_requests.php';

if (($_GET['ajax'] ?? '') === 'pending_count') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    if (!auth_has_role(['owner', 'barista'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Tidak diizinkan.']);
        exit;
    }
    session_write_close();
    echo json_encode(requestNotificationSnapshot($conn));
    exit;
}

ensureMenuRequestTable($conn);
$is_owner = auth_has_role('owner');
$user = auth_current_user();
$user_id = (int) ($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !auth_verify_csrf($_POST['csrf_token'] ?? '')) {
    header('Location: permintaan.php?error=' . urlencode('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.'));
    exit;
}

$bahan_option_query = mysqli_query($conn, "SELECT id, nama_bahan, satuan FROM bahan_baku ORDER BY nama_bahan ASC");
$bahan_option_rows = $bahan_option_query ? mysqli_fetch_all($bahan_option_query, MYSQLI_ASSOC) : [];
$bahan_options_by_id = array_column($bahan_option_rows, null, 'id');

function redirect_menu_request($type, $message) {
    $action = $_POST['action'] ?? '';
    $tab = $type === 'success' && $action === 'approve' ? 'disetujui' : ($type === 'success' && $action === 'reject' ? 'ditolak' : 'menunggu');
    if ($type === 'error' && $action === 'resubmit') $tab = 'ditolak';
    header('Location: permintaan.php?tab=' . $tab . '&' . $type . '=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_owner) {
    $action = $_POST['action'] ?? '';
    $request_id = (int) ($_POST['request_id'] ?? 0);
    if (!in_array($action, ['edit_pending', 'resubmit', 'delete_pending'], true) || $request_id <= 0) {
        redirect_menu_request('error', 'Pengajuan tidak valid.');
    }

    $request_status = $action === 'resubmit' ? "status = 'Ditolak'" : "status = 'Menunggu'";
    $query = mysqli_query($conn, "SELECT * FROM permintaan_menu WHERE id = $request_id AND user_id = $user_id AND $request_status LIMIT 1");
    $request = $query ? mysqli_fetch_assoc($query) : null;
    if (!$request) redirect_menu_request('error', 'Pengajuan sudah diproses atau tidak ditemukan.');
    $data = json_decode($request['data_json'], true) ?: [];
    if ($action === 'resubmit') {
        if (!isset($data['riwayat_penolakan']) || !is_array($data['riwayat_penolakan'])) {
            $data['riwayat_penolakan'] = [];
        }
        $data['riwayat_penolakan'][] = [
            'alasan' => $request['review_note'],
            'tanggal' => $request['reviewed_at'],
            'pemeriksa_id' => $request['reviewed_by'],
        ];
    }

    if ($action === 'delete_pending') {
        $deleted = mysqli_query($conn, "DELETE FROM permintaan_menu WHERE id = $request_id AND user_id = $user_id AND status = 'Menunggu'");
        if (!$deleted) redirect_menu_request('error', 'Pengajuan gagal dihapus.');
        if (!empty($data['foto']) && ($request['jenis'] === 'Tambah Menu' || !empty($data['foto_baru']))) {
            $photo_path = __DIR__ . '/uploads/' . basename($data['foto']);
            if (is_file($photo_path)) unlink($photo_path);
        }
        redirect_menu_request('success', 'Pengajuan berhasil dihapus.');
    }

    if (in_array($request['jenis'], ['Tambah Menu', 'Ubah Menu'], true)) {
        $name = trim($_POST['nama_menu'] ?? '');
        $category = trim($_POST['kategori'] ?? '');
        if ($name === '' || !in_array($category, ['Coffee', 'Non Coffee'], true)) redirect_menu_request('error', 'Data menu belum lengkap.');
        $data['nama_menu'] = $name;
        $data['kategori'] = $category;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) redirect_menu_request('error', 'Format foto harus JPG, JPEG, PNG, atau WEBP.');
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $new_photo = time() . '_' . uniqid() . '.' . $ext;
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $new_photo)) redirect_menu_request('error', 'Foto gagal diunggah.');
            $data['foto'] = $new_photo;
            $data['foto_baru'] = true;
        }
    } elseif (in_array($request['jenis'], ['Tambah Resep', 'Ubah Resep'], true)) {
        $edit_ids = array_map('intval', (array) ($_POST['edit_bahan_id'] ?? []));
        $edit_amounts = array_map('floatval', (array) ($_POST['edit_jumlah'] ?? []));
        $items = [];
        foreach ($edit_ids as $index => $edit_id) {
            $items[] = ['bahan_id' => $edit_id, 'jumlah' => $edit_amounts[$index] ?? 0];
        }
        $extra_ids = array_map('intval', (array) ($_POST['tambahan_bahan_id'] ?? []));
        $extra_amounts = array_map('floatval', (array) ($_POST['tambahan_jumlah'] ?? []));
        foreach ($extra_ids as $index => $extra_id) {
            $extra_amount = $extra_amounts[$index] ?? 0;
            if ($extra_id === 0 && $extra_amount == 0) continue;
            $items[] = ['bahan_id' => $extra_id, 'jumlah' => $extra_amount];
        }
        try {
            $items = validateMenuRecipeItems($conn, $items);
            if ($request['jenis'] === 'Ubah Resep') {
                $recipe_id = (int) ($data['resep_id'] ?? 0);
                $menu_id = (int) $request['menu_id'];
                $recipe_query = mysqli_query($conn, "SELECT bahan_id FROM menu_resep WHERE id = $recipe_id AND menu_id = $menu_id");
                $recipe = $recipe_query ? mysqli_fetch_assoc($recipe_query) : null;
                if (!$recipe || (int) $recipe['bahan_id'] !== $items[0]['bahan_id']) {
                    throw new RuntimeException('Resep asal sudah berubah atau tidak ditemukan. Silakan ajukan kembali dari daftar resep.');
                }
            }
        } catch (Exception $exception) {
            redirect_menu_request('error', $exception->getMessage());
        }
        $data['jumlah'] = $items[0]['jumlah'];
        $data['resep_items'] = $items;
    } else {
        redirect_menu_request('error', 'Pengajuan ini cukup dihapus lalu diajukan kembali.');
    }

    $data_safe = mysqli_real_escape_string($conn, json_encode($data, JSON_UNESCAPED_UNICODE));
    $reset_review = $action === 'resubmit' ? ", status = 'Menunggu', reviewed_by = NULL, review_note = NULL, reviewed_at = NULL, created_at = NOW()" : '';
    $updated = mysqli_query($conn, "UPDATE permintaan_menu SET data_json = '$data_safe'$reset_review WHERE id = $request_id AND user_id = $user_id AND $request_status");
    redirect_menu_request($updated ? 'success' : 'error', $updated ? 'Pengajuan berhasil diperbarui.' : 'Pengajuan gagal diperbarui.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_owner) {
    $action = $_POST['action'] ?? '';
    $request_id = (int) ($_POST['request_id'] ?? 0);
    if (!in_array($action, ['approve', 'reject'], true) || $request_id <= 0) {
        redirect_menu_request('error', 'Pengajuan tidak valid.');
    }

    mysqli_begin_transaction($conn);
    try {
        $query = mysqli_query($conn, "
            SELECT * FROM permintaan_menu
            WHERE id = $request_id AND status = 'Menunggu'
            FOR UPDATE
        ");
        $request = $query ? mysqli_fetch_assoc($query) : null;
        if (!$request) throw new Exception('Pengajuan sudah diproses atau tidak ditemukan.');

        $data = json_decode($request['data_json'], true) ?: [];
        $photo_to_delete = '';

        if ($action === 'reject') {
            $note = trim($_POST['review_note'] ?? '');
            if ($note === '') throw new Exception('Alasan penolakan wajib diisi.');
            $note_safe = mysqli_real_escape_string($conn, $note);
            $update = mysqli_query($conn, "
                UPDATE permintaan_menu
                SET status = 'Ditolak', reviewed_by = $user_id,
                    review_note = '$note_safe', reviewed_at = NOW()
                WHERE id = $request_id
            ");
            if (!$update) throw new Exception(mysqli_error($conn));
        } else {
            $menu_id = (int) $request['menu_id'];
            if ($request['jenis'] === 'Tambah Menu') {
                $name = mysqli_real_escape_string($conn, trim($data['nama_menu'] ?? ''));
                $category = mysqli_real_escape_string($conn, $data['kategori'] ?? '');
                $price = 0;
                $profit = 0;
                $photo = !empty($data['foto']) ? "'" . mysqli_real_escape_string($conn, basename($data['foto'])) . "'" : 'NULL';
                $change = mysqli_query($conn, "
                    INSERT INTO menu (nama_menu, kategori, harga_jual, keuntungan, foto, status)
                    VALUES ('$name', '$category', '$price', '$profit', $photo, 'Nonaktif')
                ");
                if (!$change) throw new Exception(mysqli_error($conn));
                $menu_id = (int) mysqli_insert_id($conn);
                $recipe_items = validateMenuRecipeItems($conn, $data['resep_items'] ?? []);
                foreach ($recipe_items as $recipe_item) {
                    $ingredient_id = (int) $recipe_item['bahan_id'];
                    $amount = (float) $recipe_item['jumlah'];
                    if (!mysqli_query($conn, "INSERT INTO menu_resep (menu_id, bahan_id, jumlah) VALUES ($menu_id, $ingredient_id, '$amount')")) {
                        throw new Exception(mysqli_error($conn));
                    }
                }
            } elseif ($request['jenis'] === 'Ubah Menu') {
                $menu_query = mysqli_query($conn, "SELECT foto, harga_jual, keuntungan FROM menu WHERE id = $menu_id FOR UPDATE");
                $menu = $menu_query ? mysqli_fetch_assoc($menu_query) : null;
                if (!$menu) throw new Exception('Menu tidak ditemukan.');
                $name = mysqli_real_escape_string($conn, trim($data['nama_menu'] ?? ''));
                $category = mysqli_real_escape_string($conn, $data['kategori'] ?? '');
                $status = mysqli_real_escape_string($conn, $data['status'] ?? 'Aktif');
                $price = (float) $menu['harga_jual'];
                $profit = (float) $menu['keuntungan'];
                $photo_name = basename($data['foto'] ?? '');
                $photo = $photo_name !== '' ? "'" . mysqli_real_escape_string($conn, $photo_name) . "'" : 'NULL';
                $change = mysqli_query($conn, "
                    UPDATE menu SET nama_menu = '$name', kategori = '$category',
                        harga_jual = '$price', keuntungan = '$profit', foto = $photo, status = '$status'
                    WHERE id = $menu_id
                ");
                if (!empty($data['foto_baru']) && !empty($menu['foto']) && $menu['foto'] !== $photo_name) {
                    $photo_to_delete = basename($menu['foto']);
                }
            } elseif (in_array($request['jenis'], ['Tambah Resep', 'Ubah Resep'], true)) {
                $menu_query = mysqli_query($conn, "SELECT id FROM menu WHERE id = $menu_id FOR UPDATE");
                if (!$menu_query || !mysqli_num_rows($menu_query)) throw new Exception('Menu tidak ditemukan.');
                $recipe_id = (int) ($data['resep_id'] ?? 0);
                $recipe = null;
                if ($request['jenis'] === 'Ubah Resep') {
                    $recipe_query = mysqli_query($conn, "SELECT bahan_id FROM menu_resep WHERE id = $recipe_id AND menu_id = $menu_id FOR UPDATE");
                    $recipe = $recipe_query ? mysqli_fetch_assoc($recipe_query) : null;
                    if (!$recipe) throw new Exception('Resep asal tidak ditemukan.');
                    $data['bahan_id'] = (int) $recipe['bahan_id'];
                }
                $items = validateMenuRecipeItems($conn, $data['resep_items'] ?? [$data]);
                if ($recipe && $items[0]['bahan_id'] !== (int) $recipe['bahan_id']) throw new Exception('Bahan resep asal tidak sesuai.');
                foreach ($items as $index => $item) {
                    $ingredient_id = $item['bahan_id'];
                    $amount = $item['jumlah'];
                    if ($recipe && $index === 0) {
                        $change = mysqli_query($conn, "UPDATE menu_resep SET jumlah = '$amount' WHERE id = $recipe_id AND menu_id = $menu_id");
                    } else {
                        $existing = mysqli_query($conn, "SELECT id FROM menu_resep WHERE menu_id = $menu_id AND bahan_id = $ingredient_id FOR UPDATE");
                        if (!$existing) throw new Exception(mysqli_error($conn));
                        if (mysqli_num_rows($existing)) throw new Exception('Bahan ' . $item['nama_bahan'] . ' sudah ada dalam resep. Silakan periksa pengajuan.');
                        $change = mysqli_query($conn, "INSERT INTO menu_resep (menu_id, bahan_id, jumlah) VALUES ($menu_id, $ingredient_id, '$amount')");
                    }
                    if (!$change) throw new Exception(mysqli_error($conn));
                }
            } elseif ($request['jenis'] === 'Hapus Resep') {
                $recipe_id = (int) ($data['resep_id'] ?? 0);
                $change = mysqli_query($conn, "DELETE FROM menu_resep WHERE id = $recipe_id AND menu_id = $menu_id");
            } else {
                throw new Exception('Jenis permintaan tidak dikenali.');
            }

            if (!$change) throw new Exception(mysqli_error($conn));
            $finish = mysqli_query($conn, "
                UPDATE permintaan_menu
                SET status = 'Disetujui', reviewed_by = $user_id, reviewed_at = NOW()
                WHERE id = $request_id
            ");
            if (!$finish) throw new Exception(mysqli_error($conn));
        }

        mysqli_commit($conn);
        if ($photo_to_delete !== '') {
            $photo_path = __DIR__ . '/uploads/' . $photo_to_delete;
            if (is_file($photo_path)) unlink($photo_path);
        }
        redirect_menu_request('success', $action === 'approve' ? 'Pengajuan menu berhasil disetujui.' : 'Pengajuan menu ditolak.');
    } catch (Exception $exception) {
        mysqli_rollback($conn);
        redirect_menu_request('error', $exception->getMessage());
    }
}

$where = $is_owner ? '1=1' : 'pm.user_id = ' . $user_id;
$requests = mysqli_query($conn, "
    SELECT pm.*, u.name AS pemohon, reviewer.name AS pemeriksa, m.nama_menu,
           m.kategori AS kategori_sekarang, m.harga_jual AS harga_sekarang,
           m.keuntungan AS keuntungan_sekarang, m.status AS status_sekarang
    FROM permintaan_menu pm
    JOIN users u ON u.id = pm.user_id
    LEFT JOIN users reviewer ON reviewer.id = pm.reviewed_by
    LEFT JOIN menu m ON m.id = pm.menu_id
    WHERE $where
    ORDER BY (pm.status = 'Menunggu') DESC,
             COALESCE(pm.reviewed_at, pm.created_at) DESC,
             pm.id DESC
");
$success = trim($_GET['success'] ?? '');
$error = trim($_GET['error'] ?? '');
$request_tabs = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'];
$active_tab = $_GET['tab'] ?? 'menunggu';
if (!is_string($active_tab) || !isset($request_tabs[$active_tab])) $active_tab = 'menunggu';
$request_rows = $requests ? mysqli_fetch_all($requests, MYSQLI_ASSOC) : [];
$tab_counts = array_fill_keys(array_keys($request_tabs), 0);
foreach ($request_rows as $row) {
    $key = strtolower($row['status']);
    if (isset($tab_counts[$key])) $tab_counts[$key]++;
}
$request_rows = array_filter($request_rows, function ($row) use ($active_tab) {
    return strtolower($row['status']) === $active_tab;
});
$recipe_details = [];
$recipes_by_menu = [];
$recipe_menu_ids = [];
foreach ($request_rows as $row) {
    if ((int) $row['menu_id'] > 0) $recipe_menu_ids[] = (int) $row['menu_id'];
}
if ($recipe_menu_ids) {
    $ids = implode(',', array_unique($recipe_menu_ids));
    $recipe_query = mysqli_query($conn, "
        SELECT r.id, r.menu_id, r.bahan_id, r.jumlah, b.nama_bahan, b.satuan
        FROM menu_resep r JOIN bahan_baku b ON b.id = r.bahan_id
        WHERE r.menu_id IN ($ids)
    ");
    while ($recipe = mysqli_fetch_assoc($recipe_query)) {
        $recipe_details[(int) $recipe['id']] = $recipe;
        $recipes_by_menu[(int) $recipe['menu_id']][] = $recipe;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Menu</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/bahan.css?v=<?= filemtime(__DIR__ . '/../css/bahan.css') ?>">
</head>
<body>
<?php $current_module = 'permintaan_menu'; include '../includes/sidebar.php'; ?>
<div class="main-content">
    <div class="header"><div>
        <h1>Pengajuan Menu</h1>
        <p><?= $is_owner ? 'Tinjau perubahan menu dan resep yang diajukan Barista' : 'Pantau status perubahan menu dan resep yang diajukan' ?></p>
    </div>
    <?php if (!$is_owner): ?><a href="index.php" class="btn-primary">Ajukan dari Daftar Menu</a><?php endif; ?>
    </div>

    <?php if ($success): ?><div class="request-notice request-notice-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="request-notice request-notice-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <nav class="workflow-tabs" aria-label="Status pengajuan menu">
        <?php foreach ($request_tabs as $key => $label): ?>
            <a href="?tab=<?= $key ?>"<?= $key === $active_tab ? ' aria-current="page"' : '' ?>><?= $label ?><span class="workflow-count"><?= $tab_counts[$key] ?></span></a>
        <?php endforeach; ?>
    </nav>
    <p class="workflow-note"><?= $is_owner ? 'Buka detail untuk memeriksa usulan, kemudian setujui atau tolak pengajuan.' : 'Menu dan resep diterapkan setelah pengajuan disetujui Owner.' ?></p>
    <div class="table-card">
        <div class="search-box bahan-search-box request-history-search"><input type="text" id="requestSearch" placeholder="Cari menu, jenis, atau status..."></div>
        <div class="workflow-table-wrap"><table>
            <thead><tr><th>No</th><?php if ($is_owner): ?><th>Pemohon</th><?php endif; ?><th>Jenis</th><th>Menu</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead>
            <tbody id="requestTableBody">
            <?php if ($request_rows): $no = 1; ?>
                <?php foreach ($request_rows as $request):
                    $data = json_decode($request['data_json'], true) ?: [];
                    $recipe = $recipe_details[(int) ($data['resep_id'] ?? 0)] ?? [];
                    if ($recipe) {
                        foreach (['bahan_id', 'nama_bahan', 'satuan'] as $field) {
                            if (empty($data[$field])) $data[$field] = $recipe[$field];
                        }
                    }
                    if (!empty($data['bahan_id'])) {
                        $ingredient = $bahan_options_by_id[(int) $data['bahan_id']] ?? [];
                        $data['satuan'] = stockInputUnitLabel($ingredient['satuan'] ?? ($data['satuan'] ?? ''));
                    }
                    if (!empty($data['resep_items'])) {
                        foreach ($data['resep_items'] as &$stored_recipe_item) {
                            $ingredient = $bahan_options_by_id[(int) ($stored_recipe_item['bahan_id'] ?? 0)] ?? [];
                            $stored_recipe_item['satuan'] = stockInputUnitLabel($ingredient['satuan'] ?? ($stored_recipe_item['satuan'] ?? ''));
                        }
                        unset($stored_recipe_item);
                    }
                    $recipe_rows = [];
                    if ($request['jenis'] === 'Tambah Menu' && !empty($data['resep_items'])) {
                        foreach ($data['resep_items'] as $recipe_item) {
                            $recipe_rows[] = ['nama' => $recipe_item['nama_bahan'] ?? '-', 'jumlah' => $recipe_item['jumlah'] ?? 0, 'satuan' => $recipe_item['satuan'] ?? ''];
                        }
                    } elseif (strpos($request['jenis'], 'Resep') !== false) {
                        if (!empty($data['resep_items'])) {
                            foreach ($data['resep_items'] as $recipe_item) {
                                $recipe_rows[] = ['nama' => $recipe_item['nama_bahan'] ?? '-', 'jumlah' => $recipe_item['jumlah'] ?? 0, 'satuan' => $recipe_item['satuan']];
                            }
                        } else {
                        $recipe_name = $data['nama_bahan'] ?? ($recipe['nama_bahan'] ?? '-');
                        $recipe_unit = $recipe['satuan'] ?? ($data['satuan'] ?? '');
                        $recipe_rows[] = ['nama' => $recipe_name, 'jumlah' => $data['jumlah'] ?? ($recipe['jumlah'] ?? 0), 'satuan' => $recipe_unit];
                        }
                    } elseif ((int) $request['menu_id'] > 0) {
                        $menu_recipe_query = mysqli_query($conn, "SELECT b.nama_bahan, r.jumlah, b.satuan FROM menu_resep r JOIN bahan_baku b ON b.id = r.bahan_id WHERE r.menu_id = " . (int) $request['menu_id'] . " ORDER BY b.nama_bahan ASC");
                        while ($menu_recipe_query && $menu_recipe = mysqli_fetch_assoc($menu_recipe_query)) $recipe_rows[] = ['nama' => $menu_recipe['nama_bahan'], 'jumlah' => $menu_recipe['jumlah'], 'satuan' => $menu_recipe['satuan']];
                    }
                    foreach ($recipe_rows as &$recipe_row) $recipe_row['satuan'] = stockInputUnitLabel($recipe_row['satuan']);
                    unset($recipe_row);
                    $recipe_before = array_map(function ($item) {
                        return ['id' => (int) $item['id'], 'bahan_id' => (int) $item['bahan_id'], 'nama' => $item['nama_bahan'], 'jumlah' => (float) $item['jumlah'], 'satuan' => stockInputUnitLabel($item['satuan'])];
                    }, $recipes_by_menu[(int) $request['menu_id']] ?? []);
                    $recipe_after = $recipe_before;
                    if ($request['jenis'] === 'Tambah Menu') {
                        $recipe_before = [];
                        $recipe_after = $recipe_rows;
                    } elseif ($request['status'] !== 'Disetujui') {
                        if ($request['jenis'] === 'Tambah Resep') {
                            $recipe_after = array_merge($recipe_before, $recipe_rows);
                        } elseif ($request['jenis'] === 'Ubah Resep') {
                            $target_recipe_id = (int) ($data['resep_id'] ?? 0);
                            $replacement = $recipe_rows;
                            $recipe_after = [];
                            foreach ($recipe_before as $old_recipe) {
                                if ((int) $old_recipe['id'] === $target_recipe_id) {
                                    if ($replacement) $recipe_after[] = array_shift($replacement);
                                } else {
                                    $recipe_after[] = $old_recipe;
                                }
                            }
                            $recipe_after = array_merge($recipe_after, $replacement);
                        } elseif ($request['jenis'] === 'Hapus Resep') {
                            $target_recipe_id = (int) ($data['resep_id'] ?? 0);
                            $recipe_after = array_values(array_filter($recipe_before, function ($item) use ($target_recipe_id) {
                                return (int) $item['id'] !== $target_recipe_id;
                            }));
                        }
                    }
                ?>
                <tr data-request-row>
                    <td><?= $no++ ?></td>
                    <?php if ($is_owner): ?><td><?= htmlspecialchars($request['pemohon']) ?></td><?php endif; ?>
                    <td><span class="badge badge-warning"><?= htmlspecialchars(menuRequestLabel($request['jenis'])) ?></span></td>
                    <td><?= htmlspecialchars($request['nama_menu'] ?: ($data['nama_menu'] ?? '-')) ?></td>
                    <td><span class="badge <?= $request['status'] === 'Disetujui' ? 'badge-success' : ($request['status'] === 'Ditolak' ? 'badge-danger' : 'badge-warning') ?>"><?= htmlspecialchars($request['status']) ?></span></td>
                    <td><?= date('d/m/Y H:i', strtotime($request['status'] !== 'Menunggu' && !empty($request['reviewed_at']) ? $request['reviewed_at'] : $request['created_at'])) ?></td>
                    <td>
                        <div class="action-group">
                        <button type="button" class="btn-stock detail-menu-request"
                            data-id="<?= (int) $request['id'] ?>"
                            data-status="<?= htmlspecialchars($request['status'], ENT_QUOTES) ?>"
                            data-note="<?= htmlspecialchars($request['review_note'] ?? '', ENT_QUOTES) ?>"
                            data-reviewer="<?= htmlspecialchars($request['pemeriksa'] ?? '-', ENT_QUOTES) ?>"
                            data-date="<?= date('d/m/Y H:i', strtotime($request['created_at'])) ?>"
                            data-review-date="<?= !empty($request['reviewed_at']) ? date('d/m/Y H:i', strtotime($request['reviewed_at'])) : '' ?>"
                            data-type="<?= htmlspecialchars($request['jenis'], ENT_QUOTES) ?>"
                            data-menu="<?= htmlspecialchars($request['nama_menu'] ?: ($data['nama_menu'] ?? '-'), ENT_QUOTES) ?>"
                            data-applicant="<?= htmlspecialchars($request['pemohon'], ENT_QUOTES) ?>"
                            data-payload="<?= htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
                            data-recipe="<?= htmlspecialchars(json_encode($recipe_rows, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
                            data-recipe-before="<?= htmlspecialchars(json_encode($recipe_before, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
                            data-recipe-after="<?= htmlspecialchars(json_encode($recipe_after, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
                            data-current="<?= htmlspecialchars(json_encode([
                                'nama_menu' => $request['nama_menu'],
                                'kategori' => $request['kategori_sekarang'],
                                ...($is_owner ? [
                                'harga_jual' => $request['harga_sekarang'],
                                'keuntungan' => $request['keuntungan_sekarang'],
                                ] : []),
                                'status' => $request['status_sekarang'],
                                'nama_bahan' => $recipe['nama_bahan'] ?? null,
                                'jumlah' => $recipe['jumlah'] ?? null,
                                'satuan' => $recipe['satuan'] ?? ($data['satuan'] ?? ''),
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>">Detail</button>
                        <?php if (!$is_owner && in_array($request['status'], ['Menunggu', 'Ditolak'], true)): ?>
                            <?php if (in_array($request['jenis'], ['Tambah Menu', 'Ubah Menu', 'Tambah Resep', 'Ubah Resep'], true)): ?>
                            <button type="button" class="<?= $request['status'] === 'Ditolak' ? 'btn-primary' : 'btn-edit' ?> edit-menu-request"
                                data-id="<?= (int) $request['id'] ?>"
                                data-type="<?= htmlspecialchars($request['jenis'], ENT_QUOTES) ?>"
                                data-action="<?= $request['status'] === 'Ditolak' ? 'resubmit' : 'edit_pending' ?>"
                                data-menu="<?= htmlspecialchars($request['nama_menu'] ?: ($data['nama_menu'] ?? '-'), ENT_QUOTES) ?>"
                                data-payload="<?= htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"><?= $request['status'] === 'Ditolak' ? 'Ajukan Lagi' : 'Edit' ?></button>
                            <?php endif; ?>
                            <?php if ($request['status'] === 'Menunggu'): ?><form method="POST" onsubmit="return confirm('Hapus pengajuan ini?')">
                                <?= auth_csrf_input() ?>
                                <input type="hidden" name="action" value="delete_pending">
                                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                <button type="submit" class="btn-delete">Hapus</button>
                            </form><?php endif; ?>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr id="requestEmpty" class="workflow-empty"<?= $request_rows ? ' hidden' : '' ?>><td colspan="<?= $is_owner ? 7 : 6 ?>">Belum ada pengajuan pada status ini.</td></tr>
            </tbody>
        </table></div>
    </div>
</div>

<div id="menuRequestDetailModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="menuDetailTitle"><div class="modal-content workflow-modal">
    <div class="modal-header"><h2 id="menuDetailTitle">Detail Pengajuan Menu</h2><button type="button" class="close-btn" id="closeMenuRequestDetail" aria-label="Tutup detail">&times;</button></div>
    <div class="request-detail-list" id="menuRequestDetail"></div>
    <div class="workflow-table-wrap" id="menuComparison" hidden>
        <table><thead><tr><th>Data</th><th>Saat Ini</th><th>Usulan</th></tr></thead><tbody id="menuComparisonRows"></tbody></table>
    </div>
    <div class="menu-recipe-comparison" id="menuRecipePanel" hidden>
        <section><h3 class="request-section-heading">Resep Sebelumnya</h3><div class="workflow-table-wrap"><table><thead><tr><th>No</th><th>Bahan</th><th>Jumlah</th><th>Satuan</th></tr></thead><tbody id="menuRecipeBeforeRows"></tbody></table></div></section>
        <section><h3 class="request-section-heading">Resep Setelah Pengajuan</h3><div class="workflow-table-wrap"><table><thead><tr><th>No</th><th>Bahan</th><th>Jumlah</th><th>Satuan</th></tr></thead><tbody id="menuRecipeAfterRows"></tbody></table></div></section>
    </div>
    <?php if ($is_owner): ?>
    <div class="workflow-review-actions" id="menuReviewActions" hidden>
        <button type="button" class="btn-delete reject-menu-request" id="rejectFromMenuDetail">Tolak</button>
        <form method="POST" onsubmit="return confirm('Setujui dan terapkan perubahan menu ini?')">
            <?= auth_csrf_input() ?>
            <input type="hidden" name="action" value="approve"><input type="hidden" name="request_id" id="approve_menu_id">
            <button type="submit" class="btn-primary">Setujui</button>
        </form>
    </div>
    <?php endif; ?>
</div></div>

<?php if ($is_owner): ?><div id="rejectMenuModal" class="modal"><div class="modal-content">
    <div class="modal-header"><h2>Tolak Pengajuan</h2><button type="button" class="close-btn" id="closeRejectMenu">&times;</button></div>
    <form method="POST"><?= auth_csrf_input() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" id="reject_menu_id">
        <div class="form-group"><label>Menu</label><input type="text" id="reject_menu_name" readonly></div>
        <div class="form-group"><label>Alasan Penolakan</label><textarea name="review_note" rows="3" maxlength="255" required></textarea></div>
        <button class="btn-delete">Tolak Pengajuan</button>
    </form>
</div></div><?php endif; ?>

<?php if (!$is_owner): ?><div id="editMenuRequestModal" class="modal"><div class="modal-content">
    <div class="modal-header"><h2>Edit Pengajuan</h2><button type="button" class="close-btn" id="closeEditMenuRequest">&times;</button></div>
    <form method="POST" enctype="multipart/form-data">
        <?= auth_csrf_input() ?>
        <input type="hidden" name="action" value="edit_pending" id="edit_request_action"><input type="hidden" name="request_id" id="edit_request_id">
        <div class="form-group"><label>Jenis Pengajuan</label><input type="text" id="edit_request_type" readonly></div>
        <div id="editRequestMenuFields">
            <div class="form-group"><label>Nama Menu</label><input type="text" name="nama_menu" id="edit_request_name"></div>
            <div class="form-group"><label>Kategori</label><select name="kategori" id="edit_request_category"><option value="Coffee">Coffee</option><option value="Non Coffee">Non Coffee</option></select></div>
            <div class="form-group"><label>Foto Menu (opsional)</label>
                <img id="editRequestPhoto" class="request-detail-photo" alt="Foto menu saat ini" hidden>
                <input type="file" id="editRequestPhotoInput" name="foto" accept=".jpg,.jpeg,.png,.webp">
                <img id="editRequestNewPhoto" class="request-detail-photo" alt="Preview foto pengganti" hidden>
            </div>
        </div>
        <div id="editRequestRecipeFields" hidden>
            <section class="edit-recipe-section">
                <h3>Bahan Resep Saat Ini</h3>
                <p class="edit-recipe-help">Periksa dan sesuaikan jumlah setiap bahan sebelum menyimpan.</p>
                <div id="editExistingRecipeRows" class="edit-existing-recipe-rows"></div>
            </section>
            <section class="edit-recipe-section edit-recipe-add-section">
                <h3>Tambah Bahan</h3>
                <p class="edit-recipe-help">Opsional, jika resep membutuhkan bahan tambahan.</p>
                <div class="edit-recipe-add-row">
                    <div class="form-group"><label>Bahan Tambahan</label><select name="tambahan_bahan_id[]"><option value="">Pilih bahan tambahan</option><?php foreach ($bahan_option_rows as $bahan_option): ?><option value="<?= (int) $bahan_option['id'] ?>" data-unit="<?= htmlspecialchars(stockInputUnitLabel($bahan_option['satuan'])) ?>"><?= htmlspecialchars($bahan_option['nama_bahan']) ?> (<?= htmlspecialchars(stockInputUnitLabel($bahan_option['satuan'])) ?>)</option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="additional-recipe-amount-label">Jumlah</label><input type="number" step="0.01" min="0.01" name="tambahan_jumlah[]" placeholder="0"></div>
                </div>
                <div id="additionalRecipeRows"></div>
                <button type="button" class="btn-secondary edit-recipe-add-button" onclick="addAdditionalRecipeRow()">+ Tambah Bahan Lagi</button>
            </section>
        </div>
        <button type="submit" class="btn-primary">Simpan Perubahan</button>
    </form>
</div></div><?php endif; ?>
<script>
const search = document.getElementById('requestSearch');
search.addEventListener('input', function () {
    const keyword = this.value.toLowerCase().trim();
    let visible = 0;
    document.querySelectorAll('[data-request-row]').forEach(row => {
        row.hidden = !row.textContent.toLowerCase().includes(keyword);
        if (!row.hidden) row.cells[0].textContent = ++visible;
    });
    document.getElementById('requestEmpty').hidden = visible > 0;
});
const rejectModal = document.getElementById('rejectMenuModal');
const detailModal = document.getElementById('menuRequestDetailModal');
bindRequestButtons('.detail-menu-request', function () {
    const payload = JSON.parse(this.dataset.payload || '{}');
    const current = JSON.parse(this.dataset.current || '{}');
    const recipeBefore = JSON.parse(this.dataset.recipeBefore || '[]');
    const recipeAfter = JSON.parse(this.dataset.recipeAfter || '[]');
    const renderRecipeRows = (targetId, recipes, emptyText) => {
        const target = document.getElementById(targetId);
        if (!recipes.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.textContent = emptyText;
            row.appendChild(cell);
            target.replaceChildren(row);
            return;
        }
        target.replaceChildren(...recipes.map((recipe, index) => {
        const row = document.createElement('tr');
        [index + 1, recipe.nama, Number(recipe.jumlah).toLocaleString('id-ID'), recipe.satuan || '-'].forEach(value => { const cell = document.createElement('td'); cell.textContent = value; row.append(cell); });
        return row;
        }));
    };
    const hasRecipeChange = this.dataset.type.includes('Resep') || this.dataset.type === 'Tambah Menu';
    document.getElementById('menuRecipePanel').hidden = !hasRecipeChange;
    renderRecipeRows('menuRecipeBeforeRows', recipeBefore, 'Belum ada resep');
    renderRecipeRows('menuRecipeAfterRows', recipeAfter, 'Tidak ada resep');
    const comparisons = [];
    [['nama_menu', 'Nama Menu'], ['kategori', 'Kategori'], ['status', 'Status']].forEach(([key, label]) => {
        if (payload[key] !== undefined) {
            const suffix = key === 'jumlah' ? ' ' + (current.satuan || payload.satuan || '') : '';
            comparisons.push([label, current[key] == null ? '-' : current[key] + suffix, payload[key] + suffix]);
        }
    });
    document.getElementById('menuComparison').hidden = !comparisons.length;
    document.getElementById('menuComparisonRows').replaceChildren(...comparisons.map(values => {
        const row = document.createElement('tr');
        values.forEach(value => { const cell = document.createElement('td'); cell.textContent = value; row.append(cell); });
        return row;
    }));
    const rows = [
        ['Pemohon', this.dataset.applicant],
        ['Jenis Pengajuan', this.dataset.type],
        ['Menu', this.dataset.menu],
        ['Status Pengajuan', this.dataset.status],
        ['Tanggal Pengajuan', this.dataset.date],
        ['Diperiksa oleh', this.dataset.reviewer],
    ];
    if (this.dataset.status === 'Disetujui' && this.dataset.reviewDate) rows.splice(5, 0, ['Tanggal Disetujui', this.dataset.reviewDate]);
    if (this.dataset.status === 'Ditolak' && this.dataset.reviewDate) rows.splice(5, 0, ['Tanggal Ditolak', this.dataset.reviewDate]);
    if (payload.nama_menu) rows.push(['Nama Menu Usulan', payload.nama_menu]);
    if (payload.kategori) rows.push(['Kategori', current.kategori && current.kategori !== payload.kategori ? `${current.kategori} → ${payload.kategori}` : payload.kategori]);
    if (payload.harga_jual !== undefined) rows.push(['Harga Jual', current.harga_jual && Number(current.harga_jual) !== Number(payload.harga_jual) ? `Rp${Number(current.harga_jual).toLocaleString('id-ID')} → Rp${Number(payload.harga_jual).toLocaleString('id-ID')}` : `Rp${Number(payload.harga_jual).toLocaleString('id-ID')}`]);
    if (payload.keuntungan !== undefined) rows.push(['Keuntungan', current.keuntungan && Number(current.keuntungan) !== Number(payload.keuntungan) ? `Rp${Number(current.keuntungan).toLocaleString('id-ID')} → Rp${Number(payload.keuntungan).toLocaleString('id-ID')}` : `Rp${Number(payload.keuntungan).toLocaleString('id-ID')}`]);
    if (payload.status) rows.push(['Status', current.status && current.status !== payload.status ? `${current.status} → ${payload.status}` : payload.status]);
    if (this.dataset.type === 'Hapus Resep') rows.push(['Perubahan Usulan', 'Bahan dihapus dari resep menu ini.']);
    if (this.dataset.note) rows.push(['Alasan Owner', this.dataset.note]);
    (payload.riwayat_penolakan || []).forEach((review, index) => {
        rows.push(['Penolakan sebelumnya ' + (index + 1) + ' (' + (review.tanggal || '-') + ')', review.alasan]);
    });
    document.getElementById('menuRequestDetail').replaceChildren(...rows.map(([label, value]) => {
        const item = document.createElement('div');
        const title = document.createElement('span');
        const content = document.createElement('strong');
        title.textContent = label;
        content.textContent = value ?? '-';
        item.append(title, content);
        return item;
    }));
    const oldPhoto = document.getElementById('menuRequestPhoto');
    if (oldPhoto) oldPhoto.remove();
    if (payload.foto) {
        const photo = document.createElement('img');
        photo.id = 'menuRequestPhoto';
        photo.className = 'request-detail-photo';
        photo.src = 'uploads/' + encodeURIComponent(String(payload.foto).split('/').pop());
        photo.alt = 'Foto menu yang diajukan';
        document.getElementById('menuRequestDetail').before(photo);
    }
    const actions = document.getElementById('menuReviewActions');
    if (actions) {
        actions.hidden = this.dataset.status !== 'Menunggu';
        document.getElementById('approve_menu_id').value = this.dataset.id;
        const reject = document.getElementById('rejectFromMenuDetail');
        reject.dataset.id = this.dataset.id;
        reject.dataset.name = this.dataset.menu;
    }
    detailModal.style.display = 'flex';
    document.getElementById('closeMenuRequestDetail').focus();
});
if (detailModal) {
    document.getElementById('closeMenuRequestDetail').addEventListener('click', () => detailModal.style.display = 'none');
    window.addEventListener('click', event => { if (event.target === detailModal) detailModal.style.display = 'none'; });
}
document.querySelectorAll('.reject-menu-request').forEach(button => button.addEventListener('click', function () {
    detailModal.style.display = 'none';
    rejectModal.querySelector('form').reset();
    document.getElementById('reject_menu_id').value = this.dataset.id;
    document.getElementById('reject_menu_name').value = this.dataset.name;
    rejectModal.style.display = 'flex';
}));
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        detailModal.style.display = 'none';
        if (rejectModal) rejectModal.style.display = 'none';
    }
});
if (rejectModal) {
    document.getElementById('closeRejectMenu').addEventListener('click', () => rejectModal.style.display = 'none');
    window.addEventListener('click', event => { if (event.target === rejectModal) rejectModal.style.display = 'none'; });
}
const editRequestModal = document.getElementById('editMenuRequestModal');
let previewPhotoUrl;
const photoInput = document.getElementById('editRequestPhotoInput');
if (photoInput) photoInput.addEventListener('change', function () {
    if (previewPhotoUrl) URL.revokeObjectURL(previewPhotoUrl);
    const preview = document.getElementById('editRequestNewPhoto');
    preview.hidden = !this.files.length;
    if (this.files.length) {
        previewPhotoUrl = URL.createObjectURL(this.files[0]);
        preview.src = previewPhotoUrl;
    }
});
function renderExistingRecipeRows(items) {
    const container = document.getElementById('editExistingRecipeRows');
    container.innerHTML = '';
    (items.length ? items : [{ bahan_id: '', nama_bahan: '-', jumlah: '' }]).forEach(item => {
        const row = document.createElement('div');
        row.className = 'edit-existing-recipe-row';
        row.innerHTML = `<input type="hidden" name="edit_bahan_id[]" value="${String(item.bahan_id || '').replace(/"/g, '&quot;')}">
            <div class="edit-recipe-index">${container.children.length + 1}</div>
            <div class="edit-recipe-ingredient"><span>Bahan</span><strong>${String(item.nama_bahan || '-').replace(/</g, '&lt;')}</strong></div>
            <div class="edit-recipe-amount"><label>Jumlah (${String(item.satuan || 'satuan').replace(/</g, '&lt;')})</label><input type="number" step="0.01" min="0.01" name="edit_jumlah[]" value="${item.jumlah || ''}" required></div>`;
        container.appendChild(row);
    });
}
function addAdditionalRecipeRow() {
    const source = document.querySelector('.edit-recipe-add-row');
    const row = source.cloneNode(true);
    row.querySelector('select').value = '';
    row.querySelector('input').value = '';
    syncAdditionalRecipeRow(row);
    document.getElementById('additionalRecipeRows').appendChild(row);
}
bindRequestButtons('.edit-menu-request', function () {
    const payload = JSON.parse(this.dataset.payload || '{}');
    const isRecipe = this.dataset.type.includes('Resep');
    editRequestModal.querySelector('form').reset();
    const photo = document.getElementById('editRequestPhoto');
    photo.hidden = isRecipe || !payload.foto;
    photo.onerror = () => { photo.hidden = true; };
    if (payload.foto) photo.src = 'uploads/' + encodeURIComponent(String(payload.foto).split('/').pop());
    document.getElementById('editRequestNewPhoto').hidden = true;
    document.getElementById('edit_request_id').value = this.dataset.id;
    document.getElementById('edit_request_action').value = this.dataset.action || 'edit_pending';
    document.getElementById('edit_request_type').value = this.dataset.type;
    document.getElementById('editRequestMenuFields').hidden = isRecipe;
    document.getElementById('editRequestRecipeFields').hidden = !isRecipe;
    document.getElementById('additionalRecipeRows').innerHTML = '';
    document.getElementById('edit_request_name').required = !isRecipe;
    document.getElementById('edit_request_category').required = !isRecipe;
    document.getElementById('edit_request_name').value = payload.nama_menu || this.dataset.menu;
    document.getElementById('edit_request_category').value = payload.kategori || 'Coffee';
    if (isRecipe) {
        const recipeItems = Array.isArray(payload.resep_items) && payload.resep_items.length
            ? payload.resep_items
            : [{ bahan_id: payload.bahan_id || '', nama_bahan: payload.nama_bahan || '-', jumlah: payload.jumlah || '', satuan: payload.satuan || '' }];
        renderExistingRecipeRows(recipeItems);
    } else {
        document.getElementById('editExistingRecipeRows').innerHTML = '';
    }
    document.querySelectorAll('#editRequestMenuFields input, #editRequestMenuFields select').forEach(input => { input.disabled = isRecipe; });
    document.querySelectorAll('#editRequestRecipeFields input, #editRequestRecipeFields select').forEach(input => { input.disabled = !isRecipe; });
    document.querySelectorAll('.edit-recipe-add-row').forEach(syncAdditionalRecipeRow);
    editRequestModal.style.display = 'flex';
});
if (editRequestModal) {
    editRequestModal.addEventListener('input', event => {
        const row = event.target.closest('.edit-recipe-add-row');
        if (row) syncAdditionalRecipeRow(row);
    });
    document.getElementById('closeEditMenuRequest').addEventListener('click', () => editRequestModal.style.display = 'none');
    window.addEventListener('click', event => { if (event.target === editRequestModal) editRequestModal.style.display = 'none'; });
}
function syncAdditionalRecipeRow(row) {
    const select = row.querySelector('select');
    const input = row.querySelector('input');
    const unit = select.selectedOptions[0]?.dataset.unit;
    row.querySelector('.additional-recipe-amount-label').textContent = unit ? 'Jumlah (' + unit + ')' : 'Jumlah';
    select.required = input.value !== '';
    input.required = select.value !== '';
}
</script>
</body>
</html>
