'use strict';
(function () {
    var _cfgEl = document.getElementById('kintai-feedback-data');
    var cfg  = _cfgEl ? JSON.parse(_cfgEl.textContent || '{}') : (window.KintaiFeedback || {});
    var BASE = cfg.baseUrl || '';
    var i18n = cfg.i18n   || {};

    var shiftsLoaded = false;

    window.fbOpen = function () {
        document.getElementById('fb-overlay').classList.add('open');
    };

    window.fbClose = function () {
        document.getElementById('fb-overlay').classList.remove('open');
    };

    window.fbCategoryChange = function (val) {
        var group = document.getElementById('fb-shift-group');
        if (val === 'shift') {
            group.style.display = '';
            document.getElementById('fb-shift-select').required = true;
            if (!shiftsLoaded) fbLoadShifts();
        } else {
            group.style.display = 'none';
            document.getElementById('fb-shift-select').required = false;
        }
    };

    function fbLoadShifts() {
        fetch(BASE + '/employee/feedback/past-shifts')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                shiftsLoaded = true;
                var sel  = document.getElementById('fb-shift-select');
                var hint = document.getElementById('fb-shift-hint');
                sel.innerHTML = '<option value="">' + i18n.selectShiftPlaceholder + '</option>';
                if (data.length === 0) {
                    hint.textContent = i18n.noPastShift;
                } else {
                    hint.textContent = '';
                    data.forEach(function (s) {
                        var opt = document.createElement('option');
                        opt.value       = s.id;
                        opt.textContent = s.label;
                        sel.appendChild(opt);
                    });
                }
            })
            .catch(function () {
                document.getElementById('fb-shift-hint').textContent = i18n.networkError;
            });
    }

    window.fbSetRating = function (val) {
        document.getElementById('fb-rating-input').value = val;
        document.querySelectorAll('.fb-star-btn').forEach(function (btn) {
            btn.classList.toggle('fb-star-btn--on', parseInt(btn.dataset.value) <= val);
        });
    };

    window.fbClearRating = function () {
        document.getElementById('fb-rating-input').value = '';
        document.querySelectorAll('.fb-star-btn').forEach(function (btn) {
            btn.classList.remove('fb-star-btn--on');
        });
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') fbClose();
    });

    if (cfg.autoOpen) {
        window.addEventListener('DOMContentLoaded', function () { fbOpen(); });
    }
}());
