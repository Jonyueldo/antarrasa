-- AntarRasa: 5 tabel inti. Impor: mysql -u root < database/antarrasa.sql
-- Aman dijalankan ulang (tidak menghapus data). Akun admin = pengguna pertama yang mendaftar.
-- Tidak ada data awal: menu dibuat merchant lalu disetujui admin.
-- Catatan: jangan membuat dump dengan "mysqldump > file.sql" di PowerShell (hasilnya UTF-16).

CREATE DATABASE IF NOT EXISTS antarrasa_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE antarrasa_db;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pengguna (
  id_pengguna INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  peran ENUM('pelanggan', 'merchant', 'driver', 'admin') NOT NULL DEFAULT 'pelanggan',
  status_akun ENUM('aktif', 'suspend_sementara', 'ban_permanen') NOT NULL DEFAULT 'aktif',
  suspend_sampai DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu (
  id_menu INT AUTO_INCREMENT PRIMARY KEY,
  id_merchant INT NOT NULL,
  nama_menu VARCHAR(100) NOT NULL,
  deskripsi TEXT NULL,
  harga DECIMAL(12,2) NOT NULL,
  gambar VARCHAR(255) NULL,
  status_stok ENUM('tersedia', 'habis') NOT NULL DEFAULT 'tersedia',
  status_moderasi ENUM('menunggu_tambah', 'menunggu_ubah', 'menunggu_hapus', 'disetujui', 'ditolak') NOT NULL DEFAULT 'menunggu_tambah',
  data_usulan_json TEXT NULL,
  KEY idx_menu_moderasi (status_moderasi),
  CONSTRAINT fk_menu_merchant FOREIGN KEY (id_merchant) REFERENCES pengguna (id_pengguna) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pesanan (
  id_pesanan INT AUTO_INCREMENT PRIMARY KEY,
  id_pelanggan INT NULL,
  id_merchant INT NULL,
  id_driver INT NULL,
  tanggal_pesan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  alamat_antar TEXT NOT NULL,
  total_harga DECIMAL(12,2) NOT NULL,
  status_pesanan ENUM('menunggu_driver', 'menuju_merchant', 'diantar', 'selesai', 'ditolak_driver') NOT NULL DEFAULT 'menunggu_driver',
  lat_driver DECIMAL(10,7) NULL,
  lng_driver DECIMAL(10,7) NULL,
  estimasi_menit INT NULL,
  rating TINYINT NULL,
  ulasan TEXT NULL,
  KEY idx_pesanan_status (status_pesanan),
  CONSTRAINT fk_pesanan_pelanggan FOREIGN KEY (id_pelanggan) REFERENCES pengguna (id_pengguna) ON DELETE SET NULL,
  CONSTRAINT fk_pesanan_merchant FOREIGN KEY (id_merchant) REFERENCES pengguna (id_pengguna) ON DELETE SET NULL,
  CONSTRAINT fk_pesanan_driver FOREIGN KEY (id_driver) REFERENCES pengguna (id_pengguna) ON DELETE SET NULL,
  CONSTRAINT chk_rating CHECK (rating IS NULL OR rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- id_menu boleh NULL: bila menu dihapus, rincian & subtotal pesanan lama tetap tersimpan.
CREATE TABLE IF NOT EXISTS detail_pesanan (
  id_detail INT AUTO_INCREMENT PRIMARY KEY,
  id_pesanan INT NOT NULL,
  id_menu INT NULL,
  jumlah INT NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_detail_pesanan FOREIGN KEY (id_pesanan) REFERENCES pesanan (id_pesanan) ON DELETE CASCADE,
  CONSTRAINT fk_detail_menu FOREIGN KEY (id_menu) REFERENCES menu (id_menu) ON DELETE SET NULL,
  CONSTRAINT chk_jumlah CHECK (jumlah > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS laporan (
  id_laporan INT AUTO_INCREMENT PRIMARY KEY,
  id_pelapor INT NOT NULL,
  id_terlapor INT NOT NULL,
  id_pesanan INT NULL,
  alasan TEXT NOT NULL,
  status_laporan ENUM('menunggu', 'diverifikasi', 'ditolak') NOT NULL DEFAULT 'menunggu',
  tanggal_lapor DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_laporan_pelapor FOREIGN KEY (id_pelapor) REFERENCES pengguna (id_pengguna) ON DELETE CASCADE,
  CONSTRAINT fk_laporan_terlapor FOREIGN KEY (id_terlapor) REFERENCES pengguna (id_pengguna) ON DELETE CASCADE,
  CONSTRAINT fk_laporan_pesanan FOREIGN KEY (id_pesanan) REFERENCES pesanan (id_pesanan) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
