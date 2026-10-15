/**
 * Tableaux de bord : widgets déplaçables (partiel _dashboard-grid.php).
 *
 * Le bouton « Personnaliser » appelle window.kintaiDashLayout.setEditing() : en mode édition, chaque widget affiche une
 * barre d'outils — poignée de glisser-déposer, flèches ↑↓ (mobile tactile, clavier) et bascule demi / pleine largeur.
 * Chaque changement est enregistré aussitôt, en arrière-plan (POST JSON vers data-save-url) ; « Réinitialiser l'ordre »
 * revient à la disposition par défaut et recharge la page.
 */
(function () {
    'use strict';

    var grid = document.querySelector('[data-dash-grid]');
    if (!grid) return;

    var saveUrl = grid.getAttribute('data-save-url');
    var saveTimer = null;
    var dragged = null;

    function items() {
        return Array.prototype.slice.call(grid.querySelectorAll(':scope > [data-widget-key]'));
    }

    function save() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            var order = [];
            var widths = {};
            items().forEach(function (item) {
                var key = item.getAttribute('data-widget-key');
                order.push(key);
                widths[key] = item.getAttribute('data-widget-width');
            });
            post({ order: order, widths: widths });
        }, 300);
    }

    function post(body) {
        return fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (res) {
            if (!res.ok) throw new Error(String(res.status));
            return res;
        }).catch(function () {
            grid.classList.add('dash-grid--save-error');
            grid.setAttribute('title', grid.getAttribute('data-label-error') || '');
        });
    }

    function setEditing(on) {
        grid.classList.toggle('dash-grid--editing', !!on);
        items().forEach(function (item) { item.draggable = false; });
    }

    function setWidth(item, width) {
        item.setAttribute('data-widget-width', width);
        item.classList.toggle('dash-grid__item--half', width === 'half');
        item.classList.toggle('dash-grid__item--full', width !== 'half');
        var btn = item.querySelector('[data-dash-width]');
        if (btn) {
            btn.textContent = grid.getAttribute(width === 'half' ? 'data-label-full' : 'data-label-half') || '';
        }
        // Les graphiques (Chart.js) se recalent sur leur nouveau conteneur.
        window.dispatchEvent(new Event('resize'));
    }

    // ── Flèches et largeur ───────────────────────────────────────────────────
    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-dash-move], [data-dash-width]');
        if (!btn || !grid.contains(btn)) return;
        var item = btn.closest('[data-widget-key]');
        if (!item) return;

        if (btn.hasAttribute('data-dash-width')) {
            setWidth(item, item.getAttribute('data-widget-width') === 'half' ? 'full' : 'half');
        } else if (btn.getAttribute('data-dash-move') === 'up') {
            var prev = item.previousElementSibling;
            if (!prev) return;
            grid.insertBefore(item, prev);
        } else {
            var next = item.nextElementSibling;
            if (!next) return;
            grid.insertBefore(next, item);
        }
        btn.focus();
        save();
    });

    // ── Glisser-déposer (souris) : seul l'appui sur la poignée rend le widget déplaçable ─────────────────────────
    grid.addEventListener('pointerdown', function (e) {
        var handle = e.target.closest('[data-dash-handle]');
        if (!handle || !grid.classList.contains('dash-grid--editing')) return;
        var item = handle.closest('[data-widget-key]');
        if (item) item.draggable = true;
    });

    grid.addEventListener('dragstart', function (e) {
        var item = e.target.closest && e.target.closest('[data-widget-key]');
        if (!item || !item.draggable) return;
        dragged = item;
        item.classList.add('dash-grid__item--dragging');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', item.getAttribute('data-widget-key')); } catch (err) { /* IE */ }
    });

    grid.addEventListener('dragover', function (e) {
        if (!dragged) return;
        e.preventDefault();
        var target = e.target.closest('[data-widget-key]');
        if (!target || target === dragged) return;
        var rect = target.getBoundingClientRect();
        // Widget en demi-largeur : avant/après selon la moitié gauche/droite ; sinon selon la moitié haute/basse.
        var after = target.getAttribute('data-widget-width') === 'half'
            ? e.clientX > rect.left + rect.width / 2
            : e.clientY > rect.top + rect.height / 2;
        grid.insertBefore(dragged, after ? target.nextElementSibling : target);
    });

    grid.addEventListener('drop', function (e) {
        if (dragged) e.preventDefault();
    });

    grid.addEventListener('dragend', function () {
        if (!dragged) return;
        dragged.classList.remove('dash-grid__item--dragging');
        dragged.draggable = false;
        dragged = null;
        save();
    });

    // ── Réinitialisation (bouton du panneau « Personnaliser ») ─────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-dash-reset]');
        if (!btn) return;
        btn.disabled = true;
        post({ reset: true }).then(function () { window.location.reload(); });
    });

    window.kintaiDashLayout = { setEditing: setEditing };
}());
