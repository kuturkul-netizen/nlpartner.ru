<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth_helper.php';
$error = '';
$success = '';
function post_value(string $key): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}
function normalize_spaces(?string $value): string
{
    $value = trim((string) $value);
    return preg_replace('/\s+/u', ' ', $value);
}
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = post_value('role');
    $email = post_value('email');
    $phone = post_value('phone');
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $confirm = isset($_POST['confirm_password']) ? (string) $_POST['confirm_password'] : '';
    $agree = isset($_POST['agree']);
    $company_name = '';
    $full_name = '';
    $contact = '';
    if ($role === 'dealer') {
        $company_name = normalize_spaces(post_value('company_name'));
        $contact = normalize_spaces(post_value('contact'));
    } elseif ($role === 'agent') {
        $full_name = normalize_spaces(post_value('full_name'));
    }
    // Regex patterns
    $fioRegex = '/^[А-ЯЁ][а-яё]{1,}\s+[А-ЯЁ][а-яё]{2,}\s+[А-ЯЁ][а-яё]{2,}$/u';
    $ipRegex = '/^ИП\s+[А-ЯЁ][а-яё]{1,}\s+[А-ЯЁ][а-яё]{2,}\s+[А-ЯЁ][а-яё]{2,}$/u';
    $oooRegex = '/^ООО\s+"[А-ЯЁ][А-ЯЁа-яё0-9\s\-]+"$/u';
    $emailRegex = '/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/';
    $phoneRegex = '/^\+7\s\(\d{3}\)\s\d{3}-\d{2}-\d{2}$/';
    if ($role !== 'dealer' && $role !== 'agent') {
        $error = '⚠️ Выберите статус партнера';
    } elseif ($email === '' || $phone === '' || $password === '' || $confirm === '') {
        $error = '⚠️ Заполните все обязательные поля';
    } elseif (!$agree) {
        $error = '⚠️ Необходимо дать согласие на обработку данных';
    } elseif ($role === 'dealer' && $company_name === '') {
        $error = '⚠️ Для дилера обязательно название компании';
    } elseif ($role === 'dealer' && $contact === '') {
        $error = '⚠️ Для дилера обязательно контактное лицо';
    } elseif ($role === 'dealer' && !preg_match($fioRegex, $contact)) {
        $error = '⚠️ Контактное лицо: Фамилия Имя Отчество (каждое слово с заглавной буквы).';
    } elseif ($role === 'dealer' && !preg_match($ipRegex, $company_name) && !preg_match($oooRegex, $company_name)) {
        $error = '⚠️ Компания: ИП Фамилия Имя Отчество или ООО "Название"';
    } elseif ($role === 'agent' && $full_name === '') {
        $error = '⚠️ Для агента обязательно ФИО';
    } elseif ($role === 'agent' && !preg_match($fioRegex, $full_name)) {
        $error = '⚠️ ФИО: Фамилия Имя Отчество (каждое слово с заглавной буквы).';
    } elseif (!preg_match($emailRegex, $email)) {
        $error = '⚠️ Email должен быть на английском';
    } elseif (!preg_match($phoneRegex, $phone)) {
        $error = '⚠️ Телефон: +7 (999) 123-45-67';
    } elseif ($password !== $confirm) {
        $error = '❌ Пароли не совпадают';
    } elseif (strlen($password) < 6) {
        $error = '❌ Пароль минимум 6 символов';
    }
    if ($error === '') {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = '⚠️ Этот Email уже зарегистрирован. <a href="/auth/login.php" class="link-error">Войти</a>';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $token = bin2hex(random_bytes(32));
                $company_value = $role === 'agent' ? $full_name : $company_name;
                $contact_value = $role === 'agent' ? $full_name : $contact;
                $stmt = $pdo->prepare(
                    'INSERT INTO users (company, role, contact_person, email, phone, password_hash, verification_token, is_verified)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0)'
                );
                $stmt->execute([$company_value, $role, $contact_value, $email, $phone, $hash, $token]);
                $host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
                $host = $host !== '' ? $host : 'localhost';
                $mailDomain = preg_replace('/:\d+$/', '', $host);
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $link = $protocol . '://' . $host . '/auth/verify.php?token=' . urlencode($token);
                $subject = '=?UTF-8?B?' . base64_encode('Подтверждение регистрации NEIROLINKS') . '?=';
                $message = "Здравствуйте!\nДля активации аккаунта перейдите по ссылке:\n$link";
                $headers = "From: NEIROLINKS <noreply@{$mailDomain}>\r\n"
                         . "MIME-Version: 1.0\r\n"
                         . "Content-Type: text/plain; charset=UTF-8\r\n";
                @mail($email, $subject, $message, $headers);
                $success = '✅ Регистрация успешна! Ссылка отправлена на Email.';
                $_POST = [];
            }
        } catch (PDOException $e) {
            error_log('Registration DB error: ' . $e->getMessage());
            $error = '❌ Ошибка БД. Попробуйте позже.';
        } catch (Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            $error = '❌ Ошибка регистрации. Попробуйте позже.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация | NEIROLINKS</title>
    
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
                    <p class="login-subtitle">регистрация партнёра</p>
                </div>
            </div>
            <?php if ($error): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><?= e($success) ?></div>
            <?php else: ?>
                <form method="POST" id="registrationForm" novalidate>
                    <div class="form-group">
                        <label>Статус партнера *</label>
                        <select name="role" id="role" required>
                            <option value="" disabled <?= !isset($_POST['role']) ? 'selected' : '' ?>>Выберите статус</option>
                            <option value="dealer" <?= (isset($_POST['role']) && $_POST['role'] === 'dealer') ? 'selected' : '' ?>>Дилер</option>
                            <option value="agent" <?= (isset($_POST['role']) && $_POST['role'] === 'agent') ? 'selected' : '' ?>>Агент</option>
                        </select>
                        <div class="error-hint">⚠️ Выберите статус партнера</div>
                    </div>
                    <!-- Модальное окно выбора типа компании -->
                    <div id="companyTypeModal" class="modal-overlay hidden">
                        <div class="modal-content">
                            <h3>Выберите тип компании</h3>
                            <p class="modal-subtitle">Для продолжения укажите организационно-правовую форму</p>
                            <div class="modal-buttons">
                                <button type="button" class="modal-btn" data-type="ip">
                                    <span class="modal-btn-icon">👤</span>
                                    <span class="modal-btn-text">
                                        <strong>ИП</strong>
                                        <small>Индивидуальный предприниматель</small>
                                    </span>
                                </button>
                                <button type="button" class="modal-btn" data-type="ooo">
                                    <span class="modal-btn-icon">🏢</span>
                                    <span class="modal-btn-text">
                                        <strong>ООО</strong>
                                        <small>Общество с ограниченной ответственностью</small>
                                    </span>
                                </button>
                            </div>
                            <button type="button" class="modal-close-btn" id="modalCloseBtn">Отмена</button>
                        </div>
                    </div>
                    <div class="form-group hidden" id="companyGroup">
                        <label>Название компании *</label>
                        <div class="company-input-wrapper">
                            <input type="text" name="company_name" id="company_name" placeholder="Нажмите для выбора типа" value="<?= e(isset($_POST['company_name']) ? (string) $_POST['company_name'] : '') ?>" autocomplete="off" readonly>
                            <span class="company-type-badge hidden" id="companyTypeBadge"></span>
                            <button type="button" class="change-type-btn" id="changeTypeBtn" title="Изменить тип">🔄</button>
                        </div>
                        <div class="error-hint" id="companyHint">⚠️ Нажмите на поле для выбора типа компании</div>
                        <div class="field-info" id="companyInfo"></div>
                    </div>
                    <div class="form-group hidden" id="contactGroup">
                        <label>Контактное лицо *</label>
                        <input type="text" name="contact" id="contact" placeholder="Фамилия Имя Отчество" value="<?= e(isset($_POST['contact']) ? (string) $_POST['contact'] : '') ?>" autocomplete="off">
                        <div class="error-hint">⚠️ Фамилия Имя Отчество (3 слова, каждое с заглавной буквы)</div>
                        <div class="field-info">Пример: Иванов Иван Иванович</div>
                    </div>
                    <div class="form-group hidden" id="fullNameGroup">
                        <label>ФИО *</label>
                        <input type="text" name="full_name" id="full_name" placeholder="Фамилия Имя Отчество" value="<?= e(isset($_POST['full_name']) ? (string) $_POST['full_name'] : '') ?>" autocomplete="off">
                        <div class="error-hint">⚠️ Фамилия Имя Отчество (3 слова, каждое с заглавной буквы)</div>
                        <div class="field-info">Пример: Иванов Иван Иванович</div>
                    </div>
                    <div class="form-group">
                        <label>Телефон *</label>
                        <input type="tel" name="phone" id="phone" required placeholder="+7 (999) 123-45-67" value="<?= e(isset($_POST['phone']) ? (string) $_POST['phone'] : '') ?>">
                        <div class="error-hint">⚠️ Формат: +7 (999) 123-45-67</div>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="text" name="email" id="email" required placeholder="name@company.ru" value="<?= e(isset($_POST['email']) ? (string) $_POST['email'] : '') ?>" autocomplete="off">
                        <div class="error-hint">⚠️ Email должен быть на английском</div>
                        <div class="field-info">Пример: info@company.ru</div>
                    </div>
                    <div class="form-group">
                        <label>Пароль *</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" required placeholder="••••••••">
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
                    <div class="checkbox-group">
                        <input type="checkbox" name="agree" id="agree" required <?= isset($_POST['agree']) ? 'checked' : '' ?>>
                        <label for="agree">Я согласен на обработку <a href="/privacy.php" target="_blank">персональных данных</a></label>
                    </div>
                    <button type="submit">Зарегистрироваться</button>
                </form>
            <?php endif; ?>
            <div class="links">
                Уже есть аккаунт? <a href="/auth/login.php">Войти в систему</a>
            </div>
        </div>
    </div>
    <script>
        // ===== REGEX PATTERNS =====
        const fioRegex = /^[А-ЯЁ][а-яё]{1,}\s+[А-ЯЁ][а-яё]{2,}\s+[А-ЯЁ][а-яё]{2,}$/u;
        const ipRegex = /^ИП\s+[А-ЯЁ][а-яё]{1,}\s+[А-ЯЁ][а-яё]{2,}\s+[А-ЯЁ][а-яё]{2,}$/u;
        const oooRegex = /^ООО\s+"[А-ЯЁ][А-ЯЁа-яё0-9\s\-]+"$/u;
        const emailRegex = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        const phoneRegex = /^\+7\s\(\d{3}\)\s\d{3}-\d{2}-\d{2}$/;
        const cyrillicRegex = /[А-ЯЁа-яё]/u;
        const latinRegex = /[a-zA-Z]/;
        // ===== LAYOUT CONVERSION MAPS =====
        const enToRuMap = {
            'q': 'й', 'w': 'ц', 'e': 'у', 'r': 'к', 't': 'е', 'y': 'н', 'u': 'г', 'i': 'ш', 'o': 'щ', 'p': 'з', '[': 'х', ']': 'ъ',
            'a': 'ф', 's': 'ы', 'd': 'в', 'f': 'а', 'g': 'п', 'h': 'р', 'j': 'о', 'k': 'л', 'l': 'д', ';': 'ж', "'": 'э',
            'z': 'я', 'x': 'ч', 'c': 'с', 'v': 'м', 'b': 'и', 'n': 'т', 'm': 'ь', ',': 'б', '.': 'ю', '/': '.',
            'Q': 'Й', 'W': 'Ц', 'E': 'У', 'R': 'К', 'T': 'Е', 'Y': 'Н', 'U': 'Г', 'I': 'Ш', 'O': 'Щ', 'P': 'З', '{': 'Х', '}': 'Ъ',
            'A': 'Ф', 'S': 'Ы', 'D': 'В', 'F': 'А', 'G': 'П', 'H': 'Р', 'J': 'О', 'K': 'Л', 'L': 'Д', ':': 'Ж', '"': 'Э',
            'Z': 'Я', 'X': 'Ч', 'C': 'С', 'V': 'М', 'B': 'И', 'N': 'Т', 'M': 'Ь', '<': 'Б', '>': 'Ю', '?': ','
        };
        const ruToEnMap = {
            'й': 'q', 'ц': 'w', 'у': 'e', 'к': 'r', 'е': 't', 'н': 'y', 'г': 'u', 'ш': 'i', 'щ': 'o', 'з': 'p', 'х': '[', 'ъ': ']',
            'ф': 'a', 'ы': 's', 'в': 'd', 'а': 'f', 'п': 'g', 'р': 'h', 'о': 'j', 'л': 'k', 'д': 'l', 'ж': ';', 'э': "'",
            'я': 'z', 'ч': 'x', 'с': 'c', 'м': 'v', 'и': 'b', 'т': 'n', 'ь': 'm', 'б': ',', 'ю': '.', '.': '/',
            'Й': 'Q', 'Ц': 'W', 'У': 'E', 'К': 'R', 'Е': 'T', 'Н': 'Y', 'Г': 'U', 'Ш': 'I', 'Щ': 'O', 'З': 'P', 'Х': '{', 'Ъ': '}',
            'Ф': 'A', 'Ы': 'S', 'В': 'D', 'А': 'F', 'П': 'G', 'Р': 'H', 'О': 'J', 'Л': 'K', 'Д': 'L', 'Ж': ':', 'Э': '"',
            'Я': 'Z', 'Ч': 'X', 'С': 'C', 'М': 'V', 'И': 'B', 'Т': 'N', 'Ь': 'M', 'Б': '<', 'Ю': '>', ',': '?',
            // Спецсимволы для email
            '"': '@',  // Русская кавычка (Shift+2) → @
            ',': '.',  // Русская запятая/точка → .
            'б': ',',  // Русская "б" → ,
            'ю': '.',  // Русская "ю" → .
            '@': '@',  // Если уже @
            '.': '.',  // Если уже .
            '-': '-',  // Дефис
            '_': '_'   // Подчеркивание
        };
        function togglePassword(btn) {
            const input = btn.previousElementSibling;
            if (input.type === 'password') { input.type = 'text'; btn.textContent = '🙈'; }
            else { input.type = 'password'; btn.textContent = '👁️'; }
        }
        function setState(field, isValid, showError) {
            const group = field.closest('.form-group');
            if (!group) return isValid;
            group.classList.toggle('valid', isValid && field.value.trim() !== '');
            group.classList.toggle('error', showError && !isValid);
            return isValid;
        }
        function validateRequired(field, showError = true) { return setState(field, field.value.trim() !== '', showError); }
        function validatePattern(field, regex, showError = true) {
            const value = field.value.trim();
            return setState(field, value !== '' && regex.test(value), showError);
        }
        function convertLayout(text, targetLang) {
            let result = '';
            const map = targetLang === 'ru' ? enToRuMap : ruToEnMap;
            for (let char of text) { result += map[char] || char; }
            return result;
        }
        function autoConvertLayout(value, targetLang) {
            if (!value) return value;
            let result = '';
            const map = targetLang === 'ru' ? enToRuMap : ruToEnMap;
            for (let char of value) {
                // Если символ есть в карте конвертации — конвертируем
                // Если нет (пробел, @, ., -) — оставляем как есть
                result += map[char] || char;
            }
            return result;
        }
        // ✅ ИСПРАВЛЕНИЕ 1: Капитализация КАЖДОГО слова (для ИП, ФИО, Контактное лицо)
        function capitalizeWords(text) {
            if (!text) return text;
            return text.replace(/\S+/g, function(word) {
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            });
        }
        // ✅ ИСПРАВЛЕНИЕ 1: Капитализация ТОЛЬКО первого слова (для ООО)
        function capitalizeFirstWordOnly(text) {
            if (!text) return text;
            // Находим первое слово и капитализируем его
            return text.replace(/^(\s*\S+)/, function(firstWord) {
                return firstWord.charAt(0).toUpperCase() + firstWord.slice(1).toLowerCase();
            }).replace(/(\s+\S+)/g, function(word) {
                // Все последующие слова - только в нижний регистр
                return word.toLowerCase();
            });
        }
        function getWords(text) { return text.trim().split(/\s+/).filter(w => w.length > 0); }
        function validateWordCount(text, minWords, maxWords, minLengths) {
            const words = getWords(text);
            if (words.length < minWords || words.length > maxWords) {
                return { valid: false, message: `Должно быть от ${minWords} до ${maxWords} слов` };
            }
            for (let i = 0; i < words.length && i < minLengths.length; i++) {
                if (words[i].length < minLengths[i]) {
                    return { valid: false, message: `Слово ${i + 1} должно содержать минимум ${minLengths[i]} букв` };
                }
            }
            return { valid: true, message: '' };
        }
        let companyType = null;
        function showCompanyModal() { document.getElementById('companyTypeModal').classList.remove('hidden'); }
        function hideCompanyModal() { document.getElementById('companyTypeModal').classList.add('hidden'); }
        function setCompanyType(type) {
            companyType = type;
            const companyInput = document.getElementById('company_name');
            const badge = document.getElementById('companyTypeBadge');
            const info = document.getElementById('companyInfo');
            if (type === 'ip') {
                companyInput.value = 'ИП ';
                badge.textContent = 'ИП';
                badge.className = 'company-type-badge ip-badge';
                info.textContent = 'Формат: ИП Фамилия Имя Отчество (только кириллица, 3 слова)';
            } else if (type === 'ooo') {
                companyInput.value = 'ООО "';
                badge.textContent = 'ООО';
                badge.className = 'company-type-badge ooo-badge';
                info.textContent = 'Формат: ООО "Название" (только кириллица, 1-5 слов)';
            }
            badge.classList.remove('hidden');
            companyInput.readOnly = false;
            companyInput.focus();
            hideCompanyModal();
            updateCompanyHint();
        }
        function resetCompanyField() {
            companyType = null;
            const companyInput = document.getElementById('company_name');
            const badge = document.getElementById('companyTypeBadge');
            const info = document.getElementById('companyInfo');
            companyInput.value = '';
            companyInput.placeholder = 'Нажмите для выбора типа';
            companyInput.readOnly = true;
            badge.classList.add('hidden');
            info.textContent = '';
            updateCompanyHint();
        }
        function updateCompanyHint() {
            const hint = document.getElementById('companyHint');
            hint.textContent = !companyType ? '⚠️ Нажмите на поле для выбора типа компании' : '';
        }
        // ✅ ИСПРАВЛЕНИЕ 1: Для ИП — капитализируем каждое слово, для ООО — только первое
        function handleCompanyInput(e) {
            if (!companyType) return;
            let value = e.target.value;
            const isIP = companyType === 'ip';
            const prefix = isIP ? 'ИП ' : 'ООО "';
            let content = value.substring(prefix.length);
            // 1. Конвертируем раскладку
            content = autoConvertLayout(content, 'ru');
            // 2. Удаляем недопустимые символы
            if (isIP) {
                content = content.replace(/[^а-яёА-ЯЁ\s]/gu, '');
            } else {
                content = content.replace(/[^а-яёА-ЯЁ0-9\s\-]/gu, '');
            }
            // 3. ✅ ИСПРАВЛЕНИЕ: Для ИП — каждое слово с заглавной, для ООО — только первое
            if (isIP) {
                content = capitalizeWords(content);
            } else {
                content = capitalizeFirstWordOnly(content);
            }
            // 4. Ограничиваем количество слов
            const words = getWords(content);
            const maxWords = isIP ? 3 : 5;
            if (words.length > maxWords) {
                content = words.slice(0, maxWords).join(' ');
            }
            e.target.value = prefix + content;
        }
        function handleCompanyBlur(e) {
            if (!companyType) return;
            let value = e.target.value;
            const isIP = companyType === 'ip';
            const prefix = isIP ? 'ИП ' : 'ООО "';
            if (isIP) {
                const content = value.substring(prefix.length).trim();
                e.target.value = prefix + content;
            } else {
                if (!value.endsWith('"')) {
                    e.target.value = value + '"';
                }
            }
            validateCompany(true);
        }
        function validateCompany(showError = true) {
            const field = document.getElementById('company_name');
            const value = field.value.trim();
            if (!companyType) { if (showError) showCompanyModal(); return false; }
            const isValid = value !== '' && (ipRegex.test(value) || oooRegex.test(value));
            return setState(field, isValid, showError);
        }
        function handleFioInput(e) {
            let value = e.target.value;
            // 1. Конвертируем раскладку
            value = autoConvertLayout(value, 'ru');
            // 2. Удаляем недопустимые символы
            value = value.replace(/[^а-яёА-ЯЁ\s]/gu, '');
            // 3. Капитализируем каждое слово
            value = capitalizeWords(value);
            // 4. Ограничиваем до 3 слов
            const words = getWords(value);
            if (words.length > 3) {
                value = words.slice(0, 3).join(' ');
            }
            e.target.value = value;
        }
        function validateFio(field, showError = true) {
            const value = field.value.trim();
            const validation = validateWordCount(value, 3, 3, [2, 3, 3]);
            if (!validation.valid) {
                if (showError) {
                    const hint = field.closest('.form-group').querySelector('.error-hint');
                    if (hint) hint.textContent = '⚠️ ' + validation.message;
                }
                return setState(field, false, showError);
            }
            return setState(field, fioRegex.test(value), showError);
        }
        // ✅ ИСПРАВЛЕНИЕ 2: Email теперь корректно принимает @ на русской раскладке
        function handleEmailInput(e) {
            let value = e.target.value;
            // 1. Конвертируем раскладку (теперь @ и . корректно конвертируются)
            value = autoConvertLayout(value, 'en');
            // 2. Разрешаем только допустимые символы email
            value = value.replace(/[^a-zA-Z0-9.@_+\-]/g, '');
            e.target.value = value;
        }
        function validateEmail(showError = true) {
            return validatePattern(document.getElementById('email'), emailRegex, showError);
        }
        function validateConfirmPassword(showError = true) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password');
            return setState(confirm, confirm.value !== '' && confirm.value === password, showError);
        }
        function toggleFields() {
            const role = document.getElementById('role').value;
            const isDealer = role === 'dealer';
            const isAgent = role === 'agent';
            document.getElementById('companyGroup').classList.toggle('hidden', !isDealer);
            document.getElementById('contactGroup').classList.toggle('hidden', !isDealer);
            document.getElementById('fullNameGroup').classList.toggle('hidden', !isAgent);
            document.getElementById('company_name').required = isDealer;
            document.getElementById('contact').required = isDealer;
            document.getElementById('full_name').required = isAgent;
            if (!isDealer) resetCompanyField();
        }
        function validateAll(showErrors = true) {
            const role = document.getElementById('role');
            const email = document.getElementById('email');
            const phone = document.getElementById('phone');
            const password = document.getElementById('password');
            const agree = document.getElementById('agree');
            let valid = true;
            valid = setState(role, role.value === 'dealer' || role.value === 'agent', showErrors) && valid;
            if (role.value === 'dealer') {
                valid = validateCompany(showErrors) && valid;
                valid = validateFio(document.getElementById('contact'), showErrors) && valid;
            }
            if (role.value === 'agent') {
                valid = validateFio(document.getElementById('full_name'), showErrors) && valid;
            }
            valid = validateEmail(showErrors) && valid;
            valid = validatePattern(phone, phoneRegex, showErrors) && valid;
            valid = setState(password, password.value.length >= 6, showErrors) && valid;
            valid = validateConfirmPassword(showErrors) && valid;
            if (!agree.checked) valid = false;
            return valid;
        }
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('registrationForm');
            if (!form) return;
            const role = document.getElementById('role');
            const email = document.getElementById('email');
            const phone = document.getElementById('phone');
            const password = document.getElementById('password');
            const confirm = document.getElementById('confirm_password');
            const company = document.getElementById('company_name');
            const contact = document.getElementById('contact');
            const fullName = document.getElementById('full_name');
            const modal = document.getElementById('companyTypeModal');
            const modalBtns = modal.querySelectorAll('.modal-btn');
            const modalCloseBtn = document.getElementById('modalCloseBtn');
            const changeTypeBtn = document.getElementById('changeTypeBtn');
            toggleFields();
            // Проверка сохраненных значений при перезагрузке
            if (company.value) {
                if (company.value.startsWith('ИП ')) setCompanyType('ip');
                else if (company.value.startsWith('ООО "')) setCompanyType('ooo');
            }
            role.addEventListener('change', function () { toggleFields(); validateAll(false); });
            company.addEventListener('click', function () { if (!companyType) showCompanyModal(); });
            changeTypeBtn.addEventListener('click', function (e) { e.stopPropagation(); showCompanyModal(); });
            modalBtns.forEach(btn => {
                btn.addEventListener('click', function () { setCompanyType(this.dataset.type); });
            });
            modalCloseBtn.addEventListener('click', hideCompanyModal);
            modal.addEventListener('click', function (e) { if (e.target === modal) hideCompanyModal(); });
            company.addEventListener('input', handleCompanyInput);
            company.addEventListener('blur', handleCompanyBlur);
            contact.addEventListener('input', handleFioInput);
            contact.addEventListener('blur', function () { validateFio(contact, true); });
            fullName.addEventListener('input', handleFioInput);
            fullName.addEventListener('blur', function () { validateFio(fullName, true); });
            email.addEventListener('input', handleEmailInput);
            email.addEventListener('blur', function () { validateEmail(true); });
            phone.addEventListener('blur', function () { validatePattern(phone, phoneRegex, true); });
            phone.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 0 && (value[0] === '7' || value[0] === '8')) { value = value.substring(1); }
                let formatted = '+7';
                if (value.length > 0) formatted += ' (' + value.substring(0, 3);
                if (value.length >= 3) formatted += ') ' + value.substring(3, 6);
                if (value.length >= 6) formatted += '-' + value.substring(6, 8);
                if (value.length >= 8) formatted += '-' + value.substring(8, 10);
                e.target.value = formatted;
            });
            password.addEventListener('blur', function () {
                setState(password, password.value.length >= 6, true);
                validateConfirmPassword(false);
            });
            confirm.addEventListener('blur', function () { validateConfirmPassword(true); });
            form.addEventListener('submit', function (e) { if (!validateAll(true)) e.preventDefault(); });
        });
    </script>
</body>
</html>