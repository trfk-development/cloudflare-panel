<?php
/**
 * Security Rules Manager
 * Управление правилами безопасности и блокировками
 * 
 * Функции:
 * - Блокировка bad bots
 * - Блокировка IP адресов
 * - Геоблокировка
 * - Защита от прямого доступа (только поисковики)
 * - Массовое применение правил
 */

require_once 'header.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Получаем группы и домены
$groupsStmt = $pdo->prepare("SELECT * FROM groups WHERE user_id = ? ORDER BY name");
$groupsStmt->execute([$userId]);
$groups = $groupsStmt->fetchAll();

$domainsStmt = $pdo->prepare("
    SELECT ca.*, g.name as group_name 
    FROM cloudflare_accounts ca 
    LEFT JOIN groups g ON ca.group_id = g.id 
    WHERE ca.user_id = ? 
    ORDER BY ca.domain
");
$domainsStmt->execute([$userId]);
$domains = $domainsStmt->fetchAll();

// Получаем статистику блокировок
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT domain_id) as protected_domains,
        SUM(CASE WHEN rule_type = 'bad_bot' THEN 1 ELSE 0 END) as bot_rules,
        SUM(CASE WHEN rule_type = 'ip_block' THEN 1 ELSE 0 END) as ip_rules,
        SUM(CASE WHEN rule_type = 'geo_block' THEN 1 ELSE 0 END) as geo_rules,
        SUM(CASE WHEN rule_type = 'referrer_only' THEN 1 ELSE 0 END) as referrer_rules
    FROM security_rules 
    WHERE user_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление безопасностью - CloudPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .rule-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .rule-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        .stat-box h3 {
            font-size: 2.5rem;
            margin-bottom: 5px;
        }
        .stat-box p {
            margin: 0;
            opacity: 0.9;
        }
        .protection-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin: 2px;
        }
        .protection-active {
            background: #d4edda;
            color: #155724;
        }
        .protection-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .country-select {
            max-height: 300px;
            overflow-y: auto;
        }
        .worker-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h1><i class="fas fa-shield-alt"></i> Управление безопасностью</h1>
                    <p class="text-muted">Защита от ботов, вредоносных IP и нежелательного трафика</p>
                </div>
            </div>

            <!-- Статистика -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-box">
                        <h3><?php echo $stats['protected_domains'] ?? 0; ?></h3>
                        <p>Защищенных доменов</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <h3><?php echo $stats['bot_rules'] ?? 0; ?></h3>
                        <p>Правил блокировки ботов</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <h3><?php echo $stats['ip_rules'] ?? 0; ?></h3>
                        <p>Заблокированных IP</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <h3><?php echo $stats['geo_rules'] ?? 0; ?></h3>
                        <p>Правил геоблокировки</p>
                    </div>
                </div>
            </div>

            <!-- Навигация по табам -->
            <ul class="nav nav-tabs mb-4" id="securityTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="bot-blocker-tab" data-bs-toggle="tab" data-bs-target="#bot-blocker" type="button">
                        <i class="fas fa-robot"></i> Блокировка ботов
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="ip-blocker-tab" data-bs-toggle="tab" data-bs-target="#ip-blocker" type="button">
                        <i class="fas fa-ban"></i> Блокировка IP
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="geo-blocker-tab" data-bs-toggle="tab" data-bs-target="#geo-blocker" type="button">
                        <i class="fas fa-globe"></i> Геоблокировка
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="referrer-only-tab" data-bs-toggle="tab" data-bs-target="#referrer-only" type="button">
                        <i class="fas fa-search"></i> Только поисковики
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="worker-manager-tab" data-bs-toggle="tab" data-bs-target="#worker-manager" type="button">
                        <i class="fas fa-cog"></i> Cloudflare Workers
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="securityTabsContent">
                <!-- Блокировка ботов -->
                <div class="tab-pane fade show active" id="bot-blocker" role="tabpanel">
                    <div class="rule-card">
                        <h4><i class="fas fa-robot"></i> Блокировка плохих ботов</h4>
                        <p class="text-muted">На основе списка из <a href="https://github.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker" target="_blank">nginx-ultimate-bad-bot-blocker</a></p>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h5>Быстрая настройка</h5>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="blockAllBots">
                                    <label class="form-check-label" for="blockAllBots">
                                        Блокировать все известные плохие боты (рекомендуется)
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="blockSpamReferrers">
                                    <label class="form-check-label" for="blockSpamReferrers">
                                        Блокировать спам-реферреры
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="blockVulnScanners">
                                    <label class="form-check-label" for="blockVulnScanners">
                                        Блокировать сканеры уязвимостей
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="blockMalware">
                                    <label class="form-check-label" for="blockMalware">
                                        Блокировать malware/adware/ransomware
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Применить к:</h5>
                                <select class="form-select mb-2" id="botBlockerScope">
                                    <option value="all">Все домены</option>
                                    <option value="group">Выбранная группа</option>
                                    <option value="selected">Выбранные домены</option>
                                </select>
                                
                                <select class="form-select mb-2" id="botBlockerGroup" style="display: none;">
                                    <option value="">Выберите группу</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <div id="botBlockerDomains" style="display: none; max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($domains as $domain): ?>
                                        <div class="form-check">
                                            <input class="form-check-input domain-checkbox" type="checkbox" value="<?php echo $domain['id']; ?>" id="domain-<?php echo $domain['id']; ?>">
                                            <label class="form-check-label" for="domain-<?php echo $domain['id']; ?>">
                                                <?php echo htmlspecialchars($domain['domain']); ?>
                                                <?php if ($domain['group_name']): ?>
                                                    <small class="text-muted">(<?php echo htmlspecialchars($domain['group_name']); ?>)</small>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <button class="btn btn-primary w-100 mt-3" onclick="applyBotBlocker()">
                                    <i class="fas fa-shield-alt"></i> Применить защиту от ботов
                                </button>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> <strong>Примечание:</strong> Список ботов будет автоматически обновляться раз в неделю из официального репозитория.
                        </div>
                    </div>
                </div>

                <!-- Блокировка IP -->
                <div class="tab-pane fade" id="ip-blocker" role="tabpanel">
                    <div class="rule-card">
                        <h4><i class="fas fa-ban"></i> Блокировка IP адресов</h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Добавить IP для блокировки</h5>
                                <textarea class="form-control mb-2" rows="10" id="ipBlockList" placeholder="Один IP или диапазон на строку:&#10;192.168.1.1&#10;10.0.0.0/8&#10;2001:db8::/32"></textarea>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="importKnownBadIps">
                                    <label class="form-check-label" for="importKnownBadIps">
                                        Импортировать известные вредоносные IP (список обновляется)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Применить к:</h5>
                                <select class="form-select mb-2" id="ipBlockerScope">
                                    <option value="all">Все домены</option>
                                    <option value="group">Выбранная группа</option>
                                    <option value="selected">Выбранные домены</option>
                                </select>
                                
                                <select class="form-select mb-2" id="ipBlockerGroup" style="display: none;">
                                    <option value="">Выберите группу</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <button class="btn btn-primary w-100 mt-3" onclick="applyIPBlocker()">
                                    <i class="fas fa-ban"></i> Применить блокировку IP
                                </button>
                                
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-exclamation-triangle"></i> Будьте осторожны! Неправильная блокировка может заблокировать легитимных пользователей.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Геоблокировка -->
                <div class="tab-pane fade" id="geo-blocker" role="tabpanel">
                    <div class="rule-card">
                        <h4><i class="fas fa-globe"></i> Географическая блокировка</h4>
                        <p class="text-muted">Разрешите или заблокируйте доступ по странам</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Режим блокировки</h5>
                                <div class="btn-group w-100 mb-3" role="group">
                                    <input type="radio" class="btn-check" name="geoMode" id="geoWhitelist" value="whitelist" checked>
                                    <label class="btn btn-outline-success" for="geoWhitelist">
                                        <i class="fas fa-check"></i> Whitelist (только разрешенные)
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="geoMode" id="geoBlacklist" value="blacklist">
                                    <label class="btn btn-outline-danger" for="geoBlacklist">
                                        <i class="fas fa-times"></i> Blacklist (заблокировать выбранные)
                                    </label>
                                </div>
                                
                                <h5>Выберите страны</h5>
                                <input type="text" class="form-control mb-2" id="countrySearch" placeholder="Поиск стран...">
                                <div class="country-select border rounded p-2" id="countryList">
                                    <!-- Будет заполнено JavaScript -->
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Выбранные страны (<span id="selectedCountCount">0</span>)</h5>
                                <div id="selectedCountries" class="border rounded p-2 mb-3" style="min-height: 100px;">
                                    <p class="text-muted text-center">Выберите страны слева</p>
                                </div>
                                
                                <h5>Применить к:</h5>
                                <select class="form-select mb-2" id="geoBlockerScope">
                                    <option value="all">Все домены</option>
                                    <option value="group">Выбранная группа</option>
                                    <option value="selected">Выбранные домены</option>
                                </select>
                                
                                <button class="btn btn-primary w-100 mt-3" onclick="applyGeoBlocker()">
                                    <i class="fas fa-globe"></i> Применить геоблокировку
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Только поисковики -->
                <div class="tab-pane fade" id="referrer-only" role="tabpanel">
                    <div class="rule-card">
                        <h4><i class="fas fa-search"></i> Доступ только через поисковики</h4>
                        <p class="text-muted">Пользователи смогут зайти на сайт только через поисковые системы или с определенных реферреров</p>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Внимание!</strong> Эта защита заблокирует прямой доступ к сайту. Используйте с осторожностью!
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Разрешенные источники</h5>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="allowGoogle" checked>
                                    <label class="form-check-label" for="allowGoogle">
                                        <i class="fab fa-google"></i> Google
                                    </label>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="allowYandex" checked>
                                    <label class="form-check-label" for="allowYandex">
                                        <i class="fab fa-yandex"></i> Yandex
                                    </label>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="allowBing" checked>
                                    <label class="form-check-label" for="allowBing">
                                        <i class="fab fa-microsoft"></i> Bing
                                    </label>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="allowDuckDuckGo" checked>
                                    <label class="form-check-label" for="allowDuckDuckGo">
                                        DuckDuckGo
                                    </label>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="allowBaidu">
                                    <label class="form-check-label" for="allowBaidu">
                                        Baidu
                                    </label>
                                </div>
                                
                                <hr>
                                
                                <h6>Дополнительные разрешенные домены</h6>
                                <textarea class="form-control mb-2" rows="5" id="customReferrers" placeholder="Один домен на строку:&#10;facebook.com&#10;twitter.com&#10;instagram.com"></textarea>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="allowEmpty">
                                    <label class="form-check-label" for="allowEmpty">
                                        Разрешить пустой referrer (закладки, прямой ввод)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Настройки защиты</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">Действие при блокировке:</label>
                                    <select class="form-select" id="referrerAction">
                                        <option value="block">Полная блокировка (403)</option>
                                        <option value="challenge">Challenge (проверка браузера)</option>
                                        <option value="redirect">Редирект на главную</option>
                                        <option value="custom">Кастомная страница</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3" id="customPageDiv" style="display: none;">
                                    <label class="form-label">URL кастомной страницы:</label>
                                    <input type="text" class="form-control" id="customPageUrl" placeholder="https://example.com/access-denied">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Исключения (URLs):</label>
                                    <textarea class="form-control" rows="3" id="referrerExceptions" placeholder="URLs которые будут доступны всегда:&#10;/api/*&#10;/webhooks/*&#10;/public/*"></textarea>
                                    <small class="text-muted">Поддерживаются wildcard (*)</small>
                                </div>
                                
                                <h5>Применить к:</h5>
                                <select class="form-select mb-2" id="referrerScope">
                                    <option value="all">Все домены</option>
                                    <option value="group">Выбранная группа</option>
                                    <option value="selected">Выбранные домены</option>
                                </select>
                                
                                <button class="btn btn-primary w-100 mt-3" onclick="applyReferrerOnly()">
                                    <i class="fas fa-shield-alt"></i> Применить защиту
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cloudflare Workers Manager -->
                <div class="tab-pane fade" id="worker-manager" role="tabpanel">
                    <div class="rule-card">
                        <h4><i class="fas fa-cog"></i> Cloudflare Workers - Продвинутая защита</h4>
                        <p class="text-muted">Создайте и примените кастомные Workers для максимальной защиты</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Готовые шаблоны Worker</h5>
                                
                                <div class="list-group mb-3">
                                    <button class="list-group-item list-group-item-action" onclick="loadWorkerTemplate('advanced-protection')">
                                        <strong>Полная защита</strong>
                                        <p class="mb-0 small">Боты + IP + Geo + Referrer блокировка</p>
                                    </button>
                                    <button class="list-group-item list-group-item-action" onclick="loadWorkerTemplate('bot-only')">
                                        <strong>Только боты</strong>
                                        <p class="mb-0 small">Блокировка известных плохих ботов</p>
                                    </button>
                                    <button class="list-group-item list-group-item-action" onclick="loadWorkerTemplate('geo-only')">
                                        <strong>Только геоблокировка</strong>
                                        <p class="mb-0 small">Блокировка по странам</p>
                                    </button>
                                    <button class="list-group-item list-group-item-action" onclick="loadWorkerTemplate('referrer-only')">
                                        <strong>Только реферреры</strong>
                                        <p class="mb-0 small">Доступ только через поисковики</p>
                                    </button>
                                    <button class="list-group-item list-group-item-action" onclick="loadWorkerTemplate('rate-limit')">
                                        <strong>Rate Limiting</strong>
                                        <p class="mb-0 small">Ограничение запросов</p>
                                    </button>
                                </div>
                                
                                <h5>Или создайте свой</h5>
                                <button class="btn btn-secondary w-100" onclick="showCustomWorker()">
                                    <i class="fas fa-code"></i> Кастомный Worker
                                </button>
                            </div>
                            <div class="col-md-6">
                                <h5>Предпросмотр Worker кода</h5>
                                <div class="worker-preview" id="workerPreview">
                                    <p class="text-muted">Выберите шаблон слева для просмотра</p>
                                </div>
                                
                                <h5 class="mt-3">Применить Worker</h5>
                                <select class="form-select mb-2" id="workerScope">
                                    <option value="all">Все домены</option>
                                    <option value="group">Выбранная группа</option>
                                    <option value="selected">Выбранные домены</option>
                                </select>
                                
                                <input type="text" class="form-control mb-2" id="workerRoute" placeholder="Route pattern: *example.com/*" value="*">
                                
                                <button class="btn btn-primary w-100" onclick="deployWorker()">
                                    <i class="fas fa-rocket"></i> Развернуть Worker
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="security_rules.js"></script>
</body>
</html>

