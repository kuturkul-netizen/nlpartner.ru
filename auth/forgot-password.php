<?php
// Файл: auth/forgot-password.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

$message = '';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            // Проверяем, существует ли пользователь
            $stmt = $pdo->prepare("SELECT id, email, is_verified FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['is_verified']) {
                // Генерируем токен сброса
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Сохраняем токен в БД
                $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                $stmt->execute([$token, $expires, $user['id']]);
                
                // Формируем ссылку
                $host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $resetLink = $protocol . '://' . $host . '/auth/reset-password.php?token=' . urlencode($token);
                
                // Отправляем письмо
                $subject = '=?UTF-8?B?' . base64_encode('Сброс пароля - NEIROLINKS') . '?=';
                $mailDomain = preg_replace('/:\d+$/', '', $host);
                $message_body = "Здравствуйте!\n\n"
                              . "Вы запросили сброс пароля для аккаунта, привязанного к этому email.\n\n"
                              . "Для создания нового пароля перейдите по ссылке:\n"
                              . $resetLink . "\n\n"
                              . "Ссылка действительна 1 час.\n\n"
                              . "Если вы не запрашивали сброс пароля — просто проигнорируйте это письмо.\n\n"
                              . "С уважением,\nКоманда NEIROLINKS";
                
                $headers = "From: NEIROLINKS <noreply@{$mailDomain}>\r\n"
                         . "Reply-To: support@{$mailDomain}\r\n"
                         . "MIME-Version: 1.0\r\n"
                         . "Content-Type: text/plain; charset=UTF-8\r\n";
                
                @mail($email, $subject, $message_body, $headers);
                
                $message = "✅ Если аккаунт с таким email существует и подтверждён, инструкция отправлена на почту.";
                $email = '';
            } else {
                // Не раскрываем, существует ли аккаунт (защита от перебора)
                $message = "✅ Если аккаунт с таким email существует и подтверждён, инструкция отправлена на почту.";
                $email = '';
            }
        } catch (PDOException $e) {
            error_log("Forgot password error: " . $e->getMessage());
            $error = "⚠️ Ошибка системы. Попробуйте позже.";
        }
    } else {
        $error = "⚠️ Введите корректный email";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Восстановление пароля | NEIROLINKS</title>
    
    <!-- 🔹 ФАВИКОНКИ -->
    <link rel="shortcut icon" href="/icon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icon-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/icon-32.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    
    <!-- 🔗 Подключение внешних стилей -->
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/style_auth.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="card">
            <!-- Шапка формы -->
            <div class="login-header">
                <img src="/logo.png" alt="NEIROLINKS" class="login-logo" onerror="this.style.display='none'">
                <div class="login-title-block">
                    <h2>NEIROLINKS Motion</h2>
                    <p class="login-subtitle">восстановление пароля</p>
                </div>
            </div>

            <!-- Сообщения -->
            <?php if ($error): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="success"><?= $message ?></div>
            <?php endif; ?>

            <?php if (!$message): ?>
            <!-- Форма запроса сброса -->
            <form method="POST" id="forgotForm">
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" name="email" id="email" required placeholder="name@company.ru" value="<?= htmlspecialchars($email) ?>" autocomplete="email">
                    <div class="error-hint">⚠️ Введите email, привязанный к аккаунту</div>
                </div>

                <button type="submit">Отправить инструкцию</button>
            </form>
            <?php endif; ?>

            <!-- Ссылки -->
            <div class="links">
                <a href="/auth/login.php">← Вернуться ко входу</a><br>
                <a href="/auth/register.php">Нет аккаунта? Зарегистрироваться</a>
            </div>
        </div>
    </div>

    <script>
        // Простая валидация email на клиенте
        document.getElementById('forgotForm')?.addEventListener('submit', function(e) {
            const email = document.getElementById('email');
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            
            if (!emailRegex.test(email.value.trim())) {
                e.preventDefault();
                email.closest('.form-group').classList.add('error');
            }
        });
    </script>
</body>
</html>