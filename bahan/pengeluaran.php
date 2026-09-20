<?php
include '../config/database.php';
include '../includes/pengeluaran.php';
include '../includes/suppliers.php';
ensureSupplierTables($conn);
include '../includes/stock_units.php';
ensureStockInputColumns($conn, 'riwayat_masuk');

ensurePengeluaranTable($conn);

$selected_periode = $_GET['periode'] ?? 'month';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

$where = ["pengeluaran.jenis IN ('Stok Awal', 'Restock')"];

if ($selected_periode === 'today') {
    $where[] = 'DATE(pengeluaran.tanggal) = CURDATE()';
} elseif ($selected_periode === '7') {
    $where[] = 'pengeluaran.tanggal >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($selected_periode === '30') {
    $where[] = 'pengeluaran.tanggal >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
} elseif ($selected_periode === 'month') {
    $where[] = 'MONTH(pengeluaran.tanggal) = MONTH(CURDATE()) AND YEAR(pengeluaran.tanggal) = YEAR(CURDATE())';
} elseif ($selected_periode === 'custom' && $start !== '' && $end !== '') {
    $start_safe = mysqli_real_escape_string($conn, $start);
    $end_safe = mysqli_real_escape_string($conn, $end);
    $where[] = "DATE(pengeluaran.tanggal) BETWEEN '$start_safe' AND '$end_safe'";
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$summary_query = mysqli_query($conn, "
    SELECT
        COALESCE(SUM(nominal), 0) AS total_pengeluaran
    FROM pengeluaran
    $where_sql
");
$summary = mysqli_fetch_assoc($summary_query);

$result = mysqli_query($conn, "
    SELECT
        pengeluaran.*,
        riwayat_masuk.jumlah_masuk,
        riwayat_masuk.satuan,
        riwayat_masuk.jumlah_input,
        riwayat_masuk.satuan_input,
        riwayat_masuk.isi_per_kemasan,
        pesanan_supplier.foto_nota,
        pesanan_supplier.kode_pesanan,
        pesanan_supplier.tanggal_pesan AS tanggal_pesanan_supplier
    FROM pengeluaran
    LEFT JOIN riwayat_masuk ON riwayat_masuk.id = pengeluaran.riwayat_masuk_id
    LEFT JOIN pesanan_supplier ON pesanan_supplier.id = pengeluaran.pesanan_supplier_id
    $where_sql
    ORDER BY pengeluaran.tanggal DESC, pengeluaran.id DESC
");

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    $periode_labels = [
        'all' => 'Semua Periode',
        'today' => 'Hari Ini',
        '7' => '7 Hari Terakhir',
        '30' => '30 Hari Terakhir',
        'month' => 'Bulan Ini',
    ];
    $periode_label = $periode_labels[$selected_periode] ?? 'Rentang Tanggal';
    if ($selected_periode === 'custom' && $start !== '' && $end !== '') {
        $periode_label = date('d/m/Y', strtotime($start)) . ' - ' . date('d/m/Y', strtotime($end));
    }
    include '../includes/pengeluaran_pdf.php';
    generatePengeluaranPdf($rows, $summary, $periode_label);
}

$expense_rows = [];
while ($result && $row = mysqli_fetch_assoc($result)) {
    $group_key = !empty($row['pesanan_supplier_id'])
        ? $row['kode_pesanan']
        : 'single|' . $row['id'];
    if (!isset($expense_rows[$group_key])) {
        $row['items'] = [];
        $row['order_ids'] = [];
        $row['group_nominal'] = 0;
        $expense_rows[$group_key] = $row;
    }
    $expense_rows[$group_key]['items'][] = $row;
    $expense_rows[$group_key]['group_nominal'] += (float) $row['nominal'];
    if (!empty($row['pesanan_supplier_id'])) $expense_rows[$group_key]['order_ids'][] = (int) $row['pesanan_supplier_id'];
    if (empty($expense_rows[$group_key]['foto_nota']) && !empty($row['foto_nota'])) {
        $expense_rows[$group_key]['foto_nota'] = $row['foto_nota'];
        $expense_rows[$group_key]['pesanan_supplier_id'] = $row['pesanan_supplier_id'];
    }
}
$expense_rows = array_values($expense_rows);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pengeluaran</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/bahan.css?v=<?= filemtime(__DIR__ . '/../css/bahan.css') ?>">
</head>
<body class="expense-report-body">

<?php
$current_module = 'pengeluaran';
include '../includes/sidebar.php';
?>

<div class="main-content expense-page">
    <div class="header">
        <div>
            <h1>Laporan Pengeluaran</h1>
            <p>Pantau pengeluaran pembelian dan penambahan stok bahan</p>
        </div>
    </div>

    <?php if (!empty($_GET['success'])): ?><div class="request-notice request-notice-success"><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?>
    <?php if (!empty($_GET['error'])): ?><div class="request-notice request-notice-error"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

    <div class="expense-summary-grid">
        <div class="summary-card expense-total-card">
            <h3>Total Pengeluaran</h3>
            <strong>Rp<?= number_format($summary['total_pengeluaran'] ?? 0, 0, ',', '.') ?></strong>
        </div>
    </div>

    <div class="table-card">
        <form method="GET" class="expense-filter-form">
            <select name="periode" onchange="toggleExpenseRange(this.value)">
                <option value="all" <?= $selected_periode === 'all' ? 'selected' : '' ?>>Semua Periode</option>
                <option value="today" <?= $selected_periode === 'today' ? 'selected' : '' ?>>Hari Ini</option>
                <option value="7" <?= $selected_periode === '7' ? 'selected' : '' ?>>7 Hari Terakhir</option>
                <option value="30" <?= $selected_periode === '30' ? 'selected' : '' ?>>30 Hari Terakhir</option>
                <option value="month" <?= $selected_periode === 'month' ? 'selected' : '' ?>>Bulan Ini</option>
                <option value="custom" <?= $selected_periode === 'custom' ? 'selected' : '' ?>>Rentang Tanggal</option>
            </select>

            <div id="expenseCustomRange" class="expense-custom-range" style="<?= $selected_periode === 'custom' ? 'display:flex;' : 'display:none;' ?>">
                <input type="date" name="start" value="<?= htmlspecialchars($start) ?>">
                <input type="date" name="end" value="<?= htmlspecialchars($end) ?>">
            </div>

            <button type="submit" class="btn-primary">Terapkan</button>
            <button type="submit" name="export" value="pdf" class="btn-stock">Export PDF</button>
        </form>

        <table class="expense-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode Pesanan</th>
                    <th>Supplier</th>
                    <th>Jumlah Bahan</th>
                    <th>Harga</th>
                    <th>Tanggal</th>
                    <th>Nota</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($expense_rows): ?>
                    <?php $no = 1; ?>
                    <?php foreach ($expense_rows as $row):
                        $expense_items = array_map(function ($item) {
                            return [
                                'nama' => $item['nama_bahan'] ?: '-',
                                'dibeli' => rtrim(rtrim(number_format((float) ($item['jumlah_input'] ?: $item['jumlah_masuk']), 2, ',', '.'), '0'), ',') . ' ' . ($item['satuan_input'] ?: $item['satuan']),
                                'konversi' => stockPurchaseConversionLabel($item['jumlah_input'], $item['satuan_input'], $item['jumlah_masuk'], $item['satuan'], $item['isi_per_kemasan']),
                                'harga_satuan' => (float) ($item['jumlah_input'] ?: $item['jumlah_masuk']) > 0 ? 'Rp' . rtrim(rtrim(number_format((float) $item['nominal'] / (float) ($item['jumlah_input'] ?: $item['jumlah_masuk']), 2, ',', '.'), '0'), ',') . ' / ' . ($item['satuan_input'] ?: $item['satuan']) : '-',
                                'jumlah' => rtrim(rtrim(number_format((float) ($item['jumlah_masuk'] ?? 0), 2, ',', '.'), '0'), ',') . ' ' . ($item['satuan'] ?? ''),
                                'harga' => 'Rp' . number_format((float) $item['nominal'], 0, ',', '.'),
                            ];
                        }, $row['items']);
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($row['kode_pesanan'] ?: '-') ?></strong></td>
                            <td><?= htmlspecialchars($row['nama_supplier'] ?: '-') ?></td>
                            <td><?= count($row['items']) ?> item</td>
                            <td class="money-cell">Rp<?= number_format($row['group_nominal'], 0, ',', '.') ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                            <td>
                                <div class="action-group expense-note-actions">
                                    <?php if (!empty($row['foto_nota'])): ?>
                                        <a class="expense-note-link" href="uploads/notas/<?= rawurlencode($row['foto_nota']) ?>" target="_blank" rel="noopener">Lihat Nota</a>
                                    <?php endif; ?>
                                    <?php if (!empty($row['pesanan_supplier_id'])): ?>
                                        <button type="button" class="btn-stock open-expense-note"
                                            data-order-id="<?= (int) $row['pesanan_supplier_id'] ?>"
                                            data-material="<?= htmlspecialchars($row['nama_bahan'] ?: '-', ENT_QUOTES) ?>"
                                            data-has-note="<?= !empty($row['foto_nota']) ? '1' : '0' ?>">
                                            <?= !empty($row['foto_nota']) ? 'Ganti Nota' : 'Upload Nota' ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="expense-note-empty">Tidak tersedia</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><button type="button" class="btn-stock expense-detail" data-items="<?= htmlspecialchars(json_encode($expense_items, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>" data-supplier="<?= htmlspecialchars($row['nama_supplier'] ?: '-', ENT_QUOTES) ?>" data-total="Rp<?= number_format($row['group_nominal'], 0, ',', '.') ?>">Detail</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">Belum ada pengeluaran pada periode ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="expenseDetailModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="expenseDetailTitle"><div class="modal-content workflow-modal">
    <div class="modal-header"><h2 id="expenseDetailTitle">Detail Pengeluaran</h2><button type="button" class="close-btn" id="closeExpenseDetail" aria-label="Tutup">&times;</button></div>
    <div class="request-detail-list" id="expenseDetailSummary"></div>
    <div class="procurement-detail-items" id="expenseDetailItems"></div>
</div></div>

<div id="expenseNoteModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="expenseNoteTitle"><div class="modal-content workflow-modal">
    <div class="modal-header"><h2 id="expenseNoteTitle">Upload Nota</h2><button type="button" class="close-btn" id="closeExpenseNote" aria-label="Tutup">&times;</button></div>
    <form action="simpan_pesanan_supplier.php" method="POST" enctype="multipart/form-data" id="expenseNoteForm">
        <?= auth_csrf_input() ?>
        <input type="hidden" name="action" value="upload_note">
        <input type="hidden" name="source" value="pengeluaran">
        <input type="hidden" name="order_id" id="expenseNoteOrderId">
        <div class="form-group"><label>Nama Bahan</label><input type="text" id="expenseNoteMaterial" readonly></div>
        <div class="form-group"><label for="expenseNoteInput">Foto Nota</label><input type="file" name="foto_nota" id="expenseNoteInput" accept="image/jpeg,image/png" required><small class="request-form-help">Format JPG, JPEG, atau PNG. Maksimal 5 MB.</small></div>
        <div class="receipt-preview" id="expenseNotePreview" hidden><img id="expenseNoteImage" alt="Pratinjau foto nota"></div>
        <button type="submit" class="btn-primary">Simpan Nota</button>
    </form>
</div></div>

<script src="../includes/purchase_table.js?v=<?= filemtime(__DIR__ . '/../includes/purchase_table.js') ?>"></script>
<script>
function toggleExpenseRange(value) {
    document.getElementById('expenseCustomRange').style.display = value === 'custom' ? 'flex' : 'none';
}

const expenseNoteModal = document.getElementById('expenseNoteModal');
let expenseNoteUrl = '';
document.querySelectorAll('.open-expense-note').forEach(button => button.addEventListener('click', function () {
    document.getElementById('expenseNoteForm').reset();
    document.getElementById('expenseNoteOrderId').value = this.dataset.orderId;
    document.getElementById('expenseNoteMaterial').value = this.dataset.material;
    document.getElementById('expenseNoteTitle').textContent = this.dataset.hasNote === '1' ? 'Ganti Foto Nota' : 'Upload Foto Nota';
    document.getElementById('expenseNotePreview').hidden = true;
    expenseNoteModal.style.display = 'flex';
}));
document.getElementById('expenseNoteInput').addEventListener('change', function () {
    const file = this.files[0];
    const preview = document.getElementById('expenseNotePreview');
    if (!file) { preview.hidden = true; return; }
    if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran foto nota maksimal 5 MB.');
        this.value = '';
        preview.hidden = true;
        return;
    }
    if (expenseNoteUrl) URL.revokeObjectURL(expenseNoteUrl);
    expenseNoteUrl = URL.createObjectURL(file);
    document.getElementById('expenseNoteImage').src = expenseNoteUrl;
    preview.hidden = false;
});
document.getElementById('closeExpenseNote').addEventListener('click', () => expenseNoteModal.style.display = 'none');
window.addEventListener('click', event => { if (event.target === expenseNoteModal) expenseNoteModal.style.display = 'none'; });

const expenseDetailModal = document.getElementById('expenseDetailModal');
document.querySelectorAll('.expense-detail').forEach(button => button.addEventListener('click', function () {
    const items = JSON.parse(this.dataset.items || '[]');
    const summary = document.getElementById('expenseDetailSummary');
    summary.innerHTML = `<div><span>Supplier</span><strong></strong></div><div><span>Total Pengeluaran</span><strong></strong></div>`;
    summary.children[0].querySelector('strong').textContent = this.dataset.supplier;
    summary.children[1].querySelector('strong').textContent = this.dataset.total;
    const table = document.createElement('table');
    table.innerHTML = '<thead><tr><th>No</th><th>Bahan Baku</th><th>Total Dibeli</th><th>Konversi</th><th>Total Satuan</th><th>Harga per Satuan Pembelian</th><th>Total Harga Pembelian</th></tr></thead>';
    const body = document.createElement('tbody');
    items.forEach((item, index) => {
        const row = document.createElement('tr');
        [index + 1, item.nama, item.dibeli, item.konversi, item.jumlah, item.harga_satuan, item.harga].forEach(value => {
            const cell = document.createElement('td');
            cell.textContent = value;
            row.appendChild(cell);
        });
        body.appendChild(row);
    });
    table.appendChild(body);
    const panel = document.getElementById('expenseDetailItems');
    const title = document.createElement('h3');
    title.textContent = 'Daftar Bahan yang Dibeli';
    panel.replaceChildren(title, wrapPurchaseTable(table));
    expenseDetailModal.style.display = 'flex';
}));
document.getElementById('closeExpenseDetail').addEventListener('click', () => expenseDetailModal.style.display = 'none');
window.addEventListener('click', event => { if (event.target === expenseDetailModal) expenseDetailModal.style.display = 'none'; });
</script>

</body>
</html>
