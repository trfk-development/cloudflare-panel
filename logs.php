<?php
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

// Исправляем запрос: используем user_id вместо domain_id
$stmt = $pdo->prepare("SELECT l.*, ca.domain FROM logs l LEFT JOIN cloudflare_accounts ca ON l.user_id = ca.user_id WHERE l.user_id = ? ORDER BY l.timestamp DESC");
$stmt->execute([$_SESSION['user_id']]);
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Логи</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .sidebar { height: 100vh; position: fixed; width: 250px; background: #343a40; color: white; }
        .content { margin-left: 250px; padding: 20px; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="p-3">
            <h4>Cloudflare Панель</h4>
            <hr>
            <a href="<?php echo BASE_PATH; ?>dashboard.php" class="btn btn-dark w-100 mb-2"><i class="fas fa-tachometer-alt"></i> Панель</a>
            <a href="<?php echo BASE_PATH; ?>proxies.php" class="btn btn-dark w-100 mb-2"><i class="fas fa-server"></i> Прокси</a>
            <a href="<?php echo BASE_PATH; ?>logs.php" class="btn btn-dark w-100 mb-2"><i class="fas fa-history"></i> Логи</a>
            <a href="<?php echo BASE_PATH; ?>logout.php" class="btn btn-dark w-100"><i class="fas fa-sign-out-alt"></i> Выход</a>
        </div>
    </div>
    
    <div class="content">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Логи</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Время</th>
                                <th>Домен</th>
                                <th>Действие</th>
                                <th>Детали</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                    <td><?php echo htmlspecialchars($log['domain'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td><?php echo htmlspecialchars($log['details'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>