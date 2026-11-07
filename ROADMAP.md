# ROADMAP AntarRasa — Core MVP (20%)

Fokus: kode seringkas mungkin, 5 tabel (`pengguna`, `menu`, `pesanan`, `detail_pesanan`, `laporan`),
4 peran (`pelanggan`, `merchant`, `driver`, `admin`), tanpa akun demo.
Setiap poin dikerjakan satu per satu, diverifikasi dengan `C:\xampp\php\php.exe -l <file>`, lalu di-commit atomic.

## Tahap 1 — MVP 2 peran (selesai)

- [x] 1. Hapus seluruh fitur, tombol auto-fill, dan data seeding Akun Demo.
- [x] 2. Sederhanakan Autentikasi (Login, Register, Logout) untuk peran `admin` dan `pelanggan`.
- [x] 3. Sederhanakan Katalog & CRUD Menu (`MENU`).
- [x] 4. Sederhanakan Keranjang Session & Checkout Pesanan (`PESANAN` & `DETAIL_PESANAN`).
- [x] 5. Sederhanakan Riwayat & Update Status Pesanan (`Menunggu`, `Diproses`, `Selesai`).

## Tahap 2 — 4 peran, moderasi, pengantaran, ulasan, pelaporan

- [x] 6. Skema 5 tabel di `database/antarrasa.sql` (ENUM peran, status akun, moderasi menu, status pengantaran, laporan).
- [x] 7. Autentikasi 4 peran (login email) dengan penolakan akun `ban_permanen` atau masih disuspend beserta sisa waktunya.
- [x] 8. Merchant: ajukan tambah/ubah/hapus menu + unggah gambar (`uploads/menu/`) berstatus `menunggu_*`; ubah `status_stok` langsung.
- [x] 9. Admin: moderasi pengajuan menu (Setujui / Tolak).
- [ ] 10. Pelanggan: katalog menu `disetujui` + `tersedia`, keranjang session satu merchant, checkout.
- [ ] 11. Driver: alur bertahap Terima/Tolak → Mulai Antar (GPS + estimasi) → Konfirmasi Diterima, peta Leaflet + OpenStreetMap untuk driver dan pelanggan.
- [ ] 12. Pelanggan: ulasan bintang 1-5 dan komentar pada pesanan `selesai`.
- [ ] 13. Pelaporan akun oleh pelanggan, merchant, dan driver (tabel `laporan`).
- [ ] 14. Admin: statistik akun, daftar akun, hapus, Suspend X Jam / Ban Permanen / Pulihkan, dan verifikasi laporan dengan tindakan langsung.
