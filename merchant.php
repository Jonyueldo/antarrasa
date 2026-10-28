<?php
declare(strict_types=1);

/**
 * Dashboard Khusus Merchant / Restoran
 * Gaya Portal GoBiz / GrabMerchant Modern (#4F46E5)
 * Alur Dapur 2 Kolom: Antrean Tiket Pesanan (Kiri) & Manajemen Menu Cepat (Kanan)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions_auth.php';
require_once __DIR__ . '/includes/functions_order.php';

mulai_sesi();

// Otorisasi: Hanya merchant dan admin yang dapat mengakses
cek_akses(['merchant', 'admin']);

$pengguna = ambil_pengguna_login();
$pesan_sukses = '';
$pesan_error = '';

// 1. Tangani Aksi Dapur Terima & Masak Pesanan ('Menunggu' -> 'Diproses')
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'terima_pesanan') {
    $id_pesanan_terima = (int)($_POST['id_pesanan'] ?? 0);
    if ($id_pesanan_terima > 0) {
        $sukses = update_status_pesanan($conn, $id_pesanan_terima, 'Diproses');
        if ($sukses) {
            $pesan_sukses = "Pesanan #{$id_pesanan_terima} diterima! Dapur mulai memasak hidangan.";
        } else {
            $pesan_error = "Gagal memperbarui status pesanan menjadi 'Diproses'.";
        }
    }
}

// 2. Tangani Aksi Tambah Menu Baru ke D1 (MENU)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah_menu') {
    $nama_menu   = (string) ($_POST['nama_menu'] ?? '');
    $harga       = (float) ($_POST['harga'] ?? 0.0);
    $deskripsi   = (string) ($_POST['deskripsi'] ?? '');
    $status_menu = (string) ($_POST['status_menu'] ?? 'Tersedia');

    if (trim($nama_menu) === '' || $harga <= 0.0) {
        $pesan_error = 'Nama hidangan dan harga yang valid (> 0) wajib diisi.';
    } else {
        $id_baru = tambah_menu_makanan($conn, $nama_menu, $harga, $deskripsi, $status_menu);
        if ($id_baru !== false) {
            $pesan_sukses = "Hidangan '{$nama_menu}' berhasil ditambahkan ke katalog menu D1!";
        } else {
            $pesan_error = 'Gagal menyimpan hidangan baru ke basis data.';
        }
    }
}

// 3. Tangani Aksi Toggle Status Menu (Tersedia <-> Habis)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'ubah_status_menu') {
    $id_menu = (int) ($_POST['id_menu'] ?? 0);
    $status_tujuan = (string) ($_POST['status_tujuan'] ?? 'Tersedia');

    if ($id_menu > 0) {
        $sukses = ubah_status_menu($conn, $id_menu, $status_tujuan);
        if ($sukses) {
            $pesan_sukses = "Ketersediaan hidangan berhasil diubah menjadi '{$status_tujuan}'.";
        } else {
            $pesan_error = 'Gagal memperbarui status ketersediaan menu.';
        }
    }
}

// 4. Tangani Aksi Hapus Menu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'hapus_menu') {
    $id_menu = (int) ($_POST['id_menu'] ?? 0);
    if ($id_menu > 0) {
        $sukses = hapus_menu($conn, $id_menu);
        if ($sukses) {
            $pesan_sukses = "Menu berhasil dihapus dari katalog DataStore D1.";
        } else {
            $pesan_error = "Menu tidak dapat dihapus karena sudah memiliki riwayat transaksi pesanan (Detail Pesanan D3).";
        }
    }
}

// Ambil data menu dan pesanan dapur
$daftar_menu = ambil_semua_menu($conn);
$semua_pesanan = ambil_semua_pesanan($conn, 40);

// Pisahkan pesanan aktif dapur (Menunggu & Diproses)
$antrean_dapur = [];
$pesanan_selesai_hari_ini = 0;
$total_menu_tersedia = 0;

foreach ($daftar_menu as $m) {
    if (strcasecmp((string)$m['status_menu'], 'Tersedia') === 0) {
        $total_menu_tersedia++;
    }
}

foreach ($semua_pesanan as $p) {
    $st = (string) $p['status_pesanan'];
    if ($st === 'Menunggu' || $st === 'Diproses') {
        $antrean_dapur[] = $p;
    } elseif ($st === 'Selesai') {
        $pesanan_selesai_hari_ini++;
    }
}

$nama_merchant_aktif = (string)($pengguna['nama_lengkap'] ?? 'Mitra Restoran');
$judul_halaman = 'Portal Merchant - ' . $nama_merchant_aktif;
$tampilkan_stepper = false;

require_once __DIR__ . '/includes/header.php';
?>

<!-- 4 KPI Metrics Restoran (GoBiz / GrabMerchant Style) -->
<div class="row g-3 mb-4">
    <!-- Antrean Dapur Aktif -->
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 rounded-4 p-3 bg-white h-100 border-start border-4 border-warning">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Antrean Dapur Aktif</span>
                    <h3 class="fw-extrabold text-dark mb-0 mt-1"><?= count($antrean_dapur) ?></h3>
                </div>
                <div class="p-3 rounded-circle" style="background-color: #fef3c7; color: #d97706;">
                    <i class="bi bi-fire fs-4"></i>
                </div>
            </div>
            <div class="text-muted small mt-2">Menunggu konfirmasi &amp; dimasak</div>
        </div>
    </div>

    <!-- Menu Aktif Tersedia -->
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 rounded-4 p-3 bg-white h-100 border-start border-4 border-success">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Menu Tersedia</span>
                    <h3 class="fw-extrabold text-dark mb-0 mt-1"><?= $total_menu_tersedia ?> <small class="fs-6 fw-normal text-muted">/ <?= count($daftar_menu) ?></small></h3>
                </div>
                <div class="bg-success-subtle text-success p-3 rounded-circle">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                </div>
            </div>
            <div class="text-muted small mt-2">Dapat dipesan pelanggan saat ini</div>
        </div>
    </div>

    <!-- Pesanan Selesai -->
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 rounded-4 p-3 bg-white h-100 border-start border-4 border-primary">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Pesanan Selesai</span>
                    <h3 class="fw-extrabold text-dark mb-0 mt-1"><?= $pesanan_selesai_hari_ini ?></h3>
                </div>
                <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                    <i class="bi bi-patch-check-fill fs-4"></i>
                </div>
            </div>
            <div class="text-muted small mt-2">Pengantaran berhasil tuntas</div>
        </div>
    </div>

    <!-- Total Transaksi -->
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 rounded-4 p-3 bg-white h-100 border-start border-4 border-info">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Riwayat Order</span>
                    <h3 class="fw-extrabold text-dark mb-0 mt-1"><?= count($semua_pesanan) ?></h3>
                </div>
                <div class="bg-info-subtle text-info p-3 rounded-circle">
                    <i class="bi bi-receipt-cutoff fs-4"></i>
                </div>
            </div>
            <div class="text-muted small mt-2">Tercatat di basis data D2</div>
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

<!-- 2-COLUMN KITCHEN WORKFLOW -->
<div class="row g-4 mb-5">
    <!-- KOLOM KIRI: ANTREAN TIKET DAPUR (KITCHEN ORDER QUEUE) -->
    <div class="col-lg-6">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-extrabold text-dark mb-0 d-flex align-items-center">
                    <i class="bi bi-fire text-warning me-2 fs-4"></i> Antrean Dapur Masuk
                </h4>
                <small class="text-muted">Tiket pesanan yang harus dimasak oleh koki</small>
            </div>
            <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill shadow-sm">
                <?= count($antrean_dapur) ?> Tiket Perlu Dikerjakan
            </span>
        </div>

        <?php if (empty($antrean_dapur)): ?>
            <div class="card shadow-sm border-0 rounded-4 p-5 text-center bg-white">
                <div class="p-3 bg-light rounded-circle d-inline-flex mx-auto mb-3">
                    <i class="bi bi-check2-circle fs-1 text-success"></i>
                </div>
                <h5 class="fw-bold text-dark">Dapur Bersih! Tidak Ada Antrean Masak</h5>
                <p class="text-muted small mb-0">Semua pesanan pelanggan saat ini telah selesai atau sedang dalam perjalanan oleh kurir.</p>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($antrean_dapur as $order): ?>
                    <?php 
                        $id_o = (int) $order['id_pesanan'];
                        $st = (string) $order['status_pesanan'];
                        $items_detail = ambil_detail_pesanan($conn, $id_o);
                    ?>
                    <div class="kitchen-ticket <?= $st === 'Menunggu' ? 'waiting' : 'cooking' ?>">
                        <!-- Header Tiket -->
                        <div class="d-flex justify-content-between align-items-start pb-2 border-bottom mb-2">
                            <div>
                                <span class="badge bg-dark font-monospace me-1">TIKET #<?= $id_o ?></span>
                                <?php if ($st === 'Menunggu'): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pesanan Baru</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark"><i class="bi bi-fire me-1"></i>Sedang Dimasak</span>
                                <?php endif; ?>
                                <div class="fw-bold text-dark fs-6 mt-1">
                                    <i class="bi bi-person me-1"></i> <?= e($order['nama_pemesan'] ?? 'Tamu (Guest)') ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="text-muted small font-monospace"><?= date('H:i', strtotime((string)$order['tanggal_pesanan'])) ?> WIB</span>
                                <div class="fw-bold text-primary font-monospace mt-1"><?= format_rupiah((float)$order['total_harga']) ?></div>
                            </div>
                        </div>

                        <!-- Alamat Singkat -->
                        <div class="small text-muted mb-2">
                            <i class="bi bi-geo-alt text-danger me-1"></i> <?= e(mb_strimwidth((string)$order['alamat_pengiriman'], 0, 75, '...')) ?>
                        </div>

                        <!-- Daftar Item Hidangan (Dari DETAIL_PESANAN D3) -->
                        <div class="p-2 bg-light rounded-3 mb-3 border">
                            <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size: 0.72rem;">Hidangan yang Harus Dimasak:</div>
                            <?php foreach ($items_detail as $item): ?>
                                <div class="ticket-item-row">
                                    <span class="fw-bold text-dark"><?= (int)$item['jumlah'] ?>x <?= e($item['nama_menu']) ?></span>
                                    <span class="font-monospace text-muted small"><?= format_rupiah((float)$item['subtotal']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Tombol Aksi Dapur -->
                        <?php if ($st === 'Menunggu'): ?>
                            <form method="POST" action="merchant.php">
                                <input type="hidden" name="aksi" value="terima_pesanan">
                                <input type="hidden" name="id_pesanan" value="<?= $id_o ?>">
                                <button type="submit" class="btn btn-warning w-100 fw-bold py-2 rounded-3 text-dark shadow-sm d-flex align-items-center justify-content-center gap-2">
                                    <i class="bi bi-fire fs-5"></i>
                                    <span>Terima &amp; Masak Pesanan (Ubah ke Diproses)</span>
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-success-subtle text-success border border-success-subtle p-2">
                                    <i class="bi bi-check-circle-fill me-1"></i> Sedang Dimasak di Dapur
                                </span>
                                <a href="status.php?id=<?= $id_o ?>" class="btn btn-sm btn-outline-custom">
                                    <i class="bi bi-eye me-1"></i> Lihat Rincian
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- KOLOM KANAN: MANAJEMEN MENU HIDANGAN CEPAT -->
    <div class="col-lg-6">
        <!-- Form Tambah Menu Cepat -->
        <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white p-4">
            <h5 class="fw-bold mb-3 pb-2 border-bottom text-dark d-flex align-items-center">
                <i class="bi bi-plus-circle-fill text-primary me-2"></i>Tambah Menu Hidangan Restoran
            </h5>

            <form method="POST" action="merchant.php">
                <input type="hidden" name="aksi" value="tambah_menu">

                <div class="row g-2 mb-2">
                    <div class="col-md-7">
                        <label for="nama_menu" class="form-label small fw-semibold text-muted text-uppercase">Nama Hidangan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_menu" name="nama_menu" placeholder="Masukkan nama hidangan..." required>
                    </div>
                    <div class="col-md-5">
                        <label for="harga" class="form-label small fw-semibold text-muted text-uppercase">Harga (Rp) <span class="text-danger">*</span></label>
                        <input type="number" step="500" min="1000" class="form-control font-monospace" id="harga" name="harga" placeholder="Contoh: 25000" required>
                    </div>
                </div>

                <div class="mb-2">
                    <label for="deskripsi" class="form-label small fw-semibold text-muted text-uppercase">Deskripsi Hidangan</label>
                    <textarea class="form-control" id="deskripsi" name="deskripsi" rows="2" placeholder="Tuliskan komposisi bumbu, rasa, atau catatan hidangan (opsional)..."></textarea>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label for="status_menu" class="form-label small fw-semibold text-muted text-uppercase">Ketersediaan Awal</label>
                        <select class="form-select" id="status_menu" name="status_menu">
                            <option value="Tersedia" selected>Tersedia (Bisa dipesan)</option>
                            <option value="Habis">Habis (Stok kosong)</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold text-white shadow-sm">
                            <i class="bi bi-save2 me-1"></i> Simpan ke Katalog D1
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabel Katalog Menu Restoran & Toggle Status Cepat -->
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden bg-white">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="fw-bold text-dark fs-6">
                    <i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>Katalog Menu Aktif (<?= count($daftar_menu) ?> Hidangan)
                </div>
                <span class="badge bg-light text-dark border"><?= $total_menu_tersedia ?> Tersedia</span>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th class="ps-3" style="min-width: 180px;">Hidangan</th>
                            <th class="text-end" style="min-width: 90px;">Harga</th>
                            <th class="text-center" style="min-width: 100px;">Status</th>
                            <th class="text-center pe-3" style="min-width: 140px;">Aksi Cepat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daftar_menu)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Belum ada menu yang didaftarkan di D1.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($daftar_menu as $m): ?>
                                <?php 
                                    $id_m = (int) $m['id_menu'];
                                    $is_tersedia = (strcasecmp((string)$m['status_menu'], 'Tersedia') === 0);
                                ?>
                                <tr>
                                    <td class="ps-3 py-2">
                                        <div class="d-flex align-items-center">
                                            <img src="<?= e(dapatkan_gambar_menu((string)$m['nama_menu'])) ?>" alt="<?= e($m['nama_menu']) ?>" class="rounded-3 me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                            <div>
                                                <div class="fw-bold text-dark small"><?= e($m['nama_menu']) ?></div>
                                                <div class="text-muted small text-truncate" style="max-width: 170px; font-size: 0.72rem;"><?= e($m['deskripsi'] ?? '-') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end font-monospace small fw-semibold">
                                        <?= format_rupiah((float) $m['harga']) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($is_tersedia): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle small">
                                                Tersedia
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small">
                                                Habis
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-3">
                                        <div class="d-inline-flex gap-1">
                                            <!-- Tombol 1-Click Toggle Status -->
                                            <form method="POST" action="merchant.php" class="d-inline">
                                                <input type="hidden" name="aksi" value="ubah_status_menu">
                                                <input type="hidden" name="id_menu" value="<?= $id_m ?>">
                                                <input type="hidden" name="status_tujuan" value="<?= $is_tersedia ? 'Habis' : 'Tersedia' ?>">
                                                <button type="submit" class="btn btn-sm <?= $is_tersedia ? 'btn-outline-secondary' : 'btn-outline-success' ?> rounded-pill py-0 px-2" style="font-size: 0.75rem;">
                                                    <?= $is_tersedia ? '<i class="bi bi-toggle2-on me-1"></i>Habis' : '<i class="bi bi-toggle2-off me-1"></i>Tersedia' ?>
                                                </button>
                                            </form>

                                            <!-- Tombol Hapus Menu -->
                                            <form method="POST" action="merchant.php" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus menu \'<?= addslashes(e($m['nama_menu'])) ?>\'?');">
                                                <input type="hidden" name="aksi" value="hapus_menu">
                                                <input type="hidden" name="id_menu" value="<?= $id_m ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
