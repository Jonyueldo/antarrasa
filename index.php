<?php
declare(strict_types=1);

// Katalog menu + tambah ke keranjang.
require_once __DIR__ . '/config/database.php';
mulai_sesi();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    wajib_login();
    $id = (int) ($_POST['id_menu'] ?? 0);
    $jumlah = max(1, min(50, (int) ($_POST['jumlah'] ?? 1)));
    $menu = ambil_satu($conn, "SELECT nama_menu FROM menu WHERE id_menu = ? AND status_menu = 'Tersedia'", 'i', [$id]);
    if ($menu) {
        $_SESSION['keranjang'][$id] = min(50, ($_SESSION['keranjang'][$id] ?? 0) + $jumlah);
        flash($menu['nama_menu'] . ' ditambahkan ke keranjang.');
    } else {
        flash('Menu tidak tersedia.', 'danger');
    }
    alihkan('index.php');
}

$daftar_menu = ambil_semua($conn, 'SELECT * FROM menu ORDER BY nama_menu');
$judul = 'Katalog';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h4 mb-3">Katalog Menu</h1>
<div class="row g-3">
    <?php foreach ($daftar_menu as $m): ?>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <h2 class="h6"><?= e($m['nama_menu']) ?></h2>
                    <p class="text-muted small flex-grow-1"><?= e($m['deskripsi']) ?></p>
                    <div class="fw-bold text-danger mb-2"><?= e(format_rupiah($m['harga'])) ?></div>
                    <?php if ($m['status_menu'] === 'Tersedia'): ?>
                        <form method="post" class="d-flex gap-2">
                            <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
                            <input type="hidden" name="id_menu" value="<?= e($m['id_menu']) ?>">
                            <input class="form-control" type="number" name="jumlah" value="1" min="1" max="50" aria-label="Jumlah">
                            <button class="btn btn-danger text-nowrap">+ Keranjang</button>
                        </form>
                    <?php else: ?>
                        <span class="badge bg-secondary align-self-start">Habis</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$daftar_menu): ?><p class="text-muted">Belum ada menu.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
