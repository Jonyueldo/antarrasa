<?php
declare(strict_types=1);

/**
 * Halaman Riwayat Transaksi Pesanan "AntarRasa"
 * Membaca data dari DataStore D2 (PESANAN) untuk pemantauan alur pemesanan
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions_order.php';

mulai_sesi();

$daftar_pesanan = ambil_semua_pesanan($conn, 50);

$judul_halaman = 'Riwayat Transaksi Pesanan';
$tampilkan_stepper = false;

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h3 class="fw-bold mb-1">
            <i class="bi bi-clock-history text-primary me-2"></i>Riwayat Pesanan Pelanggan
        </h3>
        <p class="text-muted small mb-0">Daftar rekaman transaksi tersimpan pada DataStore D2 (<code>PESANAN</code>).</p>
    </div>
    <a href="index.php" class="btn btn-primary-custom mt-2 mt-sm-0">
        <i class="bi bi-plus-lg me-1"></i> Buat Pesanan Baru
    </a>
</div>

<?php if (empty($daftar_pesanan)): ?>
    <div class="card shadow-sm border-0 text-center py-5 my-4">
        <div class="card-body">
            <i class="bi bi-journal-x text-muted display-3 mb-3"></i>
            <h4 class="fw-bold text-secondary">Belum Ada Transaksi Pemesanan</h4>
            <p class="text-muted">Silakan pilih makanan dari katalog untuk membuat pesanan pertama Anda.</p>
            <a href="index.php" class="btn btn-primary-custom mt-2">Buka Katalog Menu</a>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th style="width: 100px;">ID Pesanan</th>
                        <th style="min-width: 160px;">Waktu Transaksi</th>
                        <th style="min-width: 250px;">Alamat Pengiriman (D2)</th>
                        <th class="text-end" style="min-width: 130px;">Total Tagihan</th>
                        <th class="text-center" style="min-width: 150px;">Status Pesanan</th>
                        <th class="text-center" style="width: 160px;">Aksi Tindak Lanjut</th>
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
                            <td class="fw-bold font-monospace text-primary">#<?= $id ?></td>
                            <td class="small text-muted">
                                <?= date('d M Y, H:i', strtotime((string) $order['tanggal_pesanan'])) ?> WIB
                            </td>
                            <td class="small text-dark">
                                <?= e(mb_strimwidth((string)$order['alamat_pengiriman'], 0, 75, '...')) ?>
                            </td>
                            <td class="text-end fw-bold font-monospace text-dark">
                                <?= format_rupiah((float) $order['total_harga']) ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $info_st['class'] ?> px-2 py-1">
                                    <i class="bi <?= $info_st['icon'] ?> me-1"></i> <?= e($status) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($status === 'Menunggu'): ?>
                                    <a href="pembayaran.php?id=<?= $id ?>" class="btn btn-sm btn-warning fw-semibold shadow-sm">
                                        <i class="bi bi-credit-card me-1"></i> Bayar
                                    </a>
                                <?php else: ?>
                                    <a href="status.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary fw-semibold">
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
