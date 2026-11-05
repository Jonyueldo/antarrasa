# ROADMAP Pengembangan AntarRasa

> **Tanggal audit:** 4 November 2026
> **Cakupan:** 18 file PHP (5.589 baris) + `assets/css/style.css` + `database/antarrasa.sql`
> **Basis commit:** `69ea293` — *feat: finalisasi UI 4 role, CRUD admin, dan sinkronisasi data asli database*
> **Runtime terverifikasi:** PHP 8.5.12 (ZTS, x64) di `C:\xampp\php\php.exe`, MariaDB 10.11, database `antarrasa_db`
> **Audit rinci pendukung:** `docs/ROADMAP.md`

Dokumen ini adalah **satu-satunya daftar tugas resmi** proyek. Setiap poin dikerjakan satu per satu,
diverifikasi dengan `php -l`, lalu ditandai `[x]` dan di-commit secara atomic (lihat `CLAUDE.md` bagian 3 dan 4).

---

## Ringkasan Kondisi Saat Ini

**Yang sudah benar dan wajib dipertahankan:**

- Seluruh akses database memakai **prepared statement** — tidak ditemukan satu pun konkatenasi input ke SQL. **Tidak ada SQL Injection.**
- Password di-hash dengan `password_hash()`.
- Hampir seluruh output HTML dibungkus helper `e()`.
- Arsitektur 100% prosedural: 0 `class`, 0 framework, `declare(strict_types=1);` di seluruh file.
- Skema sudah memakai InnoDB + utf8mb4, `DECIMAL(12,2)` untuk uang, FK lengkap, `username` UNIQUE, dan `CHECK` pada `peran` serta `jumlah > 0`.

**Yang menjadi masalah:** 4 celah yang bisa dieksploitasi **tanpa login sama sekali**, tidak adanya
proteksi CSRF, bug logika yang membuat tampilan salah secara faktual, dan file dump database yang
**tidak bisa diimpor di macOS/Linux**.

---

## 1. Temuan Audit (semua sudah diverifikasi langsung di kode)

### 1.1 Celah Keamanan

| # | Tingkat | Lokasi | Temuan |
|---|---|---|---|
| **S1** | Kritis | `includes/functions_auth.php:189` | `password_verify(...)` di-OR dengan `($password === $user['password'])` — fallback password plaintext meloloskan verifikasi hash |
| **S2** | Kritis | `riwayat.php:16-21` | Tidak ada `cek_akses()`. Saat peran bukan `customer` (termasuk **tamu tanpa login**) nilai `$filter_id_pengguna` jadi `null` sehingga seluruh pesanan semua pelanggan beserta alamat lengkap terbuka |
| **S3** | Kritis | `status.php:24-37` | Blok POST `aksi_status` dieksekusi **sebelum** autentikasi apa pun, jadi siapa pun bisa mengubah status pesanan mana pun |
| **S4** | Kritis | `pembayaran.php:15-47` | Tidak ada verifikasi pemilik. Parameter `?id=` sembarang menampilkan nama dan alamat pemesan lain, dan POST `bayar_pesanan` melunasi pesanan orang lain |
| **S5** | Tinggi | seluruh form POST | **Tidak ada CSRF token sama sekali** (`grep -r csrf` memberi 0 hasil). Paling berisiko: ganti peran dan hapus pengguna di `admin.php`, serta hapus menu di `merchant.php` |
| **S6** | Tinggi | `login.php:19` | `str_starts_with($ref, '/')` meloloskan URL protocol-relative seperti `//evil.com/x`, sehingga terjadi **open redirect** setelah login sukses |
| **S7** | Tinggi | `functions_auth.php:18,310`, `functions_order.php:672` | Tiga pemanggilan `session_start()` terpisah; **tidak ada** `session_regenerate_id(true)` (session fixation), tidak ada `httponly`/`samesite`, `logout_pengguna():307` hanya `unset()` tanpa `session_destroy()`, dan tidak ada idle timeout |
| **S8** | Tinggi | `login.php:79-88` | `quick_demo` login sebagai akun pertama peran apa pun dengan password tetap `password123` dari halaman login **publik**, jadi siapa pun bisa jadi admin pada instalasi default |
| **S9** | Sedang | `functions_admin.php:129,152,159,222,249,257,268,333` | `mysqli_error($conn)` ditampilkan ke pengguna sehingga membocorkan struktur tabel dan versi database |
| **S10** | Sedang | `login.php:75-116` | Tidak ada rate limit atau lockout, jadi brute force bebas |
| **S11** | Sedang | `config/database.php`, `.gitignore:6` | Kredensial database **ter-track di git** (dipastikan lewat `git ls-files`); baris `config/database.php` di `.gitignore` masih dikomentari sehingga tidak pernah aktif |
| **S12** | Sedang | `admin.php:87-106`, `functions_admin.php:272-282` | Tidak ada proteksi admin terakhir, dan admin bisa menurunkan peran dirinya sendiri sehingga sistem bisa kehilangan administrator |
| **S13** | Rendah | `index.php:128,138` | `$pesan_sukses` dan `$pesan_peringatan` dicetak tanpa `e()` |

### 1.2 Bug Logika dan Inkonsistensi

| # | Lokasi | Temuan |
|---|---|---|
| **L1** | `functions_order.php:139,162,533` | `return $sukses && ($affected >= 0)` membuat `affected_rows == 0` tetap dianggap sukses, sehingga update untuk `id` yang tidak ada dilaporkan berhasil |
| **L2** | `status.php:27`, `driver.php:28` | **Tidak ada state machine.** Status bisa mundur (`Selesai` ke `Menunggu`) atau melompat (`Menunggu` langsung `Selesai` tanpa dibayar) |
| **L3** | `status.php:65-66` | `ambil_driver_aktif($conn)` dan `ambil_merchant_aktif($conn)` dipanggil **tanpa argumen** padahal signature-nya menerima `?int $id`, jadi selalu mengembalikan ID terkecil dan **setiap** pesanan tampil diantar kurir yang sama. Akar masalah: tabel `pesanan` tidak punya kolom `id_driver` |
| **L4** | `pembayaran.php:26,36` | `metode_bayar` hanya dilempar lewat query string lalu hilang, **tidak pernah disimpan**, sehingga tidak ada jejak pembayaran di database |
| **L5** | `functions_order.php:652` | Total Perputaran dihitung dari status yang bisa dibolak-balik bebas (L2), jadi angkanya tidak dapat dipercaya, dan selalu all-time tanpa batas tanggal |
| **L6** | `merchant.php:103`, `driver.php:43` | Label "Tuntas Hari Ini" sebenarnya menghitung seluruh pesanan selesai dalam 40 atau 15 baris terakhir, bukan hari ini |
| **L7** | `merchant.php:24-81`, `driver.php:25-38` | Tidak ada pola PRG, jadi refresh browser mengirim ulang POST dan menghasilkan menu ganda atau update status berulang (`admin.php` dan `pesanan.php` sudah benar) |
| **L8** | `merchant.php:43` vs `functions_order.php:84` | UI menolak `harga <= 0` tetapi fungsinya menerima `harga == 0`, jadi validasi tidak konsisten antar lapisan |
| **L9** | `functions_order.php:284-369` | `simpan_pesanan()` tidak membaca ulang harga dan ketersediaan **di dalam** transaksi; harga diambil dari snapshot awal request |
| **L10** | `functions_order.php:244-247` | Jika satu item berubah jadi `Habis`, **seluruh** checkout gagal dan item itu tidak dibuang otomatis |
| **L11** | `merchant.php:228`, `driver.php:144,229` | **N+1 query:** `ambil_detail_pesanan()` dipanggil di dalam loop |
| **L12** | `config/database.php:72-74` | `inisialisasi_tabel_pengguna()` jalan di **setiap request**, menjalankan `CREATE TABLE IF NOT EXISTS`, `SHOW COLUMNS`, `ALTER TABLE`, dan `COUNT(*)` per pageview |
| **L13** | `functions_order.php:149-163`, `merchant.php:78` | `hapus_menu()` mengembalikan `false` baik saat menu tidak ada maupun saat terblokir FK, sehingga pesan error selalu menuduh menu sudah punya riwayat transaksi |
| **L14** | `database/antarrasa.sql` vs seluruh kode | Dump membuat tabel **huruf kecil** (`pengguna`, `menu`, `pesanan`, `detail_pesanan`) sedangkan kode mengquery **huruf besar** pada 28 query (`FROM PENGGUNA`, `FROM PESANAN`, dan seterusnya). Jalan di Windows karena `lower_case_table_names=1`, tetapi **gagal di macOS/Linux** |
| **L15** | `riwayat.php:21`, `admin.php:152`, `merchant.php:85` | `LIMIT` hardcoded (50, 30, 40) tanpa paginasi, jadi data terpotong diam-diam saat bertambah |

### 1.3 Database dan Kolaborasi Tim

| # | Lokasi | Temuan |
|---|---|---|
| **D1** | `database/antarrasa.sql` | **File tersimpan sebagai UTF-16 LE dengan BOM** (hasil redirect `mysqldump` di PowerShell). Perintah `mysql < antarrasa.sql` **gagal total di macOS/Linux** dan diff git tidak terbaca manusia. Ini penghambat kolaborasi paling langsung |
| **D2** | `antarrasa.sql:123-133` | Dump berisi **hash password asli anggota tim** yang ter-commit ke repository |
| **D3** | `antarrasa.sql` | **Indeks hilang** pada kolom yang difilter dan di-sort hampir di semua dashboard: `pesanan.status_pesanan`, `pesanan.tanggal_pesanan`, `pengguna.peran`, `menu.status_menu` |
| **D4** | `antarrasa.sql:105-114` vs `:139-149` | `pengguna.peran` punya `CHECK`, tetapi `pesanan.status_pesanan` dan `menu.status_menu` tidak |
| **D5** | `antarrasa.sql:34-46`, `functions_order.php:419` | `detail_pesanan` menyimpan `subtotal` tetapi **tidak** menyimpan harga satuan saat pesan, dan `ambil_detail_pesanan()` join ke `menu.harga` yang berisi harga **sekarang**, jadi faktur lama tidak bisa direkonstruksi |
| **D6** | `antarrasa.sql:72-79` | Tabel `menu` tidak punya `id_merchant`, `kategori`, `gambar_url`, maupun `is_aktif` |
| **D7** | `antarrasa.sql:139-149` | Tabel `pesanan` tidak punya `id_driver`, `metode_bayar`, `waktu_bayar`, `bukti_bayar`, maupun timestamp per tahap |
| **D8** | `antarrasa.sql:23` vs `:46,79,114,149` | Collation database `utf8mb4_general_ci` sedangkan tabel `utf8mb4_unicode_ci`, jadi tidak seragam |
| **D9** | `antarrasa.sql:31,69,102,136` | Satu file mencampur `CREATE DATABASE`, `DROP TABLE`, skema, dan data, sehingga setiap impor **menimpa data lokal anggota lain** |

### 1.4 UI/UX dan Responsivitas

| # | Lokasi | Temuan |
|---|---|---|
| **U1** | `assets/css/style.css:727` | Hanya ada **satu** media query (`max-width: 991.98px`), tidak ada breakpoint ponsel di bawah 768px sama sekali |
| **U2** | `index.php:200,277` | `.floating-checkout-bar` menutup konten di layar pendek, dan `pb-5` tidak cukup |
| **U3** | `admin.php:319`, `riwayat.php:84` | Tabel 6 sampai 7 kolom hanya mengandalkan scroll horizontal di ponsel |
| **U4** | `assets/css/style.css` | Sidebar admin menyusut jadi rail 70px di bawah 992px, tanpa hamburger atau off-canvas |
| **U5** | `functions_order.php:874` | `dapatkan_gambar_menu()` menebak foto dari substring nama dan **hotlink ke Unsplash**, jadi menu baru selalu dapat foto generik dan saat offline semua gambar rusak |
| **U6** | `admin.php:405,419` | Tombol ikon-saja hanya ber-`title`, tanpa `aria-label`, dan tidak ada focus ring kustom |
| **U7** | `pesanan.php:221-230` | Setiap tombol tambah dan kurang di keranjang memicu POST dan reload halaman penuh tanpa feedback |
| **U8** | `index.php:254` | Batas `max="50"` hanya divalidasi di sisi klien |

---

## 2. Keputusan Desain yang Disepakati

1. **Skema:** hanya **tambah kolom** pada 4 tabel yang ada. Tidak ada tabel kelima, sehingga dokumen SRS dan DFD tidak perlu revisi besar.
2. **Fitur demo:** panel Simulasi Status Pengujian dan 4 tombol quick-login **dipertahankan** karena dibutuhkan saat demo atau sidang, tetapi **dikunci** di belakang flag `MODE_DEMO` ditambah pembatasan peran admin.
3. **Paradigma:** tidak ada OOP, tidak ada framework, tidak ada Composer. Semua perbaikan berupa fungsi prosedural baru di `includes/`.
4. **Nama tabel:** diseragamkan ke **huruf kecil** di DDL maupun kode agar identik di Windows, macOS, dan Linux.

---

## 3. PRIORITAS TINGGI — Perbaikan Kritis

- [x] **1. Hapus fallback password plaintext** (S1) — buang bagian `|| ($password === $user['password'])` di `includes/functions_auth.php:189`.
- [x] **2. Kunci `riwayat.php`** (S2) — tambah `cek_akses(['customer','merchant','driver','admin'])`, dan untuk peran `customer` **paksa** filter `id_pengguna` miliknya sendiri, jangan bergantung pada nilai `null`.
- [x] **3. Kunci transisi status di `status.php`** (S3) — pindahkan blok POST ke bawah `cek_akses()`, dan izinkan hanya saat `MODE_DEMO` aktif **dan** peran `admin`. Panel simulasi (`status.php:230-276`) dirender dengan syarat yang sama.
- [x] **4. Verifikasi pemilik pesanan** (S4) — buat helper `boleh_akses_pesanan(?array $pengguna, array $pesanan): bool` di `functions_order.php` (customer hanya boleh miliknya, merchant/driver/admin boleh), lalu pakai di `pembayaran.php:52` dan `status.php:41`.
- [x] **5. Tambah proteksi CSRF** (S5) — buat `token_csrf(): string` dan `cek_csrf(): bool` di `functions_auth.php`. Sisipkan input hidden `csrf_token` (nilainya dibungkus `e()`) di **semua** form POST pada `index.php`, `pesanan.php`, `pembayaran.php`, `status.php`, `merchant.php`, `driver.php`, `admin.php`, `login.php`, dan `register.php`, lalu validasi di awal setiap handler.
- [x] **6. Perketat session** (S7) — panggil `session_regenerate_id(true)` setelah login dan registrasi sukses, set `session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax'])` sebelum `session_start()`, panggil `session_destroy()` plus hapus cookie di `logout_pengguna()`, dan terapkan idle timeout 60 menit. Satukan ketiga pemanggilan `session_start()` ke `mulai_sesi()`.
- [x] **7. Tutup open redirect** (S6) — di `login.php:19` tolak `ref` yang dimulai dengan dua garis miring atau mengandung tanda titik dua; cukup izinkan daftar putih nama file `.php` lokal.
- [x] **8. Kunci mode demo** (S8) — tambah konstanta `MODE_DEMO` di `config/database.example.php` dengan default `false` di luar localhost. Blok `quick_demo` (`login.php:79-88`) dan kartu demo hanya jalan saat `MODE_DEMO === true`.
- [x] **9. Perbaiki nilai balik `affected_rows`** (L1) — di `update_status_pesanan()` dan `ubah_status_menu()`, periksa eksistensi baris lebih dulu lalu uji `$affected > 0`.
- [x] **10. Berhenti membocorkan error database** (S9) — ganti `mysqli_error($conn)` di 8 lokasi `functions_admin.php` dengan pesan umum, dan kirim detailnya ke `error_log()`.
- [x] **11. Proteksi admin terakhir dan diri sendiri** (S12) — di `functions_admin.php`, tolak perubahan peran atau penghapusan bila akan menyisakan 0 admin, dan tolak admin menurunkan peran dirinya sendiri.
- [x] **12. Escape output yang terlewat** (S13) — bungkus `$pesan_sukses` dan `$pesan_peringatan` dengan `e()` di `index.php:128,138`, lalu hentikan pemanggilan `e()` di hulu agar tidak dobel.
- [x] **13. Untrack kredensial** (S11) — aktifkan baris `config/database.php` di `.gitignore:6`, jalankan `git rm --cached config/database.php`, dan lengkapi `config/database.example.php`.
- [x] **14. Konversi dump SQL ke UTF-8** (D1) — simpan ulang `database/antarrasa.sql` sebagai UTF-8 tanpa BOM dengan akhir baris LF. Ini **prasyarat mutlak** agar anggota tim macOS/Linux bisa mengimpor sama sekali. Tambahkan catatan di `README.md`: jangan memakai `mysqldump > file.sql` di PowerShell karena menghasilkan UTF-16, gunakan `mysqldump --result-file=file.sql`.
- [x] **15. Seragamkan nama tabel huruf kecil** (L14) — ubah 28 query di `functions_order.php`, `functions_auth.php`, `functions_admin.php`, dan `login.php:47` dari huruf besar ke huruf kecil. **Prasyarat kolaborasi macOS.**
- [x] **16. Buang hash password asli dari dump** (D2) — ganti seluruh baris `INSERT INTO pengguna` dengan akun demo yang hash-nya dibuat ulang khusus untuk repository.

## 4. PRIORITAS MENENGAH — Fitur Inti dan Konsistensi

- [x] **17. Migrasi skema kolom baru** (D5, D6, D7) — buat `database/migrations/002` sampai `004`:
      `menu` ditambah `kategori VARCHAR(30)`, `gambar_url VARCHAR(255)`, `is_aktif TINYINT(1) DEFAULT 1`, `id_merchant INT NULL` (FK ke `pengguna`);
      `pesanan` ditambah `id_driver INT NULL` (FK), `metode_bayar VARCHAR(20) NULL`, `waktu_bayar DATETIME NULL`, `bukti_bayar VARCHAR(255) NULL`, serta `waktu_diproses`, `waktu_dikirim`, dan `waktu_selesai DATETIME NULL`;
      `detail_pesanan` ditambah `harga_satuan DECIMAL(12,2) NOT NULL DEFAULT 0`.
- [x] **18. Tambah indeks dan CHECK** (D3, D4) — buat `database/migrations/001_add_indexes.sql` berisi indeks pada `pesanan.status_pesanan`, `pesanan.tanggal_pesanan`, `pengguna.peran`, dan `menu.status_menu`, plus `CHECK` untuk `status_pesanan` dan `status_menu`.
- [x] **19. Pisah file database dan skrip setup** (D8, D9) — buat `database/schema.sql` (DDL saja, tanpa `CREATE DATABASE` dan tanpa `DROP TABLE`), `database/seed.sql` (data demo saja), `database/tools/setup.bat` untuk Windows/XAMPP, dan `database/tools/setup.sh` untuk macOS/Linux. Seragamkan collation ke `utf8mb4_unicode_ci`. Migrasi ditulis **idempoten** dengan `ADD COLUMN IF NOT EXISTS` dan **tidak pernah diedit setelah di-push**. Perbarui bagian setup di `README.md`.
- [x] **20. Hapus auto-migration runtime** (L12) — buang pemanggilan `inisialisasi_tabel_pengguna($conn)` dari `config/database.php:74` dan `database.example.php:38`, lalu hapus fungsinya dari `functions_auth.php:35-93`. Ganti dengan pesan "jalankan `database/tools/setup`" pada layar error koneksi yang sudah ada.
- [x] **21. State machine status pesanan** (L2) — buat `transisi_status_valid(string $dari, string $ke): bool` di `functions_order.php`. Alur sah: `Menunggu` ke `Diproses` ke `Dikirim` ke `Selesai`, ditambah `Menunggu` ke `Dibatalkan`. Panggil di dalam `update_status_pesanan()` agar `merchant.php`, `driver.php`, dan `status.php` terlindungi sekaligus.
- [x] **22. Simpan data pembayaran** (L4) — `pembayaran.php` menulis `metode_bayar` dan `waktu_bayar`, lalu tambah upload `bukti_bayar` opsional dengan validasi MIME dan ukuran, disimpan ke `assets/uploads/bukti/` memakai nama file yang **di-generate**, bukan nama asli.
- [x] **23. Catat driver dan merchant sebenarnya** (L3) — di `driver.php`, saat transisi `Diproses` ke `Dikirim`, set `pesanan.id_driver` ke id driver yang login. Di `status.php:65-66`, panggil `ambil_driver_aktif($conn, (int) $pesanan['id_driver'])`. Lakukan hal yang sama untuk merchant.
- [x] **24. Harga satuan historis** (D5) — `simpan_pesanan()` menulis `harga_satuan`, dan `ambil_detail_pesanan()` membaca `d.harga_satuan` alih-alih `m.harga` agar faktur lama tetap akurat.
- [x] **25. Terapkan PRG di merchant dan driver** (L7) — bungkus keempat handler `merchant.php:24-81` dan handler `driver.php:25-38` dengan `header("Location: ...")` plus `exit`, dan kirim pesan lewat query string mengikuti pola `admin.php:60`.
- [x] **26. Kategori menu nyata** (U5) — isi `menu.kategori` lewat form merchant, lalu ganti pencocokan substring nama di `index.php:63-70` dengan filter SQL berparameter di `ambil_semua_menu()`. Saat ini chip "Ayam & Bebek" tidak menemukan "Bebek Goreng".
- [x] **27. Edit menu dan soft delete** (L13) — buat `ubah_menu_makanan()` dan `set_aktif_menu()`, lalu ganti `hapus_menu()` menjadi soft delete (`is_aktif = 0`) sehingga menu yang pernah terjual tetap bisa dihapus dari katalog tanpa melanggar FK. `ambil_semua_menu()` menyaring `is_aktif = 1` untuk katalog pelanggan.
- [x] **28. Timestamp per tahap dan timeline akurat** — isi `waktu_diproses`, `waktu_dikirim`, dan `waktu_selesai` di `update_status_pesanan()`, lalu tampilkan di timeline `status.php:154-225`.
- [x] **29. Validasi pesanan di dalam transaksi** (L9, L10) — `simpan_pesanan()` membaca ulang harga dan `status_menu` di dalam `mysqli_begin_transaction()`. `validasi_pesanan()` membuang item `Habis` dengan pesan informatif, bukan menggagalkan seluruh checkout.
- [x] **30. Samakan validasi harga** (L8) — `tambah_menu_makanan()` menolak `harga <= 0` agar sejalan dengan `merchant.php:43`.
- [x] **31. Perbaiki label statistik** (L5, L6) — tambah parameter rentang tanggal pada `hitung_statistik_admin()`, lalu hitung "hari ini" dengan `DATE(tanggal_pesanan) = CURDATE()` di `merchant.php` dan `driver.php`, atau perbaiki labelnya agar jujur.
- [x] **32. Hilangkan N+1 query** (L11) — buat `ambil_detail_pesanan_massal($conn, array $id_pesanan): array` memakai `WHERE id_pesanan IN (...)` dengan placeholder dinamis, lalu pakai di `merchant.php:228` dan `driver.php:144,229`.

## 5. PRIORITAS TAMBAHAN — Polish dan Fitur Pelengkap

- [x] **33. Responsif ponsel** (U1 sampai U4) — tambah breakpoint 576px dan 768px, ubah tabel jadi kartu bertumpuk memakai `display:block` plus atribut `data-label` pada sel, ubah sidebar admin jadi Bootstrap Offcanvas (bundle JS sudah tersedia di `footer.php:57`), dan perbaiki overlap floating checkout bar.
- [x] **34. Gambar menu dari database** (U5) — pakai `menu.gambar_url` plus placeholder lokal di `assets/img/`, jadikan `dapatkan_gambar_menu()` fallback terakhir saja, dan hentikan hotlink Unsplash.
- [x] **35. Aksesibilitas** (U6) — tambah `aria-label` pada semua tombol ikon, buat `:focus-visible` yang terlihat, dan periksa kontras badge.
- [x] **36. Paginasi** (L15) — paginasi prosedural dengan `LIMIT ? OFFSET ?` di `riwayat.php`, `admin.php`, dan `merchant.php`.
- [x] **37. Klem jumlah di server** (U8) — batasi kuantitas 1 sampai 50 di dalam `tambah_ke_keranjang()`, tidak hanya lewat atribut `max` di HTML.
- [ ] **38. Feedback submit keranjang** (U7) — buat tombol `disabled` plus spinner saat submit memakai JavaScript vanilla kecil, tanpa framework.
- [ ] **39. Rate limiting login** (S10) — pembatas percobaan berbasis session plus jeda, tanpa tabel baru.
- [ ] **40. Laporan transaksi admin dan ekspor CSV** — filter rentang tanggal dan status, lalu ekspor memakai `header('Content-Type: text/csv')`.
- [ ] **41. Batalkan dan pesan ulang** — transisi `Menunggu` ke `Dibatalkan` oleh customer pemilik, serta tombol "Pesan Lagi" yang mengisi ulang keranjang dari `detail_pesanan`. Tambahkan status `Dibatalkan` **hanya** di `dapatkan_info_status()`.
- [ ] **42. Menu terlaris dan pendapatan driver** — agregasi `SUM(jumlah) GROUP BY id_menu` untuk merchant, dan total pengantaran per `id_driver` untuk driver.
- [ ] **43. Klaim tugas eksklusif driver** — sembunyikan pesanan yang `id_driver`-nya sudah terisi driver lain, dan sediakan tab "Tugas Saya" versus "Tersedia".
- [ ] **44. Detail per-item di riwayat** — tampilkan rincian item di `riwayat.php`, bukan hanya total.
- [ ] **45. Perbarui dokumentasi** — di `README.md`: tulis langkah setup baru, selaraskan klaim keamanan dengan kondisi sebenarnya, dan pastikan versi PHP yang ditulis sesuai runtime terverifikasi yaitu PHP 8.5.12.

---

## 6. File Kritis dan Fungsi yang Harus Dipakai Ulang

| File | Peran dalam rencana ini |
|---|---|
| `includes/functions_auth.php` | Login, session, CSRF (tugas 1, 5, 6, 20) |
| `includes/functions_order.php` | State machine, kepemilikan pesanan, harga historis, batch query (tugas 4, 9, 21, 23, 24, 27, 29, 32) |
| `includes/functions_admin.php` | Proteksi admin terakhir, kebocoran error (tugas 10, 11) |
| `config/database.php` dan `config/database.example.php` | Flag `MODE_DEMO`, hapus auto-migration (tugas 8, 13, 20) |
| `status.php`, `pembayaran.php`, `riwayat.php` | Perbaikan 4 celah kritis (tugas 2, 3, 4) |
| `merchant.php`, `driver.php` | PRG, pencatatan driver, CRUD menu (tugas 23, 25, 27, 32) |
| `admin.php` | CSRF pada aksi destruktif, laporan (tugas 5, 40) |
| `database/` | Konversi UTF-8 dan restrukturisasi schema, seed, migrations, tools (tugas 14, 16, 17, 18, 19) |
| `assets/css/style.css` | Responsivitas ponsel (tugas 33) |

**Jangan membuat ulang fungsi yang sudah ada.** Gunakan yang tersedia agar gaya kode tetap konsisten:

| Fungsi | Lokasi | Kegunaan |
|---|---|---|
| `e()` | `functions_order.php:804` | Escaping output untuk seluruh HTML baru |
| `cek_akses(array $peran, string $redirect)` | `functions_auth.php:137` | RBAC. Sudah dipakai benar di `admin.php:18`, `merchant.php:17`, dan `driver.php:18`; **pola inilah** yang ditiru di `riwayat.php`, `status.php`, dan `pembayaran.php` |
| `mulai_sesi()` | `functions_order.php:669` | Satu-satunya tempat yang boleh memanggil `session_start()`, dan tempat menyisipkan `session_set_cookie_params()` |
| `ambil_pesanan_by_id()` dan `ambil_detail_pesanan()` | `functions_order.php:382,419` | Sumber data pesanan |
| `dapatkan_info_status()` dan `dapatkan_info_peran()` | `functions_order.php:826`, `functions_auth.php:326` | Badge dan label status atau peran |
| `format_rupiah()`, `hitung_subtotal()`, `hitung_total_harga()` | `functions_order.php:815,172,186` | Format uang dan kalkulasi |
| Pola PRG | `admin.php:44-142` | Contoh redirect pasca-POST yang sudah benar |

---

## 7. Cara Verifikasi

Belum ada test suite di proyek ini, jadi verifikasi memakai skenario manual di XAMPP (`http://localhost/antarrasa`).

**Pengecekan sintaks wajib** sebelum menandai satu poin selesai. Binary `php` tidak ada di PATH, jadi gunakan path lengkap:

```
C:\xampp\php\php.exe -l <nama_file.php>
```

### Keamanan (setelah tugas 1 sampai 16)

1. Logout total, lalu buka `riwayat.php`. Harus dialihkan ke `login.php?pesan=wajib_login`, **bukan** menampilkan daftar pesanan.
2. Login sebagai customer A, lalu buka `pembayaran.php?id=` milik customer B. Harus ditolak.
3. Tanpa login, kirim POST ke `status.php` dengan `id_pesanan=1` dan `status_tujuan=Selesai`. Status di database **tidak boleh** berubah; periksa dengan `SELECT status_pesanan FROM pesanan WHERE id_pesanan=1`.
4. Kirim form POST apa pun tanpa `csrf_token`. Harus ditolak.
5. Buka `login.php?ref=//example.com` lalu login. Harus mendarat di dashboard sesuai peran, **bukan** domain luar.
6. Bandingkan `PHPSESSID` sebelum dan sesudah login. Harus berbeda.
7. Sebagai admin, coba ubah peran diri sendiri jadi `customer`, lalu coba hapus admin terakhir. Keduanya harus ditolak dengan pesan jelas.
8. Jalankan `file database/antarrasa.sql`. Harus melaporkan UTF-8, bukan UTF-16.

### Alur fungsional (setelah tugas 17 sampai 32)

9. Alur lengkap: customer menambah 2 menu, checkout, lalu bayar dengan QRIS. Periksa `SELECT metode_bayar, waktu_bayar FROM pesanan` sudah terisi.
10. Merchant menerima pesanan, lalu driver mengambil dan menyelesaikannya. Kolom `id_driver` harus terisi dan `waktu_diproses`, `waktu_dikirim`, serta `waktu_selesai` berurutan naik.
11. Buka `status.php` untuk pesanan itu. Nama kurir harus **kurir yang benar-benar mengambilnya**, bukan driver dengan ID terkecil.
12. Sebagai driver, coba set pesanan `Menunggu` langsung ke `Selesai`. Harus ditolak.
13. Merchant mengubah harga sebuah menu, lalu buka faktur lama di `pembayaran.php`. Harga satuan lama harus tetap dan subtotal konsisten.
14. Refresh halaman setelah menambah menu di `merchant.php`. **Tidak** boleh ada menu duplikat.
15. Merchant menghapus menu yang pernah terjual. Harus berhasil sebagai soft delete, hilang dari katalog pelanggan, tetapi **tetap muncul** di faktur lama.

### Database dan lintas-OS (setelah tugas 14 sampai 20)

16. Pada database kosong, jalankan `database/tools/setup.bat` di Windows. Aplikasi harus jalan penuh dan akun demo bisa login.
17. Jalankan `database/tools/setup.sh` di macOS atau Linux. Tidak boleh ada error `Table 'PENGGUNA' doesn't exist`.
18. Jalankan ulang seluruh migrasi pada database yang sudah terisi. Harus idempoten, tanpa error, dan tanpa kehilangan data.
19. Jalankan `EXPLAIN SELECT ... FROM pesanan WHERE status_pesanan='Diproses'`. Harus memakai indeks, bukan full table scan.

### UI/UX (setelah tugas 33 sampai 35)

20. Pakai DevTools emulasi iPhone SE (375px) pada `index.php`, `pesanan.php`, `riwayat.php`, `admin.php`, `merchant.php`, dan `driver.php`. Tidak boleh ada scroll horizontal, floating bar tidak menutup tombol, dan sidebar admin bisa dibuka lewat hamburger.
21. Navigasi keyboard dengan Tab pada `admin.php`. Setiap tombol ikon harus punya focus ring terlihat dan nama yang terbaca screen reader.
