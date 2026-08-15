const CACHE_NAME = 'school-crm-pwa-v4';
const OFFLINE_URL = '/offline.html';

// Institute icons are deliberately absent: they are served from /pwa/icon/{size}
// with a ?v= branding token, so precaching the bare path would pin a stale logo.
const PRECACHE_URLS = [
    OFFLINE_URL,
    '/favicon.svg',
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

self.addEventListener('push', (event) => {
    let data = {
        title: 'School CRM',
        body: 'You have an update.',
        url: '/app',
        tag: 'crm',
    };

    try {
        if (event.data) {
            data = { ...data, ...event.data.json() };
        }
    } catch (error) {
        // Keep defaults.
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'School CRM', {
            body: data.body || '',
            icon: '/pwa/icon/192',
            badge: '/pwa/icon/192',
            tag: data.tag || 'crm',
            data: { url: data.url || '/app' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = (event.notification.data && event.notification.data.url) || '/app';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if ('focus' in client && client.url.includes(self.location.origin)) {
                    client.navigate(target);

                    return client.focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(target);
            }

            return undefined;
        }),
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

    // Only versioned/immutable assets are cache-first. The manifest stays on the
    // network so a renamed institute or new icon is picked up on next launch.
    if (
        url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/pwa/icon/')
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
