# AntarRasa - Sistem Pemesanan & Pengantaran Makanan Online
> **Prototipe Progres 20% (Core MVP)** berdasarkan Dokumen Spesifikasi Kebutuhan Perangkat Lunak (**SRS IEEE Std 830-1998**).

---

## 1. Ikhtisar Sistem & Keselarasan SRS

**AntarRasa** adalah aplikasi web pemesanan dan pengantaran makanan online yang menghubungkan pelanggan dengan hidangan kuliner nusantara favorit secara cepat, transparan, dan terstruktur.

Pada tahap pengembangan **Progres 20% (Core MVP)**, sistem telah mewujudkan alur kerja fungsional penuh dari hulu ke hilir untuk **Proses 1.0 (Kelola Pesanan)** serta kerangka kerja dasar untuk **Proses 2.0 (Kelola Pembayaran)** dan **Proses 3.0 (Kelola Pengantaran)**:

```
[Katalog Menu (D1)] ➔ [Keranjang & Checkout] ➔ [Rekam Pesanan (D2 & D3)] ➔ [Simulasi Pembayaran] ➔ [Pelacakan Status & Aksi Driver]
   (index.php)              (pesanan.php)              (Status: Menunggu)             (pembayaran.php)                 (status.php)
```

### Pemetaan Spesifikasi Proses (P-Spec):
- **P-Spec 1.1 (Tampilkan Katalog Menu)**: Membaca dan menampilkan seluruh hidangan dari DataStore D1 (`MENU`) lengkap dengan harga, deskripsi, dan status ketersediaan.
- **P-Spec 1.2 (Pilih Menu)**: Pelanggan dapat memilih hidangan dan menentukan kuantitas pesanan.
- **P-Spec 1.3 (Hitung Subtotal & Total Harga)**: Menghitung secara otomatis `$harga * $jumlah` dan akumulasi total seluruh pesanan.
- **P-Spec 1.4 (Validasi Pesanan)**: Memvalidasi ketersediaan menu ('Tersedia'), kuantitas > 0, dan kelengkapan alamat pengiriman sebelum data disimpan.
- **P-Spec 1.5 (Rekam Pesanan)**: Menyimpan transaksi ke DataStore D2 (`PESANAN`) dan detail item ke D3 (`DETAIL_PESANAN`) menggunakan transaksi basis data atomik.
- **Simulasi Proses 2.0 (Kelola Pembayaran)**: Menampilkan tagihan pesanan, simulasi gateway pembayaran digital (GoPay, OVO, ShopeePay, QRIS), dan pembaruan `status_pesanan` menjadi `'Diproses'`.
- **Simulasi Proses 3.0 (Kelola Pengantaran)**: Pelacakan tahapan pesanan secara real-time dan panel simulasi bagi Merchant/Driver untuk memajukan status pesanan menjadi `'Dikirim'` dan `'Selesai'`.

---

## 2. Batasan Desain & Paradigma Pemrograman (Wajib Prosedural)

Sesuai dengan ketentuan ketat pengembangan sistem:
1. **Paradigma Murni Terstruktur / Prosedural**:
   - Seluruh kode dibangun **tanpa** konsep Object-Oriented Programming (OOP): **0 class**, **0 object creation (`new`)**, dan **0 inheritance**.
   - Seluruh logika bisnis dipusatkan pada fungsi-fungsi prosedural independen di `includes/functions_order.php`.
2. **Kepatuhan PHP 8 Terbaru**:
   - Baris pertama setiap berkas logika PHP memuat `declare(strict_types=1);`.
   - Menggunakan *parameter type hints* dan *return type declarations* pada seluruh fungsi prosedural (contoh: `function hitung_subtotal(float $harga, int $jumlah): float`).
   - Exception otomatis mysqli dinonaktifkan via `mysqli_report(MYSQLI_REPORT_OFF);` sebelum koneksi dibuat, sehingga penanganan galat murni menggunakan pengecekan prosedural (`mysqli_connect_error()`, `mysqli_error()`).
3. **Keamanan & Sanitasi Output**:
   - Seluruh keluaran data ke HTML dienkapsulasi dengan `htmlspecialchars((string) $input, ENT_QUOTES, 'UTF-8')`.
   - Seluruh manipulasi data MySQLi menggunakan *Prepared Statements* (`mysqli_prepare`, `mysqli_stmt_bind_param`, `mysqli_stmt_execute`).
4. **Konsistensi DataStore (Tepat 3 Tabel Sesuai ERD)**:
   - Hanya menggunakan 3 tabel utama: `MENU` (D1), `PESANAN` (D2), dan `DETAIL_PESANAN` (D3).
   - Tanpa tabel tambahan untuk pembayaran atau kurir; siklus hidup transaksi dipantau langsung melalui kolom `status_pesanan` pada tabel `PESANAN` (`Menunggu` &rarr; `Diproses` &rarr; `Dikirim` &rarr; `Selesai`).

---

## 3. Struktur Berkas Proyek

```
c:\xampp\htdocs\antarrasa\
├── .gitignore                      # Berkas pengecualian Git
├── README.md                       # Dokumentasi sistem, setup XAMPP, dan panduan tim
├── config/
│   ├── database.php                # Konfigurasi koneksi basis data MySQLi prosedural
│   └── database.example.php        # Template konfigurasi basis data
├── database/
│   └── antarrasa.sql               # Skema DDL 3 tabel + 6 data awal menu makanan
├── includes/
│   ├── header.php                  # Header & navigasi bersama (HTML5, Bootstrap 5, Stepper)
│   ├── footer.php                  # Footer bersama & skrip JS
│   └── functions_order.php         # Modul inti logika bisnis prosedural
├── assets/
│   └── css/
│       └── style.css               # Gaya visual kustom (tema kuliner modern, desktop 1280x720)
├── index.php                       # [P-Spec 1.1 & 1.2] Katalog Menu & Tambah ke Keranjang
├── pesanan.php                     # [P-Spec 1.3 - 1.5] Keranjang, Perhitungan, & Checkout D2/D3
├── pembayaran.php                  # [Proses 2.0] Tagihan & Simulasi Payment Gateway
├── status.php                      # [Proses 3.0] Pelacakan Status & Aksi Simulasi Driver/Merchant
└── riwayat.php                     # Pemantauan seluruh transaksi pesanan di D2
```

---

## 4. Panduan Instalasi di XAMPP

### Prasyarat:
- Komputer dengan sistem operasi Windows dan paket **XAMPP** terinstal di `C:\xampp`.
- PHP versi 8.x (PHP 8.1 / 8.2 / 8.3 / 8.5).
- Web Server Apache dan Database MySQL/MariaDB.

### Langkah 1: Penempatan Berkas
Pastikan seluruh berkas repositori ini berada pada direktori:
```
C:\xampp\htdocs\antarrasa
```

### Langkah 2: Jalankan Apache dan MySQL
1. Buka aplikasi **XAMPP Control Panel**.
2. Pada baris **Apache**, klik tombol **Start**.
3. Pada baris **MySQL**, klik tombol **Start**.
4. Pastikan kedua modul berwarna hijau dan berstatus *Running*.

### Langkah 3: Impor Basis Data lewat phpMyAdmin
1. Buka browser web Anda dan akses:
   ```
   http://localhost/phpmyadmin
   ```
2. Pada menu navigasi atas, klik tab **Import** (atau buat database baru bernama `antarrasa_db`).
3. Klik tombol **Choose File / Pilih File**, lalu arahkan ke:
   ```
   C:\xampp\htdocs\antarrasa\database\antarrasa.sql
   ```
4. Gulir ke bawah dan klik tombol **Import / Kirim**.
5. Pesan sukses akan muncul dan basis data `antarrasa_db` dengan 3 tabel (`MENU`, `PESANAN`, `DETAIL_PESANAN`) beserta data dummy awal siap digunakan.

*(Alternatif via Command Prompt)*:
```cmd
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\antarrasa\database\antarrasa.sql
```

### Langkah 4: Konfigurasi Basis Data
Periksa berkas `config/database.php`. Secara default, kredensial sudah disesuaikan dengan standar instalasi XAMPP:
- Host: `localhost`
- User: `root`
- Password: *(kosong)*
- Database: `antarrasa_db`
- Port: `3306`

### Langkah 5: Buka Aplikasi
Buka peramban (browser) Anda dan kunjungi tautan:
```
http://localhost/antarrasa
```

---

## 5. Skenario Pengujian Alur Transaksi (End-to-End)

Untuk mendemonstrasikan prototipe progres 20% secara komprehensif:

1. **Jelajahi Katalog Menu (`index.php`)**:
   - Amati 6 hidangan kuliner nusantara yang diambil dari DataStore D1 (`MENU`).
   - Menu berstatus **Habis** (misal: *Es Cendol Durian Segar*) memiliki tombol yang dinonaktifkan.
   - Pilih hidangan yang **Tersedia** (misal: 2x *Nasi Goreng Spesial*, 1x *Ayam Bakar Madu Taliwang*), atur kuantitas, lalu klik tombol tambah ke keranjang.
2. **Checkout & Pengisian Alamat (`pesanan.php`)**:
   - Klik tombol **Lihat Keranjang / Checkout**.
   - Periksa kebenaran perkalian subtotal dan akumulasi total harga.
   - Isi form alamat pengiriman, misal: `Jl. Sudirman No. 45, Kebayoran Baru, Jakarta Selatan`.
   - Klik tombol **Buat Pesanan Sekarang**.
   - Sistem akan memvalidasi data dan merekam transaksi atomik ke D2 (`PESANAN`) dan D3 (`DETAIL_PESANAN`).
3. **Simulasi Pembayaran Digital (`pembayaran.php`)**:
   - Sistem membuka rincian tagihan faktur pesanan yang berstatus awal `'Menunggu'`.
   - Pilih metode pembayaran (misal: **GoPay** atau **QRIS**).
   - Klik **Konfirmasi Simulasi Pembayaran**.
   - Kolom `status_pesanan` pada D2 akan otomatis diperbarui menjadi `'Diproses'`.
4. **Pelacakan Pengantaran & Kontrol Driver (`status.php`)**:
   - Status pesanan kini berada pada tahap **'Diproses Restoran'**.
   - Pada panel pengujian **Simulasi Aktor (Merchant & Driver)**, klik tombol:
     - **"Simulasi: Driver Berangkat"** &rarr; status berubah menjadi `'Dikirim'`.
     - **"Simulasi: Pesanan Tiba"** &rarr; status berubah menjadi `'Selesai'`.
   - Timeline pelacakan akan otomatis bereaksi dan menampilkan seluruh tahapan sukses.
5. **Pemantauan Riwayat (`riwayat.php`)**:
   - Buka menu **Riwayat Pesanan** di navbar atas untuk melihat seluruh daftar riwayat pemesanan yang tercatat di tabel `PESANAN` (D2).

---

## 6. Panduan Kolaborasi Tim (Git Workflow)

Untuk menjaga integritas kode dan keteraturan kolaborasi tim pengembang:

### 1. Standar Branching:
- `main` : Kode produksi yang stabil dan siap dipresentasikan.
- `development` : Integrasi fitur yang sedang dikembangkan.
- `feature/[nama-fitur]` : Cabang pengerjaan fitur individual (contoh: `feature/katalog-menu`, `feature/checkout-pesanan`).

### 2. Alur Kerja Kontributor:
1. Pastikan Anda berada di branch utama terbaru:
   ```bash
   git checkout main
   git pull origin main
   ```
2. Buat branch baru untuk tugas Anda:
   ```bash
   git checkout -b feature/nama-fitur
   ```
3. Lakukan pengujian sintaks PHP sebelum melakukan commit:
   ```powershell
   & "C:\xampp\php\php.exe" -l index.php
   & "C:\xampp\php\php.exe" -l pesanan.php
   ```
4. Format pesan commit terstruktur:
   - `feat:` Penambahan fungsi baru
   - `fix:` Perbaikan bug / validasi
   - `docs:` Pembaruan dokumentasi README
   - `style:` Penyesuaian CSS / tampilan UI
   - `refactor:` Peningkatan struktur kode tanpa mengubah fungsi
   *(Contoh: `git commit -m "feat: implementasi validasi pesanan prosedural P-Spec 1.4"`)*
5. Dorong branch ke remote repository dan ajukan **Pull Request (PR)**:
   ```bash
   git push origin feature/nama-fitur
   ```
