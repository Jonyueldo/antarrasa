<?php
declare(strict_types=1);

// Fungsi bantu tunggal: sesi, CSRF, query, keranjang, dan format.

// Menu yang tampil di katalog: versi aktifnya sudah disetujui admin.
const MENU_TAMPIL = "status_moderasi IN ('disetujui', 'menunggu_ubah', 'menunggu_hapus')";

const LABEL_STATUS = [
    'menunggu_driver' => ['Menunggu Driver', 'warning text-dark'],
    'menuju_merchant' => ['Driver Menuju Merchant', 'info text-dark'],
    'diantar'         => ['Sedang Diantar', 'primary'],
    'selesai'         => ['Selesai', 'success'],
    'ditolak_driver'  => ['Ditolak Driver', 'secondary'],
];

function e(mixed $nilai): string
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}

function format_rupiah(float|int|string $angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function badge_status(string $status): string
{
    [$label, $warna] = LABEL_STATUS[$status] ?? [$status, 'secondary'];
    return '<span class="badge bg-' . $warna . '">' . e($label) . '</span>';
}

function mulai_sesi(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function alihkan(string $url): never
{
    header("Location: $url");
    exit;
}

// Simpan pesan (dengan $pesan) atau ambil sekali pakai (tanpa argumen).
function flash(?string $pesan = null, string $tipe = 'success'): ?array
{
    if ($pesan !== null) {
        $_SESSION['flash'] = [$pesan, $tipe];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function token_csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function input_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(token_csrf()) . '">';
}

// Wajib dipanggil di awal setiap handler POST.
function cek_csrf(): void
{
    if (!hash_equals(token_csrf(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Permintaan tidak valid (token CSRF salah).');
    }
}

// ---- Database (prepared statement) ----

function kueri(mysqli $conn, string $sql, string $tipe = '', array $param = []): ?mysqli_stmt
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log(mysqli_error($conn));
        return null;
    }
    if ($tipe !== '') {
        mysqli_stmt_bind_param($stmt, $tipe, ...$param);
    }
    if (!mysqli_stmt_execute($stmt)) {
        error_log(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return null;
    }
    return $stmt;
}

function ambil_semua(mysqli $conn, string $sql, string $tipe = '', array $param = []): array
{
    $stmt = kueri($conn, $sql, $tipe, $param);
    if (!$stmt) {
        return [];
    }
    $baris = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $baris;
}

function ambil_satu(mysqli $conn, string $sql, string $tipe = '', array $param = []): ?array
{
    return ambil_semua($conn, $sql, $tipe, $param)[0] ?? null;
}

// INSERT/UPDATE/DELETE: mengembalikan jumlah baris terdampak, atau -1 bila gagal.
function eksekusi(mysqli $conn, string $sql, string $tipe = '', array $param = []): int
{
    $stmt = kueri($conn, $sql, $tipe, $param);
    if (!$stmt) {
        return -1;
    }
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

// ---- Akun & sesi ----

// Cari pengguna berdasarkan id atau username; sisa_detik = sisa waktu suspend (dihitung di MySQL).
function cari_pengguna(mysqli $conn, bool $by_username, int|string $nilai): ?array
{
    $kolom = $by_username ? 'username' : 'id_pengguna';
    return ambil_satu($conn, "SELECT *, GREATEST(COALESCE(TIMESTAMPDIFF(SECOND, NOW(), suspend_sampai), 0), 0) AS sisa_detik
        FROM pengguna WHERE $kolom = ?", $by_username ? 's' : 'i', [$nilai]);
}

// Pesan penolakan bila akun diban atau masih disuspend; null jika boleh masuk.
function pesan_blokir(array $u): ?string
{
    if ($u['status_akun'] === 'ban_permanen') {
        return 'Akun Anda diblokir permanen.';
    }
    $menit = (int) ceil((int) $u['sisa_detik'] / 60);
    return $menit > 0 ? sprintf('Akun Anda disuspend. Sisa waktu: %d jam %d menit.', intdiv($menit, 60), $menit % 60) : null;
}

function masuk_sesi(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['pengguna'] = ['id' => (int) $u['id_pengguna'], 'nama' => $u['nama'], 'peran' => $u['peran']];
}

function pengguna_login(): ?array
{
    return $_SESSION['pengguna'] ?? null;
}

// Paksa login (dicek ulang ke database tiap request); bila $peran diisi, peran harus salah satunya.
function wajib_login(mysqli $conn, string ...$peran): array
{
    $sesi = pengguna_login();
    $u = $sesi ? cari_pengguna($conn, false, $sesi['id']) : null;
    if (!$u || pesan_blokir($u)) {
        unset($_SESSION['pengguna']);
        alihkan('login.php');
    }
    if ($peran && !in_array($u['peran'], $peran, true)) {
        alihkan('index.php');
    }
    return ['id' => (int) $u['id_pengguna'], 'nama' => $u['nama'], 'peran' => $u['peran']];
}

// Simpan gambar menu ke uploads/menu/ dengan nama acak.
// null = tidak ada file, false = file tidak valid, string = path tersimpan.
function unggah_gambar(array $file): string|false|null
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024) {
        return false;
    }
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][mime_content_type($file['tmp_name'])] ?? null;
    if ($ext === null) {
        return false;
    }
    $path = 'uploads/menu/' . bin2hex(random_bytes(12)) . ".$ext";
    return move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $path) ? $path : false;
}

// ---- Keranjang (session: id_menu => jumlah, satu merchant) ----

function jumlah_keranjang(): int
{
    return array_sum($_SESSION['keranjang'] ?? []);
}

// Isi keranjang dengan harga terkini dari database: [baris[], total].
function detail_keranjang(mysqli $conn): array
{
    $keranjang = $_SESSION['keranjang'] ?? [];
    if (!$keranjang) {
        return [[], 0.0];
    }
    $id = array_keys($keranjang);
    $in = implode(',', array_fill(0, count($id), '?'));
    $baris = ambil_semua($conn, "SELECT *, (" . MENU_TAMPIL . " AND status_stok = 'tersedia') AS bisa_dipesan
        FROM menu WHERE id_menu IN ($in)", str_repeat('i', count($id)), $id);
    $total = 0.0;
    foreach ($baris as &$b) {
        $b['jumlah'] = $keranjang[$b['id_menu']];
        $b['subtotal'] = (float) $b['harga'] * $b['jumlah'];
        $total += $b['subtotal'];
    }
    return [$baris, $total];
}
