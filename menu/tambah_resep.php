<?php
include '../config/database.php';
include '../includes/menu_requests.php';

auth_require_post_csrf('index.php');

$menu_id = (int) ($_POST['menu_id'] ?? 0);
$bahan_ids = array_map('intval', (array) ($_POST['bahan_id'] ?? []));
$jumlahs = array_map('floatval', (array) ($_POST['jumlah'] ?? []));
$rows = [];
foreach ($bahan_ids as $index => $bahan_id) {
    $jumlah = $jumlahs[$index] ?? 0;
    if ($bahan_id > 0 && $jumlah > 0) $rows[$bahan_id] = $jumlah;
}

if (
    $menu_id <= 0 ||
    empty($rows)
) {
    header("Location: resep.php?id=$menu_id");
    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDASI MENU
|--------------------------------------------------------------------------
*/
$menu_check = mysqli_query($conn, "
    SELECT id
    FROM menu
    WHERE id = $menu_id
");

if (mysqli_num_rows($menu_check) === 0) {
    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDASI BAHAN
|--------------------------------------------------------------------------
*/
if (auth_has_role('barista')) {
    $items = [];
    foreach ($rows as $bahan_id => $jumlah) {
        $bahan_check = mysqli_query($conn, "SELECT id, nama_bahan, satuan FROM bahan_baku WHERE id = $bahan_id");
        $bahan = $bahan_check ? mysqli_fetch_assoc($bahan_check) : null;
        if (!$bahan) continue;
        $items[] = ['bahan_id' => $bahan_id, 'nama_bahan' => $bahan['nama_bahan'], 'satuan' => $bahan['satuan'], 'jumlah' => $jumlah];
    }
    if ($items) submitMenuRequest($conn, 'Tambah Resep', $menu_id, ['resep_items' => $items] + $items[0]);
    header("Location: permintaan.php?success=" . urlencode('Permintaan tambah resep berhasil dikirim.'));
    exit;
}

foreach ($rows as $bahan_id => $jumlah) {
    $duplicate = mysqli_query($conn, "SELECT id FROM menu_resep WHERE menu_id = $menu_id AND bahan_id = $bahan_id");
    if ($duplicate && mysqli_num_rows($duplicate) > 0) continue;
    $insert = mysqli_query($conn, "INSERT INTO menu_resep (menu_id, bahan_id, jumlah) VALUES ($menu_id, $bahan_id, '$jumlah')");
    if (!$insert) die(mysqli_error($conn));
}

header("Location: resep.php?id=$menu_id");
exit;
?>
