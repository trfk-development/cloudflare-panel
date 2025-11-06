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

$notification = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_proxies'])) {
    $proxiesList = explode("\n", trim($_POST['proxies']));
    $addedCount = 0;
    $errorCount = 0;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO proxies (user_id, proxy) VALUES (?, ?)");
        
        foreach ($proxiesList as $proxy) {
            $proxy = trim($proxy);
            if (empty($proxy)) continue;
            
            // Более гибкая проверка формата прокси
            if (preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)@([^:@]+):(.+)$/', $proxy, $matches)) {
                $ip = $matches[1];
                $port = $matches[2];
                
                // Валидация IP и порта
                if (filter_var($ip, FILTER_VALIDATE_IP) && $port > 0 && $port <= 65535) {
                    try {
                        $stmt->execute([$_SESSION['user_id'], $proxy]);
                        $proxyId = $pdo->lastInsertId();
                        checkProxy($pdo, $proxy, $proxyId, $_SESSION['user_id']);
                        $addedCount++;
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) { // Дубликат
                            logAction($pdo, $_SESSION['user_id'], "Proxy duplicate", "Proxy: $proxy");
                        } else {
                            throw $e;
                        }
                        $errorCount++;
                    }
                } else {
                    logAction($pdo, $_SESSION['user_id'], "Invalid proxy format", "Proxy: $proxy");
                    $errorCount++;
                }
            } else {
                logAction($pdo, $_SESSION['user_id'], "Invalid proxy format", "Proxy: $proxy");
                $errorCount++;
            }
        }
        
        if ($addedCount > 0) {
            $notification = "Добавлено прокси: $addedCount";
        }
        if ($errorCount > 0) {
            $error = "Ошибок при добавлении: $errorCount";
        }
    } catch (PDOException $e) {
        $error = "Ошибка при добавлении прокси";
        logAction($pdo, $_SESSION['user_id'], "Proxy add error", "Error: " . $e->getMessage());
    }
}

// Удаление прокси
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_proxy'])) {
    $proxyId = (int)($_POST['proxy_id'] ?? 0);
    if ($proxyId > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM proxies WHERE id = ? AND user_id = ?");
            $stmt->execute([$proxyId, $_SESSION['user_id']]);
            if ($stmt->rowCount() > 0) {
                $notification = "Прокси удален";
                logAction($pdo, $_SESSION['user_id'], "Proxy deleted", "Proxy ID: $proxyId");
            } else {
                $error = "Прокси не найден";
            }
        } catch (PDOException $e) {
            $error = "Ошибка при удалении прокси";
            logAction($pdo, $_SESSION['user_id'], "Proxy delete error", "Error: " . $e->getMessage());
        }
    }
}

// Проверка всех прокси
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_all_proxies'])) {
    $stmt = $pdo->prepare("SELECT id, proxy FROM proxies WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $allProxies = $stmt->fetchAll();
    
    $checkedCount = 0;
    foreach ($allProxies as $proxyData) {
        checkProxy($pdo, $proxyData['proxy'], $proxyData['id'], $_SESSION['user_id']);
        $checkedCount++;
    }
    
    $notification = "Проверено прокси: $checkedCount";
}

$stmt = $pdo->prepare("SELECT * FROM proxies WHERE user_id = ? ORDER BY status DESC, id DESC");
$stmt->execute([$_SESSION['user_id']]);
$proxies = $stmt->fetchAll();
?>

<?php include 'sidebar.php'; ?>

<div class="content">
    <?php if ($notification): ?>
        <div class="alert alert-success alert-dismissible">
            <?php echo htmlspecialchars($notification); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Добавить прокси</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="proxies" class="form-label">Прокси (по одному на строку)</label>
                            <textarea name="proxies" id="proxies" class="form-control" rows="5" placeholder="192.168.1.1:8080@username:password" required></textarea>
                            <small class="form-text text-muted">Формат: IP:PORT@LOGIN:PASSWORD</small>
                        </div>
                        <button type="submit" name="add_proxies" class="btn btn-primary w-100">
                            <i class="fas fa-plus"></i> Добавить прокси
                        </button>
                    </form>
                    
                    <form method="POST" class="mt-3">
                        <button type="submit" name="check_all_proxies" class="btn btn-info w-100">
                            <i class="fas fa-sync"></i> Проверить все прокси
                        </button>
                    </form>
                    
                    <div class="mt-3">
                        <a href="test_proxy.php" class="btn btn-warning w-100" target="_blank">
                            <i class="fas fa-bug"></i> Диагностика прокси
                        </a>
                        <small class="form-text text-muted">Для диагностики проблем с прокси</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Список прокси (<?php echo count($proxies); ?>)</h5>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Прокси</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($proxies as $proxy): ?>
                                    <tr>
                                        <td class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($proxy['proxy']); ?>">
                                            <?php echo htmlspecialchars($proxy['proxy']); ?>
                                        </td>
                                        <td>
                                            <?php if ($proxy['status'] == 1): ?>
                                                <span class="badge bg-success">Работает</span>
                                            <?php elseif ($proxy['status'] == 2): ?>
                                                <span class="badge bg-danger">Не работает</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Не проверен</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="proxy_id" value="<?php echo $proxy['id']; ?>">
                                                <button type="submit" name="delete_proxy" class="btn btn-sm btn-danger" onclick="return confirm('Удалить прокси?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'modals.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>