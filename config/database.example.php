<?php
declare(strict_types=1);

/**
 * Template Konfigurasi Koneksi Basis Data MySQLi (Murni Prosedural)
 * Salin berkas ini menjadi 'config/database.php' dan sesuaikan kredensial Anda.
 */

// 1. Matikan pelaporan exception otomatis berbasis objek pada mysqli
mysqli_report(MYSQLI_REPORT_OFF);

// 2. Kredensial Basis Data Lingkungan Server
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'antarrasa_db';
$db_port = 3306;

// 3. Inisialisasi koneksi prosedural
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

// 4. Penanganan kegagalan koneksi secara prosedural
if (!$conn) {
    http_response_code(500);
    echo "Koneksi database gagal: " . htmlspecialchars(mysqli_connect_error() ?: 'Unknown error', ENT_QUOTES, 'UTF-8');
    exit;
}

// 5. Atur karakter set utf8mb4
mysqli_set_charset($conn, 'utf8mb4');

// 6. Pastikan autocommit aktif
mysqli_autocommit($conn, true);

// 7. Jalankan Inisialisasi Skema DataStore D4
require_once __DIR__ . '/../includes/functions_auth.php';
require_once __DIR__ . '/../includes/functions_admin.php';
inisialisasi_tabel_pengguna($conn);
