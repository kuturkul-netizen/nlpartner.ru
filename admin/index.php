<?php
// Файл: admin/index.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

require_admin();
$user = get_logged_user();

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 1 AND is_admin_approved = 0");
    $pending_count = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'dealer' AND is_admin_approved = 1");
    $dealers_count = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'agent' AND is_admin_approved = 1");
    $agents_count = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
    $total_users = 0;
    $pending_count = 0;
    $dealers_count = 0;
    $agents_count = 0;
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="admin">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEIROLINKS Admin</title>
    
    <!-- ФАВИКОНКИ -->
    <link rel="shortcut icon" href="/icon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icon-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/icon-32.png">
    
    <!--  ВАЖНО: Подключаем стили ПОСЛЕ наших кастомных стилей -->
    <!-- Или вообще убираем их, если они мешают -->
    <style>
        /* ========================================
           ИЗОЛИРОВАННЫЕ СТИЛИ ДЛЯ АДМИНКИ
           Используем [data-theme="admin"] для изоляции
           ======================================== */
        
        [data-theme="admin"] body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa !important;
            margin: 0 !important;
            padding: 0 !important;
            min-height: 100vh;
        }
        
        /* Сброс всех внешних отступов */
        [data-theme="admin"] * {
            box-sizing: border-box;
        }
        
        /* Шапка на всю ширину */
        [data-theme="admin"] .admin-header {
            background: #0f172a;
            color: #fff;
            padding: 0 30px;
            height: 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
            left: 0;
            right: 0;
        }
        
        [data-theme="admin"] .admin-header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        [data-theme="admin"] .admin-logo {
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        [data-theme="admin"] .admin-logo::before {
            content: '️';
            font-size: 1.3rem;
        }
        
        [data-theme="admin"] .admin-user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            opacity: 0.9;
            white-space: nowrap;
        }
        
        [data-theme="admin"] .admin-nav {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        [data-theme="admin"] .admin-nav a {
            color: #fff;
            text-decoration: none;
            font-size: 0.85rem;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        
        [data-theme="admin"] .admin-nav a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        /* Контейнер контента */
        [data-theme="admin"] .admin-container {
            max-width: 1200px;
            margin: 30px auto !important;
            padding: 0 20px !important;
            width: 100%;
        }
        
        /* Уведомление */
        [data-theme="admin"] .alert-banner {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        [data-theme="admin"] .alert-banner-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        [data-theme="admin"] .alert-banner-icon {
            font-size: 1.3rem;
        }
        
        [data-theme="admin"] .alert-banner-text {
            color: #1e40af;
            font-size: 0.95rem;
        }
        
        [data-theme="admin"] .alert-banner-text strong {
            font-weight: 600;
        }
        
        [data-theme="admin"] .btn-check {
            background: #f59e0b;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        
        [data-theme="admin"] .btn-check:hover {
            background: #d97706;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }
        
        /* Сетка карточек */
        [data-theme="admin"] .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        [data-theme="admin"] .stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border-left: 4px solid;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        [data-theme="admin"] .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }
        
        [data-theme="admin"] .stat-card.warning {
            border-left-color: #f59e0b;
        }
        
        [data-theme="admin"] .stat-card.default {
            border-left-color: #0f172a;
        }
        
        [data-theme="admin"] .stat-card.success {
            border-left-color: #10b981;
        }
        
        [data-theme="admin"] .stat-card.info {
            border-left-color: #3b82f6;
        }
        
        [data-theme="admin"] .stat-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        [data-theme="admin"] .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #0f172a;
            margin: 8px 0;
            line-height: 1;
        }
        
        [data-theme="admin"] .stat-card.warning .stat-number { color: #f59e0b; }
        [data-theme="admin"] .stat-card.success .stat-number { color: #10b981; }
        [data-theme="admin"] .stat-card.info .stat-number { color: #3b82f6; }
        
        [data-theme="admin"] .stat-desc {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 15px;
        }
        
        [data-theme="admin"] .stat-card .btn {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 0.9rem;
        }
        
        [data-theme="admin"] .btn-warning {
            background: #f59e0b;
            color: #fff;
        }
        
        [data-theme="admin"] .btn-warning:hover {
            background: #d97706;
        }
        
        /* Быстрый доступ */
        [data-theme="admin"] .quick-access {
            background: #fff;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        [data-theme="admin"] .quick-access h2 {
            margin: 0 0 15px 0;
            color: #0f172a;
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        [data-theme="admin"] .quick-access-desc {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        [data-theme="admin"] .quick-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        [data-theme="admin"] .quick-btn {
            padding: 14px 20px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 0.95rem;
        }
        
        [data-theme="admin"] .quick-btn-primary {
            background: #0f172a;
            color: #fff;
        }
        
        [data-theme="admin"] .quick-btn-primary:hover {
            background: #1e293b;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.3);
        }
        
        [data-theme="admin"] .quick-btn-secondary {
            background: #e2e8f0;
            color: #64748b;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        /* Адаптивность */
        @media (max-width: 768px) {
            [data-theme="admin"] .stats-grid {
                grid-template-columns: 1fr;
            }
            
            [data-theme="admin"] .admin-header {
                padding: 0 15px;
                height: auto;
                flex-direction: column;
                gap: 10px;
                padding: 15px;
            }
            
            [data-theme="admin"] .admin-header-left,
            [data-theme="admin"] .admin-nav {
                width: 100%;
                justify-content: center;
            }
            
            [data-theme="admin"] .admin-nav {
                flex-wrap: wrap;
                gap: 5px;
            }
            
            [data-theme="admin"] .admin-nav a {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
            
            [data-theme="admin"] .alert-banner {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            [data-theme="admin"] .btn-check {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- ✅ Шапка на всю ширину -->
    <header class="admin-header">
        <div class="admin-header-left">
            <div class="admin-logo">NEIROLINKS Admin</div>
            <div class="admin-user-info"><?= htmlspecialchars($user['company'] ?? 'Admin') ?></div>
        </div>
        <nav class="admin-nav">
            <a href="/index.php">🏠 На сайт</a>
            <a href="/auth/logout.php">🚪 Выйти</a>
        </nav>
    </header>

    <!-- ✅ Контент по центру -->
    <div class="admin-container">
        
        <!-- Уведомление -->
        <?php if ($pending_count > 0): ?>
            <div class="alert-banner">
                <div class="alert-banner-content">
                    <span class="alert-banner-icon">⚠️</span>
                    <div class="alert-banner-text">
                        <strong>Внимание!</strong> У вас есть <strong><?= $pending_count ?></strong> новых заявок на подтверждение
                    </div>
                </div>
                <a href="pending-users.php" class="btn-check">Проверить сейчас</a>
            </div>
        <?php endif; ?>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card warning">
                <div class="stat-label">ОЖИДАЮТ ПОДТВЕРЖДЕНИЯ</div>
                <div class="stat-number"><?= $pending_count ?></div>
                <div class="stat-desc">Партнеры с подтвержденным email</div>
                <a href="pending-users.php" class="btn btn-warning">Управление заявками</a>
            </div>

            <div class="stat-card default">
                <div class="stat-label">ВСЕГО ПАРТНЕРОВ</div>
                <div class="stat-number"><?= $total_users ?></div>
                <div class="stat-desc">Зарегистрировано в системе</div>
            </div>

            <div class="stat-card success">
                <div class="stat-label">АКТИВНЫЕ ДИЛЕРЫ</div>
                <div class="stat-number"><?= $dealers_count ?></div>
                <div class="stat-desc">Подтверждено админом</div>
            </div>

            <div class="stat-card info">
                <div class="stat-label">АКТИВНЫЕ АГЕНТЫ</div>
                <div class="stat-number"><?= $agents_count ?></div>
                <div class="stat-desc">Подтверждено админом</div>
            </div>
        </div>

        <!-- Быстрый доступ -->
        <div class="quick-access">
            <h2>Быстрый доступ</h2>
            <p class="quick-access-desc">Выберите раздел для работы с пользователями и настройками.</p>
            <div class="quick-buttons">
                <a href="pending-users.php" class="quick-btn quick-btn-primary">
                    📋 Заявки на подтверждение
                </a>
                <button class="quick-btn quick-btn-secondary" disabled>
                    👥 Все пользователи
                </button>
            </div>
        </div>

    </div>

</body>
</html>