// Service worker Refleksi Mentee.
// BUILD_ID diubah otomatis oleh build.sh pada setiap push.
const BUILD_ID = '20261020052106-4';
const CACHE_NAME = 'refleksi-cache-' + BUILD_ID;
const OFFLINE_URL = 'offline.html';
const STATIC_ASSETS = [
  'manifest.json',
  'pwa-register.js',
  'audio-player.js',
  'build.json',
  'offline.html',
  'icons/icon-192.svg',
  'icons/icon-512.svg'
];

function sameOriginUrl(request) {
  return new URL(request.url).origin === self.location.origin;
}

function isCacheableAsset(url) {
  return /\.(?:css|js|json|svg|png|jpg|jpeg|webp|gif|woff2?|ttf)$/i.test(url.pathname);
}

async function cacheStaticAsset(request) {
  const cachedResponse = await caches.match(request);
  if (cachedResponse) {
    return cachedResponse;
  }

  const networkResponse = await fetch(request);
  if (networkResponse && networkResponse.ok) {
    const cache = await caches.open(CACHE_NAME);
    cache.put(request, networkResponse.clone());
  }
  return networkResponse;
}

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys
          .filter(key => key.startsWith('refleksi-cache-') && key !== CACHE_NAME)
          .map(key => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') {
    return;
  }

  if (!sameOriginUrl(event.request)) {
    return;
  }

  const requestUrl = new URL(event.request.url);
  const isNavigation = event.request.mode === 'navigate'
    || (event.request.headers.get('accept') || '').includes('text/html');

  if (isNavigation) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          if (response && response.ok) {
            const cloned = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, cloned));
          }
          return response;
        })
        .catch(async () => {
          const cachedResponse = await caches.match(event.request);
          return cachedResponse || caches.match(OFFLINE_URL);
        })
    );
    return;
  }

  // Jangan cache endpoint dinamis atau file konfigurasi (selain navigasi HTML).
  if (/\.(?:php|enc)$/i.test(requestUrl.pathname)) {
    return;
  }

  if (isCacheableAsset(requestUrl)) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          if (response && response.ok) {
            const cloned = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, cloned));
          }
          return response;
        })
        .catch(async () => {
          const cached = await caches.match(event.request);
          return cached || new Response('', { status: 504, statusText: 'Offline' });
        })
    );
    return;
  }

  event.respondWith(fetch(event.request));
});
