// Service Worker - PWA için offline support ve cache yönetimi
const CACHE_NAME = 'kursad-portfolio-v1';
const RUNTIME_CACHE = 'kursad-runtime-v1';

// Base path - dinamik olarak belirlenecek
const getBasePath = () => {
  const path = self.location.pathname;
  // sw.js dosyasının konumunu bul
  const swIndex = path.lastIndexOf('sw.js');
  if (swIndex === -1) {
    // Eğer sw.js bulunamazsa, path'in son / karakterinden öncesini al
    const lastSlash = path.lastIndexOf('/');
    return lastSlash > 0 ? path.substring(0, lastSlash + 1) : '/';
  }
  // sw.js'den önceki kısmı al
  let basePath = path.substring(0, swIndex);
  // Sonunda / olmalı
  if (!basePath.endsWith('/')) {
    basePath += '/';
  }
  // Eğer boşsa veya sadece / ise, /kursad/ gibi alt dizin olabilir
  return basePath || '/';
};

const BASE = getBasePath();
console.log('[Service Worker] Base path:', BASE);

// Cache'e eklenecek statik dosyalar
const STATIC_CACHE_URLS = [
  BASE,
  BASE + 'index.php',
  BASE + 'css/main_v%3D1.css',
  BASE + 'css/colorbox.css',
  BASE + 'js/jquery-1.8.3.min.js',
  BASE + 'js/jquery.colorbox.js',
  BASE + 'js/main.js',
  BASE + 'js/jquery.griddle.js',
  BASE + 'js/modernizr-2.6.2-respond-1.1.0.min.js',
  BASE + 'favicon.ico',
  BASE + 'manifest.json'
];

// Install event - İlk yüklemede cache oluştur
self.addEventListener('install', (event) => {
  console.log('[Service Worker] Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[Service Worker] Caching static files');
        // Her URL'i ayrı ayrı ekle, hata olanları atla
        return Promise.allSettled(
          STATIC_CACHE_URLS.map(url => {
            try {
              const fullUrl = url.startsWith('http') ? url : self.location.origin + (url.startsWith('/') ? url : BASE + url);
              return cache.add(new Request(fullUrl, { cache: 'reload' })).catch(err => {
                console.warn('[Service Worker] Failed to cache:', fullUrl, err);
                return null;
              });
            } catch (err) {
              console.warn('[Service Worker] Invalid URL:', url, err);
              return null;
            }
          })
        );
      })
      .then(() => {
        console.log('[Service Worker] Installation complete');
        return self.skipWaiting();
      })
      .catch((error) => {
        console.error('[Service Worker] Cache install failed:', error);
      })
  );
});

// Activate event - Eski cache'leri temizle
self.addEventListener('activate', (event) => {
  console.log('[Service Worker] Activating...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME && cacheName !== RUNTIME_CACHE) {
            console.log('[Service Worker] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event - Network first, fallback to cache
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Sadece GET isteklerini handle et
  if (request.method !== 'GET') {
    return;
  }

  // Geçersiz scheme'leri filtrele (chrome-extension, data:, blob:, vb.)
  if (!url.protocol.startsWith('http')) {
    return;
  }

  // Admin paneli ve API isteklerini cache'leme
  if (url.pathname.includes('/admin/') || url.pathname.includes('api')) {
    return;
  }
  
  // Service worker ve manifest dosyalarını cache'leme
  if (url.pathname.includes('/sw.js') || url.pathname.includes('/manifest.json')) {
    return;
  }

  // PHP sayfaları için network-first strategy
  if (url.pathname.endsWith('.php') || url.pathname === '/' || url.pathname === '' || url.pathname.endsWith(BASE) || url.pathname === BASE.slice(0, -1)) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // Başarılı response'u cache'e ekle
          if (response && response.status === 200 && response.type === 'basic') {
            try {
              const responseToCache = response.clone();
              caches.open(RUNTIME_CACHE).then((cache) => {
                cache.put(request, responseToCache).catch(err => {
                  console.warn('[Service Worker] Failed to cache response:', err);
                });
              });
            } catch (err) {
              console.warn('[Service Worker] Error cloning response:', err);
            }
          }
          return response;
        })
        .catch(() => {
          // Network hatası durumunda cache'den döndür
          return caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
              return cachedResponse;
            }
            // Cache'de de yoksa offline sayfası göster
            return caches.match(BASE + 'index.php') || new Response('Offline', { status: 503 });
          });
        })
    );
    return;
  }

  // Statik dosyalar için cache-first strategy
  event.respondWith(
    caches.match(request)
      .then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }
        // Cache'de yoksa network'ten al ve cache'e ekle
        return fetch(request)
          .then((response) => {
            if (response && response.status === 200 && response.type === 'basic') {
              try {
                const responseToCache = response.clone();
                caches.open(RUNTIME_CACHE).then((cache) => {
                  cache.put(request, responseToCache).catch(err => {
                    console.warn('[Service Worker] Failed to cache:', request.url, err);
                  });
                });
              } catch (err) {
                console.warn('[Service Worker] Error caching:', request.url, err);
              }
            }
            return response;
          })
          .catch(() => {
            // Network hatası ve cache'de yoksa
            if (request.destination === 'image') {
              return new Response('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="#ccc"/><text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#999">Image not available offline</text></svg>', {
                headers: { 'Content-Type': 'image/svg+xml' }
              });
            }
            // Ana sayfayı dene
            return caches.match(BASE + 'index.php') || new Response('Offline', { status: 503 });
          });
      })
  );
});

// Background sync için (gelecekte kullanılabilir)
self.addEventListener('sync', (event) => {
  console.log('[Service Worker] Background sync:', event.tag);
});

// Push notification için (gelecekte kullanılabilir)
self.addEventListener('push', (event) => {
  console.log('[Service Worker] Push notification received');
  const options = {
    body: event.data ? event.data.text() : 'Yeni içerik mevcut',
    icon: '/icons/icon-192x192.png',
    badge: '/icons/icon-72x72.png',
    vibrate: [200, 100, 200],
    tag: 'portfolio-update'
  };
  event.waitUntil(
    self.registration.showNotification('Kürşad Karakuş Portfolio', options)
  );
});

// Notification click event
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.openWindow('/')
  );
});
