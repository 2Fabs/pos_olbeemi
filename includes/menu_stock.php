<?php
function menuStockSelect($menu_alias = 'menu') {
    return "
        (SELECT COUNT(*) FROM menu_resep WHERE menu_id = $menu_alias.id) AS jumlah_resep,
        (
            SELECT MIN(FLOOR(
                GREATEST(
                    bahan_baku.stok - COALESCE((
                        SELECT SUM(menu_resep_reserved.jumlah * pesanan_detail_reserved.qty)
                        FROM pesanan_detail pesanan_detail_reserved
                        JOIN pesanan pesanan_reserved ON pesanan_reserved.id = pesanan_detail_reserved.pesanan_id
                        JOIN menu_resep menu_resep_reserved ON menu_resep_reserved.menu_id = pesanan_detail_reserved.menu_id
                        WHERE menu_resep_reserved.bahan_id = bahan_baku.id
                        AND pesanan_reserved.status IN ('Menunggu', 'Diproses')
                    ), 0),
                    0
                ) / NULLIF(menu_resep.jumlah, 0)
            ))
            FROM menu_resep
            JOIN bahan_baku ON bahan_baku.id = menu_resep.bahan_id
            WHERE menu_resep.menu_id = $menu_alias.id
        ) AS maksimal_porsi,
        (
            SELECT MAX(bahan_baku.stok <= bahan_baku.minimum_stok)
            FROM menu_resep
            JOIN bahan_baku ON bahan_baku.id = menu_resep.bahan_id
            WHERE menu_resep.menu_id = $menu_alias.id
        ) AS bahan_menipis
    ";
}

function menuStockInfo($menu) {
    $has_recipe = (int) ($menu['jumlah_resep'] ?? 0) > 0;
    $capacity = $has_recipe ? max(0, (int) ($menu['maksimal_porsi'] ?? 0)) : null;
    $is_low = $has_recipe && ($capacity <= 5 || !empty($menu['bahan_menipis']));

    if ($has_recipe && $capacity === 0) {
        return ['status' => 'habis', 'label' => 'Stok Habis', 'capacity' => 0];
    }

    if ($is_low) {
        $label = $capacity <= 5 ? 'Tersisa ' . $capacity : 'Stok Menipis';
        return ['status' => 'menipis', 'label' => $label, 'capacity' => $capacity];
    }

    return ['status' => 'tersedia', 'label' => 'Tersedia', 'capacity' => $capacity];
}

function menuSalesStockInfo($menu) {
    $stock_info = menuStockInfo($menu);
    $capacity = $stock_info['capacity'];

    if ($capacity === 0) {
        return ['status' => 'habis', 'label' => 'Stok Habis', 'capacity' => 0];
    }

    if ($capacity !== null && $capacity <= 5) {
        return ['status' => 'menipis', 'label' => 'Tersisa ' . $capacity, 'capacity' => $capacity];
    }

    return ['status' => 'tersedia', 'label' => 'Tersedia', 'capacity' => $capacity];
}

function validateCartStock($conn, $cart) {
    return validateCartStockWithReservations($conn, $cart, 0);
}

function validateCartStockWithReservations($conn, $cart, $exclude_pesanan_id = 0) {
    $exclude_pesanan_id = (int) $exclude_pesanan_id;
    $stock_needs = [];

    foreach ($cart as $item) {
        $menu_id = (int) ($item['id'] ?? 0);
        $qty = (int) ($item['qty'] ?? 0);

        if ($menu_id <= 0 || $qty <= 0) {
            throw new Exception('Item pesanan tidak valid.');
        }

        $recipe_query = mysqli_query($conn, "
            SELECT
                bahan_baku.id,
                bahan_baku.nama_bahan,
                bahan_baku.stok,
                menu_resep.jumlah
            FROM menu_resep
            JOIN bahan_baku ON bahan_baku.id = menu_resep.bahan_id
            WHERE menu_resep.menu_id = $menu_id
        ");

        if (!$recipe_query) {
            throw new Exception(mysqli_error($conn));
        }

        while ($ingredient = mysqli_fetch_assoc($recipe_query)) {
            $ingredient_id = (int) $ingredient['id'];
            if (!isset($stock_needs[$ingredient_id])) {
                $stock_needs[$ingredient_id] = [
                    'id' => $ingredient_id,
                    'name' => $ingredient['nama_bahan'],
                    'stock' => (float) $ingredient['stok'],
                    'required' => 0,
                ];
            }
            $stock_needs[$ingredient_id]['required'] += (float) $ingredient['jumlah'] * $qty;
        }
    }

    foreach ($stock_needs as $need) {
        $reserved_query = mysqli_query($conn, "
            SELECT COALESCE(SUM(menu_resep.jumlah * pesanan_detail.qty), 0) AS reserved
            FROM pesanan_detail
            JOIN pesanan ON pesanan.id = pesanan_detail.pesanan_id
            JOIN menu_resep ON menu_resep.menu_id = pesanan_detail.menu_id
            WHERE menu_resep.bahan_id = " . (int) $need['id'] . "
            AND pesanan.status IN ('Menunggu', 'Diproses')
            " . ($exclude_pesanan_id > 0 ? "AND pesanan.id != $exclude_pesanan_id" : "") . "
        ");
        if (!$reserved_query) {
            throw new Exception(mysqli_error($conn));
        }
        $reserved_row = mysqli_fetch_assoc($reserved_query);
        $available = $need['stock'] - (float) ($reserved_row['reserved'] ?? 0);

        if ($available < $need['required']) {
            throw new Exception('Stok ' . $need['name'] . ' tidak cukup untuk jumlah pesanan ini.');
        }
    }
}
?>
