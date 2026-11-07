-- AntarRasa: 4 tabel inti. Impor: mysql -u root < database/antarrasa.sql
-- Aman dijalankan ulang (tidak menghapus data). Akun admin = pengguna pertama yang mendaftar.
-- Catatan: jangan membuat dump dengan "mysqldump > file.sql" di PowerShell (hasilnya UTF-16).

CREATE DATABASE IF NOT EXISTS antarrasa_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE antarrasa_db;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pengguna (
  id_pengguna INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(100) NOT NULL,
  peran VARCHAR(20) NOT NULL DEFAULT 'pelanggan',
  CONSTRAINT chk_peran CHECK (peran IN ('admin', 'pelanggan'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu (
  id_menu INT AUTO_INCREMENT PRIMARY KEY,
  nama_menu VARCHAR(100) NOT NULL,
  harga DECIMAL(12,2) NOT NULL,
  deskripsi TEXT NULL,
  status_menu VARCHAR(20) NOT NULL DEFAULT 'Tersedia',
  CONSTRAINT chk_status_menu CHECK (status_menu IN ('Tersedia', 'Habis'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pesanan (
  id_pesanan INT AUTO_INCREMENT PRIMARY KEY,
  id_pengguna INT NULL,
  tanggal_pesanan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  alamat_pengiriman TEXT NOT NULL,
  total_harga DECIMAL(12,2) NOT NULL,
  status_pesanan VARCHAR(20) NOT NULL DEFAULT 'Menunggu',
  KEY idx_pesanan_pengguna (id_pengguna),
  CONSTRAINT fk_pesanan_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna (id_pengguna) ON DELETE SET NULL,
  CONSTRAINT chk_status_pesanan CHECK (status_pesanan IN ('Menunggu', 'Diproses', 'Selesai'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS detail_pesanan (
  id_detail INT AUTO_INCREMENT PRIMARY KEY,
  id_pesanan INT NOT NULL,
  id_menu INT NOT NULL,
  jumlah INT NOT NULL,
  harga_satuan DECIMAL(12,2) NOT NULL,
  KEY idx_detail_pesanan (id_pesanan),
  KEY idx_detail_menu (id_menu),
  CONSTRAINT fk_detail_pesanan FOREIGN KEY (id_pesanan) REFERENCES pesanan (id_pesanan) ON DELETE CASCADE,
  CONSTRAINT fk_detail_menu FOREIGN KEY (id_menu) REFERENCES menu (id_menu),
  CONSTRAINT chk_jumlah CHECK (jumlah > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data awal menu (master data); hanya diisi bila tabel menu masih kosong
INSERT INTO menu (nama_menu, harga, deskripsi)
SELECT * FROM (
  SELECT 'Nasi Goreng Spesial' AS nama_menu, 25000 AS harga, 'Nasi goreng dengan telur dan ayam suwir' AS deskripsi UNION ALL
  SELECT 'Ayam Bakar Madu', 32000, 'Ayam bakar bumbu madu dengan lalapan' UNION ALL
  SELECT 'Mie Ayam Bakso', 22000, 'Mie ayam dengan bakso sapi' UNION ALL
  SELECT 'Sate Ayam (10 tusuk)', 28000, 'Sate ayam bumbu kacang' UNION ALL
  SELECT 'Es Teh Manis', 6000, 'Teh manis dingin'
) AS awal WHERE NOT EXISTS (SELECT 1 FROM menu);
