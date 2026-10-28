<?php
declare(strict_types=1);

/**
 * Dashboard Administrator AntarRasa (Executive Backoffice)
 * Manajemen Pengguna Penuh (Tambah, Edit, Ganti Peran, Reset Password, Hapus) pada DataStore D4 (PENGGUNA)
 * serta Pemantauan Kinerja Transaksi (D2: PESANAN JOIN D4: PENGGUNA)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions_auth.php';
require_once __DIR__ . '/includes/functions_admin.php';
require_once __DIR__ . '/includes/functions_order.php';

mulai_sesi();

// Otorisasi: Khusus Administrator
cek_akses(['admin']);

$pengguna_aktif = ambil_pengguna_login();
$id_admin_aktif = (int)($pengguna_aktif['id_pengguna'] ?? 0);

$pesan_sukses = '';
$pesan_error = '';

// Ambil notifikasi dari URL redirect (Penerapan PRG Pattern)
if (isset($_GET['pesan_sukses'])) {
    $pesan_sukses = (string)$_GET['pesan_sukses'];
} elseif (isset($_GET['status']) && $_GET['status'] === 'sukses') {
    $pesan_sukses = 'Operasi data pengguna berhasil disimpan permanen ke database MySQL.';
}

if (isset($_GET['pesan_error'])) {
    $pesan_error = (string)$_GET['pesan_error'];
} elseif (isset($_GET['status']) && $_GET['status'] === 'gagal') {
    $pesan_error = 'Operasi pembaruan data pengguna gagal diproses.';
}

$filter_peran = isset($_GET['filter_peran']) ? (string)$_GET['filter_peran'] : '';

// =====================================================================
// PEMROSESAN AKSI MANAJEMEN PENGGUNA (POST PROSEDURAL DENGAN PRG PATTERN)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_admin'])) {
    $aksi = (string)$_POST['aksi_admin'];
    $redirect_url = 'admin.php?status=sukses';
    if ($filter_peran !== '') {
        $redirect_url .= '&filter_peran=' . urlencode($filter_peran);
    }

    // 1. Tambah Pengguna Baru
    if ($aksi === 'tambah') {
        $username     = (string)($_POST['username'] ?? '');
        $password     = (string)($_POST['password'] ?? '');
        $nama_lengkap = (string)($_POST['nama_lengkap'] ?? '');
        $peran        = (string)($_POST['peran'] ?? 'customer');

        $hasil = admin_tambah_pengguna($conn, $username, $password, $nama_lengkap, $peran);
        if ($hasil['sukses']) {
            header("Location: {$redirect_url}&pesan_sukses=" . urlencode($hasil['pesan']));
            exit;
        } else {
            header("Location: admin.php?status=gagal&pesan_error=" . urlencode($hasil['pesan']) . ($filter_peran !== '' ? '&filter_peran=' . urlencode($filter_peran) : ''));
            exit;
        }
    }

    // 2. Ubah Data Pengguna (Edit Data, Peran, & Password Baru)
    elseif ($aksi === 'ubah') {
        $id_pengguna   = (int)($_POST['id_pengguna'] ?? 0);
        $username      = (string)($_POST['username'] ?? '');
        $nama_lengkap  = (string)($_POST['nama_lengkap'] ?? '');
        $peran         = (string)($_POST['peran'] ?? 'customer');
        $password_baru = (string)($_POST['password_baru'] ?? '');

        $hasil = admin_ubah_pengguna($conn, $id_pengguna, $username, $nama_lengkap, $peran, $password_baru);
        if ($hasil['sukses']) {
            header("Location: {$redirect_url}&pesan_sukses=" . urlencode($hasil['pesan']));
            exit;
        } else {
            header("Location: admin.php?status=gagal&pesan_error=" . urlencode($hasil['pesan']) . ($filter_peran !== '' ? '&filter_peran=' . urlencode($filter_peran) : ''));
            exit;
        }
    }

    // 3. Ganti Peran Cepat (Inline Quick Role Change)
    elseif ($aksi === 'ganti_role_cepat') {
        $id_pengguna = (int)($_POST['id_pengguna'] ?? 0);
        $peran_baru  = (string)($_POST['peran_baru'] ?? '');

        $data_user = ambil_pengguna_by_id($conn, $id_pengguna);
        if ($data_user !== null) {
            $hasil = admin_ubah_pengguna($conn, $id_pengguna, (string)$data_user['username'], (string)$data_user['nama_lengkap'], $peran_baru, '');
            if ($hasil['sukses']) {
                $pesan = "Peran akun '@{$data_user['username']}' berhasil diubah menjadi '" . ucfirst($peran_baru) . "'!";
                header("Location: {$redirect_url}&pesan_sukses=" . urlencode($pesan));
                exit;
            } else {
                header("Location: admin.php?status=gagal&pesan_error=" . urlencode($hasil['pesan']) . ($filter_peran !== '' ? '&filter_peran=' . urlencode($filter_peran) : ''));
                exit;
            }
        } else {
            header("Location: admin.php?status=gagal&pesan_error=" . urlencode('Pengguna tidak ditemukan di database MySQL.') . ($filter_peran !== '' ? '&filter_peran=' . urlencode($filter_peran) : ''));
            exit;
        }
    }

    // 4. Reset Kata Sandi Cepat (Reset ke 'password123')
    elseif ($aksi === 'reset_password_default') {
        $id_pengguna = (int)($_POST['id_pengguna'] ?? 0);
        $data_user = ambil_pengguna_by_id($conn, $id_pengguna);

        if ($data_user !== null) {
            $hasil = admin_ubah_pengguna($conn, $id_pengguna, (string)$data_user['username'], (string)$data_user['nama_lengkap'], (string)$data_user['peran'], 'password123');
            if ($hasil['sukses']) {
                $pesan = "Kata sandi akun '@{$data_user['username']}' berhasil direset menjadi 'password123'!";
                header("Location: {$redirect_url}&pesan_sukses=" . urlencode($pesan));
                exit;
            } else {
                header("Location: admin.php?status=gagal&pesan_error=" . urlencode($hasil['pesan']) . ($filter_peran !== '' ? '&filter_peran=' . urlencode($filter_peran) : ''));
                exit;
            }
        } else {
            header("Location: admin.php?status=gagal&pesan_error=" . urlencode('Pengguna tidak ditemukan di database MySQL.') . ($filter_peran !== '' ? '&filter_peran=' . urlencode($filter_peran) : ''));
            exit;
        }
    }

    // 5. Hapus Pengguna
    elseif ($aksi === 'hapus') {
        $id_pengguna = (int)($_POST['id_pengguna'] ?? 0);

        $hasil = admin_hapus_pengguna($conn, $id_pengguna, $id_admin_aktif);
        if ($hasil['sukses']) {
            header("Location: {$redirect_url}&pesan_sukses=" . urlencode($hasil['pesan']));
            exit;
        } else {
            header("Location: admin.php?status=gagal&pesan_error=" . urlencode($hasil['pesan']) . ($filter_peran !== '' ? '&filter_peran=' . urlencode($filter_peran) : ''));
            exit;
        }
    }
}

// Ambil metrik statistik & rekap per peran
$statistik = hitung_statistik_admin($conn);
$rekap_peran = hitung_rekap_peran_pengguna($conn);

// Ambil daftar pengguna (dengan opsi filter peran)
$daftar_pengguna = ambil_semua_pengguna($conn, $filter_peran);

// Ambil riwayat pesanan (D2 JOIN D4)
$daftar_pesanan = ambil_semua_pesanan($conn, 30);

$judul_halaman = 'Panel Administrator - Executive Backoffice';
$tampilkan_stepper = false;

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Halaman Eksekutif -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-extrabold text-dark mb-1">
            <i class="bi bi-speedometer2 text-danger me-2"></i>Executive Backoffice AntarRasa
        </h3>
        <p class="text-muted small mb-0">
            Monitoring sentral seluruh ekosistem: Pengguna (D4), Menu (D1), dan Transaksi (D2 &amp; D3).
        </p>
    </div>
    <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
        <span class="badge bg-white text-secondary border px-3 py-2 font-monospace">
            <i class="bi bi-clock me-1 text-danger"></i> <?= date('d M Y, H:i') ?> WIB
        </span>
        <button type="button" class="btn btn-primary-custom btn-sm fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahPengguna">
            <i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna Baru
        </button>
    </div>
</div>

<!-- Alert Notifikasi Sukses / Gagal -->
<?php if ($pesan_sukses !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center shadow-sm rounded-4 mb-4" role="alert">
        <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
        <div><?= e($pesan_sukses) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($pesan_error !== ''): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center shadow-sm rounded-4 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-danger"></i>
        <div><?= e($pesan_error) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- 4 Kartu Metrik Ringkasan Sistem (Backoffice KPI Cards) -->
<div class="row g-3 mb-4">
    <!-- Kartu 1: Total Pengguna D4 -->
    <div class="col-sm-6 col-lg-3">
        <div class="admin-stat-card border-start border-4 border-danger">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Pengguna (D4)</span>
                    <h3 class="fw-extrabold text-dark mb-0 mt-1"><?= $statistik['total_pengguna'] ?></h3>
                </div>
                <div class="bg-danger-subtle text-danger p-3 rounded-circle">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
            </div>
            <div class="text-muted small mt-2" style="font-size: 0.78rem;">
                Customer, Merchant, Driver, Admin
            </div>
        </div>
    </div>

    <!-- Kartu 2: Total Menu Makanan D1 -->
    <div class="col-sm-6 col-lg-3">
        <div class="admin-stat-card border-start border-4 border-warning">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Menu (D1)</span>
                    <h3 class="fw-extrabold text-dark mb-0 mt-1"><?= $statistik['total_menu'] ?></h3>
                </div>
                <div class="bg-warning-subtle text-warning p-3 rounded-circle">
                    <i class="bi bi-egg-fried fs-4"></i>
                </div>
            </div>
            <div class="text-muted small mt-2" style="font-size: 0.78rem;">
                Katalog hidangan aktif
            </div>
        </div>
    </div>

    <!-- Kartu 3: Total Transaksi Pesanan D2 -->
    <div class="col-sm-6 col-lg-3">
        <div class="admin-stat-card border-start border-4 border-primary">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Pesanan (D2)</span>
                    <h3 class="fw-extrabold text-dark mb-0 mt-1"><?= $statistik['total_pesanan'] ?></h3>
                </div>
                <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                    <i class="bi bi-receipt fs-4"></i>
                </div>
            </div>
            <div class="text-muted small mt-2" style="font-size: 0.78rem;">
                Seluruh transaksi tersimpan
            </div>
        </div>
    </div>

    <!-- Kartu 4: Total Pendapatan Terverifikasi -->
    <div class="col-sm-6 col-lg-3">
        <div class="admin-stat-card border-start border-4 border-success">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Perputaran</span>
                    <h4 class="fw-extrabold text-success mb-0 mt-1 font-monospace" style="font-size: 1.35rem;">
                        <?= format_rupiah($statistik['total_pendapatan']) ?>
                    </h4>
                </div>
                <div class="bg-success-subtle text-success p-3 rounded-circle">
                    <i class="bi bi-cash-coin fs-4"></i>
                </div>
            </div>
            <div class="text-muted small mt-2" style="font-size: 0.78rem;">
                Pesanan lunas &amp; selesai
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     PANEL UTAMA: MANAJEMEN AKUN PENGGUNA (DATASTORE D4: PENGGUNA)
     ===================================================================== -->
<div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5 bg-white">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-people-fill text-danger me-2"></i>Manajemen Akun Pengguna (DataStore D4: PENGGUNA)
            </h5>
            <div class="text-muted small">Kelola akun, ubah peran secara instan, reset kata sandi default, dan hapus pengguna dengan proteksi FK.</div>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm fw-semibold rounded-pill" data-bs-toggle="modal" data-bs-target="#modalTambahPengguna">
            <i class="bi bi-plus-lg me-1"></i> Pengguna Baru
        </button>
    </div>

    <div class="card-body border-bottom bg-light py-2">
        <!-- Filter Tabs / Pills Berdasarkan Peran -->
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="small fw-bold text-muted me-1"><i class="bi bi-funnel me-1"></i>Filter Peran:</span>
            
            <a href="admin.php" class="btn btn-sm <?= $filter_peran === '' ? 'btn-dark' : 'btn-outline-secondary bg-white' ?> rounded-pill px-3">
                Semua Pengguna <span class="badge <?= $filter_peran === '' ? 'bg-secondary' : 'bg-light text-dark border' ?> ms-1"><?= $rekap_peran['semua'] ?></span>
            </a>
            
            <a href="admin.php?filter_peran=customer" class="btn btn-sm <?= $filter_peran === 'customer' ? 'btn-success' : 'btn-outline-success bg-white' ?> rounded-pill px-3">
                <i class="bi bi-person-check me-1"></i>Customer <span class="badge <?= $filter_peran === 'customer' ? 'bg-light text-success' : 'bg-success-subtle text-success' ?> ms-1"><?= $rekap_peran['customer'] ?></span>
            </a>

            <a href="admin.php?filter_peran=merchant" class="btn btn-sm <?= $filter_peran === 'merchant' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark bg-white' ?> rounded-pill px-3">
                <i class="bi bi-shop me-1"></i>Merchant <span class="badge <?= $filter_peran === 'merchant' ? 'bg-light text-dark' : 'bg-warning-subtle text-dark' ?> ms-1"><?= $rekap_peran['merchant'] ?></span>
            </a>

            <a href="admin.php?filter_peran=driver" class="btn btn-sm <?= $filter_peran === 'driver' ? 'btn-info text-dark' : 'btn-outline-info text-dark bg-white' ?> rounded-pill px-3">
                <i class="bi bi-bicycle me-1"></i>Driver <span class="badge <?= $filter_peran === 'driver' ? 'bg-light text-dark' : 'bg-info-subtle text-dark' ?> ms-1"><?= $rekap_peran['driver'] ?></span>
            </a>

            <a href="admin.php?filter_peran=admin" class="btn btn-sm <?= $filter_peran === 'admin' ? 'btn-danger' : 'btn-outline-danger bg-white' ?> rounded-pill px-3">
                <i class="bi bi-shield-shaded me-1"></i>Admin <span class="badge <?= $filter_peran === 'admin' ? 'bg-light text-danger' : 'bg-danger-subtle text-danger' ?> ms-1"><?= $rekap_peran['admin'] ?></span>
            </a>
        </div>
    </div>

    <!-- Tabel Interaktif Daftar Pengguna -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th class="ps-3" style="width: 70px;">ID</th>
                    <th style="min-width: 200px;">Username &amp; Identitas</th>
                    <th style="min-width: 180px;">Nama Lengkap / Mitra</th>
                    <th class="text-center" style="min-width: 130px;">Peran (Role)</th>
                    <th class="text-center" style="min-width: 180px;">Ganti Peran Cepat</th>
                    <th class="text-center pe-3" style="min-width: 220px;">Aksi Manajemen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftar_pengguna)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="bi bi-person-x fs-3 d-block mb-1 text-secondary"></i>
                            Tidak ada akun pengguna yang sesuai dengan filter peran <strong>'<?= e($filter_peran) ?>'</strong>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftar_pengguna as $u): ?>
                        <?php 
                            $id_u = (int)$u['id_pengguna'];
                            $peran_u = (string)$u['peran'];
                            $inf_peran = dapatkan_info_peran($peran_u);
                            $is_akun_sendiri = ($id_u === $id_admin_aktif);
                        ?>
                        <tr class="<?= $is_akun_sendiri ? 'table-warning-subtle' : '' ?>">
                            <td class="ps-3 font-monospace fw-bold text-muted small">#<?= $id_u ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-light p-2 me-2 border text-center d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                        <i class="bi <?= $inf_peran['icon'] ?> text-dark"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">
                                            @<?= e($u['username']) ?>
                                            <?php if ($is_akun_sendiri): ?>
                                                <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.65rem;">Akun Anda</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.72rem;">DataStore D4: PENGGUNA</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($u['nama_lengkap']) ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $inf_peran['class'] ?> px-2 py-1 shadow-sm rounded-pill" style="font-size: 0.75rem;">
                                    <i class="bi <?= $inf_peran['icon'] ?> me-1"></i><?= e(ucfirst($peran_u)) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <!-- Form Ubah Peran Cepat -->
                                <form method="POST" action="admin.php" class="d-inline-flex align-items-center gap-1">
                                    <input type="hidden" name="aksi_admin" value="ganti_role_cepat">
                                    <input type="hidden" name="id_pengguna" value="<?= $id_u ?>">
                                    <select name="peran_baru" class="form-select form-select-sm rounded-pill" style="font-size: 0.78rem;" onchange="this.form.submit()">
                                        <option value="customer" <?= $peran_u === 'customer' ? 'selected' : '' ?>>Customer</option>
                                        <option value="merchant" <?= $peran_u === 'merchant' ? 'selected' : '' ?>>Merchant</option>
                                        <option value="driver" <?= $peran_u === 'driver' ? 'selected' : '' ?>>Driver</option>
                                        <option value="admin" <?= $peran_u === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </form>
                            </td>
                            <td class="text-center pe-3">
                                <div class="d-inline-flex gap-1 align-items-center">
                                    <!-- Tombol Edit (Membuka Modal Edit) -->
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-primary rounded-pill btn-edit-pengguna" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modalEditPengguna"
                                        data-id="<?= $id_u ?>"
                                        data-username="<?= e($u['username']) ?>"
                                        data-nama="<?= e($u['nama_lengkap']) ?>"
                                        data-peran="<?= e($peran_u) ?>"
                                        title="Edit Data &amp; Password">
                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                    </button>

                                    <!-- Tombol Reset Password Cepat -->
                                    <form method="POST" action="admin.php" class="d-inline" onsubmit="return confirm('Reset kata sandi pengguna @<?= addslashes(e($u['username'])) ?> menjadi \'password123\'?');">
                                        <input type="hidden" name="aksi_admin" value="reset_password_default">
                                        <input type="hidden" name="id_pengguna" value="<?= $id_u ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning text-dark rounded-circle" title="Reset kata sandi ke password123">
                                            <i class="bi bi-key"></i>
                                        </button>
                                    </form>

                                    <!-- Tombol Hapus Pengguna -->
                                    <?php if ($is_akun_sendiri): ?>
                                        <button class="btn btn-sm btn-secondary disabled opacity-50 rounded-circle" disabled title="Akun aktif Anda tidak dapat dihapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" action="admin.php" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun @<?= addslashes(e($u['username'])) ?> (ID #<?= $id_u ?>) secara permanen?');">
                                            <input type="hidden" name="aksi_admin" value="hapus">
                                            <input type="hidden" name="id_pengguna" value="<?= $id_u ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Hapus Akun Pengguna">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- =====================================================================
     BAGIAN 2: MONITORING SELURUH TRANSAKSI PESANAN (D2 JOIN D4)
     ===================================================================== -->
<div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4 bg-white" id="tabelSemuaPesanan">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-receipt-cutoff text-primary me-2"></i>Monitoring Transaksi Pesanan (DataStore D2 JOIN D4)
            </h5>
            <div class="text-muted small">Memantau seluruh transaksi pemesanan yang terhubung dengan akun pengguna</div>
        </div>
        <span class="badge bg-light text-dark border"><?= count($daftar_pesanan) ?> Transaksi</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th class="ps-3" style="width: 90px;">No. Order</th>
                    <th>Pemesan Terdaftar (D4)</th>
                    <th>Waktu &amp; Alamat Tujuan</th>
                    <th class="text-end">Total Biaya</th>
                    <th class="text-center">Status Pesanan</th>
                    <th class="text-center pe-3">Aksi Lacak</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftar_pesanan)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted small">Belum ada pesanan yang tercatat di database.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftar_pesanan as $o): ?>
                        <?php 
                            $id_ord = (int)$o['id_pesanan'];
                            $st_info = dapatkan_info_status((string)$o['status_pesanan']);
                        ?>
                        <tr>
                            <td class="ps-3 font-monospace fw-bold text-primary">#<?= $id_ord ?></td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <?= e($o['nama_pemesan'] ?? 'Tamu (Guest)') ?>
                                </div>
                                <?php if (!empty($o['username_pemesan'])): ?>
                                    <span class="text-muted small font-monospace">@<?= e($o['username_pemesan']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border" style="font-size: 0.65rem;">Tanpa Akun</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small text-secondary text-truncate" style="max-width: 260px;" title="<?= e($o['alamat_pengiriman']) ?>">
                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($o['alamat_pengiriman']) ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.72rem;">
                                    <?= date('d M Y, H:i', strtotime((string)$o['tanggal_pesanan'])) ?> WIB
                                </div>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark">
                                <?= format_rupiah((float)$o['total_harga']) ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $st_info['class'] ?> px-2 py-1 rounded-pill" style="font-size: 0.75rem;">
                                    <i class="bi <?= $st_info['icon'] ?> me-1"></i><?= e($o['status_pesanan']) ?>
                                </span>
                            </td>
                            <td class="text-center pe-3">
                                <a href="status.php?id=<?= $id_ord ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3" title="Lacak Rincian">
                                    <i class="bi bi-compass me-1"></i> Lacak
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- =====================================================================
     MODAL 1: TAMBAH PENGGUNA BARU
     ===================================================================== -->
<div class="modal fade" id="modalTambahPengguna" tabindex="-1" aria-labelledby="modalTambahPenggunaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-danger text-white rounded-top-4 py-3">
                <h5 class="modal-title fw-bold" id="modalTambahPenggunaLabel">
                    <i class="bi bi-person-plus-fill me-2"></i>Tambah Pengguna Baru (D4)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="admin.php">
                <input type="hidden" name="aksi_admin" value="tambah">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="tambah_username" class="form-label small fw-semibold text-muted text-uppercase">Nama Pengguna (Username) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-at"></i></span>
                            <input type="text" class="form-control" id="tambah_username" name="username" placeholder="Masukkan username unik..." required>
                        </div>
                        <div class="form-text small">Hanya huruf, angka, dan garis bawah (_), min. 3 karakter.</div>
                    </div>

                    <div class="mb-3">
                        <label for="tambah_nama" class="form-label small fw-semibold text-muted text-uppercase">Nama Lengkap / Nama Mitra <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="tambah_nama" name="nama_lengkap" placeholder="Masukkan nama lengkap pengguna atau resto..." required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="tambah_peran" class="form-label small fw-semibold text-muted text-uppercase">Peran Akun (Role) <span class="text-danger">*</span></label>
                        <select class="form-select" id="tambah_peran" name="peran" required>
                            <option value="customer" selected>Customer (Pelanggan Pemesan)</option>
                            <option value="merchant">Merchant (Pemilik Restoran/Menu)</option>
                            <option value="driver">Driver (Kurir Pengantar)</option>
                            <option value="admin">Administrator (Pusat Kontrol)</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="tambah_password" class="form-label small fw-semibold text-muted text-uppercase">Kata Sandi Awal <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control" id="tambah_password" name="password" placeholder="Minimal 6 karakter" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4 py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-custom btn-sm fw-bold">
                        <i class="bi bi-save2 me-1"></i> Simpan Pengguna Baru
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =====================================================================
     MODAL 2: EDIT PENGGUNA (UBAH DATA, PERAN, & RESET PASSWORD)
     ===================================================================== -->
<div class="modal fade" id="modalEditPengguna" tabindex="-1" aria-labelledby="modalEditPenggunaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white rounded-top-4 py-3">
                <h5 class="modal-title fw-bold" id="modalEditPenggunaLabel">
                    <i class="bi bi-pencil-square me-2"></i>Edit Pengguna &amp; Peran
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="admin.php">
                <input type="hidden" name="aksi_admin" value="ubah">
                <input type="hidden" name="id_pengguna" id="edit_id_pengguna" value="">

                <div class="modal-body p-4">
                    <div class="alert alert-secondary py-2 px-3 small mb-3 rounded-3">
                        Mengubah data akun ID: <strong id="edit_label_id">#</strong>
                    </div>

                    <div class="mb-3">
                        <label for="edit_username" class="form-label small fw-semibold text-muted text-uppercase">Nama Pengguna (Username) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-at"></i></span>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_nama" class="form-label small fw-semibold text-muted text-uppercase">Nama Lengkap / Nama Mitra <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="edit_nama" name="nama_lengkap" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_peran" class="form-label small fw-semibold text-muted text-uppercase">Peran Akun (Role) <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_peran" name="peran" required>
                            <option value="customer">Customer (Pelanggan Pemesan)</option>
                            <option value="merchant">Merchant (Pemilik Restoran/Menu)</option>
                            <option value="driver">Driver (Kurir Pengantar)</option>
                            <option value="admin">Administrator (Pusat Kontrol)</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="edit_password" class="form-label small fw-semibold text-muted text-uppercase">Reset Kata Sandi Baru</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="edit_password" name="password_baru" placeholder="Masukkan password baru">
                        </div>
                        <div class="form-text small text-muted">
                            <i class="bi bi-info-circle me-1"></i>Kosongkan jika tidak ingin mengubah password akun ini.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4 py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script Interaktif untuk Memuat Data Baris ke Modal Edit -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editButtons = document.querySelectorAll('.btn-edit-pengguna');
    const inputId = document.getElementById('edit_id_pengguna');
    const labelId = document.getElementById('edit_label_id');
    const inputUsername = document.getElementById('edit_username');
    const inputNama = document.getElementById('edit_nama');
    const selectPeran = document.getElementById('edit_peran');
    const inputPassword = document.getElementById('edit_password');

    editButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const username = this.getAttribute('data-username');
            const nama = this.getAttribute('data-nama');
            const peran = this.getAttribute('data-peran');

            if (inputId) inputId.value = id;
            if (labelId) labelId.textContent = '#' + id + ' (' + username + ')';
            if (inputUsername) inputUsername.value = username;
            if (inputNama) inputNama.value = nama;
            if (selectPeran) selectPeran.value = peran;
            if (inputPassword) inputPassword.value = ''; // Reset input password baru
        });
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
