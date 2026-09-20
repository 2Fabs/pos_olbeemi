<?php
include '../config/database.php';
include_once '../includes/stock_units.php';
include_once '../includes/material_packages.php';
include_once '../includes/suppliers.php';
$is_owner = auth_has_role('owner');
ensureMaterialPackageTable($conn);
ensureMaterialPackageChainTable($conn);
ensureSupplierTables($conn);

// Bahan baru dari pesanan lama menyimpan konversi pada detail pesanan saja.
// Jadikan konversi pesanan terakhir sebagai kemasan awal bila belum ada konfigurasi.
$package_units_sql = "'dus','pack','botol','galon','sachet','kaleng','bag','kantong','kotak'";
$missing_package_query = mysqli_query($conn, "
    SELECT p.bahan_id, p.satuan_input, p.isi_per_kemasan
    FROM pesanan_supplier p
    WHERE p.satuan_input IN ($package_units_sql)
      AND p.isi_per_kemasan > 0
      AND p.id = (SELECT MAX(p2.id) FROM pesanan_supplier p2 WHERE p2.bahan_id = p.bahan_id)
      AND NOT EXISTS (
          SELECT 1 FROM bahan_kemasan k
          WHERE k.bahan_id = p.bahan_id AND k.is_default = 1
      )
");
while ($missing_package_query && ($missing_package = mysqli_fetch_assoc($missing_package_query))) {
    $package_material_id = (int) $missing_package['bahan_id'];
    $package_unit_safe = mysqli_real_escape_string($conn, $missing_package['satuan_input']);
    $package_content = (float) $missing_package['isi_per_kemasan'];
    mysqli_query($conn, "
        INSERT INTO bahan_kemasan (bahan_id, satuan_kemasan, isi_satuan_dasar, is_default, is_verified)
        VALUES ($package_material_id, '$package_unit_safe', '$package_content', 1, 0)
    ");
}

// Track changes from every stock workflow without modifying each write endpoint.
$activity_column = mysqli_query($conn, "SHOW COLUMNS FROM bahan_baku LIKE 'updated_at'");
if ($activity_column && mysqli_num_rows($activity_column) === 0) {
    mysqli_query($conn, "ALTER TABLE bahan_baku ADD COLUMN updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)");
    mysqli_query($conn, "
        UPDATE bahan_baku b
        LEFT JOIN (SELECT bahan_id, MAX(tanggal) AS terakhir FROM riwayat_masuk GROUP BY bahan_id) masuk ON masuk.bahan_id = b.id
        LEFT JOIN (SELECT bahan_id, MAX(tanggal) AS terakhir FROM riwayat_keluar GROUP BY bahan_id) keluar ON keluar.bahan_id = b.id
        SET b.updated_at = GREATEST(b.created_at, COALESCE(masuk.terakhir, b.created_at), COALESCE(keluar.terakhir, b.created_at))
    ");
}

$query = "
    SELECT *
    FROM bahan_baku
    ORDER BY
        CASE WHEN stok <= minimum_stok THEN 0 ELSE 1 END ASC,
        updated_at DESC,
        id DESC
";
$result = mysqli_query($conn, $query);

$package_by_material = [];
$package_query = mysqli_query($conn, "SELECT bahan_id, satuan_kemasan, isi_satuan_dasar, is_verified FROM bahan_kemasan WHERE is_default = 1 ORDER BY id DESC");
while ($package_query && $package = mysqli_fetch_assoc($package_query)) {
    $material_id = (int) $package['bahan_id'];
    if (!isset($package_by_material[$material_id])) $package_by_material[$material_id] = $package;
}
$package_chain_by_material = [];
$chain_query = mysqli_query($conn, "SELECT * FROM bahan_kemasan_rantai");
while ($chain_query && $chain = mysqli_fetch_assoc($chain_query)) $package_chain_by_material[(int) $chain['bahan_id']] = $chain;

$existing_bahan = [];
$supplier_options = null;

if ($is_owner) {
    $bahan_list = mysqli_query($conn, "SELECT nama_bahan FROM bahan_baku ORDER BY nama_bahan ASC");
    while ($b = mysqli_fetch_assoc($bahan_list)) {
        $existing_bahan[] = $b['nama_bahan'];
    }

    $supplier_options = mysqli_query($conn, "
        SELECT id, nama_supplier
        FROM supplier
        ORDER BY nama_supplier ASC
    ");
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Bahan Baku</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/bahan.css?v=<?= filemtime(__DIR__ . '/../css/bahan.css') ?>">
</head>
<body>

<?php
$current_module = 'bahan';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Stok Bahan Baku</h1>
            <p><?= $is_owner ? 'Kelola stok bahan baku coffeeshop' : 'Pantau ketersediaan bahan baku coffeeshop' ?></p>
        </div>

    </div>

    <div class="search-box bahan-search-box">
        <input type="text" id="searchInput" placeholder="Cari bahan baku...">
    </div>

    <div class="bahan-quick-actions">
        <?php if ($is_owner): ?>
        <button type="button" class="btn-primary add-bahan-quick-btn" onclick="openTambahModal()">
            + Tambah Bahan
        </button>
        <?php endif; ?>
        <?php if (!$is_owner): ?>
        <a href="permintaan_stok.php" class="btn-primary add-bahan-quick-btn">Ajukan Pengurangan</a>
        <?php else: ?>
        <a href="permintaan_stok.php?mode=perubahan" class="btn-stock">Pengajuan Perubahan</a>
        <?php endif; ?>
        <button type="button" id="lowStockFilter" class="btn-stock low-stock-filter" aria-pressed="false">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M10.3 3.8 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z" />
                <path d="M12 9v4" />
                <path d="M12 17h.01" />
            </svg>
            <span>Stok Menipis <strong id="lowStockCount"></strong></span>
        </button>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Bahan</th>
                    <th>Total Stok</th>
                    <th>Stok per Kemasan</th>
                    <th>Minimum</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody id="bahanTable">
                <?php
                $no = 1;
                while ($row = mysqli_fetch_assoc($result)):
                    $status = ($row['stok'] <= $row['minimum_stok']) ? 'Menipis' : 'Aman';
                    $badgeClass = ($status == 'Menipis') ? 'badge-danger' : 'badge-success';
                    $package = $package_by_material[(int) $row['id']] ?? null;
                    $chain = $package_chain_by_material[(int) $row['id']] ?? null;
                    $package_breakdown = $package
                        ? formatStockPackageBreakdown($row['stok'], $package['isi_satuan_dasar'], $package['satuan_kemasan'], $row['satuan'])
                        : null;
                    if ($chain) $package_breakdown = formatStockPackageChainBreakdown($row['stok'], $chain['jumlah_satuan_tengah'], $chain['isi_satuan_dasar'], $chain['satuan_luar'], $chain['satuan_tengah'], $row['satuan']);
                ?>
                <tr data-stock-status="<?= strtolower($status) ?>">
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['nama_bahan']) ?></td>
                    <td class="stock-total-cell">
                        <strong class="stock-total-value">
                            <?= rtrim(rtrim($row['stok'], '0'), '.') ?>
                            <span class="unit-symbol" data-unit="<?= htmlspecialchars($row['satuan']) ?>"><?= htmlspecialchars($row['satuan']) ?></span>
                        </strong>
                    </td>
                    <td class="stock-package-cell">
                        <?php if ($package_breakdown !== null): ?>
                            <strong><?= htmlspecialchars(str_replace(' + ', ' · ', $package_breakdown)) ?></strong>
                        <?php else: ?>
                            <span class="stock-package-empty">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= rtrim(rtrim($row['minimum_stok'], '0'), '.') ?> <span class="unit-symbol" data-unit="<?= htmlspecialchars($row['satuan']) ?>"><?= htmlspecialchars($row['satuan']) ?></span></td>

                    <td>
                        <span class="badge <?= $badgeClass ?>">
                            <?= $status ?>
                        </span>
                    </td>

                    <?php if ($is_owner): ?>
                    <td>
                        <div class="material-actions">
                            <button type="button" class="btn-edit" onclick="openEditStockModal(<?= $row['id'] ?>)">Edit Stok</button>
                            <details class="material-action-menu">
                                <summary aria-label="Aksi lainnya untuk <?= htmlspecialchars($row['nama_bahan'], ENT_QUOTES) ?>">•••</summary>
                                <div class="material-action-menu-list">
                                    <button type="button" class="open-edit-info-btn" data-id="<?= (int) $row['id'] ?>" data-name="<?= htmlspecialchars($row['nama_bahan'], ENT_QUOTES) ?>" data-unit="<?= htmlspecialchars($row['satuan'], ENT_QUOTES) ?>" data-minimum="<?= rtrim(rtrim($row['minimum_stok'], '0'), '.') ?>" data-package="<?= htmlspecialchars(json_encode($package ? ['unit' => $package['satuan_kemasan'], 'content' => (float) $package['isi_satuan_dasar'], 'verified' => (bool) $package['is_verified']] : null), ENT_QUOTES) ?>" data-chain="<?= htmlspecialchars(json_encode($chain ? ['outer' => $chain['satuan_luar'], 'middle' => $chain['satuan_tengah'], 'middleCount' => (float) $chain['jumlah_satuan_tengah'], 'baseContent' => (float) $chain['isi_satuan_dasar'], 'verified' => (bool) $chain['is_verified']] : null), ENT_QUOTES) ?>">Edit Info</button>
                                    <button type="button" class="open-package-modal-btn" data-id="<?= (int) $row['id'] ?>" data-name="<?= htmlspecialchars($row['nama_bahan'], ENT_QUOTES) ?>" data-unit="<?= htmlspecialchars($row['satuan'], ENT_QUOTES) ?>" data-package="<?= htmlspecialchars(json_encode($package ? ['unit' => $package['satuan_kemasan'], 'content' => (float) $package['isi_satuan_dasar'], 'verified' => (bool) $package['is_verified']] : null), ENT_QUOTES) ?>" data-chain="<?= htmlspecialchars(json_encode($chain ? ['outer' => $chain['satuan_luar'], 'middle' => $chain['satuan_tengah'], 'middleCount' => (float) $chain['jumlah_satuan_tengah'], 'baseContent' => (float) $chain['isi_satuan_dasar'], 'verified' => (bool) $chain['is_verified']] : null), ENT_QUOTES) ?>">Atur Kemasan</button>
                                    <form action="delete.php" method="POST" onsubmit="return confirm('Hapus bahan ini?')"><?= auth_csrf_input() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button type="submit">Hapus Bahan</button></form>
                                </div>
                            </details>
                        </div>
                    </td>
                    <?php else: ?>
                    <td><a href="permintaan_stok.php" class="btn-edit">Ajukan Perubahan</a></td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
                <tr id="bahanEmptyFilter" style="display:none;">
                    <td colspan="7">Tidak ada bahan yang sesuai dengan filter.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php if ($is_owner): ?>
<!-- MODAL EDIT STOK -->
<div id="editStockModal" class="modal">
    <div class="modal-content" style="width:min(1100px, 96vw); max-width:1100px;">
        <div class="modal-header">
            <h2>Edit Riwayat Stok</h2>
            <span class="close-btn" onclick="closeEditStockModal()">&times;</span>
        </div>

        <div id="editStockContent">
            <div class="table-card">
                <p>Memuat data stok...</p>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDIT RIWAYAT STOK -->
<div id="editRiwayatModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Riwayat Stok</h2>
            <span class="close-btn" onclick="closeEditRiwayatModal()">&times;</span>
        </div>

        <form action="update_riwayat_stok.php" method="POST">
            <?= auth_csrf_input() ?>
            <input type="hidden" name="riwayat_id" id="riwayat_id">
            <input type="hidden" name="redirect_to" value="index">

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
                Simpan Perubahan
            </button>
        </form>

    </div>
</div>

<!-- MODAL TAMBAH -->
<div id="tambahModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Tambah Bahan Baru</h2>
            <span class="close-btn" onclick="closeTambahModal()">&times;</span>
        </div>

        <form action="simpan_bahan.php" method="POST" id="multiBahanForm">
            <?= auth_csrf_input() ?>
            <div id="bahanInputRows" class="bahan-input-rows"></div>

            <button type="button" class="btn-stock add-bahan-row-btn" id="addBahanRowBtn">
                + Tambah Bahan Lain
            </button>

            <h3 class="bahan-form-section-title">Data Supplier</h3>

            <div class="form-group">
                <label>Supplier</label>
                <select name="supplier_id" id="newMaterialSupplierSelect" required>
                    <option value="">Pilih Supplier</option>
                    <?php while ($supplier_option = mysqli_fetch_assoc($supplier_options)): ?>
                        <option value="<?= (int) $supplier_option['id'] ?>">
                            <?= htmlspecialchars($supplier_option['nama_supplier']) ?>
                        </option>
                    <?php endwhile; ?>
                    <option value="new">+ Tambah Supplier Baru</option>
                </select>
                <small class="supplier-form-note">Semua bahan di atas akan terhubung ke supplier yang dipilih.</small>
            </div>

            <div id="newSupplierFields" class="new-supplier-fields" hidden>
                <div class="form-group">
                    <label>Nama Supplier Baru</label>
                    <input type="text" name="nama_supplier" autocomplete="organization">
                </div>

                <div class="form-group">
                    <label>Nomor Telepon Supplier</label>
                    <input type="text" name="no_telepon_supplier" placeholder="Contoh: 081234567890" autocomplete="tel">
                </div>

                <div class="form-group new-supplier-address">
                    <label>Alamat Supplier</label>
                    <textarea name="alamat_supplier" rows="2"></textarea>
                </div>
            </div>

            <button type="submit" class="btn-primary">Simpan Semua Bahan</button>
        </form>
    </div>
</div>

<template id="bahanInputRowTemplate">
    <section class="bahan-input-row">
        <div class="bahan-input-row-header">
            <strong class="bahan-row-title">Bahan 1</strong>
            <button type="button" class="remove-bahan-row" aria-label="Hapus baris bahan">Hapus</button>
        </div>
        <div class="bahan-input-grid">
            <div class="form-group bahan-name-field">
                <label>Nama Bahan</label>
                <input type="text" name="nama_bahan[]" class="nama-bahan-input" autocomplete="off" required>
                <div class="duplicate-notice" aria-live="polite"></div>
            </div>
            <div class="form-group">
                <label>Jumlah Pembelian</label>
                <input type="number" step="0.01" min="0.01" name="stok[]" required>
            </div>
            <div class="form-group">
                <label>Satuan Pembelian</label>
                <select name="satuan_pembelian[]" class="purchase-unit" required>
                    <option value="">Pilih satuan pembelian</option>
                    <option value="g">Gram (g)</option>
                    <option value="kg">Kilogram (kg)</option>
                    <option value="ml">Mililiter (ml)</option>
                    <option value="liter">Liter (L)</option>
                    <option value="pcs">pcs</option>
                    <option value="dus">Dus</option>
                    <option value="pack">Pack</option>
                    <option value="botol">Botol</option>
                    <option value="galon">Galon</option>
                    <option value="sachet">Sachet</option>
                    <option value="kaleng">Kaleng</option>
                </select>
            </div>
            <div class="form-group">
                <label>Satuan Dasar</label>
                <input name="satuan[]" class="material-base-unit" readonly placeholder="Mengikuti satuan akhir konversi">
            </div>
            <div class="request-conversion-preview material-conversion" aria-live="polite">Hasil Konversi Stok: -</div>
            <div class="form-group material-package" hidden>
                <label>Konversi Kemasan</label>
                <div class="package-conversion-chain"></div>
                <button type="button" class="btn-add-conversion add-material-conversion">+ Tambah Konversi</button>
                <small class="supplier-form-note">Contoh: 1 dus = 10 pack, lalu 1 pack = 50 pcs.</small>
                <input type="hidden" name="isi_per_kemasan[]" value="0">
                <input type="hidden" name="konversi[]" value="[]">
            </div>
            <div class="form-group">
                <label>Minimum Stok (satuan dasar)</label>
                <input type="number" step="0.01" min="0" name="minimum_stok[]" required>
            </div>
            <div class="form-group">
                <label>Harga per Satuan Pembelian (Rp)</label>
                <div class="material-price-input">
                    <span aria-hidden="true">Rp</span>
                    <input type="number" name="harga_supplier[]" min="0" step="0.01" placeholder="Contoh: 10000" aria-label="Harga per satuan pembelian dalam rupiah" required>
                </div>
            </div>
            <div class="form-group">
                <label>Total Pembelian (Rp)</label>
                <input type="hidden" name="total_pembelian[]" value="0">
                <input type="text" class="purchase-total-display" value="Rp0" readonly aria-label="Total Pembelian">
            </div>
        </div>
    </section>
</template>

<!-- MODAL EDIT INFO -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Info Bahan</h2>
            <span class="close-btn" onclick="closeEditModal()">&times;</span>
        </div>

        <form action="update_bahan.php" method="POST">
            <?= auth_csrf_input() ?>
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group">
                <label>Nama Bahan</label>
                <input type="text" name="nama_bahan" id="edit_nama" required>
            </div>

            <div class="form-group">
                <label>Satuan Stok</label>
                <input id="edit_satuan" readonly aria-describedby="edit_satuan_note">
                <small id="edit_satuan_note" class="supplier-form-note">Satuan stok dikunci agar perubahan satuan tidak mengubah arti jumlah stok. Buat bahan baru bila satuan dasarnya memang berbeda.</small>
            </div>

            <div class="form-group">
                <label>Minimum Stok</label>
                <input type="number" step="0.01" name="minimum_stok" id="edit_minimum" required>
            </div>

            <button type="submit" class="btn-primary">Simpan Perubahan</button>
        </form>
    </div>
</div>

<!-- MODAL ATUR KEMASAN -->
<div id="packageModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="packageModalTitle">Atur Kemasan</h2>
            <span class="close-btn" onclick="closePackageModal()">&times;</span>
        </div>
        <form action="simpan_kemasan_bahan.php" method="POST" id="packageConversionForm" style="margin-top:16px; border-top:1px solid #ddd; padding-top:16px;">
            <?= auth_csrf_input() ?>
            <input type="hidden" name="bahan_id" id="package_bahan_id">
            <h3 id="packageConversionTitle">Atur Kemasan Satu Tingkat</h3>
            <p class="supplier-form-note">Gunakan bila 1 kemasan langsung berisi satuan dasar, misalnya 1 botol = 1.000 ml.</p>
            <div class="form-group">
                <label>Kemasan Default</label>
                <select name="satuan_kemasan" id="package_unit" required>
                    <option value="">Pilih kemasan</option>
                    <option value="botol">Botol</option><option value="pack">Pack</option><option value="dus">Dus</option><option value="kotak">Kotak</option>
                    <option value="galon">Galon</option><option value="sachet">Sachet</option><option value="kaleng">Kaleng</option><option value="bag">Bag</option><option value="kantong">Kantong</option>
                </select>
            </div>
            <div class="form-group">
                <label id="package_content_label">Isi 1 Kemasan</label>
                <input type="number" step="0.001" min="0.001" name="isi_satuan_dasar" id="package_content" required>
            </div>
            <label class="supplier-form-note"><input type="checkbox" name="is_verified" id="package_verified" value="1"> Ukuran sudah diverifikasi dari kemasan/nota supplier</label>
            <button type="submit" class="btn-stock">Simpan Konversi Kemasan</button>
        </form>

        <form action="simpan_rantai_kemasan.php" method="POST" class="chain-package-form">
            <?= auth_csrf_input() ?><input type="hidden" name="bahan_id" id="chain_bahan_id">
            <h3 id="chainConversionTitle">Atur Kemasan Bertingkat</h3>
            <p class="supplier-form-note">Untuk kemasan bertingkat, misalnya 1 dus = 5 botol dan 1 botol = 1.000 ml.</p>
            <div class="form-group"><label>Kemasan Luar</label><select name="satuan_luar" id="chain_outer" required><option value="">Pilih</option><option>dus</option><option>pack</option><option>botol</option><option>galon</option><option>sachet</option><option>kaleng</option><option>bag</option><option>kantong</option><option>kotak</option></select></div>
            <div class="chain-middle-fields"><div class="form-group"><label>Jumlah Isi Kemasan Luar</label><input type="number" step="0.001" min="0.001" name="jumlah_satuan_tengah" id="chain_middle_count" placeholder="Contoh: 5" required></div><div class="form-group"><label>Satuan Isi</label><select name="satuan_tengah" id="chain_middle_unit" required><option value="">Pilih satuan</option><option>dus</option><option>pack</option><option>botol</option><option>galon</option><option>sachet</option><option>kaleng</option><option>bag</option><option>kantong</option><option>kotak</option></select></div></div>
            <div class="form-group"><label id="chain_base_label">Isi 1 kemasan tengah</label><input type="number" step="0.001" min="0.001" name="isi_satuan_dasar" id="chain_base_content" placeholder="Contoh: 1000" required></div>
            <label class="supplier-form-note"><input type="checkbox" name="is_verified" id="chain_verified" value="1"> Ukuran sudah diverifikasi dari kemasan/nota supplier</label>
            <button type="submit" class="btn-stock">Simpan Konversi Bertingkat</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
const searchInput = document.getElementById('searchInput');
const lowStockFilter = document.getElementById('lowStockFilter');
const lowStockCountText = document.getElementById('lowStockCount');
const bahanRows = Array.from(document.querySelectorAll('#bahanTable tr[data-stock-status]'));
const bahanEmptyFilter = document.getElementById('bahanEmptyFilter');
let showLowStockOnly = false;

function applyBahanFilters() {
    const keyword = searchInput.value.toLowerCase().trim();
    let visibleRows = 0;

    bahanRows.forEach(row => {
        const matchesSearch = row.textContent.toLowerCase().includes(keyword);
        const matchesStock = !showLowStockOnly || row.dataset.stockStatus === 'menipis';
        const isVisible = matchesSearch && matchesStock;

        row.style.display = isVisible ? '' : 'none';
        visibleRows += isVisible ? 1 : 0;
    });

    bahanEmptyFilter.style.display = visibleRows === 0 ? '' : 'none';
}

const lowStockCount = bahanRows.filter(row => row.dataset.stockStatus === 'menipis').length;
lowStockCountText.textContent = `(${lowStockCount})`;

searchInput.addEventListener('input', applyBahanFilters);

lowStockFilter.addEventListener('click', function () {
    showLowStockOnly = !showLowStockOnly;
    this.classList.toggle('is-active', showLowStockOnly);
    this.setAttribute('aria-pressed', showLowStockOnly ? 'true' : 'false');
    applyBahanFilters();
});

function openTambahModal() {
    document.getElementById('tambahModal').style.display = 'flex';
}

function closeTambahModal() {
    document.getElementById('tambahModal').style.display = 'none';
}

function openEditModal(id, nama, satuan, minimum) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_satuan').value = satuan;
    document.getElementById('edit_minimum').value = minimum;
    document.getElementById('editModal').style.display = 'flex';
}

function openPackageModal(id, nama, satuan, packageData, chainData) {
    document.getElementById('package_bahan_id').value = id;
    document.getElementById('chain_bahan_id').value = id;
    document.getElementById('package_unit').value = packageData ? packageData.unit : '';
    document.getElementById('package_content').value = packageData ? packageData.content : '';
    document.getElementById('package_verified').checked = packageData ? packageData.verified : false;
    document.getElementById('chain_outer').value = chainData ? chainData.outer : '';
    document.getElementById('chain_middle_unit').value = chainData ? chainData.middle : '';
    document.getElementById('chain_middle_count').value = chainData ? chainData.middleCount : '';
    document.getElementById('chain_base_content').value = chainData ? chainData.baseContent : '';
    document.getElementById('chain_verified').checked = chainData ? chainData.verified : false;
    document.getElementById('packageConversionTitle').textContent = chainData ? 'Ganti ke Kemasan Satu Tingkat' : (packageData ? 'Perbarui Kemasan Satu Tingkat' : 'Atur Kemasan Satu Tingkat');
    document.getElementById('chainConversionTitle').textContent = chainData ? 'Perbarui Kemasan Bertingkat' : 'Atur Kemasan Bertingkat';
    document.getElementById('package_content_label').textContent = 'Isi 1 kemasan dalam ' + satuan;
    document.getElementById('chain_base_label').textContent = 'Isi 1 kemasan tengah dalam ' + satuan;
    document.getElementById('packageModalTitle').textContent = 'Atur Kemasan: ' + nama;
    document.getElementById('packageModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function closePackageModal() {
    document.getElementById('packageModal').style.display = 'none';
}

function openEditStockModal(id) {
    const modal = document.getElementById('editStockModal');
    const content = document.getElementById('editStockContent');

    content.innerHTML = '<div class="table-card"><p>Memuat data stok...</p></div>';
    modal.style.display = 'flex';

    fetch('edit_stok.php?id=' + id + '&partial=1')
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(() => {
            content.innerHTML = '<div class="table-card"><p>Gagal memuat data stok.</p></div>';
        });
}

function closeEditStockModal() {
    document.getElementById('editStockModal').style.display = 'none';
    document.getElementById('editStockContent').innerHTML = '';
}

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

const riwayatUnitSelect = document.getElementById('riwayat_satuan_input');
if (riwayatUnitSelect) riwayatUnitSelect.addEventListener('change', updateRiwayatPackageField);

function closeEditRiwayatModal() {
    document.getElementById('editRiwayatModal').style.display = 'none';
}

function showStockHistoryNotice(message) {
    const notice = document.getElementById('stockHistoryNotice');
    if (!notice) {
        return;
    }

    notice.textContent = message;
    notice.hidden = false;
    notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

window.onclick = function(e) {
    ['tambahModal', 'editModal', 'packageModal', 'editStockModal', 'editRiwayatModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
};

document.addEventListener('click', function(e) {
    const infoButton = e.target.closest('.open-edit-info-btn');
    if (infoButton) {
        openEditModal(
            Number(infoButton.dataset.id),
            infoButton.dataset.name,
            infoButton.dataset.unit,
            infoButton.dataset.minimum,
            JSON.parse(infoButton.dataset.package || 'null'),
            JSON.parse(infoButton.dataset.chain || 'null')
        );
        return;
    }

    const packageButton = e.target.closest('.open-package-modal-btn');
    if (packageButton) {
        openPackageModal(
            Number(packageButton.dataset.id),
            packageButton.dataset.name,
            packageButton.dataset.unit,
            JSON.parse(packageButton.dataset.package || 'null'),
            JSON.parse(packageButton.dataset.chain || 'null')
        );
        return;
    }

    const button = e.target.closest('.open-edit-riwayat-btn');

    if (!button) {
        return;
    }

    if (button.dataset.blocked === '1') {
        showStockHistoryNotice('Riwayat tidak dapat diedit karena stok sudah digunakan.');
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

document.addEventListener('submit', function(e) {
    const form = e.target.closest('.delete-stock-history-form');
    if (!form) {
        return;
    }

    if (form.dataset.blocked === '1') {
        e.preventDefault();
        showStockHistoryNotice('Riwayat tidak dapat dihapus karena stok sudah digunakan.');
        return;
    }

    if (!confirm('Hapus riwayat stok masuk dan laporan pengeluaran terkait?')) {
        e.preventDefault();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const bahanExisting = <?= json_encode(array_map('strtolower', $existing_bahan)) ?>;
    const rowsContainer = document.getElementById('bahanInputRows');
    const rowTemplate = document.getElementById('bahanInputRowTemplate');
    const supplierSelect = document.getElementById('newMaterialSupplierSelect');
    const newSupplierFields = document.getElementById('newSupplierFields');

    if (!rowsContainer || !rowTemplate || !supplierSelect || !newSupplierFields) {
        return;
    }

    function toggleNewSupplierFields() {
        const isNewSupplier = supplierSelect.value === 'new';
        newSupplierFields.hidden = !isNewSupplier;
        newSupplierFields.querySelectorAll('input, textarea').forEach(field => {
            field.required = isNewSupplier;
        });
    }

    const materialRules = <?= json_encode(purchaseUnitRules()) ?>;
    const materialPackageUnits = materialRules.packages;

    function materialUnitLabel(unit) {
        return ({ g: 'g', kg: 'kg', ml: 'ml', liter: 'L', pcs: 'pcs', dus: 'dus', pack: 'pack', botol: 'botol', galon: 'galon', sachet: 'sachet' })[unit] || unit;
    }

    function materialUnitFactor(inputUnit, baseUnit) {
        const rule = materialRules.measures[inputUnit];
        return rule && rule.base === baseUnit ? rule.factor : null;
    }

    function materialConversionTargets(baseUnit, sourceUnit) {
        const finalUnits = Object.entries(materialRules.measures).map(([unit, rule]) => [unit, rule.label]);
        const smallerPackages = (materialRules.children[sourceUnit] || []).map(unit => [unit, unit.charAt(0).toUpperCase() + unit.slice(1)]);
        return [['', 'Pilih satuan']].concat(finalUnits, smallerPackages);
    }

    function createMaterialPackageConversion(row, purchaseUnit, baseUnit) {
        const field = row.querySelector('.material-package');
        const chain = field.querySelector('.package-conversion-chain');
        const addButton = field.querySelector('.add-material-conversion');
        const content = field.querySelector('input[name="isi_per_kemasan[]"]');

        function chainRows() {
            return Array.from(chain.querySelectorAll('.package-conversion-row'));
        }

        function calculate() {
            let multiplier = 1;
            const rows = chainRows();
            const finalUnit = rows.length ? rows[rows.length - 1].querySelector('select').value : '';
            baseUnit = materialRules.measures[finalUnit]?.base || '';
            row.querySelector('.material-base-unit').value = baseUnit;
            row.querySelector('[name="konversi[]"]').value = JSON.stringify(rows.map(item => ({quantity: item.querySelector('input').value, unit: item.querySelector('select').value})));
            for (let index = 0; index < rows.length; index++) {
                const quantity = Number(rows[index].querySelector('input').value);
                const target = rows[index].querySelector('select').value;
                if (!Number.isFinite(quantity) || quantity <= 0 || !target) return null;
                multiplier *= quantity;
                if (!Number.isFinite(multiplier)) return null;
                const factor = materialUnitFactor(target, baseUnit);
                if (factor !== null) return index === rows.length - 1 && Number.isFinite(multiplier * factor) ? multiplier * factor : null;
                if (!materialPackageUnits.includes(target) || index === rows.length - 1) return null;
            }
            return null;
        }

        function addRow(sourceUnit) {
            const conversionRow = document.createElement('div');
            conversionRow.className = 'package-conversion-row';
            const source = document.createElement('span');
            source.textContent = `1 ${materialUnitLabel(sourceUnit)} =`;
            const quantity = document.createElement('input');
            quantity.type = 'number'; quantity.min = '0.01'; quantity.step = '0.01'; quantity.placeholder = 'Jumlah';
            quantity.required = true;
            const target = document.createElement('select');
            target.required = true;
            const targets = materialConversionTargets('', chainRows().length ? '' : sourceUnit);
            targets.forEach(([value, label]) => target.add(new Option(label, value)));
            if (targets.filter(([value]) => value).length === 1) target.value = baseUnit;
            quantity.addEventListener('input', () => updateMaterialConversion(row, false));
            target.addEventListener('change', function () {
                const rows = chainRows();
                const currentIndex = rows.indexOf(conversionRow);
                rows.slice(currentIndex + 1).forEach(nextRow => nextRow.remove());
                addButton.hidden = true;
                if (materialPackageUnits.includes(this.value) && currentIndex === 0) addRow(this.value);
                updateMaterialConversion(row, false);
            });
            conversionRow.append(source, quantity, target);
            chain.append(conversionRow);
        }

        chain.replaceChildren();
        content.value = '0';
        addButton.disabled = false;
        addButton.hidden = true;
        addRow(purchaseUnit);
        addButton.onclick = function () {
            const rows = chainRows();
            const nextUnit = rows.length ? rows[rows.length - 1].querySelector('select').value : '';
            if (!materialPackageUnits.includes(nextUnit)) {
                alert('Pilih satuan kemasan pada konversi sebelumnya terlebih dahulu.');
                return;
            }
            if (rows.length >= 2) {
                alert('Konversi kemasan maksimal dua tingkat.');
                return;
            }
            addRow(nextUnit);
            addButton.hidden = true;
        };
        return { purchaseUnit, baseUnit, calculate };
    }

    function updateMaterialConversion(row, resetUnit) {
        const purchase = row.querySelector('.purchase-unit').value;
        const base = row.querySelector('.material-base-unit');
        const packaged = materialPackageUnits.includes(purchase);
        if (resetUnit || !packaged) base.value = materialRules.measures[purchase]?.base || '';
        const content = row.querySelector('input[name="isi_per_kemasan[]"]');
        const packageField = row.querySelector('.material-package');
        const packageChain = packageField.querySelector('.package-conversion-chain');
        const addConversionButton = packageField.querySelector('.add-material-conversion');
        addConversionButton.hidden = true;
        packageField.hidden = !packaged;
        if (packaged) {
            if (!row.packageConversion || row.packageConversion.purchaseUnit !== purchase) {
                row.packageConversion = createMaterialPackageConversion(row, purchase, base.value);
            }
            const packageContent = row.packageConversion.calculate();
            content.value = packageContent || '0';
        } else {
            content.value = '0';
            packageChain.replaceChildren();
            row.querySelector('[name="konversi[]"]').value = '[]';
            row.packageConversion = null;
        }
        const quantity = Number(row.querySelector('input[name="stok[]"]').value);
        const factor = packaged ? Number(content.value) : materialUnitFactor(purchase, base.value);
        const result = quantity * factor;
        const price = Number(row.querySelector('[name="harga_supplier[]"]').value);
        row.querySelector('[name="total_pembelian[]"]').value = Number.isFinite(quantity * price) ? (quantity * price).toFixed(2) : '0';
        row.querySelector('.purchase-total-display').value = 'Rp' + (Number(row.querySelector('[name="total_pembelian[]"]').value)).toLocaleString('id-ID', {maximumFractionDigits: 2});
        const purchaseLabel = materialRules.measures[purchase]?.label.split(' (')[0] || (purchase ? purchase.charAt(0).toUpperCase() + purchase.slice(1) : 'Satuan Pembelian');
        row.querySelector('[name="harga_supplier[]"]').closest('.form-group').querySelector('label').textContent = 'Harga per ' + purchaseLabel + ' (Rp)';
        row.querySelector('[name="minimum_stok[]"]').previousElementSibling.textContent = 'Minimum Stok (' + (base.value === 'g' ? 'gram' : base.value || 'satuan dasar') + ')';
        const detail = packaged ? Array.from(packageChain.querySelectorAll('.package-conversion-row')).map(item => item.querySelector('input').value + ' ' + materialUnitLabel(item.querySelector('select').value)).join(' x ') : '';
        row.querySelector('.material-conversion').textContent = base.value && quantity > 0 && factor > 0 && Number.isFinite(result)
            ? 'Hasil Konversi Stok: ' + quantity.toLocaleString('id-ID') + ' ' + materialUnitLabel(purchase) + (detail ? ' x ' + detail : '') + ' = ' + result.toLocaleString('id-ID', {maximumFractionDigits: 2}) + ' ' + (base.value === 'g' ? 'gram' : base.value)
            : 'Hasil konversi: -';
    }
    rowsContainer.addEventListener('change', event => {
        const row = event.target.closest('.bahan-input-row');
        if (row) updateMaterialConversion(row, event.target.matches('.purchase-unit'));
    });
    rowsContainer.addEventListener('input', event => {
        const row = event.target.closest('.bahan-input-row');
        if (row) updateMaterialConversion(row, false);
    });

    function validateBahanNames() {
        const inputs = Array.from(rowsContainer.querySelectorAll('.nama-bahan-input'));
        const names = inputs.map(input => input.value.toLowerCase().trim());

        inputs.forEach((input, index) => {
            const notice = input.parentElement.querySelector('.duplicate-notice');
            const keyword = names[index];
            const duplicateInForm = keyword !== '' && names.indexOf(keyword) !== index;
            const duplicateExisting = bahanExisting.includes(keyword);

            if (duplicateExisting || duplicateInForm) {
                notice.textContent = duplicateExisting
                    ? 'Bahan sudah tersedia di daftar bahan.'
                    : 'Nama bahan sama dengan baris lain.';
                notice.classList.add('is-visible');
                input.setCustomValidity(notice.textContent);
            } else {
                notice.textContent = '';
                notice.classList.remove('is-visible');
                input.setCustomValidity('');
            }
        });
    }

    function updateBahanRows() {
        const rows = Array.from(rowsContainer.querySelectorAll('.bahan-input-row'));
        rows.forEach((row, index) => {
            row.querySelector('.bahan-row-title').textContent = `Bahan ${index + 1}`;
            row.querySelector('.remove-bahan-row').hidden = rows.length === 1;
        });
        validateBahanNames();
    }

    function addBahanRow() {
        rowsContainer.appendChild(rowTemplate.content.cloneNode(true));
        updateBahanRows();
    }

    document.getElementById('addBahanRowBtn').addEventListener('click', addBahanRow);
    supplierSelect.addEventListener('change', toggleNewSupplierFields);
    rowsContainer.addEventListener('input', validateBahanNames);
    document.getElementById('multiBahanForm').addEventListener('submit', function(event) {
        const incompletePackage = Array.from(rowsContainer.querySelectorAll('.bahan-input-row')).some(row => {
            const purchaseUnit = row.querySelector('.purchase-unit').value;
            const packageContent = Number(row.querySelector('input[name="isi_per_kemasan[]"]').value);
            return materialPackageUnits.includes(purchaseUnit) && packageContent <= 0;
        });
        if (incompletePackage) {
            event.preventDefault();
            alert('Lengkapi konversi kemasan setiap bahan sampai ke satuan dasar.');
        }
    });
    rowsContainer.addEventListener('click', function(event) {
        const removeButton = event.target.closest('.remove-bahan-row');
        if (!removeButton) return;
        removeButton.closest('.bahan-input-row').remove();
        updateBahanRows();
    });

    addBahanRow();
    toggleNewSupplierFields();
});
</script>

</body>
</html>
