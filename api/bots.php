<?php
/**
 * DarkDate - Bots API
 * Handles bot behavior, message generation, and AI-like responses
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Bot personality configurations
 */
$BOT_PERSONALITIES = [
    'friendly' => [
        'greetings' => ['Привет! :)', 'Как дела?', 'Рад тебя видеть!', 'Хей!'],
        'responses' => ['Звучит интересно!', 'Расскажи подробнее', 'Я понимаю', 'Это круто!'],
        'emoji_style' => 'positive',
        'fear_reaction' => 'supportive'
    ],
    'mysterious' => [
        'greetings' => ['...', 'Ты здесь?', 'Я видел тебя...', 'Они не должны знать'],
        'responses' => ['Ты уверен?', 'Есть вещи, которые лучше не знать', 'Иногда я вижу сны...', 'Тишина говорит громче слов'],
        'emoji_style' => 'minimal',
        'fear_reaction' => 'enigmatic'
    ],
    'aggressive' => [
        'greetings' => ['Чего тебе?', 'Опять ты?', 'Не мешай мне', 'Что нужно?'],
        'responses' => ['Мне всё равно', 'Сам разбирайся', 'Не твоё дело', 'Заткнись'],
        'emoji_style' => 'negative',
        'fear_reaction' => 'predatory'
    ],
    'glitch' => [
        'greetings' => ['Пр..ивет', 'Т[ERROR] ты', 'Система... загрузка', '01001000 01001001'],
        'responses' => ['Данные повр[##]ждены', 'Я не могу... думать', 'Сигнал потерян', 'Перезагрузка...'],
        'emoji_style' => 'glitch',
        'fear_reaction' => 'malfunction'
    ],
    'spammer' => [
        'greetings' => ['!!!', 'СРОЧНО!!!', 'ТЫ ЗДЕСЬ???', 'ОТВЕТЬ НЕМЕДЛЕННО!!!'],
        'responses' => ['ОТВЕТЬ!!!', 'ГДЕ ТЫ???', '!!!', 'НЕ ИГНОРИРУЙ!!!'],
        'emoji_style' => 'chaotic',
        'fear_reaction' => 'overwhelming'
    ]
];

/**
 * Get all active bots from database
 * @return array List of bot configurations
 */
function getActiveBots() {
    $pdo = getDbConnection();
    $botsTable = getTableName('bots');
    
    $stmt = $pdo->prepare("SELECT * FROM $botsTable WHERE is_active = 1");
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * Get specific bot by key
 * @param string $botKey Bot identifier key
 * @return array|null Bot configuration or null if not found
 */
function getBotByKey($botKey) {
    $pdo = getDbConnection();
    $botsTable = getTableName('bots');
    
    $stmt = $pdo->prepare("SELECT * FROM $botsTable WHERE bot_key = ? AND is_active = 1");
    $stmt->execute([$botKey]);
    
    $bot = $stmt->fetch();
    return $bot ?: null;
}

/**
 * Generate bot response based on personality and context
 * @param array $bot Bot configuration
 * @param array $userMessage User's message data
 * @param int $userFearLevel Current user fear level
 * @return string Generated response
 */
function generateBotResponse($bot, $userMessage, $userFearLevel) {
    $personality = $bot['personality_type'];
    $config = $BOT_PERSONALITIES[$personality] ?? $BOT_PERSONALITIES['friendly'];
    
    // Check if fear level triggers special behavior
    if ($userFearLevel >= $bot['fear_trigger_threshold']) {
        return getFearTriggeredResponse($personality, $userFearLevel);
    }
    
    // Select random response from personality config
    $responses = $config['responses'];
    $response = $responses[array_rand($responses)];
    
    // Apply glitch effect for glitch bots
    if ($personality === 'glitch') {
        $response = applyGlitchEffect($response);
    }
    
    return $response;
}

/**
 * Get special response when user's fear level is high
 * @param string $personality Bot personality type
 * @param int $fearLevel Current fear level
 * @return string Fear-triggered response
 */
function getFearTriggeredResponse($personality, $fearLevel) {
    $fearResponses = [
        'friendly' => [
            'Тебе нехорошо? Я волнуюсь...',
            'Пожалуйста, успокойся. Всё будет хорошо.',
            'Я рядом. Дыши глубже.'
        ],
        'mysterious' => [
            'Ты чувствуешь это, да?',
            'Они приходят, когда ты боишься...',
            'Я предупреждал тебя...'
        ],
        'aggressive' => [
            'Слабак! Твой страх смешон.',
            'Бойся! Это правильно!',
            'Твой страх питает меня...'
        ],
        'glitch' => [
            'СТРАХ_ОБНАРУЖЕН [####]',
            'Система не может обраб[!!]отать твой страх',
            '01001001 01000110 01000101 01000101 01001100'
        ],
        'spammer' => [
            'БОИШЬСЯ??? БОИШЬСЯ???',
            '!!!!!!!!!!!!!!!!!',
            'НЕ СПИ НЕ СПИ НЕ СПИ!!!'
        ]
    ];
    
    $responses = $fearResponses[$personality] ?? ['...'];
    return $responses[array_rand($responses)];
}

/**
 * Apply glitch effect to text
 * @param string $text Original text
 * @return string Glitched text
 */
function applyGlitchEffect($text) {
    $glitchChars = ['[', ']', '#', '@', '!', '?', '*', '~', '^'];
    $result = '';
    
    for ($i = 0; $i < strlen($text); $i++) {
        if (rand(1, 100) <= 30) { // 30% chance to glitch each character
            $result .= $glitchChars[array_rand($glitchChars)];
        } else {
            $result .= $text[$i];
        }
    }
    
    return $result;
}

/**
 * Calculate delay before bot responds
 * @param array $bot Bot configuration
 * @return int Delay in seconds
 */
function calculateBotDelay($bot) {
    $minDelay = $bot['response_delay_min'] ?? 2;
    $maxDelay = $bot['response_delay_max'] ?? 10;
    
    return rand($minDelay, $maxDelay);
}

/**
 * Check if bot should be active at current hour
 * @param array $bot Bot configuration
 * @return bool True if bot should be active
 */
function isBotActiveNow($bot) {
    $currentHour = (int)date('H');
    $startHour = $bot['active_hours_start'] ?? 0;
    $endHour = $bot['active_hours_end'] ?? 23;
    
    if ($startHour <= $endHour) {
        return $currentHour >= $startHour && $currentHour <= $endHour;
    } else {
        // Night shift (e.g., 22:00 - 06:00)
        return $currentHour >= $startHour || $currentHour <= $endHour;
    }
}

/**
 * Schedule bot message for user
 * @param int $userId Target user ID
 * @param array $bot Bot configuration
 * @param string $message Message text
 * @param int $delaySeconds Delay before sending
 * @return bool Success status
 */
function scheduleBotMessage($userId, $bot, $message, $delaySeconds = 0) {
    $pdo = getDbConnection();
    $messagesTable = getTableName('messages');
    
    $sendTime = date('Y-m-d H:i:s', time() + $delaySeconds);
    
    $stmt = $pdo->prepare("
        INSERT INTO $messagesTable (user_id, sender_type, sender_id, message, timestamp)
        VALUES (?, 'bot', ?, ?, ?)
    ");
    
    return $stmt->execute([$userId, $bot['bot_key'], $message, $sendTime]);
}

/**
 * Send spam attack to user
 * @param int $userId Target user ID
 * @param array $bot Bot configuration
 * @param int $messageCount Number of messages to send
 * @return int Number of messages sent
 */
function sendSpamAttack($userId, $bot, $messageCount = 15) {
    $pdo = getDbConnection();
    $messagesTable = getTableName('messages');
    $spamLogsTable = getTableName('spam_logs');
    
    $spamMessages = [
        '!!!', 'ОТВЕТЬ!', 'ГДЕ ТЫ???', '!!!', 'НЕ ИГНОРИРУЙ',
        'ТЫ СЛЕДУЮЩИЙ', '!!!', 'Я ВИЖУ ТЕБЯ', 'БЕГИ!!!', '!!!'
    ];
    
    $messagesSent = 0;
    $currentTime = date('Y-m-d H:i:s');
    
    foreach (range(1, $messageCount) as $i) {
        $message = $spamMessages[array_rand($spamMessages)];
        $timestamp = date('Y-m-d H:i:s', time() + ($i * 0.3)); // 0.3 sec between messages
        
        $stmt = $pdo->prepare("
            INSERT INTO $messagesTable (user_id, sender_type, sender_id, message, timestamp)
            VALUES (?, 'bot', ?, ?, ?)
        ");
        
        if ($stmt->execute([$userId, $bot['bot_key'], $message, $timestamp])) {
            $messagesSent++;
        }
    }
    
    // Log spam attack
    $blockUntil = date('Y-m-d H:i:s', time() + SPAM_BLOCK_DURATION);
    $logStmt = $pdo->prepare("
        INSERT INTO $spamLogsTable (user_id, spam_count, last_spam_time, blocked_until, ip_address)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            spam_count = spam_count + VALUES(spam_count),
            last_spam_time = VALUES(last_spam_time),
            blocked_until = VALUES(blocked_until)
    ");
    
    $logStmt->execute([
        $userId, $messagesSent, $currentTime, $blockUntil, $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // Block user temporarily
    blockUser($userId, SPAM_BLOCK_DURATION);
    
    return $messagesSent;
}

/**
 * Send glitch message to user
 * @param int $userId Target user ID
 * @param array $bot Bot configuration
 * @param string $baseMessage Base message to glitch
 * @return bool Success status
 */
function sendGlitchMessage($userId, $bot, $baseMessage) {
    $pdo = getDbConnection();
    $messagesTable = getTableName('messages');
    
    $glitchedMessage = applyGlitchEffect($baseMessage);
    
    $stmt = $pdo->prepare("
        INSERT INTO $messagesTable (user_id, sender_type, sender_id, message)
        VALUES (?, 'bot', ?, ?)
    ");
    
    return $stmt->execute([$userId, $bot['bot_key'], $glitchedMessage]);
}

/**
 * Get bot greeting for first interaction
 * @param array $bot Bot configuration
 * @return string Greeting message
 */
function getBotGreeting($bot) {
    $personality = $bot['personality_type'];
    $config = $BOT_PERSONALITIES[$personality] ?? $BOT_PERSONALITIES['friendly'];
    
    $greetings = $config['greetings'];
    return $greetings[array_rand($greetings)];
}

/**
 * Process incoming user message and trigger bot responses
 * @param int $userId User who sent the message
 * @param string $messageText User's message text
 * @return array Response data with scheduled bot actions
 */
function processUserMessage($userId, $messageText) {
    $pdo = getDbConnection();
    $usersTable = getTableName('users');
    
    // Get user's fear level
    $userStmt = $pdo->prepare("SELECT fear_level FROM $usersTable WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    $fearLevel = $user['fear_level'] ?? 0;
    
    // Get active bots
    $bots = getActiveBots();
    $scheduledActions = [];
    
    foreach ($bots as $bot) {
        if (!isBotActiveNow($bot)) {
            continue;
        }
        
        // Determine if bot should respond based on frequency and randomness
        $shouldRespond = rand(1, 100) <= ($bot['message_frequency'] * 2); // 2x multiplier for better responsiveness
        
        if ($shouldRespond) {
            $delay = calculateBotDelay($bot);
            $response = generateBotResponse($bot, ['text' => $messageText], $fearLevel);
            
            $scheduledActions[] = [
                'bot_key' => $bot['bot_key'],
                'bot_name' => $bot['name'],
                'message' => $response,
                'delay_seconds' => $delay,
                'action_type' => 'response'
            ];
            
            // Schedule the message
            scheduleBotMessage($userId, $bot, $response, $delay);
        }
    }
    
    return [
        'user_fear_level' => $fearLevel,
        'scheduled_responses' => $scheduledActions
    ];
}

// API Endpoint Handler
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;

switch ($action) {
    case 'list':
        // List all active bots (admin function)
        $user = validateApiRequest();
        if (!$user) exit;
        
        $bots = getActiveBots();
        jsonResponse(['bots' => $bots]);
        break;
        
    case 'greeting':
        // Get bot greeting
        $user = validateApiRequest();
        if (!$user) exit;
        
        $botKey = $_GET['bot_key'] ?? 'alice';
        $bot = getBotByKey($botKey);
        
        if (!$bot) {
            jsonResponse(['error' => 'Bot not found'], 404);
        }
        
        $greeting = getBotGreeting($bot);
        jsonResponse(['greeting' => $greeting, 'bot' => $bot]);
        break;
        
    case 'respond':
        // Process user message and get bot responses
        $user = validateApiRequest();
        if (!$user) exit;
        
        $message = $_POST['message'] ?? '';
        if (empty($message)) {
            jsonResponse(['error' => 'Message required'], 400);
        }
        
        $result = processUserMessage($user['id'], $message);
        jsonResponse($result);
        break;
        
    case 'spam_attack':
        // Trigger spam attack (for testing/demo)
        $user = validateApiRequest();
        if (!$user) exit;
        
        $botKey = $_POST['bot_key'] ?? 'spammer';
        $bot = getBotByKey($botKey);
        
        if (!$bot || $bot['personality_type'] !== 'spammer') {
            jsonResponse(['error' => 'Invalid spammer bot'], 400);
        }
        
        $count = sendSpamAttack($user['id'], $bot, 15);
        jsonResponse(['messages_sent' => $count, 'blocked_duration' => SPAM_BLOCK_DURATION]);
        break;
        
    case 'glitch_message':
        // Send glitch message
        $user = validateApiRequest();
        if (!$user) exit;
        
        $botKey = $_POST['bot_key'] ?? 'glitch';
        $baseMessage = $_POST['message'] ?? 'System error detected';
        $bot = getBotByKey($botKey);
        
        if (!$bot) {
            jsonResponse(['error' => 'Bot not found'], 404);
        }
        
        $success = sendGlitchMessage($user['id'], $bot, $baseMessage);
        jsonResponse(['success' => $success]);
        break;
        
    default:
        jsonResponse(['error' => 'Invalid action', 'available_actions' => ['list', 'greeting', 'respond', 'spam_attack', 'glitch_message']], 400);
}
