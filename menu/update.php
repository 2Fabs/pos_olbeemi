<?php
include '../config/database.php';
include '../includes/menu_requests.php';

auth_require_post_csrf('index.php');

$id = (int) ($_POST['id'] ?? 0);
$nama_menu = trim($_POST['nama_menu'] ?? '');
$nama_menu_raw = $nama_menu;
$kategori = trim($_POST['kategori'] ?? '');
$is_barista = auth_has_role('barista');
$harga_jual = (float) ($_POST['harga_jual'] ?? 0);
$keuntungan = (float) ($_POST['keuntungan'] ?? 0);
$status = trim($_POST['status'] ?? '');
$edit_menu_only = auth_has_role('owner') && ($_POST['edit_menu_only'] ?? '') === '1';
if ($edit_menu_only) {
    $pricing_query = mysqli_query($conn, "SELECT harga_jual, keuntungan FROM menu WHERE id = $id");
    $pricing = $pricing_query ? mysqli_fetch_assoc($pricing_query) : null;
    if (!$pricing) { header('Location: index.php'); exit; }
    $harga_jual = (float) $pricing['harga_jual'];
    $keuntungan = (float) $pricing['keuntungan'];
    if ($harga_jual <= 0) $status = 'Nonaktif';
}

$allowed_kategori = ['Coffee', 'Non Coffee'];
$allowed_status = ['Aktif', 'Nonaktif'];

if (
    $id <= 0 ||
    empty($nama_menu) ||
    !in_array($kategori, $allowed_kategori) ||
    !in_array($status, $allowed_status) ||
    (!$is_barista && !$edit_menu_only && ($harga_jual <= 0 || $keuntungan < 0))
) {
    header("Location: index.php");
    exit;
}

$nama_menu = mysqli_real_escape_string($conn, $nama_menu);

$cek = mysqli_query($conn, "
    SELECT id
    FROM menu
    WHERE nama_menu = '$nama_menu'
    AND id != $id
");

if (mysqli_num_rows($cek) > 0) {
    header("Location: index.php");
    exit;
}

$query = mysqli_query($conn, "
    SELECT *
    FROM menu
    WHERE id = $id
");

$menu = mysqli_fetch_assoc($query);

if (!$menu) {
    header("Location: index.php");
    exit;
}

$upload_dir = __DIR__ . '/uploads/';
$foto_name = $menu['foto'];

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
        if (!auth_has_role('barista') &&
            !empty($foto_name) &&
            file_exists($upload_dir . $foto_name)
        ) {
            unlink($upload_dir . $foto_name);
        }

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
    submitMenuRequest($conn, 'Ubah Menu', $id, [
        'nama_menu' => $nama_menu_raw,
        'kategori' => $kategori,
        'foto' => $foto_name,
        'foto_baru' => $foto_name !== $menu['foto'],
        'status' => $status,
    ]);
    $request_message = $status === 'Nonaktif' && $menu['status'] === 'Aktif'
        ? 'Pengajuan penonaktifan menu berhasil dikirim.'
        : 'Pengajuan edit menu berhasil dikirim.';
    header('Location: permintaan.php?tab=menunggu&success=' . urlencode($request_message));
    exit;
}

$update = mysqli_query($conn, "
    UPDATE menu
    SET
        nama_menu = '$nama_menu',
        kategori = '$kategori',
        harga_jual = '$harga_jual',
        keuntungan = '$keuntungan',
        foto = " . ($foto_name ? "'$foto_name'" : "NULL") . ",
        status = '$status'
    WHERE id = $id
");

if (!$update) {
    die(mysqli_error($conn));
}

header("Location: index.php");
exit;
?>
