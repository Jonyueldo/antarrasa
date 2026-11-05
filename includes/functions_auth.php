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

// Pastikan session aktif lewat satu pintu: mulai_sesi() di functions_order.php
require_once __DIR__ . '/functions_order.php';
mulai_sesi();

// Muat modul fungsi manajemen pengguna admin
require_once __DIR__ . '/functions_admin.php';

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

// =====================================================================
// PROTEKSI CSRF (Cross-Site Request Forgery)
// =====================================================================

/**
 * Mengambil token CSRF milik sesi aktif (dibuat sekali per sesi).
 *
 * @return string
 */
function token_csrf(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Membuat elemen input hidden berisi token CSRF untuk disisipkan di setiap form POST.
 *
 * @return string
 */
function field_csrf(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(token_csrf(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Memvalidasi token CSRF yang dikirim lewat POST terhadap token di session.
 *
 * @return bool
 */
function cek_csrf(): bool
{
    $token_sesi = $_SESSION['csrf_token'] ?? '';
    $token_kirim = $_POST['csrf_token'] ?? '';

    return is_string($token_sesi) && is_string($token_kirim)
        && $token_sesi !== '' && hash_equals($token_sesi, $token_kirim);
}

/**
 * Gerbang CSRF: dipanggil di awal setiap halaman SEBELUM handler POST mana pun.
 * Request POST tanpa token valid dihentikan dengan HTTP 403.
 *
 * @return void
 */
function wajib_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || cek_csrf()) {
        return;
    }

    http_response_code(403);
    $kembali = htmlspecialchars(basename((string) parse_url((string) ($_SERVER['PHP_SELF'] ?? 'index.php'), PHP_URL_PATH)), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Permintaan Ditolak - AntarRasa</title></head>'
        . '<body style="font-family:sans-serif;padding:2rem;">'
        . '<h3>Permintaan Ditolak</h3>'
        . '<p>Token keamanan formulir tidak valid atau sesi Anda telah berakhir. Silakan muat ulang halaman lalu kirim ulang formulir.</p>'
        . '<p><a href="' . $kembali . '">Kembali ke halaman sebelumnya</a></p>'
        . '</body></html>';
    exit;
}

/**
 * Membersihkan parameter tujuan redirect (ref) setelah login agar tidak terjadi open redirect.
 * Hanya nama file .php lokal pada daftar putih yang diizinkan, misalnya
 * '/antarrasa/riwayat.php?id=3' menjadi 'riwayat.php?id=3'. Selain itu dikembalikan ''.
 *
 * @param string $ref
 * @return string
 */
function bersihkan_ref(string $ref): string
{
    $ref = trim($ref);

    // Tolak URL protocol-relative (//host), skema (http:, javascript:), backslash, dan karakter kontrol
    if ($ref === '' || str_starts_with($ref, '//') || str_contains($ref, ':')
        || str_contains($ref, '\\') || preg_match('/[\x00-\x1F\x7F]/', $ref)) {
        return '';
    }

    $halaman_diizinkan = [
        'index.php', 'pesanan.php', 'pembayaran.php', 'status.php',
        'riwayat.php', 'merchant.php', 'driver.php', 'admin.php', 'laporan.php',
    ];

    $path = (string) parse_url($ref, PHP_URL_PATH);
    $nama_file = basename($path);
    if (!in_array($nama_file, $halaman_diizinkan, true)) {
        return '';
    }

    $query = (string) parse_url($ref, PHP_URL_QUERY);
    if ($query !== '' && preg_match('/^[A-Za-z0-9_\-.=&%+]+$/', $query)) {
        return $nama_file . '?' . $query;
    }

    return $nama_file;
}

// =====================================================================
// RATE LIMITING LOGIN (tanpa tabel baru: session + berkas per-IP di folder temp)
// =====================================================================

/** Maksimal percobaan gagal dalam satu jendela waktu sebelum dikunci */
const BATAS_GAGAL_LOGIN = 5;
/** Jendela penghitungan percobaan gagal (detik) */
const JENDELA_GAGAL_LOGIN = 900;
/** Lama penguncian setelah melewati batas (detik) */
const DURASI_KUNCI_LOGIN = 300;

/**
 * Lokasi berkas penghitung per-IP. Disimpan per IP (di-hash) agar penyerang yang
 * membuang cookie sesi tetap terkena pembatasan.
 *
 * @return string
 */
function path_catatan_login_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');

    // Folder session pasti bisa ditulis PHP (sesi berjalan di sana); format "N;/path" ditangani
    $folder = (string) ini_get('session.save_path');
    if (str_contains($folder, ';')) {
        $folder = substr($folder, (int) strrpos($folder, ';') + 1);
    }
    if ($folder === '' || !is_dir($folder) || !is_writable($folder)) {
        $folder = sys_get_temp_dir();
    }

    return rtrim($folder, '\\/') . DIRECTORY_SEPARATOR . 'antarrasa_login_' . hash('sha256', $ip) . '.json';
}

/**
 * Membaca & (opsional) memperbarui catatan percobaan login secara atomik (flock).
 *
 * @param callable|null $ubah fn(array $data): array untuk memperbarui catatan; null = hanya baca
 * @return array ['jumlah' => int, 'awal' => int, 'kunci_sampai' => int]
 */
function akses_catatan_login_ip(?callable $ubah = null): array
{
    $kosong = ['jumlah' => 0, 'awal' => time(), 'kunci_sampai' => 0];
    $berkas = @fopen(path_catatan_login_ip(), 'c+');
    if ($berkas === false) {
        return $kosong;
    }

    flock($berkas, LOCK_EX);
    $isi = stream_get_contents($berkas);
    $data = is_string($isi) && $isi !== '' ? json_decode($isi, true) : null;
    $data = is_array($data) ? array_merge($kosong, $data) : $kosong;

    if ($ubah !== null) {
        $data = $ubah($data);
        ftruncate($berkas, 0);
        rewind($berkas);
        fwrite($berkas, (string) json_encode($data));
        fflush($berkas);
    }

    flock($berkas, LOCK_UN);
    fclose($berkas);
    return $data;
}

/**
 * Mengembalikan sisa detik penguncian login (0 = boleh mencoba login).
 * Dicek dari session DAN catatan per-IP; diambil yang paling lama.
 *
 * @return int
 */
function sisa_kunci_login(): int
{
    $sekarang = time();
    $kunci_sesi = (int) ($_SESSION['login_gagal']['kunci_sampai'] ?? 0);
    $kunci_ip = (int) (akses_catatan_login_ip()['kunci_sampai'] ?? 0);
    return max(0, max($kunci_sesi, $kunci_ip) - $sekarang);
}

/**
 * Mencatat satu percobaan login gagal (session + per-IP) lalu memberi jeda
 * yang makin panjang untuk memperlambat brute force.
 *
 * @return void
 */
function catat_gagal_login(): void
{
    $perbarui = static function (array $data): array {
        $sekarang = time();
        // Jendela kedaluwarsa: mulai hitungan baru
        if ($sekarang - (int) $data['awal'] > JENDELA_GAGAL_LOGIN) {
            $data = ['jumlah' => 0, 'awal' => $sekarang, 'kunci_sampai' => 0];
        }
        $data['jumlah'] = (int) $data['jumlah'] + 1;
        if ($data['jumlah'] >= BATAS_GAGAL_LOGIN) {
            $data['kunci_sampai'] = $sekarang + DURASI_KUNCI_LOGIN;
            $data['jumlah'] = 0;
            $data['awal'] = $sekarang;
        }
        return $data;
    };

    $sesi = is_array($_SESSION['login_gagal'] ?? null) ? $_SESSION['login_gagal'] : ['jumlah' => 0, 'awal' => time(), 'kunci_sampai' => 0];
    $_SESSION['login_gagal'] = $perbarui($sesi);
    $data_ip = akses_catatan_login_ip($perbarui);

    // Jeda bertahap 0,5 s; 1 s; 1,5 s; maksimal 2 s
    $tingkat = max((int) $_SESSION['login_gagal']['jumlah'], (int) $data_ip['jumlah'], 1);
    usleep(min($tingkat, 4) * 500000);
}

/**
 * Menghapus catatan percobaan gagal setelah login berhasil.
 *
 * @return void
 */
function reset_gagal_login(): void
{
    unset($_SESSION['login_gagal']);
    akses_catatan_login_ip(static fn(array $data): array => ['jumlah' => 0, 'awal' => time(), 'kunci_sampai' => 0]);
}

/**
 * Memeriksa apakah fitur demo (quick login & panel simulasi status) boleh aktif.
 * Bernilai false jika konstanta MODE_DEMO tidak didefinisikan di config/database.php.
 *
 * @return bool
 */
function mode_demo_aktif(): bool
{
    return defined('MODE_DEMO') && constant('MODE_DEMO') === true;
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

    $sql = "SELECT `id_pengguna`, `username`, `password`, `nama_lengkap`, `peran` FROM `pengguna` WHERE `username` = ? LIMIT 1";
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

    // Verifikasi hanya lewat hash; perbandingan plaintext dilarang karena meloloskan
    // siapa pun yang mengetahui isi kolom password (misalnya dari dump database)
    if (!password_verify($password, (string) $user['password'])) {
        return false;
    }

    // Cegah session fixation: ganti ID sesi dan token CSRF setiap kali login
    // (registrasi_customer juga melewati fungsi ini saat auto-login)
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);

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
    $sql_cek = "SELECT `id_pengguna` FROM `pengguna` WHERE `username` = ? LIMIT 1";
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

    $sql_insert = "INSERT INTO `pengguna` (`username`, `password`, `nama_lengkap`, `peran`) VALUES (?, ?, ?, ?)";
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
    mulai_sesi();

    // Kosongkan seluruh data sesi, hapus cookie, lalu musnahkan sesi di server
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $param = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $param['path'],
            'domain'   => $param['domain'],
            'secure'   => $param['secure'],
            'httponly' => $param['httponly'],
            'samesite' => $param['samesite'] ?: 'Lax',
        ]);
    }

    session_destroy();
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
