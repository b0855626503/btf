const WEB = 'DEMO';
const CACHE_VERSION = 'v1.1.0';
const STATIC_CACHE = `pwa-static-${WEB}-${CACHE_VERSION}`;
const OFFLINE_URL = '/offline';

const filesToCache = [
    OFFLINE_URL,
    'assets/wm356/js/minified_safe_optimized_no_jquery_bundle.js',
    'assets/wm356/js/js_wallet.js',
    'assets/wm356/css/addon.css',
    'assets/wm356/css/style_wallet.css',
    'assets/ui/js/ui.js',
    'assets/admin/css/web.css',
    'assets/ui/css/ui.css',
    'vendor/toasty/dist/toasty.min.css',
    'vendor/daterangepicker/daterangepicker.css',
    'vendor/daterangepicker/daterangepicker.js',
    'vendor/datatables/buttons.server-side.js',
    'storage/sound/alert.mp3',
];

self.addEventListener('install', (event) => {
    console.log('[SW] Installing...');
    self.skipWaiting();
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);
        const results = await Promise.allSettled(
            filesToCache.map(async (url) => {
                const res = await fetch(url, { credentials: 'same-origin' });
                if (!res.ok) throw new Error(`${url} failed with status ${res.status}`);
                await cache.put(url, res.clone());
            })
        );
        results.forEach(r => r.status === 'rejected' && console.warn('[SW] ❌ Failed to cache:', r.reason));
        console.log('[SW] Cached all static files');
    })());
});

// ===== ACTIVATE =====
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating new version...');
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(
            keys
                .filter(k => k.startsWith('pwa-static-') && k !== STATIC_CACHE)
                .map(k => (console.log('[SW] 🔥 Deleting old cache:', k), caches.delete(k)))
        );
        await self.clients.claim();
    })());
});

// ===== FETCH =====
self.addEventListener('fetch', (event) => {
    const req = event.request;

    // รับเฉพาะ GET + http(s)
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (!url.protocol.startsWith('http')) return;

    const sameOrigin = url.origin === self.location.origin;
    const dest = req.destination; // 'style' | 'script' | 'image' | 'font' | 'document' | ...

    // 🚫 ข้าม cross-origin ทั้งหมด (เช่น cdnjs, fonts, ฯลฯ) ให้ browser จัดการเอง
    if (!sameOrigin) {
        return; // ไม่ intercept → ไม่เกิด opaque mismatch / ERR_FAILED บน CDN
    }

    // 📦 cache-first สำหรับ static ในโดเมนตัวเอง
    if (dest === 'style' || dest === 'script' || dest === 'image' || dest === 'font') {
        event.respondWith((async () => {
            const cached = await caches.match(req);
            if (cached) return cached;

            try {
                const res = await fetch(req);
                if (res.ok && (res.type === 'basic' || res.type === 'cors')) {
                    const cache = await caches.open(STATIC_CACHE);
                    cache.put(req, res.clone());
                }
                return res;
            } catch (e) {
                console.warn('[SW] ❌ Static asset fetch failed:', req.url, e);
                // fallback เฉพาะหน้า offline
                return caches.match(OFFLINE_URL);
            }
        })());
        return;
    }

    // 🌐 network-first สำหรับหน้า (document) และคำขออื่น ๆ ในโดเมน
    event.respondWith(
        fetch(req).catch(() => caches.match(OFFLINE_URL))
    );
});