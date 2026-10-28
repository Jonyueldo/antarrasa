<?php
declare(strict_types=1);

/**
 * Konfigurasi Koneksi Basis Data MySQLi (Murni Prosedural)
 * Sesuai Batasan Desain SRS IEEE Std 830-1998 & Kompatibilitas PHP 8
 */

// 1. Matikan pelaporan exception otomatis berbasis objek pada mysqli
// Ini memastikan seluruh penanganan error menggunakan pengecekan prosedural
mysqli_report(MYSQLI_REPORT_OFF);

// 2. Kredensial Basis Data XAMPP
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'antarrasa_db';
$db_port = 3306;

// 3. Inisialisasi koneksi prosedural
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

// 4. Penanganan kegagalan koneksi secara prosedural
if (!$conn) {
    $error_pesan = mysqli_connect_error();
    $error_kode = mysqli_connect_errno();
    
    // Tampilkan pesan kesalahan ramah lingkungan dan panduan instalasi
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Koneksi Database Gagal - AntarRasa</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light py-5">
        <div class="container">
            <div class="card shadow-sm border-danger mx-auto" style="max-width: 650px;">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Kesalahan Koneksi Basis Data (MySQLi)</h5>
                </div>
                <div class="card-body">
                    <p class="text-danger fw-semibold">Tidak dapat terhubung ke server database MySQL/MariaDB:</p>
                    <div class="alert alert-secondary font-monospace small">
                        [Kode <?= htmlspecialchars((string) $error_kode, ENT_QUOTES, 'UTF-8') ?>] <?= htmlspecialchars((string) $error_pesan, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <h6>Langkah Penyelesaian:</h6>
                    <ol class="small text-muted">
                        <li>Pastikan modul <strong>MySQL</strong> pada <strong>XAMPP Control Panel</strong> sudah dalam status <strong>Running</strong>.</li>
                        <li>Pastikan basis data <code>antarrasa_db</code> telah dibuat dan diimpor dari file <code>database/antarrasa.sql</code> melalui phpMyAdmin (<code>http://localhost/phpmyadmin</code>).</li>
                        <li>Periksa kredensial pada file <code>config/database.php</code> (default XAMPP: user: <code>root</code>, password: <em>kosong</em>).</li>
                    </ol>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 5. Atur karakter set utf8mb4 untuk dukungan teks nusantara dan simbol penuh
mysqli_set_charset($conn, 'utf8mb4');

// 6. Pastikan autocommit aktif agar setiap manipulasi tersimpan permanen ke MySQL
mysqli_autocommit($conn, true);

// 7. Jalankan Inisialisasi Skema DataStore D4 (HANYA migrasi struktur & HANYA seed jika tabel benar-benar kosong 0 baris)
require_once __DIR__ . '/../includes/functions_auth.php';
require_once __DIR__ . '/../includes/functions_admin.php';
inisialisasi_tabel_pengguna($conn);
