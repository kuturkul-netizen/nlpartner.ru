<?php
// 🔌 Подключение ядра и сессии
if (session_status() === PHP_SESSION_NONE) session_start();

// ИСПРАВЛЕНО: Поднимаемся на уровень выше (../), так как мы находимся в папке /cabinet/
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// 🛡️ Проверка доступа
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

// 🗑️ Обработка выхода
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /auth/login.php');
    exit;
}

// 👤 Загрузка данных пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { session_destroy(); header('Location: /auth/login.php'); exit; }

$is_pending_approval = ($user['is_verified'] == 1 && empty($user['is_admin_approved']));
$currentPage = isset($_GET['page']) ? trim($_GET['page']) : 'orders';

// 📦 Справочники статусов
$statusMap = [
    'new' => '🆕 Новый', 'processing' => '⏳ В обработке', 'confirmed' => '✅ Подтверждён',
    'shipped' => '🚚 Отправлен', 'completed' => '📦 Выполнен', 'cancelled' => '❌ Отменён'
];
$measurementsMap = ['required' => '📐 Требуется замер', 'provided' => '✅ Замер предоставлен', 'matches' => '✔️ Соответствует сп-ции'];
$installationMap = ['required' => '🔧 Требуется', 'not_required' => '❌ Не требуется'];

// ==========================================
// 🔄 ОБРАБОТЧИКИ ЗАПРОСОВ (JSON и POST)
// ==========================================

// 1. Сначала проверяем JSON запросы (AJAX от модальных окон)
$rawInput = file_get_contents('php://input');
$isJsonRequest = (!empty($rawInput) && str_starts_with(trim($rawInput), '{'));

if ($isJsonRequest) {
    header('Content-Type: application/json; charset=utf-8');
    $req = json_decode($rawInput, true);

    if (!$req) {
        echo json_encode(['success' => false, 'message' => 'Неверный формат JSON']);
        exit;
    }

    $orderId = (int)($req['order_id'] ?? 0);
    $action = trim($req['action'] ?? '');
    
    // Проверка прав доступа к заказу
    $chk = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
    $chk->execute([$orderId, $_SESSION['user_id']]);
    if (!$chk->fetch()) { 
        echo json_encode(['success' => false, 'message' => 'Заказ не найден или доступ запрещен']); 
        exit; 
    }

    // --- Обработка замеров (Save Measurements) ---
    if ($action === 'save_measurements') {
        $data = $req['data'] ?? [];
        if (empty($data['rooms'])) { 
            echo json_encode(['success' => false, 'message' => 'Нет данных комнат']); 
            exit; 
        }

        try {
            $pdo->beginTransaction();
            
            // 1. Заголовок замеров
            $stmt = $pdo->prepare("INSERT INTO order_measurements (order_id) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
            $stmt->execute([$orderId]);
            $measureId = $pdo->lastInsertId();

            // 2. Удаляем старые комнаты (полная перезапись)
            $pdo->prepare("DELETE FROM measurement_rooms WHERE measurement_id = ?")->execute([$measureId]);

            $roomSort = 0;
            foreach ($data['rooms'] as $room) {
                $pdo->prepare("INSERT INTO measurement_rooms (measurement_id, room_name, sort_order) VALUES (?, ?, ?)")
                    ->execute([$measureId, trim($room['name'] ?? 'Комната'), $roomSort++]);
                
                $roomId = $pdo->lastInsertId();
                $winSort = 0;
                
                foreach ($room['windows'] ?? [] as $w) {
                    $pdo->prepare("INSERT INTO measurement_windows (room_id, cornice_type, mounting_type, wall_left, window_width, wall_right, 
                    offset_left, offset_right, offset_wall, drive_position, drive_side, sliding_direction, 
                    opening_type, has_tulle, height, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([
                    $roomId, 
                    $w['cornice_type'] ?? null, 
                    $w['mounting_type'] ?? null,  // ← ДОБАВЛЕНО
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

            // 3. Обновляем статус в orders
            $pdo->prepare("UPDATE orders SET measurements = 'provided' WHERE id = ? AND user_id = ?")->execute([$orderId, $_SESSION['user_id']]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Замеры успешно сохранены'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Ошибка сохранения: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // --- Обработка проверки наличия замеров ---
    if ($action === 'check_measurements') {
        $stmt = $pdo->prepare("SELECT m.id FROM order_measurements m JOIN measurement_rooms r ON m.id = r.measurement_id WHERE m.order_id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        echo json_encode(['success' => true, 'exists' => (bool)$stmt->fetch()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // --- Обработка адресных заявок (JSON вариант) ---
    // Если фронтенд шлет JSON с action: check_address_request или submit_address_request
    if (isset($req['check_address_request']) || isset($req['submit_address_request'])) {
        
        if (isset($req['check_address_request'])) {
            $type = trim($req['type']);
            $otherType = ($type === 'measurements') ? 'installation' : 'measurements';
            
            $chkStmt = $pdo->prepare("SELECT measurements, installation FROM orders WHERE id = ? AND user_id = ?");
            $chkStmt->execute([$orderId, $_SESSION['user_id']]);
            $order = $chkStmt->fetch();
            
            if ($order) {
                $currentStatus = ($type === 'measurements') ? ($order['measurements'] ?? '') : ($order['installation'] ?? '');
                
                // Проверяем текущую заявку
                $checkStmt = $pdo->prepare("SELECT id, city, street, house, entrance, floor, apartment, contact_person, phone, comment FROM address_requests WHERE order_id = ? AND request_type = ? LIMIT 1");
                $checkStmt->execute([$orderId, $type]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                // Ищем заявку другого типа для автозаполнения
                $copyFromStmt = $pdo->prepare("SELECT city, street, house, entrance, floor, apartment, contact_person FROM address_requests WHERE order_id = ? AND request_type = ? LIMIT 1");
                $copyFromStmt->execute([$orderId, $otherType]);
                $copyFrom = $copyFromStmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true, 
                    'exists' => (bool)$existing, 
                    'data' => $existing ?: null, 
                    'currentStatus' => $currentStatus,
                    'copyFrom' => $copyFrom
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success'=>false,'message'=>'Заказ не найден'], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        if (isset($req['submit_address_request'])) {
            $type = trim($req['type']);
            $editId = (int)($req['request_id'] ?? 0);
            $fields = ['city','street','house','entrance','floor','apartment','contact_person','phone','address_comment'];
            $data = [];
            foreach($fields as $f) $data[$f] = trim($req[$f] ?? '');

            try {
                if ($editId > 0) {
                    $pdo->prepare("UPDATE address_requests SET city=?,street=?,house=?,entrance=?,floor=?,apartment=?,contact_person=?,phone=?,comment=?,status='new',updated_at=NOW() WHERE id=? AND order_id=? AND user_id=?")
                        ->execute([$data['city'],$data['street'],$data['house'],$data['entrance'],$data['floor'],$data['apartment'],$data['contact_person'],$data['phone'],$data['address_comment'],$editId,$orderId,$_SESSION['user_id']]);
                } else {
                    $existsStmt = $pdo->prepare("SELECT id FROM address_requests WHERE order_id=? AND request_type=?");
                    $existsStmt->execute([$orderId, $type]);
                    if ($existsStmt->fetch()) {
                        $pdo->prepare("UPDATE address_requests SET city=?,street=?,house=?,entrance=?,floor=?,apartment=?,contact_person=?,phone=?,comment=?,status='new',updated_at=NOW() WHERE order_id=? AND request_type=?")
                            ->execute([$data['city'],$data['street'],$data['house'],$data['entrance'],$data['floor'],$data['apartment'],$data['contact_person'],$data['phone'],$data['address_comment'],$orderId,$type]);
                    } else {
                        $pdo->prepare("INSERT INTO address_requests (order_id,user_id,request_type,city,street,house,entrance,floor,apartment,contact_person,phone,comment) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                            ->execute([$orderId,$_SESSION['user_id'],$type,$data['city'],$data['street'],$data['house'],$data['entrance'],$data['floor'],$data['apartment'],$data['contact_person'],$data['phone'],$data['address_comment']]);
                    }
                }
                $statusField = ($type === 'measurements') ? 'measurements' : 'installation';
                $pdo->prepare("UPDATE orders SET $statusField = 'required' WHERE id = ? AND user_id = ?")->execute([$orderId, $_SESSION['user_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Заявка сохранена'], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success'=>false,'message'=>'Ошибка сервера: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }
    }
    
    // Если JSON пришел, но действие не распознано
    echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
    exit;
}

// 2. Обработка обычных POST запросов (Формы)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 🔍 Проверка наличия замеров
if (isset($_POST['check_measurements_request'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $orderId = (int)$_POST['order_id'];
    
    // Проверяем, есть ли замеры в БД
    $stmt = $pdo->prepare("
        SELECT m.id 
        FROM order_measurements m 
        WHERE m.order_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$orderId]);
    $exists = (bool)$stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'exists' => $exists
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
    
    // 💾 Сохранение заказа (кнопка "Сохранить и выйти" в деталях заказа)
    if (isset($_POST['save_and_exit'])) {
        $oid = (int)$_POST['view_order_id'];
        $chkStmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
        $chkStmt->execute([$oid, $_SESSION['user_id']]);
        
        if ($chkStmt->fetch()) {
            $status = trim($_POST['order_status'] ?? '');
            $measurements = trim($_POST['order_measurements'] ?? '');
            $installation = trim($_POST['order_installation'] ?? '');
            
            $updStmt = $pdo->prepare("
                UPDATE orders SET 
                    status = ?,
                    comment = ?,
                    measurements = ?,
                    installation = ?
                WHERE id = ?
            ");
            $updStmt->execute([
                $status ?: null,
                trim($_POST['comment']),
                $measurements ?: null,
                $installation ?: null,
                $oid
            ]);
        }
        header("Location: ?page=orders");
        exit;
    }

    // Обработка адресных заявок через обычную форму (если вдруг используется не AJAX)
    if (isset($_POST['check_address_request']) || isset($_POST['submit_address_request'])) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');

        if (isset($_POST['check_address_request'])) {
            $orderId = (int)$_POST['order_id'];
            $type = trim($_POST['type']);
            $otherType = ($type === 'measurements') ? 'installation' : 'measurements';
            
            $chkStmt = $pdo->prepare("SELECT measurements, installation FROM orders WHERE id = ? AND user_id = ?");
            $chkStmt->execute([$orderId, $_SESSION['user_id']]);
            $order = $chkStmt->fetch();
            
            if ($order) {
                $currentStatus = ($type === 'measurements') ? ($order['measurements'] ?? '') : ($order['installation'] ?? '');
                
                $checkStmt = $pdo->prepare("SELECT id, city, street, house, entrance, floor, apartment, contact_person, phone, comment FROM address_requests WHERE order_id = ? AND request_type = ? LIMIT 1");
                $checkStmt->execute([$orderId, $type]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                $copyFromStmt = $pdo->prepare("SELECT city, street, house, entrance, floor, apartment, contact_person FROM address_requests WHERE order_id = ? AND request_type = ? LIMIT 1");
                $copyFromStmt->execute([$orderId, $otherType]);
                $copyFrom = $copyFromStmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true, 
                    'exists' => (bool)$existing, 
                    'data' => $existing ?: null, 
                    'currentStatus' => $currentStatus,
                    'copyFrom' => $copyFrom
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success'=>false,'message'=>'Заказ не найден'], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        if (isset($_POST['submit_address_request'])) {
            $orderId = (int)$_POST['order_id'];
            $type = trim($_POST['type']);
            $editId = (int)($_POST['request_id'] ?? 0);
            $fields = ['city','street','house','entrance','floor','apartment','contact_person','phone','address_comment'];
            $data = [];
            foreach($fields as $f) $data[$f] = trim($_POST[$f] ?? '');

            $chkStmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
            $chkStmt->execute([$orderId, $_SESSION['user_id']]);
            if (!$chkStmt->fetch()) { 
                echo json_encode(['success'=>false,'message'=>'Заказ не найден'], JSON_UNESCAPED_UNICODE); 
                exit; 
            }

            try {
                if ($editId > 0) {
                    $pdo->prepare("UPDATE address_requests SET city=?,street=?,house=?,entrance=?,floor=?,apartment=?,contact_person=?,phone=?,comment=?,status='new',updated_at=NOW() WHERE id=? AND order_id=? AND user_id=?")
                        ->execute([$data['city'],$data['street'],$data['house'],$data['entrance'],$data['floor'],$data['apartment'],$data['contact_person'],$data['phone'],$data['address_comment'],$editId,$orderId,$_SESSION['user_id']]);
                } else {
                    $existsStmt = $pdo->prepare("SELECT id FROM address_requests WHERE order_id=? AND request_type=?");
                    $existsStmt->execute([$orderId, $type]);
                    if ($existsStmt->fetch()) {
                        $pdo->prepare("UPDATE address_requests SET city=?,street=?,house=?,entrance=?,floor=?,apartment=?,contact_person=?,phone=?,comment=?,status='new',updated_at=NOW() WHERE order_id=? AND request_type=?")
                            ->execute([$data['city'],$data['street'],$data['house'],$data['entrance'],$data['floor'],$data['apartment'],$data['contact_person'],$data['phone'],$data['address_comment'],$orderId,$type]);
                    } else {
                        $pdo->prepare("INSERT INTO address_requests (order_id,user_id,request_type,city,street,house,entrance,floor,apartment,contact_person,phone,comment) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                            ->execute([$orderId,$_SESSION['user_id'],$type,$data['city'],$data['street'],$data['house'],$data['entrance'],$data['floor'],$data['apartment'],$data['contact_person'],$data['phone'],$data['address_comment']]);
                    }
                }
                $statusField = ($type === 'measurements') ? 'measurements' : 'installation';
                $pdo->prepare("UPDATE orders SET $statusField = 'required' WHERE id = ? AND user_id = ?")->execute([$orderId, $_SESSION['user_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Заявка сохранена'], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success'=>false,'message'=>'Ошибка сервера: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }
    }
}

// ==========================================
// 📊 ЗАГРУЗКА ДАННЫХ ДЛЯ ОТОБРАЖЕНИЯ
// ==========================================
$viewOrderId = isset($_GET['view_order']) ? (int)$_GET['view_order'] : 0;
$detailOrder = null;
$addressRequests = [];

if ($viewOrderId > 0 && $currentPage === 'orders') {
    $dStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $dStmt->execute([$viewOrderId, $_SESSION['user_id']]);
    $detailOrder = $dStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($detailOrder) {
        $reqStmt = $pdo->prepare("SELECT * FROM address_requests WHERE order_id = ? ORDER BY created_at DESC");
        $reqStmt->execute([$viewOrderId]);
        $addressRequests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

        // 👇 НАЧАЛО: Загрузка замеров
        $measurementsData = null;
        if (($detailOrder['measurements'] ?? '') === 'provided') {
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
        // 👆 КОНЕЦ: Загрузка замеров

    } else { 
        $viewOrderId = 0; 
    }
}

$orders = [];
$totalPages = 1;
$currentPageNum = 1;
$search = '';
$statusFilter = '';

if ($currentPage === 'orders' && !$viewOrderId) {
    $currentPageNum = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
    $perPage = 10;
    $offset = ($currentPageNum - 1) * $perPage;
    $search = trim($_GET['search'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');

    $where = "user_id = ?";
    $params = [$_SESSION['user_id']];
    if ($search !== '') {
        $where .= " AND (id LIKE ? OR created_at LIKE ? OR items_json LIKE ?)";
        $like = "%$search%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($statusFilter !== '') {
        $where .= " AND status = ?";
        $params[] = $statusFilter;
    }

    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $where");
    $stmtCount->execute($params);
    $totalOrders = $stmtCount->fetchColumn();
    $totalPages = max(1, ceil($totalOrders / $perPage));

    $stmtOrd = $pdo->prepare("SELECT * FROM orders WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmtOrd->execute(array_merge($params, [$perPage, $offset]));
    $orders = $stmtOrd->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = ($currentPage === 'orders' ? 'Заказы' : ($currentPage === 'contacts' ? 'Контакты' : 'Личный кабинет'));
?>
<?php require __DIR__ . '/templates/cab_header.php'; ?>

<div class="main-grid">
<?php if ($is_pending_approval): ?>
    <div class="pending-overlay">
        <div class="pending-box">
            <h2>⏳ Ожидает подтверждения</h2>
            <p>Ваш email подтверждён, но доступ будет открыт после проверки администратором.</p>
            <a href="/auth/logout.php" class="btn btn-outline">🚪 Выйти</a>
        </div>
    </div>
<?php else: ?>
    <?php if ($currentPage === 'contacts'): ?>
        <div class="card card-full">
            <div class="card-header">📞 Контакты и поддержка</div>
            <div class="contacts-grid">
                <div class="contact-card"><h3>🏢 Офис компании</h3><div class="contact-item"><strong>Адрес:</strong> г. Екатеринбург, ул. Кондратьева 2а/2</div><div class="contact-item"><strong>Телефон:</strong> +7 (343) 382-92-43</div></div>
                <div class="contact-card"><h3>👥 Отдел продаж</h3><div class="contact-item"><strong>Телефон:</strong> +7 (343) 382-92-43 (доб. 101)</div><div class="contact-item"><strong>Email:</strong> sales@neirolinks.ru</div></div>
                <div class="contact-card"><h3>🔧 Тех. поддержка</h3><div class="contact-item"><strong>Телефон:</strong> +7 (343) 382-92-43 (доб. 102)</div><div class="contact-item"><strong>Email:</strong> support@neirolinks.ru</div></div>
            </div>
        </div>
    <?php elseif ($viewOrderId && $detailOrder): ?>
        <?php require __DIR__ . '/templates/order_detail.php'; ?>
    <?php else: ?>
        <?php require __DIR__ . '/templates/orders_list.php'; ?>
    <?php endif; ?>
<?php endif; ?>
</div>

<?php if (!$is_pending_approval && $currentPage !== 'contacts'): ?>
    <?php require __DIR__ . '/templates/modals/address_request.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/templates/modals/measurement_modal.php'; ?>
<?php require __DIR__ . '/templates/cab_footer.php'; ?>