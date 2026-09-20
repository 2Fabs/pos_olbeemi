<?php
include '../config/database.php';
include '../includes/stock_units.php';

ensureStockInputColumns($conn, 'riwayat_keluar');

$filter = $_GET['filter'] ?? 'all';
$bahan_filter = (int) ($_GET['bahan_id'] ?? 0);
$penyebab_filter = $_GET['penyebab'] ?? 'all';
$is_owner = auth_current_role() === 'owner';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

$where = "1=1";

switch ($filter) {
    case 'today':
        $where .= " AND DATE(tanggal) = CURDATE()";
        break;

    case '7':
        $where .= " AND tanggal >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;

    case '30':
        $where .= " AND tanggal >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;

    case 'custom':
        if (!empty($start) && !empty($end)) {
            $start_safe = mysqli_real_escape_string($conn, $start);
            $end_safe = mysqli_real_escape_string($conn, $end);

            $where .= " AND DATE(tanggal)
                        BETWEEN '$start_safe' AND '$end_safe'";
        }
        break;
}

if ($bahan_filter > 0) {
    $where .= " AND bahan_id = $bahan_filter";
}

$penyebab_stok = ['Stok Basi', 'Stok Rusak', 'Salah Input', 'Penyesuaian Stok'];
$penyebab_labels = [
    'penjualan' => 'Penjualan',
    'Stok Basi' => 'Stok Basi',
    'Stok Rusak' => 'Stok Rusak',
    'Salah Input' => 'Salah Input',
];
if ($is_owner) $penyebab_labels['Penyesuaian Stok'] = 'Selisih Stok Fisik';

$penyebab_per_bahan = ['0' => []];
$penyebab_query = mysqli_query($conn, "SELECT DISTINCT bahan_id, keterangan FROM riwayat_keluar");
while ($penyebab_row = $penyebab_query ? mysqli_fetch_assoc($penyebab_query) : null) {
    if (!$penyebab_row) break;
    $keterangan = trim((string) $penyebab_row['keterangan']);
    $key = in_array($keterangan, $penyebab_stok, true) ? $keterangan : 'penjualan';
    if (!isset($penyebab_labels[$key])) continue;
    $bahan_key = (string) ((int) $penyebab_row['bahan_id']);
    $penyebab_per_bahan[$bahan_key][$key] = true;
    $penyebab_per_bahan['0'][$key] = true;
}

$opsi_penyebab = [];
foreach ($penyebab_labels as $key => $label) {
    if (isset($penyebab_per_bahan[(string) $bahan_filter][$key])) $opsi_penyebab[$key] = $label;
}
if ($penyebab_filter !== 'all' && !isset($opsi_penyebab[$penyebab_filter])) $penyebab_filter = 'all';

if ($penyebab_filter === 'penjualan') {
    $penyebab_sql = implode("','", array_map(function ($item) use ($conn) {
        return mysqli_real_escape_string($conn, $item);
    }, $penyebab_stok));
    $where .= " AND keterangan NOT IN ('$penyebab_sql')";
} elseif (in_array($penyebab_filter, $penyebab_stok, true)) {
    $penyebab_safe = mysqli_real_escape_string($conn, $penyebab_filter);
    $where .= " AND keterangan = '$penyebab_safe'";
}

$bahan_query = mysqli_query($conn, "
    SELECT id, nama_bahan
    FROM bahan_baku
    ORDER BY nama_bahan ASC
");

function format_stok($number) {
    return rtrim(rtrim($number, '0'), '.');
}

function format_keterangan_keluar($keterangan) {
    $text = trim((string) $keterangan);

    if ($text === '') {
        return '-';
    }

    $text = preg_replace(
        '/^Penjualan(?:\s+pemesanan)?(?:\s+online|\s+kedai)?\s+/i',
        '',
        $text
    );
    $text = preg_replace('/\s+\((?:ORD|TRX)[^)]*\)$/i', '', $text);

    return trim($text) !== '' ? trim($text) : '-';
}

function get_riwayat_keluar_rows($conn, $where) {
    $query = mysqli_query($conn, "
        SELECT *
        FROM riwayat_keluar
        WHERE $where
        ORDER BY tanggal DESC, id DESC
    ");

    $rows = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $rows[] = $row;
    }

    return $rows;
}

$riwayat_query = mysqli_query($conn, "
    SELECT *
    FROM riwayat_keluar
    WHERE $where
    ORDER BY tanggal DESC, id DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Stok Keluar</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/bahan.css?v=<?= filemtime(__DIR__ . '/../css/bahan.css') ?>">
</head>
<body>

<?php
$current_module = 'riwayat_keluar';
include '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="header">
        <div>
            <h1>Riwayat Stok Keluar</h1>
            <p>Monitoring penggunaan bahan dari transaksi dan penyesuaian stok</p>
        </div>
    </div>

    <div class="table-card">
        <div class="filter-bar stock-in-filter stock-out-filter">
            <input type="text" id="searchInput" placeholder="Cari bahan / keterangan...">

            <form method="GET" class="filter-form">
                <select name="bahan_id">
                    <option value="0">Semua Bahan</option>

                    <?php while ($bahan = mysqli_fetch_assoc($bahan_query)): ?>
                        <option
                            value="<?= $bahan['id'] ?>"
                            <?= $bahan_filter == $bahan['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($bahan['nama_bahan']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <select name="penyebab">
                    <option value="all" <?= $penyebab_filter === 'all' ? 'selected' : '' ?>>Semua Keterangan</option>
                    <?php foreach ($opsi_penyebab as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= $penyebab_filter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="filter" onchange="toggleCustomRange(this.value)">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Semua Periode</option>
                    <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>Hari Ini</option>
                    <option value="7" <?= $filter === '7' ? 'selected' : '' ?>>7 Hari Terakhir</option>
                    <option value="30" <?= $filter === '30' ? 'selected' : '' ?>>30 Hari Terakhir</option>
                    <option value="custom" <?= $filter === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                </select>

                <div
                    id="customRange"
                    style="<?= $filter === 'custom' ? 'display:flex;' : 'display:none;' ?>">
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
                    <th>Stok Keluar</th>
                    <th>Stok Sebelumnya</th>
                    <th>Total Stok</th>
                    <th>Keterangan</th>
                    <th>Tanggal</th>
                </tr>
            </thead>

            <tbody id="riwayatTable">
                <?php if (mysqli_num_rows($riwayat_query) > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($row = mysqli_fetch_assoc($riwayat_query)): ?>
                        <?php $stok_sebelumnya = (float) $row['total_setelah'] + (float) $row['jumlah_keluar']; ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td><?= htmlspecialchars($row['nama_bahan']) ?></td>

                            <td>
                                <?= format_stok($row['jumlah_keluar']) ?>
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

                            <td><?= htmlspecialchars(format_keterangan_keluar($row['keterangan'])) ?></td>

                            <td>
                                <?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">Belum ada riwayat stok keluar.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
const stockCausesByMaterial = <?= json_encode(array_map('array_keys', $penyebab_per_bahan), JSON_UNESCAPED_UNICODE) ?>;
const stockCauseLabels = <?= json_encode($penyebab_labels, JSON_UNESCAPED_UNICODE) ?>;
const materialFilter = document.querySelector('select[name="bahan_id"]');
const causeFilter = document.querySelector('select[name="penyebab"]');

materialFilter.addEventListener('change', function () {
    const current = causeFilter.value;
    const available = stockCausesByMaterial[this.value] || [];
    causeFilter.replaceChildren(new Option('Semua Penyebab', 'all'));
    available.forEach(value => causeFilter.add(new Option(stockCauseLabels[value], value)));
    causeFilter.value = available.includes(current) ? current : 'all';
});

function toggleCustomRange(value) {
    const custom = document.getElementById('customRange');

    custom.style.display = value === 'custom'
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
