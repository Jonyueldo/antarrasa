<?php
declare(strict_types=1);

/**
 * Laporan Transaksi Administrator (DataStore D2 JOIN D4)
 * Filter rentang tanggal & status, ringkasan, tabel berpaginasi, dan ekspor CSV.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions_auth.php';
require_once __DIR__ . '/includes/functions_order.php';

mulai_sesi();
wajib_csrf();

// Otorisasi: Khusus Administrator
cek_akses(['admin']);

// Filter (seluruhnya divalidasi: tanggal lewat bersihkan_tanggal, status lewat whitelist)
$dari = bersihkan_tanggal(isset($_GET['dari']) ? (string) $_GET['dari'] : null);
$sampai = bersihkan_tanggal(isset($_GET['sampai']) ? (string) $_GET['sampai'] : null);
if ($dari !== null && $sampai !== null && $dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}
$status = isset($_GET['status']) && in_array((string) $_GET['status'], STATUS_PESANAN_VALID, true)
    ? (string) $_GET['status'] : null;

$param_filter = array_filter(['dari' => $dari, 'sampai' => $sampai, 'status' => $status], static fn($v): bool => $v !== null);

// ---------------------------------------------------------------------
// Ekspor CSV: dikirim sebagai unduhan, tanpa layout HTML
// ---------------------------------------------------------------------
if (($_GET['ekspor'] ?? '') === 'csv') {
    $baris_laporan = ambil_laporan_pesanan($conn, $dari, $sampai, $status);
    $nama_file = 'laporan_antarrasa_' . ($dari ?? 'awal') . '_sd_' . ($sampai ?? date('Y-m-d')) . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nama_file . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    $keluaran = fopen('php://output', 'w');
    // BOM UTF-8 agar Excel membaca karakter non-ASCII dengan benar
    fwrite($keluaran, "\xEF\xBB\xBF");
    // Pemisah ';' agar langsung terbaca per kolom di Excel berlocale Indonesia.
    // Parameter escape '' wajib eksplisit: nilai default-nya deprecated sejak PHP 8.4.
    fputcsv($keluaran, ['ID Pesanan', 'Tanggal Pesanan', 'Pemesan', 'Username', 'Alamat Pengiriman', 'Status',
        'Metode Bayar', 'Waktu Bayar', 'Kurir', 'Waktu Selesai', 'Total (Rp)'], ';', '"', '');
    foreach ($baris_laporan as $b) {
        fputcsv($keluaran, array_map('sel_csv_aman', [
            (int) $b['id_pesanan'],
            (string) $b['tanggal_pesanan'],
            $b['nama_pemesan'] ?? 'Tamu (Guest)',
            $b['username_pemesan'] ?? '',
            $b['alamat_pengiriman'],
            $b['status_pesanan'],
            $b['metode_bayar'] ?? '',
            $b['waktu_bayar'] ?? '',
            $b['nama_driver'] ?? '',
            $b['waktu_selesai'] ?? '',
            number_format((float) $b['total_harga'], 0, '', ''),
        ]), ';', '"', '');
    }
    fclose($keluaran);
    exit;
}

// ---------------------------------------------------------------------
// Tampilan HTML
// ---------------------------------------------------------------------
$ringkasan = hitung_ringkasan_laporan($conn, $dari, $sampai, $status);
$paginasi = hitung_paginasi($ringkasan['jumlah'], 20, isset($_GET['hal']) ? (int) $_GET['hal'] : 1);
$daftar_laporan = ambil_laporan_pesanan($conn, $dari, $sampai, $status, $paginasi['per_halaman'], $paginasi['offset']);

$judul_halaman = 'Laporan Transaksi - Executive Backoffice';
$tampilkan_stepper = false;

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-extrabold text-dark mb-1"><i class="bi bi-file-earmark-bar-graph text-danger me-2"></i>Laporan Transaksi</h3>
        <div class="text-muted small">Saring transaksi berdasarkan rentang tanggal dan status, lalu ekspor ke CSV (Excel).</div>
    </div>
    <a href="laporan.php?<?= e(http_build_query(array_merge($param_filter, ['ekspor' => 'csv']))) ?>" class="btn btn-success fw-semibold">
        <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i> Ekspor CSV
    </a>
</div>

<!-- Filter Laporan -->
<form method="GET" action="laporan.php" class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label for="lap-dari" class="form-label small fw-semibold text-muted mb-1">Dari tanggal</label>
            <input type="date" class="form-control form-control-sm" id="lap-dari" name="dari" value="<?= e($dari ?? '') ?>">
        </div>
        <div class="col-6 col-md-3">
            <label for="lap-sampai" class="form-label small fw-semibold text-muted mb-1">Sampai tanggal</label>
            <input type="date" class="form-control form-control-sm" id="lap-sampai" name="sampai" value="<?= e($sampai ?? '') ?>">
        </div>
        <div class="col-12 col-md-3">
            <label for="lap-status" class="form-label small fw-semibold text-muted mb-1">Status pesanan</label>
            <select class="form-select form-select-sm" id="lap-status" name="status">
                <option value="">Semua status</option>
                <?php foreach (STATUS_PESANAN_VALID as $st): ?>
                    <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Terapkan</button>
            <a href="laporan.php" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<!-- Ringkasan -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="admin-stat-card border-start border-4 border-primary">
            <span class="text-muted small fw-semibold text-uppercase">Jumlah Transaksi</span>
            <h3 class="fw-extrabold text-dark mb-0 mt-1"><?= (int) $ringkasan['jumlah'] ?></h3>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="admin-stat-card border-start border-4 border-secondary">
            <span class="text-muted small fw-semibold text-uppercase">Total Nominal Pesanan</span>
            <h4 class="fw-extrabold text-dark mb-0 mt-1 font-monospace"><?= format_rupiah($ringkasan['nominal']) ?></h4>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="admin-stat-card border-start border-4 border-success">
            <span class="text-muted small fw-semibold text-uppercase">Pendapatan (Dibayar/Diproses)</span>
            <h4 class="fw-extrabold text-success mb-0 mt-1 font-monospace"><?= format_rupiah($ringkasan['pendapatan']) ?></h4>
        </div>
    </div>
</div>

<!-- Tabel Laporan -->
<div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4 bg-white">
    <div class="table-responsive">
        <table class="table tabel-kartu table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th class="ps-3">No. Order</th>
                    <th>Tanggal</th>
                    <th>Pemesan</th>
                    <th>Status</th>
                    <th>Pembayaran</th>
                    <th>Kurir</th>
                    <th class="text-end pe-3">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftar_laporan)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted small">Tidak ada transaksi yang sesuai dengan filter.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftar_laporan as $b): ?>
                        <?php $info_st = dapatkan_info_status((string) $b['status_pesanan']); ?>
                        <tr>
                            <td data-label="No. Order" class="ps-3 font-monospace fw-bold">
                                <a href="status.php?id=<?= (int) $b['id_pesanan'] ?>" class="text-decoration-none">#<?= (int) $b['id_pesanan'] ?></a>
                            </td>
                            <td data-label="Tanggal" class="small text-muted"><?= e(format_waktu((string) $b['tanggal_pesanan'])) ?></td>
                            <td data-label="Pemesan" class="small"><?= e($b['nama_pemesan'] ?? 'Tamu (Guest)') ?></td>
                            <td data-label="Status">
                                <span class="badge <?= e($info_st['class']) ?> rounded-pill"><?= e($b['status_pesanan']) ?></span>
                            </td>
                            <td data-label="Pembayaran" class="small"><?= e($b['metode_bayar'] ?? '-') ?></td>
                            <td data-label="Kurir" class="small"><?= e($b['nama_driver'] ?? '-') ?></td>
                            <td data-label="Total" class="text-end pe-3 font-monospace fw-bold"><?= format_rupiah((float) $b['total_harga']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= render_paginasi($paginasi, 'laporan.php', 'hal', $param_filter) ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
