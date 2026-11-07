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
    $nama = trim((string) ($_POST['nama_lengkap'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($nama === '' || !preg_match('/^[A-Za-z0-9_]{4,30}$/', $username)) {
        $galat = 'Nama wajib diisi dan username 4-30 karakter (huruf, angka, garis bawah).';
    } elseif (strlen($password) < 6 || $password !== (string) ($_POST['konfirmasi'] ?? '')) {
        $galat = 'Kata sandi minimal 6 karakter dan harus sama dengan konfirmasi.';
    } elseif (ambil_satu($conn, 'SELECT 1 FROM pengguna WHERE username = ?', 's', [$username])) {
        $galat = 'Username sudah dipakai.';
    } else {
        // Pengguna pertama otomatis menjadi admin; selanjutnya pelanggan.
        $jumlah = (int) ambil_satu($conn, 'SELECT COUNT(*) AS n FROM pengguna')['n'];
        $peran = $jumlah === 0 ? 'admin' : 'pelanggan';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (eksekusi($conn, 'INSERT INTO pengguna (username, password, nama_lengkap, peran) VALUES (?, ?, ?, ?)', 'ssss', [$username, $hash, $nama, $peran]) > 0) {
            masuk_sesi(['id_pengguna' => mysqli_insert_id($conn), 'nama_lengkap' => $nama, 'peran' => $peran]);
            alihkan($peran === 'admin' ? 'admin.php' : 'index.php');
        }
        $galat = 'Pendaftaran gagal, coba lagi.';
    }
}

$judul = 'Daftar';
require __DIR__ . '/includes/header.php';
?>
<div class="mx-auto" style="max-width: 420px;">
    <h1 class="h4 mb-3">Daftar Akun</h1>
    <?php if ($galat): ?><div class="alert alert-danger"><?= e($galat) ?></div><?php endif; ?>
    <form method="post" class="card card-body">
        <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
        <label class="form-label" for="nama_lengkap">Nama lengkap</label>
        <input class="form-control mb-3" id="nama_lengkap" name="nama_lengkap" required value="<?= e($_POST['nama_lengkap'] ?? '') ?>">
        <label class="form-label" for="username">Username</label>
        <input class="form-control mb-3" id="username" name="username" required value="<?= e($_POST['username'] ?? '') ?>">
        <label class="form-label" for="password">Kata sandi</label>
        <input class="form-control mb-3" id="password" type="password" name="password" required minlength="6">
        <label class="form-label" for="konfirmasi">Ulangi kata sandi</label>
        <input class="form-control mb-3" id="konfirmasi" type="password" name="konfirmasi" required>
        <button class="btn btn-danger">Daftar</button>
    </form>
    <p class="mt-3 text-center">Sudah punya akun? <a href="login.php">Masuk</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
