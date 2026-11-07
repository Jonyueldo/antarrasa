<?php
declare(strict_types=1);

// Pelaporan akun oleh pelanggan, merchant, dan driver.
require_once __DIR__ . '/config/database.php';
mulai_sesi();
$saya = wajib_login($conn, 'pelanggan', 'merchant', 'driver');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $terlapor = (int) ($_POST['id_terlapor'] ?? 0);
    $id_pesanan = (int) ($_POST['id_pesanan'] ?? 0) ?: null;
    $alasan = trim((string) ($_POST['alasan'] ?? ''));
    $sah_terlapor = ambil_satu($conn, "SELECT 1 FROM pengguna WHERE id_pengguna = ? AND id_pengguna <> ? AND peran <> 'admin'", 'ii', [$terlapor, $saya['id']]);
    // Nomor pesanan (opsional) harus pesanan yang melibatkan pelapor.
    $sah_pesanan = $id_pesanan === null || ambil_satu($conn, 'SELECT 1 FROM pesanan WHERE id_pesanan = ? AND ? IN (id_pelanggan, id_merchant, id_driver)', 'ii', [$id_pesanan, $saya['id']]);

    if (!$sah_terlapor || !$sah_pesanan || $alasan === '') {
        flash('Pilih akun terlapor, isi alasan, dan pastikan nomor pesanan milik Anda.', 'danger');
    } else {
        eksekusi($conn, 'INSERT INTO laporan (id_pelapor, id_terlapor, id_pesanan, alasan) VALUES (?, ?, ?, ?)', 'iiis', [$saya['id'], $terlapor, $id_pesanan, $alasan]);
        flash('Laporan terkirim dan akan ditinjau admin.');
    }
    alihkan('laporan.php');
}

$akun = ambil_semua($conn, "SELECT id_pengguna, nama, peran FROM pengguna WHERE id_pengguna <> ? AND peran <> 'admin' ORDER BY peran, nama", 'i', [$saya['id']]);
$laporan_saya = ambil_semua($conn, 'SELECT l.*, p.nama AS nama_terlapor FROM laporan l LEFT JOIN pengguna p ON p.id_pengguna = l.id_terlapor
    WHERE l.id_pelapor = ? ORDER BY l.id_laporan DESC', 'i', [$saya['id']]);
$judul = 'Lapor Akun';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h4 mb-3">Lapor Akun</h1>
<form method="post" class="card card-body mb-4" style="max-width: 560px;">
    <?= input_csrf() ?>
    <label class="form-label" for="id_terlapor">Akun yang dilaporkan</label>
    <select class="form-select mb-3" id="id_terlapor" name="id_terlapor" required>
        <option value="">-- pilih akun --</option>
        <?php foreach ($akun as $a): ?>
            <option value="<?= e($a['id_pengguna']) ?>"><?= e($a['nama']) ?> (<?= e($a['peran']) ?>)</option>
        <?php endforeach; ?>
    </select>
    <label class="form-label" for="id_pesanan">Nomor pesanan terkait (opsional)</label>
    <input class="form-control mb-3" id="id_pesanan" type="number" name="id_pesanan" min="1">
    <label class="form-label" for="alasan">Alasan</label>
    <textarea class="form-control mb-3" id="alasan" name="alasan" rows="3" required></textarea>
    <button class="btn btn-danger">Kirim Laporan</button>
</form>

<h2 class="h5">Laporan Saya</h2>
<ul class="list-group">
    <?php foreach ($laporan_saya as $l): ?>
        <li class="list-group-item d-flex justify-content-between">
            <span><?= e($l['nama_terlapor'] ?? '-') ?>: <?= e($l['alasan']) ?></span>
            <span class="badge bg-secondary align-self-center"><?= e($l['status_laporan']) ?></span>
        </li>
    <?php endforeach; ?>
    <?php if (!$laporan_saya): ?><li class="list-group-item text-muted">Belum ada laporan.</li><?php endif; ?>
</ul>
<?php require __DIR__ . '/includes/footer.php'; ?>
