<?php
/**
 * Security Rules API
 * API для управления правилами безопасности
 */

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if (!$action) {
        throw new Exception('Не указано действие');
    }
    
    switch ($action) {
        case 'apply_bot_blocker':
            echo json_encode(applyBotBlocker($pdo, $userId, $_POST));
            break;
            
        case 'apply_ip_blocker':
            echo json_encode(applyIPBlocker($pdo, $userId, $_POST));
            break;
            
        case 'apply_geo_blocker':
            echo json_encode(applyGeoBlocker($pdo, $userId, $_POST));
            break;
            
        case 'apply_referrer_only':
            echo json_encode(applyReferrerOnly($pdo, $userId, $_POST));
            break;
            
        case 'get_worker_template':
            echo json_encode(getWorkerTemplate($_GET['template']));
            break;
            
        case 'deploy_worker':
            echo json_encode(deployWorker($pdo, $userId, $_POST));
            break;
            
        default:
            throw new Exception('Неизвестное действие');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Применить блокировку ботов
 */
function applyBotBlocker($pdo, $userId, $data) {
    $rules = $data['rules'] ?? [];
    $scope = $data['scope'] ?? [];
    
    // Получаем список доменов
    $domainIds = getScopeDomains($pdo, $userId, $scope);
    
    if (empty($domainIds)) {
        return ['success' => false, 'error' => 'Нет доменов для применения'];
    }
    
    $applied = 0;
    $proxies = getProxies($pdo, $userId);
    
    // Загружаем список bad bots
    $badBots = loadBadBotsList($rules);
    
    foreach ($domainIds as $domainId) {
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain || !$domain['zone_id']) continue;
        
        // Создаем Firewall Rule для блокировки ботов
        $expression = buildBotBlockExpression($badBots);
        
        $ruleData = [
            'action' => 'block',
            'description' => 'Auto Bot Blocker - CloudPanel',
            'filter' => [
                'expression' => $expression,
                'paused' => false
            ]
        ];
        
        $response = cloudflareApiRequestDetailed(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "zones/{$domain['zone_id']}/firewall/rules",
            'POST',
            [$ruleData],
            $proxies,
            $userId
        );
        
        if ($response['success']) {
            $applied++;
            
            // Сохраняем в БД
            saveSecurityRule($pdo, $userId, $domainId, 'bad_bot', $expression);
        }
    }
    
    return [
        'success' => true,
        'applied' => $applied,
        'total' => count($domainIds)
    ];
}

/**
 * Применить блокировку IP
 */
function applyIPBlocker($pdo, $userId, $data) {
    $ips = $data['ips'] ?? [];
    $importKnown = $data['importKnown'] ?? false;
    $scope = $data['scope'] ?? [];
    
    if ($importKnown) {
        $ips = array_merge($ips, loadKnownBadIPs());
    }
    
    $domainIds = getScopeDomains($pdo, $userId, $scope);
    
    if (empty($domainIds) || empty($ips)) {
        return ['success' => false, 'error' => 'Нет данных для применения'];
    }
    
    $applied = 0;
    $proxies = getProxies($pdo, $userId);
    
    foreach ($domainIds as $domainId) {
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain || !$domain['zone_id']) continue;
        
        // Создаем правила блокировки для каждого IP/диапазона
        foreach ($ips as $ip) {
            $ip = trim($ip);
            if (empty($ip)) continue;
            
            $accessRule = [
                'mode' => 'block',
                'configuration' => [
                    'target' => strpos($ip, '/') !== false ? 'ip_range' : 'ip',
                    'value' => $ip
                ],
                'notes' => 'Auto IP Block - CloudPanel'
            ];
            
            $response = cloudflareApiRequestDetailed(
                $pdo,
                $domain['email'],
                $domain['api_key'],
                "zones/{$domain['zone_id']}/firewall/access_rules/rules",
                'POST',
                $accessRule,
                $proxies,
                $userId
            );
            
            if ($response['success']) {
                saveSecurityRule($pdo, $userId, $domainId, 'ip_block', $ip);
            }
        }
        
        $applied++;
    }
    
    return [
        'success' => true,
        'applied' => $applied,
        'total' => count($domainIds),
        'ips_blocked' => count($ips)
    ];
}

/**
 * Применить геоблокировку
 */
function applyGeoBlocker($pdo, $userId, $data) {
    $mode = $data['mode'] ?? 'whitelist';
    $countries = $data['countries'] ?? [];
    $scope = $data['scope'] ?? [];
    
    $domainIds = getScopeDomains($pdo, $userId, $scope);
    
    if (empty($domainIds) || empty($countries)) {
        return ['success' => false, 'error' => 'Нет данных для применения'];
    }
    
    $applied = 0;
    $proxies = getProxies($pdo, $userId);
    
    // Строим выражение
    if ($mode === 'whitelist') {
        // Блокировать все кроме выбранных
        $expression = '(not ip.geoip.country in {' . implode(' ', array_map(function($c) { return '"' . $c . '"'; }, $countries)) . '})';
    } else {
        // Блокировать только выбранные
        $expression = '(ip.geoip.country in {' . implode(' ', array_map(function($c) { return '"' . $c . '"'; }, $countries)) . '})';
    }
    
    foreach ($domainIds as $domainId) {
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain || !$domain['zone_id']) continue;
        
        $ruleData = [
            'action' => 'block',
            'description' => "Auto Geo Block ({$mode}) - CloudPanel",
            'filter' => [
                'expression' => $expression,
                'paused' => false
            ]
        ];
        
        $response = cloudflareApiRequestDetailed(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "zones/{$domain['zone_id']}/firewall/rules",
            'POST',
            [$ruleData],
            $proxies,
            $userId
        );
        
        if ($response['success']) {
            $applied++;
            saveSecurityRule($pdo, $userId, $domainId, 'geo_block', json_encode(['mode' => $mode, 'countries' => $countries]));
        }
    }
    
    return [
        'success' => true,
        'applied' => $applied,
        'total' => count($domainIds),
        'mode' => $mode,
        'countries_count' => count($countries)
    ];
}

/**
 * Применить защиту "только реферреры"
 */
function applyReferrerOnly($pdo, $userId, $data) {
    $allowedReferrers = $data['allowedReferrers'] ?? [];
    $action = $data['action'] ?? 'block';
    $customPageUrl = $data['customPageUrl'] ?? '';
    $exceptions = $data['exceptions'] ?? [];
    $scope = $data['scope'] ?? [];
    
    $domainIds = getScopeDomains($pdo, $userId, $scope);
    
    if (empty($domainIds)) {
        return ['success' => false, 'error' => 'Нет доменов для применения'];
    }
    
    $applied = 0;
    $proxies = getProxies($pdo, $userId);
    
    // Строим выражение для проверки referrer
    $expression = buildReferrerExpression($allowedReferrers, $exceptions);
    
    foreach ($domainIds as $domainId) {
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain || !$domain['zone_id']) continue;
        
        $ruleAction = match($action) {
            'challenge' => 'challenge',
            'redirect' => 'redirect',
            default => 'block'
        };
        
        $ruleData = [
            'action' => $ruleAction,
            'description' => 'Auto Referrer Protection - CloudPanel',
            'filter' => [
                'expression' => $expression,
                'paused' => false
            ]
        ];
        
        if ($action === 'redirect' && !empty($customPageUrl)) {
            $ruleData['action_parameters'] = [
                'uri' => [
                    'origin' => $customPageUrl
                ]
            ];
        }
        
        $response = cloudflareApiRequestDetailed(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "zones/{$domain['zone_id']}/firewall/rules",
            'POST',
            [$ruleData],
            $proxies,
            $userId
        );
        
        if ($response['success']) {
            $applied++;
            saveSecurityRule($pdo, $userId, $domainId, 'referrer_only', json_encode($allowedReferrers));
        }
    }
    
    return [
        'success' => true,
        'applied' => $applied,
        'total' => count($domainIds)
    ];
}

/**
 * Получить шаблон Worker
 */
function getWorkerTemplate($template) {
    $templates = [
        'advanced-protection' => file_get_contents(__DIR__ . '/worker_templates/advanced-protection.js'),
        'bot-only' => file_get_contents(__DIR__ . '/worker_templates/bot-only.js'),
        'geo-only' => file_get_contents(__DIR__ . '/worker_templates/geo-only.js'),
        'referrer-only' => file_get_contents(__DIR__ . '/worker_templates/referrer-only.js'),
        'rate-limit' => file_get_contents(__DIR__ . '/worker_templates/rate-limit.js')
    ];
    
    if (!isset($templates[$template])) {
        return ['success' => false, 'error' => 'Шаблон не найден'];
    }
    
    return [
        'success' => true,
        'code' => $templates[$template],
        'template' => $template
    ];
}

/**
 * Развернуть Worker
 */
function deployWorker($pdo, $userId, $data) {
    $template = $data['template'] ?? '';
    $route = $data['route'] ?? '*';
    $scope = $data['scope'] ?? [];
    
    $workerData = getWorkerTemplate($template);
    if (!$workerData['success']) {
        return $workerData;
    }
    
    $domainIds = getScopeDomains($pdo, $userId, $scope);
    
    if (empty($domainIds)) {
        return ['success' => false, 'error' => 'Нет доменов для применения'];
    }
    
    $applied = 0;
    $proxies = getProxies($pdo, $userId);
    
    foreach ($domainIds as $domainId) {
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain || !$domain['zone_id']) continue;
        
        // Создаем Worker script
        $scriptName = "security-{$template}-" . uniqid();
        
        // Загружаем скрипт Worker
        $uploadResponse = cloudflareApiRequestDetailed(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "accounts/{$domain['account_id']}/workers/scripts/{$scriptName}",
            'PUT',
            ['script' => $workerData['code']],
            $proxies,
            $userId
        );
        
        if ($uploadResponse['success']) {
            // Создаем route для Worker
            $routePattern = str_replace('*', $domain['domain'], $route);
            
            $routeResponse = cloudflareApiRequestDetailed(
                $pdo,
                $domain['email'],
                $domain['api_key'],
                "zones/{$domain['zone_id']}/workers/routes",
                'POST',
                [
                    'pattern' => $routePattern,
                    'script' => $scriptName
                ],
                $proxies,
                $userId
            );
            
            if ($routeResponse['success']) {
                $applied++;
                saveSecurityRule($pdo, $userId, $domainId, 'worker', json_encode([
                    'template' => $template,
                    'script_name' => $scriptName,
                    'route' => $routePattern
                ]));
            }
        }
    }
    
    return [
        'success' => true,
        'applied' => $applied,
        'total' => count($domainIds),
        'template' => $template
    ];
}

/**
 * Вспомогательные функции
 */

function getScopeDomains($pdo, $userId, $scope) {
    $type = $scope['type'] ?? 'all';
    $domainIds = [];
    
    if ($type === 'all') {
        $stmt = $pdo->prepare("SELECT id FROM cloudflare_accounts WHERE user_id = ?");
        $stmt->execute([$userId]);
        $domainIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($type === 'group') {
        $groupId = $scope['groupId'] ?? null;
        $stmt = $pdo->prepare("SELECT id FROM cloudflare_accounts WHERE user_id = ? AND group_id = ?");
        $stmt->execute([$userId, $groupId]);
        $domainIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($type === 'selected') {
        $domainIds = $scope['domainIds'] ?? [];
    }
    
    return $domainIds;
}

function saveSecurityRule($pdo, $userId, $domainId, $ruleType, $ruleData) {
    $stmt = $pdo->prepare("
        INSERT INTO security_rules (user_id, domain_id, rule_type, rule_data, created_at)
        VALUES (?, ?, ?, ?, datetime('now'))
    ");
    return $stmt->execute([$userId, $domainId, $ruleType, $ruleData]);
}

function loadBadBotsList($rules) {
    // Загрузка списка из nginx-ultimate-bad-bot-blocker
    $badBots = [];
    
    if ($rules['blockAllBots'] ?? false) {
        // URL к актуальному списку
        $url = 'https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/_generator_lists/bad-user-agents.list';
        $content = @file_get_contents($url);
        
        if ($content) {
            $badBots = array_merge($badBots, explode("\n", $content));
        }
    }
    
    // Добавляем дополнительные категории
    if ($rules['blockVulnScanners'] ?? false) {
        $badBots = array_merge($badBots, [
            'nikto', 'nmap', 'sqlmap', 'nessus', 'openvas', 'acunetix',
            'metasploit', 'w3af', 'burpsuite', 'owasp', 'skipfish'
        ]);
    }
    
    if ($rules['blockMalware'] ?? false) {
        $badBots = array_merge($badBots, [
            'malware', 'ransomware', 'trojan', 'adware', 'spyware'
        ]);
    }
    
    return array_filter(array_unique(array_map('trim', $badBots)));
}

function loadKnownBadIPs() {
    // URL к списку известных плохих IP
    $urls = [
        'https://raw.githubusercontent.com/mitchellkrogza/Suspicious.Snooping.Sniffing.Hacking.IP.Addresses/master/ips.list'
    ];
    
    $badIPs = [];
    foreach ($urls as $url) {
        $content = @file_get_contents($url);
        if ($content) {
            $badIPs = array_merge($badIPs, explode("\n", $content));
        }
    }
    
    return array_filter(array_unique(array_map('trim', $badIPs)));
}

function buildBotBlockExpression($badBots) {
    // Ограничиваем количество ботов (Cloudflare имеет лимит на длину выражения)
    $badBots = array_slice($badBots, 0, 100);
    
    $conditions = [];
    foreach ($badBots as $bot) {
        $bot = addslashes($bot);
        $conditions[] = "(lower(http.user_agent) contains \"$bot\")";
    }
    
    return implode(' or ', $conditions);
}

function buildReferrerExpression($allowedReferrers, $exceptions) {
    $conditions = [];
    
    // Проверяем наличие referrer
    if (!($allowedReferrers['allowEmpty'] ?? false)) {
        $conditions[] = '(http.referer eq "")';
    }
    
    // Блокируем если referrer не из разрешенных
    $allowed = [];
    
    if ($allowedReferrers['google'] ?? false) {
        $allowed[] = '(http.referer contains "google.")';
    }
    if ($allowedReferrers['yandex'] ?? false) {
        $allowed[] = '(http.referer contains "yandex.")';
    }
    if ($allowedReferrers['bing'] ?? false) {
        $allowed[] = '(http.referer contains "bing.com")';
    }
    if ($allowedReferrers['duckduckgo'] ?? false) {
        $allowed[] = '(http.referer contains "duckduckgo.com")';
    }
    if ($allowedReferrers['baidu'] ?? false) {
        $allowed[] = '(http.referer contains "baidu.com")';
    }
    
    // Кастомные домены
    foreach ($allowedReferrers['custom'] ?? [] as $domain) {
        $allowed[] = "(http.referer contains \"$domain\")";
    }
    
    $allowedExpression = implode(' or ', $allowed);
    
    // Исключения по URL
    $exceptionExpression = '';
    if (!empty($exceptions)) {
        $exConditions = [];
        foreach ($exceptions as $pattern) {
            $pattern = str_replace('*', '', $pattern);
            $exConditions[] = "(http.request.uri.path contains \"$pattern\")";
        }
        $exceptionExpression = ' and not (' . implode(' or ', $exConditions) . ')';
    }
    
    return "(not ($allowedExpression))$exceptionExpression";
}

