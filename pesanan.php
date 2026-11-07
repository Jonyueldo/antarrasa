<?php
declare(strict_types=1);

// Keranjang (session) dan checkout.
require_once __DIR__ . '/config/database.php';
mulai_sesi();
$saya = wajib_login($conn, 'pelanggan');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'ubah') {
        $id = (int) ($_POST['id_menu'] ?? 0);
        $jumlah = min(50, (int) ($_POST['jumlah'] ?? 0));
        if ($jumlah > 0 && isset($_SESSION['keranjang'][$id])) {
            $_SESSION['keranjang'][$id] = $jumlah;
        } else {
            unset($_SESSION['keranjang'][$id]); // jumlah 0 = hapus
        }
        alihkan('pesanan.php');
    }

    if ($aksi === 'checkout') {
        $alamat = trim((string) ($_POST['alamat'] ?? ''));
        if ($alamat === '') {
            flash('Alamat pengiriman wajib diisi.', 'danger');
            alihkan('pesanan.php');
        }
        // Harga & ketersediaan dibaca ulang di dalam transaksi.
        mysqli_begin_transaction($conn);
        [$item, $total] = detail_keranjang($conn);
        $merchant = array_unique(array_column($item, 'id_merchant'));
        $ok = $item && count($item) === count($_SESSION['keranjang']) && count($merchant) === 1
            && !in_array(0, array_map('intval', array_column($item, 'bisa_dipesan')), true)
            && eksekusi($conn, 'INSERT INTO pesanan (id_pelanggan, id_merchant, alamat_antar, total_harga) VALUES (?, ?, ?, ?)', 'iisd', [$saya['id'], $merchant[0], $alamat, $total]) > 0;
        if ($ok) {
            $id_pesanan = mysqli_insert_id($conn);
            foreach ($item as $m) {
                $ok = $ok && eksekusi($conn, 'INSERT INTO detail_pesanan (id_pesanan, id_menu, jumlah, subtotal) VALUES (?, ?, ?, ?)', 'iiid', [$id_pesanan, $m['id_menu'], $m['jumlah'], $m['subtotal']]) > 0;
            }
        }
        if ($ok) {
            mysqli_commit($conn);
            unset($_SESSION['keranjang'], $_SESSION['merchant_keranjang']);
            flash('Pesanan dibuat dan menunggu driver.');
            alihkan('riwayat.php');
        }
        mysqli_rollback($conn);
        flash('Checkout gagal: ada menu yang habis atau tidak lagi tersedia. Periksa keranjang Anda.', 'danger');
        alihkan('pesanan.php');
    }
}

[$item, $total] = detail_keranjang($conn);
$judul = 'Keranjang';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h4 mb-3">Keranjang</h1>
<?php if (!$item): ?>
    <p class="text-muted">Keranjang kosong. <a href="index.php">Lihat katalog</a></p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table bg-white align-middle">
            <thead><tr><th>Menu</th><th>Harga</th><th style="width:140px">Jumlah</th><th class="text-end">Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($item as $m): ?>
                <tr>
                    <td><?= e($m['nama_menu']) ?><?= $m['bisa_dipesan'] ? '' : ' <span class="badge bg-secondary">Tidak tersedia</span>' ?></td>
                    <td><?= e(format_rupiah($m['harga'])) ?></td>
                    <td>
                        <form method="post" class="d-flex gap-1">
                            <?= input_csrf() ?>
                            <input type="hidden" name="aksi" value="ubah">
                            <input type="hidden" name="id_menu" value="<?= e($m['id_menu']) ?>">
                            <input class="form-control form-control-sm" type="number" name="jumlah" value="<?= e($m['jumlah']) ?>" min="0" max="50" aria-label="Jumlah <?= e($m['nama_menu']) ?>">
                            <button class="btn btn-sm btn-outline-secondary">OK</button>
                        </form>
                    </td>
                    <td class="text-end"><?= e(format_rupiah($m['subtotal'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end"><?= e(format_rupiah($total)) ?></th></tr></tfoot>
        </table>
    </div>
    <p class="small text-muted">Isi jumlah 0 untuk menghapus item.</p>
    <form method="post" class="card card-body">
        <?= input_csrf() ?>
        <input type="hidden" name="aksi" value="checkout">
        <label class="form-label" for="alamat">Alamat pengiriman</label>
        <textarea class="form-control mb-3" id="alamat" name="alamat" rows="2" required></textarea>
        <button class="btn btn-danger">Pesan Sekarang</button>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
