<?php
include '../config/database.php';
include '../includes/pesanan_tables.php';
include '../includes/menu_stock.php';

ensurePesananTables($conn);

auth_require_post_csrf('index.php');

$cart_json = $_POST['cart_data'] ?? '';
$nama_pelanggan = trim($_POST['nama_pelanggan'] ?? '');
$nomor_wa = trim($_POST['nomor_wa'] ?? '');
$metode_pembayaran = trim($_POST['metode_pembayaran'] ?? 'Cash');
$catatan = trim($_POST['catatan'] ?? '');
$allowed_payment_methods = ['Cash', 'QRIS'];

if (!in_array($metode_pembayaran, $allowed_payment_methods, true)) {
    $metode_pembayaran = 'Cash';
}

$cart = json_decode($cart_json, true);

if (!is_array($cart) || count($cart) === 0 || $nama_pelanggan === '' || $nomor_wa === '') {
    die('Data pesanan tidak valid. Nomor WhatsApp wajib diisi.');
}

mysqli_begin_transaction($conn);

try {
    validateCartStock($conn, $cart);

    $kode_pesanan = 'ORD-' . date('YmdHis');
    $subtotal = 0;
    $items = [];

    foreach ($cart as $item) {
        $menu_id = (int) ($item['id'] ?? 0);
        $qty = (int) ($item['qty'] ?? 0);

        if ($menu_id <= 0 || $qty <= 0) {
            throw new Exception('Item pesanan tidak valid.');
        }

        $menu_query = mysqli_query($conn, "
            SELECT id, nama_menu, harga_jual
            FROM menu
            WHERE id = $menu_id
            AND status = 'Aktif'
        ");

        $menu = mysqli_fetch_assoc($menu_query);

        if (!$menu) {
            throw new Exception('Menu tidak ditemukan atau tidak aktif.');
        }

        $harga = (float) $menu['harga_jual'];
        $item_subtotal = $harga * $qty;
        $subtotal += $item_subtotal;

        $items[] = [
            'menu_id' => (int) $menu['id'],
            'nama_menu' => $menu['nama_menu'],
            'harga_satuan' => $harga,
            'qty' => $qty,
            'subtotal' => $item_subtotal,
        ];
    }

    $nama_safe = mysqli_real_escape_string($conn, $nama_pelanggan);
    $nomor_safe = mysqli_real_escape_string($conn, $nomor_wa);
    $metode_safe = mysqli_real_escape_string($conn, $metode_pembayaran);
    $catatan_safe = mysqli_real_escape_string($conn, $catatan);
    $sumber_safe = mysqli_real_escape_string($conn, 'QR Menu');

    $insert_pesanan = mysqli_query($conn, "
        INSERT INTO pesanan (
            kode_pesanan,
            nama_pelanggan,
            nomor_wa,
            metode_pembayaran,
            sumber_pesanan,
            catatan,
            status,
            subtotal
        ) VALUES (
            '$kode_pesanan',
            '$nama_safe',
            '$nomor_safe',
            '$metode_safe',
            '$sumber_safe',
            '$catatan_safe',
            'Diproses',
            '$subtotal'
        )
    ");

    if (!$insert_pesanan) {
        throw new Exception(mysqli_error($conn));
    }

    $pesanan_id = mysqli_insert_id($conn);

    foreach ($items as $item) {
        $nama_menu = mysqli_real_escape_string($conn, $item['nama_menu']);
        $menu_id = (int) $item['menu_id'];
        $harga = (float) $item['harga_satuan'];
        $qty = (int) $item['qty'];
        $item_subtotal = (float) $item['subtotal'];

        $insert_detail = mysqli_query($conn, "
            INSERT INTO pesanan_detail (
                pesanan_id,
                menu_id,
                nama_menu,
                harga_satuan,
                qty,
                subtotal
            ) VALUES (
                $pesanan_id,
                $menu_id,
                '$nama_menu',
                '$harga',
                $qty,
                '$item_subtotal'
            )
        ");

        if (!$insert_detail) {
            throw new Exception(mysqli_error($conn));
        }
    }

    mysqli_commit($conn);

    header("Location: sukses.php?id=$pesanan_id");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    die($e->getMessage());
}
?>
