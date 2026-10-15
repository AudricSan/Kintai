/**
 * Assistant de configuration, étape « Bundles » : empêche de cocher plus de bundles que la licence n'en permet
 * (data-max sur le formulaire ; vide = illimité) et affiche le compteur. Le serveur vérifie de nouveau.
 */
(function () {
    'use strict';

    var form = document.getElementById('setup-bundles-form');
    if (!form) return;

    var max = parseInt(form.getAttribute('data-max') || '', 10);
    var counter = document.getElementById('setup-bundles-count');
    var boxes = form.querySelectorAll('input[type="checkbox"][name="bundles[]"]');

    function sync() {
        var checked = 0;
        boxes.forEach(function (b) { if (b.checked) checked++; });
        if (!isNaN(max)) {
            boxes.forEach(function (b) { b.disabled = !b.checked && checked >= max; });
        }
        if (counter) {
            counter.textContent = (counter.getAttribute('data-template') || '').replace('{count}', String(checked));
        }
    }

    boxes.forEach(function (b) { b.addEventListener('change', sync); });
    sync();
}());
