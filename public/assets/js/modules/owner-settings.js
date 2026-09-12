/**
 * Owner settings : pastilles de présélection pour la couleur principale.
 */
(function () {
    document.querySelectorAll('[data-color-presets-for]').forEach(function (group) {
        var input = document.getElementById(group.getAttribute('data-color-presets-for'));
        if (!input) return;

        var swatches = group.querySelectorAll('.color-preset');
        function syncActive() {
            swatches.forEach(function (btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-color').toLowerCase() === input.value.toLowerCase());
            });
        }

        swatches.forEach(function (btn) {
            var color = btn.getAttribute('data-color');
            btn.style.backgroundColor = color;
            btn.addEventListener('click', function () {
                input.value = color;
                input.dispatchEvent(new Event('input'));
                input.dispatchEvent(new Event('change'));
                syncActive();
            });
        });

        input.addEventListener('input', syncActive);
        syncActive();
    });
})();
