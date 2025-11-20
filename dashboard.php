<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'handle_forms.php';
require_once 'header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$notification = $_GET['notification'] ?? '';
$error = $_GET['error'] ?? '';

// Получаем параметры сортировки и фильтрации
$sort_by = $_GET['sort_by'] ?? 'domain';
$sort_order = ($_GET['sort_order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$group_id = $_GET['group_id'] ?? null;
$search = trim($_GET['search'] ?? '');

// Валидация сортировки
$valid_sorts = ['domain', 'group_name', 'email', 'dns_ip', 'ssl_mode'];
if (!in_array($sort_by, $valid_sorts)) {
    $sort_by = 'domain';
}

// Получаем группы
$groupStmt = $pdo->prepare("SELECT * FROM groups WHERE user_id = ?");
$groupStmt->execute([$userId]);
$groups = $groupStmt->fetchAll();

// Получаем аккаунты
$stmt = $pdo->prepare("SELECT * FROM cloudflare_credentials WHERE user_id = ?");
$stmt->execute([$userId]);
$accounts = $stmt->fetchAll();

// Пагинация
$perPage = 200;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Формируем фильтры
$filters = ["ca.user_id = ?"];
$params = [$userId];

if ($group_id === 'none') {
    $filters[] = "ca.group_id IS NULL";
} elseif ($group_id) {
    $filters[] = "ca.group_id = ?";
    $params[] = $group_id;
}

if ($search) {
    $filters[] = "ca.domain LIKE ?";
    $params[] = "%$search%";
}

// Получаем общее количество
$countSql = "SELECT COUNT(*) FROM cloudflare_accounts ca WHERE " . implode(' AND ', $filters);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalDomains = $countStmt->fetchColumn();
$totalPages = ceil($totalDomains / $perPage);

// Получаем домены
$orderBy = match($sort_by) {
    'group_name' => 'COALESCE(g.name, "Без группы")',
    'email' => 'cc.email',
    'dns_ip' => 'ca.dns_ip',
    'ssl_mode' => 'ca.ssl_mode',
    default => 'ca.domain'
};

$sql = "
    SELECT ca.*, cc.email, g.name AS group_name 
    FROM cloudflare_accounts ca 
    JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
    LEFT JOIN groups g ON ca.group_id = g.id 
    WHERE " . implode(' AND ', $filters) . "
    ORDER BY $orderBy $sort_order 
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$domains = $stmt->fetchAll();

// Функции для отображения
function getSSLModeInfo($mode) {
    // Валидные режимы SSL согласно официальной документации Cloudflare API v4
    // https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/
    $modes = [
        'off' => ['name' => 'Off', 'class' => 'danger', 'description' => 'SSL отключен - не рекомендуется'],
        'flexible' => ['name' => 'Flexible', 'class' => 'warning', 'description' => 'Браузер ↔ Cloudflare зашифрован, Cloudflare ↔ Origin незашифрован'],
        'full' => ['name' => 'Full', 'class' => 'info', 'description' => 'Полное шифрование (самоподписанный сертификат на origin)'],
        'strict' => ['name' => 'Full (Strict)', 'class' => 'success', 'description' => 'Полное шифрование с проверкой действительного сертификата']
    ];
    
    // Обрабатываем неизвестные режимы
    if (!isset($modes[$mode])) {
        // Логируем неизвестный режим для отладки
        error_log("Unknown SSL mode detected: " . $mode);
        return [
            'name' => 'Неизвестно (' . htmlspecialchars($mode) . ')', 
            'class' => 'secondary',
            'description' => 'Неизвестный SSL режим'
        ];
    }
    
    return $modes[$mode];
}

function getDomainStatusInfo($status, $httpCode = null) {
    // Если есть HTTP код, используем его для определения статуса
    if ($httpCode !== null) {
        if ($httpCode === 200) {
            return [
                'name' => "HTTP {$httpCode}",
                'class' => 'success',
                'icon' => 'check-circle'
            ];
        } elseif ($httpCode > 0) {
            return [
                'name' => "HTTP {$httpCode}",
                'class' => 'danger',
                'icon' => 'exclamation-triangle'
            ];
        } else {
            return [
                'name' => 'Не отвечает',
                'class' => 'danger',
                'icon' => 'times-circle'
            ];
        }
    }
    
    // Старая логика для совместимости
    $statuses = [
        'online_ok' => ['name' => 'HTTP 200', 'class' => 'success', 'icon' => 'check-circle'],
        'online_error' => ['name' => 'Ошибка HTTP', 'class' => 'danger', 'icon' => 'exclamation-triangle'],
        'online_https' => ['name' => 'Online (HTTPS)', 'class' => 'success', 'icon' => 'check-circle'],
        'online_http' => ['name' => 'Online (HTTP)', 'class' => 'warning', 'icon' => 'exclamation-triangle'],
        'offline' => ['name' => 'Недоступен', 'class' => 'danger', 'icon' => 'times-circle'],
        'unknown' => ['name' => 'Неизвестно', 'class' => 'secondary', 'icon' => 'question-circle']
    ];
    return $statuses[$status] ?? $statuses['unknown'];
}

function formatNameservers($nsRecords) {
    if ($nsRecords === null || $nsRecords === '') {
        return '<small class="text-muted">NS: не настроены</small>';
    }
    
    // Парсим JSON если это строка
    if (is_string($nsRecords)) {
        $nsArray = json_decode($nsRecords, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($nsArray)) {
            return '<small class="text-muted">NS: ошибка данных</small>';
        }
    } else {
        $nsArray = $nsRecords;
    }
    
    if (empty($nsArray)) {
        return '<small class="text-muted">NS: не настроены</small>';
    }
    
    // Создаем уникальный ID для этого набора NS
    $nsId = 'ns_' . md5(implode(',', $nsArray));
    
    // Показываем все NS серверы полностью
    $nsDisplay = array_map(function($ns) {
        return htmlspecialchars($ns);
    }, $nsArray);
    
    $fullNSList = implode(', ', $nsArray);
    $displayNSList = implode('<br>', $nsDisplay);
    
    $result = '<div class="ns-container">';
    $result .= '<small class="text-muted">NS (' . count($nsArray) . '):</small><br>';
    $result .= '<div class="ns-list" style="font-size: 0.85em; line-height: 1.3; max-width: 200px; word-break: break-all;">';
    $result .= $displayNSList;
    $result .= '</div>';
    
    // Добавляем кнопки для копирования
    $result .= '<div class="ns-actions mt-1">';
    $result .= '<button class="btn btn-outline-secondary btn-xs me-1" onclick="copyNSToClipboard(\'' . htmlspecialchars($fullNSList, ENT_QUOTES) . '\')" title="Копировать все NS">';
    $result .= '<i class="fas fa-copy"></i>';
    $result .= '</button>';
    $result .= '<button class="btn btn-outline-info btn-xs" onclick="showNSModal(\'' . $nsId . '\', ' . htmlspecialchars(json_encode($nsArray), ENT_QUOTES) . ')" title="Показать полностью">';
    $result .= '<i class="fas fa-expand"></i>';
    $result .= '</button>';
    $result .= '</div>';
    $result .= '</div>';
    
    return $result;
}

?>

<?php include 'sidebar.php'; ?>

<style>
@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}

.delete-animation {
    animation: fadeOut 0.5s ease-out;
}

.btn-xs {
    padding: 0.15rem 0.3rem;
    font-size: 0.75rem;
    line-height: 1.2;
    border-radius: 0.2rem;
}

.ns-container {
    background: #f8f9fa;
    padding: 0.5rem;
    border-radius: 0.3rem;
    border: 1px solid #e9ecef;
}

.ns-list {
    font-family: 'Courier New', monospace;
    background: white;
    padding: 0.3rem;
    border-radius: 0.2rem;
    border: 1px solid #dee2e6;
    margin: 0.2rem 0;
}

.dns-info {
    font-size: 0.9em;
    color: #495057;
}

/* Улучшения для DNS колонки */
.table td {
    vertical-align: top;
    padding: 0.75rem 0.5rem;
}

.table th:nth-child(5), /* DNS IP колонка */
.table td:nth-child(5) {
    min-width: 250px;
    max-width: 300px;
}

.ns-container {
    max-width: 280px;
}

.ns-actions .btn {
    margin-right: 0.2rem;
}

/* Адаптивность для мобильных */
@media (max-width: 768px) {
    .table th:nth-child(5),
    .table td:nth-child(5) {
        min-width: 200px;
        max-width: 250px;
    }
    
    .ns-container {
        max-width: 220px;
    }
}
</style>

<div class="content">
    <!-- Уведомления -->
    <?php if ($notification): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($notification); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Основные инструменты -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h5 class="card-title text-warning">
                        <i class="fas fa-search me-2"></i>Диагностика сертификатов
                    </h5>
                    <p class="card-text">Проверка и исправление проблем с SSL сертификатами</p>
                    <a href="debug_certificates.php" class="btn btn-warning" target="_blank">
                        <i class="fas fa-stethoscope me-1"></i>Открыть диагностику
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h5 class="card-title text-primary">
                        <i class="fas fa-cogs me-2"></i>Массовые операции
                    </h5>
                    <p class="card-text">Массовая смена IP, HTTPS и TLS настроек</p>
                    <a href="mass_operations.php" class="btn btn-primary" target="_blank">
                        <i class="fas fa-magic me-1"></i>Открыть операции
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h5 class="card-title text-success">
                        <i class="fas fa-tasks me-2"></i>Управление очередями
                    </h5>
                    <p class="card-text">Мониторинг и управление задачами в очереди</p>
                    <a href="queue_dashboard.php" class="btn btn-success" target="_blank">
                        <i class="fas fa-list-ul me-1"></i>Открыть очереди
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Основная таблица -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Домены (<?php echo $totalDomains; ?>)</h5>
            <button class="btn btn-outline-info btn-sm" onclick="openAddTokenModal()" title="Добавить API токен">
                <i class="fas fa-key me-1"></i>Добавить API токен
            </button>
        </div>
        
        <div class="card-body">
            <!-- Фильтры -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <select id="groupFilter" class="form-select" onchange="applyFilters()">
                        <option value="">Все группы</option>
                        <option value="none" <?php echo $group_id === 'none' ? 'selected' : ''; ?>>Без группы</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>" <?php echo $group_id == $group['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($group['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <input type="text" id="searchInput" class="form-control" placeholder="Поиск домена..." 
                           value="<?php echo htmlspecialchars($search); ?>" onkeyup="searchDomains(event)">
                </div>
                
                <div class="col-md-4">
                    <button class="btn btn-outline-primary" onclick="refreshPage()">
                        <i class="fas fa-sync me-1"></i>Обновить
                    </button>
                </div>
            </div>

            <!-- Таблица доменов -->
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                            <th><a href="#" onclick="sortBy('domain')">Домен <i class="fas fa-sort"></i></a></th>
                            <th><a href="#" onclick="sortBy('group_name')">Группа <i class="fas fa-sort"></i></a></th>
                            <th><a href="#" onclick="sortBy('email')">Email <i class="fas fa-sort"></i></a></th>
                            <th><a href="#" onclick="sortBy('dns_ip')">DNS IP & NS <i class="fas fa-sort"></i></a></th>
                            <th>HTTPS</th>
                            <th>TLS</th>
                            <th><a href="#" onclick="sortBy('ssl_mode')">SSL Mode <i class="fas fa-sort"></i></a></th>
                            <th>Статус</th>
                            <th>Сертификаты</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($domains as $domain): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="domain-checkbox" value="<?php echo $domain['id']; ?>">
                                </td>
                                <td><?php echo htmlspecialchars($domain['domain'] ?? 'Не указано'); ?></td>
                                <td><?php echo htmlspecialchars($domain['group_name'] ?? 'Без группы'); ?></td>
                                <td><?php echo htmlspecialchars($domain['email'] ?? 'Не указано'); ?></td>
                                <td id="dns-<?php echo $domain['id']; ?>">
                                    <div class="dns-info mb-2">
                                        <strong>IP:</strong> <?php echo htmlspecialchars($domain['dns_ip'] ?? 'Не указано'); ?>
                                    </div>
                                    <?php echo formatNameservers($domain['ns_records'] ?? ''); ?>
                                </td>
                                <td id="https-<?php echo $domain['id']; ?>">
                                    <?php echo ($domain['always_use_https'] ?? 0) ? 'Вкл' : 'Выкл'; ?>
                                </td>
                                <td id="tls-<?php echo $domain['id']; ?>">
                                    <?php echo htmlspecialchars($domain['min_tls_version'] ?? 'Не указано'); ?>
                                </td>
                                <td id="ssl-<?php echo $domain['id']; ?>">
                                    <?php 
                                    $sslMode = $domain['ssl_mode'] ?? 'unknown';
                                    $modeInfo = getSSLModeInfo($sslMode);
                                    ?>
                                    <span class="badge bg-<?php echo $modeInfo['class']; ?>" 
                                          title="<?php echo htmlspecialchars($modeInfo['description'] ?? 'SSL режим'); ?>">
                                        <?php echo $modeInfo['name']; ?>
                                    </span>
                                </td>
                                <td id="status-<?php echo $domain['id']; ?>">
                                    <?php 
                                    $status = $domain['domain_status'] ?? 'unknown';
                                    $statusInfo = getDomainStatusInfo($status, $domain['http_code'] ?? null);
                                    ?>
                                    <span class="badge bg-<?php echo $statusInfo['class']; ?>" 
                                          title="Последняя проверка: <?php echo $domain['last_check'] ?? 'Никогда'; ?>">
                                        <i class="fas fa-<?php echo $statusInfo['icon']; ?> me-1"></i>
                                        <?php echo $statusInfo['name']; ?>
                                    </span>
                                </td>
                                <td id="cert-<?php echo $domain['id']; ?>">
                                    <?php if ($domain['ssl_cert_id']): ?>
                                        <span class="badge bg-success" title="SSL сертификат создан">
                                            <i class="fas fa-certificate me-1"></i>Есть
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="fas fa-times me-1"></i>Нет
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-info" onclick="updateDNS(<?php echo $domain['id']; ?>)" 
                                                title="Обновить DNS IP">
                                            <i class="fas fa-globe"></i>
                                        </button>
                                        <button class="btn btn-outline-success" onclick="checkSSL(<?php echo $domain['id']; ?>)" 
                                                title="Проверить SSL">
                                            <i class="fas fa-shield-alt"></i>
                                        </button>
                                        <button class="btn btn-outline-warning" onclick="checkStatus(<?php echo $domain['id']; ?>)" 
                                                title="Проверить статус">
                                            <i class="fas fa-heartbeat"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteDomain(<?php echo $domain['id']; ?>, '<?php echo htmlspecialchars($domain['domain'], ENT_QUOTES); ?>')" 
                                                title="Удалить домен">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="purgeCache(<?php echo $domain['id']; ?>)" 
                                                title="Очистить кеш">
                                            <i class="fas fa-broom"></i>
                                        </button>
                                        <button class="btn btn-outline-dark" onclick="toggleUnderAttack(<?php echo $domain['id']; ?>, true)" 
                                                title="Under Attack ON">
                                            <i class="fas fa-bolt"></i>
                                        </button>
                                        <button class="btn btn-outline-dark" onclick="toggleUnderAttack(<?php echo $domain['id']; ?>, false)" 
                                                title="Under Attack OFF">
                                            <i class="fas fa-bolt-slash"></i>
                                        </button>
                                        <button class="btn btn-outline-warning" onclick="applyPageRule(<?php echo $domain['id']; ?>, 'cache_static')" 
                                                title="Page Rule: Cache Everything">
                                            <i class="fas fa-scroll"></i>
                                        </button>
                                        <button class="btn btn-outline-info" onclick="setupEmailRouting(<?php echo $domain['id']; ?>)" 
                                                title="Email Routing">
                                            <i class="fas fa-envelope"></i>
                                        </button>
                                        <button class="btn btn-outline-primary" onclick="manageWorkers(<?php echo $domain['id']; ?>)"
                                                title="Cloudflare Workers">
                                            <i class="fas fa-code"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="createDnsRecordPrompt(<?php echo $domain['id']; ?>)" 
                                                title="Добавить DNS запись">
                                            <i class="fas fa-circle-plus"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="deleteDnsRecordPrompt(<?php echo $domain['id']; ?>)" 
                                                title="Удалить DNS запись">
                                            <i class="fas fa-circle-minus"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Массовые действия -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Массовые действия с выбранными доменами</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <button class="btn btn-info w-100" onclick="bulkUpdateDNS()" title="Обновить DNS IP">
                                <i class="fas fa-globe me-1"></i>DNS IP
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-success w-100" onclick="bulkCheckSSL()" title="Проверить SSL">
                                <i class="fas fa-shield-alt me-1"></i>SSL
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-warning w-100" onclick="bulkCheckStatus()" title="Проверить статус">
                                <i class="fas fa-heartbeat me-1"></i>Статус
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100" onclick="bulkCreateCerts()" title="Создать сертификаты">
                                <i class="fas fa-certificate me-1"></i>Сертификаты
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-secondary w-100" onclick="bulkAddNSToQueue()" title="Добавить NS задачи в очередь">
                                <i class="fas fa-server me-1"></i>NS в очередь
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-danger w-100" onclick="megaOperation()" title="DNS IP + SSL статус + Статус домена + SSL сертификаты">
                                <i class="fas fa-rocket me-1"></i>🚀 МЕГА-ОПЕРАЦИЯ
                            </button>
                        </div>
                        <div class="col-md-2 mt-2 mt-md-0">
                            <button class="btn btn-outline-primary w-100" onclick="openBulkWorkersModal()" title="Cloudflare Workers">
                                <i class="fas fa-code me-1"></i>Workers
                            </button>
                        </div>
                    </div>
                    <div class="row mt-3 g-3">
                        <div class="col-md-4">
                            <button class="btn btn-outline-danger w-100" onclick="bulkDeleteDomains()" title="Удалить выбранные домены">
                                <i class="fas fa-trash me-1"></i>Удалить выбранные
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-info w-100" onclick="bulkAddAllNSToQueue()" title="Добавить в очередь NS обновления для всех доменов">
                                <i class="fas fa-plus-circle me-1"></i>Все NS в очередь
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-success w-100" onclick="exportAllNS()" title="Экспортировать все NS серверы">
                                <i class="fas fa-download me-1"></i>Экспорт NS
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Пагинация -->
            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&<?php echo http_build_query($_GET); ?>">
                                    Предыдущая
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&<?php echo http_build_query($_GET); ?>">
                                    Следующая
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Модальное окно для операций -->
<div class="modal fade" id="operationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="operationTitle">Выполнение операции</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="progress mb-3">
                    <div class="progress-bar" id="progressBar" style="width: 0%">0%</div>
                </div>
                <div id="operationLog" style="height: 300px; overflow-y: auto; background: #f8f9fa; padding: 1rem; font-family: monospace; font-size: 0.9rem;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-danger" id="stopOperation" style="display: none;">Остановить</button>
            </div>
        </div>
    </div>
</div>

<!-- Подключаем модальные окна из sidebar -->
<?php include 'modals.php'; ?>

<!-- Модальное окно для просмотра NS серверов -->
<div class="modal fade" id="nsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-server me-2"></i>NS серверы
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Полный список NS серверов для удобного копирования
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Список NS серверов (по одному на строку):</label>
                    <textarea id="nsTextarea" class="form-control" rows="8" readonly style="font-family: 'Courier New', monospace; font-size: 0.9rem;"></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">NS серверы через запятую:</label>
                    <input type="text" id="nsCommaSeparated" class="form-control" readonly style="font-family: 'Courier New', monospace; font-size: 0.9rem;">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Для конфигурации DNS:</label>
                    <textarea id="nsDnsConfig" class="form-control" rows="6" readonly style="font-family: 'Courier New', monospace; font-size: 0.9rem;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary" onclick="copyAllNSFormats()">
                    <i class="fas fa-copy me-1"></i>Копировать список
                </button>
                <button type="button" class="btn btn-success" onclick="copyNSCommaSeparated()">
                    <i class="fas fa-copy me-1"></i>Копировать через запятую
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для добавления API токена -->
<div class="modal fade" id="addTokenModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-key me-2"></i>Добавить API токен
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="tokenModalStatus" class="mb-3"></div>
                
                <form id="addTokenForm">
                    <div class="mb-3">
                        <label for="tokenAccount" class="form-label">Аккаунт <span class="text-danger">*</span></label>
                        <select class="form-select" id="tokenAccount" required>
                            <option value="">— Выберите аккаунт —</option>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>">
                                    <?php echo htmlspecialchars($account['email'] ?? 'Аккаунт #' . $account['id']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tokenName" class="form-label">Название токена <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tokenName" placeholder="Например: Production API Token" required>
                        <div class="form-text">Укажите понятное название для идентификации токена</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tokenValue" class="form-label">API токен <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tokenValue" placeholder="Вставьте токен из Cloudflare" required>
                        <div class="form-text">Скопируйте токен из панели Cloudflare</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tokenTag" class="form-label">Тег (необязательно)</label>
                        <input type="text" class="form-control" id="tokenTag" placeholder="Например: production, staging">
                        <div class="form-text">Дополнительный тег для категоризации</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveApiToken()">
                    <i class="fas fa-save me-1"></i>Сохранить токен
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Глобальные переменные
let operationModal = null;
let currentOperation = null;
let workerModalInstance = null;
let bulkWorkerModalInstance = null;
let tokenModalInstance = null;
let workerCurrentDomainId = null;
let workerCurrentDomainName = '';
let workerTemplatesCache = [];

document.addEventListener('DOMContentLoaded', function() {
    operationModal = new bootstrap.Modal(document.getElementById('operationModal'));
    const workerModalEl = document.getElementById('manageWorkerModal');
    if (workerModalEl) {
        workerModalInstance = new bootstrap.Modal(workerModalEl);
    }
    const bulkWorkerModalEl = document.getElementById('bulkWorkerModal');
    if (bulkWorkerModalEl) {
        bulkWorkerModalInstance = new bootstrap.Modal(bulkWorkerModalEl);
    }
    const tokenModalEl = document.getElementById('addTokenModal');
    if (tokenModalEl) {
        tokenModalInstance = new bootstrap.Modal(tokenModalEl);
    }

    const saveTemplateCheckbox = document.getElementById('workerSaveTemplate');
    if (saveTemplateCheckbox) {
        saveTemplateCheckbox.addEventListener('change', toggleWorkerTemplateNameField);
    }

    document.querySelectorAll('input[name="bulkWorkerScope"]').forEach(radio => {
        radio.addEventListener('change', handleBulkWorkerScopeChange);
    });
});

// Функция для форматирования NS серверов в JavaScript
function formatNameserversJS(nsRecords) {
    if (!nsRecords || nsRecords === '') {
        return '<small class="text-muted">NS: не указаны</small>';
    }
    
    let nsArray;
    if (typeof nsRecords === 'string') {
        try {
            nsArray = JSON.parse(nsRecords);
        } catch (e) {
            return '<small class="text-muted">NS: ошибка данных</small>';
        }
    } else {
        nsArray = nsRecords;
    }
    
    if (!Array.isArray(nsArray) || nsArray.length === 0) {
        return '<small class="text-muted">NS: не настроены</small>';
    }
    
    // Создаем уникальный ID для этого набора NS
    const nsId = 'ns_' + Math.random().toString(36).substr(2, 9);
    
    // Показываем все NS серверы полностью
    const nsDisplay = nsArray.map(ns => ns).join('<br>');
    const fullNSList = nsArray.join(', ');
    
    let result = '<div class="ns-container">';
    result += '<small class="text-muted">NS (' + nsArray.length + '):</small><br>';
    result += '<div class="ns-list" style="font-size: 0.85em; line-height: 1.3; max-width: 200px; word-break: break-all;">';
    result += nsDisplay;
    result += '</div>';
    
    // Добавляем кнопки для копирования
    result += '<div class="ns-actions mt-1">';
    result += '<button class="btn btn-outline-secondary btn-xs me-1" onclick="copyNSToClipboard(\'' + fullNSList.replace(/'/g, "\\'") + '\')" title="Копировать все NS">';
    result += '<i class="fas fa-copy"></i>';
    result += '</button>';
    result += '<button class="btn btn-outline-info btn-xs" onclick="showNSModal(\'' + nsId + '\', ' + JSON.stringify(nsArray) + ')" title="Показать полностью">';
    result += '<i class="fas fa-expand"></i>';
    result += '</button>';
    result += '</div>';
    result += '</div>';
    
    return result;
}

// Функция для получения информации о SSL режиме в JavaScript
function getSSLModeInfo(mode) {
    // Валидные режимы SSL согласно официальной документации Cloudflare
    const modes = {
        'off': { name: 'Off', class: 'danger', description: 'SSL отключен' },
        'flexible': { name: 'Flexible', class: 'warning', description: 'Браузер ↔ Cloudflare зашифрован' },
        'full': { name: 'Full', class: 'info', description: 'Полное шифрование (без проверки сертификата)' },
        'strict': { name: 'Full (strict)', class: 'success', description: 'Полное шифрование с проверкой сертификата' }
    };
    
    // Обрабатываем неизвестные режимы
    if (!modes[mode]) {
        console.warn('Unknown SSL mode detected:', mode);
        return { 
            name: 'Неизвестно (' + mode + ')', 
            class: 'secondary',
            description: 'Неизвестный SSL режим'
        };
    }
    
    return modes[mode];
}

// Функции фильтрации и поиска
function applyFilters() {
    const group = document.getElementById('groupFilter').value;
    const search = document.getElementById('searchInput').value;
    
    const params = new URLSearchParams(window.location.search);
    params.set('page', '1');
    
    if (group) {
        params.set('group_id', group);
    } else {
        params.delete('group_id');
    }
    
    if (search) {
        params.set('search', search);
    } else {
        params.delete('search');
    }
    
    window.location.search = params.toString();
}

function searchDomains(event) {
    if (event.key === 'Enter') {
        applyFilters();
    }
}

function sortBy(column) {
    const params = new URLSearchParams(window.location.search);
    const currentSort = params.get('sort_by');
    const currentOrder = params.get('sort_order');
    
    params.set('sort_by', column);
    params.set('sort_order', (currentSort === column && currentOrder === 'asc') ? 'desc' : 'asc');
    params.set('page', '1');
    
    window.location.search = params.toString();
}

function refreshPage() {
    window.location.reload();
}

// Функции выбора доменов
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.domain-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

function getSelectedDomains() {
    return Array.from(document.querySelectorAll('.domain-checkbox:checked')).map(cb => cb.value);
}

// Функция для обновления счетчика выбранных доменов
function updateSelectedCount() {
    const selected = getSelectedDomains();
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.domain-checkbox');
    
    // Обновляем состояние "Выбрать все"
    if (selected.length === 0) {
        selectAll.indeterminate = false;
        selectAll.checked = false;
    } else if (selected.length === checkboxes.length) {
        selectAll.indeterminate = false;
        selectAll.checked = true;
    } else {
        selectAll.indeterminate = true;
        selectAll.checked = false;
    }
}

// Функции уведомлений
function showNotification(message, type = 'info') {
    const alertClass = {
        'info': 'alert-info',
        'success': 'alert-success',
        'warning': 'alert-warning',
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

function addLog(message, type = 'info') {
    const log = document.getElementById('operationLog');
    if (!log) return;
    
    const timestamp = new Date().toLocaleTimeString();
    const colorClass = {
        'info': 'text-info',
        'success': 'text-success',
        'warning': 'text-warning',
        'error': 'text-danger'
    }[type] || 'text-muted';
    
    const logEntry = document.createElement('div');
    logEntry.className = colorClass;
    logEntry.textContent = `[${timestamp}] ${message}`;
    
    log.appendChild(logEntry);
    log.scrollTop = log.scrollHeight;
}

// Функции операций
async function performOperation(operationType, domainIds, title) {
    document.getElementById('operationTitle').textContent = title;
    addLog(`Начинаем ${title.toLowerCase()}...`, 'info');
    operationModal.show();
    
    const progressBar = document.getElementById('progressBar');
    let completed = 0;
    const total = domainIds.length;
    
    for (let i = 0; i < domainIds.length; i++) {
        const domainId = domainIds[i];
        
        try {
            addLog(`Обработка домена ${i + 1}/${total}...`, 'info');
            
            const response = await fetch(`${operationType}.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `domain_id=${domainId}`
            });
            
            // Проверяем статус HTTP ответа
            if (!response.ok) {
                addLog(`❌ Домен ${i + 1}: HTTP ошибка ${response.status} ${response.statusText}`, 'error');
                completed++;
                continue;
            }
            
            // Получаем текст ответа для диагностики
            const responseText = await response.text();
            
            // Проверяем, что ответ не пустой
            if (!responseText) {
                addLog(`❌ Домен ${i + 1}: Пустой ответ от сервера`, 'error');
                completed++;
                continue;
            }
            
            // Пытаемся парсить JSON
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (jsonError) {
                addLog(`❌ Домен ${i + 1}: Неверный JSON ответ`, 'error');
                addLog(`📄 Ответ сервера: ${responseText.substring(0, 200)}...`, 'error');
                completed++;
                continue;
            }
            
            if (result.success) {
                addLog(`✅ Домен ${i + 1}: ${result.message || 'Успешно'}`, 'success');
                updateDomainRow(domainId, operationType, result);
            } else {
                addLog(`❌ Домен ${i + 1}: ${result.error || 'Неизвестная ошибка'}`, 'error');
            }
        } catch (error) {
            addLog(`❌ Домен ${i + 1}: Ошибка JavaScript - ${error.message}`, 'error');
            console.error(`Fetch error for domain ${domainId}:`, error);
        }
        
        completed++;
        const progress = Math.round((completed / total) * 100);
        progressBar.style.width = `${progress}%`;
        progressBar.textContent = `${progress}%`;
        
        // Задержка между запросами
        if (i < domainIds.length - 1) {
            await new Promise(resolve => setTimeout(resolve, 500));
        }
    }
    
    addLog(`Операция завершена. Обработано: ${completed}/${total}`, 'success');
}

function updateDomainRow(domainId, operationType, result) {
    // Обновляем данные в таблице
    if (operationType === 'update_dns_ip' && result.dns_ip) {
        const cell = document.getElementById(`dns-${domainId}`);
        if (cell) {
            // Обновляем DNS IP и добавляем NS серверы
            let dnsContent = '<div class="dns-info mb-2"><strong>IP:</strong> ' + result.dns_ip + '</div>';
            if (result.name_servers && result.name_servers.length > 0) {
                dnsContent += formatNameserversJS(result.name_servers);
            } else {
                dnsContent += '<small class="text-muted">NS: обновляются...</small>';
            }
            cell.innerHTML = dnsContent;
        }
    }
    
    if (operationType === 'check_ssl_status') {
        if (result.ssl_mode) {
            const cell = document.getElementById(`ssl-${domainId}`);
            if (cell) {
                const modeInfo = getSSLModeInfo(result.ssl_mode);
                cell.innerHTML = `<span class="badge bg-${modeInfo.class}" title="${modeInfo.description}">${modeInfo.name}</span>`;
            }
        }
        
        if (result.always_use_https !== undefined) {
            const cell = document.getElementById(`https-${domainId}`);
            if (cell) cell.textContent = result.always_use_https ? 'Вкл' : 'Выкл';
        }
        
        if (result.min_tls_version) {
            const cell = document.getElementById(`tls-${domainId}`);
            if (cell) cell.textContent = result.min_tls_version;
        }
    }
    
    if (operationType === 'check_domain_status') {
        const cell = document.getElementById(`status-${domainId}`);
        if (cell) {
            let statusClass, statusIcon, statusText;
            
            // Проверяем есть ли http_code в ответе
            if (result.http_code !== undefined) {
                if (result.http_code === 200) {
                    statusClass = 'success';
                    statusIcon = 'check-circle';
                    statusText = `HTTP ${result.http_code}`;
                } else if (result.http_code > 0) {
                    statusClass = 'danger';
                    statusIcon = 'exclamation-triangle';
                    statusText = `HTTP ${result.http_code}`;
                } else {
                    statusClass = 'danger';
                    statusIcon = 'times-circle';
                    statusText = 'Не отвечает';
                }
            } else {
                // Если http_code отсутствует, используем domain_status
                if (result.domain_status === 'online_ok') {
                    statusClass = 'success';
                    statusIcon = 'check-circle';
                    statusText = 'Доступен';
                } else if (result.domain_status === 'online_error') {
                    statusClass = 'danger';
                    statusIcon = 'exclamation-triangle';
                    statusText = 'Ошибка';
                } else {
                    statusClass = 'danger';
                    statusIcon = 'times-circle';
                    statusText = 'Недоступен';
                }
            }
            
            cell.innerHTML = `
                <span class="badge bg-${statusClass}" title="Последняя проверка: ${new Date().toLocaleString()}">
                    <i class="fas fa-${statusIcon} me-1"></i>${statusText}
                </span>
            `;
        }
    }
    
    if (operationType === 'create_certificate' && result.ssl_cert_id) {
        const cell = document.getElementById(`cert-${domainId}`);
        if (cell) {
            cell.innerHTML = `
                <span class="badge bg-success" title="SSL сертификат создан">
                    <i class="fas fa-certificate me-1"></i>Есть
                </span>
            `;
        }
    }
}

// Индивидуальные операции
async function updateDNS(domainId) {
    await addTaskToQueue('update_dns_ip', [domainId], 'Обновление DNS IP');
}

async function checkSSL(domainId) {
    await addTaskToQueue('check_ssl_status', [domainId], 'Проверка SSL статуса');
}

async function checkStatus(domainId) {
    await addTaskToQueue('check_domain_status', [domainId], 'Проверка статуса домена');
}

// Функция добавления задач в очередь (по аналогии с мега операцией)
async function addTaskToQueue(taskType, domainIds, title) {
    try {
        showNotification(`Добавляем в очередь: ${title}...`, 'info');
        
        const tasks = [];
        
        // Добавляем задачи для каждого домена
        for (let domainId of domainIds) {
            const response = await fetch('queue_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'add_task',
                    task_type: taskType,
                    domain_id: domainId,
                    data: {}
                })
            });
            
            const result = await response.json();
            tasks.push(result);
        }
        
        const successCount = tasks.filter(t => t.success).length;
        const errorCount = tasks.length - successCount;
        
        if (successCount > 0) {
            showNotification(
                `✅ Добавлено в очередь: ${successCount} задач${errorCount > 0 ? `, ошибок: ${errorCount}` : ''}`, 
                errorCount > 0 ? 'warning' : 'success'
            );
            
            // Предложить запустить процессор или открить дашборд очередей
            setTimeout(() => {
                if (confirm('Задачи добавлены в очередь! Запустить обработку сейчас?')) {
                    processNSQueue();
                } else if (confirm('Открыть дашборд очередей для мониторинга?')) {
                    window.open('queue_dashboard.php', '_blank');
                }
            }, 1000);
        } else {
            showNotification('❌ Не удалось добавить задачи в очередь', 'error');
        }
        
    } catch (error) {
        showNotification('Ошибка добавления в очередь: ' + error.message, 'error');
    }
}

// Массовые операции через очередь
async function bulkUpdateDNS() {
    const domains = getSelectedDomains();
    if (domains.length === 0) {
        showNotification('Выберите домены для обновления DNS IP', 'warning');
        return;
    }
    await addTaskToQueue('update_dns_ip', domains, `Массовое обновление DNS IP (${domains.length} доменов)`);
}

async function bulkCheckSSL() {
    const domains = getSelectedDomains();
    if (domains.length === 0) {
        showNotification('Выберите домены для проверки SSL', 'warning');
        return;
    }
    await addTaskToQueue('check_ssl_status', domains, `Массовая проверка SSL (${domains.length} доменов)`);
}

async function bulkCheckStatus() {
    const domains = getSelectedDomains();
    if (domains.length === 0) {
        showNotification('Выберите домены для проверки статуса', 'warning');
        return;
    }
    await addTaskToQueue('check_domain_status', domains, `Массовая проверка статуса (${domains.length} доменов)`);
}

async function bulkCreateCerts() {
    const domains = getSelectedDomains();
    if (domains.length === 0) {
        showNotification('Выберите домены для создания сертификатов', 'warning');
        return;
    }
    
    if (confirm(`Создать SSL сертификаты для ${domains.length} доменов через очередь?`)) {
        await addTaskToQueue('create_origin_certificate', domains, `Создание сертификатов (${domains.length} доменов)`);
    }
}

// Удаление отдельного домена
async function deleteDomain(domainId, domainName) {
    if (!confirm(`Вы уверены что хотите удалить домен "${domainName}"?\n\nЭто действие нельзя отменить!`)) {
        return;
    }
    
    try {
        const response = await fetch('delete_domain.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `domain_id=${domainId}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            // Удаляем строку из таблицы
            const row = document.querySelector(`input[value="${domainId}"]`).closest('tr');
            if (row) {
                row.style.animation = 'fadeOut 0.5s';
                setTimeout(() => {
                    row.remove();
                    updateSelectedCount();
                }, 500);
            }
        } else {
            showNotification('Ошибка: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Ошибка при удалении домена: ' + error.message, 'error');
    }
}

// Массовые операции
async function bulkDeleteDomains() {
    const domains = getSelectedDomains();
    if (domains.length === 0) {
        showNotification('Выберите домены для удаления', 'warning');
        return;
    }
    
    if (!confirm(`Вы уверены что хотите удалить ${domains.length} доменов?\n\nЭто действие нельзя отменить!`)) {
        return;
    }
    
    // Показываем модальное окно с прогрессом
    document.getElementById('operationTitle').textContent = 'Массовое удаление доменов';
    addLog(`Начинаем удаление ${domains.length} доменов...`, 'info');
    operationModal.show();
    
    const progressBar = document.getElementById('progressBar');
    let completed = 0;
    let successCount = 0;
    let errorCount = 0;
    
    for (let i = 0; i < domains.length; i++) {
        const domainId = domains[i];
        
        try {
            addLog(`Удаление домена ${i + 1}/${domains.length}...`, 'info');
            
            const response = await fetch('delete_domain.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `domain_id=${domainId}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                addLog(`✅ Домен ${i + 1}: ${result.message}`, 'success');
                // Удаляем строку из таблицы
                const row = document.querySelector(`input[value="${domainId}"]`).closest('tr');
                if (row) {
                    row.style.animation = 'fadeOut 0.5s';
                    setTimeout(() => row.remove(), 500);
                }
                successCount++;
            } else {
                addLog(`❌ Домен ${i + 1}: ${result.error}`, 'error');
                errorCount++;
            }
        } catch (error) {
            addLog(`❌ Домен ${i + 1}: Ошибка соединения - ${error.message}`, 'error');
            errorCount++;
        }
        
        completed++;
        const progress = Math.round((completed / domains.length) * 100);
        progressBar.style.width = `${progress}%`;
        progressBar.textContent = `${progress}%`;
        
        // Задержка между запросами
        if (i < domains.length - 1) {
            await new Promise(resolve => setTimeout(resolve, 300));
        }
    }
    
    addLog(`Удаление завершено! Успешно: ${successCount}, ошибок: ${errorCount}`, successCount > 0 ? 'success' : 'error');
    showNotification(`Удаление завершено! Успешно: ${successCount}, ошибок: ${errorCount}`, 
                   errorCount === 0 ? 'success' : 'warning');
    
    // Обновляем счетчик выбранных
    setTimeout(() => updateSelectedCount(), 1000);
}

async function megaOperation() {
    const domains = getSelectedDomains();
    if (domains.length === 0) {
        showNotification('Выберите домены для МЕГА-ОПЕРАЦИИ', 'warning');
        return;
    }
    
    if (confirm(`🚀 ЗАПУСТИТЬ МЕГА-ОПЕРАЦИЮ для ${domains.length} доменов?\n\nБудут выполнены:\n- Обновление DNS IP\n- Проверка SSL статуса\n- Проверка статуса домена\n- Создание SSL сертификатов`)) {
        document.getElementById('operationTitle').textContent = '🚀 МЕГА-ОПЕРАЦИЯ';
        operationModal.show();
        addLog('🚀 ЗАПУСК МЕГА-ОПЕРАЦИИ!', 'info');
        
        // Выполняем все операции последовательно
        await performOperation('update_dns_ip', domains, 'DNS IP');
        await performOperation('check_ssl_status', domains, 'SSL статус');
        await performOperation('check_domain_status', domains, 'Статус домена');
        await performOperation('create_certificate', domains, 'Создание SSL сертификатов');
        
        addLog('🎉 МЕГА-ОПЕРАЦИЯ ЗАВЕРШЕНА!', 'success');
        addLog('📄 Созданные сертификаты доступны в разделе "SSL Сертификаты"', 'info');
        
        // Добавляем кнопку для быстрого перехода к сертификатам
        setTimeout(() => {
            const viewCertsButton = document.createElement('button');
            viewCertsButton.className = 'btn btn-success mt-2';
            viewCertsButton.innerHTML = '<i class="fas fa-certificate me-1"></i>Просмотреть сертификаты';
            viewCertsButton.onclick = () => window.open('view_certificates.php', '_blank');
            
            const modalFooter = document.querySelector('#operationModal .modal-footer');
            modalFooter.insertBefore(viewCertsButton, modalFooter.firstChild);
        }, 1000);
    }
}

// Функции для работы с NS очередями
async function bulkAddNSToQueue() {
    const domains = getSelectedDomains();
    if (domains.length === 0) {
        showNotification('Выберите домены для добавления NS задач в очередь', 'warning');
        return;
    }
    
    if (!confirm(`Добавить ${domains.length} NS задач в очередь?`)) {
        return;
    }
    
    try {
        showNotification('Добавляем NS задачи в очередь...', 'info');
        
        const response = await fetch('ns_queue_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'add_selected_ns_update',
                domain_ids: domains
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(
                `Добавлено в очередь: ${result.added}, пропущено: ${result.skipped}`, 
                'success'
            );
            
            if (result.errors && result.errors.length > 0) {
                console.log('Ошибки при добавлении задач:', result.errors);
            }
        } else {
            showNotification('Ошибка: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Ошибка сети: ' + error.message, 'error');
    }
}

async function bulkAddAllNSToQueue() {
    if (!confirm('Добавить массовую задачу обновления NS для всех доменов?\n\nОбновятся домены без NS записей или с устаревшими NS.')) {
        return;
    }
    
    // Показать диалог выбора лимита
    const limit = prompt('Сколько доменов обработать за раз? (рекомендуется: 10-20)', '10');
    if (!limit || isNaN(limit) || limit <= 0) {
        showNotification('Отменено или неверный лимит', 'warning');
        return;
    }
    
    try {
        showNotification('Добавляем массовую NS задачу в очередь...', 'info');
        
        const response = await fetch('ns_queue_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'add_bulk_ns_update',
                limit: parseInt(limit)
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            
            // Предложить открыть дашборд очередей
            setTimeout(() => {
                if (confirm('Задача добавлена! Открыть дашборд очередей для мониторинга?')) {
                    window.open('queue_dashboard.php', '_blank');
                }
            }, 1000);
        } else {
            showNotification('Ошибка: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Ошибка сети: ' + error.message, 'error');
    }
}

// Функция для быстрого запуска процессора очередей
async function processNSQueue() {
    try {
        showNotification('Запускаем процессор очередей...', 'info');
        
        const response = await fetch('queue_processor.php?action=process&auth_token=cloudflare_queue_processor_2024', {
            method: 'GET'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(
                `Процессор выполнен: ${result.processed} задач за ${result.execution_time}с`, 
                'success'
            );
            
            // Показать результаты если есть
            if (result.results && result.results.length > 0) {
                console.log('Результаты выполнения очереди:', result.results);
            }
            
            // Предложить обновить страницу для отображения обновленных NS
            setTimeout(() => {
                if (confirm('Процессор завершен! Обновить страницу для отображения обновленных NS?')) {
                    location.reload();
                }
            }, 2000);
        } else {
            showNotification('Ошибка процессора: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Ошибка запуска процессора: ' + error.message, 'error');
    }
}

// Добавляем слушателей событий
document.addEventListener('DOMContentLoaded', function() {
    // Обновляем счетчик выбранных доменов при изменении чекбоксов
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('domain-checkbox')) {
            updateSelectedCount();
        }
    });
    
    // Показываем подсказку о доступности очередей
    const queueTooltip = document.createElement('div');
    queueTooltip.className = 'position-fixed bg-info text-white p-2 rounded';
    queueTooltip.style.cssText = 'bottom: 20px; left: 20px; z-index: 1000; font-size: 0.8em; max-width: 250px;';
    queueTooltip.innerHTML = `
        <i class="fas fa-info-circle me-1"></i>
        <strong>Совет:</strong> Используйте очереди для больших объемов данных - 
        <a href="queue_dashboard.php" target="_blank" class="text-white">
            <u>открыть дашборд очередей</u>
        </a>
    `;
    
    document.body.appendChild(queueTooltip);
    
    // Скрываем подсказку через 10 секунд
    setTimeout(() => {
        if (queueTooltip.parentNode) {
            queueTooltip.style.animation = 'fadeOut 1s';
            setTimeout(() => queueTooltip.remove(), 1000);
        }
    }, 10000);
});

// Функции для работы с NS серверами
function copyNSToClipboard(nsText) {
    navigator.clipboard.writeText(nsText).then(function() {
        showNotification('✅ NS серверы скопированы в буфер обмена', 'success');
    }).catch(function(err) {
        // Fallback для старых браузеров
        const textArea = document.createElement('textarea');
        textArea.value = nsText;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('✅ NS серверы скопированы в буфер обмена', 'success');
    });
}

let nsModal;
let currentNSData = [];

function showNSModal(nsId, nsArray) {
    if (!nsModal) {
        nsModal = new bootstrap.Modal(document.getElementById('nsModal'));
    }
    
    currentNSData = nsArray;
    
    // Заполняем поля в модальном окне
    const nsTextarea = document.getElementById('nsTextarea');
    const nsCommaSeparated = document.getElementById('nsCommaSeparated');
    const nsDnsConfig = document.getElementById('nsDnsConfig');
    
    // Список по строкам
    nsTextarea.value = nsArray.join('\n');
    
    // Через запятую
    nsCommaSeparated.value = nsArray.join(', ');
    
    // Для DNS конфигурации
    const dnsConfig = nsArray.map((ns, index) => {
        return `NS${index + 1}: ${ns}`;
    }).join('\n');
    nsDnsConfig.value = dnsConfig;
    
    // Показываем модальное окно
    nsModal.show();
}

function copyAllNSFormats() {
    const nsText = currentNSData.join('\n');
    copyNSToClipboard(nsText);
}

function copyNSCommaSeparated() {
    const nsText = currentNSData.join(', ');
    copyNSToClipboard(nsText);
}

function exportAllNS() {
    const domains = getSelectedDomains();
    if (domains.length === 0) {
        showNotification('Выберите домены для экспорта NS или воспользуйтесь опцией "Экспорт всех NS"', 'warning');
        return;
    }
    
    // Собираем NS серверы из выбранных доменов
    let allNS = new Set(); // Используем Set для исключения дубликатов
    let domainNSMap = new Map();
    
    domains.forEach(domainId => {
        const dnsCell = document.getElementById(`dns-${domainId}`);
        if (dnsCell) {
            const nsContainers = dnsCell.querySelectorAll('.ns-list');
            nsContainers.forEach(container => {
                const nsText = container.textContent || container.innerText;
                if (nsText && nsText !== 'NS: не указаны' && nsText !== 'NS: не настроены') {
                    const nsServers = nsText.split('\n').filter(ns => ns.trim() && !ns.includes('NS:'));
                    nsServers.forEach(ns => {
                        const cleanNS = ns.trim();
                        if (cleanNS) {
                            allNS.add(cleanNS);
                            if (!domainNSMap.has(domainId)) {
                                domainNSMap.set(domainId, []);
                            }
                            domainNSMap.get(domainId).push(cleanNS);
                        }
                    });
                }
            });
        }
    });
    
    if (allNS.size === 0) {
        showNotification('Не найдено NS серверов у выбранных доменов', 'warning');
        return;
    }
    
    // Формируем текст для экспорта
    const uniqueNS = Array.from(allNS).sort();
    const exportText = uniqueNS.join('\n');
    
    // Показываем результат в модальном окне
    if (!nsModal) {
        nsModal = new bootstrap.Modal(document.getElementById('nsModal'));
    }
    
    document.getElementById('nsTextarea').value = exportText;
    document.getElementById('nsCommaSeparated').value = uniqueNS.join(', ');
    
    // Для DNS конфигурации
    const dnsConfig = uniqueNS.map((ns, index) => {
        return `NS${index + 1}: ${ns}`;
    }).join('\n');
    document.getElementById('nsDnsConfig').value = dnsConfig;
    
    currentNSData = uniqueNS;
    nsModal.show();
    
    showNotification(`Найдено ${uniqueNS.length} уникальных NS серверов из ${domains.length} доменов`, 'success');
}

async function manageWorkers(domainId, skipModalShow = false) {
    if (!domainId) return;
    workerCurrentDomainId = domainId;
    workerTemplatesCache = workerTemplatesCache || [];

    const loader = document.getElementById('workerModalLoader');
    const content = document.getElementById('workerModalContent');
    const domainNameEl = document.getElementById('workerModalDomainName');
    const statusEl = document.getElementById('workerModalStatus');
    const templateSelect = document.getElementById('workerTemplateSelect');
    const routeInput = document.getElementById('workerRoutePattern');
    const customScript = document.getElementById('workerCustomScript');

    if (!skipModalShow && workerModalInstance) {
        workerModalInstance.show();
    }

    if (loader && content) {
        loader.classList.remove('d-none');
        content.classList.add('d-none');
    }
    if (statusEl) {
        statusEl.textContent = '';
    }

    try {
        const response = await fetch('workers_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_domain', domain_id: domainId })
        });

        const result = await response.json();
        if (!result.success) {
            showNotification(result.error || 'Не удалось получить данные Workers', 'error');
            return;
        }

        const domain = result.domain || {};
        workerCurrentDomainName = domain.name || '';
        if (domainNameEl) {
            domainNameEl.textContent = workerCurrentDomainName;
        }

        workerTemplatesCache = result.templates || [];
        populateWorkerTemplatesSelect(templateSelect, workerTemplatesCache, true);
        populateWorkerTemplatesSelect(document.getElementById('bulkWorkerTemplate'), workerTemplatesCache, true);

        if (routeInput) {
            routeInput.value = `{{domain}}/*`;
        }
        if (customScript) {
            customScript.value = '';
        }

        const routesContainer = document.getElementById('workerRoutesContainer');
        if (routesContainer) {
            routesContainer.innerHTML = renderWorkerRoutes(result.routes, result.stored_routes || []);
            attachWorkerRouteHandlers();
        }

        if (document.getElementById('workerDomainId')) {
            document.getElementById('workerDomainId').value = domainId;
        }

        if (loader && content) {
            loader.classList.add('d-none');
            content.classList.remove('d-none');
        }
        setWorkerModalStatus('Данные успешно загружены', 'success');
    } catch (e) {
        showNotification('Ошибка загрузки Workers: ' + e.message, 'error');
        setWorkerModalStatus('Ошибка: ' + e.message, 'error');
    }
}

function populateWorkerTemplatesSelect(selectElement, templates, includePlaceholder = false) {
    if (!selectElement) return;
    const previous = selectElement.value;
    selectElement.innerHTML = '';
    if (includePlaceholder) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = '— Выберите шаблон —';
        selectElement.appendChild(option);
    }
    (templates || []).forEach(template => {
        const option = document.createElement('option');
        option.value = template.id;
        option.textContent = template.name;
        option.dataset.description = template.description || '';
        selectElement.appendChild(option);
    });
    if (previous) {
        selectElement.value = previous;
    }
}

function renderWorkerRoutes(routesResponse, storedRoutes) {
    let html = '';
    const routes = (routesResponse && routesResponse.success && Array.isArray(routesResponse.data)) ? routesResponse.data : [];

    html += '<div class="table-responsive">';
    html += '<table class="table table-sm align-middle">';
    html += '<thead><tr><th>Паттерн</th><th>Статус</th><th>Источник</th><th>Действия</th></tr></thead><tbody>';

    if (routes.length > 0) {
        routes.forEach(route => {
            const pattern = route.pattern || '';
            const enabled = route.enabled ? 'Активен' : 'Выключен';
            const scriptSource = route.script === '' ? 'Zone Worker' : (route.script || '—');
            html += `
                <tr>
                    <td><code>${pattern}</code></td>
                    <td><span class="badge ${route.enabled ? 'bg-success' : 'bg-secondary'}">${enabled}</span></td>
                    <td>${scriptSource}</td>
                    <td>
                        <button type="button" class="btn btn-outline-danger btn-sm worker-detach-btn" data-route-id="${route.id || ''}" data-route-pattern="${encodeURIComponent(pattern)}">
                            <i class="fas fa-unlink"></i> Отключить
                        </button>
                    </td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="4" class="text-muted">Маршруты Cloudflare отсутствуют. Добавьте маршрут, чтобы активировать Worker.</td></tr>';
    }

    html += '</tbody></table></div>';

    if (storedRoutes && storedRoutes.length) {
        html += '<div class="mt-3">';
        html += '<h6 class="fw-bold">Последние операции</h6>';
        html += '<div class="small text-muted">Хранится локальная история применённых настроек</div>';
        html += '<ul class="list-group mt-2">';
        storedRoutes.forEach(route => {
            html += `<li class="list-group-item d-flex justify-content-between align-items-start">
                <div>
                    <div><code>${route.route_pattern}</code></div>
                    <div class="text-muted small">Статус: ${route.status || '—'}${route.last_error ? ' / Ошибка: ' + route.last_error : ''}</div>
                </div>
                <span class="badge bg-light text-dark">${route.applied_at || '—'}</span>
            </li>`;
        });
        html += '</ul></div>';
    }

    return html;
}

function attachWorkerRouteHandlers() {
    document.querySelectorAll('#workerRoutesContainer .worker-detach-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const routeId = btn.dataset.routeId || '';
            const routePattern = decodeURIComponent(btn.dataset.routePattern || '');
            if (!routeId && !routePattern) {
                showNotification('Не удалось определить маршрут', 'warning');
                return;
            }
            detachWorkerRoute(routeId, routePattern);
        });
    });
}

function setWorkerModalStatus(message, type = 'info') {
    const statusEl = document.getElementById('workerModalStatus');
    if (!statusEl) return;
    statusEl.textContent = message || '';
    statusEl.className = type === 'error' ? 'text-danger' : (type === 'success' ? 'text-success' : 'text-muted');
}

function toggleWorkerTemplateNameField() {
    const checkbox = document.getElementById('workerSaveTemplate');
    const input = document.getElementById('workerTemplateName');
    if (!checkbox || !input) return;
    if (checkbox.checked) {
        input.classList.remove('d-none');
        input.focus();
    } else {
        input.classList.add('d-none');
        input.value = '';
    }
}

async function applyWorkerTemplate() {
    const templateSelect = document.getElementById('workerTemplateSelect');
    const routeInput = document.getElementById('workerRoutePattern');
    if (!workerCurrentDomainId || !templateSelect) return;

    const templateId = parseInt(templateSelect.value, 10);
    if (!templateId) {
        showNotification('Выберите шаблон для применения', 'warning');
        return;
    }

    const routePattern = routeInput ? routeInput.value.trim() : '';
    setWorkerModalStatus('Применяем шаблон...', 'info');

    try {
        const response = await fetch('workers_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'apply_template', domain_id: workerCurrentDomainId, template_id: templateId, route_pattern: routePattern })
        });
        const result = await response.json();
        if (result.success) {
            showNotification('Шаблон Workers применен', 'success');
            setWorkerModalStatus('Worker успешно обновлён', 'success');
            await manageWorkers(workerCurrentDomainId, true);
        } else {
            showNotification(result.error || 'Ошибка применения шаблона', 'error');
            setWorkerModalStatus(result.error || 'Ошибка применения шаблона', 'error');
        }
    } catch (e) {
        showNotification('Сбой применения шаблона: ' + e.message, 'error');
        setWorkerModalStatus('Ошибка: ' + e.message, 'error');
    }
}

async function applyWorkerCustomScript() {
    if (!workerCurrentDomainId) return;
    const script = (document.getElementById('workerCustomScript')?.value || '').trim();
    const routePattern = document.getElementById('workerRoutePattern')?.value.trim() || '';
    const saveTemplate = document.getElementById('workerSaveTemplate')?.checked || false;
    const templateNameInput = document.getElementById('workerTemplateName');
    const templateName = saveTemplate ? (templateNameInput?.value.trim() || '') : 'Custom Worker';

    if (!script) {
        showNotification('Введите JavaScript код для загрузки', 'warning');
        return;
    }
    if (saveTemplate && !templateName) {
        showNotification('Укажите название шаблона', 'warning');
        return;
    }

    setWorkerModalStatus('Загружаем пользовательский скрипт...', 'info');

    try {
        const response = await fetch('workers_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'apply_custom',
                domain_id: workerCurrentDomainId,
                route_pattern: routePattern,
                script,
                template_name: templateName,
                save_template: saveTemplate ? 1 : 0
            })
        });
        const result = await response.json();
        if (result.success) {
            showNotification('Пользовательский скрипт применён', 'success');
            setWorkerModalStatus('Worker успешно обновлён', 'success');
            document.getElementById('workerCustomScript').value = '';
            if (document.getElementById('workerSaveTemplate')) {
                document.getElementById('workerSaveTemplate').checked = false;
                toggleWorkerTemplateNameField();
            }
            await manageWorkers(workerCurrentDomainId, true);
        } else {
            showNotification(result.error || 'Ошибка загрузки скрипта', 'error');
            setWorkerModalStatus(result.error || 'Ошибка загрузки скрипта', 'error');
        }
    } catch (e) {
        showNotification('Сбой загрузки скрипта: ' + e.message, 'error');
        setWorkerModalStatus('Ошибка: ' + e.message, 'error');
    }
}

async function detachWorkerRoute(routeId, routePattern) {
    if (!workerCurrentDomainId) return;
    if (!confirm('Отключить маршрут Workers?')) return;

    try {
        const response = await fetch('workers_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'detach_route', domain_id: workerCurrentDomainId, route_id: routeId, route_pattern: routePattern })
        });
        const result = await response.json();
        if (result.success) {
            showNotification('Маршрут отключён', 'success');
            await manageWorkers(workerCurrentDomainId, true);
        } else {
            showNotification(result.error || 'Не удалось отключить маршрут', 'error');
        }
    } catch (e) {
        showNotification('Сбой отключения маршрута: ' + e.message, 'error');
    }
}

function reloadWorkerModalData() {
    if (!workerCurrentDomainId) return;
    manageWorkers(workerCurrentDomainId, true);
}

async function openBulkWorkersModal() {
    try {
        const response = await fetch('workers_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'list_templates' })
        });
        const result = await response.json();
        if (!result.success) {
            showNotification(result.error || 'Не удалось загрузить шаблоны', 'error');
            return;
        }
        workerTemplatesCache = result.templates || [];
        populateWorkerTemplatesSelect(document.getElementById('bulkWorkerTemplate'), workerTemplatesCache, true);
    } catch (e) {
        showNotification('Сбой загрузки шаблонов: ' + e.message, 'error');
        return;
    }

    const selectedDomains = getSelectedDomains();
    const infoEl = document.getElementById('bulkWorkerSelectionInfo');
    if (infoEl) {
        infoEl.textContent = selectedDomains.length > 0
            ? `Выбрано доменов: ${selectedDomains.length}. Вы можете применить Worker только к ним или расширить область.`
            : 'Домены не выбраны. Можно применить Worker к группе или ко всем доменам.';
    }

    const selectedScope = document.getElementById('bulkWorkerScopeSelected');
    if (selectedScope) {
        selectedScope.disabled = selectedDomains.length === 0;
        if (selectedDomains.length === 0) {
            document.getElementById('bulkWorkerScopeAll').checked = true;
        } else if (!selectedScope.disabled) {
            selectedScope.checked = true;
        }
    }

    const routeInput = document.getElementById('bulkWorkerRoutePattern');
    if (routeInput) {
        routeInput.value = '{{domain}}/*';
    }

    document.getElementById('bulkWorkerResult')?.classList.add('d-none');
    handleBulkWorkerScopeChange();

    if (bulkWorkerModalInstance) {
        bulkWorkerModalInstance.show();
    }
}

function handleBulkWorkerScopeChange() {
    const scope = document.querySelector('input[name="bulkWorkerScope"]:checked')?.value || 'selected';
    const wrapper = document.getElementById('bulkWorkerGroupWrapper');
    if (!wrapper) return;
    if (scope === 'group') {
        wrapper.classList.remove('d-none');
    } else {
        wrapper.classList.add('d-none');
    }
}

async function bulkApplyWorkers() {
    const scope = document.querySelector('input[name="bulkWorkerScope"]:checked')?.value || 'selected';
    const templateSelect = document.getElementById('bulkWorkerTemplate');
    const routePattern = document.getElementById('bulkWorkerRoutePattern')?.value.trim() || '';
    const resultBox = document.getElementById('bulkWorkerResult');

    if (!templateSelect || !templateSelect.value) {
        showNotification('Выберите шаблон для массового применения', 'warning');
        return;
    }

    const payload = {
        action: 'bulk_apply',
        template_id: parseInt(templateSelect.value, 10),
        route_pattern: routePattern,
        scope
    };

    if (scope === 'selected') {
        const domainIds = getSelectedDomains();
        if (domainIds.length === 0) {
            showNotification('Не выбраны домены для операции', 'warning');
            return;
        }
        payload.domain_ids = domainIds.map(id => parseInt(id, 10));
    } else if (scope === 'group') {
        const groupId = document.getElementById('bulkWorkerGroup')?.value;
        if (!groupId) {
            showNotification('Выберите группу для операции', 'warning');
            return;
        }
        payload.group_id = parseInt(groupId, 10);
    }

    try {
        const response = await fetch('workers_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!result.success) {
            showNotification(result.error || 'Ошибка массового применения', 'error');
            return;
        }

        if (resultBox) {
            const successes = (result.results || []).filter(r => r.success);
            const failures = (result.results || []).filter(r => !r.success);
            let html = '';
            html += `<div class="alert alert-success">Успешно: ${successes.length}</div>`;
            if (failures.length > 0) {
                html += '<div class="alert alert-warning">Неудачи:</div><ul class="list-group">';
                failures.slice(0, 10).forEach(f => {
                    html += `<li class="list-group-item small"><strong>${f.domain || f.domain_id}</strong>: ${f.error || 'Ошибка'}</li>`;
                });
                if (failures.length > 10) {
                    html += `<li class="list-group-item small text-muted">И ещё ${failures.length - 10} ошибок...</li>`;
                }
                html += '</ul>';
            }
            resultBox.innerHTML = html;
            resultBox.classList.remove('d-none');
        }

        showNotification(`Workers применены. Успешно: ${(result.results || []).filter(r => r.success).length}`, 'success');
        if (bulkWorkerModalInstance) {
            // оставляем модал открытым для просмотра результатов
        }
    } catch (e) {
        showNotification('Сбой массового применения: ' + e.message, 'error');
    }
}

async function purgeCache(domainId) {
    if (!confirm('Очистить кеш для выбранного домена?')) return;
    try {
        const form = new URLSearchParams();
        form.append('domain_id', String(domainId));
        form.append('purge_everything', '1');
        const resp = await fetch('cache_api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form.toString() });
        const result = await resp.json();
        if (result.success) {
            showNotification('Кеш очищен', 'success');
        } else {
            showNotification('Ошибка очистки кеша: ' + (result.error || 'Unknown'), 'error');
        }
    } catch (e) {
        showNotification('Сбой сети: ' + e.message, 'error');
    }
}

async function toggleUnderAttack(domainId, enable) {
    try {
        const form = new URLSearchParams();
        form.append('domain_id', String(domainId));
        form.append('action', enable ? 'under_attack_on' : 'under_attack_off');
        const resp = await fetch('security_api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form.toString() });
        const result = await resp.json();
        if (result.success) showNotification('Security level обновлен', 'success'); else showNotification(result.error || 'Ошибка', 'error');
    } catch (e) { showNotification('Сбой сети: ' + e.message, 'error'); }
}

async function applyPageRule(domainId, rule) {
    try {
        const form = new URLSearchParams();
        form.append('domain_id', String(domainId));
        form.append('rule_type', rule);
        const resp = await fetch('page_rules_api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form.toString() });
        const result = await resp.json();
        if (result.success) showNotification('Page Rule применено', 'success'); else showNotification(result.error || 'Ошибка', 'error');
    } catch (e) { showNotification('Сбой сети: ' + e.message, 'error'); }
}

async function setupEmailRouting(domainId) {
    const source = prompt('Локальная часть адреса (до @):', 'info');
    if (!source) return;
    const destination = prompt('Куда пересылать письма (email):');
    if (!destination) return;
    try {
        const form = new URLSearchParams();
        form.append('domain_id', String(domainId));
        form.append('source', source);
        form.append('destination', destination);
        const resp = await fetch('email_routing_api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form.toString() });
        const result = await resp.json();
        if (result.success) showNotification('Email routing настроен', 'success'); else showNotification(result.error || 'Ошибка', 'error');
    } catch (e) { showNotification('Сбой сети: ' + e.message, 'error'); }
}

async function createDnsRecordPrompt(domainId) {
    const type = prompt('Тип записи (A, AAAA, CNAME, TXT, etc):', 'A');
    if (!type) return;
    const name = prompt('Имя (например, sub.example.com):');
    if (!name) return;
    const content = prompt('Значение (IP/цель/строка):');
    if (!content) return;
    const ttl = prompt('TTL (1=auto):', '1');
    try {
        const form = new URLSearchParams();
        form.append('action', 'create');
        form.append('domain_id', String(domainId));
        form.append('type', type);
        form.append('name', name);
        form.append('content', content);
        form.append('ttl', ttl || '1');
        const resp = await fetch('dns_api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form.toString() });
        const result = await resp.json();
        if (result.success) showNotification('DNS запись создана', 'success'); else showNotification(result.error || 'Ошибка', 'error');
    } catch (e) { showNotification('Сбой сети: ' + e.message, 'error'); }
}

async function deleteDnsRecordPrompt(domainId) {
    const recordId = prompt('ID DNS записи для удаления:');
    if (!recordId) return;
    try {
        const form = new URLSearchParams();
        form.append('action', 'delete');
        form.append('domain_id', String(domainId));
        form.append('record_id', recordId);
        const resp = await fetch('dns_api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form.toString() });
        const result = await resp.json();
        if (result.success) showNotification('DNS запись удалена', 'success'); else showNotification(result.error || 'Ошибка', 'error');
    } catch (e) { showNotification('Сбой сети: ' + e.message, 'error'); }
}

// Функции для работы с API токенами
function openAddTokenModal() {
    if (!tokenModalInstance) {
        const tokenModalEl = document.getElementById('addTokenModal');
        if (tokenModalEl) {
            tokenModalInstance = new bootstrap.Modal(tokenModalEl);
        } else {
            showNotification('Модальное окно не найдено', 'error');
            return;
        }
    }
    
    // Очищаем форму
    document.getElementById('addTokenForm').reset();
    document.getElementById('tokenModalStatus').innerHTML = '';
    
    // Показываем модальное окно
    tokenModalInstance.show();
}

async function saveApiToken() {
    const accountId = document.getElementById('tokenAccount').value;
    const name = document.getElementById('tokenName').value.trim();
    const token = document.getElementById('tokenValue').value.trim();
    const tag = document.getElementById('tokenTag').value.trim();
    const statusEl = document.getElementById('tokenModalStatus');
    
    // Валидация
    if (!accountId) {
        statusEl.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i>Выберите аккаунт</div>';
        return;
    }
    
    if (!name) {
        statusEl.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i>Укажите название токена</div>';
        return;
    }
    
    if (!token) {
        statusEl.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i>Введите API токен</div>';
        return;
    }
    
    // Показываем загрузку
    statusEl.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-1"></i>Сохранение токена...</div>';
    
    try {
        const response = await fetch('tokens_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'create',
                account_id: parseInt(accountId, 10),
                name: name,
                token: token,
                tag: tag || null
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            statusEl.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-1"></i>Токен успешно сохранен!</div>';
            showNotification('API токен успешно добавлен', 'success');
            
            // Очищаем форму
            document.getElementById('addTokenForm').reset();
            
            // Закрываем модальное окно через 1.5 секунды
            setTimeout(() => {
                if (tokenModalInstance) {
                    tokenModalInstance.hide();
                }
            }, 1500);
        } else {
            statusEl.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i>Ошибка: ${result.error || 'Неизвестная ошибка'}</div>`;
            showNotification('Ошибка сохранения токена: ' + (result.error || 'Неизвестная ошибка'), 'error');
        }
    } catch (error) {
        statusEl.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i>Ошибка сети: ${error.message}</div>`;
        showNotification('Ошибка сети: ' + error.message, 'error');
    }
}
</script>

</body>
</html> 