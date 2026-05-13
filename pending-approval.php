<?php
// Файл: pending-approval.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_helper.php';

// Проверка авторизации
if (!is_logged_in()) {
    header('Location: /auth/login.php');
    exit;
}

$user = get_logged_user();

// Если уже подтвержден -> в кабинет
if (!empty($user['is_admin_approved'])) {
    header('Location: /index.php');
    exit;
}

// Если email не подтвержден -> на вход
if (empty($user['is_verified'])) {
    header('Location: /auth/login.php');
    exit;
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('resend_admin_notification') && resend_admin_notification($user)) {
        $success_msg = "✅ Уведомление администратору отправлено повторно.";
    } else {
        $error_msg = "⚠️ Ошибка отправки. Попробуйте позже.";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ожидание подтверждения | NEIROLINKS</title>
    
    <!-- 🔹 ФАВИКОНКИ -->
    <link rel="shortcut icon" href="/icon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icon-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/icon-32.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    
    <!-- 🔗 Подключение стилей (строго как в login.php) -->
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/style_auth.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="card">
            <!-- Шапка (полная копия login.php) -->
            <div class="login-header">
                <img src="/logo.png" alt="NEIROLINKS" class="login-logo" onerror="this.style.display='none'">
                <div class="login-title-block">
                    <h2>NEIROLINKS Motion</h2>
                    <p class="login-subtitle">ожидание подтверждения</p>
                </div>
            </div>

            <!-- Статус и иконка -->
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <span style="font-size: 2.5rem; display: block; margin-bottom: 0.5rem;">⏳</span>
                <h3 style="margin: 0 0 0.5rem; font-size: 1.1rem; color: var(--primary); font-weight: 600;">Ваш аккаунт ожидает подтверждения</h3>
                <p style="margin: 0; color: var(--text-light); font-size: 0.9rem;">Email подтверждён, заявка на проверке</p>
            </div>

            <hr style="border: none; border-top: 1px solid var(--border); margin: 1.5rem 0;">

            <!-- Данные заявки (стилизованы под поля ввода) -->
            <div style="margin-bottom: 1.5rem;">
                <label style="font-size: 0.8rem; color: var(--text-light); margin-bottom: 0.8rem; display: block; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">📋 Данные заявки</label>
                
                <div class="form-group" style="margin-bottom: 0.8rem;">
                    <label style="font-size: 0.9rem;">Статус партнера</label>
                    <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; font-weight: 600; color: var(--primary); display: flex; align-items: center; gap: 8px; border: 1px solid var(--border);">
                        <?= $user['role'] === 'dealer' ? '🏢' : '🤝' ?>
                        <?= htmlspecialchars($user['role'] === 'dealer' ? 'Дилер' : 'Агент') ?>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0.8rem;">
                    <label style="font-size: 0.9rem;">Компания / ФИО</label>
                    <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; font-weight: 500; color: var(--text); border: 1px solid var(--border);">
                        <?= htmlspecialchars($user['company'] ?? 'Не указано') ?>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0.8rem;">
                    <label style="font-size: 0.9rem;">Email</label>
                    <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; font-weight: 500; color: var(--text); border: 1px solid var(--border);">
                        <?= htmlspecialchars($user['email']) ?>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0.8rem;">
                    <label style="font-size: 0.9rem;">Дата регистрации</label>
                    <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; font-weight: 500; color: var(--text); border: 1px solid var(--border);">
                        <?= date('d.m.Y в H:i', strtotime($user['created_at'])) ?>
                    </div>
                </div>
            </div>

            <!-- Сообщения -->
            <?php if ($success_msg): ?>
                <div class="success" style="margin-bottom: 1rem;"><?= htmlspecialchars($success_msg) ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="error" style="margin-bottom: 1rem;"><?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <!-- Кнопка (стандартный стиль из style_auth.css) -->
            <form method="POST">
                <button type="submit">🔔 Напомнить о заявке</button>
            </form>

            <!-- Ссылки -->
            <div class="links" style="margin-top: 1.2rem;">
                <a href="/auth/logout.php">Выйти из кабинета</a>
            </div>
            <div style="text-align: center; font-size: 0.8rem; color: var(--text-light); margin-top: 0.5rem;">
                ⏱️ Обычно проверка занимает до 24 часов
            </div>
        </div>
    </div>
</body>
</html>