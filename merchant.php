<?php
declare(strict_types=1);

// Merchant: ajukan tambah/ubah/hapus menu (perlu persetujuan admin) dan ubah stok langsung.
require_once __DIR__ . '/config/database.php';
mulai_sesi();
$saya = wajib_login($conn, 'merchant');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id_menu'] ?? 0);
    $menu = ambil_satu($conn, 'SELECT * FROM menu WHERE id_menu = ? AND id_merchant = ?', 'ii', [$id, $saya['id']]);
    // Menu yang belum pernah tayang boleh diubah/dihapus langsung tanpa persetujuan.
    $belum_tayang = $menu && in_array($menu['status_moderasi'], ['menunggu_tambah', 'ditolak'], true);

    if ($aksi === 'simpan') {
        $nama = trim((string) ($_POST['nama_menu'] ?? ''));
        $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
        $harga = (float) ($_POST['harga'] ?? 0);
        $gambar = unggah_gambar($_FILES['gambar'] ?? []);
        if ($nama === '' || $harga <= 0 || $gambar === false) {
            flash('Nama wajib diisi, harga harus lebih dari 0, dan gambar harus JPG/PNG/WEBP maksimal 2 MB.', 'danger');
            alihkan('merchant.php' . ($id ? "?edit=$id" : ''));
        }
        if ($id === 0) {
            eksekusi($conn, 'INSERT INTO menu (id_merchant, nama_menu, deskripsi, harga, gambar) VALUES (?, ?, ?, ?, ?)', 'issds', [$saya['id'], $nama, $deskripsi, $harga, $gambar]);
        } elseif ($belum_tayang) {
            eksekusi($conn, "UPDATE menu SET nama_menu = ?, deskripsi = ?, harga = ?, gambar = COALESCE(?, gambar), status_moderasi = 'menunggu_tambah' WHERE id_menu = ?", 'ssdsi', [$nama, $deskripsi, $harga, $gambar, $id]);
        } elseif ($menu) {
            $usulan = json_encode(['nama_menu' => $nama, 'deskripsi' => $deskripsi, 'harga' => $harga, 'gambar' => $gambar ?? $menu['gambar']]);
            eksekusi($conn, "UPDATE menu SET status_moderasi = 'menunggu_ubah', data_usulan_json = ? WHERE id_menu = ?", 'si', [$usulan, $id]);
        }
        flash('Pengajuan menu dikirim dan menunggu persetujuan admin.');
    } elseif ($aksi === 'hapus' && $menu) {
        $belum_tayang
            ? eksekusi($conn, 'DELETE FROM menu WHERE id_menu = ?', 'i', [$id])
            : eksekusi($conn, "UPDATE menu SET status_moderasi = 'menunggu_hapus', data_usulan_json = NULL WHERE id_menu = ?", 'i', [$id]);
        flash($belum_tayang ? 'Menu dihapus.' : 'Pengajuan hapus menu dikirim ke admin.');
    } elseif ($aksi === 'stok' && $menu) {
        eksekusi($conn, "UPDATE menu SET status_stok = IF(status_stok = 'tersedia', 'habis', 'tersedia') WHERE id_menu = ?", 'i', [$id]);
        flash('Status stok diperbarui.');
    }
    alihkan('merchant.php');
}

$daftar_menu = ambil_semua($conn, 'SELECT * FROM menu WHERE id_merchant = ? ORDER BY id_menu DESC', 'i', [$saya['id']]);
$edit = null;
foreach ($daftar_menu as $m) {
    if ((int) $m['id_menu'] === (int) ($_GET['edit'] ?? 0)) {
        $edit = array_merge($m, json_decode((string) $m['data_usulan_json'], true) ?? []);
    }
}
$judul = 'Menu Saya';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h4 mb-3">Menu Saya</h1>
<form method="post" enctype="multipart/form-data" class="card card-body mb-4">
    <?= input_csrf() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id_menu" value="<?= e($edit['id_menu'] ?? 0) ?>">
    <h2 class="h6"><?= $edit ? 'Ajukan Perubahan Menu' : 'Ajukan Menu Baru' ?></h2>
    <div class="row g-2">
        <div class="col-md-3"><input class="form-control" name="nama_menu" placeholder="Nama menu" aria-label="Nama menu" required value="<?= e($edit['nama_menu'] ?? '') ?>"></div>
        <div class="col-md-2"><input class="form-control" type="number" name="harga" min="1" placeholder="Harga" aria-label="Harga" required value="<?= e(isset($edit['harga']) ? (int) $edit['harga'] : '') ?>"></div>
        <div class="col-md-3"><input class="form-control" name="deskripsi" placeholder="Deskripsi" aria-label="Deskripsi" value="<?= e($edit['deskripsi'] ?? '') ?>"></div>
        <div class="col-md-3"><input class="form-control" type="file" name="gambar" accept="image/jpeg,image/png,image/webp" aria-label="Gambar menu"></div>
        <div class="col-md-1"><button class="btn btn-danger w-100">Ajukan</button></div>
    </div>
    <?php if ($edit): ?><a class="small mt-2" href="merchant.php">Batal ubah</a><?php endif; ?>
</form>

<div class="table-responsive">
    <table class="table bg-white align-middle">
        <thead><tr><th>Menu</th><th>Harga</th><th>Moderasi</th><th>Stok</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($daftar_menu as $m): ?>
            <tr>
                <td>
                    <?php if ($m['gambar']): ?><img src="<?= e($m['gambar']) ?>" alt="" width="48" height="48" class="rounded me-2" style="object-fit:cover"><?php endif; ?>
                    <?= e($m['nama_menu']) ?>
                    <?php if ($m['data_usulan_json']): $u = json_decode($m['data_usulan_json'], true); ?>
                        <div class="small text-muted">Usulan: <?= e($u['nama_menu']) ?>, <?= e(format_rupiah($u['harga'])) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= e(format_rupiah($m['harga'])) ?></td>
                <td><span class="badge bg-<?= $m['status_moderasi'] === 'disetujui' ? 'success' : ($m['status_moderasi'] === 'ditolak' ? 'danger' : 'warning text-dark') ?>"><?= e(str_replace('_', ' ', $m['status_moderasi'])) ?></span></td>
                <td>
                    <form method="post"><?= input_csrf() ?>
                        <input type="hidden" name="aksi" value="stok"><input type="hidden" name="id_menu" value="<?= e($m['id_menu']) ?>">
                        <button class="btn btn-sm btn-outline-<?= $m['status_stok'] === 'tersedia' ? 'success' : 'secondary' ?>"><?= e(ucfirst($m['status_stok'])) ?></button>
                    </form>
                </td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="merchant.php?edit=<?= e($m['id_menu']) ?>">Ubah</a>
                    <?php if ($m['status_moderasi'] !== 'menunggu_hapus'): ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus menu ini?')"><?= input_csrf() ?>
                            <input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id_menu" value="<?= e($m['id_menu']) ?>">
                            <button class="btn btn-sm btn-outline-danger">Hapus</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$daftar_menu): ?><tr><td colspan="5" class="text-muted">Belum ada menu.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
