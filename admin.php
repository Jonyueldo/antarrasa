<?php
declare(strict_types=1);

// Admin: moderasi menu, manajemen akun (suspend/ban/hapus), dan verifikasi laporan.
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
    } elseif (in_array($aksi, ['suspend', 'ban', 'pulihkan', 'hapus'], true)) {
        // Sanksi akun; peran <> 'admin' mencegah admin menyanksi dirinya sendiri/admin lain.
        $target = (int) ($_POST['id_pengguna'] ?? 0);
        $syarat = " WHERE id_pengguna = ? AND peran <> 'admin'";
        $n = match ($aksi) {
            'suspend'  => eksekusi($conn, "UPDATE pengguna SET status_akun = 'suspend_sementara', suspend_sampai = NOW() + INTERVAL ? HOUR" . $syarat,
                'ii', [max(1, min(8760, (int) ($_POST['jam'] ?? 1))), $target]),
            'ban'      => eksekusi($conn, "UPDATE pengguna SET status_akun = 'ban_permanen', suspend_sampai = NULL" . $syarat, 'i', [$target]),
            'pulihkan' => eksekusi($conn, "UPDATE pengguna SET status_akun = 'aktif', suspend_sampai = NULL" . $syarat, 'i', [$target]),
            'hapus'    => eksekusi($conn, 'DELETE FROM pengguna' . $syarat, 'i', [$target]),
        };
        if ($n > 0) {
            eksekusi($conn, "UPDATE laporan SET status_laporan = 'diverifikasi' WHERE id_laporan = ?", 'i', [(int) ($_POST['id_laporan'] ?? 0)]);
        }
        flash($n > 0 ? 'Tindakan akun berhasil.' : 'Tindakan gagal atau tidak ada perubahan.', $n > 0 ? 'success' : 'danger');
    } elseif ($aksi === 'tolak_laporan') {
        eksekusi($conn, "UPDATE laporan SET status_laporan = 'ditolak' WHERE id_laporan = ? AND status_laporan = 'menunggu'", 'i', [(int) ($_POST['id_laporan'] ?? 0)]);
        flash('Laporan ditolak.');
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

// Tombol sanksi untuk satu akun (dipakai di daftar akun dan laporan).
function form_sanksi(int $id_pengguna, int $id_laporan = 0): string
{
    $tersembunyi = input_csrf() . '<input type="hidden" name="id_pengguna" value="' . $id_pengguna . '"><input type="hidden" name="id_laporan" value="' . $id_laporan . '">';
    $html = '<form method="post" class="d-inline-flex gap-1">' . $tersembunyi
        . '<input class="form-control form-control-sm" style="width:70px" type="number" name="jam" value="24" min="1" max="8760" aria-label="Jumlah jam">'
        . '<button class="btn btn-sm btn-warning" name="aksi" value="suspend">Suspend Jam</button></form> ';
    foreach (['ban' => ['Ban Permanen', 'danger'], 'pulihkan' => ['Pulihkan', 'outline-success'], 'hapus' => ['Hapus', 'outline-danger']] as $aksi => [$label, $warna]) {
        if ($id_laporan && $aksi !== 'ban') {
            continue; // di laporan cukup suspend / ban
        }
        $konfirmasi = $aksi === 'hapus' ? ' onsubmit="return confirm(&quot;Hapus akun ini?&quot;)"' : '';
        $html .= '<form method="post" class="d-inline"' . $konfirmasi . '>' . $tersembunyi
            . '<button class="btn btn-sm btn-' . $warna . '" name="aksi" value="' . $aksi . '">' . $label . '</button></form> ';
    }
    return $html;
}

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
                <td class="text-nowrap">
                    <?= form_sanksi((int) $l['id_terlapor'], (int) $l['id_laporan']) ?>
                    <form method="post" class="d-inline"><?= input_csrf() ?>
                        <input type="hidden" name="id_laporan" value="<?= e($l['id_laporan']) ?>">
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
        <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Status</th><th>Tindakan</th></tr></thead>
        <tbody>
        <?php foreach ($akun as $a): ?>
            <tr>
                <td><?= e($a['nama']) ?></td>
                <td><?= e($a['email']) ?></td>
                <td><?= e($a['peran']) ?></td>
                <td>
                    <?php if ($a['status_akun'] === 'ban_permanen'): ?><span class="badge bg-danger">Ban permanen</span>
                    <?php elseif ($a['sisa_detik'] > 0): ?><span class="badge bg-warning text-dark">Suspend s/d <?= e($a['suspend_sampai']) ?></span>
                    <?php else: ?><span class="badge bg-success">Aktif</span><?php endif; ?>
                </td>
                <td class="text-nowrap"><?= $a['peran'] === 'admin' ? '-' : form_sanksi((int) $a['id_pengguna']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
