<?php
declare(strict_types=1);

/**
 * Halaman Status & Pelacakan Pesanan (Simulasi Proses 3.0: Kelola Pengantaran)
 * Menampilkan detail pesanan dari D2 & D3, status alur hidup pesanan real-time
 * dengan 4 tahap animasi (Menunggu -> Diproses -> Dikirim -> Selesai),
 * serta data kurir & restoran murni dari DataStore D4 (PENGGUNA).
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions_order.php';

mulai_sesi();

$id_pesanan = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id_pesanan']) ? (int) $_POST['id_pesanan'] : 0);
$pesan_error = '';

if ($id_pesanan <= 0) {
    header("Location: index.php");
    exit;
}

// 1. Tangani Aksi Simulasi Pengantaran / Merchant (Proses 3.0)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_status'])) {
    $status_tujuan = (string) $_POST['status_tujuan'];
    $valid_transisi = ['Menunggu', 'Diproses', 'Dikirim', 'Selesai'];

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
$status_saat_ini = (string) $pesanan['status_pesanan'];
$status_info = dapatkan_info_status($status_saat_ini);

// Ambil data akun driver & merchant langsung dari tabel PENGGUNA (DataStore D4)
$driver_aktif = ambil_driver_aktif($conn);
$merchant_aktif = ambil_merchant_aktif($conn);

// Nomor step untuk timeline
$nomor_step = match ($status_saat_ini) {
    'Menunggu' => 1,
    'Diproses' => 2,
    'Dikirim'  => 3,
    'Selesai'  => 4,
    default    => 1
};

$judul_halaman = 'Pelacakan Pesanan #' . $id_pesanan;
$tahap_aktif = 4;

require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb Navigasi -->
<div class="mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted"><i class="bi bi-house-door me-1"></i>Beranda</a></li>
            <li class="breadcrumb-item"><a href="riwayat.php" class="text-decoration-none text-muted">Pesanan Saya</a></li>
            <li class="breadcrumb-item active fw-bold text-dark" aria-current="page">Lacak #<?= $id_pesanan ?></li>
        </ol>
    </nav>
</div>

<!-- Alert Notifikasi Status Pembaruan -->
<?php if (isset($_GET['bayar_sukses'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4 d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-patch-check-fill fs-4 me-3 text-success"></i>
        <div>
            <strong>Pembayaran Berhasil Dikonfirmasi!</strong> Pesanan di DataStore D2 (<code>PESANAN</code>) telah diperbarui menjadi <strong>'Diproses'</strong>. Dapur restoran kini mulai memasak hidangan Anda.
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['update_sukses'])): ?>
    <div class="alert alert-info alert-dismissible fade show shadow-sm rounded-4 d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-arrow-repeat fs-4 me-3 text-info"></i>
        <div>
            <strong>Status Pesanan Berubah:</strong> Status transaksi #<?= $id_pesanan ?> kini menjadi <strong>'<?= e((string)$_GET['update_sukses']) ?>'</strong>.
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($pesan_error !== ''): ?>
    <div class="alert alert-danger shadow-sm rounded-4 mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($pesan_error) ?>
    </div>
<?php endif; ?>

<!-- Header Status Pelacakan -->
<div class="card shadow-sm border-0 rounded-4 mb-4 bg-white p-4">
    <div class="row align-items-center gy-3">
        <div class="col-md-7">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-light text-secondary border font-monospace">ORDER #<?= $id_pesanan ?></span>
                <span class="text-muted small">&bull; <?= date('d M Y, H:i', strtotime((string) $pesanan['tanggal_pesanan'])) ?> WIB</span>
            </div>
            <h3 class="fw-extrabold mb-1 text-dark">
                Pelacakan Pengantaran Makanan
            </h3>
            <p class="text-muted small mb-0">
                Pemesan (D4): <strong><?= e($pesanan['nama_pemesan'] ?? 'Pelanggan Tamu') ?></strong> &bull; Total: <strong class="text-danger font-monospace"><?= format_rupiah((float) $pesanan['total_harga']) ?></strong>
            </p>
        </div>
        <div class="col-md-5 text-md-end">
            <span class="badge <?= $status_info['class'] ?> fs-6 px-3 py-2 rounded-pill shadow-sm">
                <i class="bi <?= $status_info['icon'] ?> me-1"></i> <?= $status_info['label'] ?>
            </span>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- Kolom Kiri: 4-Stage Animated Tracking Timeline (GoFood Style) -->
    <div class="col-lg-7">
        <div class="tracking-stepper-wrap mb-4">
            <h5 class="fw-bold mb-4 pb-2 border-bottom text-dark d-flex align-items-center justify-content-between">
                <span><i class="bi bi-radar text-danger me-2"></i>Alur Pelacakan Real-Time (Proses 3.0)</span>
                <span class="badge bg-light text-muted small border">Tahap <?= $nomor_step ?> dari 4</span>
            </h5>

            <div class="pe-2">
                <!-- Tahap 1: Menunggu -->
                <div class="track-step-item <?= $nomor_step > 1 ? 'completed' : ($nomor_step === 1 ? 'active' : '') ?>">
                    <div class="track-step-icon">
                        <?php if ($nomor_step > 1): ?><i class="bi bi-check-lg"></i><?php else: ?><i class="bi bi-credit-card"></i><?php endif; ?>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1 <?= $nomor_step === 1 ? 'text-danger' : 'text-dark' ?>">
                            1. Menunggu Pembayaran
                        </h6>
                        <p class="text-muted small mb-0">
                            Pesanan tercatat di <code>PESANAN</code> (D2).
                            <?php if ($nomor_step === 1): ?>
                                <a href="pembayaran.php?id=<?= $id_pesanan ?>" class="btn btn-sm btn-outline-danger rounded-pill ms-2 py-0">Bayar Sekarang</a>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success ms-1 small">Lunas</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Tahap 2: Diproses (Menggunakan Nama Merchant Asli dari PENGGUNA) -->
                <div class="track-step-item <?= $nomor_step > 2 ? 'completed' : ($nomor_step === 2 ? 'active' : '') ?>">
                    <div class="track-step-icon">
                        <?php if ($nomor_step > 2): ?><i class="bi bi-check-lg"></i><?php else: ?><i class="bi bi-fire"></i><?php endif; ?>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1 <?= $nomor_step === 2 ? 'text-primary' : 'text-dark' ?>">
                            2. Dapur Restoran Memasak Pesanan
                        </h6>
                        <p class="text-muted small mb-0">
                            <?php if ($merchant_aktif !== null): ?>
                                Pembayaran diterima. Restoran <strong><?= e($merchant_aktif['nama_lengkap']) ?></strong> (@<?= e($merchant_aktif['username']) ?>) sedang meracik bumbu dan memasak hidangan pesanan Anda.
                            <?php else: ?>
                                Pembayaran diterima. Dapur restoran sedang meracik bumbu dan memasak hidangan pesanan Anda.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Tahap 3: Dikirim (Menggunakan Nama Driver Asli dari PENGGUNA) -->
                <div class="track-step-item <?= $nomor_step > 3 ? 'completed' : ($nomor_step === 3 ? 'active' : '') ?>">
                    <div class="track-step-icon">
                        <?php if ($nomor_step > 3): ?><i class="bi bi-check-lg"></i><?php else: ?><i class="bi bi-bicycle"></i><?php endif; ?>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1 <?= $nomor_step === 3 ? 'text-primary' : 'text-dark' ?>">
                            3. Kurir Sedang Mengantar ke Alamat Anda
                        </h6>
                        <p class="text-muted small mb-0">
                            <?php if ($driver_aktif !== null): ?>
                                Pesanan telah diambil oleh kurir <strong><?= e($driver_aktif['nama_lengkap']) ?></strong> (@<?= e($driver_aktif['username']) ?>) dan sedang dalam perjalanan menuju alamat tujuan Anda.
                            <?php else: ?>
                                Pesanan sedang dalam proses pengantaran oleh kurir menuju alamat tujuan Anda.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Tahap 4: Selesai -->
                <div class="track-step-item <?= $nomor_step === 4 ? 'completed active' : '' ?>">
                    <div class="track-step-icon">
                        <i class="bi bi-check2-all"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1 <?= $nomor_step === 4 ? 'text-success' : 'text-dark' ?>">
                            4. Makanan Tiba &amp; Pesanan Selesai
                        </h6>
                        <p class="text-muted small mb-0">
                            Makanan telah berhasil diserahkan kepada pelanggan dalam keadaan segar dan nikmat.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Simulasi Aktor Pengujian (Merchant & Driver) -->
        <div class="card shadow-sm border-0 rounded-4 p-4 bg-white mb-4">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-sliders text-warning fs-5 me-2"></i>
                <h6 class="fw-bold mb-0 text-dark">Simulasi Status Pengujian (Penguji / Dosen / Evaluator)</h6>
            </div>
            <p class="text-muted small mb-3">
                Ubah alur status pesanan #<?= $id_pesanan ?> secara langsung tanpa perlu berpindah akun:
            </p>

            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="status.php" class="d-inline">
                    <input type="hidden" name="aksi_status" value="1">
                    <input type="hidden" name="id_pesanan" value="<?= $id_pesanan ?>">
                    <input type="hidden" name="status_tujuan" value="Menunggu">
                    <button type="submit" class="btn btn-sm <?= $status_saat_ini === 'Menunggu' ? 'btn-warning fw-bold' : 'btn-outline-secondary' ?>">
                        <i class="bi bi-hourglass me-1"></i> 1. Menunggu
                    </button>
                </form>

                <form method="POST" action="status.php" class="d-inline">
                    <input type="hidden" name="aksi_status" value="1">
                    <input type="hidden" name="id_pesanan" value="<?= $id_pesanan ?>">
                    <input type="hidden" name="status_tujuan" value="Diproses">
                    <button type="submit" class="btn btn-sm <?= $status_saat_ini === 'Diproses' ? 'btn-info fw-bold text-dark' : 'btn-outline-info text-dark' ?>">
                        <i class="bi bi-fire me-1"></i> 2. Diproses Resto
                    </button>
                </form>

                <form method="POST" action="status.php" class="d-inline">
                    <input type="hidden" name="aksi_status" value="1">
                    <input type="hidden" name="id_pesanan" value="<?= $id_pesanan ?>">
                    <input type="hidden" name="status_tujuan" value="Dikirim">
                    <button type="submit" class="btn btn-sm <?= $status_saat_ini === 'Dikirim' ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                        <i class="bi bi-bicycle me-1"></i> 3. Dikirim Driver
                    </button>
                </form>

                <form method="POST" action="status.php" class="d-inline">
                    <input type="hidden" name="aksi_status" value="1">
                    <input type="hidden" name="id_pesanan" value="<?= $id_pesanan ?>">
                    <input type="hidden" name="status_tujuan" value="Selesai">
                    <button type="submit" class="btn btn-sm <?= $status_saat_ini === 'Selesai' ? 'btn-success fw-bold' : 'btn-outline-success' ?>">
                        <i class="bi bi-check2-all me-1"></i> 4. Selesai
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Info Driver, Resto, dan Ringkasan Menu -->
    <div class="col-lg-5">
        <!-- Kartu Kurir Pengantar (Murni Membaca Data Asli dari DataStore D4: PENGGUNA) -->
        <div class="card shadow-sm border-0 rounded-4 p-4 bg-white mb-4">
            <h6 class="fw-bold text-muted small text-uppercase mb-3">Mitra Kurir AntarRasa (DataStore D4)</h6>
            <?php if ($driver_aktif !== null): ?>
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-success text-white p-3 rounded-circle shadow-sm">
                        <i class="bi bi-bicycle fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-dark fs-6"><?= e($driver_aktif['nama_lengkap']) ?></div>
                        <div class="text-muted small">Username: <strong>@<?= e($driver_aktif['username']) ?></strong> &bull; ID Kurir #<?= (int)$driver_aktif['id_pengguna'] ?></div>
                        <div class="mt-2">
                            <?php if ($status_saat_ini === 'Menunggu'): ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill small">
                                    <i class="bi bi-hourglass me-1"></i> Menunggu Pembayaran
                                </span>
                            <?php elseif ($status_saat_ini === 'Diproses'): ?>
                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill small">
                                    <i class="bi bi-box-seam me-1"></i> Menunggu Pengambilan di Dapur
                                </span>
                            <?php elseif ($status_saat_ini === 'Dikirim'): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill small">
                                    <i class="bi bi-bicycle me-1"></i> Sedang Mengantar ke Alamat Tujuan
                                </span>
                            <?php elseif ($status_saat_ini === 'Selesai'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small">
                                    <i class="bi bi-check2-all me-1"></i> Pengantaran Selesai
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="d-flex align-items-center gap-3 text-muted">
                    <div class="bg-light text-secondary p-3 rounded-circle border">
                        <i class="bi bi-person-x fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark">Belum ada kurir terdaftar di sistem</div>
                        <small class="text-muted">Tidak ada pengguna dengan peran 'driver' pada tabel PENGGUNA.</small>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Kartu Rincian Alamat & Hidangan -->
        <div class="card shadow-sm border-0 rounded-4 p-4 bg-white">
            <h6 class="fw-bold text-muted small text-uppercase mb-3">Tujuan Pengiriman &amp; Hidangan</h6>
            
            <div class="driver-location-box mb-3">
                <div class="small fw-bold text-danger mb-1"><i class="bi bi-geo-alt-fill me-1"></i>Alamat Pengantaran</div>
                <div class="small text-dark"><?= nl2br(e($pesanan['alamat_pengiriman'])) ?></div>
            </div>

            <div class="border rounded-3 p-3 bg-light mb-3">
                <div class="small fw-bold text-muted text-uppercase mb-2">Item Hidangan (<?= count($detail_items) ?>)</div>
                <ul class="list-unstyled mb-0 small">
                    <?php foreach ($detail_items as $item): ?>
                        <li class="d-flex justify-content-between py-1 border-bottom border-light-subtle">
                            <span><?= e($item['nama_menu']) ?> &times; <?= (int)$item['jumlah'] ?></span>
                            <span class="font-monospace fw-semibold"><?= format_rupiah((float)$item['subtotal']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="d-flex justify-content-between pt-2 fw-bold text-dark fs-6">
                    <span>Total Pembayaran:</span>
                    <span class="text-danger font-monospace"><?= format_rupiah((float)$pesanan['total_harga']) ?></span>
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="riwayat.php" class="btn btn-outline-custom flex-grow-1 rounded-pill small">
                    <i class="bi bi-clock-history me-1"></i> Riwayat Pesanan
                </a>
                <a href="index.php" class="btn btn-primary-custom flex-grow-1 rounded-pill small">
                    <i class="bi bi-bag-plus me-1"></i> Pesan Lagi
                </a>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
