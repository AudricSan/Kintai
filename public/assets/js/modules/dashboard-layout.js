/**
 * Tableaux de bord : widgets déplaçables (partiel _dashboard-grid.php).
 *
 * Le bouton « Personnaliser » appelle window.kintaiDashLayout.setEditing() : en mode édition, chaque widget affiche une
 * barre d'outils — poignée de glisser-déposer, flèches ↑↓ (mobile tactile, clavier) et bascule demi / pleine largeur.
 * Chaque changement est enregistré aussitôt, en arrière-plan (POST JSON vers data-save-url) ; « Réinitialiser l'ordre »
 * revient à la disposition par défaut et recharge la page.
 *
 * Widgets affichés (POST JSON vers data-widgets-url) : le bouton ✕ d'un widget le retire de la page et le remet dans
 * la liste « Ajouter un widget » ; ajouter un widget le place en fin de tableau de bord, sans rechargement : la page
 * est relue en arrière-plan (le contenu du widget est rendu côté serveur) et seul le nouveau widget est inséré, avec ses
 * dépendances (styles dynamiques, données de graphiques, scripts). Si le widget n'est pas trouvé dans la page relue,
 * la page est rechargée, le mode édition restant ouvert et la page positionnée sur le nouveau widget.
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

    // Nonce CSP de la page courante (lu sur un élément existant : l'attribut est masqué, la propriété reste lisible).
    function pageNonce(selector) {
        var el = document.querySelector(selector);
        return el ? (el.nonce || el.getAttribute('nonce') || '') : '';
    }

    // Styles dynamiques (style_class()) de la page relue absents de la page courante.
    function importStyles(doc) {
        var known = Array.prototype.map.call(document.querySelectorAll('style'), function (s) { return s.textContent; });
        var nonce = pageNonce('style[nonce]') || pageNonce('script[nonce]');
        Array.prototype.forEach.call(doc.querySelectorAll('style'), function (s) {
            if (known.indexOf(s.textContent) !== -1) return;
            var style = document.createElement('style');
            if (nonce) style.nonce = nonce;
            style.textContent = s.textContent;
            document.head.appendChild(style);
        });
    }

    // Blocs de données JSON (ex. #kintai-stats-data) : remplacés ou ajoutés. Renvoie true si l'un d'eux a changé.
    function importData(doc) {
        var changed = false;
        Array.prototype.forEach.call(doc.querySelectorAll('script[type="application/json"][id]'), function (s) {
            var current = document.getElementById(s.id);
            if (current && current.textContent === s.textContent) return;
            var data = document.createElement('script');
            data.type = 'application/json';
            data.id = s.id;
            data.textContent = s.textContent;
            if (current) current.replaceWith(data); else document.body.appendChild(data);
            changed = true;
        });
        return changed;
    }

    // Recrée un script (ceux insérés depuis un document analysé ne s'exécutent pas), avec le nonce de la page.
    function cloneScript(s) {
        var script = document.createElement('script');
        Array.prototype.forEach.call(s.attributes, function (a) {
            if (a.name !== 'nonce') script.setAttribute(a.name, a.value);
        });
        var nonce = pageNonce('script[nonce]');
        if (nonce) script.nonce = nonce;
        script.textContent = s.textContent;
        return script;
    }

    // Scripts externes de la page relue pas encore chargés ici (ex. stats-charts.js, dashboard.js).
    function importScripts(doc) {
        var loaded = Array.prototype.map.call(document.querySelectorAll('script[src]'), function (s) { return s.src; });
        Array.prototype.forEach.call(doc.querySelectorAll('script[src]'), function (s) {
            var src = new URL(s.getAttribute('src'), window.location.href).href;
            if (loaded.indexOf(src) === -1) document.body.appendChild(cloneScript(s));
        });
    }

    // Insère le widget rendu dans la page relue ; false s'il n'y figure pas.
    function insertWidget(doc, key) {
        var section = doc.getElementById('dash-widget-' + key);
        if (!section || !section.hasAttribute('data-widget-key')) return false;

        importStyles(doc);
        var dataChanged = importData(doc);
        var item = document.importNode(section, true);
        Array.prototype.forEach.call(item.querySelectorAll('script'), function (s) {
            s.replaceWith(s.type === 'application/json' ? s : cloneScript(s));
        });
        grid.appendChild(item);
        importScripts(doc);
        if (dataChanged && window.kintaiStatsCharts) window.kintaiStatsCharts.refresh();
        window.dispatchEvent(new Event('resize'));
        item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return true;
    }

    function reloadOn(key) {
        try { sessionStorage.setItem(EDIT_FLAG, '1'); } catch (err) { /* stockage indisponible */ }
        window.location.hash = 'dash-widget-' + key;
        window.location.reload();
    }

    function addWidget(btn) {
        var key = btn.getAttribute('data-dash-add');
        btn.disabled = true;
        post({ add: key }, widgetsUrl).then(function (res) {
            if (!res) { btn.disabled = false; return; }
            var url = window.location.href.split('#')[0];
            return fetch(url, { headers: { 'Accept': 'text/html' }, cache: 'no-store', credentials: 'same-origin' })
                .then(function (page) {
                    if (!page.ok) throw new Error(String(page.status));
                    return page.text();
                })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    if (!insertWidget(doc, key)) { reloadOn(key); return; }
                    btn.remove();
                    refreshAddMenu();
                    var menu = addMenu();
                    if (menu) menu.open = false;
                })
                .catch(function () { reloadOn(key); });
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
