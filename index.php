<?php
declare(strict_types=1);

// Katalog menu + tambah ke keranjang (pelanggan).
require_once __DIR__ . '/config/database.php';
mulai_sesi();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    wajib_login($conn, 'pelanggan');
    $id = (int) ($_POST['id_menu'] ?? 0);
    $jumlah = max(1, min(50, (int) ($_POST['jumlah'] ?? 1)));
    $menu = ambil_satu($conn, 'SELECT nama_menu, id_merchant FROM menu WHERE id_menu = ? AND status_stok = \'tersedia\' AND ' . MENU_TAMPIL, 'i', [$id]);
    $merchant_keranjang = empty($_SESSION['keranjang']) ? null : ($_SESSION['merchant_keranjang'] ?? null);

    if (!$menu) {
        flash('Menu tidak tersedia.', 'danger');
    } elseif ($merchant_keranjang !== null && $merchant_keranjang !== (int) $menu['id_merchant']) {
        flash('Keranjang hanya boleh berisi menu dari satu merchant. Selesaikan atau kosongkan keranjang dulu.', 'warning');
    } else {
        $_SESSION['merchant_keranjang'] = (int) $menu['id_merchant'];
        $_SESSION['keranjang'][$id] = min(50, ($_SESSION['keranjang'][$id] ?? 0) + $jumlah);
        flash($menu['nama_menu'] . ' ditambahkan ke keranjang.');
    }
    alihkan('index.php');
}

$daftar_menu = ambil_semua($conn, 'SELECT m.*, p.nama AS nama_merchant FROM menu m JOIN pengguna p ON p.id_pengguna = m.id_merchant
    WHERE ' . MENU_TAMPIL . " AND p.status_akun <> 'ban_permanen' ORDER BY p.nama, m.nama_menu");
$bisa_pesan = (pengguna_login()['peran'] ?? '') === 'pelanggan';
$judul = 'Katalog';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h4 mb-3">Katalog Menu</h1>
<div class="row g-3">
    <?php foreach ($daftar_menu as $m): ?>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100">
                <?php if ($m['gambar']): ?><img src="<?= e($m['gambar']) ?>" class="card-img-top" alt="<?= e($m['nama_menu']) ?>" style="height:180px;object-fit:cover"><?php endif; ?>
                <div class="card-body d-flex flex-column">
                    <h2 class="h6 mb-0"><?= e($m['nama_menu']) ?></h2>
                    <div class="small text-muted mb-2"><?= e($m['nama_merchant']) ?></div>
                    <p class="small flex-grow-1"><?= e($m['deskripsi']) ?></p>
                    <div class="fw-bold text-danger mb-2"><?= e(format_rupiah($m['harga'])) ?></div>
                    <?php if ($m['status_stok'] !== 'tersedia'): ?>
                        <span class="badge bg-secondary align-self-start">Habis</span>
                    <?php elseif ($bisa_pesan): ?>
                        <form method="post" class="d-flex gap-2">
                            <?= input_csrf() ?>
                            <input type="hidden" name="id_menu" value="<?= e($m['id_menu']) ?>">
                            <input class="form-control" type="number" name="jumlah" value="1" min="1" max="50" aria-label="Jumlah">
                            <button class="btn btn-danger text-nowrap">+ Keranjang</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$daftar_menu): ?><p class="text-muted">Belum ada menu.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
