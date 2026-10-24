-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 24 Okt 2026 pada 07.09
-- Versi server: 10.11.16-MariaDB
-- Versi PHP: 8.5.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `antarrasa_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_pesanan`
--

CREATE TABLE `detail_pesanan` (
  `id_detail` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `id_menu` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL
) ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `menu`
--

CREATE TABLE `menu` (
  `id_menu` int(11) NOT NULL,
  `nama_menu` varchar(100) NOT NULL,
  `harga` decimal(12,2) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `status_menu` varchar(20) NOT NULL DEFAULT 'Tersedia'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `menu`
--

INSERT INTO `menu` (`id_menu`, `nama_menu`, `harga`, `deskripsi`, `status_menu`) VALUES
(1, 'Nasi Goreng Spesial AntarRasa', 22000.00, 'Nasi goreng harum rempah istimewa dengan telur mata sapi, suwiran ayam gurih, acar segar, dan kerupuk renyah.', 'Tersedia'),
(2, 'Ayam taliwang', 30000.00, 'Ayam bakar bumbu taliwang khas Lombok dengan olesan madu alami, disajikan bersama sambal terasi pedas manis.', 'Tersedia'),
(3, 'Rendang Daging Sapi Minang', 42000.00, 'Daging sapi pilihan dimasak perlahan dengan santan kental dan racikan rempah Minang autentik hingga empuk meresap.', 'Tersedia'),
(4, 'Sate Ayam Madura (10 Tusuk)', 25000.00, 'Potongan daging ayam empuk dibakar sempurna, disiram saus kacang gurih pekat, kecap manis, dan taburan bawang goreng.', 'Tersedia'),
(5, 'Mie Goreng Seafood Nusantara', 26000.00, 'Mie telur kenyal ditumis dengan udang segar, cumi, bakso ikan, sayuran hijau renyah, dan bumbu oriental khas nusantara.', 'Tersedia'),
(6, 'Es Cendol Durian Segar', 18000.00, 'Minuman penutup manis legit dengan cendol pandan kenyal, santan kelapa murni, gula aren alami, dan topping durian asli.', 'Tersedia'),
(7, 'Kebab', 0.00, 'hidangan khas Timur Tengah yang terbuat dari potongan atau cincangan daging (seperti sapi, domba, kambing, atau ayam) yang dimasak dengan cara dipanggang, dibakar di atas api, atau dimasak secara vertikal.', 'Tersedia');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pesanan`
--

CREATE TABLE `pesanan` (
  `id_pesanan` int(11) NOT NULL,
  `tanggal_pesanan` datetime NOT NULL DEFAULT current_timestamp(),
  `alamat_pengiriman` text NOT NULL,
  `total_harga` decimal(12,2) NOT NULL,
  `status_pesanan` varchar(30) NOT NULL DEFAULT 'Menunggu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `pesanan`
--

INSERT INTO `pesanan` (`id_pesanan`, `tanggal_pesanan`, `alamat_pengiriman`, `total_harga`, `status_pesanan`) VALUES
(1, '2026-10-23 22:32:53', 'Jl. Medan Merdeka Utara, RT.3/RW.2, Gambir, Kecamatan Gambir, Kota Jakarta Pusat, Daerah Khusus Ibukota Jakarta 10110', 84000.00, 'Selesai'),
(2, '2026-10-23 22:36:12', 'Jl. Medan Merdeka Utara No. 3, Gambir, Kecamatan Gambir, Kota Jakarta Pusat, Daerah Khusus Ibukota Jakarta 10110', 28000.00, 'Diproses');

--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `fk_detail_pesanan_pesanan` (`id_pesanan`),
  ADD KEY `fk_detail_pesanan_menu` (`id_menu`);

--
-- Indeks untuk tabel `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`id_menu`);

--
-- Indeks untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id_pesanan`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `menu`
--
ALTER TABLE `menu`
  MODIFY `id_menu` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id_pesanan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD CONSTRAINT `fk_detail_pesanan_menu` FOREIGN KEY (`id_menu`) REFERENCES `menu` (`id_menu`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detail_pesanan_pesanan` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
