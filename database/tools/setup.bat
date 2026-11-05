@echo off
REM =====================================================================
REM AntarRasa - Setup Basis Data untuk Windows / XAMPP
REM ---------------------------------------------------------------------
REM Urutan: buat database (jika belum ada) -> schema.sql -> migrations\*.sql
REM         (urut nama) -> seed.sql. Seluruh langkah IDEMPOTEN: aman dijalankan
REM         ulang tanpa menghapus atau menimpa data lokal yang sudah ada.
REM
REM Pemakaian (dari folder mana pun):
REM   database\tools\setup.bat              setup lengkap + data demo
REM   database\tools\setup.bat --tanpa-seed tanpa data demo
REM
REM Variabel opsional (set sebelum menjalankan):
REM   MYSQL_BIN (default C:\xampp\mysql\bin\mysql.exe), DB_HOST (localhost),
REM   DB_PORT (3306), DB_USER (root), DB_PASS (kosong), DB_NAME (antarrasa_db)
REM =====================================================================
setlocal EnableExtensions

if not defined MYSQL_BIN set "MYSQL_BIN=C:\xampp\mysql\bin\mysql.exe"
if not defined DB_HOST set "DB_HOST=localhost"
if not defined DB_PORT set "DB_PORT=3306"
if not defined DB_USER set "DB_USER=root"
if not defined DB_NAME set "DB_NAME=antarrasa_db"
if defined DB_PASS set "MYSQL_PWD=%DB_PASS%"

set "DIR_DB=%~dp0.."
set "MYSQL_ARGS=--default-character-set=utf8mb4 -h %DB_HOST% -P %DB_PORT% -u %DB_USER%"

if not exist "%MYSQL_BIN%" (
    echo [GAGAL] mysql client tidak ditemukan di "%MYSQL_BIN%".
    echo         Set variabel MYSQL_BIN ke lokasi mysql.exe Anda.
    exit /b 1
)

echo [1/4] Membuat database %DB_NAME% (jika belum ada)...
"%MYSQL_BIN%" %MYSQL_ARGS% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; ALTER DATABASE %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 goto gagal

echo [2/4] Menerapkan schema.sql...
"%MYSQL_BIN%" %MYSQL_ARGS% %DB_NAME% < "%DIR_DB%\schema.sql"
if errorlevel 1 goto gagal

echo [3/4] Menjalankan migrasi...
for /f "delims=" %%F in ('dir /b /o:n "%DIR_DB%\migrations\*.sql"') do (
    echo       - %%F
    "%MYSQL_BIN%" %MYSQL_ARGS% %DB_NAME% < "%DIR_DB%\migrations\%%F"
    if errorlevel 1 goto gagal
)

if /i "%~1"=="--tanpa-seed" (
    echo [4/4] Data demo dilewati ^(--tanpa-seed^).
) else (
    echo [4/4] Mengisi data demo ^(seed.sql^)...
    "%MYSQL_BIN%" %MYSQL_ARGS% %DB_NAME% < "%DIR_DB%\seed.sql"
    if errorlevel 1 goto gagal
)

echo.
echo [SELESAI] Database %DB_NAME% siap digunakan. Akun demo: admin / password123
endlocal
exit /b 0

:gagal
echo.
echo [GAGAL] Setup database berhenti karena terjadi error di atas.
endlocal
exit /b 1
