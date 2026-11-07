# AntarRasa — Pemesanan Makanan Online (Core MVP)

PHP 8.5 prosedural + MySQLi (prepared statement) + Bootstrap 5. Tanpa framework, tanpa OOP, tanpa akun demo.

## Fitur (4 alur utama)
1. **Autentikasi** — daftar, masuk, keluar. Peran: `admin` dan `pelanggan`. Pengguna pertama yang mendaftar otomatis menjadi `admin`.
2. **Katalog & CRUD menu** — pelanggan melihat katalog; admin menambah, mengubah, dan menghapus menu (`admin.php`).
3. **Keranjang & checkout** — keranjang di session, checkout dalam satu transaksi (harga dibaca ulang saat checkout).
4. **Riwayat & status** — pelanggan melihat pesanannya; admin mengubah status `Menunggu` → `Diproses` → `Selesai`.

## Setup
1. Jalankan Apache & MySQL (XAMPP), letakkan proyek di `htdocs/antarrasa`.
2. Impor database: `mysql -u root < database/antarrasa.sql` (atau impor lewat phpMyAdmin).
3. Salin `config/database.example.php` menjadi `config/database.php`, sesuaikan kredensial.
4. Buka `http://localhost/antarrasa`, daftar akun pertama (menjadi admin).

> Jangan membuat dump dengan `mysqldump > file.sql` di PowerShell (menghasilkan UTF-16); gunakan `mysqldump --result-file=file.sql`.

## Struktur
`index.php` katalog · `pesanan.php` keranjang/checkout · `riwayat.php` riwayat/status · `admin.php` CRUD menu ·
`login.php` `register.php` `logout.php` · `includes/fungsi.php` helper tunggal · `database/antarrasa.sql` 4 tabel (`pengguna`, `menu`, `pesanan`, `detail_pesanan`).

## Keamanan
Prepared statement di seluruh query, `password_hash`, escape output dengan `htmlspecialchars`, token CSRF di semua form POST, cookie sesi `httponly` + `samesite`, `session_regenerate_id` saat masuk.
