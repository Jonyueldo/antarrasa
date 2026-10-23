<?php
declare(strict_types=1);

/**
 * Halaman Pesanan / Checkout "AntarRasa"
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
        // Simpan ke D2 (PESANAN) dan D3 (DETAIL_PESANAN)
        $id_pesanan_baru = simpan_pesanan($conn, $hasil_validasi['alamat'], $hasil_validasi['items_valid']);

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

$judul_halaman = 'Checkout & Konfirmasi Pesanan';
$tahap_aktif = 2;
$tampilkan_stepper = true;

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">
            <i class="bi bi-cart-check text-primary me-2"></i>Checkout & Konfirmasi Pesanan
        </h3>
        <p class="text-muted small mb-0">Periksa daftar hidangan pilihan Anda dan cantumkan alamat pengiriman tujuan.</p>
    </div>
    <a href="index.php" class="btn btn-outline-custom">
        <i class="bi bi-arrow-left me-1"></i> Tambah Menu Lain
    </a>
</div>

<!-- Tampilkan Pesan Error Validasi jika ada -->
<?php if (!empty($pesan_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <div class="d-flex align-items-center mb-1">
            <i class="bi bi-exclamation-octagon-fill fs-5 me-2"></i>
            <span class="fw-bold">Validasi Pesanan Gagal:</span>
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
    <!-- Tampilan Keranjang Kosong -->
    <div class="card shadow-sm border-0 text-center py-5 my-4">
        <div class="card-body">
            <div class="display-1 text-muted mb-3">
                <i class="bi bi-cart-x"></i>
            </div>
            <h4 class="fw-bold text-secondary">Keranjang Belanja Masih Kosong</h4>
            <p class="text-muted mx-auto" style="max-width: 480px;">
                Anda belum memilih menu makanan apapun dari katalog kami. Silakan kembali ke katalog untuk memilih hidangan favorit Anda.
            </p>
            <a href="index.php" class="btn btn-primary-custom px-4 py-2 mt-2">
                <i class="bi bi-grid me-2"></i> Jelajahi Menu Makanan
            </a>
        </div>
    </div>
<?php else: ?>
    <!-- Form & Ringkasan Checkout -->
    <div class="row g-4">
        <!-- Kolom Kiri: Tabel Rincian Menu -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-card-checklist text-primary me-2"></i>Daftar Menu yang Dipesan
                    </h5>
                    <form method="POST" action="pesanan.php" onsubmit="return confirm('Apakah Anda yakin ingin mengosongkan seluruh keranjang?');">
                        <input type="hidden" name="aksi_keranjang" value="kosongkan">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash3 me-1"></i> Kosongkan Keranjang
                        </button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead class="table-light">
                            <tr class="small text-uppercase text-muted">
                                <th style="min-width: 240px;">Hidangan</th>
                                <th class="text-end" style="min-width: 110px;">Harga</th>
                                <th class="text-center" style="min-width: 140px;">Kuantitas</th>
                                <th class="text-end" style="min-width: 120px;">Subtotal</th>
                                <th class="text-center" style="width: 50px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items_keranjang as $item): ?>
                                <?php 
                                    $id = (int) $item['id_menu'];
                                    $is_tersedia = (strcasecmp((string) $item['status_menu'], 'Tersedia') === 0);
                                ?>
                                <tr class="<?= !$is_tersedia ? 'table-warning' : '' ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= e($item['gambar_url']) ?>" alt="<?= e($item['nama_menu']) ?>" class="rounded-3 me-3" style="width: 52px; height: 52px; object-fit: cover;">
                                            <div>
                                                <div class="fw-bold text-dark"><?= e($item['nama_menu']) ?></div>
                                                <?php if (!$is_tersedia): ?>
                                                    <span class="badge bg-danger small">Stok Habis (Wajib dihapus)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success-subtle text-success small">Tersedia</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end text-muted font-monospace">
                                        <?= format_rupiah((float) $item['harga']) ?>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" action="pesanan.php" class="d-inline-flex align-items-center justify-content-center">
                                            <input type="hidden" name="aksi_keranjang" value="update">
                                            <input type="hidden" name="id_menu" value="<?= $id ?>">
                                            <div class="input-group input-group-sm" style="width: 110px;">
                                                <input type="number" name="jumlah" class="form-control text-center" value="<?= (int) $item['jumlah'] ?>" min="1" max="50">
                                                <button type="submit" class="btn btn-outline-secondary" title="Perbarui Qty">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                    <td class="text-end fw-bold text-dark font-monospace">
                                        <?= format_rupiah((float) $item['subtotal']) ?>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" action="pesanan.php">
                                            <input type="hidden" name="aksi_keranjang" value="hapus">
                                            <input type="hidden" name="id_menu" value="<?= $id ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger border-0" title="Hapus dari keranjang">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="3" class="text-end py-3">Total Akumulasi Subtotal:</th>
                                <th class="text-end py-3 fs-5 text-primary font-monospace"><?= format_rupiah($total_keseluruhan) ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Catatan Kepatuhan SRS -->
            <div class="alert alert-info d-flex align-items-start small shadow-sm">
                <i class="bi bi-info-circle-fill fs-5 me-3 text-info mt-1"></i>
                <div>
                    <strong>P-Spec 1.5 & Konsistensi DataStore:</strong> 
                    Saat tombol "Buat Pesanan Sekarang" diklik, sistem mengeksekusi transaksi basis data atomik. 
                    Header pesanan dicatat ke <code>PESANAN</code> (D2) dengan status awal <code>Menunggu</code>, 
                    dan rincian item direkam ke <code>DETAIL_PESANAN</code> (D3).
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Form Alamat & Ringkasan Checkout -->
        <div class="col-lg-4">
            <div class="order-summary-card shadow-sm sticky-top" style="top: 85px;">
                <h5 class="fw-bold mb-3 pb-2 border-bottom text-dark">
                    <i class="bi bi-geo-alt-fill text-danger me-2"></i>Informasi Pengiriman
                </h5>

                <form method="POST" action="pesanan.php" id="formCheckout">
                    <input type="hidden" name="aksi_pesanan" value="buat_pesanan">

                    <div class="mb-3">
                        <label for="alamat_pengiriman" class="form-label fw-semibold">
                            Alamat Lengkap Pengiriman <span class="text-danger">*</span>
                        </label>
                        <textarea 
                            class="form-control" 
                            id="alamat_pengiriman" 
                            name="alamat_pengiriman" 
                            rows="4" 
                            placeholder="Contoh: Jl. Sudirman No. 45, RT 02 / RW 05, Kel. Senayan, Kec. Kebayoran Baru, Jakarta Selatan. (Depan Pos Satpam)" 
                            required><?= e($alamat_pengiriman) ?></textarea>
                        <div class="form-text small">
                            Pastikan mencantumkan nomor rumah, patokan jalan, atau catatan khusus untuk kurir.
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Hidangan:</span>
                            <span class="fw-semibold"><?= count($items_keranjang) ?> Jenis Menu</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Ongkos Kirim:</span>
                            <span class="text-success fw-semibold"><i class="bi bi-gift-fill me-1"></i>Gratis (Promo MVP)</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark fs-6">Total Pembayaran:</span>
                            <span class="fw-bold text-primary fs-5 font-monospace"><?= format_rupiah($total_keseluruhan) ?></span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-custom w-100 py-3 fs-6 shadow">
                        <i class="bi bi-check2-circle me-2"></i> Buat Pesanan Sekarang
                    </button>
                    <div class="text-center mt-2">
                        <span class="text-muted small">
                            <i class="bi bi-shield-lock me-1"></i> Transaksi Prosedural Aman
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
