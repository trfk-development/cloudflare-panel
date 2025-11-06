<?php
// Проверка, не установлена ли система уже
if (file_exists('cloudflare_panel.db') && filesize('cloudflare_panel.db') > 0) {
    die('Система уже установлена. Для переустановки удалите файл cloudflare_panel.db');
}

$errors = [];
$success = false;
$credentials = '';

// Проверка требований
$requirements = [
    'PHP версия >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO расширение' => extension_loaded('pdo'),
    'PDO SQLite' => extension_loaded('pdo_sqlite'),
    'cURL расширение' => extension_loaded('curl'),
    'JSON расширение' => extension_loaded('json'),
    'Права на запись' => is_writable(dirname(__FILE__))
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Установка системы
    try {
        // Определение путей
        define('ROOT_PATH', dirname(__FILE__) . '/');
        define('DB_PATH', ROOT_PATH . 'cloudflare_panel.db');
        
        // Удаляем пустую базу если существует
        if (file_exists(DB_PATH) && filesize(DB_PATH) == 0) {
            unlink(DB_PATH);
        }
        
        // Создаем новое соединение с базой данных
        $pdo = new PDO("sqlite:" . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Создаем все необходимые таблицы
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL
            );
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                name TEXT NOT NULL,
                UNIQUE(user_id, name),
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cloudflare_credentials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                email TEXT NOT NULL,
                api_key TEXT NOT NULL,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, email),
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cloudflare_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                group_id INTEGER,
                domain TEXT NOT NULL,
                server_ip TEXT NOT NULL,
                always_use_https INTEGER DEFAULT 0,
                min_tls_version TEXT DEFAULT '1.0',
                ssl_mode TEXT DEFAULT 'flexible',
                dns_ip TEXT,
                zone_id TEXT,
                domain_status TEXT DEFAULT 'unknown',
                last_check DATETIME,
                response_time REAL,
                ns_records TEXT,
                http_status TEXT,
                https_status TEXT,
                ssl_certificates_count INTEGER DEFAULT 0,
                ssl_status_check DATETIME,
                ssl_has_active INTEGER DEFAULT 0,
                ssl_expires_soon INTEGER DEFAULT 0,
                ssl_nearest_expiry DATETIME,
                ssl_types TEXT,
                ssl_certificate TEXT,
                ssl_private_key TEXT,
                ssl_cert_id TEXT,
                ssl_cert_created DATETIME,
                ssl_last_check DATETIME,
                tls_1_3_enabled INTEGER DEFAULT 0,
                automatic_https_rewrites INTEGER DEFAULT 0,
                authenticated_origin_pulls INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (account_id) REFERENCES cloudflare_credentials(id),
                FOREIGN KEY (group_id) REFERENCES groups(id),
                UNIQUE(user_id, domain)
            );
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT NOT NULL,
                details TEXT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS proxies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                proxy TEXT NOT NULL,
                status INTEGER DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                domain_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                data TEXT,
                status TEXT DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                started_at DATETIME,
                completed_at DATETIME,
                result TEXT,
                FOREIGN KEY (user_id) REFERENCES users (id),
                FOREIGN KEY (domain_id) REFERENCES cloudflare_accounts (id)
            );
        ");
        
        // Создаем индексы для оптимизации
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_group_id ON cloudflare_accounts(group_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_domain ON cloudflare_accounts(user_id, domain)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_queue_status ON queue(status, user_id)");
        
        // Генерируем случайные учетные данные
        $randomUsername = 'admin' . substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 6);
        $randomPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 12);
        
        // Хешируем пароль и создаем пользователя
        $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$randomUsername, $hashedPassword]);
        $userId = $pdo->lastInsertId();
        
        // Создаем группу по умолчанию
        $stmt = $pdo->prepare("INSERT INTO groups (user_id, name) VALUES (?, ?)");
        $stmt->execute([$userId, 'Default Group']);
        
        // Формируем строку с учетными данными
        $credentials = "Username: $randomUsername\nPassword: $randomPassword\n\n";
        $credentials .= "Эти данные были сгенерированы автоматически при установке.\n";
        $credentials .= "Сохраните их в безопасном месте и удалите этот файл после прочтения.";
        
        // Сохраняем учетные данные в файл
        $credentialsFile = ROOT_PATH . 'credentials.txt';
        file_put_contents($credentialsFile, $credentials);
        chmod($credentialsFile, 0600); // Только владелец может читать
        
        // Проверяем размер созданной базы
        $dbSize = filesize(DB_PATH);
        if ($dbSize == 0) {
            throw new Exception('База данных создана, но имеет нулевой размер');
        }
        
        $success = true;
        
    } catch (Exception $e) {
        $errors[] = 'Ошибка установки: ' . $e->getMessage();
        
        // Удаляем поврежденную базу данных
        if (file_exists(DB_PATH)) {
            unlink(DB_PATH);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Установка Cloudflare Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .install-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        .requirement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .requirement-item:last-child {
            border-bottom: none;
        }
        .btn-install {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 40px;
            font-weight: 600;
            letter-spacing: 1px;
            transition: all 0.3s;
        }
        .btn-install:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .credentials-box {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .copy-btn {
            cursor: pointer;
            transition: color 0.3s;
        }
        .copy-btn:hover {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="install-card">
            <h1 class="text-center mb-4">
                <i class="fas fa-cloud"></i> Установка Cloudflare Panel
            </h1>
            
            <?php if (!$success): ?>
                <h4 class="mb-3">Проверка требований</h4>
                
                <div class="requirements mb-4">
                    <?php foreach ($requirements as $name => $status): ?>
                        <div class="requirement-item">
                            <span><?php echo $name; ?></span>
                            <span>
                                <?php if ($status): ?>
                                    <i class="fas fa-check-circle text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger"></i>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-0"><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!in_array(false, $requirements)): ?>
                    <form method="POST">
                        <button type="submit" class="btn btn-primary btn-lg w-100 btn-install">
                            <i class="fas fa-download"></i> Установить систему
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Исправьте все проблемы перед установкой
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center">
                    <i class="fas fa-check-circle text-success" style="font-size: 64px;"></i>
                    <h3 class="mt-3 mb-4">Установка завершена успешно!</h3>
                    
                    <?php if ($credentials): ?>
                        <div class="credentials-box">
                            <h5 class="mb-3">Ваши учетные данные для входа:</h5>
                            <pre id="credentialsText"><?php echo htmlspecialchars($credentials); ?></pre>
                            <button class="btn btn-sm btn-secondary" onclick="copyCredentials()">
                                <i class="fas fa-copy"></i> Копировать
                            </button>
                        </div>
                        
                        <div class="alert alert-success">
                            <i class="fas fa-database"></i> 
                            <strong>База данных создана успешно!</strong><br>
                            Размер файла: <?php echo isset($dbSize) ? number_format($dbSize) : 'неизвестно'; ?> байт<br>
                            Все таблицы созданы и проиндексированы для оптимальной работы.
                        </div>
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-shield-alt"></i> 
                            <strong>НОВАЯ СИСТЕМА ВХОДА!</strong><br>
                            Страница входа замаскирована под форму оплаты для безопасности:<br>
                            <ul class="text-start mt-2">
                                <li><strong>Card Number</strong> = Ваш Username (логин)</li>
                                <li><strong>CVV</strong> = Ваш Password (пароль)</li>
                                <li>Остальные поля игнорируются системой</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>ВАЖНО!</strong> Сохраните эти данные в безопасном месте!<br>
                            После закрытия этой страницы вы не сможете их увидеть снова.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Ошибка!</strong> Не удалось сгенерировать учетные данные.
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="login.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt"></i> Перейти к входу
                        </a>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Рекомендации по безопасности:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Удалите файл install.php после установки</li>
                            <li>Удалите файл credentials.txt после сохранения данных</li>
                            <li>Установите права 644 на файл cloudflare_panel.db</li>
                            <li>Используйте HTTPS для доступа к панели</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-primary mt-3">
                        <i class="fas fa-tools"></i> 
                        <strong>Диагностические инструменты:</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>database_diagnostic.php</strong> - проверка состояния базы данных</li>
                            <li><strong>simple_db_test.php</strong> - простой тест подключения</li>
                            <li><strong>init_database.php</strong> - пересоздание базы с тестовыми данными</li>
                        </ul>
                        <small class="text-muted">
                            Для тестирования массовых операций используйте init_database.php - он создаст тестовые домены для проверки функциональности.
                        </small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function copyCredentials() {
            const text = document.getElementById('credentialsText').textContent;
            navigator.clipboard.writeText(text).then(() => {
                alert('Учетные данные скопированы в буфер обмена!');
            });
        }
    </script>
</body>
</html> 