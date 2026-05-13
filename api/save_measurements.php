<?php
// /workspace/api/save_measurements.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// Проверка авторизации
$user = checkAuth(); // Убедитесь, что эта функция возвращает null, если пользователь не залогинен
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Пользователь не авторизован']);
    exit;
}

// Чтение JSON тела запроса
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Некорректные данные (JSON не распознан)']);
    exit;
}

// Обязательные поля
$required = ['order_id', 'room_name', 'carcass_type', 'mounting_type'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Заполните поле: $field"]);
        exit;
    }
}

$order_id = (int)$data['order_id'];

// Проверка: принадлежит ли заказ этому пользователю
$stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user['id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ к этому заказу запрещен']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Проверяем, есть ли уже замеры для этого заказа
    $checkStmt = $pdo->prepare("SELECT id FROM measurements WHERE order_id = ?");
    $checkStmt->execute([$order_id]);
    
    if ($checkStmt->fetch()) {
        // Обновление
        $sql = "UPDATE measurements SET 
                room_name = ?, carcass_type = ?, mounting_type = ?, 
                wall_left = ?, window_width = ?, wall_right = ?, 
                offset_left = ?, offset_right = ?, offset_wall = ?,
                drive_side = ?, has_tulle = ?, sliding_direction = ?, opening_type = ?,
                updated_at = NOW()
                WHERE order_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['room_name'], $data['carcass_type'], $data['mounting_type'],
            $data['wall_left'] ?? 0, $data['window_width'] ?? 0, $data['wall_right'] ?? 0,
            $data['offset_left'] ?? 0, $data['offset_right'] ?? 0, $data['offset_wall'] ?? 0,
            $data['drive_side'] ?? 'left', $data['has_tulle'] ?? 0, 
            $data['sliding_direction'] ?? 'left', $data['opening_type'] ?? 'standard',
            $order_id
        ]);
    } else {
        // Вставка
        $sql = "INSERT INTO measurements 
                (order_id, room_name, carcass_type, mounting_type, 
                 wall_left, window_width, wall_right, 
                 offset_left, offset_right, offset_wall,
                 drive_side, has_tulle, sliding_direction, opening_type, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $order_id,
            $data['room_name'], $data['carcass_type'], $data['mounting_type'],
            $data['wall_left'] ?? 0, $data['window_width'] ?? 0, $data['wall_right'] ?? 0,
            $data['offset_left'] ?? 0, $data['offset_right'] ?? 0, $data['offset_wall'] ?? 0,
            $data['drive_side'] ?? 'left', $data['has_tulle'] ?? 0, 
            $data['sliding_direction'] ?? 'left', $data['opening_type'] ?? 'standard'
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Замеры сохранены']);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
}
?>