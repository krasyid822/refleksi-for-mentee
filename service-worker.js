const CACHE_NAME = 'refleksi-cache-v3';
const STATIC_ASSETS = [
  'manifest.json',
  'pwa-register.js',
  'icons/icon-192.svg',
  'icons/icon-512.svg'
];

function sameOriginUrl(request) {
  return new URL(request.url).origin === self.location.origin;
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
      Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') {
    return;
  }

  const requestUrl = new URL(event.request.url);

  if (!sameOriginUrl(event.request)) {
    return;
  }

  if (event.request.mode === 'navigate' || event.request.headers.get('accept')?.includes('text/html')) {
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
          return cachedResponse || caches.match('/index.php');
        })
    );
    return;
  }

  if (requestUrl.pathname.startsWith('/icons/') || requestUrl.pathname === '/manifest.json' || requestUrl.pathname === '/pwa-register.js') {
    event.respondWith(cacheStaticAsset(event.request));
    return;
  }

  event.respondWith(fetch(event.request));
});
