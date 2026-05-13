<?php
// Файл: /api/save_order.php
header('Content-Type: application/json');

// 🔧 Сначала обрабатываем CORS и OPTIONS — ДО всех проверок
$allowedOrigin = getenv('SITE_URL') ?: 'http://localhost';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: {$allowedOrigin}");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    exit(0);
}

// Для продакшена раскомментируйте проверку origin:
// if ($origin !== $allowedOrigin) {
//     http_response_code(403);
//     echo json_encode(['error' => 'Forbidden origin']);
//     exit;
// }
// Для разработки разрешаем все:
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// 🔧 Только ПОСЛЕ CORS проверяем метод
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No items provided']);
    exit;
}

try {
    $pdo->beginTransaction();

    $userId = $_SESSION['user_id'];
    $itemsJson = json_encode($input['items']);
    
    // 🔧 camelCase как отправляет фронтенд
    $totalDealer = floatval($input['totalDealer'] ?? 0);
    $totalClient = floatval($input['totalClient'] ?? 0);
    $margin = floatval($input['totalMargin'] ?? 0);

    $stmt = $pdo->prepare("INSERT INTO orders (user_id, items_json, total_dealer, total_client, margin, status) VALUES (?, ?, ?, ?, ?, 'new')");
    $stmt->execute([$userId, $itemsJson, $totalDealer, $totalClient, $margin]);

    $orderId = $pdo->lastInsertId();
    $pdo->commit();

    echo json_encode(['success' => true, 'order_id' => $orderId]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Save order error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save order', 'debug' => $e->getMessage()]);
}