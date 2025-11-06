<div class="sidebar">
    <div class="p-3">
        <h4>Cloudflare Панель</h4>
        <hr>
        
        <!-- Основные разделы -->
        <a href="<?php echo BASE_PATH; ?>dashboard.php" class="btn btn-dark w-100 mb-2">
            <i class="fas fa-tachometer-alt me-2"></i>Панель управления
        </a>
        
        <a href="<?php echo BASE_PATH; ?>mass_operations.php" class="btn btn-primary w-100 mb-2">
            <i class="fas fa-cogs me-2"></i>Массовые операции
        </a>
        
        <a href="<?php echo BASE_PATH; ?>debug_certificates.php" class="btn btn-warning w-100 mb-2">
            <i class="fas fa-search me-2"></i>Диагностика сертификатов
        </a>
        
        <a href="<?php echo BASE_PATH; ?>view_certificates.php" class="btn btn-success w-100 mb-2">
            <i class="fas fa-certificate me-2"></i>SSL Сертификаты
        </a>
        
        <hr>
        
        <!-- Security / Rules / Cache -->
        <a href="<?php echo BASE_PATH; ?>security_settings.php" class="btn btn-outline-danger w-100 mb-2">
            <i class="fas fa-shield-alt me-2"></i>Security / Bots
        </a>
        <a href="<?php echo BASE_PATH; ?>page_rules.php" class="btn btn-outline-warning w-100 mb-2">
            <i class="fas fa-scroll me-2"></i>Page Rules
        </a>
        <a href="<?php echo BASE_PATH; ?>cache_tools.php" class="btn btn-outline-success w-100 mb-2">
            <i class="fas fa-broom me-2"></i>Cache Tools
        </a>
        
        <hr>
        
        <!-- Управление группами -->
        <button type="button" class="btn btn-outline-secondary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#addGroupModal">
            <i class="fas fa-users me-2"></i>Добавить группу
        </button>
        
        <button type="button" class="btn btn-outline-secondary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#deleteGroupModal">
            <i class="fas fa-trash-alt me-2"></i>Удалить группу
        </button>
        
        <hr>
        
        <!-- Добавление доменов -->
        <button type="button" class="btn btn-outline-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#addDomainModal">
            <i class="fas fa-globe me-2"></i>Добавить домен
        </button>
        
        <button type="button" class="btn btn-outline-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#addDomainsBulkModal">
            <i class="fas fa-globe-americas me-2"></i>Массовое добавление доменов
        </button>
        
        <!-- Добавление аккаунтов -->
        <button type="button" class="btn btn-outline-info w-100 mb-2" data-bs-toggle="modal" data-bs-target="#addAccountModal">
            <i class="fas fa-user-plus me-2"></i>Добавить аккаунт
        </button>
        
        <button type="button" class="btn btn-outline-info w-100 mb-2" data-bs-toggle="modal" data-bs-target="#addAccountsBulkModal">
            <i class="fas fa-users-cog me-2"></i>Массовое добавление аккаунтов
        </button>
        
        <a href="<?php echo BASE_PATH; ?>export_tokens.php" class="btn btn-outline-info w-100 mb-2">
            <i class="fas fa-key me-2"></i>Экспорт Token/Tag
        </a>
        
        <hr>
        
        <!-- Дополнительные разделы -->
        <a href="<?php echo BASE_PATH; ?>proxies.php" class="btn btn-dark w-100 mb-2">
            <i class="fas fa-server me-2"></i>Прокси
        </a>
        
        <a href="<?php echo BASE_PATH; ?>logs.php" class="btn btn-dark w-100 mb-2">
            <i class="fas fa-history me-2"></i>Логи
        </a>
        
        <hr>
        
        <!-- Выход -->
        <a href="<?php echo BASE_PATH; ?>logout.php" class="btn btn-danger w-100">
            <i class="fas fa-sign-out-alt me-2"></i>Выход
        </a>
    </div>
</div>