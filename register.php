<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
mulai_sesi();

if (pengguna_login()) {
    alihkan('index.php');
}

$pilihan_peran = ['pelanggan' => 'Pelanggan', 'merchant' => 'Merchant (penjual)', 'driver' => 'Driver (kurir)'];
$galat = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $peran = (string) ($_POST['peran'] ?? '');

    if ($nama === '' || !preg_match('/^[A-Za-z0-9_]{4,50}$/', $username) || !isset($pilihan_peran[$peran])) {
        $galat = 'Nama dan peran wajib diisi; username 4-50 karakter (huruf, angka, garis bawah).';
    } elseif (strlen($password) < 6 || $password !== (string) ($_POST['konfirmasi'] ?? '')) {
        $galat = 'Kata sandi minimal 6 karakter dan harus sama dengan konfirmasi.';
    } elseif (cari_pengguna($conn, true, $username)) {
        $galat = 'Username sudah dipakai.';
    } else {
        // Pengguna pertama otomatis menjadi admin.
        if ((int) ambil_satu($conn, 'SELECT COUNT(*) AS n FROM pengguna')['n'] === 0) {
            $peran = 'admin';
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (eksekusi($conn, 'INSERT INTO pengguna (nama, username, password, peran) VALUES (?, ?, ?, ?)', 'ssss', [$nama, $username, $hash, $peran]) > 0) {
            masuk_sesi(['id_pengguna' => mysqli_insert_id($conn), 'nama' => $nama, 'peran' => $peran]);
            alihkan(['admin' => 'admin.php', 'merchant' => 'merchant.php', 'driver' => 'riwayat.php'][$peran] ?? 'index.php');
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
        <?= input_csrf() ?>
        <label class="form-label" for="nama">Nama</label>
        <input class="form-control mb-3" id="nama" name="nama" required value="<?= e($_POST['nama'] ?? '') ?>">
        <label class="form-label" for="username">Username</label>
        <input class="form-control mb-3" id="username" name="username" required pattern="[A-Za-z0-9_]{4,50}" value="<?= e($_POST['username'] ?? '') ?>">
        <label class="form-label" for="peran">Daftar sebagai</label>
        <select class="form-select mb-3" id="peran" name="peran">
            <?php foreach ($pilihan_peran as $nilai => $label): ?>
                <option value="<?= e($nilai) ?>" <?= ($_POST['peran'] ?? '') === $nilai ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="form-label" for="password">Kata sandi</label>
        <input class="form-control mb-3" id="password" type="password" name="password" required minlength="6">
        <label class="form-label" for="konfirmasi">Ulangi kata sandi</label>
        <input class="form-control mb-3" id="konfirmasi" type="password" name="konfirmasi" required>
        <button class="btn btn-danger">Daftar</button>
    </form>
    <p class="mt-3 text-center">Sudah punya akun? <a href="login.php">Masuk</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
