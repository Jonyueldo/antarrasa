<?php
declare(strict_types=1);

/**
 * Modul Fungsi Bisnis Pemesanan "AntarRasa" (Murni Prosedural)
 * Memenuhi P-Spec Proses 1.0 (Kelola Pesanan), 2.0 (Pembayaran), 3.0 (Pengantaran)
 * serta Manajemen Menu (Merchant) & Statistik (Admin).
 *
 * Aturan PHP 8:
 * - declare(strict_types=1) aktif
 * - Parameter type hint & return type declaration pada seluruh fungsi
 * - Tanpa class / object / inheritance
 * - Menggunakan prepared statement prosedural mysqli_*
 */

// =====================================================================
// 1. FUNGSI P-SPEC 1.0: PENGELOLAAN KATALOG & PERHITUNGAN
// =====================================================================

/**
 * Daftar kategori menu resmi: kunci disimpan di kolom menu.kategori,
 * label & ikon dipakai chip katalog serta form merchant.
 */
const KATEGORI_MENU = [
    'ayam'    => ['label' => 'Ayam & Bebek',     'icon' => 'bi-egg-fried'],
    'nasi'    => ['label' => 'Nasi Goreng',      'icon' => 'bi-cup-hot'],
    'mie'     => ['label' => 'Olahan Mie',       'icon' => 'bi-water'],
    'sate'    => ['label' => 'Sate & Bakaran',   'icon' => 'bi-fire'],
    'daging'  => ['label' => 'Daging & Rendang', 'icon' => 'bi-basket'],
    'minuman' => ['label' => 'Minuman Segar',    'icon' => 'bi-cup-straw'],
    'lainnya' => ['label' => 'Lainnya',          'icon' => 'bi-three-dots'],
];

/**
 * Mengambil daftar menu dari DataStore D1 (MENU), opsional difilter kategori
 *
 * @param mysqli $conn Objek koneksi MySQLi prosedural
 * @param string|null $kategori Kunci KATEGORI_MENU; null / tidak dikenal = semua kategori
 * @param bool $hanya_aktif true = sembunyikan menu yang sudah dihapus (soft delete, is_aktif = 0)
 * @return array Array berisi asosiatif data menu
 */
function ambil_semua_menu($conn, ?string $kategori = null, bool $hanya_aktif = true): array
{
    $daftar_menu = [];
    $kolom = "id_menu, id_merchant, nama_menu, harga, deskripsi, kategori, gambar_url, status_menu, is_aktif";
    $syarat_aktif = $hanya_aktif ? "is_aktif = 1" : "1 = 1";

    if ($kategori !== null && isset(KATEGORI_MENU[$kategori])) {
        // Filter kategori lewat prepared statement berparameter
        $stmt = mysqli_prepare($conn, "SELECT $kolom FROM menu WHERE $syarat_aktif AND kategori = ? ORDER BY id_menu ASC");
        if (!$stmt) {
            return $daftar_menu;
        }
        mysqli_stmt_bind_param($stmt, "s", $kategori);
        mysqli_stmt_execute($stmt);
        $hasil = mysqli_stmt_get_result($stmt);
        if ($hasil !== false) {
            $daftar_menu = mysqli_fetch_all($hasil, MYSQLI_ASSOC);
            mysqli_free_result($hasil);
        }
        mysqli_stmt_close($stmt);
        return $daftar_menu;
    }

    $query = mysqli_query($conn, "SELECT $kolom FROM menu WHERE $syarat_aktif ORDER BY id_menu ASC");
    if ($query !== false) {
        $daftar_menu = mysqli_fetch_all($query, MYSQLI_ASSOC);
        mysqli_free_result($query);
    }

    return $daftar_menu;
}

/**
 * Mengambil satu data menu berdasarkan ID dari DataStore D1 (MENU)
 *
 * @param mysqli $conn
 * @param int $id_menu
 * @return array|null Data menu jika ditemukan, null jika tidak
 */
function ambil_menu_by_id($conn, int $id_menu): ?array
{
    $sql = "SELECT id_menu, id_merchant, nama_menu, harga, deskripsi, kategori, gambar_url, status_menu, is_aktif FROM menu WHERE id_menu = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $id_menu);
    mysqli_stmt_execute($stmt);
    
    $hasil = mysqli_stmt_get_result($stmt);
    $data = null;
    
    if ($hasil !== false && mysqli_num_rows($hasil) > 0) {
        $data = mysqli_fetch_assoc($hasil);
        mysqli_free_result($hasil);
    }
    
    mysqli_stmt_close($stmt);
    return $data;
}

/**
 * Menambahkan hidangan baru ke DataStore D1 (MENU) - Fitur Merchant
 *
 * @param mysqli $conn
 * @param string $nama_menu
 * @param float $harga
 * @param string $deskripsi
 * @param string $status_menu
 * @param int|null $id_merchant Merchant pemilik menu (null jika ditambahkan admin)
 * @param string $kategori Kunci KATEGORI_MENU (tidak dikenal = 'lainnya')
 * @return int|false ID menu baru jika berhasil, false jika gagal
 */
function tambah_menu_makanan($conn, string $nama_menu, float $harga, string $deskripsi, string $status_menu = 'Tersedia', ?int $id_merchant = null, string $kategori = 'lainnya'): int|false
{
    if ($id_merchant !== null && $id_merchant <= 0) {
        $id_merchant = null;
    }
    if (!isset(KATEGORI_MENU[$kategori])) {
        $kategori = 'lainnya';
    }

    $nama_bersih = trim($nama_menu);
    if ($nama_bersih === '' || $harga < 0.0) {
        return false;
    }

    $status_valid = ['Tersedia', 'Habis'];
    if (!in_array($status_menu, $status_valid, true)) {
        $status_menu = 'Tersedia';
    }

    $sql = "INSERT INTO menu (id_merchant, nama_menu, harga, deskripsi, kategori, status_menu) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "isdsss", $id_merchant, $nama_bersih, $harga, $deskripsi, $kategori, $status_menu);
    $eksekusi = mysqli_stmt_execute($stmt);

    if (!$eksekusi) {
        mysqli_stmt_close($stmt);
        return false;
    }

    $id_baru = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return $id_baru > 0 ? $id_baru : false;
}

/**
 * Mengubah status ketersediaan menu ('Tersedia' / 'Habis') - Fitur Merchant
 *
 * @param mysqli $conn
 * @param int $id_menu
 * @param string $status_baru
 * @return bool
 */
function ubah_status_menu($conn, int $id_menu, string $status_baru): bool
{
    $status_valid = ['Tersedia', 'Habis'];
    if (!in_array($status_baru, $status_valid, true)) {
        return false;
    }

    // Periksa eksistensi baris lebih dulu agar id fiktif tidak dilaporkan sukses
    $menu = ambil_menu_by_id($conn, $id_menu);
    if ($menu === null) {
        return false;
    }

    // Status sudah sama: tidak ada yang perlu diubah (MySQL akan memberi affected_rows = 0)
    if ((string) $menu['status_menu'] === $status_baru) {
        return true;
    }

    $sql = "UPDATE menu SET status_menu = ? WHERE id_menu = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "si", $status_baru, $id_menu);
    $sukses = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    return $sukses && ($affected > 0);
}

/**
 * Memeriksa apakah pengguna boleh mengelola (ubah/hapus/toggle) sebuah menu.
 * Admin boleh semua; merchant hanya menu miliknya atau menu yang belum punya pemilik.
 *
 * @param array|null $pengguna
 * @param array $menu Data dari ambil_menu_by_id()
 * @return bool
 */
function boleh_kelola_menu(?array $pengguna, array $menu): bool
{
    $peran = (string) ($pengguna['peran'] ?? '');
    if ($peran === 'admin') {
        return true;
    }
    if ($peran !== 'merchant') {
        return false;
    }
    $id_pemilik = (int) ($menu['id_merchant'] ?? 0);
    return $id_pemilik === 0 || $id_pemilik === (int) ($pengguna['id_pengguna'] ?? -1);
}

/**
 * Mengubah data hidangan (nama, harga, deskripsi, kategori, status) - Fitur Merchant
 *
 * @param mysqli $conn
 * @param int $id_menu
 * @param string $nama_menu
 * @param float $harga Wajib > 0
 * @param string $deskripsi
 * @param string $kategori Kunci KATEGORI_MENU
 * @param string $status_menu 'Tersedia' / 'Habis'
 * @return bool false jika menu tidak ada / sudah dihapus / data tidak valid
 */
function ubah_menu_makanan($conn, int $id_menu, string $nama_menu, float $harga, string $deskripsi, string $kategori, string $status_menu): bool
{
    $nama_bersih = trim($nama_menu);
    if ($nama_bersih === '' || mb_strlen($nama_bersih) > 100 || $harga <= 0.0
        || !isset(KATEGORI_MENU[$kategori]) || !in_array($status_menu, ['Tersedia', 'Habis'], true)) {
        return false;
    }

    $menu = ambil_menu_by_id($conn, $id_menu);
    if ($menu === null || (int) $menu['is_aktif'] !== 1) {
        return false;
    }

    $sql = "UPDATE menu SET nama_menu = ?, harga = ?, deskripsi = ?, kategori = ?, status_menu = ? WHERE id_menu = ? AND is_aktif = 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "sdsssi", $nama_bersih, $harga, $deskripsi, $kategori, $status_menu, $id_menu);
    $sukses = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // affected_rows = 0 wajar bila data yang dikirim sama persis; eksistensi sudah dicek di atas
    return $sukses;
}

/**
 * Mengaktifkan / menonaktifkan menu dari katalog (soft delete) - Fitur Merchant
 *
 * @param mysqli $conn
 * @param int $id_menu
 * @param bool $aktif
 * @return bool false jika menu tidak ditemukan
 */
function set_aktif_menu($conn, int $id_menu, bool $aktif): bool
{
    $menu = ambil_menu_by_id($conn, $id_menu);
    if ($menu === null) {
        return false;
    }

    $nilai = $aktif ? 1 : 0;
    if ((int) $menu['is_aktif'] === $nilai) {
        return true;
    }

    $stmt = mysqli_prepare($conn, "UPDATE menu SET is_aktif = ? WHERE id_menu = ?");
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "ii", $nilai, $id_menu);
    $sukses = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $sukses;
}

/**
 * Menghapus menu dari katalog secara SOFT DELETE (is_aktif = 0) - Fitur Merchant.
 * Baris tetap ada sehingga FK detail_pesanan aman dan faktur lama tetap menampilkan menu ini.
 *
 * @param mysqli $conn
 * @param int $id_menu
 * @return bool false hanya jika menu tidak ditemukan atau sudah dihapus sebelumnya
 */
function hapus_menu($conn, int $id_menu): bool
{
    $menu = ambil_menu_by_id($conn, $id_menu);
    if ($menu === null || (int) $menu['is_aktif'] !== 1) {
        return false;
    }

    return set_aktif_menu($conn, $id_menu, false);
}

/**
 * Menghitung subtotal untuk satu jenis menu (harga * jumlah)
 *
 * @param float $harga Harga satuan menu
 * @param int $jumlah Jumlah yang dipesan
 * @return float Hasil perkalian harga dan jumlah
 */
function hitung_subtotal(float $harga, int $jumlah): float
{
    if ($jumlah <= 0 || $harga < 0.0) {
        return 0.0;
    }
    return round($harga * (float) $jumlah, 2);
}

/**
 * Menghitung akumulasi seluruh subtotal dari daftar item pesanan
 *
 * @param array $items Array asosiatif yang masing-masing memiliki key 'subtotal'
 * @return float Akumulasi total harga
 */
function hitung_total_harga(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        if (isset($item['subtotal']) && is_numeric($item['subtotal'])) {
            $total += (float) $item['subtotal'];
        }
    }
    return round($total, 2);
}

/**
 * Validasi pesanan sebelum disimpan ke basis data
 * Memeriksa: ketersediaan menu di D1, jumlah > 0, dan kelengkapan alamat
 *
 * @param array $pilihan_menu Array berisi [id_menu => jumlah]
 * @param string $alamat Alamat pengiriman yang diisi pelanggan
 * @param array $menu_master Array seluruh menu dari database untuk referensi cepat
 * @return array Hasil validasi ['sukses' => bool, 'pesan_error' => array, 'items_valid' => array]
 */
function validasi_pesanan(array $pilihan_menu, string $alamat, array $menu_master = []): array
{
    $pesan_error = [];
    $items_valid = [];
    
    // 1. Validasi Alamat Pengiriman
    $alamat_bersih = trim($alamat);
    if ($alamat_bersih === '') {
        $pesan_error[] = "Alamat pengiriman wajib diisi lengkap.";
    } elseif (mb_strlen($alamat_bersih) < 8) {
        $pesan_error[] = "Alamat pengiriman terlalu singkat. Mohon masukkan alamat lengkap beserta nomor rumah / patokan (min. 8 karakter).";
    }

    // Buat lookup array menu berdasarkan id_menu
    $lookup_menu = [];
    foreach ($menu_master as $m) {
        $lookup_menu[(int) $m['id_menu']] = $m;
    }

    // 2. Validasi Item Menu
    if (empty($pilihan_menu)) {
        $pesan_error[] = "Keranjang pesanan masih kosong. Silakan pilih menu makanan terlebih dahulu.";
    } else {
        foreach ($pilihan_menu as $id_menu => $jumlah) {
            $id = (int) $id_menu;
            $qty = (int) $jumlah;

            if ($qty <= 0) {
                continue; // abaikan jika qty 0 atau negatif
            }

            if (!isset($lookup_menu[$id])) {
                $pesan_error[] = "Menu dengan ID #{$id} tidak ditemukan di sistem.";
                continue;
            }

            $menu_item = $lookup_menu[$id];

            if (strcasecmp((string) $menu_item['status_menu'], 'Tersedia') !== 0) {
                $pesan_error[] = "Maaf, menu '" . $menu_item['nama_menu'] . "' sedang habis dan tidak dapat dipesan.";
                continue;
            }

            $harga = (float) $menu_item['harga'];
            $subtotal = hitung_subtotal($harga, $qty);

            $items_valid[] = [
                'id_menu'   => $id,
                'nama_menu' => (string) $menu_item['nama_menu'],
                'harga'     => $harga,
                'jumlah'    => $qty,
                'subtotal'  => $subtotal,
            ];
        }

        if (empty($items_valid) && empty($pesan_error)) {
            $pesan_error[] = "Tidak ada item menu yang valid dalam keranjang pesanan.";
        }
    }

    return [
        'sukses'      => empty($pesan_error),
        'pesan_error' => $pesan_error,
        'items_valid' => $items_valid,
        'alamat'      => $alamat_bersih,
    ];
}

/**
 * Menyimpan transaksi pesanan ke DataStore D2 (PESANAN) dan D3 (DETAIL_PESANAN)
 * Mendukung penyimpanan id_pengguna (D4: PENGGUNA) jika customer telah login
 *
 * @param mysqli $conn Objek koneksi MySQLi
 * @param string $alamat Alamat pengiriman yang sudah divalidasi
 * @param array $detail_items Array rincian item hasil validasi
 * @param int|null $id_pengguna ID pengguna yang membuat pesanan (nullable)
 * @return int|false Mengembalikan id_pesanan baru jika berhasil, false jika gagal
 */
function simpan_pesanan($conn, string $alamat, array $detail_items, ?int $id_pengguna = null): int|false
{
    if (empty($detail_items)) {
        return false;
    }

    $total_harga = hitung_total_harga($detail_items);
    $status_awal = 'Menunggu';

    // 1. Mulai Transaksi Prosedural
    mysqli_begin_transaction($conn);

    // 2. Insert ke DataStore D2 (PESANAN)
    if ($id_pengguna !== null && $id_pengguna > 0) {
        $sql_pesanan = "INSERT INTO pesanan (id_pengguna, alamat_pengiriman, total_harga, status_pesanan) VALUES (?, ?, ?, ?)";
        $stmt_pesanan = mysqli_prepare($conn, $sql_pesanan);

        if (!$stmt_pesanan) {
            mysqli_rollback($conn);
            return false;
        }

        mysqli_stmt_bind_param($stmt_pesanan, "isds", $id_pengguna, $alamat, $total_harga, $status_awal);
    } else {
        $sql_pesanan = "INSERT INTO pesanan (id_pengguna, alamat_pengiriman, total_harga, status_pesanan) VALUES (NULL, ?, ?, ?)";
        $stmt_pesanan = mysqli_prepare($conn, $sql_pesanan);

        if (!$stmt_pesanan) {
            mysqli_rollback($conn);
            return false;
        }

        mysqli_stmt_bind_param($stmt_pesanan, "sds", $alamat, $total_harga, $status_awal);
    }

    $eksekusi_pesanan = mysqli_stmt_execute($stmt_pesanan);

    if (!$eksekusi_pesanan) {
        mysqli_stmt_close($stmt_pesanan);
        mysqli_rollback($conn);
        return false;
    }

    $id_pesanan_baru = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt_pesanan);

    if ($id_pesanan_baru <= 0) {
        mysqli_rollback($conn);
        return false;
    }

    // 3. Insert ke DataStore D3 (DETAIL_PESANAN) menggunakan Prepared Statement
    // harga_satuan dibekukan saat transaksi agar faktur lama tetap akurat walau harga menu berubah
    $sql_detail = "INSERT INTO detail_pesanan (id_pesanan, id_menu, jumlah, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?)";
    $stmt_detail = mysqli_prepare($conn, $sql_detail);

    if (!$stmt_detail) {
        mysqli_rollback($conn);
        return false;
    }

    foreach ($detail_items as $item) {
        $id_menu = (int) $item['id_menu'];
        $jumlah = (int) $item['jumlah'];
        $harga_satuan = (float) $item['harga'];
        $subtotal = (float) $item['subtotal'];

        mysqli_stmt_bind_param($stmt_detail, "iiidd", $id_pesanan_baru, $id_menu, $jumlah, $harga_satuan, $subtotal);
        $eksekusi_detail = mysqli_stmt_execute($stmt_detail);

        if (!$eksekusi_detail) {
            mysqli_stmt_close($stmt_detail);
            mysqli_rollback($conn);
            return false;
        }
    }

    mysqli_stmt_close($stmt_detail);

    // 4. Komit Transaksi Prosedural jika seluruh langkah sukses
    $komit = mysqli_commit($conn);
    if (!$komit) {
        mysqli_rollback($conn);
        return false;
    }

    return $id_pesanan_baru;
}

// =====================================================================
// 2. FUNGSI PENGAMBILAN & PEMBARUAN PESANAN (PROSES 2.0 & 3.0)
// =====================================================================

/**
 * Mengambil informasi pesanan dari DataStore D2 (PESANAN) JOIN D4 (PENGGUNA)
 *
 * @param mysqli $conn
 * @param int $id_pesanan
 * @return array|null
 */
function ambil_pesanan_by_id($conn, int $id_pesanan): ?array
{
    $sql = "SELECT p.id_pesanan, p.id_pengguna, p.id_driver, p.tanggal_pesanan, p.alamat_pengiriman,
                   p.total_harga, p.status_pesanan, p.metode_bayar, p.waktu_bayar, p.bukti_bayar,
                   p.waktu_diproses, p.waktu_dikirim, p.waktu_selesai,
                   u.nama_lengkap AS nama_pemesan, u.username AS username_pemesan
            FROM pesanan p
            LEFT JOIN pengguna u ON p.id_pengguna = u.id_pengguna
            WHERE p.id_pesanan = ?
            LIMIT 1";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "i", $id_pesanan);
    mysqli_stmt_execute($stmt);

    $hasil = mysqli_stmt_get_result($stmt);
    $data = null;

    if ($hasil !== false && mysqli_num_rows($hasil) > 0) {
        $data = mysqli_fetch_assoc($hasil);
        mysqli_free_result($hasil);
    }

    mysqli_stmt_close($stmt);
    return $data;
}

/**
 * Memeriksa apakah pengguna boleh melihat / membayar sebuah pesanan.
 * - merchant, driver, admin : boleh semua pesanan
 * - customer               : hanya pesanan miliknya sendiri
 * - tamu (belum login)     : hanya pesanan tamu yang dibuat di sesinya sendiri
 *
 * @param array|null $pengguna Data dari ambil_pengguna_login()
 * @param array $pesanan Data dari ambil_pesanan_by_id()
 * @return bool
 */
function boleh_akses_pesanan(?array $pengguna, array $pesanan): bool
{
    $id_pemilik = isset($pesanan['id_pengguna']) ? (int) $pesanan['id_pengguna'] : 0;

    if ($pengguna === null) {
        $pesanan_tamu = $_SESSION['pesanan_tamu'] ?? [];
        return $id_pemilik === 0
            && is_array($pesanan_tamu)
            && in_array((int) $pesanan['id_pesanan'], $pesanan_tamu, true);
    }

    $peran = (string) ($pengguna['peran'] ?? '');
    if (in_array($peran, ['merchant', 'driver', 'admin'], true)) {
        return true;
    }

    if ($peran === 'customer') {
        $id_login = (int) ($pengguna['id_pengguna'] ?? 0);
        return $id_login > 0 && $id_login === $id_pemilik;
    }

    return false;
}

/**
 * Mencatat id pesanan tamu ke session agar hanya pembuatnya yang bisa membuka.
 *
 * @param int $id_pesanan
 * @return void
 */
function catat_pesanan_tamu(int $id_pesanan): void
{
    $daftar = $_SESSION['pesanan_tamu'] ?? [];
    if (!is_array($daftar)) {
        $daftar = [];
    }
    $daftar[] = $id_pesanan;
    // Simpan maksimal 20 pesanan terakhir agar session tidak membengkak
    $_SESSION['pesanan_tamu'] = array_slice(array_values(array_unique($daftar)), -20);
}

/**
 * Mengambil rincian item pesanan dari DataStore D3 (DETAIL_PESANAN) JOIN D1 (MENU)
 * Kolom 'harga' berisi harga SATUAN SAAT TRANSAKSI (d.harga_satuan), bukan harga menu sekarang.
 *
 * @param mysqli $conn
 * @param int $id_pesanan
 * @return array
 */
function ambil_detail_pesanan($conn, int $id_pesanan): array
{
    $items = [];
    $sql = "SELECT d.id_detail, d.id_pesanan, d.id_menu, d.jumlah, d.subtotal, m.nama_menu,
                   d.harga_satuan AS harga
            FROM detail_pesanan d
            JOIN menu m ON d.id_menu = m.id_menu
            WHERE d.id_pesanan = ?
            ORDER BY d.id_detail ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $items;
    }

    mysqli_stmt_bind_param($stmt, "i", $id_pesanan);
    mysqli_stmt_execute($stmt);

    $hasil = mysqli_stmt_get_result($stmt);
    if ($hasil !== false) {
        $items = mysqli_fetch_all($hasil, MYSQLI_ASSOC);
        mysqli_free_result($hasil);
    }

    mysqli_stmt_close($stmt);
    return $items;
}

/**
 * Mengambil daftar pesanan (dengan opsi filter status dan id_pengguna)
 *
 * @param mysqli $conn
 * @param int $limit
 * @param string|null $status_filter
 * @param int|null $id_pengguna
 * @return array
 */
function ambil_semua_pesanan($conn, int $limit = 50, ?string $status_filter = null, ?int $id_pengguna = null): array
{
    $daftar = [];
    $sql = "SELECT p.id_pesanan, p.id_pengguna, p.tanggal_pesanan, p.alamat_pengiriman, 
                   p.total_harga, p.status_pesanan,
                   u.nama_lengkap AS nama_pemesan, u.username AS username_pemesan 
            FROM pesanan p 
            LEFT JOIN pengguna u ON p.id_pengguna = u.id_pengguna";

    $conditions = [];
    $params = [];
    $types = "";

    if ($status_filter !== null && $status_filter !== '') {
        $conditions[] = "p.status_pesanan = ?";
        $params[] = $status_filter;
        $types .= "s";
    }

    if ($id_pengguna !== null && $id_pengguna > 0) {
        $conditions[] = "p.id_pengguna = ?";
        $params[] = $id_pengguna;
        $types .= "i";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY p.id_pesanan DESC LIMIT ?";
    $params[] = $limit;
    $types .= "i";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $daftar;
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);

    $hasil = mysqli_stmt_get_result($stmt);
    if ($hasil !== false) {
        $daftar = mysqli_fetch_all($hasil, MYSQLI_ASSOC);
        mysqli_free_result($hasil);
    }

    mysqli_stmt_close($stmt);
    return $daftar;
}

/** Daftar seluruh status pesanan yang sah (selaras dengan CHECK chk_status_pesanan) */
const STATUS_PESANAN_VALID = ['Menunggu', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan'];

/**
 * State machine status pesanan. Alur sah:
 *   Menunggu -> Diproses -> Dikirim -> Selesai
 *   Menunggu -> Dibatalkan
 * Status tidak boleh mundur maupun melompat tahap.
 *
 * @param string $dari Status saat ini
 * @param string $ke Status tujuan
 * @return bool
 */
function transisi_status_valid(string $dari, string $ke): bool
{
    $alur = [
        'Menunggu'   => ['Diproses', 'Dibatalkan'],
        'Diproses'   => ['Dikirim'],
        'Dikirim'    => ['Selesai'],
        'Selesai'    => [],
        'Dibatalkan' => [],
    ];

    return in_array($ke, $alur[$dari] ?? [], true);
}

/**
 * Memperbarui kolom status_pesanan pada DataStore D2 (PESANAN)
 * dengan validasi state machine (lihat transisi_status_valid()).
 *
 * @param mysqli $conn
 * @param int $id_pesanan
 * @param string $status_baru ('Menunggu', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan')
 * @param int|null $id_driver Driver yang mengambil pesanan; hanya dicatat pada transisi ke 'Dikirim'
 * @return bool
 */
function update_status_pesanan($conn, int $id_pesanan, string $status_baru, ?int $id_driver = null): bool
{
    if (!in_array($status_baru, STATUS_PESANAN_VALID, true)) {
        return false;
    }

    // Periksa eksistensi baris lebih dulu agar id fiktif tidak dilaporkan sukses
    $pesanan = ambil_pesanan_by_id($conn, $id_pesanan);
    if ($pesanan === null) {
        return false;
    }

    $status_lama = (string) $pesanan['status_pesanan'];

    // Status sudah sama: tidak ada yang perlu diubah (misalnya form terkirim ulang)
    if ($status_lama === $status_baru) {
        return true;
    }

    // Tolak transisi mundur atau melompat tahap
    if (!transisi_status_valid($status_lama, $status_baru)) {
        return false;
    }

    // Syarat status lama di WHERE mencegah race condition: jika pihak lain
    // sudah mengubah status lebih dulu, affected_rows = 0 dan update ditolak
    $catat_driver = ($status_baru === 'Dikirim' && $id_driver !== null && $id_driver > 0);
    $sql = $catat_driver
        ? "UPDATE pesanan SET status_pesanan = ?, id_driver = ? WHERE id_pesanan = ? AND status_pesanan = ?"
        : "UPDATE pesanan SET status_pesanan = ? WHERE id_pesanan = ? AND status_pesanan = ?";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return false;
    }

    if ($catat_driver) {
        mysqli_stmt_bind_param($stmt, "siis", $status_baru, $id_driver, $id_pesanan, $status_lama);
    } else {
        mysqli_stmt_bind_param($stmt, "sis", $status_baru, $id_pesanan, $status_lama);
    }
    $sukses = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    return $sukses && ($affected > 0);
}

// =====================================================================
// PEMBAYARAN (PROSES 2.0)
// =====================================================================

/** Metode pembayaran yang diterima (selaras dengan pilihan di pembayaran.php) */
const METODE_BAYAR_VALID = ['GoPay', 'ShopeePay', 'OVO', 'QRIS'];

/** Batas ukuran file bukti bayar: 2 MB */
const BATAS_UKURAN_BUKTI = 2 * 1024 * 1024;

/** Tipe MIME bukti bayar yang diterima => ekstensi file hasil generate */
const MIME_BUKTI_VALID = [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
    'application/pdf' => 'pdf',
];

/**
 * Memvalidasi dan menyimpan file bukti bayar (opsional) dari $_FILES.
 * - MIME diperiksa dari ISI file (finfo), bukan dari nama/ekstensi kiriman browser
 * - Nama file di-generate acak; nama asli dari pengguna tidak pernah dipakai
 *
 * @param array|null $file Elemen $_FILES['bukti_bayar'] atau null
 * @param string $folder_tujuan Folder absolut penyimpanan
 * @return array ['sukses' => bool, 'nama_file' => ?string, 'pesan' => string]
 */
function simpan_bukti_bayar(?array $file, string $folder_tujuan): array
{
    // Tidak ada file yang dipilih: bukti bayar bersifat opsional
    if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['sukses' => true, 'nama_file' => null, 'pesan' => ''];
    }

    $kode_error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($kode_error === UPLOAD_ERR_INI_SIZE || $kode_error === UPLOAD_ERR_FORM_SIZE) {
        return ['sukses' => false, 'nama_file' => null, 'pesan' => 'Ukuran file bukti bayar maksimal 2 MB.'];
    }
    if ($kode_error !== UPLOAD_ERR_OK) {
        return ['sukses' => false, 'nama_file' => null, 'pesan' => 'Unggahan bukti bayar gagal. Silakan coba lagi.'];
    }

    $path_sementara = (string) ($file['tmp_name'] ?? '');
    if ($path_sementara === '' || !is_uploaded_file($path_sementara)) {
        return ['sukses' => false, 'nama_file' => null, 'pesan' => 'File bukti bayar tidak valid.'];
    }

    $ukuran = (int) ($file['size'] ?? 0);
    if ($ukuran <= 0 || $ukuran > BATAS_UKURAN_BUKTI) {
        return ['sukses' => false, 'nama_file' => null, 'pesan' => 'Ukuran file bukti bayar maksimal 2 MB.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo !== false ? (string) finfo_file($finfo, $path_sementara) : '';
    if (!isset(MIME_BUKTI_VALID[$mime])) {
        return ['sukses' => false, 'nama_file' => null, 'pesan' => 'Format bukti bayar harus JPG, PNG, WEBP, atau PDF.'];
    }

    if (!is_dir($folder_tujuan) && !mkdir($folder_tujuan, 0755, true) && !is_dir($folder_tujuan)) {
        error_log('[AntarRasa] Folder bukti bayar tidak dapat dibuat: ' . $folder_tujuan);
        return ['sukses' => false, 'nama_file' => null, 'pesan' => 'Penyimpanan bukti bayar tidak tersedia.'];
    }

    $nama_file = 'bukti_' . bin2hex(random_bytes(16)) . '.' . MIME_BUKTI_VALID[$mime];
    if (!move_uploaded_file($path_sementara, $folder_tujuan . DIRECTORY_SEPARATOR . $nama_file)) {
        error_log('[AntarRasa] Gagal memindahkan bukti bayar ke ' . $folder_tujuan);
        return ['sukses' => false, 'nama_file' => null, 'pesan' => 'Gagal menyimpan file bukti bayar.'];
    }

    return ['sukses' => true, 'nama_file' => $nama_file, 'pesan' => ''];
}

/**
 * Mencatat pembayaran pesanan: status Menunggu -> Diproses (lewat state machine)
 * sekaligus menyimpan metode_bayar, waktu_bayar, dan bukti_bayar dalam SATU transaksi.
 *
 * @param mysqli $conn
 * @param int $id_pesanan
 * @param string $metode_bayar Salah satu METODE_BAYAR_VALID
 * @param string|null $bukti_bayar Nama file hasil simpan_bukti_bayar() atau null
 * @return bool
 */
function bayar_pesanan($conn, int $id_pesanan, string $metode_bayar, ?string $bukti_bayar = null): bool
{
    if (!in_array($metode_bayar, METODE_BAYAR_VALID, true)) {
        return false;
    }

    mysqli_begin_transaction($conn);

    $pesanan = ambil_pesanan_by_id($conn, $id_pesanan);
    if ($pesanan === null || (string) $pesanan['status_pesanan'] !== 'Menunggu'
        || !update_status_pesanan($conn, $id_pesanan, 'Diproses')) {
        mysqli_rollback($conn);
        return false;
    }

    $sql = "UPDATE pesanan SET metode_bayar = ?, waktu_bayar = NOW(), bukti_bayar = ? WHERE id_pesanan = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        mysqli_rollback($conn);
        return false;
    }

    mysqli_stmt_bind_param($stmt, "ssi", $metode_bayar, $bukti_bayar, $id_pesanan);
    $sukses = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    if (!$sukses || !mysqli_commit($conn)) {
        mysqli_rollback($conn);
        return false;
    }

    return true;
}

/**
 * Mengambil data akun driver langsung dari tabel PENGGUNA (DataStore D4)
 * Murni membaca database tanpa fallback dummy/hardcoded
 *
 * PENTING: jika $id_driver diberikan, pencarian bersifat KETAT (null bila tidak ada).
 * Tanpa $id_driver, fungsi mengembalikan driver pertama - JANGAN dipakai untuk
 * menampilkan kurir sebuah pesanan; gunakan pesanan.id_driver.
 *
 * @param mixed $conn Objek koneksi MySQLi prosedural
 * @param int|null $id_driver ID driver spesifik (opsional)
 * @return array|null Asosiatif [id_pengguna, username, nama_lengkap, peran] atau null
 */
function ambil_driver_aktif($conn, ?int $id_driver = null): ?array
{
    if ($id_driver !== null) {
        if ($id_driver <= 0) {
            return null;
        }
        $sql = "SELECT id_pengguna, username, nama_lengkap, peran FROM pengguna WHERE id_pengguna = ? AND peran = 'driver' LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, "i", $id_driver);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $driver = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $driver;
    }

    $sql = "SELECT id_pengguna, username, nama_lengkap, peran FROM pengguna WHERE peran = 'driver' ORDER BY id_pengguna ASC LIMIT 1";
    $query = mysqli_query($conn, $sql);
    if ($query !== false) {
        $driver = mysqli_fetch_assoc($query);
        mysqli_free_result($query);
        return $driver ?: null;
    }

    return null;
}

/**
 * Mengambil merchant yang benar-benar menyiapkan sebuah pesanan,
 * diturunkan dari menu.id_merchant pada item-item pesanan tersebut.
 *
 * @param mysqli $conn
 * @param int $id_pesanan
 * @return array|null [id_pengguna, username, nama_lengkap, peran] atau null jika menu belum punya merchant
 */
function ambil_merchant_pesanan($conn, int $id_pesanan): ?array
{
    $sql = "SELECT u.id_pengguna, u.username, u.nama_lengkap, u.peran
            FROM detail_pesanan d
            JOIN menu m ON d.id_menu = m.id_menu
            JOIN pengguna u ON m.id_merchant = u.id_pengguna
            WHERE d.id_pesanan = ? AND u.peran = 'merchant'
            ORDER BY d.id_detail ASC
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, "i", $id_pesanan);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $merchant = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $merchant;
}

/**
 * Mengambil data akun merchant/restoran langsung dari tabel PENGGUNA (DataStore D4)
 * Murni membaca database tanpa fallback dummy/hardcoded
 *
 * @param mixed $conn Objek koneksi MySQLi prosedural
 * @param int|null $id_merchant ID merchant spesifik (opsional)
 * @return array|null Asosiatif [id_pengguna, username, nama_lengkap, peran] atau null jika belum ada merchant terdaftar
 */
function ambil_merchant_aktif($conn, ?int $id_merchant = null): ?array
{
    if ($id_merchant !== null && $id_merchant > 0) {
        $sql = "SELECT id_pengguna, username, nama_lengkap, peran FROM pengguna WHERE id_pengguna = ? AND peran = 'merchant' LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_merchant);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $merchant = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if ($merchant !== null) {
                return $merchant;
            }
        }
    }

    $sql = "SELECT id_pengguna, username, nama_lengkap, peran FROM pengguna WHERE peran = 'merchant' ORDER BY id_pengguna ASC LIMIT 1";
    $query = mysqli_query($conn, $sql);
    if ($query !== false) {
        $merchant = mysqli_fetch_assoc($query);
        mysqli_free_result($query);
        return $merchant ?: null;
    }

    return null;
}

// =====================================================================
// 3. FUNGSI STATISTIK (DASHBOARD ADMIN, MERCHANT, DRIVER)
// =====================================================================

/**
 * Menghitung metrik statistik untuk Dashboard Administrator
 *
 * @param mysqli $conn
 * @return array
 */
function hitung_statistik_admin($conn): array
{
    $stats = [
        'total_pengguna'   => 0,
        'total_menu'       => 0,
        'total_pesanan'    => 0,
        'total_pendapatan' => 0.0,
    ];

    // Total Pengguna
    $q_user = mysqli_query($conn, "SELECT COUNT(*) as jml FROM pengguna");
    if ($q_user !== false) {
        $row = mysqli_fetch_assoc($q_user);
        $stats['total_pengguna'] = (int) ($row['jml'] ?? 0);
        mysqli_free_result($q_user);
    }

    // Total Menu
    $q_menu = mysqli_query($conn, "SELECT COUNT(*) as jml FROM menu WHERE is_aktif = 1");
    if ($q_menu !== false) {
        $row = mysqli_fetch_assoc($q_menu);
        $stats['total_menu'] = (int) ($row['jml'] ?? 0);
        mysqli_free_result($q_menu);
    }

    // Total Pesanan
    $q_pesanan = mysqli_query($conn, "SELECT COUNT(*) as jml FROM pesanan");
    if ($q_pesanan !== false) {
        $row = mysqli_fetch_assoc($q_pesanan);
        $stats['total_pesanan'] = (int) ($row['jml'] ?? 0);
        mysqli_free_result($q_pesanan);
    }

    // Total Pendapatan (Pesanan yang sudah dibayar: Diproses, Dikirim, Selesai)
    $q_rev = mysqli_query($conn, "SELECT SUM(total_harga) as rev FROM pesanan WHERE status_pesanan IN ('Diproses', 'Dikirim', 'Selesai')");
    if ($q_rev !== false) {
        $row = mysqli_fetch_assoc($q_rev);
        $stats['total_pendapatan'] = (float) ($row['rev'] ?? 0.0);
        mysqli_free_result($q_rev);
    }

    return $stats;
}

// =====================================================================
// 4. FUNGSI PENGELOLAAN KERANJANG SEMENTARA (SESSION PROSEDURAL)
// =====================================================================

/** Batas waktu sesi login tanpa aktivitas (detik) = 60 menit */
const BATAS_IDLE_SESI = 3600;

/**
 * Memastikan session PHP aktif secara prosedural.
 * SATU-SATUNYA tempat yang boleh memanggil session_start() di seluruh aplikasi.
 * - Cookie sesi httponly + SameSite=Lax (+ secure jika HTTPS)
 * - Strict mode agar ID sesi buatan penyerang ditolak
 * - Idle timeout: data login dihapus jika tidak aktif lebih dari BATAS_IDLE_SESI
 */
function mulai_sesi(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $pakai_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $pakai_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $sekarang = time();
    $terakhir = (int) ($_SESSION['aktivitas_terakhir'] ?? $sekarang);

    if (isset($_SESSION['pengguna']) && ($sekarang - $terakhir) > BATAS_IDLE_SESI) {
        // Sesi login kedaluwarsa: hapus identitas login dan ganti ID sesi
        unset(
            $_SESSION['id_pengguna'],
            $_SESSION['username'],
            $_SESSION['nama_lengkap'],
            $_SESSION['peran'],
            $_SESSION['pengguna'],
            $_SESSION['csrf_token']
        );
        session_regenerate_id(true);
    }

    $_SESSION['aktivitas_terakhir'] = $sekarang;
}

/**
 * Mengambil isi keranjang belanja dari session
 * @return array [id_menu => jumlah]
 */
function ambil_keranjang(): array
{
    mulai_sesi();
    if (!isset($_SESSION['antarrasa_cart']) || !is_array($_SESSION['antarrasa_cart'])) {
        $_SESSION['antarrasa_cart'] = [];
    }
    return $_SESSION['antarrasa_cart'];
}

/**
 * Menambahkan atau menjumlahkan kuantitas menu ke keranjang belanja
 *
 * @param int $id_menu
 * @param int $jumlah
 */
function tambah_ke_keranjang(int $id_menu, int $jumlah = 1): void
{
    mulai_sesi();
    if ($jumlah <= 0) {
        return;
    }
    if (!isset($_SESSION['antarrasa_cart']) || !is_array($_SESSION['antarrasa_cart'])) {
        $_SESSION['antarrasa_cart'] = [];
    }
    if (isset($_SESSION['antarrasa_cart'][$id_menu])) {
        $_SESSION['antarrasa_cart'][$id_menu] += $jumlah;
    } else {
        $_SESSION['antarrasa_cart'][$id_menu] = $jumlah;
    }
}

/**
 * Mengatur nilai kuantitas tertentu untuk satu menu di keranjang
 *
 * @param int $id_menu
 * @param int $jumlah
 */
function perbarui_keranjang(int $id_menu, int $jumlah): void
{
    mulai_sesi();
    if (!isset($_SESSION['antarrasa_cart']) || !is_array($_SESSION['antarrasa_cart'])) {
        $_SESSION['antarrasa_cart'] = [];
    }
    if ($jumlah <= 0) {
        unset($_SESSION['antarrasa_cart'][$id_menu]);
    } else {
        $_SESSION['antarrasa_cart'][$id_menu] = $jumlah;
    }
}

/**
 * Menghapus satu menu dari keranjang belanja
 *
 * @param int $id_menu
 */
function hapus_dari_keranjang(int $id_menu): void
{
    mulai_sesi();
    if (isset($_SESSION['antarrasa_cart'][$id_menu])) {
        unset($_SESSION['antarrasa_cart'][$id_menu]);
    }
}

/**
 * Mengosongkan seluruh isi keranjang belanja
 */
function kosongkan_keranjang(): void
{
    mulai_sesi();
    $_SESSION['antarrasa_cart'] = [];
}

/**
 * Menghitung total seluruh item yang ada di keranjang
 *
 * @return int
 */
function hitung_jumlah_item_keranjang(): int
{
    $keranjang = ambil_keranjang();
    $total_item = 0;
    foreach ($keranjang as $qty) {
        $total_item += (int) $qty;
    }
    return $total_item;
}

/**
 * Menghitung total nominal harga seluruh item yang ada di keranjang belanja
 *
 * @param mixed $conn
 * @return float
 */
function hitung_total_nominal_keranjang($conn): float
{
    $keranjang = ambil_keranjang();
    if (empty($keranjang)) {
        return 0.0;
    }
    $total = 0.0;
    $menu_list = ambil_semua_menu($conn);
    $lookup = [];
    foreach ($menu_list as $m) {
        $lookup[(int) $m['id_menu']] = (float) $m['harga'];
    }
    foreach ($keranjang as $id => $qty) {
        $id_int = (int) $id;
        if (isset($lookup[$id_int])) {
            $total += $lookup[$id_int] * (int) $qty;
        }
    }
    return round($total, 2);
}

// =====================================================================
// 5. FUNGSI PEMBANTU (HELPERS) FORMAT & KEAMANAN OUTPUT
// =====================================================================

/**
 * Escape string untuk pencegahan XSS pada keluaran HTML
 *
 * @param mixed $nilai
 * @return string
 */
function e(mixed $nilai): string
{
    return htmlspecialchars((string) ($nilai ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Memformat angka menjadi format mata uang Rupiah (IDR)
 *
 * @param float $nominal
 * @return string Contoh: "Rp 28.000"
 */
function format_rupiah(float $nominal): string
{
    return 'Rp ' . number_format($nominal, 0, ',', '.');
}

/**
 * Mengembalikan konfigurasi warna dan label badge berdasarkan status_pesanan
 *
 * @param string $status
 * @return array ['class' => string, 'label' => string, 'icon' => string]
 */
function dapatkan_info_status(string $status): array
{
    return match ($status) {
        'Menunggu' => [
            'class' => 'bg-warning text-dark',
            'badge' => 'warning',
            'label' => 'Menunggu Pembayaran',
            'icon'  => 'bi-hourglass-split',
            'step'  => 1
        ],
        'Diproses' => [
            'class' => 'bg-info text-dark',
            'badge' => 'info',
            'label' => 'Diproses Restoran',
            'icon'  => 'bi-fire',
            'step'  => 2
        ],
        'Dikirim' => [
            'class' => 'bg-primary text-white',
            'badge' => 'primary',
            'label' => 'Sedang Dikirim Driver',
            'icon'  => 'bi-bicycle',
            'step'  => 3
        ],
        'Selesai' => [
            'class' => 'bg-success text-white',
            'badge' => 'success',
            'label' => 'Pesanan Selesai',
            'icon'  => 'bi-check-circle-fill',
            'step'  => 4
        ],
        default => [
            'class' => 'bg-secondary text-white',
            'badge' => 'secondary',
            'label' => $status,
            'icon'  => 'bi-question-circle',
            'step'  => 0
        ]
    };
}

/**
 * Mendapatkan tautan gambar kuliner beresolusi tinggi (Unsplash Food terkurasi)
 * dengan fallback yang andal
 *
 * @param string $nama_menu
 * @return string URL gambar
 */
function dapatkan_gambar_menu(string $nama_menu): string
{
    $nama_lower = mb_strtolower($nama_menu);

    if (str_contains($nama_lower, 'nasi goreng')) {
        return 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=700&q=80';
    } elseif (str_contains($nama_lower, 'ayam') || str_contains($nama_lower, 'taliwang')) {
        return 'https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?auto=format&fit=crop&w=700&q=80';
    } elseif (str_contains($nama_lower, 'rendang')) {
        return 'https://images.unsplash.com/photo-1626777552726-4a6b54c97e46?auto=format&fit=crop&w=700&q=80';
    } elseif (str_contains($nama_lower, 'sate')) {
        return 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=700&q=80';
    } elseif (str_contains($nama_lower, 'mie')) {
        return 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=700&q=80';
    } elseif (str_contains($nama_lower, 'cendol') || str_contains($nama_lower, 'durian') || str_contains($nama_lower, 'es')) {
        return 'https://images.unsplash.com/photo-1579954115545-a95591f28bfc?auto=format&fit=crop&w=700&q=80';
    } elseif (str_contains($nama_lower, 'kebab') || str_contains($nama_lower, 'burger')) {
        return 'https://images.unsplash.com/photo-1561758033-d89a9ad46330?auto=format&fit=crop&w=700&q=80';
    }

    // Default Indonesian food placeholder
    return 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=700&q=80';
}
