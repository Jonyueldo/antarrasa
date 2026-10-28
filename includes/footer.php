<?php
declare(strict_types=1);

$halaman_saat_ini = basename($_SERVER['PHP_SELF'] ?? '');
?>

<?php if ($halaman_saat_ini === 'admin.php'): ?>
    </main>
    <footer class="py-3 px-4 bg-white border-top text-muted small d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <div>
            <strong>AntarRasa Executive Backoffice</strong> &copy; <?= date('Y') ?> &bull; SRS IEEE Std 830-1998
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-secondary-subtle text-secondary border">PHP 8.5 Prosedural</span>
            <span class="badge bg-danger-subtle text-danger border">Admin Mode</span>
        </div>
    </footer>
    </div><!-- /.admin-main-content -->
    </div><!-- /.admin-layout-wrapper -->

<?php elseif ($halaman_saat_ini === 'login.php' || $halaman_saat_ini === 'register.php'): ?>
    <!-- Auth Split-Screen: Tidak menggunakan tag main/footer global -->

<?php else: ?>
    </main>

    <footer class="footer-customer mt-5 py-4 bg-white border-top">
        <div class="container text-center text-md-start">
            <div class="row align-items-center gy-3">
                <div class="col-md-6">
                    <div class="fw-bold text-dark mb-1 d-flex align-items-center justify-content-center justify-content-md-start gap-2">
                        <span class="badge p-1 bg-danger text-white"><i class="bi bi-egg-fried"></i></span>
                        <span class="fs-6">AntarRasa Indonesia &copy; <?= date('Y') ?></span>
                    </div>
                    <p class="mb-0 small text-muted">
                        Platform Pemesanan & Pengantaran Kuliner Online &bull; SRS IEEE Std 830-1998.
                        4 DataStore: <code>MENU</code>, <code>PESANAN</code>, <code>DETAIL_PESANAN</code>, <code>PENGGUNA</code>.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <span class="badge bg-light text-secondary border me-1">
                        <i class="bi bi-cpu me-1"></i> PHP 8.5 Prosedural
                    </span>
                    <span class="badge bg-light text-secondary border me-1">
                        <i class="bi bi-database me-1"></i> MySQLi Prosedural
                    </span>
                    <span class="badge bg-light text-secondary border">
                        <i class="bi bi-bootstrap me-1"></i> Bootstrap 5.3
                    </span>
                </div>
            </div>
        </div>
    </footer>
<?php endif; ?>

<!-- Bootstrap 5.3 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
