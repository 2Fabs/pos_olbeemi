<?php
include '../config/database.php';
include '../includes/menu_requests.php';

auth_require_post_csrf('index.php');

$menu_id = (int) ($_POST['menu_id'] ?? 0);
$resep_id = (int) ($_POST['resep_id'] ?? 0);
$jumlah = (float) ($_POST['jumlah'] ?? 0);

if (
    $menu_id <= 0 ||
    $resep_id <= 0 ||
    !is_finite($jumlah) || $jumlah <= 0
) {
    header("Location: resep.php?id=$menu_id");
    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDASI RESEP
|--------------------------------------------------------------------------
*/
$check = mysqli_query($conn, "
    SELECT menu_resep.id, menu_resep.bahan_id, bahan_baku.nama_bahan, bahan_baku.satuan
    FROM menu_resep
    JOIN bahan_baku ON bahan_baku.id = menu_resep.bahan_id
    WHERE menu_resep.id = $resep_id
    AND menu_resep.menu_id = $menu_id
");

if (mysqli_num_rows($check) === 0) {
    header("Location: resep.php?id=$menu_id");
    exit;
}
$recipe = mysqli_fetch_assoc($check);

if (auth_has_role('barista')) {
    submitMenuRequest($conn, 'Ubah Resep', $menu_id, [
        'resep_id' => $resep_id,
        'bahan_id' => (int) $recipe['bahan_id'],
        'nama_bahan' => $recipe['nama_bahan'],
        'satuan' => $recipe['satuan'],
        'jumlah' => $jumlah,
    ]);
    header("Location: permintaan.php?success=" . urlencode('Permintaan edit resep berhasil dikirim.'));
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE
|--------------------------------------------------------------------------
*/
$update = mysqli_query($conn, "
    UPDATE menu_resep
    SET jumlah = '$jumlah'
    WHERE id = $resep_id
");

if (!$update) {
    die(mysqli_error($conn));
}

header("Location: resep.php?id=$menu_id");
exit;
?>
