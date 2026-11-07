# AntarRasa — Pemesanan & Pengantaran Makanan (Core MVP)

PHP 8.5 prosedural + MySQLi (prepared statement) + Bootstrap 5 + Leaflet/OpenStreetMap. Tanpa framework, tanpa OOP, tanpa akun demo.

## Peran & fitur
| Peran | Halaman | Fitur |
|---|---|---|
| Pelanggan | `index.php`, `pesanan.php`, `riwayat.php`, `laporan.php` | Pesan menu (`disetujui` + `tersedia`, satu merchant per keranjang), lacak driver di peta + estimasi tiba, ulasan bintang 1-5, lapor akun |
| Merchant | `merchant.php`, `riwayat.php`, `laporan.php` | Ajukan tambah/ubah/hapus menu + gambar (menunggu persetujuan admin), ubah stok langsung, lihat pesanan masuk, lapor akun |
| Driver | `riwayat.php`, `laporan.php` | Terima/Tolak → Mulai Antar (GPS browser atau koordinat simulasi + estimasi 15-30 menit) → Perbarui Lokasi → Konfirmasi Pesanan Diterima, lapor akun |
| Admin | `admin.php`, `riwayat.php` | Moderasi menu, statistik & daftar akun, Suspend X Jam / Ban Permanen / Pulihkan / Hapus, verifikasi laporan |

Pengguna pertama yang mendaftar otomatis menjadi **admin**; berikutnya memilih pelanggan, merchant, atau driver saat daftar.
Akun yang diban atau masih disuspend ditolak saat login (dengan sisa waktu) dan sesinya langsung terputus.

## Setup
1. Jalankan Apache & MySQL (XAMPP), letakkan proyek di `htdocs/antarrasa`.
2. Impor database: `mysql -u root < database/antarrasa.sql` (atau lewat phpMyAdmin).
3. Salin `config/database.example.php` menjadi `config/database.php`, sesuaikan kredensial.
4. Buka `http://localhost/antarrasa/register.php` dan daftarkan akun pertama (menjadi admin).

> Jangan membuat dump dengan `mysqldump > file.sql` di PowerShell (menghasilkan UTF-16); gunakan `mysqldump --result-file=file.sql`.

## Struktur
- `includes/fungsi.php` — helper tunggal (sesi, CSRF, query, keranjang, unggah gambar)
- `includes/header.php`, `includes/footer.php` — layout, navigasi per peran, skrip peta
- `database/antarrasa.sql` — 5 tabel: `pengguna`, `menu`, `pesanan`, `detail_pesanan`, `laporan`
- `uploads/menu/` — gambar menu (tidak di-commit; eksekusi skrip diblokir `.htaccess`)

## Keamanan
Prepared statement di seluruh query, `password_hash`, `htmlspecialchars` untuk output, token CSRF di semua form POST,
cookie sesi `httponly` + `samesite`, `session_regenerate_id` saat masuk, validasi MIME & ukuran gambar dengan nama file acak,
transisi status pesanan atomik (driver lain tidak bisa mengambil pesanan yang sudah diklaim).
