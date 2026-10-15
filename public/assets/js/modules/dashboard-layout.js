/**
 * Tableaux de bord : widgets déplaçables (partiel _dashboard-grid.php).
 *
 * Le bouton « Personnaliser » appelle window.kintaiDashLayout.setEditing() : en mode édition, chaque widget affiche une
 * barre d'outils — poignée de glisser-déposer, flèches ↑↓ (mobile tactile, clavier) et bascule demi / pleine largeur.
 * Chaque changement est enregistré aussitôt, en arrière-plan (POST JSON vers data-save-url) ; « Réinitialiser l'ordre »
 * revient à la disposition par défaut et recharge la page.
 *
 * Widgets affichés (POST JSON vers data-widgets-url) : le bouton ✕ d'un widget le retire de la page et le remet dans
 * la liste « Ajouter un widget » ; ajouter un widget le place en fin de tableau de bord puis recharge la page (son
 * contenu est rendu côté serveur), le mode édition restant ouvert et la page positionnée sur le nouveau widget.
 */
(function () {
    'use strict';

    var grid = document.querySelector('[data-dash-grid]');
    if (!grid) return;

    var saveUrl = grid.getAttribute('data-save-url');
    var widgetsUrl = grid.getAttribute('data-widgets-url');
    var EDIT_FLAG = 'kintai.dashEditing';
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

    // Renvoie une promesse résolue avec la réponse, ou avec null en cas d'échec (signalé sur la grille).
    function post(body, url) {
        return fetch(url || saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (res) {
            if (!res.ok) throw new Error(String(res.status));
            return res;
        }).catch(function () {
            grid.classList.add('dash-grid--save-error');
            grid.setAttribute('title', grid.getAttribute('data-label-error') || '');
            return null;
        });
    }

    function addMenu() {
        return document.querySelector('[data-dash-add-menu]');
    }

    function refreshAddMenu() {
        var menu = addMenu();
        if (!menu) return;
        var empty = menu.querySelector('[data-dash-add-empty]');
        if (empty) empty.hidden = menu.querySelector('[data-dash-add]') !== null;
    }

    // Retrait : le widget quitte la page et revient dans la liste « Ajouter un widget ».
    function removeWidget(item) {
        var key = item.getAttribute('data-widget-key');
        post({ remove: key }, widgetsUrl).then(function (res) {
            if (!res) return;
            var menu = addMenu();
            var list = menu && menu.querySelector('.dash-add-widget__menu');
            if (list) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'dash-add-widget__item';
                btn.setAttribute('role', 'menuitem');
                btn.setAttribute('data-dash-add', key);
                btn.textContent = item.getAttribute('data-widget-label') || key;
                list.insertBefore(btn, list.querySelector('[data-dash-add-empty]'));
            }
            item.remove();
            refreshAddMenu();
            window.dispatchEvent(new Event('resize'));
        });
    }

    function addWidget(btn) {
        var key = btn.getAttribute('data-dash-add');
        btn.disabled = true;
        post({ add: key }, widgetsUrl).then(function (res) {
            if (!res) { btn.disabled = false; return; }
            try { sessionStorage.setItem(EDIT_FLAG, '1'); } catch (err) { /* stockage indisponible */ }
            window.location.hash = 'dash-widget-' + key;
            window.location.reload();
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
        var btn = e.target.closest('[data-dash-move], [data-dash-width], [data-dash-remove]');
        if (!btn || !grid.contains(btn)) return;
        var item = btn.closest('[data-widget-key]');
        if (!item) return;

        if (btn.hasAttribute('data-dash-remove')) {
            removeWidget(item);
            return;
        }
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

    // ── Panneau « Personnaliser » : réinitialisation et ajout d'un widget ──────────────────────────────────────
    document.addEventListener('click', function (e) {
        var add = e.target.closest('[data-dash-add]');
        if (add) {
            addWidget(add);
            return;
        }
        var btn = e.target.closest('[data-dash-reset]');
        if (!btn) return;
        btn.disabled = true;
        post({ reset: true }).then(function () { window.location.reload(); });
    });

    window.kintaiDashLayout = { setEditing: setEditing };

    // Après l'ajout d'un widget : rouvrir le panneau « Personnaliser » (et donc le mode édition).
    var reopen = false;
    try {
        reopen = sessionStorage.getItem(EDIT_FLAG) === '1';
        sessionStorage.removeItem(EDIT_FLAG);
    } catch (err) { /* stockage indisponible */ }
    if (reopen) {
        var panel = document.querySelector('.dash-customize-panel.hidden');
        if (panel) {
            panel.classList.remove('hidden');
            setEditing(true);
        }
    }
}());
