<?php
/**
 * DarkDate - Database Configuration
 * Phase 2: Backend Setup
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'darkdate');
define('DB_USER', 'root'); // Change in production!
define('DB_PASS', '');     // Change in production!
define('DB_CHARSET', 'utf8mb4');

// API Security
define('API_SECRET_KEY', 'CHANGE_THIS_TO_A_SECURE_RANDOM_STRING_IN_PRODUCTION');
define('SESSION_EXPIRY_HOURS', 24);

// Fear System Settings
define('MAX_FEAR_LEVEL', 100);
define('FEAR_DECAY_RATE', 5); // Fear points lost per hour

// Spam Protection
define('SPAM_BLOCK_DURATION', 10); // seconds
define('MAX_MESSAGES_PER_MINUTE', 10);

// Error Reporting (disable in production!)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

/**
 * Get PDO database connection
 * @return PDO Database connection
 * @throws PDOException
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    
    return $pdo;
}

/**
 * Generate secure random token
 * @param int $length Token length
 * @return string
 */
function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Log user action for analytics
 * @param int $userId
 * @param string $action
 * @param array $metadata
 */
function logUserAction($userId, $action, $metadata = []) {
    $logFile = __DIR__ . '/../logs/user_actions.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = json_encode([
        'timestamp' => $timestamp,
        'user_id' => $userId,
        'action' => $action,
        'metadata' => $metadata,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]) . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Update fear level for user
 * @param int $userId
 * @param int $change Fear level change (positive or negative)
 * @param string $triggerEvent
 */
function updateFearLevel($userId, $change, $triggerEvent) {
    $pdo = getDbConnection();
    
    // Get current fear level
    $stmt = $pdo->prepare("SELECT fear_level FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        $newFearLevel = max(0, min(MAX_FEAR_LEVEL, $user['fear_level'] + $change));
        
        // Update user fear level
        $updateStmt = $pdo->prepare("UPDATE users SET fear_level = ? WHERE id = ?");
        $updateStmt->execute([$newFearLevel, $userId]);
        
        // Log fear change
        $logStmt = $pdo->prepare("INSERT INTO fear_log (user_id, fear_level, trigger_event) VALUES (?, ?, ?)");
        $logStmt->execute([$userId, $newFearLevel, $triggerEvent]);
        
        return $newFearLevel;
    }
    
    return 0;
}

/**
 * Check if user is currently blocked due to spam
 * @param int $userId
 * @return bool
 */
function isUserBlocked($userId) {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("SELECT is_blocked, block_until FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user && $user['is_blocked']) {
        if ($user['block_until'] && strtotime($user['block_until']) > time()) {
            return true;
        } else {
            // Unblock user
            $unblockStmt = $pdo->prepare("UPDATE users SET is_blocked = FALSE, block_until = NULL WHERE id = ?");
            $unblockStmt->execute([$userId]);
            return false;
        }
    }
    
    return false;
}

/**
 * Block user for spam
 * @param int $userId
 * @param int $duration Duration in seconds
 */
function blockUser($userId, $duration = SPAM_BLOCK_DURATION) {
    $pdo = getDbConnection();
    
    $blockUntil = date('Y-m-d H:i:s', time() + $duration);
    
    $stmt = $pdo->prepare("UPDATE users SET is_blocked = TRUE, block_until = ? WHERE id = ?");
    $stmt->execute([$blockUntil, $userId]);
    
    logUserAction($userId, 'blocked_for_spam', ['duration' => $duration, 'block_until' => $blockUntil]);
}

/**
 * Validate API request
 * @return array|false User data or false if invalid
 */
function validateApiRequest() {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? $_GET['token'] ?? null;
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return false;
    }
    
    // Remove 'Bearer ' prefix if present
    $token = str_replace('Bearer ', '', $token);
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT u.* FROM users u
        JOIN sessions s ON u.id = s.user_id
        WHERE s.token = ? AND s.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        return false;
    }
    
    if (isUserBlocked($user['id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'You are temporarily blocked due to suspicious activity']);
        return false;
    }
    
    return $user;
}

/**
 * Send JSON response
 * @param mixed $data
 * @param int $statusCode
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get all headers from request
 * @return array
 */
function getallheaders() {
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
    }
    return $headers;
}
