<?php
declare(strict_types=1);

// Riwayat pesanan: pelanggan melihat miliknya, admin melihat semua dan mengubah status.
require_once __DIR__ . '/config/database.php';
mulai_sesi();
$pengguna = wajib_login();
$admin = $pengguna['peran'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    if (!$admin) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    $id = (int) ($_POST['id_pesanan'] ?? 0);
    $pesanan = ambil_satu($conn, 'SELECT status_pesanan FROM pesanan WHERE id_pesanan = ?', 'i', [$id]);
    $berikut = STATUS_BERIKUT[$pesanan['status_pesanan'] ?? ''] ?? null;
    // Hanya boleh maju satu tahap: Menunggu -> Diproses -> Selesai.
    if ($berikut && eksekusi($conn, 'UPDATE pesanan SET status_pesanan = ? WHERE id_pesanan = ? AND status_pesanan = ?', 'sis', [$berikut, $id, $pesanan['status_pesanan']]) > 0) {
        flash("Pesanan #$id kini $berikut.");
    } else {
        flash('Status pesanan tidak dapat diubah.', 'danger');
    }
    alihkan('riwayat.php');
}

$sql = 'SELECT p.*, u.nama_lengkap FROM pesanan p LEFT JOIN pengguna u ON u.id_pengguna = p.id_pengguna';
$daftar = $admin
    ? ambil_semua($conn, "$sql ORDER BY p.id_pesanan DESC")
    : ambil_semua($conn, "$sql WHERE p.id_pengguna = ? ORDER BY p.id_pesanan DESC", 'i', [$pengguna['id']]);

// Satu query untuk semua item pesanan yang tampil.
$item = [];
if ($daftar) {
    $id_pesanan = array_column($daftar, 'id_pesanan');
    $in = implode(',', array_fill(0, count($id_pesanan), '?'));
    $baris = ambil_semua($conn, "SELECT d.*, m.nama_menu FROM detail_pesanan d JOIN menu m ON m.id_menu = d.id_menu WHERE d.id_pesanan IN ($in)", str_repeat('i', count($id_pesanan)), $id_pesanan);
    foreach ($baris as $b) {
        $item[$b['id_pesanan']][] = $b;
    }
}

$judul = $admin ? 'Semua Pesanan' : 'Riwayat Pesanan';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h4 mb-3"><?= e($judul) ?></h1>
<?php foreach ($daftar as $o): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>#<?= e($o['id_pesanan']) ?> &middot; <?= e($o['tanggal_pesanan']) ?><?= $admin ? ' &middot; ' . e($o['nama_lengkap'] ?? '-') : '' ?></span>
            <?= badge_status($o['status_pesanan']) ?>
        </div>
        <div class="card-body">
            <ul class="mb-2">
                <?php foreach ($item[$o['id_pesanan']] ?? [] as $d): ?>
                    <li><?= e($d['nama_menu']) ?> &times; <?= e($d['jumlah']) ?> (<?= e(format_rupiah($d['harga_satuan'])) ?>)</li>
                <?php endforeach; ?>
            </ul>
            <div class="small text-muted">Alamat: <?= e($o['alamat_pengiriman']) ?></div>
            <div class="fw-bold">Total: <?= e(format_rupiah($o['total_harga'])) ?></div>
            <?php if ($admin && isset(STATUS_BERIKUT[$o['status_pesanan']])): ?>
                <form method="post" class="mt-2">
                    <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
                    <input type="hidden" name="id_pesanan" value="<?= e($o['id_pesanan']) ?>">
                    <button class="btn btn-sm btn-danger">Ubah ke <?= e(STATUS_BERIKUT[$o['status_pesanan']]) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php if (!$daftar): ?><p class="text-muted">Belum ada pesanan.</p><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
