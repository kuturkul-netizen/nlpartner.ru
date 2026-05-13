/* =========================================
   NEIROLINKS Motion | Личный кабинет
   File: /cabinet/assets/js/cabinet.js
   ========================================= */

let currentRequestType = '';
let phoneInput = null;
let phoneStatus = null;
let phoneHint = null;

// Инициализация элементов после загрузки DOM
document.addEventListener('DOMContentLoaded', function() {
    phoneInput = document.getElementById('phoneInput');
    phoneStatus = document.getElementById('phoneStatus');
    phoneHint = document.getElementById('phoneHint');
    
    if (phoneInput) {
        phoneInput.addEventListener('input', e => { 
            e.target.value = formatPhone(e.target.value); 
            updatePhoneState(); 
        });
        phoneInput.addEventListener('blur', updatePhoneState);
        phoneInput.addEventListener('focus', function() { 
            if(this.classList.contains('is-invalid')) { 
                this.classList.remove('is-invalid'); 
                if(phoneHint) {
                    phoneHint.textContent = '+7 (999) 123-45-67'; 
                    phoneHint.classList.remove('invalid');
                }
            } 
        });
    }
});

// 📱 Маска и валидация телефона
function formatPhone(v) {
    if (!v) return '';
    let d = v.replace(/\D/g, '');
    if (d.startsWith('8')) d = '7' + d.slice(1);
    if (!d.startsWith('7')) d = '7' + d;
    d = d.slice(0, 11);
    let f = '+7';
    if (d.length > 1) f += ' (' + d.slice(1, 4);
    if (d.length >= 5) f += ') ' + d.slice(4, 7);
    if (d.length >= 8) f += '-' + d.slice(7, 9);
    if (d.length >= 10) f += '-' + d.slice(9, 11);
    return f;
}

function isPhoneValid(v) { 
    return v.replace(/\D/g, '').length === 11 && v.replace(/\D/g, '').startsWith('7'); 
}

function updatePhoneState() {
    if (!phoneInput) return;
    const v = phoneInput.value, valid = isPhoneValid(v);
    phoneInput.classList.toggle('is-valid', valid);
    phoneInput.classList.toggle('is-invalid', !valid && v.length >= 10);
    if(phoneStatus) phoneStatus.textContent = valid ? '✓' : (v.length >= 10 ? '✗' : '');
    if(phoneHint) {
        phoneHint.textContent = valid ? 'Корректно ✓' : 'Проверьте формат';
        phoneHint.classList.toggle('valid', valid);
        phoneHint.classList.toggle('invalid', !valid && v.length >= 10);
    }
}

// 🔄 Дропдауны
function toggleDropdown(id) {
    if (window.event) window.event.stopPropagation();
    document.querySelectorAll('.status-dropdown').forEach(d => { 
        if(d.id !== id) d.classList.remove('show'); 
    });
    const dropdown = document.getElementById(id);
    if(dropdown) dropdown.classList.toggle('show');
}

// 🔄 Дропдауны
function selectOption(val, txt, field, oid) {
    if (window.event) window.event.stopPropagation();
    
    // 1. Если выбрано "Требуется" → проверяем заявку и открываем форму адреса
    if ((field === 'measurements' && val === 'required') || (field === 'installation' && val === 'required')) {
        checkExistingRequest(field, oid, txt);
        return;
    }
    
    // 2. Если выбрано "Замер предоставлен" → ПРОВЕРЯЕМ наличие данных
    if (field === 'measurements' && val === 'provided') {
        checkExistingMeasurements(oid, () => {
            if (typeof window.openMeasurementModal === 'function') {
                window.openMeasurementModal();
            } else {
                showNotification('⚠️ Скрипт замеров не загружен', 'error');
            }
        });
        return;
    }
    
    // 3. Остальные статусы → просто обновляем текст
    updateField(val, txt, field, oid);
}

// 🔧 Обновление поля + управление видимостью блоков заявок
function updateField(val, txt, field, oid) {
    const span = document.getElementById(field+'-text');
    if(span) span.textContent = txt;
    const hid = document.getElementById('order_'+field+'_hidden');
    if(hid) hid.value = val;
    const dd = document.getElementById(field+'-dropdown');
    if(dd) { 
        dd.querySelectorAll('.status-option').forEach(o => o.classList.toggle('active', o.dataset.value===val)); 
        dd.classList.remove('show'); 
    }
    
    if (field === 'measurements') {
        toggleBlockContent('measurementRequestsContent', val === 'required');
    }
    if (field === 'installation') {
        toggleBlockContent('installationRequestsContent', val === 'required');
    }
    
    toggleRequestsSection();
}

// 👁️ Показ/скрытие внутреннего контента
function toggleBlockContent(id, show) {
    const el = document.getElementById(id);
    if(el) el.style.display = show ? 'block' : 'none';
}

// 👁️ Управление главным блоком заявок
function toggleRequestsSection() {
    const mBlock = document.getElementById('measurementRequestsContent');
    const iBlock = document.getElementById('installationRequestsContent');
    const mainSection = document.getElementById('requestsSection');
    
    if (mainSection && mBlock && iBlock) {
        const mVisible = mBlock.style.display !== 'none';
        const iVisible = iBlock.style.display !== 'none';
        mainSection.style.display = (mVisible || iVisible) ? 'block' : 'none';
    }
}

// 🔍 ПРОВЕРКА СУЩЕСТВУЮЩЕЙ ЗАЯВКИ
function checkExistingRequest(type, oid, txt) {
    const badge = document.querySelector(`[onclick*="${type}-dropdown"]`);
    const span = document.getElementById(type+'-text');
    const orig = span ? span.textContent : '';
    
    if(badge) badge.classList.add('loading');
    if(span) span.textContent = '⏳ Проверка...';
    
    const fd = new FormData(); 
    fd.append('check_address_request','1'); 
    fd.append('order_id', oid); 
    fd.append('type', type);

    fetch('/cabinet/cabinet.php', { method:'POST', body:fd })
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(d => {
        if(d.success) {
            if(d.exists) {
                updateField('required', txt, type, oid);
                showNotification('ℹ️ Данные уже заполнены. Нажмите "Редактировать" для изменения.', 'success');
            } else {
                currentRequestType = type;
                const f = document.getElementById('addressForm');
                const eid = document.getElementById('edit_request_id'); 
                if(eid) eid.value='';
                if(f) f.reset(); 
                
                const title = document.getElementById('modalTitle');
                if(title) title.textContent = type==='measurements' ? '📐 Заявка на замер' : '🔧 Заявка на монтаж';
                
                if(d.copyFrom) {
                    console.log('📋 Автозаполнение из заявки другого типа:', d.copyFrom);
                    const fields = ['city', 'street', 'house', 'entrance', 'floor', 'apartment', 'contact_person'];
                    fields.forEach(k => {
                        const input = f.querySelector(`[name="${k}"]`);
                        if(input && d.copyFrom[k]) {
                            input.value = d.copyFrom[k];
                        }
                    });
                    showNotification('ℹ️ Поля заполнены данными из другой заявки', 'success');
                }
                
                openModal();
            }
        } else { 
            if(span) span.textContent = orig; 
            showNotification('⚠️ '+d.message,'error'); 
        }
    }).catch(()=>{ 
        if(span) span.textContent = orig; 
        currentRequestType = type; 
        openModal(); 
    })
    .finally(()=>{ if(badge) badge.classList.remove('loading'); });
}

// 🔍 ПРОВЕРКА НАЛИЧИЯ ЗАМЕРОВ НА СЕРВЕРЕ
function checkExistingMeasurements(oid, callback) {
    const badge = document.querySelector(`[onclick*="measurements-dropdown"]`);
    const span = document.getElementById('measurements-text');
    const orig = span ? span.textContent : '';
    
    if(badge) badge.classList.add('loading');
    if(span) span.textContent = '⏳ Проверка...';
    
    const fd = new FormData(); 
    fd.append('check_measurements_request','1'); 
    fd.append('order_id', oid);

    fetch('/cabinet/cabinet.php', { method:'POST', body:fd })
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(d => {
        if(d.success) {
            if(d.exists) {
                updateField('provided', '✅ Замер предоставлен', 'measurements', oid);
                showNotification('ℹ️ Данные замеров уже заполнены', 'success');
            } else {
                if(callback) callback();
            }
        } else { 
            showNotification('⚠️ '+d.message,'error'); 
        }
    }).catch(()=>{ 
        if(callback) callback();
    })
    .finally(()=>{ if(badge) badge.classList.remove('loading'); });
}

// 📤 Модальное окно
function openModal() { 
    const modal = document.getElementById('addressModal');
    if(modal) {
        modal.classList.add('show'); 
        document.body.classList.add('modal-open');
        updatePhoneState(); 
    }
}

function closeModal() { 
    const modal = document.getElementById('addressModal');
    if(modal) {
        modal.classList.remove('show'); 
        document.body.classList.remove('modal-open');
    }
    const form = document.getElementById('addressForm');
    if(form) form.reset();
    const e = document.getElementById('edit_request_id'); 
    if(e) e.value=''; 
    updatePhoneState(); 
}

// ✏️ Редактирование заявки - ГЛОБАЛЬНАЯ ФУНКЦИЯ
window.editRequest = function(id, type, data) {
    console.log('🔵 Edit clicked:', {id, type, data});
    
    try {
        currentRequestType = type;
        const f = document.getElementById('addressForm');
        if(!f) {
            console.error('❌ Form not found!');
            alert('Ошибка: форма не найдена');
            return;
        }
        
        const fields = ['city', 'street', 'house', 'entrance', 'floor', 'apartment', 'contact_person', 'phone'];
        
        fields.forEach(k => {
            const i = f.querySelector(`[name="${k}"]`); 
            if(i && data[k]) {
                i.value = data[k];
                console.log(`✅ Заполнено поле ${k}:`, data[k]);
            }
        });

        const commentInput = f.querySelector('[name="address_comment"]');
        if(commentInput && data.comment) {
            commentInput.value = data.comment;
            console.log('✅ Заполнен комментарий:', data.comment);
        }

        if(data.phone && phoneInput) {
            phoneInput.value = formatPhone(data.phone);
            console.log('📱 Телефон:', phoneInput.value);
        }
        
        let hid = document.getElementById('edit_request_id');
        if(!hid) { 
            hid = document.createElement('input'); 
            hid.type = 'hidden'; 
            hid.name = 'edit_request_id'; 
            hid.id = 'edit_request_id'; 
            f.appendChild(hid); 
        }
        hid.value = id;
        console.log('🔑 ID заявки:', id);
        
        const title = document.getElementById('modalTitle');
        if(title) {
            title.textContent = type==='measurements' ? '✏️ Редактирование замера' : '✏️ Редактирование монтажа';
        }
        
        openModal();
        console.log('✅ Модалка открыта');
    } catch(err) {
        console.error('❌ Ошибка в editRequest:', err);
        alert('Ошибка при редактировании: ' + err.message);
    }
}

function submitAddress() {
    const f = document.getElementById('addressForm'), btn = document.getElementById('submitBtn');
    if(phoneInput && phoneInput.value && !isPhoneValid(phoneInput.value)) { 
        phoneInput.classList.add('is-invalid'); 
        if(phoneHint) {
            phoneHint.textContent='⚠️ Исправьте номер'; 
            phoneHint.classList.add('invalid');
        }
        phoneInput.focus(); 
        return; 
    }
    if(!f || !f.checkValidity()) { 
        if(f) f.reportValidity(); 
        return; 
    }
    
    const fd = new FormData(f); 
    fd.append('submit_address_request','1'); 
    fd.append('order_id', window.cabOrderId || 0); 
    fd.append('type', currentRequestType);

    if(btn) {
        btn.disabled = true; 
        btn.innerHTML = '⏳ Отправка...';
    }
    
    fetch('/cabinet/cabinet.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
        if(d.success) {
            const t = currentRequestType==='measurements' ? '📐 Требуется замер' : '🔧 Требуется';
            updateField('required', t, currentRequestType, window.cabOrderId);
            closeModal();
            window.location.href = window.location.href + '?t=' + Date.now();
        } else {
            showNotification('❌ '+d.message,'error');
        }
    }).catch(() => showNotification('❌ Ошибка сети','error'))
    .finally(() => { 
        if(btn) {
            btn.disabled=false; 
            btn.innerHTML='💾 Сохранить заявку';
        }
    });
}

// 🖨️ Печать согласия
function printAgreement() {
    const f = document.getElementById('addressForm'); 
    if(!f || !f.checkValidity()) { 
        if(f) f.reportValidity(); 
        return; 
    }
    const d = Object.fromEntries(new FormData(f));
    const txt = currentRequestType==='measurements' ? 'замера' : 'монтажа';
    const w = window.open('','_blank');
    w.document.write(`<html><head><title>Согласие на ${txt}</title><style>body{font-family:Arial;padding:40px}h1{border-bottom:2px solid #3b82f6;padding-bottom:10px}.row{margin:10px 0}b{color:#64748b}sig{margin-top:60px;border-top:1px solid #ccc;padding-top:10px;display:inline-block;width:300px}</style></head><body><h1>СОГЛАСИЕ на ${txt}</h1><div class="row"><b>Город:</b> ${d.city||''}</div><div class="row"><b>Улица:</b> ${d.street||''}</div><div class="row"><b>Дом:</b> ${d.house||''}</div>${d.entrance?`<div class="row"><b>Подъезд:</b> ${d.entrance}</div>`:''}${d.floor?`<div class="row"><b>Этаж:</b> ${d.floor}</div>`:''}${d.apartment?`<div class="row"><b>Кв:</b> ${d.apartment}</div>`:''}<div class="row"><b>Контакт:</b> ${d.contact_person||''}</div><div class="row"><b>Тел:</b> ${d.phone||''}</div>${d.address_comment?`<div class="row"><b>Прим:</b> ${d.address_comment}</div>`:''}<sig>Подпись: _______________</sig></body></html>`);
    w.document.close(); 
    w.print();
}

function showNotification(msg, type='success') {
    document.querySelectorAll('.toast-notification').forEach(t => t.remove());
    const t = document.createElement('div'); 
    t.className = `toast-notification toast-${type}`; 
    t.textContent = msg; 
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity='0'; setTimeout(()=>t.remove(), 300); }, 3000);
}

// Закрытие дропдаунов при клике вне
document.addEventListener('click', e => { 
    if(!e.target.closest('.status-selector')) 
        document.querySelectorAll('.status-dropdown').forEach(d => d.classList.remove('show')); 
});

// Закрытие модалки при клике на оверлей
const modalOverlay = document.getElementById('addressModal');
if(modalOverlay) {
    modalOverlay.addEventListener('click', e => { if(e.target === modalOverlay) closeModal(); });
}

// ️ Прямая печать из карточки (без открытия модалки)
window.printDirect = function(data) {
    const txt = data.request_type === 'measurements' ? 'замера' : 'монтажа';
    
    const printContent = `
        <html>
        <head>
            <title>Согласие на ${txt}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 40px; color: #333; }
                h1 { border-bottom: 2px solid #3b82f6; padding-bottom: 10px; font-size: 24px; }
                .row { margin: 12px 0; font-size: 14px; }
                .label { font-weight: bold; color: #64748b; width: 100px; display: inline-block; }
                .sig { margin-top: 80px; border-top: 1px solid #ccc; padding-top: 10px; display: inline-block; width: 300px; text-align: center; }
            </style>
        </head>
        <body>
            <h1>СОГЛАСИЕ на ${txt}</h1>
            <div class="row"><span class="label">Город:</span> ${data.city}</div>
            <div class="row"><span class="label">Улица:</span> ${data.street}</div>
            <div class="row"><span class="label">Дом:</span> ${data.house}</div>
            ${data.entrance ? `<div class="row"><span class="label">Подъезд:</span> ${data.entrance}</div>` : ''}
            ${data.floor ? `<div class="row"><span class="label">Этаж:</span> ${data.floor}</div>` : ''}
            ${data.apartment ? `<div class="row"><span class="label">Кв:</span> ${data.apartment}</div>` : ''}
            <div class="row"><span class="label">Контакт:</span> ${data.contact_person}</div>
            <div class="row"><span class="label">Тел:</span> ${data.phone}</div>
            ${data.comment ? `<div class="row"><span class="label">Прим:</span> ${data.comment}</div>` : ''}
            <div class="sig">Подпись клиента: _______________</div>
        </body>
        </html>
    `;

    const w = window.open('', '_blank');
    w.document.write(printContent);
    w.document.close();
    w.focus();
    w.print();
};

// 🚀 Заглушка для передачи в работу
window.transferToWorkStub = function(id) {
    showNotification('⏳ Функция передачи в монтаж скоро появится', 'success');
};

/* =========================================
📏 MODAL & MEASUREMENTS LOGIC
========================================= */

window.openMeasurementModal = function(isEdit = false) {
    const modal = document.getElementById('measurementModal');
    const container = document.getElementById('roomsContainer');
    if (!modal || !container) return;

    container.innerHTML = '';

    if (isEdit && window.measurementsEditData) {
        window.measurementsEditData.forEach(room => {
            addRoom();
            const roomEl = container.lastElementChild;
            roomEl.querySelector('.room-name-input').value = room.name || '';

            room.windows.forEach((w, idx) => {
                if (idx > 0) addWindow(roomEl.dataset.roomId);
                const winCard = roomEl.querySelectorAll('.window-card')[idx];
                _fillWindowData(winCard, w);
            });
        });
    } else {
        addRoom();
    }

    modal.classList.add('show');
    document.body.classList.add('modal-open');
};

function _fillWindowData(card, data) {
    const set = (sel, val) => {
        const el = card.querySelector(sel);
        if (el) el.value = (val !== null && val !== undefined) ? val : '';
    };

    set('.cornice-type', data.cornice_type);
    set('[name="mounting_type"]', data.mounting_type);
    set('[name="wall_left"]', data.wall_left);
    set('[name="window_width"]', data.window_width);
    set('[name="wall_right"]', data.wall_right);
    set('[name="offset_left"]', data.offset_left);
    set('[name="offset_wall"]', data.offset_wall);
    set('[name="offset_right"]', data.offset_right);
    set('[name="drive_side"]', data.drive_side);
    set('[name="has_tulle"]', data.has_tulle);
    set('[name="sliding_direction"]', data.sliding_direction);
    set('[name="opening_type"]', data.opening_type);
    set('[name="height"]', data.height);

    const corniceSelect = card.querySelector('.cornice-type');
    if (corniceSelect) window.handleCorniceType(corniceSelect);

    const slidingSelect = card.querySelector('[name="sliding_direction"]');
    if (slidingSelect) window.handleSlidingDir(slidingSelect);
}

window.closeMeasurementModal = function() {
    const modal = document.getElementById('measurementModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.classList.remove('modal-open');
    }
};

window.closeMeasurementModalOutside = function(event) {
    if (event.target === event.currentTarget) {
        window.closeMeasurementModal();
    }
};

window.addRoom = function() {
    const container = document.getElementById('roomsContainer');
    const roomId = 'room-' + Date.now();
    
    const roomDiv = document.createElement('div');
    roomDiv.className = 'room-block';
    roomDiv.dataset.roomId = roomId;
    roomDiv.innerHTML = `
        <div class="room-header">
            <input type="text" class="room-name-input" placeholder="Название комнаты (например: Спальня)">
            <button type="button" class="btn-add-win" onclick="addWindow('${roomId}')">➕ Добавить окно</button>
            <button type="button" class="btn-remove-room" onclick="removeRoom('${roomId}')" title="Удалить комнату">🗑️</button>
        </div>
        <div class="windows-list" id="wins-${roomId}"></div>
    `;
    container.appendChild(roomDiv);
    addWindow(roomId);
};

window.addWindow = function(roomId) {
    const container = document.getElementById(`wins-${roomId}`);
    if (!container) return;
    
    const tpl = document.getElementById('windowTemplate').content.cloneNode(true);
    const winCard = tpl.querySelector('.window-card');
    const idx = container.children.length + 1;
    
    winCard.querySelector('.win-idx').textContent = idx;
    container.appendChild(winCard);
};

window.removeRoom = function(roomId) {
    const room = document.querySelector(`.room-block[data-room-id="${roomId}"]`);
    if (room && confirm('Удалить эту комнату и все окна внутри?')) room.remove();
};

window.removeWindow = function(btn) {
    const card = btn.closest('.window-card');
    const list = card.parentElement;
    if (list.children.length > 1) card.remove();
    else showNotification('⚠️ В комнате должно остаться минимум одно окно', 'warning');
    
    Array.from(list.children).forEach((c, i) => c.querySelector('.win-idx').textContent = i + 1);
};

window.handleCorniceType = function(select) {
    const card = select.closest('.window-card');
    const type = select.value;
    const grid = card.querySelector('.form-grid');
    
    card.querySelectorAll('.conditional-field').forEach(el => el.style.display = 'none');
    
    if (grid) grid.classList.remove('grid-sliding', 'grid-roman');

    if (type === 'sliding') {
        if (grid) grid.classList.add('grid-sliding');
        const tulleField = card.querySelector('.field-tulle');
        const slidingField = card.querySelector('.field-sliding');
        if (tulleField) tulleField.style.display = 'block';
        if (slidingField) slidingField.style.display = 'block';
    } 
    else if (type === 'roman' || type === 'roller') {
        if (grid) grid.classList.add('grid-roman');
        const heightField = card.querySelector('.field-height');
        if (heightField) heightField.style.display = 'block';
    } 
    else {
        if (grid) grid.classList.add('grid-sliding');
    }
};

window.handleSlidingDir = function(select) {
    const card = select.closest('.window-card');
    const isCenter = select.value === 'center';
    
    const openingField = card.querySelector('.field-opening');
    if (openingField) {
        openingField.style.display = isCenter ? 'block' : 'none';
    }
};

window.submitMeasurements = async function(btn) {
    if (!btn && window.event) btn = window.event.target;
    
    const rooms = [];
    let isValid = true;
    let firstInvalidField = null;
   
    console.log('🔍 Начинаем сбор данных...');

    document.querySelectorAll('.room-block').forEach(roomEl => {
        const roomName = roomEl.querySelector('.room-name-input').value.trim();
        
        if (!roomName) {
            isValid = false;
            const nameInput = roomEl.querySelector('.room-name-input');
            nameInput.style.borderColor = 'red';
            if (!firstInvalidField) firstInvalidField = nameInput;
        } else {
            roomEl.querySelector('.room-name-input').style.borderColor = '';
        }

        const windows = [];
        
        roomEl.querySelectorAll('.window-card').forEach(winEl => {
            const corniceType = winEl.querySelector('.cornice-type')?.value || '';
            const mountingType = winEl.querySelector('[name="mounting_type"]')?.value || '';
    
            const getVal = (name) => winEl.querySelector(`[name="${name}"]`)?.value?.trim() || '';
    
            const fields = {
                wall_left: parseInt(getVal('wall_left')) || null,
                window_width: parseInt(getVal('window_width')) || null,
                wall_right: parseInt(getVal('wall_right')) || null,
                offset_left: parseInt(getVal('offset_left')) || null,
                offset_right: parseInt(getVal('offset_right')) || null,
                offset_wall: parseInt(getVal('offset_wall')) || null,
                drive_position: parseInt(getVal('drive_position')) || null,
                height: parseInt(getVal('height')) || null
            };

            const selects = {
                cornice_type: corniceType,
                mounting_type: mountingType,
                drive_side: getVal('drive_side') || null,
                has_tulle: getVal('has_tulle') || null,
                sliding_direction: getVal('sliding_direction') || null,
                opening_type: getVal('opening_type') || null
            };

            const requiredFields = ['cornice_type', 'mounting_type', 'window_width'];
            requiredFields.forEach(fieldName => {
                const value = selects[fieldName] || fields[fieldName];
                if (!value || (typeof value === 'number' && value <= 0)) {
                    isValid = false;
                    const inputEl = winEl.querySelector(`[name="${fieldName}"]`) || winEl.querySelector('.cornice-type');
                    if (inputEl) {
                        inputEl.style.borderColor = 'red';
                        if (!firstInvalidField) firstInvalidField = inputEl;
                    }
                } else {
                    const inputEl = winEl.querySelector(`[name="${fieldName}"]`) || winEl.querySelector('.cornice-type');
                    if (inputEl) inputEl.style.borderColor = '';
                }
            });

            if (corniceType === 'sliding') {
                if (!selects.sliding_direction) {
                    isValid = false;
                    const el = winEl.querySelector('[name="sliding_direction"]');
                    if (el) {
                        el.style.borderColor = 'red';
                        if (!firstInvalidField) firstInvalidField = el;
                    }
                } else {
                    const el = winEl.querySelector('[name="sliding_direction"]');
                    if (el) el.style.borderColor = '';
                }
                
                if (selects.sliding_direction === 'center' && !selects.opening_type) {
                    isValid = false;
                    const el = winEl.querySelector('[name="opening_type"]');
                    if (el) {
                        el.style.borderColor = 'red';
                        if (!firstInvalidField) firstInvalidField = el;
                    }
                }
            }
            
            if (corniceType === 'roman' || corniceType === 'roller') {
                if (!fields.height || fields.height <= 0) {
                    isValid = false;
                    const el = winEl.querySelector('[name="height"]');
                    if (el) {
                        el.style.borderColor = 'red';
                        if (!firstInvalidField) firstInvalidField = el;
                    }
                } else {
                    winEl.querySelector('[name="height"]').style.borderColor = '';
                }
            }

            const w = {
                cornice_type: corniceType,
                mounting_type: mountingType,
                ...fields,
                drive_side: selects.drive_side,
                has_tulle: selects.has_tulle,
                sliding_direction: selects.sliding_direction,
                opening_type: selects.opening_type
            };
            
            windows.push(w);
        });
        
        if (windows.length === 0) {
            isValid = false;
            showNotification('⚠️ В комнате должно быть хотя бы одно окно', 'error');
        }
        
        rooms.push({ name: roomName || 'Без названия', windows });
    });

    if (!isValid) {
        showNotification('⚠️ Заполните все обязательные поля (отмечены красным)', 'error');
        console.warn('❌ Валидация не пройдена');
        if (firstInvalidField) {
            firstInvalidField.focus();
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }

    console.log('🚀 Отправка на сервер:', { rooms });
    
    btn.disabled = true; 
    btn.innerHTML = '⏳ Сохранение...';

    try {
        const res = await fetch('/cabinet/cabinet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'save_measurements', 
                order_id: window.cabOrderId, 
                data: { rooms } 
            })
        });
        
        const d = await res.json();
        console.log('📡 Ответ сервера:', d);

        if (d.success) {
            showNotification('✅ ' + d.message, 'success');
            window.closeMeasurementModal();
            location.reload(); 
        } else {
            showNotification('❌ ' + d.message, 'error');
        }
    } catch (err) {
        console.error(err);
        showNotification('❌ Ошибка сети', 'error');
    } finally {
        if (!d?.success) {
            btn.disabled = false; 
            btn.innerHTML = '💾 Сохранить замеры';
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.getElementById('measurementModal');
    if (overlay) {
        overlay.addEventListener('click', window.closeMeasurementModalOutside);
    }
    
    const style = document.createElement('style');
    style.textContent = `
        .conditional-field { transition: all 0.3s ease; }
        .conditional-field:not([style*="display:none"]) { animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    `;
    document.head.appendChild(style);
});

/* =========================================
   🔹 НОВЫЕ ФУНКЦИИ ДЛЯ КНОПОК ЗАЯВОК
   ========================================= */

async function sendToWork(requestId, type, mode) {
    const labels = {
        'to_system': 'общую очередь (сотрудники Neirolinks)',
        'to_dealer': 'ваших исполнителей'
    };
    
    const actionLabel = type === 'measurements' ? 'замер' : 'монтаж';
    
    if (!confirm(`📤 Передать заявку на ${actionLabel} в ${labels[mode]}?`)) {
        return;
    }
    
    try {
        const res = await fetch('/api/create_request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                request_id: requestId,
                type: type,
                mode: mode
            }),
            redirect: 'manual'
        });
        
        if (res.status === 401) {
            if (confirm('⚠️ Ваша сессия истекла. Войти снова?')) {
                window.location.href = '/auth/login.php';
            }
            return;
        }
        
        const contentType = res.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await res.text();
            console.error('❌ Ожидался JSON, получено:', text.substring(0, 200));
            showNotification('❌ Ошибка сервера', 'error');
            return;
        }
        
        const apiResult = await res.json();
        
        if (apiResult.success) {
            showNotification('✅ ' + apiResult.message, 'success');
            window.location.href = window.location.href + '?t=' + Date.now();
        } else {
            showNotification('❌ ' + apiResult.message, 'error');
        }
    } catch (err) {
        console.error('Ошибка отправки заявки:', err);
        showNotification('❌ Ошибка сети. Проверьте подключение.', 'error');
    }
}

async function sendToClient(requestId, type) {
    if (type !== 'measurements') {
        showNotification('⚠️ Ссылка для клиента доступна только для заявок на замер', 'warning');
        return;
    }
    
    try {
        const res = await fetch('/api/create_request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                request_id: requestId,
                type: 'measurement',
                mode: 'to_client'
            }),
            redirect: 'manual'
        });
        
        if (res.status === 401) {
            if (confirm('⚠️ Ваша сессия истекла. Войти снова?')) {
                window.location.href = '/auth/login.php';
            }
            return;
        }
        
        const contentType = res.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await res.text();
            console.error('❌ Ожидался JSON, получено:', text.substring(0, 200));
            showNotification('❌ Ошибка сервера (см. консоль)', 'error');
            return;
        }
        
        const apiResult = await res.json();
        
        if (apiResult.success && apiResult.public_link) {
            showPublicLinkModal(apiResult.public_link);
        } else {
            showNotification('❌ ' + (apiResult.message || 'Не удалось создать ссылку'), 'error');
        }
    } catch (err) {
        console.error('Ошибка создания ссылки:', err);
        showNotification('❌ Ошибка сети. Проверьте подключение.', 'error');
    }
}

function showPublicLinkModal(link) {
    const oldModal = document.getElementById('publicLinkModal');
    if (oldModal) oldModal.remove();
    
    const modal = document.createElement('div');
    modal.id = 'publicLinkModal';
    modal.className = 'modal-overlay-pwa';
    modal.style.cssText = `
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        animation: fadeIn 0.2s ease;
    `;
    
    modal.innerHTML = `
        <div style="
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            max-width: 480px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: slideUp 0.3s ease;
        ">
            <h3 style="margin: 0 0 12px; font-size: 18px; font-weight: 600; color: #1e293b;">
                🔗 Ссылка для клиента
            </h3>
            <p style="margin: 0 0 16px; color: #64748b; font-size: 14px; line-height: 1.5;">
                Отправьте эту ссылку клиенту. Он сможет заполнить замеры без авторизации.
                <br><strong>Срок действия:</strong> 7 дней.
            </p>
            
            <div style="display: flex; gap: 8px; margin-bottom: 20px;">
                <input type="text" 
                       value="${link}" 
                       readonly
                       onclick="this.select()"
                       style="
                           flex: 1;
                           padding: 10px 12px;
                           border: 1px solid #cbd5e1;
                           border-radius: 6px;
                           font-size: 13px;
                           background: #f8fafc;
                           color: #334155;
                       ">
                <button type="button"
                        onclick="copyToClipboard('${link}', this)"
                        style="
                            padding: 10px 16px;
                            background: #3b82f6;
                            color: #fff;
                            border: none;
                            border-radius: 6px;
                            font-size: 13px;
                            font-weight: 500;
                            cursor: pointer;
                            transition: background 0.2s;
                        ">
                    📋 Копировать
                </button>
            </div>
            
            <button type="button"
                    onclick="closePublicLinkModal()"
                    style="
                        width: 100%;
                        padding: 10px;
                        background: #f1f5f9;
                        color: #334155;
                        border: none;
                        border-radius: 6px;
                        font-size: 14px;
                        font-weight: 500;
                        cursor: pointer;
                        transition: background 0.2s;
                    ">
                Закрыть
            </button>
        </div>
    `;
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closePublicLinkModal();
    });
    
    const onEsc = (e) => {
        if (e.key === 'Escape') {
            closePublicLinkModal();
            document.removeEventListener('keydown', onEsc);
        }
    };
    document.addEventListener('keydown', onEsc);
    
    document.body.appendChild(modal);
}

function closePublicLinkModal() {
    const modal = document.getElementById('publicLinkModal');
    if (modal) {
        modal.style.animation = 'fadeOut 0.2s ease';
        setTimeout(() => modal.remove(), 200);
    }
}

async function copyToClipboard(text, btn) {
    try {
        await navigator.clipboard.writeText(text);
        showCopyFeedback(btn);
    } catch (err) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showCopyFeedback(btn);
    }
}

function showCopyFeedback(btn) {
    const originalText = btn.innerHTML;
    const originalBg = btn.style.background;
    
    btn.innerHTML = '✅ Скопировано!';
    btn.style.background = '#10b981';
    
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.style.background = originalBg;
    }, 2000);
}

(function addModalAnimations() {
    if (document.getElementById('modal-animations-style')) return;
    
    const style = document.createElement('style');
    style.id = 'modal-animations-style';
    style.textContent = `
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    `;
    document.head.appendChild(style);
})();