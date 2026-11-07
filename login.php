<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
mulai_sesi();

if (pengguna_login()) {
    alihkan('index.php');
}

$galat = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $user = ambil_satu($conn, 'SELECT * FROM pengguna WHERE username = ?', 's', [$username]);
    if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password'])) {
        masuk_sesi($user);
        alihkan($user['peran'] === 'admin' ? 'admin.php' : 'index.php');
    }
    $galat = 'Username atau kata sandi salah.';
}

$judul = 'Masuk';
require __DIR__ . '/includes/header.php';
?>
<div class="mx-auto" style="max-width: 420px;">
    <h1 class="h4 mb-3">Masuk</h1>
    <?php if ($galat): ?><div class="alert alert-danger"><?= e($galat) ?></div><?php endif; ?>
    <form method="post" class="card card-body">
        <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
        <label class="form-label" for="username">Username</label>
        <input class="form-control mb-3" id="username" name="username" required autofocus>
        <label class="form-label" for="password">Kata sandi</label>
        <input class="form-control mb-3" id="password" type="password" name="password" required>
        <button class="btn btn-danger">Masuk</button>
    </form>
    <p class="mt-3 text-center">Belum punya akun? <a href="register.php">Daftar</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
