<?php

function ensureMenuRequestTable($conn) {
    $query = mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS permintaan_menu (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            jenis VARCHAR(30) NOT NULL,
            menu_id INT NULL,
            data_json TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'Menunggu',
            reviewed_by INT NULL,
            review_note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL,
            INDEX idx_permintaan_menu_status (status),
            INDEX idx_permintaan_menu_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (!$query) {
        die('Gagal menyiapkan permintaan perubahan menu: ' . mysqli_error($conn));
    }
}

function submitMenuRequest($conn, $type, $menu_id, array $data) {
    ensureMenuRequestTable($conn);
    $user = auth_current_user();
    $user_id = (int) ($user['id'] ?? 0);
    $type_safe = mysqli_real_escape_string($conn, $type);
    $data_safe = mysqli_real_escape_string($conn, json_encode($data, JSON_UNESCAPED_UNICODE));
    $menu_value = $menu_id > 0 ? (int) $menu_id : 'NULL';

    $insert = mysqli_query($conn, "
        INSERT INTO permintaan_menu (user_id, jenis, menu_id, data_json)
        VALUES ($user_id, '$type_safe', $menu_value, '$data_safe')
    ");

    if (!$insert) {
        die('Permintaan perubahan menu gagal disimpan: ' . mysqli_error($conn));
    }
}

function requestNotificationSnapshot($conn) {
    $owner = auth_has_role('owner');
    $user_id = (int) (auth_current_user()['id'] ?? 0);
    $where = $owner ? '1=1' : "user_id = $user_id";
    $target_status = $owner ? 'Menunggu' : 'Ditolak';
    $result = [];
    foreach (['menu' => 'permintaan_menu', 'stok' => 'permintaan_stok'] as $key => $table) {
        $exists = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        $rows = [];
        if ($exists && mysqli_num_rows($exists)) {
            $query = mysqli_query($conn, "SELECT * FROM $table WHERE $where ORDER BY id");
            if (!$query) throw new RuntimeException('Notifikasi gagal dibaca.');
            $rows = mysqli_fetch_all($query, MYSQLI_ASSOC);
        }
        $pending = [];
        foreach ($rows as $row) {
            if ($row['status'] !== $target_status) continue;
            $group = $key === 'stok' && !empty($row['batch_key']) ? $row['batch_key'] : 'single:' . $row['id'];
            $pending[$row['user_id'] . ':' . $group] = true;
        }
        $result[$key] = ['count' => count($pending), 'ids' => array_keys($pending), 'version' => hash('sha256', json_encode($rows))];
    }
    return $result;
}

function validateMenuRecipeItems($conn, array $items) {
    if (!$items) throw new RuntimeException('Minimal satu bahan resep harus diisi.');
    $validated = [];
    foreach ($items as $item) {
        $id = (int) ($item['bahan_id'] ?? 0);
        $amount = (float) ($item['jumlah'] ?? 0);
        if ($id <= 0 || !is_finite($amount) || $amount <= 0) {
            throw new RuntimeException('Setiap bahan resep harus memiliki jumlah lebih dari nol.');
        }
        if (isset($validated[$id])) throw new RuntimeException('Bahan resep yang sama cukup diisi sekali.');
        $query = mysqli_query($conn, "SELECT nama_bahan, satuan FROM bahan_baku WHERE id = $id LIMIT 1");
        $ingredient = $query ? mysqli_fetch_assoc($query) : null;
        if (!$ingredient) throw new RuntimeException('Bahan resep tidak ditemukan. Silakan periksa kembali.');
        $validated[$id] = ['bahan_id' => $id, 'nama_bahan' => $ingredient['nama_bahan'], 'satuan' => $ingredient['satuan'], 'jumlah' => $amount];
    }
    return array_values($validated);
}

function menuRequestLabel($type) {
    $labels = [
        'Tambah Menu' => 'Tambah Menu',
        'Ubah Menu' => 'Edit Menu',
        'Tambah Resep' => 'Tambah Resep',
        'Ubah Resep' => 'Edit Resep',
        'Hapus Resep' => 'Hapus Resep',
    ];

    return $labels[$type] ?? $type;
}
?>
