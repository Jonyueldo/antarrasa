# ROADMAP AntarRasa — Core MVP (20%)

Fokus: kode seminimal mungkin, 4 tabel (`pengguna`, `menu`, `pesanan`, `detail_pesanan`), 2 peran (`admin`, `pelanggan`), tanpa akun demo.
Setiap poin dikerjakan satu per satu, diverifikasi dengan `C:\xampp\php\php.exe -l <file>`, lalu di-commit atomic.

- [x] 1. Hapus seluruh fitur, tombol auto-fill, dan data seeding Akun Demo.
- [x] 2. Sederhanakan Autentikasi (Login, Register, Logout) untuk peran `admin` dan `pelanggan`.
- [x] 3. Sederhanakan Katalog & CRUD Menu (`MENU`).
- [x] 4. Sederhanakan Keranjang Session & Checkout Pesanan (`PESANAN` & `DETAIL_PESANAN`).
- [x] 5. Sederhanakan Riwayat & Update Status Pesanan (`Menunggu`, `Diproses`, `Selesai`).
