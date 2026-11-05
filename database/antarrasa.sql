-- =====================================================================
-- SNAPSHOT LENGKAP (skema + data demo) hasil generate dari:
--   database/schema.sql + database/migrations/*.sql + database/seed.sql
-- PERINGATAN: file ini berisi DROP TABLE, sehingga MENIMPA seluruh data
-- lokal. Untuk setup harian gunakan database/tools/setup.bat atau setup.sh.
-- =====================================================================
-- MariaDB dump 10.19  Distrib 10.11.16-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: antarrasa_db
-- ------------------------------------------------------
-- Server version	10.11.16-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `antarrasa_db`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `antarrasa_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `antarrasa_db`;

--
-- Table structure for table `detail_pesanan`
--

DROP TABLE IF EXISTS `detail_pesanan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_pesanan` (
  `id_detail` int(11) NOT NULL AUTO_INCREMENT,
  `id_pesanan` int(11) NOT NULL,
  `id_menu` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_satuan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id_detail`),
  KEY `fk_detail_pesanan_pesanan` (`id_pesanan`),
  KEY `fk_detail_pesanan_menu` (`id_menu`),
  CONSTRAINT `fk_detail_pesanan_menu` FOREIGN KEY (`id_menu`) REFERENCES `menu` (`id_menu`) ON UPDATE CASCADE,
  CONSTRAINT `fk_detail_pesanan_pesanan` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_jumlah_positif` CHECK (`jumlah` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detail_pesanan`
--

LOCK TABLES `detail_pesanan` WRITE;
/*!40000 ALTER TABLE `detail_pesanan` DISABLE KEYS */;
INSERT INTO `detail_pesanan` VALUES
(1,1,1,2,28000.00,56000.00),
(2,1,3,1,42000.00,42000.00),
(3,2,2,2,35000.00,70000.00),
(4,3,1,1,28000.00,28000.00),
(5,3,6,1,18000.00,18000.00),
(6,4,3,1,42000.00,42000.00);
/*!40000 ALTER TABLE `detail_pesanan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu`
--

DROP TABLE IF EXISTS `menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu` (
  `id_menu` int(11) NOT NULL AUTO_INCREMENT,
  `id_merchant` int(11) DEFAULT NULL,
  `nama_menu` varchar(100) NOT NULL,
  `harga` decimal(12,2) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `kategori` varchar(30) DEFAULT NULL,
  `gambar_url` varchar(255) DEFAULT NULL,
  `status_menu` varchar(20) NOT NULL DEFAULT 'Tersedia',
  `is_aktif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_menu`),
  KEY `fk_menu_merchant` (`id_merchant`),
  KEY `idx_menu_status` (`status_menu`),
  CONSTRAINT `fk_menu_merchant` FOREIGN KEY (`id_merchant`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_status_menu` CHECK (`status_menu` in ('Tersedia','Habis'))
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu`
--

LOCK TABLES `menu` WRITE;
/*!40000 ALTER TABLE `menu` DISABLE KEYS */;
INSERT INTO `menu` VALUES
(1,20,'Nasi Goreng Spesial AntarRasa',28000.00,'Nasi goreng harum rempah istimewa dengan telur mata sapi, suwiran ayam gurih, acar segar, dan kerupuk renyah.','nasi',NULL,'Tersedia',1),
(2,20,'Ayam Bakar Madu Taliwang',35000.00,'Ayam bakar bumbu taliwang khas Lombok dengan olesan madu alami, disajikan bersama sambal terasi pedas manis.','ayam',NULL,'Tersedia',1),
(3,20,'Rendang Daging Sapi Minang',42000.00,'Daging sapi pilihan dimasak perlahan dengan santan kental dan racikan rempah Minang autentik hingga empuk meresap.','daging',NULL,'Tersedia',1),
(4,20,'Sate Ayam Madura (10 Tusuk)',30000.00,'Potongan daging ayam empuk dibakar sempurna, disiram saus kacang gurih pekat, kecap manis, dan taburan bawang goreng.','sate',NULL,'Tersedia',1),
(5,20,'Mie Goreng Seafood Nusantara',32000.00,'Mie telur kenyal ditumis dengan udang segar, cumi, bakso ikan, sayuran hijau renyah, dan bumbu oriental khas nusantara.','mie',NULL,'Tersedia',1),
(6,20,'Es Cendol Durian Segar',18000.00,'Minuman penutup manis legit dengan cendol pandan kenyal, santan kelapa murni, gula aren alami, dan topping durian asli.','minuman',NULL,'Tersedia',1);
/*!40000 ALTER TABLE `menu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengguna`
--

DROP TABLE IF EXISTS `pengguna`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengguna` (
  `id_pengguna` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `peran` varchar(20) NOT NULL DEFAULT 'customer',
  PRIMARY KEY (`id_pengguna`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_pengguna_peran` (`peran`),
  CONSTRAINT `chk_peran_pengguna` CHECK (`peran` in ('customer','merchant','driver','admin'))
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengguna`
--

LOCK TABLES `pengguna` WRITE;
/*!40000 ALTER TABLE `pengguna` DISABLE KEYS */;
INSERT INTO `pengguna` VALUES
(1,'customer','$2y$12$UWaKam70LO9FaEwIPFs5v.q5A.GJm3TVQruVcsqXy2nrqEE0w85YC','Pelanggan Demo','customer'),
(3,'driver','$2y$12$kal.xSQ8JnHiyU83zpDRtexk3lWoVEEugmEZYw2fKdcVQ8dT1pxpS','Kurir Demo','driver'),
(4,'admin','$2y$12$.q8DFf6LYvnlGheibncZ4eAdFesMeMImm0eSQH85gIvdQEWYbqEcm','Administrator Demo','admin'),
(20,'merchant','$2y$12$5s.05D5vu0yF4KVBRW1N8uz0ZGVo0ZP4gC.icGhzecl/NO/26ByvO','Warung Demo AntarRasa','merchant'),
(21,'customer2','$2y$12$eoJwDLsllHU1sYQLypg1aeo3GzJLqJVFiGgw8eI9VpX6/eRNZ7UO6','Pelanggan Demo Kedua','customer');
/*!40000 ALTER TABLE `pengguna` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pesanan`
--

DROP TABLE IF EXISTS `pesanan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pesanan` (
  `id_pesanan` int(11) NOT NULL AUTO_INCREMENT,
  `id_pengguna` int(11) DEFAULT NULL,
  `id_driver` int(11) DEFAULT NULL,
  `tanggal_pesanan` datetime NOT NULL DEFAULT current_timestamp(),
  `alamat_pengiriman` text NOT NULL,
  `total_harga` decimal(12,2) NOT NULL,
  `status_pesanan` varchar(30) NOT NULL DEFAULT 'Menunggu',
  `metode_bayar` varchar(20) DEFAULT NULL,
  `waktu_bayar` datetime DEFAULT NULL,
  `bukti_bayar` varchar(255) DEFAULT NULL,
  `waktu_diproses` datetime DEFAULT NULL,
  `waktu_dikirim` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  PRIMARY KEY (`id_pesanan`),
  KEY `fk_pesanan_pengguna` (`id_pengguna`),
  KEY `fk_pesanan_driver` (`id_driver`),
  KEY `idx_pesanan_status` (`status_pesanan`),
  KEY `idx_pesanan_tanggal` (`tanggal_pesanan`),
  CONSTRAINT `fk_pesanan_driver` FOREIGN KEY (`id_driver`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pesanan_pengguna` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_status_pesanan` CHECK (`status_pesanan` in ('Menunggu','Diproses','Dikirim','Selesai','Dibatalkan'))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pesanan`
--

LOCK TABLES `pesanan` WRITE;
/*!40000 ALTER TABLE `pesanan` DISABLE KEYS */;
INSERT INTO `pesanan` VALUES
(1,1,NULL,'2026-10-24 10:15:00','Jl. Sudirman No. 45, RT 02 / RW 05, Kebayoran Baru, Jakarta Selatan',98000.00,'Selesai',NULL,NULL,NULL,NULL,NULL,NULL),
(2,1,NULL,'2026-10-25 12:30:00','Jl. Gatot Subroto No. 12, Kuningan Barat, Mampang Prapatan, Jakarta Selatan',70000.00,'Selesai',NULL,NULL,NULL,NULL,NULL,NULL),
(3,21,NULL,'2026-10-28 11:57:26','jl sawit no 67',46000.00,'Selesai',NULL,NULL,NULL,NULL,NULL,NULL),
(4,1,NULL,'2026-10-28 11:58:50','jln mbg no 128',42000.00,'Selesai',NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `pesanan` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
