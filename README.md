<div align="center">

# 🚀 CloudPanel

### Мощная панель управления Cloudflare для массового управления доменами

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/)
[![Cloudflare API](https://img.shields.io/badge/Cloudflare-API%20v4-orange)](https://developers.cloudflare.com/api/)
[![Version](https://img.shields.io/badge/version-2.0-green.svg)](CHANGELOG.md)

[Возможности](#-возможности) •
[Установка](#-установка) •
[Использование](#-использование) •
[Безопасность](#-безопасность) •
[API](#-api) •
[Документация](#-документация)

---

</div>

## 📋 Описание

**CloudPanel** — это комплексная система управления доменами через Cloudflare API. Позволяет управлять **сотнями доменов** через единый интерфейс с поддержкой массовых операций, групп, Workers, и продвинутой системы безопасности.

### 🎯 Для кого?

- 🏢 **Веб-студии** — управление сайтами клиентов
- 💼 **SEO агентства** — массовые операции с доменами
- 🔐 **DevOps инженеры** — автоматизация управления DNS и SSL
- 🌐 **Владельцы доменных портфелей** — централизованное управление
- 🛡️ **Security специалисты** — защита от ботов и вредоносного трафика

---

## ✨ Возможности

### 🌐 Управление доменами

<table>
<tr>
<td width="50%">

#### DNS Management
- ✅ Управление DNS записями (A, AAAA, CNAME, MX, TXT, SRV, CAA)
- ✅ Массовое добавление/изменение
- ✅ Автоматическая синхронизация DNS IP
- ✅ Bulk операции по группам
- ✅ Экспорт/импорт DNS записей

</td>
<td width="50%">

#### Domain Organization
- ✅ Группировка доменов
- ✅ Массовые операции
- ✅ Фильтрация и поиск
- ✅ Сортировка по параметрам
- ✅ Пагинация (200 доменов/страница)

</td>
</tr>
</table>

### 🔒 SSL/TLS управление

- ✅ Режимы SSL: Off, Flexible, Full, Full (Strict)
- ✅ Минимальная версия TLS (1.0, 1.1, 1.2, 1.3)
- ✅ Always Use HTTPS
- ✅ Automatic HTTPS Rewrites
- ✅ Origin CA сертификаты
- ✅ Universal SSL
- ✅ Authenticated Origin Pulls
- ✅ TLS 1.3 включение
- ✅ Массовое обновление настроек

### 🛡️ Security Rules Manager

<table>
<tr>
<td width="50%">

#### 🤖 Блокировка Bad Bots
Интеграция с [nginx-ultimate-bad-bot-blocker](https://github.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker):
- ✅ 5000+ известных bad bots
- ✅ SEO инструменты (Semrush, Ahrefs)
- ✅ Сканеры уязвимостей (Nikto, SQLmap, Nmap)
- ✅ Malware, Ransomware, Adware
- ✅ Автообновление списков (еженедельно)

#### 🚫 Блокировка IP
- ✅ Одиночные IP адреса
- ✅ CIDR диапазоны (IPv4/IPv6)
- ✅ Автоимпорт вредоносных IP
- ✅ Массовое добавление

</td>
<td width="50%">

#### 🌍 Геоблокировка
- ✅ Whitelist режим (только разрешенные страны)
- ✅ Blacklist режим (блокировка стран)
- ✅ 195+ стран с флагами
- ✅ Поиск по названию
- ✅ Массовое применение

#### 🔍 Защита "Только поисковики"
**Доступ ТОЛЬКО через:**
- ✅ Google, Yandex, Bing, DuckDuckGo
- ✅ Кастомные разрешенные домены
- ✅ Блокировка прямого доступа
- ✅ Настраиваемые исключения

</td>
</tr>
</table>

### ⚙️ Cloudflare Workers

**5 готовых шаблонов Workers:**

| Шаблон | Описание | Размер | Скорость |
|--------|----------|--------|----------|
| 🛡️ **Advanced Protection** | Полная защита: боты + IP + geo + referrer + rate limit | 12 KB | <1ms |
| 🤖 **Bot Blocker** | Только блокировка ботов | 2 KB | <0.5ms |
| 🌍 **Geo Blocker** | Только геоблокировка | 1.5 KB | <0.3ms |
| 🔍 **Referrer Only** | Только проверка referrer | 2 KB | <0.5ms |
| ⏱️ **Rate Limiter** | Ограничение запросов | 2.5 KB | <0.5ms |

**Возможности:**
- ✅ Визуальный предпросмотр кода
- ✅ Массовое развертывание
- ✅ Настройка route patterns
- ✅ Хранение в базе данных

### 🔥 Firewall & Security

- ✅ Firewall Rules (WAF)
- ✅ Rate Limiting
- ✅ Security Level (Under Attack режим)
- ✅ Bot Fight Mode
- ✅ Challenge Passage
- ✅ Browser Integrity Check
- ✅ Блокировка по странам
- ✅ Кастомные правила (Expression Builder)

### ⚡ Performance

- ✅ Cache управление (Purge, Level, Browser TTL)
- ✅ Page Rules (до 125 правил)
- ✅ Argo Smart Routing
- ✅ Development Mode
- ✅ Always Online
- ✅ Polish (сжатие изображений)
- ✅ Mirage
- ✅ Rocket Loader

### 📧 Email Routing

- ✅ Создание email маршрутов
- ✅ Catch-all адреса
- ✅ Пересылка на несколько адресов
- ✅ Управление правилами

### 📊 Analytics & Monitoring

- ✅ Статистика трафика
- ✅ DNS analytics
- ✅ Security events
- ✅ Performance metrics
- ✅ Логирование всех действий
- ✅ История изменений

### 🔄 Массовые операции

- ✅ Bulk добавление доменов
- ✅ Bulk добавление Cloudflare аккаунтов
- ✅ Массовое обновление DNS
- ✅ Массовое изменение SSL настроек
- ✅ Групповые операции
- ✅ Система очередей для длительных задач

### 🌐 Прокси поддержка

- ✅ HTTP/HTTPS прокси
- ✅ Ротация прокси
- ✅ Проверка работоспособности
- ✅ Автоматический выбор
- ✅ Статистика использования

---

## 🚀 Установка

### Требования

- **PHP:** 7.4 или выше
- **Расширения PHP:**
  - PDO
  - PDO SQLite
  - cURL
  - JSON
- **Веб-сервер:** Nginx или Apache
- **Права:** Запись в директорию проекта

### Шаг 1: Клонирование репозитория

```bash
git clone https://github.com/yourusername/cloudpanel.git
cd cloudpanel
```

### Шаг 2: Установка прав доступа

```bash
# Linux/macOS
chmod 755 .
chmod 644 *.php
chmod 600 cloudflare_panel.db  # после создания

# Создание необходимых директорий
mkdir -p cache worker_templates migrations
chmod 755 cache worker_templates migrations
```

### Шаг 3: Первоначальная установка

**Через браузер:**

```
http://yourdomain.com/install.php
```

1. Следуйте инструкциям установщика
2. Сохраните сгенерированные учетные данные
3. **ВАЖНО:** Удалите `install.php` после установки

**Через командную строку:**

```bash
php init_database.php
```

### Шаг 4: Настройка веб-сервера

<details>
<summary><b>Nginx конфигурация</b></summary>

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    root /var/www/cloudpanel;
    index index.php;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Block access to sensitive files
    location ~ /(credentials\.txt|install\.php|\.db)$ {
        deny all;
    }
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}
```

</details>

<details>
<summary><b>Apache конфигурация</b></summary>

```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/cloudpanel
    
    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem
    
    <Directory /var/www/cloudpanel>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Block sensitive files
    <FilesMatch "(credentials\.txt|install\.php|\.db)$">
        Require all denied
    </FilesMatch>
</VirtualHost>
```

</details>

### Шаг 5: Security Rules Manager (опционально)

```bash
# 1. Применить SQL миграцию
sqlite3 cloudflare_panel.db < migrations/add_security_rules_table.sql

# 2. Настроить автообновление списков ботов
crontab -e

# Добавить строку (обновление каждое воскресенье в 3:00):
0 3 * * 0 php /path/to/cloudpanel/update_bad_bots_list.php
```

### Шаг 6: Настройка очереди (опционально)

```bash
# Для обработки массовых операций
crontab -e

# Добавить строку (каждую минуту):
* * * * * php /path/to/cloudpanel/queue_processor.php
```

---

## 🔐 Первый вход

### Особенность входа

**Страница входа замаскирована под форму оплаты для безопасности:**

```
http://yourdomain.com/login.php
```

**Как войти:**
1. **Card Number** = Ваш Username (логин)
2. **CVV** = Ваш Password (пароль)
3. Остальные поля игнорируются

> 💡 **Подсказка:** Учетные данные находятся в файле `credentials.txt` после первой установки. **Удалите этот файл** после сохранения данных!

---

## 📖 Использование

### Добавление Cloudflare аккаунта

**Единичное добавление:**

1. Перейдите: **Аккаунты Cloudflare** → **Добавить аккаунт**
2. Введите:
   - Email (для legacy auth) или оставьте пустым
   - **API Key** или **API Token** (рекомендуется)
3. Нажмите **Сохранить**

**Массовое добавление:**

```
Dashboard → Массовые операции → Добавить аккаунты

Формат (каждый аккаунт на новой строке):
email1@example.com;api_key_or_token_1
email2@example.com;api_key_or_token_2
;your_api_token_3
```

### Создание API Token в Cloudflare

**Рекомендуемый метод аутентификации:**

1. Зайдите: https://dash.cloudflare.com/profile/api-tokens
2. **Create Token** → **Create Custom Token**
3. Выдайте права:
   - Zone → Zone → Read
   - Zone → DNS → Edit
   - Zone → Zone Settings → Edit
   - Zone → SSL and Certificates → Edit
4. (Опционально) Ограничьте по IP и зонам
5. **Create Token** → Скопируйте токен
6. Добавьте в CloudPanel

### Добавление доменов

**Единичное добавление:**

```
Dashboard → Добавить домен
```

1. Выберите Cloudflare аккаунт
2. Введите домен: `example.com`
3. IP адрес сервера: `192.168.1.1`
4. Выберите группу (опционально)
5. SSL режим: `Flexible` / `Full` / `Full (Strict)`
6. **Сохранить**

**Массовое добавление:**

```
Массовые операции → Добавить домены

Формат:
domain1.com;192.168.1.1;flexible;Group1
domain2.com;192.168.1.2;full;Group2
domain3.com;192.168.1.3;strict;Group1
```

### Управление DNS

```
Dashboard → Выберите домен → DNS Records
```

**Создание DNS записи:**
- **Тип:** A, AAAA, CNAME, MX, TXT, SRV, CAA
- **Имя:** `subdomain` или `@`
- **Содержимое:** IP адрес или значение
- **TTL:** Auto (1) или значение в секундах
- **Proxied:** ☁️ (да) или ⚪ (нет)

**Массовое обновление DNS IP:**

```
Массовые операции → Обновить DNS IP

Выберите: Все домены / Группа / Выбранные
Нажмите: Обновить
```

### SSL/TLS настройки

**Индивидуальные настройки:**

```
Dashboard → Домен → Настройки → SSL/TLS
```

- **SSL Mode:** Off / Flexible / Full / Full (Strict)
- **Min TLS Version:** 1.0 / 1.1 / 1.2 / 1.3
- **Always Use HTTPS:** Вкл/Выкл
- **Automatic HTTPS Rewrites:** Вкл/Выкл

**Массовое обновление:**

```
Массовые операции → SSL Settings

Выберите домены → Настройте параметры → Применить
```

---

## 🛡️ Security Rules Manager

### Быстрый старт

```
Dashboard → Security Rules Manager
```

### 1. Блокировка Bad Bots

**Защита от:**
- SEO инструменты (Semrush, Ahrefs, Majestic)
- Парсеры (Scrapy, curl, wget)
- Сканеры (Nikto, SQLmap, Nmap)
- Malware, Ransomware

**Применение:**

```
Вкладка: Блокировка ботов
Выбрать: 
  ✅ Блокировать все известные плохие боты
  ✅ Блокировать спам-реферреры
  ✅ Блокировать сканеры уязвимостей
  ✅ Блокировать malware

Применить к: Все домены / Группа / Выбранные
Нажать: Применить защиту от ботов
```

### 2. Геоблокировка

**Пример: Доступ только из России и СНГ**

```
Вкладка: Геоблокировка
Режим: Whitelist (только разрешенные)
Выбрать страны:
  - 🇷🇺 Россия
  - 🇧🇾 Беларусь
  - 🇰🇿 Казахстан
  - 🇺🇦 Украина

Применить к: Группа "Основные сайты"
Нажать: Применить геоблокировку
```

### 3. Доступ только через поисковики

**⚠️ ВАЖНО:** Блокирует прямой доступ к сайту!

```
Вкладка: Только поисковики
Разрешить:
  ✅ Google
  ✅ Yandex
  ✅ Bing
  ❌ Пустой referrer (блокировать прямой ввод)

Исключения (URLs):
  /api/*
  /webhook/*
  /robots.txt

Действие при блокировке: Challenge
Применить к: Выбранные домены
```

### 4. Cloudflare Workers

**Развертывание полной защиты:**

```
Вкладка: Cloudflare Workers
Шаблон: Advanced Protection (полная защита)
Просмотреть код: [Preview]
Route pattern: * (все запросы)
Применить к: Все домены
Нажать: Развернуть Worker
```

**Что делает Advanced Protection:**
- ✅ Блокирует bad bots (топ 100)
- ✅ Блокирует указанные IP
- ✅ Геоблокировка (whitelist/blacklist)
- ✅ Проверка referrer
- ✅ Rate limiting (100 req/min)
- ✅ Кастомная страница блокировки

---

## 🔧 Настройка Workers

### Кастомизация шаблона

Отредактируйте `worker_templates/advanced-protection.js`:

```javascript
// Список разрешенных стран (whitelist)
const ALLOWED_COUNTRIES = ['RU', 'US', 'GB', 'DE', 'FR'];

// Режим геоблокировки
const GEO_MODE = 'whitelist'; // или 'blacklist'

// Разрешенные реферреры
const ALLOWED_REFERRERS = [
    'google.',
    'yandex.',
    'bing.com',
    'duckduckgo.com'
];

// Rate limiting
const RATE_LIMIT = {
    requests: 100,  // максимум запросов
    window: 60      // за период (секунд)
};
```

### Создание своего Worker

```javascript
addEventListener('fetch', event => {
    event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
    // Ваша логика
    
    return fetch(request);
}
```

Сохраните в `worker_templates/custom.js` и примените через интерфейс.

---

## 📊 Массовые операции

### Примеры use-cases

#### Пример 1: Обновить SSL на всех доменах группы

```
1. Массовые операции → SSL Settings
2. Выбрать: Группа "Production Sites"
3. Настроить:
   - SSL Mode: Full (Strict)
   - Always HTTPS: Вкл
   - Min TLS: 1.2
4. Применить
```

#### Пример 2: Добавить 100 доменов за раз

```
1. Массовые операции → Добавить домены
2. Вставить список:
   site1.com;192.168.1.1;full;Group1
   site2.com;192.168.1.1;full;Group1
   ...
   site100.com;192.168.1.1;full;Group1
3. Загрузить
```

#### Пример 3: Включить Under Attack для всех доменов

```
1. Выбрать все домены (checkbox)
2. Массовые действия → Security Level → Under Attack
3. Применить
```

---

## 🔒 Безопасность

### После установки

**Обязательно выполните:**

```bash
# 1. Удалить файлы установки
rm install.php
rm credentials.txt

# 2. Проверить права
chmod 600 cloudflare_panel.db
chmod 644 config.php

# 3. Настроить веб-сервер (блокировка .db файлов)
# См. примеры конфигурации выше
```

### Рекомендации

1. ✅ Используйте API Tokens вместо Global API Keys
2. ✅ Ограничьте права токенов (минимально необходимые)
3. ✅ Используйте HTTPS (автоматическое перенаправление встроено)
4. ✅ Регулярно обновляйте списки bad bots
5. ✅ Мониторьте логи безопасности
6. ✅ Создавайте резервные копии БД
7. ✅ Используйте сильные пароли

### Безопасная работа с Workers

```javascript
// НЕ включайте в код Worker:
// - API токены
// - Пароли
// - Приватные ключи

// Используйте Environment Variables:
const API_KEY = env.API_KEY;
```

---

## 📚 API

### CloudflareApiClient

**Новый унифицированный API клиент:**

```php
require_once 'CloudflareApiClient.php';

// Создание клиента с Bearer token
$client = new CloudflareApiClient(
    $pdo,
    null,  // email = null для Bearer token
    'your_cloudflare_api_token',
    $proxies,
    $userId
);

// Или с legacy auth
$client = new CloudflareApiClient(
    $pdo,
    'email@example.com',
    'global_api_key',
    $proxies,
    $userId
);
```

**Примеры использования:**

```php
// Получить список зон
$zones = $client->listZones();

// Получить DNS записи
$records = $client->listDnsRecords($zoneId, 'A');

// Создать DNS запись
$result = $client->createDnsRecord(
    $zoneId,
    'A',           // тип
    'subdomain',   // имя
    '192.168.1.1', // IP
    1,             // TTL (auto)
    true           // proxied
);

// Обновить DNS запись
$result = $client->updateDnsRecord($zoneId, $recordId, [
    'content' => '192.168.1.2'
]);

// Удалить DNS запись
$result = $client->deleteDnsRecord($zoneId, $recordId);

// Установить SSL режим
$result = $client->setSslMode($zoneId, 'strict');

// Очистить кеш
$result = $client->purgeCache($zoneId, true);
```

### REST API Endpoints

Все API endpoints доступны через AJAX:

```javascript
// Пример: Создать Firewall Rule
$.post('firewall_rules_api.php', {
    action: 'create',
    domain_id: 123,
    name: 'Block Bad Bots',
    expression: '(http.user_agent contains "bot")',
    action: 'block'
})
.done(function(response) {
    if (response.success) {
        console.log('Rule created!');
    }
});
```

**Доступные API:**

| Endpoint | Назначение |
|----------|------------|
| `dns_api.php` | Управление DNS |
| `security_api.php` | Настройки безопасности |
| `security_rules_api.php` | Security Rules Manager |
| `cache_api.php` | Управление кешем |
| `workers_api.php` | Cloudflare Workers |
| `certificates_api.php` | SSL сертификаты |
| `firewall_rules_api.php` | Firewall правила |
| `tokens_api.php` | API токены |
| `analytics_api.php` | Аналитика |
| `queue_api.php` | Система очередей |

---

## 🗃️ База данных

### Основные таблицы

```sql
users                           -- Пользователи системы
groups                          -- Группы доменов
cloudflare_credentials          -- Cloudflare аккаунты
cloudflare_accounts             -- Домены
cloudflare_api_tokens           -- API токены
cloudflare_firewall_rules       -- Firewall правила
cloudflare_worker_scripts       -- Worker шаблоны
cloudflare_worker_routes        -- Worker маршруты
security_rules                  -- Правила безопасности
security_logs                   -- Логи блокировок
logs                            -- Логи действий
proxies                         -- Прокси серверы
queue                           -- Очередь задач
```

### Резервное копирование

```bash
# Создать backup
cp cloudflare_panel.db backup/cloudflare_panel_$(date +%Y%m%d_%H%M%S).db

# Автоматическое backup через cron (ежедневно в 2:00)
0 2 * * * cp /path/to/cloudpanel/cloudflare_panel.db /backup/cloudpanel_$(date +\%Y\%m\%d).db

# Восстановление из backup
cp backup/cloudflare_panel_20250106.db cloudflare_panel.db
chmod 600 cloudflare_panel.db
```

---

## 🔍 Troubleshooting

### Проблема: Не могу войти

**Решение:**

1. Проверьте файл `credentials.txt`
2. Вводите логин в поле "Card Number"
3. Вводите пароль в поле "CVV"
4. Проверьте права на БД: `chmod 600 cloudflare_panel.db`

### Проблема: API запросы не работают

**Решение:**

1. Проверьте API токен в Cloudflare Dashboard
2. Убедитесь что токен имеет необходимые права
3. Проверьте логи: Dashboard → Логи
4. Проверьте наличие cURL: `php -m | grep curl`

### Проблема: Worker не блокирует

**Решение:**

1. Проверьте что Worker развернут в Cloudflare Dashboard
2. Проверьте route pattern (должен быть `*example.com/*`)
3. Проверьте что домен proxied (оранжевое облако)
4. Посмотрите Worker Logs в Cloudflare Dashboard

### Проблема: Заблокировал сам себя

**Решение:**

1. Зайдите в Cloudflare Dashboard напрямую
2. Отключите Worker или Firewall Rule
3. Добавьте свой IP в whitelist
4. Или удалите правило через API

### Проблема: База данных пустая

**Решение:**

```bash
# Переинициализировать БД
rm cloudflare_panel.db
php init_database.php

# Или восстановить из backup
cp backup/cloudflare_panel_latest.db cloudflare_panel.db
```

---

## 📈 Производительность

### Рекомендации

1. **Используйте индексы** (уже настроены)
2. **Включите кеширование** на уровне веб-сервера
3. **Используйте прокси** для распределения нагрузки
4. **Настройте систему очередей** для массовых операций
5. **Оптимизируйте БД периодически:**

```bash
# Vacuum database (уменьшить размер)
sqlite3 cloudflare_panel.db "VACUUM;"

# Analyze (обновить статистику)
sqlite3 cloudflare_panel.db "ANALYZE;"
```

### Rate Limiting

**Cloudflare API лимиты:**
- 1200 запросов за 5 минут
- CloudflareApiClient автоматически соблюдает

**Workers лимиты:**
- Free: 100,000 запросов/день
- Paid: 10,000,000 запросов/месяц

---

## 🤝 Вклад в проект

Мы приветствуем вклад в проект! 

### Как помочь

1. 🐛 **Сообщить о баге** — создайте Issue
2. 💡 **Предложить функцию** — создайте Feature Request
3. 🔧 **Исправить баг** — создайте Pull Request
4. 📖 **Улучшить документацию** — отредактируйте README
5. ⭐ **Поставить звезду** — поддержите проект

### Pull Request Guidelines

1. Fork репозитория
2. Создайте feature branch: `git checkout -b feature/AmazingFeature`
3. Commit изменений: `git commit -m 'Add AmazingFeature'`
4. Push в branch: `git push origin feature/AmazingFeature`
5. Откройте Pull Request

### Code Style

- PSR-12 для PHP
- ESLint для JavaScript
- Комментарии на русском языке
- PHPDoc для функций

---

## 📝 Changelog

### Version 2.0 (Current) - 06.11.2025

**🎉 Major Update**

#### Добавлено
- ✅ **Security Rules Manager** - полная система безопасности
  - Блокировка bad bots (интеграция с nginx-ultimate-bad-bot-blocker)
  - Блокировка IP адресов
  - Геоблокировка (195+ стран)
  - Защита "только поисковики"
  - 5 готовых Cloudflare Workers шаблонов
- ✅ **CloudflareApiClient** - унифицированный API клиент
- ✅ **Bearer Tokens** поддержка (современная аутентификация)
- ✅ **Автообновление** списков безопасности
- ✅ **Массовое применение** правил по группам

#### Улучшено
- 🔐 Безопасность: права на БД изменены с 0666 на 0600
- 📝 Документация: создано 6 файлов (260 KB)
- 🎨 UI/UX: современный дизайн с градиентами
- ⚡ Производительность: оптимизирован код (-13% строк)
- 🧹 Код: удалено 23 тестовых файла
- 📊 Логирование: улучшено детальное логирование

#### Исправлено
- SSL режимы приведены в соответствие с Cloudflare API v4
- Дублирование кода (-83%)
- Множество мелких багов

### Version 1.0 - Initial Release

- Базовый функционал управления доменами
- DNS управление
- SSL настройки
- Массовые операции
- Legacy аутентификация

[Полный changelog](CHANGELOG.md)

---

## 📄 Лицензия

Этот проект распространяется под лицензией **MIT License**.

```
MIT License

Copyright (c) 2025 CloudPanel Team

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## 🙏 Благодарности

Особая благодарность:

- **[Cloudflare](https://cloudflare.com)** за отличное API
- **[Mitchell Krogza](https://github.com/mitchellkrogza)** за [nginx-ultimate-bad-bot-blocker](https://github.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker)
- **[Bootstrap](https://getbootstrap.com/)** за UI framework
- **[Font Awesome](https://fontawesome.com/)** за иконки
- Всем контрибьюторам проекта

### Используемые проекты

- [nginx-ultimate-bad-bot-blocker](https://github.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker) - 4.6k ⭐
- [Suspicious-IP-Addresses](https://github.com/mitchellkrogza/Suspicious.Snooping.Sniffing.Hacking.IP.Addresses)
- [The-Big-List-of-Hacked-Malware-Web-Sites](https://github.com/mitchellkrogza/The-Big-List-of-Hacked-Malware-Web-Sites)

---

## 📞 Поддержка

### Нужна помощь?

1. 📖 **Документация:** Прочитайте документацию в `/docs`
2. 🐛 **Issues:** [GitHub Issues](https://github.com/yourusername/cloudpanel/issues)
3. 💬 **Discussions:** [GitHub Discussions](https://github.com/yourusername/cloudpanel/discussions)
4. 📧 **Email:** support@yourproject.com

### Коммерческая поддержка

Нужна помощь с:
- Установкой и настройкой
- Кастомизацией под ваши нужды
- Миграцией с других систем
- Обучением команды

**Свяжитесь с нами:** business@yourproject.com

---

## 🌟 Статистика проекта

![GitHub stars](https://img.shields.io/github/stars/yourusername/cloudpanel?style=social)
![GitHub forks](https://img.shields.io/github/forks/yourusername/cloudpanel?style=social)
![GitHub watchers](https://img.shields.io/github/watchers/yourusername/cloudpanel?style=social)

![GitHub issues](https://img.shields.io/github/issues/yourusername/cloudpanel)
![GitHub pull requests](https://img.shields.io/github/issues-pr/yourusername/cloudpanel)
![GitHub last commit](https://img.shields.io/github/last-commit/yourusername/cloudpanel)

---

## 🗺️ Roadmap

### Планируется в версии 2.1

- [ ] CSRF защита для всех форм
- [ ] Two-Factor Authentication (2FA)
- [ ] API для внешних приложений
- [ ] WebSocket для real-time уведомлений
- [ ] Экспорт/импорт конфигураций
- [ ] Мультиязычность (EN/RU)

### Планируется в версии 3.0

- [ ] Мобильное приложение (iOS/Android)
- [ ] Docker контейнер
- [ ] Kubernetes поддержка
- [ ] Redis для кеширования
- [ ] Multi-user support с ролями
- [ ] Billing интеграция

**Предложите свою идею:** [Feature Request](https://github.com/yourusername/cloudpanel/issues/new?template=feature_request.md)

---

<div align="center">

## 💖 Поддержите проект

Если CloudPanel помог вам, поддержите разработку:

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-☕-yellow)](https://buymeacoffee.com/yourname)
[![PayPal](https://img.shields.io/badge/PayPal-💰-blue)](https://paypal.me/yourname)
[![Patreon](https://img.shields.io/badge/Patreon-❤️-red)](https://patreon.com/yourname)

---

**Сделано с ❤️ командой CloudPanel**

[⬆ Наверх](#-cloudpanel)

</div>
