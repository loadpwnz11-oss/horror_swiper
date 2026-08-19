<?php
/**
 * DarkDate - Chat API
 * Handles sending/receiving messages, spam protection, and bot interactions
 */

require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$user = validateApiRequest();
if (!$user) {
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        handleGetMessages($action, $user);
        break;
    
    case 'POST':
        handlePostMessage($action, $user);
        break;
    
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

/**
 * Handle GET requests
 */
function handleGetMessages($action, $user) {
    switch ($action) {
        case 'history':
            getChatHistory($user);
            break;
        
        case 'notifications':
            getNotifications($user);
            break;
        
        case 'status':
            getChatStatus($user);
            break;
        
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

/**
 * Handle POST requests
 */
function handlePostMessage($action, $user) {
    switch ($action) {
        case 'send':
            sendMessage($user);
            break;
        
        case 'read':
            markAsRead($user);
            break;
        
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

/**
 * Get chat history
 */
function getChatHistory($user) {
    $pdo = getDbConnection();
    $messagesTable = getTableName('messages');
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT id, sender_type, sender_id, message as content, timestamp, is_read, 'text' as message_type
        FROM $messagesTable
        WHERE user_id = ?
        ORDER BY timestamp DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$user['id'], $limit, $offset]);
    $messages = array_reverse($stmt->fetchAll());
    
    // Format messages
    foreach ($messages as &$msg) {
        $msg['timestamp_formatted'] = date('H:i', strtotime($msg['timestamp']));
        $msg['date_formatted'] = date('d.m.Y', strtotime($msg['timestamp']));
    }
    
    jsonResponse(['messages' => $messages]);
}

/**
 * Get notifications
 */
function getNotifications($user) {
    $pdo = getDbConnection();
    $notificationsTable = getTableName('notifications');
    $unreadOnly = isset($_GET['unread']);
    
    $query = "SELECT * FROM $notificationsTable WHERE user_id = ?";
    if ($unreadOnly) {
        $query .= " AND is_read = FALSE";
    }
    $query .= " ORDER BY created_at DESC LIMIT 20";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll();
    
    // Format notifications
    foreach ($notifications as &$notif) {
        $notif['created_at_formatted'] = date('d.m.Y H:i', strtotime($notif['created_at']));
    }
    
    jsonResponse(['notifications' => $notifications]);
}

/**
 * Get chat status (fear level, block status, etc.)
 */
function getChatStatus($user) {
    $pdo = getDbConnection();
    $usersTable = getTableName('users');
    $messagesTable = getTableName('messages');
    $notificationsTable = getTableName('notifications');
    
    // Get fresh user data
    $stmt = $pdo->prepare("SELECT fear_level, is_blocked, block_until FROM $usersTable WHERE id = ?");
    $stmt->execute([$user['id']]);
    $userData = $stmt->fetch();
    
    $blockRemaining = 0;
    if ($userData['is_blocked'] && $userData['block_until']) {
        $blockRemaining = max(0, strtotime($userData['block_until']) - time());
    }
    
    // Get unread counts
    $msgStmt = $pdo->prepare("SELECT COUNT(*) FROM $messagesTable WHERE user_id = ? AND is_read = FALSE");
    $msgStmt->execute([$user['id']]);
    $unreadMessages = $msgStmt->fetchColumn();
    
    $notifStmt = $pdo->prepare("SELECT COUNT(*) FROM $notificationsTable WHERE user_id = ? AND is_read = FALSE");
    $notifStmt->execute([$user['id']]);
    $unreadNotifications = $notifStmt->fetchColumn();
    
    // Get last message timestamp
    $lastMsgStmt = $pdo->prepare("SELECT MAX(timestamp) FROM $messagesTable WHERE user_id = ?");
    $lastMsgStmt->execute([$user['id']]);
    $lastMessageAt = $lastMsgStmt->fetchColumn();
    
    jsonResponse([
        'fear_level' => $userData['fear_level'],
        'is_blocked' => $userData['is_blocked'],
        'block_remaining' => $blockRemaining,
        'unread_messages' => $unreadMessages,
        'unread_notifications' => $unreadNotifications,
        'last_message_at' => $lastMessageAt
    ]);
}

/**
 * Send a message
 */
function sendMessage($user) {
    global $MAX_MESSAGES_PER_MINUTE;
    
    // Check if blocked
    if (isUserBlocked($user['id'])) {
        jsonResponse(['error' => 'You are temporarily blocked', 'blocked' => true], 403);
    }
    
    $content = trim($_POST['content'] ?? '');
    $messageType = $_POST['type'] ?? 'text';
    
    if (empty($content)) {
        jsonResponse(['error' => 'Message cannot be empty'], 400);
    }
    
    if (strlen($content) > 1000) {
        jsonResponse(['error' => 'Message too long (max 1000 characters)'], 400);
    }
    
    $pdo = getDbConnection();
    $messagesTable = getTableName('messages');
    
    // Spam detection: check message frequency
    $oneMinuteAgo = date('Y-m-d H:i:s', time() - 60);
    $spamStmt = $pdo->prepare("
        SELECT COUNT(*) FROM $messagesTable 
        WHERE user_id = ? AND timestamp > ? AND sender_type = 'user'
    ");
    $spamStmt->execute([$user['id'], $oneMinuteAgo]);
    $recentMessages = $spamStmt->fetchColumn();
    
    if ($recentMessages >= MAX_MESSAGES_PER_MINUTE) {
        // Block user for spam
        blockUser($user['id']);
        logUserAction($user['id'], 'spam_detected', ['message_count' => $recentMessages]);
        jsonResponse([
            'error' => 'Spam detected. You have been temporarily blocked.',
            'blocked' => true,
            'block_duration' => SPAM_BLOCK_DURATION
        ], 403);
    }
    
    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO $messagesTable (user_id, sender_type, sender_id, message, timestamp)
        VALUES (?, 'user', ?, ?, NOW())
    ");
    $stmt->execute([$user['id'], $user['username'], $content]);
    
    $messageId = $pdo->lastInsertId();
    
    // Update fear level based on message content (simple keyword detection)
    $fearKeywords = ['help', 'scared', 'afraid', 'danger', 'run', 'hide', 'death', 'die'];
    $fearTriggered = false;
    foreach ($fearKeywords as $keyword) {
        if (stripos($content, $keyword) !== false) {
            $fearTriggered = true;
            break;
        }
    }
    
    if ($fearTriggered) {
        $newFearLevel = updateFearLevel($user['id'], 5, 'scary_message_sent');
    }
    
    logUserAction($user['id'], 'message_sent', ['length' => strlen($content)]);
    
    // Simulate bot response after delay (in real implementation, use async queue)
    $botResponse = generateBotResponse($content, $user['id']);
    
    jsonResponse([
        'success' => true,
        'message_id' => $messageId,
        'fear_level' => $fearTriggered ? $newFearLevel : $user['fear_level'],
        'bot_response_pending' => $botResponse !== null
    ]);
}

/**
 * Mark messages as read
 */
function markAsRead($user) {
    $messageIds = json_decode($_POST['message_ids'] ?? '[]', true);
    
    if (!is_array($messageIds) || empty($messageIds)) {
        jsonResponse(['error' => 'No message IDs provided'], 400);
    }
    
    $pdo = getDbConnection();
    $messagesTable = getTableName('messages');
    
    // Prepare placeholders
    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
    
    $stmt = $pdo->prepare("
        UPDATE $messagesTable 
        SET is_read = TRUE 
        WHERE user_id = ? AND id IN ($placeholders)
    ");
    
    $params = array_merge([$user['id']], $messageIds);
    $stmt->execute($params);
    
    jsonResponse(['success' => true, 'marked_count' => $stmt->rowCount()]);
}

/**
 * Generate bot response based on user message
 * This is a simple implementation - can be expanded with AI or scripted responses
 */
function generateBotResponse($userMessage, $userId) {
    $pdo = getDbConnection();
    $messagesTable = getTableName('messages');
    $notificationsTable = getTableName('notifications');
    
    // Simple response logic
    $responses = [
        'hello' => 'Hello... I\'ve been waiting for you.',
        'hi' => 'Finally! I thought you\'d never come.',
        'help' => 'Help? There\'s no help here anymore...',
        'who' => 'Does it matter who I am?',
        'where' => 'Everywhere. Nowhere. Does it matter?',
    ];
    
    $userMessageLower = strtolower($userMessage);
    $response = null;
    
    foreach ($responses as $keyword => $reply) {
        if (strpos($userMessageLower, $keyword) !== false) {
            $response = $reply;
            break;
        }
    }
    
    // Random creepy response if no match
    if (!$response) {
        $creepyResponses = [
            'I see you...',
            'Why did you join this app?',
            'They\'re watching you too.',
            'Don\'t trust the screen.',
            'Look behind you.',
        ];
        $response = $creepyResponses[array_rand($creepyResponses)];
    }
    
    // Schedule bot response
    $delaySeconds = rand(3, 15);
    $responseTime = date('Y-m-d H:i:s', time() + $delaySeconds);
    
    $stmt = $pdo->prepare("
        INSERT INTO $messagesTable (user_id, sender_type, sender_id, message, timestamp)
        VALUES (?, 'bot', 'mystery_bot', ?, ?)
    ");
    $stmt->execute([$userId, $response, $responseTime]);
    
    // Create notification for delayed message
    $notifStmt = $pdo->prepare("
        INSERT INTO $notificationsTable (user_id, title, message, type)
        VALUES (?, 'New Message', 'You have a new message', 'chat')
    ");
    $notifStmt->execute([$userId]);
    
    return [
        'response' => $response,
        'delay' => $delaySeconds
    ];
}
