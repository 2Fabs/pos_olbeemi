<?php
include '../config/database.php';
include '../includes/pesanan_tables.php';
include '../includes/menu_stock.php';

ensurePesananTables($conn);

auth_require_post_csrf('index.php');

$cart_json = $_POST['cart_data'] ?? '';
$metode = trim($_POST['metode_pembayaran'] ?? '');
$bayar = (float) ($_POST['bayar'] ?? 0);
$kembalian = (float) ($_POST['kembalian'] ?? 0);
$nama_pelanggan_input = trim($_POST['nama_pelanggan'] ?? '');
$nomor_wa = preg_replace('/\D/', '', (string) ($_POST['nomor_wa'] ?? ''));
$catatan_input = trim($_POST['catatan'] ?? '');
$allowed_metode = ['Cash', 'QRIS'];

if (empty($cart_json) || !in_array($metode, $allowed_metode, true)) {
    die('Data transaksi tidak valid.');
}

$cart = json_decode($cart_json, true);

if (!is_array($cart) || count($cart) === 0) {
    die('Keranjang kosong.');
}

if ($nomor_wa !== '' && preg_match('/^(?:62|0)8\d{8,11}$/', $nomor_wa) !== 1) {
    die('Nomor WhatsApp tidak valid.');
}

mysqli_begin_transaction($conn);

try {
    validateCartStock($conn, $cart);

    $kode_pesanan = 'ORD-KD-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -4));
    $nama_pelanggan = $nama_pelanggan_input !== ''
        ? $nama_pelanggan_input
        : 'Pelanggan Kedai';
    $sumber_pesanan = 'Kedai';
    $catatan = $catatan_input;
    $subtotal = 0;
    $items = [];

    foreach ($cart as $item) {
        $menu_id = (int) ($item['id'] ?? 0);
        $qty = (int) ($item['qty'] ?? 0);

        if ($menu_id <= 0 || $qty <= 0) {
            throw new Exception('Item transaksi tidak valid.');
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

    if ($subtotal <= 0) {
        throw new Exception('Subtotal tidak valid.');
    }

    if ($metode === 'Cash') {
        if ($bayar < $subtotal) {
            throw new Exception('Uang bayar kurang.');
        }

        $kembalian = $bayar - $subtotal;
    } else {
        $bayar = $subtotal;
        $kembalian = 0;
    }

    $kode_safe = mysqli_real_escape_string($conn, $kode_pesanan);
    $nama_safe = mysqli_real_escape_string($conn, $nama_pelanggan);
    $nomor_wa_safe = mysqli_real_escape_string($conn, $nomor_wa);
    $sumber_safe = mysqli_real_escape_string($conn, $sumber_pesanan);
    $metode_safe = mysqli_real_escape_string($conn, $metode);
    $catatan_safe = mysqli_real_escape_string($conn, $catatan);

    $insert_pesanan = mysqli_query($conn, "
        INSERT INTO pesanan (
            kode_pesanan,
            nama_pelanggan,
            nomor_wa,
            metode_pembayaran,
            sumber_pesanan,
            catatan,
            status,
            subtotal,
            bayar,
            kembalian
        ) VALUES (
            '$kode_safe',
            '$nama_safe',
            '$nomor_wa_safe',
            '$metode_safe',
            '$sumber_safe',
            '$catatan_safe',
            'Diproses',
            '$subtotal',
            '$bayar',
            '$kembalian'
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

    header("Location: ../pesanan/detail.php?id=$pesanan_id&success=masuk");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    die($e->getMessage());
}
?>
