# Agent007 - Управление аккаунтами ТГ через уникальные коды

## Описание

WordPress плагин для регистрации номеров телефонов с генерацией уникальных кодов доступа (ключей). Поддерживает REST API, систему логирования и админ-интерфейс для управления.

## Требования

- WordPress 5.0+
- PHP 7.2+
- MySQL 5.7+ или MariaDB 10.0+

## Установка

1. Поместить папку `agent007` в `wp-content/plugins/`
2. Активировать плагин в администраторской панели WordPress
3. При активации автоматически создаются необходимые таблицы БД

## Возможности

### 1. REST API Endpoint

**URL:** `/wp-json/agent007/v1/reg`  
**Метод:** POST  
**Параметры:**
- `phone` (обязательный) - номер телефона только из цифр (без +, пробелов, скобок). Минимум 10 символов.

**Пример запроса:**
```bash
curl -X POST http://127.0.0.1:8080/wp-json/agent007/v1/reg \
  -d "phone=79991234567" \
  -H "Content-Type: application/x-www-form-urlencoded"
```

**Успешный ответ (200):**
```json
{
  "success": true,
  "key": "AbCdEfGhIj",
  "message": "Phone registered successfully"
}
```

**Ошибка валидации (400):**
```json
{
  "code": "invalid_phone",
  "message": "Phone number must contain only digits and be at least 10 characters long.",
  "data": {
    "status": 400
  }
}
```

**Превышен лимит запросов (429):**
```json
{
  "code": "rate_limit_exceeded",
  "message": "Too many requests. Please try again later.",
  "data": {
    "status": 429
  }
}
```

### 1a. Ловушка join (этап 2)

**URL:** `/join/{key}` (ключ — 10 латинских букв, регистр важен)  
Запасной вариант без постоянных ссылок: `/?agent007_join={key}`

Поиск ключа в `targets`. Ответ зависит от запроса:
- браузер (GET) → HTML-страница с номером или «Не найден»;
- `POST`, заголовок `Accept: application/json` или `?format=json` → JSON.

**JSON-ответ:**
```json
{ "status": 200, "response": "79991234567", "id": 13 }
```
Коды: `200` — найден (`response` = номер, `id` = ID записи `targets`); `400` — ключ некорректный; `404` — не найден (`response` = «Не найден», `id` = null); `429` — больше 30 запросов в минуту с одного IP; `503` — ошибка БД. HTTP-код ответа совпадает с `status`.

Пример:
```bash
curl -X POST http://127.0.0.1:8080/join/AbCdEfGhIj
```

### 2. Система логирования

Все операции логируются в файл `PROCESS.LOG` в корне проекта:
```
[2026-10-19 14:30:45] [INFO] Plugin activated successfully [IP: 192.168.1.1]
[2026-10-19 14:31:22] [INFO] Database tables created successfully
[2026-10-19 14:32:10] [INFO] Target created - Phone: 79991234567, Key: AbCdEfGhIj [IP: 192.168.1.100]
[2026-10-19 14:33:00] [WARNING] Rate limit exceeded for IP: 192.168.1.100
[2026-10-19 14:35:15] [ERROR] Failed to create target - Phone: 79991234567, Error: Duplicate entry
```

**Функции логирования:**
- Автоматическое логирование при активации/деактивации плагина
- Логирование всех операций БД (создание, обновление)
- Логирование всех REST API запросов с IP-адресом клиента
- Включение/выключение логирования через админ-панель

### 3. Админ-панель

**Меню:** Admin → Agent007 → Логирование

**Функции:**
- Просмотр последних 100 строк лога в real-time
- Кнопка "Очистить лог" - удаление всех логов
- Кнопка "Скачать лог" - экспорт PROCESS.LOG
- Переключатель "Enable Logging" - включение/выключение логирования

## Таблицы БД

### wp_agent007_targets
Таблица для хранения зарегистрированных номеров телефонов:

| Поле | Тип | Описание |
|------|-----|---------|
| ID | BIGINT | Primary Key |
| phone | VARCHAR(20) | Номер телефона (не уникальный: повторная регистрация создает новую запись с новым ключом) |
| key | VARCHAR(10) | Сгенерированный ключ доступа |
| status | TINYINT | Статус: 0=ожидание, 1=открыто, 2=завершено |
| access_path | VARCHAR(255) | Путь доступа (зарезервировано) |
| created_at | DATETIME | Время создания |
| updated_at | DATETIME | Время обновления |

### wp_agent007_accounts
Таблица для хранения связанных аккаунтов:

| Поле | Тип | Описание |
|------|-----|---------|
| ID | BIGINT | Primary Key |
| phone | VARCHAR(20) | Номер телефона |
| status | TINYINT | Статус аккаунта: 1=активен, 0=неактивен |
| target_id | BIGINT | Foreign Key на targets.ID |
| created_at | DATETIME | Время создания |
| updated_at | DATETIME | Время обновления |

## Безопасность

### Реализованные меры:
- ✅ Валидация формата номера телефона (только цифры, 10+ символов)
- ✅ Защита от SQL injection (используются prepared statements)
- ✅ Rate limiting: максимум 10 запросов в минуту с одного IP
- ✅ Все входные данные санитизируются и валидируются
- ✅ Проверка прав доступа в админ-панели (`manage_options`)
- ✅ Логирование всех запросов с IP-адресом
- ✅ SOLID принципы проектирования и Design Patterns

## Тестирование

### 1. Проверка активации плагина
```php
// В админ-панели WordPress:
// Plugins → Agent007 → Activate
```

### 2. Проверка создания таблиц
```sql
-- phpMyAdmin или MySQL CLI
SHOW TABLES LIKE 'wp_agent007%';
-- Должны вывести: wp_agent007_targets, wp_agent007_accounts
```

### 3. Тестирование REST API (PowerShell)
```powershell
$headers = @{ "Content-Type" = "application/x-www-form-urlencoded" }
$body = "phone=79991234567"
$response = Invoke-WebRequest -Uri "http://your-site.local/wp-json/agent007/v1/reg" `
  -Method POST -Headers $headers -Body $body
$response.Content | ConvertFrom-Json
```

### 4. Проверка логирования
```bash
# Просмотр в админ-панели: Agent007 → Логирование
# Или прямое чтение файла:
tail -f PROCESS.LOG
```

### 5. Тестирование rate limiting
```powershell
# Выполнить более 10 запросов с одного IP в течение 1 минуты
# Ожидаемо получить ошибку 429 (Too Many Requests)
```

## Архитектура

```
agent007/
├── agent007.php              # Точка входа, определение констант
├── includes/
│   ├── class-plugin.php      # Главный класс, управление хуками
│   ├── class-database.php    # Управление БД, CRUD операции
│   ├── class-rest-api.php    # REST API handler
│   └── class-logger.php      # Система логирования
├── admin/
│   ├── class-admin.php       # Админ-интерфейс
│   └── views/
│       ├── dashboard.php     # Главная страница
│       └── logging.php       # Страница логирования
├── assets/
│   ├── css/admin.css         # Стили админки
│   └── js/admin.js           # Скрипты админки
├── logs/                     # Директория для логов
└── .gitignore                # Git исключения
```

## SOLID и Design Patterns

- **Single Responsibility**: каждый класс отвечает за одну функцию
- **Dependency Injection**: передача зависимостей через конструктор
- **Service Locator**: доступ к сервисам через главный класс Plugin
- **Data Mapper**: отображение объектов БД через класс Database
- **Observer Pattern**: использование WordPress хуков

## Версионирование

Плагин использует Git для версионирования:
```bash
cd wp-content/plugins/agent007
git log                    # Просмотр истории коммитов
git status                 # Статус репозитория
```

## Лицензия

GPL v2 или выше (как требует WordPress)

## Разработка

**Автор:** Development Team  
**Email:** dev@agent007.local  
**GitHub:** https://github.com/jimmypageledzeppeling-netizen/agent

## Changelog

### v1.0.0 (2026-10-19)
- ✅ Начальная версия
- ✅ Таблицы БД (targets, accounts)
- ✅ REST API endpoint /reg
- ✅ Система логирования
- ✅ Админ-интерфейс
