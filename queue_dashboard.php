<?php
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Получаем общую статистику очереди
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM queue 
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$queueStats = $stmt->fetch();

// Получаем статистику NS задач
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM queue 
    WHERE user_id = ? AND type LIKE '%ns%'
");
$stmt->execute([$userId]);
$nsStats = $stmt->fetch();

// Получаем домены без NS записей
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM cloudflare_accounts 
    WHERE user_id = ? AND (ns_records IS NULL OR ns_records = '' OR ns_records = '[]')
");
$stmt->execute([$userId]);
$domainsNeedingNS = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔄 Управление очередью задач</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-stats {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
        }
        .card-ns-stats {
            background: linear-gradient(45deg, #28a745, #1e7e34);
            color: white;
        }
        .status-badge {
            font-size: 0.8em;
            min-width: 80px;
        }
        .task-row {
            border-left: 4px solid #dee2e6;
        }
        .task-row.pending {
            border-left-color: #ffc107;
        }
        .task-row.processing {
            border-left-color: #17a2b8;
        }
        .task-row.completed {
            border-left-color: #28a745;
        }
        .task-row.failed {
            border-left-color: #dc3545;
        }
        .auto-refresh {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Автообновление -->
        <div class="auto-refresh">
            <div class="card">
                <div class="card-body py-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="autoRefresh">
                        <label class="form-check-label" for="autoRefresh">
                            <i class="fas fa-sync-alt"></i> Автообновление
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Заголовок -->
        <div class="row mb-4">
            <div class="col">
                <h1><i class="fas fa-tasks"></i> Управление очередью задач</h1>
                <p class="text-muted">Мониторинг и управление задачами обновления NS серверов</p>
            </div>
            <div class="col-auto">
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Назад к дашборду
                </a>
            </div>
        </div>

        <!-- Статистика -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card card-stats">
                    <div class="card-body">
                        <h5><i class="fas fa-list"></i> Общая статистика очереди</h5>
                        <div class="row text-center">
                            <div class="col">
                                <div class="h3 mb-0"><?= $queueStats['total'] ?></div>
                                <small>Всего задач</small>
                            </div>
                            <div class="col">
                                <div class="h3 mb-0"><?= $queueStats['pending'] ?></div>
                                <small>В очереди</small>
                            </div>
                            <div class="col">
                                <div class="h3 mb-0"><?= $queueStats['processing'] ?></div>
                                <small>Выполняется</small>
                            </div>
                            <div class="col">
                                <div class="h3 mb-0"><?= $queueStats['completed'] ?></div>
                                <small>Завершено</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card card-ns-stats">
                    <div class="card-body">
                        <h5><i class="fas fa-server"></i> NS задачи</h5>
                        <div class="row text-center">
                            <div class="col">
                                <div class="h3 mb-0"><?= $nsStats['total'] ?></div>
                                <small>NS задач</small>
                            </div>
                            <div class="col">
                                <div class="h3 mb-0"><?= $nsStats['pending'] ?></div>
                                <small>В очереди</small>
                            </div>
                            <div class="col">
                                <div class="h3 mb-0"><?= $domainsNeedingNS ?></div>
                                <small>Нужно NS</small>
                            </div>
                            <div class="col">
                                <div class="h3 mb-0"><?= $nsStats['failed'] ?></div>
                                <small>Ошибки</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Панель управления -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-cogs"></i> Управление NS задачами</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <button class="btn btn-success btn-block w-100 mb-2" onclick="addBulkNSUpdate()">
                                    <i class="fas fa-rocket"></i> Массовое обновление NS
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary btn-block w-100 mb-2" onclick="processQueue()">
                                    <i class="fas fa-play"></i> Запустить процессор
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-warning btn-block w-100 mb-2" onclick="clearCompleted()">
                                    <i class="fas fa-broom"></i> Очистить завершенные
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-info btn-block w-100 mb-2" onclick="refreshStatus()">
                                    <i class="fas fa-sync-alt"></i> Обновить статус
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Активные задачи -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-clock"></i> Активные задачи</h5>
                        <small class="text-muted">Обновляется автоматически</small>
                    </div>
                    <div class="card-body" id="activeTasks">
                        <div class="text-center py-3">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Загрузка...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- История задач -->
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> История задач</h5>
                    </div>
                    <div class="card-body" id="taskHistory">
                        <div class="text-center py-3">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Загрузка...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно для настроек массового обновления -->
    <div class="modal fade" id="bulkNSModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Массовое обновление NS серверов</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="bulkLimit" class="form-label">Количество доменов для обработки:</label>
                        <input type="number" class="form-control" id="bulkLimit" value="10" min="1" max="50">
                        <div class="form-text">Рекомендуется не более 20 доменов за раз</div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Будут обновлены NS серверы для доменов, у которых они отсутствуют или устарели.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-success" onclick="confirmBulkNSUpdate()">
                        <i class="fas fa-rocket"></i> Добавить в очередь
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let autoRefreshInterval;
        let bulkNSModal;
        
        document.addEventListener('DOMContentLoaded', function() {
            bulkNSModal = new bootstrap.Modal(document.getElementById('bulkNSModal'));
            
            // Загружаем начальные данные
            refreshStatus();
            
            // Настройка автообновления
            const autoRefreshCheckbox = document.getElementById('autoRefresh');
            autoRefreshCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    autoRefreshInterval = setInterval(refreshStatus, 5000); // Каждые 5 секунд
                } else {
                    clearInterval(autoRefreshInterval);
                }
            });
        });
        
        function refreshStatus() {
            fetch('ns_queue_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_queue_status'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateActiveTasks(data.pending_tasks, data.processing_tasks);
                    updateTaskHistory(data.recent_tasks);
                } else {
                    showError('Ошибка получения статуса: ' + data.error);
                }
            })
            .catch(error => {
                showError('Ошибка сети: ' + error.message);
            });
        }
        
        function updateActiveTasks(pendingTasks, processingTasks) {
            const container = document.getElementById('activeTasks');
            let html = '';
            
            if (processingTasks.length === 0 && pendingTasks.length === 0) {
                html = '<div class="text-center text-muted py-3">Нет активных задач</div>';
            } else {
                if (processingTasks.length > 0) {
                    html += '<h6><i class="fas fa-cog fa-spin"></i> Выполняется:</h6>';
                    processingTasks.forEach(task => {
                        html += renderTaskRow(task, 'processing');
                    });
                }
                
                if (pendingTasks.length > 0) {
                    html += '<h6><i class="fas fa-clock"></i> В очереди:</h6>';
                    pendingTasks.forEach(task => {
                        html += renderTaskRow(task, 'pending');
                    });
                }
            }
            
            container.innerHTML = html;
        }
        
        function updateTaskHistory(recentTasks) {
            const container = document.getElementById('taskHistory');
            let html = '';
            
            if (recentTasks.length === 0) {
                html = '<div class="text-center text-muted py-3">Нет задач в истории</div>';
            } else {
                recentTasks.forEach(task => {
                    html += renderTaskRow(task, task.status);
                });
            }
            
            container.innerHTML = html;
        }
        
        function renderTaskRow(task, status) {
            const statusBadges = {
                'pending': '<span class="badge bg-warning status-badge">В очереди</span>',
                'processing': '<span class="badge bg-info status-badge">Выполняется</span>',
                'completed': '<span class="badge bg-success status-badge">Завершено</span>',
                'failed': '<span class="badge bg-danger status-badge">Ошибка</span>',
                'cancelled': '<span class="badge bg-secondary status-badge">Отменено</span>'
            };
            
            const typeLabels = {
                'update_ns_records': 'Обновление NS',
                'bulk_update_ns_records': 'Массовое обновление NS'
            };
            
            let actions = '';
            if (status === 'pending') {
                actions = `<button class="btn btn-sm btn-outline-danger" onclick="cancelTask(${task.id})">
                    <i class="fas fa-times"></i> Отменить
                </button>`;
            }
            
            return `
                <div class="task-row ${status} p-3 mb-2 bg-white rounded border">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            ${statusBadges[status] || '<span class="badge bg-secondary">' + status + '</span>'}
                        </div>
                        <div class="col-md-3">
                            <strong>${typeLabels[task.type] || task.type}</strong>
                            ${task.domain ? '<br><small class="text-muted">' + task.domain + '</small>' : ''}
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">
                                Создано: ${new Date(task.created_at).toLocaleString('ru-RU')}
                                ${task.completed_at ? '<br>Завершено: ' + new Date(task.completed_at).toLocaleString('ru-RU') : ''}
                            </small>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted">ID: ${task.id}</small>
                        </div>
                        <div class="col-md-2 text-end">
                            ${actions}
                        </div>
                    </div>
                </div>
            `;
        }
        
        function addBulkNSUpdate() {
            bulkNSModal.show();
        }
        
        function confirmBulkNSUpdate() {
            const limit = document.getElementById('bulkLimit').value;
            
            fetch('ns_queue_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'add_bulk_ns_update',
                    limit: parseInt(limit)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    bulkNSModal.hide();
                    refreshStatus();
                } else {
                    showError('Ошибка: ' + data.error);
                }
            })
            .catch(error => {
                showError('Ошибка сети: ' + error.message);
            });
        }
        
        function processQueue() {
            fetch('queue_processor.php?action=process&auth_token=cloudflare_queue_processor_2024', {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(`Обработано задач: ${data.processed}, время: ${data.execution_time}с`);
                    refreshStatus();
                } else {
                    showError('Ошибка процессора: ' + data.error);
                }
            })
            .catch(error => {
                showError('Ошибка запуска процессора: ' + error.message);
            });
        }
        
        function clearCompleted() {
            fetch('ns_queue_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'clear_completed_ns_tasks'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    refreshStatus();
                    // Перезагружаем страницу для обновления статистики
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showError('Ошибка: ' + data.error);
                }
            })
            .catch(error => {
                showError('Ошибка сети: ' + error.message);
            });
        }
        
        function cancelTask(taskId) {
            if (!confirm('Вы уверены, что хотите отменить эту задачу?')) {
                return;
            }
            
            fetch('ns_queue_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'cancel_pending_task',
                    task_id: taskId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    refreshStatus();
                } else {
                    showError('Ошибка: ' + data.error);
                }
            })
            .catch(error => {
                showError('Ошибка сети: ' + error.message);
            });
        }
        
        function showSuccess(message) {
            // Простое уведомление (можно заменить на toast)
            alert('✅ ' + message);
        }
        
        function showError(message) {
            // Простое уведомление (можно заменить на toast)
            alert('❌ ' + message);
        }
    </script>
</body>
</html> 