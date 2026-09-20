<?php
include '../config/database.php';
$can_manage_recipe = auth_has_role(['barista', 'owner']);

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$menu_id = (int) $_GET['id'];

$menu_query = mysqli_query($conn, "
    SELECT *
    FROM menu
    WHERE id = $menu_id
");

$menu = mysqli_fetch_assoc($menu_query);

if (!$menu) {
    header("Location: index.php");
    exit;
}

$bahan_query = mysqli_query($conn, "
    SELECT *
    FROM bahan_baku
    ORDER BY nama_bahan ASC
");
$bahan_options = $bahan_query ? mysqli_fetch_all($bahan_query, MYSQLI_ASSOC) : [];

$resep_query = mysqli_query($conn, "
    SELECT
        menu_resep.id,
        menu_resep.jumlah,
        bahan_baku.nama_bahan,
        bahan_baku.satuan
    FROM menu_resep
    JOIN bahan_baku ON menu_resep.bahan_id = bahan_baku.id
    WHERE menu_resep.menu_id = $menu_id
    ORDER BY bahan_baku.nama_bahan ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Resep</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/pos.css?v=<?= filemtime(__DIR__ . '/../css/pos.css') ?>">
</head>
<body>

<?php
$current_module = 'menu';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header">
        <div>
            <h1><?= $can_manage_recipe ? 'Kelola Resep Menu' : 'Detail Resep Menu' ?></h1>
        </div>

        <?php if ($can_manage_recipe): ?>
        <button class="btn-primary" onclick="openTambahModal()">
            + Tambah Bahan
        </button>
        <?php endif; ?>
    </div>

    <div class="table-card">
        <div class="stock-summary">
            <h2><?= htmlspecialchars($menu['nama_menu']) ?></h2>
        </div>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Bahan</th>
                    <th>Jumlah</th>
                    <?php if ($can_manage_recipe): ?><th>Aksi</th><?php endif; ?>
                </tr>
            </thead>

            <tbody>
                <?php if (mysqli_num_rows($resep_query) > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($row = mysqli_fetch_assoc($resep_query)): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td><?= htmlspecialchars($row['nama_bahan']) ?></td>

                            <td>
                                <?= rtrim(rtrim($row['jumlah'], '0'), '.') ?>
                                <span class="unit-symbol" data-unit="<?= htmlspecialchars($row['satuan']) ?>"><?= htmlspecialchars($row['satuan']) ?></span>
                            </td>

                            <?php if ($can_manage_recipe): ?>
                            <td>
                                <div class="action-group">
                                    <button
                                        class="btn-edit"
                                        onclick="openEditModal(
                                            <?= $row['id'] ?>,
                                            '<?= $row['jumlah'] ?>',
                                            '<?= htmlspecialchars(stockInputUnitLabel($row['satuan']), ENT_QUOTES) ?>'
                                        )">
                                        Edit
                                    </button>

                                    <form action="delete_resep.php" method="POST" onsubmit="return confirm('Hapus bahan dari resep?')">
                                        <?= auth_csrf_input() ?>
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="menu_id" value="<?= (int) $menu_id ?>">
                                        <button type="submit" class="btn-delete">Hapus</button>
                                    </form>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $can_manage_recipe ? 4 : 3 ?>">Belum ada bahan dalam resep.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top:20px;">
            <a href="index.php" class="btn-stock">
                ← Kembali ke Menu
            </a>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH -->
<div id="tambahModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Tambah Bahan ke Resep</h2>
            <button type="button" class="close-btn" onclick="closeTambahModal()" aria-label="Tutup tambah resep">&times;</button>
        </div>

        <form action="tambah_resep.php" method="POST">
            <?= auth_csrf_input() ?>
            <input type="hidden" name="menu_id" value="<?= $menu_id ?>">

            <div id="recipeAddRows">
                <div class="recipe-add-row">
                    <div class="form-group"><label>Bahan</label><select name="bahan_id[]" required>
                        <option value="">Pilih Bahan</option>
                        <?php foreach ($bahan_options as $bahan): ?><option value="<?= $bahan['id'] ?>" data-unit="<?= htmlspecialchars(stockInputUnitLabel($bahan['satuan'])) ?>"><?= htmlspecialchars($bahan['nama_bahan']) ?> (<?= htmlspecialchars(stockInputUnitLabel($bahan['satuan'])) ?>)</option><?php endforeach; ?>
                    </select></div>
                    <div class="form-group"><label class="recipe-amount-label">Jumlah</label><input type="number" step="0.01" min="0.01" name="jumlah[]" required></div>
                    <button type="button" class="btn-delete recipe-remove-row" onclick="removeRecipeAddRow(this)">Hapus</button>
                </div>
            </div>
            <button type="button" class="btn-stock" onclick="addRecipeAddRow()">+ Tambah Bahan Lagi</button>

            <button type="submit" class="btn-primary">
                Simpan Resep
            </button>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Resep</h2>
            <button type="button" class="close-btn" onclick="closeEditModal()" aria-label="Tutup edit resep">&times;</button>
        </div>

        <form action="update_resep.php" method="POST">
            <?= auth_csrf_input() ?>
            <input type="hidden" name="menu_id" value="<?= $menu_id ?>">
            <input type="hidden" name="resep_id" id="edit_resep_id">

            <div class="form-group">
                <label id="edit_satuan_label">Jumlah</label>
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="jumlah"
                    id="edit_jumlah"
                    required>
                <small class="request-form-help" id="edit_satuan_help">Satuan: -</small>
            </div>

            <button type="submit" class="btn-primary">
                Simpan Perubahan
            </button>
        </form>
    </div>
</div>

<script>
function openTambahModal() {
    document.getElementById('tambahModal').style.display = 'flex';
}

function closeTambahModal() {
    document.getElementById('tambahModal').style.display = 'none';
}

function addRecipeAddRow() {
    const first = document.querySelector('.recipe-add-row');
    const row = first.cloneNode(true);
    row.querySelector('select').value = '';
    row.querySelector('input').value = '';
    row.querySelector('.recipe-amount-label').textContent = 'Jumlah';
    document.getElementById('recipeAddRows').appendChild(row);
}

function removeRecipeAddRow(button) {
    const rows = document.querySelectorAll('.recipe-add-row');
    if (rows.length === 1) {
        rows[0].querySelector('select').value = '';
        rows[0].querySelector('input').value = '';
        rows[0].querySelector('.recipe-amount-label').textContent = 'Jumlah';
        return;
    }
    button.closest('.recipe-add-row').remove();
}

function openEditModal(id, jumlah, satuan) {
    document.getElementById('edit_resep_id').value = id;
    document.getElementById('edit_jumlah').value = jumlah;
    document.getElementById('edit_satuan_label').textContent = 'Jumlah (' + satuan + ')';
    document.getElementById('edit_satuan_help').textContent = 'Satuan: ' + satuan;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById('recipeAddRows').addEventListener('change', event => {
    if (!event.target.matches('select')) return;
    const unit = event.target.selectedOptions[0]?.dataset.unit;
    event.target.closest('.recipe-add-row').querySelector('.recipe-amount-label').textContent = unit ? 'Jumlah (' + unit + ')' : 'Jumlah';
});

window.onclick = function(e) {
    ['tambahModal', 'editModal'].forEach(id => {
        const modal = document.getElementById(id);

        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
};
</script>

</body>
</html>
