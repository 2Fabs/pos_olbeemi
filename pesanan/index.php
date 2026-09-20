<?php
include '../config/database.php';
include '../includes/pesanan_tables.php';

ensurePesananTables($conn);

$status_filter = $_GET['status'] ?? 'proses';
$type_filter = $_GET['type'] ?? 'all';
$allowed_status = ['proses', 'Selesai', 'Dibatalkan'];
$allowed_types = ['all', 'kedai', 'online'];

if (!in_array($status_filter, $allowed_status)) {
    $status_filter = 'proses';
}

if (!in_array($type_filter, $allowed_types)) {
    $type_filter = 'all';
}

if (auth_has_role(['barista'])) {
    $status_filter = 'proses';
}

$status_where = "1=1";

if ($status_filter === 'proses') {
    $status_where .= " AND status IN ('Menunggu', 'Diproses')";
} else {
    $status_safe = mysqli_real_escape_string($conn, $status_filter);
    $status_where .= " AND status = '$status_safe'";
}

$where = $status_where;

if ($type_filter === 'kedai') {
    $where .= " AND sumber_pesanan = 'Kedai'";
} elseif ($type_filter === 'online') {
    $where .= " AND COALESCE(sumber_pesanan, '') <> 'Kedai'";
}

$summary_query = mysqli_query($conn, "
    SELECT
        SUM(CASE WHEN status IN ('Menunggu', 'Diproses') THEN 1 ELSE 0 END) AS proses,
        SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) AS selesai,
        SUM(CASE WHEN status = 'Dibatalkan' THEN 1 ELSE 0 END) AS dibatalkan
    FROM pesanan
");
$summary = mysqli_fetch_assoc($summary_query);

$type_summary_query = mysqli_query($conn, "
    SELECT
        COUNT(*) AS all_count,
        SUM(CASE WHEN sumber_pesanan = 'Kedai' THEN 1 ELSE 0 END) AS kedai,
        SUM(CASE WHEN COALESCE(sumber_pesanan, '') <> 'Kedai' THEN 1 ELSE 0 END) AS online
    FROM pesanan
    WHERE $status_where
");
$type_summary = mysqli_fetch_assoc($type_summary_query);

$order_direction = auth_has_role('barista') ? 'ASC' : 'DESC';
$pesanan_query = mysqli_query($conn, "
    SELECT *
    FROM pesanan
    WHERE $where
    ORDER BY created_at $order_direction, id $order_direction
");

function formatRupiahPesanan($number) {
    return 'Rp' . number_format($number, 0, ',', '.');
}

function formatStatusPesanan($status) {
    return $status === 'Diproses' ? 'Dalam Proses' : $status;
}

function statusClassPesanan($status) {
    return in_array($status, ['Menunggu', 'Diproses'], true)
        ? 'diproses'
        : strtolower($status);
}

function formatSumberPesanan($source) {
    return $source === 'Kedai'
        ? 'Kedai'
        : 'QR';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/pemesanan.css?v=<?= filemtime(__DIR__ . '/../css/pemesanan.css') ?>">
</head>
<body>

<?php
$current_module = 'pesanan';
include '../includes/sidebar.php';
?>

<div class="main-content orders-page">
    <div class="header">
        <div>
            <h1>Pesanan</h1>
            <p>Daftar pesanan kedai dan online dalam satu halaman.</p>
        </div>
    </div>

    <div class="orders-overview-bar">
        <div class="order-type-tabs">
            <a href="index.php?status=<?= urlencode($status_filter) ?>&type=all" class="<?= $type_filter === 'all' ? 'active' : '' ?>">
                Semua <span>(<?= (int) ($type_summary['all_count'] ?? 0) ?>)</span>
            </a>
            <a href="index.php?status=<?= urlencode($status_filter) ?>&type=kedai" class="<?= $type_filter === 'kedai' ? 'active' : '' ?>">
                Kedai <span>(<?= (int) ($type_summary['kedai'] ?? 0) ?>)</span>
            </a>
            <a href="index.php?status=<?= urlencode($status_filter) ?>&type=online" class="<?= $type_filter === 'online' ? 'active' : '' ?>">
                Online <span>(<?= (int) ($type_summary['online'] ?? 0) ?>)</span>
            </a>
        </div>

        <div class="order-summary-grid">
            <a href="index.php?status=proses&type=<?= urlencode($type_filter) ?>" class="<?= $status_filter === 'proses' ? 'active' : '' ?>">
                <span>Dalam Proses</span>
                <strong><?= (int) ($summary['proses'] ?? 0) ?></strong>
            </a>

            <?php if (!auth_has_role(['barista'])): ?>
                <a href="index.php?status=Selesai&type=<?= urlencode($type_filter) ?>" class="<?= $status_filter === 'Selesai' ? 'active' : '' ?>">
                    <span>Selesai</span>
                    <strong><?= (int) ($summary['selesai'] ?? 0) ?></strong>
                </a>

                <a href="index.php?status=Dibatalkan&type=<?= urlencode($type_filter) ?>" class="<?= $status_filter === 'Dibatalkan' ? 'active' : '' ?>">
                    <span>DIBATALKAN</span>
                    <strong><?= (int) ($summary['dibatalkan'] ?? 0) ?></strong>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-card orders-table-card">
        <div class="search-box"><input type="search" id="orderSearch" placeholder="Cari nama pelanggan atau kode pesanan..." aria-label="Cari pesanan"></div>
        <table class="orders-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Pelanggan</th>
                    <th>Keterangan</th>
                    <th>Metode</th>
                    <th>Status</th>
                    <th>Subtotal</th>
                    <th>Waktu</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($pesanan_query) > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($row = mysqli_fetch_assoc($pesanan_query)): ?>
                        <?php $is_kedai = ($row['sumber_pesanan'] ?? '') === 'Kedai'; ?>
                        <tr data-order-search="<?= htmlspecialchars(strtolower($row['kode_pesanan'] . ' ' . $row['nama_pelanggan']), ENT_QUOTES) ?>">
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['kode_pesanan']) ?></td>
                            <td>
                                <strong><?= $is_kedai ? 'Kedai' : htmlspecialchars($row['nama_pelanggan']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars(formatSumberPesanan($row['sumber_pesanan'] ?? 'QR Menu')) ?></td>
                            <td><?= htmlspecialchars($row['metode_pembayaran'] ?? 'Cash') ?></td>
                            <td>
                                <span class="order-status <?= statusClassPesanan($row['status']) ?>">
                                    <?= htmlspecialchars(formatStatusPesanan($row['status'])) ?>
                                </span>
                            </td>
                            <td class="money-cell"><?= formatRupiahPesanan($row['subtotal']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                            <td>
                                <div class="order-table-actions">
                                    <a href="detail.php?id=<?= (int) $row['id'] ?>" class="btn-stock">
                                        Detail
                                    </a>

                                    <?php if (!empty($row['transaksi_id']) && auth_has_role(['owner'])): ?>
                                        <a href="../laporan/detail.php?id=<?= (int) $row['transaksi_id'] ?>" class="btn-primary">
                                            Laporan
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">Belum ada pesanan.</td>
                    </tr>
                <?php endif; ?>
                <tr id="orderSearchEmpty" hidden><td colspan="9">Pesanan tidak ditemukan.</td></tr>
            </tbody>
        </table>
    </div>

    </div>
</div>
<script>
document.getElementById('orderSearch').addEventListener('input', function () {
    let visible = 0;
    document.querySelectorAll('[data-order-search]').forEach(row => {
        row.hidden = !row.dataset.orderSearch.includes(this.value.toLowerCase().trim());
        if (!row.hidden) visible++;
    });
    document.getElementById('orderSearchEmpty').hidden = visible > 0 || !this.value.trim();
});
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.order-summary-grid > a').forEach(function (el) {
        el.addEventListener('click', function (e) {
            // ripple effect
            var rect = el.getBoundingClientRect();
            var ripple = document.createElement('span');
            ripple.className = 'ripple';
            var size = Math.max(rect.width, rect.height) * 0.6;
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            el.appendChild(ripple);
            setTimeout(function () { ripple.remove(); }, 700);
        });

        // keyboard activation for accessibility
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                el.click();
            }
        });
    });
});
</script>
</body>
</html>
