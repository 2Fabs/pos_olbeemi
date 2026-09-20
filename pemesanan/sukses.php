<?php
include '../config/database.php';
include '../includes/pesanan_tables.php';
include 'config.php';

ensurePesananTables($conn);

$pesanan_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($pesanan_id <= 0) {
    die('Pesanan tidak ditemukan.');
}

$pesanan_query = mysqli_query($conn, "
    SELECT *
    FROM pesanan
    WHERE id = $pesanan_id
");
$pesanan = mysqli_fetch_assoc($pesanan_query);

if (!$pesanan) {
    die('Pesanan tidak ditemukan.');
}

$detail_query = mysqli_query($conn, "
    SELECT *
    FROM pesanan_detail
    WHERE pesanan_id = $pesanan_id
    ORDER BY id ASC
");

$details = [];
while ($row = mysqli_fetch_assoc($detail_query)) {
    $details[] = $row;
}

function formatRupiahOrder($number) {
    return 'Rp' . number_format($number, 0, ',', '.');
}

$message_lines = [
    'Halo Olbeemi, saya sudah membuat pesanan.',
    '',
    'Kode: ' . $pesanan['kode_pesanan'],
    'Nama: ' . $pesanan['nama_pelanggan'],
    'Jenis Pesanan: ' . (($pesanan['sumber_pesanan'] ?? 'QR Menu') === 'Kedai' ? 'Kedai' : 'Pemesanan online'),
    'Metode Pembayaran: ' . ($pesanan['metode_pembayaran'] ?? 'Cash'),
];

$message_lines[] = '';
$message_lines[] = 'Detail Pesanan:';

foreach ($details as $idx => $detail) {
    $message_lines[] =
        ($idx + 1) . '. ' .
        $detail['nama_menu'] .
        ' x' . (int) $detail['qty'] .
        ' - ' . formatRupiahOrder($detail['subtotal']);
}

$message_lines[] = '';
$message_lines[] = 'Subtotal: ' . formatRupiahOrder($pesanan['subtotal']);

if ($pesanan['catatan'] !== '') {
    $message_lines[] = 'Catatan: ' . $pesanan['catatan'];
}

$wa_text = rawurlencode(implode("\n", $message_lines));
$wa_target = preg_replace('/\D/', '', $store_whatsapp_number ?? '');
$wa_link = $wa_target
    ? "https://wa.me/$wa_target?text=$wa_text"
    : "https://wa.me/?text=$wa_text";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Terkirim</title>
    <link rel="stylesheet" href="../css/pemesanan.css">
</head>
<body class="public-order-body">
    <main class="success-page">
        <section class="success-card">
            <span class="success-mark">✓</span>
            <h1>Pesanan Terkirim</h1>
            <p>Berikan kode ini ke kasir jika diperlukan.</p>

            <div class="order-code">
                <?= htmlspecialchars($pesanan['kode_pesanan']) ?>
            </div>

            <div class="success-summary">
                <div>
                    <span>Nama</span>
                    <strong><?= htmlspecialchars($pesanan['nama_pelanggan']) ?></strong>
                </div>

                <div>
                    <span>Subtotal</span>
                    <strong><?= formatRupiahOrder($pesanan['subtotal']) ?></strong>
                </div>

                <div>
                    <span>Jenis Pesanan</span>
                    <strong><?= htmlspecialchars(($pesanan['sumber_pesanan'] ?? 'QR Menu') === 'Kedai' ? 'Kedai' : 'Pemesanan online') ?></strong>
                </div>

                <div>
                    <span>Metode Pembayaran</span>
                    <strong><?= htmlspecialchars($pesanan['metode_pembayaran'] ?? 'Cash') ?></strong>
                </div>
            </div>

            <div class="success-items">
                <?php foreach ($details as $detail): ?>
                    <div>
                        <span>
                            <?= htmlspecialchars($detail['nama_menu']) ?>
                            x<?= (int) $detail['qty'] ?>
                        </span>
                        <strong><?= formatRupiahOrder($detail['subtotal']) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="success-actions">
                <a href="<?= htmlspecialchars($wa_link) ?>" class="btn-wa" target="_blank" rel="noopener">
                    Kirim ke WhatsApp
                </a>
                <a href="index.php" class="btn-order-back">
                    Pesan Lagi
                </a>
            </div>
        </section>
    </main>
</body>
</html>
