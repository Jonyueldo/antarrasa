/*M!999999\- enable the sandbox mode */ 
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

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `antarrasa_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

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
(1,1,1,2,56000.00),
(2,1,3,1,42000.00),
(3,2,2,2,70000.00),
(4,3,1,1,28000.00),
(5,3,6,1,18000.00),
(6,4,3,1,42000.00);
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
  `nama_menu` varchar(100) NOT NULL,
  `harga` decimal(12,2) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `status_menu` varchar(20) NOT NULL DEFAULT 'Tersedia',
  PRIMARY KEY (`id_menu`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu`
--

LOCK TABLES `menu` WRITE;
/*!40000 ALTER TABLE `menu` DISABLE KEYS */;
INSERT INTO `menu` VALUES
(1,'Nasi Goreng Spesial AntarRasa',28000.00,'Nasi goreng harum rempah istimewa dengan telur mata sapi, suwiran ayam gurih, acar segar, dan kerupuk renyah.','Tersedia'),
(2,'Ayam Bakar Madu Taliwang',35000.00,'Ayam bakar bumbu taliwang khas Lombok dengan olesan madu alami, disajikan bersama sambal terasi pedas manis.','Tersedia'),
(3,'Rendang Daging Sapi Minang',42000.00,'Daging sapi pilihan dimasak perlahan dengan santan kental dan racikan rempah Minang autentik hingga empuk meresap.','Tersedia'),
(4,'Sate Ayam Madura (10 Tusuk)',30000.00,'Potongan daging ayam empuk dibakar sempurna, disiram saus kacang gurih pekat, kecap manis, dan taburan bawang goreng.','Tersedia'),
(5,'Mie Goreng Seafood Nusantara',32000.00,'Mie telur kenyal ditumis dengan udang segar, cumi, bakso ikan, sayuran hijau renyah, dan bumbu oriental khas nusantara.','Tersedia'),
(6,'Es Cendol Durian Segar',18000.00,'Minuman penutup manis legit dengan cendol pandan kenyal, santan kelapa murni, gula aren alami, dan topping durian asli.','Tersedia');
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
  CONSTRAINT `chk_peran_pengguna` CHECK (`peran` in ('customer','merchant','driver','admin'))
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengguna`
--

LOCK TABLES `pengguna` WRITE;
/*!40000 ALTER TABLE `pengguna` DISABLE KEYS */;
-- Akun DEMO khusus repository. Seluruh kata sandi: password123
-- Hash dibuat ulang dengan password_hash() dan TIDAK berasal dari akun asli anggota tim.
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
  `tanggal_pesanan` datetime NOT NULL DEFAULT current_timestamp(),
  `alamat_pengiriman` text NOT NULL,
  `total_harga` decimal(12,2) NOT NULL,
  `status_pesanan` varchar(30) NOT NULL DEFAULT 'Menunggu',
  PRIMARY KEY (`id_pesanan`),
  KEY `fk_pesanan_pengguna` (`id_pengguna`),
  CONSTRAINT `fk_pesanan_pengguna` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pesanan`
--

LOCK TABLES `pesanan` WRITE;
/*!40000 ALTER TABLE `pesanan` DISABLE KEYS */;
INSERT INTO `pesanan` VALUES
(1,1,'2026-10-24 10:15:00','Jl. Sudirman No. 45, RT 02 / RW 05, Kebayoran Baru, Jakarta Selatan',98000.00,'Selesai'),
(2,1,'2026-10-25 12:30:00','Jl. Gatot Subroto No. 12, Kuningan Barat, Mampang Prapatan, Jakarta Selatan',70000.00,'Selesai'),
(3,21,'2026-10-28 11:57:26','jl sawit no 67',46000.00,'Selesai'),
(4,1,'2026-10-28 11:58:50','jln mbg no 128',42000.00,'Selesai');
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

-- Dump completed on 2026-10-28 12:12:51
