<?php
include_once __DIR__ . '/menu_stock.php';

function buildKodeTransaksi($sumber_pesanan, $timestamp, $pesanan_id) {
    $sumber_normalized = strtolower(trim((string) $sumber_pesanan));
    $prefix_transaksi = $sumber_normalized === 'kedai'
        ? 'INV-KD-'
        : 'INV-QR-';

    return $prefix_transaksi . $timestamp . '-' . (int) $pesanan_id;
}

function syncLegacyKodeTransaksi($conn, $transaksi_id = 0) {
    $transaksi_id = (int) $transaksi_id;
    $where = $transaksi_id > 0
        ? 'WHERE transaksi.id = ' . $transaksi_id
        : '';

    $legacy_query = mysqli_query($conn, "
        SELECT
            transaksi.id AS transaksi_id,
            transaksi.kode_transaksi,
            transaksi.created_at,
            pesanan.id AS pesanan_id,
            pesanan.sumber_pesanan
        FROM transaksi
        JOIN pesanan ON pesanan.transaksi_id = transaksi.id
        $where
    ");

    if (!$legacy_query) {
        throw new Exception(mysqli_error($conn));
    }

    while ($row = mysqli_fetch_assoc($legacy_query)) {
        $timestamp = date('YmdHis', strtotime($row['created_at'] ?? date('Y-m-d H:i:s')));
        $expected_kode = buildKodeTransaksi(
            $row['sumber_pesanan'] ?? '',
            $timestamp,
            (int) $row['pesanan_id']
        );

        if ($row['kode_transaksi'] === $expected_kode) {
            continue;
        }

        $kode_safe = mysqli_real_escape_string($conn, $expected_kode);
        $current_transaksi_id = (int) $row['transaksi_id'];

        $update_kode = mysqli_query($conn, "
            UPDATE transaksi
            SET kode_transaksi = '$kode_safe'
            WHERE id = $current_transaksi_id
        ");

        if (!$update_kode) {
            throw new Exception(mysqli_error($conn));
        }
    }
}

function completePesananAsTransaksi($conn, $pesanan_id, $metode_pembayaran = '', $bayar_input = 0) {
    $pesanan_id = (int) $pesanan_id;
    $allowed_metode = ['Cash', 'QRIS'];

    if ($pesanan_id <= 0) {
        throw new Exception('Pesanan tidak valid.');
    }

    mysqli_begin_transaction($conn);

    try {
        $pesanan_query = mysqli_query($conn, "
            SELECT *
            FROM pesanan
            WHERE id = $pesanan_id
            FOR UPDATE
        ");

        $pesanan = mysqli_fetch_assoc($pesanan_query);

        if (!$pesanan) {
            throw new Exception('Pesanan tidak ditemukan.');
        }

        if (!in_array($metode_pembayaran, $allowed_metode, true)) {
            $metode_pembayaran = $pesanan['metode_pembayaran'] ?? 'Cash';
        }

        if (!in_array($metode_pembayaran, $allowed_metode, true)) {
            $metode_pembayaran = 'Cash';
        }

        if (!empty($pesanan['transaksi_id'])) {
            $transaksi_id = (int) $pesanan['transaksi_id'];

            mysqli_query($conn, "
                UPDATE pesanan
                SET status = 'Selesai',
                    selesai_at = COALESCE(selesai_at, NOW())
                WHERE id = $pesanan_id
            ");

            mysqli_commit($conn);
            return $transaksi_id;
        }

        $detail_query = mysqli_query($conn, "
            SELECT *
            FROM pesanan_detail
            WHERE pesanan_id = $pesanan_id
            ORDER BY id ASC
        ");

        $items = [];
        $stock_needs = [];
        $subtotal = 0;
        $total_profit = 0;

        while ($detail = mysqli_fetch_assoc($detail_query)) {
            $menu_id = (int) $detail['menu_id'];
            $qty = (int) $detail['qty'];

            if ($menu_id <= 0 || $qty <= 0) {
                throw new Exception('Item pesanan tidak valid.');
            }

            $menu_query = mysqli_query($conn, "
                SELECT id, nama_menu, keuntungan
                FROM menu
                WHERE id = $menu_id
            ");

            $menu = mysqli_fetch_assoc($menu_query);

            if (!$menu) {
                throw new Exception('Menu pada pesanan tidak ditemukan.');
            }

            $harga = (float) $detail['harga_satuan'];
            $profit = (float) $menu['keuntungan'];
            $item_subtotal = (float) $detail['subtotal'];

            if ($item_subtotal <= 0) {
                $item_subtotal = $harga * $qty;
            }

            $subtotal += $item_subtotal;
            $total_profit += $profit * $qty;

            $items[] = [
                'menu_id' => $menu_id,
                'nama_menu' => $detail['nama_menu'],
                'harga_satuan' => $harga,
                'keuntungan_satuan' => $profit,
                'qty' => $qty,
                'subtotal' => $item_subtotal,
            ];

            $resep_query = mysqli_query($conn, "
                SELECT
                    menu_resep.jumlah,
                    bahan_baku.id AS bahan_id,
                    bahan_baku.nama_bahan,
                    bahan_baku.stok
                FROM menu_resep
                JOIN bahan_baku ON menu_resep.bahan_id = bahan_baku.id
                WHERE menu_resep.menu_id = $menu_id
                FOR UPDATE
            ");

            while ($bahan = mysqli_fetch_assoc($resep_query)) {
                $bahan_id = (int) $bahan['bahan_id'];
                $jumlah_keluar = (float) $bahan['jumlah'] * $qty;

                if (!isset($stock_needs[$bahan_id])) {
                    $stock_needs[$bahan_id] = [
                        'nama_bahan' => $bahan['nama_bahan'],
                        'stok' => (float) $bahan['stok'],
                        'jumlah' => 0,
                    ];
                }

                $stock_needs[$bahan_id]['jumlah'] += $jumlah_keluar;
            }
        }

        if (count($items) === 0) {
            throw new Exception('Detail pesanan masih kosong.');
        }

        validateCartStockWithReservations($conn, array_map(function ($item) {
            return ['id' => $item['menu_id'], 'qty' => $item['qty']];
        }, $items), $pesanan_id);

        foreach ($stock_needs as $need) {
            if ($need['stok'] < $need['jumlah']) {
                throw new Exception('Stok ' . $need['nama_bahan'] . ' tidak cukup.');
            }
        }

        if ($subtotal <= 0) {
            $subtotal = (float) $pesanan['subtotal'];
        }

        $kode_transaksi = buildKodeTransaksi(
            $pesanan['sumber_pesanan'] ?? '',
            date('YmdHis'),
            $pesanan_id
        );
        $kode_safe = mysqli_real_escape_string($conn, $kode_transaksi);
        $metode_safe = mysqli_real_escape_string($conn, $metode_pembayaran);
        $kembalian = 0;
        $stored_bayar = (float) ($pesanan['bayar'] ?? 0);

        if ($metode_pembayaran === 'Cash') {
            $bayar = (float) preg_replace('/\D/', '', (string) $bayar_input);

            if ($bayar <= 0 && $stored_bayar > 0) {
                $bayar = $stored_bayar;
            }

            if ($bayar <= 0) {
                throw new Exception('Jumlah bayar wajib diisi.');
            }

            if ($bayar < $subtotal) {
                throw new Exception('Uang bayar kurang.');
            }

            $kembalian = $bayar - $subtotal;
        } else {
            $bayar = $stored_bayar > 0 ? $stored_bayar : $subtotal;
            $kembalian = 0;
        }

        $insert_transaksi = mysqli_query($conn, "
            INSERT INTO transaksi (
                kode_transaksi,
                metode_pembayaran,
                subtotal,
                bayar,
                kembalian,
                total_profit
            ) VALUES (
                '$kode_safe',
                '$metode_safe',
                '$subtotal',
                '$bayar',
                '$kembalian',
                '$total_profit'
            )
        ");

        if (!$insert_transaksi) {
            throw new Exception(mysqli_error($conn));
        }

        $transaksi_id = mysqli_insert_id($conn);

        foreach ($items as $item) {
            $menu_id = (int) $item['menu_id'];
            $nama_menu = mysqli_real_escape_string($conn, $item['nama_menu']);
            $harga = (float) $item['harga_satuan'];
            $profit = (float) $item['keuntungan_satuan'];
            $qty = (int) $item['qty'];
            $item_subtotal = (float) $item['subtotal'];

            $insert_detail = mysqli_query($conn, "
                INSERT INTO transaksi_detail (
                    transaksi_id,
                    menu_id,
                    nama_menu,
                    harga_satuan,
                    keuntungan_satuan,
                    qty,
                    subtotal
                ) VALUES (
                    $transaksi_id,
                    $menu_id,
                    '$nama_menu',
                    '$harga',
                    '$profit',
                    $qty,
                    '$item_subtotal'
                )
            ");

            if (!$insert_detail) {
                throw new Exception(mysqli_error($conn));
            }

            $resep_query = mysqli_query($conn, "
                SELECT
                    menu_resep.jumlah,
                    bahan_baku.id AS bahan_id,
                    bahan_baku.nama_bahan,
                    bahan_baku.satuan
                FROM menu_resep
                JOIN bahan_baku ON menu_resep.bahan_id = bahan_baku.id
                WHERE menu_resep.menu_id = $menu_id
                FOR UPDATE
            ");

            while ($bahan = mysqli_fetch_assoc($resep_query)) {
                $bahan_id = (int) $bahan['bahan_id'];
                $jumlah_keluar = (float) $bahan['jumlah'] * $qty;
                $nama_bahan = mysqli_real_escape_string($conn, $bahan['nama_bahan']);
                $satuan = mysqli_real_escape_string($conn, $bahan['satuan']);
                $keterangan = mysqli_real_escape_string(
                    $conn,
                    $item['nama_menu']
                );

                $update_stok = mysqli_query($conn, "
                    UPDATE bahan_baku
                    SET stok = stok - '$jumlah_keluar'
                    WHERE id = $bahan_id
                ");

                if (!$update_stok) {
                    throw new Exception(mysqli_error($conn));
                }

                $stok_query = mysqli_query($conn, "
                    SELECT stok
                    FROM bahan_baku
                    WHERE id = $bahan_id
                ");
                $stok_row = mysqli_fetch_assoc($stok_query);
                $stok_baru = (float) $stok_row['stok'];

                $insert_riwayat = mysqli_query($conn, "
                    INSERT INTO riwayat_keluar (
                        bahan_id,
                        nama_bahan,
                        jumlah_keluar,
                        satuan,
                        total_setelah,
                        keterangan
                    ) VALUES (
                        $bahan_id,
                        '$nama_bahan',
                        '$jumlah_keluar',
                        '$satuan',
                        '$stok_baru',
                        '$keterangan'
                    )
                ");

                if (!$insert_riwayat) {
                    throw new Exception(mysqli_error($conn));
                }
            }
        }

        $update_pesanan = mysqli_query($conn, "
            UPDATE pesanan
            SET status = 'Selesai',
                bayar = '$bayar',
                kembalian = '$kembalian',
                transaksi_id = $transaksi_id,
                selesai_at = NOW()
            WHERE id = $pesanan_id
        ");

        if (!$update_pesanan) {
            throw new Exception(mysqli_error($conn));
        }

        mysqli_commit($conn);
        return $transaksi_id;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}
?>
