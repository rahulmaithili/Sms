/**
 * Developed by Rameez Scripts
 * Service Worker — cache static assets, network-first for API
 */
var CACHE_NAME = 'sub-mgmt-v2';
var STATIC_ASSETS = [
    './styles.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11'
];

// install — cache static assets
self.addEventListener('install', function(e) {
    e.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

// activate — clean old caches
self.addEventListener('activate', function(e) {
    e.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.filter(function(k) { return k !== CACHE_NAME; })
                    .map(function(k) { return caches.delete(k); })
            );
        })
    );
    self.clients.claim();
});

// fetch — network first, fallback to cache
self.addEventListener('fetch', function(e) {
    // only handle http/https GET requests
    if (e.request.method !== 'GET') return;
    if (!e.request.url.startsWith('http')) return;

    // skip AJAX/API calls
    if (e.request.url.indexOf('action=') !== -1) return;

    e.respondWith(
        fetch(e.request).then(function(res) {
            if (res.ok && e.request.url.match(/^https?:\/\//)) {
                var clone = res.clone();
                caches.open(CACHE_NAME).then(function(cache) {
                    cache.put(e.request, clone);
                }).catch(function() {});
            }
            return res;
        }).catch(function() {
            return caches.match(e.request);
        })
    );
});
