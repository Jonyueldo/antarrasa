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
    $u = cari_pengguna($conn, true, trim((string) ($_POST['email'] ?? '')));
    if (!$u || !password_verify((string) ($_POST['password'] ?? ''), $u['password'])) {
        $galat = 'Email atau kata sandi salah.';
    } elseif ($blokir = pesan_blokir($u)) {
        $galat = $blokir;
    } else {
        masuk_sesi($u);
        alihkan(['admin' => 'admin.php', 'merchant' => 'merchant.php', 'driver' => 'riwayat.php'][$u['peran']] ?? 'index.php');
    }
}

$judul = 'Masuk';
require __DIR__ . '/includes/header.php';
?>
<div class="mx-auto" style="max-width: 420px;">
    <h1 class="h4 mb-3">Masuk</h1>
    <?php if ($galat): ?><div class="alert alert-danger"><?= e($galat) ?></div><?php endif; ?>
    <form method="post" class="card card-body">
        <?= input_csrf() ?>
        <label class="form-label" for="email">Email</label>
        <input class="form-control mb-3" id="email" type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
        <label class="form-label" for="password">Kata sandi</label>
        <input class="form-control mb-3" id="password" type="password" name="password" required>
        <button class="btn btn-danger">Masuk</button>
    </form>
    <p class="mt-3 text-center">Belum punya akun? <a href="register.php">Daftar</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
