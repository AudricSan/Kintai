/**
 * Liste employés : modal de sélection de période pour la fiche de paie.
 * Le message d'erreur i18n est lu depuis #payslip-meta (data-msg-invalid-range).
 * psPeriodClose et psPeriodOpen sont exposés en global (onclick dans la vue).
 */
(function () {
    var meta = document.getElementById('payslip-meta');
    if (!meta) return;

    var msgInvalidRange = meta.dataset.msgInvalidRange || '';
    var _pendingUrl = '';

    var fromInput = document.getElementById('ps-from');
    var toInput   = document.getElementById('ps-to');
    var modal     = document.getElementById('ps-period-modal');
    var grid      = document.getElementById('ps-month-grid');
    if (!fromInput || !toInput || !modal || !grid) return;

    // Pré-remplir avec le mois en cours
    var now     = new Date();
    var y       = now.getFullYear();
    var m       = String(now.getMonth() + 1).padStart(2, '0');
    var lastDay = new Date(y, now.getMonth() + 1, 0).getDate();
    fromInput.value = y + '-' + m + '-01';
    toInput.value   = y + '-' + m + '-' + String(lastDay).padStart(2, '0');

    var todayFrom = y + '-' + m + '-01';
    document.querySelectorAll('.ps-month-btn').forEach(function (btn) {
        if (btn.dataset.from === todayFrom) btn.classList.add('active');
    });

    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('.ps-month-btn');
        if (!btn) return;
        document.querySelectorAll('.ps-month-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        fromInput.value = btn.dataset.from;
        toInput.value   = btn.dataset.to;
    });

    ['ps-from', 'ps-to'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', function () {
            document.querySelectorAll('.ps-month-btn').forEach(function (b) { b.classList.remove('active'); });
        });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ps-period-trigger');
        if (!btn) return;
        _pendingUrl = btn.dataset.url || '';
        modal.removeAttribute('hidden');
    });

    window.psPeriodClose = function () {
        modal.setAttribute('hidden', '');
        _pendingUrl = '';
    };

    window.psPeriodOpen = function () {
        var from = fromInput.value;
        var to   = toInput.value;
        if (!from || !to || from > to) {
            alert(msgInvalidRange);
            return;
        }
        var url = _pendingUrl
            .replace('__FROM__', encodeURIComponent(from))
            .replace('__TO__',   encodeURIComponent(to));
        window.open(url, '_blank');
        window.psPeriodClose();
    };

    modal.addEventListener('click', function (e) {
        if (e.target === this) window.psPeriodClose();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') window.psPeriodClose();
    });
})();
