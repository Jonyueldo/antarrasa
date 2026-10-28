<?php
declare(strict_types=1);

/**
 * Pengendali Keluar Sesi Pengguna (Logout)
 */

require_once __DIR__ . '/includes/functions_auth.php';

logout_pengguna();
header("Location: login.php?pesan=logout");
exit;
