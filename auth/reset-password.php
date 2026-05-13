<?php
// Файл: auth/reset-password.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$valid_token = false;

// Проверяем токен
if ($token) {
    try {
        $stmt = $pdo->prepare("SELECT id, reset_token_expires FROM users WHERE reset_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && strtotime($user['reset_token_expires']) > time()) {
            $valid_token = true;
        } else {
            $error = "❌ Ссылка недействительна или истекла";
        }
    } catch (PDOException $e) {
        error_log("Reset token check error: " . $e->getMessage());
        $error = "⚠️ Ошибка системы";
    }
} else {
    $error = "❌ Токен не указан";
}

// Обработка смены пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (strlen($password) < 6) {
        $error = "❌ Пароль должен содержать минимум 6 символов";
    } elseif ($password !== $confirm) {
        $error = "❌ Пароли не совпадают";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE reset_token = ?");
            $stmt->execute([$hash, $token]);
            
            $success = "✅ Пароль успешно изменён! <a href='/auth/login.php'>Войти</a>";
            $valid_token = false;
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = "⚠️ Ошибка при смене пароля";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новый пароль | NEIROLINKS</title>
    
    <!-- 🔹 ФАВИКОНКИ -->
    <link rel="shortcut icon" href="/icon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icon-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/icon-32.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/style_auth.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="card">
            <div class="login-header">
                <img src="/logo.png" alt="NEIROLINKS" class="login-logo" onerror="this.style.display='none'">
                <div class="login-title-block">
                    <h2>NEIROLINKS Motion</h2>
                    <p class="login-subtitle">новый пароль</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?= $success ?></div>
            <?php elseif ($valid_token): ?>
                <form method="POST" id="resetForm">
                    <div class="form-group">
                        <label>Новый пароль *</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" required placeholder="••••••••" minlength="6">
                            <span class="toggle-password" onclick="togglePassword(this)">👁️</span>
                        </div>
                        <div class="error-hint">⚠️ Минимум 6 символов</div>
                    </div>
                    <div class="form-group">
                        <label>Подтвердите пароль *</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password" required placeholder="••••••••">
                            <span class="toggle-password" onclick="togglePassword(this)">👁️</span>
                        </div>
                        <div class="error-hint">⚠️ Пароли должны совпадать</div>
                    </div>
                    <button type="submit">Сохранить новый пароль</button>
                </form>
            <?php endif; ?>

            <div class="links">
                <a href="/auth/login.php">← Вернуться ко входу</a>
            </div>
        </div>
    </div>

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
    </script>
</body>
</html>