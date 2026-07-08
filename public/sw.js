const CACHE = 'kintai-v2';
const ASSETS = [
  '/assets/css/app.css',
  '/assets/js/modules/timeclock.js',
  '/assets/js/modules/mobile.js',
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(ASSETS))
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    )
  );
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);

  // Skip non-GET, non-HTTP requests
  if (e.request.method !== 'GET' || !url.protocol.startsWith('http')) return;

  // API calls → network-first, fallback to cache
  if (url.pathname.startsWith('/api/')) {
    e.respondWith(networkFirst(e.request));
    return;
  }

  // Static assets → cache-first
  if (url.pathname.startsWith('/assets/')) {
    e.respondWith(cacheFirst(e.request));
    return;
  }

  // Everything else → network-only (dynamic PHP)
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
    return cached || new Response('Offline', { status: 503 });
  }
}

async function cacheFirst(req) {
  const cached = await caches.match(req);
  if (cached) return cached;
  try {
    return await fetch(req);
  } catch {
    return new Response('Offline', { status: 503 });
  }
}
