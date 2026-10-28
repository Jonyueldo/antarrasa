<?php
declare(strict_types=1);

/**
 * Halaman Utama & Katalog Menu Makanan "AntarRasa" (Customer Super-App Experience)
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

// 2. Filter Pencarian & Kategori
$kata_kunci = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$kategori_aktif = isset($_GET['kategori']) ? trim((string)$_GET['kategori']) : 'semua';

$semua_menu = ambil_semua_menu($conn);
$daftar_menu = [];
$jumlah_menu_tersedia = 0;
$jumlah_menu_habis = 0;

foreach ($semua_menu as $menu) {
    if (strcasecmp((string) $menu['status_menu'], 'Tersedia') === 0) {
        $jumlah_menu_tersedia++;
    } else {
        $jumlah_menu_habis++;
    }

    $cocok = true;
    if ($kata_kunci !== '') {
        $haystack = mb_strtolower((string) $menu['nama_menu'] . ' ' . (string) ($menu['deskripsi'] ?? ''));
        if (!str_contains($haystack, mb_strtolower($kata_kunci))) {
            $cocok = false;
        }
    }

    if ($kategori_aktif !== '' && $kategori_aktif !== 'semua') {
        $haystack = mb_strtolower((string) $menu['nama_menu']);
        if ($kategori_aktif === 'ayam' && !str_contains($haystack, 'ayam')) $cocok = false;
        if ($kategori_aktif === 'nasi' && !str_contains($haystack, 'nasi')) $cocok = false;
        if ($kategori_aktif === 'mie' && !str_contains($haystack, 'mie')) $cocok = false;
        if ($kategori_aktif === 'sate' && !str_contains($haystack, 'sate')) $cocok = false;
        if ($kategori_aktif === 'minuman' && !str_contains($haystack, 'es') && !str_contains($haystack, 'cendol') && !str_contains($haystack, 'teh') && !str_contains($haystack, 'kopi')) $cocok = false;
    }

    if ($cocok) {
        $daftar_menu[] = $menu;
    }
}

$total_keranjang = hitung_jumlah_item_keranjang();
$nominal_keranjang = hitung_total_nominal_keranjang($conn);

$judul_halaman = 'Pesan Makanan Online Favoritmu';
$tahap_aktif = 1;

require_once __DIR__ . '/includes/header.php';
?>

<!-- Promo Hero Banner ala GoFood / ShopeeFood -->
<div class="promo-hero-customer mb-4">
    <div class="row align-items-center position-relative" style="z-index: 2;">
        <div class="col-lg-8">
            <span class="badge bg-white text-danger fw-bold px-3 py-1 mb-2 rounded-pill shadow-sm" style="font-size: 0.8rem;">
                <i class="bi bi-fire text-danger me-1"></i> Promo Ongkir Cepat &amp; Makanan Hangat
            </span>
            <?php if ($pengguna_aktif !== null): ?>
                <h1 class="display-6 fw-extrabold mb-2 text-white">
                    Mau santap apa hari ini, <?= e($pengguna_aktif['nama_lengkap']) ?>?
                </h1>
            <?php else: ?>
                <h1 class="display-6 fw-extrabold mb-2 text-white">
                    Pesan Makanan Favoritmu, Diantar Cepat Sampai Tujuan!
                </h1>
            <?php endif; ?>
            <p class="mb-0 text-white opacity-90 fs-6" style="max-width: 620px;">
                Eksplor menu kuliner lezat nusantara dari dapur restoran pilihan. Pesanan langsung dimasak segar dan diantar oleh kurir terpercaya.
            </p>
        </div>
        <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
            <a href="pesanan.php" class="btn btn-light text-danger fw-bold px-4 py-3 rounded-pill shadow-lg d-inline-flex align-items-center gap-2">
                <i class="bi bi-bag-heart-fill fs-5"></i>
                <span>Lihat Keranjang (<?= $total_keranjang ?>)</span>
            </a>
        </div>
    </div>
</div>

<!-- Alert Notifikasi Registrasi Baru -->
<?php if (isset($_GET['registrasi_sukses'])): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center shadow-sm rounded-4" role="alert">
        <i class="bi bi-check-circle-fill fs-4 me-2 text-success"></i>
        <div><strong>Pendaftaran Berhasil!</strong> Akun pelanggan baru Anda siap digunakan untuk memesan kuliner lezat di AntarRasa.</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Alert Notifikasi Sukses Tambah Item -->
<?php if ($pesan_sukses !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center shadow-sm rounded-4" role="alert">
        <i class="bi bi-cart-check-fill fs-4 me-2 text-success"></i>
        <div class="flex-grow-1"><?= $pesan_sukses ?></div>
        <a href="pesanan.php" class="btn btn-sm btn-success rounded-pill px-3 py-1 ms-2 fw-semibold">Checkout</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Alert Notifikasi Peringatan -->
<?php if ($pesan_peringatan !== ''): ?>
    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center shadow-sm rounded-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-4 me-2 text-warning"></i>
        <div><?= $pesan_peringatan ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Baris Kategori Cepat ala GoFood / ShopeeFood -->
<div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="fw-bold small text-muted me-1"><i class="bi bi-funnel me-1"></i>Kategori:</span>
        <a href="index.php?kategori=semua<?= $kata_kunci !== '' ? '&q=' . urlencode($kata_kunci) : '' ?>" 
           class="category-chip <?= $kategori_aktif === 'semua' ? 'active' : '' ?>">
            <i class="bi bi-grid"></i> Semua
        </a>
        <a href="index.php?kategori=ayam<?= $kata_kunci !== '' ? '&q=' . urlencode($kata_kunci) : '' ?>" 
           class="category-chip <?= $kategori_aktif === 'ayam' ? 'active' : '' ?>">
            <i class="bi bi-egg-fried"></i> Ayam &amp; Bebek
        </a>
        <a href="index.php?kategori=nasi<?= $kata_kunci !== '' ? '&q=' . urlencode($kata_kunci) : '' ?>" 
           class="category-chip <?= $kategori_aktif === 'nasi' ? 'active' : '' ?>">
            <i class="bi bi-cup-hot"></i> Nasi Goreng
        </a>
        <a href="index.php?kategori=mie<?= $kata_kunci !== '' ? '&q=' . urlencode($kata_kunci) : '' ?>" 
           class="category-chip <?= $kategori_aktif === 'mie' ? 'active' : '' ?>">
            <i class="bi bi-water"></i> Olahan Mie
        </a>
        <a href="index.php?kategori=sate<?= $kata_kunci !== '' ? '&q=' . urlencode($kata_kunci) : '' ?>" 
           class="category-chip <?= $kategori_aktif === 'sate' ? 'active' : '' ?>">
            <i class="bi bi-fire"></i> Sate &amp; Bakaran
        </a>
        <a href="index.php?kategori=minuman<?= $kata_kunci !== '' ? '&q=' . urlencode($kata_kunci) : '' ?>" 
           class="category-chip <?= $kategori_aktif === 'minuman' ? 'active' : '' ?>">
            <i class="bi bi-cup-straw"></i> Minuman Segar
        </a>
    </div>

    <!-- Status Menu Badge -->
    <div class="d-none d-md-flex align-items-center gap-2">
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
            <i class="bi bi-check-circle-fill me-1"></i> <?= $jumlah_menu_tersedia ?> Tersedia
        </span>
        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill">
            <i class="bi bi-slash-circle me-1"></i> <?= $jumlah_menu_habis ?> Habis
        </span>
    </div>
</div>

<!-- Header Judul Menu & Hasil Pencarian -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-extrabold text-dark mb-0">
            <?php if ($kata_kunci !== ''): ?>
                Hasil Pencarian: "<?= e($kata_kunci) ?>"
                <small class="fs-6 fw-normal text-muted ms-2">(<?= count($daftar_menu) ?> hidangan ditemukan)</small>
                <a href="index.php" class="btn btn-sm btn-link text-decoration-none text-danger p-0 ms-2">Reset Pencarian</a>
            <?php else: ?>
                Menu Hidangan Rekomendasi
            <?php endif; ?>
        </h4>
    </div>
</div>

<!-- Grid Card Menu Modern (GoFood / ShopeeFood Style) -->
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5 pb-5">
    <?php if (empty($daftar_menu)): ?>
        <div class="col-12 text-center py-5">
            <div class="p-4 bg-white rounded-4 shadow-sm border mx-auto" style="max-width: 500px;">
                <i class="bi bi-search text-muted display-4 mb-3 d-block"></i>
                <h5 class="fw-bold text-dark">Menu Tidak Ditemukan</h5>
                <p class="text-muted small mb-3">Tidak ada menu makanan yang sesuai dengan pencarian atau kategori yang Anda pilih.</p>
                <a href="index.php" class="btn btn-outline-custom rounded-pill px-4">Lihat Seluruh Menu</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($daftar_menu as $menu): ?>
            <?php 
                $id_menu = (int) $menu['id_menu'];
                $is_tersedia = (strcasecmp((string) $menu['status_menu'], 'Tersedia') === 0);
                $gambar_url = dapatkan_gambar_menu((string) $menu['nama_menu']);
            ?>
            <div class="col">
                <div class="food-card">
                    <!-- Foto Menu Makanan -->
                    <div class="food-card-img-wrap">
                        <img src="<?= e($gambar_url) ?>" alt="<?= e($menu['nama_menu']) ?>" class="food-card-img" loading="lazy">
                        
                        <!-- Stock Status Badge -->
                        <?php if ($is_tersedia): ?>
                            <span class="food-badge-stock food-badge-available">
                                <i class="bi bi-check-circle-fill me-1"></i> Tersedia
                            </span>
                        <?php else: ?>
                            <span class="food-badge-stock food-badge-soldout">
                                <i class="bi bi-slash-circle me-1"></i> Habis
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Isi Kartu Menu -->
                    <div class="food-card-body">
                        <h5 class="food-card-title"><?= e($menu['nama_menu']) ?></h5>
                        <p class="food-card-desc"><?= e($menu['deskripsi'] ?? 'Hidangan istimewa siap dimasak oleh koki restoran.') ?></p>
                        
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                            <div>
                                <small class="text-muted d-block" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700;">Harga</small>
                                <span class="food-card-price"><?= format_rupiah((float) $menu['harga']) ?></span>
                            </div>

                            <!-- Form Kontrol Stepper & Tambah Keranjang -->
                            <?php if ($is_tersedia): ?>
                                <form method="POST" action="index.php<?= $kata_kunci !== '' ? '?q=' . urlencode($kata_kunci) : '' ?>" class="d-flex align-items-center gap-2 m-0">
                                    <input type="hidden" name="aksi" value="tambah_keranjang">
                                    <input type="hidden" name="id_menu" value="<?= $id_menu ?>">
                                    
                                    <div class="qty-stepper">
                                        <button class="qty-stepper-btn" type="button" onclick="ubahJumlah(<?= $id_menu ?>, -1)">&minus;</button>
                                        <input type="number" name="jumlah" id="jumlah_<?= $id_menu ?>" class="qty-stepper-input" value="1" min="1" max="50" required>
                                        <button class="qty-stepper-btn" type="button" onclick="ubahJumlah(<?= $id_menu ?>, 1)">&#43;</button>
                                    </div>

                                    <button type="submit" class="btn btn-primary-custom p-2 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px;" title="Tambah ke Keranjang">
                                        <i class="bi bi-plus-lg text-white fs-6"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary disabled rounded-pill px-3 py-1" disabled>
                                    <i class="bi bi-x-circle me-1"></i> Habis
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Floating Action Bar untuk Keranjang (Jika ada item aktif) -->
<?php if ($total_keranjang > 0): ?>
    <div class="floating-checkout-bar">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-white text-danger p-2 rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="bi bi-bag-check-fill fs-5"></i>
            </div>
            <div>
                <div class="fw-bold fs-6 text-white"><?= $total_keranjang ?> Item di Keranjang &bull; <?= format_rupiah($nominal_keranjang) ?></div>
                <small class="text-white-50">Lanjutkan untuk memeriksa rincian pesanan & alamat pengantaran</small>
            </div>
        </div>
        <a href="pesanan.php" class="btn btn-light text-danger fw-bold rounded-pill px-4 py-2 shadow-sm d-inline-flex align-items-center gap-2">
            <span>Checkout Sekarang</span>
            <i class="bi bi-arrow-right"></i>
        </a>
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
