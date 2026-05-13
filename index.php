<?php
// Файл: index.php
// 🔐 Дилерский портал NEIROLINKS Motion
session_start();
// 1. Подключение ядра
if (!file_exists(__DIR__ . '/config/db.php')) {
    die('Ошибка: Файл config/db.php не найден.');
}
require __DIR__ . '/config/db.php'; // Подключает $pdo
require_once __DIR__ . '/includes/auth_helper.php';
// 2. Проверка авторизации и статусов
if (!is_logged_in()) {
    header('Location: /auth/login.php');
    exit;
}
$user = get_logged_user();
if (!$user) {
    session_destroy();
    header('Location: /auth/login.php');
    exit;
}
// Проверка подтверждения email
if ($user['is_verified'] == 0) {
    header('Location: /auth/login.php?error=verify_email');
    exit;
}
// Проверка подтверждения администратором
if ($user['is_admin_approved'] == 0) {
    header('Location: /pending-approval.php');
    exit;
}
// 3. Определение роли
$isAgent = ($user['role'] === 'agent');
if (!is_array($user)) {
    $user = ['company' => 'Пользователь', 'role' => 'dealer'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEIROLINKS Motion | Дилерский портал</title>
    <!-- 🔹 ФАВИКОНКИ -->
    <link rel="shortcut icon" href="/icon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icon-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/icon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/icon-512.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <!-- 🔗 Подключение вынесенных стилей -->
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-brand">
                <img src="logo.png" alt="NEIROLINKS Motion" class="logo" onerror="this.style.display='none'">
                <div class="header-text">
                    <span class="header-title">NEIROLINKS Motion |</span>
                    <span class="header-subtitle">Дилерский портал</span>
                </div>
            </div>
            <div class="header-info">
                <div>
                    <b>👤 <?= htmlspecialchars($user['company'] ?? 'Пользователь') ?></b>
                    <span style="background:#e0e7ff;color:#3730a3;padding:2px 8px;border-radius:4px;font-size:0.75rem;margin-left:6px;">
                        <?= htmlspecialchars($user['role'] === 'admin' ? 'Админ' : ($user['role'] === 'dealer' ? 'Дилер' : 'Агент')) ?>
                    </span>
                </div>
                <div>ООО «Нейролинкс» | ИНН 6658578360</div>
                <div>📍 г. Екатеринбург, ул. Кондратьева 2а/2</div>
                <div>📞 +7 (343) 382-92-43 | 📧 neirolinks@yandex.ru</div>
                <div style="margin-top:8px;">
                    <a href="/cabinet/cabinet.php" style="color:#3b82f6;text-decoration:none;font-weight:500;font-size:0.85rem;margin-right:15px;"> Личный кабинет</a>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="/admin/index.php" style="color:#dc3545;text-decoration:none;font-weight:500;font-size:0.85rem;margin-right:15px;">🛡️ Админка</a>
                    <?php endif; ?>
                    <a href="/auth/logout.php" style="color:#ef4444;text-decoration:none;font-weight:500;font-size:0.85rem;">🚪 Выйти</a>
                </div>
            </div>
        </header>

        <div class="card">
            <!-- 🔹 Удалён плейсхолдер "Выберите раздел". Категория инициализируется автоматически -->
            <div class="grid-2">
                <div><label>Раздел каталога</label><select id="category"></select></div>
            </div>
            <div id="mainFormBlock">
                <div class="grid-2"><div><label>Серия</label><select id="series" disabled><option value="">Выберите серию</option></select></div></div>
                <div id="descToggle" onclick="toggleDesc()">📖 Показать описание</div>
                <div id="descBox"></div>
                <div id="slidingFieldsWrapper" style="display:none;">
                    <div class="grid-5">
                        <div id="sizeGroup"><label>Размер карниза (мм)</label><input type="number" id="mmInput" placeholder="Ширина" min="100" step="1"><span id="maxWidthLabel" class="limit-text"></span><div id="slidingWarning" style="display: none; color: var(--warning); font-size: 0.8rem; margin-top: 6px; font-weight: 500; line-height: 1.4;"></div></div>
                        <div id="driveGroup"><label>Положение привода</label><select id="drivePos"><option value="">—</option><option value="справа">Справа</option><option value="слева">Слева</option></select></div>
                        <div id="controlGroup"><label>Способ управления</label><select id="controlMethod"><option value="">—</option></select></div>
                        <div id="typeGroup"><label>Тип раздвижки</label><select id="openType"><option value="">—</option><option value="от центра">От центра</option><option value="от края">От края</option></select></div>
                        <div id="colorGroup"><label>Цвет</label><select id="color"><option value="">—</option><option value="белый">Белый</option><option value="чёрный">Чёрный</option></select></div>
                        <div id="heightGroup"><label>Высота карниза (мм)</label><input type="number" id="heightInput" placeholder="Например: 2000" min="100" step="1"></div>
                    </div>
                </div>
            </div>

            <div id="accBlock" style="display:none;">
                <label>Выберите аксессуары для добавления в заказ</label>
                <div class="table-wrap">
                    <table><thead><tr>
                        <th style="width: 500px;">Наименование</th>
                        <th style="width: 140px; text-align: center;">Цвет</th>
                        <th style="width: 140px; text-align: center; <?= $isAgent ? 'display:none;' : '' ?>">Цена дилер</th>
                        <th style="width: 140px; text-align: center;">Цена РРЦ</th>
                        <th style="width: 140px; text-align: center;">Кол-во</th>
                    </tr></thead><tbody id="accBody"></tbody></table>
                </div>
                <button id="addAccBtn">➕ Добавить выбранные аксессуары в заказ</button>
            </div>

            <div class="preview-grid" id="previewBlock" style="margin-top: 16px;">
                <div class="preview-box" id="pDealerBox" style="<?= $isAgent ? 'display:none;' : '' ?>"><small>Дилер за ед.</small><span id="pDealer">0 ₽</span></div>
                <div class="preview-box"><small>РРЦ за ед.</small><span id="pRRCP">0 ₽</span></div>
                <div class="preview-box main"><small>Цена клиента</small><span id="pClient">0 ₽</span></div>
                <div class="preview-box" style="background:#ecfdf5; border-color:#10b981;"><small>Маржа</small><span id="pMargin" style="color:#047857;">0 ₽</span></div>
            </div>
            <div class="btn-row" id="addMainBtnBlock"><button id="addBtn" disabled>➕ Добавить в спецификацию</button></div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table><thead><tr>
                    <th>Наименование</th>
                    <th style="width:70px; text-align:center;">Кол-во</th>
                    <th style="<?= $isAgent ? 'display:none;' : '' ?>; width:100px; text-align:center;">Дилер</th>
                    <th style="width:100px; text-align:center;">РРЦ</th>
                    <th style="width:80px; text-align:center;">Скидка %</th>
                    <th style="width:120px; text-align:center;">Цена клиента</th>
                    <th style="width:100px; text-align:center;">Маржа</th>
                    <th style="width:40px;"></th>
                </tr></thead><tbody id="orderBody"></tbody><tfoot><tr>
                    <td colspan="2" style="text-align: right; padding-right: 10px;">ИТОГО ПО ЗАКАЗУ:</td>
                    <td id="tDealer" style="font-weight:bold; text-align:center; <?= $isAgent ? 'display:none;' : '' ?>;">0 ₽</td>
                    <td></td><td></td>
                    <td id="tClient" style="font-weight:bold; color:var(--primary-accent); text-align:center;">0 ₽</td>
                    <td id="tMargin" style="font-weight:bold; text-align:center;">0 ₽</td>
                    <td></td>
                </tr></tfoot></table>
            </div>
            <div class="btn-row">
                <button id="copyBtn">📋 Копировать спецификацию</button>
                <button id="saveOrderBtn">➕ Добавить в заказ</button>
                <button id="clearBtn">🗑 Очистить заказ</button>
            </div>
        </div>
    </div>
    <div id="toast">✅ Сообщение</div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const IS_AGENT = <?= json_encode($isAgent) ?>;
        const USER_COMPANY = <?= json_encode($user['company'] ?? 'Партнер NEIROLINKS') ?>;
        const USER_PHONE = <?= json_encode($user['phone'] ?? '') ?>;
        const USER_EMAIL = <?= json_encode($user['email'] ?? '') ?>;

        const CATALOG = {
            "Раздвижные электрокарнизы": {
                "Curtain Lite (somfy)": { desc: "Электрокарниз NEIROLINKS Motion\n- накладной, крепление к потолку или стене (опционально);\n- раздвижной (от центра или от края);\n- тихая комплектация (прорезиненные колесики);\n- питание от сети 220В;\n- управление проводное: фазное, сухой контакт, RS-485;\n- управление беспроводное: радио, ZigBee;\n- способ управления: пультом, со смартфона, интеграция в систему \"умный дом\" в т.ч. голосовое управление, например Алиса.\n- максимальный вес штор: не более 30 кг.", controls: ["Фазное", "Сухой контакт", "RS-485", "Радио", "ZigBee"], sizes: [{s:"до 1,5",d:13900,r:19460},{s:"до 2",d:14960,r:20940},{s:"до 2,5",d:16100,r:22540},{s:"до 3",d:17070,r:23900},{s:"до 3,5",d:18430,r:25800},{s:"до 4",d:19500,r:27290},{s:"до 4,5",d:20640,r:28890},{s:"до 5",d:21700,r:30380},{s:"до 5,5",d:22840,r:31980},{s:"до 6",d:23810,r:33330},{s:"до 6,5",d:24950,r:34940},{s:"до 7",d:26010,r:36420},{s:"до 7,5",d:27160,r:38020},{s:"до 8",d:28220,r:39510},{s:"до 8,5",d:29360,r:41110},{s:"до 9",d:30330,r:42460},{s:"до 9,5",d:31480,r:44070},{s:"до 10",d:32540,r:45550},{s:"до 10,5",d:33680,r:47150},{s:"до 11",d:34740,r:48640},{s:"до 11,5",d:35880,r:50240},{s:"до 12",d:36850,r:51590}] },
                "Curtain (somfy)": { desc: "Электрокарниз NEIROLINKS Motion\n- накладной, крепление к потолку или стене (опционально);\n- раздвижной (от центра или от края);\n- тихая комплектация (прорезиненные колесики);\n- питание от сети 220В;\n- управление проводное: фазное, сухой контакт, RS-485;\n- управление беспроводное: радио, Wi-Fi, ZigBee;\n- способ управления: пультом, со смартфона, интеграция в систему \"умный дом\" в т.ч. голосовое управление, например Алиса.\n- максимальный вес штор: не более 60 кг.", controls: ["Фазное", "Сухой контакт", "RS-485", "Радио", "Wi-Fi", "ZigBee"], sizes: [{s:"до 1,5",d:16210,r:22690},{s:"до 2",d:17270,r:24180},{s:"до 2,5",d:18410,r:25780},{s:"до 3",d:19380,r:27130},{s:"до 3,5",d:20740,r:29040},{s:"до 4",d:21800,r:30530},{s:"до 4,5",d:22950,r:32130},{s:"до 5",d:24010,r:33610},{s:"до 5,5",d:25150,r:35210},{s:"до 6",d:26120,r:36570},{s:"до 6,5",d:27270,r:38170},{s:"до 7",d:28330,r:39660},{s:"до 7,5",d:29470,r:41260},{s:"до 8",d:30530,r:42740},{s:"до 8,5",d:31670,r:44340},{s:"до 9",d:32640,r:45700},{s:"до 9,5",d:33790,r:47300},{s:"до 10",d:34850,r:48790},{s:"до 10,5",d:35990,r:50390},{s:"до 11",d:37050,r:51870},{s:"до 11,5",d:38190,r:53470},{s:"до 12",d:39160,r:54830}] },
                "Curtain Battery (somfy)": { desc: "Электрокарниз NEIROLINKS Motion\n- накладной, крепление к потолку или стене (опционально);\n- раздвижной (от центра или от края);\n- тихая комплектация (прорезиненные колесики);\n- питание от встроенного аккумулятора;\n- управление беспроводное: радио;\n- способ управления: пультом.\n- максимальный вес штор: не более 40 кг.", controls: ["Радио"], sizes: [{s:"до 1,5",d:20500,r:28700},{s:"до 2",d:21560,r:30180},{s:"до 2,5",d:22700,r:31780},{s:"до 3",d:23670,r:33140},{s:"до 3,5",d:25030,r:35050},{s:"до 4",d:26090,r:36530},{s:"до 4,5",d:27240,r:38130},{s:"до 5",d:28300,r:39620},{s:"до 5,5",d:29440,r:41220},{s:"до 6",d:30410,r:42580}] },
                "Hidden (kraab)": { desc: "Электрокарниз NEIROLINKS Motion\n- встраиваемый в ГКЛ или натяжной потолок;\n- раздвижной (от центра или от края);\n- тихая комплектация (прорезиненные колесики);\n- питание от сети 220В;\n- управление проводное: фазное, сухой контакт, RS-485;\n- управление беспроводное: радио, Wi-Fi, ZigBee;\n- способ управления: пультом, со смартфона, интеграция в систему \"умный дом\" в т.ч. голосовое управление, например Алиса.\n- максимальный вес штор: не более 60 кг.", controls: ["Фазное", "Сухой контакт", "RS-485", "Радио", "Wi-Fi", "ZigBee"], sizes: [{s:"до 2",d:28750,r:40250},{s:"до 2,5",d:31130,r:43580},{s:"до 3",d:33420,r:46780},{s:"до 3,5",d:35800,r:50110},{s:"до 4",d:38080,r:53320},{s:"до 4,5",d:40460,r:56650},{s:"до 5",d:42750,r:59850},{s:"до 5,5",d:45130,r:63180},{s:"до 6",d:47410,r:66380},{s:"до 6,5",d:49800,r:69710},{s:"до 7",d:52080,r:72910},{s:"до 7,5",d:54460,r:76240},{s:"до 8",d:56750,r:79450},{s:"до 8,5",d:59130,r:82780},{s:"до 9",d:61410,r:85980},{s:"до 9,5",d:63800,r:89310},{s:"до 10",d:66080,r:92510},{s:"до 10,5",d:68460,r:95840},{s:"до 11",d:70750,r:99050},{s:"до 11,5",d:73130,r:102380},{s:"до 12",d:75410,r:105580}] }
            },
            "Ручные карнизы": {
                "Curtain Tulle (somfy)": { desc: "Карниз NEIROLINKS Motion\n- накладной, крепление к потолку или стене (опционально);\n- раздвижной (от центра или от края);\n- тихая комплектация (прорезиненные колесики);\n- максимальный вес штор: не более 60 кг.", colors: ["Белый", "Чёрный"], sizes: [{s:"до 1,5",d:4120,r:5760},{s:"до 2",d:5080,r:7120},{s:"до 2,5",d:6130,r:8580},{s:"до 3",d:7000,r:9810},{s:"до 3,5",d:8270,r:11580},{s:"до 4",d:9240,r:12940},{s:"до 4,5",d:10290,r:14400},{s:"до 5",d:11260,r:15760},{s:"до 5,5",d:12300,r:17230},{s:"до 6",d:13180,r:18450},{s:"до 6,5",d:14230,r:19920},{s:"до 7",d:15190,r:21270},{s:"до 7,5",d:16240,r:22740},{s:"до 8",d:17210,r:24090},{s:"до 8,5",d:18260,r:25560},{s:"до 9",d:19130,r:26780},{s:"до 9,5",d:20180,r:28250},{s:"до 10",d:21150,r:29600},{s:"до 10,5",d:22200,r:31070},{s:"до 11",d:23160,r:32430},{s:"до 11,5",d:24210,r:33900},{s:"до 12",d:25080,r:35120}] },
                "Hidden Tulle (kraab)": { desc: "Карниз NEIROLINKS Motion\n- встраиваемый в ГКЛ или натяжной потолок;\n- раздвижной (от центра или от края);\n- тихая комплектация (прорезиненные колесики);\n- максимальный вес штор: не более 60 кг.", colors: ["Белый", "Чёрный"], sizes: [{s:"до 2",d:14920,r:20880},{s:"до 2,5",d:17200,r:24080},{s:"до 3",d:19390,r:27150},{s:"до 3,5",d:21680,r:30350},{s:"до 4",d:23870,r:33420},{s:"до 4,5",d:26160,r:36620},{s:"до 5",d:28350,r:39690},{s:"до 5,5",d:30630,r:42880},{s:"до 6",d:32820,r:45950},{s:"до 6,5",d:35110,r:49150},{s:"до 7",d:37300,r:52220},{s:"до 7,5",d:39590,r:55420},{s:"до 8",d:41780,r:58490},{s:"до 8,5",d:44060,r:61690},{s:"до 9",d:46260,r:64760},{s:"до 9,5",d:48540,r:67960},{s:"до 10",d:50730,r:71020},{s:"до 10,5",d:53020,r:74220},{s:"до 11",d:55210,r:77290},{s:"до 11,5",d:57500,r:80490},{s:"до 12",d:59690,r:83560}] }
            },
            "Римские электрокарнизы": {
                "Shade (Coulisse)": { desc: "Электрокарниз NEIROLINKS Motion\n- римский, крепление к потолку или стене (опционально);\n- подъёмный механизм складного типа;\n- тихая комплектация;\n- питание от сети 220В;\n- управление проводное: фазное, сухой контакт, RS-485;\n- управление беспроводное: радио, Wi-Fi, ZigBee;\n- способ управления: пультом, со смартфона, интеграция в систему \"умный дом\" в т.ч. голосовое управление, например Алиса.\n- максимальный вес штор: не более 40 кг.", controls: ["Фазное", "Сухой контакт", "RS-485", "Радио", "Wi-Fi", "ZigBee"], sizes: [{s:"до 0,8",d:17580,r:24600},{s:"до 1",d:19310,r:27030},{s:"до 1,2",d:20250,r:28360},{s:"до 1,4",d:21860,r:30610},{s:"до 1,6",d:22810,r:31930},{s:"до 1,8",d:24540,r:34360},{s:"до 2",d:25490,r:35680},{s:"до 2,2",d:27100,r:37940},{s:"до 2,4",d:28040,r:39260},{s:"до 2,6",d:29780,r:41690},{s:"до 2,8",d:30720,r:43010},{s:"до 3",d:32330,r:45260},{s:"до 3,5",d:35490,r:49680},{s:"до 3,75",d:37330,r:52260},{s:"до 4",d:38510,r:53910},{s:"до 4,5",d:42330,r:59260}] },
                "Shade Compact (Coulisse)": { desc: "Электрокарниз NEIROLINKS Motion\n- римский, крепление к потолку или стене (опционально);\n- подъёмный механизм складного типа;\n- тихая комплектация;\n- питание DC 24В;\n- управление проводное: RS-485;\n- управление беспроводное: радио;\n- способ управления: пультом, интеграция в систему \"умный дом\" в т.ч. голосовое управление, например Алиса.\n- максимальный вес штор: не более 3,5 кг.", controls: ["RS-485", "Радио"], sizes: [{s:"до 0,6",d:10950,r:15330},{s:"до 0,8",d:11500,r:16100},{s:"до 1",d:12360,r:17310},{s:"до 1,2",d:12920,r:18090},{s:"до 1,4",d:13720,r:19200},{s:"до 1,6",d:14270,r:19980},{s:"до 1,8",d:15130,r:21190},{s:"до 2",d:15690,r:21960},{s:"до 2,2",d:16490,r:23080},{s:"до 2,4",d:17040,r:23860},{s:"до 2,6",d:17900,r:25060},{s:"до 2,8",d:18460,r:25840},{s:"до 3",d:19250,r:26960}] },
                "Shade Battery (Coulisse)": { desc: "Электрокарниз NEIROLINKS Motion\n- римский, крепление к потолку или стене (опционально);\n- подъёмный механизм складного типа;\n- тихая комплектация;\n- питание от встроенного аккумулятора;\n- управление беспроводное: радио, ZigBee;\n- способ управления: пультом, со смартфона, интеграция в систему \"умный дом\" в т.ч. голосовое управление, например Алиса.\n- максимальный вес штор: не более 24 кг.", controls: ["Радио", "ZigBee"], sizes: [{s:"до 0,8",d:22480,r:31460},{s:"до 1",d:24210,r:33890},{s:"до 1,2",d:25150,r:35220},{s:"до 1,4",d:26760,r:37470},{s:"до 1,6",d:27710,r:38790},{s:"до 1,8",d:29440,r:41220},{s:"до 2",d:30390,r:42540},{s:"до 2,2",d:32000,r:44800},{s:"до 2,4",d:32940,r:46120},{s:"до 2,6",d:34680,r:48550},{s:"до 2,8",d:35620,r:49870},{s:"до 3",d:37230,r:52120},{s:"до 3,5",d:40390,r:56540},{s:"до 3,75",d:42230,r:59120},{s:"до 4",d:43410,r:60770},{s:"до 4,5",d:47230,r:66120}] }
            },
            "Рулонные электрокарнизы": {
                "Roll-up": { desc: "Электрокарниз NEIROLINKS Motion\n- рулонный, крепление к потолку или стене (опционально);\n- тихая комплектация;\n- питание от сети 220В;\n- управление проводное: фазное, сухой контакт, RS-485;\n- управление беспроводное: радио, Wi-Fi, ZigBee;\n- способ управления: пультом, со смартфона, интеграция в систему \"умный дом\" в т.ч. голосовое управление, например Алиса.\n- максимальный вес штор: не более 40 кг.", controls: ["Фазное", "Сухой контакт", "RS-485", "Радио", "Wi-Fi", "ZigBee"], sizes: [{s:"до 0,6",d:12970,r:18160},{s:"до 0,8",d:13170,r:18440},{s:"до 1",d:13370,r:18710},{s:"до 1,2",d:13560,r:18990},{s:"до 1,4",d:13760,r:19260},{s:"до 1,6",d:13950,r:19530},{s:"до 1,8",d:14150,r:19810},{s:"до 2",d:14350,r:20080},{s:"до 2,2",d:14540,r:20360},{s:"до 2,4",d:14740,r:20630},{s:"до 2,6",d:14930,r:20910},{s:"до 2,8",d:15130,r:21180},{s:"до 3",d:15330,r:21460}] },
                "Roll-up Battery": { desc: "Электрокарниз NEIROLINKS Motion\n- рулонный, крепление к потолку или стене (опционально);\n- подъёмный механизм складного типа;\n- тихая комплектация;\n- питание от встроенного аккумулятора;\n- управление беспроводное: радио, ZigBee;\n- способ управления: пультом, со смартфона, интеграция в систему \"умный дом\" в т.ч. голосовое управление, например Алиса.\n- максимальный вес штор: не более 24 кг.", controls: ["Радио", "ZigBee"], sizes: [{s:"до 0,6",d:17870,r:25020},{s:"до 0,8",d:18070,r:25300},{s:"до 1",d:18270,r:25570},{s:"до 1,2",d:18460,r:25850},{s:"до 1,4",d:18660,r:26120},{s:"до 1,6",d:18850,r:26390},{s:"до 1,8",d:19050,r:26670},{s:"до 2",d:19250,r:26940},{s:"до 2,2",d:19440,r:27220},{s:"до 2,4",d:19640,r:27490},{s:"до 2,6",d:19830,r:27770},{s:"до 2,8",d:20030,r:28040},{s:"до 3",d:20230,r:28320}] }
            },
            "Аксессуары": {
                "Кронштейн настенный с крышкой 5-28 см": { colors: [], sizes: [{s:"1 шт",d:370,r:520}] },
                "Кронштейн усиленный настенный 25-35 см": { colors: [], sizes: [{s:"1 шт",d:450,r:630}] },
                "Пульт 1-канальный, кнопочный": { colors: ["Белый", "Чёрный"], sizes: [{s:"1 шт",d:1680,r:2350}] },
                "Пульт 2-канальный, кнопочный": { colors: ["Белый", "Чёрный"], sizes: [{s:"1 шт",d:1870,r:2620}] },
                "Пульт 6-канальный, кнопочный": { colors: ["Белый", "Чёрный"], sizes: [{s:"1 шт",d:1970,r:2760}] },
                "Пульт 16-канальный, кнопочный": { colors: ["Белый", "Чёрный"], sizes: [{s:"1 шт",d:2130,r:2980}] },
                "Настенный радиовыключатель 1-канальный": { colors: ["Белый", "Чёрный"], sizes: [{s:"1 шт",d:1680,r:2350}] },
                "Настенный радиовыключатель 5-канальный": { colors: ["Белый", "Чёрный"], sizes: [{s:"1 шт",d:1970,r:2760}] },
                "Блок управления Wifi to RF контроллер": { colors: [], sizes: [{s:"1 шт",d:5300,r:7420}] }
            }
        };

        const els = {
            cat: document.getElementById('category'), ser: document.getElementById('series'),
            mmInput: document.getElementById('mmInput'), heightInput: document.getElementById('heightInput'),
            desc: document.getElementById('descBox'), descToggle: document.getElementById('descToggle'),
            add: document.getElementById('addBtn'), drivePos: document.getElementById('drivePos'), openType: document.getElementById('openType'), color: document.getElementById('color'),
            controlMethod: document.getElementById('controlMethod'), addAcc: document.getElementById('addAccBtn'), copy: document.getElementById('copyBtn'), clear: document.getElementById('clearBtn'),
            saveOrder: document.getElementById('saveOrderBtn'), tbody: document.getElementById('orderBody'),
            p: { d: document.getElementById('pDealer'), r: document.getElementById('pRRCP'), c: document.getElementById('pClient'), m: document.getElementById('pMargin') },
            t: { d: document.getElementById('tDealer'), c: document.getElementById('tClient'), m: document.getElementById('tMargin') },
            mainBlock: document.getElementById('mainFormBlock'), accBlock: document.getElementById('accBlock'), accBody: document.getElementById('accBody'),
            preview: document.getElementById('previewBlock'), addMainBlock: document.getElementById('addMainBtnBlock'),
            slidingTypeWrapper: document.getElementById('slidingFieldsWrapper'),
            colorWrapper: document.getElementById('colorGroup'), heightWrapper: document.getElementById('heightGroup'),
            maxWidthLabel: document.getElementById('maxWidthLabel'), slidingWarning: document.getElementById('slidingWarning'),
            toast: document.getElementById('toast'), driveGroup: document.getElementById('driveGroup'),
            controlGroup: document.getElementById('controlGroup'), typeGroup: document.getElementById('typeGroup')
        };
        let order = [];

        window.toggleDesc = () => {
            const box = els.desc;
            if(box.style.display === 'none' || box.style.display === '') { box.style.display = 'block'; els.descToggle.textContent = '📕 Скрыть описание'; }
            else { box.style.display = 'none'; els.descToggle.textContent = '📖 Показать описание'; }
        };

        // 🔹 1. Инициализация категорий (без пустого плейсхолдера)
        if(els.cat) {
            Object.keys(CATALOG).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c; opt.textContent = c;
                els.cat.appendChild(opt);
            });
            // ✅ По умолчанию выбрана целевая категория
            els.cat.value = 'Раздвижные электрокарнизы';
        }

        // 🔹 2. Единая функция синхронизации UI под выбранную категорию
        function syncCategoryUI() {
            // Сброс полей ввода
            els.mmInput.value = ''; els.heightInput.value = '';
            els.desc.style.display = 'none'; els.descToggle.style.display = 'none';
            els.descToggle.textContent = '📖 Показать описание';
            els.add.disabled = true; els.drivePos.value = ''; els.openType.value = '';
            els.color.value = ''; els.controlMethod.value = '';
            els.maxWidthLabel.textContent = ''; els.mmInput.removeAttribute('max');
            Object.values(els.p).forEach(e => e.textContent = '0 ₽');
            els.slidingWarning.style.display = 'none';

            // Базовая видимость полей
            els.driveGroup.style.display = 'block'; els.controlGroup.style.display = 'block';
            els.typeGroup.style.display = 'block'; els.colorWrapper.style.display = 'block';
            els.heightWrapper.style.display = 'block';
            document.querySelector('.grid-5').style.gridTemplateColumns = 'repeat(5, 1fr)';

            const cat = els.cat.value;

            if(cat === 'Аксессуары') {
                els.mainBlock.style.display = 'none'; els.accBlock.style.display = 'block';
                els.preview.style.display = 'none'; els.addMainBlock.style.display = 'none';
                els.slidingTypeWrapper.style.display = 'none';
                els.ser.disabled = true; els.ser.innerHTML = '<option value="">—</option>';
                renderAccPanel();
            } else {
                els.mainBlock.style.display = 'block'; els.accBlock.style.display = 'none';
                els.preview.style.display = 'grid'; els.addMainBlock.style.display = 'flex';
                els.slidingTypeWrapper.style.display = 'block';

                if(cat === 'Раздвижные электрокарнизы') {
                    els.typeGroup.style.display = 'block'; els.heightWrapper.style.display = 'none';
                } else if(cat === 'Римские электрокарнизы' || cat === 'Рулонные электрокарнизы') {
                    els.typeGroup.style.display = 'none'; els.colorWrapper.style.display = 'none'; els.heightWrapper.style.display = 'block';
                } else if(cat === 'Ручные карнизы') {
                    els.driveGroup.style.display = 'none'; els.controlGroup.style.display = 'none';
                    els.typeGroup.style.display = 'none'; els.heightWrapper.style.display = 'none';
                    els.colorWrapper.style.display = 'block';
                    document.querySelector('.grid-5').style.gridTemplateColumns = '1fr 1fr';
                }

                // Заполнение списка серий
                els.ser.innerHTML = '<option value="">Выберите серию</option>';
                if(CATALOG[cat]) {
                    Object.keys(CATALOG[cat]).forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s; opt.textContent = s;
                        els.ser.appendChild(opt);
                    });
                }
                els.ser.disabled = false;
            }
            updateSlidingConstraints();
            validateForm();
        }

        // Привязка обработчика изменения категории
        els.cat.addEventListener('change', syncCategoryUI);
        // Первичный запуск логики при загрузке
        syncCategoryUI();

        function getSeriesMaxWidth(seriesName, category) {
            if (!seriesName || !category || !CATALOG[category] || !CATALOG[category][seriesName]) return 12000;
            const sizes = CATALOG[category][seriesName].sizes;
            if (!sizes || sizes.length === 0) return 12000;
            const lastStr = sizes[sizes.length - 1].s;
            const num = parseFloat(lastStr.replace('до ', '').replace(',', '.'));
            return Math.round(num * 1000);
        }
        function parseRangeToMm(rangeStr) { return parseFloat(rangeStr.replace('до ', '').replace(',', '.')) * 1000; }
        function getPriceByMm(sizes, mm) {
            for (let item of sizes) { if (mm <= parseRangeToMm(item.s)) return item; }
            return sizes[sizes.length - 1];
        }
        function updateSlidingWarning() {
            const mm = parseInt(els.mmInput.value) || 0;
            const ser = els.ser.value;
            if (els.cat.value === 'Раздвижные электрокарнизы' && ser === 'Curtain Lite (somfy)' && mm > 4000) {
                els.slidingWarning.textContent = '⚠️ При длине более 4000 мм рекомендуем выбирать электрокарниз из серии Curtain';
                els.slidingWarning.style.display = 'block';
            } else { els.slidingWarning.style.display = 'none'; els.slidingWarning.textContent = ''; }
        }
        function updateSlidingConstraints() {
            if (els.cat.value === 'Раздвижные электрокарнизы') {
                const mm = parseInt(els.mmInput.value) || 0;
                const openSelect = els.openType;
                const edgeOption = openSelect.querySelector('option[value="от края"]');
                if (mm > 6000) {
                    if (openSelect.value === 'от края') { openSelect.value = 'от центра'; validateForm(); }
                    if (edgeOption) { edgeOption.disabled = true; edgeOption.style.display = 'none'; }
                } else {
                    if (edgeOption) { edgeOption.disabled = false; edgeOption.style.display = ''; }
                }
            }
            updateSlidingWarning();
        }

        els.ser.addEventListener('change', () => {
            els.desc.style.display = 'none'; els.descToggle.style.display = 'none'; els.descToggle.textContent = '📖 Показать описание';
            const maxW = getSeriesMaxWidth(els.ser.value, els.cat.value);
            els.mmInput.max = maxW; els.maxWidthLabel.textContent = `Максимальная ширина: ${maxW/1000} м`;
            if(els.ser.value) {
                const data = CATALOG[els.cat.value][els.ser.value];
                if(data.desc) { els.desc.innerHTML = `<strong>${els.ser.value}</strong><br>${data.desc.replace(/\n/g, '<br>')}`; els.descToggle.style.display = 'block'; }
                if(els.cat.value !== 'Ручные карнизы') {
                    els.controlMethod.innerHTML = '<option value="">—</option>';
                    if(data.controls) data.controls.forEach(c => els.controlMethod.innerHTML += `<option value="${c}">${c}</option>`);
                }
            }
            validateForm(); updateSlidingConstraints();
        });

        function validateForm() {
            [els.mmInput, els.heightInput, els.drivePos, els.openType, els.color, els.controlMethod].forEach(el => el && el.classList.remove('error'));
            if (!els.ser.value) return resetPreviewState(); // ✅ Убрана проверка пустой категории
            let itemData, isValid = false;
            const isSliding = els.cat.value === 'Раздвижные электрокарнизы';
            const isRoman = els.cat.value === 'Римские электрокарнизы';
            const isRoller = els.cat.value === 'Рулонные электрокарнизы';
            const isManual = els.cat.value === 'Ручные карнизы';
            const maxW = getSeriesMaxWidth(els.ser.value, els.cat.value);
            
            if (isSliding || isRoman || isRoller || isManual) {
                const mm = parseInt(els.mmInput.value) || 0;
                if (mm < 100) { if(els.mmInput.value) els.mmInput.classList.add('error'); return resetPreviewState(); }
                if (mm > maxW) { els.mmInput.classList.add('error'); return resetPreviewState(); }
                if (isManual) {
                    if (!els.color.value) { els.color.classList.add('error'); return resetPreviewState(); }
                } else {
                    if (!els.drivePos.value || !els.controlMethod.value) return resetPreviewState();
                    if (isSliding && (!els.openType.value || !els.color.value)) return resetPreviewState();
                    if ((isRoman || isRoller) && !els.heightInput.value) { els.heightInput.classList.add('error'); return resetPreviewState(); }
                }
                itemData = getPriceByMm(CATALOG[els.cat.value][els.ser.value].sizes, mm);
                isValid = true;
            } else { return resetPreviewState(); }
            
            if (IS_AGENT) {
                els.p.r.textContent = formatMoney(itemData.r); els.p.c.textContent = formatMoney(itemData.r);
                const agentMargin = Math.round(itemData.r * 0.10);
                els.p.m.textContent = `+${formatMoney(agentMargin)}`; els.p.m.style.color = 'var(--success)';
            } else {
                els.p.d.textContent = formatMoney(itemData.d); els.p.r.textContent = formatMoney(itemData.r);
                els.p.c.textContent = formatMoney(itemData.r);
                els.p.m.textContent = `+${formatMoney(itemData.r - itemData.d)}`; els.p.m.style.color = 'var(--success)';
            }
            els.add.disabled = !isValid;
        }
        function resetPreviewState() { Object.values(els.p).forEach(e => e.textContent = '0 ₽'); els.add.disabled = true; }

        [els.mmInput, els.heightInput, els.drivePos, els.openType, els.color, els.controlMethod].forEach(e => {
            if(e) { e.addEventListener('change', validateForm); e.addEventListener('input', validateForm); }
        });
        els.mmInput.addEventListener('input', updateSlidingConstraints);
        els.mmInput.addEventListener('change', updateSlidingConstraints);

        els.add.addEventListener('click', () => {
            validateForm(); if(els.add.disabled) return;
            const isSliding = els.cat.value === 'Раздвижные электрокарнизы';
            const isRoman = els.cat.value === 'Римские электрокарнизы';
            const isRoller = els.cat.value === 'Рулонные электрокарнизы';
            const isManual = els.cat.value === 'Ручные карнизы';
            let itemData, mmVal;
            if (isSliding || isRoman || isRoller || isManual) { mmVal = parseInt(els.mmInput.value); itemData = getPriceByMm(CATALOG[els.cat.value][els.ser.value].sizes, mmVal); }
            const cleanSeries = els.ser.value.replace(/\s*\(.*?\)/g, '').trim();
            let name;
            if(isSliding) name = `Электрокарниз NEIROLINKS Motion ${cleanSeries} ${mmVal} мм, упр-е ${els.controlMethod.value}, привод ${els.drivePos.value}, раздвижка ${els.openType.value}, ${els.color.value}`;
            else if(isRoman) name = `Электрокарниз NEIROLINKS Motion ${cleanSeries} ${mmVal} мм, упр-е ${els.controlMethod.value}, привод ${els.drivePos.value}, высота ${els.heightInput.value}`;
            else if(isRoller) name = `Электрокарниз NEIROLINKS Motion ${cleanSeries} ${mmVal} мм, упр-е ${els.controlMethod.value}, привод ${els.drivePos.value}, высота ${els.heightInput.value}`;
            else if(isManual) name = `Карниз NEIROLINKS Motion ${cleanSeries} ${mmVal} мм, ${els.color.value}`;
            order.push({ ser: els.ser.value, name, q: 1, d: itemData.d, r: itemData.r, dis: 0, isSliding });
            renderTable();
            els.mmInput.value = ''; els.heightInput.value = ''; els.drivePos.value = ''; els.openType.value = ''; els.color.value = ''; els.controlMethod.value = '';
            validateForm();
        });

        function renderAccPanel() {
            els.accBody.innerHTML = '';
            for(let name in CATALOG["Аксессуары"]) {
                const item = CATALOG["Аксессуары"][name]; const price = item.sizes[0];
                const colorHtml = item.colors.length > 0 ? `<select class="acc-color"><option value="">—</option>${item.colors.map(c => `<option value="${c}">${c}</option>`).join('')}</select>` : '<span style="color:#94a3b8">—</span>';
                const dealerTdStyle = IS_AGENT ? 'display:none;' : '';
                els.accBody.innerHTML += `<tr><td style="font-weight:500;">${name}</td><td style="text-align: center;">${colorHtml}</td><td style="text-align: center; ${dealerTdStyle}">${formatMoney(price.d)}</td><td style="text-align: center;">${formatMoney(price.r)}</td><td style="text-align: center;"><input type="number" class="acc-input acc-qty" min="0" value="0" data-d="${price.d}" data-r="${price.r}"></td></tr>`;
            }
        }
        els.addAcc.addEventListener('click', () => {
            let hasError = false;
            els.accBody.querySelectorAll('tr').forEach(row => {
                if((parseInt(row.querySelector('.acc-qty').value) || 0) > 0) {
                    const sel = row.querySelector('.acc-color');
                    if(sel && !sel.value) { hasError = true; sel.style.border = '2px solid var(--danger)'; setTimeout(()=>sel.style.border='', 3000); }
                }
            });
            if(hasError) { showToast('Необходимо указать цвет!'); return; }
            let added = false;
            els.accBody.querySelectorAll('tr').forEach(row => {
                const q = parseInt(row.querySelector('.acc-qty').value) || 0;
                if(q > 0) {
                    const colorSel = row.querySelector('.acc-color');
                    const d = parseInt(row.querySelector('.acc-qty').dataset.d), r = parseInt(row.querySelector('.acc-qty').dataset.r);
                    const name = colorSel && colorSel.value ? `${row.cells[0].textContent}, ${colorSel.value}` : row.cells[0].textContent;
                    order.push({ ser: "Аксессуары", name, s: "1 шт", q, d, r, dis: 0, isSliding: false });
                    row.querySelector('.acc-qty').value = 0; if(colorSel) colorSel.selectedIndex = 0; added = true;
                }
            });
            if(added) { renderTable(); showToast('Аксессуары добавлены в заказ', true); }
        });

        window.removeItem = (i) => { order.splice(i,1); renderTable(); };
        window.updateRow = (i, field, value) => {
            if(field === 'q') order[i].q = Math.max(1, parseInt(value)||1);
            if(field === 'dis') order[i].dis = Math.min(IS_AGENT ? 10 : 100, Math.max(0, parseFloat(value)||0));
            const clUnit = Math.round(order[i].r * (1 - order[i].dis/100));
            const margin = IS_AGENT ? Math.round(clUnit * 0.10) : (clUnit - order[i].d);
            const totalClient = clUnit * order[i].q;
            const totalMargin = margin * order[i].q;
            const row = document.querySelectorAll('#orderBody tr')[i];
            if(row) {
                row.querySelector('.row-client').textContent = formatMoney(totalClient);
                const mEl = row.querySelector('.row-margin');
                mEl.textContent = totalMargin >= 0 ? `+${formatMoney(totalMargin)}` : `-${formatMoney(Math.abs(totalMargin))}`;
                mEl.style.color = totalMargin >= 0 ? 'var(--success)' : 'var(--danger)';
            }
            calcTotals();
        }

        function renderTable() {
            els.tbody.innerHTML = '';
            order.forEach((item, i) => {
                const clUnit = Math.round(item.r * (1 - item.dis/100));
                const margin = IS_AGENT ? Math.round(clUnit * 0.10) : (clUnit - item.d);
                const totalClient = clUnit * item.q;
                const totalMargin = margin * item.q;
                const dealerTd = IS_AGENT ? '' : `<td class="text-center">${formatMoney(item.d)}</td>`;
                els.tbody.innerHTML += `<tr data-idx="${i}"><td style="font-weight:500;">${item.name}</td><td class="text-center"><input type="number" class="table-input" value="${item.q}" min="1" onchange="updateRow(${i}, 'q', this.value)"></td>${dealerTd}<td class="text-center">${formatMoney(item.r)}</td><td class="text-center"><input type="number" class="table-input" value="${item.dis}" min="0" max="${IS_AGENT ? 10 : 100}" step="1" onchange="updateRow(${i}, 'dis', this.value)"></td><td class="row-client" style="font-weight:600; color:var(--primary-accent); text-align:center;">${formatMoney(totalClient)}</td><td class="row-margin text-center" style="color:${totalMargin>=0?'var(--success)':'var(--danger)'};">${totalMargin>=0?'+':''}${formatMoney(totalMargin)}</td><td class="text-center"><button class="remove-btn" onclick="removeItem(${i})">✖</button></td></tr>`;
            });
            calcTotals();
        }
        function calcTotals() {
            let d=0, c=0, m=0;
            order.forEach(x => {
                const clUnit = Math.round(x.r * (1 - x.dis/100));
                d += x.d*x.q; c += clUnit * x.q;
                const margin = IS_AGENT ? (clUnit * 0.10) : (clUnit - x.d);
                m += margin * x.q;
            });
            els.t.d.textContent = formatMoney(d); els.t.c.textContent = formatMoney(c);
            els.t.m.textContent = m>=0 ? `+${formatMoney(m)}` : `-${formatMoney(Math.abs(m))}`;
            els.t.m.style.color = m>=0 ? 'var(--success)' : 'var(--danger)';
        }
        function formatMoney(n) { return new Intl.NumberFormat('ru-RU', { style:'currency', currency:'RUB', maximumFractionDigits:0 }).format(n); }
        function showToast(msg, isSuccess = false) { els.toast.textContent = isSuccess ? `✅ ${msg}` : `⚠️ ${msg}`; els.toast.className = isSuccess ? 'success show' : 'show'; setTimeout(() => els.toast.classList.remove('show'), 3000); }

        // 🔹 Очистка заказа без сброса категории
        els.clear.addEventListener('click', () => {
            if(confirm('Очистить весь заказ?')) {
                order = []; renderTable();
                els.mmInput.value = ''; els.heightInput.value = ''; els.drivePos.value = '';
                els.openType.value = ''; els.color.value = ''; els.controlMethod.value = '';
                els.desc.style.display = 'none'; els.descToggle.style.display = 'none';
                validateForm();
            }
        });

        els.copy.addEventListener('click', () => {
            if(order.length === 0) return showToast('Сначала добавьте позиции в заказ');
            let txt = `📋 СПЕЦИФИКАЦИЯ\n📍 ${USER_COMPANY}`;
            if (USER_PHONE || USER_EMAIL) {
                let contacts = [];
                if (USER_PHONE) contacts.push(USER_PHONE);
                if (USER_EMAIL) contacts.push(USER_EMAIL);
                txt += `\n📞 ${contacts.join(' | ')}`;
            }
            txt += `\n━━━━━━━━━━━━━━━━━━━━━━\n`;
            let totalDiscountSum = 0;
            let grandTotal = 0;
            order.forEach((x, i) => {
                const dis = parseFloat(x.dis) || 0;
                const unitPriceOriginal = x.r;
                const unitPriceDiscounted = Math.round(unitPriceOriginal * (1 - dis / 100));
                const lineTotalOriginal = unitPriceOriginal * x.q;
                const lineTotalDiscounted = unitPriceDiscounted * x.q;
                totalDiscountSum += (unitPriceOriginal - unitPriceDiscounted) * x.q;
                grandTotal += lineTotalDiscounted;
                txt += `${i+1}. ${x.name}\nКол-во: ${x.q}`;
                if (dis > 0) txt += ` | Скидка: ${dis}%`;
                txt += `\n`;
                if (dis > 0) { txt += `Цена: ${formatMoney(lineTotalOriginal)}\nЦена со скидкой: ${formatMoney(lineTotalDiscounted)}\n`; }
                else { txt += `Цена: ${formatMoney(lineTotalDiscounted)}\n`; }
                txt += `\n`;
            });
            txt += `━━━━━━━━━━━━━━━━━━━━━━\n`;
            if (totalDiscountSum > 0) txt += `💸 Скидка: ${formatMoney(totalDiscountSum)}\n`;
            txt += `💰 ИТОГО: ${formatMoney(grandTotal)}\n`;
            txt += `📅 Дата предложения: ${new Date().toLocaleDateString('ru-RU')}`;
            navigator.clipboard.writeText(txt).then(() => showToast('Спецификация скопирована! Готово к отправке', true));
        });

        els.saveOrder.addEventListener('click', async () => {
            if (order.length === 0) return showToast('Корзина пуста, нечего сохранять', false);
            els.saveOrder.disabled = true; els.saveOrder.textContent = '⏳ Сохранение...';
            try {
                const res = await fetch('/api/save_order.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        items: order,
                        totalDealer: parseFloat(els.t.d.textContent.replace(/[^\d.-]/g, '')),
                        totalClient: parseFloat(els.t.c.textContent.replace(/[^\d.-]/g, '')),
                        totalMargin: parseFloat(els.t.m.textContent.replace(/[^\d.-]/g, ''))
                    })
                });
                const text = await res.text();
                let data;
                try {
                    const jsonStart = text.indexOf('{');
                    const jsonEnd = text.lastIndexOf('}');
                    if (jsonStart === -1 || jsonEnd === -1) throw new Error('JSON not found');
                    data = JSON.parse(text.substring(jsonStart, jsonEnd + 1));
                } catch (e) {
                    console.error('Raw response:', text);
                    throw new Error('Некорректный ответ сервера');
                }
                if (data.success) {
                    showToast(`✅ Заказ #${data.order_id || data.id} успешно сохранён!`, true);
                    window.location.href = '/cabinet/cabinet.php';
                } else {
                    throw new Error(data.error || data.message || 'Неизвестная ошибка');
                }
            } catch (e) {
                console.error('Save error:', e);
                showToast('❌ ' + e.message, false);
            } finally {
                els.saveOrder.disabled = false;
                els.saveOrder.textContent = '➕ Добавить в заказ';
            }
        });
    });
    </script>
</body>
</html>