// Service worker MJG WMS.
// - Halaman (navigasi): selalu ambil dari server biar data stok/SO/PO gak basi. Kalau offline -> offline.html.
// - Aset statis (logo, ikon, font, Tailwind/Lucide CDN): cache, diperbarui di belakang layar.
// - POST / download cetak / export tidak disentuh sama sekali.
const CACHE = 'mjg-wms-v1';
const PRECACHE = ['/offline.html', '/images/logo-mjg.png', '/images/icon-mjg.png', '/images/pwa-192.png'];
const STATIC_HOSTS = ['cdn.tailwindcss.com', 'cdn.jsdelivr.net', 'fonts.googleapis.com', 'fonts.gstatic.com'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
        return;
    }

    const url = new URL(req.url);
    const isStatic = STATIC_HOSTS.includes(url.hostname)
        || (url.origin === self.location.origin && url.pathname.startsWith('/images/'));
    if (!isStatic) return;

    event.respondWith(
        caches.open(CACHE).then((cache) => cache.match(req).then((cached) => {
            const network = fetch(req).then((res) => {
                if (res.ok || res.type === 'opaque') cache.put(req, res.clone());
                return res;
            }).catch(() => cached);
            return cached || network;
        }))
    );
});
