<?php
declare(strict_types=1);

/**
 * Halaman Pembayaran (Simulasi Proses 2.0: Kelola Pembayaran)
 * Menampilkan tagihan pesanan dari D2 & D3, pilihan Payment Gateway,
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
    <div class="card shadow-sm border-0 text-center py-5 my-5">
        <div class="card-body">
            <i class="bi bi-search text-danger display-3 mb-3"></i>
            <h4 class="fw-bold">Pesanan #<?= $id_pesanan ?> Tidak Ditemukan</h4>
            <p class="text-muted">Nomor transaksi pesanan tidak terdaftar pada DataStore D2 (PESANAN).</p>
            <a href="index.php" class="btn btn-primary-custom mt-2">Kembali ke Katalog Menu</a>
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
$tampilkan_stepper = true;

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Halaman Pembayaran -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h3 class="fw-bold mb-1">
            <i class="bi bi-wallet2 text-primary me-2"></i>Tagihan & Pembayaran Pesanan
        </h3>
        <p class="text-muted small mb-0">Simulasi Proses 2.0 (Kelola Pembayaran) - Verifikasi tagihan dan selesaikan transaksi.</p>
    </div>
    <div class="mt-2 mt-sm-0">
        <span class="badge <?= $status_info['class'] ?> fs-6 px-3 py-2">
            <i class="bi <?= $status_info['icon'] ?> me-1"></i> Status: <?= e($pesanan['status_pesanan']) ?>
        </span>
    </div>
</div>

<?php if (isset($_GET['status_order']) && $_GET['status_order'] === 'berhasil'): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
        <div>
            <strong>Pesanan Berhasil Dicatat!</strong> Transaksi telah disimpan ke DataStore D2 (<code>PESANAN</code>) &amp; D3 (<code>DETAIL_PESANAN</code>) dengan nomor <strong>#<?= $id_pesanan ?></strong>. Silakan lanjutkan simulasi pembayaran.
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($pesan_error !== ''): ?>
    <div class="alert alert-danger shadow-sm">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($pesan_error) ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Kolom Kiri: Rincian Tagihan & Informasi Pesanan (D2 & D3) -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-receipt me-2 text-primary"></i>Rincian Faktur #<?= $id_pesanan ?>
                </h5>
                <span class="text-muted small font-monospace">
                    <?= date('d M Y, H:i', strtotime((string) $pesanan['tanggal_pesanan'])) ?> WIB
                </span>
            </div>
            
            <div class="card-body">
                <!-- Alamat Pengiriman -->
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-geo-alt-fill text-danger fs-5 me-2 mt-1"></i>
                        <div>
                            <div class="fw-bold text-dark small text-uppercase">Tujuan Pengantaran (D2):</div>
                            <div class="text-secondary"><?= nl2br(e($pesanan['alamat_pengiriman'])) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Tabel Item Menu yang Dipesan -->
                <div class="table-responsive mb-2">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Menu Makanan</th>
                                <th class="text-end">Harga Satuan</th>
                                <th class="text-center">Jumlah</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detail_items as $item): ?>
                                <tr>
                                    <td class="fw-semibold text-dark"><?= e($item['nama_menu']) ?></td>
                                    <td class="text-end text-muted font-monospace"><?= format_rupiah((float) $item['harga']) ?></td>
                                    <td class="text-center font-monospace"><?= (int) $item['jumlah'] ?>x</td>
                                    <td class="text-end fw-bold font-monospace"><?= format_rupiah((float) $item['subtotal']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-top">
                            <tr>
                                <td colspan="3" class="text-end fw-bold py-2">Total Tagihan:</td>
                                <td class="text-end fw-bold py-2 text-primary fs-5 font-monospace">
                                    <?= format_rupiah((float) $pesanan['total_harga']) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Pilihan Payment Gateway & Konfirmasi Pembayaran -->
    <div class="col-lg-5">
        <?php if ($is_sudah_bayar): ?>
            <!-- Jika Sudah Bayar -->
            <div class="card shadow-sm border-success border-2 rounded-4 text-center p-4">
                <div class="text-success display-4 mb-2">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <h4 class="fw-bold text-success mb-1">Tagihan Telah Lunas!</h4>
                <p class="text-muted small mb-4">
                    Pesanan #<?= $id_pesanan ?> saat ini berstatus <strong><?= e($pesanan['status_pesanan']) ?></strong> dan sedang dipersiapkan oleh pihak restoran.
                </p>
                <a href="status.php?id=<?= $id_pesanan ?>" class="btn btn-primary-custom py-2 shadow-sm">
                    <i class="bi bi-compass me-2"></i> Lacak Status Pengiriman (Proses 3.0)
                </a>
            </div>
        <?php else: ?>
            <!-- Form Simulasi Payment Gateway -->
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-credit-card-2-front text-primary me-2"></i>Pilih Metode Pembayaran
                    </h5>
                    <div class="text-muted small">Simulasi Gateway Pembayaran Digital</div>
                </div>

                <div class="card-body">
                    <form method="POST" action="pembayaran.php">
                        <input type="hidden" name="aksi" value="bayar_pesanan">
                        <input type="hidden" name="id_pesanan" value="<?= $id_pesanan ?>">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-uppercase text-muted">E-Wallet & QRIS:</label>
                            
                            <div class="d-flex flex-column gap-2">
                                <label class="border rounded-3 p-3 d-flex align-items-center justify-content-between cursor-pointer payment-option">
                                    <div class="d-flex align-items-center">
                                        <input type="radio" name="metode_bayar" value="GoPay" class="form-check-input me-3 mt-0" checked>
                                        <div>
                                            <div class="fw-bold">GoPay</div>
                                            <div class="small text-muted">Saldo E-Wallet Instan</div>
                                        </div>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary fw-bold">Instan</span>
                                </label>

                                <label class="border rounded-3 p-3 d-flex align-items-center justify-content-between cursor-pointer payment-option">
                                    <div class="d-flex align-items-center">
                                        <input type="radio" name="metode_bayar" value="OVO" class="form-check-input me-3 mt-0">
                                        <div>
                                            <div class="fw-bold">OVO Cash</div>
                                            <div class="small text-muted">Notifikasi Push Pembayaran</div>
                                        </div>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary fw-bold">Instan</span>
                                </label>

                                <label class="border rounded-3 p-3 d-flex align-items-center justify-content-between cursor-pointer payment-option">
                                    <div class="d-flex align-items-center">
                                        <input type="radio" name="metode_bayar" value="ShopeePay" class="form-check-input me-3 mt-0">
                                        <div>
                                            <div class="fw-bold">ShopeePay</div>
                                            <div class="small text-muted">Saldo Dompet Shopee</div>
                                        </div>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary fw-bold">Instan</span>
                                </label>

                                <label class="border rounded-3 p-3 d-flex align-items-center justify-content-between cursor-pointer payment-option">
                                    <div class="d-flex align-items-center">
                                        <input type="radio" name="metode_bayar" value="QRIS" class="form-check-input me-3 mt-0">
                                        <div>
                                            <div class="fw-bold">QRIS Standar Nasional</div>
                                            <div class="small text-muted">Scan QR dari semua m-Banking / E-Wallet</div>
                                        </div>
                                    </div>
                                    <span class="badge bg-success-subtle text-success fw-bold">Semua Bank</span>
                                </label>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Jumlah yang Dibayar:</span>
                                <span class="fw-bold text-primary fs-5 font-monospace"><?= format_rupiah((float) $pesanan['total_harga']) ?></span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100 py-3 fw-bold shadow">
                            <i class="bi bi-check-circle-fill me-2"></i> Konfirmasi Simulasi Pembayaran
                        </button>
                        <div class="text-center mt-2">
                            <span class="text-muted small">
                                Mengubah <code>status_pesanan</code> di D2 menjadi <strong>'Diproses'</strong>
                            </span>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.payment-option:hover {
    border-color: var(--ar-primary) !important;
    background-color: var(--ar-primary-light);
}
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
