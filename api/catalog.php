<?php
// api/catalog.php
// 🔌 Единое подключение ядра
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth_helper.php';
session_start();

// 🔐 Проверка авторизации
requireAuth(['admin', 'dealer', 'agent']);

// 👤 Получаем данные пользователя
$user = getCurrentUser($pdo);
if (!is_array($user)) {
    $user = ['company' => 'Пользователь', 'role' => 'dealer'];
}
$isAgent = ($user['role'] === 'agent');

// Заголовки
header('Content-Type: application/json; charset=utf-8');

// Получаем действие
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // 1️⃣ Получение списка категорий
        case 'categories':
            $stmt = $pdo->query("SELECT DISTINCT category FROM catalog ORDER BY category ASC");
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode($categories);
            break;

        // 2️⃣ Получение серий для категории
        case 'series':
            $category = $_GET['category'] ?? '';
            if (empty($category)) {
                echo json_encode(['error' => 'Категория не указана']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM catalog WHERE category = ? ORDER BY id ASC");
            $stmt->execute([$category]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($items as $item) {
                $result[$item['series']] = [
                    'desc' => $item['description'] ?? '',
                    'controls' => $item['controls'] ? json_decode($item['controls']) : [],
                    'colors' => $item['colors'] ? json_decode($item['colors']) : [],
                    'max_width' => $item['max_width'] ?? 12000
                ];
            }
            echo json_encode($result);
            break;

        // 3️⃣ Получение цены по миллиметрам
        case 'price':
            $category = $_GET['category'] ?? '';
            $series = $_GET['series'] ?? '';
            $mm = intval($_GET['mm'] ?? 0);

            if (empty($category) || empty($series) || $mm <= 0) {
                echo json_encode(['success' => false, 'message' => 'Некорректные параметры']);
                exit;
            }

            // Находим товар
            $stmt = $pdo->prepare("SELECT id FROM catalog WHERE category = ? AND series = ?");
            $stmt->execute([$category, $series]);
            $catalogId = $stmt->fetchColumn();

            if (!$catalogId) {
                echo json_encode(['success' => false, 'message' => 'Товар не найден']);
                exit;
            }

            // Получаем все ценовые диапазоны
            $stmt = $pdo->prepare("SELECT * FROM catalog_prices WHERE catalog_id = ? ORDER BY max_mm ASC");
            $stmt->execute([$catalogId]);
            $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($prices)) {
                echo json_encode(['success' => false, 'message' => 'Цены не найдены']);
                exit;
            }

            // Ищем подходящий диапазон
            $foundPrice = end($prices); // По умолчанию берем последний (максимальный)
            foreach ($prices as $price) {
                if ($mm <= $price['max_mm']) {
                    $foundPrice = $price;
                    break;
                }
            }

            // Формируем ответ
            $response = [
                'success' => true,
                'data' => [
                    'range_label' => $foundPrice['range_label'],
                    'max_mm' => $foundPrice['max_mm'],
                    'rrc_price' => (float)$foundPrice['rrc_price']
                ]
            ];

            // 🕵️ Для агента НЕ отдаем цену дилера
            if (!$isAgent) {
                $response['data']['dealer_price'] = (float)$foundPrice['dealer_price'];
            } else {
                $response['data']['dealer_price'] = null; // Явно скрываем
            }

            echo json_encode($response);
            break;

        // 4️⃣ Получение аксессуаров (структура + цены)
        case 'accessories':
            $stmt = $pdo->prepare("SELECT * FROM catalog WHERE category = 'Аксессуары' ORDER BY id ASC");
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($items as $item) {
                // Получаем цену для аксессуара (обычно один размер "1 шт")
                $pStmt = $pdo->prepare("SELECT * FROM catalog_prices WHERE catalog_id = ?");
                $pStmt->execute([$item['id']]);
                $prices = $pStmt->fetchAll(PDO::FETCH_ASSOC);

                $priceData = $prices[0] ?? ['dealer_price' => 0, 'rrc_price' => 0];

                $result[$item['series']] = [
                    'colors' => $item['colors'] ? json_decode($item['colors']) : [],
                    'dealer_price' => $isAgent ? null : (float)$priceData['dealer_price'], // Скрываем для агента
                    'rrc_price' => (float)$priceData['rrc_price']
                ];
            }
            echo json_encode($result);
            break;

        default:
            echo json_encode(['error' => 'Неизвестное действие', 'available_actions' => ['categories', 'series', 'price', 'accessories']]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Внутренняя ошибка сервера', 'message' => $e->getMessage()]);
}