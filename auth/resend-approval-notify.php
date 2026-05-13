<?php
// Файл: auth/resend-approval-notify.php

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$user = get_current_user_data(); // ИСПРАВЛЕНО

if (!$user) {
    header('Location: login.php');
    exit;
}

// Если уже одобрен, redirect на главную
if ($user['is_admin_approved']) {
    header('Location: /index.php');
    exit;
}

// Если даже email не подтвержден, redirect на логин
if (!$user['is_verified']) {
    header('Location: login.php');
    exit;
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (resend_admin_notification($user)) {
        $success_msg = "Уведомление администратору отправлено повторно. Пожалуйста, подождите.";
    } else {
        $error_msg = "Ошибка отправки. Попробуйте позже или свяжитесь с поддержкой.";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Статус заявки | NEIROLINKS</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .status-card { max-width: 600px; margin: 40px auto; padding: 30px; border: 1px solid #ddd; border-radius: 8px; text-align: center; background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-resend { background: #ffc107; color: #000; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; font-weight: bold; font-size: 1rem; }
        .btn-resend:hover { background: #e0a800; }
        .btn-logout { background: #6c757d; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;}
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        h2 { color: #333; }
        p { color: #555; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="status-card">
            <h2>⏳ Ожидание подтверждения администратора</h2>
            <p>Ваш аккаунт (<strong><?= htmlspecialchars($user['email']) ?></strong>) успешно создан и подтвержден по email.</p>
            <p>В настоящее время ваша заявка на роль <strong><?= htmlspecialchars($user['role']) ?></strong> находится на проверке у менеджера.</p>
            
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
            
            <p style="font-size: 0.9em; color: #666;">Если прошло более 24 часов, вы можете отправить повторное уведомление.</p>
            
            <form method="POST">
                <button type="submit" class="btn-resend">🔔 Напомнить администратору</button>
            </form>
            
            <br>
            <a href="logout.php" class="btn-logout">Выйти</a>
        </div>
    </div>
</body>
</html>