<?php
declare(strict_types=1);

/**
 * Dashboard Khusus Driver / Kurir Pengantar
 * Gaya Aplikasi GoPartner / GrabDriver Modern (#0F172A + #10B981)
 * Memungkinkan driver mengambil pesanan siap antar ('Diproses' -> 'Dikirim')
 * dan menyelesaikan pengantaran ke pelanggan ('Dikirim' -> 'Selesai').
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions_auth.php';
require_once __DIR__ . '/includes/functions_order.php';

mulai_sesi();

// Otorisasi: Hanya driver dan admin yang dapat mengakses
cek_akses(['driver', 'admin']);

$pengguna = ambil_pengguna_login();
$pesan_sukses = '';
$pesan_error = '';

// Tangani Aksi Driver Mengubah Status Pesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_driver'])) {
    $id_pesanan   = (int) ($_POST['id_pesanan'] ?? 0);
    $status_tujuan = (string) ($_POST['status_tujuan'] ?? '');
    $transisi_valid = ['Dikirim', 'Selesai'];

    if ($id_pesanan > 0 && in_array($status_tujuan, $transisi_valid, true)) {
        $sukses = update_status_pesanan($conn, $id_pesanan, $status_tujuan);
        if ($sukses) {
            $pesan_sukses = "Status pesanan #{$id_pesanan} berhasil diperbarui menjadi '{$status_tujuan}'!";
        } else {
            $pesan_error = "Gagal memperbarui status pengantaran di database.";
        }
    }
}

// Ambil pesanan berdasarkan kategori status
$pesanan_siap_antar  = ambil_semua_pesanan($conn, 25, 'Diproses');
$pesanan_dalam_antar = ambil_semua_pesanan($conn, 25, 'Dikirim');
$pesanan_selesai     = ambil_semua_pesanan($conn, 15, 'Selesai');

$judul_halaman = 'Panel Pengantaran Driver - AntarRasa';
$tampilkan_stepper = false;

require_once __DIR__ . '/includes/header.php';
?>

<!-- HUD Quick Stats Bar (Driver Overview) -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card shadow-sm border-0 rounded-4 p-3 bg-white border-start border-4 border-primary">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Sedang Diantar</span>
                    <h3 class="fw-extrabold text-primary mb-0 mt-1"><?= count($pesanan_dalam_antar) ?></h3>
                </div>
                <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                    <i class="bi bi-bicycle fs-4"></i>
                </div>
            </div>
            <div class="text-muted small mt-2">Pesanan dalam perjalanan ke customer</div>
        </div>
    </div>

    <div class="col-sm-4">
        <div class="card shadow-sm border-0 rounded-4 p-3 bg-white border-start border-4 border-warning">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Siap Dijemput di Resto</span>
                    <h3 class="fw-extrabold text-dark mb-0 mt-1"><?= count($pesanan_siap_antar) ?></h3>
                </div>
                <div class="p-3 rounded-circle" style="background-color: #fef3c7; color: #d97706;">
                    <i class="bi bi-box-seam-fill fs-4"></i>
                </div>
            </div>
            <div class="text-muted small mt-2">Makanan matang siap diantar</div>
        </div>
    </div>

    <div class="col-sm-4">
        <div class="card shadow-sm border-0 rounded-4 p-3 bg-white border-start border-4 border-success">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Tuntas Hari Ini</span>
                    <h3 class="fw-extrabold text-success mb-0 mt-1"><?= count($pesanan_selesai) ?></h3>
                </div>
                <div class="bg-success-subtle text-success p-3 rounded-circle">
                    <i class="bi bi-check2-circle fs-4"></i>
                </div>
            </div>
            <div class="text-muted small mt-2">Pengantaran berhasil diselesaikan</div>
        </div>
    </div>
</div>

<!-- Alert Notifikasi -->
<?php if ($pesan_sukses !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center shadow-sm rounded-4 mb-4" role="alert">
        <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
        <div><?= e($pesan_sukses) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($pesan_error !== ''): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center shadow-sm rounded-4 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-danger"></i>
        <div><?= e($pesan_error) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- =====================================================================
     BAGIAN 1: PESANAN SEDANG DALAM PENGANTARAN (STATUS: 'Dikirim')
     ===================================================================== -->
<div class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-extrabold text-dark mb-0 d-flex align-items-center">
                <i class="bi bi-navigation-fill text-primary me-2"></i> Sedang Anda Antar Saat Ini
            </h4>
            <small class="text-muted">Selesaikan pesanan setelah sampai di alamat pelanggan</small>
        </div>
        <span class="badge bg-primary text-white fw-bold px-3 py-2 rounded-pill shadow-sm">
            <?= count($pesanan_dalam_antar) ?> Tugas Aktif
        </span>
    </div>

    <?php if (empty($pesanan_dalam_antar)): ?>
        <div class="card shadow-sm border-0 rounded-4 p-4 text-center bg-white mb-4">
            <div class="text-muted py-3">
                <i class="bi bi-bicycle fs-2 d-block mb-2 text-secondary"></i>
                Tidak ada pesanan yang sedang diantar saat ini. Silakan pilih tugas siap jemput di bawah.
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-4">
            <?php foreach ($pesanan_dalam_antar as $order): ?>
                <?php 
                    $id_o = (int) $order['id_pesanan'];
                    $rincian_item = ambil_detail_pesanan($conn, $id_o);
                ?>
                <div class="col-lg-6">
                    <div class="driver-card-job p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <!-- Header Job Card -->
                            <div class="d-flex justify-content-between align-items-start pb-2 border-bottom mb-3">
                                <div>
                                    <span class="badge bg-primary font-monospace fs-6">PESANAN #<?= $id_o ?></span>
                                    <div class="fw-bold text-dark fs-5 mt-1">
                                        <i class="bi bi-person-fill text-primary me-1"></i> <?= e($order['nama_pemesan'] ?? 'Tamu (Guest)') ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Sedang Diantar</span>
                                    <div class="text-muted small mt-1"><?= date('H:i', strtotime((string)$order['tanggal_pesanan'])) ?> WIB</div>
                                </div>
                            </div>

                            <!-- Alamat Kotak Pengantaran Menonjol -->
                            <div class="driver-location-box mb-3">
                                <div class="fw-bold text-danger small mb-1">
                                    <i class="bi bi-geo-alt-fill me-1"></i> ALAMAT TUJUAN PENGANTARAN:
                                </div>
                                <div class="text-dark fw-semibold small">
                                    <?= nl2br(e($order['alamat_pengiriman'])) ?>
                                </div>
                            </div>

                            <!-- Rincian Item Hidangan -->
                            <div class="p-2 bg-light rounded-3 mb-4 border small">
                                <span class="fw-bold text-muted text-uppercase d-block mb-1" style="font-size: 0.72rem;">Menu yang Diantar:</span>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($rincian_item as $it): ?>
                                        <li class="fw-semibold text-dark"><?= e($it['nama_menu']) ?> &times; <?= (int)$it['jumlah'] ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <!-- Tombol Selesaikan Pengantaran -->
                        <form method="POST" action="driver.php">
                            <input type="hidden" name="aksi_driver" value="1">
                            <input type="hidden" name="id_pesanan" value="<?= $id_o ?>">
                            <input type="hidden" name="status_tujuan" value="Selesai">
                            <button type="submit" class="btn btn-driver-finish w-100 py-3 fs-6 d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-check2-all fs-5"></i>
                                <span>Pesanan Sampai - Selesaikan Pengantaran (Ubah ke Selesai)</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- =====================================================================
     BAGIAN 2: TUGAS SIAP DIJEMPUT DI RESTORAN (STATUS: 'Diproses')
     ===================================================================== -->
<div class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-extrabold text-dark mb-0 d-flex align-items-center">
                <i class="bi bi-box-seam-fill text-warning me-2"></i> Tugas Siap Jemput di Restoran (Status: Diproses)
            </h4>
            <small class="text-muted">Makanan matang di dapur dan siap Anda ambil untuk diantarkan</small>
        </div>
        <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill shadow-sm">
            <?= count($pesanan_siap_antar) ?> Menunggu Kurir
        </span>
    </div>

    <?php if (empty($pesanan_siap_antar)): ?>
        <div class="card shadow-sm border-0 rounded-4 p-4 text-center bg-white mb-4">
            <div class="text-muted py-3">
                <i class="bi bi-hourglass-split fs-2 d-block mb-2 text-warning"></i>
                Belum ada pesanan berstatus 'Diproses' yang siap diambil dari dapur restoran.
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-4">
            <?php foreach ($pesanan_siap_antar as $order): ?>
                <?php 
                    $id_o = (int) $order['id_pesanan'];
                    $rincian_item = ambil_detail_pesanan($conn, $id_o);
                ?>
                <div class="col-lg-6">
                    <div class="driver-card-job p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <!-- Header Job Card -->
                            <div class="d-flex justify-content-between align-items-start pb-2 border-bottom mb-3">
                                <div>
                                    <span class="badge bg-warning text-dark font-monospace fs-6">ORDER #<?= $id_o ?></span>
                                    <div class="fw-bold text-dark fs-5 mt-1">
                                        <i class="bi bi-person me-1"></i> <?= e($order['nama_pemesan'] ?? 'Tamu (Guest)') ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Siap Diambil</span>
                                    <div class="fw-bold text-danger font-monospace mt-1"><?= format_rupiah((float)$order['total_harga']) ?></div>
                                </div>
                            </div>

                            <!-- Alamat Kotak Pengantaran Menonjol -->
                            <div class="driver-location-box mb-3">
                                <div class="fw-bold text-danger small mb-1">
                                    <i class="bi bi-geo-alt-fill me-1"></i> TITIK ANTAR:
                                </div>
                                <div class="text-dark fw-semibold small">
                                    <?= nl2br(e($order['alamat_pengiriman'])) ?>
                                </div>
                            </div>

                            <!-- Menu Hidangan -->
                            <div class="p-2 bg-light rounded-3 mb-4 border small">
                                <span class="fw-bold text-muted text-uppercase d-block mb-1" style="font-size: 0.72rem;">Hidangan di Dapur:</span>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($rincian_item as $it): ?>
                                        <li><?= e($it['nama_menu']) ?> &times; <?= (int)$it['jumlah'] ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <!-- Tombol Ambil & Antar -->
                        <form method="POST" action="driver.php">
                            <input type="hidden" name="aksi_driver" value="1">
                            <input type="hidden" name="id_pesanan" value="<?= $id_o ?>">
                            <input type="hidden" name="status_tujuan" value="Dikirim">
                            <button type="submit" class="btn btn-driver-pickup w-100 py-3 fs-6 d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-bicycle fs-5"></i>
                                <span>Ambil &amp; Antar Pesanan (Ubah ke Dikirim)</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- =====================================================================
     BAGIAN 3: RIWAYAT PENGANTARAN SELESAI
     ===================================================================== -->
<div class="card shadow-sm border-0 rounded-4 overflow-hidden bg-white">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div class="fw-bold text-dark fs-6">
            <i class="bi bi-clock-history me-2 text-success"></i>Riwayat Pengantaran Selesai Terbaru
        </div>
        <span class="badge bg-success-subtle text-success border border-success-subtle"><?= count($pesanan_selesai) ?> Tuntas</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th class="ps-3">No. Order</th>
                    <th>Pemesan (D4)</th>
                    <th>Alamat Pengiriman</th>
                    <th class="text-end">Total</th>
                    <th class="text-center pe-3">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pesanan_selesai)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted small">Belum ada riwayat pesanan selesai.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pesanan_selesai as $order): ?>
                        <tr>
                            <td class="ps-3 font-monospace fw-bold text-primary">#<?= (int)$order['id_pesanan'] ?></td>
                            <td class="small fw-semibold"><?= e($order['nama_pemesan'] ?? 'Tamu (Guest)') ?></td>
                            <td class="small text-muted"><?= e(mb_strimwidth((string)$order['alamat_pengiriman'], 0, 60, '...')) ?></td>
                            <td class="text-end font-monospace small fw-bold"><?= format_rupiah((float)$order['total_harga']) ?></td>
                            <td class="text-center pe-3">
                                <span class="badge bg-success-subtle text-success border border-success-subtle small">
                                    <i class="bi bi-check2-all me-1"></i> Selesai
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
