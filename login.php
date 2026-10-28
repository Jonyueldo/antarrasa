<?php
declare(strict_types=1);

/**
 * Halaman Masuk / Login Multi-Peran (D4: PENGGUNA)
 * Super-App Split-Screen Modern UI/UX
 * Mendukung autentikasi Customer, Merchant, Driver, dan Admin
 * Dilengkapi 4 kartu klik-cepat yang membaca langsung data pengguna asli dari database MySQL
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions_auth.php';
require_once __DIR__ . '/includes/functions_order.php';

mulai_sesi();

$ref = isset($_GET['ref']) ? (string) $_GET['ref'] : (isset($_POST['ref']) ? (string) $_POST['ref'] : '');
// Cegah open redirect: pastikan hanya path lokal
if ($ref !== '' && (!str_starts_with($ref, '/') && !preg_match('/^[a-zA-Z0-9_\-\.\/]+\.php(\?.*)?$/', $ref))) {
    $ref = '';
}

// Jika sudah login, langsung arahkan ke dashboard yang sesuai
if (apakah_login()) {
    $peran = peran_pengguna_saat_ini();
    if ($ref !== '') {
        header("Location: {$ref}");
        exit;
    }
    match ($peran) {
        'merchant' => header("Location: merchant.php"),
        'driver'   => header("Location: driver.php"),
        'admin'    => header("Location: admin.php"),
        default    => header("Location: index.php"),
    };
    exit;
}

// Ambil akun pengguna asli per peran dari tabel PENGGUNA (DataStore D4)
$akun_peran = [
    'customer' => null,
    'merchant' => null,
    'driver'   => null,
    'admin'    => null,
];

$res_pengguna = mysqli_query($conn, "SELECT id_pengguna, username, nama_lengkap, peran FROM PENGGUNA ORDER BY id_pengguna ASC");
if ($res_pengguna !== false) {
    while ($row = mysqli_fetch_assoc($res_pengguna)) {
        $p = (string)$row['peran'];
        if (isset($akun_peran[$p]) && $akun_peran[$p] === null) {
            $akun_peran[$p] = $row;
        }
    }
    mysqli_free_result($res_pengguna);
}

$pesan_error = '';
$pesan_sukses = '';

// Pesan dari redirect
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'wajib_login') {
        $pesan_error = 'Silakan masuk terlebih dahulu untuk mengakses halaman tersebut.';
    } elseif ($_GET['pesan'] === 'akses_ditolak') {
        $pesan_error = 'Anda tidak memiliki hak akses (peran) untuk membuka halaman yang dituju.';
    } elseif ($_GET['pesan'] === 'registrasi_sukses') {
        $pesan_sukses = 'Akun customer Anda berhasil didaftarkan! Silakan masuk menggunakan kata sandi Anda.';
    } elseif ($_GET['pesan'] === 'logout') {
        $pesan_sukses = 'Anda telah berhasil keluar dari sistem.';
    }
}

// 1. Tangani Aksi Login Form Biasa atau Demo Quick-Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_input = '';
    $password_input = '';

    if (isset($_POST['aksi']) && $_POST['aksi'] === 'quick_demo') {
        // Tombol cepat demo: Ambil username akun asli dari tabel PENGGUNA
        $role_pilihan = (string) ($_POST['akun_demo'] ?? '');
        if (isset($akun_peran[$role_pilihan]) && $akun_peran[$role_pilihan] !== null) {
            $username_input = (string)$akun_peran[$role_pilihan]['username'];
            $password_input = 'password123';
        } else {
            $username_input = $role_pilihan;
            $password_input = 'password123';
        }
    } else {
        $username_input = (string) ($_POST['username'] ?? '');
        $password_input = (string) ($_POST['password'] ?? '');
    }

    if ($username_input !== '' && $password_input !== '') {
        $user_login = login_pengguna($conn, $username_input, $password_input);
        if ($user_login !== false) {
            // Arahkan ke halaman tujuan sesuai peran atau referensi
            if ($ref !== '') {
                header("Location: {$ref}");
                exit;
            }

            match ($user_login['peran']) {
                'merchant' => header("Location: merchant.php"),
                'driver'   => header("Location: driver.php"),
                'admin'    => header("Location: admin.php"),
                default    => header("Location: index.php"),
            };
            exit;
        } else {
            $pesan_error = 'Nama pengguna atau kata sandi tidak valid. Silakan periksa kembali.';
        }
    } else {
        $pesan_error = 'Mohon masukkan nama pengguna dan kata sandi.';
    }
}

$judul_halaman = 'Masuk ke Ekosistem AntarRasa';
$tampilkan_stepper = false;

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-split-wrapper">
    <!-- HERO SEBELAH KIRI (BRANDING & SHOWCASE EKOSISTEM) -->
    <div class="auth-split-hero">
        <div>
            <!-- Brand Badge -->
            <a href="index.php" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-4">
                <div class="p-2 rounded-circle bg-danger text-white d-inline-flex shadow">
                    <i class="bi bi-egg-fried fs-3"></i>
                </div>
                <span class="fs-3 fw-extrabold text-white">AntarRasa</span>
                <span class="badge bg-warning text-dark fw-bold rounded-pill ms-1" style="font-size: 0.75rem;">Super-App</span>
            </a>

            <div class="mt-4" style="max-width: 540px;">
                <span class="badge bg-danger-subtle text-danger fw-bold px-3 py-1 mb-3 rounded-pill" style="font-size: 0.78rem;">
                    <i class="bi bi-cpu-fill me-1"></i> PHP 8.5 Murni Prosedural &bull; IEEE Std 830-1998
                </span>
                <h1 class="display-5 fw-extrabold text-white mb-3" style="letter-spacing: -1px; line-height: 1.15;">
                    Satu Ekosistem Kuliner untuk Semua Peran.
                </h1>
                <p class="text-secondary lead fs-6 mb-4">
                    Platform pengantaran makanan online lengkap dengan integrasi 4 peran pengguna pada DataStore D4: Pelanggan, Pemilik Resto, Kurir Pengantar, dan Administrator.
                </p>

                <!-- Grid 4 Pilar Peran -->
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-10">
                            <i class="bi bi-bag-heart-fill text-danger fs-4 mb-2 d-block"></i>
                            <div class="fw-bold text-white small">Customer Portal</div>
                            <div class="text-secondary small" style="font-size: 0.72rem;">Katalog &amp; tracking real-time</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-10">
                            <i class="bi bi-shop text-warning fs-4 mb-2 d-block"></i>
                            <div class="fw-bold text-white small">Merchant Kitchen</div>
                            <div class="text-secondary small" style="font-size: 0.72rem;">Kelola menu &amp; antrean dapur</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-10">
                            <i class="bi bi-bicycle text-info fs-4 mb-2 d-block"></i>
                            <div class="fw-bold text-white small">Driver Courier</div>
                            <div class="text-secondary small" style="font-size: 0.72rem;">Jemput &amp; antar ke lokasi</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-10">
                            <i class="bi bi-shield-lock-fill text-success fs-4 mb-2 d-block"></i>
                            <div class="fw-bold text-white small">Executive Admin</div>
                            <div class="text-secondary small" style="font-size: 0.72rem;">Kontrol CRUD &amp; pemantauan</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-4 border-top border-white border-opacity-10 d-flex justify-content-between align-items-center text-secondary small">
            <div>&copy; <?= date('Y') ?> AntarRasa Indonesia. All rights reserved.</div>
            <a href="index.php" class="text-white text-decoration-none small">
                <i class="bi bi-arrow-left me-1"></i> Buka Website
            </a>
        </div>
    </div>

    <!-- FORM SEBELAH KANAN (INPUT & TOMBOL QUICK DEMO) -->
    <div class="auth-split-form-wrap">
        <div class="mb-4">
            <h3 class="fw-extrabold text-dark mb-1">Masuk ke Akun</h3>
            <p class="text-muted small mb-0">Silakan masukkan nama pengguna atau gunakan tombol akun demo di bawah.</p>
        </div>

        <?php if ($pesan_error !== ''): ?>
            <div class="alert alert-danger d-flex align-items-center py-2 px-3 small rounded-3 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-danger"></i>
                <div><?= e($pesan_error) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($pesan_sukses !== ''): ?>
            <div class="alert alert-success d-flex align-items-center py-2 px-3 small rounded-3 mb-3" role="alert">
                <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
                <div><?= e($pesan_sukses) ?></div>
            </div>
        <?php endif; ?>

        <!-- Form Autentikasi Standard -->
        <form method="POST" action="login.php" class="mb-4">
            <input type="hidden" name="ref" value="<?= e($ref) ?>">

            <div class="mb-3">
                <label for="username" class="form-label small fw-bold text-dark text-uppercase">Nama Pengguna (Username)</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" placeholder="Masukkan nama pengguna..." required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label small fw-bold text-dark text-uppercase mb-0">Kata Sandi</label>
                    <span class="text-muted small" style="font-size: 0.72rem;">Default demo: <code>password123</code></span>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="Masukkan kata sandi..." required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary-custom w-100 py-3 fw-bold rounded-pill shadow-sm mb-3">
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
            </button>

            <div class="text-center small text-muted">
                Belum memiliki akun? <a href="register.php" class="fw-bold text-danger text-decoration-none">Daftar Akun Customer</a>
            </div>
        </form>

        <!-- 4 QUICK-LOGIN DEMO BUTTONS (MURNI MEMBACA DATA ASLI DARI TABEL PENGGUNA D4) -->
        <div class="pt-3 border-top">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="fw-bold text-dark small">
                    <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Quick Demo 1-Klik (DataStore D4)
                </div>
                <span class="text-muted" style="font-size: 0.72rem;">Klik untuk langsung masuk</span>
            </div>

            <div class="row g-2">
                <!-- Customer Demo -->
                <div class="col-6">
                    <?php if ($akun_peran['customer'] !== null): ?>
                        <form method="POST" action="login.php">
                            <input type="hidden" name="ref" value="<?= e($ref) ?>">
                            <input type="hidden" name="aksi" value="quick_demo">
                            <input type="hidden" name="akun_demo" value="customer">
                            <button type="submit" class="demo-btn-card">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-success-subtle text-success p-1 rounded"><i class="bi bi-person-fill"></i></span>
                                    <div class="text-truncate">
                                        <div class="fw-bold text-dark small" style="line-height: 1.1;">Customer</div>
                                        <small class="text-muted text-truncate d-block" style="font-size: 0.7rem;"><?= e($akun_peran['customer']['nama_lengkap']) ?></small>
                                    </div>
                                </div>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="demo-btn-card opacity-50">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-secondary-subtle text-secondary p-1 rounded"><i class="bi bi-person-x"></i></span>
                                <div>
                                    <div class="fw-bold text-secondary small" style="line-height: 1.1;">Customer</div>
                                    <small class="text-muted" style="font-size: 0.7rem;">Belum ada akun</small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Merchant Demo -->
                <div class="col-6">
                    <?php if ($akun_peran['merchant'] !== null): ?>
                        <form method="POST" action="login.php">
                            <input type="hidden" name="ref" value="<?= e($ref) ?>">
                            <input type="hidden" name="aksi" value="quick_demo">
                            <input type="hidden" name="akun_demo" value="merchant">
                            <button type="submit" class="demo-btn-card">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-warning-subtle text-warning p-1 rounded"><i class="bi bi-shop"></i></span>
                                    <div class="text-truncate">
                                        <div class="fw-bold text-dark small" style="line-height: 1.1;">Merchant</div>
                                        <small class="text-muted text-truncate d-block" style="font-size: 0.7rem;"><?= e($akun_peran['merchant']['nama_lengkap']) ?></small>
                                    </div>
                                </div>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="demo-btn-card opacity-50">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-secondary-subtle text-secondary p-1 rounded"><i class="bi bi-shop"></i></span>
                                <div>
                                    <div class="fw-bold text-secondary small" style="line-height: 1.1;">Merchant</div>
                                    <small class="text-muted" style="font-size: 0.7rem;">Belum ada akun</small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Driver Demo -->
                <div class="col-6">
                    <?php if ($akun_peran['driver'] !== null): ?>
                        <form method="POST" action="login.php">
                            <input type="hidden" name="ref" value="<?= e($ref) ?>">
                            <input type="hidden" name="aksi" value="quick_demo">
                            <input type="hidden" name="akun_demo" value="driver">
                            <button type="submit" class="demo-btn-card">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-info-subtle text-info p-1 rounded"><i class="bi bi-bicycle"></i></span>
                                    <div class="text-truncate">
                                        <div class="fw-bold text-dark small" style="line-height: 1.1;">Driver</div>
                                        <small class="text-muted text-truncate d-block" style="font-size: 0.7rem;"><?= e($akun_peran['driver']['nama_lengkap']) ?></small>
                                    </div>
                                </div>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="demo-btn-card opacity-50">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-secondary-subtle text-secondary p-1 rounded"><i class="bi bi-bicycle"></i></span>
                                <div>
                                    <div class="fw-bold text-secondary small" style="line-height: 1.1;">Driver</div>
                                    <small class="text-muted" style="font-size: 0.7rem;">Belum ada akun</small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Admin Demo -->
                <div class="col-6">
                    <?php if ($akun_peran['admin'] !== null): ?>
                        <form method="POST" action="login.php">
                            <input type="hidden" name="ref" value="<?= e($ref) ?>">
                            <input type="hidden" name="aksi" value="quick_demo">
                            <input type="hidden" name="akun_demo" value="admin">
                            <button type="submit" class="demo-btn-card">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-danger-subtle text-danger p-1 rounded"><i class="bi bi-shield-shaded"></i></span>
                                    <div class="text-truncate">
                                        <div class="fw-bold text-dark small" style="line-height: 1.1;">Administrator</div>
                                        <small class="text-muted text-truncate d-block" style="font-size: 0.7rem;"><?= e($akun_peran['admin']['nama_lengkap']) ?></small>
                                    </div>
                                </div>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="demo-btn-card opacity-50">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-secondary-subtle text-secondary p-1 rounded"><i class="bi bi-shield-x"></i></span>
                                <div>
                                    <div class="fw-bold text-secondary small" style="line-height: 1.1;">Administrator</div>
                                    <small class="text-muted" style="font-size: 0.7rem;">Belum ada akun</small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
