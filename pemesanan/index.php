<?php
include '../config/database.php';
include '../includes/pesanan_tables.php';
include '../includes/menu_stock.php';

ensurePesananTables($conn);

$jenis_pesanan = 'Pemesanan online';

$menu_query = mysqli_query($conn, "
    SELECT menu.*, " . menuStockSelect('menu') . "
    FROM menu
    WHERE status = 'Aktif'
      AND harga_jual > 0
      AND EXISTS (SELECT 1 FROM menu_resep WHERE menu_resep.menu_id = menu.id)
    ORDER BY kategori ASC, nama_menu ASC
");

$kategori_query = mysqli_query($conn, "
    SELECT DISTINCT kategori
    FROM menu
    WHERE status = 'Aktif'
    ORDER BY kategori ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Menu Olbeemi</title>
    <link rel="stylesheet" href="../css/pemesanan.css?v=<?= filemtime(__DIR__ . '/../css/pemesanan.css') ?>">
</head>
<body class="public-order-body has-mobile-cart-hint">
    <main class="order-page">
        <section class="order-layout">
            <div class="order-main">
                <header class="order-header">
                    <div class="order-brand">
                        <img src="../images/logo.png" alt="Olbeemi" class="order-logo">
                    </div>

                    <span class="table-badge"><?= htmlspecialchars($jenis_pesanan) ?></span>
                </header>

                <section class="order-toolbar">
                    <input type="text" id="searchInput" placeholder="Cari menu...">

                    <select id="filterKategori">
                        <option value="all">Semua Kategori</option>
                        <?php while ($kategori = mysqli_fetch_assoc($kategori_query)): ?>
                            <option value="<?= htmlspecialchars($kategori['kategori']) ?>">
                                <?= htmlspecialchars($kategori['kategori']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </section>

                <div class="menu-scroll-box">
                    <div class="menu-grid" id="menuGrid">
                        <?php while ($menu = mysqli_fetch_assoc($menu_query)): ?>
                            <?php $stock_info = menuSalesStockInfo($menu); ?>
                            <article
                                class="menu-card menu-stock-<?= $stock_info['status'] ?>"
                                data-kategori="<?= htmlspecialchars($menu['kategori']) ?>"
                                data-nama="<?= htmlspecialchars(strtolower($menu['nama_menu'])) ?>">
                                <?php if (
                                    $menu['foto'] &&
                                    file_exists(__DIR__ . '/../menu/uploads/' . $menu['foto'])
                                ): ?>
                                    <img
                                        src="../menu/uploads/<?= htmlspecialchars($menu['foto']) ?>"
                                        alt="<?= htmlspecialchars($menu['nama_menu']) ?>">
                                <?php else: ?>
                                    <div class="menu-placeholder">No Image</div>
                                <?php endif; ?>

                                <div class="menu-info">
                                    <h2><?= htmlspecialchars($menu['nama_menu']) ?></h2>
                                    <span><?= htmlspecialchars($menu['kategori']) ?></span>
                                    <strong>Rp<?= number_format($menu['harga_jual'], 0, ',', '.') ?></strong>
                                    <span class="menu-stock-label menu-stock-label-<?= $stock_info['status'] ?>">
                                        <?= htmlspecialchars($stock_info['label']) ?>
                                    </span>

                                    <button
                                        type="button"
                                        class="btn-add-cart"
                                        title="Tambahkan ke keranjang"
                                        aria-label="Tambahkan ke keranjang"
                                        onclick='addToCart(
                                            <?= (int) $menu['id'] ?>,
                                            <?= json_encode($menu['nama_menu']) ?>,
                                            <?= (float) $menu['harga_jual'] ?>,
                                            <?= $stock_info['capacity'] === null ? 'null' : $stock_info['capacity'] ?>
                                        )'
                                        <?= $stock_info['status'] === 'habis' ? 'disabled' : '' ?>
                                    >
                                        +
                                    </button>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <aside class="order-cart" id="orderCart">
                <div class="order-cart-heading">
                    <h2>Daftar Pesanan</h2>
                    <button type="button" class="mobile-cart-close" id="closeMobileCartBtn" aria-label="Tutup daftar pesanan">×</button>
                </div>

                <div id="cartItems" class="cart-items">
                    <div class="empty-cart">Belum ada menu dipilih</div>
                </div>

                <form id="orderForm" action="simpan.php" method="POST" onsubmit="return prepareOrder()">
                    <?= auth_csrf_input() ?>
                    <input type="hidden" name="cart_data" id="cartData">
                    <input type="hidden" name="subtotal" id="subtotalInput">

                    <div class="cart-total">
                        <span>Subtotal</span>
                        <strong id="subtotalText">Rp0</strong>
                    </div>

                    <p class="checkout-note">Data pelanggan diisi saat kirim pesanan.</p>

                    <button type="button" class="btn-submit-order" id="openCheckoutModalBtn">
                        Pesan
                    </button>
                </form>
            </aside>
        </section>
    </main>

    <div class="mobile-cart-hint" id="mobileCartHint">
        <span class="mobile-cart-hint-icon">+</span>
        <span>Pilih menu favoritmu, lalu tekan tombol <strong>+</strong> untuk menambahkannya ke pesanan.</span>
    </div>

    <button type="button" class="mobile-cart-bar" id="mobileCartBar" aria-controls="orderCart" aria-expanded="false" hidden>
        <span>
            <small id="mobileCartCount">0 item</small>
            <strong id="mobileCartTotal">Rp0</strong>
        </span>
        <span class="mobile-cart-action">Lihat Pesanan</span>
    </button>
    <div class="mobile-cart-overlay" id="mobileCartOverlay"></div>

    <div id="welcomeModal" class="welcome-modal" aria-hidden="true">
        <div class="welcome-modal-card" role="dialog" aria-modal="true" aria-labelledby="welcomeModalTitle">
            <div class="welcome-logo-wrap">
                <img src="../images/logo.png" alt="Olbeemi" class="welcome-logo">
            </div>
            <p class="welcome-kicker">welcome</p>
            <h1 id="welcomeModalTitle">Olbeemi</h1>
            <p class="welcome-copy">Menu favoritmu, siap dipesan online.</p>
            <button type="button" class="welcome-btn" id="closeWelcomeModalBtn">Mulai Pesan</button>
        </div>
    </div>

    <div id="checkoutModal" class="checkout-modal" aria-hidden="true">
        <div class="checkout-modal-card" role="dialog" aria-modal="true" aria-labelledby="checkoutModalTitle">
            <div class="checkout-modal-header">
                <div>
                    <h3 id="checkoutModalTitle">Data Pelanggan</h3>
                    <p>Lengkapi data untuk melanjutkan pesanan</p>
                </div>
                <button type="button" class="checkout-modal-close" id="closeCheckoutModalBtn" aria-label="Tutup popup">
                    ×
                </button>
            </div>

            <div class="checkout-form-fields">
                <div class="checkout-customer-head">
                    <strong>Informasi Pelanggan</strong>
                    <span>Wajib diisi</span>
                </div>

                <div class="checkout-customer-grid">
                    <label>
                        Nama Pelanggan
                        <input type="text" form="orderForm" name="nama_pelanggan" id="namaPelanggan" required>
                    </label>

                    <label>
                        Nomor WhatsApp
                        <input type="text" form="orderForm" name="nomor_wa" placeholder="Contoh: 081234567890" required>
                    </label>
                </div>


                <div class="customer-payment-method">
                    <span>Metode Pembayaran</span>

                    <div class="customer-payment-options">
                        <label>
                            <input type="radio" form="orderForm" name="metode_pembayaran" value="Cash" checked>
                            <span>Cash</span>
                        </label>

                        <label>
                            <input type="radio" form="orderForm" name="metode_pembayaran" value="QRIS">
                            <span>QRIS</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="checkout-modal-actions">
                <button type="button" class="btn-modal-cancel" id="cancelCheckoutModalBtn">Batal</button>
                <button type="submit" form="orderForm" class="btn-submit-order">Kirim Pesanan</button>
            </div>
        </div>
    </div>

<script>
let cart = [];

function formatRupiah(number) {
    return 'Rp' + Number(number).toLocaleString('id-ID');
}

function addToCart(id, nama, harga, maksimalPorsi) {
    const existing = cart.find(item => item.id === id);

    if (maksimalPorsi !== null && (existing?.qty || 0) >= maksimalPorsi) {
        alert(`Stok bahan hanya mencukupi untuk ${maksimalPorsi} porsi ${nama}.`);
        return;
    }

    if (existing) {
        existing.qty++;
    } else {
        cart.push({ id, nama, harga, maksimalPorsi, qty: 1 });
    }

    renderCart();
}

function changeQty(index, delta) {
    if (delta > 0 && cart[index].maksimalPorsi !== null && cart[index].qty >= cart[index].maksimalPorsi) {
        alert(`Stok bahan hanya mencukupi untuk ${cart[index].maksimalPorsi} porsi ${cart[index].nama}.`);
        return;
    }

    cart[index].qty += delta;

    if (cart[index].qty <= 0) {
        cart.splice(index, 1);
    }

    renderCart();
}

function removeItem(index) {
    cart.splice(index, 1);
    renderCart();
}

function getSubtotal() {
    return cart.reduce((sum, item) => sum + (item.harga * item.qty), 0);
}

function renderCart() {
    const cartItems = document.getElementById('cartItems');
    const subtotal = getSubtotal();
    const totalItems = cart.reduce((sum, item) => sum + item.qty, 0);

    document.getElementById('mobileCartCount').textContent = `${totalItems} item`;
    document.getElementById('mobileCartTotal').textContent = formatRupiah(subtotal);
    document.getElementById('mobileCartBar').hidden = totalItems === 0;
    document.getElementById('mobileCartHint').hidden = totalItems > 0;
    document.body.classList.toggle('has-mobile-cart', totalItems > 0);
    document.body.classList.toggle('has-mobile-cart-hint', totalItems === 0);

    if (cart.length === 0) {
        cartItems.innerHTML = '<div class="empty-cart">Belum ada menu dipilih</div>';
        document.getElementById('subtotalText').textContent = 'Rp0';
        closeMobileCart();
        return;
    }

    cartItems.innerHTML = cart.map((item, index) => `
        <div class="cart-item">
            <div>
                <strong>${item.nama}</strong>
                <small>${formatRupiah(item.harga)} x ${item.qty}</small>
            </div>

            <div class="qty-actions">
                <button type="button" onclick="changeQty(${index}, -1)">-</button>
                <span>${item.qty}</span>
                <button type="button" onclick="changeQty(${index}, 1)">+</button>
            </div>

            <button type="button" class="btn-remove" onclick="removeItem(${index})">
                Hapus
            </button>
        </div>
    `).join('');

    document.getElementById('subtotalText').textContent = formatRupiah(subtotal);
}

function prepareOrder() {
    if (cart.length === 0) {
        alert('Daftar pesanan masih kosong.');
        return false;
    }

    document.getElementById('cartData').value = JSON.stringify(cart);
    document.getElementById('subtotalInput').value = getSubtotal();

    closeCheckoutModal();

    return true;
}

function filterMenu() {
    const keyword = document.getElementById('searchInput').value.toLowerCase();
    const kategori = document.getElementById('filterKategori').value;

    document.querySelectorAll('.menu-card').forEach(card => {
        const matchNama = card.dataset.nama.includes(keyword);
        const matchKategori = kategori === 'all' || card.dataset.kategori === kategori;

        card.style.display = matchNama && matchKategori ? '' : 'none';
    });
}

const checkoutModal = document.getElementById('checkoutModal');
const openCheckoutModalBtn = document.getElementById('openCheckoutModalBtn');
const closeCheckoutModalBtn = document.getElementById('closeCheckoutModalBtn');
const cancelCheckoutModalBtn = document.getElementById('cancelCheckoutModalBtn');
const welcomeModal = document.getElementById('welcomeModal');
const closeWelcomeModalBtn = document.getElementById('closeWelcomeModalBtn');
const orderCart = document.getElementById('orderCart');
const mobileCartBar = document.getElementById('mobileCartBar');
const mobileCartOverlay = document.getElementById('mobileCartOverlay');
const closeMobileCartBtn = document.getElementById('closeMobileCartBtn');

function openMobileCart() {
    orderCart.classList.add('is-mobile-open');
    mobileCartOverlay.classList.add('is-open');
    mobileCartBar.setAttribute('aria-expanded', 'true');
    document.body.classList.add('cart-sheet-open');
}

function closeMobileCart() {
    orderCart.classList.remove('is-mobile-open');
    mobileCartOverlay.classList.remove('is-open');
    mobileCartBar.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('cart-sheet-open');
}

function openWelcomeModal() {
    welcomeModal.classList.add('is-open');
    welcomeModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
}

function closeWelcomeModal() {
    welcomeModal.classList.remove('is-open');
    welcomeModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
}

function openCheckoutModal() {
    if (cart.length === 0) {
        alert('Daftar pesanan masih kosong.');
        return;
    }

    closeMobileCart();
    checkoutModal.classList.add('is-open');
    checkoutModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');

    const namaInput = document.getElementById('namaPelanggan');
    if (namaInput) {
        namaInput.focus();
    }
}

function closeCheckoutModal() {
    checkoutModal.classList.remove('is-open');
    checkoutModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
}

closeWelcomeModalBtn.addEventListener('click', closeWelcomeModal);
openCheckoutModalBtn.addEventListener('click', openCheckoutModal);
closeCheckoutModalBtn.addEventListener('click', closeCheckoutModal);
cancelCheckoutModalBtn.addEventListener('click', closeCheckoutModal);
mobileCartBar.addEventListener('click', openMobileCart);
closeMobileCartBtn.addEventListener('click', closeMobileCart);
mobileCartOverlay.addEventListener('click', closeMobileCart);

welcomeModal.addEventListener('click', function (event) {
    if (event.target === welcomeModal) {
        closeWelcomeModal();
    }
});

checkoutModal.addEventListener('click', function (event) {
    if (event.target === checkoutModal) {
        closeCheckoutModal();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && welcomeModal.classList.contains('is-open')) {
        closeWelcomeModal();
    }

    if (event.key === 'Escape' && checkoutModal.classList.contains('is-open')) {
        closeCheckoutModal();
    }

    if (event.key === 'Escape' && orderCart.classList.contains('is-mobile-open')) {
        closeMobileCart();
    }
});

document.getElementById('searchInput').addEventListener('input', filterMenu);
document.getElementById('filterKategori').addEventListener('change', filterMenu);
openWelcomeModal();
</script>
</body>
</html>
