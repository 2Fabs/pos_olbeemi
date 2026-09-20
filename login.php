<?php
include 'config/database.php';

auth_ensure_users_table($conn);

if (auth_is_logged_in()) {
    header('Location: ' . auth_home_path());
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $password === '') {
        $error = 'Nama dan password wajib diisi.';
    } elseif (auth_login($conn, $name, $password)) {
        header('Location: ' . auth_home_path());
        exit;
    } else {
        $error = 'Nama atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login POS Olbeemi</title>
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
</head>
<body>
<div class="login-page">
    <div class="login-photos" aria-hidden="true">
        <div class="login-photo login-photo-left">
            <img src="images/OlbeemiLatarBelakang2.jpeg" alt="">
        </div>
        <div class="login-photo login-photo-center">
            <img src="images/OlbeemiLatarBelakang.jpeg" alt="">
        </div>
        <div class="login-photo login-photo-right">
            <img src="images/OlbeemiLatarBelakang3.jpeg" alt="">
        </div>
    </div>

    <div class="login-card">
        <div class="login-card-header">
            <img src="images/logo.png" alt="Logo Olbeemi" class="login-card-logo">
            <h1>Welcome</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <label>
                Nama
                <input type="text" name="name" placeholder="Masukkan nama pengguna" required>
            </label>

            <label>
                Password
                <span class="password-field">
                    <input type="password" name="password" id="passwordInput" placeholder="Masukkan password" required>
                    <button type="button" class="password-toggle" id="passwordToggle" aria-label="Tampilkan password" aria-pressed="false">
                        <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                            <circle cx="12" cy="12" r="2.75" />
                        </svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 3l18 18" />
                            <path d="M10.6 6.2A9.7 9.7 0 0 1 12 6c6 0 9.5 6 9.5 6a15 15 0 0 1-2.2 2.9M6.2 6.2C3.8 7.9 2.5 12 2.5 12s3.5 6 9.5 6a9.6 9.6 0 0 0 4.1-.9" />
                            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                        </svg>
                    </button>
                </span>
            </label>

            <button type="submit" class="btn-primary">Masuk</button>
        </form>
    </div>
</div>
<script>
const passwordInput = document.getElementById('passwordInput');
const passwordToggle = document.getElementById('passwordToggle');

passwordToggle.addEventListener('click', function () {
    const showPassword = passwordInput.type === 'password';

    passwordInput.type = showPassword ? 'text' : 'password';
    passwordToggle.classList.toggle('is-visible', showPassword);
    passwordToggle.setAttribute('aria-label', showPassword ? 'Sembunyikan password' : 'Tampilkan password');
    passwordToggle.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
});
</script>
</body>
</html>
