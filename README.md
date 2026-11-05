# AntarRasa - Sistem Pemesanan & Pengantaran Makanan Online
> **Super-App Modern UI/UX (Gaya GoFood / GrabFood / ShopeeFood)**  
> **Arsitektur 4 DataStore: `menu`, `pesanan`, `detail_pesanan`, `pengguna`**  
> Berdasarkan Dokumen Spesifikasi Kebutuhan Perangkat Lunak (**SRS IEEE Std 830-1998**).  
> **Runtime terverifikasi:** PHP **8.5.12** (ZTS, x64) &bull; MariaDB **10.11** &bull; XAMPP (Windows) / macOS / Linux

---

## 1. Ikhtisar Sistem & Desain Antarmuka Berbasis Peran (4 Role UI)

**AntarRasa** adalah aplikasi web pemesanan dan pengantaran makanan online bergaya super-app kuliner. Antarmuka dirancang berbeda untuk 4 peran pengguna sesuai alur kerja masing-masing, dan seluruhnya responsif hingga lebar ponsel 375px.

1. **Customer UI (`index.php`, `pesanan.php`, `pembayaran.php`, `status.php`, `riwayat.php`)**
   - *Category Filter Chips* yang membaca kolom `menu.kategori` di database (Ayam & Bebek, Nasi Goreng, Olahan Mie, Sate & Bakaran, Daging & Rendang, Minuman Segar, Lainnya).
   - *Food Card Grid* dengan foto menu dari database (atau ilustrasi lokal per kategori), stepper kuantitas (maksimal 50 porsi per menu, dibatasi di server), dan *Floating Checkout Bar*.
   - Checkout di dalam transaksi database: harga & ketersediaan dibaca ulang saat checkout, menu yang habis dikeluarkan otomatis dengan pemberitahuan.
   - Pembayaran **GoPay / ShopeePay / OVO / QRIS** tersimpan di database (metode, waktu bayar, unggah bukti bayar opsional).
   - *Tracking Timeline* 4 tahap dengan waktu tiap tahap, nama restoran & kurir yang benar-benar menangani pesanan.
   - **Batalkan pesanan** (selama belum dibayar) dan **Pesan Lagi** (isi ulang keranjang dari pesanan lama).
   - Riwayat pesanan berpaginasi lengkap dengan rincian item dan harga satuan saat transaksi.

2. **Merchant Portal UI (`merchant.php`)**
   - **Antrean Dapur** (status Menunggu & Diproses) berpaginasi dengan tombol *"Terima & Masak Pesanan"*.
   - **Manajemen Menu**: tambah, **edit**, unggah foto, toggle Tersedia/Habis, dan **hapus (soft delete)** — menu yang pernah terjual hilang dari katalog tetapi tetap tampil di faktur lama.
   - Kartu **Menu Terlaris** (agregasi porsi terjual dari pesanan yang sudah dibayar) dan KPI "Tuntas Hari Ini" yang benar-benar dihitung per tanggal hari ini.

3. **Driver Courier HUD UI (`driver.php`)**
   - Tab **"Tugas Saya"** (pesanan yang Anda klaim) dan **"Tersedia"** (pesanan siap jemput yang belum diklaim driver lain).
   - Klaim tugas **eksklusif**: satu pesanan hanya bisa diambil satu driver, dan hanya driver tersebut yang dapat menyelesaikannya.
   - **Rekap Pengantaran** per driver: jumlah pengantaran selesai, sedang diantar, dan total nilai pesanan yang diantar.

4. **Executive Admin Backoffice UI (`admin.php`, `laporan.php`)**
   - Sidebar gelap yang berubah menjadi menu *hamburger* (Bootstrap Offcanvas) di layar < 992px.
   - 4 Kartu KPI dengan **filter periode tanggal** (Total Pengguna, Total Menu, Total Pesanan, Total Perputaran).
   - Manajemen akun pengguna (tambah/edit/ganti peran/reset kata sandi/hapus) dengan **proteksi admin terakhir** dan larangan menurunkan peran diri sendiri.
   - Monitoring transaksi berpaginasi dan halaman **Laporan Transaksi** dengan filter tanggal & status serta **ekspor CSV** (siap dibuka di Excel).

5. **Split-Screen Authentication (`login.php` & `register.php`)**
   - Form login & registrasi customer. **4 kartu quick-login demo hanya tampil saat `MODE_DEMO` aktif** (lihat bagian 3).

---

## 2. Arsitektur Basis Data (4 DataStore Terstruktur)

Nama tabel **huruf kecil** di DDL maupun kode agar identik di Windows, macOS, dan Linux.

1. **D1: `menu`** — katalog hidangan: `kategori`, `gambar_url`, `id_merchant` (pemilik), `status_menu` (Tersedia/Habis), `is_aktif` (soft delete).
2. **D2: `pesanan`** — transaksi utama: `id_pengguna` (pemesan), `id_driver` (kurir), `status_pesanan`, `metode_bayar`, `waktu_bayar`, `bukti_bayar`, serta `waktu_diproses` / `waktu_dikirim` / `waktu_selesai`.
3. **D3: `detail_pesanan`** — rincian item: `jumlah`, `harga_satuan` (dibekukan saat transaksi), `subtotal`.
4. **D4: `pengguna`** — akun pengguna dengan kata sandi hash *bcrypt* dan peran (`customer`, `merchant`, `driver`, `admin`).

```
   [D4: pengguna] ──(pemesan / kurir / pemilik menu)──┐
         │                                            │
         ▼ (1 to many)                                ▼
   [D2: pesanan] ◄── (many to 1) ── [D3: detail_pesanan] ──► [D1: menu]
```

**Integritas data di level database:** InnoDB + utf8mb4 (`utf8mb4_unicode_ci`), `DECIMAL(12,2)` untuk uang, foreign key lengkap, `CHECK` pada `peran`, `status_pesanan`, `status_menu`, dan `jumlah > 0`, serta indeks pada kolom filter dashboard (`status_pesanan`, `tanggal_pesanan`, `peran`, `status_menu`).

**Alur status pesanan (state machine, ditegakkan di `update_status_pesanan()`):**

```
Menunggu ──► Diproses ──► Dikirim ──► Selesai
    │
    └──────► Dibatalkan   (hanya oleh customer pemilik, sebelum dibayar)
```

Status tidak dapat mundur maupun melompat tahap.

---

## 3. Akun Demo Default & Mode Demo

Akun demo dari `database/seed.sql` (kata sandi seluruhnya **`password123`**; hash dibuat khusus untuk repository, bukan akun asli anggota tim):

| Peran (Role) | Username | Password | Dashboard |
| :--- | :--- | :--- | :--- |
| **Customer** | `customer` | `password123` | Katalog, keranjang, checkout, pembayaran, pelacakan, riwayat |
| **Customer** | `customer2` | `password123` | Akun customer kedua untuk menguji isolasi data antar pelanggan |
| **Merchant** | `merchant` | `password123` | Antrean dapur, manajemen menu, menu terlaris |
| **Driver** | `driver` | `password123` | Tugas Saya / Tersedia, rekap pengantaran |
| **Admin** | `admin` | `password123` | Backoffice, manajemen pengguna, laporan & ekspor CSV |

**`MODE_DEMO`** (didefinisikan di `config/database.php`) mengaktifkan dua fitur yang dibutuhkan saat demo/sidang:
- 4 kartu **quick-login 1-klik** di halaman login, dan
- **panel simulasi status** di `status.php` (hanya untuk admin; tetap mengikuti state machine).

Secara default `MODE_DEMO` **hanya aktif jika aplikasi diakses dari komputer lokal** (`127.0.0.1` / `::1`). Di server yang dapat diakses publik, ubah menjadi `define('MODE_DEMO', false);`.

---

## 4. Batasan Desain & Paradigma Pemrograman (Wajib Prosedural)

1. **Paradigma Murni Prosedural**
   - Tidak ada definisi `class` / `interface` / `trait`, tidak ada keyword `new`, tidak ada framework maupun Composer.
   - Logika dipusatkan pada fungsi prosedural di `includes/functions_order.php`, `includes/functions_auth.php`, dan `includes/functions_admin.php`.
2. **Kepatuhan PHP 8.5.12**
   - Seluruh 18 berkas PHP diawali `declare(strict_types=1);` dan lolos `php -l` tanpa *deprecated warning*.
   - *Type hint* parameter & *return type* pada fungsi; contoh kompatibilitas: `fputcsv()` dipanggil dengan parameter `escape` eksplisit (nilai default-nya *deprecated* sejak PHP 8.4).
   - `mysqli_report(MYSQLI_REPORT_OFF)` — penanganan galat murni prosedural; detail galat database **dicatat ke `error_log()`**, pengguna hanya melihat pesan umum.

---

## 5. Keamanan (Kondisi Sebenarnya)

Daftar berikut mencerminkan proteksi yang **benar-benar terpasang** di kode saat ini (hasil perbaikan audit pada `ROADMAP.md`):

| Ancaman | Proteksi |
| :--- | :--- |
| SQL Injection | Seluruh kueri berinput memakai *prepared statement* (`mysqli_prepare` + `bind_param`), termasuk filter dinamis (`IN (?, ?, ...)`, `LIMIT ? OFFSET ?`) |
| XSS | Seluruh keluaran dinamis melalui `e()` = `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`; teks konfirmasi JavaScript di-encode dengan `json_encode(JSON_HEX_*)` |
| CSRF | Token per sesi (`token_csrf()` / `field_csrf()`) di **semua** form POST, divalidasi `wajib_csrf()` dengan `hash_equals()` sebelum handler apa pun |
| Akses tidak sah | RBAC `cek_akses()`; kepemilikan pesanan `boleh_akses_pesanan()` (pesanan orang lain tampil "tidak ditemukan"); merchant hanya mengelola menunya sendiri; driver hanya menyelesaikan pesanan yang ia klaim |
| Kata sandi | `password_hash()` / `password_verify()` (bcrypt); tanpa perbandingan plaintext |
| Brute force login | 5 kali gagal dalam 15 menit → login dikunci 5 menit, dicatat per **sesi dan per IP** (tidak bisa dilewati dengan membuang cookie), plus jeda bertahap |
| Sesi | Cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` bila HTTPS), *strict mode*, `session_regenerate_id(true)` saat login, logout menghancurkan sesi, *idle timeout* 60 menit |
| Open redirect | Parameter `ref` setelah login hanya menerima daftar putih halaman `.php` lokal |
| Unggahan berkas | Validasi MIME dari isi berkas (`finfo`), maks. 2 MB, nama berkas acak, folder dilindungi `.htaccess` (tanpa eksekusi skrip & tanpa daftar isi) |
| CSV / formula injection | Sel ekspor yang diawali `= + - @` diberi awalan `'` |
| Integritas data | State machine status pesanan, `UPDATE ... WHERE status_lama` untuk mencegah *race condition*, `CHECK` & foreign key di database, admin terakhir tidak dapat dihapus/diturunkan |
| Kebocoran kredensial | `config/database.php` tidak di-commit (lihat `.gitignore`) |

**Keterbatasan yang perlu diketahui:**
- Aplikasi tidak memaksa HTTPS; di server publik pasang HTTPS agar cookie sesi mendapat atribut `Secure`.
- Admin dapat me-reset kata sandi pengguna ke `password123`; minta pengguna segera menggantinya.
- Panjang minimal kata sandi registrasi adalah 6 karakter.
- Pembatasan login per IP memakai berkas di folder sesi PHP; di balik *reverse proxy* semua klien terlihat ber-IP sama.

---

## 6. Struktur Berkas Proyek

```
antarrasa/
├── .gitignore / .gitattributes     # Pengecualian Git & aturan akhir baris (.sql/.sh = LF, .bat = CRLF)
├── README.md                       # Dokumentasi sistem & panduan tim
├── ROADMAP.md                      # Daftar tugas resmi hasil audit
├── CLAUDE.md                       # Pedoman pengembangan & konvensi commit
├── config/
│   ├── database.php                # Koneksi MySQLi + MODE_DEMO (lokal, TIDAK di-commit)
│   └── database.example.php        # Template konfigurasi (salin menjadi database.php)
├── database/
│   ├── schema.sql                  # DDL terbaru 4 tabel, aman dijalankan ulang
│   ├── seed.sql                    # Data demo (INSERT IGNORE)
│   ├── migrations/                 # Migrasi skema bertahap & idempoten (001-005)
│   ├── tools/                      # setup.bat (Windows) & setup.sh (macOS/Linux)
│   └── antarrasa.sql               # Snapshot lengkap hasil generate (reset total via phpMyAdmin)
├── includes/
│   ├── header.php / footer.php     # Layout adaptif per peran
│   ├── functions_order.php         # Pemesanan, pembayaran, state machine, menu, statistik, paginasi, laporan
│   ├── functions_auth.php          # Autentikasi, sesi, CSRF, rate limiting, mode demo
│   └── functions_admin.php         # Manajemen pengguna admin & pencatatan error database
├── assets/
│   ├── css/style.css               # Design tokens, komponen, responsif (992/768/576px), aksesibilitas
│   ├── js/antarrasa.js             # Feedback submit (spinner & cegah klik ganda), vanilla JS
│   ├── img/                        # Ilustrasi lokal menu per kategori (SVG)
│   └── uploads/                    # Bukti bayar & foto menu (tidak di-commit, dilindungi .htaccess)
├── index.php                       # [Customer] Katalog, kategori, floating checkout bar
├── pesanan.php                     # [Customer] Keranjang & checkout
├── pembayaran.php                  # [Customer] Faktur, metode bayar, bukti bayar, batalkan
├── status.php                      # [Customer] Timeline pelacakan, batalkan, pesan lagi (+ panel simulasi admin)
├── riwayat.php                     # [Semua peran] Riwayat transaksi berpaginasi + rincian item
├── login.php / register.php        # Autentikasi & registrasi customer
├── logout.php                      # Keluar sesi
├── merchant.php                    # [Merchant] Antrean dapur, manajemen menu, menu terlaris
├── driver.php                      # [Driver] Tugas Saya / Tersedia, rekap pengantaran
├── admin.php                       # [Admin] KPI, manajemen pengguna, monitoring transaksi
└── laporan.php                     # [Admin] Laporan transaksi & ekspor CSV
```

---

## 7. Panduan Menjalankan di XAMPP

1. Pastikan seluruh berkas berada di `C:\xampp\htdocs\antarrasa` (macOS/Linux: folder web server Anda).
2. Buka **XAMPP Control Panel**, lalu aktifkan modul **Apache** dan **MySQL** (keduanya *Running*).
3. Buat file konfigurasi lokal dari template (file ini **tidak** di-commit ke git):
   ```
   copy config\database.example.php config\database.php      (Windows)
   cp config/database.example.php config/database.php        (macOS/Linux)
   ```
   Sesuaikan kredensial di dalamnya bila MySQL Anda tidak memakai default XAMPP (`root` tanpa password).
4. Siapkan database dengan skrip setup (membuat database, tabel, migrasi, dan data demo):
   ```
   database\tools\setup.bat                 (Windows / XAMPP)
   sh database/tools/setup.sh               (macOS / Linux)
   ```
   Skrip ini **idempoten**: aman dijalankan ulang setiap kali `git pull` membawa migrasi baru,
   dan **tidak** menghapus atau menimpa data lokal Anda. Tambahkan `--tanpa-seed` jika tidak ingin data demo.
   Kredensial non-default diatur lewat variabel lingkungan, misalnya `DB_USER=root DB_PASS=rahasia sh database/tools/setup.sh`
   (Windows: `set DB_PASS=rahasia` sebelum menjalankan `setup.bat`).
5. Buka peramban di:
   ```
   http://localhost/antarrasa
   ```
6. Kunjungi `http://localhost/antarrasa/login.php` dan masuk dengan salah satu akun demo (atau kartu quick-login saat `MODE_DEMO` aktif).

> **Setelah `git pull`:** jalankan ulang `database/tools/setup` agar migrasi baru diterapkan ke database lokal Anda.
> Jika `config/database.php` hilang setelah pull, salin ulang dari `config/database.example.php` (langkah 3).

### Struktur Folder `database/`

| Berkas | Isi | Kapan dipakai |
|---|---|---|
| `schema.sql` | DDL terbaru (`CREATE TABLE IF NOT EXISTS`), tanpa `CREATE DATABASE` / `DROP TABLE` | Dijalankan otomatis oleh skrip setup |
| `migrations/NNN_*.sql` | Perubahan skema bertahap, idempoten (`ADD COLUMN IF NOT EXISTS`) | Menaikkan versi database lama milik anggota tim |
| `seed.sql` | Data demo (`INSERT IGNORE`, tidak menimpa data yang ada) | Dijalankan otomatis oleh skrip setup |
| `antarrasa.sql` | Snapshot lengkap hasil generate (berisi `DROP TABLE`) | Impor sekali lewat phpMyAdmin bila ingin **reset total** |
| `tools/setup.bat`, `tools/setup.sh` | Skrip setup lintas OS | Setup awal dan setelah setiap `git pull` |

**Aturan perubahan skema untuk tim:**
1. Jangan pernah mengedit file migrasi yang sudah di-push. Buat file baru dengan nomor berikutnya (misal `006_...sql`).
2. Tulis migrasi idempoten (`ADD COLUMN IF NOT EXISTS`, `CREATE INDEX IF NOT EXISTS`, `FOREIGN KEY IF NOT EXISTS`).
3. Perbarui juga `schema.sql` agar mencerminkan kondisi terbaru, lalu perbarui `antarrasa.sql`.
4. Saat membuat ulang dump, **jangan** memakai `mysqldump ... > file.sql` di PowerShell karena redirect `>`
   menghasilkan file **UTF-16** yang tidak bisa diimpor di macOS/Linux. Gunakan opsi `--result-file`:
   ```
   C:\xampp\mysql\bin\mysqldump.exe -u root --databases antarrasa_db --result-file=database/antarrasa.sql
   ```
   Lalu hapus baris pertama `/*M!999999\- enable the sandbox mode */` (ditambahkan MariaDB 10.11+ dan
   ditolak oleh client MySQL di macOS/Linux).

---

## 8. Verifikasi

Belum ada *test suite* otomatis di repository. Setiap perubahan diverifikasi dengan:

```
C:\xampp\php\php.exe -l <nama_file.php>
```

serta skenario manual pada bagian **7. Cara Verifikasi** di `ROADMAP.md` (keamanan, alur fungsional, database lintas OS, dan UI/UX ponsel 375px).
