<?php
// Файл: auth/login.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// Если уже залогинен
if (is_logged_in()) {
    $user = get_current_user_data();
    if ($user && !empty($user['is_admin_approved'])) {
        header('Location: /index.php');
    } else {
        header('Location: /pending-approval.php');
    }
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                if (!$user['is_verified']) {
                    $error = "📧 Подтвердите ваш email адрес. Проверьте почту.";
                } elseif (empty($user['is_admin_approved'])) {
                    // Вход разрешен, но редирект на страницу ожидания
                    $_SESSION['user_id'] = $user['id'];
                    header('Location: /pending-approval.php');
                    exit;
                } else {
                    // ✅ Полный доступ + БЕЗОПАСНЫЙ редирект
                    $_SESSION['user_id'] = $user['id'];
                    
                    // Получаем и декодируем редирект (только 1 раз!)
                    $redirect = $_GET['redirect'] ?? '';
                    if ($redirect) {
                        $redirect = urldecode($redirect);
                        
                        // 🔐 Запрещаем редирект обратно на auth/ или login.php
                        if (strpos($redirect, 'login.php') !== false || 
                            strpos($redirect, '/auth/') !== false ||
                            strpos($redirect, 'register.php') !== false) {
                            $redirect = '/index.php';
                        }
                        // 🔐 Разрешаем только относительные пути или наш домен
                        elseif (strpos($redirect, 'http') === 0) {
                            $parsed = parse_url($redirect);
                            $host = $parsed['host'] ?? '';
                            if (!in_array($host, ['zakaz.nlpartner.ru', 'nlpartner.ru', 'www.nlpartner.ru', 'localhost'])) {
                                $redirect = '/index.php';
                            }
                        }
                    } else {
                        $redirect = '/index.php';
                    }
                    
                    header('Location: ' . $redirect);
                    exit;
                }
            } else {
                $error = "❌ Неверный логин или пароль";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "⚠️ Ошибка системы. Попробуйте позже.";
        }
    } else {
        $error = "⚠️ Заполните все поля";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход | NEIROLINKS</title>
    
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
                    <p class="login-subtitle">вход в систему</p>
                </div>
            </div>

            <!-- Сообщение об ошибке -->
            <?php if ($error): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>

            <!-- Форма входа -->
            <form method="POST" id="loginForm" novalidate>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" name="email" id="email" required placeholder="name@company.ru" value="<?= htmlspecialchars($email) ?>" autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="password">Пароль *</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" required placeholder="••••••••" autocomplete="current-password">
                        <span class="toggle-password" onclick="togglePassword(this)">👁️</span>
                    </div>
                </div>

                <button type="submit">Войти</button>
            </form>

            <!-- Ссылки -->
            <div class="links">
                   Нет аккаунта? <a href="/auth/register.php">Зарегистрироваться</a><br>
                   <a href="/auth/forgot-password.php">Забыли пароль?</a>
            </div>
        </div>
    </div>

    <!-- Скрипт для показа/скрытия пароля -->
    <script>
        function togglePassword(btn) {
            const input = btn.previousElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
            } else {
                input.type = 'password';
                btn.textContent = '👁️';
            }
        }

        // Валидация на клиенте (опционально)
        document.getElementById('loginForm')?.addEventListener('submit', function(e) {
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            
            if (!emailRegex.test(email.value.trim())) {
                e.preventDefault();
                email.closest('.form-group').classList.add('error');
            }
            if (password.value.length < 6) {
                e.preventDefault();
                password.closest('.form-group').classList.add('error');
            }
        });
    </script>
</body>
</html>