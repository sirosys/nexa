{{--
    Harus dirender paling awal di <head>, sebelum CSS/konten lain, supaya class "dark"
    sudah terpasang di <html> sebelum browser sempat menggambar halaman (mencegah flash
    tema yang salah). Ikut preferensi sistem (prefers-color-scheme) selama user belum
    memilih tema secara manual lewat <x-theme-toggle> (pilihan manual disimpan di
    localStorage key "theme").
--}}
<script>
    (function () {
        var media = window.matchMedia('(prefers-color-scheme: dark)');

        function applyTheme() {
            var stored = localStorage.getItem('theme');
            var isDark = stored ? stored === 'dark' : media.matches;
            document.documentElement.classList.toggle('dark', isDark);
        }

        applyTheme();
        media.addEventListener('change', applyTheme);
    })();
</script>
