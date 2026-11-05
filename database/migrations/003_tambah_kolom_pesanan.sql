-- =====================================================================
-- Migrasi 003: Kolom baru tabel `pesanan` (D7)
-- Idempoten: aman dijalankan berulang kali. JANGAN diedit setelah di-push;
-- buat file migrasi baru untuk perubahan berikutnya.
-- =====================================================================

ALTER TABLE `pesanan`
    ADD COLUMN IF NOT EXISTS `id_driver` INT(11) NULL DEFAULT NULL AFTER `id_pengguna`,
    ADD COLUMN IF NOT EXISTS `metode_bayar` VARCHAR(20) NULL DEFAULT NULL AFTER `status_pesanan`,
    ADD COLUMN IF NOT EXISTS `waktu_bayar` DATETIME NULL DEFAULT NULL AFTER `metode_bayar`,
    ADD COLUMN IF NOT EXISTS `bukti_bayar` VARCHAR(255) NULL DEFAULT NULL AFTER `waktu_bayar`,
    ADD COLUMN IF NOT EXISTS `waktu_diproses` DATETIME NULL DEFAULT NULL AFTER `bukti_bayar`,
    ADD COLUMN IF NOT EXISTS `waktu_dikirim` DATETIME NULL DEFAULT NULL AFTER `waktu_diproses`,
    ADD COLUMN IF NOT EXISTS `waktu_selesai` DATETIME NULL DEFAULT NULL AFTER `waktu_dikirim`;

ALTER TABLE `pesanan`
    ADD CONSTRAINT `fk_pesanan_driver` FOREIGN KEY IF NOT EXISTS (`id_driver`)
    REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE;
