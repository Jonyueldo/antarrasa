<?php
declare(strict_types=1);

/**
 * Halaman Pendaftaran Akun Pelanggan Baru (D4: PENGGUNA)
 * Super-App Split-Screen Modern UI/UX
 * Khusus untuk registrasi dengan peran 'customer'
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions_auth.php';
require_once __DIR__ . '/includes/functions_order.php';

mulai_sesi();

// Jika sudah login, redirect ke halaman utama
if (apakah_login()) {
    header("Location: index.php");
    exit;
}

$pesan_error = '';
$nama_lengkap = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = (string) ($_POST['nama_lengkap'] ?? '');
    $username     = (string) ($_POST['username'] ?? '');
    $password     = (string) ($_POST['password'] ?? '');
    $konfirmasi   = (string) ($_POST['konfirmasi_password'] ?? '');

    if ($password !== $konfirmasi) {
        $pesan_error = 'Konfirmasi kata sandi tidak cocok dengan kata sandi yang Anda masukkan.';
    } else {
        $hasil_reg = registrasi_customer($conn, $username, $password, $nama_lengkap);

        if ($hasil_reg['sukses']) {
            // Otomatis login akun baru
            login_pengguna($conn, $username, $password);
            header("Location: index.php?registrasi_sukses=1");
            exit;
        } else {
            $pesan_error = $hasil_reg['pesan'];
        }
    }
}

$judul_halaman = 'Daftar Akun Customer Baru - AntarRasa';
$tampilkan_stepper = false;

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-split-wrapper">
    <!-- HERO SEBELAH KIRI (CUSTOMER BENEFIT & BRANDING) -->
    <div class="auth-split-hero">
        <div>
            <a href="index.php" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-4">
                <div class="p-2 rounded-circle bg-danger text-white d-inline-flex shadow">
                    <i class="bi bi-egg-fried fs-3"></i>
                </div>
                <span class="fs-3 fw-extrabold text-white">AntarRasa</span>
                <span class="badge bg-warning text-dark fw-bold rounded-pill ms-1" style="font-size: 0.75rem;">Customer</span>
            </a>

            <div class="mt-4" style="max-width: 520px;">
                <span class="badge bg-danger-subtle text-danger fw-bold px-3 py-1 mb-3 rounded-pill" style="font-size: 0.78rem;">
                    <i class="bi bi-gift-fill me-1"></i> Promo Khusus Pengguna Baru
                </span>
                <h1 class="display-5 fw-extrabold text-white mb-3" style="letter-spacing: -1px; line-height: 1.15;">
                    Nikmati Hidangan Favorit Tanpa Antre.
                </h1>
                <p class="text-secondary lead fs-6 mb-4">
                    Daftar akun sekarang untuk mulai memesan aneka menu masakan nusantara pilihan. Pesanan Anda akan langsung dimasak hangat dan diantarkan cepat oleh kurir.
                </p>

                <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-2 rounded-circle bg-success-subtle text-success d-inline-flex">
                            <i class="bi bi-check-lg fs-5"></i>
                        </div>
                        <div class="text-white small">Akses instan ke seluruh katalog hidangan restoran autentik</div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-2 rounded-circle bg-success-subtle text-success d-inline-flex">
                            <i class="bi bi-check-lg fs-5"></i>
                        </div>
                        <div class="text-white small">Pelacakan status pesanan real-time dari dapur hingga depan rumah</div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-2 rounded-circle bg-success-subtle text-success d-inline-flex">
                            <i class="bi bi-check-lg fs-5"></i>
                        </div>
                        <div class="text-white small">Simpan riwayat pesanan otomatis ke DataStore D2 &amp; D4</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-4 border-top border-white border-opacity-10 d-flex justify-content-between align-items-center text-secondary small">
            <div>&copy; <?= date('Y') ?> AntarRasa Indonesia. IEEE Std 830-1998</div>
            <a href="login.php" class="text-white text-decoration-none small">
                Sudah punya akun? Masuk <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>

    <!-- FORM SEBELAH KANAN (REGISTRASI CUSTOMER) -->
    <div class="auth-split-form-wrap">
        <div class="mb-4">
            <h3 class="fw-extrabold text-dark mb-1">Daftar Akun Baru</h3>
            <p class="text-muted small mb-0">Isi data diri Anda untuk membuat akun pelanggan AntarRasa.</p>
        </div>

        <?php if ($pesan_error !== ''): ?>
            <div class="alert alert-danger d-flex align-items-center py-2 px-3 small rounded-3 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-danger"></i>
                <div><?= e($pesan_error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="mb-3">
                <label for="nama_lengkap" class="form-label small fw-bold text-dark text-uppercase">Nama Lengkap</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-person-badge text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="nama_lengkap" name="nama_lengkap" value="<?= e($nama_lengkap) ?>" placeholder="Masukkan nama lengkap Anda..." required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label for="username" class="form-label small fw-bold text-dark text-uppercase">Nama Pengguna (Username)</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-at text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" value="<?= e($username) ?>" placeholder="Masukkan username unik..." required>
                </div>
                <div class="text-muted" style="font-size: 0.72rem;">Hanya huruf, angka, dan garis bawah (_), min. 4 karakter.</div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label small fw-bold text-dark text-uppercase">Kata Sandi</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="Minimal 6 karakter" required>
                </div>
            </div>

            <div class="mb-4">
                <label for="konfirmasi_password" class="form-label small fw-bold text-dark text-uppercase">Ulangi Kata Sandi</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-shield-lock text-muted"></i></span>
                    <input type="password" class="form-control border-start-0 ps-0" id="konfirmasi_password" name="konfirmasi_password" placeholder="Ketik ulang kata sandi" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary-custom w-100 py-3 fw-bold rounded-pill shadow-sm mb-3">
                <i class="bi bi-check-circle-fill me-1"></i> Buat Akun &amp; Mulai Pesan
            </button>

            <div class="text-center small text-muted">
                Sudah memiliki akun? <a href="login.php" class="fw-bold text-danger text-decoration-none">Masuk di sini</a>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
