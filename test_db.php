<?php
/**
 * Тестовый скрипт для проверки подключения к БД и наличия таблиц
 * Загрузите этот файл на хостинг и откройте в браузере: vash-site.ru/test_db.php
 * 
 * ВАЖНО: Перед загрузкой убедитесь, что в config/database.php прописаны верные данные!
 */

// Отключаем вывод ошибок в браузер (для безопасности), но включаем логирование
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DarkDate - Проверка БД</title>
    <style>
        body { font-family: 'Courier New', monospace; background: #000; color: #0f0; padding: 20px; }
        h1 { border-bottom: 1px solid #0f0; padding-bottom: 10px; }
        .success { color: #0f0; }
        .error { color: #f00; font-weight: bold; }
        .warning { color: #ff0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #111; }
        .status-ok { color: #0f0; }
        .status-missing { color: #f00; }
    </style>
</head>
<body>
    <h1>🕵️ DarkDate: Диагностика Базы Данных</h1>
    <p>Запуск проверки...</p>
    <hr>

    <?php
    // Подключаем конфиг
    if (!file_exists(__DIR__ . '/config/database.php')) {
        echo "<div class='error'>❌ Файл config/database.php не найден!</div>";
        exit;
    }
    
    require_once __DIR__ . '/config/database.php';

    echo "<h3>1. Проверка конфигурации:</h3>";
    echo "<ul>";
    echo "<li>Хост: " . htmlspecialchars(DB_HOST) . "</li>";
    echo "<li>База: " . htmlspecialchars(DB_NAME) . "</li>";
    echo "<li>Пользователь: " . htmlspecialchars(DB_USER) . "</li>";
    echo "<li>Префикс таблиц: " . (defined('DB_PREFIX') ? DB_PREFIX : 'не задан') . "</li>";
    echo "</ul>";

    echo "<h3>2. Попытка подключения:</h3>";
    
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        echo "<div class='success'>✅ Успешное подключение к базе данных <strong>" . htmlspecialchars(DB_NAME) . "</strong>!</div>";

        // Список ожидаемых таблиц
        $expected_tables = [
            'darkdate_users',
            'darkdate_sessions',
            'darkdate_messages',
            'darkdate_story_progress',
            'darkdate_notifications',
            'darkdate_fear_log',
            'darkdate_achievements',
            'darkdate_spam_logs'
        ];

        echo "<h3>3. Статус таблиц:</h3>";
        echo "<table><thead><tr><th>Таблица</th><th>Статус</th><th>Кол-во записей</th></tr></thead><tbody>";

        $existing_tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($expected_tables as $table) {
            $exists = in_array($table, $existing_tables);
            $count = $exists ? $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() : 0;
            
            $status_class = $exists ? 'status-ok' : 'status-missing';
            $status_icon = $exists ? '✅' : '❌';
            $status_text = $exists ? 'Существует' : 'Отсутствует';
            
            echo "<tr>";
            echo "<td>$table</td>";
            echo "<td class='$status_class'>$status_icon $status_text</td>";
            echo "<td>" . ($exists ? $count : '-') . "</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";

        if (count(array_diff($expected_tables, $existing_tables)) > 0) {
            echo "<div class='warning'>⚠️ Некоторые таблицы отсутствуют. Пожалуйста, импортируйте файл sql/schema.sql через phpMyAdmin.</div>";
        } else {
            echo "<div class='success'>🎉 Все необходимые таблицы найдены! База данных готова к работе.</div>";
        }

        // Тест записи (опционально, создадим тестовую сессию и удалим)
        echo "<h3>4. Тест записи/чтения:</h3>";
        try {
            $test_token = bin2hex(random_bytes(16));
            $test_user_id = 999999; // Временный ID
            
            // Пробуем вставить тестовую запись (если таблица sessions существует)
            if (in_array('darkdate_sessions', $existing_tables)) {
                $stmt = $pdo->prepare("INSERT INTO darkdate_sessions (user_id, token, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$test_user_id, $test_token]);
                
                $stmt = $pdo->prepare("SELECT id FROM darkdate_sessions WHERE token = ?");
                $stmt->execute([$test_token]);
                $result = $stmt->fetch();
                
                if ($result) {
                    // Удаляем тестовую запись
                    $delStmt = $pdo->prepare("DELETE FROM darkdate_sessions WHERE token = ?");
                    $delStmt->execute([$test_token]);
                    echo "<div class='success'>✅ Тест записи и чтения пройден успешно!</div>";
                } else {
                    echo "<div class='error'>❌ Не удалось прочитать тестовую запись.</div>";
                }
            } else {
                echo "<div class='warning'>⚠️ Пропущено (таблица sessions отсутствует).</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ Ошибка при тесте записи: " . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } catch (PDOException $e) {
        echo "<div class='error'>❌ Ошибка подключения к БД: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<div class='warning'>💡 Проверьте правильность логина/пароля в файле config/database.php и права доступа пользователя к базе.</div>";
    }
    ?>

    <hr>
    <p style="color: #555; font-size: 0.8em;">DarkDate System Check v1.0</p>
</body>
</html>
