<?php
include '../config/database.php';

// Penyesuaian stok harus diajukan oleh barista dan ditinjau owner.
// Halaman lama ini dipertahankan hanya agar tautan tersimpan tidak menghasilkan 404.
header('Location: permintaan_stok.php');
exit;

$bahan_query = mysqli_query($conn, "
    SELECT id, nama_bahan, stok, satuan, minimum_stok
    FROM bahan_baku
    ORDER BY nama_bahan ASC
");

function format_stok_penyesuaian($number) {
    return rtrim(rtrim(number_format((float) $number, 2, '.', ''), '0'), '.');
}
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

<?php
$current_module = 'penyesuaian_stok';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Penyesuaian Stok</h1>
            <p>Catat bahan basi, rusak, atau selisih stok fisik</p>
        </div>
    </div>

    <div class="search-box bahan-search-box">
        <input type="text" id="searchInput" placeholder="Cari bahan baku...">
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Bahan</th>
                    <th>Stok Saat Ini</th>
                    <th>Satuan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="adjustmentTable">
                <?php if ($bahan_query && mysqli_num_rows($bahan_query) > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($bahan = mysqli_fetch_assoc($bahan_query)): ?>
                        <?php $menipis = (float) $bahan['stok'] <= (float) $bahan['minimum_stok']; ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($bahan['nama_bahan']) ?></td>
                            <td><?= format_stok_penyesuaian($bahan['stok']) ?></td>
                            <td><span class="unit-symbol" data-unit="<?= htmlspecialchars($bahan['satuan']) ?>"><?= htmlspecialchars($bahan['satuan']) ?></span></td>
                            <td>
                                <span class="badge <?= $menipis ? 'badge-danger' : 'badge-success' ?>">
                                    <?= $menipis ? 'Menipis' : 'Aman' ?>
                                </span>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="btn-reduce-stock open-adjustment-modal"
                                    data-id="<?= (int) $bahan['id'] ?>"
                                    data-name="<?= htmlspecialchars($bahan['nama_bahan'], ENT_QUOTES) ?>"
                                    data-stock="<?= format_stok_penyesuaian($bahan['stok']) ?>"
                                    data-unit="<?= htmlspecialchars($bahan['satuan'], ENT_QUOTES) ?>"
                                    <?= (float) $bahan['stok'] <= 0 ? 'disabled' : '' ?>>
                                    Kurangi Stok
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6">Belum ada bahan baku.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="adjustmentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Kurangi Stok</h2>
            <span class="close-btn" onclick="closeAdjustmentModal()">&times;</span>
        </div>

        <form action="kurangi_stok.php" method="POST">
            <?= auth_csrf_input() ?>
            <input type="hidden" name="id" id="adjustment_id">
            <input type="hidden" name="redirect_to" value="penyesuaian_stok">

            <div class="form-group">
                <label>Nama Bahan</label>
                <input type="text" id="adjustment_name" readonly>
            </div>

            <div class="form-group">
                <label>Stok Saat Ini</label>
                <input type="text" id="adjustment_stock" readonly>
            </div>

            <div class="form-group">
                <label>Jumlah Stok Keluar</label>
                <input type="number" name="jumlah" id="adjustment_amount" min="0.01" step="0.01" required>
            </div>

            <div class="form-group">
                <label>Keterangan</label>
                <select name="keterangan" required>
                    <option value="">Pilih Keterangan</option>
                    <option value="Stok Basi">Stok Basi</option>
                    <option value="Stok Rusak">Stok Rusak</option>
                    <option value="Penyesuaian Stok">Selisih Stok Fisik</option>
                </select>
            </div>

            <button type="submit" class="btn-primary">Simpan Penyesuaian</button>
        </form>
    </div>
</div>

<script>
const searchInput = document.getElementById('searchInput');

searchInput.addEventListener('input', function() {
    const keyword = this.value.toLowerCase().trim();
    document.querySelectorAll('#adjustmentTable tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(keyword) ? '' : 'none';
    });
});

document.querySelectorAll('.open-adjustment-modal').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('adjustment_id').value = this.dataset.id;
        document.getElementById('adjustment_name').value = this.dataset.name;
        document.getElementById('adjustment_stock').value = this.dataset.stock + ' ' + this.dataset.unit;
        document.getElementById('adjustment_amount').value = '';
        document.getElementById('adjustment_amount').max = this.dataset.stock;
        document.getElementById('adjustmentModal').style.display = 'flex';
    });
});

function closeAdjustmentModal() {
    document.getElementById('adjustmentModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('adjustmentModal')) {
        closeAdjustmentModal();
    }
});
</script>
</body>
</html>
