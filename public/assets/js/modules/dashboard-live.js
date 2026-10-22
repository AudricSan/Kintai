/**
 * Tableaux de bord : rafraîchissement automatique des widgets et mode mural (partiel _dashboard-grid.php).
 *
 * Rafraîchissement : chaque widget peut déclarer un intervalle (data-widget-refresh, en secondes ; 0 = jamais). Un seul
 * minuteur vérifie toutes les 15 s quels widgets sont échus, relit alors la page en arrière-plan (les droits, les magasins
 * visibles et les données sont donc exactement ceux du chargement initial) et ne remplace que le contenu des widgets
 * dont le HTML a changé. Rien ne se passe onglet caché, en mode édition, pour un widget replié ni quand le focus est dans
 * le widget (pas de perte de saisie). « Actualiser » force un passage. Un échec est silencieux (le contenu reste), sauf
 * une session expirée : un message clair remplace alors l'écran figé.
 *
 * Mode mural (bouton « Activer le mode mural ») : plein écran (API Fullscreen), barre latérale et en-tête masquées
 * (classe dash-wall sur <html>), écran maintenu allumé quand le navigateur le permet (Screen Wake Lock), sortie par Échap
 * ou le bouton « Quitter le mode mural ». Il reste derrière la session de l'utilisateur.
 */
(function () {
    'use strict';

    var grid = document.querySelector('[data-dash-grid]');
    if (!grid) return;

    var TICK_MS = 15000;
    var lastRun = {};
    var running = false;
    var expired = false;
    var lastUpdate = Date.now();
    var wakeLock = null;

    var labelEl = document.querySelector('[data-dash-updated]');
    var refreshBtn = document.querySelector('[data-dash-refresh-now]');
    var wallBtn = document.querySelector('[data-dash-wall]');
    var wallExit = document.querySelector('[data-dash-wall-exit]');
    // Le mode mural est réservé aux écrans de bureau et de tablette : même seuil que le CSS (téléphone ≤ 768 px).
    var phone = window.matchMedia('(max-width: 768px)');

    function items() {
        return Array.prototype.slice.call(grid.querySelectorAll(':scope > [data-widget-key]'));
    }

    function intervalOf(item) {
        return parseInt(item.getAttribute('data-widget-refresh'), 10) || 0;
    }

    function isEditing() {
        return grid.classList.contains('dash-grid--editing');
    }

    function isDue(item, force) {
        if (intervalOf(item) <= 0 || item.getAttribute('data-widget-collapsed') === '1') return false;
        if (item.contains(document.activeElement) && document.activeElement !== document.body) return false;
        if (force) return true;
        var key = item.getAttribute('data-widget-key');
        return Date.now() - (lastRun[key] || 0) >= intervalOf(item) * 1000;
    }

    function showUpdated() {
        if (!labelEl) return;
        var minutes = Math.floor((Date.now() - lastUpdate) / 60000);
        var text = minutes < 1
            ? (labelEl.getAttribute('data-label-now') || '')
            : (labelEl.getAttribute('data-label-ago') || '').replace(':n', String(minutes));
        labelEl.textContent = text;
    }

    function showExpired() {
        expired = true;
        if (labelEl) {
            labelEl.textContent = labelEl.getAttribute('data-label-expired') || '';
            labelEl.classList.add('dash-updated--error');
        }
    }

    function refresh(force) {
        if (running || expired) return;
        if (!force && (document.visibilityState !== 'visible' || isEditing())) return;
        var due = items().filter(function (item) { return isDue(item, force); });
        if (due.length === 0) return;

        running = true;
        if (refreshBtn) refreshBtn.disabled = true;
        var url = window.location.href.split('#')[0];
        fetch(url, { headers: { 'Accept': 'text/html' }, cache: 'no-store', credentials: 'same-origin' })
            .then(function (res) {
                // Session expirée : redirection vers la connexion ou refus.
                if (res.status === 401 || res.status === 403 || (res.redirected && /\/login/.test(res.url))) {
                    showExpired();
                    return null;
                }
                if (!res.ok) throw new Error(String(res.status));
                return res.text();
            })
            .then(function (html) {
                if (html === null) return;
                var doc = new DOMParser().parseFromString(html, 'text/html');
                due.forEach(function (item) {
                    var key = item.getAttribute('data-widget-key');
                    lastRun[key] = Date.now();
                    var fresh = doc.getElementById('dash-widget-' + key);
                    var from = fresh && fresh.querySelector(':scope > .dash-grid__content');
                    var to = item.querySelector(':scope > .dash-grid__content');
                    if (!from || !to || from.innerHTML === to.innerHTML) return;
                    to.innerHTML = from.innerHTML;
                });
                lastUpdate = Date.now();
                showUpdated();
                window.dispatchEvent(new Event('resize'));
            })
            .catch(function () { /* échec silencieux : le contenu actuel reste affiché */ })
            .then(function () {
                running = false;
                if (refreshBtn) refreshBtn.disabled = false;
            });
    }

    setInterval(function () { refresh(false); showUpdated(); }, TICK_MS);
    document.addEventListener('visibilitychange', function () {
        // Retour sur l'onglet : rattrape tout de suite ce qui est échu.
        if (document.visibilityState === 'visible') {
            refresh(false);
            acquireWakeLock();
        }
    });
    items().forEach(function (item) { lastRun[item.getAttribute('data-widget-key')] = Date.now(); });
    showUpdated();

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-dash-refresh-now]')) {
            refresh(true);
        } else if (e.target.closest('[data-dash-wall]')) {
            enterWall();
        } else if (e.target.closest('[data-dash-wall-exit]')) {
            exitWall();
        }
    });

    // ── Mode mural ───────────────────────────────────────────────────────────
    function acquireWakeLock() {
        if (!document.documentElement.classList.contains('dash-wall') || !('wakeLock' in navigator) || wakeLock) return;
        navigator.wakeLock.request('screen').then(function (lock) {
            wakeLock = lock;
            lock.addEventListener('release', function () { wakeLock = null; });
        }).catch(function () { /* refusé : l'écran peut s'éteindre, sans conséquence */ });
    }

    function releaseWakeLock() {
        if (wakeLock) {
            wakeLock.release().catch(function () { /* déjà libéré */ });
            wakeLock = null;
        }
    }

    function setWall(on) {
        document.documentElement.classList.toggle('dash-wall', on);
        if (wallBtn) wallBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
        // « Quitter » n'est visible et utilisable qu'en mode mural.
        if (wallExit) { wallExit.hidden = !on; wallExit.disabled = !on; }
        if (on) acquireWakeLock(); else releaseWakeLock();
        window.dispatchEvent(new Event('resize'));
    }

    function enterWall() {
        if (phone.matches) return;
        setWall(true);
        var el = document.documentElement;
        if (el.requestFullscreen) {
            el.requestFullscreen().catch(function () { /* plein écran refusé : le mode mural reste actif dans la fenêtre */ });
        }
    }

    function exitWall() {
        if (document.fullscreenElement && document.exitFullscreen) {
            document.exitFullscreen().catch(function () { /* déjà sorti */ });
        }
        setWall(false);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.documentElement.classList.contains('dash-wall') && !document.fullscreenElement) {
            setWall(false);
        }
    });

    // Le bouton est caché sur téléphone ; l'état de départ du bouton « Quitter » est aussi désactivé.
    if (wallBtn) wallBtn.disabled = phone.matches;
    if (wallExit) wallExit.disabled = true;
    var onPhoneChange = function () {
        if (wallBtn) wallBtn.disabled = phone.matches;
        // Écran réduit ou téléphone tourné en mode mural : on en sort plutôt que de laisser une page sans barres.
        if (phone.matches && document.documentElement.classList.contains('dash-wall')) exitWall();
    };
    if (phone.addEventListener) phone.addEventListener('change', onPhoneChange);
    else if (phone.addListener) phone.addListener(onPhoneChange);

    // Échap (ou tout autre moyen de quitter le plein écran) quitte aussi le mode mural.
    document.addEventListener('fullscreenchange', function () {
        if (!document.fullscreenElement && document.documentElement.classList.contains('dash-wall')) {
            setWall(false);
        }
    });
}());
