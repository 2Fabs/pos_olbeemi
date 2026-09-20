<?php
include '../config/database.php';
include '../includes/menu_stock.php';

$menu_query = mysqli_query($conn, "
    SELECT menu.*, " . menuStockSelect('menu') . "
    FROM menu
    WHERE menu.status = 'Aktif'
      AND menu.harga_jual > 0
      AND EXISTS (SELECT 1 FROM menu_resep WHERE menu_resep.menu_id = menu.id)
    ORDER BY kategori ASC, nama_menu ASC
");

$kategori_query = mysqli_query($conn, "
    SELECT DISTINCT kategori
    FROM menu
    WHERE status = 'Aktif'
    AND kategori IN ('Coffee', 'Non Coffee')
    ORDER BY kategori ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Penjualan</title>

    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/pos.css?v=<?= filemtime(__DIR__ . '/../css/pos.css') ?>">
</head>
<body>

<?php
$current_module = 'transaksi';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="transaksi-layout">

        <!-- LEFT SIDE -->
        <div class="produk-section">

            <div class="header">
                <div>
                    <h1>🛒 Kasir</h1>
                    <p>Pilih menu untuk transaksi penjualan</p>
                </div>
                <div class="header-stats">
                    <div class="stat-item">
                        <span class="stat-label">Total Item</span>
                        <span class="stat-value" id="totalItems">0</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Harga</span>
                        <span class="stat-value" id="totalHarga">Rp0</span>
                    </div>
                </div>
            </div>

            <div class="produk-toolbar">
                <input
                    type="text"
                    id="searchInput"
                    placeholder="Cari menu..."
                >

                <select id="filterKategori">
                    <option value="all">Semua Kategori</option>
                    <?php while ($kategori = mysqli_fetch_assoc($kategori_query)): ?>
                        <option value="<?= htmlspecialchars($kategori['kategori']) ?>">
                            <?= htmlspecialchars($kategori['kategori']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- SCROLL BOX -->
            <div class="produk-scroll-box">
                <div class="produk-grid" id="produkGrid">

                    <?php while ($menu = mysqli_fetch_assoc($menu_query)): ?>
                        <?php $stock_info = menuSalesStockInfo($menu); ?>
                        <div
                            class="produk-card menu-stock-<?= $stock_info['status'] ?>"
                            data-kategori="<?= htmlspecialchars($menu['kategori']) ?>"
                            data-nama="<?= strtolower($menu['nama_menu']) ?>"
                        >

                            <?php if (
                                $menu['foto'] &&
                                file_exists(__DIR__ . '/../menu/uploads/' . $menu['foto'])
                            ): ?>
                                <img
                                    src="../menu/uploads/<?= htmlspecialchars($menu['foto']) ?>"
                                    alt="<?= htmlspecialchars($menu['nama_menu']) ?>"
                                >
                            <?php else: ?>
                                <div class="produk-placeholder">
                                    No Image
                                </div>
                            <?php endif; ?>

                            <div class="produk-info">
                                <h3><?= htmlspecialchars($menu['nama_menu']) ?></h3>
                                <p><?= htmlspecialchars($menu['kategori']) ?></p>
                                <strong>
                                    Rp<?= number_format($menu['harga_jual'], 0, ',', '.') ?>
                                </strong>
                                <span class="menu-stock-label menu-stock-label-<?= $stock_info['status'] ?>">
                                    <?= htmlspecialchars($stock_info['label']) ?>
                                </span>

                                <button
                                    type="button"
                                    class="btn-add-cart"
                                    title="Tambahkan ke keranjang"
                                    aria-label="Tambahkan ke keranjang"
                                    onclick='addToCart(
                                        <?= $menu['id'] ?>,
                                        <?= json_encode($menu['nama_menu']) ?>,
                                        <?= $menu['harga_jual'] ?>,
                                        <?= $menu['keuntungan'] ?>,
                                        <?= $stock_info['capacity'] === null ? 'null' : $stock_info['capacity'] ?>
                                    )'
                                    <?= $stock_info['status'] === 'habis' ? 'disabled' : '' ?>
                                >
                                    +
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>

                </div>
            </div>
        </div>

        <!-- RIGHT SIDE -->
        <div class="cart-section">
            <div class="cart-card">

                <h2>Keranjang</h2>

                <div id="cartItems" class="cart-items">
                    <div class="empty-cart">
                        Belum ada menu dipilih
                    </div>
                </div>

                <div class="cart-summary">

                    <div class="summary-row">
                        <span>Subtotal</span>
                        <strong id="subtotalText">Rp0</strong>
                    </div>

                    <button
                        type="button"
                        class="btn-process"
                        onclick="openPaymentModal()"
                    >
                        Proses
                    </button>

                </div>
            </div>
        </div>

    </div>
</div>

<div id="paymentModal" class="modal payment-modal">
    <div class="modal-content payment-modal-content">
        <div class="modal-header">
            <div>
                <h2>Proses Pembayaran</h2>
                <p>Pilih metode pembayaran dan masukkan nominal bayar</p>
            </div>
            <span class="close-btn" onclick="closePaymentModal()">&times;</span>
        </div>

        <div class="payment-total-card">
            <span>Total Bayar</span>
            <strong id="modalSubtotalText">Rp0</strong>
        </div>

        <form
            action="simpan_transaksi.php"
            method="POST"
            id="checkoutForm"
            onsubmit="return prepareCheckout()"
        >
            <?= auth_csrf_input() ?>
            <div class="payment-customer-card">
                <div class="payment-customer-head">
                    <strong>Data Pelanggan</strong>
                    <span>Opsional</span>
                </div>
                <div class="payment-customer-grid">
                    <label>
                        Nama Pelanggan
                        <input
                            type="text"
                            name="nama_pelanggan"
                            id="customerNameInput"
                            maxlength="120"
                            placeholder="Contoh: Andi"
                            autocomplete="name"
                        >
                    </label>
                    <label>
                        Nomor WhatsApp
                        <input
                            type="tel"
                            name="nomor_wa"
                            id="customerWhatsappInput"
                            maxlength="20"
                            inputmode="tel"
                            placeholder="Contoh: 081234567890"
                            autocomplete="tel"
                        >
                    </label>
                </div>
                <p>Isi nomor WhatsApp jika nota akan dikirim ke pelanggan.</p>
            </div>

            <div class="payment-method">
                <label class="payment-option">
                    <input
                        type="radio"
                        name="payment_method"
                        value="Cash"
                        checked
                        onchange="togglePayment()"
                    >
                    <span>Cash</span>
                </label>

                <label class="payment-option">
                    <input
                        type="radio"
                        name="payment_method"
                        value="QRIS"
                        onchange="togglePayment()"
                    >
                    <span>QRIS</span>
                </label>
            </div>

            <div id="cashSection">
                <input
                    type="text"
                    inputmode="numeric"
                    id="bayarInput"
                    placeholder="Masukkan Jumlah Bayar"
                    autocomplete="off"
                    oninput="formatBayarInput(this)"
                >

                <div class="summary-row">
                    <span>Kembalian</span>
                    <strong id="kembalianText">Rp0</strong>
                </div>
            </div>

            <input type="hidden" name="cart_data" id="cartData">
            <input type="hidden" name="subtotal" id="subtotalInput">
            <input type="hidden" name="metode_pembayaran" id="paymentInput">
            <input type="hidden" name="bayar" id="bayarHidden">
            <input type="hidden" name="kembalian" id="kembalianHidden">

            <div class="payment-actions">
                <button
                    type="button"
                    class="btn-cancel-payment"
                    onclick="closePaymentModal()"
                >
                    Batal
                </button>

                <button type="submit" class="btn-checkout">
                    Bayar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let cart = [];

function formatRupiah(number) {
    return 'Rp' + Number(number).toLocaleString('id-ID');
}

function parseRupiahInput(value) {
    return Number(String(value).replace(/\D/g, '') || 0);
}

function formatBayarInput(input) {
    const rawValue = String(input.value).replace(/\D/g, '');

    input.value = rawValue
        ? Number(rawValue).toLocaleString('id-ID')
        : '';

    calculateChange();
}

function addToCart(id, nama, harga, keuntungan, maksimalPorsi) {
    const existing = cart.find(item => item.id === id);

    if (maksimalPorsi !== null && (existing?.qty || 0) >= maksimalPorsi) {
        alert(`Stok bahan hanya mencukupi untuk ${maksimalPorsi} porsi ${nama}.`);
        return;
    }

    if (existing) {
        existing.qty++;
    } else {
        cart.push({
            id,
            nama,
            harga,
            keuntungan,
            maksimalPorsi,
            qty: 1
        });
    }

    renderCart();
}

function renderCart() {
    const cartItems = document.getElementById('cartItems');
    const subtotalText = document.getElementById('subtotalText');
    const modalSubtotalText = document.getElementById('modalSubtotalText');
    const totalItems = document.getElementById('totalItems');
    const totalHarga = document.getElementById('totalHarga');

    if (cart.length === 0) {
        cartItems.innerHTML = `
            <div class="empty-cart">
                Belum ada menu dipilih
            </div>
        `;

        subtotalText.textContent = 'Rp0';
        modalSubtotalText.textContent = 'Rp0';
        totalItems.textContent = '0';
        totalHarga.textContent = 'Rp0';
        calculateChange();
        return;
    }

    let html = '';
    let subtotal = 0;
    let itemCount = 0;

    cart.forEach((item, index) => {
        subtotal += item.harga * item.qty;
        itemCount += item.qty;

        html += `
            <div class="cart-item">
                <div>
                    <strong>${item.nama}</strong>
                    <small>${formatRupiah(item.harga)} x ${item.qty}</small>
                </div>

                <div class="qty-actions">
                    <button
                        type="button"
                        onclick="changeQty(${index}, -1)"
                    >-</button>

                    <span>${item.qty}</span>

                    <button
                        type="button"
                        onclick="changeQty(${index}, 1)"
                    >+</button>
                </div>

                <button
                    type="button"
                    class="btn-remove"
                    onclick="removeItem(${index})"
                >Hapus</button>
            </div>
        `;
    });

    cartItems.innerHTML = html;
    subtotalText.textContent = formatRupiah(subtotal);
    modalSubtotalText.textContent = formatRupiah(subtotal);
    totalItems.textContent = itemCount;
    totalHarga.textContent = formatRupiah(subtotal);

    calculateChange();
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
    return cart.reduce((sum, item) => {
        return sum + (item.harga * item.qty);
    }, 0);
}

function openPaymentModal() {
    if (cart.length === 0) {
        alert('Keranjang kosong.');
        return;
    }

    document.getElementById('modalSubtotalText').textContent =
        formatRupiah(getSubtotal());

    document.getElementById('paymentModal').style.display = 'flex';
    togglePayment();

    const method = document.querySelector(
        'input[name="payment_method"]:checked'
    ).value;

    if (method === 'Cash') {
        document.getElementById('bayarInput').focus();
    }
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function updatePaymentOptionState() {
    document.querySelectorAll('.payment-option').forEach(label => {
        const input = label.querySelector('input[name="payment_method"]');
        if (input && input.checked) {
            label.classList.add('active');
        } else {
            label.classList.remove('active');
        }
    });
}

function togglePayment() {
    const method = document.querySelector(
        'input[name="payment_method"]:checked'
    ).value;

    const cashSection = document.getElementById('cashSection');

    cashSection.style.display = method === 'Cash'
        ? 'block'
        : 'none';

    updatePaymentOptionState();
    calculateChange();
}

function calculateChange() {
    const method = document.querySelector(
        'input[name="payment_method"]:checked'
    ).value;

    const subtotal = getSubtotal();

    if (method === 'QRIS') {
        document.getElementById('kembalianText').textContent = 'Rp0';
        return;
    }

    const bayar = parseRupiahInput(
        document.getElementById('bayarInput').value
    );

    const kembalian = bayar - subtotal;

    document.getElementById('kembalianText').textContent =
        formatRupiah(kembalian > 0 ? kembalian : 0);
}

function prepareCheckout() {
    if (document.querySelector('.btn-checkout').disabled) return false;
    if (cart.length === 0) {
        alert('Keranjang kosong.');
        return false;
    }

    const method = document.querySelector(
        'input[name="payment_method"]:checked'
    ).value;

    const subtotal = getSubtotal();
    const whatsappInput = document.getElementById('customerWhatsappInput');
    const whatsappDigits = whatsappInput.value.replace(/\D/g, '');

    if (whatsappDigits !== '' && !/^(?:62|0)8\d{8,11}$/.test(whatsappDigits)) {
        alert('Nomor WhatsApp belum valid. Gunakan format 08... atau 628...');
        whatsappInput.focus();
        return false;
    }

    let bayar = 0;
    let kembalian = 0;

    if (method === 'Cash') {
        bayar = parseRupiahInput(
            document.getElementById('bayarInput').value
        );

        if (bayar < subtotal) {
            alert('Uang bayar kurang.');
            return false;
        }

        kembalian = bayar - subtotal;
    }

    document.getElementById('cartData').value =
        JSON.stringify(cart);

    document.getElementById('subtotalInput').value =
        subtotal;

    document.getElementById('paymentInput').value =
        method;

    document.getElementById('bayarHidden').value =
        bayar;

    document.getElementById('kembalianHidden').value =
        kembalian;

    document.querySelector('.btn-checkout').disabled = true;
    document.querySelector('.btn-checkout').textContent = 'Menyimpan pesanan...';
    return true;
}

window.addEventListener('pageshow', () => {
    const button = document.querySelector('.btn-checkout');
    button.disabled = false;
    button.textContent = 'Proses Pembayaran';
});

function filterProduk() {
    const keyword = document
        .getElementById('searchInput')
        .value
        .toLowerCase();

    const kategori = document
        .getElementById('filterKategori')
        .value;

    document.querySelectorAll('.produk-card').forEach(card => {
        const nama = card.dataset.nama;
        const kat = card.dataset.kategori;

        const matchNama = nama.includes(keyword);
        const matchKategori =
            kategori === 'all' || kat === kategori;

        card.style.display =
            (matchNama && matchKategori)
                ? ''
                : 'none';
    });
}

document
    .getElementById('searchInput')
    .addEventListener('input', filterProduk);

document
    .getElementById('filterKategori')
    .addEventListener('change', filterProduk);

document.getElementById('paymentModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePaymentModal();
    }
});

togglePayment();
</script>

</body>
</html>
