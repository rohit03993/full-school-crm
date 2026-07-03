const CACHE_NAME = 'school-crm-pwa-v1';

const PRECACHE_URLS = [
    '/',
    '/favicon.svg',
    '/pwa/manifest/public',
    '/pwa/manifest/portal',
    '/pwa/manifest/admin',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cached) => {
            if (cached) {
                return cached;
            }

            return fetch(event.request).then((response) => {
                if (! response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }

                if (
                    url.pathname.startsWith('/build/')
                    || url.pathname.startsWith('/pwa/icon/')
                ) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
                }

                return response;
            });
        }),
    );
});
