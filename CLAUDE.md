# Pedoman Pengembangan Proyek AntarRasa (CLAUDE.md)

## 1. Bahasa Komunikasi & Penamaan Kode
- **Bahasa Percakapan & Komentar**: Selalu gunakan Bahasa Indonesia yang jelas, ringkas, dan profesional saat berdiskusi di terminal, menjelaskan kode, maupun menulis komentar (`//`).
- **Teks Antarmuka (UI)**: Seluruh label form, tombol, pesan validasi, peringatan error, dan notifikasi pada aplikasi wajib menggunakan Bahasa Indonesia.
- **Konvensi Penamaan**: Gunakan format `snake_case` berbahasa Indonesia untuk nama variabel, fungsi, dan kolom database agar konsisten dengan kode yang ada (contoh: `$id_pengguna`, `$total_harga`, `ambil_semua_menu()`).

## 2. Aturan Teknis & Arsitektur
- **PHP 8.5.12 Prosedural Murni**:
  - Wajib menyertakan `declare(strict_types=1);` di baris paling atas setiap file PHP.
  - Seluruh sintaks, fungsi bawaan, tipe data parameter/return, dan penanganan error wajib 100% kompatibel dengan standar **PHP 8.5.12** tanpa memunculkan *deprecated warnings*.
  - DILARANG menggunakan OOP (`class`, `interface`, `trait`) atau framework PHP eksternal.
- **Keamanan Database (MySQLi Prosedural)**:
  - Wajib menggunakan **Prepared Statements** (`mysqli_prepare`, `mysqli_stmt_bind_param`, `mysqli_stmt_execute`) untuk seluruh query yang melibatkan input pengguna guna mencegah SQL Injection.
  - Gunakan charset `utf8mb4` pada koneksi database.
- **Keamanan Output (Anti-XSS)**:
  - Wajib membungkus seluruh data dinamis yang ditampilkan ke HTML dengan `htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8')`.
- **Konsistensi Skema Database**:
  - Pertahankan konsistensi nama tabel utama sesuai `database/antarrasa.sql`, yaitu **huruf kecil** (`pengguna`, `menu`, `pesanan`, `detail_pesanan`), baik di DDL maupun di seluruh query PHP. Nama tabel di MySQL/MariaDB bersifat case-sensitive di macOS/Linux, sehingga query `FROM PENGGUNA` akan gagal di sana walaupun berjalan di Windows.

## 3. Alur Kerja Berdasarkan `ROADMAP.md`
- Kerjakan tugas pada `ROADMAP.md` secara bertahap sesuai urutan prioritas atau poin spesifik yang diminta pengguna.
- Sebelum menyatakan satu poin selesai, wajib jalankan pengecekan sintaks `php -l <nama_file.php>` pada setiap file PHP yang baru dibuat atau dimodifikasi.
- Setelah terverifikasi bebas syntax error, segera perbarui status tugas terkait di `ROADMAP.md` dari `[ ]` menjadi `[x]`.

## 4. Standar Git Commit & Kolaborasi Tim
- **Format Pesan Commit (Conventional Commits Bahasa Indonesia)**:
  Gunakan format `<tipe>(<cakupan>): <deskripsi singkat dalam Bahasa Indonesia>` (maksimal 72 karakter, huruf kecil di awal deskripsi, tanpa tanda titik di akhir).
  - `feat`: Penambahan fitur baru (contoh: `feat(pesanan): tambah fitur filter status pesanan pelanggan`)
  - `fix`: Perbaikan bug atau celah keamanan (contoh: `fix(auth): amankan query login dengan prepared statement`)
  - `refactor`: Perbaikan struktur kode tanpa mengubah fungsi (contoh: `refactor(menu): rapikan fungsi query daftar menu`)
  - `style`: Perbaikan tampilan UI/CSS/HTML (contoh: `style(navbar): perbaiki tampilan responsif menu mobile`)
  - `db`: Perubahan skema atau data awal di `database/antarrasa.sql` (contoh: `db(skema): tambah indeks pada kolom id_pengguna`)
  - `docs`: Perubahan dokumentasi seperti `ROADMAP.md` atau `CLAUDE.md`
- **Aturan Atomic Commit**:
  - Lakukan 1 kali `git commit` setiap selesai mengerjakan 1 poin `ROADMAP.md` (jangan menggabungkan banyak fitur berbeda dalam satu commit).
  - Hanya lakukan `git add` pada file yang relevan dengan tugas tersebut.
  - Jangan menjalankan `git push` atau `git reset --hard` secara otomatis tanpa instruksi eksplisit dari pengguna.
- **Sinkronisasi Database Tim**:
  - Setiap ada perubahan struktur tabel (`CREATE`, `ALTER`) atau data awal master, wajib perbarui juga file `database/antarrasa.sql` agar anggota tim lain di Windows dan macOS tetap sinkron.
