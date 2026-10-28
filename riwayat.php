<?php
declare(strict_types=1);

/**
 * Halaman Riwayat Transaksi Pesanan "AntarRasa"
 * Membaca data dari DataStore D2 (PESANAN) JOIN D4 (PENGGUNA)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions_auth.php';
require_once __DIR__ . '/includes/functions_order.php';

mulai_sesi();

$pengguna = ambil_pengguna_login();
$peran = $pengguna['peran'] ?? null;
$id_user = $pengguna['id_pengguna'] ?? null;

// Jika customer yang login, prioritaskan menampilkan pesanannya sendiri
$filter_id_pengguna = ($peran === 'customer') ? $id_user : null;
$daftar_pesanan = ambil_semua_pesanan($conn, 50, null, $filter_id_pengguna);

$judul_halaman = ($peran === 'customer') ? 'Pesanan Saya' : 'Riwayat Transaksi Pesanan';
$tampilkan_stepper = false;

require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb Navigasi -->
<div class="mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted"><i class="bi bi-house-door me-1"></i>Beranda</a></li>
            <li class="breadcrumb-item active fw-bold text-dark" aria-current="page"><?= ($peran === 'customer') ? 'Pesanan Saya' : 'Riwayat Transaksi' ?></li>
        </ol>
    </nav>
</div>

<!-- Header Judul -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h3 class="fw-extrabold mb-1 text-dark">
            <i class="bi bi-clock-history text-danger me-2"></i>
            <?= ($peran === 'customer') ? 'Daftar Riwayat Pesanan Saya' : 'Semua Riwayat Transaksi Pesanan' ?>
        </h3>
        <p class="text-muted small mb-0">
            <?= ($peran === 'customer') 
                ? 'Pantau perkembangan dan status pesanan makanan yang pernah Anda buat.' 
                : 'Daftar seluruh transaksi yang tercatat pada DataStore D2 (PESANAN) dan terhubung ke D4 (PENGGUNA).' ?>
        </p>
    </div>
    <a href="index.php" class="btn btn-primary-custom rounded-pill mt-2 mt-sm-0">
        <i class="bi bi-plus-lg me-1"></i> Buat Pesanan Baru
    </a>
</div>

<?php if (empty($daftar_pesanan)): ?>
    <div class="card shadow-sm border-0 rounded-4 text-center py-5 my-4 bg-white">
        <div class="card-body py-5">
            <div class="p-3 bg-light text-muted rounded-circle d-inline-flex mb-3">
                <i class="bi bi-journal-x fs-1"></i>
            </div>
            <h4 class="fw-extrabold text-dark">Belum Ada Transaksi Pemesanan</h4>
            <p class="text-muted mx-auto mb-4" style="max-width: 480px;">
                <?= ($peran === 'customer') 
                    ? 'Anda belum pernah membuat pesanan di akun ini. Buka katalog menu dan nikmati aneka hidangan lezat!' 
                    : 'Belum ada transaksi pemesanan yang tercatat di sistem.' ?>
            </p>
            <a href="index.php" class="btn btn-primary-custom px-4 py-2 rounded-pill shadow-sm">
                <i class="bi bi-grid-fill me-2"></i> Buka Katalog Menu
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5 bg-white">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div class="fw-bold text-dark fs-6">
                <i class="bi bi-receipt text-danger me-2"></i>Daftar Transaksi (<?= count($daftar_pesanan) ?> Pesanan)
            </div>
            <span class="badge bg-light text-secondary border font-monospace">DataStore D2 &amp; D4</span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th class="ps-3" style="width: 100px;">ID Order</th>
                        <th style="min-width: 150px;">Waktu Transaksi</th>
                        <?php if ($peran !== 'customer'): ?>
                            <th style="min-width: 150px;">Pemesan (D4)</th>
                        <?php endif; ?>
                        <th style="min-width: 250px;">Alamat Pengantaran</th>
                        <th class="text-end" style="min-width: 130px;">Total Tagihan</th>
                        <th class="text-center" style="min-width: 150px;">Status Alur</th>
                        <th class="text-center pe-3" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar_pesanan as $order): ?>
                        <?php 
                            $id = (int) $order['id_pesanan'];
                            $status = (string) $order['status_pesanan'];
                            $info_st = dapatkan_info_status($status);
                        ?>
                        <tr>
                            <td class="ps-3 fw-bold font-monospace text-primary">#<?= $id ?></td>
                            <td class="small text-muted">
                                <i class="bi bi-calendar3 me-1"></i><?= date('d M Y, H:i', strtotime((string) $order['tanggal_pesanan'])) ?> WIB
                            </td>
                            <?php if ($peran !== 'customer'): ?>
                                <td>
                                    <div class="fw-bold text-dark small">
                                        <?= e($order['nama_pemesan'] ?? 'Tamu (Guest)') ?>
                                    </div>
                                    <?php if (!empty($order['username_pemesan'])): ?>
                                        <div class="text-muted" style="font-size: 0.72rem;">@<?= e($order['username_pemesan']) ?></div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="small text-secondary">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e(mb_strimwidth((string)$order['alamat_pengiriman'], 0, 75, '...')) ?>
                            </td>
                            <td class="text-end fw-extrabold font-monospace text-danger">
                                <?= format_rupiah((float) $order['total_harga']) ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $info_st['class'] ?> px-2 py-1 rounded-pill small">
                                    <i class="bi <?= $info_st['icon'] ?> me-1"></i> <?= e($status) ?>
                                </span>
                            </td>
                            <td class="text-center pe-3">
                                <?php if ($status === 'Menunggu'): ?>
                                    <a href="pembayaran.php?id=<?= $id ?>" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold text-dark shadow-sm">
                                        <i class="bi bi-wallet2 me-1"></i> Bayar
                                    </a>
                                <?php else: ?>
                                    <a href="status.php?id=<?= $id ?>" class="btn btn-sm btn-outline-custom rounded-pill px-3">
                                        <i class="bi bi-compass me-1"></i> Lacak
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
