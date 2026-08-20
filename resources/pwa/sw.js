const CACHE_VERSION = 'directory-pwa-__VERSION__';
const OFFLINE_CACHE = `${CACHE_VERSION}-offline`;
const ASSET_CACHE = `${CACHE_VERSION}-assets`;
const METADATA_CACHE = `${CACHE_VERSION}-metadata`;
const OFFLINE_URL = '/offline.html';

const SENSITIVE_PREFIXES = [
    '/escort/',
    '/media/',
    '/branding/',
    '/conversion/',
    '/age-gate/',
    '/login',
    '/register',
    '/dashboard',
    '/admin',
    '/staff',
    '/seo',
    '/profile',
    '/profiles',
    '/my-profiles',
    '/onboarding',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(OFFLINE_CACHE)
            .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith('directory-pwa-') && ! [OFFLINE_CACHE, ASSET_CACHE, METADATA_CACHE].includes(key))
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (SENSITIVE_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
        event.respondWith(fetch(request));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request, ASSET_CACHE));
        return;
    }

    if (url.pathname === '/manifest.webmanifest' || url.pathname.startsWith('/pwa/')) {
        event.respondWith(networkFirst(request, METADATA_CACHE));
    }
});

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;

    const response = await fetch(request);
    if (response.ok && response.type === 'basic') {
        const cache = await caches.open(cacheName);
        await cache.put(request, response.clone());
    }

    return response;
}

async function networkFirst(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok && response.type === 'basic') {
            const cache = await caches.open(cacheName);
            await cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        const cached = await caches.match(request);
        if (cached) return cached;
        throw error;
    }
}
