<?php
// 🔌 Подключение ядра
if (session_status() === PHP_SESSION_NONE) session_start();

// Пути: поднимаемся на 2 уровня вверх, так как мы в /zakaz/
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// 🛡️ Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    // Для PWA возвращаем JSON, для браузера — редирект
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
        exit;
    }
    header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// 👤 Данные пользователя
$stmt = $pdo->prepare("SELECT id, company, email, phone, is_admin_approved FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Для обратной совместимости создаём псевдоним name
$user['name'] = $user['company'] ?? $user['contact_person'] ?? $user['email'];
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Проверка одобрения админом
if (empty($user['is_admin_approved'])) {
    require __DIR__ . '/templates/pending_approval.php';
    exit;
}

// 📦 Справочники
$statusMap = [
    'new' => '🆕 Новый', 'processing' => '⏳ В обработке', 'confirmed' => '✅ Подтверждён',
    'shipped' => '🚚 Отправлен', 'completed' => '📦 Выполнен', 'cancelled' => '❌ Отменён'
];
$measurementsMap = [
    'required' => '📐 Требуется замер', 
    'provided' => '✅ Замер предоставлен', 
    'matches' => '✔️ Соответствует'
];
$installationMap = [
    'required' => '🔧 Требуется монтаж', 
    'not_required' => '❌ Не требуется'
];

// ==========================================
// 🔄 ОБРАБОТКА ЗАПРОСОВ (JSON API)
// ==========================================
$rawInput = file_get_contents('php://input');
$isJson = !empty($rawInput) && str_starts_with(trim($rawInput), '{');

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    $req = json_decode($rawInput, true);
    
    if (!$req) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }
    
    $orderId = (int)($req['order_id'] ?? 0);
    $action = trim($req['action'] ?? '');
    
    // Проверка прав на заказ
    $chk = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
    $chk->execute([$orderId, $_SESSION['user_id']]);
    if (!$chk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
        exit;
    }
    
    // --- Сохранение замеров ---
    if ($action === 'save_measurements') {
        $data = $req['data'] ?? [];
        if (empty($data['rooms'])) {
            echo json_encode(['success' => false, 'message' => 'Нет данных']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Заголовок замеров
            $stmt = $pdo->prepare("INSERT INTO order_measurements (order_id) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
            $stmt->execute([$orderId]);
            $measureId = $pdo->lastInsertId();
            
            // Удаляем старые данные
            $pdo->prepare("DELETE FROM measurement_rooms WHERE measurement_id = ?")->execute([$measureId]);
            
            $roomSort = 0;
            foreach ($data['rooms'] as $room) {
                $pdo->prepare("INSERT INTO measurement_rooms (measurement_id, room_name, sort_order) VALUES (?, ?, ?)")
                    ->execute([$measureId, trim($room['name'] ?? 'Комната'), $roomSort++]);
                
                $roomId = $pdo->lastInsertId();
                $winSort = 0;
                
                foreach ($room['windows'] ?? [] as $w) {
                    $pdo->prepare("INSERT INTO measurement_windows (room_id, cornice_type, mounting_type, wall_left, window_width, wall_right, offset_left, offset_right, offset_wall, drive_position, drive_side, sliding_direction, opening_type, has_tulle, height, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([
                            $roomId,
                            $w['cornice_type'] ?? null,
                            $w['mounting_type'] ?? null,  // ✅ Добавлено поле
                            $w['wall_left'] ?: null,
                            $w['window_width'] ?: null,
                            $w['wall_right'] ?: null,
                            $w['offset_left'] ?: null,
                            $w['offset_right'] ?: null,
                            $w['offset_wall'] ?: null,
                            $w['drive_position'] ?: null,
                            $w['drive_side'] ?: null,
                            $w['sliding_direction'] ?: null,
                            $w['opening_type'] ?: null,
                            (int)($w['has_tulle'] ?? 0),
                            $w['height'] ?: null,
                            $winSort++
                        ]);
                }
            }
            
            // Обновляем статус заказа
            $pdo->prepare("UPDATE orders SET measurements = 'provided' WHERE id = ? AND user_id = ?")
                ->execute([$orderId, $_SESSION['user_id']]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Замеры сохранены'], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    
    // --- Проверка наличия замеров ---
    if ($action === 'check_measurements') {
        $stmt = $pdo->prepare("SELECT m.id FROM order_measurements m JOIN measurement_rooms r ON m.id = r.measurement_id WHERE m.order_id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        echo json_encode(['success' => true, 'exists' => (bool)$stmt->fetch()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ==========================================
// 📊 ЗАГРУЗКА ДАННЫХ ДЛЯ ОТОБРАЖЕНИЯ
// ==========================================
$orders = [];
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Фильтры
$where = "user_id = ?";
$params = [$_SESSION['user_id']];
if ($search) {
    $where .= " AND (id LIKE ? OR items_json LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like;
}
if ($statusFilter) {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

// Пагинация
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $where");
$stmtCount->execute($params);
$totalOrders = $stmtCount->fetchColumn();
$totalPages = max(1, ceil($totalOrders / $perPage));

// Заказов
$stmtOrd = $pdo->prepare("SELECT * FROM orders WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmtOrd->execute(array_merge($params, [$perPage, $offset]));
$orders = $stmtOrd->fetchall(PDO::FETCH_ASSOC);

// Данные для модального окна замеров
$measurementsData = null;
$viewOrderId = isset($_GET['view_order']) ? (int)$_GET['view_order'] : 0;
if ($viewOrderId && ($ordersData = array_filter($orders, fn($o) => $o['id'] == $viewOrderId))) {
    $mStmt = $pdo->prepare("SELECT m.id, r.id as room_id, r.room_name, w.* 
        FROM order_measurements m 
        LEFT JOIN measurement_rooms r ON m.id = r.measurement_id 
        LEFT JOIN measurement_windows w ON r.id = w.room_id 
        WHERE m.order_id = ? 
        ORDER BY r.sort_order ASC, w.sort_order ASC");
    $mStmt->execute([$viewOrderId]);
    $rows = $mStmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) $measurementsData = $rows;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>NL Партнёр - Заказы</title>
    
    <!-- PWA Meta -->
    <meta name="theme-color" content="#3b82f6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NL Заказы">
    <link rel="manifest" href="/zakaz/manifest.json">
    <link rel="apple-touch-icon" href="/zakaz/assets/img/icon-192.png">
    
    <!-- Стили -->
    <link rel="stylesheet" href="/zakaz/assets/css/zakaz.css">
</head>
<body>
    <!-- Шапка -->
    <header class="app-header">
        <div class="header-brand">
            <img src="/assets/img/logo-small.png" alt="NEIROLINKS" class="logo">
            <div class="header-text">
                <div class="header-title">NL Партнёр</div>
                <div class="header-subtitle">Заказы</div>
            </div>
        </div>
        <div class="header-actions">
            <button id="installBtn" class="btn-install" style="display:none;">📲 Установить</button>
            <a href="/auth/logout.php" class="btn-logout">🚪 Выход</a>
        </div>
    </header>
    
    <!-- Основной контент -->
    <main class="app-main">
        <!-- Фильтры -->
        <div class="filters-bar">
            <input type="search" id="searchInput" placeholder="Поиск по заказу..." value="<?= htmlspecialchars($search) ?>">
            <select id="statusFilter">
                <option value="">Все статусы</option>
                <?php foreach($statusMap as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <button id="applyFilters">🔍 Найти</button>
        </div>
        
        <!-- Список заказов -->
        <div class="orders-list">
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <p>📭 Заказов пока нет</p>
                    <a href="/">← На главную</a>
                </div>
            <?php else: ?>
                <?php foreach($orders as $order): ?>
                    <div class="order-card" data-order-id="<?= $order['id'] ?>">
                        <div class="order-header">
                            <span class="order-id">Заказ #<?= $order['id'] ?></span>
                            <span class="status-badge status-<?= $order['status'] ?>">
                                <?= $statusMap[$order['status']] ?? $order['status'] ?>
                            </span>
                        </div>
                        <div class="order-body">
                            <div class="order-date">📅 <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></div>
                            <div class="order-items">
                                <?php 
                                $items = json_decode($order['items_json'] ?? '[]', true);
                                $total = array_sum(array_map(fn($it) => ($it['r']??0) * ($it['q']??1), $items));
                                ?>
                                📦 <?= count($items) ?> тов. | 💰 <?= number_format($total, 0, '.', ' ') ?> ₽
                            </div>
                            <div class="order-statuses">
                                <span class="status-mini <?= $order['measurements'] === 'provided' ? 'done' : '' ?>">
                                    📐 <?= $measurementsMap[$order['measurements'] ?? ''] ?? '—' ?>
                                </span>
                                <span class="status-mini <?= $order['installation'] === 'required' ? 'active' : '' ?>">
                                    🔧 <?= $installationMap[$order['installation'] ?? ''] ?? '—' ?>
                                </span>
                            </div>
                        </div>
                        <div class="order-actions">
                            <?php if ($order['measurements'] === 'required'): ?>
                                <button class="btn-action btn-measure" onclick="openMeasurementModal(<?= $order['id'] ?>)">
                                    📐 Замер
                                </button>
                            <?php endif; ?>
                            <button class="btn-action btn-details" onclick="viewOrderDetails(<?= $order['id'] ?>)">
                                ℹ️ Подробнее
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Пагинация -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?p=<?= $p ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $statusFilter ? '&status='.$statusFilter : '' ?>" 
                       class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Модальное окно замеров -->
    <?php require __DIR__ . '/templates/modals/measurement_modal.php'; ?>
    
    <!-- Toast уведомления -->
    <div id="toastContainer"></div>
    
    <!-- Скрипты -->
    <script>
        window.cabOrderId = <?= $viewOrderId ?: 'null' ?>;
        window.currentUser = <?= json_encode($user, JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="/zakaz/assets/js/zakaz.js"></script>
    
    <!-- Регистрация Service Worker -->
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/zakaz/sw.js')
                .then(reg => {
                    console.log('✅ SW registered:', reg.scope);
                    // Проверка обновлений
                    reg.addEventListener('updatefound', () => {
                        const newWorker = reg.installing;
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                if (confirm('🔄 Доступна новая версия. Обновить?')) {
                                    newWorker.postMessage({ type: 'SKIP_WAITING' });
                                    location.reload();
                                }
                            }
                        });
                    });
                })
                .catch(err => console.log('❌ SW error:', err));
        });
        
        // Обработка сообщений от SW
        navigator.serviceWorker.addEventListener('message', event => {
            if (event.data?.type === 'OFFLINE_SAVED') {
                showToast(event.data.message, 'success');
            }
        });
    }
    
    // Кнопка установки PWA
    let deferredPrompt;
    const installBtn = document.getElementById('installBtn');
    
    window.addEventListener('beforeinstallprompt', e => {
        e.preventDefault();
        deferredPrompt = e;
        installBtn.style.display = 'inline-block';
    });
    
    installBtn.addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log('Install prompt:', outcome);
            deferredPrompt = null;
            installBtn.style.display = 'none';
        }
    });
    
    // Уведомления
    function showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // Фильтры
    document.getElementById('applyFilters')?.addEventListener('click', () => {
        const search = document.getElementById('searchInput').value;
        const status = document.getElementById('statusFilter').value;
        let url = '?';
        if (search) url += 'search=' + encodeURIComponent(search) + '&';
        if (status) url += 'status=' + status;
        location.href = url;
    });
    
    // Просмотр заказа
    function viewOrderDetails(id) {
        location.href = '?view_order=' + id;
    }
    </script>
</body>
</html>