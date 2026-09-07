// assets/theme.js  v2.0
(function () {
    var html = document.documentElement;
    var KEY  = 'gymproTheme';

    /* ── Apply theme (sets data-theme + persists) ── */
    function applyTheme(t) {
        if (t !== 'light') t = 'dark';
        html.setAttribute('data-theme', t);
        try { localStorage.setItem(KEY, t); } catch(e) {}
    }

    /* ── Restore saved preference immediately (before paint) ── */
    var saved = 'dark';
    try { saved = localStorage.getItem(KEY) || 'dark'; } catch(e) {}
    applyTheme(saved);

    /* ── Single delegated click listener ── */
    document.addEventListener('click', function (e) {
        if (e.target.closest('#themeBtn, .theme-btn')) {
            e.preventDefault();
            var cur  = html.getAttribute('data-theme') || 'dark';
            applyTheme(cur === 'dark' ? 'light' : 'dark');
        }
    });
})();
