<?php
/**
 * API: Создание задач для монтажников
 * File: /api/create_request.php
 * Важно: НИКАКИХ пробелов/переносов перед <?php и после ?>
 */

// 🔌 Отключаем вывод ошибок в браузер (только в лог)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// 🔌 Подключение ядра
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔧 Проверка пути к config
$configPath = __DIR__ . '/../config/db.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Config not found']);
    exit;
}
require_once $configPath;

// Заголовки для JSON API (ДО любого вывода!)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🔐 Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit;
}

// Чтение JSON из тела запроса
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Empty data']);
    exit;
}

$requestId = (int)($data['request_id'] ?? 0);
$type = $data['type'] ?? '';
$mode = $data['mode'] ?? '';

$userId = $_SESSION['user_id'];

// Валидация
if ($requestId <= 0 || !in_array($type, ['measurement', 'installation']) || !in_array($mode, ['to_system', 'to_dealer', 'to_client'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Получаем заявку
$stmt = $pdo->prepare("SELECT ar.*, o.user_id as order_user_id, o.id as order_id, o.dealer_id FROM address_requests ar JOIN orders o ON ar.order_id = o.id WHERE ar.id = ?");
$stmt->execute([$requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Request not found']);
    exit;
}

// Проверка прав
if ($request['order_user_id'] != $userId && $request['dealer_id'] != $userId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$orderId = $request['order_id'];
$dealerId = $request['dealer_id'] ?? $userId;

try {
    $pdo->beginTransaction();
    
    if ($mode === 'to_system' || $mode === 'to_dealer') {
        $requestType = ($mode === 'to_system') ? 'to_system' : 'to_dealer_installers';
        
        $stmt = $pdo->prepare("INSERT INTO work_requests (order_id, dealer_id, created_by_user_id, type, request_type, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$orderId, $dealerId, $userId, $type, $requestType]);
        
        $message = ($mode === 'to_system') ? 'Заявка передана в общую очередь' : 'Заявка передана вашим исполнителям';
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => $message]);
        
    } elseif ($mode === 'to_client') {
        if ($type !== 'measurement') {
            throw new Exception('Link only for measurements');
        }
        
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $stmt = $pdo->prepare("INSERT INTO work_requests (order_id, dealer_id, created_by_user_id, type, request_type, status, public_token, token_expires_at) VALUES (?, ?, ?, ?, 'to_client', 'pending', ?, ?)");
        $stmt->execute([$orderId, $dealerId, $userId, $type, $token, $expires]);
        
        $publicLink = "https://zakaz.nlpartner.ru/public/measure.php?token=" . $token;
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Link created', 'public_link' => $publicLink]);
        
    } else {
        throw new Exception('Unknown mode');
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
// ⚠️ ВАЖНО: Никакого кода после этой строки! Никаких пробелов!