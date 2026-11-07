<?php
declare(strict_types=1);

// Admin: moderasi menu.
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
    }
    alihkan('admin.php');
}

$pengajuan = ambil_semua($conn, "SELECT m.*, p.nama AS nama_merchant FROM menu m JOIN pengguna p ON p.id_pengguna = m.id_merchant
    WHERE m.status_moderasi LIKE 'menunggu%' ORDER BY m.id_menu");
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
<?php require __DIR__ . '/includes/footer.php'; ?>
