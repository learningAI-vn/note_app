/**
 * CraftNotes Service Worker (Criterion #31: Offline Capabilities)
 * Caches application shell assets using relative paths so the app remains accessible offline.
 */

const CACHE_NAME = 'craftnotes-shell-v1';
const SHELL_ASSETS = [
    'index.php',
    'login.php',
    'register.php',
    'profile.php',
    'manifest.json',
    'assets/css/style.css',
    'assets/js/app.js',
    'assets/js/security_share.js',
    'assets/js/offline_db.js',
    'assets/img/default-avatar.svg'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_ASSETS)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') {
        return;
    }

    // Network-first with cache fallback for HTML pages & static assets
    event.respondWith(
        fetch(req)
            .then((networkRes) => {
                if (networkRes && networkRes.status === 200 && !req.url.includes('api/')) {
                    const clone = networkRes.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(req, clone));
                }
                return networkRes;
            })
            .catch(() => caches.match(req).then((cached) => cached || caches.match('index.php')))
    );
});
