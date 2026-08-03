/**
 * myshift — Core JS
 */
'use strict';

// --- CSRF : injection automatique dans les formulaires et les requêtes fetch ---
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) return;

    // Injecter _token dans tous les formulaires POST au chargement
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form').forEach(form => {
            const method = (form.getAttribute('method') || 'get').toUpperCase();
            if (method === 'POST' && !form.querySelector('input[name="_token"]')) {
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = '_token';
                input.value = csrfToken;
                form.appendChild(input);
            }
        });
    });

    // Patch fetch pour envoyer X-CSRF-Token sur les requêtes mutantes
    const _fetch = window.fetch;
    window.fetch = function (input, init = {}) {
        const method = (init.method || 'GET').toUpperCase();
        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
            init.headers = Object.assign({ 'X-CSRF-Token': csrfToken }, init.headers || {});
        }
        return _fetch(input, init);
    };
}());

// --- Topbar nav toggle (mobile) ---
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('topbarNavToggle');
    const nav = document.getElementById('topbarNav');

    if (!toggle || !nav) return;

    function closeNav() {
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    toggle.addEventListener('click', () => {
        const opening = !nav.classList.contains('open');
        nav.classList.toggle('open', opening);
        toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        document.body.style.overflow = opening ? 'hidden' : '';
    });

    // Ferme le menu après un tap sur un lien (couvre le retour arrière du
    // navigateur qui peut restaurer la page avec le menu resté ouvert).
    nav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', closeNav);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeNav();
    });

    // Restaure un état propre si la page est resservie depuis le bfcache.
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) closeNav();
    });
});

// --- Menus déroulants de la topbar (Planning, RH, Rapports...) ---
document.addEventListener('DOMContentLoaded', () => {
    const groups = document.querySelectorAll('.topbar-nav-group');
    if (!groups.length) return;

    groups.forEach((group) => {
        const trigger = group.querySelector('.topbar-nav-group__trigger');
        if (!trigger) return;
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const opening = !group.classList.contains('is-open');
            groups.forEach((g) => g.classList.remove('is-open'));
            group.classList.toggle('is-open', opening);
        });
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.topbar-nav-group')) {
            groups.forEach((g) => g.classList.remove('is-open'));
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            groups.forEach((g) => g.classList.remove('is-open'));
        }
    });
});

// --- Thème (sombre / clair) ---
(function () {
    var STORAGE_KEY = 'kintai-theme';

    function storedPref() {
        return localStorage.getItem(STORAGE_KEY);
    }

    // Calcul lever/coucher du soleil en heures locales (latitude 45° N, heure locale)
    function sunTimes() {
        var now  = new Date();
        var doy  = Math.round((now - new Date(now.getFullYear(), 0, 1)) / 86400000) + 1;
        var decl = 0.4093 * Math.sin(2 * Math.PI / 365 * (doy - 81));
        var lat  = 45 * Math.PI / 180;
        var cosH = -Math.tan(lat) * Math.tan(decl);
        cosH     = Math.max(-1, Math.min(1, cosH));
        var H    = Math.acos(cosH) * 12 / Math.PI;
        return { sunrise: 12 - H, sunset: 12 + H };
    }

    function isSundown() {
        var s = sunTimes();
        var h = new Date().getHours() + new Date().getMinutes() / 60;
        return h >= s.sunset || h < s.sunrise;
    }

    // Priorité 1 : préférence manuelle
    // Priorité 2 : prefers-color-scheme (OS)
    // Priorité 3 : coucher du soleil (fallback navigateurs anciens)
    function resolveTheme() {
        var manual = storedPref();
        if (manual) return manual;

        if (window.matchMedia && window.matchMedia('(prefers-color-scheme)').media !== 'not all') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        return isSundown() ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        document.documentElement.dataset.theme = theme;
        var isDark = theme === 'dark';
        var icon   = document.getElementById('theme-icon');
        var label  = document.getElementById('theme-label');
        if (icon)  icon.textContent  = isDark ? '☀️' : '🌙';
        if (label) label.textContent = isDark ? 'Mode clair' : 'Mode sombre';
        document.querySelectorAll('#themeToggle').forEach(function (btn) {
            btn.title = isDark ? 'Passer en mode clair' : 'Passer en mode sombre';
        });
    }

    // Appliquer immédiatement avant DOMContentLoaded pour éviter le flash
    applyTheme(resolveTheme());

    document.addEventListener('DOMContentLoaded', function () {
        // Synchroniser les icônes maintenant que le DOM est prêt
        applyTheme(resolveTheme());

        // Toggle manuel — stocke la préférence explicite
        document.querySelectorAll('#themeToggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
                localStorage.setItem(STORAGE_KEY, next);
                applyTheme(next);
            });
        });

        // Réévaluation périodique (seulement sans préférence manuelle ni préférence OS connue)
        setInterval(function () {
            if (!storedPref() &&
                window.matchMedia &&
                window.matchMedia('(prefers-color-scheme)').media === 'not all') {
                applyTheme(resolveTheme());
            }
        }, 60000);

        // Réévaluation au retour sur l'onglet (sans préférence manuelle)
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && !storedPref()) {
                applyTheme(resolveTheme());
            }
        });
    });
}());

// ── User dropdown ─────────────────────────────────
(function () {
    var dropdown = document.getElementById('user-dropdown');
    var toggle   = document.getElementById('user-toggle');
    if (!dropdown || !toggle) return;

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = dropdown.classList.toggle('user-dropdown--open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target)) {
            dropdown.classList.remove('user-dropdown--open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && dropdown.classList.contains('user-dropdown--open')) {
            dropdown.classList.remove('user-dropdown--open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
        }
    });
}());

// ── Generic modal open/close ─────────────────────
window.openModal = function (id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('open');
};

window.closeModal = function (id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('open');
};

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.modal.open').forEach(function (m) {
        m.classList.remove('open');
    });
});

// ── Filtres instantanés (texte : debounce, date/select restants : au change) ──
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form.filter-bar, form.shifts-filters').forEach(form => {
        let debounceTimer;
        form.querySelectorAll('input[type="text"]:not([onchange]):not([data-client-filter]), input[type="search"]:not([onchange]):not([data-client-filter])').forEach(input => {
            input.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => form.submit(), 500);
            });
        });
        form.querySelectorAll('input[type="date"]:not([onchange]), select:not([onchange])').forEach(input => {
            input.addEventListener('change', () => form.submit());
        });
    });
});

// ── Segmented control (ex. Liste/Calendrier/Timeline) ────────────────
// Anime le curseur vers l'option cliquée avant de suivre le lien, pour
// que le changement de vue ne soit pas un simple rechargement sec.
document.addEventListener('click', function (e) {
    var link = e.target.closest('.btn-group--switcher > a.btn:not(.btn--active)');
    if (!link) return;

    var group = link.closest('.btn-group--switcher');
    var thumb = group ? group.querySelector('.btn-group__thumb') : null;
    if (!thumb) return;

    var links = Array.prototype.filter.call(group.children, function (el) {
        return el.matches('a.btn');
    });
    var index = links.indexOf(link);
    if (index === -1) return;

    e.preventDefault();
    thumb.style.setProperty('--pos', index);
    setTimeout(function () { window.location.href = link.href; }, 180);
});
