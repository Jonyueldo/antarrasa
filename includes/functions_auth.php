<?php
declare(strict_types=1);

/**
 * Modul Fungsi Autentikasi & Otorisasi Pengguna (Murni Prosedural)
 * Spesifikasi: SRS IEEE Std 830-1998 (Opsi B: 4 DataStore - D4: PENGGUNA)
 *
 * Aturan PHP 8:
 * - declare(strict_types=1) aktif
 * - Parameter type hint & return type declaration pada seluruh fungsi
 * - Tanpa class / object / inheritance
 * - Menggunakan prepared statement mysqli_* prosedural
 * - 100% Membaca & Menulis Langsung ke Basis Data MySQL
 */

// Pastikan session aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Muat modul fungsi manajemen pengguna admin
require_once __DIR__ . '/functions_admin.php';

/**
 * Inisialisasi Skema Tabel PENGGUNA (DataStore D4) & Relasi Foreign Key di PESANAN (D2)
 *
 * CATATAN PENTING:
 * HANYA melakukan INSERT akun awal JIKA tabel PENGGUNA benar-benar KOSONG (0 baris).
 * TIDAK PERNAH melakukan pengecekan per-username atau re-seed otomatis pada request biasa,
 * sehingga akun yang dihapus atau diubah oleh Admin akan tersimpan PERMANEN di MySQL.
 *
 * @param mysqli $conn
 * @return void
 */
function inisialisasi_tabel_pengguna($conn): void
{
    // 1. Buat tabel PENGGUNA jika belum ada sama sekali di MySQL
    $sql_tabel_pengguna = "
    CREATE TABLE IF NOT EXISTS `PENGGUNA` (
        `id_pengguna` INT NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `nama_lengkap` VARCHAR(100) NOT NULL,
        `peran` VARCHAR(20) NOT NULL DEFAULT 'customer',
        PRIMARY KEY (`id_pengguna`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    mysqli_query($conn, $sql_tabel_pengguna);

    // 2. Periksa apakah kolom id_pengguna ada di tabel PESANAN dan izinkan bernilai NULL
    $cek_kolom = mysqli_query($conn, "SHOW COLUMNS FROM `PESANAN` LIKE 'id_pengguna'");
    if ($cek_kolom !== false) {
        if (mysqli_num_rows($cek_kolom) === 0) {
            // Tambahkan kolom id_pengguna ke tabel PESANAN
            mysqli_query($conn, "ALTER TABLE `PESANAN` ADD COLUMN `id_pengguna` INT NULL DEFAULT NULL AFTER `id_pesanan`");
            mysqli_query($conn, "ALTER TABLE `PESANAN` ADD CONSTRAINT `fk_pesanan_pengguna` FOREIGN KEY (`id_pengguna`) REFERENCES `PENGGUNA` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE");
        } else {
            // Pastikan kolom id_pengguna boleh NULL agar penghapusan user tidak terblokir
            mysqli_query($conn, "ALTER TABLE `PESANAN` MODIFY COLUMN `id_pengguna` INT NULL DEFAULT NULL");
        }
        mysqli_free_result($cek_kolom);
    }

    // 3. SEEDING AKUN AWAL:
    // HANYA jalankan JIKA tabel PENGGUNA benar-benar kosong melompong (COUNT = 0).
    // Jika tabel sudah memiliki 1 akun atau lebih, JANGAN PERNAH menyuntikkan data lagi!
    $cek_isi = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `PENGGUNA`");
    if ($cek_isi !== false) {
        $row = mysqli_fetch_assoc($cek_isi);
        $total_akun = (int)($row['total'] ?? 0);
        mysqli_free_result($cek_isi);

        if ($total_akun === 0) {
            $password_default_hash = password_hash('password123', PASSWORD_DEFAULT);
            $akun_default = [
                ['username' => 'customer', 'nama_lengkap' => 'Thiroz',                  'peran' => 'customer'],
                ['username' => 'merchant', 'nama_lengkap' => 'Dapur Raden (Merchant)',  'peran' => 'merchant'],
                ['username' => 'driver',   'nama_lengkap' => 'Samudera',                'peran' => 'driver'],
                ['username' => 'admin',    'nama_lengkap' => 'Administrator AntarRasa', 'peran' => 'admin'],
            ];

            $sql_insert = "INSERT INTO `PENGGUNA` (`username`, `password`, `nama_lengkap`, `peran`) VALUES (?, ?, ?, ?)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            if ($stmt_insert) {
                foreach ($akun_default as $akun) {
                    mysqli_stmt_bind_param($stmt_insert, "ssss", $akun['username'], $password_default_hash, $akun['nama_lengkap'], $akun['peran']);
                    mysqli_stmt_execute($stmt_insert);
                }
                mysqli_stmt_close($stmt_insert);
            }
        }
    }
}

/**
 * Mengambil data pengguna yang sedang login dari session
 *
 * @return array|null Asosiatif data pengguna atau null jika belum login
 */
function ambil_pengguna_login(): ?array
{
    if (isset($_SESSION['pengguna']) && is_array($_SESSION['pengguna'])) {
        return $_SESSION['pengguna'];
    }
    return null;
}

/**
 * Memeriksa apakah ada pengguna yang sedang login
 *
 * @return bool
 */
function apakah_login(): bool
{
    return ambil_pengguna_login() !== null;
}

/**
 * Mengambil peran pengguna yang sedang login
 *
 * @return string|null 'customer', 'merchant', 'driver', 'admin', atau null
 */
function peran_pengguna_saat_ini(): ?string
{
    $pengguna = ambil_pengguna_login();
    return $pengguna['peran'] ?? null;
}

/**
 * Pemeriksa Hak Akses Berdasarkan Peran (Role-Based Access Control)
 * Mengalihkan halaman jika pengguna belum login atau peran tidak cocok.
 *
 * @param array $peran_diizinkan Daftar peran yang memiliki izin, misal: ['admin']
 * @param string $redirect_ke URL tujuan pengalihan jika tidak diizinkan
 * @return void
 */
function cek_akses(array $peran_diizinkan, string $redirect_ke = 'login.php'): void
{
    if (!apakah_login()) {
        $url_asal = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: {$redirect_ke}?pesan=wajib_login&ref={$url_asal}");
        exit;
    }

    $peran_aktif = peran_pengguna_saat_ini();
    if ($peran_aktif === null || !in_array($peran_aktif, $peran_diizinkan, true)) {
        header("Location: login.php?pesan=akses_ditolak");
        exit;
    }
}

/**
 * Memproses login pengguna dengan validasi langsung ke tabel MySQL PENGGUNA (D4)
 * (Tanpa array statis/hardcoded)
 *
 * @param mysqli $conn
 * @param string $username
 * @param string $password
 * @return array|false Mengembalikan data pengguna jika sukses, false jika gagal
 */
function login_pengguna($conn, string $username, string $password): array|false
{
    $username_bersih = trim($username);
    if ($username_bersih === '' || $password === '') {
        return false;
    }

    $sql = "SELECT `id_pengguna`, `username`, `password`, `nama_lengkap`, `peran` FROM `PENGGUNA` WHERE `username` = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "s", $username_bersih);
    mysqli_stmt_execute($stmt);

    $hasil = mysqli_stmt_get_result($stmt);
    if ($hasil === false || mysqli_num_rows($hasil) === 0) {
        mysqli_stmt_close($stmt);
        return false;
    }

    $user = mysqli_fetch_assoc($hasil);
    mysqli_free_result($hasil);
    mysqli_stmt_close($stmt);

    // Verifikasi hash password (atau kecocokan langsung jika diset manual)
    $password_cocok = password_verify($password, (string) $user['password']) || ($password === $user['password']);

    if (!$password_cocok) {
        return false;
    }

    // Buat session login
    $_SESSION['id_pengguna']  = (int) $user['id_pengguna'];
    $_SESSION['username']     = (string) $user['username'];
    $_SESSION['nama_lengkap'] = (string) $user['nama_lengkap'];
    $_SESSION['peran']        = (string) $user['peran'];
    $_SESSION['pengguna']     = [
        'id_pengguna'  => (int) $user['id_pengguna'],
        'username'     => (string) $user['username'],
        'nama_lengkap' => (string) $user['nama_lengkap'],
        'peran'        => (string) $user['peran'],
    ];

    return $_SESSION['pengguna'];
}

/**
 * Alias fungsi proses_login untuk kompatibilitas penuh kueri autentikasi langsung ke MySQL
 *
 * @param mysqli $conn
 * @param string $username
 * @param string $password
 * @return array|false
 */
function proses_login($conn, string $username, string $password): array|false
{
    return login_pengguna($conn, $username, $password);
}

/**
 * Registrasi akun customer baru langsung ke tabel MySQL PENGGUNA (DataStore D4)
 *
 * @param mysqli $conn
 * @param string $username
 * @param string $password
 * @param string $nama_lengkap
 * @return array ['sukses' => bool, 'pesan' => string, 'id_pengguna' => int|null]
 */
function registrasi_customer($conn, string $username, string $password, string $nama_lengkap): array
{
    $username_bersih = trim($username);
    $nama_bersih     = trim($nama_lengkap);

    if (mb_strlen($username_bersih) < 4) {
        return ['sukses' => false, 'pesan' => 'Username minimal 4 karakter (huruf, angka, atau underscore).', 'id_pengguna' => null];
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username_bersih)) {
        return ['sukses' => false, 'pesan' => 'Username hanya boleh memuat huruf, angka, dan garis bawah (_).', 'id_pengguna' => null];
    }

    if (mb_strlen($nama_bersih) < 3) {
        return ['sukses' => false, 'pesan' => 'Nama lengkap wajib diisi minimal 3 karakter.', 'id_pengguna' => null];
    }

    if (mb_strlen($password) < 6) {
        return ['sukses' => false, 'pesan' => 'Kata sandi minimal 6 karakter demi keamanan akun Anda.', 'id_pengguna' => null];
    }

    // Periksa apakah username sudah digunakan di MySQL
    $sql_cek = "SELECT `id_pengguna` FROM `PENGGUNA` WHERE `username` = ? LIMIT 1";
    $stmt_cek = mysqli_prepare($conn, $sql_cek);
    if (!$stmt_cek) {
        return ['sukses' => false, 'pesan' => 'Gagal mempersiapkan kueri verifikasi username.', 'id_pengguna' => null];
    }

    mysqli_stmt_bind_param($stmt_cek, "s", $username_bersih);
    mysqli_stmt_execute($stmt_cek);
    $res = mysqli_stmt_get_result($stmt_cek);

    if ($res !== false && mysqli_num_rows($res) > 0) {
        mysqli_free_result($res);
        mysqli_stmt_close($stmt_cek);
        return ['sukses' => false, 'pesan' => "Username '{$username_bersih}' sudah terdaftar. Silakan pilih username lain.", 'id_pengguna' => null];
    }
    if ($res !== false) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt_cek);

    // Hash password secara aman
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $peran = 'customer';

    $sql_insert = "INSERT INTO `PENGGUNA` (`username`, `password`, `nama_lengkap`, `peran`) VALUES (?, ?, ?, ?)";
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    if (!$stmt_insert) {
        return ['sukses' => false, 'pesan' => 'Gagal mempersiapkan kueri registrasi pengguna.', 'id_pengguna' => null];
    }

    mysqli_stmt_bind_param($stmt_insert, "ssss", $username_bersih, $password_hash, $nama_bersih, $peran);
    $sukses = mysqli_stmt_execute($stmt_insert);

    if (!$sukses) {
        mysqli_stmt_close($stmt_insert);
        return ['sukses' => false, 'pesan' => 'Terjadi kesalahan sistem saat menyimpan data pengguna ke MySQL.', 'id_pengguna' => null];
    }

    $id_baru = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt_insert);

    return [
        'sukses'      => true,
        'pesan'       => 'Pendaftaran akun customer berhasil disimpan ke MySQL!',
        'id_pengguna' => $id_baru,
    ];
}

/**
 * Mengeluarkan pengguna dari sesi (Logout)
 *
 * @return void
 */
function logout_pengguna(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    unset($_SESSION['id_pengguna']);
    unset($_SESSION['username']);
    unset($_SESSION['nama_lengkap']);
    unset($_SESSION['peran']);
    unset($_SESSION['pengguna']);
}

/**
 * Mendapatkan konfigurasi visual warna badge peran pengguna
 *
 * @param string $peran
 * @return array ['class' => string, 'label' => string, 'icon' => string]
 */
function dapatkan_info_peran(string $peran): array
{
    return match (mb_strtolower($peran)) {
        'admin' => [
            'class' => 'bg-danger text-white',
            'badge' => 'danger',
            'label' => 'Administrator',
            'icon'  => 'bi-shield-shaded'
        ],
        'merchant' => [
            'class' => 'bg-warning text-dark',
            'badge' => 'warning',
            'label' => 'Merchant / Restoran',
            'icon'  => 'bi-shop'
        ],
        'driver' => [
            'class' => 'bg-info text-dark',
            'badge' => 'info',
            'label' => 'Driver / Kurir',
            'icon'  => 'bi-bicycle'
        ],
        default => [
            'class' => 'bg-success text-white',
            'badge' => 'success',
            'label' => 'Pelanggan / Customer',
            'icon'  => 'bi-person-check'
        ]
    };
}
