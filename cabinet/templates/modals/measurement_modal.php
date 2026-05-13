<div id="measurementModal" class="modal-overlay" onclick="closeMeasurementModalOutside(event)">
    <div class="modal-content modal-measurements" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 class="modal-title">📐 Ввод замеров</h3>
            <p class="modal-subtitle">Укажите размеры для каждого окна</p>
        </div>
        
        <div class="modal-body" id="roomsContainer">
            <!-- Комнаты добавляются сюда -->
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn-add-room" onclick="addRoom()">➕ Добавить комнату</button>
            <button type="button" class="btn-primary" onclick="submitMeasurements(this)">💾 Сохранить замеры</button>
            <button type="button" class="btn-cancel" onclick="closeMeasurementModal()">Отмена</button>
        </div>
    </div>
</div>

<!-- 🔹 Шаблон окна (клон JS) -->
<template id="windowTemplate">
    <div class="window-card">
        <div class="window-header">
            <span class="window-title">Окно #<span class="win-idx">1</span></span>
            <button type="button" class="btn-remove-win" onclick="removeWindow(this)" title="Удалить">🗑️</button>
        </div>
        
        <!-- 🔥 Сетка: класс grid-sliding по умолчанию, меняется через JS -->
        <div class="form-grid grid-sliding">
            
            <!-- ===== РЯД 1: Тип, Крепление, Тюль/Высота ===== -->
            
            <!-- Тип карниза -->
            <div class="form-group">
                <label>Тип карниза</label>
                <select class="cornice-type" onchange="handleCorniceType(this)">
                    <option value="" disabled selected>Выбрать</option>
                    <option value="sliding">Раздвижной</option>
                    <option value="roman">Римская штора</option>
                    <option value="roller">Рулонная штора</option>
                </select>
            </div>
            
            <!-- Тип крепления -->
            <div class="form-group">
                <label>Тип крепления</label>
                <select name="mounting_type">
                    <option value="" disabled selected>Выбрать</option>
                    <option value="ceiling">К потолку</option>
                    <option value="wall">К стене</option>
                </select>
            </div>

            <!-- Тюль (виден для раздвижных) -->
            <div class="form-group conditional-field field-tulle">
                <label>Наличие тюля</label>
                <select name="has_tulle">
                    <option value="" disabled selected>Выбрать</option>
                    <option value="0">Нет</option>
                    <option value="1">Да</option>
                </select>
            </div>

            <!-- Высота (видна для римских/рулонных) -->
            <div class="form-group conditional-field field-height" style="display:none;">
                <label>Высота проёма</label>
                <input type="number" name="height" placeholder="мм">
            </div>


            <!-- ===== РЯД 2: Простенки и проём ===== -->
            
            <div class="form-group">
                <label>Левый простенок</label>
                <input type="number" name="wall_left" placeholder="мм">
            </div>
            <div class="form-group">
                <label>Оконный проём *</label>
                <input type="number" name="window_width" placeholder="мм">
            </div>
            <div class="form-group">
                <label>Правый простенок</label>
                <input type="number" name="wall_right" placeholder="мм">
            </div>


            <!-- ===== РЯД 3: Отступы + Привод ===== -->
            
            <div class="form-group">
                <label>Отступ слева</label>
                <input type="number" name="offset_left" placeholder="мм">
            </div>
            <div class="form-group">
                <label>Отступ от стены</label>
                <input type="number" name="offset_wall" placeholder="мм">
            </div>
            <div class="form-group">
                <label>Отступ справа</label>
                <input type="number" name="offset_right" placeholder="мм">
            </div>
            
            <!-- Привод (теперь в этом ряду, после отступов) -->
            <div class="form-group">
                <label>Положение привода</label>
                <select name="drive_side">
                    <option value="" disabled selected>Выбрать</option>
                    <option value="left">Слева</option>
                    <option value="right">Справа</option>
                </select>
            </div>


            <!-- ===== РЯД 4: Раздвижка и открытие (только для sliding) ===== -->
            
            <div class="form-group conditional-field field-sliding" style="display:none;">
                <label>Раздвижка от</label>
                <select name="sliding_direction" onchange="handleSlidingDir(this)">
                    <option value="" disabled selected>Выбрать</option>
                    <option value="center">От центра</option>
                    <option value="edge">От края</option>
                </select>
            </div>
            
            <div class="form-group conditional-field field-opening" style="display:none;">
                <label>Открытие</label>
                <select name="opening_type">
                    <option value="" disabled selected>Выбрать</option>
                    <option value="from_center">От центра карниза</option>
                    <option value="from_edge">От центра окна</option>
                </select>
            </div>
            
        </div>
    </div>
</template>