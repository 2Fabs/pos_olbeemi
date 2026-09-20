<?php
include '../config/database.php';
include '../includes/menu_stock.php';
include '../includes/stock_units.php';
include '../includes/menu_requests.php';
ensureMenuRequestTable($conn);
$is_barista = auth_has_role('barista');
$is_owner = auth_has_role('owner');
$recipe_materials_query = mysqli_query($conn, "SELECT id, nama_bahan, satuan FROM bahan_baku ORDER BY nama_bahan ASC");
$recipe_materials = $recipe_materials_query ? mysqli_fetch_all($recipe_materials_query, MYSQLI_ASSOC) : [];
$existing_menu_names = [];
$existing_name_query = mysqli_query($conn, "SELECT nama_menu FROM menu ORDER BY nama_menu");
while ($existing_name_query && $existing_name = mysqli_fetch_assoc($existing_name_query)) $existing_menu_names[] = mb_strtolower(trim($existing_name['nama_menu']));
$pending_name_query = mysqli_query($conn, "SELECT data_json FROM permintaan_menu WHERE jenis = 'Tambah Menu' AND status = 'Menunggu'");
while ($pending_name_query && $pending_name = mysqli_fetch_assoc($pending_name_query)) {
    $pending_data = json_decode($pending_name['data_json'], true) ?: [];
    if (!empty($pending_data['nama_menu'])) $existing_menu_names[] = mb_strtolower(trim($pending_data['nama_menu']));
}
$existing_menu_names = array_values(array_unique($existing_menu_names));

$result = mysqli_query($conn, "
    SELECT menu.*, " . menuStockSelect('menu') . "
    FROM menu
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Menu</title>
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
            <h1>Daftar Menu</h1>
            <p><?= $is_owner ? 'Kelola menu, harga, dan resep coffeeshop' : ($is_barista ? 'Ajukan penambahan atau perubahan menu coffeeshop' : 'Pantau menu dan resep coffeeshop') ?></p>
        </div>

        <?php if ($is_barista || $is_owner): ?>
        <button class="btn-primary" onclick="openTambahModal()">
            + Tambah Menu
        </button>
        <?php endif; ?>
        <?php if ($is_owner): ?>
        <a class="btn-stock" href="simulasi_stok.php">Atur Stok Menu</a>
        <?php endif; ?>
    </div>
    <?php if (!empty($_GET['success'])): ?><div class="request-notice request-notice-success"><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?>
    <?php if (!empty($_GET['error'])): ?><div class="request-notice request-notice-error"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

    <div class="menu-toolbar">
        <input type="text" id="searchInput" placeholder="Cari menu...">

        <select id="filterKategori">
            <option value="all">Semua Kategori</option>
            <option value="Coffee">Coffee</option>
            <option value="Non Coffee">Non Coffee</option>
        </select>

        <select id="filterStatus">
            <option value="all">Semua Status</option>
            <option value="Aktif">Aktif</option>
            <option value="Nonaktif">Nonaktif</option>
        </select>
    </div>

    <div class="table-card">
        <table class="menu-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Foto</th>
                    <th>Nama Menu</th>
                    <th>Kategori</th>
                    <?php if (!$is_barista): ?>
                    <th>Harga</th>
                    <th>Keuntungan</th>
                    <?php endif; ?>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody id="menuTable">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php $stock_info = menuStockInfo($row); ?>
                        <tr data-kategori="<?= htmlspecialchars($row['kategori']) ?>" data-status="<?= htmlspecialchars($row['status']) ?>">
                            <td><?= $no++ ?></td>

                            <td class="menu-media-cell">
                                <?php if ($row['foto'] && file_exists(__DIR__ . '/uploads/' . $row['foto'])): ?>
                                    <img
                                        class="menu-image"
                                        src="uploads/<?= htmlspecialchars($row['foto']) ?>"
                                        alt="<?= htmlspecialchars($row['nama_menu']) ?>">
                                <?php else: ?>
                                    <div class="menu-placeholder">
                                        No Image
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="menu-name">
                                    <?= htmlspecialchars($row['nama_menu']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="menu-category-pill">
                                    <?= htmlspecialchars($row['kategori']) ?>
                                </span>
                            </td>

                            <?php if (!$is_barista): ?>
                                <td class="money-cell">
                                    Rp<?= number_format($row['harga_jual'], 0, ',', '.') ?>
                                </td>

                                <td class="money-cell">
                                    Rp<?= number_format($row['keuntungan'], 0, ',', '.') ?>
                                </td>
                            <?php endif; ?>

                            <td>
                                <?php if ((float) $row['harga_jual'] <= 0 && (int) $row['jumlah_resep'] <= 0): ?>
                                    <span class="badge badge-warning">Belum Siap Dijual</span>
                                <?php elseif ((float) $row['harga_jual'] <= 0): ?>
                                    <span class="badge badge-warning">Harga Belum Diatur</span>
                                <?php elseif ((int) $row['jumlah_resep'] <= 0): ?>
                                    <span class="badge badge-warning">Resep Belum Diatur</span>
                                <?php elseif ($row['status'] === 'Aktif'): ?>
                                    <?php if ($stock_info['status'] === 'habis'): ?>
                                        <span class="menu-stock-label menu-stock-label-habis">
                                            Stok Habis
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Aktif</span>
                                        <span class="menu-stock-label menu-stock-label-<?= $stock_info['status'] ?>">
                                            <?= htmlspecialchars($stock_info['label']) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-danger">Nonaktif</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="action-group menu-actions">
                                    <?php if ($is_barista): ?>
                                    <button
                                        class="btn-edit"
                                        onclick="openEditModal(
                                            <?= $row['id'] ?>,
                                            '<?= htmlspecialchars($row['nama_menu'], ENT_QUOTES) ?>',
                                            '<?= $row['kategori'] ?>',
                                            '<?= $row['status'] ?>'
                                        )">
                                        Edit
                                    </button>

                                    <a href="resep.php?id=<?= $row['id'] ?>" class="btn-stock">
                                        Resep
                                    </a>
                                    <?php if ($row['status'] === 'Aktif'): ?>
                                    <form action="update.php" method="POST" onsubmit="return confirm('Ajukan penonaktifan menu <?= htmlspecialchars($row['nama_menu'], ENT_QUOTES) ?> kepada Owner?')">
                                        <?= auth_csrf_input() ?>
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="nama_menu" value="<?= htmlspecialchars($row['nama_menu'], ENT_QUOTES) ?>">
                                        <input type="hidden" name="kategori" value="<?= htmlspecialchars($row['kategori'], ENT_QUOTES) ?>">
                                        <input type="hidden" name="status" value="Nonaktif">
                                        <button type="submit" class="btn-delete">Ajukan Nonaktifkan</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <?php if ($is_owner): ?>
                                    <button type="button" class="btn-edit" onclick="openPriceModal(
                                        <?= (int) $row['id'] ?>,
                                        '<?= htmlspecialchars($row['nama_menu'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($row['kategori'], ENT_QUOTES) ?>',
                                        '<?= $row['harga_jual'] ?>',
                                        '<?= $row['keuntungan'] ?>',
                                        '<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>'
                                    )"><?= (float) $row['harga_jual'] <= 0 ? 'Isi Harga' : 'Edit Harga' ?></button>
                                    <form action="toggle_status.php" method="POST" onsubmit="return confirm('<?= $row['status'] === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' ?> menu ini?')">
                                        <?= auth_csrf_input() ?>
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="<?= $row['status'] === 'Aktif' ? 'btn-delete' : 'btn-primary' ?>">
                                        <?= $row['status'] === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <a href="resep.php?id=<?= $row['id'] ?>" class="btn-stock">
                                        <?= (int) $row['jumlah_resep'] <= 0 ? 'Isi Resep' : ($is_owner ? 'Kelola Resep' : 'Lihat Resep') ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $is_barista ? 6 : 8 ?>">Belum ada menu.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($is_owner): ?>
<div id="priceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h2>Edit Harga Menu</h2><span class="close-btn" onclick="closePriceModal()">&times;</span></div>
        <form action="update.php" method="POST">
            <?= auth_csrf_input() ?>
            <input type="hidden" name="id" id="price_id">
            <input type="hidden" name="nama_menu" id="price_name_value">
            <input type="hidden" name="kategori" id="price_category">
            <input type="hidden" name="status" id="price_status">
            <div class="form-group"><label>Nama Menu</label><input type="text" id="price_name_label" readonly></div>
            <input type="hidden" name="harga_jual" id="price_sell">
            <input type="hidden" name="keuntungan" id="price_profit">
            <div class="form-group"><label>Harga Jual</label><input type="text" id="price_sell_display" inputmode="numeric" required></div>
            <div class="form-group"><label>Keuntungan</label><input type="text" id="price_profit_display" inputmode="numeric" required></div>
            <button type="submit" class="btn-primary">Simpan Harga</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- MODAL TAMBAH -->
<div id="tambahModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Tambah Menu</h2>
            <span class="close-btn" onclick="closeTambahModal()">&times;</span>
        </div>

        <form action="simpan.php" method="POST" enctype="multipart/form-data" id="addMenuForm">
            <?= auth_csrf_input() ?>
            <?php if ($is_owner): ?>
            <div class="form-group"><label>Harga Jual (Rp)</label><input type="number" name="harga_jual" min="1" step="1" required></div>
            <div class="form-group"><label>Keuntungan (Rp)</label><input type="number" name="keuntungan" min="0" step="1" required></div>
            <?php endif; ?>
            <div class="form-group">
                <label>Nama Menu</label>
                <input type="text" name="nama_menu" id="addMenuName" required>
                <small class="menu-field-error" id="addMenuNameError" hidden>Menu sudah ada.</small>
            </div>

            <div class="form-group">
                <label>Kategori</label>
                <select name="kategori" required>
                    <option value="">Pilih Kategori</option>
                    <option value="Coffee">Coffee</option>
                    <option value="Non Coffee">Non Coffee</option>
                </select>
            </div>

            <div class="form-group">
                <label>Foto Menu</label>
                <input type="file" name="foto" id="addMenuPhoto" accept=".jpg,.jpeg,.png,.webp" <?= $is_barista ? 'required' : '' ?>>
                <?php if ($is_barista): ?><small>Foto wajib diunggah untuk pengajuan menu baru.</small><?php endif; ?>
                <?php if ($is_barista): ?><small class="menu-field-error" id="addMenuPhotoError" hidden>Foto menu belum ada.</small><?php endif; ?>
            </div>

            <?php if ($is_barista): ?>
            <div class="menu-create-recipe">
                <h3>Resep Menu</h3>
                <small>Tambahkan bahan dan jumlah yang digunakan untuk satu porsi.</small>
                <div id="newMenuRecipeRows">
                    <div class="menu-create-recipe-row">
                        <div class="form-group"><label>Nama Bahan</label><select name="resep_bahan_id[]" required><option value="">Pilih bahan</option><?php foreach ($recipe_materials as $material): ?><option value="<?= (int) $material['id'] ?>" data-unit="<?= htmlspecialchars(stockInputUnitLabel($material['satuan']), ENT_QUOTES) ?>"><?= htmlspecialchars($material['nama_bahan']) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Jumlah</label><div class="stock-amount-field"><input type="number" name="resep_jumlah[]" min="0.01" step="0.01" required><span class="new-menu-recipe-unit">-</span></div></div>
                        <button type="button" class="btn-delete remove-new-menu-recipe" hidden>&times;</button>
                    </div>
                </div>
                <button type="button" class="btn-secondary" id="addNewMenuRecipe">+ Tambah Bahan</button>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn-primary">
                Simpan Menu
            </button>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Menu</h2>
            <span class="close-btn" onclick="closeEditModal()">&times;</span>
        </div>

        <form action="update.php" method="POST" enctype="multipart/form-data">
            <?= auth_csrf_input() ?>
            <?php if ($is_owner): ?><input type="hidden" name="edit_menu_only" value="1"><?php endif; ?>
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group">
                <label>Nama Menu</label>
                <input type="text" name="nama_menu" id="edit_nama" required>
            </div>

            <div class="form-group">
                <label>Kategori</label>
                <select name="kategori" id="edit_kategori" required>
                    <option value="Coffee">Coffee</option>
                    <option value="Non Coffee">Non Coffee</option>
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_status" required>
                    <option value="Aktif">Aktif</option>
                    <option value="Nonaktif">Nonaktif</option>
                </select>
            </div>

            <div class="form-group">
                <label>Ganti Foto</label>
                <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp">
            </div>

            <button type="submit" class="btn-primary">
                Update Menu
            </button>
        </form>
    </div>
</div>

<script>
function filterMenu() {
    const keyword = document.getElementById('searchInput').value.toLowerCase();
    const kategori = document.getElementById('filterKategori').value;
    const status = document.getElementById('filterStatus').value;
    const rows = document.querySelectorAll('#menuTable tr');

    rows.forEach(row => {
        const matchKeyword = row.textContent.toLowerCase().includes(keyword);
        const matchKategori =
            kategori === 'all' || row.dataset.kategori === kategori;
        const matchStatus = status === 'all' || row.dataset.status === status;

        row.style.display = matchKeyword && matchKategori && matchStatus
            ? ''
            : 'none';
    });
}

document.getElementById('searchInput').addEventListener('keyup', filterMenu);
document.getElementById('filterKategori').addEventListener('change', filterMenu);
document.getElementById('filterStatus').addEventListener('change', filterMenu);

function openTambahModal() {
    document.getElementById('tambahModal').style.display = 'flex';
}

function closeTambahModal() {
    document.getElementById('tambahModal').style.display = 'none';
}

const newMenuRecipeRows = document.getElementById('newMenuRecipeRows');
const existingMenuNames = <?= json_encode($existing_menu_names, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const addMenuForm = document.getElementById('addMenuForm');
const addMenuName = document.getElementById('addMenuName');
const addMenuPhoto = document.getElementById('addMenuPhoto');
function validateAddMenuName() {
    const duplicate = existingMenuNames.includes(addMenuName.value.trim().toLocaleLowerCase('id-ID'));
    document.getElementById('addMenuNameError').hidden = !duplicate;
    addMenuName.classList.toggle('field-invalid', duplicate);
    return !duplicate;
}
function validateAddMenuPhoto() {
    const error = document.getElementById('addMenuPhotoError');
    if (!error) return true;
    const valid = addMenuPhoto.files.length > 0;
    error.hidden = valid;
    addMenuPhoto.classList.toggle('field-invalid', !valid);
    return valid;
}
addMenuName.addEventListener('input', validateAddMenuName);
addMenuPhoto?.addEventListener('change', validateAddMenuPhoto);
addMenuForm.addEventListener('submit', event => {
    const nameValid = validateAddMenuName();
    const photoValid = validateAddMenuPhoto();
    if (!nameValid || !photoValid) {
        event.preventDefault();
        (!nameValid ? addMenuName : addMenuPhoto).focus();
    }
});
function refreshNewMenuRecipeRows() {
    if (!newMenuRecipeRows) return;
    const rows = newMenuRecipeRows.querySelectorAll('.menu-create-recipe-row');
    const selects = Array.from(newMenuRecipeRows.querySelectorAll('select'));
    const selectedValues = selects.map(select => select.value).filter(Boolean);
    rows.forEach((row, index) => {
        row.querySelector('.remove-new-menu-recipe').hidden = rows.length === 1;
        const select = row.querySelector('select');
        Array.from(select.options).forEach(option => {
            option.disabled = option.value !== '' && option.value !== select.value && selectedValues.includes(option.value);
            option.hidden = option.disabled;
        });
        row.querySelector('.new-menu-recipe-unit').textContent = select.options[select.selectedIndex]?.dataset.unit || '-';
    });
}
document.getElementById('addNewMenuRecipe')?.addEventListener('click', () => {
    const row = newMenuRecipeRows.firstElementChild.cloneNode(true);
    row.querySelector('select').value = '';
    row.querySelector('input').value = '';
    newMenuRecipeRows.appendChild(row);
    refreshNewMenuRecipeRows();
});
newMenuRecipeRows?.addEventListener('change', refreshNewMenuRecipeRows);
newMenuRecipeRows?.addEventListener('click', event => {
    const button = event.target.closest('.remove-new-menu-recipe');
    if (!button) return;
    button.closest('.menu-create-recipe-row').remove();
    refreshNewMenuRecipeRows();
});
refreshNewMenuRecipeRows();

function openEditModal(id, nama, kategori, status) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_kategori').value = kategori;
    document.getElementById('edit_status').value = status;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function openPriceModal(id, name, category, price, profit, status) {
    document.getElementById('price_id').value = id;
    document.getElementById('price_name_value').value = name;
    document.getElementById('price_name_label').value = name;
    document.getElementById('price_category').value = category;
    document.getElementById('price_status').value = status;
    document.getElementById('price_sell').value = price;
    document.getElementById('price_profit').value = profit;
    document.getElementById('price_sell_display').value = formatPriceInput(String(Math.round(Number(price))));
    document.getElementById('price_profit_display').value = formatPriceInput(String(Math.round(Number(profit))));
    document.getElementById('priceModal').style.display = 'flex';
}

function formatPriceInput(value) {
    const number = String(value || '').replace(/\D/g, '');
    return number ? 'Rp' + Number(number).toLocaleString('id-ID') : '';
}

function syncPriceInput(displayId, hiddenId) {
    const display = document.getElementById(displayId);
    const numeric = String(display.value || '').replace(/\D/g, '');
    display.value = formatPriceInput(numeric);
    document.getElementById(hiddenId).value = numeric;
}

document.getElementById('price_sell_display')?.addEventListener('input', () => syncPriceInput('price_sell_display', 'price_sell'));
document.getElementById('price_profit_display')?.addEventListener('input', () => syncPriceInput('price_profit_display', 'price_profit'));

function closePriceModal() {
    document.getElementById('priceModal').style.display = 'none';
}

window.onclick = function(e) {
    ['tambahModal', 'editModal', 'priceModal'].forEach(id => {
        const modal = document.getElementById(id);

        if (modal && e.target === modal) {
            modal.style.display = 'none';
        }
    });
};
</script>

</body>
</html>
