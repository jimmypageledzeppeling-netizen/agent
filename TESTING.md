# Тестирование плагина Agent007

## Предварительные требования

1. WordPress активирован и доступен
2. Плагин agent007 активирован в админ-панели
3. REST API доступен (по умолчанию в WordPress 4.7+)

## Определение URL WordPress

### Способ 1: Проверить адрес в админ-панели
```
Settings → General → WordPress Address (URL)
```
Используйте этот адрес для всех API запросов.

### Способ 2: Проверить через wp-config.php
```php
// Если в wp-config.php определены:
define('WP_HOME', 'http://example.com');
define('WP_SITEURL', 'http://example.com');
```

### Способ 3: Проверить через базу данных
```sql
SELECT option_value FROM wp_options WHERE option_name = 'siteurl';
-- Вернет текущий URL сайта
```

## Проверка работоспособности REST API

### Тест 1: Проверить, доступен ли REST API
```bash
# Linux/macOS/Git Bash
curl http://your-site.local/wp-json/

# Или используя PowerShell
$response = Invoke-WebRequest -Uri "http://your-site.local/wp-json/"
$response.Content | ConvertFrom-Json
```

Ожидаемый ответ:
```json
{
  "name": "Your Site Name",
  "description": "...",
  "url": "http://your-site.local",
  "home": "http://your-site.local",
  "gmt_offset": 0,
  ...
}
```

### Тест 2: Проверить, доступен ли endpoint нашего плагина
```bash
curl http://your-site.local/wp-json/agent007/v1/

# Ожидаемый ответ:
# 404 Not Found (это нормально, т.к. этот путь не определен)
```

### Тест 3: Проверить, зарегистрирован ли маршрут /reg
```bash
# Вызвать неправильный метод (например, GET вместо POST)
curl -X GET http://your-site.local/wp-json/agent007/v1/reg

# Ожидаемый ответ: 405 Method Not Allowed
# Это означает, что маршрут зарегистрирован, но только для POST
```

## Полное тестирование API

### Сценарий 1: Валидный номер телефона

**curl:**
```bash
curl -X POST http://your-site.local/wp-json/agent007/v1/reg \
  -d "phone=79991234567" \
  -H "Content-Type: application/x-www-form-urlencoded"
```

**PowerShell:**
```powershell
$headers = @{ "Content-Type" = "application/x-www-form-urlencoded" }
$body = "phone=79991234567"
$response = Invoke-WebRequest -Uri "http://your-site.local/wp-json/agent007/v1/reg" `
  -Method POST -Headers $headers -Body $body
$response.Content | ConvertFrom-Json | Format-Table -AutoSize
```

**Ожидаемый ответ (200):**
```json
{
  "success": true,
  "key": "AbCdEfGhIj",
  "message": "Phone registered successfully"
}
```

### Сценарий 2: Невалидный номер телефона

```bash
curl -X POST http://your-site.local/wp-json/agent007/v1/reg \
  -d "phone=invalid" \
  -H "Content-Type: application/x-www-form-urlencoded"
```

**Ожидаемый ответ (400):**
```json
{
  "code": "invalid_phone",
  "message": "Phone number must contain only digits and be at least 10 characters long.",
  "data": {
    "status": 400
  }
}
```

### Сценарий 3: Повторная регистрация того же номера

```bash
# Первый запрос - успешно
curl -X POST http://your-site.local/wp-json/agent007/v1/reg \
  -d "phone=79991234567" \
  -H "Content-Type: application/x-www-form-urlencoded"

# Ожидаемый ответ (200): { "success": true, "key": "AbCdEfGhIj", ... }

# Второй запрос с тем же номером - должен вернуть НОВЫЙ ключ
curl -X POST http://your-site.local/wp-json/agent007/v1/reg \
  -d "phone=79991234567" \
  -H "Content-Type: application/x-www-form-urlencoded"

# Ожидаемый ответ (200): { "success": true, "key": "QwErTyUiOp", ... }
# Обратите внимание: ключ должен быть другим!
```

### Сценарий 4: Rate limiting (превышение лимита)

```bash
# Выполнить более 10 запросов подряд с одного IP
for i in {1..15}; do
  curl -X POST http://your-site.local/wp-json/agent007/v1/reg \
    -d "phone=7999123456$i" \
    -H "Content-Type: application/x-www-form-urlencoded"
  echo ""
done

# Запросы 1-10: успешно (200)
# Запросы 11-15: ошибка (429)
```

**Ожидаемый ответ для 11-го запроса (429):**
```json
{
  "code": "rate_limit_exceeded",
  "message": "Too many requests. Please try again later.",
  "data": {
    "status": 429
  }
}
```

## Проверка логирования

### Способ 1: Через админ-панель
```
Admin → Agent007 → Логирование
```
Просмотрите последние 100 строк лога.

### Способ 2: Прямое чтение файла
```bash
# Linux/macOS/Git Bash
tail -f /path/to/PROCESS.LOG

# Windows PowerShell
Get-Content "C:\WORK\WP\PROCESS.LOG" -Tail 20 -Wait
```

### Способ 3: Проверить содержимое лога после API запроса
```bash
# Выполнить API запрос
curl -X POST http://your-site.local/wp-json/agent007/v1/reg \
  -d "phone=79991234567" \
  -H "Content-Type: application/x-www-form-urlencoded"

# Прочитать последние строки лога
tail -n 5 /path/to/PROCESS.LOG
```

**Ожидаемый вывод в логе:**
```
[2026-10-19 14:32:10] [INFO] Target created - Phone: 79991234567, Key: AbCdEfGhIj [IP: 192.168.1.100]
[2026-10-19 14:32:10] [INFO] Phone registration successful - Phone: 79991234567, Key: AbCdEfGhIj, IP: 192.168.1.100
```

## Проверка таблиц БД

### Способ 1: phpMyAdmin
```
1. Откройте phpMyAdmin
2. Выберите базу данных WordPress
3. Посмотрите таблицы:
   - wp_agent007_targets
   - wp_agent007_accounts
```

### Способ 2: MySQL CLI
```bash
mysql -u wordpress_user -p wordpress_db

# Показать все таблицы плагина
SHOW TABLES LIKE 'wp_agent007%';

# Просмотреть структуру таблицы targets
DESCRIBE wp_agent007_targets;

# Просмотреть записи в targets
SELECT * FROM wp_agent007_targets;
```

### Способ 3: WordPress WP-CLI
```bash
# Показать все таблицы плагина
wp db tables | grep agent007

# Выполнить SQL запрос
wp db query "SELECT * FROM wp_agent007_targets;"
```

## Отладка проблем

### Проблема 1: 404 Not Found

**Причины:**
- WordPress не запущен на указанном адресе
- REST API отключен
- Плагин не активирован
- Маршруты .htaccess не работают

**Решение:**
```bash
# 1. Проверить URL WordPress
curl http://your-site.local/

# 2. Проверить REST API
curl http://your-site.local/wp-json/

# 3. Проверить активацию плагина в админке
# Admin → Plugins → Agent007 (должен быть "Deactivate")

# 4. Проверить .htaccess
cat /path/to/.htaccess
```

### Проблема 2: 500 Internal Server Error

**Причины:**
- Ошибка в коде плагина
- Проблема с БД
- Ошибка в PHP

**Решение:**
```bash
# 1. Проверить лог PHP ошибок
tail -f /var/log/php-errors.log

# 2. Проверить лог Apache/Nginx
tail -f /var/log/apache2/error.log

# 3. Запустить test-plugin.php для проверки целостности
php wp-content/plugins/agent007/test-plugin.php

# 4. Включить WP_DEBUG в wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

### Проблема 3: 405 Method Not Allowed

**Причина:**
Попытка использовать неправильный HTTP метод (например, GET вместо POST).

**Решение:**
```bash
# Убедитесь, что используется POST
curl -X POST http://your-site.local/wp-json/agent007/v1/reg ...
```

### Проблема 4: Rate limiting не работает

**Проверка:**
```bash
# Выполнить 15 запросов подряд
for i in {1..15}; do
  echo "Request $i:"
  curl -X POST http://your-site.local/wp-json/agent007/v1/reg \
    -d "phone=7999123456$i" \
    -H "Content-Type: application/x-www-form-urlencoded" -s | jq .
  sleep 0.1
done

# Запросы 11-15 должны вернуть 429
```

**Отладка:**
```php
// Добавить в class-rest-api.php для отладки:
error_log( 'Rate limit check for IP: ' . $ip );
error_log( 'Current request count: ' . $request_count );
```

## Чек-лист перед отправкой в production

- [ ] Плагин активирован в админке
- [ ] Таблицы созданы в БД
- [ ] REST API доступен и регистрирует маршрут `/agent007/v1/reg`
- [ ] Валидация номера телефона работает
- [ ] Генерация ключей работает
- [ ] Логирование включено и пишет в PROCESS.LOG
- [ ] Rate limiting работает (тестировано более 10 запросов)
- [ ] Повторные запросы с одним номером генерируют разные ключи
- [ ] Админ-панель доступна и показывает логи
- [ ] Безопасность: используются prepared statements
- [ ] Все ошибки логируются с IP-адресом

## Полезные команды для тестирования

### Bash/curl быстрое тестирование
```bash
#!/bin/bash
SITE_URL="http://your-site.local"

echo "Testing Agent007 Plugin..."
echo "=========================="

# Test 1: Valid phone
echo "Test 1: Valid phone"
curl -X POST $SITE_URL/wp-json/agent007/v1/reg \
  -d "phone=79991234567" \
  -H "Content-Type: application/x-www-form-urlencoded" -s | jq .

# Test 2: Invalid phone
echo "Test 2: Invalid phone"
curl -X POST $SITE_URL/wp-json/agent007/v1/reg \
  -d "phone=invalid" \
  -H "Content-Type: application/x-www-form-urlencoded" -s | jq .

# Test 3: Rate limiting
echo "Test 3: Rate limiting (11 requests)"
for i in {1..11}; do
  echo "Request $i:"
  curl -X POST $SITE_URL/wp-json/agent007/v1/reg \
    -d "phone=7999123456$i" \
    -H "Content-Type: application/x-www-form-urlencoded" -s | jq .code
done
```

### PowerShell быстрое тестирование
```powershell
$SiteUrl = "http://your-site.local"
$headers = @{ "Content-Type" = "application/x-www-form-urlencoded" }

Write-Host "Testing Agent007 Plugin..."
Write-Host "=========================="

# Test 1: Valid phone
Write-Host "Test 1: Valid phone"
$response = Invoke-WebRequest -Uri "$SiteUrl/wp-json/agent007/v1/reg" `
  -Method POST -Headers $headers -Body "phone=79991234567"
$response.Content | ConvertFrom-Json | Format-Table

# Test 2: Invalid phone
Write-Host "Test 2: Invalid phone"
try {
  $response = Invoke-WebRequest -Uri "$SiteUrl/wp-json/agent007/v1/reg" `
    -Method POST -Headers $headers -Body "phone=invalid"
} catch {
  $_.Exception.Response.StatusCode
  $_.ErrorDetails.Message | ConvertFrom-Json | Format-Table
}
```

## Дополнительная информация

- Лог файл: `PROCESS.LOG` в корне WordPress проекта
- Таблицы БД: `wp_agent007_targets`, `wp_agent007_accounts`
- Админ-панель: Admin → Agent007
- REST API: `/wp-json/agent007/v1/reg`
- Документация: `README.md` в папке плагина
