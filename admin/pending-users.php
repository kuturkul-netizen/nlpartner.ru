<?php
// Файл: admin/pending-users.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// Строгая проверка прав администратора
require_admin();

$admin = get_logged_user();

// Получение списка заявок (is_verified=1, is_admin_approved=0)
$pending_users = [];
try {
    $stmt = $pdo->query("SELECT * FROM users WHERE is_verified = 1 AND is_admin_approved = 0 ORDER BY created_at DESC");
    $pending_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Pending Users Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="admin">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки | NEIROLINKS Admin</title>
    
    <!-- ФАВИКОНКИ -->
    <link rel="shortcut icon" href="/icon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icon-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/icon-32.png">
    
    <style>
        /* ========================================
           ИЗОЛИРОВАННЫЕ СТИЛИ ДЛЯ АДМИНКИ
           ======================================== */
        
        [data-theme="admin"] body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa !important;
            margin: 0 !important;
            padding: 0 !important;
            min-height: 100vh;
        }
        
        [data-theme="admin"] * {
            box-sizing: border-box;
        }
        
        /* Шапка */
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
        }
        
        [data-theme="admin"] .admin-logo::before {
            content: '🛡️';
        }
        
        [data-theme="admin"] .admin-user-info {
            font-size: 0.9rem;
            opacity: 0.9;
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
            white-space: nowrap;
        }
        
        [data-theme="admin"] .admin-nav a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        /* Контейнер */
        [data-theme="admin"] .admin-container {
            max-width: 1200px;
            margin: 30px auto !important;
            padding: 0 20px !important;
        }
        
        /* Карточка */
        [data-theme="admin"] .card {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        /* Заголовок секции */
        [data-theme="admin"] .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        [data-theme="admin"] .section-title {
            margin: 0;
            color: #0f172a;
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        [data-theme="admin"] .section-count {
            background: #eff6ff;
            color: #1e40af;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        /* Таблица */
        [data-theme="admin"] .table-wrapper {
            overflow-x: auto;
            margin: 0 -25px;
            padding: 0 25px;
        }
        
        [data-theme="admin"] table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        [data-theme="admin"] th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 12px 15px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }
        
        [data-theme="admin"] td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        
        [data-theme="admin"] tr:hover {
            background: #f8fafc;
        }
        
        [data-theme="admin"] tr:last-child td {
            border-bottom: none;
        }
        
        /* Бейджи ролей */
        [data-theme="admin"] .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        [data-theme="admin"] .badge-dealer {
            background: #fef3c7;
            color: #92400e;
        }
        
        [data-theme="admin"] .badge-agent {
            background: #dcfce7;
            color: #166534;
        }
        
        /* Кнопки */
        [data-theme="admin"] .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 14px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        [data-theme="admin"] .btn-success {
            background: #10b981;
            color: #fff;
        }
        
        [data-theme="admin"] .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        [data-theme="admin"] .btn-danger {
            background: #ef4444;
            color: #fff;
        }
        
        [data-theme="admin"] .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        [data-theme="admin"] .btn-sm {
            padding: 5px 12px;
            font-size: 0.8rem;
        }
        
        [data-theme="admin"] .actions-cell {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        /* Пустое состояние */
        [data-theme="admin"] .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #64748b;
        }
        
        [data-theme="admin"] .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        [data-theme="admin"] .empty-state h3 {
            margin: 0 0 10px 0;
            color: #0f172a;
            font-size: 1.2rem;
        }
        
        [data-theme="admin"] .empty-state p {
            margin: 0 0 20px 0;
            font-size: 0.95rem;
        }
        
        /* Модальное окно */
        [data-theme="admin"] .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
        }
        
        [data-theme="admin"] .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 480px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        [data-theme="admin"] .modal-close {
            color: #94a3b8;
            float: right;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            transition: color 0.2s;
        }
        
        [data-theme="admin"] .modal-close:hover {
            color: #64748b;
        }
        
        [data-theme="admin"] .modal-title {
            margin: 0 0 10px 0;
            color: #0f172a;
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        [data-theme="admin"] .modal-subtitle {
            color: #64748b;
            font-size: 0.9rem;
            margin: 0 0 20px 0;
        }
        
        [data-theme="admin"] .modal-subtitle strong {
            color: #0f172a;
        }
        
        [data-theme="admin"] .form-group {
            margin-bottom: 20px;
        }
        
        [data-theme="admin"] .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
            font-size: 0.9rem;
        }
        
        [data-theme="admin"] .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.95rem;
            resize: vertical;
            min-height: 100px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        [data-theme="admin"] .form-group textarea:focus {
            outline: none;
            border-color: #0f172a;
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.1);
        }
        
        [data-theme="admin"] .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }
        
        [data-theme="admin"] .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }
        
        [data-theme="admin"] .btn-secondary:hover {
            background: #cbd5e1;
        }
        
        /* Адаптивность */
        @media (max-width: 768px) {
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
            
            [data-theme="admin"] .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            [data-theme="admin"] .actions-cell {
                flex-direction: column;
            }
            
            [data-theme="admin"] .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <!-- Шапка -->
    <header class="admin-header">
        <div class="admin-header-left">
            <div class="admin-logo">NEIROLINKS Admin</div>
            <span class="admin-user-info"><?= htmlspecialchars($admin['company'] ?? $admin['email']) ?></span>
        </div>
        <nav class="admin-nav">
            <a href="/admin/index.php">📊 Дашборд</a>
            <a href="/index.php">🏠 На сайт</a>
            <a href="/auth/logout.php">🚪 Выйти</a>
        </nav>
    </header>

    <div class="admin-container">
        <div class="card">
            
            <!-- Заголовок секции -->
            <div class="section-header">
                <h2 class="section-title">📋 Заявки на подтверждение</h2>
                <span class="section-count"><?= count($pending_users) ?> заявок</span>
            </div>
            
            <?php if (count($pending_users) > 0): ?>
                
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Компания / ФИО</th>
                                <th>Роль</th>
                                <th>Email</th>
                                <th>Телефон</th>
                                <th>Дата</th>
                                <th style="text-align: center;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_users as $u): ?>
                                <tr>
                                    <td><strong>#<?= $u['id'] ?></strong></td>
                                    <td>
                                        <div style="font-weight: 600; color: #0f172a;">
                                            <?= htmlspecialchars($u['company'] ?? 'Не указано') ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: #94a3b8; margin-top: 3px;">
                                            <?= $u['role'] === 'dealer' ? '👤 ' . htmlspecialchars($u['contact_person'] ?? '-') : '👤 ФИО' ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $u['role'] ?>">
                                            <?= $u['role'] === 'dealer' ? 'Дилер' : 'Агент' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td><?= htmlspecialchars($u['phone']) ?></td>
                                    <td style="white-space: nowrap; color: #64748b;">
                                        <?= date('d.m.Y', strtotime($u['created_at'])) ?>
                                    <div style="font-size: 0.85rem; margin-top: 3px;">
                                        <?= date('H:i', strtotime($u['created_at'])) ?>
                                    </div>
                                    </td>
                                    <td>
                                        <div class="actions-cell">
                                            <button class="btn btn-success btn-sm" onclick="approveUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['email']) ?>')">
                                                ✔ Подтвердить
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="openRejectModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['email']) ?>')">
                                                ✖ Отклонить
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php else: ?>
                
                <div class="empty-state">
                    <div class="empty-state-icon">🎉</div>
                    <h3>Нет ожидающих заявок</h3>
                    <p>Все новые пользователи уже проверены и подтверждены.</p>
                    <a href="/admin/index.php" class="btn btn-success">Вернуться на дашборд</a>
                </div>
                
            <?php endif; ?>
            
        </div>
    </div>

    <!-- Модальное окно отклонения -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeRejectModal()">&times;</span>
            <h3 class="modal-title" style="color: #ef4444;">✖ Отклонение заявки</h3>
            <p class="modal-subtitle">
                Пользователь: <strong id="rejectEmailDisplay"></strong>
            </p>
            <form id="rejectForm" method="POST" action="reject-user.php">
                <input type="hidden" name="user_id" id="rejectUserId">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <div class="form-group">
                    <label for="reason">Причина отклонения:</label>
                    <textarea name="reason" id="reason" required placeholder="Например: Неверно указаны данные компании..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Отмена</button>
                    <button type="submit" class="btn btn-danger">Подтвердить отклонение</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Открытие модального окна
        function openRejectModal(userId, email) {
            document.getElementById('rejectUserId').value = userId;
            document.getElementById('rejectEmailDisplay').textContent = email;
            document.getElementById('rejectModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // Закрытие модального окна
        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
            document.getElementById('reason').value = '';
            document.body.style.overflow = '';
        }

        // Закрытие по клику вне окна
        window.onclick = function(event) {
            const modal = document.getElementById('rejectModal');
            if (event.target == modal) {
                closeRejectModal();
            }
        }

        // Закрытие по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeRejectModal();
            }
        });

        // Подтверждение пользователя
        function approveUser(userId, email) {
            if(!confirm('Подтвердить регистрацию пользователя ' + email + '?\n\nЕму будет отправлено уведомление на почту.')) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'approve-user.php';
            
            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'user_id';
            inputId.value = userId;
            
            const inputToken = document.createElement('input');
            inputToken.type = 'hidden';
            inputToken.name = 'csrf_token';
            inputToken.value = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
            
            form.appendChild(inputId);
            form.appendChild(inputToken);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>