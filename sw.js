const CACHE = 'pb-static-v3';

self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(Promise.resolve());
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (key) {
                return key !== CACHE;
            }).map(function (key) {
                return caches.delete(key);
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') return;
    var url = new URL(event.request.url);
    if (url.origin !== self.location.origin) return;
    if (!/\.(css|js|jpeg|jpg|png|webp|svg|woff2)$/i.test(url.pathname)) return;

    event.respondWith(
        caches.open(CACHE).then(function (cache) {
            return cache.match(event.request).then(function (cached) {
                var network = fetch(event.request).then(function (response) {
                    if (response && response.ok) cache.put(event.request, response.clone());
                    return response;
                }).catch(function () {
                    return cached;
                });
                return cached || network;
            });
        })
    );
});
