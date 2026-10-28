<?php
declare(strict_types=1);

/**
 * Halaman Pembayaran (Simulasi Proses 2.0: Kelola Pembayaran)
 * Menampilkan tagihan pesanan dari D2 & D3, pilihan Payment Gateway (GoPay, OVO, ShopeePay, QRIS),
 * serta tombol konfirmasi pembayaran yang mengubah status_pesanan menjadi 'Diproses'.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions_order.php';

mulai_sesi();

$id_pesanan = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id_pesanan']) ? (int) $_POST['id_pesanan'] : 0);
$pesan_error = '';
$pesan_sukses = '';

if ($id_pesanan <= 0) {
    header("Location: index.php");
    exit;
}

// 1. Tangani Aksi Simulasi Pembayaran (Proses 2.0)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'bayar_pesanan') {
    $metode_bayar = isset($_POST['metode_bayar']) ? (string) $_POST['metode_bayar'] : 'GoPay';
    
    // Ambil data pesanan saat ini
    $pesanan_cek = ambil_pesanan_by_id($conn, $id_pesanan);
    if ($pesanan_cek !== null) {
        if ($pesanan_cek['status_pesanan'] === 'Menunggu') {
            // Perbarui status pesanan di D2 (PESANAN) menjadi 'Diproses'
            $sukses_update = update_status_pesanan($conn, $id_pesanan, 'Diproses');
            if ($sukses_update) {
                // Alihkan ke Halaman Status & Pelacakan (Proses 3.0)
                header("Location: status.php?id=" . $id_pesanan . "&bayar_sukses=1&metode=" . urlencode($metode_bayar));
                exit;
            } else {
                $pesan_error = "Gagal memperbarui status pembayaran pada database.";
            }
        } else {
            // Sudah dibayar sebelumnya
            header("Location: status.php?id=" . $id_pesanan);
            exit;
        }
    } else {
        $pesan_error = "Data pesanan tidak ditemukan.";
    }
}

// 2. Ambil Data Pesanan dari D2 (PESANAN) dan D3 (DETAIL_PESANAN)
$pesanan = ambil_pesanan_by_id($conn, $id_pesanan);

if ($pesanan === null) {
    $judul_halaman = 'Pesanan Tidak Ditemukan';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="card shadow-sm border-0 rounded-4 text-center py-5 my-5 bg-white">
        <div class="card-body">
            <i class="bi bi-search text-danger display-3 mb-3"></i>
            <h4 class="fw-bold">Pesanan #<?= $id_pesanan ?> Tidak Ditemukan</h4>
            <p class="text-muted">Nomor transaksi pesanan tidak terdaftar pada DataStore D2 (PESANAN).</p>
            <a href="index.php" class="btn btn-primary-custom rounded-pill mt-2">Kembali ke Katalog Menu</a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$detail_items = ambil_detail_pesanan($conn, $id_pesanan);
$status_info = dapatkan_info_status((string) $pesanan['status_pesanan']);
$is_sudah_bayar = ($pesanan['status_pesanan'] !== 'Menunggu');

$judul_halaman = 'Pembayaran Pesanan #' . $id_pesanan;
$tahap_aktif = 3;

require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb Navigasi -->
<div class="mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted"><i class="bi bi-house-door me-1"></i>Beranda</a></li>
            <li class="breadcrumb-item"><a href="pesanan.php" class="text-decoration-none text-muted">Keranjang</a></li>
            <li class="breadcrumb-item active fw-bold text-dark" aria-current="page">Pembayaran #<?= $id_pesanan ?></li>
        </ol>
    </nav>
</div>

<!-- Header Halaman Pembayaran -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h3 class="fw-extrabold mb-1 text-dark">
            <i class="bi bi-wallet2 text-danger me-2"></i>Tagihan &amp; Pembayaran Digital
        </h3>
        <p class="text-muted small mb-0">Simulasi Proses 2.0 (Kelola Pembayaran) - Verifikasi faktur dan selesaikan transaksi.</p>
    </div>
    <div class="mt-2 mt-sm-0">
        <span class="badge <?= $status_info['class'] ?> fs-6 px-3 py-2 rounded-pill">
            <i class="bi <?= $status_info['icon'] ?> me-1"></i> Status: <?= e($pesanan['status_pesanan']) ?>
        </span>
    </div>
</div>

<?php if (isset($_GET['status_order']) && $_GET['status_order'] === 'berhasil'): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4 d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
        <div>
            <strong>Pesanan Berhasil Dicatat!</strong> Transaksi telah tersimpan di <code>PESANAN</code> (D2) &amp; <code>DETAIL_PESANAN</code> (D3) dengan nomor <strong>#<?= $id_pesanan ?></strong>. Silakan pilih metode pembayaran untuk melanjutkan pemrosesan.
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($pesan_error !== ''): ?>
    <div class="alert alert-danger shadow-sm rounded-4 mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($pesan_error) ?>
    </div>
<?php endif; ?>

<div class="row g-4 mb-5">
    <!-- Kolom Kiri: Rincian Faktur Digital Modern (Struk Kasir Digital) -->
    <div class="col-lg-7">
        <div class="receipt-digital shadow-sm">
            <!-- Header Struk -->
            <div class="d-flex justify-content-between align-items-start pb-3 border-bottom border-dashed mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="p-2 rounded-circle bg-danger text-white d-inline-flex"><i class="bi bi-egg-fried fs-5"></i></span>
                    <div>
                        <div class="fw-extrabold text-dark fs-5">AntarRasa Super-App</div>
                        <div class="text-muted small">Faktur Pembayaran Kuliner Resmi</div>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-light text-secondary border font-monospace">PESANAN #<?= $id_pesanan ?></span>
                    <div class="text-muted small mt-1"><?= date('d M Y, H:i', strtotime((string) $pesanan['tanggal_pesanan'])) ?> WIB</div>
                </div>
            </div>

            <!-- Detail Pemesan & Alamat -->
            <div class="p-3 bg-light rounded-3 mb-3 border">
                <div class="row g-2">
                    <div class="col-sm-6">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem;">Nama Pelanggan (D4)</small>
                        <div class="fw-bold text-dark"><?= e($pesanan['nama_pemesan'] ?? 'Pelanggan Tamu') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem;">Status Transaksi</small>
                        <div><span class="badge <?= $status_info['class'] ?> rounded-pill small"><?= e($pesanan['status_pesanan']) ?></span></div>
                    </div>
                    <div class="col-12 mt-2 pt-2 border-top">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem;">Alamat Pengantaran (D2)</small>
                        <div class="text-dark small"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= nl2br(e($pesanan['alamat_pengiriman'])) ?></div>
                    </div>
                </div>
            </div>

            <!-- Rincian Item Menu -->
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th>Menu</th>
                            <th class="text-end">Harga</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detail_items as $item): ?>
                            <tr>
                                <td class="fw-semibold text-dark py-2"><?= e($item['nama_menu']) ?></td>
                                <td class="text-end text-muted font-monospace small"><?= format_rupiah((float) $item['harga']) ?></td>
                                <td class="text-center font-monospace small"><?= (int) $item['jumlah'] ?>x</td>
                                <td class="text-end fw-bold text-dark font-monospace"><?= format_rupiah((float) $item['subtotal']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="border-top">
                        <tr>
                            <td colspan="3" class="text-end text-muted small py-2">Ongkos Kirim Cepat:</td>
                            <td class="text-end text-success fw-bold small py-2">Gratis</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end fw-bold py-2 fs-6">Total Tagihan:</td>
                            <td class="text-end fw-extrabold text-danger fs-5 font-monospace py-2">
                                <?= format_rupiah((float) $pesanan['total_harga']) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Footer Struk -->
            <div class="text-center pt-2 border-top border-dashed text-muted small">
                <i class="bi bi-shield-check text-success me-1"></i> Pembayaran Anda dijamin aman dengan standar IEEE Std 830-1998
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Pilihan Payment Gateway & Konfirmasi Pembayaran -->
    <div class="col-lg-5">
        <?php if ($is_sudah_bayar): ?>
            <!-- Jika Sudah Bayar -->
            <div class="card shadow-sm border-success border-2 rounded-4 text-center p-4 bg-white">
                <div class="p-3 bg-success-subtle text-success rounded-circle d-inline-flex mx-auto mb-3">
                    <i class="bi bi-patch-check-fill fs-1"></i>
                </div>
                <h4 class="fw-extrabold text-success mb-1">Tagihan Telah Lunas!</h4>
                <p class="text-muted small mb-4">
                    Pesanan #<?= $id_pesanan ?> saat ini berstatus <strong><?= e($pesanan['status_pesanan']) ?></strong> dan dapur restoran sedang mempersiapkan pesanan Anda.
                </p>
                <a href="status.php?id=<?= $id_pesanan ?>" class="btn btn-primary-custom py-3 rounded-pill fw-bold shadow-sm d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>Lacak Pengantaran Real-Time</span>
                </a>
            </div>
        <?php else: ?>
            <!-- Form Pilihan E-Wallet & Payment Gateway -->
            <div class="card shadow-sm border-0 rounded-4 p-4 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-credit-card-2-front-fill text-danger me-2"></i>Metode Pembayaran
                    </h5>
                    <span class="badge bg-danger-subtle text-danger fw-bold">E-Wallet &amp; QRIS</span>
                </div>

                <form method="POST" action="pembayaran.php">
                    <input type="hidden" name="aksi" value="bayar_pesanan">
                    <input type="hidden" name="id_pesanan" value="<?= $id_pesanan ?>">

                    <div class="d-flex flex-column gap-2 mb-3">
                        <!-- GoPay -->
                        <label class="border rounded-4 p-3 d-flex align-items-center justify-content-between cursor-pointer payment-method-card active-method">
                            <div class="d-flex align-items-center gap-3">
                                <input type="radio" name="metode_bayar" value="GoPay" class="form-check-input mt-0" checked>
                                <div>
                                    <div class="fw-extrabold text-dark"><i class="bi bi-wallet2 text-primary me-1"></i> GoPay</div>
                                    <div class="text-muted small">Saldo E-Wallet Instan &bull; Tanpa Biaya Admin</div>
                                </div>
                            </div>
                            <span class="badge bg-primary-subtle text-primary fw-bold">Paling Populer</span>
                        </label>

                        <!-- ShopeePay -->
                        <label class="border rounded-4 p-3 d-flex align-items-center justify-content-between cursor-pointer payment-method-card">
                            <div class="d-flex align-items-center gap-3">
                                <input type="radio" name="metode_bayar" value="ShopeePay" class="form-check-input mt-0">
                                <div>
                                    <div class="fw-extrabold text-dark"><i class="bi bi-bag-check-fill text-danger me-1"></i> ShopeePay</div>
                                    <div class="text-muted small">Saldo Dompet Shopee &bull; Cashback Koin</div>
                                </div>
                            </div>
                            <span class="badge bg-danger-subtle text-danger fw-bold">Diskon</span>
                        </label>

                        <!-- OVO -->
                        <label class="border rounded-4 p-3 d-flex align-items-center justify-content-between cursor-pointer payment-method-card">
                            <div class="d-flex align-items-center gap-3">
                                <input type="radio" name="metode_bayar" value="OVO" class="form-check-input mt-0">
                                <div>
                                    <div class="fw-extrabold text-dark"><i class="bi bi-phone-fill text-purple me-1" style="color: #6366f1;"></i> OVO Cash</div>
                                    <div class="text-muted small">Push Notification Langsung ke HP</div>
                                </div>
                            </div>
                            <span class="badge bg-light text-secondary border fw-bold">Instan</span>
                        </label>

                        <!-- QRIS -->
                        <label class="border rounded-4 p-3 d-flex align-items-center justify-content-between cursor-pointer payment-method-card">
                            <div class="d-flex align-items-center gap-3">
                                <input type="radio" name="metode_bayar" value="QRIS" class="form-check-input mt-0">
                                <div>
                                    <div class="fw-extrabold text-dark"><i class="bi bi-qr-code-scan text-success me-1"></i> QRIS Nasional</div>
                                    <div class="text-muted small">BCA, Mandiri, BRI, BNI, Dana, LinkAja</div>
                                </div>
                            </div>
                            <span class="badge bg-success-subtle text-success fw-bold">Semua Bank</span>
                        </label>
                    </div>

                    <!-- Jumlah Tagihan -->
                    <div class="p-3 bg-light rounded-3 mb-3 border d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark">Total Pembayaran:</span>
                        <span class="fw-extrabold text-danger fs-5 font-monospace"><?= format_rupiah((float) $pesanan['total_harga']) ?></span>
                    </div>

                    <!-- Tombol Konfirmasi Simulasi Pembayaran -->
                    <button type="submit" class="btn btn-success w-100 py-3 fw-bold rounded-pill shadow-sm fs-6">
                        <i class="bi bi-check-circle-fill me-2"></i> Konfirmasi Pembayaran Sekarang
                    </button>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted" style="font-size: 0.75rem;">
                            Simulasi otomatis mengubah status di <code>PESANAN</code> (D2) menjadi <strong>'Diproses'</strong>
                        </small>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.payment-method-card {
    transition: all 0.2s ease;
}
.payment-method-card:hover {
    border-color: var(--cf-primary) !important;
    background-color: var(--cf-primary-light);
}
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
