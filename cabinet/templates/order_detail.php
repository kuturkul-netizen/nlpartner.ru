<?php 
// Показываем сообщение об успехе и сразу очищаем сессию
$successMsg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['success_msg']);

// ✅ ОПРЕДЕЛЯЕМ переменные для проверки наличия заявок
$hasMeasurementRequests = ($detailOrder['measurements'] === 'required' && !empty(array_filter($addressRequests, fn($r) => $r['request_type'] === 'measurements')));
$hasInstallationRequests = ($detailOrder['installation'] === 'required' && !empty(array_filter($addressRequests, fn($r) => $r['request_type'] === 'installation')));
?>

<?php if ($successMsg): ?>
<div class="msg-success"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>

<div class="detail-card">
<div class="detail-header">
    <h2 style="margin:0;">📦 Заказ №<?= $detailOrder['id'] ?></h2>
    <div class="status-row">
        <?php 
        $fields = [
            ['key'=>'measurements','label'=>'Замеры','map'=>$measurementsMap],
            ['key'=>'installation','label'=>'Установка','map'=>$installationMap],
            ['key'=>'status','label'=>'Статус','map'=>$statusMap]
        ];
        foreach($fields as $f): 
            $val = $detailOrder[$f['key']] ?? '';
            $txt = (!empty($val) && isset($f['map'][$val])) ? $f['map'][$val] : 'Выбрать';
        ?>
        <div class="dropdown-group">
            <span class="dropdown-label"><?= $f['label'] ?></span>
            <div class="status-selector">
                <span class="status-badge" onclick="toggleDropdown('<?= $f['key'] ?>-dropdown')">
                    <span id="<?= $f['key'] ?>-text"><?= $txt ?></span> <span>▼</span>
                </span>
                <div id="<?= $f['key'] ?>-dropdown" class="status-dropdown">
                    <?php foreach($f['map'] as $k=>$v): ?>
                    <div class="status-option <?= $k===$val?'active':'' ?>" data-value="<?= $k ?>" onclick="selectOption('<?= $k ?>','<?= htmlspecialchars($v,ENT_QUOTES) ?>','<?= $f['key'] ?>', <?= $detailOrder['id'] ?>)"><?= $v ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-item"><strong>📅 ДАТА</strong><span style="font-weight:bold;"><?= date('d.m.Y H:i', strtotime($detailOrder['created_at'])) ?></span></div>
    <?php
    $itemsForCalc = json_decode($detailOrder['items_json'] ?? '[]', true) ?: [];
    $totalRrc = $totalDisc = 0;
    foreach($itemsForCalc as $it) { $base=($it['r']??0)*($it['q']??1); $totalRrc+=$base; $totalDisc+=$base*(1-($it['dis']??0)/100); }
    ?>
    <div class="detail-item"><strong>💸 СКИДКА</strong><span style="color:#ef4444; font-weight:bold;">- <?= number_format($totalRrc-$totalDisc,0,'.',' ') ?> ₽</span></div>
    <div class="detail-item"><strong>💰 ИТОГО</strong><span style="font-weight:bold;"><?= number_format($totalDisc,0,'.',' ') ?> ₽</span></div>
    <div class="detail-item">
        <strong>📈 МАРЖА</strong>
        <span style="font-weight:bold; color: <?= ($detailOrder['margin']>=0?'#10b981':'#ef4444') ?>;">
            <?= ($detailOrder['margin']>=0?'+':'') ?><?= number_format($detailOrder['margin'],0,'.',' ') ?> ₽
        </span>
    </div>
</div>

<h3 style="margin:20px 0 10px;">📋 Состав заказа</h3>
<div class="table-wrap">
<table class="items-table">
    <thead><tr><th>Товар</th><th>Кол-во</th><th>Цена</th><th>Сумма</th></tr></thead>
    <tbody>
    <?php foreach($itemsForCalc as $it): $p=round($it['r']*(1-($it['dis']??0)/100)); ?>
    <tr><td><?= htmlspecialchars($it['name']) ?></td><td><?= $it['q'] ?></td><td><?= number_format($p,0,'.',' ') ?> ₽</td><td><?= number_format($p*$it['q'],0,'.',' ') ?> ₽</td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- ОДИН ОБЩИЙ БЛОК ЗАЯВОК -->
<div class="detail-card" style="margin-top:20px; display: <?= ($hasMeasurementRequests || $hasInstallationRequests) ? 'block' : 'none' ?>;" id="requestsSection">
    <h3 style="margin: 0 0 16px 0; font-size: 20px; font-weight: 700; color: #1e293b; line-height: 1.2;">📋 Заявки</h3>
    
    <?php if (empty($addressRequests)): ?>
        <p style="color:#94a3b8; padding: 10px;">Заявок пока нет.</p>
    <?php else: 
        // Разделяем заявки по типам
        $measurementReqs = array_filter($addressRequests, fn($r) => $r['request_type'] === 'measurements');
        $installationReqs = array_filter($addressRequests, fn($r) => $r['request_type'] === 'installation');
    ?>

    <!-- БЛОК ЗАМЕРОВ (синий) -->
    <div id="measurementRequestsContent" style="display: <?= !empty($measurementReqs) ? 'block' : 'none' ?>;">
        <?php foreach($measurementReqs as $req): ?>
        <div class="request-card" style="padding:0; background:#fff; border:1px solid #e2e8f0; border-left: 4px solid #3b82f6; border-radius:8px; margin-bottom:16px; overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
            
            <!-- Шапка -->
            <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                <div style="font-size: 14px; font-weight: 600; color: #334155;">📐 Заявка на замеры</div>
                <div style="font-size: 14px; font-weight: 600; color: #334155;">
                    Дата создания:
                    <span style="margin-left:4px; font-weight: normal; color:#64748b;"><?= date('d.m.Y H:i', strtotime($req['created_at'])) ?></span>
                </div>
            </div>

            <!-- Тело -->
            <div style="padding:16px; font-size:14px; line-height:1.6; color:#475569; background: #fff;">
                <div style="margin-bottom:4px;">
                    <strong>📍 Адрес:</strong> <?= htmlspecialchars($req['city']) ?>, <?= htmlspecialchars($req['street']) ?>, д. <?= htmlspecialchars($req['house']) ?>
                    <?php if($req['entrance']): ?>, подъезд <?= htmlspecialchars($req['entrance']) ?><?php endif; ?>
                    <?php if($req['floor']): ?>, эт. <?= htmlspecialchars($req['floor']) ?><?php endif; ?>
                    <?php if($req['apartment']): ?>, кв. <?= htmlspecialchars($req['apartment']) ?><?php endif; ?>
                </div>
                <div style="margin-bottom:8px;">
                    <strong>👤 Контакт:</strong> <?= htmlspecialchars($req['contact_person']) ?> | 📞 <?= htmlspecialchars($req['phone']) ?>
                </div>
                <?php if(!empty($req['comment'])): ?>
                    <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; color:#64748b; margin-top:8px; border:none;">
                         <strong>💬 Комментарий:</strong> <?= htmlspecialchars($req['comment']) ?>
                    </div>
                <?php endif; ?>
            </div>

<!-- Кнопки (синие) -->
<div style="display:flex; align-items:center; justify-content:flex-end; gap:10px; padding: 0px 16px; background:#fff;">
    <button type="button" onclick="sendToWork(<?= $req['id'] ?>, 'measurements', 'to_system')" style="background:transparent; border:1px solid #3b82f6; color:#3b82f6; padding:6px 12px; margin-bottom:2px; border-radius:6px; font-size:13px; cursor:pointer; transition:0.2s; font-weight:500;" title="Замер выполнит сотрудник Neirolinks">
         🌐 Передать в работу
    </button>
    <button type="button" onclick="sendToWork(<?= $req['id'] ?>, 'measurements', 'to_dealer')" style="background:transparent; border:1px solid #3b82f6; color:#3b82f6; padding:6px 12px; margin-bottom:2px; border-radius:6px; font-size:13px; cursor:pointer; transition:0.2s; font-weight:500;" title="Замер выполнит сотрудник дилера">
         👥 Взять в работу
    </button>
    <button type="button" onclick="sendToClient(<?= $req['id'] ?>, 'measurements')" style="background:transparent; border:1px solid #3b82f6; color:#3b82f6; padding:6px 12px; margin-bottom:2px; border-radius:6px; font-size:13px; cursor:pointer; transition:0.2s; font-weight:500;" title="Замер выполнит клиент">
         🔗 Передать клиенту
    </button>
    <button type="button" onclick="printDirect(<?= htmlspecialchars(json_encode($req, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8') ?>)" style="background:transparent; border:1px solid #3b82f6; color:#3b82f6; padding:6px 12px; margin-bottom:2px; border-radius:6px; font-size:13px; cursor:pointer; transition:0.2s; font-weight:500;" title="Распечатать согласие на обработку персональных данных">
        🖨️ Распечатать согласие
    </button>
    <button type="button" onclick="editRequest(<?= $req['id'] ?>, 'measurements', <?= htmlspecialchars(json_encode($req, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8') ?>)" style="background:#3b82f6; border:none; color:#fff; padding:6px 14px; margin-bottom:1px; border-radius:6px; font-size:13px; cursor:pointer; transition:0.2s; font-weight:500;" title="Редактировать заявку">
         ✏️ Редактировать
    </button>
</div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- БЛОК МОНТАЖА (зеленый) -->
    <div id="installationRequestsContent" style="display: <?= !empty($installationReqs) ? 'block' : 'none' ?>;">
        <?php foreach($installationReqs as $req): ?>
        <div class="request-card" style="padding:0; background:#fff; border:1px solid #e2e8f0; border-left: 4px solid #10b981; border-radius:8px; margin-bottom:16px; overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
            
            <!-- Шапка -->
            <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                <div style="font-size: 14px; font-weight: 600; color: #334155;">🔧 Заявка на монтаж</div>
                <div style="font-size: 14px; font-weight: 600; color: #334155;">
                    Дата создания:
                    <span style="margin-left:4px; font-weight: normal; color:#64748b;"><?= date('d.m.Y H:i', strtotime($req['created_at'])) ?></span>
                </div>
            </div>

            <!-- Тело -->
            <div style="padding:16px; font-size:14px; line-height:1.6; color:#475569; background: #fff;">
                <div style="margin-bottom:4px;">
                    <strong>📍 Адрес:</strong> <?= htmlspecialchars($req['city']) ?>, <?= htmlspecialchars($req['street']) ?>, д. <?= htmlspecialchars($req['house']) ?>
                    <?php if($req['entrance']): ?>, подъезд <?= htmlspecialchars($req['entrance']) ?><?php endif; ?>
                    <?php if($req['floor']): ?>, эт. <?= htmlspecialchars($req['floor']) ?><?php endif; ?>
                    <?php if($req['apartment']): ?>, кв. <?= htmlspecialchars($req['apartment']) ?><?php endif; ?>
                </div>
                <div style="margin-bottom:8px;">
                    <strong>👤 Контакт:</strong> <?= htmlspecialchars($req['contact_person']) ?> | 📞 <?= htmlspecialchars($req['phone']) ?>
                </div>
                <?php if(!empty($req['comment'])): ?>
                    <div style="background:#f1f5f9; padding:6px 10px; border-radius:4px; color:#64748b; margin-top:8px; border:none;">
                         <strong>💬 Комментарий:</strong> <?= htmlspecialchars($req['comment']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Кнопки (зеленые) -->
<div style="display:flex; align-items:center; justify-content:flex-end; gap:10px; padding: 0px 16px; background:#fff;">
    <button type="button" onclick="sendToWork(<?= $req['id'] ?>, 'installation', 'to_system')" style="background:transparent; border:1px solid #10b981; color:#10b981; padding:6px 12px; margin-bottom:2px; border-radius:6px; font-size:13px; cursor:pointer; transition:0.2s; font-weight:500;" title="Монтаж выполнит сотрудник Neirolinks">
         🌐 Передать в работу
    </button>
    <button type="button" onclick="sendToWork(<?= $req['id'] ?>, 'installation', 'to_dealer')" style="background:transparent; border:1px solid #10b981; color:#10b981; padding:6px 12px; margin-bottom:2px; border-radius:6px; font-size:13px; cursor:pointer; transition:0.2s; font-weight:500;" title="Монтаж выполнит сотрудник дилера">
         👥 Взять в работу
    </button>
    <button type="button" onclick="printDirect(<?= htmlspecialchars(json_encode($req, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8') ?>)" style="background:transparent; border:1px solid #10b981; color:#10b981; padding:6px 12px; margin-bottom:2px; border-radius:6px; font-size:13px; cursor:pointer; transition:0.2s; font-weight:500;" title="Распечатать согласие на обработку персональных данных">
        🖨️ Распечатать согласие
    </button>
    <button type="button" onclick="editRequest(<?= $req['id'] ?>, 'installation', <?= htmlspecialchars(json_encode($req, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8') ?>)" style="background:#10b981; border:none; color:#fff; padding:6px 14px; margin-bottom:1px; border-radius:6px; font-size:13px; cursor:pointer; transition:0.2s; font-weight:500;" title="Редактировать заявку">
         ✏️ Редактировать
    </button>
</div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>
<?php if (!empty($measurementsData)): ?>
    <?php require __DIR__ . '/modals/measurement_display.php'; ?>
<?php endif; ?>
<div class="comment-form">
    <h3>💬 Комментарий</h3>
    <form method="POST">
        <input type="hidden" name="view_order_id" value="<?= $detailOrder['id'] ?>">
        <input type="hidden" name="order_status" id="order_status_hidden" value="<?= htmlspecialchars($detailOrder['status']) ?>">
        <input type="hidden" name="order_measurements" id="order_measurements_hidden" value="<?= htmlspecialchars($detailOrder['measurements'] ?? '') ?>">
        <input type="hidden" name="order_installation" id="order_installation_hidden" value="<?= htmlspecialchars($detailOrder['installation'] ?? '') ?>">
        <textarea name="comment" style="width: 100%; box-sizing: border-box; resize: vertical;" placeholder="Заметка, вопрос менеджеру..."><?= htmlspecialchars($detailOrder['comment'] ?? '') ?></textarea>
        <div class="action-panel">
            <a href="?page=orders<?= !empty($search)?"&search=".urlencode($search):'' ?><?= !empty($statusFilter)?"&status=$statusFilter":'' ?>" class="btn-back">← Назад</a>
            <button type="submit" name="save_and_exit" class="btn-save">💾 Сохранить и выйти</button>
        </div>
    </form>
</div>
</div>

<script>window.cabOrderId = <?= $detailOrder['id'] ?>;</script>
<script>
console.log('📊 Текущий статус замеров:', '<?= htmlspecialchars($detailOrder['measurements'] ?? '') ?>');
console.log('📊 Текущий статус монтажа:', '<?= htmlspecialchars($detailOrder['installation'] ?? '') ?>');
</script>