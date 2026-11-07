<?php
declare(strict_types=1);

// Layout atas. Halaman mengisi $judul sebelum memuat berkas ini.
mulai_sesi();
$p = pengguna_login();
$flash = flash();
$nav = match ($p['peran'] ?? null) {
    'pelanggan' => ['index.php' => 'Katalog', 'pesanan.php' => 'Keranjang (' . jumlah_keranjang() . ')', 'riwayat.php' => 'Pesanan Saya', 'laporan.php' => 'Lapor'],
    'merchant'  => ['merchant.php' => 'Menu Saya', 'riwayat.php' => 'Pesanan Masuk', 'laporan.php' => 'Lapor'],
    'driver'    => ['riwayat.php' => 'Tugas Antar', 'laporan.php' => 'Lapor'],
    'admin'     => ['admin.php' => 'Admin', 'riwayat.php' => 'Semua Pesanan', 'index.php' => 'Katalog'],
    default     => ['index.php' => 'Katalog'],
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($judul ?? 'AntarRasa') ?> - AntarRasa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-md navbar-dark bg-danger mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">AntarRasa</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuNav" aria-label="Buka menu"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="menuNav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($nav as $url => $label): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e($url) ?>"><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <div class="navbar-text">
                <?php if ($p): ?>
                    <?= e($p['nama']) ?> (<?= e($p['peran']) ?>) &middot; <a class="text-white" href="logout.php">Keluar</a>
                <?php else: ?>
                    <a class="text-white" href="login.php">Masuk</a> &middot; <a class="text-white" href="register.php">Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main class="container pb-5">
<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash[1]) ?>"><?= e($flash[0]) ?></div>
<?php endif; ?>
