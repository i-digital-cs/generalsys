{{-- Apply stored theme before CSS to avoid a light flash (T-9.13). --}}
<script>
(function () {
    try {
        var theme = localStorage.getItem('hr-pro-theme');
        if (theme === 'light' || theme === 'dark') {
            document.documentElement.setAttribute('data-bs-theme', theme);
        }
    } catch (e) {}
})();
</script>
