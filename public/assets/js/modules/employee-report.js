'use strict';
(function () {
    var _cfgEl = document.getElementById('kintai-report-data');
    var i18n = (_cfgEl ? JSON.parse(_cfgEl.textContent || '{}') : (window.KintaiReport || {})).i18n || {};

    var _pendingUrl = '';

    var now     = new Date();
    var y       = now.getFullYear();
    var mo      = String(now.getMonth() + 1).padStart(2, '0');
    var lastDay = new Date(y, now.getMonth() + 1, 0).getDate();
    document.getElementById('ps-from').value = y + '-' + mo + '-01';
    document.getElementById('ps-to').value   = y + '-' + mo + '-' + String(lastDay).padStart(2, '0');

    var todayFrom = y + '-' + mo + '-01';
    document.querySelectorAll('.ps-month-btn').forEach(function (btn) {
        if (btn.dataset.from === todayFrom) btn.classList.add('active');
    });

    document.getElementById('ps-month-grid').addEventListener('click', function (e) {
        var btn = e.target.closest('.ps-month-btn');
        if (!btn) return;
        document.querySelectorAll('.ps-month-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        document.getElementById('ps-from').value = btn.dataset.from;
        document.getElementById('ps-to').value   = btn.dataset.to;
    });

    ['ps-from', 'ps-to'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', function () {
            document.querySelectorAll('.ps-month-btn').forEach(function (b) { b.classList.remove('active'); });
        });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ps-period-trigger');
        if (!btn) return;
        _pendingUrl = btn.dataset.url || '';
        document.getElementById('ps-period-modal').removeAttribute('hidden');
    });

    window.psPeriodClose = function () {
        document.getElementById('ps-period-modal').setAttribute('hidden', '');
        _pendingUrl = '';
    };

    window.psPeriodOpen = function () {
        var from = document.getElementById('ps-from').value;
        var to   = document.getElementById('ps-to').value;
        if (!from || !to || from > to) {
            alert(i18n.invalidDateRange);
            return;
        }
        var url = _pendingUrl
            .replace('__FROM__', encodeURIComponent(from))
            .replace('__TO__',   encodeURIComponent(to));
        window.open(url, '_blank');
        psPeriodClose();
    };

    document.getElementById('ps-period-modal').addEventListener('click', function (e) {
        if (e.target === this) psPeriodClose();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') psPeriodClose();
    });
}());
