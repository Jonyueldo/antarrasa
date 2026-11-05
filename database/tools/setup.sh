#!/usr/bin/env sh
# =====================================================================
# AntarRasa - Setup Basis Data untuk macOS / Linux
# ---------------------------------------------------------------------
# Urutan: buat database (jika belum ada) -> schema.sql -> migrations/*.sql
#         (urut nama) -> seed.sql. Seluruh langkah IDEMPOTEN: aman dijalankan
#         ulang tanpa menghapus atau menimpa data lokal yang sudah ada.
#
# Pemakaian:
#   sh database/tools/setup.sh               setup lengkap + data demo
#   sh database/tools/setup.sh --tanpa-seed  tanpa data demo
#
# Variabel opsional:
#   MYSQL_BIN (default: mysql), DB_HOST (localhost), DB_PORT (3306),
#   DB_USER (root), DB_PASS (kosong), DB_NAME (antarrasa_db)
#   Contoh: DB_USER=root DB_PASS=rahasia sh database/tools/setup.sh
# =====================================================================
set -eu

DIR_DB=$(cd "$(dirname "$0")/.." && pwd)

MYSQL_BIN=${MYSQL_BIN:-mysql}
DB_HOST=${DB_HOST:-localhost}
DB_PORT=${DB_PORT:-3306}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-}
DB_NAME=${DB_NAME:-antarrasa_db}

# Password lewat environment agar tidak muncul di daftar proses
if [ -n "$DB_PASS" ]; then
    MYSQL_PWD=$DB_PASS
    export MYSQL_PWD
fi

if ! command -v "$MYSQL_BIN" >/dev/null 2>&1; then
    echo "[GAGAL] mysql client '$MYSQL_BIN' tidak ditemukan. Set MYSQL_BIN ke lokasi mysql Anda." >&2
    exit 1
fi

jalankan_mysql() {
    "$MYSQL_BIN" --default-character-set=utf8mb4 -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$@"
}

echo "[1/4] Membuat database $DB_NAME (jika belum ada)..."
jalankan_mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; ALTER DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "[2/4] Menerapkan schema.sql..."
jalankan_mysql "$DB_NAME" < "$DIR_DB/schema.sql"

echo "[3/4] Menjalankan migrasi..."
for berkas in $(ls "$DIR_DB/migrations/"*.sql | sort); do
    echo "      - $(basename "$berkas")"
    jalankan_mysql "$DB_NAME" < "$berkas"
done

if [ "${1:-}" = "--tanpa-seed" ]; then
    echo "[4/4] Data demo dilewati (--tanpa-seed)."
else
    echo "[4/4] Mengisi data demo (seed.sql)..."
    jalankan_mysql "$DB_NAME" < "$DIR_DB/seed.sql"
fi

echo
echo "[SELESAI] Database $DB_NAME siap digunakan. Akun demo: admin / password123"
