'use strict';
(function () {
    var _cfgEl = document.getElementById('kintai-report-issue-data');
    var cfg = _cfgEl ? JSON.parse(_cfgEl.textContent || '{}') : {};

    window.riOpen = function () {
        document.getElementById('ri-overlay').classList.add('open');
    };

    window.riClose = function () {
        document.getElementById('ri-overlay').classList.remove('open');
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') riClose();
    });

    if (cfg.autoOpen) {
        window.addEventListener('DOMContentLoaded', function () { riOpen(); });
    }
}());
