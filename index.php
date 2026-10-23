<?php
declare(strict_types=1);

/**
 * Halaman Utama & Katalog Menu Makanan "AntarRasa"
 * Memenuhi P-Spec 1.1 (Tampilkan Katalog Menu) & P-Spec 1.2 (Pilih Menu)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions_order.php';

mulai_sesi();

$pesan_sukses = '';
$pesan_peringatan = '';

// 1. Tangani Aksi Tambah Item ke Keranjang via Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah_keranjang') {
    $id_menu_pilihan = isset($_POST['id_menu']) ? (int) $_POST['id_menu'] : 0;
    $jumlah_pilihan = isset($_POST['jumlah']) ? (int) $_POST['jumlah'] : 1;

    if ($id_menu_pilihan > 0 && $jumlah_pilihan > 0) {
        $info_menu = ambil_menu_by_id($conn, $id_menu_pilihan);
        if ($info_menu !== null) {
            if (strcasecmp((string) $info_menu['status_menu'], 'Tersedia') === 0) {
                tambah_ke_keranjang($id_menu_pilihan, $jumlah_pilihan);
                $pesan_sukses = "Berhasil menambahkan " . $jumlah_pilihan . "x '" . e($info_menu['nama_menu']) . "' ke keranjang belanja!";
            } else {
                $pesan_peringatan = "Maaf, menu '" . e($info_menu['nama_menu']) . "' saat ini sedang habis.";
            }
        } else {
            $pesan_peringatan = "Menu tidak ditemukan di database.";
        }
    } else {
        $pesan_peringatan = "Kuantitas pemesanan harus lebih dari 0.";
    }
}

// 2. Ambil seluruh data menu dari DataStore D1 (MENU)
$daftar_menu = ambil_semua_menu($conn);
$jumlah_menu_tersedia = 0;
$jumlah_menu_habis = 0;

foreach ($daftar_menu as $menu) {
    if (strcasecmp((string) $menu['status_menu'], 'Tersedia') === 0) {
        $jumlah_menu_tersedia++;
    } else {
        $jumlah_menu_habis++;
    }
}

$judul_halaman = 'Katalog Menu Makanan';
$tahap_aktif = 1;
$tampilkan_stepper = true;

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner Informasi Prototipe MVP 20% -->
<div class="hero-banner">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <span class="badge bg-warning text-dark mb-2 px-3 py-2 fw-bold">
                <i class="bi bi-stars me-1"></i> SRS IEEE Std 830-1998 - Progres 20% (Core MVP)
            </span>
            <h1 class="display-6 fw-bold mb-2">Nikmati Cita Rasa Kuliner Terbaik di AntarRasa</h1>
            <p class="text-light-subtle mb-0 lead fs-6">
                Sistem Pemesanan dan Pengantaran Makanan Online berbasis PHP 8 Murni Prosedural. 
                Pilih menu favorit Anda dari katalog <strong>DataStore D1 (MENU)</strong>, lakukan checkout untuk menyimpan pesanan ke <strong>D2 & D3</strong>, 
                dan lanjutkan simulasi alur pembayaran serta pelacakan pengiriman.
            </p>
        </div>
        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
            <a href="pesanan.php" class="btn btn-warning btn-lg fw-bold px-4 py-2 text-dark shadow">
                <i class="bi bi-bag-check-fill me-2"></i> Lihat Keranjang (<?= $total_keranjang ?>)
            </a>
        </div>
    </div>
</div>

<!-- Alert Notifikasi -->
<?php if ($pesan_sukses !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
        <div><?= $pesan_sukses ?></div>
        <a href="pesanan.php" class="btn btn-sm btn-success ms-auto me-2 fw-semibold">Lanjut ke Checkout</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($pesan_peringatan !== ''): ?>
    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-warning"></i>
        <div><?= $pesan_peringatan ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Baris Header Katalog & Filter Status -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h3 class="fw-bold mb-1">
            <i class="bi bi-book-half text-danger me-2"></i>Katalog Menu Restoran
        </h3>
        <p class="text-muted small mb-0">Menampilkan hidangan autentik yang siap dimasak dan diantar hangat.</p>
    </div>
    <div class="d-flex gap-2 mt-2 mt-sm-0">
        <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
            <i class="bi bi-check-circle me-1"></i> <?= $jumlah_menu_tersedia ?> Menu Tersedia
        </span>
        <span class="badge bg-secondary-subtle text-secondary border border-secondary px-3 py-2">
            <i class="bi bi-x-circle me-1"></i> <?= $jumlah_menu_habis ?> Menu Habis
        </span>
    </div>
</div>

<!-- Grid Kartu Menu Makanan (DataStore D1) -->
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
    <?php if (empty($daftar_menu)): ?>
        <div class="col-12 text-center py-5">
            <i class="bi bi-inbox text-muted display-4"></i>
            <h5 class="mt-3 text-secondary">Belum ada menu yang terdaftar di basis data.</h5>
            <p class="text-muted small">Pastikan file <code>database/antarrasa.sql</code> sudah diimpor ke MySQL.</p>
        </div>
    <?php else: ?>
        <?php foreach ($daftar_menu as $menu): ?>
            <?php 
                $id_menu = (int) $menu['id_menu'];
                $is_tersedia = (strcasecmp((string) $menu['status_menu'], 'Tersedia') === 0);
                $gambar_url = dapatkan_gambar_menu((string) $menu['nama_menu']);
            ?>
            <div class="col">
                <div class="menu-card shadow-sm">
                    <!-- Gambar Menu -->
                    <div class="menu-card-img-wrap">
                        <img src="<?= e($gambar_url) ?>" alt="<?= e($menu['nama_menu']) ?>" class="menu-card-img" loading="lazy">
                        
                        <!-- Status Badge -->
                        <?php if ($is_tersedia): ?>
                            <span class="badge bg-success menu-card-badge shadow-sm">
                                <i class="bi bi-check-circle-fill me-1"></i> Tersedia
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary menu-card-badge shadow-sm">
                                <i class="bi bi-slash-circle me-1"></i> Habis
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Detail Menu -->
                    <div class="menu-card-body">
                        <h5 class="menu-card-title"><?= e($menu['nama_menu']) ?></h5>
                        <p class="menu-card-desc"><?= e($menu['deskripsi'] ?? 'Deskripsi menu belum tersedia.') ?></p>
                        
                        <div class="d-flex justify-content-between align-items-center pt-2 mt-auto border-top">
                            <div>
                                <span class="d-block text-muted small">Harga Satuan</span>
                                <span class="menu-card-price"><?= format_rupiah((float) $menu['harga']) ?></span>
                            </div>

                            <!-- Form Pemilihan & Penambahan Kuantitas -->
                            <?php if ($is_tersedia): ?>
                                <form method="POST" action="index.php" class="d-flex align-items-center gap-1">
                                    <input type="hidden" name="aksi" value="tambah_keranjang">
                                    <input type="hidden" name="id_menu" value="<?= $id_menu ?>">
                                    
                                    <div class="input-group input-group-sm" style="width: 125px;">
                                        <button class="btn btn-outline-secondary" type="button" onclick="ubahJumlah(<?= $id_menu ?>, -1)">-</button>
                                        <input type="number" name="jumlah" id="jumlah_<?= $id_menu ?>" class="form-control text-center px-1" value="1" min="1" max="50" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="ubahJumlah(<?= $id_menu ?>, 1)">+</button>
                                    </div>

                                    <button type="submit" class="btn btn-sm btn-primary-custom ms-1" title="Tambah ke Keranjang">
                                        <i class="bi bi-cart-plus-fill"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary disabled" disabled>
                                    <i class="bi bi-x-circle me-1"></i> Stok Habis
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Floating Action Bar untuk Keranjang (Jika ada item) -->
<?php if ($total_keranjang > 0): ?>
<div class="sticky-bottom bg-white border-top shadow-lg py-3 px-4 rounded-top-4 mb-0 mx-auto" style="max-width: 900px; z-index: 1020;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary-subtle text-primary p-2 rounded-circle">
                <i class="bi bi-cart-check-fill fs-4 text-primary"></i>
            </div>
            <div>
                <div class="fw-bold fs-6">Ada <?= $total_keranjang ?> item di keranjang pesanan Anda</div>
                <div class="text-muted small">Lanjutkan untuk memeriksa rincian tagihan & mengisi alamat pengantaran.</div>
            </div>
        </div>
        <div>
            <a href="pesanan.php" class="btn btn-primary-custom px-4 py-2">
                Lanjut ke Checkout <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Fungsi manipulasi angka kuantitas tombol +/-
function ubahJumlah(idMenu, delta) {
    const input = document.getElementById('jumlah_' + idMenu);
    if (!input) return;
    let val = parseInt(input.value, 10);
    if (isNaN(val)) val = 1;
    val += delta;
    if (val < 1) val = 1;
    if (val > 50) val = 50;
    input.value = val;
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
