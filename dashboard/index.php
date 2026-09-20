<?php
include '../config/database.php';
include_once '../includes/stock_units.php';

$role = auth_current_role();
$user = auth_current_user();
$is_owner = $role === 'owner';
$is_kasir = $role === 'kasir';
$is_barista = $role === 'barista';

function dashboardRow($conn, $sql) {
    $query = mysqli_query($conn, $sql);
    return $query ? (mysqli_fetch_assoc($query) ?: []) : [];
}

function dashboardRows($conn, $sql) {
    $query = mysqli_query($conn, $sql);
    $rows = [];
    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) $rows[] = $row;
    }
    return $rows;
}

function dashboardMoney($value) {
    return 'Rp' . number_format((float) $value, 0, ',', '.');
}

function dashboardStock($value) {
    return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
}

$today = dashboardRow($conn, "
    SELECT COUNT(*) AS transactions,
           COALESCE(SUM(bayar - kembalian), 0) AS revenue,
           COALESCE(SUM(total_profit), 0) AS profit
    FROM transaksi
    WHERE DATE(created_at) = CURDATE()
");
$orders = dashboardRow($conn, "
    SELECT
        COALESCE(SUM(status = 'Menunggu'), 0) AS waiting,
        COALESCE(SUM(status = 'Diproses'), 0) AS processing,
        COALESCE(SUM(status = 'Selesai' AND DATE(COALESCE(selesai_at, updated_at)) = CURDATE()), 0) AS completed
    FROM pesanan
");
$stock = dashboardRow($conn, "
    SELECT COALESCE(SUM(stok <= minimum_stok), 0) AS low_stock,
           COALESCE(SUM(stok <= 0), 0) AS out_stock
    FROM bahan_baku
");
$stock_request_count = stockRequestCountExpression($conn);
$requests = dashboardRow($conn, "
    SELECT
        (SELECT $stock_request_count FROM permintaan_stok WHERE status = 'Menunggu') AS stock_requests,
        (SELECT COUNT(*) FROM permintaan_menu WHERE status = 'Menunggu') AS menu_requests,
        (SELECT COUNT(*) FROM pesanan_supplier WHERE status = 'Sudah Dipesan') AS incoming,
        (SELECT COUNT(*) FROM bahan_baku b WHERE b.stok <= b.minimum_stok AND NOT EXISTS (
            SELECT 1 FROM pesanan_supplier p WHERE p.bahan_id = b.id
              AND p.status IN ('Sudah Dipesan', 'Menunggu Persetujuan')
        )) AS materials_to_order
");

$recent_orders = dashboardRows($conn, "
    SELECT id, kode_pesanan, nama_pelanggan, sumber_pesanan, status, subtotal, created_at
    FROM pesanan
    ORDER BY created_at DESC
    LIMIT 5
");
$active_orders = dashboardRows($conn, "
    SELECT id, kode_pesanan, nama_pelanggan, sumber_pesanan, status, created_at
    FROM pesanan
    WHERE status IN ('Menunggu', 'Diproses')
    ORDER BY FIELD(status, 'Menunggu', 'Diproses'), created_at ASC
    LIMIT 5
");
$low_stocks = dashboardRows($conn, "
    SELECT nama_bahan, stok, minimum_stok, satuan
    FROM bahan_baku
    WHERE stok <= minimum_stok
    ORDER BY (stok / NULLIF(minimum_stok, 0)) ASC, nama_bahan ASC
    LIMIT 5
");
$top_menus = $is_owner ? dashboardRows($conn, "
    SELECT COALESCE(MAX(menu.nama_menu), MAX(transaksi_detail.nama_menu)) AS nama_menu,
           SUM(transaksi_detail.qty) AS sold
    FROM transaksi_detail
    JOIN transaksi ON transaksi.id = transaksi_detail.transaksi_id
    LEFT JOIN menu ON menu.id = transaksi_detail.menu_id
    WHERE transaksi.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY transaksi_detail.menu_id
    ORDER BY sold DESC, nama_menu ASC
    LIMIT 5
") : [];

$hour = (int) date('H');
$greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
$date_label = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/dashboard.css?v=<?= filemtime(__DIR__ . '/../css/dashboard.css') ?>">
</head>
<body>
<?php $current_module = 'dashboard'; include '../includes/sidebar.php'; ?>

<main class="main-content dashboard-page">
    <header class="dashboard-hero">
        <div>
            <span class="dashboard-eyebrow"><?= htmlspecialchars(ucfirst($role)) ?> Workspace</span>
            <h1><?= $greeting ?>, <?= htmlspecialchars($user['name'] ?? 'Pengguna') ?></h1>
            <p><?= $is_owner ? 'Pantau operasional Olbeemi dalam satu tampilan.' : ($is_kasir ? 'Ringkasan antrean dan transaksi hari ini.' : 'Lihat pekerjaan pesanan dan persediaan yang perlu ditangani.') ?></p>
        </div>
        <time datetime="<?= date('Y-m-d') ?>"><?= $date_label ?></time>
    </header>

    <section class="dashboard-stats" aria-label="Ringkasan hari ini">
        <?php if ($is_owner): ?>
            <a href="../laporan/index.php?filter=today" class="dashboard-stat accent"><span>Pendapatan Hari Ini</span><strong><?= dashboardMoney($today['revenue'] ?? 0) ?></strong><small><?= (int) ($today['transactions'] ?? 0) ?> transaksi</small></a>
            <a href="../laporan/index.php?filter=today" class="dashboard-stat"><span>Keuntungan Hari Ini</span><strong><?= dashboardMoney($today['profit'] ?? 0) ?></strong><small>Dari transaksi selesai</small></a>
            <a href="../pesanan/index.php?status=proses" class="dashboard-stat"><span>Pesanan Diproses</span><strong><?= (int) ($orders['waiting'] ?? 0) + (int) ($orders['processing'] ?? 0) ?></strong><small><?= (int) ($orders['waiting'] ?? 0) ?> menunggu</small></a>
            <a href="../bahan/index.php" class="dashboard-stat danger"><span>Stok Menipis</span><strong><?= (int) ($stock['low_stock'] ?? 0) ?></strong><small><?= (int) ($stock['out_stock'] ?? 0) ?> stok habis</small></a>
        <?php elseif ($is_kasir): ?>
            <a href="../pesanan/index.php?status=proses" class="dashboard-stat accent"><span>Antrean Pesanan</span><strong><?= (int) ($orders['waiting'] ?? 0) ?></strong><small>Menunggu diproses</small></a>
            <a href="../pesanan/index.php?status=proses" class="dashboard-stat"><span>Dalam Proses</span><strong><?= (int) ($orders['processing'] ?? 0) ?></strong><small>Pesanan aktif</small></a>
            <a href="../pesanan/index.php?status=Selesai" class="dashboard-stat"><span>Selesai Hari Ini</span><strong><?= (int) ($orders['completed'] ?? 0) ?></strong><small>Pesanan selesai</small></a>
            <a href="../transaksi/index.php" class="dashboard-stat"><span>Transaksi Hari Ini</span><strong><?= (int) ($today['transactions'] ?? 0) ?></strong><small><?= dashboardMoney($today['revenue'] ?? 0) ?></small></a>
        <?php else: ?>
            <a href="../pesanan/index.php" class="dashboard-stat accent"><span>Pesanan Menunggu</span><strong><?= (int) ($orders['waiting'] ?? 0) ?></strong><small>Perlu disiapkan</small></a>
            <a href="../pesanan/index.php" class="dashboard-stat"><span>Dalam Proses</span><strong><?= (int) ($orders['processing'] ?? 0) ?></strong><small>Sedang dikerjakan</small></a>
            <a href="../bahan/index.php" class="dashboard-stat danger"><span>Stok Menipis</span><strong><?= (int) ($stock['low_stock'] ?? 0) ?></strong><small><?= (int) ($stock['out_stock'] ?? 0) ?> stok habis</small></a>
            <a href="../bahan/pesan_supplier.php?tahap=dipesan" class="dashboard-stat"><span>Barang Masuk</span><strong><?= (int) ($requests['incoming'] ?? 0) ?></strong><small>Perlu diperiksa</small></a>
        <?php endif; ?>
    </section>

    <section class="dashboard-grid">
        <article class="dashboard-panel dashboard-attention">
            <div class="dashboard-panel-header"><div><span class="panel-kicker">Prioritas</span><h2>Perlu Ditangani</h2></div></div>
            <div class="attention-list">
                <?php if ($is_owner): ?>
                    <a href="../bahan/permintaan_stok.php?tab=menunggu"><span class="attention-icon">S</span><div><strong>Pengajuan Penyesuaian Stok</strong><small>Menunggu persetujuan Owner</small></div><b data-request-dashboard="stok"><?= (int) ($requests['stock_requests'] ?? 0) ?></b></a>
                    <a href="../menu/permintaan.php?tab=menunggu"><span class="attention-icon">M</span><div><strong>Pengajuan Menu</strong><small>Menunggu pemeriksaan</small></div><b data-request-dashboard="menu"><?= (int) ($requests['menu_requests'] ?? 0) ?></b></a>
                    <a href="../bahan/pesan_supplier.php?tahap=siap"><span class="attention-icon">B</span><div><strong>Pesan Bahan Baku</strong><small>Stok menipis yang belum dipesan</small></div><b><?= (int) ($requests['materials_to_order'] ?? 0) ?></b></a>
                <?php elseif ($is_kasir): ?>
                    <a href="../transaksi/index.php"><span class="attention-icon">+</span><div><strong>Transaksi Baru</strong><small>Buka halaman kasir</small></div><b>→</b></a>
                    <a href="../pesanan/index.php?status=proses&type=online"><span class="attention-icon">Q</span><div><strong>Pesanan Online</strong><small>Periksa pesanan QR terbaru</small></div><b>→</b></a>
                    <a href="../pesanan/qr.php"><span class="attention-icon">QR</span><div><strong>QR Menu</strong><small>Tampilkan kode pemesanan</small></div><b>→</b></a>
                <?php else: ?>
                    <a href="../pesanan/index.php"><span class="attention-icon">P</span><div><strong>Siapkan Pesanan</strong><small>Pesanan aktif saat ini</small></div><b><?= (int) ($orders['waiting'] ?? 0) + (int) ($orders['processing'] ?? 0) ?></b></a>
                    <a href="../bahan/pesan_supplier.php?tahap=dipesan"><span class="attention-icon">B</span><div><strong>Barang Masuk</strong><small>Cocokkan dengan pesanan supplier</small></div><b><?= (int) ($requests['incoming'] ?? 0) ?></b></a>
                    <a href="../bahan/permintaan_stok.php"><span class="attention-icon">S</span><div><strong>Penyesuaian Stok</strong><small>Ajukan stok rusak, basi, atau salah input</small></div><b>→</b></a>
                <?php endif; ?>
            </div>
        </article>

        <article class="dashboard-panel">
            <div class="dashboard-panel-header"><div><span class="panel-kicker"><?= $is_owner ? '30 Hari Terakhir' : 'Persediaan' ?></span><h2><?= $is_owner ? 'Menu Terlaris' : 'Stok Perlu Perhatian' ?></h2></div><a href="<?= $is_owner ? '../laporan/index.php?filter=30&view=menu' : '../bahan/index.php' ?>">Lihat semua</a></div>
            <div class="compact-list">
                <?php $side_rows = $is_owner ? $top_menus : $low_stocks; ?>
                <?php if ($side_rows): foreach ($side_rows as $index => $item): ?>
                    <div class="compact-row"><span class="rank"><?= $index + 1 ?></span><div><strong><?= htmlspecialchars($is_owner ? $item['nama_menu'] : $item['nama_bahan']) ?></strong><small><?= $is_owner ? (int) $item['sold'] . ' terjual' : dashboardStock($item['stok']) . ' ' . htmlspecialchars($item['satuan']) . ' · Minimum ' . dashboardStock($item['minimum_stok']) ?></small></div></div>
                <?php endforeach; else: ?>
                    <div class="dashboard-empty"><?= $is_owner ? 'Belum ada data penjualan.' : 'Semua stok dalam kondisi aman.' ?></div>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <section class="dashboard-panel dashboard-orders">
        <div class="dashboard-panel-header"><div><span class="panel-kicker"><?= $is_barista ? 'Antrean Produksi' : 'Aktivitas Terbaru' ?></span><h2><?= $is_barista ? 'Pesanan yang Disiapkan' : 'Pesanan Terbaru' ?></h2></div><a href="../pesanan/index.php">Lihat semua</a></div>
        <div class="recent-order-list">
            <?php $display_orders = $is_barista ? $active_orders : $recent_orders; ?>
            <?php if ($display_orders): foreach ($display_orders as $order): ?>
                <a href="../pesanan/detail.php?id=<?= (int) $order['id'] ?>" class="recent-order">
                    <div><strong><?= htmlspecialchars($order['kode_pesanan']) ?></strong><small><?= ($order['sumber_pesanan'] ?? '') === 'Kedai' ? 'Kedai' : htmlspecialchars($order['nama_pelanggan']) ?></small></div>
                    <span class="source-pill"><?= htmlspecialchars(($order['sumber_pesanan'] ?? '') === 'Kedai' ? 'Kedai' : 'Online') ?></span>
                    <span class="status-pill status-<?= strtolower($order['status']) ?>"><?= htmlspecialchars($order['status'] === 'Diproses' ? 'Dalam Proses' : $order['status']) ?></span>
                    <?php if (!$is_barista): ?><strong class="order-total"><?= dashboardMoney($order['subtotal']) ?></strong><?php endif; ?>
                    <time><?= date('H:i', strtotime($order['created_at'])) ?></time>
                </a>
            <?php endforeach; else: ?>
                <div class="dashboard-empty">Belum ada pesanan untuk ditampilkan.</div>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
