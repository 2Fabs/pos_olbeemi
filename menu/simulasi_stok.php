<?php
include '../config/database.php';

if (auth_current_role() !== 'owner') {
    header('Location: index.php');
    exit;
}

function simulationRedirect($type, $message) {
    header('Location: simulasi_stok.php?' . $type . '=' . urlencode($message));
    exit;
}

function simulationRows($conn) {
    $query = mysqli_query($conn, "
        SELECT m.id AS menu_id, m.nama_menu, r.bahan_id, r.jumlah,
               b.nama_bahan, b.stok, b.minimum_stok, b.satuan
        FROM menu m
        JOIN menu_resep r ON r.menu_id = m.id
        JOIN bahan_baku b ON b.id = r.bahan_id
        ORDER BY m.id, r.id
    ");
    if (!$query) throw new Exception(mysqli_error($conn));
    return mysqli_fetch_all($query, MYSQLI_ASSOC);
}

function simulationPlan($conn) {
    $rows = simulationRows($conn);
    $menus = [];
    $material_usage = [];
    $materials = [];

    foreach ($rows as $row) {
        $menu_id = (int) $row['menu_id'];
        $material_id = (int) $row['bahan_id'];
        $menus[$menu_id]['name'] = $row['nama_menu'];
        $menus[$menu_id]['ingredients'][$material_id] = (float) $row['jumlah'];
        $material_usage[$material_id][$menu_id] = true;
        $materials[$material_id] = [
            'name' => $row['nama_bahan'], 'unit' => $row['satuan'],
            'stock' => (float) $row['stok'], 'minimum' => (float) $row['minimum_stok'],
            'max_recipe' => max((float) ($materials[$material_id]['max_recipe'] ?? 0), (float) $row['jumlah']),
        ];
    }

    $candidates = [];
    foreach ($menus as $menu_id => $menu) {
        foreach ($menu['ingredients'] as $material_id => $amount) {
            if (count($material_usage[$material_id]) === 1) {
                $candidates[] = ['menu_id' => $menu_id, 'material_id' => $material_id, 'amount' => $amount];
                break;
            }
        }
    }
    if (count($candidates) < 2) {
        throw new Exception('Diperlukan minimal dua menu dengan bahan resep yang tidak dipakai menu lain agar pengaturan tidak memengaruhi menu lainnya.');
    }

    $habis = $candidates[0];
    $menipis = null;
    foreach ($candidates as $candidate) {
        if ($candidate['menu_id'] !== $habis['menu_id'] && $candidate['material_id'] !== $habis['material_id']) {
            $menipis = $candidate;
            break;
        }
    }
    if (!$menipis) throw new Exception('Belum ditemukan dua menu yang stoknya dapat diatur secara terpisah.');

    $targets = [];
    foreach ($materials as $material_id => $material) {
        $targets[$material_id] = max($material['max_recipe'] * 25, $material['minimum'] * 3, 10);
    }
    $targets[$habis['material_id']] = 0;
    $targets[$menipis['material_id']] = max($menipis['amount'] * 3, $menipis['amount']);

    return compact('materials', 'targets', 'habis', 'menipis', 'menus');
}

try {
    $plan = simulationPlan($conn);
} catch (Exception $exception) {
    $plan_error = $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    auth_require_post_csrf('simulasi_stok.php');
    if (!empty($plan_error)) simulationRedirect('error', $plan_error);

    mysqli_begin_transaction($conn);
    try {
        if (!mysqli_query($conn, "UPDATE menu SET status = 'Aktif'")) throw new Exception(mysqli_error($conn));

        foreach ($plan['targets'] as $material_id => $target) {
            $lock = mysqli_query($conn, "SELECT id, nama_bahan, stok, satuan FROM bahan_baku WHERE id = $material_id FOR UPDATE");
            $material = $lock ? mysqli_fetch_assoc($lock) : null;
            if (!$material) throw new Exception('Bahan untuk pengaturan stok tidak ditemukan.');
            $before = (float) $material['stok'];
            if (abs($before - $target) < 0.0001) continue;

            $name = mysqli_real_escape_string($conn, $material['nama_bahan']);
            $unit = mysqli_real_escape_string($conn, $material['satuan']);
            if (!mysqli_query($conn, "UPDATE bahan_baku SET stok = '$target' WHERE id = $material_id")) throw new Exception(mysqli_error($conn));

            if ($target > $before) {
                $delta = $target - $before;
                $history = "INSERT INTO riwayat_masuk (bahan_id, nama_bahan, jumlah_masuk, satuan, keterangan, total_setelah, tanggal) VALUES ($material_id, '$name', '$delta', '$unit', 'Penyesuaian stok menu', '$target', NOW())";
            } else {
                $delta = $before - $target;
                $history = "INSERT INTO riwayat_keluar (bahan_id, nama_bahan, jumlah_keluar, satuan, total_setelah, keterangan, tanggal) VALUES ($material_id, '$name', '$delta', '$unit', '$target', 'Penyesuaian Stok', NOW())";
            }
            if (!mysqli_query($conn, $history)) throw new Exception(mysqli_error($conn));
        }
        mysqli_commit($conn);
        simulationRedirect('success', 'Semua menu sudah aktif. ' . $plan['menus'][$plan['menipis']['menu_id']]['name'] . ' disetel tersisa 3 porsi, dan ' . $plan['menus'][$plan['habis']['menu_id']]['name'] . ' disetel habis.');
    } catch (Exception $exception) {
        mysqli_rollback($conn);
        simulationRedirect('error', $exception->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Atur Stok Menu</title><link rel="stylesheet" href="../css/style.css"></head>
<body><?php $current_module = 'menu'; include '../includes/sidebar.php'; ?>
<div class="main-content"><div class="header"><div><h1>Atur Stok Menu</h1><p>Aktifkan semua menu dengan satu menu stok menipis dan satu menu stok habis.</p></div></div>
<?php if (!empty($_GET['success'])): ?><div class="request-notice request-notice-success"><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?>
<?php if (!empty($_GET['error']) || !empty($plan_error)): ?><div class="request-notice request-notice-error"><?= htmlspecialchars($_GET['error'] ?? $plan_error) ?></div><?php endif; ?>
<?php if (empty($plan_error)): ?><div class="table-card"><h2>Pengaturan stok</h2><p><strong><?= htmlspecialchars($plan['menus'][$plan['menipis']['menu_id']]['name']) ?></strong> akan tersisa 3 porsi melalui bahan <?= htmlspecialchars($plan['materials'][$plan['menipis']['material_id']]['name']) ?>.</p><p><strong><?= htmlspecialchars($plan['menus'][$plan['habis']['menu_id']]['name']) ?></strong> akan habis melalui bahan <?= htmlspecialchars($plan['materials'][$plan['habis']['material_id']]['name']) ?>.</p><p>Perubahan ini tersimpan di database dan setiap bahan dibuatkan riwayat masuk atau keluar, sehingga stok akhir dan riwayat selalu sama.</p><form method="POST" onsubmit="return confirm('Terapkan pengaturan stok ini ke data aktif?');"><?= auth_csrf_input() ?><button class="btn-primary" type="submit">Terapkan Pengaturan</button> <a class="btn-stock" href="index.php">Batal</a></form></div><?php endif; ?></div></body></html>
