{{--
    Applies the stored appearance before first paint so neither the Inertia app nor
    the Flux-rendered auth screens flash the wrong theme. `flux.appearance` is the
    single stored preference; dark is the product default.
--}}
<script>
    (function () {
        var key = 'flux.appearance';
        var stored = null;

        try {
            stored = window.localStorage.getItem(key);
        } catch (error) {
            stored = null;
        }

        var theme = stored === 'light' ? 'light' : 'dark';

        if (stored !== theme) {
            try {
                window.localStorage.setItem(key, theme);
            } catch (error) {
                // Storage is unavailable; the default still applies for this visit.
            }
        }

        document.documentElement.dataset.theme = theme;
        document.documentElement.classList.toggle('dark', theme === 'dark');
    })();
</script>
