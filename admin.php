<?php
declare(strict_types=1);

// CRUD menu (khusus admin).
require_once __DIR__ . '/config/database.php';
mulai_sesi();
wajib_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id_menu'] ?? 0);
    $nama = trim((string) ($_POST['nama_menu'] ?? ''));
    $harga = (float) ($_POST['harga'] ?? 0);
    $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
    $status = ($_POST['status_menu'] ?? '') === 'Habis' ? 'Habis' : 'Tersedia';

    if ($aksi === 'simpan' && ($nama === '' || $harga <= 0)) {
        flash('Nama wajib diisi dan harga harus lebih dari 0.', 'danger');
        alihkan('admin.php' . ($id ? "?edit=$id" : ''));
    }
    if ($aksi === 'simpan' && $id === 0) {
        $hasil = eksekusi($conn, 'INSERT INTO menu (nama_menu, harga, deskripsi, status_menu) VALUES (?, ?, ?, ?)', 'sdss', [$nama, $harga, $deskripsi, $status]);
        flash($hasil > 0 ? 'Menu ditambahkan.' : 'Gagal menambah menu.', $hasil > 0 ? 'success' : 'danger');
    } elseif ($aksi === 'simpan') {
        $hasil = eksekusi($conn, 'UPDATE menu SET nama_menu = ?, harga = ?, deskripsi = ?, status_menu = ? WHERE id_menu = ?', 'sdssi', [$nama, $harga, $deskripsi, $status, $id]);
        flash($hasil >= 0 ? 'Menu diperbarui.' : 'Gagal memperbarui menu.', $hasil >= 0 ? 'success' : 'danger');
    } elseif ($aksi === 'hapus') {
        $hasil = eksekusi($conn, 'DELETE FROM menu WHERE id_menu = ?', 'i', [$id]);
        // Gagal biasanya karena menu sudah tercatat di detail_pesanan (foreign key).
        $hasil > 0 ? flash('Menu dihapus.') : flash('Menu tidak bisa dihapus karena sudah pernah dipesan. Ubah statusnya menjadi Habis.', 'danger');
    }
    alihkan('admin.php');
}

$edit = isset($_GET['edit']) ? ambil_satu($conn, 'SELECT * FROM menu WHERE id_menu = ?', 'i', [(int) $_GET['edit']]) : null;
$daftar_menu = ambil_semua($conn, 'SELECT * FROM menu ORDER BY nama_menu');
$judul = 'Kelola Menu';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h4 mb-3">Kelola Menu</h1>
<form method="post" class="card card-body mb-4">
    <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id_menu" value="<?= e($edit['id_menu'] ?? 0) ?>">
    <h2 class="h6"><?= $edit ? 'Ubah Menu' : 'Tambah Menu' ?></h2>
    <div class="row g-2">
        <div class="col-md-4"><input class="form-control" name="nama_menu" placeholder="Nama menu" aria-label="Nama menu" required value="<?= e($edit['nama_menu'] ?? '') ?>"></div>
        <div class="col-md-2"><input class="form-control" type="number" name="harga" min="1" step="1" placeholder="Harga" aria-label="Harga" required value="<?= e(isset($edit['harga']) ? (int) $edit['harga'] : '') ?>"></div>
        <div class="col-md-3"><input class="form-control" name="deskripsi" placeholder="Deskripsi" aria-label="Deskripsi" value="<?= e($edit['deskripsi'] ?? '') ?>"></div>
        <div class="col-md-2">
            <select class="form-select" name="status_menu" aria-label="Status">
                <?php foreach (['Tersedia', 'Habis'] as $s): ?>
                    <option <?= ($edit['status_menu'] ?? 'Tersedia') === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1"><button class="btn btn-danger w-100">Simpan</button></div>
    </div>
    <?php if ($edit): ?><a class="small mt-2" href="admin.php">Batal ubah</a><?php endif; ?>
</form>

<div class="table-responsive">
    <table class="table table-sm bg-white align-middle">
        <thead><tr><th>Menu</th><th>Harga</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($daftar_menu as $m): ?>
            <tr>
                <td><?= e($m['nama_menu']) ?></td>
                <td><?= e(format_rupiah($m['harga'])) ?></td>
                <td><?= e($m['status_menu']) ?></td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="admin.php?edit=<?= e($m['id_menu']) ?>">Ubah</a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus menu ini?')">
                        <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id_menu" value="<?= e($m['id_menu']) ?>">
                        <button class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
