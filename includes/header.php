<?php
declare(strict_types=1);

// Pastikan fungsi modul telah dimuat
if (!function_exists('ambil_keranjang')) {
    require_once __DIR__ . '/functions_order.php';
}

mulai_sesi();
$total_keranjang = hitung_jumlah_item_keranjang();
$halaman_saat_ini = basename($_SERVER['PHP_SELF'] ?? '');
$tahap_aktif = isset($tahap_aktif) ? (int) $tahap_aktif : 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($judul_halaman) ? e($judul_halaman) . ' - AntarRasa' : 'AntarRasa - Sistem Pemesanan & Pengantaran Makanan Online' ?></title>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Kustomisasi CSS AntarRasa -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Navbar Utama -->
<nav class="navbar navbar-expand-lg navbar-antarrasa sticky-top">
    <div class="container">
        <a class="brand-logo" href="index.php">
            <i class="bi bi-egg-fried fs-3"></i>
            <span>AntarRasa</span>
            <span class="brand-badge d-none d-sm-inline">MVP 20%</span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto align-items-center gap-1">
                <li class="nav-item">
                    <a class="nav-link px-3 <?= $halaman_saat_ini === 'index.php' ? 'fw-bold text-dark active' : '' ?>" href="index.php">
                        <i class="bi bi-grid me-1"></i> Katalog Menu
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 <?= $halaman_saat_ini === 'riwayat.php' ? 'fw-bold text-dark active' : '' ?>" href="riwayat.php">
                        <i class="bi bi-clock-history me-1"></i> Riwayat Pesanan
                    </a>
                </li>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-outline-custom position-relative px-3 py-2" href="pesanan.php">
                        <i class="bi bi-cart3 me-1"></i> Keranjang
                        <?php if ($total_keranjang > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $total_keranjang ?>
                                <span class="visually-hidden">item di keranjang</span>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Container Konten Utama -->
<main class="container my-4">
    <!-- Stepper Alur 4 Tahap Proses SRS -->
    <?php if (isset($tampilkan_stepper) && $tampilkan_stepper): ?>
    <div class="stepper-wrapper d-none d-md-flex">
        <div class="stepper-item <?= $tahap_aktif === 1 ? 'active' : ($tahap_aktif > 1 ? 'completed' : '') ?>">
            <div class="stepper-icon">
                <?php if ($tahap_aktif > 1): ?><i class="bi bi-check-lg"></i><?php else: ?>1<?php endif; ?>
            </div>
            <div>
                <div class="stepper-title">Pilih Menu</div>
                <div class="stepper-desc">Katalog D1 (MENU)</div>
            </div>
        </div>

        <div class="stepper-item <?= $tahap_aktif === 2 ? 'active' : ($tahap_aktif > 2 ? 'completed' : '') ?>">
            <div class="stepper-icon">
                <?php if ($tahap_aktif > 2): ?><i class="bi bi-check-lg"></i><?php else: ?>2<?php endif; ?>
            </div>
            <div>
                <div class="stepper-title">Checkout & Alamat</div>
                <div class="stepper-desc">Rekam D2 & D3</div>
            </div>
        </div>

        <div class="stepper-item <?= $tahap_aktif === 3 ? 'active' : ($tahap_aktif > 3 ? 'completed' : '') ?>">
            <div class="stepper-icon">
                <?php if ($tahap_aktif > 3): ?><i class="bi bi-check-lg"></i><?php else: ?>3<?php endif; ?>
            </div>
            <div>
                <div class="stepper-title">Simulasi Pembayaran</div>
                <div class="stepper-desc">Proses 2.0 (Gateway)</div>
            </div>
        </div>

        <div class="stepper-item <?= $tahap_aktif === 4 ? 'active' : '' ?>">
            <div class="stepper-icon">
                4
            </div>
            <div>
                <div class="stepper-title">Pelacakan Status</div>
                <div class="stepper-desc">Proses 3.0 (Driver)</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
