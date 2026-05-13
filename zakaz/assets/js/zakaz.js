/* =========================================
   NL Партнёр - Мобильный JS (PWA)
   ========================================= */

// Глобальные переменные
window.currentOrderId = null;
window.isOnline = navigator.onLine;

// Обновление статуса онлайн/офлайн
window.addEventListener('online', () => {
  window.isOnline = true;
  showToast('🌐 Интернет восстановлен', 'success');
  if ('serviceWorker' in navigator && 'sync' in ServiceWorkerRegistration.prototype) {
    navigator.serviceWorker.ready.then(reg => reg.sync.register('sync-measurements'));
  }
});

window.addEventListener('offline', () => {
  window.isOnline = false;
  showToast('⚠️ Нет подключения. Работа в офлайн-режиме.', 'warning');
});

// ===== Модальное окно замеров =====
window.openMeasurementModal = function(orderId) {
  window.currentOrderId = orderId;
  const modal = document.getElementById('measurementModal');
  const container = document.getElementById('roomsContainer');
  
  if (!modal || !container) return;
  container.innerHTML = '';
  addRoom();
  modal.classList.add('show');
  document.body.style.overflow = 'hidden';
};

window.closeMeasurementModal = function() {
  const modal = document.getElementById('measurementModal');
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = '';
  }
};

document.addEventListener('click', e => {
  const modal = document.getElementById('measurementModal');
  if (modal && e.target === modal) closeMeasurementModal();
});

// ===== Управление комнатами и окнами =====
window.addRoom = function() {
  const container = document.getElementById('roomsContainer');
  const roomId = 'room-' + Date.now();
  const roomDiv = document.createElement('div');
  roomDiv.className = 'room-block';
  roomDiv.dataset.roomId = roomId;
  roomDiv.innerHTML = `
    <div class="room-header">
      <input type="text" class="room-name-input" placeholder="Название комнаты">
      <button type="button" class="btn-add-win" onclick="addWindow('${roomId}')">➕ Окно</button>
      <button type="button" class="btn-remove-room" onclick="removeRoom('${roomId}')">🗑️</button>
    </div>
    <div class="windows-list" id="wins-${roomId}"></div>
  `;
  container.appendChild(roomDiv);
  addWindow(roomId);
};

window.addWindow = function(roomId) {
  const container = document.getElementById(`wins-${roomId}`);
  if (!container) return;
  const tpl = document.getElementById('windowTemplate');
  if (!tpl) return;
  const winCard = tpl.content.cloneNode(true).querySelector('.window-card');
  const idx = container.children.length + 1;
  winCard.querySelector('.win-idx').textContent = idx;
  container.appendChild(winCard);
};

window.removeRoom = function(roomId) {
  const room = document.querySelector(`.room-block[data-room-id="${roomId}"]`);
  if (room && confirm('Удалить комнату со всеми окнами?')) room.remove();
};

window.removeWindow = function(btn) {
  const card = btn.closest('.window-card');
  const list = card.parentElement;
  if (list.children.length > 1) {
    card.remove();
    Array.from(list.children).forEach((c, i) => {
      c.querySelector('.win-idx').textContent = i + 1;
    });
  } else {
    showToast('⚠️ Должно быть минимум одно окно', 'warning');
  }
};

// ===== Обработка типа карниза =====
window.handleCorniceType = function(select) {
  const card = select.closest('.window-card');
  const type = select.value;
  card.querySelectorAll('.conditional-field').forEach(el => el.style.display = 'none');
  
  if (type === 'sliding') {
    card.querySelector('.field-tulle')?.style.setProperty('display', 'block');
    card.querySelector('.field-sliding')?.style.setProperty('display', 'block');
  } else if (type === 'roman' || type === 'roller') {
    card.querySelector('.field-height')?.style.setProperty('display', 'block');
  }
};

window.handleSlidingDir = function(select) {
  const card = select.closest('.window-card');
  const isCenter = select.value === 'center';
  card.querySelector('.field-opening')?.style.setProperty('display', isCenter ? 'block' : 'none');
};

// ===== Сохранение замеров =====
window.submitMeasurements = async function(btn) {
  if (!btn) btn = document.querySelector('#measurementModal .btn-primary');
  if (!btn) return;
  
  const rooms = [];
  let isValid = true;
  
  document.querySelectorAll('.room-block').forEach(roomEl => {
    const roomName = roomEl.querySelector('.room-name-input')?.value.trim() || 'Комната';
    const windows = [];
    
    roomEl.querySelectorAll('.window-card').forEach(winEl => {
      const getVal = (name) => winEl.querySelector(`[name="${name}"]`)?.value?.trim() || null;
      const corniceType = winEl.querySelector('.cornice-type')?.value;
      const windowWidth = parseInt(getVal('window_width'));
      
      if (!corniceType || !windowWidth || windowWidth <= 0) {
        isValid = false;
        showToast('⚠️ Заполните тип карниза и ширину проёма', 'error');
        return;
      }
      
      windows.push({
        cornice_type: corniceType,
        mounting_type: getVal('mounting_type'),
        window_width: windowWidth,
        height: getVal('height') ? parseInt(getVal('height')) : null,
        wall_left: getVal('wall_left') ? parseInt(getVal('wall_left')) : null,
        wall_right: getVal('wall_right') ? parseInt(getVal('wall_right')) : null,
        offset_left: getVal('offset_left') ? parseInt(getVal('offset_left')) : null,
        offset_right: getVal('offset_right') ? parseInt(getVal('offset_right')) : null,
        offset_wall: getVal('offset_wall') ? parseInt(getVal('offset_wall')) : null,
        drive_side: getVal('drive_side'),
        has_tulle: getVal('has_tulle'),
        sliding_direction: getVal('sliding_direction'),
        opening_type: getVal('opening_type')
      });
    });
    
    if (windows.length > 0) rooms.push({ name: roomName, windows });
  });
  
  if (!isValid || rooms.length === 0) {
    showToast('⚠️ Проверьте заполнение полей', 'error');
    return;
  }
  
  const originalText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '⏳ Сохранение...';
  
  // ✅ ПРАВИЛЬНО: ключ "data:"
  const payload = {
    action: 'save_measurements',
    order_id: window.currentOrderId,
    data: { rooms }
  };
  
  try {
    if (window.isOnline) {
      const res = await fetch('/zakaz/zakaz.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const result = await res.json();
      
      if (result.success) {
        showToast('✅ ' + result.message, 'success');
        closeMeasurementModal();
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast('❌ ' + result.message, 'error');
      }
    } else {
      await saveOfflineMeasurement(payload);
      showToast('📭 Сохранено офлайн. Отправится при появлении сети.', 'success');
      closeMeasurementModal();
    }
  } catch (err) {
    console.error(err);
    showToast('❌ Ошибка сети', 'error');
  } finally {
    if (!btn.disabled) {
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  }
};

// Сохранение в IndexedDB при офлайне
async function saveOfflineMeasurement(data) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('NLZakazDB', 1);
    request.onupgradeneeded = function(e) {
      const db = e.target.result;
      if (!db.objectStoreNames.contains('pending_measurements')) {
        db.createObjectStore('pending_measurements', { keyPath: 'id', autoIncrement: true });
      }
    };
    request.onsuccess = function(e) {
      const db = e.target.result;
      const tx = db.transaction('pending_measurements', 'readwrite');
      tx.objectStore('pending_measurements').add({ ...data, timestamp: new Date().toISOString() });
      tx.oncomplete = resolve;
      tx.onerror = reject;
    };
    request.onerror = reject;
  });
}

// ===== Утилиты =====
function showToast(message, type = 'info') {
  const container = document.getElementById('toastContainer');
  if (!container) return;
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// ===== Инициализация =====
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input, select, textarea').forEach(el => {
    el.addEventListener('focus', () => {
      if (window.innerWidth < 768) document.body.style.zoom = '1.0';
    });
  });
});