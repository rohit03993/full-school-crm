const CACHE_NAME = 'school-crm-pwa-v2';
const OFFLINE_URL = '/offline.html';

const PRECACHE_URLS = [
    OFFLINE_URL,
    '/favicon.svg',
    '/pwa/icon/192',
    '/pwa/icon/512',
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

    // Never intercept Livewire / auth / API / private file streams
    if (
        url.pathname.startsWith('/livewire')
        || url.pathname.startsWith('/admin/livewire')
        || url.pathname.startsWith('/api/')
        || url.pathname.startsWith('/webhooks/')
        || url.pathname.includes('/download')
    ) {
        return;
    }

    const isNavigation = event.request.mode === 'navigate'
        || (event.request.headers.get('accept') || '').includes('text/html');

    if (isNavigation) {
        event.respondWith(networkFirstNavigation(event.request));

        return;
    }

    if (
        url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/pwa/')
        || url.pathname === '/favicon.svg'
        || url.pathname === '/favicon.ico'
        || url.pathname === OFFLINE_URL
    ) {
        event.respondWith(cacheFirstAsset(event.request));
    }
});

async function networkFirstNavigation(request) {
    try {
        const response = await fetch(request);

        return response;
    } catch (error) {
        const cached = await caches.match(OFFLINE_URL);

        if (cached) {
            return cached;
        }

        return new Response('You are offline.', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        });
    }
}

async function cacheFirstAsset(request) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response && response.status === 200 && response.type === 'basic') {
        const copy = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
    }

    return response;
}
