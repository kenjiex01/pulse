<script>
    (function () {
        function hidePulseLoader() {
            var el = document.getElementById('pulse-full-screen-loader');
            if (el) {
                el.classList.add('hidden');
                el.setAttribute('aria-hidden', 'true');
            }
        }
        if (document.readyState === 'complete') {
            hidePulseLoader();
        } else {
            window.addEventListener('load', hidePulseLoader);
            document.addEventListener('DOMContentLoaded', hidePulseLoader);
        }
    })();
</script>
