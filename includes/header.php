<?php
declare(strict_types=1);

// Pastikan fungsi modul telah dimuat
if (!function_exists('ambil_keranjang')) {
    require_once __DIR__ . '/functions_order.php';
}
if (!function_exists('apakah_login')) {
    require_once __DIR__ . '/functions_auth.php';
}

mulai_sesi();
$total_keranjang = hitung_jumlah_item_keranjang();
$halaman_saat_ini = basename($_SERVER['PHP_SELF'] ?? '');
$tahap_aktif = isset($tahap_aktif) ? (int) $tahap_aktif : 1;

$pengguna_aktif = ambil_pengguna_login();
$peran_aktif = $pengguna_aktif['peran'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($judul_halaman) ? e($judul_halaman) . ' - AntarRasa' : 'AntarRasa - Layanan Kuliner Online' ?></title>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Kustomisasi CSS Modern AntarRasa (Super-App Style) -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= $halaman_saat_ini === 'admin.php' ? 'bg-light' : '' ?>">

<?php if ($halaman_saat_ini === 'login.php' || $halaman_saat_ini === 'register.php'): ?>
    <!-- Halaman Auth Split-Screen Modern (Tanpa Navbar Default) -->

<?php elseif ($halaman_saat_ini === 'admin.php'): ?>
    <!-- =====================================================================
         LAYOUT ADMIN: EXECUTIVE BACKOFFICE (SIDEBAR KIRI GELAP #0F172A)
         ===================================================================== -->
    <div class="admin-layout-wrapper">
        <!-- Sidebar Navigasi Kiri -->
        <aside class="admin-sidebar shadow-lg">
            <a href="admin.php" class="admin-sidebar-brand">
                <i class="bi bi-shield-lock-fill fs-4 text-danger"></i>
                <span class="admin-sidebar-text">AntarRasa <small class="text-danger fw-bold" style="font-size: 0.65rem;">ADMIN</small></span>
            </a>
            
            <div class="py-3 flex-grow-1">
                <div class="px-3 mb-2 small text-uppercase text-secondary fw-bold" style="font-size: 0.7rem;">Menu Utama</div>
                <a href="admin.php" class="admin-nav-item active">
                    <i class="bi bi-speedometer2"></i>
                    <span class="admin-sidebar-text">Dashboard &amp; User</span>
                </a>
                <a href="#tabelSemuaPesanan" class="admin-nav-item">
                    <i class="bi bi-receipt-cutoff"></i>
                    <span class="admin-sidebar-text">Monitoring Order</span>
                </a>

                <div class="px-3 mt-4 mb-2 small text-uppercase text-secondary fw-bold" style="font-size: 0.7rem;">Pintas Peran Ekosistem</div>
                <a href="merchant.php" class="admin-nav-item">
                    <i class="bi bi-shop text-warning"></i>
                    <span class="admin-sidebar-text">Panel Merchant</span>
                </a>
                <a href="driver.php" class="admin-nav-item">
                    <i class="bi bi-bicycle text-info"></i>
                    <span class="admin-sidebar-text">Panel Driver</span>
                </a>
                <a href="index.php" class="admin-nav-item">
                    <i class="bi bi-bag-heart text-danger"></i>
                    <span class="admin-sidebar-text">Katalog Customer</span>
                </a>
            </div>

            <!-- Bagian Bawah Sidebar: Profil & Keluar -->
            <div class="p-3 border-top border-secondary border-opacity-25">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="admin-sidebar-text">
                        <div class="fw-bold small text-white"><?= e($pengguna_aktif['nama_lengkap'] ?? 'Admin') ?></div>
                        <div class="text-secondary small" style="font-size: 0.75rem;">@<?= e($pengguna_aktif['username'] ?? 'admin') ?></div>
                    </div>
                    <a href="logout.php" class="btn btn-sm btn-outline-danger border-0" title="Keluar">
                        <i class="bi bi-box-arrow-right fs-5"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Area Konten Utama Backoffice -->
        <div class="admin-main-content">
            <header class="admin-topbar">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-danger px-2 py-1"><i class="bi bi-shield-check me-1"></i>Backoffice Executive</span>
                    <span class="text-muted small">&bull; Sistem Pemesanan &amp; Pengantaran AntarRasa</span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <a href="index.php" class="btn btn-sm btn-outline-secondary" target="_blank">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Buka Website Customer
                    </a>
                    <a href="logout.php" class="btn btn-sm btn-danger fw-semibold">
                        <i class="bi bi-power me-1"></i> Logout
                    </a>
                </div>
            </header>
            <main class="container-fluid p-4">

<?php elseif ($halaman_saat_ini === 'merchant.php'): ?>
    <!-- =====================================================================
         LAYOUT MERCHANT: GOBIZ / GRABMERCHANT PORTAL HEADER (#4F46E5)
         ===================================================================== -->
    <header class="navbar-merchant py-2 sticky-top">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <a class="brand-merchant" href="merchant.php">
                    <div class="bg-primary text-white p-2 rounded-3 shadow-sm d-inline-flex">
                        <i class="bi bi-shop fs-4"></i>
                    </div>
                    <div>
                        <span class="fs-5 fw-extrabold text-dark d-block" style="line-height: 1.1;"><?= e($pengguna_aktif['nama_lengkap'] ?? 'Mitra Restoran') ?></span>
                        <small class="text-primary fw-bold" style="font-size: 0.75rem;"><i class="bi bi-patch-check-fill me-1"></i>GoBiz / GrabMerchant Partner</small>
                    </div>
                </a>
                <span class="merchant-badge-online d-none d-md-inline-flex align-items-center gap-1">
                    <span class="spinner-grow spinner-grow-sm text-success" style="width: 8px; height: 8px;" role="status"></span>
                    Restoran Buka &bull; Siap Menerima Pesanan
                </span>
            </div>

            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small d-none d-sm-inline">Masuk sebagai: <strong><?= e($pengguna_aktif['nama_lengkap'] ?? 'Merchant') ?></strong></span>
                <a href="index.php" class="btn btn-sm btn-outline-custom">
                    <i class="bi bi-eye me-1"></i> Lihat Menu
                </a>
                <a href="logout.php" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </header>
    <main class="container my-4">

<?php elseif ($halaman_saat_ini === 'driver.php'): ?>
    <!-- =====================================================================
         LAYOUT DRIVER: GOPARTNER / GRABDRIVER COURIER HUD HEADER
         ===================================================================== -->
    <header class="header-driver-hud sticky-top">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-success text-white p-2 rounded-circle shadow-sm d-inline-flex">
                    <i class="bi bi-bicycle fs-4"></i>
                </div>
                <div>
                    <span class="fs-6 fw-bold text-white d-block" style="line-height: 1.1;">AntarRasa Kurir</span>
                    <small class="text-secondary" style="font-size: 0.78rem;">Mitra Driver: <?= e($pengguna_aktif['nama_lengkap'] ?? 'Kurir') ?></small>
                </div>
                <div class="driver-badge-live ms-2">
                    <span class="driver-pulse-dot"></span>
                    STATUS: ONLINE - SIAP MENGANTAR
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="index.php" class="btn btn-sm btn-outline-light d-none d-md-inline">
                    <i class="bi bi-shop me-1"></i> Cek Resto
                </a>
                <a href="logout.php" class="btn btn-sm btn-danger fw-bold">
                    <i class="bi bi-power me-1"></i> Offline / Logout
                </a>
            </div>
        </div>
    </header>
    <main class="container my-4">

<?php else: ?>
    <!-- =====================================================================
         LAYOUT CUSTOMER: GOFOOD / SHOPEEFOOD CONSUMER NAVBAR (#EE4D2D / #00AA13)
         ===================================================================== -->
    <nav class="navbar navbar-expand-lg navbar-customer sticky-top">
        <div class="container">
            <a class="brand-customer" href="index.php">
                <div class="p-2 rounded-circle d-inline-flex text-white shadow-sm" style="background: linear-gradient(135deg, #ee4d2d 0%, #ff5722 100%);">
                    <i class="bi bi-egg-fried fs-4"></i>
                </div>
                <span class="fw-bold" style="letter-spacing: -0.5px;">AntarRasa</span>
                <span class="brand-badge-customer d-none d-sm-inline">Food Delivery</span>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navCustomer" aria-controls="navCustomer" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navCustomer">
                <!-- Search Bar Cepat ala GoFood / ShopeeFood -->
                <div class="mx-lg-auto my-2 my-lg-0 position-relative" style="max-width: 380px; width: 100%;">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <form method="GET" action="index.php" class="m-0">
                        <input type="text" name="q" class="form-control form-control-sm search-box-customer py-2" placeholder="Cari hidangan menu makanan atau minuman..." value="<?= isset($_GET['q']) ? e((string)$_GET['q']) : '' ?>">
                    </form>
                </div>

                <ul class="navbar-nav align-items-center gap-2">
                    <li class="nav-item">
                        <a class="nav-link px-2 <?= $halaman_saat_ini === 'index.php' ? 'fw-bold text-dark active' : '' ?>" href="index.php">
                            <i class="bi bi-grid-fill text-danger me-1"></i> Menu
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-2 <?= $halaman_saat_ini === 'riwayat.php' ? 'fw-bold text-dark active' : '' ?>" href="riwayat.php">
                            <i class="bi bi-clock-history text-primary me-1"></i> Pesanan Saya
                        </a>
                    </li>

                    <!-- Tombol Keranjang Belanja -->
                    <li class="nav-item">
                        <a class="btn btn-outline-custom position-relative py-2 px-3" href="pesanan.php">
                            <i class="bi bi-bag-heart-fill text-danger me-1"></i> Keranjang
                            <?php if ($total_keranjang > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger shadow-sm">
                                    <?= $total_keranjang ?>
                                    <span class="visually-hidden">item</span>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <!-- Identitas Akun Pengguna / Tombol Masuk -->
                    <?php if ($pengguna_aktif !== null): ?>
                        <?php $inf_p = dapatkan_info_peran($pengguna_aktif['peran']); ?>
                        <li class="nav-item dropdown ms-lg-2">
                            <a class="btn btn-light border dropdown-toggle d-flex align-items-center gap-2 py-1 px-3 rounded-pill" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="badge <?= $inf_p['class'] ?> rounded-pill" style="font-size: 0.7rem;">
                                    <?= e(ucfirst($pengguna_aktif['peran'])) ?>
                                </span>
                                <span class="fw-semibold text-dark small"><?= e($pengguna_aktif['nama_lengkap']) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-1">
                                <li class="px-3 py-2 border-bottom bg-light">
                                    <div class="fw-bold small"><?= e($pengguna_aktif['nama_lengkap']) ?></div>
                                    <div class="text-muted small">@<?= e($pengguna_aktif['username']) ?> &bull; <?= e(ucfirst($pengguna_aktif['peran'])) ?></div>
                                </li>
                                <?php if ($peran_aktif === 'admin'): ?>
                                    <li><a class="dropdown-item py-2" href="admin.php"><i class="bi bi-shield-shaded me-2 text-danger"></i>Panel Administrator</a></li>
                                <?php endif; ?>
                                <?php if ($peran_aktif === 'merchant' || $peran_aktif === 'admin'): ?>
                                    <li><a class="dropdown-item py-2" href="merchant.php"><i class="bi bi-shop me-2 text-warning"></i>Panel Merchant Resto</a></li>
                                <?php endif; ?>
                                <?php if ($peran_aktif === 'driver' || $peran_aktif === 'admin'): ?>
                                    <li><a class="dropdown-item py-2" href="driver.php"><i class="bi bi-bicycle me-2 text-info"></i>Panel Kurir Driver</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item py-2" href="riwayat.php"><i class="bi bi-receipt me-2 text-primary"></i>Riwayat Pesanan</a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar (Logout)</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-2 d-flex gap-2">
                            <a href="login.php" class="btn btn-sm btn-outline-custom px-3 py-2 fw-semibold">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                            </a>
                            <a href="register.php" class="btn btn-sm btn-primary-custom px-3 py-2">
                                <i class="bi bi-person-plus me-1"></i> Daftar
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container my-4">
<?php endif; ?>
