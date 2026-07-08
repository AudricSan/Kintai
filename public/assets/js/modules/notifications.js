/**
 * Notifications : dropdown cloche + toasts au chargement + polling toutes les 15 s.
 */
(function () {
    'use strict';

    var meta     = document.getElementById('notif-meta');
    var labels   = {};
    var pollUrl  = '';

    if (meta) {
        try { labels  = JSON.parse(meta.dataset.labels  || '{}'); } catch (e) {}
        pollUrl = meta.dataset.pollUrl || '';
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    function escapeHtml(str) {
        var d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    // ── Toasts ───────────────────────────────────────────────────────────────

    var toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }

    function showToast(title, body) {
        var toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML =
            '<div>' +
                '<div class="toast__title">' + escapeHtml(title) + '</div>' +
                (body ? '<div class="toast__body">' + escapeHtml(body) + '</div>' : '') +
            '</div>';
        toastContainer.appendChild(toast);

        setTimeout(function () {
            toast.classList.add('toast--fading');
            toast.addEventListener('animationend', function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            });
        }, 4000);
    }

    // ── Badge ─────────────────────────────────────────────────────────────────

    function updateBadge(count) {
        var toggle = document.getElementById('notif-toggle');
        if (!toggle) return;

        var badge = toggle.querySelector('.notif-dropdown__badge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'notif-dropdown__badge';
                toggle.appendChild(badge);
            }
            badge.textContent = count > 99 ? '99+' : count;
        } else if (badge) {
            badge.parentNode.removeChild(badge);
        }
    }

    // ── Toasts au chargement (notifs reçues depuis la dernière visite) ────────

    if (meta) {
        try {
            var recent = JSON.parse(meta.dataset.recent || '[]');
            recent.forEach(function (n) {
                showToast(labels[n.type] || n.type, n.body);
            });
        } catch (e) {}
    }

    // ── Polling toutes les 15 s ───────────────────────────────────────────────

    if (pollUrl) {
        var lastCheck = new Date().toISOString();

        setInterval(function () {
            var url = pollUrl + '?since=' + encodeURIComponent(lastCheck);
            var now = new Date().toISOString(); // capturer avant la requête

            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data) return;
                    lastCheck = now;

                    if (data.notifications && data.notifications.length) {
                        data.notifications.forEach(function (n) {
                            showToast(labels[n.type] || n.type, n.body);
                        });
                    }

                    if (typeof data.unread_count === 'number') {
                        updateBadge(data.unread_count);
                    }
                })
                .catch(function () {}); // réseau indisponible : on ignore silencieusement
        }, 15000);
    }

    // ── Dropdown ─────────────────────────────────────────────────────────────

    var toggle   = document.getElementById('notif-toggle');
    var dropdown = document.getElementById('notif-dropdown');

    if (toggle && dropdown) {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.toggle('notif-dropdown--open');
        });

        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('notif-dropdown--open');
            }
        });
    }

})();
