<?php
declare(strict_types=1);

/**
 * Modul Fungsi Bisnis Pemesanan "AntarRasa" (Murni Prosedural)
 * Memenuhi P-Spec Proses 1.0 (Kelola Pesanan) & Dasar Proses 2.0 / 3.0
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
 * Mengambil seluruh daftar menu dari DataStore D1 (MENU)
 *
 * @param mysqli $conn Objek koneksi MySQLi prosedural
 * @return array Array berisi asosiatif data menu
 */
function ambil_semua_menu($conn): array
{
    $daftar_menu = [];
    $sql = "SELECT id_menu, nama_menu, harga, deskripsi, status_menu FROM MENU ORDER BY id_menu ASC";
    
    $query = mysqli_query($conn, $sql);
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
    $sql = "SELECT id_menu, nama_menu, harga, deskripsi, status_menu FROM MENU WHERE id_menu = ? LIMIT 1";
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
 * Menggunakan transaksi MySQLi dan prepared statements prosedural murni
 *
 * @param mysqli $conn Objek koneksi MySQLi
 * @param string $alamat Alamat pengiriman yang sudah divalidasi
 * @param array $detail_items Array rincian item hasil validasi
 * @return int|false Mengembalikan id_pesanan baru jika berhasil, false jika gagal
 */
function simpan_pesanan($conn, string $alamat, array $detail_items): int|false
{
    if (empty($detail_items)) {
        return false;
    }

    $total_harga = hitung_total_harga($detail_items);
    $status_awal = 'Menunggu';

    // 1. Mulai Transaksi Prosedural
    mysqli_begin_transaction($conn);

    // 2. Insert ke DataStore D2 (PESANAN)
    $sql_pesanan = "INSERT INTO PESANAN (alamat_pengiriman, total_harga, status_pesanan) VALUES (?, ?, ?)";
    $stmt_pesanan = mysqli_prepare($conn, $sql_pesanan);

    if (!$stmt_pesanan) {
        mysqli_rollback($conn);
        return false;
    }

    mysqli_stmt_bind_param($stmt_pesanan, "sds", $alamat, $total_harga, $status_awal);
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
    $sql_detail = "INSERT INTO DETAIL_PESANAN (id_pesanan, id_menu, jumlah, subtotal) VALUES (?, ?, ?, ?)";
    $stmt_detail = mysqli_prepare($conn, $sql_detail);

    if (!$stmt_detail) {
        mysqli_rollback($conn);
        return false;
    }

    foreach ($detail_items as $item) {
        $id_menu = (int) $item['id_menu'];
        $jumlah = (int) $item['jumlah'];
        $subtotal = (float) $item['subtotal'];

        mysqli_stmt_bind_param($stmt_detail, "iiid", $id_pesanan_baru, $id_menu, $jumlah, $subtotal);
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
 * Mengambil informasi pesanan dari DataStore D2 (PESANAN)
 *
 * @param mysqli $conn
 * @param int $id_pesanan
 * @return array|null
 */
function ambil_pesanan_by_id($conn, int $id_pesanan): ?array
{
    $sql = "SELECT id_pesanan, tanggal_pesanan, alamat_pengiriman, total_harga, status_pesanan 
            FROM PESANAN 
            WHERE id_pesanan = ? 
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
 * Mengambil rincian item pesanan dari DataStore D3 (DETAIL_PESANAN) JOIN D1 (MENU)
 *
 * @param mysqli $conn
 * @param int $id_pesanan
 * @return array
 */
function ambil_detail_pesanan($conn, int $id_pesanan): array
{
    $items = [];
    $sql = "SELECT d.id_detail, d.id_pesanan, d.id_menu, d.jumlah, d.subtotal, m.nama_menu, m.harga 
            FROM DETAIL_PESANAN d
            JOIN MENU m ON d.id_menu = m.id_menu
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
 * Mengambil daftar seluruh pesanan (untuk monitoring & riwayat)
 *
 * @param mysqli $conn
 * @param int $limit
 * @return array
 */
function ambil_semua_pesanan($conn, int $limit = 20): array
{
    $daftar = [];
    $sql = "SELECT id_pesanan, tanggal_pesanan, alamat_pengiriman, total_harga, status_pesanan 
            FROM PESANAN 
            ORDER BY id_pesanan DESC 
            LIMIT ?";
            
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $daftar;
    }

    mysqli_stmt_bind_param($stmt, "i", $limit);
    mysqli_stmt_execute($stmt);

    $hasil = mysqli_stmt_get_result($stmt);
    if ($hasil !== false) {
        $daftar = mysqli_fetch_all($hasil, MYSQLI_ASSOC);
        mysqli_free_result($hasil);
    }

    mysqli_stmt_close($stmt);
    return $daftar;
}

/**
 * Memperbarui kolom status_pesanan pada DataStore D2 (PESANAN)
 * Mendukung simulasi proses pembayaran dan pengiriman
 *
 * @param mysqli $conn
 * @param int $id_pesanan
 * @param string $status_baru ('Menunggu', 'Diproses', 'Dikirim', 'Selesai')
 * @return bool
 */
function update_status_pesanan($conn, int $id_pesanan, string $status_baru): bool
{
    $status_valid = ['Menunggu', 'Diproses', 'Dikirim', 'Selesai'];
    if (!in_array($status_baru, $status_valid, true)) {
        return false;
    }

    $sql = "UPDATE PESANAN SET status_pesanan = ? WHERE id_pesanan = ?";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "si", $status_baru, $id_pesanan);
    $sukses = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    return $sukses && ($affected >= 0);
}

// =====================================================================
// 3. FUNGSI PENGELOLAAN KERANJANG SEMENTARA (SESSION PROSEDURAL)
// =====================================================================

/**
 * Memastikan session PHP aktif secara prosedural
 */
function mulai_sesi(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
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

// =====================================================================
// 4. FUNGSI PEMBANTU (HELPERS) FORMAT & KEAMANAN OUTPUT
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
    } elseif (str_contains($nama_lower, 'ayam bakar') || str_contains($nama_lower, 'taliwang')) {
        return 'https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?auto=format&fit=crop&w=700&q=80';
    } elseif (str_contains($nama_lower, 'rendang')) {
        return 'https://images.unsplash.com/photo-1626777552726-4a6b54c97e46?auto=format&fit=crop&w=700&q=80';
    } elseif (str_contains($nama_lower, 'sate')) {
        return 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=700&q=80';
    } elseif (str_contains($nama_lower, 'mie')) {
        return 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=700&q=80';
    } elseif (str_contains($nama_lower, 'cendol') || str_contains($nama_lower, 'durian') || str_contains($nama_lower, 'es')) {
        return 'https://images.unsplash.com/photo-1579954115545-a95591f28bfc?auto=format&fit=crop&w=700&q=80';
    }

    // Default Indonesian food placeholder
    return 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=700&q=80';
}
