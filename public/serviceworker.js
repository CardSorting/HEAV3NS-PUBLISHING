const CACHE_NAME = 'pwa-v3';
const filesToCache = [];

// Install
self.addEventListener('install', event => {
    self.skipWaiting();

    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(filesToCache))
    );
});

// Activate
self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const cacheNames = await caches.keys();

        await Promise.all(
            cacheNames
                .filter(name => name.startsWith('pwa-') && name !== CACHE_NAME)
                .map(name => caches.delete(name))
        );

        await self.clients.claim();
    })());
});

// Fetch
//
// Intentionally not implemented.
//
// This worker caches nothing (`filesToCache` is empty and nothing ever calls
// `cache.put`), so a fetch handler could only ever miss the cache and hand the
// request straight back to the network. It bought us nothing, and re-issuing an
// intercepted request via `fetch(event.request)` corrupts multipart/form-data
// uploads on iOS/WebKit: the request can reach the server with `Content-Length: 0`
// and no file body. That broke attachment, avatar and ID-verification uploads for
// iPhone users whose worker had already claimed the page.
//
// Omitting the handler entirely also lets the browser skip booting this worker for
// navigations. If offline support is ever added, scope the handler to GET only --
// never let a service worker relay a request that carries a body.

// Push
self.addEventListener('push', event => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = {
            title: 'Notification',
            body: event.data ? event.data.text() : '',
        };
    }

    const title = data.title || 'New notification';

    const options = {
        body: data.body || '',
        icon: data.icon || '/assets/pwa/icons/icon-192x192.png',
        badge: data.badge || '/assets/pwa/icons/badge-72x72.png',
        image: data.image || undefined,
        tag: data.tag || undefined,
        renotify: !!data.renotify,
        requireInteraction: !!data.requireInteraction,
        data: {
            url: data.url || '/',
        },
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// Notification click
self.addEventListener('notificationclick', event => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/';

    event.waitUntil((async () => {
        const windowClients = await clients.matchAll({
            type: 'window',
            includeUncontrolled: true,
        });

        for (const client of windowClients) {
            try {
                const clientUrl = new URL(client.url);

                if (clientUrl.origin === self.location.origin) {
                    await client.navigate(targetUrl);
                    await client.focus();
                    return;
                }
            } catch (e) {}
        }

        return clients.openWindow(targetUrl);
    })());
});
