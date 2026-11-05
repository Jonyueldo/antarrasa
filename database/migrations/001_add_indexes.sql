-- =====================================================================
-- Migrasi 001: Indeks kolom filter/sort dashboard (D3) dan CHECK status (D4)
-- Idempoten: aman dijalankan berulang kali. JANGAN diedit setelah di-push;
-- buat file migrasi baru untuk perubahan berikutnya.
-- =====================================================================

CREATE INDEX IF NOT EXISTS `idx_pesanan_status` ON `pesanan` (`status_pesanan`);
CREATE INDEX IF NOT EXISTS `idx_pesanan_tanggal` ON `pesanan` (`tanggal_pesanan`);
CREATE INDEX IF NOT EXISTS `idx_pengguna_peran` ON `pengguna` (`peran`);
CREATE INDEX IF NOT EXISTS `idx_menu_status` ON `menu` (`status_menu`);

-- 'Dibatalkan' sudah disertakan untuk alur pembatalan pesanan oleh customer
ALTER TABLE `pesanan`
    ADD CONSTRAINT IF NOT EXISTS `chk_status_pesanan`
    CHECK (`status_pesanan` IN ('Menunggu', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan'));

ALTER TABLE `menu`
    ADD CONSTRAINT IF NOT EXISTS `chk_status_menu`
    CHECK (`status_menu` IN ('Tersedia', 'Habis'));
