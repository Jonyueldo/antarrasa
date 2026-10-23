-- =====================================================================
-- Basis Data Sistem Pemesanan & Pengantaran Makanan Online "AntarRasa"
-- Spesifikasi: SRS IEEE Std 830-1998 (Core MVP Progres 20%)
-- Konsistensi DataStore: Tepat 3 Tabel Utama (D1, D2, D3)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `antarrasa_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `antarrasa_db`;

-- ---------------------------------------------------------------------
-- 1. DataStore D1: Tabel MENU
-- Menyimpan katalog makanan & minuman yang ditawarkan
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `DETAIL_PESANAN`;
DROP TABLE IF EXISTS `PESANAN`;
DROP TABLE IF EXISTS `MENU`;

CREATE TABLE `MENU` (
    `id_menu` INT NOT NULL AUTO_INCREMENT,
    `nama_menu` VARCHAR(100) NOT NULL,
    `harga` DECIMAL(12,2) NOT NULL,
    `deskripsi` TEXT NULL,
    `status_menu` VARCHAR(20) NOT NULL DEFAULT 'Tersedia',
    PRIMARY KEY (`id_menu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. DataStore D2: Tabel PESANAN
-- Menyimpan transaksi utama pemesanan makanan
-- Kolom status_pesanan mencakup: 'Menunggu', 'Diproses', 'Dikirim', 'Selesai'
-- ---------------------------------------------------------------------
CREATE TABLE `PESANAN` (
    `id_pesanan` INT NOT NULL AUTO_INCREMENT,
    `tanggal_pesanan` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `alamat_pengiriman` TEXT NOT NULL,
    `total_harga` DECIMAL(12,2) NOT NULL,
    `status_pesanan` VARCHAR(30) NOT NULL DEFAULT 'Menunggu',
    PRIMARY KEY (`id_pesanan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. DataStore D3: Tabel DETAIL_PESANAN
-- Menyimpan rincian item menu yang dipesan pada setiap pesanan
-- ---------------------------------------------------------------------
CREATE TABLE `DETAIL_PESANAN` (
    `id_detail` INT NOT NULL AUTO_INCREMENT,
    `id_pesanan` INT NOT NULL,
    `id_menu` INT NOT NULL,
    `jumlah` INT NOT NULL,
    `subtotal` DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (`id_detail`),
    CONSTRAINT `fk_detail_pesanan_pesanan` 
        FOREIGN KEY (`id_pesanan`) 
        REFERENCES `PESANAN` (`id_pesanan`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    CONSTRAINT `fk_detail_pesanan_menu` 
        FOREIGN KEY (`id_menu`) 
        REFERENCES `MENU` (`id_menu`) 
        ON DELETE RESTRICT 
        ON UPDATE CASCADE,
    CONSTRAINT `chk_jumlah_positif` 
        CHECK (`jumlah` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Dummy Data Awal Menu Makanan (Minimal 5 menu sesuai spesifikasi)
-- ---------------------------------------------------------------------
INSERT INTO `MENU` (`id_menu`, `nama_menu`, `harga`, `deskripsi`, `status_menu`) VALUES
(1, 'Nasi Goreng Spesial AntarRasa', 28000.00, 'Nasi goreng harum rempah istimewa dengan telur mata sapi, suwiran ayam gurih, acar segar, dan kerupuk renyah.', 'Tersedia'),
(2, 'Ayam Bakar Madu Taliwang', 35000.00, 'Ayam bakar bumbu taliwang khas Lombok dengan olesan madu alami, disajikan bersama sambal terasi pedas manis.', 'Tersedia'),
(3, 'Rendang Daging Sapi Minang', 42000.00, 'Daging sapi pilihan dimasak perlahan dengan santan kental dan racikan rempah Minang autentik hingga empuk meresap.', 'Tersedia'),
(4, 'Sate Ayam Madura (10 Tusuk)', 30000.00, 'Potongan daging ayam empuk dibakar sempurna, disiram saus kacang gurih pekat, kecap manis, dan taburan bawang goreng.', 'Tersedia'),
(5, 'Mie Goreng Seafood Nusantara', 32000.00, 'Mie telur kenyal ditumis dengan udang segar, cumi, bakso ikan, sayuran hijau renyah, dan bumbu oriental khas nusantara.', 'Tersedia'),
(6, 'Es Cendol Durian Segar', 18000.00, 'Minuman penutup manis legit dengan cendol pandan kenyal, santan kelapa murni, gula aren alami, dan topping durian asli.', 'Habis');
