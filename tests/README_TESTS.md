# 🧪 DarkDate: Руководство по тестированию

## 📋 Содержание
1. [Быстрый старт](#быстрый-старт)
2. [Автоматические тесты](#автоматические-тесты)
3. [Ручное тестирование через cURL](#ручное-тестирование-через-curl)
4. [Тестирование через Postman](#тестирование-через-postman)
5. [Проверка контента](#проверка-контента)

---

## 🚀 Быстрый старт

### Предварительные требования
- ✅ MySQL сервер запущен
- ✅ PHP 7.4+ установлен
- ✅ База данных `darkdate` создана
- ✅ Файлы API размещены в `/api/`

### Шаг 1: Инициализация БД
```bash
mysql -u root -p darkdate < sql/darkdate_schema.sql
mysql -u root -p darkdate < sql/bots_seed.sql
```

### Шаг 2: Запуск тестов
```bash
chmod +x tests/test_api.sh
./tests/test_api.sh
```

---

## 🤖 Автоматические тесты

### Скрипт `test_api.sh`
**Расположение:** `/workspace/tests/test_api.sh`

**Что проверяет:**
| № | Тест | Описание |
|---|------|----------|
| 1 | Регистрация | Создание нового пользователя |
| 2 | Авторизация | Получение session token |
| 3 | Список ботов | Получение всех 5 ботов |
| 4 | Отправка сообщения | Сообщение боту #1 |
| 5 | История чата | Проверка сохранения сообщений |
| 6 | Прогресс сюжета | Уровень страха и прогресс |
| 7 | Выбор варианта | Влияние на сюжет |
| 8 | Уведомления | Проверка системы уведомлений |
| 9 | Защита | Отказ в доступе с неверным токеном |
| 10 | Logout | Корректный выход |

**Запуск:**
```bash
cd /workspace
chmod +x tests/test_api.sh
./tests/test_api.sh
```

**Ожидаемый результат:**
```
🚀 Запуск тестов DarkDate API...
================================
✓ PASS: Регистрация успешна
✓ PASS: Авторизация успешна
✓ PASS: Список ботов получен
✓ PASS: Сообщение отправлено
✓ PASS: История чата получена
✓ PASS: Прогресс сюжета получен
✓ PASS: Выбор принят
✓ PASS: Уведомления получены
✓ PASS: Защита работает (доступ запрещен)
✓ PASS: Выход выполнен успешно
================================
✅ Тестирование завершено!
```

---

## 📟 Ручное тестирование через cURL

### 1. Регистрация
```bash
curl -X POST http://localhost/darkdate/api/auth.php \
  -H "Content-Type: application/json" \
  -d '{"action":"register","email":"test@darkdate.com","password":"Pass123!","username":"Tester"}'
```

### 2. Логин
```bash
curl -X POST http://localhost/darkdate/api/auth.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","email":"test@darkdate.com","password":"Pass123!"}'
```
*Сохраните `token` из ответа для следующих запросов*

### 3. Получить список ботов
```bash
curl -X GET "http://localhost/darkdate/api/bots.php?action=list"
```

### 4. Отправить сообщение
```bash
curl -X POST http://localhost/darkdate/api/chat.php \
  -H "Content-Type: application/json" \
  -d '{"action":"send","bot_id":1,"message":"Привет!","session_token":"ВАШ_ТОКЕН"}'
```

### 5. Получить историю чата
```bash
curl -X GET "http://localhost/darkdate/api/chat.php?action=history&bot_id=1&session_token=ВАШ_ТОКЕН"
```

### 6. Проверить прогресс сюжета
```bash
curl -X GET "http://localhost/darkdate/api/story.php?action=progress&session_token=ВАШ_ТОКЕН"
```

### 7. Сделать выбор в сюжете
```bash
curl -X POST http://localhost/darkdate/api/story.php \
  -H "Content-Type: application/json" \
  -d '{"action":"choice","choice_id":1,"session_token":"ВАШ_ТОКЕН"}'
```

### 8. Получить уведомления
```bash
curl -X GET "http://localhost/darkdate/api/chat.php?action=notifications&session_token=ВАШ_ТОКЕН"
```

### 9. Выйти
```bash
curl -X POST http://localhost/darkdate/api/auth.php \
  -H "Content-Type: application/json" \
  -d '{"action":"logout","session_token":"ВАШ_ТОКЕН"}'
```

---

## 🔧 Тестирование через Postman

### Коллекция запросов

Создайте коллекцию **DarkDate API** со следующими запросами:

#### 1. Register (POST)
- **URL:** `{{base_url}}/auth.php`
- **Body (raw JSON):**
```json
{
  "action": "register",
  "email": "postman@darkdate.com",
  "password": "Test123!",
  "username": "PostmanUser"
}
```

#### 2. Login (POST)
- **URL:** `{{base_url}}/auth.php`
- **Body (raw JSON):**
```json
{
  "action": "login",
  "email": "postman@darkdate.com",
  "password": "Test123!"
}
```
*Сохраните токен в переменную окружения Postman: `pm.environment.set("session_token", response.token);`*

#### 3. Get Bots (GET)
- **URL:** `{{base_url}}/bots.php?action=list`

#### 4. Send Message (POST)
- **URL:** `{{base_url}}/chat.php`
- **Body (raw JSON):**
```json
{
  "action": "send",
  "bot_id": 1,
  "message": "Hello from Postman!",
  "session_token": "{{session_token}}"
}
```

#### 5. Get Progress (GET)
- **URL:** `{{base_url}}/story.php?action=progress&session_token={{session_token}}`

---

## 📚 Проверка контента

### Файл контент-плана
**Расположение:** `/workspace/docs/CONTENT_PLAN.md`

**Что содержит:**
- 🎭 3 ветки сюжета (Доверие, Паранойя, Взлом)
- 💬 20+ фраз для 5 ботов
- ⚡ 5 уровней событий по страху
- 🎯 3 секретных триггера

### Как проверить контент в БД

#### 1. Проверить ботов
```sql
SELECT id, name, personality, description 
FROM darkdate_bots;
```

#### 2. Проверить структуру таблиц
```sql
DESCRIBE darkdate_users;
DESCRIBE darkdate_messages;
DESCRIBE darkdate_story_progress;
```

#### 3. Проверить начальные данные
```sql
SELECT COUNT(*) as bot_count FROM darkdate_bots;
-- Ожидаемо: 5 ботов
```

---

## 🐛 Отладка

### Частые ошибки

| Ошибка | Причина | Решение |
|--------|---------|---------|
| `Connection refused` | MySQL не запущен | `sudo service mysql start` |
| `404 Not Found` | Неверный путь к API | Проверьте `BASE_URL` в тестах |
| `Access denied` | Неверные credentials БД | Проверьте `config/database.php` |
| `Invalid token` | Токен истек | Залогиньтесь заново |

### Логи
- **PHP ошибки:** `/var/log/php/error.log`
- **MySQL логи:** `/var/log/mysql/error.log`
- **Apache/Nginx:** `/var/log/apache2/error.log`

---

## 📊 Чек-лист готовности

- [ ] База данных создана
- [ ] Таблицы импортированы
- [ ] Боты загружены (5 шт)
- [ ] API файлы на месте
- [ ] Автоматические тесты проходят (10/10)
- [ ] Ручные тесты работают
- [ ] Контент проверен

**Статус:** Готово к интеграции с фронтендом! 🎉
