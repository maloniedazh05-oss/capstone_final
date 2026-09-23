<?php
// Shared frontend assets with CDN fallback.
// Usage in <head>: set $NEED_CHART = true on chart pages, then require_once this file.
// Set $NEED_CHART = true only on pages that draw charts; icons load everywhere.
// Local files first (offline-safe); the CDN is fetched only if the local
// copy failed, so deploys missing assets/ still render charts and icons.
?>
<link rel="stylesheet" href="assets/node_modules/@fortawesome/fontawesome-free/css/all.min.css">
<?php if (!empty($NEED_CHART)): ?>
<script src="assets/node_modules/chart.js/dist/chart.umd.js"></script>
<script>if (typeof Chart === 'undefined') { document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.js"><\/script>'); }</script>
<?php endif; ?>
<script>
(function () {
    // Font Awesome probe: the ::before glyph exists only if the CSS loaded.
    var probe = document.createElement('i');
    probe.className = 'fa-solid fa-circle';
    probe.style.display = 'none';
    document.head.appendChild(probe);
    var ok = false;
    try {
        var content = getComputedStyle(probe, '::before').getPropertyValue('content');
        ok = content && content !== 'none' && content !== 'normal';
    } catch (e) {
        ok = false;
    }
    probe.remove();
    if (!ok) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7.3.1/css/all.min.css';
        document.head.appendChild(link);
    }
})();
</script>
