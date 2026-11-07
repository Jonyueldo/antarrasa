<?php
declare(strict_types=1);

// Salin menjadi config/database.php lalu sesuaikan kredensial (file itu tidak di-commit).
mysqli_report(MYSQLI_REPORT_OFF);

$conn = mysqli_connect('localhost', 'root', '', 'antarrasa_db', 3306);
if (!$conn) {
    http_response_code(500);
    exit('Koneksi database gagal. Impor database/antarrasa.sql lalu periksa config/database.php.');
}
mysqli_set_charset($conn, 'utf8mb4');

require_once __DIR__ . '/../includes/fungsi.php';
