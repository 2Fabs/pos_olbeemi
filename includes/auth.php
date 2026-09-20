<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function auth_allowed_scripts() {
    return ['login.php', 'logout.php'];
}

function auth_allowed_paths() {
    return [
        '/pos_olbeemi/pemesanan/index.php',
        '/pos_olbeemi/pemesanan/simpan.php',
        '/pos_olbeemi/pemesanan/sukses.php',
    ];
}

function auth_is_allowed_script() {
    if (in_array(basename($_SERVER['SCRIPT_NAME']), auth_allowed_scripts(), true)) {
        return true;
    }

    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';

    return in_array($script_name, auth_allowed_paths(), true);
}

function auth_ensure_users_table($conn) {
    $users_table = mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (!$users_table) {
        die('Gagal membuat tabel users: ' . mysqli_error($conn));
    }

    $role_column = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role'");
    if ($role_column && mysqli_num_rows($role_column) === 0) {
        $add_role = mysqli_query($conn, "
            ALTER TABLE users
            ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'owner'
            AFTER password_hash
        ");

        if (!$add_role) {
            die('Gagal menambah role pengguna: ' . mysqli_error($conn));
        }
    }

    if (!empty($_SESSION['user']['id']) && empty($_SESSION['user']['role'])) {
        $user_id = (int) $_SESSION['user']['id'];
        $role_query = mysqli_query($conn, "SELECT role FROM users WHERE id = $user_id LIMIT 1");
        $role_data = $role_query ? mysqli_fetch_assoc($role_query) : null;
        $_SESSION['user']['role'] = $role_data['role'] ?? '';
    }

}

function auth_current_user() {
    return $_SESSION['user'] ?? null;
}

function auth_is_logged_in() {
    return !empty($_SESSION['user']);
}

function auth_current_role() {
    return strtolower((string) ($_SESSION['user']['role'] ?? ''));
}

function auth_has_role($roles) {
    $roles = (array) $roles;
    return in_array(auth_current_role(), $roles, true);
}

function auth_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function auth_csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(auth_csrf_token(), ENT_QUOTES) . '">';
}

function auth_verify_csrf($token = null) {
    return is_string($token)
        && $token !== ''
        && hash_equals(auth_csrf_token(), $token);
}

function auth_require_post_csrf($redirect = 'index.php') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        header('Location: ' . $redirect);
        exit;
    }
}

function auth_home_path() {
    return '/pos_olbeemi/dashboard/index.php';
}

function auth_path_allowed_for_role($role, $script_name) {
    if ($role === 'owner') {
        return true;
    }

    if ($role === 'kasir') {
        return $script_name === '/pos_olbeemi/dashboard/index.php'
            || strpos($script_name, '/pos_olbeemi/transaksi/') === 0
            || strpos($script_name, '/pos_olbeemi/pesanan/') === 0;
    }

    if ($role === 'barista') {
        return in_array($script_name, [
            '/pos_olbeemi/dashboard/index.php',
            '/pos_olbeemi/pesanan/index.php',
            '/pos_olbeemi/pesanan/detail.php',
            '/pos_olbeemi/pesanan/update_status.php',
            '/pos_olbeemi/menu/index.php',
            '/pos_olbeemi/menu/resep.php',
            '/pos_olbeemi/menu/permintaan.php',
            '/pos_olbeemi/menu/simpan.php',
            '/pos_olbeemi/menu/update.php',
            '/pos_olbeemi/menu/tambah_resep.php',
            '/pos_olbeemi/menu/update_resep.php',
            '/pos_olbeemi/menu/delete_resep.php',
            '/pos_olbeemi/bahan/index.php',
            '/pos_olbeemi/bahan/pesan_supplier.php',
            '/pos_olbeemi/bahan/permintaan_stok.php',
            '/pos_olbeemi/bahan/pengajuan_perubahan_bahan.php',
            '/pos_olbeemi/bahan/riwayat_masuk.php',
            '/pos_olbeemi/bahan/riwayat_keluar.php',
        ], true);
    }

    return false;
}

function auth_require_login() {
    if (auth_is_allowed_script()) {
        return;
    }

    if (!auth_is_logged_in()) {
        header('Location: /pos_olbeemi/login.php');
        exit;
    }

    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    if (!auth_path_allowed_for_role(auth_current_role(), $script_name)) {
        header('Location: ' . auth_home_path());
        exit;
    }
}

function auth_login($conn, $name, $password) {
    $name_trim = trim($name);

    if ($name_trim === '' || $password === '') {
        return false;
    }

    $name_safe = mysqli_real_escape_string($conn, $name_trim);
    $query = mysqli_query($conn, "SELECT * FROM users WHERE name = '$name_safe' LIMIT 1");
    if (!$query) {
        return false;
    }

    if (mysqli_num_rows($query) === 0) {
        return false;
    }

    $user = mysqli_fetch_assoc($query);

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        mysqli_query($conn, "
            UPDATE users
            SET password_hash = '" . mysqli_real_escape_string($conn, $new_hash) . "'
            WHERE id = " . (int) $user['id'] . "
        ");
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'role' => strtolower((string) ($user['role'] ?? '')),
    ];
    $_SESSION['navigation_login_key'] = bin2hex(random_bytes(8));
    unset($_SESSION['csrf_token']);

    return true;
}

function auth_logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}
