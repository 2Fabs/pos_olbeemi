<?php
include '../config/database.php';
include '../includes/suppliers.php';
include '../includes/stock_units.php';

ensureSupplierTables($conn);
$is_owner = auth_has_role('owner');
$stages = $is_owner ? ['siap' => 'Perlu Dipesan'] : [];
$stages += ['dipesan' => 'Sudah Dipesan', 'selesai' => 'Selesai'];
$stage = $_GET['tahap'] ?? ($is_owner ? 'siap' : 'dipesan');
if (!is_string($stage) || !isset($stages[$stage])) $stage = $is_owner ? 'siap' : 'dipesan';
$stage_counts = array_fill_keys(array_keys($stages), 0);
$procurement_rows = [];
if ($is_owner) {
    $ready_query = mysqli_query($conn, "
        SELECT b.id, b.nama_bahan, b.stok, b.satuan, b.minimum_stok,
               0 AS permintaan_restock_id, NULL AS tanggal_pesan,
               NULL AS status_permintaan
        FROM bahan_baku b
        WHERE b.stok <= b.minimum_stok
          AND NOT EXISTS (
              SELECT 1 FROM pesanan_supplier p WHERE p.bahan_id = b.id
              AND p.status IN ('Sudah Dipesan', 'Menunggu Persetujuan')
          )
        ORDER BY (b.stok / NULLIF(b.minimum_stok, 0)) ASC, b.nama_bahan ASC
    ");
    while ($row = mysqli_fetch_assoc($ready_query)) {
        $procurement_rows[] = $row + [
            'tahap' => 'siap', 'pesanan_supplier_id' => null, 'supplier_pesanan' => null,
            'jumlah_pesanan' => 0, 'jumlah_input' => 0, 'satuan_input' => '',
            'isi_per_kemasan' => 0, 'total_pembelian' => 0, 'foto_nota' => null,
            'status_pesanan' => null,
        ];
    }
}

$order_query = mysqli_query($conn, "
    SELECT p.kode_pesanan, p.id AS pesanan_supplier_id, p.bahan_id AS id, b.nama_bahan, b.stok,
           p.satuan, p.jumlah AS jumlah_pesanan, p.jumlah_input, p.satuan_input,
           p.isi_per_kemasan, p.total_pembelian, p.foto_nota, p.status AS status_pesanan,
           p.tanggal_pesan, p.tanggal_diterima, s.nama_supplier AS supplier_pesanan
    FROM pesanan_supplier p
    LEFT JOIN bahan_baku b ON b.id = p.bahan_id
    LEFT JOIN supplier s ON s.id = p.supplier_id
    WHERE p.status IN ('Sudah Dipesan', 'Diterima')
    ORDER BY p.tanggal_pesan DESC, p.id DESC
");
while ($row = mysqli_fetch_assoc($order_query)) {
    $row['tahap'] = $row['status_pesanan'] === 'Diterima' ? 'selesai' : 'dipesan';
    $row['nama_bahan'] = $row['nama_bahan'] ?: 'Bahan tidak tersedia';
    $procurement_rows[] = $row;
}
foreach ($procurement_rows as $row) $stage_counts[$row['tahap']]++;
$procurement_rows = array_filter($procurement_rows, function ($row) use ($stage) {
    return $row['tahap'] === $stage;
});
$supplier_by_bahan = [];
$supplier_query = mysqli_query($conn, "
    SELECT
        supplier_bahan.bahan_id,
        supplier.id,
        supplier.nama_supplier
    FROM supplier_bahan
    JOIN supplier ON supplier.id = supplier_bahan.supplier_id
    ORDER BY supplier.nama_supplier ASC
");

while ($supplier = mysqli_fetch_assoc($supplier_query)) {
    $supplier_by_bahan[(int) $supplier['bahan_id']][] = [
        'id' => (int) $supplier['id'],
        'nama_supplier' => $supplier['nama_supplier'],
    ];
}

$all_suppliers = [];
$all_supplier_query = mysqli_query($conn, 'SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier');
while ($all_supplier_query && $supplier = mysqli_fetch_assoc($all_supplier_query)) $all_suppliers[] = $supplier;

$all_materials = [];
$materials_query = mysqli_query($conn, "
    SELECT b.id, b.nama_bahan, b.stok, b.satuan
    FROM bahan_baku b
    WHERE NOT EXISTS (
        SELECT 1
        FROM pesanan_supplier p
        WHERE p.bahan_id = b.id
          AND p.status IN ('Sudah Dipesan', 'Menunggu Persetujuan')
    )
    ORDER BY b.nama_bahan ASC
");
while ($materials_query && $material = mysqli_fetch_assoc($materials_query)) {
    $all_materials[] = [
        'id' => (int) $material['id'],
        'name' => $material['nama_bahan'],
        'stock' => (float) $material['stok'],
        'unit' => $material['satuan'],
    ];
}

$supplier_filters = [];
foreach ($procurement_rows as &$row) {
    $row_supplier_names = [];
    $ordered_supplier = trim((string) ($row['supplier_pesanan'] ?? ''));
    if ($ordered_supplier !== '') {
        $row_supplier_names[] = $ordered_supplier;
    } else {
        foreach ($supplier_by_bahan[(int) $row['id']] ?? [] as $supplier) {
            $row_supplier_names[] = $supplier['nama_supplier'];
        }
    }
    foreach ($row_supplier_names as $supplier_name) {
        $supplier_filters[$supplier_name] = $supplier_name;
    }
    $row['supplier_options'] = implode(', ', $row_supplier_names);
    $row['supplier_filter'] = implode('|', array_map('mb_strtolower', $row_supplier_names));
}
unset($row);
natcasesort($supplier_filters);
usort($procurement_rows, function ($left, $right) use ($stage) {
    if ($stage !== 'siap') {
        $date_column = $stage === 'selesai' ? 'tanggal_diterima' : 'tanggal_pesan';
        $left_date = strtotime((string) ($left[$date_column] ?? '')) ?: 0;
        $right_date = strtotime((string) ($right[$date_column] ?? '')) ?: 0;
        if ($left_date !== $right_date) return $right_date <=> $left_date;
        return (int) $right['pesanan_supplier_id'] <=> (int) $left['pesanan_supplier_id'];
    }

    $left_supplier = $left['supplier_pesanan'] ?: ($left['supplier_options'] ?: 'ZZZ');
    $right_supplier = $right['supplier_pesanan'] ?: ($right['supplier_options'] ?: 'ZZZ');
    $supplier_comparison = strcasecmp($left_supplier, $right_supplier);
    if ($supplier_comparison !== 0) return $supplier_comparison;

    $stock_comparison = (float) $left['stok'] <=> (float) $right['stok'];
    return $stock_comparison !== 0
        ? $stock_comparison
        : strcasecmp($left['nama_bahan'], $right['nama_bahan']);
});

if ($stage !== 'siap') {
    $grouped_orders = [];
    foreach ($procurement_rows as $row) {
        // One checkout retains its code across ordering and receiving.
        $group_key = implode('|', [$row['kode_pesanan'], $row['status_pesanan']]);
        if (!isset($grouped_orders[$group_key])) {
            $row['order_items'] = [];
            $row['order_ids'] = [];
            $row['total_pembelian_group'] = 0;
            $grouped_orders[$group_key] = $row;
        }
        $grouped_orders[$group_key]['order_items'][] = $row;
        $grouped_orders[$group_key]['order_ids'][] = (int) $row['pesanan_supplier_id'];
        $grouped_orders[$group_key]['total_pembelian_group'] += (float) $row['total_pembelian'];
        if (empty($grouped_orders[$group_key]['foto_nota']) && !empty($row['foto_nota'])) {
            $grouped_orders[$group_key]['foto_nota'] = $row['foto_nota'];
            $grouped_orders[$group_key]['pesanan_supplier_id'] = $row['pesanan_supplier_id'];
        }
    }
    $procurement_rows = array_values($grouped_orders);
}

function format_stok_supplier($number) {
    return rtrim(rtrim(number_format((float) $number, 2, ',', '.'), '0'), ',');
}

$success = trim($_GET['success'] ?? '');
$error = trim($_GET['error'] ?? '');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_owner ? 'Pesan Bahan Baku' : 'Penerimaan Bahan' ?></title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/bahan.css?v=<?= filemtime(__DIR__ . '/../css/bahan.css') ?>">
<script src="../includes/purchase_table.js?v=<?= filemtime(__DIR__ . '/../includes/purchase_table.js') ?>"></script>
</head>
<body>

<?php
$current_module = 'pesan_supplier';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header">
        <div>
            <h1><?= $is_owner ? 'Pesan Bahan Baku' : 'Penerimaan Bahan' ?></h1>
            <p><?= $is_owner ? 'Pesan bahan yang stoknya menipis dan pantau statusnya' : 'Konfirmasi barang yang sudah diterima' ?></p>
        </div>
        <?php if ($is_owner): ?><button type="button" class="btn-primary" id="openNewOrder">Pesan Bahan</button><a href="suppliers.php" class="btn-stock">Kelola Supplier</a><?php endif; ?>
    </div>

    <?php if ($success !== ''): ?><div class="request-notice request-notice-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="request-notice request-notice-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <nav class="workflow-tabs" aria-label="Status pesanan bahan">
        <?php foreach ($stages as $key => $label): ?>
            <a href="?tahap=<?= $key ?>"<?= $stage === $key ? ' aria-current="page"' : '' ?>><?= $label ?><span class="workflow-count"><?= $stage_counts[$key] ?></span></a>
        <?php endforeach; ?>
    </nav>
    <p class="workflow-note"><?= $is_owner ? 'Stok menipis → Pesan bahan → Barang diterima → Selesai.' : 'Periksa nama dan jumlah barang, lalu konfirmasi agar stok langsung diperbarui.' ?></p>
    <div class="table-card">
        <h2><?= $stages[$stage] ?></h2>
        <div class="procurement-filters">
            <div class="search-box bahan-search-box"><input id="procurementSearch" type="search" placeholder="Cari nama bahan..." aria-label="Cari nama bahan"></div>
            <select id="procurementSupplierFilter" aria-label="Filter berdasarkan supplier">
                <option value="">Semua Supplier</option>
                <?php foreach ($supplier_filters as $supplier_name): ?>
                    <option value="<?= htmlspecialchars(mb_strtolower($supplier_name), ENT_QUOTES) ?>"><?= htmlspecialchars($supplier_name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($is_owner && $stage === 'siap'): ?>
        <div class="bulk-order-toolbar">
            <span id="bulkSelectionText">Belum ada bahan dipilih</span>
            <button type="button" class="btn-primary" id="openBulkOrder" disabled>Pesan Terpilih</button>
        </div>
        <?php endif; ?>
        <div class="workflow-table-wrap"><table class="procurement-table">
            <thead>
                <tr>
                    <?php if ($is_owner && $stage === 'siap'): ?><th><input type="checkbox" id="selectAllMaterials" aria-label="Pilih semua bahan"></th><?php endif; ?>
                    <th>No</th>
                    <th><?= $stage !== 'siap' ? 'Daftar Pesanan' : 'Nama Bahan' ?></th>
                    <th>Supplier</th>
                    <th><?= $stage === 'siap' ? 'Stok Saat Ini' : 'Jumlah Bahan' ?></th>
                    <th><?= $stage === 'siap' ? 'Pemesanan' : 'Tanggal' ?></th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="procurementRows">
                <?php if ($procurement_rows): ?>
                    <?php $no = 1; ?>
                    <?php foreach ($procurement_rows as $bahan):
                        $order_items = $bahan['order_items'] ?? [$bahan];
                        $order_detail = [
                            'Kode Pemesanan' => $bahan['kode_pesanan'] ?? '-',
                            'Supplier' => $bahan['supplier_pesanan'] ?: '-',
                            'Tahap' => $stages[$bahan['tahap']],
                            'Tanggal Pesan' => !empty($bahan['tanggal_pesan']) ? date('d/m/Y H:i', strtotime($bahan['tanggal_pesan'])) : '-',
                            'Tanggal Selesai' => !empty($bahan['tanggal_diterima']) ? date('d/m/Y H:i', strtotime($bahan['tanggal_diterima'])) : '-',
                        ];
                        $order_detail_items = array_map(function ($item) use ($is_owner) {
                            $detail = [
                                'nama' => $item['nama_bahan'],
                                'dibeli' => (float) $item['jumlah_input'] > 0
                                    ? format_stok_supplier($item['jumlah_input']) . ' ' . stockInputUnitLabel($item['satuan_input'])
                                    : format_stok_supplier($item['jumlah_pesanan']) . ' ' . stockInputUnitLabel($item['satuan']),
                                'konversi' => stockPurchaseConversionLabel($item['jumlah_input'], $item['satuan_input'], $item['jumlah_pesanan'], $item['satuan'], $item['isi_per_kemasan']),
                                'hasil' => format_stok_supplier($item['jumlah_pesanan']) . ' ' . stockInputUnitLabel($item['satuan']),
                            ];
                            if ($is_owner) {
                                $quantity = (float) ($item['jumlah_input'] ?: $item['jumlah_pesanan']);
                                $detail['harga_satuan'] = $quantity > 0 ? 'Rp' . rtrim(rtrim(number_format((float) $item['total_pembelian'] / $quantity, 2, ',', '.'), '0'), ',') . ' / ' . stockInputUnitLabel($item['satuan_input'] ?: $item['satuan']) : '-';
                                $detail['harga'] = 'Rp' . rtrim(rtrim(number_format((float) $item['total_pembelian'], 2, ',', '.'), '0'), ',');
                            }
                            return $detail;
                        }, $order_items);
                        if ($is_owner) $order_detail['Total Pembelian'] = 'Rp' . number_format((float) ($bahan['total_pembelian_group'] ?? $bahan['total_pembelian']), 0, ',', '.');
                    ?>
                        <tr data-procurement-row data-suppliers="<?= htmlspecialchars($bahan['supplier_filter'] ?? '', ENT_QUOTES) ?>">
                            <?php if ($is_owner && $stage === 'siap'): ?>
                            <td><input type="checkbox" class="bulk-material-check"
                                value="<?= (int) $bahan['id'] ?>"
                                data-name="<?= htmlspecialchars($bahan['nama_bahan'], ENT_QUOTES) ?>"
                                data-unit="<?= htmlspecialchars($bahan['satuan'], ENT_QUOTES) ?>"
                                aria-label="Pilih <?= htmlspecialchars($bahan['nama_bahan'], ENT_QUOTES) ?>"></td>
                            <?php endif; ?>
                            <td data-row-number><?= $no++ ?></td>
                            <td class="procurement-material-cell">
                                <?php if ($stage !== 'siap'): ?>
                                    <strong><?= htmlspecialchars($bahan['kode_pesanan'] ?? '-') ?></strong><small><?= count($order_items) ?> bahan baku</small>
                                    <?php if (count($order_items) === 1): ?><small><?= htmlspecialchars($bahan['nama_bahan']) ?></small><?php endif; ?>
                                <?php else: ?>
                                    <strong><?= htmlspecialchars($bahan['nama_bahan']) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($bahan['supplier_pesanan'] ?: ($bahan['supplier_options'] ?: 'Belum ada supplier')) ?></td>
                            <td>
                                <?php if (!empty($bahan['pesanan_supplier_id'])): ?>
                                    <strong class="procurement-final-amount"><?= count($order_items) ?> item</strong>
                                <?php else: ?>
                                    <strong class="procurement-final-amount"><?= format_stok_supplier($bahan['stok']) ?> <?= htmlspecialchars(stockInputUnitLabel($bahan['satuan'])) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $display_date = $stage === 'selesai' ? $bahan['tanggal_diterima'] : $bahan['tanggal_pesan']; ?>
                                <?= $display_date ? date('d/m/Y H:i', strtotime($display_date)) : ($stage === 'selesai' ? 'Belum diterima' : 'Belum dipesan') ?>
                            </td>
                            <td>
                                <?php if ($stage === 'siap'): ?>
                                    <span class="badge badge-danger">Perlu Dipesan</span>
                                <?php elseif ($stage === 'selesai'): ?>
                                    <span class="badge badge-success">Selesai</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Menunggu Barang</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-group">
                                    <?php if (!empty($bahan['pesanan_supplier_id'])): ?>
                                    <button type="button" class="btn-stock procurement-detail" data-detail="<?= htmlspecialchars(json_encode($order_detail, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>" data-items="<?= htmlspecialchars(json_encode($order_detail_items, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>">Detail</button>
                                    <?php endif; ?>
                                    <?php if ($is_owner && $stage === 'siap'): ?>
                                        <button
                                            type="button"
                                            class="btn-order-supplier"
                                            data-id="<?= (int) $bahan['id'] ?>"
                                            data-name="<?= htmlspecialchars($bahan['nama_bahan'], ENT_QUOTES) ?>"
                                            data-stock="<?= format_stok_supplier($bahan['stok']) ?>"
                                            data-request-id="<?= (int) $bahan['permintaan_restock_id'] ?>"
                                            data-unit="<?= htmlspecialchars($bahan['satuan'], ENT_QUOTES) ?>">
                                            Pesan Bahan
                                        </button>
                                    <?php elseif (!$is_owner && $bahan['status_pesanan'] === 'Sudah Dipesan'): ?>
                                        <form method="POST" action="permintaan_stok.php" onsubmit="return confirm('Konfirmasi barang ini sudah diterima?')">
                                            <?= auth_csrf_input() ?>
                                            <input type="hidden" name="action" value="receive_order">
                                            <?php foreach ($bahan['order_ids'] ?? [(int) $bahan['pesanan_supplier_id']] as $order_id): ?>
                                                <input type="hidden" name="order_ids[]" value="<?= (int) $order_id ?>">
                                            <?php endforeach; ?>
                                            <button type="submit" class="btn-primary">Terima Semua</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr id="procurementEmpty" class="workflow-empty"<?= $procurement_rows ? ' hidden' : '' ?>>
                    <td colspan="<?= $is_owner && $stage === 'siap' ? 8 : 7 ?>">Belum ada data pada tahap ini.<?= $is_owner && $stage === 'siap' ? ' Semua stok masih aman atau sudah dipesan.' : '' ?></td>
                </tr>
            </tbody>
        </table></div>
    </div>
</div>

<div id="procurementDetailModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="procurementDetailTitle"><div class="modal-content workflow-modal">
    <div class="modal-header"><h2 id="procurementDetailTitle">Detail Pesanan Bahan Baku</h2><button type="button" class="close-btn" id="closeProcurementDetail" aria-label="Tutup detail">&times;</button></div>
    <div class="request-detail-list" id="procurementDetail"></div>
    <div class="procurement-detail-items" id="procurementDetailItems" hidden></div>
</div></div>

<?php if ($is_owner): ?>
<div id="supplierOrderModal" class="modal">
    <div class="modal-content workflow-modal">
        <div class="modal-header">
            <h2>Pesan Bahan</h2>
            <span class="close-btn" onclick="closeSupplierOrderModal()">&times;</span>
        </div>

        <form id="supplierOrderForm" action="simpan_pesanan_supplier.php" method="POST">
            <?= auth_csrf_input() ?>
            <input type="hidden" name="bahan_id" id="supplier_order_bahan_id">
            <input type="hidden" name="request_id" id="supplier_order_request_id">
            <div class="form-group">
                <label>Nama Bahan</label>
                <input type="text" id="supplier_order_bahan" readonly>
            </div>

            <div class="form-group">
                <label>Stok Saat Ini</label>
                <input type="text" id="supplier_order_stock" readonly>
            </div>

            <div class="form-group">
                <label>Supplier</label>
                <select name="supplier_id" id="supplier_order_select" required>
                    <option value="">Pilih Supplier</option>
                </select>
                <small class="supplier-form-note" id="supplier_order_note"></small>
                <small class="supplier-form-note"><a href="suppliers.php">Tambah supplier atau hubungkan bahan ke supplier</a></small>
            </div>

            <div class="form-group">
                <label>Jumlah yang Dibeli</label>
                <input type="number" name="jumlah" id="supplier_order_amount" min="0.01" step="0.01" required>
            </div>

            <div class="request-stock-grid">
                <div class="form-group">
                    <label>Satuan Pembelian</label>
                    <select name="satuan_input" id="supplier_order_input_unit" required>
                        <option value="">Pilih satuan</option>
                        <option value="g" data-category="weight">Gram (g)</option>
                        <option value="kg" data-category="weight">Kilogram (kg)</option>
                        <option value="ml" data-category="volume">Mililiter (ml)</option>
                        <option value="liter" data-category="volume">Liter (L)</option>
                        <option value="pcs" data-category="count">Pcs</option>
                        <option value="dus" data-package="1" data-category="all">Dus</option>
                        <option value="pack" data-package="1" data-category="all">Pack</option>
                        <option value="botol" data-package="1" data-category="all">Botol</option>
                        <option value="galon" data-package="1" data-category="all">Galon</option>
                        <option value="sachet" data-package="1" data-category="all">Sachet</option>
                        <option value="kaleng" data-package="1" data-category="all">Kaleng</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Satuan Stok</label>
                    <input type="text" id="supplier_order_base_unit" readonly>
                </div>
            </div>

            <div class="request-conversion-preview" id="supplierOrderConversion" aria-live="polite" hidden></div>
            <div class="form-group" id="supplierOrderPackageField" hidden>
                <label>Konversi Kemasan</label>
                <div class="package-conversion-chain" id="supplierOrderConversionChain"></div>
                <button type="button" class="btn-add-conversion" id="addSupplierConversion">+ Tambah Konversi</button>
                <small class="request-form-help">Contoh: 1 dus = 10 pack, lalu 1 pack = 50 pcs.</small>
                <input type="hidden" name="isi_per_kemasan" id="supplier_order_package_content" value="0">
                <input type="hidden" name="konversi" value="[]">
            </div>

            <div class="form-group">
                <label>Harga per Satuan Pembelian (Rp)</label>
                <input type="number" name="harga_satuan" id="supplier_order_price" min="0" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Total Pembelian</label>
                <input type="hidden" name="total_pembelian" value="0">
                <input type="text" id="supplier_order_total_display" value="Rp0" readonly aria-label="Total Pembelian">
            </div>

            <button type="submit" class="btn-primary">Pesan Bahan</button>
        </form>
    </div>
</div>

<div id="bulkOrderModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="bulkOrderTitle"><div class="modal-content workflow-modal bulk-order-modal">
    <div class="modal-header"><h2 id="bulkOrderTitle">Pesan Beberapa Bahan</h2><button type="button" class="close-btn" id="closeBulkOrder" aria-label="Tutup">&times;</button></div>
    <form id="bulkOrderForm" action="simpan_pesanan_supplier.php" method="POST">
        <?= auth_csrf_input() ?>
        <input type="hidden" name="action" value="batch_order">
        <div class="form-group" id="bulkSupplierGroup"><label for="bulkSupplier">Supplier</label><select name="supplier_id" id="bulkSupplier" required><option value="">Pilih Supplier</option></select><small class="request-form-help">Hanya supplier yang menyediakan semua bahan terpilih yang ditampilkan.</small></div>
        <p class="request-form-help bulk-supplier-auto-note" id="bulkSupplierAutoNote" hidden></p>
        <p class="bulk-order-guide">Isi jumlah dan harga per satuan pembelian. Stok masuk serta total pembelian dihitung otomatis.</p>
        <div class="bulk-order-items" id="bulkOrderItems"></div>
        <div class="bulk-order-toolbar">
            <button type="button" class="btn-stock" id="addBulkExistingMaterial">+ Tambah Stok yang Sudah Ada</button>
            <button type="button" class="btn-stock" id="addBulkNewMaterial">+ Tambah Bahan Baru</button>
        </div>
        <div class="bulk-existing-picker" id="bulkExistingPicker" hidden>
            <p class="bulk-existing-title">Pilih bahan lain dari supplier ini untuk ikut dipesan.</p>
            <div class="bulk-existing-table-wrap">
                <table class="bulk-existing-table">
                    <thead><tr><th>Bahan</th><th>Stok Saat Ini</th><th>Tambahkan</th></tr></thead>
                    <tbody id="bulkExistingList"></tbody>
                </table>
            </div>
            <button type="button" class="btn-primary" id="confirmBulkExistingMaterial">Tambahkan yang Dipilih</button>
        </div>
        <div class="bulk-conversion-summary" id="bulkConversionSummary" hidden></div>
        <button type="submit" class="btn-primary">Simpan Semua Pesanan</button>
    </form>
</div></div>

<script>
const suppliersByBahan = <?= json_encode($supplier_by_bahan, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const allMaterials = <?= json_encode($all_materials, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const bulkChecks = Array.from(document.querySelectorAll('.bulk-material-check'));
const bulkButton = document.getElementById('openBulkOrder');
const bulkModal = document.getElementById('bulkOrderModal');
const supplierConversionBuilder = createPackageConversion(
    document.getElementById('supplierOrderConversionChain'),
    document.getElementById('addSupplierConversion'),
    '',
    () => updateSupplierOrderConversion()
);

function updateBulkConversionSummary() {
    const summary = document.getElementById('bulkConversionSummary');
    const completed = Array.from(document.querySelectorAll('.bulk-order-item')).filter(item => item.dataset.convertedAmount);
    summary.hidden = completed.length === 0;
    if (!completed.length) {
        summary.replaceChildren();
        return;
    }
    const title = document.createElement('strong');
    title.textContent = 'Ringkasan Hasil Stok Masuk';
    const table = document.createElement('table');
    table.innerHTML = '<thead><tr><th>Bahan Baku</th><th>Total Dibeli</th><th>Konversi</th><th>Total Satuan</th><th>Harga per Satuan Pembelian</th><th>Total Harga Pembelian</th></tr></thead>';
    const body = document.createElement('tbody');
    const number = value => Number(value).toLocaleString('id-ID', {maximumFractionDigits: 2});
    completed.forEach(item => {
        const row = document.createElement('tr');
        const unit = item.querySelector('[name="satuan_input[]"]').value;
        const values = [item.querySelector('.bulk-item-name').textContent.replace(/^\d+\.\s*/, ''),
            number(item.querySelector('[name="jumlah[]"]').value) + ' ' + unitLabel(unit),
            '1 ' + unitLabel(unit) + ' = ' + Number(Number(item.dataset.convertedAmount) / Number(item.querySelector('[name="jumlah[]"]').value)).toLocaleString('id-ID', {maximumFractionDigits: 6}) + ' ' + unitLabel(item.dataset.baseUnit),
            number(item.dataset.convertedAmount) + ' ' + unitLabel(item.dataset.baseUnit),
            'Rp' + number(item.querySelector('[name="harga_satuan[]"]').value) + ' / ' + unitLabel(unit),
            'Rp' + number(item.querySelector('[name="total_pembelian[]"]').value)];
        values.forEach(value => { const cell = document.createElement('td'); cell.textContent = value; row.append(cell); });
        body.append(row);
    });
    table.append(body);
    summary.replaceChildren(title, wrapPurchaseTable(table));
}

function updateBulkSelection() {
    const selected = bulkChecks.filter(check => check.checked);
    document.getElementById('bulkSelectionText').textContent = selected.length
        ? `${selected.length} bahan dipilih`
        : 'Belum ada bahan dipilih';
    bulkButton.disabled = selected.length < 1;
}

function purchaseUnits(baseUnit) {
    return conversionTargets(baseUnit, '').concat(packageUnits.map(unit => [unit, unit.charAt(0).toUpperCase() + unit.slice(1)]));
}

const purchaseRules = <?= json_encode(purchaseUnitRules()) ?>;
const packageUnits = purchaseRules.packages;

function unitLabel(unit) {
    const labels = {
        g: 'g', gram: 'g', kg: 'kg', ml: 'ml', liter: 'L', l: 'L', pcs: 'pcs',
        dus: 'dus', pack: 'pack', botol: 'botol', galon: 'galon', sachet: 'sachet'
    };
    return labels[unit] || unit;
}

function normalizeUnit(unit) {
    return unit === 'gram' ? 'g' : (unit === 'l' ? 'liter' : unit);
}

function conversionTargets(baseUnit, sourceUnit) {
    const normalizedBase = normalizeUnit(baseUnit);
    const canonicalBase = purchaseRules.measures[normalizedBase]?.base || normalizedBase;
    const finalUnits = Object.entries(purchaseRules.measures).filter(([, rule]) => rule.base === canonicalBase).map(([unit, rule]) => [unit, rule.label]);
    const smallerPackages = (purchaseRules.children[sourceUnit] || []).map(unit => [unit, unit.charAt(0).toUpperCase() + unit.slice(1)]);
    return [['', 'Pilih satuan']].concat(finalUnits, smallerPackages);
}

function unitFactor(inputUnit, baseUnit) {
    const input = normalizeUnit(inputUnit);
    const base = normalizeUnit(baseUnit);
    const inputRule = purchaseRules.measures[input];
    const baseRule = purchaseRules.measures[base];
    return inputRule && baseRule && inputRule.base === baseRule.base ? inputRule.factor / baseRule.factor : null;
}

function createPackageConversion(chain, addButton, baseUnit, onChange) {
    let startUnit = '';

    function rows() {
        return Array.from(chain.querySelectorAll('.package-conversion-row'));
    }

    function calculate() {
        let multiplier = 1;
        const chainRows = rows();
        const payload = chain.parentElement.querySelector('input[name^="konversi"]');
        if (payload) payload.value = JSON.stringify(chainRows.map(item => ({quantity: item.querySelector('input').value, unit: item.querySelector('select').value})));
        for (let index = 0; index < chainRows.length; index++) {
            const row = chainRows[index];
            const quantity = parseFloat(row.querySelector('input').value || '0');
            const target = row.querySelector('select').value;
            if (!Number.isFinite(quantity) || quantity <= 0 || !target) return null;
            multiplier *= quantity;
            const factor = unitFactor(target, baseUnit);
            if (factor !== null) {
                const finalAmount = multiplier * factor;
                return index === chainRows.length - 1 && Number.isFinite(finalAmount)
                    ? { amount: finalAmount, text: chainRows.map(item => Number(item.querySelector('input').value).toLocaleString('id-ID') + ' ' + unitLabel(item.querySelector('select').value)).join(' x ') }
                    : null;
            }
            if (!packageUnits.includes(target) || index === chainRows.length - 1) return null;
        }
        return null;
    }

    function addRow(sourceUnit) {
        const row = document.createElement('div');
        row.className = 'package-conversion-row';
        const source = document.createElement('span');
        source.textContent = `1 ${unitLabel(sourceUnit)} =`;
        const quantity = document.createElement('input');
        quantity.type = 'number';
        quantity.min = '0.01';
        quantity.step = '0.01';
        quantity.placeholder = 'Jumlah';
        quantity.required = true;
        const target = document.createElement('select');
        target.required = true;
        const targets = conversionTargets(baseUnit, rows().length ? '' : sourceUnit);
        targets.forEach(([value, label]) => target.add(new Option(label, value)));
        if (targets.filter(([value]) => value).length === 1) target.value = normalizeUnit(baseUnit);
        quantity.addEventListener('input', onChange);
        target.addEventListener('change', function () {
            const currentRows = rows();
            const currentIndex = currentRows.indexOf(row);
            currentRows.slice(currentIndex + 1).forEach(nextRow => nextRow.remove());
            addButton.hidden = true;
            if (packageUnits.includes(this.value) && currentIndex === 0) addRow(this.value);
            onChange();
        });
        row.append(source, quantity, target);
        chain.append(row);
    }

    addButton.addEventListener('click', function () {
        const currentRows = rows();
        const lastRow = currentRows[currentRows.length - 1];
        const nextUnit = lastRow ? lastRow.querySelector('select').value : '';
        if (!packageUnits.includes(nextUnit)) {
            alert('Pilih satuan kemasan pada konversi sebelumnya terlebih dahulu.');
            return;
        }
        if (currentRows.length >= 2) {
            alert('Konversi kemasan maksimal dua tingkat.');
            return;
        }
        addRow(nextUnit);
        addButton.hidden = true;
        onChange();
    });

    return {
        reset(unit, nextBaseUnit = '') {
            if (nextBaseUnit) baseUnit = nextBaseUnit;
            startUnit = unit;
            chain.replaceChildren();
            addButton.hidden = true;
            if (unit) addRow(unit);
        },
        calculate,
    };
}

function commonSuppliers(selected) {
    if (!selected.length) return [];
    const first = suppliersByBahan[selected[0].value] || [];
    return first.filter(supplier => selected.every(check =>
        (suppliersByBahan[check.value] || []).some(item => item.id === supplier.id)
    ));
}

function applyFilteredSupplier(select) {
    const filter = document.getElementById('procurementSupplierFilter');
    const supplierName = filter ? filter.value : '';
    if (!supplierName) return;
    const option = Array.from(select.options).find(item => item.textContent.toLowerCase() === supplierName);
    if (option) select.value = option.value;
}

function configureBulkSupplier(suppliers) {
    const supplierSelect = document.getElementById('bulkSupplier');
    const supplierGroup = document.getElementById('bulkSupplierGroup');
    const autoNote = document.getElementById('bulkSupplierAutoNote');

    supplierSelect.replaceChildren(new Option('Pilih Supplier', ''));
    suppliers.forEach(supplier => supplierSelect.add(new Option(supplier.nama_supplier, supplier.id)));
    applyFilteredSupplier(supplierSelect);

    const hasOneSupplier = suppliers.length === 1;
    if (hasOneSupplier) supplierSelect.value = String(suppliers[0].id);
    supplierGroup.hidden = hasOneSupplier;
    autoNote.hidden = !hasOneSupplier;
    autoNote.textContent = hasOneSupplier ? `Supplier otomatis: ${suppliers[0].nama_supplier}` : '';
}

bulkChecks.forEach(check => check.addEventListener('change', updateBulkSelection));
const selectAllMaterials = document.getElementById('selectAllMaterials');
if (selectAllMaterials) {
    selectAllMaterials.addEventListener('change', function () {
        bulkChecks.forEach(check => {
            if (!check.closest('tr').hidden) check.checked = this.checked;
        });
        updateBulkSelection();
    });
}

function createBulkOrderItem(options) {
    const items = document.getElementById('bulkOrderItems');
    const item = document.createElement('section');
    item.className = 'bulk-order-item';
    item.dataset.isNew = options.isNew ? '1' : '0';

    const title = document.createElement('h3');
    const titleText = document.createElement('span');
    titleText.className = 'bulk-item-name';
    titleText.textContent = options.name || 'Bahan Baru';
    title.appendChild(titleText);

    const removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.className = 'btn-delete';
    removeButton.textContent = 'Hapus';
    removeButton.hidden = false;
    removeButton.addEventListener('click', function () {
        item.remove();
        updateBulkItemLabels();
        updateBulkConversionSummary();
    });

    const hiddenId = document.createElement('input');
    hiddenId.type = 'hidden'; hiddenId.name = 'bahan_id[]'; hiddenId.value = options.materialId > 0 ? String(options.materialId) : '0';
    const hiddenFlag = document.createElement('input');
    hiddenFlag.type = 'hidden'; hiddenFlag.name = 'is_bahan_baru[]'; hiddenFlag.value = options.isNew ? '1' : '0';

    const grid = document.createElement('div');
    grid.className = 'bulk-order-grid';

    let newNameInput;
    let newUnitSelect;
    let newMinimumInput;
    if (options.isNew) {
        const nameGroup = document.createElement('label');
        nameGroup.className = 'form-group';
        const nameLabel = document.createElement('span'); nameLabel.textContent = 'Nama Bahan Baru';
        newNameInput = document.createElement('input');
        newNameInput.name = 'nama_bahan_baru[]'; newNameInput.required = true;
        newNameInput.addEventListener('input', function () {
            titleText.textContent = this.value.trim() || 'Bahan Baru';
            updateBulkConversionSummary();
        });
        nameGroup.append(nameLabel, newNameInput);
        grid.append(nameGroup);
    } else {
        const blankName = document.createElement('input');
        blankName.type = 'hidden'; blankName.name = 'nama_bahan_baru[]'; blankName.value = '';
        const blankUnit = document.createElement('input');
        blankUnit.type = 'hidden'; blankUnit.name = 'satuan_baru[]'; blankUnit.value = '';
        const blankMinimum = document.createElement('input');
        blankMinimum.type = 'hidden'; blankMinimum.name = 'minimum_stok_baru[]'; blankMinimum.value = '0';
        item.append(blankName, blankUnit, blankMinimum);
    }

    const fields = [
        ['Jumlah yang Dibeli', 'number', 'jumlah[]', '0.01'],
        ['Satuan Pembelian', 'select', 'satuan_input[]', ''],
        ['Harga per Satuan Pembelian (Rp)', 'number', 'harga_satuan[]', '0'],
        ['Total Pembelian (Rp)', 'number', 'total_pembelian[]', '0'],
    ];
    let amountInput;
    let unitInput;
    fields.forEach(([labelText, type, name, min]) => {
        const group = document.createElement('label');
        group.className = 'form-group';
        const label = document.createElement('span');
        label.textContent = labelText;
        let control;
        if (type === 'select') {
            control = document.createElement('select');
            if (!options.isNew) purchaseUnits(options.baseUnit).forEach(([value, text]) => control.add(new Option(text, value)));
            else control.add(new Option('Pilih satuan dasar dulu', ''));
        } else {
            control = document.createElement('input');
            control.type = type; control.min = min; control.step = '0.01';
        }
        control.name = name;
        control.required = true;
        if (name === 'total_pembelian[]') { control.readOnly = true; control.value = '0'; }
        if (name === 'jumlah[]') amountInput = control;
        if (name === 'satuan_input[]') unitInput = control;
        group.append(label, control);
        if (name === 'total_pembelian[]') {
            control.type = 'hidden';
            const display = document.createElement('input'); display.type = 'text'; display.readOnly = true; display.className = 'purchase-total-display'; display.value = 'Rp0';
            group.append(display);
        }
        grid.append(group);
    });

    const packageGroup = document.createElement('div');
    packageGroup.className = 'bulk-package-field';
    packageGroup.hidden = true;
    const packageLabel = document.createElement('strong');
    packageLabel.textContent = 'Konversi Kemasan';
    const packageChain = document.createElement('div');
    packageChain.className = 'package-conversion-chain';
    const addConversion = document.createElement('button');
    addConversion.type = 'button';
    addConversion.className = 'btn-add-conversion';
    addConversion.textContent = '+ Tambah Konversi';
    const packageInput = document.createElement('input');
    packageInput.type = 'hidden';
    packageInput.name = 'isi_per_kemasan[]';
    packageInput.value = '0';
    const conversionPayload = document.createElement('input');
    conversionPayload.type = 'hidden'; conversionPayload.name = 'konversi[]'; conversionPayload.value = '[]';
    packageGroup.append(packageLabel, packageChain, addConversion, packageInput, conversionPayload);

    const conversion = document.createElement('div');
    conversion.className = 'request-conversion-preview bulk-order-conversion';
    conversion.hidden = true;

    const baseField = document.createElement('div');
    baseField.className = 'form-group';
    const baseLabel = document.createElement('label'); baseLabel.textContent = 'Satuan Dasar';
    let baseInput;
    if (options.isNew) {
        newUnitSelect = document.createElement('select');
        [['', 'Pilih satuan'], ['g', 'Gram (g)'], ['ml', 'Mililiter (ml)'], ['pcs', 'Pcs']].forEach(([value, text]) => newUnitSelect.add(new Option(text, value)));
        baseField.append(baseLabel, newUnitSelect);
        const minGroup = document.createElement('label');
        minGroup.className = 'form-group';
        const minLabel = document.createElement('span'); minLabel.textContent = 'Minimum Stok';
        newMinimumInput = document.createElement('input');
        newMinimumInput.type = 'number'; newMinimumInput.min = '0'; newMinimumInput.step = '0.01'; newMinimumInput.value = '0';
        newMinimumInput.name = 'minimum_stok_baru[]';
        minGroup.append(minLabel, newMinimumInput);
        baseField.append(minGroup);
        const hiddenUnit = document.createElement('input');
        hiddenUnit.type = 'hidden'; hiddenUnit.name = 'satuan_baru[]';
        item.append(hiddenUnit);
        newUnitSelect.addEventListener('change', function () {
            hiddenUnit.value = this.value;
            unitInput.replaceChildren();
            if (this.value) purchaseUnits(this.value).forEach(([value, text]) => unitInput.add(new Option(text, value)));
            else unitInput.add(new Option('Pilih satuan dasar dulu', ''));
            conversionBuilder.reset('', this.value);
            updateBulkConversion();
        });
    } else {
        baseInput = document.createElement('input'); baseInput.readOnly = true; baseInput.value = options.baseUnit === 'g' ? 'gram' : options.baseUnit;
        baseField.append(baseLabel, baseInput);
    }

    let conversionBuilder;
    function currentBaseUnit() {
        return options.isNew ? (newUnitSelect.value || '') : options.baseUnit;
    }
    function updateBulkConversion() {
        const baseUnit = currentBaseUnit();
        const price = item.querySelector('[name="harga_satuan[]"]');
        item.querySelector('[name="total_pembelian[]"]').value = (Number(amountInput.value) * Number(price.value)).toFixed(2);
        item.querySelector('.purchase-total-display').value = 'Rp' + Number(item.querySelector('[name="total_pembelian[]"]').value).toLocaleString('id-ID', {maximumFractionDigits: 2});
        price.previousElementSibling.textContent = 'Harga per ' + (unitInput.value ? unitInput.selectedOptions[0].text.split(' (')[0] : 'Satuan Pembelian') + ' (Rp)';
        const selectedUnit = unitInput.options[unitInput.selectedIndex];
        const isPackage = selectedUnit && packageUnits.includes(selectedUnit.value);
        packageGroup.hidden = !isPackage;
        packageChain.querySelectorAll('input, select').forEach(control => control.disabled = !isPackage);
        if (!isPackage) packageInput.value = '0';

        const amount = parseFloat(amountInput.value || '0');
        const packageConversion = isPackage && conversionBuilder ? conversionBuilder.calculate() : null;
        if (isPackage) packageInput.value = packageConversion ? packageConversion.amount : '0';
        if (!baseUnit || !selectedUnit || !selectedUnit.value || amount <= 0 || (isPackage && !packageConversion)) {
            delete item.dataset.convertedAmount;
            conversion.hidden = true;
            updateBulkConversionSummary();
            return;
        }

        const factor = isPackage ? packageConversion.amount : unitFactor(selectedUnit.value, baseUnit);
        const inputLabel = selectedUnit.value === 'liter' ? 'L' : selectedUnit.value;
        const packageText = isPackage ? ` x (${packageConversion.text})` : '';
        conversion.textContent = `Hasil stok masuk: ${amount} ${inputLabel}${packageText} = ${(amount * factor).toLocaleString('id-ID')} ${baseUnit}`;
        conversion.hidden = false;
        item.dataset.convertedAmount = amount * factor;
        item.dataset.baseUnit = baseUnit;
        updateBulkConversionSummary();
    }

    conversionBuilder = createPackageConversion(packageChain, addConversion, currentBaseUnit(), updateBulkConversion);
    amountInput.addEventListener('input', updateBulkConversion);
    grid.querySelector('[name="harga_satuan[]"]').addEventListener('input', updateBulkConversion);
    unitInput.addEventListener('change', function () {
        const selectedUnit = unitInput.options[unitInput.selectedIndex];
        if (selectedUnit && packageUnits.includes(selectedUnit.value)) conversionBuilder.reset(selectedUnit.value, currentBaseUnit());
        updateBulkConversion();
    });

    const priceFields = [grid.querySelector('[name="harga_satuan[]"]').parentElement, grid.querySelector('[name="total_pembelian[]"]').parentElement];
    item.append(title, removeButton, hiddenId, hiddenFlag, grid, baseField, conversion, packageGroup, ...priceFields);
    items.append(item);
    updateBulkItemLabels();
    return item;
}

function updateBulkItemLabels() {
    Array.from(document.querySelectorAll('.bulk-order-item')).forEach((item, index) => {
        const nameText = item.querySelector('.bulk-item-name');
        const currentName = nameText.textContent.replace(/^\d+\.\s*/, '');
        nameText.textContent = `${index + 1}. ${currentName}`;
    });
}

function openBulkOrder(selected) {
    const suppliers = selected.length ? commonSuppliers(selected) : <?= json_encode($all_suppliers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (!suppliers.length) {
        alert('Bahan yang dipilih tidak memiliki supplier yang sama. Pilih bahan dari supplier yang sama.');
        return;
    }

    configureBulkSupplier(suppliers);

    document.getElementById('bulkOrderItems').replaceChildren();
    document.getElementById('bulkConversionSummary').hidden = true;
    selected.forEach(check => {
        createBulkOrderItem({
            materialId: Number(check.value),
            name: check.dataset.name,
            baseUnit: check.dataset.unit,
            isNew: false,
        });
    });
    bulkModal.style.display = 'flex';
    document.getElementById('bulkExistingPicker').hidden = true;
}
if (bulkButton) bulkButton.addEventListener('click', () => openBulkOrder(bulkChecks.filter(check => check.checked)));
document.getElementById('openNewOrder').addEventListener('click', () => openBulkOrder([]));

document.getElementById('addBulkNewMaterial').addEventListener('click', function () {
    if (bulkModal.style.display !== 'flex') return;
    document.getElementById('bulkExistingPicker').hidden = true;
    createBulkOrderItem({ materialId: 0, name: 'Bahan Baru', baseUnit: '', isNew: true });
});

const bulkExistingPicker = document.getElementById('bulkExistingPicker');
const bulkExistingList = document.getElementById('bulkExistingList');

document.getElementById('addBulkExistingMaterial').addEventListener('click', function () {
    if (bulkModal.style.display !== 'flex') return;
    const supplierId = document.getElementById('bulkSupplier').value;
    if (!supplierId) {
        alert('Pilih supplier terlebih dahulu.');
        return;
    }
    const usedIds = Array.from(document.querySelectorAll('#bulkOrderItems input[name="bahan_id[]"]')).map(input => Number(input.value));
    const options = allMaterials.filter(material =>
        (suppliersByBahan[material.id] || []).some(supplier => String(supplier.id) === supplierId)
        && !usedIds.includes(material.id)
    );
    if (!options.length) {
        alert('Tidak ada bahan lain dari supplier ini yang bisa ditambahkan.');
        return;
    }
    bulkExistingList.replaceChildren();
    options.forEach(material => {
        const row = document.createElement('tr');
        const nameCell = document.createElement('td');
        const stockCell = document.createElement('td');
        const checkCell = document.createElement('td');
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.value = material.id;
        checkbox.className = 'bulk-existing-material-check';
        checkbox.setAttribute('aria-label', `Tambahkan ${material.name}`);
        nameCell.textContent = material.name;
        stockCell.textContent = `${Number(material.stock).toLocaleString('id-ID')} ${material.unit}`;
        checkCell.appendChild(checkbox);
        row.append(nameCell, stockCell, checkCell);
        bulkExistingList.appendChild(row);
    });
    bulkExistingPicker.hidden = false;
});

document.getElementById('confirmBulkExistingMaterial').addEventListener('click', function () {
    const materialIds = Array.from(document.querySelectorAll('.bulk-existing-material-check:checked')).map(input => Number(input.value));
    if (!materialIds.length) {
        alert('Pilih minimal satu bahan yang ingin ditambahkan.');
        return;
    }
    materialIds.forEach(materialId => {
        const material = allMaterials.find(item => item.id === materialId);
        if (material) createBulkOrderItem({ materialId: material.id, name: material.name, baseUnit: material.unit, isNew: false });
    });
    bulkExistingPicker.hidden = true;
});

document.getElementById('closeBulkOrder').addEventListener('click', () => bulkModal.style.display = 'none');
window.addEventListener('click', event => { if (event.target === bulkModal) bulkModal.style.display = 'none'; });

function openSupplierOrderModal(button) {
    document.getElementById('supplierOrderForm').reset();
    const suppliers = suppliersByBahan[button.dataset.id] || [];
    const supplierSelect = document.getElementById('supplier_order_select');
    const supplierNote = document.getElementById('supplier_order_note');

    document.getElementById('supplier_order_bahan_id').value = button.dataset.id;
    document.getElementById('supplier_order_request_id').value = button.dataset.requestId;
    document.getElementById('supplier_order_bahan').value = button.dataset.name;
    document.getElementById('supplier_order_stock').value = button.dataset.stock + ' ' + button.dataset.unit;
    document.getElementById('supplier_order_amount').value = '';
    document.getElementById('supplier_order_base_unit').value = button.dataset.unit;
    const inputUnit = document.getElementById('supplier_order_input_unit');
    const category = ['gram', 'g', 'kg'].includes(button.dataset.unit)
        ? 'weight'
        : (['ml', 'liter', 'l'].includes(button.dataset.unit) ? 'volume' : 'count');
    inputUnit.value = '';
    Array.from(inputUnit.options).slice(1).forEach(option => {
        option.hidden = option.dataset.package
            ? !['all', category].includes(option.dataset.category)
            : option.dataset.category !== category;
    });
    document.getElementById('supplier_order_package_content').value = '';
    document.getElementById('supplierOrderPackageField').hidden = true;
    document.getElementById('supplierOrderConversion').hidden = true;
    supplierConversionBuilder.reset('', button.dataset.unit);
    supplierSelect.innerHTML = '<option value="">Pilih Supplier</option>';

    suppliers.forEach(supplier => {
        const option = document.createElement('option');
        option.value = supplier.id;
        option.textContent = supplier.nama_supplier;
        supplierSelect.appendChild(option);
    });
    applyFilteredSupplier(supplierSelect);

    supplierNote.textContent = suppliers.length
        ? 'Pilih supplier tempat bahan akan dipesan.'
        : 'Bahan ini belum memiliki supplier.';
    document.getElementById('supplierOrderModal').style.display = 'flex';
}

function closeSupplierOrderModal() {
    document.getElementById('supplierOrderModal').style.display = 'none';
}

document.querySelectorAll('.btn-order-supplier').forEach(button => {
    button.addEventListener('click', () => openBulkOrder([{value: button.dataset.id, dataset: button.dataset}]));
});

function updateSupplierOrderConversion() {
    const amount = parseFloat(document.getElementById('supplier_order_amount').value || '0');
    const inputUnit = document.getElementById('supplier_order_input_unit');
    const selected = inputUnit.options[inputUnit.selectedIndex];
    const baseUnit = document.getElementById('supplier_order_base_unit').value;
    const price = document.getElementById('supplier_order_price');
    document.querySelector('#supplierOrderForm [name="total_pembelian"]').value = (amount * Number(price.value)).toFixed(2);
    document.getElementById('supplier_order_total_display').value = 'Rp' + Number(document.querySelector('#supplierOrderForm [name="total_pembelian"]').value).toLocaleString('id-ID', {maximumFractionDigits: 2});
    price.previousElementSibling.textContent = 'Harga per ' + (selected && selected.value ? selected.text.split(' (')[0] : 'Satuan Pembelian') + ' (Rp)';
    const packageField = document.getElementById('supplierOrderPackageField');
    const packageInput = document.getElementById('supplier_order_package_content');
    const conversion = document.getElementById('supplierOrderConversion');
    const isPackage = selected && selected.dataset.package === '1';
    packageField.hidden = !isPackage;
    packageField.querySelectorAll('.package-conversion-chain input, .package-conversion-chain select').forEach(control => control.disabled = !isPackage);
    const packageConversion = isPackage ? supplierConversionBuilder.calculate() : null;
    packageInput.value = packageConversion ? packageConversion.amount : '0';

    if (!selected || !selected.value || amount <= 0 || (isPackage && !packageConversion)) {
        conversion.hidden = true;
        return;
    }

    const normalizedBaseUnit = baseUnit === 'gram' ? 'g' : baseUnit;
    const normalizedInputUnit = selected.value === 'l' ? 'liter' : selected.value;

    const factor = isPackage ? packageConversion.amount : unitFactor(selected.value, baseUnit);
    const inputLabel = selected.value === 'liter' ? 'L' : selected.value;
    const packageText = isPackage ? ` x (${packageConversion.text})` : '';
    conversion.textContent =
        `Hasil konversi: ${amount} ${inputLabel}${packageText} = ${(amount * factor).toLocaleString('id-ID')} ${baseUnit}`;
    conversion.hidden = false;
}

document.getElementById('supplier_order_input_unit').addEventListener('change', function () {
    const selected = this.options[this.selectedIndex];
    if (selected && selected.dataset.package === '1') {
        supplierConversionBuilder.reset(selected.value, document.getElementById('supplier_order_base_unit').value);
    }
    updateSupplierOrderConversion();
});
document.getElementById('supplier_order_amount').addEventListener('input', updateSupplierOrderConversion);
document.getElementById('supplier_order_price').addEventListener('input', updateSupplierOrderConversion);

document.getElementById('supplierOrderForm').addEventListener('submit', function(e) {
    const supplierSelect = document.getElementById('supplier_order_select');
    const amount = document.getElementById('supplier_order_amount').value;
    const unitSelect = document.getElementById('supplier_order_input_unit');
    const selectedUnit = unitSelect.options[unitSelect.selectedIndex];

    if (!supplierSelect.value || !amount) {
        e.preventDefault();
        return;
    }
    if (selectedUnit && selectedUnit.dataset.package === '1' && !supplierConversionBuilder.calculate()) {
        e.preventDefault();
        alert('Lengkapi konversi kemasan sampai ke satuan stok.');
    }
});

document.getElementById('bulkOrderForm').addEventListener('submit', function(e) {
    if (!this.querySelector('.bulk-order-item')) { e.preventDefault(); alert('Tambahkan minimal satu bahan.'); return; }
    const incompletePackage = Array.from(this.querySelectorAll('.bulk-order-item')).some(item => {
        const unit = item.querySelector('select[name="satuan_input[]"]').value;
        const content = parseFloat(item.querySelector('input[name="isi_per_kemasan[]"]').value || '0');
        return packageUnits.includes(unit) && content <= 0;
    });
    if (incompletePackage) {
        e.preventDefault();
        alert('Lengkapi konversi kemasan setiap bahan sampai ke satuan stok.');
        return;
    }
    const incompleteNewMaterial = Array.from(this.querySelectorAll('.bulk-order-item[data-is-new="1"]')).some(item => {
        const name = item.querySelector('input[name="nama_bahan_baru[]"]').value.trim();
        const unit = item.querySelector('input[name="satuan_baru[]"]').value;
        return !name || !unit;
    });
    if (incompleteNewMaterial) {
        e.preventDefault();
        alert('Lengkapi nama dan satuan dasar untuk setiap bahan baru.');
    }
});

window.addEventListener('click', function(e) {
    const modal = document.getElementById('supplierOrderModal');
    if (e.target === modal) modal.style.display = 'none';
});
</script>
<?php endif; ?>
<script>
const procurementModal = document.getElementById('procurementDetailModal');
document.querySelectorAll('.procurement-detail').forEach(button => button.addEventListener('click', function () {
    const detail = JSON.parse(this.dataset.detail || '{}');
    const items = JSON.parse(this.dataset.items || '[]');
    document.getElementById('procurementDetail').replaceChildren(...Object.entries(detail).map(([label, value]) => {
        const item = document.createElement('div');
        const title = document.createElement('span');
        const content = document.createElement('strong');
        title.textContent = label;
        content.textContent = value ?? '-';
        item.append(title, content);
        return item;
    }));
    const itemPanel = document.getElementById('procurementDetailItems');
    itemPanel.hidden = !items.length;
    if (items.length) {
        const table = document.createElement('table');
        const head = document.createElement('thead');
        const headerRow = document.createElement('tr');
        ['No', 'Bahan Baku', 'Total Dibeli', 'Konversi', 'Total Satuan'<?= $is_owner ? ", 'Harga per Satuan Pembelian', 'Total Harga Pembelian'" : '' ?>].forEach(label => {
            const cell = document.createElement('th');
            cell.textContent = label;
            headerRow.appendChild(cell);
        });
        head.appendChild(headerRow);
        const body = document.createElement('tbody');
        items.forEach((item, index) => {
            const row = document.createElement('tr');
            [index + 1, item.nama, item.dibeli, item.konversi, item.hasil<?= $is_owner ? ', item.harga_satuan, item.harga' : '' ?>].forEach(value => {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });
            body.appendChild(row);
        });
        table.append(head, body);
        const title = document.createElement('h3');
        title.textContent = 'Daftar Bahan yang Dibeli';
        itemPanel.replaceChildren(title, wrapPurchaseTable(table));
    }
    procurementModal.style.display = 'flex';
    document.getElementById('closeProcurementDetail').focus();
}));
document.getElementById('closeProcurementDetail').addEventListener('click', () => procurementModal.style.display = 'none');
window.addEventListener('click', event => { if (event.target === procurementModal) procurementModal.style.display = 'none'; });
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        procurementModal.style.display = 'none';
        const orderModal = document.getElementById('supplierOrderModal');
        if (orderModal) orderModal.style.display = 'none';
    }
});

function filterProcurementRows() {
    const keyword = document.getElementById('procurementSearch').value.toLowerCase().trim();
    const supplier = document.getElementById('procurementSupplierFilter').value;
    let visible = 0;
    document.querySelectorAll('[data-procurement-row]').forEach(row => {
        const matchesKeyword = row.textContent.toLowerCase().includes(keyword);
        const rowSuppliers = (row.dataset.suppliers || '').split('|');
        const matchesSupplier = !supplier || rowSuppliers.includes(supplier);
        row.hidden = !matchesKeyword || !matchesSupplier;
        if (row.hidden) {
            const check = row.querySelector('.bulk-material-check');
            if (check) check.checked = false;
        }
        if (!row.hidden) {
            visible++;
            const numberCell = row.querySelector('[data-row-number]');
            if (numberCell) numberCell.textContent = visible;
        }
    });
    const empty = document.getElementById('procurementEmpty');
    empty.hidden = visible > 0;
    empty.cells[0].textContent = keyword || supplier ? 'Tidak ada bahan dari supplier yang sesuai.' : 'Belum ada data pada tahap ini.';
    const selectAll = document.getElementById('selectAllMaterials');
    if (selectAll) selectAll.checked = false;
    if (typeof updateBulkSelection === 'function') updateBulkSelection();
}
document.getElementById('procurementSearch').addEventListener('input', filterProcurementRows);
document.getElementById('procurementSupplierFilter').addEventListener('change', filterProcurementRows);
</script>
</body>
</html>
