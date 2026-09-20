<?php
include '../config/database.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$transaksi_id = (int) $_GET['id'];

if ($transaksi_id <= 0) {
    header("Location: index.php");
    exit;
}

$transaksi_query = mysqli_query($conn, "
    SELECT *
    FROM transaksi
    WHERE id = $transaksi_id
");

$transaksi = mysqli_fetch_assoc($transaksi_query);

if (!$transaksi) {
    header("Location: index.php");
    exit;
}

$detail_query = mysqli_query($conn, "
    SELECT *
    FROM transaksi_detail
    WHERE transaksi_id = $transaksi_id
    ORDER BY id ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Transaksi</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/pos.css">
</head>
<body>

<?php
$current_module = 'laporan';
include '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="header">
        <div>
            <h1>Detail Transaksi</h1>
            <p><?= htmlspecialchars($transaksi['kode_transaksi']) ?></p>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <h3>Tanggal</h3>
            <strong><?= date('d/m/Y H:i', strtotime($transaksi['created_at'])) ?></strong>
        </div>

        <div class="summary-card">
            <h3>Metode</h3>
            <strong><?= htmlspecialchars($transaksi['metode_pembayaran']) ?></strong>
        </div>

        <div class="summary-card">
            <h3>Subtotal</h3>
            <strong>Rp<?= number_format($transaksi['subtotal'], 0, ',', '.') ?></strong>
        </div>

        <div class="summary-card">
            <h3>Bayar</h3>
            <strong>Rp<?= number_format($transaksi['bayar'], 0, ',', '.') ?></strong>
        </div>

        <div class="summary-card">
            <h3>Kembalian</h3>
            <strong>Rp<?= number_format($transaksi['kembalian'], 0, ',', '.') ?></strong>
        </div>

        <div class="summary-card">
            <h3>Keuntungan</h3>
            <strong>Rp<?= number_format($transaksi['total_profit'], 0, ',', '.') ?></strong>
        </div>
    </div>

    <div class="table-card">
        <h2 style="margin-bottom:20px;">Item Transaksi</h2>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Menu</th>
                    <th>Harga Satuan</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                    <th>Keuntungan</th>
                </tr>
            </thead>

            <tbody>
                <?php if (mysqli_num_rows($detail_query) > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($item = mysqli_fetch_assoc($detail_query)): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td><?= htmlspecialchars($item['nama_menu']) ?></td>

                            <td class="money-cell">
                                Rp<?= number_format($item['harga_satuan'], 0, ',', '.') ?>
                            </td>

                            <td><?= $item['qty'] ?></td>

                            <td class="money-cell">
                                Rp<?= number_format($item['subtotal'], 0, ',', '.') ?>
                            </td>

                            <td class="money-cell">
                                Rp<?= number_format($item['keuntungan_satuan'] * $item['qty'], 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">Tidak ada detail transaksi.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top:20px;">
            <a href="index.php" class="btn-stock">
                ← Kembali ke Laporan
            </a>
        </div>
    </div>

</div>

</body>
</html>
