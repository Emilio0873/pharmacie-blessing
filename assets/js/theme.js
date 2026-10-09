(function () {
    var KEY = 'pb-theme';
    var media = window.matchMedia('(prefers-color-scheme: dark)');

    function choice() {
        var saved = localStorage.getItem(KEY) || 'system';
        return saved === 'light' || saved === 'dark' ? saved : 'system';
    }

    function resolved(mode) {
        if (mode === 'light' || mode === 'dark') return mode;
        return media.matches ? 'dark' : 'light';
    }

    function apply(mode) {
        var theme = resolved(mode);
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-theme-choice', mode);
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.setAttribute('content', theme === 'dark' ? '#020617' : '#f8fafc');
        document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
            var on = btn.getAttribute('data-theme-choice') === mode;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var mode = btn.getAttribute('data-theme-choice');
            localStorage.setItem(KEY, mode);
            apply(mode);
        });
    });

    if (typeof media.addEventListener === 'function') {
        media.addEventListener('change', function () {
            if (choice() === 'system') apply('system');
        });
    }

    apply(choice());

    var installButtons = document.querySelectorAll('.pb-install');
    var deferred = null;
    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

    if (standalone) {
        installButtons.forEach(function (btn) { btn.hidden = true; });
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferred = event;
    });

    installButtons.forEach(function (installBtn) {
        installBtn.addEventListener('click', function () {
            if (deferred) {
                deferred.prompt();
                deferred.userChoice.finally(function () {
                    deferred = null;
                });
                return;
            }
            var ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
            if (ios) {
                alert("Sur iPhone : touchez Partager, puis « Sur l'écran d'accueil » pour installer Pharmacie Blessing.");
                return;
            }
            alert("Ouvrez ce site dans Chrome sur le téléphone, puis touchez « Installer l'app » ou le menu du navigateur, « Installer l'application ».");
        });
    });

    if ('serviceWorker' in navigator) {
        var base = window.PB_BASE || '';
        navigator.serviceWorker.register(base + '/sw.js').catch(function () {});
    }
})();
