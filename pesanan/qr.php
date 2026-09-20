<?php
include '../config/database.php';

$order_link = 'https://olbeemi.online/pemesanan/';
$qr_src = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&format=png&data=' . rawurlencode($order_link);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Menu</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/pemesanan.css?v=<?= filemtime(__DIR__ . '/../css/pemesanan.css') ?>">
</head>
<body>

<?php
$current_module = 'qr';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header">
        <div>
            <h1>QR Menu</h1>
        </div>

        <a href="index.php" class="btn-stock">
            Pesanan
        </a>
    </div>

    <div class="qr-layout">
        <div class="qr-hero">
            <div class="qr-hero-copy">
                <span class="qr-chip">Scan & Order</span>
                <h2>QR Menu siap dipakai</h2>
                <p>Gunakan kode QR ini agar pelanggan bisa langsung membuka menu pemesanan dari ponsel mereka.</p>
            </div>

            <div class="qr-card">
                <div class="qr-card-head">
                    <span class="qr-chip">QR Menu</span>
                </div>

                <div class="qr-image-wrap">
                    <img src="<?= htmlspecialchars($qr_src) ?>" alt="QR Menu">
                </div>

                <div class="qr-actions">
                    <button type="button" class="btn-primary" id="downloadQrBtn" data-download-src="<?= htmlspecialchars($qr_src) ?>">
                        Unduh PNG
                    </button>
                    <a href="<?= htmlspecialchars($order_link) ?>" class="btn-stock" target="_blank">
                        Buka Menu
                    </a>
                </div>

                <?php if (function_exists('auth_is_logged_in') && auth_is_logged_in()): ?>
                    <div class="qr-logout">
                        <a href="../logout.php" class="btn-stock">
                            Logout
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('downloadQrBtn');
        if (!btn) return;

        btn.addEventListener('click', function() {
            const src = this.dataset.downloadSrc;
            if (!src) return;

            fetch(src)
                .then(response => response.blob())
                .then(blob => {
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'qr-menu.png';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(url);
                })
                .catch(() => {
                    window.location.href = src;
                });
        });
    });
</script>
</body>
</html>
