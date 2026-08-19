<?php
/**
 * DarkDate - Тест 01: Регистрация и Авторизация
 * Запускать прямо в браузере на хостинге
 * 
 * ИНСТРУКЦИЯ:
 * 1. Загрузите этот файл на хостинг Timeweb в папку tests/
 * 2. Откройте в браузере: http://ваш-домен.ru/tests/01_auth_test.php
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

// Генерация уникального имени для теста
$testUsername = 'testuser_' . time();
$testEmail = 'test_' . time() . '@darkdate.test';
$testPassword = 'TestPass123!';

echo "<h1>🧪 DarkDate - Тест 01: Регистрация и Авторизация</h1>";
echo "<hr>";
echo "<p><strong>Дата теста:</strong> " . date('d.m.Y H:i:s') . "</p>";
echo "<p><strong>Тестовый пользователь:</strong> {$testUsername}</p>";
echo "<hr>";

$token = null;
$userId = null;

// ============================================
// ТЕСТ 1: Регистрация нового пользователя
// ============================================
echo "<h2>1️⃣ Тест регистрации</h2>";

try {
    $usersTable = 'darkdate_users';
    
    // Хэшируем пароль
    $passwordHash = password_hash($testPassword, PASSWORD_DEFAULT);
    
    // Вставляем пользователя
    $stmt = $pdo->prepare("INSERT INTO $usersTable (username, email, password_hash, created_at) VALUES (?, ?, ?, NOW())");
    $result = $stmt->execute([$testUsername, $testEmail, $passwordHash]);
    
    if ($result) {
        $userId = $pdo->lastInsertId();
        testResult("Регистрация пользователя", true, "User ID: {$userId}, Username: {$testUsername}");
    } else {
        testResult("Регистрация пользователя", false, "Не удалось вставить пользователя в БД");
    }
} catch (PDOException $e) {
    testResult("Регистрация пользователя", false, "Ошибка БД: " . $e->getMessage());
}

// ============================================
// ТЕСТ 2: Создание сессии (токен)
// ============================================
echo "<h2>2️⃣ Тест создания сессии</h2>";

if ($userId) {
    try {
        $sessionsTable = 'darkdate_sessions';
        
        // Генерируем токен
        $token = bin2hex(random_bytes(64));
        $expiresAt = date('Y-m-d H:i:s', time() + (24 * 3600)); // 24 часа
        
        $stmt = $pdo->prepare("INSERT INTO $sessionsTable (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
        $result = $stmt->execute([$userId, $token, $expiresAt]);
        
        if ($result) {
            testResult("Создание сессии", true, "Токен: " . substr($token, 0, 20) . "...");
        } else {
            testResult("Создание сессии", false, "Не удалось создать сессию");
        }
    } catch (PDOException $e) {
        testResult("Создание сессии", false, "Ошибка БД: " . $e->getMessage());
    }
} else {
    testResult("Создание сессии", false, "Пропущено: нет userId");
}

// ============================================
// ТЕСТ 3: Проверка токена (валидация)
// ============================================
echo "<h2>3️⃣ Тест валидации токена</h2>";

if ($token && $userId) {
    try {
        $stmt = $pdo->prepare("SELECT u.* FROM darkdate_users u JOIN darkdate_sessions s ON u.id = s.user_id WHERE s.token = ? AND s.expires_at > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user && $user['id'] == $userId) {
            testResult("Валидация токена", true, "Пользователь успешно найден: " . $user['username']);
        } else {
            testResult("Валидация токена", false, "Токен недействителен или истёк");
        }
    } catch (PDOException $e) {
        testResult("Валидация токена", false, "Ошибка БД: " . $e->getMessage());
    }
} else {
    testResult("Валидация токена", false, "Пропущено: нет токена");
}

// ============================================
// ТЕСТ 4: Логин (проверка пароля)
// ============================================
echo "<h2>4️⃣ Тест логина</h2>";

if ($userId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM darkdate_users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($testPassword, $user['password_hash'])) {
            testResult("Логин с паролем", true, "Пароль успешно проверен");
        } else {
            testResult("Логин с паролем", false, "Неверный пароль или пользователь не найден");
        }
    } catch (PDOException $e) {
        testResult("Логин с паролем", false, "Ошибка БД: " . $e->getMessage());
    }
} else {
    testResult("Логин с паролем", false, "Пропущено: нет userId");
}

// ============================================
// ТЕСТ 5: Logout (удаление сессии)
// ============================================
echo "<h2>5️⃣ Тест выхода (logout)</h2>";

if ($token) {
    try {
        $stmt = $pdo->prepare("DELETE FROM darkdate_sessions WHERE token = ?");
        $result = $stmt->execute([$token]);
        
        if ($result) {
            // Проверяем что сессия удалена
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM darkdate_sessions WHERE token = ?");
            $checkStmt->execute([$token]);
            $count = $checkStmt->fetchColumn();
            
            if ($count == 0) {
                testResult("Logout (удаление сессии)", true, "Сессия успешно удалена");
            } else {
                testResult("Logout (удаление сессии)", false, "Сессия не была удалена");
            }
        } else {
            testResult("Logout (удаление сессии)", false, "Ошибка при удалении сессии");
        }
    } catch (PDOException $e) {
        testResult("Logout (удаление сессии)", false, "Ошибка БД: " . $e->getMessage());
    }
} else {
    testResult("Logout (удаление сессии)", false, "Пропущено: нет токена");
}

// ============================================
// ИТОГИ
// ============================================
echo "<hr>";
echo "<h2>📊 Итоги теста</h2>";
echo "<div style='padding: 15px; background: #e7f3ff; border: 1px solid #2196F3; border-radius: 5px;'>";
echo "<p><strong>Тестовое имя пользователя:</strong> {$testUsername}</p>";
echo "<p><strong>Тестовый email:</strong> {$testEmail}</p>";
echo "<p><strong>ID пользователя:</strong> " . ($userId ?? 'не создан') . "</p>";
echo "<p><strong>Статус:</strong> " . ($userId ? '✅ Тесты завершены' : '❌ Тесты прерваны') . "</p>";
echo "</div>";

echo "<hr>";
echo "<p style='color: #666; font-size: 12px;'>Для очистки тестовых данных выполните SQL:<br>";
echo "<code>DELETE FROM darkdate_sessions WHERE user_id = {$userId};</code><br>";
echo "<code>DELETE FROM darkdate_users WHERE username = '{$testUsername}';</code></p>";
?>
