<?php
/**
 * Упрощенные массовые операции Cloudflare
 */

// Подавляем вывод ошибок и предупреждений для чистого JSON ответа
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';
require_once 'functions.php';

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Получаем домены пользователя с информацией о наличии API токена
$stmt = $pdo->prepare("
    SELECT ca.id, ca.domain, ca.zone_id, ca.dns_ip, ca.ssl_mode, ca.always_use_https, 
           ca.min_tls_version, g.name as group_name, cc.email, ca.account_id,
           CASE WHEN cat.id IS NOT NULL THEN 1 ELSE 0 END as has_api_token
    FROM cloudflare_accounts ca
    JOIN cloudflare_credentials cc ON ca.account_id = cc.id
    LEFT JOIN groups g ON ca.group_id = g.id
    LEFT JOIN cloudflare_api_tokens cat ON ca.account_id = cat.account_id AND cat.user_id = ca.user_id
    WHERE ca.user_id = ?
    GROUP BY ca.id
    ORDER BY ca.domain ASC
");
$stmt->execute([$userId]);
$domains = $stmt->fetchAll();

// Обработка массовых операций
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Подавляем любые дополнительные ошибки для POST запросов
    error_reporting(0);
    ini_set('display_errors', 0);
    
    header('Content-Type: application/json');
    
    $selectedDomains = $_POST['domain_ids'] ?? [];
    if (empty($selectedDomains)) {
        echo json_encode(['success' => false, 'error' => 'Не выбраны домены']);
        exit;
    }
    
    // Декодируем JSON если нужно
    if (is_string($selectedDomains)) {
        $selectedDomains = json_decode($selectedDomains, true);
    }
    
    $results = [];
    $success = 0;
    $errors = 0;
    
    foreach ($selectedDomains as $domainId) {
        try {
            $result = performOperation($_POST['action'], $domainId, $_POST);
            $results[] = $result;
            if ($result['success']) {
                $success++;
            } else {
                $errors++;
            }
        } catch (Exception $e) {
            $results[] = ['success' => false, 'error' => $e->getMessage(), 'domain_id' => $domainId];
            $errors++;
        }
        
        // Задержка между операциями
        usleep(500000); // 0.5 секунды
    }
    
    echo json_encode([
        'success' => true,
        'processed' => count($selectedDomains),
        'success_count' => $success,
        'error_count' => $errors,
        'results' => $results
    ]);
    exit;
}

function performOperation($action, $domainId, $params) {
    global $pdo, $userId;
    
    // Получаем информацию о домене
    $stmt = $pdo->prepare("
        SELECT ca.*, cc.email, cc.api_key
        FROM cloudflare_accounts ca
        JOIN cloudflare_credentials cc ON ca.account_id = cc.id
        WHERE ca.id = ? AND ca.user_id = ?
    ");
    $stmt->execute([$domainId, $userId]);
    $domain = $stmt->fetch();
    
    if (!$domain) {
        return ['success' => false, 'error' => 'Домен не найден', 'domain_id' => $domainId];
    }
    
    // ДОБАВЛЕНО: Логирование параметров для отладки
    logAction($pdo, $userId, "Mass Operation Request", "Action: $action, Domain: {$domain['domain']}, Params: " . json_encode($params));
    
    switch ($action) {
        case 'change_ip':
            return changeIP($domain, $params['new_ip'] ?? '');
            
        case 'change_ssl_mode':
            return changeSSLMode($domain, $params['ssl_mode'] ?? '');
            
        case 'change_https':
            return changeHTTPS($domain, $params['always_use_https'] ?? '');
            
        case 'change_tls':
            return changeTLS($domain, $params['min_tls_version'] ?? '');
            
        case 'change_bot_fight_mode':
            return changeBotFightMode($domain, $params['enabled'] ?? '1');
            
        case 'change_ai_labyrinth':
            return changeAILabyrinth($domain, $params['enabled'] ?? '1');
            
        case 'delete_domain':
            return deleteDomainFromMass($domain);
            
        case 'purge_cache':
            return purgeCache($domain);
            
        case 'configure_caching':
            return configureCaching($domain);
            
        default:
            return ['success' => false, 'error' => 'Неизвестная операция', 'domain_id' => $domainId];
    }
}

function changeIP($domain, $newIP) {
    global $pdo, $userId;
    
    if (!$domain['zone_id']) {
        return ['success' => false, 'error' => 'Zone ID не найден', 'domain_id' => $domain['id']];
    }
    
    // ИСПРАВЛЕНО: Валидация IP адреса
    if (empty($newIP) || !filter_var($newIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return ['success' => false, 'error' => "Некорректный IPv4 адрес: '$newIP'", 'domain_id' => $domain['id']];
    }
    
    try {
        // Получаем прокси для API запроса
        $proxies = getProxies($pdo, $userId);
        
        logAction($pdo, $userId, "Mass IP Change Attempt", "Domain: {$domain['domain']}, New IP: '$newIP'");
        
        // Получаем все A-записи для домена
        $dnsResponse = cloudflareApiRequest(
            $pdo, 
            $domain['email'], 
            $domain['api_key'], 
            "zones/{$domain['zone_id']}/dns_records?type=A",
            'GET', 
            [], 
            $proxies, 
            $userId
        );
        
        if (!$dnsResponse || empty($dnsResponse->result)) {
            logAction($pdo, $userId, "Mass IP Change Failed", "Domain: {$domain['domain']}, Error: A-записи не найдены");
            return ['success' => false, 'error' => 'A-записи не найдены', 'domain_id' => $domain['id']];
        }
        
        $updatedCount = 0;
        $errorCount = 0;
        
        // Обновляем все A-записи на новый IP
        foreach ($dnsResponse->result as $record) {
            if ($record->type === 'A' && $record->content !== $newIP) {
                $updateResult = cloudflareApiRequest(
                    $pdo,
                    $domain['email'],
                    $domain['api_key'],
                    "zones/{$domain['zone_id']}/dns_records/{$record->id}",
                    'PATCH',
                    [
                        'content' => $newIP,
                        'name' => $record->name,
                        'type' => 'A',
                        'ttl' => $record->ttl ?? 1,
                        'proxied' => $record->proxied ?? false
                    ],
                    $proxies,
                    $userId
                );
                
                if ($updateResult && isset($updateResult->success) && $updateResult->success) {
                    $updatedCount++;
                } else {
                    $errorCount++;
                    logAction($pdo, $userId, "Mass IP Change Record Failed", "Domain: {$domain['domain']}, Record: {$record->name}, Error: API returned false");
                }
            }
        }
        
        if ($updatedCount > 0) {
            // Обновляем IP в базе данных
            $stmt = $pdo->prepare("UPDATE cloudflare_accounts SET dns_ip = ? WHERE id = ?");
            $stmt->execute([$newIP, $domain['id']]);
            
            // Логируем операцию
            logAction($pdo, $userId, "Mass IP Change Success", "Domain: {$domain['domain']}, New IP: $newIP, Records Updated: $updatedCount, Errors: $errorCount");
            
            return [
                'success' => true,
                'message' => "IP изменен на $newIP ($updatedCount записей обновлено" . ($errorCount > 0 ? ", $errorCount ошибок" : "") . ")",
                'domain_id' => $domain['id'],
                'new_ip' => $newIP,
                'records_updated' => $updatedCount,
                'errors' => $errorCount
            ];
        } else {
            logAction($pdo, $userId, "Mass IP Change Failed", "Domain: {$domain['domain']}, Error: Не удалось обновить ни одну DNS запись");
            return ['success' => false, 'error' => 'Не удалось обновить DNS записи', 'domain_id' => $domain['id']];
        }
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Mass IP Change Exception", "Domain: {$domain['domain']}, Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Ошибка API: ' . $e->getMessage(), 'domain_id' => $domain['id']];
    }
}

function changeSSLMode($domain, $sslMode) {
    global $pdo, $userId;
    
    if (!$domain['zone_id']) {
        return ['success' => false, 'error' => 'Zone ID не найден', 'domain_id' => $domain['id']];
    }
    
    try {
        $proxies = getProxies($pdo, $userId);
        
        // ИСПРАВЛЕНО: Валидация SSL режима
        $validSslModes = ['off', 'flexible', 'full', 'strict'];
        if (!in_array($sslMode, $validSslModes)) {
            return ['success' => false, 'error' => "Недопустимый SSL режим: $sslMode", 'domain_id' => $domain['id']];
        }
        
        logAction($pdo, $userId, "Mass SSL Mode Change Attempt", "Domain: {$domain['domain']}, SSL Mode: '$sslMode'");
        
        // Обновляем SSL режим через Cloudflare API
        $result = cloudflareApiRequest(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "zones/{$domain['zone_id']}/settings/ssl",
            'PATCH',
            ['value' => $sslMode],
            $proxies,
            $userId
        );
        
        if ($result && isset($result->success) && $result->success) {
            // Обновляем в базе данных
            $stmt = $pdo->prepare("UPDATE cloudflare_accounts SET ssl_mode = ? WHERE id = ?");
            $stmt->execute([$sslMode, $domain['id']]);
            
            logAction($pdo, $userId, "Mass SSL Mode Change Success", "Domain: {$domain['domain']}, New SSL Mode: $sslMode");
            
            return [
                'success' => true,
                'message' => "SSL режим изменен на $sslMode",
                'domain_id' => $domain['id'],
                'ssl_mode' => $sslMode
            ];
        } else {
            $errorMsg = 'Не удалось изменить SSL режим через API';
            if (isset($result->errors) && is_array($result->errors)) {
                $errors = array_map(function($err) { return $err->message ?? 'Unknown error'; }, $result->errors);
                $errorMsg .= ': ' . implode(', ', $errors);
            }
            
            logAction($pdo, $userId, "Mass SSL Mode Change Failed", "Domain: {$domain['domain']}, Error: $errorMsg");
            return ['success' => false, 'error' => $errorMsg, 'domain_id' => $domain['id']];
        }
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Mass SSL Mode Change Exception", "Domain: {$domain['domain']}, Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Ошибка API: ' . $e->getMessage(), 'domain_id' => $domain['id']];
    }
}

function changeHTTPS($domain, $alwaysUseHttps) {
    global $pdo, $userId;
    
    if (!$domain['zone_id']) {
        return ['success' => false, 'error' => 'Zone ID не найден', 'domain_id' => $domain['id']];
    }
    
    try {
        $proxies = getProxies($pdo, $userId);
        
        // ИСПРАВЛЕНО: Правильная обработка строковых значений
        // Преобразуем строковые значения в boolean, а затем в формат API
        $alwaysUseHttpsBool = ($alwaysUseHttps === '1' || $alwaysUseHttps === 1 || $alwaysUseHttps === true);
        $value = $alwaysUseHttpsBool ? 'on' : 'off';
        
        logAction($pdo, $userId, "Mass HTTPS Change Attempt", "Domain: {$domain['domain']}, Input: '$alwaysUseHttps', Bool: " . ($alwaysUseHttpsBool ? 'true' : 'false') . ", API Value: '$value'");
        
        // Обновляем Always Use HTTPS через Cloudflare API
        $result = cloudflareApiRequest(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "zones/{$domain['zone_id']}/settings/always_use_https",
            'PATCH',
            ['value' => $value],
            $proxies,
            $userId
        );
        
        if ($result && isset($result->success) && $result->success) {
            // Обновляем в базе данных с правильным boolean значением
            $stmt = $pdo->prepare("UPDATE cloudflare_accounts SET always_use_https = ? WHERE id = ?");
            $stmt->execute([$alwaysUseHttpsBool ? 1 : 0, $domain['id']]);
            
            logAction($pdo, $userId, "Mass HTTPS Change Success", "Domain: {$domain['domain']}, Always Use HTTPS: $value");
            
            return [
                'success' => true,
                'message' => "Always Use HTTPS " . ($alwaysUseHttpsBool ? 'включен' : 'выключен'),
                'domain_id' => $domain['id'],
                'always_use_https' => $alwaysUseHttpsBool
            ];
        } else {
            $errorMsg = 'Не удалось изменить настройку HTTPS через API';
            if (isset($result->errors) && is_array($result->errors)) {
                $errors = array_map(function($err) { return $err->message ?? 'Unknown error'; }, $result->errors);
                $errorMsg .= ': ' . implode(', ', $errors);
            }
            
            logAction($pdo, $userId, "Mass HTTPS Change Failed", "Domain: {$domain['domain']}, Error: $errorMsg");
            return ['success' => false, 'error' => $errorMsg, 'domain_id' => $domain['id']];
        }
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Mass HTTPS Change Exception", "Domain: {$domain['domain']}, Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Ошибка API: ' . $e->getMessage(), 'domain_id' => $domain['id']];
    }
}

function changeTLS($domain, $minTlsVersion) {
    global $pdo, $userId;
    
    if (!$domain['zone_id']) {
        return ['success' => false, 'error' => 'Zone ID не найден', 'domain_id' => $domain['id']];
    }
    
    try {
        $proxies = getProxies($pdo, $userId);
        
        // ИСПРАВЛЕНО: Валидация TLS версии
        $validTlsVersions = ['1.0', '1.1', '1.2', '1.3'];
        if (!in_array($minTlsVersion, $validTlsVersions)) {
            return ['success' => false, 'error' => "Недопустимая версия TLS: $minTlsVersion", 'domain_id' => $domain['id']];
        }
        
        logAction($pdo, $userId, "Mass TLS Change Attempt", "Domain: {$domain['domain']}, TLS Version: '$minTlsVersion'");
        
        // Обновляем минимальную версию TLS через Cloudflare API
        $result = cloudflareApiRequest(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "zones/{$domain['zone_id']}/settings/min_tls_version",
            'PATCH',
            ['value' => $minTlsVersion],
            $proxies,
            $userId
        );
        
        if ($result && isset($result->success) && $result->success) {
            // Обновляем в базе данных
            $stmt = $pdo->prepare("UPDATE cloudflare_accounts SET min_tls_version = ? WHERE id = ?");
            $stmt->execute([$minTlsVersion, $domain['id']]);
            
            logAction($pdo, $userId, "Mass TLS Change Success", "Domain: {$domain['domain']}, Min TLS Version: $minTlsVersion");
            
            return [
                'success' => true,
                'message' => "Минимальная версия TLS изменена на $minTlsVersion",
                'domain_id' => $domain['id'],
                'min_tls_version' => $minTlsVersion
            ];
        } else {
            $errorMsg = 'Не удалось изменить версию TLS через API';
            if (isset($result->errors) && is_array($result->errors)) {
                $errors = array_map(function($err) { return $err->message ?? 'Unknown error'; }, $result->errors);
                $errorMsg .= ': ' . implode(', ', $errors);
            }
            
            logAction($pdo, $userId, "Mass TLS Change Failed", "Domain: {$domain['domain']}, Error: $errorMsg");
            return ['success' => false, 'error' => $errorMsg, 'domain_id' => $domain['id']];
        }
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Mass TLS Change Exception", "Domain: {$domain['domain']}, Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Ошибка API: ' . $e->getMessage(), 'domain_id' => $domain['id']];
    }
}

function changeBotFightMode($domain, $enabled = '1') {
    global $pdo, $userId;
    
    if (!$domain['zone_id']) {
        return ['success' => false, 'error' => 'Zone ID не найден', 'domain_id' => $domain['id']];
    }
    
    try {
        $proxies = getProxies($pdo, $userId);
        
        // Получаем API токен для аккаунта
        $accountId = $domain['account_id'] ?? null;
        if (!$accountId) {
            return ['success' => false, 'error' => 'Account ID не найден', 'domain_id' => $domain['id']];
        }
        
        // Получаем первый доступный токен для этого аккаунта
        $tokens = listCloudflareApiTokens($pdo, $userId, $accountId);
        if (empty($tokens)) {
            return ['success' => false, 'error' => 'API токен не найден для этого аккаунта. Добавьте токен в настройках.', 'domain_id' => $domain['id']];
        }
        
        // Используем первый токен (самый свежий, так как они отсортированы по created_at DESC)
        $apiToken = $tokens[0]['token'];
        
        // Преобразуем строковое значение в boolean
        $fightModeEnabled = ($enabled === '1' || $enabled === 1 || $enabled === true);
        $action = $fightModeEnabled ? 'Enabling' : 'Disabling';
        
        logAction($pdo, $userId, "Mass Bot Fight Mode Change Attempt", "Domain: {$domain['domain']}, $action Bot Fight Mode, Using Token: " . substr($apiToken, 0, 10) . '...');
        
        $url = "https://api.cloudflare.com/client/v4/zones/{$domain['zone_id']}/bot_management";
        $payload = json_encode(['fight_mode' => $fightModeEnabled]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        // Используем Bearer token для аутентификации
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
            'Authorization: Bearer ' . trim($apiToken)
        ];
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Настройка прокси
        if ($proxies) {
            $proxy = getRandomProxy($proxies);
            if ($proxy && preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)@([^:@]+):(.+)$/', $proxy, $matches)) {
                $proxyIp = $matches[1];
                $proxyPort = $matches[2];
                $proxyLogin = $matches[3];
                $proxyPass = $matches[4];
                
                curl_setopt($ch, CURLOPT_PROXY, "$proxyIp:$proxyPort");
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxyLogin:$proxyPass");
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                
                logAction($pdo, $userId, "Using Proxy (Bot Fight Mode)", "Proxy: $proxyIp:$proxyPort, Domain: {$domain['domain']}");
            }
        }
       
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            logAction($pdo, $userId, "Bot Fight Mode cURL Error", "Domain: {$domain['domain']}, Error: $curlError");
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $curlError,
                'domain_id' => $domain['id']
            ];
        }
        
        $result = json_decode($response, true);
        
        // Логируем ответ
        logAction($pdo, $userId, "Bot Fight Mode API Response", 
            "Domain: {$domain['domain']}, HTTP Code: $httpCode, Success: " . (isset($result['success']) ? ($result['success'] ? 'true' : 'false') : 'unknown') .
            ", Response: " . substr($response, 0, 500));
        
        if ($httpCode === 200 && isset($result['success']) && $result['success']) {
            $fightMode = $result['result']['fight_mode'] ?? false;
            $status = $fightMode ? 'enabled' : 'disabled';
            logAction($pdo, $userId, "Bot Fight Mode Change Success", "Domain: {$domain['domain']}, Bot Fight Mode: $status");
            
            $message = $fightMode ? "Bot Fight Mode включен" : "Bot Fight Mode выключен";
            
            return [
                'success' => true,
                'message' => $message,
                'domain_id' => $domain['id'],
                'result' => $result['result'] ?? null
            ];
        } else {
            $actionText = $fightModeEnabled ? 'включить' : 'выключить';
            $errorMsg = "Не удалось $actionText Bot Fight Mode через API";
            $errorDetails = [];
            
            if (isset($result['errors']) && is_array($result['errors']) && !empty($result['errors'])) {
                foreach ($result['errors'] as $err) {
                    if (is_array($err)) {
                        $errorDetails[] = ($err['message'] ?? '') . ($err['code'] ? ' (code: ' . $err['code'] . ')' : '');
                    } else {
                        $errorDetails[] = (string)$err;
                    }
                }
            }
            
            if ($httpCode !== 200) {
                $errorDetails[] = 'HTTP ' . $httpCode;
            }
            
            if (empty($errorDetails)) {
                $errorDetails[] = 'Неизвестная ошибка API';
            }
            
            $errorMsg .= ': ' . implode(', ', $errorDetails);
            
            logAction($pdo, $userId, "Bot Fight Mode Change Failed", "Domain: {$domain['domain']}, Error: $errorMsg");
            return [
                'success' => false,
                'error' => $errorMsg,
                'domain_id' => $domain['id'],
                'http_code' => $httpCode
            ];
        }
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Bot Fight Mode Change Exception", "Domain: {$domain['domain']}, Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Ошибка: ' . $e->getMessage(),
            'domain_id' => $domain['id']
        ];
    }
}

function changeAILabyrinth($domain, $enabled = '1') {
    global $pdo, $userId;
    
    if (!$domain['zone_id']) {
        return ['success' => false, 'error' => 'Zone ID не найден', 'domain_id' => $domain['id']];
    }
    
    try {
        $proxies = getProxies($pdo, $userId);
        
        // Получаем API токен для аккаунта
        $accountId = $domain['account_id'] ?? null;
        if (!$accountId) {
            return ['success' => false, 'error' => 'Account ID не найден', 'domain_id' => $domain['id']];
        }
        
        // Получаем первый доступный токен для этого аккаунта
        $tokens = listCloudflareApiTokens($pdo, $userId, $accountId);
        if (empty($tokens)) {
            return ['success' => false, 'error' => 'API токен не найден для этого аккаунта. Добавьте токен в настройках.', 'domain_id' => $domain['id']];
        }
        
        // Используем первый токен (самый свежий, так как они отсортированы по created_at DESC)
        $apiToken = $tokens[0]['token'];
        
        // Преобразуем строковое значение в формат API
        $crawlerProtectionEnabled = ($enabled === '1' || $enabled === 1 || $enabled === true);
        $crawlerProtectionValue = $crawlerProtectionEnabled ? 'enabled' : 'disabled';
        $action = $crawlerProtectionEnabled ? 'Enabling' : 'Disabling';
        
        logAction($pdo, $userId, "Mass AI Labyrinth Change Attempt", "Domain: {$domain['domain']}, $action AI Labyrinth, Using Token: " . substr($apiToken, 0, 10) . '...');
        
        $url = "https://api.cloudflare.com/client/v4/zones/{$domain['zone_id']}/bot_management";
        $payload = json_encode(['crawler_protection' => $crawlerProtectionValue]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        // Используем Bearer token для аутентификации
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
            'Authorization: Bearer ' . trim($apiToken)
        ];
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Настройка прокси
        if ($proxies) {
            $proxy = getRandomProxy($proxies);
            if ($proxy && preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)@([^:@]+):(.+)$/', $proxy, $matches)) {
                $proxyIp = $matches[1];
                $proxyPort = $matches[2];
                $proxyLogin = $matches[3];
                $proxyPass = $matches[4];
                
                curl_setopt($ch, CURLOPT_PROXY, "$proxyIp:$proxyPort");
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxyLogin:$proxyPass");
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                
                logAction($pdo, $userId, "Using Proxy (AI Labyrinth)", "Proxy: $proxyIp:$proxyPort, Domain: {$domain['domain']}");
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            logAction($pdo, $userId, "AI Labyrinth cURL Error", "Domain: {$domain['domain']}, Error: $curlError");
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $curlError,
                'domain_id' => $domain['id']
            ];
        }
        
        $result = json_decode($response, true);
        
        // Логируем ответ
        logAction($pdo, $userId, "AI Labyrinth API Response", 
            "Domain: {$domain['domain']}, HTTP Code: $httpCode, Success: " . (isset($result['success']) ? ($result['success'] ? 'true' : 'false') : 'unknown') .
            ", Response: " . substr($response, 0, 500));
        
        if ($httpCode === 200 && isset($result['success']) && $result['success']) {
            $crawlerProtection = $result['result']['crawler_protection'] ?? 'disabled';
            logAction($pdo, $userId, "AI Labyrinth Change Success", "Domain: {$domain['domain']}, Crawler Protection: $crawlerProtection");
            
            $message = ($crawlerProtection === 'enabled') ? "AI Labyrinth включен" : "AI Labyrinth выключен";
            
            return [
                'success' => true,
                'message' => $message,
                'domain_id' => $domain['id'],
                'result' => $result['result'] ?? null
            ];
        } else {
            $actionText = $crawlerProtectionEnabled ? 'включить' : 'выключить';
            $errorMsg = "Не удалось $actionText AI Labyrinth через API";
            $errorDetails = [];
            
            if (isset($result['errors']) && is_array($result['errors']) && !empty($result['errors'])) {
                foreach ($result['errors'] as $err) {
                    if (is_array($err)) {
                        $errorDetails[] = ($err['message'] ?? '') . ($err['code'] ? ' (code: ' . $err['code'] . ')' : '');
                    } else {
                        $errorDetails[] = (string)$err;
                    }
                }
            }
            
            if ($httpCode !== 200) {
                $errorDetails[] = 'HTTP ' . $httpCode;
            }
            
            if (empty($errorDetails)) {
                $errorDetails[] = 'Неизвестная ошибка API';
            }
            
            $errorMsg .= ': ' . implode(', ', $errorDetails);
            
            logAction($pdo, $userId, "AI Labyrinth Change Failed", "Domain: {$domain['domain']}, Error: $errorMsg");
            return [
                'success' => false,
                'error' => $errorMsg,
                'domain_id' => $domain['id'],
                'http_code' => $httpCode
            ];
        }
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "AI Labyrinth Change Exception", "Domain: {$domain['domain']}, Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Ошибка: ' . $e->getMessage(),
            'domain_id' => $domain['id']
        ];
    }
}

function purgeCache($domain) {
    global $pdo, $userId;
    
    if (!$domain['zone_id']) {
        return ['success' => false, 'error' => 'Zone ID не найден', 'domain_id' => $domain['id']];
    }
    
    try {
        $proxies = getProxies($pdo, $userId);
        $zoneId = $domain['zone_id'];
        
        logAction($pdo, $userId, "Mass Cache Purge Attempt", "Domain: {$domain['domain']}, Zone: $zoneId");
        
        $payload = ['purge_everything' => true];
        $purgeResp = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/purge_cache", 'POST', $payload, $proxies, $userId);
        
        if ($purgeResp['success']) {
            logAction($pdo, $userId, "Mass Cache Purge Success", "Domain: {$domain['domain']}, Zone: $zoneId");
            
            return [
                'success' => true,
                'message' => "Кеш очищен",
                'domain_id' => $domain['id'],
                'zone_id' => $zoneId
            ];
        } else {
            $err = 'Не удалось очистить кеш';
            if (!empty($purgeResp['api_errors'])) {
                $err .= ': ' . implode(', ', array_map(fn($e) => ($e['code'] ?? '?') . ' ' . ($e['message'] ?? 'unknown'), $purgeResp['api_errors']));
            }
            
            logAction($pdo, $userId, "Mass Cache Purge Failed", "Domain: {$domain['domain']}, Error: $err");
            return ['success' => false, 'error' => $err, 'domain_id' => $domain['id']];
        }
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Mass Cache Purge Exception", "Domain: {$domain['domain']}, Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Ошибка API: ' . $e->getMessage(), 'domain_id' => $domain['id']];
    }
}

function configureCaching($domain) {
    global $pdo, $userId;
    
    if (!$domain['zone_id']) {
        return ['success' => false, 'error' => 'Zone ID не найден', 'domain_id' => $domain['id']];
    }
    
    try {
        $proxies = getProxies($pdo, $userId);
        $zoneId = $domain['zone_id'];
        
        logAction($pdo, $userId, "Mass Configure Caching Attempt", "Domain: {$domain['domain']}, Zone: $zoneId");
        
        // Определяем базовый URL (с протоколом)
        $baseUrl = $domain['domain'];
        $domainPattern = "*{$baseUrl}";
        
        // Паттерны для правил
        $patterns = [
            'assets' => "{$domainPattern}/assets/*",
            'js' => "{$domainPattern}/js/*",
            'build' => "{$domainPattern}/build/*"
        ];
        
        // Настройки кеширования
        $browserCacheTTL = 86400; // 1 день в секундах
        $edgeCacheTTL = 604800; // 7 дней в секундах
        
        $createdRules = [];
        $failedRules = [];
        $priority = 1;
        
        // Создаем 3 правила для каждого паттерна
        foreach ($patterns as $name => $pattern) {
            $rule = [
                'targets' => [[
                    'target' => 'url',
                    'constraint' => [
                        'operator' => 'matches',
                        'value' => $pattern
                    ]
                ]],
                'actions' => [
                    [
                        'id' => 'browser_cache_ttl',
                        'value' => $browserCacheTTL
                    ],
                    [
                        'id' => 'cache_level',
                        'value' => 'cache_everything'
                    ],
                    [
                        'id' => 'edge_cache_ttl',
                        'value' => $edgeCacheTTL
                    ]
                ],
                'status' => 'active',
                'priority' => $priority++
            ];
            
            $resp = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/pagerules", 'POST', $rule, $proxies, $userId);
            
            if ($resp['success']) {
                $createdRules[] = $name;
                logAction($pdo, $userId, "Mass Configure Caching Rule Created", "Domain: {$domain['domain']}, Pattern: $pattern, Rule: $name");
            } else {
                $err = "Не удалось создать правило для $name";
                if (!empty($resp['api_errors'])) {
                    $err .= ': ' . implode(', ', array_map(fn($e) => ($e['code'] ?? '?') . ' ' . ($e['message'] ?? 'unknown'), $resp['api_errors']));
                }
                $failedRules[] = $err;
                logAction($pdo, $userId, "Mass Configure Caching Rule Failed", "Domain: {$domain['domain']}, Pattern: $pattern, Error: $err");
            }
            
            // Небольшая задержка между запросами
            usleep(200000); // 0.2 секунды
        }
        
        if (count($createdRules) > 0) {
            $message = "Создано правил: " . count($createdRules) . " из " . count($patterns);
            if (count($failedRules) > 0) {
                $message .= ". Ошибки: " . implode('; ', $failedRules);
            }
            
            logAction($pdo, $userId, "Mass Configure Caching Success", "Domain: {$domain['domain']}, Created: " . implode(', ', $createdRules));
            
            return [
                'success' => true,
                'message' => $message,
                'domain_id' => $domain['id'],
                'zone_id' => $zoneId,
                'created_rules' => $createdRules,
                'failed_rules' => $failedRules
            ];
        } else {
            $err = 'Не удалось создать ни одно правило: ' . implode('; ', $failedRules);
            logAction($pdo, $userId, "Mass Configure Caching Failed", "Domain: {$domain['domain']}, Error: $err");
            return ['success' => false, 'error' => $err, 'domain_id' => $domain['id']];
        }
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Mass Configure Caching Exception", "Domain: {$domain['domain']}, Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Ошибка API: ' . $e->getMessage(), 'domain_id' => $domain['id']];
    }
}

function deleteDomainFromMass($domain) {
    global $pdo, $userId;
    
    try {
        // Начинаем транзакцию для безопасного удаления
        $pdo->beginTransaction();
        
        // Удаляем домен
        $deleteStmt = $pdo->prepare("DELETE FROM cloudflare_accounts WHERE id = ? AND user_id = ?");
        $deleteResult = $deleteStmt->execute([$domain['id'], $userId]);
        
        if (!$deleteResult || $deleteStmt->rowCount() === 0) {
            throw new Exception('Не удалось удалить домен из базы данных');
        }
        
        // Логируем операцию
        logAction($pdo, $userId, "Mass Delete Domain", "Domain deleted: {$domain['domain']} (Email: {$domain['email']})");
        
        // Подтверждаем транзакцию
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Домен {$domain['domain']} удален",
            'domain_id' => $domain['id'],
            'domain' => $domain['domain']
        ];
        
    } catch (Exception $e) {
        // Откатываем транзакцию при ошибке
        $pdo->rollBack();
        return ['success' => false, 'error' => 'Ошибка при удалении: ' . $e->getMessage(), 'domain_id' => $domain['id']];
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Массовые операции Cloudflare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .operation-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .operation-card:hover {
            border-color: #007bff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,123,255,0.15);
        }
        .log-container {
            background: #1a1a1a;
            color: #e0e0e0;
            border-radius: 8px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            max-height: 300px;
            overflow-y: auto;
        }
        .log-success { color: #4CAF50; }
        .log-error { color: #f44336; }
        .log-info { color: #2196F3; }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Заголовок -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1><i class="fas fa-cogs text-primary me-2"></i>Массовые операции Cloudflare</h1>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Назад к панели
                    </a>
                </div>
            </div>
        </div>

        <!-- Статистика -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-chart-bar me-2"></i>Статистика</h5>
                        <p class="card-text">
                            <strong>Всего доменов:</strong> <?php echo count($domains); ?><br>
                            <strong>С Zone ID:</strong> <?php echo count(array_filter($domains, fn($d) => !empty($d['zone_id']))); ?><br>
                            <strong>С SSL:</strong> <?php echo count(array_filter($domains, fn($d) => $d['ssl_mode'] !== 'off')); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>Информация</h5>
                        <p class="card-text">
                            Выберите домены и операцию для массового выполнения.<br>
                            Операции выполняются последовательно с задержкой 0.5 секунды.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Выбор доменов -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Выбор доменов</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <input type="text" id="domainSearch" class="form-control" placeholder="Поиск по домену..." 
                                   onkeyup="filterDomains()">
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="hasApiTokenFilter" onchange="filterDomains()">
                                <label class="form-check-label" for="hasApiTokenFilter">
                                    <i class="fas fa-key me-1"></i>Has API token
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-check-label">
                                <input type="checkbox" id="selectAll" class="form-check-input me-2" onchange="toggleSelectAll()">
                                Выбрать все домены
                            </label>
                        </div>
                        
                        <div id="domainList" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($domains as $domain): ?>
                                <div class="form-check mb-2 domain-item" 
                                     data-domain="<?php echo htmlspecialchars(strtolower($domain['domain'])); ?>"
                                     data-has-api-token="<?php echo $domain['has_api_token'] ? '1' : '0'; ?>">
                                    <input class="form-check-input domain-checkbox" type="checkbox" 
                                           value="<?php echo $domain['id']; ?>" id="domain-<?php echo $domain['id']; ?>">
                                    <label class="form-check-label" for="domain-<?php echo $domain['id']; ?>">
                                        <strong><?php echo htmlspecialchars($domain['domain']); ?></strong>
                                        <?php if ($domain['has_api_token']): ?>
                                            <i class="fas fa-key text-success ms-1" title="Has API token"></i>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($domain['group_name'] ?? 'Без группы'); ?>
                                            • IP: <?php echo htmlspecialchars($domain['dns_ip'] ?? 'Не указан'); ?>
                                        </small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">Выбрано: <span id="selectedCount">0</span> доменов</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Операции -->
            <div class="col-md-8">
                <!-- Смена IP -->
                <div class="card operation-card mb-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-network-wired me-2"></i>Смена IP адресов</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <input type="text" id="newIP" class="form-control" placeholder="Новый IP адрес" 
                                       pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-info w-100" onclick="changeIP()">
                                    <i class="fas fa-play me-1"></i>Сменить IP
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SSL режим -->
                <div class="card operation-card mb-3">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>SSL режим</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <select id="sslMode" class="form-select">
                                    <option value="off">Off - SSL отключен</option>
                                    <option value="flexible">Flexible - Частичное шифрование</option>
                                    <option value="full">Full - Полное шифрование</option>
                                    <option value="strict" selected>Full (strict) - С проверкой сертификата</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-success w-100" onclick="changeSSLMode()">
                                    <i class="fas fa-play me-1"></i>Изменить SSL режим
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HTTPS -->
                <div class="card operation-card mb-3">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Always Use HTTPS</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <select id="httpsMode" class="form-select">
                                    <option value="1" selected>Включить Always Use HTTPS</option>
                                    <option value="0">Выключить Always Use HTTPS</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-warning w-100" onclick="changeHTTPS()">
                                    <i class="fas fa-play me-1"></i>Изменить HTTPS
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TLS версия -->
                <div class="card operation-card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Минимальная версия TLS</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <select id="tlsVersion" class="form-select">
                                    <option value="1.0">TLS 1.0</option>
                                    <option value="1.1">TLS 1.1</option>
                                    <option value="1.2" selected>TLS 1.2</option>
                                    <option value="1.3">TLS 1.3</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-primary w-100" onclick="changeTLS()">
                                    <i class="fas fa-play me-1"></i>Изменить TLS версию
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bot Fight Mode -->
                <div class="card operation-card mb-3">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-shield-virus me-2"></i>Bot Fight Mode</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <select id="botFightMode" class="form-select">
                                    <option value="1" selected>Включить Bot Fight Mode</option>
                                    <option value="0">Выключить Bot Fight Mode</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-dark w-100" onclick="changeBotFightMode()">
                                    <i class="fas fa-play me-1"></i>Изменить Bot Fight Mode
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Labyrinth -->
                <div class="card operation-card mb-3">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-robot me-2"></i>AI Labyrinth</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <select id="aiLabyrinthMode" class="form-select">
                                    <option value="1" selected>Включить AI Labyrinth</option>
                                    <option value="0">Выключить AI Labyrinth</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-secondary w-100" onclick="changeAILabyrinth()">
                                    <i class="fas fa-play me-1"></i>Изменить AI Labyrinth
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Очистка кеша -->
                <div class="card operation-card mb-3">
                    <div class="card-header bg-purple text-white" style="background-color: #6f42c1 !important;">
                        <h5 class="mb-0"><i class="fas fa-broom me-2"></i>Очистка кеша</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Информация:</strong> Очищает весь кеш Cloudflare для выбранных доменов.
                        </div>
                        <button class="btn w-100" onclick="purgeCache()" style="background-color: #6f42c1; color: white;">
                            <i class="fas fa-broom me-1"></i>Очистить кеш
                        </button>
                    </div>
                </div>

                <!-- Настройка кеширования -->
                <div class="card operation-card mb-3">
                    <div class="card-header text-white" style="background-color: #17a2b8 !important;">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Настроить кеширование</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Информация:</strong> Создает 3 правила кеширования для каждого домена:
                            <ul class="mb-0 mt-2">
                                <li><code>/assets/*</code> - Browser Cache: 1 день, Cache Level: Cache Everything, Edge Cache: 7 дней</li>
                                <li><code>/js/*</code> - Browser Cache: 1 день, Cache Level: Cache Everything, Edge Cache: 7 дней</li>
                                <li><code>/build/*</code> - Browser Cache: 1 день, Cache Level: Cache Everything, Edge Cache: 7 дней</li>
                            </ul>
                        </div>
                        <button class="btn w-100" onclick="configureCaching()" style="background-color: #17a2b8; color: white;">
                            <i class="fas fa-cog me-1"></i>Настроить кеширование
                        </button>
                    </div>
                </div>

                <!-- Удаление доменов -->
                <div class="card operation-card mb-3">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-trash me-2"></i>Удаление доменов</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>Внимание!</strong> Это действие нельзя отменить!
                        </div>
                        <button class="btn btn-danger w-100" onclick="deleteSelectedDomains()">
                            <i class="fas fa-trash me-1"></i>Удалить выбранные домены
                        </button>
                    </div>
                </div>

                <!-- МЕГА операция -->
                <div class="card operation-card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-rocket me-2"></i>МЕГА-ОПЕРАЦИЯ</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Применить все настройки сразу: IP + SSL (Full strict) + HTTPS (Вкл) + TLS 1.2</p>
                        <button class="btn btn-danger w-100 btn-lg" onclick="megaOperation()" style="animation: pulse 2s infinite;">
                            <i class="fas fa-rocket me-1"></i>🚀 ЗАПУСТИТЬ ВСЁ СРАЗУ!
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Лог операций -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-terminal me-2"></i>Лог операций</h5>
                    </div>
                    <div class="card-body">
                        <div class="progress mb-3" style="display: none;" id="progressContainer">
                            <div class="progress-bar" id="progressBar" style="width: 0%">0%</div>
                        </div>
                        <div id="operationLog" class="log-container">
                            Логи операций появятся здесь...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Управление выбором доменов
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.domain-checkbox:not([style*="display: none"])');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelectedCount();
        }
        
        // Обновляем счетчик при изменении фильтров
        document.getElementById('hasApiTokenFilter').addEventListener('change', function() {
            updateSelectedCount();
        });

        function updateSelectedCount() {
            const checked = document.querySelectorAll('.domain-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = checked;
        }

        // Фильтрация доменов по поисковому запросу и API token
        function filterDomains() {
            const searchTerm = document.getElementById('domainSearch').value.toLowerCase().trim();
            const hasApiTokenFilter = document.getElementById('hasApiTokenFilter').checked;
            const domainItems = document.querySelectorAll('.domain-item');
            let visibleCount = 0;
            
            domainItems.forEach(item => {
                const domainName = item.getAttribute('data-domain');
                const hasApiToken = item.getAttribute('data-has-api-token') === '1';
                
                // Проверка поискового запроса
                const matchesSearch = searchTerm === '' || domainName.includes(searchTerm);
                
                // Проверка фильтра API token
                const matchesApiTokenFilter = !hasApiTokenFilter || hasApiToken;
                
                if (matchesSearch && matchesApiTokenFilter) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Обновляем счетчик выбранных доменов
            updateSelectedCount();
            
            // Показываем сообщение если ничего не найдено
            const domainList = document.getElementById('domainList');
            let noResultsMsg = document.getElementById('noResultsMessage');
            
            if (visibleCount === 0 && (searchTerm !== '' || hasApiTokenFilter)) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'noResultsMessage';
                    noResultsMsg.className = 'alert alert-info mt-3';
                    noResultsMsg.innerHTML = '<i class="fas fa-info-circle me-1"></i>Домены не найдены';
                    domainList.appendChild(noResultsMsg);
                }
            } else {
                if (noResultsMsg) {
                    noResultsMsg.remove();
                }
            }
        }

        // Обновляем счетчик при изменении чекбоксов
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('domain-checkbox')) {
                updateSelectedCount();
            }
        });

        // Функции логирования
        function addLog(message, type = 'info') {
            const log = document.getElementById('operationLog');
            const timestamp = new Date().toLocaleTimeString();
            const colorClass = {
                'info': 'log-info',
                'success': 'log-success',
                'error': 'log-error'
            }[type] || 'log-info';
            
            const logEntry = document.createElement('div');
            logEntry.className = colorClass;
            logEntry.textContent = `[${timestamp}] ${message}`;
            
            log.appendChild(logEntry);
            log.scrollTop = log.scrollHeight;
        }

        function showProgress(current, total) {
            const container = document.getElementById('progressContainer');
            const bar = document.getElementById('progressBar');
            
            if (current === 0) {
                container.style.display = 'block';
            }
            
            const percent = Math.round((current / total) * 100);
            bar.style.width = `${percent}%`;
            bar.textContent = `${percent}%`;
            
            if (current >= total) {
                setTimeout(() => {
                    container.style.display = 'none';
                }, 2000);
            }
        }

        function showNotification(message, type = 'info') {
            const alertClass = {
                'info': 'alert-info',
                'success': 'alert-success',
                'error': 'alert-danger'
            }[type] || 'alert-info';
            
            const alert = document.createElement('div');
            alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alert);
            
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }

        // Получение выбранных доменов
        function getSelectedDomains() {
            return Array.from(document.querySelectorAll('.domain-checkbox:checked')).map(cb => cb.value);
        }

        // Выполнение операции
        async function performOperation(action, params = {}) {
            const domains = getSelectedDomains();
            
            if (domains.length === 0) {
                showNotification('Выберите домены для операции', 'error');
                return;
            }

            addLog(`Начинаем операцию для ${domains.length} доменов...`, 'info');
            showProgress(0, domains.length);

            const formData = new FormData();
            formData.append('action', action);
            formData.append('domain_ids', JSON.stringify(domains));
            
            Object.keys(params).forEach(key => {
                formData.append(key, params[key]);
            });

            try {
                const response = await fetch('mass_operations.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    addLog(`✅ Операция завершена! Успешно: ${result.success_count}, ошибок: ${result.error_count}`, 'success');
                    
                    // Показываем детали
                    result.results.forEach((res, index) => {
                        if (res.success) {
                            addLog(`✅ Домен ${index + 1}: ${res.message}`, 'success');
                            
                            // Если это удаление, удаляем строку из списка
                            if (action === 'delete_domain' && res.domain_id) {
                                const domainCheckbox = document.querySelector(`input[value="${res.domain_id}"]`);
                                if (domainCheckbox) {
                                    const domainDiv = domainCheckbox.closest('.form-check');
                                    if (domainDiv) {
                                        domainDiv.style.animation = 'fadeOut 0.5s';
                                        setTimeout(() => {
                                            domainDiv.remove();
                                            updateSelectedCount();
                                        }, 500);
                                    }
                                }
                            }
                        } else {
                            addLog(`❌ Домен ${index + 1}: ${res.error}`, 'error');
                        }
                        showProgress(index + 1, result.results.length);
                    });

                    showNotification(`Операция завершена! Успешно: ${result.success_count}, ошибок: ${result.error_count}`, 
                                   result.error_count > 0 ? 'warning' : 'success');
                } else {
                    addLog(`❌ Ошибка операции: ${result.error}`, 'error');
                    showNotification(`Ошибка: ${result.error}`, 'error');
                }
            } catch (error) {
                addLog(`❌ Ошибка соединения: ${error.message}`, 'error');
                showNotification('Ошибка соединения с сервером', 'error');
            }
        }

        // Операции
        function changeIP() {
            const newIP = document.getElementById('newIP').value.trim();
            
            if (!newIP) {
                showNotification('Введите новый IP адрес', 'error');
                return;
            }

            // Проверка формата IP
            const ipPattern = /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/;
            if (!ipPattern.test(newIP)) {
                showNotification('Введите корректный IPv4 адрес', 'error');
                return;
            }

            performOperation('change_ip', { new_ip: newIP });
        }

        function changeSSLMode() {
            const sslMode = document.getElementById('sslMode').value;
            performOperation('change_ssl_mode', { ssl_mode: sslMode });
        }

        function changeHTTPS() {
            const httpsMode = document.getElementById('httpsMode').value;
            performOperation('change_https', { always_use_https: httpsMode });
        }

        function changeTLS() {
            const tlsVersion = document.getElementById('tlsVersion').value;
            performOperation('change_tls', { min_tls_version: tlsVersion });
        }

        function changeBotFightMode() {
            const botFightMode = document.getElementById('botFightMode').value;
            performOperation('change_bot_fight_mode', { enabled: botFightMode });
        }

        function changeAILabyrinth() {
            const aiLabyrinthMode = document.getElementById('aiLabyrinthMode').value;
            performOperation('change_ai_labyrinth', { enabled: aiLabyrinthMode });
        }

        function purgeCache() {
            const domains = getSelectedDomains();
            
            if (domains.length === 0) {
                showNotification('Выберите домены для очистки кеша', 'error');
                return;
            }

            if (!confirm(`Очистить кеш для ${domains.length} выбранных доменов?`)) {
                return;
            }

            performOperation('purge_cache', {});
        }

        function configureCaching() {
            const domains = getSelectedDomains();
            
            if (domains.length === 0) {
                showNotification('Выберите домены для настройки кеширования', 'error');
                return;
            }

            if (!confirm(`Настроить кеширование для ${domains.length} выбранных доменов?\n\nБудут созданы 3 правила для каждого домена:\n- /assets/*\n- /js/*\n- /build/*`)) {
                return;
            }

            performOperation('configure_caching', {});
        }

        function deleteSelectedDomains() {
            const domains = getSelectedDomains();
            
            if (domains.length === 0) {
                showNotification('Выберите домены для удаления', 'error');
                return;
            }

            if (!confirm(`Вы уверены что хотите удалить ${domains.length} доменов?\n\nЭто действие нельзя отменить!`)) {
                return;
            }

            performOperation('delete_domain', {});
        }

        async function megaOperation() {
            const domains = getSelectedDomains();
            
            if (domains.length === 0) {
                showNotification('Выберите домены для МЕГА-ОПЕРАЦИИ', 'error');
                return;
            }

            const newIP = document.getElementById('newIP').value.trim();
            if (!newIP) {
                showNotification('Для МЕГА-ОПЕРАЦИИ нужно указать IP адрес', 'error');
                return;
            }

            if (!confirm(`🚀 ЗАПУСТИТЬ МЕГА-ОПЕРАЦИЮ для ${domains.length} доменов?\n\nБудет применено:\n- IP: ${newIP}\n- SSL: Full (strict)\n- HTTPS: Включен\n- TLS: 1.2`)) {
                return;
            }

            addLog('🚀 ЗАПУСК МЕГА-ОПЕРАЦИИ!', 'info');

            // Выполняем все операции последовательно
            await performOperation('change_ip', { new_ip: newIP });
            await performOperation('change_ssl_mode', { ssl_mode: 'strict' });
            await performOperation('change_https', { always_use_https: '1' });
            await performOperation('change_tls', { min_tls_version: '1.2' });

            addLog('🎉 МЕГА-ОПЕРАЦИЯ ЗАВЕРШЕНА!', 'success');
            showNotification('🚀 МЕГА-ОПЕРАЦИЯ ЗАВЕРШЕНА!', 'success');
        }

        // Инициализация
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
            addLog('Интерфейс массовых операций загружен', 'info');
        });
    </script>
</body>
</html>