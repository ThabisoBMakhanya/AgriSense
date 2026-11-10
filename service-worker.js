const CACHE_NAME = 'agrisense-offline-v10';
const OFFLINE_FILES = [
  '/farm_journal_offline.html',
  '/assets/farm-journal-ui.html',
  '/assets/css/style.css?v=20261101-journal-unified-2',
  '/assets/js/script.js?v=20261101-ui-simple-1',
  '/assets/js/farm-journal.js?v=20261101-journal-unified-2',
  '/assets/js/farm-enterprises.js?v=20261025-enterprises-1',
  '/assets/manifest.webmanifest',
  '/assets/agrisense-logo.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(OFFLINE_FILES)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key.startsWith('agrisense-offline-') && key !== CACHE_NAME).map((key) => caches.delete(key)))));
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method !== 'GET' || url.origin !== self.location.origin) return;

  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(async () => (await caches.match('/farm_journal_offline.html')) || Response.error()));
    return;
  }

  if (url.pathname.startsWith('/assets/')) {
    event.respondWith(caches.match(request, { ignoreSearch: true }).then((cached) => cached || fetch(request).then((response) => {
      if (response.ok) caches.open(CACHE_NAME).then((cache) => cache.put(request, response.clone()));
      return response;
    })));
  }
});
