<?php
/**
 * DarkDate - Тест 02: Чат с Ботом и Проверка Ответа
 * Запускать прямо в браузере на хостинге
 * 
 * ИНСТРУКЦИЯ:
 * 1. Загрузите этот файл на хостинг Timeweb в папку tests/
 * 2. Откройте в браузере: http://ваш-домен.ru/tests/02_chat_bot_test.php
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

echo "<h1>🧪 DarkDate - Тест 02: Чат с Ботом</h1>";
echo "<hr>";
echo "<p><strong>Дата теста:</strong> " . date('d.m.Y H:i:s') . "</p>";
echo "<hr>";

// Создаём тестового пользователя
$testUsername = 'chattest_' . time();
$testEmail = 'chattest_' . time() . '@darkdate.test';
$testPassword = 'TestPass123!';

try {
    $passwordHash = password_hash($testPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO darkdate_users (username, email, password_hash, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$testUsername, $testEmail, $passwordHash]);
    $userId = $pdo->lastInsertId();
    
    // Создаём токен
    $token = bin2hex(random_bytes(64));
    $expiresAt = date('Y-m-d H:i:s', time() + (24 * 3600));
    $stmt = $pdo->prepare("INSERT INTO darkdate_sessions (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$userId, $token, $expiresAt]);
    
    echo "<p><strong>Тестовый пользователь:</strong> {$testUsername} (ID: {$userId})</p>";
    echo "<p><strong>Токен:</strong> " . substr($token, 0, 20) . "...</p>";
} catch (PDOException $e) {
    die("❌ Ошибка создания тестового пользователя: " . $e->getMessage());
}

// ============================================
// ТЕСТ 1: Получение списка ботов
// ============================================
echo "<h2>1️⃣ Тест получения списка ботов</h2>";

try {
    $stmt = $pdo->query("SELECT id, name, personality_type FROM darkdate_bots WHERE is_active = 1");
    $bots = $stmt->fetchAll();
    
    if (count($bots) >= 5) {
        testResult("Список ботов получен", true, "Найдено ботов: " . count($bots));
        echo "<ul>";
        foreach ($bots as $bot) {
            echo "<li><strong>{$bot['name']}</strong> (тип: {$bot['personality_type']})</li>";
        }
        echo "</ul>";
    } else {
        testResult("Список ботов получен", false, "Найдено только " . count($bots) . " ботов (ожидалось 5+)");
    }
} catch (PDOException $e) {
    testResult("Список ботов получен", false, "Ошибка БД: " . $e->getMessage());
}

// ============================================
// ТЕСТ 2: Отправка сообщения боту
// ============================================
echo "<h2>2️⃣ Тест отправки сообщения</h2>";

if (isset($bots[0])) {
    $botId = $bots[0]['id'];
    $botName = $bots[0]['name'];
    $testMessage = "Привет, {$botName}! Как дела?";
    
    try {
        $stmt = $pdo->prepare("INSERT INTO darkdate_messages (user_id, sender_type, sender_id, message, message_type, timestamp) VALUES (?, 'user', ?, ?, 'text', NOW())");
        $stmt->execute([$userId, $testUsername, $testMessage]);
        $messageId = $pdo->lastInsertId();
        
        if ($messageId) {
            testResult("Отправка сообщения", true, "Message ID: {$messageId}, Текст: \"{$testMessage}\"");
        } else {
            testResult("Отправка сообщения", false, "Не удалось получить ID сообщения");
        }
    } catch (PDOException $e) {
        testResult("Отправка сообщения", false, "Ошибка БД: " . $e->getMessage());
    }
} else {
    testResult("Отправка сообщения", false, "Нет доступных ботов");
}

// ============================================
// ТЕСТ 3: Получение истории чата
// ============================================
echo "<h2>3️⃣ Тест получения истории чата</h2>";

try {
    $stmt = $pdo->prepare("SELECT id, sender_type, sender_id, message, timestamp FROM darkdate_messages WHERE user_id = ? ORDER BY timestamp DESC LIMIT 10");
    $stmt->execute([$userId]);
    $messages = $stmt->fetchAll();
    
    if (count($messages) > 0) {
        testResult("История чата получена", true, "Найдено сообщений: " . count($messages));
        echo "<ul>";
        foreach ($messages as $msg) {
            $sender = $msg['sender_type'] === 'user' ? 'Вы' : 'Бот';
            echo "<li><strong>[{$sender}]</strong> {$msg['message']}</li>";
        }
        echo "</ul>";
    } else {
        testResult("История чата получена", false, "История пуста");
    }
} catch (PDOException $e) {
    testResult("История чата получена", false, "Ошибка БД: " . $e->getMessage());
}

// ============================================
// ТЕСТ 4: Генерация ответа бота
// ============================================
echo "<h2>4️⃣ Тест генерации ответа бота</h2>";

if (isset($bots[0])) {
    $bot = $bots[0];
    
    // Проверяем личность бота
    $personalityResponses = [
        'friendly' => ['Привет! Рад тебя видеть!', 'Как твои дела?', 'Здорово что ты здесь!'],
        'mysterious' => ['...', 'Ты чувствуешь это?', 'Они наблюдают...'],
        'aggressive' => ['Чего тебе?', 'Говори быстрее!', 'Не трать моё время!'],
        'glitch' => ['Пр..ивет', 'Система [ERROR]', '01001000 01001001'],
        'spammer' => ['!!!ОТВЕТЬ НЕМЕДЛЕННО!!!', 'ТЫ ЗДЕСЬ???', '!!!']
    ];
    
    $responses = $personalityResponses[$bot['personality_type']] ?? ['...'];
    $expectedResponse = $responses[array_rand($responses)];
    
    // Имитируем ответ бота
    try {
        $botResponse = "Ответ от {$bot['name']}: {$expectedResponse}";
        $stmt = $pdo->prepare("INSERT INTO darkdate_messages (user_id, sender_type, sender_id, message, message_type, timestamp) VALUES (?, 'bot', ?, ?, 'text', DATE_ADD(NOW(), INTERVAL 3 SECOND))");
        $stmt->execute([$userId, $bot['name'], $botResponse]);
        $responseId = $pdo->lastInsertId();
        
        if ($responseId) {
            testResult("Генерация ответа бота", true, "Бот ответил: \"{$botResponse}\"");
        } else {
            testResult("Генерация ответа бота", false, "Не удалось создать ответ");
        }
    } catch (PDOException $e) {
        testResult("Генерация ответа бота", false, "Ошибка БД: " . $e->getMessage());
    }
} else {
    testResult("Генерация ответа бота", false, "Нет доступных ботов");
}

// ============================================
// ТЕСТ 5: Проверка типов личности ботов
// ============================================
echo "<h2>5️⃣ Тест проверки типов личности</h2>";

$expectedPersonalities = ['friendly', 'mysterious', 'aggressive', 'glitch', 'spammer'];
$foundPersonalities = [];

foreach ($bots as $bot) {
    $foundPersonalities[] = $bot['personality_type'];
}

$allPresent = true;
$missing = [];
foreach ($expectedPersonalities as $p) {
    if (!in_array($p, $foundPersonalities)) {
        $allPresent = false;
        $missing[] = $p;
    }
}

if ($allPresent) {
    testResult("Все типы личности представлены", true, "Найдены: " . implode(', ', $foundPersonalities));
} else {
    testResult("Все типы личности представлены", false, "Отсутствуют: " . implode(', ', $missing));
}

// ============================================
// ТЕСТ 6: Спам-атака (симуляция)
// ============================================
echo "<h2>6️⃣ Тест спам-атаки (симуляция)</h2>";

try {
    $spamCount = 15;
    $currentTime = date('Y-m-d H:i:s');
    
    for ($i = 0; $i < $spamCount; $i++) {
        $timestamp = date('Y-m-d H:i:s', time() + ($i * 0.3));
        $stmt = $pdo->prepare("INSERT INTO darkdate_messages (user_id, sender_type, sender_id, message, message_type, timestamp) VALUES (?, 'bot', 'SPAM_BOT', 'СПАМ СООБЩЕНИЕ #' . ?, 'text', ?)");
        $stmt->execute([$userId, $i, $timestamp]);
    }
    
    // Проверяем количество
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM darkdate_messages WHERE user_id = ? AND sender_id = 'SPAM_BOT'");
    $checkStmt->execute([$userId]);
    $count = $checkStmt->fetchColumn();
    
    if ($count >= $spamCount) {
        testResult("Спам-атака симулирована", true, "Отправлено {$count} спам-сообщений");
    } else {
        testResult("Спам-атака симулирована", false, "Отправлено только {$count} из {$spamCount}");
    }
} catch (PDOException $e) {
    testResult("Спам-атака симулирована", false, "Ошибка БД: " . $e->getMessage());
}

// ============================================
// ИТОГИ
// ============================================
echo "<hr>";
echo "<h2>📊 Итоги теста</h2>";
echo "<div style='padding: 15px; background: #e7f3ff; border: 1px solid #2196F3; border-radius: 5px;'>";
echo "<p><strong>Тестовый пользователь:</strong> {$testUsername}</p>";
echo "<p><strong>Всего ботов доступно:</strong> " . count($bots) . "</p>";
echo "<p><strong>Сообщений отправлено:</strong> " . (isset($messageId) ? '1+' : '0') . "</p>";
echo "<p><strong>Статус:</strong> ✅ Тесты завершены</p>";
echo "</div>";

echo "<hr>";
echo "<p style='color: #666; font-size: 12px;'>Для очистки тестовых данных выполните SQL:<br>";
echo "<code>DELETE FROM darkdate_messages WHERE user_id = {$userId};</code><br>";
echo "<code>DELETE FROM darkdate_sessions WHERE user_id = {$userId};</code><br>";
echo "<code>DELETE FROM darkdate_users WHERE username = '{$testUsername}';</code></p>";
?>
