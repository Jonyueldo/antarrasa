-- =====================================================================
-- Migrasi 004: Harga satuan historis pada `detail_pesanan` (D5)
-- Idempoten: aman dijalankan berulang kali. JANGAN diedit setelah di-push;
-- buat file migrasi baru untuk perubahan berikutnya.
-- =====================================================================

ALTER TABLE `detail_pesanan`
    ADD COLUMN IF NOT EXISTS `harga_satuan` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `jumlah`;

-- Rekonstruksi harga satuan transaksi lama dari subtotal yang tersimpan
-- (lebih akurat daripada harga menu saat ini yang mungkin sudah berubah)
UPDATE `detail_pesanan`
SET `harga_satuan` = ROUND(`subtotal` / `jumlah`, 2)
WHERE `harga_satuan` = 0 AND `jumlah` > 0;
