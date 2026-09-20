<?php
include '../config/database.php';
include '../includes/material_packages.php';

$role = auth_current_role();
$user = auth_current_user();
$is_owner = $role === 'owner';
$user_id = (int) ($user['id'] ?? 0);
ensureMaterialPackageTable($conn);
ensureMaterialPackageChainTable($conn);

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS permintaan_perubahan_bahan (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, bahan_id INT NOT NULL,
    jenis VARCHAR(20) NOT NULL, usulan JSON NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Menunggu',
    reviewed_by INT NULL, review_note VARCHAR(255) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL, INDEX idx_perubahan_bahan_status (status), INDEX idx_perubahan_bahan_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function perubahan_redirect($message, $error = false) {
    header('Location: permintaan_stok.php?' . ($error ? 'error=' : 'success=') . urlencode($message)); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) perubahan_redirect('Sesi formulir kedaluwarsa.', true);
    $action = $_POST['action'] ?? '';
    if ($action === 'submit' && $role === 'barista') {
        $bahan_id = (int) ($_POST['bahan_id'] ?? 0);
        $jenis = $_POST['jenis'] ?? '';
        $material_query = mysqli_query($conn, "SELECT id FROM bahan_baku WHERE id = $bahan_id LIMIT 1");
        if (!$material_query || !mysqli_num_rows($material_query)) perubahan_redirect('Bahan tidak ditemukan.', true);
        if ($jenis === 'Info') {
            $usulan = ['nama_bahan' => trim((string) ($_POST['nama_bahan'] ?? '')), 'minimum_stok' => (float) ($_POST['minimum_stok'] ?? -1), 'satuan_baru' => strtolower(trim((string) ($_POST['satuan_baru'] ?? '')))];
            if ($usulan['nama_bahan'] === '' || $usulan['minimum_stok'] < 0 || !in_array($usulan['satuan_baru'], ['g', 'ml', 'pcs'], true)) perubahan_redirect('Isi data info bahan yang diusulkan.', true);
        } elseif ($jenis === 'Kemasan') {
            $usulan = ['satuan_kemasan' => strtolower(trim((string) ($_POST['satuan_kemasan'] ?? ''))), 'isi_satuan_dasar' => (float) ($_POST['isi_satuan_dasar'] ?? 0)];
            if (!in_array($usulan['satuan_kemasan'], purchaseUnitRules()['packages'], true) || $usulan['isi_satuan_dasar'] <= 0) perubahan_redirect('Data kemasan tidak valid.', true);
        } else perubahan_redirect('Jenis perubahan tidak valid.', true);
        $json = mysqli_real_escape_string($conn, json_encode($usulan, JSON_UNESCAPED_UNICODE));
        if (!mysqli_query($conn, "INSERT INTO permintaan_perubahan_bahan (user_id, bahan_id, jenis, usulan) VALUES ($user_id, $bahan_id, '$jenis', '$json')")) perubahan_redirect('Pengajuan gagal disimpan.', true);
        perubahan_redirect('Pengajuan perubahan berhasil dikirim ke Owner.');
    }
    if (in_array($action, ['approve', 'reject'], true) && $is_owner) {
        $id = (int) ($_POST['id'] ?? 0);
        mysqli_begin_transaction($conn);
        try {
            $query = mysqli_query($conn, "SELECT * FROM permintaan_perubahan_bahan WHERE id = $id AND status = 'Menunggu' FOR UPDATE");
            $request = $query ? mysqli_fetch_assoc($query) : null;
            if (!$request) throw new Exception('Pengajuan sudah diproses atau tidak ditemukan.');
            $note = mysqli_real_escape_string($conn, trim((string) ($_POST['review_note'] ?? '')));
            if ($action === 'approve') {
                $proposal = json_decode($request['usulan'], true);
                $material_id = (int) $request['bahan_id'];
                if ($request['jenis'] === 'Info') {
                    $name = mysqli_real_escape_string($conn, $proposal['nama_bahan']); $minimum = (float) $proposal['minimum_stok']; $unit = mysqli_real_escape_string($conn, $proposal['satuan_baru']);
                    $current = mysqli_query($conn, "SELECT satuan FROM bahan_baku WHERE id = $material_id FOR UPDATE"); $current_material = $current ? mysqli_fetch_assoc($current) : null;
                    if (!$current_material) throw new Exception('Bahan tidak ditemukan.');
                    if ($current_material['satuan'] !== $proposal['satuan_baru']) {
                        $used = mysqli_query($conn, "SELECT 1 FROM riwayat_keluar WHERE bahan_id = $material_id LIMIT 1");
                        if ($used && mysqli_num_rows($used)) throw new Exception('Satuan dasar tidak dapat diubah karena bahan sudah memiliki riwayat stok keluar.');
                        if (!mysqli_query($conn, "UPDATE riwayat_masuk SET satuan = '$unit' WHERE bahan_id = $material_id") || !mysqli_query($conn, "DELETE FROM bahan_kemasan_rantai WHERE bahan_id = $material_id") || !mysqli_query($conn, "DELETE FROM bahan_kemasan WHERE bahan_id = $material_id")) throw new Exception(mysqli_error($conn));
                    }
                    if (!mysqli_query($conn, "UPDATE bahan_baku SET nama_bahan = '$name', minimum_stok = '$minimum', satuan = '$unit' WHERE id = $material_id")) throw new Exception(mysqli_error($conn));
                } else {
                    $unit = mysqli_real_escape_string($conn, $proposal['satuan_kemasan']); $content = (float) $proposal['isi_satuan_dasar'];
                    if (!mysqli_query($conn, "DELETE FROM bahan_kemasan_rantai WHERE bahan_id = $material_id") || !mysqli_query($conn, "UPDATE bahan_kemasan SET is_default = 0 WHERE bahan_id = $material_id") || !mysqli_query($conn, "INSERT INTO bahan_kemasan (bahan_id, satuan_kemasan, isi_satuan_dasar, is_default, is_verified) VALUES ($material_id, '$unit', '$content', 1, 0) ON DUPLICATE KEY UPDATE isi_satuan_dasar = VALUES(isi_satuan_dasar), is_default = 1, is_verified = 0")) throw new Exception(mysqli_error($conn));
                }
            }
            $status = $action === 'approve' ? 'Disetujui' : 'Ditolak';
            if (!mysqli_query($conn, "UPDATE permintaan_perubahan_bahan SET status = '$status', reviewed_by = $user_id, review_note = '$note', reviewed_at = NOW() WHERE id = $id")) throw new Exception(mysqli_error($conn));
            mysqli_commit($conn); perubahan_redirect('Pengajuan ' . strtolower($status) . '.');
        } catch (Exception $e) { mysqli_rollback($conn); perubahan_redirect($e->getMessage(), true); }
    }
    perubahan_redirect('Aksi tidak diizinkan.', true);
}

$materials = mysqli_query($conn, 'SELECT id, nama_bahan, stok, satuan, minimum_stok FROM bahan_baku ORDER BY nama_bahan');
$where = $is_owner ? '1=1' : 'p.user_id = ' . $user_id;
$requests = mysqli_query($conn, "SELECT p.*, b.nama_bahan, b.minimum_stok, b.satuan, u.name AS pemohon FROM permintaan_perubahan_bahan p JOIN bahan_baku b ON b.id = p.bahan_id LEFT JOIN users u ON u.id = p.user_id WHERE $where ORDER BY (p.status = 'Menunggu') DESC, p.created_at DESC");
$selected_id = (int) ($_GET['bahan_id'] ?? 0);
?>
<!doctype html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Penyesuaian Stok</title><link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>"><link rel="stylesheet" href="../css/bahan.css?v=<?= filemtime(__DIR__ . '/../css/bahan.css') ?>"></head><body>
<?php $current_module = 'permintaan_stok'; include '../includes/sidebar.php'; ?>
<div class="main-content"><div class="header"><div><h1>Penyesuaian Stok</h1><p><?= $is_owner ? 'Tinjau perubahan info dan kemasan dari barista' : 'Ajukan perubahan info atau kemasan bahan kepada Owner' ?></p></div><a href="permintaan_stok.php" class="btn-edit">Kembali ke Penyesuaian Stok</a></div>
<?php if (!empty($_GET['success'])): ?><div class="request-notice request-notice-success"><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?><?php if (!empty($_GET['error'])): ?><div class="request-notice request-notice-error"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
<?php if (!$is_owner): ?><div class="table-card request-form-card"><h2>Ajukan Perubahan</h2><form method="post" id="materialChangeForm"><?= auth_csrf_input() ?><input type="hidden" name="action" value="submit"><div class="form-group"><label>Bahan</label><select name="bahan_id" id="requestMaterial" required><option value="">Pilih bahan</option><?php while ($material = mysqli_fetch_assoc($materials)): ?><option value="<?= (int) $material['id'] ?>" data-name="<?= htmlspecialchars($material['nama_bahan'], ENT_QUOTES) ?>" data-minimum="<?= (float) $material['minimum_stok'] ?>"<?= $selected_id === (int) $material['id'] ? ' selected' : '' ?>><?= htmlspecialchars($material['nama_bahan']) ?></option><?php endwhile; ?></select></div><div class="form-group"><label>Jenis Perubahan</label><select name="jenis" id="requestKind" required><option value="Stok">Penyesuaian Stok</option><option value="Info">Perubahan Info</option><option value="Kemasan">Perubahan Kemasan</option></select></div><p class="request-form-help" id="stockChangeHelp">Untuk perubahan stok, lanjutkan ke formulir penyesuaian stok yang sudah ada.</p><div id="infoFields" hidden><div class="form-group"><label>Nama Bahan Baru</label><input name="nama_bahan" id="requestName"></div><div class="form-group"><label>Minimum Stok Baru</label><input type="number" step="0.01" min="0" name="minimum_stok" id="requestMinimum"></div></div><div id="packageFields" hidden><div class="form-group"><label>Kemasan</label><select name="satuan_kemasan"><option value="botol">Botol</option><option value="pack">Pack</option><option value="dus">Dus</option><option value="kotak">Kotak</option><option value="galon">Galon</option><option value="sachet">Sachet</option><option value="kaleng">Kaleng</option><option value="bag">Bag</option><option value="kantong">Kantong</option></select></div><div class="form-group"><label>Isi 1 Kemasan dalam Satuan Dasar</label><input type="number" step="0.001" min="0.001" name="isi_satuan_dasar"></div></div><button class="btn-primary" type="submit" id="materialChangeSubmit">Lanjutkan</button></form></div><?php endif; ?>
<div class="table-card"><h2><?= $is_owner ? 'Daftar Pengajuan' : 'Riwayat Pengajuan Saya' ?></h2><table><thead><tr><th>Bahan</th><?php if ($is_owner): ?><th>Barista</th><?php endif; ?><th>Jenis</th><th>Usulan</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php while ($row = mysqli_fetch_assoc($requests)): $proposal = json_decode($row['usulan'], true); ?><tr><td><?= htmlspecialchars($row['nama_bahan']) ?></td><?php if ($is_owner): ?><td><?= htmlspecialchars($row['pemohon'] ?: '-') ?></td><?php endif; ?><td><?= htmlspecialchars($row['jenis']) ?></td><td><?= $row['jenis'] === 'Info' ? htmlspecialchars($proposal['nama_bahan']) . ' · minimum ' . $proposal['minimum_stok'] : htmlspecialchars($proposal['satuan_kemasan']) . ' = ' . $proposal['isi_satuan_dasar'] . ' ' . htmlspecialchars($row['satuan']) ?></td><td><span class="badge <?= $row['status'] === 'Disetujui' ? 'badge-success' : ($row['status'] === 'Ditolak' ? 'badge-danger' : 'badge-warning') ?>"><?= htmlspecialchars($row['status']) ?></span></td><td><?php if ($is_owner && $row['status'] === 'Menunggu'): ?><form method="post" class="action-group"><?= auth_csrf_input() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button name="action" value="approve" class="btn-primary">Setujui</button><button name="action" value="reject" class="btn-delete">Tolak</button></form><?php else: ?><?= htmlspecialchars($row['review_note'] ?: '-') ?><?php endif; ?></td></tr><?php endwhile; ?></tbody></table></div></div>
<script>const material=document.getElementById('requestMaterial'),kind=document.getElementById('requestKind'),form=document.getElementById('materialChangeForm'); function sync(){const type=kind.value,info=type==='Info',stock=type==='Stok';document.getElementById('infoFields').hidden=!info;document.getElementById('packageFields').hidden=type!=='Kemasan';document.getElementById('stockChangeHelp').hidden=!stock;document.getElementById('materialChangeSubmit').textContent=stock?'Buka Penyesuaian Stok':'Kirim ke Owner';if(info&&material.value){const o=material.options[material.selectedIndex];document.getElementById('requestName').value=o.dataset.name;document.getElementById('requestMinimum').value=o.dataset.minimum;}} if(material){material.addEventListener('change',sync);kind.addEventListener('change',sync);form.addEventListener('submit',e=>{if(kind.value==='Stok'){e.preventDefault();location.href='permintaan_stok.php';}});sync();}</script></body></html>
