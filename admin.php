<?php
declare(strict_types=1);

// Admin: moderasi menu, verifikasi laporan (suspend/ban), dan daftar akun (aktifkan kembali/hapus).
require_once __DIR__ . '/config/database.php';
mulai_sesi();
$saya = wajib_login($conn, 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'setujui' || $aksi === 'tolak') {
        $id = (int) ($_POST['id_menu'] ?? 0);
        $m = ambil_satu($conn, "SELECT * FROM menu WHERE id_menu = ? AND status_moderasi LIKE 'menunggu%'", 'i', [$id]);
        $jenis = $m['status_moderasi'] ?? '';
        if ($aksi === 'setujui' && $jenis === 'menunggu_hapus') {
            eksekusi($conn, 'DELETE FROM menu WHERE id_menu = ?', 'i', [$id]);
        } elseif ($aksi === 'setujui' && $jenis === 'menunggu_ubah') {
            $u = json_decode((string) $m['data_usulan_json'], true);
            eksekusi($conn, "UPDATE menu SET nama_menu = ?, deskripsi = ?, harga = ?, gambar = ?, status_moderasi = 'disetujui', data_usulan_json = NULL WHERE id_menu = ?",
                'ssdsi', [$u['nama_menu'], $u['deskripsi'], $u['harga'], $u['gambar'], $id]);
        } elseif ($m) {
            // Tolak tambah => ditolak; tolak ubah/hapus => kembali ke versi yang sudah disetujui.
            $status = ($aksi === 'tolak' && $jenis === 'menunggu_tambah') ? 'ditolak' : 'disetujui';
            eksekusi($conn, 'UPDATE menu SET status_moderasi = ?, data_usulan_json = NULL WHERE id_menu = ?', 'si', [$status, $id]);
        }
        flash($m ? 'Pengajuan menu ' . ($aksi === 'setujui' ? 'disetujui.' : 'ditolak.') : 'Pengajuan tidak ditemukan.', $m ? 'success' : 'danger');
    } elseif (in_array($aksi, ['suspend', 'ban', 'tolak_laporan'], true)) {
        // Verifikasi laporan: satu-satunya tempat menjatuhkan suspend/ban. Terlapor diambil dari laporan, bukan dari form.
        $id_laporan = (int) ($_POST['id_laporan'] ?? 0);
        $l = ambil_satu($conn, "SELECT l.id_terlapor, p.peran FROM laporan l JOIN pengguna p ON p.id_pengguna = l.id_terlapor
            WHERE l.id_laporan = ? AND l.status_laporan = 'menunggu'", 'i', [$id_laporan]);
        // Akun admin tidak bisa disanksi; laporan terhadapnya hanya bisa ditolak.
        $ok = $l !== null && ($aksi === 'tolak_laporan' || $l['peran'] !== 'admin');
        if ($ok) {
            mysqli_begin_transaction($conn);
            if ($aksi !== 'tolak_laporan') {
                $ok = ($aksi === 'suspend'
                    ? eksekusi($conn, "UPDATE pengguna SET status_akun = 'suspend_sementara', suspend_sampai = NOW() + INTERVAL ? HOUR WHERE id_pengguna = ?",
                        'ii', [max(1, min(8760, (int) ($_POST['jam'] ?? 1))), $l['id_terlapor']])
                    : eksekusi($conn, "UPDATE pengguna SET status_akun = 'ban_permanen', suspend_sampai = NULL WHERE id_pengguna = ?", 'i', [$l['id_terlapor']])) >= 0;
            }
            $ok = $ok && eksekusi($conn, 'UPDATE laporan SET status_laporan = ? WHERE id_laporan = ?', 'si',
                [$aksi === 'tolak_laporan' ? 'ditolak' : 'diverifikasi', $id_laporan]) > 0;
            $ok ? mysqli_commit($conn) : mysqli_rollback($conn);
        }
        $pesan = ['suspend' => 'Laporan disetujui, akun terlapor disuspend.', 'ban' => 'Laporan disetujui, akun terlapor diban permanen.', 'tolak_laporan' => 'Laporan ditolak.'];
        flash($ok ? $pesan[$aksi] : 'Laporan tidak ditemukan atau sudah diproses.', $ok ? 'success' : 'danger');
    } elseif ($aksi === 'pulihkan' || $aksi === 'hapus') {
        // Daftar akun: hanya cabut sanksi atau hapus; akun admin selalu dikunci.
        $syarat = " WHERE id_pengguna = ? AND peran <> 'admin'";
        $n = $aksi === 'pulihkan'
            ? eksekusi($conn, "UPDATE pengguna SET status_akun = 'aktif', suspend_sampai = NULL" . $syarat, 'i', [(int) ($_POST['id_pengguna'] ?? 0)])
            : eksekusi($conn, 'DELETE FROM pengguna' . $syarat, 'i', [(int) ($_POST['id_pengguna'] ?? 0)]);
        flash($n > 0 ? ($aksi === 'hapus' ? 'Akun dihapus.' : 'Akun diaktifkan kembali.') : 'Tindakan gagal atau tidak ada perubahan.', $n > 0 ? 'success' : 'danger');
    }
    alihkan('admin.php');
}

$pengajuan = ambil_semua($conn, "SELECT m.*, p.nama AS nama_merchant FROM menu m JOIN pengguna p ON p.id_pengguna = m.id_merchant
    WHERE m.status_moderasi LIKE 'menunggu%' ORDER BY m.id_menu");
$akun = ambil_semua($conn, 'SELECT *, GREATEST(COALESCE(TIMESTAMPDIFF(SECOND, NOW(), suspend_sampai), 0), 0) AS sisa_detik FROM pengguna ORDER BY peran, nama');
$statistik = array_count_values(array_column($akun, 'peran'));
$laporan = ambil_semua($conn, "SELECT l.*, a.nama AS nama_pelapor, b.nama AS nama_terlapor, b.peran AS peran_terlapor FROM laporan l
    JOIN pengguna a ON a.id_pengguna = l.id_pelapor JOIN pengguna b ON b.id_pengguna = l.id_terlapor
    WHERE l.status_laporan = 'menunggu' ORDER BY l.id_laporan");

$judul = 'Admin';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h4 mb-3">Panel Admin</h1>

<h2 class="h5">Moderasi Menu (<?= count($pengajuan) ?>)</h2>
<div class="table-responsive mb-4">
    <table class="table bg-white align-middle">
        <thead><tr><th>Pengajuan</th><th>Merchant</th><th>Data saat ini</th><th>Usulan</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pengajuan as $m): $u = json_decode((string) $m['data_usulan_json'], true); ?>
            <tr>
                <td><span class="badge bg-warning text-dark"><?= e(str_replace('menunggu_', '', $m['status_moderasi'])) ?></span></td>
                <td><?= e($m['nama_merchant']) ?></td>
                <td>
                    <?php if ($m['gambar']): ?><img src="<?= e($m['gambar']) ?>" alt="" width="40" height="40" class="rounded" style="object-fit:cover"><?php endif; ?>
                    <?= e($m['nama_menu']) ?> &middot; <?= e(format_rupiah($m['harga'])) ?>
                    <div class="small text-muted"><?= e($m['deskripsi']) ?></div>
                </td>
                <td>
                    <?php if ($u): ?>
                        <?php if ($u['gambar']): ?><img src="<?= e($u['gambar']) ?>" alt="" width="40" height="40" class="rounded" style="object-fit:cover"><?php endif; ?>
                        <?= e($u['nama_menu']) ?> &middot; <?= e(format_rupiah($u['harga'])) ?>
                        <div class="small text-muted"><?= e($u['deskripsi']) ?></div>
                    <?php else: ?>-<?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <?php foreach (['setujui' => 'success', 'tolak' => 'outline-danger'] as $aksi => $warna): ?>
                        <form method="post" class="d-inline"><?= input_csrf() ?>
                            <input type="hidden" name="aksi" value="<?= $aksi ?>"><input type="hidden" name="id_menu" value="<?= e($m['id_menu']) ?>">
                            <button class="btn btn-sm btn-<?= $warna ?>"><?= ucfirst($aksi) ?></button>
                        </form>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$pengajuan): ?><tr><td colspan="5" class="text-muted">Tidak ada pengajuan.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<h2 class="h5">Laporan Masuk (<?= count($laporan) ?>)</h2>
<div class="table-responsive mb-4">
    <table class="table bg-white align-middle">
        <thead><tr><th>Pelapor</th><th>Terlapor</th><th>Pesanan</th><th>Alasan</th><th>Tindakan</th></tr></thead>
        <tbody>
        <?php foreach ($laporan as $l): ?>
            <tr>
                <td><?= e($l['nama_pelapor']) ?></td>
                <td><?= e($l['nama_terlapor']) ?> (<?= e($l['peran_terlapor']) ?>)</td>
                <td><?= $l['id_pesanan'] ? '#' . e($l['id_pesanan']) : '-' ?></td>
                <td><?= e($l['alasan']) ?></td>
                <td>
                    <form method="post" class="d-flex flex-wrap gap-1"><?= input_csrf() ?>
                        <input type="hidden" name="id_laporan" value="<?= e($l['id_laporan']) ?>">
                        <?php if ($l['peran_terlapor'] !== 'admin'): ?>
                            <input class="form-control form-control-sm" style="width:70px" type="number" name="jam" value="24" min="1" max="8760" aria-label="Lama suspend (jam)">
                            <button class="btn btn-sm btn-warning" name="aksi" value="suspend">Setujui &amp; Suspend (jam)</button>
                            <button class="btn btn-sm btn-danger" name="aksi" value="ban" onclick="return confirm('Ban permanen akun ini?')">Setujui &amp; Ban Permanen</button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-secondary" name="aksi" value="tolak_laporan">Tolak Laporan</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$laporan): ?><tr><td colspan="5" class="text-muted">Tidak ada laporan baru.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<h2 class="h5">Akun (<?= count($akun) ?>)</h2>
<div class="d-flex flex-wrap gap-2 mb-2">
    <?php foreach (['pelanggan', 'merchant', 'driver', 'admin'] as $r): ?>
        <span class="badge bg-light text-dark border fs-6"><?= e(ucfirst($r)) ?>: <?= e($statistik[$r] ?? 0) ?></span>
    <?php endforeach; ?>
</div>
<div class="table-responsive">
    <table class="table bg-white align-middle">
        <thead><tr><th>Username</th><th>Nama</th><th>Peran</th><th>Status</th><th>Tindakan</th></tr></thead>
        <tbody>
        <?php foreach ($akun as $a): ?>
            <tr>
                <td><?= e($a['username']) ?></td>
                <td><?= e($a['nama']) ?></td>
                <td><?= e($a['peran']) ?></td>
                <td>
                    <?php if ($a['status_akun'] === 'ban_permanen'): ?><span class="badge bg-danger">Ban permanen</span>
                    <?php elseif ($a['sisa_detik'] > 0): ?><span class="badge bg-warning text-dark">Suspend s/d <?= e($a['suspend_sampai']) ?></span>
                    <?php else: ?><span class="badge bg-success">Aktif</span><?php endif; ?>
                </td>
                <td class="text-nowrap">
                    <?php if ($a['peran'] === 'admin'): ?>
                        <span class="text-muted small">Terkunci</span>
                    <?php else: ?>
                        <form method="post" class="d-inline"><?= input_csrf() ?>
                            <input type="hidden" name="id_pengguna" value="<?= e($a['id_pengguna']) ?>">
                            <?php if ($a['status_akun'] === 'ban_permanen' || $a['sisa_detik'] > 0): ?>
                                <button class="btn btn-sm btn-outline-success" name="aksi" value="pulihkan">Aktifkan Kembali</button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-danger" name="aksi" value="hapus" onclick="return confirm('Hapus akun ini?')">Hapus Akun</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
