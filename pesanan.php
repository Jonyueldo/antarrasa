<?php
declare(strict_types=1);

/**
 * Halaman Pesanan / Checkout "AntarRasa" (Customer Cart & Checkout)
 * Memenuhi P-Spec 1.3 (Hitung Subtotal & Total), P-Spec 1.4 (Validasi Pesanan),
 * dan P-Spec 1.5 (Rekam Pesanan ke DataStore D2 & D3)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions_order.php';

mulai_sesi();

$pesan_error = [];
$alamat_pengiriman = '';

// Ambil data menu master dari DataStore D1 (MENU) untuk validasi & pencocokan harga terbaru
$daftar_menu_master = ambil_semua_menu($conn);
$lookup_menu = [];
foreach ($daftar_menu_master as $m) {
    $lookup_menu[(int) $m['id_menu']] = $m;
}

// 1. Tangani Aksi Pengelolaan Keranjang (Update Qty / Hapus Item / Kosongkan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_keranjang'])) {
    $aksi = (string) $_POST['aksi_keranjang'];

    if ($aksi === 'update') {
        $id_menu = isset($_POST['id_menu']) ? (int) $_POST['id_menu'] : 0;
        $jumlah = isset($_POST['jumlah']) ? (int) $_POST['jumlah'] : 0;
        perbarui_keranjang($id_menu, $jumlah);
        header("Location: pesanan.php");
        exit;
    } elseif ($aksi === 'hapus') {
        $id_menu = isset($_POST['id_menu']) ? (int) $_POST['id_menu'] : 0;
        hapus_dari_keranjang($id_menu);
        header("Location: pesanan.php");
        exit;
    } elseif ($aksi === 'kosongkan') {
        kosongkan_keranjang();
        header("Location: pesanan.php");
        exit;
    }
}

// 2. Tangani Aksi Buat Pesanan (P-Spec 1.4 & 1.5)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_pesanan']) && $_POST['aksi_pesanan'] === 'buat_pesanan') {
    $alamat_pengiriman = isset($_POST['alamat_pengiriman']) ? (string) $_POST['alamat_pengiriman'] : '';
    $keranjang = ambil_keranjang();

    // Jalankan validasi bisnis prosedural
    $hasil_validasi = validasi_pesanan($keranjang, $alamat_pengiriman, $daftar_menu_master);

    if ($hasil_validasi['sukses']) {
        // Ambil id_pengguna jika customer sedang login (D4: PENGGUNA)
        $id_pengguna_login = isset($_SESSION['id_pengguna']) ? (int) $_SESSION['id_pengguna'] : null;

        // Simpan ke D2 (PESANAN) dan D3 (DETAIL_PESANAN) dengan referensi ke PENGGUNA
        $id_pesanan_baru = simpan_pesanan($conn, $hasil_validasi['alamat'], $hasil_validasi['items_valid'], $id_pengguna_login);

        if ($id_pesanan_baru !== false) {
            // Bersihkan keranjang belanja setelah berhasil merekam transaksi
            kosongkan_keranjang();

            // Alihkan ke Halaman Pembayaran (Proses 2.0)
            header("Location: pembayaran.php?id=" . $id_pesanan_baru . "&status_order=berhasil");
            exit;
        } else {
            $pesan_error[] = "Terjadi kegagalan saat menyimpan transaksi ke basis data (Rollback dilakukan).";
        }
    } else {
        $pesan_error = $hasil_validasi['pesan_error'];
    }
}

// 3. Bangun Ringkasan Item Keranjang Belanja
$keranjang = ambil_keranjang();
$items_keranjang = [];
$total_keseluruhan = 0.0;

foreach ($keranjang as $id_menu => $qty) {
    $id = (int) $id_menu;
    $jumlah = (int) $qty;

    if ($jumlah > 0 && isset($lookup_menu[$id])) {
        $menu_item = $lookup_menu[$id];
        $harga = (float) $menu_item['harga'];
        $subtotal = hitung_subtotal($harga, $jumlah);

        $items_keranjang[] = [
            'id_menu'     => $id,
            'nama_menu'   => (string) $menu_item['nama_menu'],
            'harga'       => $harga,
            'jumlah'      => $jumlah,
            'subtotal'    => $subtotal,
            'status_menu' => (string) $menu_item['status_menu'],
            'gambar_url'  => dapatkan_gambar_menu((string) $menu_item['nama_menu']),
        ];
    }
}

// Hitung total keseluruhan menggunakan fungsi prosedural
$total_keseluruhan = hitung_total_harga($items_keranjang);

$judul_halaman = 'Keranjang & Checkout Pesanan';
$tahap_aktif = 2;

require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb Navigasi -->
<div class="mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted"><i class="bi bi-house-door me-1"></i>Beranda</a></li>
            <li class="breadcrumb-item active fw-bold text-dark" aria-current="page">Keranjang Pesanan</li>
        </ol>
    </nav>
</div>

<!-- Header Judul -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h3 class="fw-extrabold mb-1 text-dark">
            <i class="bi bi-bag-heart-fill text-danger me-2"></i>Keranjang &amp; Konfirmasi Checkout
        </h3>
        <p class="text-muted small mb-0">Periksa daftar hidangan pilihan Anda dan lengkapi alamat pengantaran.</p>
    </div>
    <a href="index.php" class="btn btn-outline-custom rounded-pill mt-2 mt-sm-0">
        <i class="bi bi-plus-circle me-1"></i> Tambah Menu Lain
    </a>
</div>

<!-- Tampilkan Pesan Error Validasi jika ada -->
<?php if (!empty($pesan_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-4 mb-4" role="alert">
        <div class="d-flex align-items-center mb-1">
            <i class="bi bi-exclamation-octagon-fill fs-5 me-2 text-danger"></i>
            <span class="fw-bold">Pemeriksaan Pesanan Gagal:</span>
        </div>
        <ul class="mb-0 ps-4 small">
            <?php foreach ($pesan_error as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (empty($items_keranjang)): ?>
    <!-- Tampilan Keranjang Kosong ala Super-App -->
    <div class="card shadow-sm border-0 rounded-4 text-center py-5 my-4 bg-white">
        <div class="card-body py-5">
            <div class="p-3 bg-danger-subtle text-danger rounded-circle d-inline-flex mb-3 shadow-sm">
                <i class="bi bi-bag-x fs-1"></i>
            </div>
            <h4 class="fw-extrabold text-dark">Keranjang Belanja Masih Kosong</h4>
            <p class="text-muted mx-auto mb-4" style="max-width: 480px;">
                Belum ada hidangan yang Anda masukkan ke keranjang. Jelajahi menu favorit nusantara dan pilih yang Anda sukai!
            </p>
            <a href="index.php" class="btn btn-primary-custom px-4 py-2 rounded-pill shadow-sm">
                <i class="bi bi-grid-fill me-2"></i> Jelajahi Menu Makanan
            </a>
        </div>
    </div>
<?php else: ?>
    <!-- Form & Ringkasan Checkout 2 Kolom -->
    <div class="row g-4 mb-5">
        <!-- Kolom Kiri: Daftar Item Keranjang -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <div class="fw-bold text-dark fs-6">
                        <i class="bi bi-card-checklist text-danger me-2"></i>Rincian Pesanan (<?= count($items_keranjang) ?> Menu)
                    </div>
                    <form method="POST" action="pesanan.php" onsubmit="return confirm('Apakah Anda yakin ingin mengosongkan seluruh keranjang?');">
                        <input type="hidden" name="aksi_keranjang" value="kosongkan">
                        <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none p-0 fw-semibold">
                            <i class="bi bi-trash3 me-1"></i> Kosongkan
                        </button>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light small text-uppercase text-muted">
                                <tr>
                                    <th class="ps-3" style="min-width: 220px;">Hidangan</th>
                                    <th class="text-end" style="min-width: 100px;">Harga</th>
                                    <th class="text-center" style="min-width: 130px;">Jumlah</th>
                                    <th class="text-end" style="min-width: 110px;">Subtotal</th>
                                    <th class="text-center pe-3" style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items_keranjang as $item): ?>
                                    <?php 
                                        $id = (int) $item['id_menu'];
                                        $is_tersedia = (strcasecmp((string) $item['status_menu'], 'Tersedia') === 0);
                                    ?>
                                    <tr class="<?= !$is_tersedia ? 'table-warning' : '' ?>">
                                        <td class="ps-3 py-3">
                                            <div class="d-flex align-items-center">
                                                <img src="<?= e($item['gambar_url']) ?>" alt="<?= e($item['nama_menu']) ?>" class="rounded-3 me-3" style="width: 54px; height: 54px; object-fit: cover;">
                                                <div>
                                                    <div class="fw-bold text-dark"><?= e($item['nama_menu']) ?></div>
                                                    <?php if (!$is_tersedia): ?>
                                                        <span class="badge bg-danger small">Stok Habis (Hapus untuk lanjut)</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success-subtle text-success small">Tersedia</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end text-muted font-monospace small">
                                            <?= format_rupiah((float) $item['harga']) ?>
                                        </td>
                                        <td class="text-center">
                                            <form method="POST" action="pesanan.php" class="d-inline-flex align-items-center justify-content-center">
                                                <input type="hidden" name="aksi_keranjang" value="update">
                                                <input type="hidden" name="id_menu" value="<?= $id ?>">
                                                
                                                <div class="qty-stepper">
                                                    <button class="qty-stepper-btn" type="submit" name="jumlah" value="<?= max(0, $item['jumlah'] - 1) ?>">&minus;</button>
                                                    <span class="qty-stepper-input d-flex align-items-center justify-content-center"><?= $item['jumlah'] ?></span>
                                                    <button class="qty-stepper-btn" type="submit" name="jumlah" value="<?= min(50, $item['jumlah'] + 1) ?>">&#43;</button>
                                                </div>
                                            </form>
                                        </td>
                                        <td class="text-end fw-bold text-dark font-monospace">
                                            <?= format_rupiah((float) $item['subtotal']) ?>
                                        </td>
                                        <td class="text-center pe-3">
                                            <form method="POST" action="pesanan.php">
                                                <input type="hidden" name="aksi_keranjang" value="hapus">
                                                <input type="hidden" name="id_menu" value="<?= $id ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="3" class="text-end py-3 ps-3">Total Subtotal Menu:</th>
                                    <th class="text-end py-3 fs-6 text-danger font-monospace"><?= format_rupiah($total_keseluruhan) ?></th>
                                    <th class="pe-3"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Catatan Kepatuhan SRS -->
            <div class="p-3 bg-white rounded-4 border shadow-sm small text-muted d-flex align-items-start gap-3">
                <i class="bi bi-info-circle-fill fs-5 text-primary mt-0"></i>
                <div>
                    <strong>P-Spec 1.5 &amp; Integritas DataStore:</strong> 
                    Data pesanan disimpan secara atomik ke <code>PESANAN</code> (D2) dengan status awal <code>Menunggu</code>, 
                    dan setiap rincian item tercatat ke <code>DETAIL_PESANAN</code> (D3) terhubung ke akun <code>PENGGUNA</code> (D4).
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Form Alamat & Tagihan Checkout -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 p-4 bg-white sticky-top" style="top: 85px;">
                <h5 class="fw-bold mb-3 pb-2 border-bottom text-dark d-flex align-items-center">
                    <i class="bi bi-geo-alt-fill text-danger me-2"></i>Alamat &amp; Pembayaran
                </h5>

                <form method="POST" action="pesanan.php" id="formCheckout">
                    <input type="hidden" name="aksi_pesanan" value="buat_pesanan">

                    <?php if (apakah_login()): ?>
                        <?php $user_aktif = ambil_pengguna_login(); ?>
                        <div class="alert alert-success d-flex align-items-center py-2 px-3 mb-3 small rounded-3">
                            <i class="bi bi-person-check-fill fs-5 me-2 text-success"></i>
                            <div>
                                Memesan sebagai: <strong><?= e($user_aktif['nama_lengkap']) ?></strong> (@<?= e($user_aktif['username']) ?>)
                                <div class="text-muted" style="font-size: 0.72rem;">ID Pengguna otomatis terhubung ke tabel PESANAN (D2)</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border d-flex align-items-center py-2 px-3 mb-3 small rounded-3">
                            <i class="bi bi-person-exclamation fs-5 me-2 text-secondary"></i>
                            <div>
                                Anda memesan sebagai tamu.
                                <a href="login.php?ref=pesanan.php" class="fw-bold text-danger text-decoration-none ms-1">Masuk / Daftar</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Form Alamat Pengantaran -->
                    <div class="mb-3">
                        <label for="alamat_pengiriman" class="form-label fw-bold small text-dark">
                            Alamat Lengkap Pengantaran <span class="text-danger">*</span>
                        </label>
                        <div class="driver-location-box mb-2">
                            <div class="d-flex align-items-center gap-2 text-danger fw-bold small mb-1">
                                <i class="bi bi-geo-fill"></i> Titik Antar Kurir
                            </div>
                            <textarea 
                                class="form-control border-0 bg-transparent p-0" 
                                id="alamat_pengiriman" 
                                name="alamat_pengiriman" 
                                rows="3" 
                                placeholder="Tuliskan alamat pengantaran lengkap, nomor rumah, dan patokan jalan..." 
                                required><?= e($alamat_pengiriman) ?></textarea>
                        </div>
                        <small class="text-muted d-block" style="font-size: 0.75rem;">
                            Pastikan mencantumkan nomor rumah, patokan jalan, atau instruksi khusus untuk memudahkan kurir.
                        </small>
                    </div>

                    <!-- Voucher & Promo Ongkir -->
                    <div class="d-flex align-items-center justify-content-between p-2 mb-3 bg-light rounded-3 border">
                        <div class="d-flex align-items-center gap-2 small">
                            <i class="bi bi-tag-fill text-danger"></i>
                            <span class="fw-semibold">Voucher Ongkir Cepat</span>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold">Gratis Ongkir</span>
                    </div>

                    <!-- Rincian Biaya -->
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-muted">Total Subtotal Menu:</span>
                            <span class="fw-bold font-monospace"><?= format_rupiah($total_keseluruhan) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-muted">Biaya Pengantaran (Kurir):</span>
                            <span class="text-success fw-bold"><del class="text-muted font-monospace me-1">Rp 12.000</del> Gratis</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-muted">Biaya Layanan &amp; Penanganan:</span>
                            <span class="text-success fw-bold">Gratis</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-extrabold text-dark fs-6">Total Pembayaran:</span>
                            <span class="fw-extrabold text-danger fs-5 font-monospace"><?= format_rupiah($total_keseluruhan) ?></span>
                        </div>
                    </div>

                    <!-- Tombol Lanjut ke Pembayaran -->
                    <button type="submit" class="btn btn-primary-custom w-100 py-3 fs-6 fw-bold rounded-pill shadow-sm">
                        <i class="bi bi-wallet2 me-2"></i> Lanjut ke Pembayaran
                    </button>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <i class="bi bi-shield-check text-success me-1"></i> Transaksi terverifikasi aman &amp; bergaransi sampai
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
