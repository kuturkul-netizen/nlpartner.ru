<div id="measurementModal" class="modal-overlay" onclick="closeMeasurementModalOutside(event)">
    <div class="modal-content modal-measurements" onclick="event.stopPropagation()">
        
        <!-- Шапка -->
        <div class="modal-header">
            <h3 class="modal-title">📐 Ввод замеров</h3>
            <p class="modal-subtitle">Укажите размеры для каждого окна</p>
        </div>
        
        <!-- Тело: комнаты -->
        <div class="modal-body" id="roomsContainer">
            <!-- Комнаты добавляются сюда через JS -->
        </div>
        
        <!-- Футер: кнопки -->
        <div class="modal-footer">
            <button type="button" class="btn-add-room" onclick="addRoom()">➕ Добавить комнату</button>
            <button type="button" class="btn-primary" onclick="submitMeasurements(this)">💾 Сохранить замеры</button>
            <button type="button" class="btn-cancel" onclick="closeMeasurementModal()">Отмена</button>
        </div>
        
    </div>
</div>

<!-- 🔹 Шаблон окна (клонирование через JS) -->
<template id="windowTemplate">
    <div class="window-card">
        <div class="window-header">
            <span class="window-title">Окно #<span class="win-idx">1</span></span>
            <button type="button" class="btn-remove-win" onclick="removeWindow(this)" title="Удалить">🗑️</button>
        </div>
        
        <!-- 🔥 Сетка полей: класс grid-sliding по умолчанию, меняется через JS -->
        <div class="form-grid grid-sliding">
            
            <!-- ===== РЯД 1: Тип, Крепление, Тюль/Высота ===== -->
            
            <!-- Тип карниза -->
            <div class="form-group">
                <label class="form-label">Тип карниза</label>
                <select class="form-input cornice-type" onchange="handleCorniceType(this)">
                    <option value="" disabled selected>Выбрать</option>
                    <option value="sliding">Раздвижной</option>
                    <option value="roman">Римская штора</option>
                    <option value="roller">Рулонная штора</option>
                </select>
            </div>
            
            <!-- Тип крепления -->
            <div class="form-group">
                <label class="form-label">Тип крепления</label>
                <select class="form-input" name="mounting_type">
                    <option value="" disabled selected>Выбрать</option>
                    <option value="ceiling">К потолку</option>
                    <option value="wall">К стене</option>
                </select>
            </div>

            <!-- Тюль (виден для раздвижных) -->
            <div class="form-group conditional-field field-tulle">
                <label class="form-label">Наличие тюля</label>
                <select class="form-input" name="has_tulle">
                    <option value="" disabled selected>Выбрать</option>
                    <option value="0">Нет</option>
                    <option value="1">Да</option>
                </select>
            </div>

            <!-- Высота (видна для римских/рулонных) -->
            <div class="form-group conditional-field field-height" style="display:none;">
                <label class="form-label">Высота проёма</label>
                <input type="number" class="form-input" name="height" placeholder="мм">
            </div>


            <!-- ===== РЯД 2: Простенки и проём ===== -->
            
            <div class="form-group">
                <label class="form-label">Левый простенок</label>
                <input type="number" class="form-input" name="wall_left" placeholder="мм">
            </div>
            <div class="form-group">
                <label class="form-label">Оконный проём *</label>
                <input type="number" class="form-input" name="window_width" placeholder="мм" required>
            </div>
            <div class="form-group">
                <label class="form-label">Правый простенок</label>
                <input type="number" class="form-input" name="wall_right" placeholder="мм">
            </div>


            <!-- ===== РЯД 3: Отступы + Привод ===== -->
            
            <div class="form-group">
                <label class="form-label">Отступ слева</label>
                <input type="number" class="form-input" name="offset_left" placeholder="мм">
            </div>
            <div class="form-group">
                <label class="form-label">Отступ от стены</label>
                <input type="number" class="form-input" name="offset_wall" placeholder="мм">
            </div>
            <div class="form-group">
                <label class="form-label">Отступ справа</label>
                <input type="number" class="form-input" name="offset_right" placeholder="мм">
            </div>
            
            <!-- Привод (в этом ряду, после отступов) -->
            <div class="form-group">
                <label class="form-label">Положение привода</label>
                <select class="form-input" name="drive_side">
                    <option value="" disabled selected>Выбрать</option>
                    <option value="left">Слева</option>
                    <option value="right">Справа</option>
                </select>
            </div>


            <!-- ===== РЯД 4: Раздвижка и открытие (только для sliding) ===== -->
            
            <div class="form-group conditional-field field-sliding" style="display:none;">
                <label class="form-label">Раздвижка от</label>
                <select class="form-input" name="sliding_direction" onchange="handleSlidingDir(this)">
                    <option value="" disabled selected>Выбрать</option>
                    <option value="center">От центра</option>
                    <option value="edge">От края</option>
                </select>
            </div>
            
            <div class="form-group conditional-field field-opening" style="display:none;">
                <label class="form-label">Открытие</label>
                <select class="form-input" name="opening_type">
                    <option value="" disabled selected>Выбрать</option>
                    <option value="from_center">От центра карниза</option>
                    <option value="from_edge">От центра окна</option>
                </select>
            </div>
            
        </div>
    </div>
</template>