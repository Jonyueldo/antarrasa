-- =====================================================================
-- Migrasi 005: Isi kategori menu lama yang masih NULL
-- Idempoten: hanya menyentuh baris dengan kategori NULL. JANGAN diedit
-- setelah di-push; buat file migrasi baru untuk perubahan berikutnya.
-- Urutan CASE penting: 'Sate Ayam' harus masuk 'sate', bukan 'ayam'.
-- Kunci kategori selaras dengan konstanta KATEGORI_MENU di functions_order.php.
-- =====================================================================

UPDATE `menu`
SET `kategori` = CASE
    WHEN LOWER(`nama_menu`) LIKE '%sate%' OR (LOWER(`nama_menu`) LIKE '%bakar%' AND LOWER(`nama_menu`) NOT LIKE '%ayam%') THEN 'sate'
    WHEN LOWER(`nama_menu`) LIKE '%mie%' OR LOWER(`nama_menu`) LIKE '%bakmi%' OR LOWER(`nama_menu`) LIKE '%kwetiau%' THEN 'mie'
    WHEN LOWER(`nama_menu`) LIKE '%nasi%' THEN 'nasi'
    WHEN LOWER(`nama_menu`) LIKE 'es %' OR LOWER(`nama_menu`) LIKE '%cendol%' OR LOWER(`nama_menu`) LIKE '%teh%'
         OR LOWER(`nama_menu`) LIKE '%kopi%' OR LOWER(`nama_menu`) LIKE '%jus%' OR LOWER(`nama_menu`) LIKE '%minuman%' THEN 'minuman'
    WHEN LOWER(`nama_menu`) LIKE '%rendang%' OR LOWER(`nama_menu`) LIKE '%daging%' OR LOWER(`nama_menu`) LIKE '%sapi%' THEN 'daging'
    WHEN LOWER(`nama_menu`) LIKE '%ayam%' OR LOWER(`nama_menu`) LIKE '%bebek%' THEN 'ayam'
    ELSE 'lainnya'
END
WHERE `kategori` IS NULL;
