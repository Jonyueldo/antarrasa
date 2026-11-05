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
wajib_csrf();

// Otorisasi: Hanya driver dan admin yang dapat mengakses
cek_akses(['driver', 'admin']);

$pengguna = ambil_pengguna_login();

// Notifikasi hasil aksi dari redirect PRG (dicetak dengan e() di bawah)
$pesan_sukses = isset($_GET['pesan_sukses']) ? (string) $_GET['pesan_sukses'] : '';
$pesan_error = isset($_GET['pesan_error']) ? (string) $_GET['pesan_error'] : '';

// Tangani Aksi Driver Mengubah Status Pesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_driver'])) {
    $id_pesanan   = (int) ($_POST['id_pesanan'] ?? 0);
    $status_tujuan = (string) ($_POST['status_tujuan'] ?? '');
    $transisi_valid = ['Dikirim', 'Selesai'];

    if ($id_pesanan > 0 && in_array($status_tujuan, $transisi_valid, true)) {
        // Catat driver yang benar-benar mengambil pesanan (admin tidak dicatat sebagai kurir)
        $id_driver_login = (($pengguna['peran'] ?? '') === 'driver') ? (int) $pengguna['id_pengguna'] : null;
        $sukses = update_status_pesanan($conn, $id_pesanan, $status_tujuan, $id_driver_login);
        if ($sukses) {
            $pesan_sukses = $status_tujuan === 'Dikirim'
                ? "Pesanan #{$id_pesanan} berhasil Anda klaim. Silakan antar ke alamat pelanggan."
                : "Pesanan #{$id_pesanan} selesai diantar. Terima kasih!";
        } else {
            $pesan_error = $status_tujuan === 'Dikirim'
                ? "Pesanan #{$id_pesanan} tidak dapat diambil: sudah diklaim driver lain atau statusnya bukan lagi 'Diproses'."
                : "Pesanan #{$id_pesanan} tidak dapat diselesaikan: hanya driver yang mengambil pesanan ini yang dapat menyelesaikannya.";
        }
    }
}

// Pola PRG (Post/Redirect/Get): setiap POST selalu diakhiri redirect agar
// refresh browser tidak mengirim ulang update status. Setelah aksi, buka tab "Tugas Saya".
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tujuan = 'driver.php?tab=saya';
    if ($pesan_error !== '') {
        $tujuan .= '&pesan_error=' . urlencode($pesan_error);
    } elseif ($pesan_sukses !== '') {
        $tujuan .= '&pesan_sukses=' . urlencode($pesan_sukses);
    }
    header("Location: {$tujuan}");
    exit;
}

// Driver hanya melihat miliknya sendiri; admin (null) melihat semua driver
$id_driver_statistik = (($pengguna['peran'] ?? '') === 'driver') ? (int) $pengguna['id_pengguna'] : null;

// Klaim tugas eksklusif: "Tersedia" = belum diklaim siapa pun, "Tugas Saya" = diklaim driver ini
$pesanan_siap_antar  = ambil_tugas_driver($conn, 'tersedia', $id_driver_statistik, 25);
$pesanan_dalam_antar = ambil_tugas_driver($conn, 'saya', $id_driver_statistik, 25);
$pesanan_selesai     = ambil_tugas_driver($conn, 'selesai', $id_driver_statistik, 15);

// Tab aktif (tanpa JavaScript): default "Tugas Saya" bila sedang ada antaran, selain itu "Tersedia"
$tab_aktif = (string) ($_GET['tab'] ?? '');
if (!in_array($tab_aktif, ['saya', 'tersedia'], true)) {
    $tab_aktif = empty($pesanan_dalam_antar) ? 'tersedia' : 'saya';
}

// Rincian item kedua daftar diambil dalam SATU query (bukan per pesanan di dalam loop)
$detail_tugas = ambil_detail_pesanan_massal(
    $conn,
    array_merge(array_column($pesanan_dalam_antar, 'id_pesanan'), array_column($pesanan_siap_antar, 'id_pesanan'))
);

// "Tuntas Hari Ini": driver melihat hitungan miliknya sendiri, admin melihat semua driver
$jumlah_selesai_hari_ini = hitung_selesai_hari_ini($conn, $id_driver_statistik);

// Rekap pengantaran per id_driver: driver melihat miliknya, admin melihat semua driver
$rekap_driver = ambil_rekap_driver($conn, $id_driver_statistik);

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
                    <h3 class="fw-extrabold text-success mb-0 mt-1"><?= $jumlah_selesai_hari_ini ?></h3>
                </div>
                <div class="bg-success-subtle text-success p-3 rounded-circle">
                    <i class="bi bi-check2-circle fs-4"></i>
                </div>
            </div>
            <div class="text-muted small mt-2">Pengantaran berhasil diselesaikan</div>
        </div>
    </div>
</div>

<!-- Rekap Pengantaran per Driver (agregasi berdasarkan pesanan.id_driver) -->
<div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div class="fw-bold text-dark fs-6">
            <i class="bi bi-graph-up-arrow text-success me-2" aria-hidden="true"></i><?= $id_driver_statistik !== null ? 'Rekap Pengantaran Saya' : 'Rekap Pengantaran Seluruh Driver' ?>
        </div>
        <span class="badge bg-success-subtle text-success border border-success-subtle">Total sepanjang waktu</span>
    </div>
    <?php if (empty($rekap_driver)): ?>
        <div class="p-4 text-muted small">Belum ada akun driver terdaftar.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table tabel-kartu align-middle mb-0">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th class="ps-3">Driver</th>
                        <th class="text-center">Selesai Diantar</th>
                        <th class="text-center">Sedang Diantar</th>
                        <th class="text-end pe-3">Nilai Pesanan Diantar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rekap_driver as $rd): ?>
                        <tr>
                            <td data-label="Driver" class="ps-3">
                                <div class="fw-semibold text-dark small"><?= e($rd['nama_driver']) ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;">@<?= e($rd['username']) ?></div>
                            </td>
                            <td data-label="Selesai Diantar" class="text-center fw-bold"><?= (int) $rd['total_selesai'] ?></td>
                            <td data-label="Sedang Diantar" class="text-center"><?= (int) $rd['sedang_diantar'] ?></td>
                            <td data-label="Nilai Pesanan Diantar" class="text-end pe-3 font-monospace fw-bold"><?= format_rupiah((float) $rd['total_nilai']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Alert Notifikasi -->
<?php if ($pesan_sukses !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center shadow-sm rounded-4 mb-4" role="alert">
        <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
        <div><?= e($pesan_sukses) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
<?php endif; ?>

<?php if ($pesan_error !== ''): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center shadow-sm rounded-4 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-danger"></i>
        <div><?= e($pesan_error) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
<?php endif; ?>

<!-- Tab Tugas: "Tugas Saya" (diklaim driver ini) vs "Tersedia" (belum diklaim siapa pun) -->
<ul class="nav nav-pills mb-4 gap-2">
    <li class="nav-item">
        <a class="nav-link <?= $tab_aktif === 'saya' ? 'active' : 'border' ?>" href="driver.php?tab=saya" <?= $tab_aktif === 'saya' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-person-badge me-1" aria-hidden="true"></i> Tugas Saya
            <span class="badge <?= $tab_aktif === 'saya' ? 'bg-light text-dark' : 'bg-primary' ?> ms-1"><?= count($pesanan_dalam_antar) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab_aktif === 'tersedia' ? 'active' : 'border' ?>" href="driver.php?tab=tersedia" <?= $tab_aktif === 'tersedia' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-box-seam me-1" aria-hidden="true"></i> Tersedia
            <span class="badge <?= $tab_aktif === 'tersedia' ? 'bg-light text-dark' : 'bg-warning text-dark' ?> ms-1"><?= count($pesanan_siap_antar) ?></span>
        </a>
    </li>
</ul>

<?php if ($tab_aktif === 'saya'): ?>
<!-- =====================================================================
     BAGIAN 1 (TAB "TUGAS SAYA"): PESANAN YANG SEDANG ANDA ANTAR (STATUS: 'Dikirim')
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
                    $rincian_item = $detail_tugas[$id_o] ?? [];
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
                            <?= field_csrf() ?>
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
<?php else: ?>

<!-- =====================================================================
     BAGIAN 2 (TAB "TERSEDIA"): TUGAS SIAP DIJEMPUT, BELUM DIKLAIM DRIVER LAIN
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
                    $rincian_item = $detail_tugas[$id_o] ?? [];
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
                            <?= field_csrf() ?>
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
<?php endif; ?>

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
        <table class="table tabel-kartu table-hover align-middle mb-0">
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
                            <td data-label="No. Order" class="ps-3 font-monospace fw-bold text-primary">#<?= (int)$order['id_pesanan'] ?></td>
                            <td data-label="Pemesan (D4)" class="small fw-semibold"><?= e($order['nama_pemesan'] ?? 'Tamu (Guest)') ?></td>
                            <td data-label="Alamat Pengiriman" class="small text-muted"><?= e(mb_strimwidth((string)$order['alamat_pengiriman'], 0, 60, '...')) ?></td>
                            <td data-label="Total" class="text-end font-monospace small fw-bold"><?= format_rupiah((float)$order['total_harga']) ?></td>
                            <td data-label="Status" class="text-center pe-3">
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
