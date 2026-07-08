'use strict';
(function () {
    var _cfgEl = document.getElementById('kintai-timeline-data');
    var i18n = (_cfgEl ? JSON.parse(_cfgEl.textContent || '{}') : (window.KintaiTimeline || {})).i18n || {};

    var tip = document.createElement('div');
    tip.id = 'sd-diag-tip';
    tip.className = 'tl-diag-tip';
    document.body.appendChild(tip);

    function renderDiag(d) {
        var staffColor = d.understaffed ? '#fca5a5' : '#86efac';
        var html = '<div class="tl-diag-hdr">'
            + '👥 ' + d.staff + i18n.staff_planned_label
            + (d.min_staff > 0
                ? ' · ' + i18n.peak_abbr
                  + ' <span style="color:' + staffColor + '">' + d.peak + '</span>'
                  + '<span class="tl-diag-dim"> / min ' + d.min_staff + ' ' + i18n.simult_abbr + '</span>'
                : '')
            + (d.understaffed ? ' <span class="tl-diag-warn">— ' + i18n.understaffed_warn + '</span>' : '')
            + '</div>';
        if (d.understaffed_ranges && d.understaffed_ranges.length) {
            html += '<div class="tl-diag-section tl-diag-section--under">⚠ ' + i18n.understaffed_warn + ' (' + d.understaffed_ranges.length + ')</div>';
            d.understaffed_ranges.forEach(function (r) { html += '<div class="tl-diag-item">· ' + r + '</div>'; });
        }
        if (d.conflicts && d.conflicts.length) {
            html += '<div class="tl-diag-section tl-diag-section--conflict">⚡ ' + i18n.alert_conflicts + ' (' + d.conflicts.length + ')</div>';
            d.conflicts.forEach(function (c) { html += '<div class="tl-diag-item">· ' + c + '</div>'; });
        }
        if (d.short && d.short.length) {
            html += '<div class="tl-diag-section tl-diag-section--short">⏱ ' + i18n.alert_short_shifts + ' (' + d.short.length + ')</div>';
            d.short.forEach(function (s) { html += '<div class="tl-diag-item">· ' + s + '</div>'; });
        }
        if (d.long && d.long.length) {
            html += '<div class="tl-diag-section tl-diag-section--long">⏰ ' + i18n.alert_long_shifts + ' (' + d.long.length + ')</div>';
            d.long.forEach(function (l) { html += '<div class="tl-diag-item">· ' + l + '</div>'; });
        }
        return html;
    }

    document.addEventListener('mouseenter', function (e) {
        if (!(e.target instanceof Element)) return;
        var badge = e.target.closest('.sd-diag-badge');
        if (!badge) return;
        var d;
        try { d = JSON.parse(badge.dataset.diag || '{}'); } catch (_) { return; }
        tip.innerHTML = renderDiag(d);
        var rect   = badge.getBoundingClientRect();
        var tipW   = 340, margin = 8;
        var left   = Math.min(rect.left, window.innerWidth - tipW - margin);
        tip.classList.add('open');
        tip.style.left = Math.max(margin, left) + 'px';
        tip.style.top  = (rect.bottom + 6) + 'px';
    }, true);

    document.addEventListener('mouseleave', function (e) {
        if (e.target instanceof Element && e.target.closest('.sd-diag-badge')) tip.classList.remove('open');
    }, true);

    document.addEventListener('scroll', function () { tip.classList.remove('open'); }, true);
}());
