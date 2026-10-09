<?php
function render_theme_boot($base = '') {
    $base = rtrim((string)$base, '/');
    $baseJson = json_encode($base);
    echo <<<HTML
<script>
window.PB_BASE = {$baseJson};
(function () {
    var saved = localStorage.getItem('pb-theme') || 'system';
    var dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    var theme = saved === 'light' || saved === 'dark' ? saved : (dark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.setAttribute('data-theme-choice', saved);
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', theme === 'dark' ? '#020617' : '#f8fafc');
})();
</script>
HTML;
}

function render_theme_switch() {
    echo <<<HTML
<div class="pb-tools" role="group" aria-label="Apparence et application">
    <div class="pb-theme" role="group" aria-label="Thème">
        <button type="button" data-theme-choice="system" title="Thème du système" aria-label="Thème du système"><i class="fas fa-display"></i></button>
        <button type="button" data-theme-choice="light" title="Thème clair" aria-label="Thème clair"><i class="fas fa-sun"></i></button>
        <button type="button" data-theme-choice="dark" title="Thème sombre" aria-label="Thème sombre"><i class="fas fa-moon"></i></button>
    </div>
    <button type="button" class="pb-install">
        <i class="fas fa-download"></i><span>Installer l'app</span>
    </button>
</div>
HTML;
}
