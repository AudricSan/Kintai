/**
 * Store form : toggle déductions + cartes fonctionnalités interactives.
 */
(function () {
    /* ── Toggle déductions ─────────────────────── */
    var dedToggle = document.querySelector('[name="ded_enabled"]');
    if (dedToggle) {
        dedToggle.addEventListener('change', function () {
            var fields = document.getElementById('ded-fields');
            if (fields) fields.hidden = !this.checked;
        });
    }

    /* ── Style du symbole yen (visible seulement si devise = JPY) ─── */
    var currencySelect = document.querySelector('[name="currency"]');
    var jpyStyleGroup   = document.getElementById('jpy-symbol-style-group');
    if (currencySelect && jpyStyleGroup) {
        var toggleJpyStyle = function () {
            jpyStyleGroup.hidden = currencySelect.value !== 'JPY';
        };
        currencySelect.addEventListener('change', toggleJpyStyle);
        toggleJpyStyle();
    }

    /* ── Cartes de fonctionnalités ──────────────── */
    var lang   = document.documentElement.lang || 'fr';
    var onTxt  = lang === 'ja' ? '有効' : (lang === 'en' ? 'Active'   : 'Actif');
    var offTxt = lang === 'ja' ? '無効' : (lang === 'en' ? 'Inactive' : 'Inactif');

    function applyFeatureState(card, checked) {
        card.classList.toggle('feature-card--on', checked);
        var lbl = card.querySelector('.toggle-switch__label');
        if (lbl) lbl.textContent = checked ? onTxt : offTxt;
    }

    document.querySelectorAll('.feature-card__input').forEach(function (cb) {
        applyFeatureState(cb.closest('.feature-card'), cb.checked);

        cb.addEventListener('change', function () {
            applyFeatureState(this.closest('.feature-card'), this.checked);
        });
    });
})();
