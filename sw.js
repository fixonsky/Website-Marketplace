const CACHE_NAME = 'rental-kostum-v1.0.0';
const STATIC_CACHE = 'static-v1';
const DYNAMIC_CACHE = 'dynamic-v1';

// Files to cache immediately
const STATIC_FILES = [
  './INDEXXX.php',
  './INDEXXX2.php', 
  './INDEXXX3.php',
  './pesanan.php',
  './profile.css',
  './icon.jpg',
  './Manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css'
];

// Network-first strategy URLs (PHP files that need fresh data)
const NETWORK_FIRST_URLS = [
  '/INDEXXX2.php',
  '/pesanan.php',
  '/proses_',
  '/api/',
  '/get_'
];

// Install event
self.addEventListener('install', event => {
  console.log('Service Worker installing...');
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => {
        console.log('Precaching static files');
        return cache.addAll(STATIC_FILES);
      })
      .catch(err => console.log('Cache install failed:', err))
  );
  self.skipWaiting();
});

// Activate event
self.addEventListener('activate', event => {
  console.log('Service Worker activating...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch event with different strategies
self.addEventListener('fetch', event => {
  const requestUrl = new URL(event.request.url);
  
  // Skip non-GET requests
  if (event.request.method !== 'GET') {
    return;
  }
  
  // Network first for PHP files and API calls
  if (NETWORK_FIRST_URLS.some(url => requestUrl.pathname.includes(url))) {
    event.respondWith(networkFirst(event.request));
  }
  // Cache first for static assets
  else if (requestUrl.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2)$/)) {
    event.respondWith(cacheFirst(event.request));
  }
  // Stale while revalidate for HTML pages
  else {
    event.respondWith(staleWhileRevalidate(event.request));
  }
});

// Network first strategy
async function networkFirst(request) {
  try {
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      const cache = await caches.open(DYNAMIC_CACHE);
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    console.log('Network failed, trying cache:', error);
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    // Return offline page for navigation requests
    if (request.mode === 'navigate') {
      return caches.match('./offline.html');
    }
    throw error;
  }
}

// Cache first strategy
async function cacheFirst(request) {
  const cachedResponse = await caches.match(request);
  if (cachedResponse) {
    return cachedResponse;
  }
  
  try {
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      const cache = await caches.open(STATIC_CACHE);
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    console.log('Cache and network failed:', error);
    throw error;
  }
}

// Stale while revalidate strategy
async function staleWhileRevalidate(request) {
  const cache = await caches.open(DYNAMIC_CACHE);
  const cachedResponse = await cache.match(request);
  
  const fetchPromise = fetch(request).then(networkResponse => {
    if (networkResponse.ok) {
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  }).catch(err => {
    console.log('Network request failed:', err);
    return cachedResponse;
  });
  
  return cachedResponse || fetchPromise;
}

// Background sync for form submissions
self.addEventListener('sync', event => {
  if (event.tag === 'profile-sync') {
    event.waitUntil(syncProfileData());
  }
});

async function syncProfileData() {
  // Handle offline form submissions when back online
  const formData = await getStoredFormData();
  if (formData) {
    try {
      const response = await fetch('./INDEXXX2.php', {
        method: 'POST',
        body: formData
      });
      if (response.ok) {
        await clearStoredFormData();
        console.log('Profile data synced successfully');
      }
    } catch (error) {
      console.log('Sync failed:', error);
    }
  }
}

async function getStoredFormData() {
  // Implementation to get stored form data from IndexedDB
  return null; // Placeholder
}

async function clearStoredFormData() {
  // Implementation to clear stored form data
}