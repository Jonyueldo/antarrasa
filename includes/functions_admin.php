<?php
declare(strict_types=1);

/**
 * Modul Fungsi Prosedural Manajemen Pengguna Administrator (DataStore D4: PENGGUNA)
 * 100% Murni Kueri Langsung ke MySQL (Tanpa Array Statis / Fallback Hardcoded)
 *
 * Aturan PHP 8:
 * - declare(strict_types=1) aktif
 * - Parameter type hint & return type declaration pada seluruh fungsi
 * - Tanpa class / object / inheritance
 * - Menggunakan prepared statement mysqli_* prosedural
 */

/**
 * Mengambil data seluruh pengguna dari tabel MySQL PENGGUNA (DataStore D4)
 * dengan opsi filter peran.
 *
 * @param mysqli $conn
 * @param string $filter_peran Filter: '', 'customer', 'merchant', 'driver', 'admin'
 * @return array Array asosiatif daftar pengguna langsung dari MySQL
 */
function ambil_semua_pengguna($conn, string $filter_peran = ''): array
{
    $daftar = [];
    $filter_bersih = mb_strtolower(trim($filter_peran));
    $peran_valid = ['customer', 'merchant', 'driver', 'admin'];

    if ($filter_bersih !== '' && in_array($filter_bersih, $peran_valid, true)) {
        $sql = "SELECT id_pengguna, username, nama_lengkap, peran FROM `PENGGUNA` WHERE `peran` = ? ORDER BY `id_pengguna` ASC";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $filter_bersih);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res !== false) {
                $daftar = mysqli_fetch_all($res, MYSQLI_ASSOC);
                mysqli_free_result($res);
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        $sql = "SELECT id_pengguna, username, nama_lengkap, peran FROM `PENGGUNA` ORDER BY `id_pengguna` ASC";
        $q = mysqli_query($conn, $sql);
        if ($q !== false) {
            $daftar = mysqli_fetch_all($q, MYSQLI_ASSOC);
            mysqli_free_result($q);
        }
    }

    return $daftar;
}

/**
 * Mengambil satu data pengguna berdasarkan id_pengguna langsung dari MySQL
 *
 * @param mysqli $conn
 * @param int $id_pengguna
 * @return array|null
 */
function ambil_pengguna_by_id($conn, int $id_pengguna): ?array
{
    if ($id_pengguna <= 0) {
        return null;
    }

    $sql = "SELECT id_pengguna, username, nama_lengkap, peran FROM `PENGGUNA` WHERE `id_pengguna` = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "i", $id_pengguna);
    mysqli_stmt_execute($stmt);

    $res = mysqli_stmt_get_result($stmt);
    $data = null;
    if ($res !== false && mysqli_num_rows($res) > 0) {
        $data = mysqli_fetch_assoc($res);
        mysqli_free_result($res);
    }

    mysqli_stmt_close($stmt);
    return $data;
}

/**
 * Menambahkan akun pengguna baru ke tabel MySQL PENGGUNA (DataStore D4)
 *
 * @param mysqli $conn
 * @param string $username
 * @param string $password
 * @param string $nama_lengkap
 * @param string $peran ('customer', 'merchant', 'driver', 'admin')
 * @return array ['sukses' => bool, 'pesan' => string, 'id_pengguna' => int|null]
 */
function admin_tambah_pengguna($conn, string $username, string $password, string $nama_lengkap, string $peran): array
{
    $username_bersih = trim($username);
    $nama_bersih     = trim($nama_lengkap);
    $peran_bersih    = mb_strtolower(trim($peran));
    $peran_valid     = ['customer', 'merchant', 'driver', 'admin'];

    // Validasi Masukan
    if (mb_strlen($username_bersih) < 3) {
        return ['sukses' => false, 'pesan' => 'Nama pengguna (username) minimal 3 karakter.', 'id_pengguna' => null];
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username_bersih)) {
        return ['sukses' => false, 'pesan' => 'Username hanya boleh memuat huruf, angka, dan underscore (_).', 'id_pengguna' => null];
    }

    if (mb_strlen($nama_bersih) < 2) {
        return ['sukses' => false, 'pesan' => 'Nama lengkap wajib diisi minimal 2 karakter.', 'id_pengguna' => null];
    }

    if (mb_strlen($password) < 6) {
        return ['sukses' => false, 'pesan' => 'Kata sandi minimal 6 karakter demi keamanan akun.', 'id_pengguna' => null];
    }

    if (!in_array($peran_bersih, $peran_valid, true)) {
        return ['sukses' => false, 'pesan' => 'Peran pengguna tidak valid. Pilih Customer, Merchant, Driver, atau Admin.', 'id_pengguna' => null];
    }

    // Periksa keunikan username langsung di MySQL
    $sql_cek = "SELECT id_pengguna FROM `PENGGUNA` WHERE `username` = ? LIMIT 1";
    $stmt_cek = mysqli_prepare($conn, $sql_cek);
    if (!$stmt_cek) {
        return ['sukses' => false, 'pesan' => 'Gagal mempersiapkan kueri verifikasi username: ' . mysqli_error($conn), 'id_pengguna' => null];
    }

    mysqli_stmt_bind_param($stmt_cek, "s", $username_bersih);
    mysqli_stmt_execute($stmt_cek);
    $res = mysqli_stmt_get_result($stmt_cek);

    if ($res !== false && mysqli_num_rows($res) > 0) {
        mysqli_free_result($res);
        mysqli_stmt_close($stmt_cek);
        return ['sukses' => false, 'pesan' => "Username '{$username_bersih}' sudah digunakan oleh akun lain.", 'id_pengguna' => null];
    }
    if ($res !== false) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt_cek);

    // Hash kata sandi menggunakan standar bcrypt
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $sql_ins = "INSERT INTO `PENGGUNA` (`username`, `password`, `nama_lengkap`, `peran`) VALUES (?, ?, ?, ?)";
    $stmt_ins = mysqli_prepare($conn, $sql_ins);
    if (!$stmt_ins) {
        return ['sukses' => false, 'pesan' => 'Gagal mempersiapkan kueri penyimpanan akun: ' . mysqli_error($conn), 'id_pengguna' => null];
    }

    mysqli_stmt_bind_param($stmt_ins, "ssss", $username_bersih, $password_hash, $nama_bersih, $peran_bersih);
    $eksekusi = mysqli_stmt_execute($stmt_ins);

    if (!$eksekusi) {
        $error_db = mysqli_error($conn);
        mysqli_stmt_close($stmt_ins);
        return ['sukses' => false, 'pesan' => 'Gagal menyimpan akun ke basis data MySQL: ' . $error_db, 'id_pengguna' => null];
    }

    $id_baru = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt_ins);

    return [
        'sukses'      => true,
        'pesan'       => "Pengguna baru '@{$username_bersih}' (" . ucfirst($peran_bersih) . ") berhasil ditambahkan secara permanen ke MySQL!",
        'id_pengguna' => $id_baru,
    ];
}

/**
 * Memperbarui data pengguna, peran, atau reset kata sandi langsung di tabel MySQL PENGGUNA
 *
 * @param mysqli $conn
 * @param int $id_pengguna
 * @param string $username
 * @param string $nama_lengkap
 * @param string $peran
 * @param string $password_baru (Kosong jika tidak ingin mengubah password)
 * @return array ['sukses' => bool, 'pesan' => string]
 */
function admin_ubah_pengguna(
    $conn,
    int $id_pengguna,
    string $username,
    string $nama_lengkap,
    string $peran,
    string $password_baru = ''
): array {
    if ($id_pengguna <= 0) {
        return ['sukses' => false, 'pesan' => 'ID pengguna tidak valid.'];
    }

    $username_bersih = trim($username);
    $nama_bersih     = trim($nama_lengkap);
    $peran_bersih    = mb_strtolower(trim($peran));
    $peran_valid     = ['customer', 'merchant', 'driver', 'admin'];

    if (mb_strlen($username_bersih) < 3) {
        return ['sukses' => false, 'pesan' => 'Username minimal 3 karakter.'];
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username_bersih)) {
        return ['sukses' => false, 'pesan' => 'Username hanya boleh memuat huruf, angka, dan underscore (_).'];
    }

    if (mb_strlen($nama_bersih) < 2) {
        return ['sukses' => false, 'pesan' => 'Nama lengkap wajib diisi minimal 2 karakter.'];
    }

    if (!in_array($peran_bersih, $peran_valid, true)) {
        return ['sukses' => false, 'pesan' => 'Peran pengguna tidak valid.'];
    }

    // Periksa apakah username sudah dipakai oleh akun LAIN
    $sql_cek = "SELECT id_pengguna FROM `PENGGUNA` WHERE `username` = ? AND `id_pengguna` != ? LIMIT 1";
    $stmt_cek = mysqli_prepare($conn, $sql_cek);
    if (!$stmt_cek) {
        return ['sukses' => false, 'pesan' => 'Gagal mempersiapkan kueri verifikasi username: ' . mysqli_error($conn)];
    }

    mysqli_stmt_bind_param($stmt_cek, "si", $username_bersih, $id_pengguna);
    mysqli_stmt_execute($stmt_cek);
    $res = mysqli_stmt_get_result($stmt_cek);

    if ($res !== false && mysqli_num_rows($res) > 0) {
        mysqli_free_result($res);
        mysqli_stmt_close($stmt_cek);
        return ['sukses' => false, 'pesan' => "Username '{$username_bersih}' sudah digunakan oleh pengguna lain."];
    }
    if ($res !== false) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt_cek);

    // Update data dengan atau tanpa mengubah kata sandi
    if ($password_baru !== '') {
        if (mb_strlen($password_baru) < 6) {
            return ['sukses' => false, 'pesan' => 'Kata sandi baru minimal 6 karakter.'];
        }

        $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
        $sql_update = "UPDATE `PENGGUNA` SET `username` = ?, `password` = ?, `nama_lengkap` = ?, `peran` = ? WHERE `id_pengguna` = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        if (!$stmt_update) {
            return ['sukses' => false, 'pesan' => 'Gagal mempersiapkan kueri update: ' . mysqli_error($conn)];
        }

        mysqli_stmt_bind_param($stmt_update, "ssssi", $username_bersih, $password_hash, $nama_bersih, $peran_bersih, $id_pengguna);
    } else {
        $sql_update = "UPDATE `PENGGUNA` SET `username` = ?, `nama_lengkap` = ?, `peran` = ? WHERE `id_pengguna` = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        if (!$stmt_update) {
            return ['sukses' => false, 'pesan' => 'Gagal mempersiapkan kueri update: ' . mysqli_error($conn)];
        }

        mysqli_stmt_bind_param($stmt_update, "sssi", $username_bersih, $nama_bersih, $peran_bersih, $id_pengguna);
    }

    $sukses = mysqli_stmt_execute($stmt_update);
    $affected = mysqli_stmt_affected_rows($stmt_update);
    mysqli_stmt_close($stmt_update);

    if (!$sukses) {
        return ['sukses' => false, 'pesan' => 'Gagal memperbarui data pengguna di basis data MySQL: ' . mysqli_error($conn)];
    }

    // Jika yang diubah adalah akun Admin yang sedang login saat ini, sinkronkan sesi
    if (isset($_SESSION['id_pengguna']) && (int)$_SESSION['id_pengguna'] === $id_pengguna) {
        $_SESSION['username']     = $username_bersih;
        $_SESSION['nama_lengkap'] = $nama_bersih;
        $_SESSION['peran']        = $peran_bersih;
        $_SESSION['pengguna']     = [
            'id_pengguna'  => $id_pengguna,
            'username'     => $username_bersih,
            'nama_lengkap' => $nama_bersih,
            'peran'        => $peran_bersih,
        ];
    }

    return [
        'sukses' => true,
        'pesan'  => "Perubahan akun '@{$username_bersih}' berhasil disimpan permanen ke MySQL!" . ($password_baru !== '' ? ' (Kata sandi telah direset)' : ''),
    ];
}

/**
 * Menghapus akun pengguna secara permanen dari tabel MySQL PENGGUNA (DataStore D4)
 * Menangani Foreign Key di PESANAN terlebih dahulu agar tidak gagal/ditolak.
 *
 * @param mysqli $conn
 * @param int $id_pengguna
 * @param int $id_admin_aktif
 * @return array ['sukses' => bool, 'pesan' => string]
 */
function admin_hapus_pengguna($conn, int $id_pengguna, int $id_admin_aktif): array
{
    if ($id_pengguna <= 0) {
        return ['sukses' => false, 'pesan' => 'ID pengguna tidak valid.'];
    }

    // Proteksi Keamanan: Cegah Admin menghapus akun miliknya sendiri yang sedang aktif
    if ($id_pengguna === $id_admin_aktif) {
        return [
            'sukses' => false,
            'pesan'  => 'Aksi ditolak demi keamanan: Anda tidak dapat menghapus akun Administrator Anda sendiri yang sedang aktif digunakan.',
        ];
    }

    // Ambil data untuk konfirmasi nama akun
    $user = ambil_pengguna_by_id($conn, $id_pengguna);
    if ($user === null) {
        return ['sukses' => false, 'pesan' => 'Data pengguna tidak ditemukan di database MySQL.'];
    }

    $username_lama = (string)$user['username'];

    // 1. ATASI MASALAH FOREIGN KEY:
    // Lepaskan referensi id_pengguna di tabel PESANAN sebelum menghapus pengguna
    $stmt_fk = mysqli_prepare($conn, "UPDATE `PESANAN` SET `id_pengguna` = NULL WHERE `id_pengguna` = ?");
    if ($stmt_fk) {
        mysqli_stmt_bind_param($stmt_fk, "i", $id_pengguna);
        mysqli_stmt_execute($stmt_fk);
        mysqli_stmt_close($stmt_fk);
    }

    // 2. HAPUS PENGGUNA SECARA PERMANEN DARI TABEL PENGGUNA (D4)
    $stmt_del = mysqli_prepare($conn, "DELETE FROM `PENGGUNA` WHERE `id_pengguna` = ?");
    if (!$stmt_del) {
        return ['sukses' => false, 'pesan' => 'Gagal mempersiapkan kueri penghapusan akun: ' . mysqli_error($conn)];
    }

    mysqli_stmt_bind_param($stmt_del, "i", $id_pengguna);
    $eksekusi = mysqli_stmt_execute($stmt_del);
    $affected = mysqli_stmt_affected_rows($stmt_del);
    mysqli_stmt_close($stmt_del);

    if ($eksekusi && $affected > 0) {
        return [
            'sukses' => true,
            'pesan'  => "Akun pengguna '@{$username_lama}' (ID #{$id_pengguna}) berhasil dihapus permanen dari MySQL.",
        ];
    }

    return [
        'sukses' => false,
        'pesan'  => 'Gagal menghapus pengguna dari basis data MySQL (Data tidak terpengaruh).',
    ];
}

/**
 * Menghitung rekapitulasi jumlah akun per peran langsung dari MySQL
 *
 * @param mysqli $conn
 * @return array ['semua' => int, 'customer' => int, 'merchant' => int, 'driver' => int, 'admin' => int]
 */
function hitung_rekap_peran_pengguna($conn): array
{
    $rekap = [
        'semua'    => 0,
        'customer' => 0,
        'merchant' => 0,
        'driver'   => 0,
        'admin'    => 0,
    ];

    $sql = "SELECT `peran`, COUNT(*) as jml FROM `PENGGUNA` GROUP BY `peran`";
    $q = mysqli_query($conn, $sql);
    if ($q !== false) {
        while ($row = mysqli_fetch_assoc($q)) {
            $p = mb_strtolower((string)$row['peran']);
            $jml = (int)$row['jml'];
            if (isset($rekap[$p])) {
                $rekap[$p] = $jml;
            }
            $rekap['semua'] += $jml;
        }
        mysqli_free_result($q);
    }

    return $rekap;
}
