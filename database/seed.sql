-- =====================================================================
-- AntarRasa - Data Demo (tanpa DDL)
-- ---------------------------------------------------------------------
-- * INSERT IGNORE: baris dengan id yang sudah ada TIDAK ditimpa, sehingga
--   menjalankan ulang seed tidak merusak data lokal anggota tim lain.
-- * Seluruh akun demo memakai kata sandi: password123
--   (hash dibuat ulang khusus repository, bukan akun asli anggota tim).
-- * Urutan mengikuti dependensi foreign key.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO `pengguna` (`id_pengguna`, `username`, `password`, `nama_lengkap`, `peran`) VALUES
(1,  'customer',  '$2y$12$UWaKam70LO9FaEwIPFs5v.q5A.GJm3TVQruVcsqXy2nrqEE0w85YC', 'Pelanggan Demo',        'customer'),
(3,  'driver',    '$2y$12$kal.xSQ8JnHiyU83zpDRtexk3lWoVEEugmEZYw2fKdcVQ8dT1pxpS', 'Kurir Demo',            'driver'),
(4,  'admin',     '$2y$12$.q8DFf6LYvnlGheibncZ4eAdFesMeMImm0eSQH85gIvdQEWYbqEcm', 'Administrator Demo',    'admin'),
(20, 'merchant',  '$2y$12$5s.05D5vu0yF4KVBRW1N8uz0ZGVo0ZP4gC.icGhzecl/NO/26ByvO', 'Warung Demo AntarRasa', 'merchant'),
(21, 'customer2', '$2y$12$eoJwDLsllHU1sYQLypg1aeo3GzJLqJVFiGgw8eI9VpX6/eRNZ7UO6', 'Pelanggan Demo Kedua',  'customer');

INSERT IGNORE INTO `menu` (`id_menu`, `id_merchant`, `nama_menu`, `harga`, `deskripsi`, `kategori`, `gambar_url`, `status_menu`, `is_aktif`) VALUES
(1, 20, 'Nasi Goreng Spesial AntarRasa', 28000.00, 'Nasi goreng harum rempah istimewa dengan telur mata sapi, suwiran ayam gurih, acar segar, dan kerupuk renyah.', 'nasi', NULL, 'Tersedia', 1),
(2, 20, 'Ayam Bakar Madu Taliwang', 35000.00, 'Ayam bakar bumbu taliwang khas Lombok dengan olesan madu alami, disajikan bersama sambal terasi pedas manis.', 'ayam', NULL, 'Tersedia', 1),
(3, 20, 'Rendang Daging Sapi Minang', 42000.00, 'Daging sapi pilihan dimasak perlahan dengan santan kental dan racikan rempah Minang autentik hingga empuk meresap.', 'daging', NULL, 'Tersedia', 1),
(4, 20, 'Sate Ayam Madura (10 Tusuk)', 30000.00, 'Potongan daging ayam empuk dibakar sempurna, disiram saus kacang gurih pekat, kecap manis, dan taburan bawang goreng.', 'sate', NULL, 'Tersedia', 1),
(5, 20, 'Mie Goreng Seafood Nusantara', 32000.00, 'Mie telur kenyal ditumis dengan udang segar, cumi, bakso ikan, sayuran hijau renyah, dan bumbu oriental khas nusantara.', 'mie', NULL, 'Tersedia', 1),
(6, 20, 'Es Cendol Durian Segar', 18000.00, 'Minuman penutup manis legit dengan cendol pandan kenyal, santan kelapa murni, gula aren alami, dan topping durian asli.', 'minuman', NULL, 'Tersedia', 1);

INSERT IGNORE INTO `pesanan` (`id_pesanan`, `id_pengguna`, `id_driver`, `tanggal_pesanan`, `alamat_pengiriman`, `total_harga`, `status_pesanan`) VALUES
(1, 1,  NULL, '2026-10-24 10:15:00', 'Jl. Sudirman No. 45, RT 02 / RW 05, Kebayoran Baru, Jakarta Selatan', 98000.00, 'Selesai'),
(2, 1,  NULL, '2026-10-25 12:30:00', 'Jl. Gatot Subroto No. 12, Kuningan Barat, Mampang Prapatan, Jakarta Selatan', 70000.00, 'Selesai'),
(3, 21, NULL, '2026-10-28 11:57:26', 'jl sawit no 67', 46000.00, 'Selesai'),
(4, 1,  NULL, '2026-10-28 11:58:50', 'jln mbg no 128', 42000.00, 'Selesai');

INSERT IGNORE INTO `detail_pesanan` (`id_detail`, `id_pesanan`, `id_menu`, `jumlah`, `harga_satuan`, `subtotal`) VALUES
(1, 1, 1, 2, 28000.00, 56000.00),
(2, 1, 3, 1, 42000.00, 42000.00),
(3, 2, 2, 2, 35000.00, 70000.00),
(4, 3, 1, 1, 28000.00, 28000.00),
(5, 3, 6, 1, 18000.00, 18000.00),
(6, 4, 3, 1, 42000.00, 42000.00);
