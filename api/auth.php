<?php
/**
 * DarkDate - Authentication API
 * Handles user registration, login, and session management
 */

require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'register':
        handleRegister();
        break;
    
    case 'login':
        handleLogin();
        break;
    
    case 'logout':
        handleLogout();
        break;
    
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

/**
 * Handle user registration
 */
function handleRegister() {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    
    // Validation
    if (strlen($username) < 3 || strlen($username) > 50) {
        jsonResponse(['error' => 'Username must be between 3 and 50 characters'], 400);
    }
    
    if (strlen($password) < 6) {
        jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
    }
    
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Invalid email address'], 400);
    }
    
    $pdo = getDbConnection();
    $usersTable = getTableName('users');
    $sessionsTable = getTableName('sessions');
    
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM $usersTable WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Username already taken'], 409);
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $insertStmt = $pdo->prepare("
        INSERT INTO $usersTable (username, password_hash, email, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    
    try {
        $insertStmt->execute([$username, $passwordHash, $email]);
        $userId = $pdo->lastInsertId();
        
        logUserAction($userId, 'user_registered', ['email' => $email]);
        
        // Create session token
        $token = generateToken();
        $expiresAt = date('Y-m-d H:i:s', time() + (SESSION_EXPIRY_HOURS * 3600));
        
        $sessionStmt = $pdo->prepare("
            INSERT INTO $sessionsTable (user_id, token, expires_at)
            VALUES (?, ?, ?)
        ");
        $sessionStmt->execute([$userId, $token, $expiresAt]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Registration successful',
            'token' => $token,
            'user' => [
                'id' => $userId,
                'username' => $username,
                'email' => $email
            ]
        ]);
        
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        jsonResponse(['error' => 'Registration failed'], 500);
    }
}

/**
 * Handle user login
 */
function handleLogin() {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        jsonResponse(['error' => 'Username and password are required'], 400);
    }
    
    $pdo = getDbConnection();
    $usersTable = getTableName('users');
    $sessionsTable = getTableName('sessions');
    
    // Get user
    $stmt = $pdo->prepare("SELECT * FROM $usersTable WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['error' => 'Invalid credentials'], 401);
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        logUserAction($user['id'], 'failed_login_attempt', []);
        jsonResponse(['error' => 'Invalid credentials'], 401);
    }
    
    // Check if user is blocked
    if (isUserBlocked($user['id'])) {
        jsonResponse(['error' => 'Account is temporarily blocked'], 403);
    }
    
    // Update last login
    $updateStmt = $pdo->prepare("UPDATE $usersTable SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    
    // Create session token
    $token = generateToken();
    $expiresAt = date('Y-m-d H:i:s', time() + (SESSION_EXPIRY_HOURS * 3600));
    
    $sessionStmt = $pdo->prepare("
        INSERT INTO $sessionsTable (user_id, token, expires_at)
        VALUES (?, ?, ?)
    ");
    $sessionStmt->execute([$user['id'], $token, $expiresAt]);
    
    logUserAction($user['id'], 'user_logged_in', []);
    
    jsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'fear_level' => $user['fear_level']
        ]
    ]);
}

/**
 * Handle user logout
 */
function handleLogout() {
    $user = validateApiRequest();
    
    if (!$user) {
        return; // validateApiRequest already sent response
    }
    
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    
    $pdo = getDbConnection();
    $sessionsTable = getTableName('sessions');
    $stmt = $pdo->prepare("DELETE FROM $sessionsTable WHERE token = ?");
    $stmt->execute([$token]);
    
    logUserAction($user['id'], 'user_logged_out', []);
    
    jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
}
