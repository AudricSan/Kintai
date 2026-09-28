/**
 * Notifications push (Web Push via FCM) : demande de permission, abonnement du
 * navigateur et enregistrement du jeton côté serveur. Auto-désactivé (rien ne
 * s'exécute) tant que #push-meta n'est pas rendu, c'est-à-dire tant que
 * PUSH_FCM_WEB_VAPID_KEY n'est pas configurée (voir config/push.php).
 *
 * L'échange abonnement navigateur → jeton FCM se fait en appelant directement
 * l'API publique fcmregistrations.googleapis.com (celle qu'utilise en interne
 * le SDK JS Firebase) plutôt que de charger ce SDK : évite une dépendance CDN
 * externe sur chaque page (l'app reste servie entièrement en local — voir
 * l'architecture "offline-capable" du projet).
 */
(function () {
    'use strict';

    var meta = document.getElementById('push-meta');
    if (!meta) return;
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        renderState('unsupported');
        return;
    }

    var projectId      = meta.dataset.projectId || '';
    var apiKey         = meta.dataset.apiKey || '';
    var appId          = meta.dataset.appId || '';
    var vapidKey       = meta.dataset.vapidKey || '';
    var subscribeUrl   = meta.dataset.subscribeUrl || '';
    var unsubscribeUrl = meta.dataset.unsubscribeUrl || '';
    var STORAGE_KEY    = 'kintai-push-token';

    var blocks = {
        unsupported: document.getElementById('push-status-unsupported'),
        denied:      document.getElementById('push-status-denied'),
        disabled:    document.getElementById('push-status-disabled'),
        enabled:     document.getElementById('push-status-enabled'),
    };

    function renderState(state) {
        Object.keys(blocks).forEach(function (key) {
            if (blocks[key]) blocks[key].hidden = key !== state;
        });
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // XHR plutôt que fetch() : le fetch() global est patché par app.js pour
    // injecter X-CSRF-Token sur tout POST, y compris vers cette origine tierce
    // — un header hors de la politique CORS de Google ferait échouer le preflight.
    function xhrJson(method, url, headers, body) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open(method, url, true);
            Object.keys(headers || {}).forEach(function (k) { xhr.setRequestHeader(k, headers[k]); });
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try { resolve(JSON.parse(xhr.responseText || '{}')); } catch (e) { resolve({}); }
                } else {
                    reject(new Error('http_' + xhr.status));
                }
            };
            xhr.onerror = function () { reject(new Error('network_error')); };
            xhr.send(body ? JSON.stringify(body) : null);
        });
    }

    function registerWithFcm(subscription) {
        var json = subscription.toJSON();
        return xhrJson(
            'POST',
            'https://fcmregistrations.googleapis.com/v1/projects/' + encodeURIComponent(projectId) + '/registrations',
            {
                'Content-Type': 'application/json',
                'x-goog-api-key': apiKey,
                'x-firebase-appid': appId,
            },
            {
                web: {
                    endpoint: json.endpoint,
                    p256dh: json.keys.p256dh,
                    auth: json.keys.auth,
                    applicationPubKey: vapidKey,
                },
            }
        ).then(function (data) {
            if (!data.token) throw new Error('fcm_registration_no_token');
            return data.token;
        });
    }

    function subscribe() {
        return Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') {
                renderState('denied');
                throw new Error('permission_denied');
            }
            return navigator.serviceWorker.ready;
        }).then(function (registration) {
            return registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidKey),
            });
        }).then(registerWithFcm).then(function (token) {
            try { localStorage.setItem(STORAGE_KEY, token); } catch (e) {}
            return fetch(subscribeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: token }),
            });
        }).then(function () {
            renderState('enabled');
        });
    }

    function unsubscribe() {
        return navigator.serviceWorker.ready.then(function (registration) {
            return registration.pushManager.getSubscription();
        }).then(function (subscription) {
            var token = null;
            try { token = localStorage.getItem(STORAGE_KEY); } catch (e) {}
            var done = subscription ? subscription.unsubscribe() : Promise.resolve();
            return done.then(function () {
                try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
                if (token) {
                    return fetch(unsubscribeUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ token: token }),
                    });
                }
            });
        }).then(function () {
            renderState('disabled');
        });
    }

    function detectInitialState() {
        if (Notification.permission === 'denied') {
            renderState('denied');
            return;
        }
        navigator.serviceWorker.ready.then(function (registration) {
            return registration.pushManager.getSubscription();
        }).then(function (subscription) {
            renderState(subscription ? 'enabled' : 'disabled');
        }).catch(function () {
            renderState('disabled');
        });
    }

    var enableBtn  = document.getElementById('push-enable-btn');
    var disableBtn = document.getElementById('push-disable-btn');

    if (enableBtn) {
        enableBtn.addEventListener('click', function () {
            enableBtn.disabled = true;
            subscribe().catch(function () {
                if (Notification.permission !== 'denied') renderState('disabled');
            }).then(function () {
                enableBtn.disabled = false;
            });
        });
    }
    if (disableBtn) {
        disableBtn.addEventListener('click', function () {
            disableBtn.disabled = true;
            unsubscribe().catch(function () {}).then(function () {
                disableBtn.disabled = false;
            });
        });
    }

    detectInitialState();
})();
