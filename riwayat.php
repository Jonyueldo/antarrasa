<?php
declare(strict_types=1);

// Daftar pesanan untuk semua peran + aksi driver bertahap.
require_once __DIR__ . '/config/database.php';
mulai_sesi();
$saya = wajib_login($conn);
$peran = $saya['peran'];

// aksi driver => [status asal, status tujuan]
const ALUR_DRIVER = [
    'terima'  => ['menunggu_driver', 'menuju_merchant'],
    'tolak'   => ['menunggu_driver', 'ditolak_driver'],
    'antar'   => ['menuju_merchant', 'diantar'],
    'lokasi'  => ['diantar', 'diantar'],
    'selesai' => ['diantar', 'selesai'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id_pesanan'] ?? 0);

    if ($peran === 'driver' && isset(ALUR_DRIVER[$aksi])) {
        [$dari, $ke] = ALUR_DRIVER[$aksi];
        $lat = $lng = $estimasi = null;
        if ($aksi === 'antar' || $aksi === 'lokasi') {
            // Koordinat dari GPS browser; jika kosong/tidak valid pakai simulasi sekitar Jakarta.
            $lat = is_numeric($_POST['lat'] ?? '') && abs((float) $_POST['lat']) <= 90 ? (float) $_POST['lat'] : -6.2 + random_int(0, 2000) / 100000;
            $lng = is_numeric($_POST['lng'] ?? '') && abs((float) $_POST['lng']) <= 180 ? (float) $_POST['lng'] : 106.81 + random_int(0, 2000) / 100000;
            $estimasi = $aksi === 'antar' ? random_int(15, 30) : null;
        }
        // COALESCE(id_driver, saya) = saya: pesanan baru (belum ada driver) atau milik driver ini.
        $n = eksekusi($conn, 'UPDATE pesanan SET status_pesanan = ?, id_driver = ?, lat_driver = COALESCE(?, lat_driver),
            lng_driver = COALESCE(?, lng_driver), estimasi_menit = COALESCE(?, estimasi_menit)
            WHERE id_pesanan = ? AND status_pesanan = ? AND COALESCE(id_driver, ?) = ?',
            'siddiisii', [$ke, $saya['id'], $lat, $lng, $estimasi, $id, $dari, $saya['id'], $saya['id']]);
        flash($n > 0 ? 'Pesanan #' . $id . ': ' . LABEL_STATUS[$ke][0] . '.' : 'Aksi tidak dapat dilakukan (pesanan sudah diambil atau statusnya berubah).', $n > 0 ? 'success' : 'danger');
    }
    alihkan('riwayat.php');
}

// Filter per peran; driver juga melihat pesanan baru yang menunggu driver.
[$where, $tipe, $param] = match ($peran) {
    'pelanggan' => ['p.id_pelanggan = ?', 'i', [$saya['id']]],
    'merchant'  => ['p.id_merchant = ?', 'i', [$saya['id']]],
    'driver'    => ["p.id_driver = ? OR p.status_pesanan = 'menunggu_driver'", 'i', [$saya['id']]],
    default     => ['1 = 1', '', []],
};
$daftar = ambil_semua($conn, "SELECT p.*, pl.nama AS nama_pelanggan, mc.nama AS nama_merchant, dr.nama AS nama_driver
    FROM pesanan p
    LEFT JOIN pengguna pl ON pl.id_pengguna = p.id_pelanggan
    LEFT JOIN pengguna mc ON mc.id_pengguna = p.id_merchant
    LEFT JOIN pengguna dr ON dr.id_pengguna = p.id_driver
    WHERE $where ORDER BY p.status_pesanan = 'selesai', p.id_pesanan DESC", $tipe, $param);

// Satu query untuk semua item pesanan yang tampil.
$item = [];
if ($daftar) {
    $id_pesanan = array_column($daftar, 'id_pesanan');
    $in = implode(',', array_fill(0, count($id_pesanan), '?'));
    $baris = ambil_semua($conn, "SELECT d.*, m.nama_menu FROM detail_pesanan d LEFT JOIN menu m ON m.id_menu = d.id_menu
        WHERE d.id_pesanan IN ($in)", str_repeat('i', count($id_pesanan)), $id_pesanan);
    foreach ($baris as $b) {
        $item[$b['id_pesanan']][] = $b;
    }
}

// Tombol aksi driver per status.
$tombol_driver = [
    'menunggu_driver' => ['terima' => ['Terima', 'success'], 'tolak' => ['Tolak', 'outline-danger']],
    'menuju_merchant' => ['antar' => ['Mulai Antar', 'primary']],
    'diantar'         => ['lokasi' => ['Perbarui Lokasi', 'outline-primary'], 'selesai' => ['Konfirmasi Pesanan Diterima', 'success']],
];
$pakai_peta = true;
$judul = ['pelanggan' => 'Pesanan Saya', 'merchant' => 'Pesanan Masuk', 'driver' => 'Tugas Antar'][$peran] ?? 'Semua Pesanan';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h4 mb-3"><?= e($judul) ?></h1>
<?php foreach ($daftar as $o): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>#<?= e($o['id_pesanan']) ?> &middot; <?= e($o['tanggal_pesan']) ?></span>
            <?= badge_status($o['status_pesanan']) ?>
        </div>
        <div class="card-body">
            <div class="small text-muted mb-2">
                Pelanggan: <?= e($o['nama_pelanggan'] ?? '-') ?> &middot; Merchant: <?= e($o['nama_merchant'] ?? '-') ?> &middot; Driver: <?= e($o['nama_driver'] ?? '-') ?>
            </div>
            <ul class="mb-2">
                <?php foreach ($item[$o['id_pesanan']] ?? [] as $d): ?>
                    <li><?= e($d['nama_menu'] ?? '(menu dihapus)') ?> &times; <?= e($d['jumlah']) ?> = <?= e(format_rupiah($d['subtotal'])) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="small">Alamat antar: <?= e($o['alamat_antar']) ?></div>
            <div class="fw-bold mb-2">Total: <?= e(format_rupiah($o['total_harga'])) ?></div>

            <?php if ($o['status_pesanan'] === 'diantar' && $o['lat_driver'] !== null): ?>
                <div class="alert alert-info py-2 mb-2">Estimasi tiba &plusmn; <?= e($o['estimasi_menit']) ?> menit</div>
                <div class="peta rounded border mb-2" style="height:220px" data-lat="<?= e($o['lat_driver']) ?>" data-lng="<?= e($o['lng_driver']) ?>"></div>
            <?php endif; ?>

            <?php if ($peran === 'driver'): ?>
                <?php foreach ($tombol_driver[$o['status_pesanan']] ?? [] as $aksi => [$label, $warna]): ?>
                    <form method="post" class="d-inline" <?= in_array($aksi, ['antar', 'lokasi'], true) ? 'data-gps' : '' ?>>
                        <?= input_csrf() ?>
                        <input type="hidden" name="aksi" value="<?= e($aksi) ?>">
                        <input type="hidden" name="id_pesanan" value="<?= e($o['id_pesanan']) ?>">
                        <input type="hidden" name="lat"><input type="hidden" name="lng">
                        <button class="btn btn-sm btn-<?= e($warna) ?>"><?= e($label) ?></button>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php if (!$daftar): ?><p class="text-muted">Belum ada pesanan.</p><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
