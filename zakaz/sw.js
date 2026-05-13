const CACHE_NAME = 'nl-zakaz-v1';
const STATIC_CACHE = 'static-v1';
const DYNAMIC_CACHE = 'dynamic-v1';

// Статические файлы для офлайн-режима
const STATIC_ASSETS = [
  '/zakaz/zakaz.php',
  '/zakaz/assets/css/zakaz.css',
  '/zakaz/assets/js/zakaz.js',
  '/zakaz/assets/img/icon-192.png',
  '/zakaz/assets/img/icon-512.png',
  '/zakaz/templates/modals/measurement_modal.php'
];

// Установка: кэшируем статику
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => {
        console.log('📦 Кэширование статики...');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => self.skipWaiting())
  );
});

// Активация: чистим старый кэш
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.filter(key => key !== STATIC_CACHE && key !== DYNAMIC_CACHE)
            .map(key => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

// Перехват запросов: стратегия "Сеть → Кэш → Офлайн"
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // API-запросы: только сеть + сохранение в кэш при успехе
  if (url.pathname.includes('zakaz.php') && request.method === 'POST') {
    event.respondWith(
      fetch(request)
        .then(response => {
          if (response.ok) {
            const clone = response.clone();
            caches.open(DYNAMIC_CACHE).then(cache => cache.put(request, clone));
          }
          return response;
        })
        .catch(() => {
          // Офлайн: сохраняем запрос в IndexedDB для последующей синхронизации
          return saveOfflineRequest(request);
        })
    );
    return;
  }

  // Статика: Кэш → Сеть
  if (STATIC_ASSETS.some(path => url.pathname.includes(path.replace('/zakaz/', '')))) {
    event.respondWith(
      caches.match(request)
        .then(cached => cached || fetch(request))
    );
    return;
  }

  // Остальное: Сеть → Кэш
  event.respondWith(
    fetch(request)
      .then(response => {
        if (response.ok && response.type === 'basic') {
          const clone = response.clone();
          caches.open(DYNAMIC_CACHE).then(cache => cache.put(request, clone));
        }
        return response;
      })
      .catch(() => caches.match(request))
  );
});

// 🔁 Фоновая синхронизация (когда появился интернет)
self.addEventListener('sync', event => {
  if (event.tag === 'sync-measurements') {
    event.waitUntil(syncPendingRequests());
  }
});

// Сохранение запроса в IndexedDB при офлайне
async function saveOfflineRequest(request) {
  const db = await openDB();
  const tx = db.transaction('pending_requests', 'readwrite');
  
  const body = await request.clone().text();
  await tx.objectStore('pending_requests').add({
    id: Date.now() + '-' + Math.random().toString(36).substr(2, 9),
    url: request.url,
    method: request.method,
    body: body,
    timestamp: new Date().toISOString()
  });
  
  // Показать уведомление пользователю
  self.clients.matchAll().then(clients => {
    clients.forEach(client => {
      client.postMessage({ 
        type: 'OFFLINE_SAVED', 
        message: '📭 Данные сохранены. Отправятся при появлении интернета.' 
      });
    });
  });
  
  // Вернуть "успех" фронтенду, чтобы не было ошибки
  return new Response(JSON.stringify({ success: true, offline: true }), {
    headers: { 'Content-Type': 'application/json' }
  });
}

// Отправка сохранённых запросов при появлении сети
async function syncPendingRequests() {
  const db = await openDB();
  const tx = db.transaction('pending_requests', 'readwrite');
  const store = tx.objectStore('pending_requests');
  const requests = await store.getAll();
  
  for (const req of requests) {
    try {
      await fetch(req.url, {
        method: req.method,
        headers: { 'Content-Type': 'application/json' },
        body: req.body
      });
      await store.delete(req.id);
      console.log('✅ Синхронизировано:', req.id);
    } catch (err) {
      console.error('❌ Ошибка синхронизации:', err);
    }
  }
}

// Простая обертка для IndexedDB
function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('NLZakazDB', 1);
    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
    request.onupgradeneeded = event => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains('pending_requests')) {
        db.createObjectStore('pending_requests', { keyPath: 'id' });
      }
    };
  });
}

// Сообщения от страницы к SW
self.addEventListener('message', event => {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});