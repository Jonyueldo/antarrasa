<?php
declare(strict_types=1);

/**
 * Halaman Status & Pelacakan Pesanan (Simulasi Proses 3.0: Kelola Pengantaran)
 * Menampilkan detail pesanan dari D2 & D3, status pembayaran,
 * serta tombol simulasi bagi Merchant/Driver untuk memajukan status ke 'Dikirim' dan 'Selesai'.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions_order.php';

mulai_sesi();

$id_pesanan = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id_pesanan']) ? (int) $_POST['id_pesanan'] : 0);
$pesan_aksi = '';
$pesan_error = '';

if ($id_pesanan <= 0) {
    header("Location: index.php");
    exit;
}

// 1. Tangani Aksi Simulasi Pengantaran / Merchant (Proses 3.0)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_status'])) {
    $status_tujuan = (string) $_POST['status_tujuan'];
    $valid_transisi = ['Diproses', 'Dikirim', 'Selesai'];

    if (in_array($status_tujuan, $valid_transisi, true)) {
        $sukses = update_status_pesanan($conn, $id_pesanan, $status_tujuan);
        if ($sukses) {
            header("Location: status.php?id=" . $id_pesanan . "&update_sukses=" . urlencode($status_tujuan));
            exit;
        } else {
            $pesan_error = "Gagal memperbarui status pengiriman di database.";
        }
    }
}

// 2. Ambil Informasi Pesanan dari D2 (PESANAN) dan D3 (DETAIL_PESANAN)
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
$status_saat_ini = (string) $pesanan['status_pesanan'];
$status_info = dapatkan_info_status($status_saat_ini);

// Hitung nomor tahap untuk timeline
$nomor_step = match ($status_saat_ini) {
    'Menunggu' => 1,
    'Diproses' => 2,
    'Dikirim'  => 3,
    'Selesai'  => 4,
    default    => 1
};

$judul_halaman = 'Status Pesanan #' . $id_pesanan;
$tahap_aktif = 4;
$tampilkan_stepper = true;

require_once __DIR__ . '/includes/header.php';
?>

<!-- Alert Notifikasi Status Pembaruan -->
<?php if (isset($_GET['bayar_sukses'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm d-flex align-items-center" role="alert">
        <i class="bi bi-patch-check-fill fs-4 me-3 text-success"></i>
        <div>
            <strong>Pembayaran Berhasil Dikonfirmasi!</strong> Status pesanan di DataStore D2 (<code>PESANAN</code>) telah diperbarui menjadi <strong>'Diproses'</strong>. Dapur restoran sedang menyiapkan hidangan Anda.
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['update_sukses'])): ?>
    <div class="alert alert-info alert-dismissible fade show shadow-sm d-flex align-items-center" role="alert">
        <i class="bi bi-arrow-repeat fs-4 me-3 text-info"></i>
        <div>
            <strong>Simulasi Berhasil:</strong> Status pesanan #<?= $id_pesanan ?> telah diubah menjadi <strong>'<?= e((string)$_GET['update_sukses']) ?>'</strong>.
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($pesan_error !== ''): ?>
    <div class="alert alert-danger shadow-sm">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($pesan_error) ?>
    </div>
<?php endif; ?>

<!-- Header Status -->
<div class="card shadow-sm border-0 rounded-4 mb-4">
    <div class="card-body p-4">
        <div class="row align-items-center gy-3">
            <div class="col-md-7">
                <span class="badge bg-light text-muted border mb-2 font-monospace">PESANAN #<?= $id_pesanan ?></span>
                <h3 class="fw-bold mb-1">
                    Pelacakan Pengantaran Makanan
                </h3>
                <p class="text-muted small mb-0">
                    Waktu Pemesanan: <strong><?= date('d M Y, H:i', strtotime((string) $pesanan['tanggal_pesanan'])) ?> WIB</strong>
                </p>
            </div>
            <div class="col-md-5 text-md-end">
                <span class="badge <?= $status_info['class'] ?> fs-5 px-3 py-2 shadow-sm">
                    <i class="bi <?= $status_info['icon'] ?> me-1"></i> <?= $status_info['label'] ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Visualisasi Garis Waktu (Timeline) Pengantaran -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 rounded-4 p-4 mb-4">
            <h5 class="fw-bold mb-4 pb-2 border-bottom text-dark">
                <i class="bi bi-activity text-primary me-2"></i>Status Alur Pengantaran (Proses 3.0)
            </h5>

            <!-- Timeline Status Vertikal -->
            <div class="tracking-timeline ps-2">
                <!-- Tahap 1: Menunggu -->
                <div class="timeline-item <?= $nomor_step > 1 ? 'passed' : ($nomor_step === 1 ? 'active' : '') ?>">
                    <div class="timeline-dot">
                        <?php if ($nomor_step > 1): ?><i class="bi bi-check-lg"></i><?php else: ?><i class="bi bi-cash-stack"></i><?php endif; ?>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 <?= $nomor_step === 1 ? 'text-primary' : '' ?>">Pesanan Dibuat & Menunggu Pembayaran</h6>
                        <p class="text-muted small mb-0">
                            Header pesanan tercatat di tabel <code>PESANAN</code> (D2).
                            <?php if ($nomor_step === 1): ?>
                                <a href="pembayaran.php?id=<?= $id_pesanan ?>" class="btn btn-sm btn-outline-warning ms-2 py-0">Bayar Sekarang</a>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Tahap 2: Diproses -->
                <div class="timeline-item <?= $nomor_step > 2 ? 'passed' : ($nomor_step === 2 ? 'active' : '') ?>">
                    <div class="timeline-dot">
                        <?php if ($nomor_step > 2): ?><i class="bi bi-check-lg"></i><?php else: ?><i class="bi bi-fire"></i><?php endif; ?>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 <?= $nomor_step === 2 ? 'text-primary' : '' ?>">Pesanan Sedang Diproses di Restoran</h6>
                        <p class="text-muted small mb-0">Pembayaran terverifikasi. Koki sedang memasak hidangan lezat pesanan Anda.</p>
                    </div>
                </div>

                <!-- Tahap 3: Dikirim -->
                <div class="timeline-item <?= $nomor_step > 3 ? 'passed' : ($nomor_step === 3 ? 'active' : '') ?>">
                    <div class="timeline-dot">
                        <?php if ($nomor_step > 3): ?><i class="bi bi-check-lg"></i><?php else: ?><i class="bi bi-bicycle"></i><?php endif; ?>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 <?= $nomor_step === 3 ? 'text-primary' : '' ?>">Pesanan Sedang Dikirim oleh Kurir</h6>
                        <p class="text-muted small mb-0">Makanan telah diambil driver dan sedang dalam perjalanan menuju alamat tujuan.</p>
                    </div>
                </div>

                <!-- Tahap 4: Selesai -->
                <div class="timeline-item <?= $nomor_step === 4 ? 'passed' : '' ?>">
                    <div class="timeline-dot">
                        <i class="bi bi-check2-all"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 <?= $nomor_step === 4 ? 'text-success' : '' ?>">Pesanan Tiba &amp; Selesai</h6>
                        <p class="text-muted small mb-0">Pesanan telah diterima oleh pelanggan dengan baik. Selamat menikmati santapan!</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Simulasi Aktor Merchant / Driver (SRS P-Spec 3.0) -->
        <div class="simulation-panel shadow-sm mb-4">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-sliders2-vertical text-warning fs-5 me-2"></i>
                <h6 class="fw-bold mb-0 text-dark">Simulasi Aktor Pengujian (Merchant &amp; Driver)</h6>
            </div>
            <p class="text-muted small mb-3">
                Dalam lingkungan operasional nyata, pembaruan status dilakukan melalui aplikasi Merchant atau aplikasi Driver. 
                Gunakan tombol di bawah untuk menyimulasikan pergantian status pesanan secara langsung:
            </p>

            <div class="d-flex flex-wrap gap-2">
                <?php if ($status_saat_ini === 'Menunggu'): ?>
                    <a href="pembayaran.php?id=<?= $id_pesanan ?>" class="btn btn-warning btn-sm fw-bold">
                        <i class="bi bi-wallet2 me-1"></i> Ke Halaman Pembayaran (Proses 2.0)
                    </a>
                <?php elseif ($status_saat_ini === 'Diproses'): ?>
                    <form method="POST" action="status.php">
                        <input type="hidden" name="aksi_status" value="update">
                        <input type="hidden" name="id_pesanan" value="<?= $id_pesanan ?>">
                        <input type="hidden" name="status_tujuan" value="Dikirim">
                        <button type="submit" class="btn btn-primary btn-sm fw-bold shadow-sm">
                            <i class="bi bi-bicycle me-1"></i> Simulasi: Driver Berangkat (Status &rarr; 'Dikirim')
                        </button>
                    </form>
                <?php elseif ($status_saat_ini === 'Dikirim'): ?>
                    <form method="POST" action="status.php">
                        <input type="hidden" name="aksi_status" value="update">
                        <input type="hidden" name="id_pesanan" value="<?= $id_pesanan ?>">
                        <input type="hidden" name="status_tujuan" value="Selesai">
                        <button type="submit" class="btn btn-success btn-sm fw-bold shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Simulasi: Pesanan Tiba (Status &rarr; 'Selesai')
                        </button>
                    </form>
                <?php elseif ($status_saat_ini === 'Selesai'): ?>
                    <span class="badge bg-success-subtle text-success border border-success p-2">
                        <i class="bi bi-check-circle-fill me-1"></i> Siklus transaksi pemesanan telah lengkap (100% Selesai)
                    </span>
                    <a href="index.php" class="btn btn-outline-primary btn-sm ms-2">
                        <i class="bi bi-plus-circle me-1"></i> Buat Pesanan Baru
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Rincian Lengkap Pesanan (D2 & D3) -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-info-circle text-primary me-2"></i>Rincian Transaksi
                </h5>
            </div>
            
            <div class="card-body">
                <!-- Alamat Tujuan -->
                <div class="mb-3">
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Alamat Pengiriman (D2):</span>
                    <div class="p-2 bg-light rounded border small">
                        <?= nl2br(e($pesanan['alamat_pengiriman'])) ?>
                    </div>
                </div>

                <!-- Daftar Hidangan D3 -->
                <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Rincian Menu (D3):</span>
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Menu</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detail_items as $item): ?>
                                <tr>
                                    <td class="small fw-semibold"><?= e($item['nama_menu']) ?></td>
                                    <td class="small text-center"><?= (int) $item['jumlah'] ?>x</td>
                                    <td class="small text-end font-monospace"><?= format_rupiah((float) $item['subtotal']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-top">
                            <tr>
                                <th colspan="2" class="text-end py-2">Total Harga:</th>
                                <th class="text-end py-2 text-primary fs-6 font-monospace">
                                    <?= format_rupiah((float) $pesanan['total_harga']) ?>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="d-grid gap-2">
                    <a href="riwayat.php" class="btn btn-outline-custom">
                        <i class="bi bi-clock-history me-1"></i> Lihat Semua Riwayat Pesanan
                    </a>
                    <a href="index.php" class="btn btn-primary-custom">
                        <i class="bi bi-grid me-1"></i> Pesan Menu Lain
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
