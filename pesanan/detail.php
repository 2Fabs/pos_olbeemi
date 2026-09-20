<?php
include '../config/database.php';
include '../includes/pesanan_tables.php';
include '../includes/pesanan_transaksi.php';

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

$is_barista_user = auth_has_role(['barista']);
if ($is_barista_user && !in_array($pesanan['status'], ['Menunggu', 'Diproses'], true)) {
    header('Location: index.php');
    exit;
}

$detail_query = mysqli_query($conn, "
    SELECT *
    FROM pesanan_detail
    WHERE pesanan_id = $pesanan_id
    ORDER BY id ASC
");

$linked_transaksi = null;

if (!empty($pesanan['transaksi_id'])) {
    $transaksi_id = (int) $pesanan['transaksi_id'];
    syncLegacyKodeTransaksi($conn, $transaksi_id);
    $transaksi_query = mysqli_query($conn, "
        SELECT id, kode_transaksi, metode_pembayaran, bayar, kembalian
        FROM transaksi
        WHERE id = $transaksi_id
    ");
    $linked_transaksi = mysqli_fetch_assoc($transaksi_query);
}

$status_error = trim($_GET['error'] ?? '');
$status_success = trim($_GET['success'] ?? '');
$metode_pembayaran_pesanan = $pesanan['metode_pembayaran'] ?? 'Cash';
$sumber_pesanan = $pesanan['sumber_pesanan'] ?? 'QR Menu';
$is_kedai_order = $sumber_pesanan === 'Kedai';
$has_custom_customer_name = trim((string) ($pesanan['nama_pelanggan'] ?? '')) !== ''
    && ($pesanan['nama_pelanggan'] ?? '') !== 'Pelanggan Kedai';
$bayar_pesanan = (float) ($pesanan['bayar'] ?? 0);
$kembalian_pesanan = (float) ($pesanan['kembalian'] ?? 0);
$can_process_order = auth_has_role(['owner', 'barista']);
$can_finalize_order = auth_has_role(['owner', 'kasir']);

if (!in_array($metode_pembayaran_pesanan, ['Cash', 'QRIS'], true)) {
    $metode_pembayaran_pesanan = 'Cash';
}

function formatRupiahPesananDetail($number) {
    return 'Rp' . number_format($number, 0, ',', '.');
}

function formatStatusPesananDetail($status) {
    return $status === 'Diproses' ? 'Dalam Proses' : $status;
}

function formatSumberPesananDetail($source) {
    return $source === 'Kedai'
        ? 'Kedai'
        : 'QR';
}

function formatJenisPesananDetail($source) {
    return $source === 'Kedai'
        ? 'Langsung di Kedai'
        : 'Pemesanan online';
}

$status_input_label = 'Metode Pembayaran';
$status_panel_class = 'is-process';

if (($pesanan['status'] ?? '') === 'Selesai') {
    $status_input_label = 'Pesanan Selesai';
    $status_panel_class = 'is-complete';
} elseif (($pesanan['status'] ?? '') === 'Dibatalkan') {
    $status_input_label = 'Pesanan Dibatalkan';
    $status_panel_class = 'is-cancelled';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/pemesanan.css?v=<?= filemtime(__DIR__ . '/../css/pemesanan.css') ?>">
</head>
<body>

<?php
$current_module = 'pesanan';
include '../includes/sidebar.php';
?>

<div class="main-content order-detail-page">
    <div class="header">
        <div>
            <h1>Detail Pesanan</h1>
            <p><?= htmlspecialchars($pesanan['kode_pesanan']) ?></p>
        </div>

        <a href="index.php" class="btn-stock">
            Kembali
        </a>
    </div>

    <?php if ($status_error !== ''): ?>
        <div class="order-alert error">
            <?= htmlspecialchars($status_error) ?>
        </div>
    <?php elseif ($status_success === 'selesai'): ?>
        <div class="order-alert success">
            Pesanan selesai, sudah masuk laporan penjualan, dan stok bahan baku sudah terpotong.
        </div>
    <?php elseif ($status_success === 'masuk'): ?>
        <div class="order-alert success">
            Pesanan dari kedai sudah masuk. Klik Selesai untuk memasukkan ke laporan penjualan dan memotong stok.
        </div>
    <?php endif; ?>

    <div class="order-detail-grid">
        <div class="order-detail-card combined-detail-card">
            <section class="order-detail-section">
            <h2>Informasi Pesanan</h2>

            <div class="info-panel">
                <?php if (!$is_kedai_order || $has_custom_customer_name): ?>
                <div class="info-row">
                    <span>Pelanggan</span>
                    <strong><?= htmlspecialchars($pesanan['nama_pelanggan']) ?></strong>
                </div>
                <?php endif; ?>

                <?php if (!$is_barista_user && ($pesanan['nomor_wa'] ?? '') !== ''): ?>
                <div class="info-row">
                    <span>Nomor WA</span>
                    <strong><?= htmlspecialchars($pesanan['nomor_wa']) ?></strong>
                </div>
                <?php endif; ?>

                <?php if (!$is_kedai_order): ?>
                <div class="info-row">
                    <span>Sumber Pesanan</span>
                    <strong><?= htmlspecialchars(formatSumberPesananDetail($sumber_pesanan)) ?></strong>
                </div>
                <?php endif; ?>

                <div class="info-row">
                    <span>Metode Pembayaran</span>
                    <strong><?= htmlspecialchars($metode_pembayaran_pesanan) ?></strong>
                </div>

                <?php if (
                    $pesanan['catatan'] !== ''
                    && $pesanan['catatan'] !== 'Pesanan dibuat langsung oleh kasir di kedai.'
                ): ?>
                <div class="info-row">
                    <span>Catatan</span>
                    <strong><?= htmlspecialchars($pesanan['catatan']) ?></strong>
                </div>
                <?php endif; ?>

                <div class="info-row">
                    <span>Subtotal</span>
                    <strong><?= formatRupiahPesananDetail($pesanan['subtotal']) ?></strong>
                </div>

                <?php if ($bayar_pesanan > 0): ?>
                    <div class="info-row">
                        <span>Bayar</span>
                        <strong><?= formatRupiahPesananDetail($bayar_pesanan) ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Kembalian</span>
                        <strong><?= formatRupiahPesananDetail($kembalian_pesanan) ?></strong>
                    </div>
                <?php endif; ?>

                <?php if ($linked_transaksi): ?>
                    <div class="info-row">
                        <span>Transaksi</span>
                        <strong>
                            <?php if (auth_has_role(['owner'])): ?>
                                <a href="../laporan/detail.php?id=<?= (int) $linked_transaksi['id'] ?>">
                                    <?= htmlspecialchars($linked_transaksi['kode_transaksi']) ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($linked_transaksi['kode_transaksi']) ?>
                            <?php endif; ?>
                        </strong>
                    </div>
                <?php endif; ?>
            </div>
            </section>

            <section class="order-detail-section order-status-section">
            <h2>Status Pesanan</h2>

            <?php if ($linked_transaksi): ?>
                <div class="status-panel <?= htmlspecialchars($status_panel_class) ?>">
                    <div class="status-panel-header">
                        <span><?= htmlspecialchars(formatJenisPesananDetail($sumber_pesanan)) ?></span>
                        <strong><?= htmlspecialchars(formatStatusPesananDetail($pesanan['status'])) ?></strong>
                    </div>
                    <div class="status-panel-reference">
                        <span>Kode Transaksi</span>
                        <strong><?= htmlspecialchars($linked_transaksi['kode_transaksi']) ?></strong>
                    </div>
                    <div class="linked-transaction-actions">
                        <a href="../transaksi/struk.php?id=<?= (int) $linked_transaksi['id'] ?>" class="btn-primary" target="_blank" rel="noopener">
                            Cetak Nota
                        </a>

                        <?php if (auth_has_role(['owner'])): ?>
                            <a href="../laporan/detail.php?id=<?= (int) $linked_transaksi['id'] ?>" class="btn-stock">
                                Lihat Laporan
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="status-panel <?= htmlspecialchars($status_panel_class) ?>">
                    <div class="status-panel-header">
                        <span><?= htmlspecialchars(formatJenisPesananDetail($sumber_pesanan)) ?></span>
                        <strong><?= htmlspecialchars(formatStatusPesananDetail($pesanan['status'])) ?></strong>
                    </div>

                    <?php if (($pesanan['status'] ?? '') === 'Dibatalkan'): ?>
                        <div class="status-panel-message">
                            Pesanan ini dibatalkan. Tidak ada tindakan lebih lanjut yang diperlukan.
                        </div>
                    <?php else: ?>
                        <form
                            action="update_status.php"
                            method="POST"
                            class="status-actions"
                            onsubmit="return validateOrderStatus(event)"
                        >
                            <?= auth_csrf_input() ?>
                            <input type="hidden" name="id" value="<?= (int) $pesanan['id'] ?>">

                            <?php if ($can_finalize_order && $is_kedai_order): ?>
                                <input type="hidden" name="metode_pembayaran" value="<?= htmlspecialchars($metode_pembayaran_pesanan) ?>">
                                <input type="hidden" name="bayar" value="<?= htmlspecialchars((string) $bayar_pesanan) ?>">
                            <?php elseif ($can_finalize_order): ?>
                                <div class="status-field-block">
                                    <div class="status-field-row">
                                        <label class="status-field-label" for="statusPaymentMethod">
                                            <?= htmlspecialchars($status_input_label) ?>
                                        </label>
                                        <select
                                            name="metode_pembayaran"
                                            id="statusPaymentMethod"
                                            onchange="toggleOrderCashSection()"
                                        >
                                            <option value="Cash" <?= $metode_pembayaran_pesanan === 'Cash' ? 'selected' : '' ?>>Cash</option>
                                            <option value="QRIS" <?= $metode_pembayaran_pesanan === 'QRIS' ? 'selected' : '' ?>>QRIS</option>
                                        </select>
                                    </div>
                                </div>

                                <div id="orderCashSection" class="status-cash-section">
                                    <div class="status-field-block">
                                        <div class="status-field-row">
                                            <label class="status-field-label" for="orderBayarInput">
                                                Jumlah Bayar
                                            </label>
                                            <input
                                                type="text"
                                                name="bayar"
                                                id="orderBayarInput"
                                                inputmode="numeric"
                                                autocomplete="off"
                                                placeholder="Contoh: 150.000"
                                                oninput="formatOrderBayarInput(this)"
                                            >
                                        </div>
                                    </div>

                                    <div class="status-change-row">
                                        <span>Kembalian</span>
                                        <strong id="orderKembalianText">Rp0</strong>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($can_process_order && ($pesanan['status'] ?? '') === 'Menunggu'): ?>
                                <button type="submit" name="status" value="Diproses" class="btn-primary">
                                    Mulai Proses
                                </button>
                            <?php endif; ?>

                            <?php if ($can_finalize_order): ?>
                                <button type="submit" name="status" value="Selesai" class="btn-stock">
                                    Selesai
                                </button>

                                <button type="submit" name="status" value="Dibatalkan" class="btn-delete">
                                    Batalkan
                                </button>
                            <?php elseif (($pesanan['status'] ?? '') === 'Diproses'): ?>
                                <div class="status-panel-message">
                                    Pesanan sedang diproses dan dapat diserahkan langsung kepada kasir.
                                </div>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            </section>
        </div>
    </div>

    <div class="table-card order-items-card">
        <table class="order-items-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Menu</th>
                    <th>Harga</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; ?>
                <?php while ($detail = mysqli_fetch_assoc($detail_query)): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($detail['nama_menu']) ?></td>
                        <td class="money-cell"><?= formatRupiahPesananDetail($detail['harga_satuan']) ?></td>
                        <td><?= (int) $detail['qty'] ?></td>
                        <td class="money-cell"><?= formatRupiahPesananDetail($detail['subtotal']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
const orderSubtotal = <?= (float) $pesanan['subtotal'] ?>;

function formatOrderRupiah(number) {
    return 'Rp' + Number(number).toLocaleString('id-ID');
}

function parseOrderRupiah(value) {
    return Number(String(value).replace(/\D/g, '') || 0);
}

function formatOrderBayarInput(input) {
    const rawValue = String(input.value).replace(/\D/g, '');

    input.value = rawValue
        ? Number(rawValue).toLocaleString('id-ID')
        : '';

    calculateOrderChange();
}

function calculateOrderChange() {
    const bayarInput = document.getElementById('orderBayarInput');
    const kembalianText = document.getElementById('orderKembalianText');

    if (!bayarInput || !kembalianText) {
        return;
    }

    const bayar = parseOrderRupiah(bayarInput.value);
    const kembalian = Math.max(bayar - orderSubtotal, 0);

    kembalianText.textContent = formatOrderRupiah(kembalian);
}

function toggleOrderCashSection() {
    const method = document.getElementById('statusPaymentMethod');
    const cashSection = document.getElementById('orderCashSection');

    if (!method || !cashSection) {
        return;
    }

    cashSection.style.display = method.value === 'Cash' ? 'grid' : 'none';
    calculateOrderChange();
}

function validateOrderStatus(event) {
    const submitter = event.submitter;

    if (!submitter || submitter.value !== 'Selesai') {
        return true;
    }

    const methodInput = document.getElementById('statusPaymentMethod');

    if (!methodInput) {
        return true;
    }

    const method = methodInput.value;

    if (method !== 'Cash') {
        return true;
    }

    const bayar = parseOrderRupiah(document.getElementById('orderBayarInput').value);

    if (bayar <= 0) {
        alert('Jumlah bayar wajib diisi.');
        return false;
    }

    if (bayar < orderSubtotal) {
        alert('Uang bayar kurang.');
        return false;
    }

    return true;
}

toggleOrderCashSection();
</script>
</body>
</html>
