<?php
declare(strict_types=1);

// Fungsi bantu tunggal: sesi, CSRF, query, keranjang, dan format.

const STATUS_BERIKUT = ['Menunggu' => 'Diproses', 'Diproses' => 'Selesai'];

function e(mixed $nilai): string
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}

function format_rupiah(float|int|string $angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
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

// Wajib dipanggil di awal setiap handler POST.
function cek_csrf(): void
{
    if (!hash_equals(token_csrf(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Permintaan tidak valid (token CSRF salah).');
    }
}

function pengguna_login(): ?array
{
    return $_SESSION['pengguna'] ?? null;
}

// Paksa login; jika $peran diisi, peran harus cocok. Mengembalikan data pengguna.
function wajib_login(?string $peran = null): array
{
    $p = pengguna_login();
    if ($p === null) {
        alihkan('login.php');
    }
    if ($peran !== null && $p['peran'] !== $peran) {
        alihkan('index.php');
    }
    return $p;
}

function masuk_sesi(array $p): void
{
    session_regenerate_id(true);
    $_SESSION['pengguna'] = ['id' => (int) $p['id_pengguna'], 'nama' => $p['nama_lengkap'], 'peran' => $p['peran']];
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

// ---- Keranjang (session: id_menu => jumlah) ----

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
    $baris = ambil_semua($conn, "SELECT * FROM menu WHERE id_menu IN ($in)", str_repeat('i', count($id)), $id);
    $total = 0.0;
    foreach ($baris as &$b) {
        $b['jumlah'] = $keranjang[$b['id_menu']];
        $b['subtotal'] = (float) $b['harga'] * $b['jumlah'];
        $total += $b['subtotal'];
    }
    return [$baris, $total];
}

function badge_status(string $status): string
{
    $warna = ['Menunggu' => 'warning text-dark', 'Diproses' => 'info text-dark', 'Selesai' => 'success'][$status] ?? 'secondary';
    return '<span class="badge bg-' . $warna . '">' . e($status) . '</span>';
}
