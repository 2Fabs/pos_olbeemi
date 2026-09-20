<?php
include '../config/database.php';
include_once '../includes/stock_units.php';
include_once '../includes/material_packages.php';

ensureStockInputColumns($conn, 'riwayat_masuk');
ensureMaterialPackageTable($conn);
ensureMaterialPackageChainTable($conn);

$is_partial = isset($_GET['partial']) && $_GET['partial'] === '1';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$bahan_id = (int) $_GET['id'];

$bahan_query = mysqli_query($conn, "
    SELECT *
    FROM bahan_baku
    WHERE id = $bahan_id
");

$bahan = mysqli_fetch_assoc($bahan_query);

if (!$bahan) {
    header("Location: index.php");
    exit;
}

$stock_input_options = [];
$base_unit = stockCanonicalUnit($bahan['satuan']);
foreach (purchaseUnitRules()['measures'] as $unit => $definition) {
    if ($definition['base'] !== $base_unit) continue;
    $stock_input_options[$unit] = ['label' => $definition['label'], 'factor' => $definition['factor'], 'is_package' => false];
}
foreach (purchaseUnitRules()['packages'] as $unit) {
    $stock_input_options[$unit] = ['label' => ucfirst(materialPackageLabel($unit)), 'factor' => 0, 'is_package' => true];
}
$package_query = mysqli_query($conn, "SELECT satuan_kemasan, isi_satuan_dasar FROM bahan_kemasan WHERE bahan_id = $bahan_id AND is_default = 1 ORDER BY id DESC LIMIT 1");
if ($package_query && ($package = mysqli_fetch_assoc($package_query))) {
    $stock_input_options[$package['satuan_kemasan']] = ['label' => materialPackageLabel($package['satuan_kemasan']) . ' (1 = ' . formatMaterialPackageQuantity($package['isi_satuan_dasar']) . ' ' . stockInputUnitLabel($bahan['satuan']) . ')', 'factor' => (float) $package['isi_satuan_dasar'], 'is_package' => true];
}
$chain_query = mysqli_query($conn, "SELECT * FROM bahan_kemasan_rantai WHERE bahan_id = $bahan_id LIMIT 1");
if ($chain_query && ($chain = mysqli_fetch_assoc($chain_query))) {
    $middle_factor = (float) $chain['isi_satuan_dasar'];
    $outer_factor = (float) $chain['jumlah_satuan_tengah'] * $middle_factor;
    $stock_input_options[$chain['satuan_tengah']] = ['label' => materialPackageLabel($chain['satuan_tengah']) . ' (1 = ' . formatMaterialPackageQuantity($middle_factor) . ' ' . stockInputUnitLabel($bahan['satuan']) . ')', 'factor' => $middle_factor, 'is_package' => true];
    $stock_input_options[$chain['satuan_luar']] = ['label' => materialPackageLabel($chain['satuan_luar']) . ' (1 = ' . formatMaterialPackageQuantity($outer_factor) . ' ' . stockInputUnitLabel($bahan['satuan']) . ')', 'factor' => $outer_factor, 'is_package' => true];
}
$stock_input_options_json = htmlspecialchars(json_encode($stock_input_options, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES);

$riwayat = mysqli_query($conn, "
    SELECT riwayat_masuk.*,
        EXISTS (
            SELECT 1
            FROM riwayat_keluar
            WHERE riwayat_keluar.bahan_id = riwayat_masuk.bahan_id
            AND riwayat_keluar.tanggal >= riwayat_masuk.tanggal
        ) AS sudah_dipakai
    FROM riwayat_masuk
    WHERE bahan_id = $bahan_id
    ORDER BY tanggal ASC, id ASC
");
ob_start();
?>
<div class="table-card">
    <div id="stockHistoryNotice" class="stock-history-notice" role="alert" hidden></div>

    <div style="margin-bottom:20px;">
        <h2><?= htmlspecialchars($bahan['nama_bahan']) ?></h2>
        <p>
            Stok Saat Ini:
            <strong>
                <?= rtrim(rtrim($bahan['stok'], '0'), '.') ?>
                <span class="unit-symbol" data-unit="<?= htmlspecialchars($bahan['satuan']) ?>"><?= htmlspecialchars($bahan['satuan']) ?></span>
            </strong>
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Keterangan</th>
                <th>Perubahan</th>
                <th>Total Stok</th>
                <th>Tanggal</th>
                <th>Aksi</th>
            </tr>
        </thead>

        <tbody>
            <?php
            $no = 1;
            while ($row = mysqli_fetch_assoc($riwayat)):
            ?>
            <tr>
                <td><?= $no++ ?></td>

                <td><?= htmlspecialchars($row['keterangan']) ?></td>

                <td>
                    +<?= rtrim(rtrim($row['jumlah_masuk'], '0'), '.') ?>
                    <span class="unit-symbol" data-unit="<?= htmlspecialchars($row['satuan']) ?>"><?= htmlspecialchars($row['satuan']) ?></span>
                </td>

                <td>
                    <?= rtrim(rtrim($row['total_setelah'], '0'), '.') ?>
                    <span class="unit-symbol" data-unit="<?= htmlspecialchars($row['satuan']) ?>"><?= htmlspecialchars($row['satuan']) ?></span>
                </td>

                <td>
                    <?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?>
                </td>

                <td>
                    <div class="action-group">
                        <button
                            type="button"
                            class="btn-edit open-edit-riwayat-btn"
                            data-id="<?= $row['id'] ?>"
                            data-keterangan="<?= htmlspecialchars($row['keterangan'], ENT_QUOTES) ?>"
                            data-jumlah="<?= rtrim(rtrim($row['jumlah_masuk'], '0'), '.') ?>"
                            data-jumlah-input="<?= rtrim(rtrim($row['jumlah_input'] ?: $row['jumlah_masuk'], '0'), '.') ?>"
                            data-satuan-input="<?= htmlspecialchars($row['satuan_input'] ?: $bahan['satuan'], ENT_QUOTES) ?>"
                            data-package-content="<?= (float) ($row['isi_per_kemasan'] ?? 0) ?>"
                            data-input-options="<?= $stock_input_options_json ?>"
                            data-blocked="<?= (int) $row['sudah_dipakai'] ?>">
                            Edit
                        </button>
                        <form action="hapus_riwayat_stok.php" method="POST" class="delete-stock-history-form" data-blocked="<?= (int) $row['sudah_dipakai'] ?>">
                            <?= auth_csrf_input() ?>
                            <input type="hidden" name="riwayat_id" value="<?= (int) $row['id'] ?>">
                            <button type="submit" class="btn-delete">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <?php if (!$is_partial): ?>
        <div style="margin-top:20px;">
            <a href="index.php" class="btn-delete">
                Kembali
            </a>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

if ($is_partial) {
    echo $content;
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Riwayat Stok</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/bahan.css">
</head>
<body>

<?php
$current_module = 'edit_stok';
include '../includes/sidebar.php';
?>
<div class="main-content">
    <div class="header">
        <div>
            <h1>Edit Riwayat Stok</h1>
            <p>Pilih histori stok yang ingin diubah</p>
        </div>
    </div>

    <?= $content ?>
</div>

<!-- MODAL EDIT RIWAYAT -->
<div id="editRiwayatModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Riwayat Stok</h2>
            <span class="close-btn" onclick="closeEditRiwayatModal()">&times;</span>
        </div>

        <form action="update_riwayat_stok.php" method="POST">
            <?= auth_csrf_input() ?>
            <input type="hidden" name="riwayat_id" id="riwayat_id">

            <div class="form-group">
                <label>Keterangan</label>
                <input type="text" name="keterangan" id="riwayat_keterangan" required>
            </div>

            <div class="form-group">
                <label>Jumlah</label>
                <input type="number" step="0.01" name="jumlah_masuk" id="riwayat_jumlah" required>
            </div>

            <div class="form-group">
                <label>Satuan Input</label>
                <select name="satuan_input" id="riwayat_satuan_input" required></select>
                <small class="supplier-form-note" id="riwayat_conversion_note"></small>
            </div>

            <div class="form-group" id="riwayat_package_field" hidden>
                <label id="riwayat_package_label">Isi 1 Kemasan</label>
                <input type="number" step="0.001" min="0.001" name="isi_per_kemasan" id="riwayat_package_content">
                <small class="supplier-form-note">Masukkan hasil konversi ke satuan stok dasar.</small>
            </div>

            <button type="submit" class="btn-primary">
                Update Riwayat
            </button>
        </form>
    </div>
</div>

<script>
function openEditRiwayatModal(id, keterangan, jumlah, jumlahInput, satuanInput, packageContent, inputOptions) {
    document.getElementById('riwayat_id').value = id;
    document.getElementById('riwayat_keterangan').value = keterangan;
    document.getElementById('riwayat_jumlah').value = jumlahInput || jumlah;
    const unitSelect = document.getElementById('riwayat_satuan_input');
    unitSelect.replaceChildren();
    Object.entries(inputOptions || {}).forEach(([unit, option]) => {
        const inputOption = new Option(option.label, unit);
        inputOption.dataset.package = option.is_package ? '1' : '0';
        inputOption.dataset.factor = option.factor || '';
        unitSelect.add(inputOption);
    });
    unitSelect.value = satuanInput;
    if (!unitSelect.value && unitSelect.options.length) unitSelect.selectedIndex = 0;
    updateRiwayatPackageField();
    document.getElementById('riwayat_package_content').value = packageContent || document.getElementById('riwayat_package_content').value;
    document.getElementById('editRiwayatModal').style.display = 'flex';
}

function updateRiwayatPackageField() {
    const select = document.getElementById('riwayat_satuan_input');
    const option = select.options[select.selectedIndex];
    const packageField = document.getElementById('riwayat_package_field');
    const packageInput = document.getElementById('riwayat_package_content');
    const isPackage = option && option.dataset.package === '1';
    packageField.hidden = !isPackage;
    packageInput.required = isPackage;
    if (isPackage) {
        document.getElementById('riwayat_package_label').textContent = `Isi 1 ${option.value} dalam satuan stok dasar`;
        packageInput.value = option.dataset.factor || '';
    } else {
        packageInput.value = '';
    }
    document.getElementById('riwayat_conversion_note').textContent = isPackage ? 'Konversi kemasan akan dipakai untuk menghitung stok.' : '';
}

document.getElementById('riwayat_satuan_input').addEventListener('change', updateRiwayatPackageField);

function closeEditRiwayatModal() {
    document.getElementById('editRiwayatModal').style.display = 'none';
}

document.addEventListener('click', function(e) {
    const button = e.target.closest('.open-edit-riwayat-btn');

    if (!button) {
        return;
    }

    openEditRiwayatModal(
        button.dataset.id,
        button.dataset.keterangan,
        button.dataset.jumlah,
        button.dataset.jumlahInput,
        button.dataset.satuanInput,
        button.dataset.packageContent,
        JSON.parse(button.dataset.inputOptions || '{}')
    );
});

window.onclick = function(e) {
    const modal = document.getElementById('editRiwayatModal');

    if (e.target === modal) {
        modal.style.display = 'none';
    }
};
</script>

</body>
</html>
