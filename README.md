# AntarRasa - Sistem Pemesanan & Pengantaran Makanan Online
> **Super-App Modern UI/UX (Gaya GoFood / GrabFood / ShopeeFood)**  
> **Arsitektur 4 DataStore: `MENU`, `PESANAN`, `DETAIL_PESANAN`, `PENGGUNA`**  
> Berdasarkan Dokumen Spesifikasi Kebutuhan Perangkat Lunak (**SRS IEEE Std 830-1998**).

---

## 1. Ikhtisar Sistem & Desain Antarmuka Berbasis Peran (4 Role UI)

**AntarRasa** adalah aplikasi web pemesanan dan pengantaran makanan online modern bergaya super-app kuliner (GoFood / GrabFood / ShopeeFood). Antarmuka pengguna (UI/UX) dirancang khusus dan berbeda total untuk 4 peran pengguna sesuai alur kerja (*jobdesk*) masing-masing:

1. **Customer UI (`index.php`, `pesanan.php`, `pembayaran.php`, `status.php`, `riwayat.php`)**:
   - Palet warna hangat oranye-merah (`#EE4D2D` / `#FF5722`) dipadukan aksen hijau segar (`#00AA13`).
   - *Promo Hero Banner* kuliner nusantara dan *Category Filter Chips* cepat (Semua, Ayam & Bebek, Nasi Goreng, Mie, Sate, Minuman).
   - *Food Card Grid* dengan foto makanan rounded (`object-fit: cover`), badge ketersediaan (*Tersedia* / *Habis*), stepper kuantitas (`-` / `+`), dan *Floating Checkout Bar* mengambang saat keranjang terisi.
   - *Digital Receipt Invoice* dan pilihan metode E-Wallet interaktif (**GoPay**, **ShopeePay**, **OVO**, **QRIS**).
   - *Animated 4-Stage Tracking Timeline* dengan indikator berkedip (*pulsing badge*) untuk memantau status pesanan secara *real-time*.

2. **Merchant Portal UI (`merchant.php`)**:
   - Tema profesional GoBiz / GrabMerchant dengan nuansa Indigo/Navy (`#4F46E5`) dan putih bersih.
   - **Alur Dapur 2 Kolom (Kitchen Workflow)**:
     - **Kolom Kiri (Antrean Dapur)**: Tiket pesanan dapur masuk (`.kitchen-ticket`) menampilkan daftar hidangan yang harus dimasak (dari `DETAIL_PESANAN` D3) dan tombol besar *"Terima & Masak Pesanan (Ubah ke Diproses)"*.
     - **Kolom Kanan (Manajemen Menu Cepat)**: Form tambah hidangan baru ke D1 (`MENU`) dan tabel katalog dengan tombol toggle 1-klik (*Set Habis* &harr; *Set Tersedia*) serta hapus menu.

3. **Driver Courier HUD UI (`driver.php`)**:
   - Tema kurir GoPartner / GrabDriver dengan *high-contrast dark header* (`#1E293B`) dan badge berdenyut **"STATUS: ONLINE - SIAP MENGANTAR"**.
   - **Tugas Aktif (Sedang Diantar / 'Dikirim')**: Kartu pengantaran dengan kotak alamat pengantaran menonjol, daftar pesanan, dan tombol hijau *"Pesanan Sampai - Selesaikan Pengantaran (Ubah ke Selesai)"*.
   - **Tugas Siap Jemput (Status 'Diproses')**: Kartu pesanan matang di dapur resto dengan tombol *"Ambil & Antar Pesanan (Ubah ke Dikirim)"*.
   - Riwayat pengantaran selesai hari ini.

4. **Executive Admin Backoffice UI (`admin.php`)**:
   - Tata letak backoffice eksekutif dengan *Sidebar Navigasi Kiri Gelap* (`#0F172A`) dan area konten modern (`#F8FAFC`).
   - 4 Kartu KPI Metrik (Total Pengguna D4, Total Menu D1, Total Transaksi D2, Total Perputaran/Pendapatan).
   - Panel Manajemen Akun Pengguna D4 lengkap dengan filter pills per peran, modal tambah/edit pengguna, pengganti peran instan (1-klik), reset kata sandi default (`password123`), dan hapus pengguna dengan proteksi Foreign Key.
   - Tabel Monitoring Transaksi Seluruh Ekosistem (D2 JOIN D4).

5. **Split-Screen Authentication (`login.php` & `register.php`)**:
   - Tata letak split-screen modern: Sisi kiri menampilkan branding ekosistem, 4 pilar peran, dan highlight IEEE Std 830-1998; Sisi kanan memuat form login bersih dan **4 Tombol Cepat Akun Demo** untuk berganti peran dalam 1 kali klik.

---

## 2. Arsitektur Basis Data (4 DataStore Terstruktur)

Sistem mengelola data melalui 4 tabel relasional MySQL:
1. **D1: `MENU`** — Menyimpan katalog hidangan makanan & minuman yang ditawarkan merchant.
2. **D2: `PESANAN`** — Menyimpan transaksi pemesanan utama, terhubung dengan foreign key `id_pengguna` ke tabel `PENGGUNA`.
3. **D3: `DETAIL_PESANAN`** — Menyimpan rincian item menu yang dipesan pada setiap transaksi.
4. **D4: `PENGGUNA`** — Menyimpan akun pengguna, kata sandi terenkripsi hash (*bcrypt*), dan hak akses berdasarkan peran (*role-based access control*).

```
   [D4: PENGGUNA] (Customer, Merchant, Driver, Admin)
         │
         ▼ (1 to many)
   [D2: PESANAN] ◄──── (many to 1) ──── [D3: DETAIL_PESANAN] ────► [D1: MENU]
```

---

## 3. Akun Demo Default & Akses Cepat

Semua akun demo menggunakan kata sandi default: **`password123`**

| Peran (Role) | Username | Password | Deskripsi Akses & Dashboard |
| :--- | :--- | :--- | :--- |
| **Customer** | `customer` | `password123` | Katalog makanan GoFood style, stepper kuantitas, keranjang belanja, checkout bergaransi, e-wallet, dan pelacakan animasi 4 tahap. |
| **Merchant** | `merchant` | `password123` | Portal dapur GoBiz style, antrean tiket masak (D3), terima pesanan ke status 'Diproses', tambah menu baru, dan toggle ketersediaan 1-klik. |
| **Driver** | `driver` | `password123` | HUD kurir GoPartner style, indikator status online, ambil pesanan siap jemput ('Diproses' &rarr; 'Dikirim'), dan selesaikan pengantaran ('Dikirim' &rarr; 'Selesai'). |
| **Admin** | `admin` | `password123` | Backoffice eksekutif dengan dark sidebar, 4 KPI stats, manajemen pengguna D4 CRUD penuh, dan pemantauan transaksi D2 JOIN D4. |

> **Fitur Klik-Cepat Login**: Pada [login.php](file:///c:/xampp/htdocs/antarrasa/login.php) telah disediakan 4 kartu klik-cepat akun demo sehingga Anda dapat langsung berganti peran dalam 1 kali klik tanpa perlu mengetik manual.

---

## 4. Batasan Desain & Paradigma Pemrograman (Wajib Prosedural)

Sesuai ketentuan ketat rekayasa perangkat lunak:
1. **Paradigma Murni Terstruktur / Prosedural**:
   - Seluruh kode dibangun **tanpa** konsep Object-Oriented Programming (OOP): **0 class**, **0 object creation (`new`)**, dan **0 inheritance**.
   - Logika bisnis dipusatkan pada fungsi-fungsi prosedural independen di `includes/functions_order.php`, `includes/functions_auth.php`, dan `includes/functions_admin.php`.
2. **Kepatuhan PHP 8 Terbaru**:
   - Baris pertama setiap berkas logika memuat `declare(strict_types=1);`.
   - Menggunakan *parameter type hints* dan *return type declarations* pada seluruh fungsi prosedural.
   - Exception otomatis mysqli dinonaktifkan via `mysqli_report(MYSQLI_REPORT_OFF);` sebelum koneksi dibuat, sehingga penanganan galat murni menggunakan pengecekan prosedural (`mysqli_connect_error()`, `mysqli_error()`).
3. **Keamanan & Sanitasi Output**:
   - Seluruh keluaran data ke HTML dienkapsulasi dengan `htmlspecialchars((string) $input, ENT_QUOTES, 'UTF-8')`.
   - Seluruh kueri data MySQLi menggunakan *Prepared Statements* (`mysqli_prepare`, `mysqli_stmt_bind_param`, `mysqli_stmt_execute`).
   - Kata sandi pengguna dienkripsi menggunakan fungsi hash standar industri `password_hash($password, PASSWORD_DEFAULT)` dan diverifikasi dengan `password_verify()`.

---

## 5. Struktur Berkas Proyek

```
c:\xampp\htdocs\antarrasa\
├── .gitignore                      # Berkas pengecualian Git
├── README.md                       # Dokumentasi sistem, setup XAMPP, dan panduan tim
├── config/
│   ├── database.php                # Koneksi MySQLi prosedural + Auto-Migration DataStore D4
│   └── database.example.php        # Template konfigurasi basis data
├── database/
│   └── antarrasa.sql               # Skema DDL 4 tabel (D1, D2, D3, D4) + data dummy awal
├── includes/
│   ├── header.php                  # Header adaptif peran (Navbar Customer, Portal Merchant, HUD Driver, Sidebar Admin)
│   ├── footer.php                  # Penutup layout adaptif & skrip Bootstrap 5.3
│   ├── functions_order.php         # Modul inti logika pemesanan & manajemen menu
│   ├── functions_auth.php          # Modul autentikasi, registrasi customer, & auto-migration
│   └── functions_admin.php         # Modul manajemen pengguna Admin (CRUD murni MySQL tanpa auto-seed berulang)
├── assets/
│   └── css/
│       └── style.css               # Desain tokens & komponen modern (GoFood / GoBiz / GoPartner / Admin)
├── index.php                       # [Customer] Katalog Menu, Banner Hero, Kategori, & Floating Checkout Bar
├── pesanan.php                     # [Customer] Keranjang & Konfirmasi Checkout Alamat
├── pembayaran.php                  # [Customer] Faktur Digital & Gateway E-Wallet (GoPay, ShopeePay, OVO, QRIS)
├── status.php                      # [Customer] Pelacakan Animasi 4 Tahap + Panel Simulasi Aktor
├── riwayat.php                     # [Customer] Daftar Riwayat Transaksi Pesanan
├── login.php                       # Split-Screen Login + 4 Tombol Cepat Demo
├── register.php                    # Split-Screen Registrasi Customer Baru (D4)
├── logout.php                      # Pengendali Keluar Sesi
├── merchant.php                    # [Merchant] Portal Dapur GoBiz: Antrean Tiket Masak & Manajemen Menu
├── driver.php                      # [Driver] HUD Kurir GoPartner: Ambil Jemputan & Selesaikan Pengantaran
└── admin.php                       # [Admin] Executive Backoffice: Sidebar, KPI Metrik, & CRUD Pengguna D4
```

---

## 6. Panduan Menjalankan di XAMPP

1. Pastikan seluruh berkas berada di `C:\xampp\htdocs\antarrasa`.
2. Buka **XAMPP Control Panel**, lalu aktifkan modul **Apache** dan **MySQL** (keduanya *Running*).
3. Buat database `antarrasa_db` di `http://localhost/phpmyadmin` (impor file `database/antarrasa.sql` atau biarkan skrip migrasi otomatis berjalan).
4. Buka peramban di:
   ```
   http://localhost/antarrasa
   ```
5. Kunjungi `http://localhost/antarrasa/login.php` dan gunakan salah satu tombol demo untuk mengevaluasi alur setiap peran.
