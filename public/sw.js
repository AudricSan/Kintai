const CACHE = 'kintai-v3';

// Base de déploiement déduite du scope d'enregistrement (ex. '/kintai') plutôt
// que codée en dur, pour fonctionner aussi bien à la racine du domaine que
// sous un sous-répertoire (installation self-hosted dans un sous-dossier).
const BASE = new URL(self.registration.scope).pathname.replace(/\/$/, '');

const OFFLINE_URL = BASE + '/offline.html';
const ASSETS = [
  BASE + '/assets/css/app.css',
  BASE + '/assets/js/app.js',
  BASE + '/assets/js/modules/notifications.js',
  BASE + '/assets/js/modules/timeclock.js',
  BASE + '/assets/js/modules/mobile.js',
  BASE + '/assets/img/kintai-192.png',
  OFFLINE_URL,
];

self.addEventListener('install', (e) => {
  self.skipWaiting();
  e.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(ASSETS))
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);

  // Ignore le cross-origin, et tout ce qui n'est pas HTTP(S) (extensions...).
  if (url.origin !== self.location.origin) return;

  // Navigation (ouverture/rechargement d'une page) : réseau d'abord pour rester à
  // jour, dernière version connue en secours, puis une page hors-ligne dédiée si
  // rien n'a jamais été mis en cache pour cette URL (ex. tout premier lancement
  // hors-ligne).
  if (e.request.mode === 'navigate') {
    e.respondWith(
      fetch(e.request)
        .then((res) => {
          if (res.ok) {
            const copy = res.clone();
            caches.open(CACHE).then((cache) => cache.put(e.request, copy));
          }
          return res;
        })
        .catch(() => caches.match(e.request).then((cached) => cached || caches.match(OFFLINE_URL)))
    );
    return;
  }

  if (e.request.method !== 'GET') return;

  // Appels API → réseau d'abord, secours cache (dernières données connues hors-ligne).
  if (url.pathname.startsWith(BASE + '/api/')) {
    e.respondWith(networkFirst(e.request));
    return;
  }

  // Assets statiques → cache d'abord (immuables entre deux déploiements).
  if (url.pathname.startsWith(BASE + '/assets/')) {
    e.respondWith(cacheFirst(e.request));
    return;
  }

  // Reste (pages dynamiques hors navigation, ex. requêtes fetch d'un module JS) :
  // réseau uniquement, sans mise en cache implicite de contenu potentiellement sensible.
});

async function networkFirst(req) {
  try {
    const res = await fetch(req);
    if (res.ok) {
      const cache = await caches.open(CACHE);
      cache.put(req, res.clone());
    }
    return res;
  } catch {
    const cached = await caches.match(req);
    return cached || new Response(JSON.stringify({ error: 'offline' }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' },
    });
  }
}

async function cacheFirst(req) {
  const cached = await caches.match(req);
  if (cached) return cached;
  try {
    const res = await fetch(req);
    if (res.ok) {
      const cache = await caches.open(CACHE);
      cache.put(req, res.clone());
    }
    return res;
  } catch {
    return new Response('Offline', { status: 503 });
  }
}
