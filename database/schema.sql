-- =====================================================================
-- AntarRasa - Skema Basis Data (DDL saja)
-- ---------------------------------------------------------------------
-- * TANPA CREATE DATABASE dan TANPA DROP TABLE: aman dijalankan berulang
--   kali pada database yang sudah berisi data (tabel yang ada tidak disentuh).
-- * Mencerminkan kondisi skema TERBARU (sudah termasuk seluruh migrasi).
--   Database lama dinaikkan versinya lewat database/migrations/*.sql.
-- * Jalankan lewat database/tools/setup.bat (Windows) atau setup.sh (macOS/Linux).
-- * Nama tabel WAJIB huruf kecil agar identik di Windows, macOS, dan Linux.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- D4: pengguna
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pengguna` (
  `id_pengguna` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `peran` VARCHAR(20) NOT NULL DEFAULT 'customer',
  PRIMARY KEY (`id_pengguna`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_pengguna_peran` (`peran`),
  CONSTRAINT `chk_peran_pengguna` CHECK (`peran` IN ('customer','merchant','driver','admin'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- D1: menu
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu` (
  `id_menu` INT(11) NOT NULL AUTO_INCREMENT,
  `id_merchant` INT(11) DEFAULT NULL,
  `nama_menu` VARCHAR(100) NOT NULL,
  `harga` DECIMAL(12,2) NOT NULL,
  `deskripsi` TEXT DEFAULT NULL,
  `kategori` VARCHAR(30) DEFAULT NULL,
  `gambar_url` VARCHAR(255) DEFAULT NULL,
  `status_menu` VARCHAR(20) NOT NULL DEFAULT 'Tersedia',
  `is_aktif` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_menu`),
  KEY `fk_menu_merchant` (`id_merchant`),
  KEY `idx_menu_status` (`status_menu`),
  CONSTRAINT `fk_menu_merchant` FOREIGN KEY (`id_merchant`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_status_menu` CHECK (`status_menu` IN ('Tersedia','Habis'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- D2: pesanan
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pesanan` (
  `id_pesanan` INT(11) NOT NULL AUTO_INCREMENT,
  `id_pengguna` INT(11) DEFAULT NULL,
  `id_driver` INT(11) DEFAULT NULL,
  `tanggal_pesanan` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `alamat_pengiriman` TEXT NOT NULL,
  `total_harga` DECIMAL(12,2) NOT NULL,
  `status_pesanan` VARCHAR(30) NOT NULL DEFAULT 'Menunggu',
  `metode_bayar` VARCHAR(20) DEFAULT NULL,
  `waktu_bayar` DATETIME DEFAULT NULL,
  `bukti_bayar` VARCHAR(255) DEFAULT NULL,
  `waktu_diproses` DATETIME DEFAULT NULL,
  `waktu_dikirim` DATETIME DEFAULT NULL,
  `waktu_selesai` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_pesanan`),
  KEY `fk_pesanan_pengguna` (`id_pengguna`),
  KEY `fk_pesanan_driver` (`id_driver`),
  KEY `idx_pesanan_status` (`status_pesanan`),
  KEY `idx_pesanan_tanggal` (`tanggal_pesanan`),
  CONSTRAINT `fk_pesanan_pengguna` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pesanan_driver` FOREIGN KEY (`id_driver`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_status_pesanan` CHECK (`status_pesanan` IN ('Menunggu','Diproses','Dikirim','Selesai','Dibatalkan'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- D3: detail_pesanan
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `detail_pesanan` (
  `id_detail` INT(11) NOT NULL AUTO_INCREMENT,
  `id_pesanan` INT(11) NOT NULL,
  `id_menu` INT(11) NOT NULL,
  `jumlah` INT(11) NOT NULL,
  `harga_satuan` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id_detail`),
  KEY `fk_detail_pesanan_pesanan` (`id_pesanan`),
  KEY `fk_detail_pesanan_menu` (`id_menu`),
  CONSTRAINT `fk_detail_pesanan_menu` FOREIGN KEY (`id_menu`) REFERENCES `menu` (`id_menu`) ON UPDATE CASCADE,
  CONSTRAINT `fk_detail_pesanan_pesanan` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_jumlah_positif` CHECK (`jumlah` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
