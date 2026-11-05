/* ==========================================================================
   AntarRasa - Feedback submit form keranjang (JavaScript vanilla, tanpa framework)
   Form dengan atribut data-form-feedback akan:
   - menonaktifkan tombol submit & menampilkan spinner saat dikirim
   - mencegah klik ganda (form tidak terkirim dua kali)
   ========================================================================== */
(function () {
    'use strict';

    function tampilkanProses(tombol) {
        if (!tombol || tombol.dataset.htmlAsli !== undefined) {
            return;
        }
        // Tombol simbol kecil (−, +, ikon) cukup spinner; tombol berteks diberi label terlihat
        var berteks = tombol.textContent.trim().length > 2;
        tombol.dataset.htmlAsli = tombol.innerHTML;
        tombol.disabled = true;
        tombol.setAttribute('aria-disabled', 'true');
        tombol.innerHTML =
            '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>' +
            (berteks ? '<span class="ms-2">Memproses...</span>' : '<span class="visually-hidden">Memproses...</span>');
    }

    function pulihkan(form) {
        delete form.dataset.terkirim;
        form.removeAttribute('aria-busy');
        form.querySelectorAll('button[data-html-asli]').forEach(function (tombol) {
            tombol.innerHTML = tombol.dataset.htmlAsli;
            delete tombol.dataset.htmlAsli;
            tombol.disabled = false;
            tombol.removeAttribute('aria-disabled');
        });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-form-feedback')) {
            return;
        }
        // Konfirmasi (onsubmit="return confirm(...)") dibatalkan pengguna: jangan ubah apa pun
        if (e.defaultPrevented) {
            return;
        }
        // Cegah pengiriman ganda
        if (form.dataset.terkirim === '1') {
            e.preventDefault();
            return;
        }
        form.dataset.terkirim = '1';
        form.setAttribute('aria-busy', 'true');

        var pengirim = e.submitter || form.querySelector('button[type="submit"]');
        // Ditunda agar name/value tombol pengirim (mis. stepper jumlah) tetap ikut terkirim;
        // tombol yang di-disable SEBELUM data form disusun tidak akan ikut dikirim browser.
        setTimeout(function () {
            tampilkanProses(pengirim);
            form.querySelectorAll('button[type="submit"]').forEach(function (tombol) {
                if (tombol !== pengirim) {
                    tombol.disabled = true;
                }
            });
        }, 0);
    });

    // Tombol Kembali (bfcache) memulihkan halaman lama: aktifkan lagi tombolnya
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            document.querySelectorAll('form[data-form-feedback]').forEach(pulihkan);
        }
    });
})();
