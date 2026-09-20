<?php
include '../config/database.php';
include '../includes/menu_requests.php';

auth_require_post_csrf('index.php');

$resep_id = (int) ($_POST['id'] ?? 0);
$menu_id = (int) ($_POST['menu_id'] ?? 0);

if (
    $resep_id <= 0 ||
    $menu_id <= 0
) {
    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDASI RESEP
|--------------------------------------------------------------------------
*/
$check = mysqli_query($conn, "
    SELECT menu_resep.id, bahan_baku.nama_bahan
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
    submitMenuRequest($conn, 'Hapus Resep', $menu_id, [
        'resep_id' => $resep_id,
        'nama_bahan' => $recipe['nama_bahan'],
    ]);
    header("Location: permintaan.php?success=" . urlencode('Permintaan hapus resep berhasil dikirim.'));
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/
$delete = mysqli_query($conn, "
    DELETE FROM menu_resep
    WHERE id = $resep_id
");

if (!$delete) {
    die(mysqli_error($conn));
}

header("Location: resep.php?id=$menu_id");
exit;
?>
