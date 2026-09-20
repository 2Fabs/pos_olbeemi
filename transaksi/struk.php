<?php
include '../config/database.php';
include '../includes/pesanan_transaksi.php';

$transaksi_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($transaksi_id <= 0) {
    die('Struk tidak ditemukan.');
}

syncLegacyKodeTransaksi($conn, $transaksi_id);

$transaksi_query = mysqli_query($conn, "
    SELECT *
    FROM transaksi
    WHERE id = $transaksi_id
");
$transaksi = mysqli_fetch_assoc($transaksi_query);

if (!$transaksi) {
    die('Struk tidak ditemukan.');
}

$pesanan_query = mysqli_query($conn, "
    SELECT nama_pelanggan, nomor_wa, sumber_pesanan, catatan
    FROM pesanan
    WHERE transaksi_id = $transaksi_id
    LIMIT 1
");
$pesanan = $pesanan_query ? mysqli_fetch_assoc($pesanan_query) : null;

$details_query = mysqli_query($conn, "
    SELECT *
    FROM transaksi_detail
    WHERE transaksi_id = $transaksi_id
    ORDER BY id ASC
");

$details = [];
$total_qty = 0;

while ($row = mysqli_fetch_assoc($details_query)) {
    $details[] = $row;
    $total_qty += (int) $row['qty'];
}

function formatNominal($number) {
    return number_format($number, 0, ',', '.');
}

function formatTanggalStruk($date) {
    return date('d/m/Y H:i', strtotime($date ?? date('Y-m-d H:i:s')));
}

function formatNomorWhatsAppStruk($number) {
    $digits = preg_replace('/\D/', '', (string) $number);

    if ($digits === '') {
        return '';
    }

    if (strpos($digits, '0') === 0) {
        return '62' . substr($digits, 1);
    }

    if (strpos($digits, '8') === 0) {
        return '62' . $digits;
    }

    return $digits;
}

$nomor_wa = formatNomorWhatsAppStruk($pesanan['nomor_wa'] ?? '');
$is_online_order = $pesanan && ($pesanan['sumber_pesanan'] ?? '') !== 'Kedai';
$has_customer_name = $pesanan
    && trim((string) ($pesanan['nama_pelanggan'] ?? '')) !== ''
    && ($pesanan['nama_pelanggan'] ?? '') !== 'Pelanggan Kedai';
$has_whatsapp_number = $nomor_wa !== '';
$can_send_whatsapp = $pesanan
    && preg_match('/^62\d{8,13}$/', $nomor_wa) === 1;
$nama_pelanggan_wa = trim((string) ($pesanan['nama_pelanggan'] ?? '')) ?: 'Pelanggan';
$catatan_pesanan = trim((string) ($pesanan['catatan'] ?? ''));
if ($catatan_pesanan === 'Pesanan dibuat langsung oleh kasir di kedai.') {
    $catatan_pesanan = '';
}
$whatsapp_message = "Hai $nama_pelanggan_wa,\n"
    . "Berikut nota pesanan Anda.\n"
    . "Terima kasih telah memesan di Olbeemi.";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Transaksi</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap');

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 22px;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.7), transparent 28%),
                linear-gradient(135deg, #f1e7dc, #ddc7b1);
            color: #111;
            font-family: "Plus Jakarta Sans", sans-serif;
        }

        .receipt-wrap {
            display: grid;
            justify-content: center;
            gap: 16px;
        }

        .receipt {
            width: 80mm;
            padding: 6mm 5mm;
            background: #fff;
            color: #111;
            border: 1px solid rgba(95, 59, 40, 0.1);
            border-radius: 8px;
            box-shadow: 0 16px 40px rgba(60, 36, 22, 0.16);
            font-family: "Courier New", Courier, monospace;
            font-size: 11px;
            line-height: 1.35;
        }

        .store-header {
            text-align: center;
        }

        .store-header h1 {
            margin: 0 0 3px;
            color: #111;
            font-family: inherit;
            font-size: 17px;
            letter-spacing: 0;
        }

        .store-header p {
            margin: 2px 0;
        }

        .dash {
            margin: 9px 0;
            border-top: 1px dashed #111;
        }

        .receipt-row,
        .item-line {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .receipt-row span:first-child,
        .item-line span {
            flex: 1;
        }

        .receipt-row strong,
        .item-line strong {
            color: #111;
            font-family: inherit;
            font-size: inherit;
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }

        .receipt-note {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px dotted #777;
        }

        .receipt-note span {
            display: block;
            margin-bottom: 3px;
            font-weight: 700;
        }

        .receipt-note strong {
            display: block;
            font-family: inherit;
            font-size: inherit;
            font-weight: 400;
            line-height: 1.4;
            text-align: left;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .item {
            margin-bottom: 8px;
        }

        .item-name {
            margin-bottom: 2px;
            font-weight: 700;
            word-break: break-word;
        }

        .summary .receipt-row {
            margin: 3px 0;
        }

        .summary .grand-total {
            margin-top: 6px;
            font-size: 13px;
            font-weight: 700;
        }

        .footer {
            text-align: center;
        }

        .footer p {
            margin: 3px 0;
        }

        .receipt-actions {
            width: 80mm;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 10px 12px;
            border: none;
            border: 1px solid transparent;
            border-radius: 11px;
            cursor: pointer;
            font-size: 13px;
            font-family: inherit;
            font-weight: 700;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-print {
            background: linear-gradient(135deg, #8a5a3b, #5f3b28);
            color: #fff;
            box-shadow: 0 8px 16px rgba(95, 59, 40, 0.18);
        }

        .btn-download {
            background: #ead8c6;
            color: #4d3020;
            border-color: rgba(95, 59, 40, 0.12);
        }

        .btn-whatsapp {
            grid-column: 1 / -1;
            justify-self: center;
            width: calc((100% - 10px) / 2);
            background: #477456;
            color: #fff;
            box-shadow: 0 8px 16px rgba(71, 116, 86, 0.2);
        }

        .btn:disabled {
            cursor: not-allowed;
            opacity: 0.48;
            transform: none;
            box-shadow: none;
        }

        .receipt.is-exporting {
            width: 80mm;
            padding: 4mm;
            border: none;
            border-radius: 0;
            box-shadow: none;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn:focus-visible {
            outline: 3px solid rgba(138, 90, 59, 0.26);
            outline-offset: 2px;
        }

        @page {
            size: 80mm auto;
            margin: 0;
        }

        @media print {
            body {
                min-height: auto;
                padding: 0;
                background: #fff;
            }

            .receipt-wrap {
                display: block;
            }

            .receipt {
                width: 80mm;
                margin: 0;
                padding: 4mm;
                box-shadow: none;
                border: none;
                border-radius: 0;
            }

            .receipt-actions {
                display: none;
            }
        }

        @media screen and (max-width: 480px) {
            body {
                padding: 12px;
            }

            .receipt,
            .receipt-actions {
                width: 100%;
            }

            .receipt {
                padding: 16px;
            }

            .receipt-actions {
                grid-template-columns: 1fr;
            }

            .btn-whatsapp {
                grid-column: auto;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="receipt-wrap">
        <section class="receipt">
            <header class="store-header">
                <h1>POS OLBEEMI</h1>
                <p>COFFEE SHOP</p>
                <p>Nota Penjualan</p>
            </header>

            <div class="dash"></div>

            <div class="receipt-row">
                <span>Kode</span>
                <strong><?= htmlspecialchars($transaksi['kode_transaksi']) ?></strong>
            </div>
            <?php if ($is_online_order || $has_customer_name): ?>
                <div class="receipt-row">
                    <span>Pelanggan</span>
                    <strong><?= htmlspecialchars($nama_pelanggan_wa) ?></strong>
                </div>
            <?php endif; ?>
            <div class="receipt-row">
                <span>Tanggal</span>
                <strong><?= formatTanggalStruk($transaksi['created_at'] ?? null) ?></strong>
            </div>
            <div class="receipt-row">
                <span>Metode</span>
                <strong><?= htmlspecialchars($transaksi['metode_pembayaran']) ?></strong>
            </div>
            <?php if ($catatan_pesanan !== ''): ?>
                <div class="receipt-note">
                    <span>Catatan</span>
                    <strong><?= nl2br(htmlspecialchars($catatan_pesanan)) ?></strong>
                </div>
            <?php endif; ?>

            <div class="dash"></div>

            <?php foreach ($details as $detail): ?>
                <div class="item">
                    <div class="item-name">
                        <?= htmlspecialchars($detail['nama_menu']) ?>
                    </div>
                    <div class="item-line">
                        <span>
                            <?= (int) $detail['qty'] ?> x <?= formatNominal($detail['harga_satuan']) ?>
                        </span>
                        <strong><?= formatNominal($detail['subtotal']) ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="dash"></div>

            <div class="summary">
                <div class="receipt-row">
                    <span>Total Item</span>
                    <strong><?= $total_qty ?></strong>
                </div>
                <div class="receipt-row grand-total">
                    <span>TOTAL</span>
                    <strong>Rp<?= formatNominal($transaksi['subtotal']) ?></strong>
                </div>
                <div class="receipt-row">
                    <span>Bayar</span>
                    <strong>Rp<?= formatNominal($transaksi['bayar']) ?></strong>
                </div>
                <div class="receipt-row">
                    <span>Kembali</span>
                    <strong>Rp<?= formatNominal($transaksi['kembalian']) ?></strong>
                </div>
            </div>

            <div class="dash"></div>

            <footer class="footer">
                <p>Terima kasih</p>
                <p>Silakan datang kembali</p>
            </footer>
        </section>

        <div class="receipt-actions">
            <button class="btn btn-print" onclick="window.print()">
                Cetak Nota
            </button>
            <button type="button" class="btn btn-download" id="downloadReceiptBtn" onclick="downloadReceiptPng()">
                Unduh PNG
            </button>
            <?php if ($can_send_whatsapp): ?>
                <button type="button" class="btn btn-whatsapp" id="whatsappReceiptBtn" onclick="openReceiptWhatsApp()" disabled>
                    Kirim ke WhatsApp
                </button>
            <?php elseif ($has_whatsapp_number): ?>
                <button type="button" class="btn btn-whatsapp" disabled title="Nomor WhatsApp pelanggan belum valid">
                    Nomor WA Tidak Valid
                </button>
            <?php endif; ?>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script>
        const receiptFileName = <?= json_encode('nota-' . $transaksi['kode_transaksi'] . '.png') ?>;
        const receiptWhatsAppUrl = <?= json_encode(
            $can_send_whatsapp
                ? 'https://wa.me/' . $nomor_wa . '?text=' . rawurlencode($whatsapp_message)
                : ''
        ) ?>;

        async function downloadReceiptPng() {
            const receipt = document.querySelector('.receipt');
            const downloadButton = document.getElementById('downloadReceiptBtn');
            const whatsappButton = document.getElementById('whatsappReceiptBtn');

            if (typeof html2canvas !== 'function') {
                alert('Pembuat PNG belum termuat. Periksa koneksi internet lalu muat ulang halaman.');
                return;
            }

            downloadButton.disabled = true;
            downloadButton.textContent = 'Membuat PNG...';
            receipt.classList.add('is-exporting');

            try {
                const targetWidth = 640;
                const scale = targetWidth / receipt.offsetWidth;
                const canvas = await html2canvas(receipt, {
                    backgroundColor: '#ffffff',
                    scale: scale,
                    useCORS: true,
                    logging: false,
                });
                const link = document.createElement('a');

                link.download = receiptFileName;
                link.href = canvas.toDataURL('image/png');
                link.click();

                downloadButton.textContent = 'PNG Terunduh';
                if (whatsappButton) {
                    whatsappButton.disabled = false;
                }
            } catch (error) {
                downloadButton.textContent = 'Unduh PNG';
                alert('Nota PNG gagal dibuat. Silakan coba kembali.');
            } finally {
                receipt.classList.remove('is-exporting');
                downloadButton.disabled = false;
            }
        }

        function openReceiptWhatsApp() {
            if (receiptWhatsAppUrl) {
                window.open(receiptWhatsAppUrl, '_blank', 'noopener');
            }
        }
    </script>
</body>
</html>
