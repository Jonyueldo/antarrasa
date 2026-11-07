<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/fungsi.php';

mulai_sesi();
$_SESSION = [];
session_destroy();
alihkan('login.php');
