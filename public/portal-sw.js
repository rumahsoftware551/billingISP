const CACHE='jaringanku-portal-v1';
const SHELL=['/manifest.webmanifest','/icons/jaringanku-192.svg'];
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(c=>c.addAll(SHELL)).then(()=>self.skipWaiting()));});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));});
self.addEventListener('fetch',event=>{if(event.request.method!=='GET')return;const url=new URL(event.request.url);if(url.origin!==self.location.origin)return;if(url.pathname.startsWith('/portal/')){event.respondWith(fetch(event.request).catch(()=>new Response('Portal Jaringanku sedang offline. Coba lagi saat koneksi tersedia.',{status:503,headers:{'Content-Type':'text/plain; charset=utf-8','Cache-Control':'no-store'}})));return;}if(SHELL.includes(url.pathname)){event.respondWith(caches.match(event.request).then(r=>r||fetch(event.request)));}});
