<?php
include '../config/database.php';
include '../includes/menu_requests.php';

auth_require_post_csrf('index.php');

$nama_menu = trim($_POST['nama_menu'] ?? '');
$nama_menu_raw = $nama_menu;
$kategori = trim($_POST['kategori'] ?? '');
$is_barista = auth_has_role('barista');
$harga_jual = (float) ($_POST['harga_jual'] ?? 0);
$keuntungan = (float) ($_POST['keuntungan'] ?? 0);

$allowed_kategori = ['Coffee', 'Non Coffee'];

if (
    empty($nama_menu) ||
    !in_array($kategori, $allowed_kategori) ||
    ($is_barista && (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK || empty($_FILES['foto']['name']))) ||
    (!$is_barista && ($harga_jual <= 0 || $keuntungan < 0))
) {
    header('Location: index.php?error=' . urlencode($is_barista ? 'Nama, kategori, dan foto menu wajib diisi.' : 'Nama menu dan kategori wajib diisi dengan benar.'));
    exit;
}

$nama_menu = mysqli_real_escape_string($conn, $nama_menu);

$cek = mysqli_query($conn, "
    SELECT id
    FROM menu
    WHERE nama_menu = '$nama_menu'
");

if (mysqli_num_rows($cek) > 0) {
    header('Location: index.php?error=' . urlencode('Nama menu sudah digunakan.'));
    exit;
}

if ($is_barista) {
    $pending_names = mysqli_query($conn, "SELECT data_json FROM permintaan_menu WHERE jenis = 'Tambah Menu' AND status = 'Menunggu'");
    while ($pending_names && $pending_request = mysqli_fetch_assoc($pending_names)) {
        $pending_data = json_decode($pending_request['data_json'], true) ?: [];
        if (mb_strtolower(trim($pending_data['nama_menu'] ?? '')) === mb_strtolower($nama_menu_raw)) {
            header('Location: index.php?error=' . urlencode('Nama menu sedang diajukan dan menunggu persetujuan Owner.'));
            exit;
        }
    }
}

$upload_dir = __DIR__ . '/uploads/';
$foto_name = null;

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (
    isset($_FILES['foto']) &&
    $_FILES['foto']['error'] === 0 &&
    !empty($_FILES['foto']['name'])
) {
    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($ext, $allowed_ext)) {
        $foto_name = time() . '_' . uniqid() . '.' . $ext;

        $moved = move_uploaded_file(
            $_FILES['foto']['tmp_name'],
            $upload_dir . $foto_name
        );

        if (!$moved) {
            die('Upload foto gagal.');
        }
    }
}

if ($is_barista) {
    $recipe_ids = array_map('intval', (array) ($_POST['resep_bahan_id'] ?? []));
    $recipe_amounts = array_map('floatval', (array) ($_POST['resep_jumlah'] ?? []));
    if (!$recipe_ids || count($recipe_ids) !== count($recipe_amounts)) {
        if ($foto_name && is_file($upload_dir . $foto_name)) unlink($upload_dir . $foto_name);
        header('Location: index.php?error=' . urlencode('Minimal satu bahan resep beserta jumlahnya wajib diisi.'));
        exit;
    }
    $recipe_items = [];
    foreach ($recipe_ids as $index => $recipe_id) {
        $recipe_items[] = ['bahan_id' => $recipe_id, 'jumlah' => $recipe_amounts[$index] ?? 0];
    }
    try {
        $recipe_items = validateMenuRecipeItems($conn, $recipe_items);
    } catch (Exception $exception) {
        if ($foto_name && is_file($upload_dir . $foto_name)) unlink($upload_dir . $foto_name);
        header('Location: index.php?error=' . urlencode($exception->getMessage()));
        exit;
    }
    submitMenuRequest($conn, 'Tambah Menu', 0, [
        'nama_menu' => $nama_menu_raw,
        'kategori' => $kategori,
        'foto' => $foto_name,
        'status' => 'Aktif',
        'resep_items' => $recipe_items,
    ]);
    header('Location: permintaan.php?tab=menunggu&success=' . urlencode('Pengajuan tambah menu beserta resep berhasil dikirim.'));
    exit;
}

$insert = mysqli_query($conn, "
    INSERT INTO menu (
        nama_menu,
        kategori,
        harga_jual,
        keuntungan,
        foto,
        status
    ) VALUES (
        '$nama_menu',
        '$kategori',
        '$harga_jual',
        '$keuntungan',
        " . ($foto_name ? "'$foto_name'" : "NULL") . ",
        'Aktif'
    )
");

if (!$insert) {
    die(mysqli_error($conn));
}

header("Location: index.php");
exit;
?>
