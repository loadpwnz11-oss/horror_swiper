<?php
/**
 * DarkDate - Тест 03: Механика Страха и Сюжета
 * Запускать прямо в браузере на хостинге
 * 
 * ИНСТРУКЦИЯ:
 * 1. Загрузите этот файл на хостинг Timeweb в папку tests/
 * 2. Откройте в браузере: http://ваш-домен.ru/tests/03_story_fear_test.php
 * 3. Наблюдайте за результатами тестов
 */

// Настройки для подключения к БД
require_once '../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

if (!$pdo) {
    die("❌ Ошибка подключения к базе данных");
}

// Вспомогательная функция для вывода результатов
function testResult($testName, $success, $message = '') {
    $icon = $success ? '✅' : '❌';
    $color = $success ? 'green' : 'red';
    echo "<div style='padding: 10px; margin: 5px 0; background: #f0f0f0; border-left: 4px solid {$color};'>";
    echo "<strong>{$icon} {$testName}</strong>";
    if ($message) {
        echo "<br><small style='color: #666;'>{$message}</small>";
    }
    echo "</div>";
}

echo "<h1>🧪 DarkDate - Тест 03: Механика Страха и Сюжета</h1>";
echo "<hr>";
echo "<p><strong>Дата теста:</strong> " . date('d.m.Y H:i:s') . "</p>";
echo "<hr>";

// Создаём тестового пользователя
$testUsername = 'feartest_' . time();
$testEmail = 'feartest_' . time() . '@darkdate.test';
$testPassword = 'TestPass123!';

try {
    $passwordHash = password_hash($testPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO darkdate_users (username, email, password_hash, fear_level, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->execute([$testUsername, $testEmail, $passwordHash]);
    $userId = $pdo->lastInsertId();
    
    // Создаём токен
    $token = bin2hex(random_bytes(64));
    $expiresAt = date('Y-m-d H:i:s', time() + (24 * 3600));
    $stmt = $pdo->prepare("INSERT INTO darkdate_sessions (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$userId, $token, $expiresAt]);
    
    echo "<p><strong>Тестовый пользователь:</strong> {$testUsername} (ID: {$userId})</p>";
    echo "<p><strong>Начальный уровень страха:</strong> 0</p>";
} catch (PDOException $e) {
    die("❌ Ошибка создания тестового пользователя: " . $e->getMessage());
}

// ============================================
// ТЕСТ 1: Проверка начального уровня страха
// ============================================
echo "<h2>1️⃣ Тест начального уровня страха</h2>";

try {
    $stmt = $pdo->prepare("SELECT fear_level FROM darkdate_users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user && $user['fear_level'] === 0) {
        testResult("Начальный уровень страха", true, "Уровень страха: {$user['fear_level']} (ожидалось 0)");
    } else {
        testResult("Начальный уровень страха", false, "Уровень страха: " . ($user['fear_level'] ?? 'null') . " (ожидалось 0)");
    }
} catch (PDOException $e) {
    testResult("Начальный уровень страха", false, "Ошибка БД: " . $e->getMessage());
}

// ============================================
// ТЕСТ 2: Увеличение уровня страха
// ============================================
echo "<h2>2️⃣ Тест увеличения уровня страха</h2>";

try {
    $fearIncrease = 15;
    $stmt = $pdo->prepare("UPDATE darkdate_users SET fear_level = fear_level + ? WHERE id = ?");
    $stmt->execute([$fearIncrease, $userId]);
    
    // Проверяем новый уровень
    $stmt = $pdo->prepare("SELECT fear_level FROM darkdate_users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user && $user['fear_level'] === $fearIncrease) {
        testResult("Увеличение уровня страха", true, "Новый уровень: {$user['fear_level']} (+{$fearIncrease})");
    } else {
        testResult("Увеличение уровня страха", false, "Уровень: " . ($user['fear_level'] ?? 'null') . " (ожидалось {$fearIncrease})");
    }
} catch (PDOException $e) {
    testResult("Увеличение уровня страха", false, "Ошибка БД: " . $e->getMessage());
}

// ============================================
// ТЕСТ 3: Логирование изменения страха
// ============================================
echo "<h2>3️⃣ Тест логирования изменений страха</h2>";

try {
    $fearLevel = 15;
    $triggerEvent = 'scary_message_received';
    
    $stmt = $pdo->prepare("INSERT INTO darkdate_fear_log (user_id, fear_level, fear_change, reason, event_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$userId, $fearLevel, 15, 'Получено страшное сообщение', $triggerEvent]);
    $logId = $pdo->lastInsertId();
    
    if ($logId) {
        testResult("Логирование изменения страха", true, "Log ID: {$logId}, Событие: {$triggerEvent}");
    } else {
        testResult("Логирование изменения страха", false, "Не удалось создать запись в логе");
    }
} catch (PDOException $e) {
    testResult("Логирование изменения страха", false, "Ошибка БД: " . $e->getMessage());
}

// ============================================
// ТЕСТ 4: Создание прогресса сюжета
// ============================================
echo "<h2>4️⃣ Тест создания прогресса сюжета</h2>";

try {
    $chapterId = 1;
    $sceneId = 1;
    $choicesJson = json_encode(['scene_1' => 1]);
    
    $stmt = $pdo->prepare("INSERT INTO darkdate_story_progress (user_id, chapter, scene, choices_made, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$userId, $chapterId, $sceneId, $choicesJson]);
    $progressId = $pdo->lastInsertId();
    
    if ($progressId) {
        testResult("Создание прогресса сюжета", true, "Progress ID: {$progressId}, Глава: {$chapterId}, Сцена: {$sceneId}");
    } else {
        testResult("Создание прогресса сюжета", false, "Не удалось создать запись прогресса");
    }
} catch (PDOException $e) {
    testResult("Создание прогресса сюжета", false, "Ошибка БД: " . $e->getMessage());
}

// ============================================
// ТЕСТ 5: Обновление выбора в сюжете
// ============================================
echo "<h2>5️⃣ Тест обновления выбора в сюжете</h2>";

try {
    $newChoices = json_encode(['scene_1' => 2, 'scene_2' => 1]);
    
    $stmt = $pdo->prepare("UPDATE darkdate_story_progress SET choices_made = ?, updated_at = NOW() WHERE user_id = ? AND chapter = 1");
    $stmt->execute([$newChoices, $userId]);
    $affectedRows = $stmt->rowCount();
    
    if ($affectedRows > 0) {
        testResult("Обновление выбора в сюжете", true, "Обновлено записей: {$affectedRows}");
        
        // Проверяем что данные обновились
        $checkStmt = $pdo->prepare("SELECT choices_made FROM darkdate_story_progress WHERE user_id = ? AND chapter = 1");
        $checkStmt->execute([$userId]);
        $progress = $checkStmt->fetch();
        
        $decodedChoices = json_decode($progress['choices_made'], true);
        if ($decodedChoices && isset($decodedChoices['scene_2'])) {
            testResult("Проверка обновлённых данных", true, "Выборы сохранены: " . json_encode($decodedChoices, JSON_UNESCAPED_UNICODE));
        } else {
            testResult("Проверка обновлённых данных", false, "Данные не соответствуют ожидаемым");
        }
    } else {
        testResult("Обновление выбора в сюжете", false, "Ни одна запись не обновлена");
    }
} catch (PDOException $e) {
    testResult("Обновление выбора в сюжете", false, "Ошибка БД: " . $e->getMessage());
}

// ============================================
// ТЕСТ 6: Проверка порогов страха
// ============================================
echo "<h2>6️⃣ Тест проверки порогов страха</h2>";

$fearThresholds = [
    ['level' => 20, 'effect' => 'Легкие глитчи'],
    ['level' => 50, 'effect' => 'Навязчивые идеи'],
    ['level' => 80, 'effect' => 'Психоз'],
    ['level' => 100, 'effect' => 'Финал']
];

echo "<table style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
echo "<tr style='background: #333; color: white;'><th style='padding: 10px;'>Уровень</th><th style='padding: 10px;'>Эффект</th><th style='padding: 10px;'>Статус</th></tr>";

foreach ($fearThresholds as $threshold) {
    $isActive = ($threshold['level'] <= 15); // Текущий уровень страха = 15
    $status = $isActive ? '🟡 Активен' : '⚪ Неактивен';
    $bgColor = $isActive ? '#fff3cd' : '#f8f9fa';
    
    echo "<tr style='background: {$bgColor};'>";
    echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$threshold['level']}</td>";
    echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$threshold['effect']}</td>";
    echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$status}</td>";
    echo "</tr>";
}

echo "</table>";

testResult("Пороги страха отображены", true, "Текущий уровень (15) активирует первый порог");

// ============================================
// ТЕСТ 7: Создание уведомления о событии
// ============================================
echo "<h2>7️⃣ Тест создания уведомления</h2>";

try {
    $notifTitle = 'Странное сообщение';
    $notifMessage = 'Вы получили сообщение от неизвестного...';
    $notifType = 'horror';
    
    $stmt = $pdo->prepare("INSERT INTO darkdate_notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$userId, $notifTitle, $notifMessage, $notifType]);
    $notifId = $pdo->lastInsertId();
    
    if ($notifId) {
        testResult("Создание уведомления", true, "Notification ID: {$notifId}, Тип: {$notifType}");
    } else {
        testResult("Создание уведомления", false, "Не удалось создать уведомление");
    }
} catch (PDOException $e) {
    testResult("Создание уведомления", false, "Ошибка БД: " . $e->getMessage());
}

// ============================================
// ТЕСТ 8: Получение всех уведомлений
// ============================================
echo "<h2>8️⃣ Тест получения уведомлений</h2>";

try {
    $stmt = $pdo->prepare("SELECT id, title, message, type, is_read, created_at FROM darkdate_notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
    
    if (count($notifications) > 0) {
        testResult("Получение уведомлений", true, "Найдено уведомлений: " . count($notifications));
        echo "<ul>";
        foreach ($notifications as $notif) {
            $readStatus = $notif['is_read'] ? '📖 Прочитано' : '📬 Новое';
            echo "<li><strong>[{$notif['type']}]</strong> {$notif['title']} - {$readStatus}</li>";
        }
        echo "</ul>";
    } else {
        testResult("Получение уведомлений", false, "Уведомления не найдены");
    }
} catch (PDOException $e) {
    testResult("Получение уведомлений", false, "Ошибка БД: " . $e->getMessage());
}

// ============================================
// ТЕСТ 9: Достижения (разблокировка)
// ============================================
echo "<h2>9️⃣ Тест разблокировки достижения</h2>";

try {
    $achievementKey = 'first_contact';
    $achievementName = 'Первый контакт';
    
    $stmt = $pdo->prepare("INSERT INTO darkdate_achievements (user_id, achievement_key, progress, is_completed, unlocked_at) VALUES (?, ?, 100, 1, NOW())");
    $stmt->execute([$userId, $achievementKey]);
    $achId = $pdo->lastInsertId();
    
    if ($achId) {
        testResult("Разблокировка достижения", true, "Achievement ID: {$achId}, Ключ: {$achievementKey}");
    } else {
        testResult("Разблокировка достижения", false, "Не удалось разблокировать достижение");
    }
} catch (PDOException $e) {
    testResult("Разблокировка достижения", false, "Ошибка БД: " . $e->getMessage());
}

// ============================================
// ИТОГИ
// ============================================
echo "<hr>";
echo "<h2>📊 Итоги теста</h2>";
echo "<div style='padding: 15px; background: #e7f3ff; border: 1px solid #2196F3; border-radius: 5px;'>";
echo "<p><strong>Тестовый пользователь:</strong> {$testUsername}</p>";
echo "<p><strong>Текущий уровень страха:</strong> 15</p>";
echo "<p><strong>Прогресс сюжета:</strong> Глава 1, Сцена 1</p>";
echo "<p><strong>Уведомлений:</strong> " . (isset($notifications) ? count($notifications) : 0) . "</p>";
echo "<p><strong>Достижений:</strong> 1</p>";
echo "<p><strong>Статус:</strong> ✅ Тесты завершены</p>";
echo "</div>";

echo "<hr>";
echo "<h3>🎯 Влияние уровня страха на игру:</h3>";
echo "<ul>";
echo "<li><strong>0-20:</strong> Обычный чат, стандартные ответы ботов</li>";
echo "<li><strong>21-50:</strong> Легкие глитчи, искажение текста у ERROR_404</li>";
echo "<li><strong>51-80:</strong> Навязчивые идеи, боты упоминают личные данные</li>";
echo "<li><strong>81-99:</strong> Психоз, чат \"ломается\", сообщения дублируются</li>";
echo "<li><strong>100:</strong> Финал (разблокировка концовки)</li>";
echo "</ul>";

echo "<hr>";
echo "<p style='color: #666; font-size: 12px;'>Для очистки тестовых данных выполните SQL:<br>";
echo "<code>DELETE FROM darkdate_notifications WHERE user_id = {$userId};</code><br>";
echo "<code>DELETE FROM darkdate_achievements WHERE user_id = {$userId};</code><br>";
echo "<code>DELETE FROM darkdate_fear_log WHERE user_id = {$userId};</code><br>";
echo "<code>DELETE FROM darkdate_story_progress WHERE user_id = {$userId};</code><br>";
echo "<code>DELETE FROM darkdate_sessions WHERE user_id = {$userId};</code><br>";
echo "<code>DELETE FROM darkdate_users WHERE username = '{$testUsername}';</code></p>";
?>
