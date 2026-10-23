/**
 * Composant UserPicker (liste d'employés à cocher) : « tout sélectionner », « aucun » et compteur de sélection.
 * Délégation d'événements sur le document, donc valable pour tout sélecteur présent dans la page, y compris ceux d'un bundle.
 */
(function () {
    'use strict';

    function boxes(picker) {
        return picker.querySelectorAll('.user-picker__list input[type="checkbox"]');
    }

    function refresh(picker) {
        var counter = picker.querySelector('[data-user-picker-count]');
        if (!counter) {
            return;
        }
        var all = boxes(picker);
        var checked = 0;
        all.forEach(function (box) {
            if (box.checked) {
                checked++;
            }
        });
        var label = counter.getAttribute('data-label') || ':count / :total';
        counter.textContent = label.replace(':count', String(checked)).replace(':total', String(all.length));
    }

    function setAll(picker, state) {
        boxes(picker).forEach(function (box) {
            box.checked = state;
        });
        refresh(picker);
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        var picker = target.closest('[data-user-picker]');
        if (!picker) {
            return;
        }
        if (target.closest('[data-user-picker-all]')) {
            setAll(picker, true);
        } else if (target.closest('[data-user-picker-none]')) {
            setAll(picker, false);
        }
    });

    document.addEventListener('change', function (event) {
        var target = event.target;
        if (target instanceof Element && target.closest('.user-picker__list')) {
            var picker = target.closest('[data-user-picker]');
            if (picker) {
                refresh(picker);
            }
        }
    });

    function init() {
        document.querySelectorAll('[data-user-picker]').forEach(refresh);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
