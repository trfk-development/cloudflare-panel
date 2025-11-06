<?php
require_once 'header.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

// Получаем группы для модальных окон
$groupStmt = $pdo->prepare("SELECT * FROM groups WHERE user_id = ?");
$groupStmt->execute([$_SESSION['user_id']]);
$groups = $groupStmt->fetchAll();

// Получаем аккаунты для модальных окон
$accountStmt = $pdo->prepare("SELECT * FROM cloudflare_credentials WHERE user_id = ?");
$accountStmt->execute([$_SESSION['user_id']]);
$accounts = $accountStmt->fetchAll();

// Получаем все домены с сертификатами
$stmt = $pdo->prepare("
    SELECT ca.id, ca.domain, ca.ssl_cert_id, ca.ssl_certificate, ca.ssl_private_key, ca.ssl_cert_created,
           ca.ssl_certificates_count, ca.ssl_has_active, ca.ssl_expires_soon, ca.ssl_nearest_expiry,
           cc.email, g.name AS group_name
    FROM cloudflare_accounts ca 
    JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
    LEFT JOIN groups g ON ca.group_id = g.id 
    WHERE ca.user_id = ? 
    ORDER BY CASE WHEN ca.ssl_cert_id IS NOT NULL THEN 0 ELSE 1 END, ca.domain ASC
");
$stmt->execute([$_SESSION['user_id']]);
$allDomains = $stmt->fetchAll();

// Разделяем домены на те, у которых есть сертификаты, и те, у которых нет
$domainsWithCerts = [];
$domainsWithoutCerts = [];

foreach ($allDomains as $domain) {
    if ($domain['ssl_cert_id'] && $domain['ssl_certificate'] && $domain['ssl_private_key']) {
        $domainsWithCerts[] = $domain;
    } else {
        $domainsWithoutCerts[] = $domain;
    }
}

$viewMode = $_GET['view'] ?? 'compact';
?>

<?php include 'sidebar.php'; ?>

<style>
/* Компактные стили */
.cert-container {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 15px;
    overflow: hidden;
    transition: all 0.2s ease;
}

.cert-container:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-color: #007bff;
}

.cert-header {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
    padding: 12px 16px;
    cursor: pointer;
    user-select: none;
}

.cert-header:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
}

.cert-content {
    display: none;
    padding: 16px;
}

.cert-content.show {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; max-height: 0; }
    to { opacity: 1; max-height: 1000px; }
}

.cert-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.cert-section {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    overflow: hidden;
}

.cert-section-header {
    background: #e9ecef;
    padding: 8px 12px;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    justify-content: between;
    align-items: center;
}

.cert-code {
    background: #2d3748;
    color: #e2e8f0;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    line-height: 1.3;
    padding: 12px;
    margin: 0;
    max-height: 200px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
    position: relative;
}

.cert-code.collapsed {
    max-height: 60px;
    overflow: hidden;
}

.cert-code::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 20px;
    background: linear-gradient(transparent, #2d3748);
    pointer-events: none;
}

.cert-code.expanded::after {
    display: none;
}

.quick-actions {
    display: flex;
    gap: 4px;
    align-items: center;
}

.btn-xs {
    padding: 2px 6px;
    font-size: 11px;
    border-radius: 3px;
}

.stats-bar {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.view-controls {
    background: #f8f9fa;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.search-box {
    position: relative;
}

.search-box input {
    padding-right: 40px;
}

.search-box .search-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

.domain-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 16px;
    font-weight: 600;
}

.expand-icon {
    transition: transform 0.2s ease;
}

.cert-header[aria-expanded="true"] .expand-icon {
    transform: rotate(180deg);
}

.no-cert-item {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 6px;
    padding: 12px 16px;
    margin-bottom: 8px;
    display: flex;
    justify-content: between;
    align-items: center;
}

.table-view .cert-table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.table-view .cert-table td {
    vertical-align: middle;
    padding: 8px 12px;
}

.copy-cell {
    white-space: nowrap;
}

.mass-actions {
    background: #e3f2fd;
    border: 1px solid #bbdefb;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 16px;
}

@media (max-width: 768px) {
    .cert-grid {
        grid-template-columns: 1fr;
    }
    
    .view-controls .row > div {
        margin-bottom: 10px;
    }
}
</style>

<div class="content">
    <div class="card">
        <div class="card-body">
            <!-- Заголовок -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">
                <i class="fas fa-certificate me-2"></i>SSL Сертификаты
            </h5>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm" onclick="window.location.href='dashboard.php'">
                        <i class="fas fa-plus me-1"></i>Создать
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.location.reload()">
                        <i class="fas fa-sync me-1"></i>Обновить
                    </button>
                </div>
            </div>

            <!-- Статистика -->
            <div class="stats-bar">
                <div class="row text-center">
                    <div class="col-6 col-md-3">
                        <div class="h4 mb-1"><?php echo count($domainsWithCerts); ?></div>
                        <div class="small">С сертификатами</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="h4 mb-1"><?php echo count($domainsWithoutCerts); ?></div>
                        <div class="small">Без сертификатов</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="h4 mb-1"><?php echo count($allDomains); ?></div>
                        <div class="small">Всего доменов</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="h4 mb-1"><?php echo count($allDomains) > 0 ? round((count($domainsWithCerts) / count($allDomains)) * 100) : 0; ?>%</div>
                        <div class="small">Покрытие</div>
                    </div>
                </div>
            </div>

            <!-- Управление -->
            <div class="view-controls">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="search-box">
                            <input type="text" id="searchDomains" class="form-control form-control-sm" placeholder="Поиск доменов...">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <input type="radio" class="btn-check" name="filter" id="filter-all" value="all" checked>
                            <label class="btn btn-outline-primary" for="filter-all">Все</label>
                            
                            <input type="radio" class="btn-check" name="filter" id="filter-with" value="with">
                            <label class="btn btn-outline-success" for="filter-with">С сертификатами</label>
                            
                            <input type="radio" class="btn-check" name="filter" id="filter-without" value="without">
                            <label class="btn btn-outline-warning" for="filter-without">Без сертификатов</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <input type="radio" class="btn-check" name="view" id="view-compact" value="compact" <?php echo $viewMode === 'compact' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-info" for="view-compact">
                                <i class="fas fa-th-list me-1"></i>Компактный
                            </label>
                            
                            <input type="radio" class="btn-check" name="view" id="view-table" value="table" <?php echo $viewMode === 'table' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-info" for="view-table">
                                <i class="fas fa-table me-1"></i>Таблица
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Массовые операции -->
            <?php if (!empty($domainsWithCerts)): ?>
            <div class="mass-actions">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="small text-muted">Массовые операции:</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-xs" onclick="expandAll()">
                            <i class="fas fa-expand-alt me-1"></i>Развернуть все
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-xs" onclick="collapseAll()">
                            <i class="fas fa-compress-alt me-1"></i>Свернуть все
                        </button>
                        <button type="button" class="btn btn-outline-success btn-xs" onclick="copyAllCertificates()">
                            <i class="fas fa-copy me-1"></i>Копировать все CRT
                        </button>
                        <button type="button" class="btn btn-outline-warning btn-xs" onclick="copyAllKeys()">
                            <i class="fas fa-key me-1"></i>Копировать все KEY
                        </button>
                        <button type="button" class="btn btn-outline-info btn-xs" onclick="downloadAllCertificates()">
                            <i class="fas fa-download me-1"></i>Скачать архив
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Компактный вид -->
            <div id="compact-view" style="<?php echo $viewMode === 'table' ? 'display: none;' : ''; ?>">
                <!-- Домены с сертификатами -->
                <?php if (!empty($domainsWithCerts)): ?>
                    <h6 class="text-success mb-3">
                        <i class="fas fa-shield-alt me-2"></i>Домены с сертификатами (<?php echo count($domainsWithCerts); ?>)
                    </h6>
                    
                    <?php foreach ($domainsWithCerts as $domain): ?>
                        <div class="cert-container domain-item" data-domain="<?php echo htmlspecialchars($domain['domain']); ?>" data-has-cert="true">
                            <div class="cert-header" onclick="toggleCertificate('<?php echo $domain['id']; ?>')" aria-expanded="false">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="domain-badge">
                                        <i class="fas fa-globe"></i>
                                        <span><?php echo htmlspecialchars($domain['domain']); ?></span>
                                        <?php if ($domain['ssl_expires_soon']): ?>
                                            <span class="badge badge-warning" title="Сертификат истекает скоро">⚠️</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="text-end small">
                                            <div>Origin CA • ID: <?php echo htmlspecialchars($domain['ssl_cert_id']); ?></div>
                                            <?php if ($domain['ssl_cert_created']): ?>
                                                <div class="opacity-75">Создан: <?php echo date('d.m.Y', strtotime($domain['ssl_cert_created'])); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="quick-actions">
                                            <button type="button" class="btn btn-light btn-xs" onclick="event.stopPropagation(); copyQuickCert('<?php echo $domain['id']; ?>')" title="Быстро копировать сертификат">
                                                <i class="fas fa-certificate"></i>
                                            </button>
                                            <button type="button" class="btn btn-light btn-xs" onclick="event.stopPropagation(); copyQuickKey('<?php echo $domain['id']; ?>')" title="Быстро копировать ключ">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <i class="fas fa-chevron-down expand-icon"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="cert-content" id="cert-content-<?php echo $domain['id']; ?>">
                                <div class="cert-grid">
                                    <!-- Приватный ключ (слева) -->
                                    <div class="cert-section">
                                        <div class="cert-section-header">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-key text-warning me-2"></i>
                                                <span>Приватный ключ (KEY)</span>
                                            </div>
                                            <div class="quick-actions">
                                                <button type="button" class="btn btn-warning btn-xs" onclick="copyPrivateKey('<?php echo $domain['id']; ?>')">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                                <button type="button" class="btn btn-success btn-xs" onclick="downloadFile('<?php echo $domain['id']; ?>', 'key')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <button type="button" class="btn btn-info btn-xs" onclick="toggleCodeExpand('key-<?php echo $domain['id']; ?>')">
                                                    <i class="fas fa-expand"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <pre class="cert-code collapsed" id="key-<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['ssl_private_key']); ?></pre>
                                    </div>

                                    <!-- Сертификат (справа) -->
                                    <div class="cert-section">
                                        <div class="cert-section-header">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-certificate text-primary me-2"></i>
                                                <span>Сертификат (CRT/PEM)</span>
                                            </div>
                                            <div class="quick-actions">
                                                <button type="button" class="btn btn-primary btn-xs" onclick="copyCertificate('<?php echo $domain['id']; ?>')">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                                <button type="button" class="btn btn-success btn-xs" onclick="downloadFile('<?php echo $domain['id']; ?>', 'cert')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <button type="button" class="btn btn-info btn-xs" onclick="toggleCodeExpand('cert-<?php echo $domain['id']; ?>')">
                                                    <i class="fas fa-expand"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <pre class="cert-code collapsed" id="cert-<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['ssl_certificate']); ?></pre>
                                    </div>
                                </div>
                                
                                <!-- Дополнительная информация -->
                                <div class="mt-3 p-3 bg-light rounded">
                                    <div class="row">
                                        <div class="col-md-6 small">
                                            <strong>Группа:</strong> <?php echo htmlspecialchars($domain['group_name'] ?? 'Без группы'); ?><br>
                                            <strong>Email:</strong> <?php echo htmlspecialchars($domain['email']); ?>
                                        </div>
                                        <div class="col-md-6 small">
                                            <strong>Всего сертификатов:</strong> <?php echo $domain['ssl_certificates_count'] ?? 1; ?><br>
                                            <strong>Статус:</strong> <?php echo $domain['ssl_has_active'] ? 'Активен' : 'Неактивен'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Домены без сертификатов -->
                <?php if (!empty($domainsWithoutCerts)): ?>
                    <h6 class="text-warning mb-3 mt-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>Домены без сертификатов (<?php echo count($domainsWithoutCerts); ?>)
                    </h6>
                    
                    <?php foreach ($domainsWithoutCerts as $domain): ?>
                        <div class="no-cert-item domain-item" data-domain="<?php echo htmlspecialchars($domain['domain']); ?>" data-has-cert="false">
                            <div class="d-flex align-items-center flex-grow-1">
                                <i class="fas fa-globe text-muted me-2"></i>
                                <div>
                                    <div class="font-weight-bold"><?php echo htmlspecialchars($domain['domain']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($domain['group_name'] ?? 'Без группы'); ?> • <?php echo htmlspecialchars($domain['email']); ?></small>
                                </div>
                            </div>
                            <button type="button" class="btn btn-warning btn-sm" onclick="createCertificateForDomain('<?php echo $domain['id']; ?>', '<?php echo htmlspecialchars($domain['domain']); ?>')">
                                <i class="fas fa-plus me-1"></i>Создать
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Табличный вид -->
            <div id="table-view" class="table-view" style="<?php echo $viewMode === 'compact' ? 'display: none;' : ''; ?>">
                <?php if (!empty($domainsWithCerts)): ?>
                    <div class="cert-table">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 200px;">Домен</th>
                                    <th style="width: 120px;">Группа</th>
                                    <th style="width: 100px;">Статус</th>
                                    <th style="width: 80px;">KEY</th>
                                    <th style="width: 80px;">CRT</th>
                                    <th style="width: 100px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                                <?php foreach ($domainsWithCerts as $domain): ?>
                                    <tr class="domain-item" data-domain="<?php echo htmlspecialchars($domain['domain']); ?>" data-has-cert="true">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-globe text-primary me-2"></i>
                                                <div>
                                                    <div class="font-weight-bold"><?php echo htmlspecialchars($domain['domain']); ?></div>
                                                    <small class="text-muted">ID: <?php echo htmlspecialchars($domain['ssl_cert_id']); ?></small>
                                                </div>
                                            </div>
                                    </td>
                                        <td class="small"><?php echo htmlspecialchars($domain['group_name'] ?? 'Без группы'); ?></td>
                                        <td>
                                            <span class="badge bg-success">Origin CA</span>
                                            <?php if ($domain['ssl_expires_soon']): ?>
                                                <br><span class="badge bg-warning mt-1">⚠️ Истекает</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="copy-cell">
                                            <button type="button" class="btn btn-warning btn-xs w-100" onclick="copyPrivateKey('<?php echo $domain['id']; ?>')">
                                                <i class="fas fa-key me-1"></i>KEY
                                            </button>
                                    </td>
                                        <td class="copy-cell">
                                            <button type="button" class="btn btn-primary btn-xs w-100" onclick="copyCertificate('<?php echo $domain['id']; ?>')">
                                                <i class="fas fa-certificate me-1"></i>CRT
                                            </button>
                                    </td>
                                    <td>
                                            <div class="btn-group-vertical" role="group">
                                                <button type="button" class="btn btn-success btn-xs" onclick="downloadFile('<?php echo $domain['id']; ?>', 'both')" title="Скачать оба файла">
                                                    <i class="fas fa-download"></i>
                                        </button>
                                                <button type="button" class="btn btn-info btn-xs" onclick="showFullCert('<?php echo $domain['id']; ?>', '<?php echo htmlspecialchars($domain['domain']); ?>')" title="Показать полностью">
                                                    <i class="fas fa-eye"></i>
                                        </button>
                                            </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
                
                <?php if (!empty($domainsWithoutCerts)): ?>
                    <div class="mt-4">
                        <h6 class="text-warning mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>Домены без сертификатов
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <tbody>
                                    <?php foreach ($domainsWithoutCerts as $domain): ?>
                                        <tr class="domain-item" data-domain="<?php echo htmlspecialchars($domain['domain']); ?>" data-has-cert="false">
                                            <td>
                                                <i class="fas fa-globe text-muted me-2"></i>
                                                <?php echo htmlspecialchars($domain['domain']); ?>
                                            </td>
                                            <td class="small text-muted"><?php echo htmlspecialchars($domain['group_name'] ?? 'Без группы'); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-warning btn-sm" onclick="createCertificateForDomain('<?php echo $domain['id']; ?>', '<?php echo htmlspecialchars($domain['domain']); ?>')">
                                                    <i class="fas fa-plus me-1"></i>Создать сертификат
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($allDomains)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    У вас пока нет доменов. <a href="dashboard.php" class="alert-link">Добавить домены</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Скрытые элементы для хранения данных сертификатов -->
<?php foreach ($domainsWithCerts as $domain): ?>
    <textarea style="display: none;" id="hidden-cert-<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['ssl_certificate']); ?></textarea>
    <textarea style="display: none;" id="hidden-key-<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['ssl_private_key']); ?></textarea>
    <span style="display: none;" id="hidden-domain-<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['domain']); ?></span>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Поиск по доменам
    const searchInput = document.getElementById('searchDomains');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const domainItems = document.querySelectorAll('.domain-item');
            
            domainItems.forEach(item => {
                const domainName = item.getAttribute('data-domain').toLowerCase();
                item.style.display = domainName.includes(searchTerm) ? 'block' : 'none';
            });
        });
    }

    // Фильтры
    const filterRadios = document.querySelectorAll('input[name="filter"]');
    filterRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const filter = this.value;
            const domainItems = document.querySelectorAll('.domain-item');
            
            domainItems.forEach(item => {
                const hasCert = item.getAttribute('data-has-cert') === 'true';
                let show = false;
                
                switch(filter) {
                    case 'all': show = true; break;
                    case 'with': show = hasCert; break;
                    case 'without': show = !hasCert; break;
                }
                
                item.style.display = show ? 'block' : 'none';
            });
        });
    });

    // Переключение видов
    const viewRadios = document.querySelectorAll('input[name="view"]');
    viewRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const view = this.value;
            const compactView = document.getElementById('compact-view');
            const tableView = document.getElementById('table-view');
            
            if (view === 'compact') {
                compactView.style.display = 'block';
                tableView.style.display = 'none';
            } else {
                compactView.style.display = 'none';
                tableView.style.display = 'block';
            }
            
            // Обновляем URL
            const url = new URL(window.location);
            url.searchParams.set('view', view);
            window.history.pushState({}, '', url);
        });
    });
});

// Функция показа уведомлений
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = `
        top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;
    `;
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
}

// Переключение видимости сертификата
function toggleCertificate(domainId) {
    const content = document.getElementById('cert-content-' + domainId);
    const header = content.previousElementSibling;
    const isExpanded = header.getAttribute('aria-expanded') === 'true';
    
    if (isExpanded) {
        content.classList.remove('show');
        header.setAttribute('aria-expanded', 'false');
                } else {
        content.classList.add('show');
        header.setAttribute('aria-expanded', 'true');
    }
}

// Переключение развернутого вида кода
function toggleCodeExpand(elementId) {
    const codeElement = document.getElementById(elementId);
    codeElement.classList.toggle('collapsed');
    codeElement.classList.toggle('expanded');
}

// Развернуть все сертификаты
function expandAll() {
    document.querySelectorAll('.cert-content').forEach(content => {
        content.classList.add('show');
        content.previousElementSibling.setAttribute('aria-expanded', 'true');
    });
    showNotification('Все сертификаты развернуты', 'info');
}

// Свернуть все сертификаты
function collapseAll() {
    document.querySelectorAll('.cert-content').forEach(content => {
        content.classList.remove('show');
        content.previousElementSibling.setAttribute('aria-expanded', 'false');
    });
    showNotification('Все сертификаты свернуты', 'info');
}

// Быстрое копирование сертификата
function copyQuickCert(domainId) {
    const certData = document.getElementById('hidden-cert-' + domainId).value;
    copyToClipboard(certData, 'Сертификат скопирован!');
}

// Быстрое копирование ключа
function copyQuickKey(domainId) {
    const keyData = document.getElementById('hidden-key-' + domainId).value;
    copyToClipboard(keyData, 'Приватный ключ скопирован!');
}

// Копирование сертификата
function copyCertificate(domainId) {
    const certData = document.getElementById('hidden-cert-' + domainId).value;
    copyToClipboard(certData, 'Сертификат скопирован в буфер обмена!');
}

// Копирование приватного ключа
function copyPrivateKey(domainId) {
    const keyData = document.getElementById('hidden-key-' + domainId).value;
    copyToClipboard(keyData, 'Приватный ключ скопирован в буфер обмена!');
}

// Универсальная функция копирования
function copyToClipboard(text, successMessage) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification(successMessage, 'success');
    }).catch(() => {
        // Fallback
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification(successMessage, 'success');
    });
}

// Скачивание файлов
function downloadFile(domainId, type) {
    const domainName = document.getElementById('hidden-domain-' + domainId).textContent;
    
    if (type === 'cert') {
        const content = document.getElementById('hidden-cert-' + domainId).value;
        downloadBlob(content, domainName + '.crt');
    } else if (type === 'key') {
        const content = document.getElementById('hidden-key-' + domainId).value;
        downloadBlob(content, domainName + '.key');
    } else if (type === 'both') {
        const certContent = document.getElementById('hidden-cert-' + domainId).value;
        const keyContent = document.getElementById('hidden-key-' + domainId).value;
        downloadBlob(certContent, domainName + '.crt');
        setTimeout(() => downloadBlob(keyContent, domainName + '.key'), 200);
        showNotification(`Файлы ${domainName}.crt и ${domainName}.key загружены!`, 'success');
        return;
    }
    
    showNotification(`Файл загружен!`, 'success');
}

// Скачивание blob
function downloadBlob(content, filename) {
    const blob = new Blob([content], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Копирование всех сертификатов
function copyAllCertificates() {
    const allCerts = [];
    document.querySelectorAll('[id^="hidden-cert-"]').forEach(element => {
        const domainId = element.id.replace('hidden-cert-', '');
        const domainName = document.getElementById('hidden-domain-' + domainId).textContent;
        allCerts.push(`# ${domainName}\n${element.value}\n`);
    });
    
    copyToClipboard(allCerts.join('\n'), `Скопированы сертификаты ${allCerts.length} доменов!`);
}

// Копирование всех ключей
function copyAllKeys() {
    const allKeys = [];
    document.querySelectorAll('[id^="hidden-key-"]').forEach(element => {
        const domainId = element.id.replace('hidden-key-', '');
        const domainName = document.getElementById('hidden-domain-' + domainId).textContent;
        allKeys.push(`# ${domainName}\n${element.value}\n`);
    });
    
    copyToClipboard(allKeys.join('\n'), `Скопированы ключи ${allKeys.length} доменов!`);
}

// Скачивание архива всех сертификатов
function downloadAllCertificates() {
    const zip = new JSZip();
    
    document.querySelectorAll('[id^="hidden-cert-"]').forEach(element => {
        const domainId = element.id.replace('hidden-cert-', '');
        const domainName = document.getElementById('hidden-domain-' + domainId).textContent;
        const certContent = element.value;
        const keyContent = document.getElementById('hidden-key-' + domainId).value;
        
        zip.file(`${domainName}.crt`, certContent);
        zip.file(`${domainName}.key`, keyContent);
    });
    
    zip.generateAsync({type:"blob"}).then(function(content) {
        const url = window.URL.createObjectURL(content);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'ssl-certificates.zip';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        showNotification('Архив с сертификатами загружен!', 'success');
    });
}

// Показать полный сертификат в модальном окне
function showFullCert(domainId, domainName) {
    const certContent = document.getElementById('hidden-cert-' + domainId).value;
    const keyContent = document.getElementById('hidden-key-' + domainId).value;
    
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-certificate me-2"></i>${domainName}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
            <div class="row">
                <div class="col-md-6">
                            <h6><i class="fas fa-key text-warning me-2"></i>Приватный ключ</h6>
                            <pre class="cert-code expanded">${keyContent}</pre>
                </div>
                <div class="col-md-6">
                            <h6><i class="fas fa-certificate text-primary me-2"></i>Сертификат</h6>
                            <pre class="cert-code expanded">${certContent}</pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" onclick="copyToClipboard('${keyContent.replace(/'/g, "\\'")}', 'Ключ скопирован!')">
                        <i class="fas fa-key me-1"></i>Копировать ключ
                    </button>
                    <button type="button" class="btn btn-primary" onclick="copyToClipboard('${certContent.replace(/'/g, "\\'")}', 'Сертификат скопирован!')">
                        <i class="fas fa-certificate me-1"></i>Копировать сертификат
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
            </div>
        `;
    
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    modal.addEventListener('hidden.bs.modal', function() {
        modal.remove();
    });
}

// Создание сертификата для домена
function createCertificateForDomain(domainId, domainName) {
    if (!confirm(`Создать Origin CA сертификат для домена ${domainName}?`)) {
        return;
    }
    
    showNotification('Перенаправление на создание сертификата...', 'info');
    window.location.href = `dashboard.php?highlight=${domainId}#certificates`;
    }
</script>

<!-- Подключаем JSZip для архивации (для функции скачивания архива) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<?php include 'modals.php'; ?>

</body>
</html> 