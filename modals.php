<!-- Модальное окно для добавления группы -->
<div class="modal fade" id="addGroupModal" tabindex="-1" aria-labelledby="addGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addGroupModalLabel">Добавить группу</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="text" name="group_name" class="form-control mb-2" placeholder="Название группы" required>
                    <button type="submit" name="add_group" class="btn btn-primary w-100">Добавить</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для удаления группы -->
<div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-labelledby="deleteGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteGroupModalLabel">Удалить группу</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <select name="group_id" class="form-select mb-2" required>
                        <option value="">Выберите группу для удаления</option>
                        <?php if (isset($groups)): ?>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <button type="submit" name="delete_group" class="btn btn-danger w-100" onclick="return confirm('Вы уверены, что хотите удалить группу? Все домены этой группы будут сброшены в статус \'Без группы\'.')">Удалить</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для добавления одного домена -->
<div class="modal fade" id="addDomainModal" tabindex="-1" aria-labelledby="addDomainModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDomainModalLabel">Добавить домен</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <select name="account_id" class="form-select mb-2" required>
                        <option value="">Выберите аккаунт</option>
                        <?php if (isset($accounts)): ?>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['email']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <select name="group_id" class="form-select mb-2" required>
                        <option value="">Выберите группу</option>
                        <?php if (isset($groups)): ?>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <input type="text" name="domain" class="form-control mb-2" placeholder="example.com" required>
                    <input type="text" name="server_ip" class="form-control mb-2" placeholder="192.168.1.1" required>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="enable_https" class="form-check-input" id="enableHttpsSingle">
                        <label class="form-check-label" for="enableHttpsSingle">Включить Always Use HTTPS</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="enable_tls13" class="form-check-input" id="enableTlsSingle">
                        <label class="form-check-label" for="enableTlsSingle">Включить TLS 1.3</label>
                    </div>
                    <button type="submit" name="add_domain" class="btn btn-primary w-100">Добавить</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для массового добавления доменов -->
<div class="modal fade" id="addDomainsBulkModal" tabindex="-1" aria-labelledby="addDomainsBulkModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDomainsBulkModalLabel">Массовое добавление доменов</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <select name="account_id" class="form-select mb-2" required>
                        <option value="">Выберите аккаунт</option>
                        <?php if (isset($accounts)): ?>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['email']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <select name="group_id" class="form-select mb-2" required>
                        <option value="">Выберите группу</option>
                        <?php if (isset($groups)): ?>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <textarea name="domains_list" class="form-control mb-2" rows="5" placeholder="example.com;192.168.1.1
example2.com;192.168.1.2" required></textarea>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="enable_https" class="form-check-input" id="enableHttpsBulk">
                        <label class="form-check-label" for="enableHttpsBulk">Включить Always Use HTTPS</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="enable_tls13" class="form-check-input" id="enableTlsBulk">
                        <label class="form-check-label" for="enableTlsBulk">Включить TLS 1.3</label>
                    </div>
                    <button type="submit" name="add_domains_bulk" class="btn btn-primary w-100">Добавить списком</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для добавления одного аккаунта -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAccountModalLabel">Добавить аккаунт</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <select name="group_id" class="form-select mb-2" required>
                        <option value="">Выберите группу</option>
                        <?php if (isset($groups)): ?>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <input type="email" name="email" class="form-control mb-2" placeholder="Email" required>
                    <input type="text" name="api_key" class="form-control mb-2" placeholder="API Key" required>
                    <button type="submit" name="add_account" class="btn btn-primary w-100">Добавить</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для массового добавления аккаунтов -->
<div class="modal fade" id="addAccountsBulkModal" tabindex="-1" aria-labelledby="addAccountsBulkModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAccountsBulkModalLabel">Массовое добавление аккаунтов</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="bulkAccountsForm">
                    <select name="group_id" class="form-select mb-2" required>
                        <option value="">Выберите группу</option>
                        <?php if (isset($groups)): ?>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <textarea name="accounts_list" id="accountsList" class="form-control mb-2" rows="5" placeholder="email1@example.com;api_key1
email2@example.com;api_key2" required></textarea>
                    <div class="alert alert-info">
                        <small>
                            <strong>Формат:</strong> email;api_key (каждый аккаунт с новой строки)<br>
                            <strong>Пример:</strong><br>
                            user1@example.com;1234567890abcdef1234567890abcdef12345678<br>
                            user2@example.com;abcdef1234567890abcdef1234567890abcdef12
                        </small>
                    </div>
                    <button type="submit" name="add_accounts_bulk" id="bulkAddButton" class="btn btn-primary w-100">Добавить аккаунты</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для просмотра NS серверов -->
<div class="modal fade" id="nsServersModal" tabindex="-1" aria-labelledby="nsServersModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nsServersModalLabel">
                    <i class="fas fa-server me-2"></i>NS Серверы домена
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="nsServersContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для смены IP через очередь -->
<div class="modal fade" id="changeIPModal" tabindex="-1" aria-labelledby="changeIPModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeIPModalLabel">
                    <i class="fas fa-network-wired me-2"></i>Смена IP адресов
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Выберите домены и укажите новый IP адрес. Задачи будут добавлены в очередь для обработки.
                </div>
                <form id="changeIPForm">
                    <div class="mb-3">
                        <label for="newIPAddress" class="form-label">Новый IP адрес</label>
                        <input type="text" class="form-control" id="newIPAddress" placeholder="192.168.1.1" required pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                        <div class="form-text">Введите корректный IPv4 адрес</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Выбранные домены:</label>
                        <div id="selectedDomainsForIP" class="border rounded p-2 bg-light" style="max-height: 200px; overflow-y: auto;">
                            <span class="text-muted">Домены не выбраны</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="processIPChange()">
                    <i class="fas fa-tasks me-2"></i>Добавить в очередь
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для добавления аккаунта через очередь -->
<div class="modal fade" id="addAccountQueueModal" tabindex="-1" aria-labelledby="addAccountQueueModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAccountQueueModalLabel">
                    <i class="fas fa-user-plus me-2"></i>Добавление аккаунта через очередь
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Аккаунт будет добавлен через систему очередей с автоматическим получением доменов из Cloudflare.
                </div>
                <form id="addAccountQueueForm">
                    <div class="mb-3">
                        <label for="accountEmail" class="form-label">Email аккаунта</label>
                        <input type="email" class="form-control" id="accountEmail" placeholder="user@example.com" required>
                    </div>
                    <div class="mb-3">
                        <label for="accountApiKey" class="form-label">API Key</label>
                        <input type="text" class="form-control" id="accountApiKey" placeholder="Cloudflare API Key" required>
                        <div class="form-text">Global API Key или Token с правами на чтение зон</div>
                    </div>
                    <div class="mb-3">
                        <label for="accountGroupId" class="form-label">Группа</label>
                        <select class="form-select" id="accountGroupId" required>
                            <option value="">Выберите группу</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="processAccountAdd()">
                    <i class="fas fa-tasks me-2"></i>Добавить в очередь
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для смены SSL режима -->
<div class="modal fade" id="changeSSLModeModal" tabindex="-1" aria-labelledby="changeSSLModeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeSSLModeModalLabel">
                    <i class="fas fa-shield-alt me-2"></i>Смена SSL режима
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Выберите новый SSL режим для выбранных доменов.
                </div>
                <form id="changeSSLModeForm">
                    <div class="mb-3">
                        <label for="newSSLMode" class="form-label">SSL режим</label>
                        <select class="form-select" id="newSSLMode" required>
                            <option value="">Выберите SSL режим</option>
                            <option value="off">Off - SSL отключен</option>
                            <option value="flexible">Flexible - Частичное шифрование</option>
                            <option value="full">Full - Полное шифрование</option>
                            <option value="strict">Full (strict) - С проверкой сертификата</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Выбранные домены:</label>
                        <div id="selectedDomainsForSSL" class="border rounded p-2 bg-light" style="max-height: 200px; overflow-y: auto;">
                            <span class="text-muted">Домены не выбраны</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="processSSLModeChange()">
                    <i class="fas fa-tasks me-2"></i>Добавить в очередь
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для смены TLS версии -->
<div class="modal fade" id="changeTLSModal" tabindex="-1" aria-labelledby="changeTLSModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeTLSModalLabel">
                    <i class="fas fa-lock me-2"></i>Смена версии TLS
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Выберите минимальную версию TLS для выбранных доменов.
                </div>
                <form id="changeTLSForm">
                    <div class="mb-3">
                        <label for="newTLSVersion" class="form-label">Версия TLS</label>
                        <select class="form-select" id="newTLSVersion" required>
                            <option value="">Выберите версию TLS</option>
                            <option value="1.0">TLS 1.0 (не рекомендуется)</option>
                            <option value="1.1">TLS 1.1 (устарело)</option>
                            <option value="1.2">TLS 1.2 (рекомендуется)</option>
                            <option value="1.3">TLS 1.3 (современный)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Выбранные домены:</label>
                        <div id="selectedDomainsForTLS" class="border rounded p-2 bg-light" style="max-height: 200px; overflow-y: auto;">
                            <span class="text-muted">Домены не выбраны</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="processTLSChange()">
                    <i class="fas fa-tasks me-2"></i>Добавить в очередь
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для смены HTTPS настройки -->
<div class="modal fade" id="changeHTTPSModal" tabindex="-1" aria-labelledby="changeHTTPSModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeHTTPSModalLabel">
                    <i class="fas fa-globe me-2"></i>Настройка Always Use HTTPS
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Включите или выключите принудительное перенаправление на HTTPS.
                </div>
                <form id="changeHTTPSForm">
                    <div class="mb-3">
                        <label for="newHTTPSSetting" class="form-label">Always Use HTTPS</label>
                        <select class="form-select" id="newHTTPSSetting" required>
                            <option value="">Выберите настройку</option>
                            <option value="1">Включить - Принудительное HTTPS</option>
                            <option value="0">Выключить - Разрешить HTTP</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Выбранные домены:</label>
                        <div id="selectedDomainsForHTTPS" class="border rounded p-2 bg-light" style="max-height: 200px; overflow-y: auto;">
                            <span class="text-muted">Домены не выбраны</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="processHTTPSChange()">
                    <i class="fas fa-tasks me-2"></i>Добавить в очередь
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно управления Cloudflare Workers для домена -->
<div class="modal fade" id="manageWorkerModal" tabindex="-1" aria-labelledby="manageWorkerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manageWorkerModalLabel">
                    <i class="fas fa-code me-2"></i>Cloudflare Workers для домена <span id="workerModalDomainName" class="text-primary"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="workerModalLoader" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Загрузка...</span></div>
                </div>

                <div id="workerModalContent" class="d-none">
                    <div class="mb-4">
                        <h6 class="fw-bold mb-3"><i class="fas fa-route me-2"></i>Активные маршруты</h6>
                        <div id="workerRoutesContainer" class="table-responsive border rounded p-3 bg-light"></div>
                    </div>

                    <div class="border rounded p-3">
                        <h6 class="fw-bold mb-3"><i class="fas fa-wrench me-2"></i>Применить шаблон</h6>
                        <form id="workerApplyForm">
                            <input type="hidden" id="workerDomainId">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="workerTemplateSelect" class="form-label">Шаблон</label>
                                    <select id="workerTemplateSelect" class="form-select">
                                        <option value="">— Выберите шаблон —</option>
                                    </select>
                                    <div class="form-text">Выберите ранее сохранённый скрипт Workers</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="workerRoutePattern" class="form-label">Маршрут</label>
                                    <input type="text" id="workerRoutePattern" class="form-control" placeholder="{{domain}}/*">
                                    <div class="form-text">Используйте {{domain}} для подстановки домена. По умолчанию: example.com/*</div>
                                </div>
                            </div>
                        </form>
                        <div class="mt-3 d-flex gap-2">
                            <button type="button" class="btn btn-primary" onclick="applyWorkerTemplate()">
                                <i class="fas fa-play me-1"></i>Применить шаблон
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="reloadWorkerModalData()">
                                <i class="fas fa-sync me-1"></i>Обновить данные
                            </button>
                        </div>
                    </div>

                    <div class="border rounded p-3 mt-4">
                        <h6 class="fw-bold mb-3"><i class="fas fa-file-code me-2"></i>Пользовательский скрипт</h6>
                        <div class="mb-3">
                            <label for="workerCustomScript" class="form-label">JavaScript</label>
                            <textarea id="workerCustomScript" class="form-control" rows="10" placeholder="// Вставьте сюда скрипт Cloudflare Workers"></textarea>
                        </div>
                        <div class="row g-3 align-items-center">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="workerSaveTemplate">
                                    <label class="form-check-label" for="workerSaveTemplate">Сохранить как новый шаблон</label>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <input type="text" id="workerTemplateName" class="form-control d-none" placeholder="Название нового шаблона">
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-success" onclick="applyWorkerCustomScript()">
                                <i class="fas fa-cloud-upload-alt me-1"></i>Загрузить скрипт
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span id="workerModalStatus" class="me-auto text-muted"></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно массового применения Workers -->
<div class="modal fade" id="bulkWorkerModal" tabindex="-1" aria-labelledby="bulkWorkerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkWorkerModalLabel"><i class="fas fa-layer-group me-2"></i>Массовое применение Workers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" id="bulkWorkerSelectionInfo"></div>
                <form id="bulkWorkerForm" class="border rounded p-3 bg-light">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="bulkWorkerTemplate" class="form-label">Шаблон</label>
                            <select id="bulkWorkerTemplate" class="form-select" required>
                                <option value="">— Выберите шаблон —</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="bulkWorkerRoutePattern" class="form-label">Маршрут</label>
                            <input type="text" id="bulkWorkerRoutePattern" class="form-control" placeholder="{{domain}}/*">
                            <div class="form-text">Маршрут будет применён к каждому домену (поддерживается {{domain}})</div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Область применения</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="bulkWorkerScope" id="bulkWorkerScopeSelected" value="selected" checked>
                            <label class="form-check-label" for="bulkWorkerScopeSelected">Только выбранные домены</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="bulkWorkerScope" id="bulkWorkerScopeGroup" value="group">
                            <label class="form-check-label" for="bulkWorkerScopeGroup">Вся группа</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="bulkWorkerScope" id="bulkWorkerScopeAll" value="all">
                            <label class="form-check-label" for="bulkWorkerScopeAll">Все домены</label>
                        </div>
                        <div class="mt-2 d-none" id="bulkWorkerGroupWrapper">
                            <label for="bulkWorkerGroup" class="form-label">Выберите группу</label>
                            <select id="bulkWorkerGroup" class="form-select">
                                <option value="">— Выберите группу —</option>
                                <?php if (isset($groups)): ?>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </form>
                <div id="bulkWorkerResult" class="mt-3 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary" onclick="bulkApplyWorkers()">
                    <i class="fas fa-cloud-upload-alt me-1"></i>Применить
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для создания SSL сертификатов -->
<div class="modal fade" id="createCertificateModal" tabindex="-1" aria-labelledby="createCertificateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createCertificateModalLabel">
                    <i class="fas fa-certificate me-2"></i>Создание SSL сертификатов
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Внимание!</strong> Будут созданы Origin CA сертификаты Cloudflare для выбранных доменов. 
                    Сертификаты действительны 1 год и включают wildcard поддомены.
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Origin CA сертификаты предназначены для шифрования трафика между Cloudflare и вашим сервером.
                </div>
                <form id="createCertificateForm">
                    <div class="mb-3">
                        <label class="form-label">Выбранные домены:</label>
                        <div id="selectedDomainsForCert" class="border rounded p-2 bg-light" style="max-height: 300px; overflow-y: auto;">
                            <span class="text-muted">Домены не выбраны</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeCertificateData">
                            <label class="form-check-label" for="includeCertificateData">
                                Показать сертификаты и ключи в логах (не рекомендуется для продакшена)
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-warning" onclick="processCertificateCreation()">
                    <i class="fas fa-tasks me-2"></i>Создать сертификаты
                </button>
            </div>
        </div>
    </div>
</div>