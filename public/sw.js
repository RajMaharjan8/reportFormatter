/* Report Generator service worker.
 * - Static assets (build output, images, icons, fonts): stale-while-revalidate.
 * - Page navigations: network-first with an offline fallback.
 * Dynamic/auth HTML is never cached, so logged-in pages stay fresh.
 */
const CACHE = 'reportgen-v1';
const PRECACHE = [
    '/offline.html',
    '/favicon.svg',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

const STATIC_RE = /\/(build|images|icons)\//;
const STATIC_EXT = /\.(?:css|js|png|jpg|jpeg|gif|svg|ico|webp|woff2?|ttf)$/;

self.addEventListener('fetch', (event) => {
    const req = event.request;

    if (req.method !== 'GET') {
        return;
    }

    const url = new URL(req.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Static assets — serve cached, refresh in the background.
    if (STATIC_RE.test(url.pathname) || STATIC_EXT.test(url.pathname)) {
        event.respondWith(
            caches.open(CACHE).then(async (cache) => {
                const cached = await cache.match(req);
                const network = fetch(req)
                    .then((res) => {
                        if (res && res.ok) {
                            cache.put(req, res.clone());
                        }
                        return res;
                    })
                    .catch(() => cached);
                return cached || network;
            })
        );
        return;
    }

    // Page navigations — try the network, fall back to the offline page.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    }
});
