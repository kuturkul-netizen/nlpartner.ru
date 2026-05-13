<?php
$rooms = [];
foreach ($measurementsData as $row) {
    $rid = $row['room_id'] ?? 'tmp_' . uniqid();
    if (!isset($rooms[$rid])) {
        $rooms[$rid] = ['name' => $row['room_name'] ?: 'Комната', 'windows' => []];
    }
    $rooms[$rid]['windows'][] = $row;
}

$dict = [
    'cornice' => ['sliding' => 'Раздвижной', 'roman' => 'Римская штора', 'roller' => 'Рулонная штора'],
    'mounting' => ['ceiling' => 'К потолку', 'wall' => 'К стене'],
    'drive' => ['left' => 'Слева', 'right' => 'Справа'],
    'sliding_dir' => ['center' => 'От центра', 'edge' => 'От края'],
    'opening' => ['from_center' => 'От центра карниза', 'from_edge' => 'От центра окна'],
    'tulle' => ['1' => 'Да', '0' => 'Нет']
];
?>

<!-- ВНЕШНИЙ КОНТЕЙНЕР -->
<div class="detail-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
    
    <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #1e293b;">
        📐 Замеры
    </h3>
    
    <!-- Внутренняя карточка -->
    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #3b82f6; border-radius:8px; margin-bottom:16px; overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
        
        <!-- Шапка -->
        <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; width:100%; box-sizing:border-box;">
            <div style="font-size: 14px; font-weight: 600; color: #334155;">📐 Фактические размеры</div>
            <div style="font-size: 14px; font-weight: 600; color: #334155;">
                <span style="font-weight: normal; color:#64748b; margin-left:4px;">
                    <?= count($rooms) ?> комн. / <?= count($measurementsData) ?> ок.
                </span>
            </div>
        </div>

        <!-- Тело -->
        <div style="padding: 16px 16px 0px 16px; font-size:14px; line-height:1.6; color:#475569; background: #fff;">
            <?php foreach ($rooms as $room): ?>
                <div style="margin-bottom: 0; padding-bottom: 0;">
                    
                    <!-- 🪵 Комната (синяя кнопка) -->
                    <span style="display:inline-block; background:#3b82f6; color:#ffffff; padding:1px 14px; border-radius:6px; font-size:14px; font-weight:600; margin-bottom:12px; border:none;">
                        🪵 <?= htmlspecialchars($room['name']) ?>
                    </span>

                    <?php $winNum = 0; foreach ($room['windows'] as $w): $winNum++; $type = $w['cornice_type'] ?? ''; ?>
                        
                        <!-- 🟢 Окно (зеленая кнопка-тег) -->
                        <div style="margin-bottom:12px;">
                            <span style="display:inline-block; background:#10b981; color:#fff; padding:1px 14px; border-radius:6px; font-size:14px; font-weight:600; margin-bottom:8px;">
                                Окно №<?= $winNum ?>
                            </span>
                            
                            <!-- Сетка полей (стандартный серый стиль) -->
                            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:8px;">
                                <!-- РЯД 1 -->
                                <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; font-size:13px; line-height:1.4;"><strong>Тип карниза:</strong> <?= $dict['cornice'][$type] ?? '—' ?></div>
                                <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; font-size:13px; line-height:1.4;"><strong>Тип крепления:</strong> <?= $dict['mounting'][$w['mounting_type'] ?? ''] ?? 'Не выбрано' ?></div>
                                <?php if ($type === 'sliding'): ?>
                                    <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; font-size:13px; line-height:1.4;"><strong>Наличие тюля:</strong> <?= $dict['tulle'][$w['has_tulle'] ?? ''] ?? 'Не выбрано' ?></div>
                                <?php else: ?>
                                    <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; font-size:13px; line-height:1.4;"><strong>Высота проёма:</strong> <?= (!empty($w['height'])) ? (int)$w['height'] . ' мм' : '—' ?></div>
                                <?php endif; ?>

                                <!-- РЯД 2 -->
                                <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; font-size:13px; line-height:1.4;"><strong>Левый простенок:</strong> <?= (!empty($w['wall_left'])) ? (int)$w['wall_left'] . ' мм' : '—' ?></div>
                                <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; font-size:13px; line-height:1.4;"><strong>Оконный проём:</strong> <?= (!empty($w['window_width'])) ? (int)$w['window_width'] . ' мм' : '—' ?></div>
                                <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; font-size:13px; line-height:1.4;"><strong>Правый простенок:</strong> <?= (!empty($w['wall_right'])) ? (int)$w['wall_right'] . ' мм' : '—' ?></div>

                                <!-- РЯД 3 -->
                                <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; font-size:13px; line-height:1.4;"><strong>Отступ слева:</strong> <?= (!empty($w['offset_left'])) ? (int)$w['offset_left'] . ' мм' : '—' ?></div>
                                <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; font-size:13px; line-height:1.4;"><strong>Отступ от стены:</strong> <?= (!empty($w['offset_wall'])) ? (int)$w['offset_wall'] . ' мм' : '—' ?></div>
                                <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; font-size:13px; line-height:1.4;"><strong>Отступ справа:</strong> <?= (!empty($w['offset_right'])) ? (int)$w['offset_right'] . ' мм' : '—' ?></div>

                                <!-- РЯД 4 -->
                                <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; font-size:13px; line-height:1.4;"><strong>Положение привода:</strong> <?= $dict['drive'][$w['drive_side'] ?? ''] ?? 'Не выбрано' ?></div>
                                <?php if ($type === 'sliding'): ?>
                                    <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; font-size:13px; line-height:1.4;"><strong>Раздвижка от:</strong> <?= $dict['sliding_dir'][$w['sliding_direction'] ?? ''] ?? '—' ?></div>
                                    <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; font-size:13px; line-height:1.4;"><strong>Открытие:</strong> <?= $dict['opening'][$w['opening_type'] ?? ''] ?? '—' ?></div>
                                <?php else: ?>
                                    <div style="visibility:hidden"></div>
                                    <div style="visibility:hidden"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Футер -->
        <div style="display:flex; align-items:center; justify-content:flex-end; gap:10px; padding: 0px 16px 12px; background:#fff;">
            <button type="button" onclick="openMeasurementModal(true)" style="background:#3b82f6; border:none; color:#fff; padding:6px 14px; border-radius:6px; font-size:13px; cursor:pointer; transition:0.2s; font-weight:500;">
                ✏️ Редактировать
            </button>
        </div>
        
    </div>
</div>

<script>
    window.measurementsEditData = <?= json_encode(array_values($rooms), JSON_UNESCAPED_UNICODE) ?>;
</script>