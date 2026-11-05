-- =====================================================================
-- Migrasi 002: Kolom baru tabel `menu` (D6)
-- Idempoten: aman dijalankan berulang kali. JANGAN diedit setelah di-push;
-- buat file migrasi baru untuk perubahan berikutnya.
-- =====================================================================

ALTER TABLE `menu`
    ADD COLUMN IF NOT EXISTS `kategori` VARCHAR(30) NULL DEFAULT NULL AFTER `deskripsi`,
    ADD COLUMN IF NOT EXISTS `gambar_url` VARCHAR(255) NULL DEFAULT NULL AFTER `kategori`,
    ADD COLUMN IF NOT EXISTS `is_aktif` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status_menu`,
    ADD COLUMN IF NOT EXISTS `id_merchant` INT(11) NULL DEFAULT NULL AFTER `id_menu`;

ALTER TABLE `menu`
    ADD CONSTRAINT `fk_menu_merchant` FOREIGN KEY IF NOT EXISTS (`id_merchant`)
    REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Menu lama belum punya pemilik: tetapkan ke merchant pertama yang terdaftar
UPDATE `menu`
SET `id_merchant` = (SELECT MIN(`id_pengguna`) FROM `pengguna` WHERE `peran` = 'merchant')
WHERE `id_merchant` IS NULL;
