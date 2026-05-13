<?php if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin'): ?>
    <a href="/cabinet.php" class="btn" style="background:var(--primary); color:#fff; padding:8px 16px; border-radius:8px; text-decoration:none;">👤 Кабинет</a>
<?php endif; ?>