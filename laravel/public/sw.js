// Service Worker for Judo Toernooi PWA
// BELANGRIJK: Verhoog VERSION bij elke release om update te forceren
// v1.1.7 - 2026-01-21: Fix input text color
const VERSION = '1.1.7';
const CACHE_NAME = `judo-toernooi-v${VERSION}`;
const OFFLINE_URL = '/offline.html';

// Assets to cache immediately on install
const PRECACHE_ASSETS = [
    '/',
    '/offline.html',
    '/manifest.json',
    '/icon-192x192.png',
    '/icon-512x512.png',
];

// Install event - cache core assets and skip waiting
self.addEventListener('install', (event) => {
    console.log(`[SW] Installing version ${VERSION}`);
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[SW] Caching core assets');
            return cache.addAll(PRECACHE_ASSETS);
        })
    );
    // Force activation - don't wait for tabs to close
    self.skipWaiting();
});

// Activate event - clean up old caches and claim clients
self.addEventListener('activate', (event) => {
    console.log(`[SW] Activating version ${VERSION}`);
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            // Delete ALL old caches, not just judo-toernooi ones
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME)
                    .map((name) => {
                        console.log(`[SW] Deleting cache: ${name}`);
                        return caches.delete(name);
                    })
            );
        }).then(() => {
            // Take control of all clients immediately
            return self.clients.claim();
        }).then(() => {
            // Notify all clients about the update and force reload
            return self.clients.matchAll().then(clients => {
                clients.forEach(client => {
                    client.postMessage({
                        type: 'SW_UPDATED',
                        version: VERSION,
                        forceReload: true
                    });
                });
            });
        })
    );
});

// Message handler for manual update check
self.addEventListener('message', (event) => {
    if (event.data === 'CHECK_UPDATE') {
        event.ports[0].postMessage({ version: VERSION });
    }
    if (event.data === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// Fetch event - network first for HTML, cache first for assets
self.addEventListener('fetch', (event) => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // Skip chrome-extension and other non-http(s) requests
    if (!event.request.url.startsWith('http')) {
        return;
    }

    const url = new URL(event.request.url);

    // For API/data requests - network only (don't cache dynamic data)
    if (url.pathname.startsWith('/api/') ||
        url.pathname.includes('/weging/') ||
        url.pathname.includes('/mat/') ||
        url.pathname.includes('/dojo/') ||
        url.pathname.includes('/spreker/') ||
        event.request.headers.get('Accept')?.includes('application/json')) {
        event.respondWith(
            fetch(event.request).catch(() => {
                return new Response(JSON.stringify({ error: 'Offline' }), {
                    status: 503,
                    headers: { 'Content-Type': 'application/json' }
                });
            })
        );
        return;
    }

    // For navigation requests - ALWAYS network first to get latest version
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    if (response.ok) {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(event.request, responseClone);
                        });
                    }
                    return response;
                })
                .catch(() => {
                    return caches.match(event.request).then((cached) => {
                        return cached || caches.match(OFFLINE_URL);
                    });
                })
        );
        return;
    }

    // For other assets - network first, cache fallback
    event.respondWith(
        fetch(event.request)
            .then((response) => {
                if (response.ok) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return response;
            })
            .catch(() => {
                return caches.match(event.request);
            })
    );
});
