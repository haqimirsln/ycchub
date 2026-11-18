// YCC Hub service worker. Keeps the app shell (styles, fonts, icons) on the
// phone so it opens fast, and shows a friendly page when there's no signal.
// Pages and data always come fresh from the server: seats and attendance must be live.
const VERSION = {!! json_encode($version) !!};
const CACHE = 'ycc-hub-' + VERSION;
const BASE = {!! json_encode($base, JSON_UNESCAPED_SLASHES) !!};
const OFFLINE_URL = {!! json_encode($offlineUrl, JSON_UNESCAPED_SLASHES) !!};
const PRECACHE = {!! json_encode($precache, JSON_UNESCAPED_SLASHES) !!};
const STATIC_PATHS = [BASE + 'build/', BASE + 'images/'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll(PRECACHE.map((url) => new Request(url, { cache: 'reload' }))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('ycc-hub-') && k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET' || !request.url.startsWith(BASE)) {
        return;
    }

    // Pages: always the network; the offline page only when there is no connection.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    // Built CSS/JS, fonts and images have versioned names, so a cached copy is always right.
    if (STATIC_PATHS.some((path) => request.url.startsWith(path))) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }
                return response;
            }))
        );
    }
});

// ---- Phone notifications ----------------------------------------------------
// The server sends { title, body, url, tag, icon }; tapping opens that page in the app.
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: event.data ? event.data.text() : '' };
    }
    const title = data.title || 'YCC Hub';
    event.waitUntil(self.registration.showNotification(title, {
        body: data.body || '',
        icon: data.icon || (BASE + 'images/app/icon-192.png'),
        badge: BASE + 'images/app/icon-192.png',
        tag: data.tag || undefined,
        renotify: Boolean(data.tag),
        data: { url: data.url || BASE },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || BASE;
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((wins) => {
            for (const win of wins) {
                if (win.url.startsWith(BASE) && 'focus' in win) {
                    return win.focus().then((w) => (w && 'navigate' in w ? w.navigate(url) : w));
                }
            }
            return self.clients.openWindow(url);
        })
    );
});
