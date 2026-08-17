# DarkDate - Phase 2 Backend Setup Guide

## Шаг 1: Архитектура Бэкенда и База Данных ✅

### Структура проекта:
```
/workspace
├── api/
│   ├── auth.php       # Регистрация, вход, сессии
│   ├── chat.php       # Чат, сообщения, уведомления
│   └── story.php      # Сюжет, главы, выборы
├── config/
│   └── database.php   # Конфигурация БД и общие функции
├── sql/
│   └── schema.sql     # Схема базы данных
├── logs/              # Логи действий пользователей
└── assets/            # Ресурсы (CSS, JS, аудио, изображения)
```

### Установка базы данных:

1. **Создайте базу данных MySQL:**
```bash
mysql -u root -p < /workspace/sql/schema.sql
```

2. **Настройте подключение:**
Откройте `/workspace/config/database.php` и измените:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'darkdate');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('API_SECRET_KEY', 'generate_secure_random_string_here');
```

### Ключевые функции бэкенда:

#### 🔐 Система безопасности:
- Хэширование паролей (bcrypt)
- Токены сессий с истечением срока
- Защита от SQL-инъекций (prepared statements)
- Блокировка за спам (10 секунд)

#### 😨 Динамический "Уровень Страха":
- Диапазон: 0–100
- Влияет на сюжет и события
- Логирование всех изменений
- Автоматическое снижение со временем

#### 📊 Логирование:
- Все действия пользователя записываются в `logs/user_actions.log`
- JSON формат для удобного анализа
- IP-адреса, временные метки, метаданные

### API Endpoints:

#### **Auth API** (`/api/auth.php`)
| Action | Method | Parameters | Description |
|--------|--------|------------|-------------|
| `register` | POST | username, password, email | Регистрация нового пользователя |
| `login` | POST | username, password | Вход в систему |
| `logout` | POST | token (header) | Выход из системы |

#### **Chat API** (`/api/chat.php`)
| Action | Method | Parameters | Description |
|--------|--------|------------|-------------|
| `history` | GET | limit, offset | Получить историю чата |
| `notifications` | GET | unread (optional) | Получить уведомления |
| `status` | GET | — | Статус чата (fear level, блокировки) |
| `send` | POST | content, type | Отправить сообщение |
| `read` | POST | message_ids | Отметить сообщения как прочитанные |

#### **Story API** (`/api/story.php`)
| Action | Method | Parameters | Description |
|--------|--------|------------|-------------|
| `progress` | GET | — | Прогресс сюжета пользователя |
| `chapter` | GET | chapter_id, scene_id | Получить главу/сцену |
| `chapters` | GET | — | Все доступные главы |
| `choice` | POST | chapter_id, scene_id, choice_id | Сделать выбор в сюжете |
| `start` | POST | chapter_id | Начать новую главу |

### Примеры запросов:

#### Регистрация:
```javascript
fetch('/api/auth.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=register&username=testuser&password=securepass123'
});
```

#### Отправка сообщения:
```javascript
fetch('/api/chat.php?action=send', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Authorization': 'Bearer YOUR_TOKEN'
    },
    body: 'content=Hello...'
});
```

#### Получение истории чата:
```javascript
fetch('/api/chat.php?action=history&limit=50', {
    headers: {'Authorization': 'Bearer YOUR_TOKEN'}
});
```

### Таблицы базы данных:

1. **users** — Пользователи (уровень страха, блокировки)
2. **messages** — Сообщения чата (текст, глитчи, спам)
3. **story_progress** — Прогресс сюжета (выборы, главы)
4. **notifications** — Уведомления (обычные, хоррор, фейковые)
5. **fear_log** — История изменений уровня страха
6. **achievements** — Достижения игроков
7. **sessions** — Активные сессии (токены)

### Следующие шаги:

✅ **Шаг 1 завершён!** Переходим к **Шагу 2: Движок Чата и Обучение**.

В следующем шаге мы создадим:
- Frontend интерфейс чата
- Интеграцию с первой сюжетной главой
- Систему выборов реплик
- Эмуляцию "живого" времени ответов

---

**Статус Фазы 2:** 1/6 шагов выполнено (16.7%)
