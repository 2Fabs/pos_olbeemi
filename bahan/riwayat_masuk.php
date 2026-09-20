<?php
include '../config/database.php';
include '../includes/suppliers.php';
include '../includes/stock_units.php';

ensureSupplierTables($conn);
ensureStockInputColumns($conn, 'riwayat_masuk');

$selected_bahan = isset($_GET['bahan_id']) ? (int) $_GET['bahan_id'] : 0;
$selected_supplier = isset($_GET['supplier_id']) ? (int) $_GET['supplier_id'] : 0;
$selected_jenis = trim($_GET['jenis'] ?? '');
$selected_periode = isset($_GET['periode']) ? $_GET['periode'] : 'all';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

$bahan_options = mysqli_query($conn, "
    SELECT id, nama_bahan
    FROM bahan_baku
    ORDER BY nama_bahan ASC
");

$supplier_options = mysqli_query($conn, "
    SELECT DISTINCT s.id, s.nama_supplier
    FROM riwayat_masuk rm
    JOIN supplier s ON s.id = rm.supplier_id
    ORDER BY s.nama_supplier ASC
");

$where = [];

if ($selected_bahan > 0) {
    $where[] = "bahan_id = $selected_bahan";
}

if ($selected_supplier > 0) {
    $where[] = "supplier_id = $selected_supplier";
}

if (in_array($selected_jenis, ['Stok Awal', 'Restock'], true)) {
    $jenis_safe = mysqli_real_escape_string($conn, $selected_jenis);
    $where[] = "keterangan = '$jenis_safe'";
}

if ($selected_periode === 'today') {
    $where[] = "DATE(tanggal) = CURDATE()";
} elseif ($selected_periode === '7') {
    $where[] = "tanggal >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($selected_periode === '30') {
    $where[] = "tanggal >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($selected_periode === 'custom' && !empty($start) && !empty($end)) {
    $start_safe = mysqli_real_escape_string($conn, $start);
    $end_safe = mysqli_real_escape_string($conn, $end);

    $where[] = "DATE(tanggal) BETWEEN '$start_safe' AND '$end_safe'";
}

$where_sql = '';

if (!empty($where)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

function format_stok($number) {
    return rtrim(rtrim($number, '0'), '.');
}

function get_riwayat_masuk_rows($conn, $where_sql) {
    $query = mysqli_query($conn, "
        SELECT *
        FROM riwayat_masuk
        $where_sql
        ORDER BY tanggal DESC, id DESC
    ");

    $rows = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $rows[] = $row;
    }

    return $rows;
}

$result = mysqli_query($conn, "
    SELECT *
    FROM riwayat_masuk
    $where_sql
    ORDER BY tanggal DESC, id DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Stok Masuk</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/bahan.css?v=<?= filemtime(__DIR__ . '/../css/bahan.css') ?>">
</head>
<body>

<?php
$current_module = 'riwayat_masuk';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Riwayat Stok Masuk</h1>
            <p>Catatan histori perubahan stok bahan baku</p>
        </div>
    </div>

    <div class="table-card">

        <div class="filter-bar stock-in-filter">
            <input type="text" id="searchInput" placeholder="Cari bahan / keterangan...">

            <form method="GET" class="filter-form">
                <select name="bahan_id">
                    <option value="0">Semua Bahan</option>

                    <?php while ($bahan = mysqli_fetch_assoc($bahan_options)): ?>
                        <option value="<?= $bahan['id'] ?>"
                            <?= $selected_bahan == $bahan['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($bahan['nama_bahan']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <select name="supplier_id">
                    <option value="0">Semua Supplier</option>
                    <?php while ($supplier = mysqli_fetch_assoc($supplier_options)): ?>
                        <option value="<?= (int) $supplier['id'] ?>" <?= $selected_supplier === (int) $supplier['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($supplier['nama_supplier']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <select name="jenis">
                    <option value="">Semua Keterangan</option>
                    <option value="Stok Awal" <?= $selected_jenis === 'Stok Awal' ? 'selected' : '' ?>>Stok Awal</option>
                    <option value="Restock" <?= $selected_jenis === 'Restock' ? 'selected' : '' ?>>Restock</option>
                </select>

                <select name="periode" onchange="toggleCustomRange(this.value)">
                    <option value="all" <?= $selected_periode == 'all' ? 'selected' : '' ?>>Semua Periode</option>
                    <option value="today" <?= $selected_periode == 'today' ? 'selected' : '' ?>>Hari Ini</option>
                    <option value="7" <?= $selected_periode == '7' ? 'selected' : '' ?>>7 Hari Terakhir</option>
                    <option value="30" <?= $selected_periode == '30' ? 'selected' : '' ?>>30 Hari Terakhir</option>
                    <option value="custom" <?= $selected_periode == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                </select>

                <div
                    id="customRange"
                    style="<?= $selected_periode === 'custom' ? 'display:flex;' : 'display:none;' ?>">
                    <input type="date" name="start" value="<?= htmlspecialchars($start) ?>">
                    <input type="date" name="end" value="<?= htmlspecialchars($end) ?>">
                </div>

                <button type="submit" class="btn-primary">
                    Filter
                </button>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Bahan</th>
                    <th>Supplier</th>
                    <th>Stok Masuk</th>
                    <th>Stok Sebelumnya</th>
                    <th>Total Stok</th>
                    <th>Keterangan</th>
                    <th>Tanggal</th>
                </tr>
            </thead>

            <tbody id="riwayatTable">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php $stok_sebelumnya = (float) $row['total_setelah'] - (float) $row['jumlah_masuk']; ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td><?= htmlspecialchars($row['nama_bahan']) ?></td>

                            <td><?= htmlspecialchars($row['supplier_nama'] ?: '-') ?></td>

                            <td>
                                <?= format_stok($row['jumlah_masuk']) ?>
                                <span class="unit-symbol" data-unit="<?= htmlspecialchars($row['satuan']) ?>"><?= htmlspecialchars($row['satuan']) ?></span>
                            </td>

                            <td>
                                <?= format_stok(number_format($stok_sebelumnya, 2, '.', '')) ?>
                                <span class="unit-symbol" data-unit="<?= htmlspecialchars($row['satuan']) ?>"><?= htmlspecialchars($row['satuan']) ?></span>
                            </td>

                            <td>
                                <?= rtrim(rtrim($row['total_setelah'], '0'), '.') ?>
                                <span class="unit-symbol" data-unit="<?= htmlspecialchars($row['satuan']) ?>"><?= htmlspecialchars($row['satuan']) ?></span>
                            </td>

                            <td><?= htmlspecialchars($row['keterangan']) ?></td>

                            <td>
                                <?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">Tidak ada data riwayat.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleCustomRange(value) {
    const customRange = document.getElementById('customRange');

    customRange.style.display = value === 'custom'
        ? 'flex'
        : 'none';
}

document.getElementById('searchInput').addEventListener('keyup', function() {
    const keyword = this.value.toLowerCase();
    const rows = document.querySelectorAll('#riwayatTable tr');

    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(keyword)
            ? ''
            : 'none';
    });
});
</script>

</body>
</html>
