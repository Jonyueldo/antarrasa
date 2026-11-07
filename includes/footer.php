</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($pakai_peta)): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Peta posisi driver (Leaflet + OpenStreetMap, tanpa API key).
document.querySelectorAll('.peta').forEach(function (el) {
    var posisi = [parseFloat(el.dataset.lat), parseFloat(el.dataset.lng)];
    var peta = L.map(el).setView(posisi, 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '&copy; OpenStreetMap'}).addTo(peta);
    L.marker(posisi).addTo(peta).bindPopup('Posisi driver');
});
// Isi koordinat GPS browser sebelum submit; jika ditolak/gagal, server memakai koordinat simulasi.
document.querySelectorAll('form[data-gps]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
        if (!navigator.geolocation) return;
        ev.preventDefault();
        var kirim = function () { form.submit(); };
        navigator.geolocation.getCurrentPosition(function (p) {
            form.lat.value = p.coords.latitude;
            form.lng.value = p.coords.longitude;
            kirim();
        }, kirim, {timeout: 5000});
    });
});
</script>
<?php endif; ?>
</body>
</html>
