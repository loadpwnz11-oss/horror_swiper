<?php
/**
 * DarkDate - Story & Chapter API
 * Handles story progression, choices, and chapter unlocking
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
        handleGetStory($action, $user);
        break;
    
    case 'POST':
        handlePostStory($action, $user);
        break;
    
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

/**
 * Handle GET requests
 */
function handleGetStory($action, $user) {
    switch ($action) {
        case 'progress':
            getStoryProgress($user);
            break;
        
        case 'chapter':
            getChapter($user);
            break;
        
        case 'chapters':
            getAllChapters($user);
            break;
        
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

/**
 * Handle POST requests
 */
function handlePostStory($action, $user) {
    switch ($action) {
        case 'choice':
            makeChoice($user);
            break;
        
        case 'start':
            startChapter($user);
            break;
        
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

/**
 * Get user's story progress
 */
function getStoryProgress($user) {
    $pdo = getDbConnection();
    $storyProgressTable = getTableName('story_progress');
    
    $stmt = $pdo->prepare("
        SELECT chapter, scene, choices_made, completed_at
        FROM $storyProgressTable
        WHERE user_id = ?
        ORDER BY chapter ASC
    ");
    $stmt->execute([$user['id']]);
    $progress = $stmt->fetchAll();
    
    // Parse JSON choices
    foreach ($progress as &$prog) {
        $prog['choices_made'] = json_decode($prog['choices_made'], true);
        // Map to expected format
        $prog['chapter_id'] = $prog['chapter'];
        $prog['scene_id'] = $prog['scene'];
    }
    
    // Get current fear level impact on story
    $fearImpact = getFearImpact($user['fear_level']);
    
    jsonResponse([
        'progress' => $progress,
        'current_chapter' => !empty($progress) ? end($progress)['chapter'] : 1,
        'fear_impact' => $fearImpact
    ]);
}

/**
 * Get specific chapter content
 */
function getChapter($user) {
    $chapterId = (int)($_GET['chapter_id'] ?? 1);
    $sceneId = (int)($_GET['scene_id'] ?? 1);
    
    // Check if chapter is unlocked
    if (!isChapterUnlocked($user['id'], $chapterId)) {
        jsonResponse(['error' => 'Chapter is locked'], 403);
    }
    
    // Get chapter data (in production, this would come from database)
    $chapterData = getChapterContent($chapterId, $sceneId, $user);
    
    jsonResponse($chapterData);
}

/**
 * Get all available chapters
 */
function getAllChapters($user) {
    $chapters = [
        [
            'id' => 1,
            'title' => 'Первый Контакт',
            'description' => 'Обучение. Вы получаете странное сообщение...',
            'unlocked' => true,
            'completed' => isChapterCompleted($user['id'], 1),
            'scenes_count' => 5
        ],
        [
            'id' => 2,
            'title' => 'Тени Прошлого',
            'description' => 'Кто стоит за этими сообщениями?',
            'unlocked' => isChapterCompleted($user['id'], 1),
            'completed' => isChapterCompleted($user['id'], 2),
            'scenes_count' => 7
        ],
        [
            'id' => 3,
            'title' => 'Цифровой Призрак',
            'description' => 'Граница между реальностью и виртуальным миром стирается',
            'unlocked' => isChapterCompleted($user['id'], 2),
            'completed' => isChapterCompleted($user['id'], 3),
            'scenes_count' => 8
        ]
    ];
    
    jsonResponse(['chapters' => $chapters]);
}

/**
 * Make a story choice
 */
function makeChoice($user) {
    $chapterId = (int)($_POST['chapter_id'] ?? 1);
    $sceneId = (int)($_POST['scene_id'] ?? 1);
    $choiceId = (int)($_POST['choice_id'] ?? 0);
    
    if (!isChapterUnlocked($user['id'], $chapterId)) {
        jsonResponse(['error' => 'Chapter is locked'], 403);
    }
    
    $pdo = getDbConnection();
    $storyProgressTable = getTableName('story_progress');
    
    // Save choice
    $existingStmt = $pdo->prepare("SELECT id, choices_made FROM $storyProgressTable WHERE user_id = ? AND chapter = ?");
    $existingStmt->execute([$user['id'], $chapterId]);
    $existing = $existingStmt->fetch();
    
    $choices = $existing ? json_decode($existing['choices_made'], true) : [];
    $choices["scene_{$sceneId}"] = $choiceId;
    
    if ($existing) {
        $updateStmt = $pdo->prepare("UPDATE $storyProgressTable SET choices_made = ? WHERE user_id = ? AND chapter = ?");
        $updateStmt->execute([json_encode($choices), $user['id'], $chapterId]);
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO $storyProgressTable (user_id, chapter, scene, choices_made)
            VALUES (?, ?, ?, ?)
        ");
        $insertStmt->execute([$user['id'], $chapterId, $sceneId, json_encode($choices)]);
    }
    
    // Calculate fear impact based on choice
    $fearChange = calculateFearFromChoice($chapterId, $sceneId, $choiceId);
    if ($fearChange !== 0) {
        $newFearLevel = updateFearLevel($user['id'], $fearChange, "chapter_{$chapterId}_scene_{$sceneId}_choice_{$choiceId}");
    }
    
    // Get next scene
    $nextScene = getNextScene($chapterId, $sceneId, $choiceId);
    
    logUserAction($user['id'], 'story_choice_made', [
        'chapter' => $chapterId,
        'scene' => $sceneId,
        'choice' => $choiceId,
        'fear_change' => $fearChange
    ]);
    
    jsonResponse([
        'success' => true,
        'next_scene' => $nextScene,
        'fear_level' => isset($newFearLevel) ? $newFearLevel : $user['fear_level'],
        'fear_change' => $fearChange
    ]);
}

/**
 * Start a new chapter
 */
function startChapter($user) {
    $chapterId = (int)($_POST['chapter_id'] ?? 1);
    
    if (!isChapterUnlocked($user['id'], $chapterId)) {
        jsonResponse(['error' => 'Chapter is locked'], 403);
    }
    
    $pdo = getDbConnection();
    $storyProgressTable = getTableName('story_progress');
    
    // Check if already started
    $stmt = $pdo->prepare("SELECT id FROM $storyProgressTable WHERE user_id = ? AND chapter = ?");
    $stmt->execute([$user['id'], $chapterId]);
    
    if (!$stmt->fetch()) {
        // Start new chapter
        $insertStmt = $pdo->prepare("
            INSERT INTO $storyProgressTable (user_id, chapter, scene, choices_made)
            VALUES (?, ?, 1, '{}')
        ");
        $insertStmt->execute([$user['id'], $chapterId]);
        
        logUserAction($user['id'], 'chapter_started', ['chapter' => $chapterId]);
        
        // Increase fear at chapter start
        if ($chapterId > 1) {
            $newFearLevel = updateFearLevel($user['id'], 10, "chapter_{$chapterId}_started");
        }
    }
    
    jsonResponse([
        'success' => true,
        'chapter_id' => $chapterId,
        'starting_scene' => 1
    ]);
}

/**
 * Check if chapter is unlocked for user
 */
function isChapterUnlocked($userId, $chapterId) {
    if ($chapterId === 1) {
        return true;
    }
    
    return isChapterCompleted($userId, $chapterId - 1);
}

/**
 * Check if chapter is completed
 */
function isChapterCompleted($userId, $chapterId) {
    $pdo = getDbConnection();
    $storyProgressTable = getTableName('story_progress');
    
    $stmt = $pdo->prepare("SELECT completed_at FROM $storyProgressTable WHERE user_id = ? AND chapter = ? AND completed_at IS NOT NULL");
    $stmt->execute([$userId, $chapterId]);
    
    return $stmt->fetch() !== false;
}

/**
 * Get chapter content (simplified - in production use database)
 */
function getChapterContent($chapterId, $sceneId, $user) {
    // Chapter 1: First Contact (Tutorial)
    if ($chapterId === 1) {
        return getChapter1Content($sceneId, $user);
    }
    
    // Other chapters would be implemented similarly
    return [
        'error' => 'Chapter not found'
    ];
}

/**
 * Chapter 1: First Contact - Tutorial Chapter
 */
function getChapter1Content($sceneId, $user) {
    $scenes = [
        1 => [
            'scene_id' => 1,
            'text' => 'Ваш телефон вибрирует. Неизвестный номер. Сообщение приходит в странном приложении, которое вы не помните, что устанавливали...',
            'image' => 'phone_vibration.png',
            'audio' => 'vibration_sound.mp3',
            'choices' => [
                ['id' => 1, 'text' => 'Открыть сообщение', 'next_scene' => 2],
                ['id' => 2, 'text' => 'Игнорировать', 'next_scene' => 3]
            ],
            'fear_effect' => 5,
            'glitch_effect' => false
        ],
        2 => [
            'scene_id' => 2,
            'text' => '"Привет... Я знаю, кто ты. И я знаю, где ты живёшь." Сообщение сопровождается странной фотографией. Это ваш дом.',
            'image' => 'house_photo.png',
            'audio' => 'creepy_ambient.mp3',
            'choices' => [
                ['id' => 1, 'text' => 'Кто это?', 'next_scene' => 4],
                ['id' => 2, 'text' => 'Это чья-то шутка', 'next_scene' => 4],
                ['id' => 3, 'text' => 'Удалить приложение', 'next_scene' => 5]
            ],
            'fear_effect' => 10,
            'glitch_effect' => true
        ],
        3 => [
            'scene_id' => 3,
            'text' => 'Вы пытаетесь игнорировать сообщение, но телефон продолжает вибрировать. again и again. Приложение открывается само.',
            'image' => 'phone_glitch.png',
            'audio' => 'multiple_vibrations.mp3',
            'choices' => [
                ['id' => 1, 'text' => 'Посмотреть наконец', 'next_scene' => 2],
                ['id' => 2, 'text' => 'Выключить телефон', 'next_scene' => 6]
            ],
            'fear_effect' => 8,
            'glitch_effect' => true
        ],
        4 => [
            'scene_id' => 4,
            'text' => '"Шутка? Нет... Это не шутка. Ты следующий." Экран начинает мерцать. Появляются странные символы.',
            'image' => 'screen_glitch.png',
            'audio' => 'static_noise.mp3',
            'choices' => [
                ['id' => 1, 'text' => 'Что тебе нужно?', 'next_scene' => 7],
                ['id' => 2, 'text' => 'Я вызову полицию!', 'next_scene' => 8]
            ],
            'fear_effect' => 12,
            'glitch_effect' => true
        ],
        5 => [
            'scene_id' => 5,
            'text' => 'Приложение не удаляется. Иконка исчезает, но сообщения продолжают приходить через системные уведомления.',
            'image' => 'notification_flood.png',
            'audio' => 'notification_spam.mp3',
            'choices' => [
                ['id' => 1, 'text' => 'Перезагрузить телефон', 'next_scene' => 9],
                ['id' => 2, 'text' => 'Смириться и читать', 'next_scene' => 7]
            ],
            'fear_effect' => 15,
            'glitch_effect' => true
        ],
        6 => [
            'scene_id' => 6,
            'text' => 'Телефон не выключается. Экран горит в темноте. Сообщение: "Ты не можешь спрятаться."',
            'image' => 'dark_room_phone.png',
            'audio' => 'ominous_hum.mp3',
            'choices' => [
                ['id' => 1, 'text' => 'Выбросить телефон', 'next_scene' => 10],
                ['id' => 2, 'text' => 'Спросить чего они хотят', 'next_scene' => 7]
            ],
            'fear_effect' => 18,
            'glitch_effect' => true
        ],
        7 => [
            'scene_id' => 7,
            'text' => '"Мне нужно... чтобы ты играл. В мою игру. Если выиграешь — отпущу. Если проиграешь..." Фотография меняется. Теперь это вы, спящий.',
            'image' => 'sleeping_photo.png',
            'audio' => 'heartbeat.mp3',
            'choices' => [
                ['id' => 1, 'text' => 'Какая игра?', 'next_scene' => 11, 'chapter_complete' => true],
                ['id' => 2, 'text' => 'Я не буду играть!', 'next_scene' => 11, 'chapter_complete' => true]
            ],
            'fear_effect' => 20,
            'glitch_effect' => true
        ],
        8 => [
            'scene_id' => 8,
            'text' => '"Полиция? Ха! Они уже здесь. Посмотри в окно."' Вы смотрите — на улице стоит чёрная машина с затемнёнными стёклами.',
            'image' => 'black_car.png',
            'audio' => 'car_engine.mp3',
            'choices' => [
                ['id' => 1, 'text' => 'Это невозможно...', 'next_scene' => 7, 'chapter_complete' => true],
                ['id' => 2, 'text' => 'Бежать!', 'next_scene' => 7, 'chapter_complete' => true]
            ],
            'fear_effect' => 25,
            'glitch_effect' => true
        ],
        9 => [
            'scene_id' => 9,
            'text' => 'После перезагрузки телефон показывает странное сообщение при загрузке: "Я всегда здесь". Приложение появляется снова.',
            'image' => 'boot_message.png',
            'audio' => 'system_boot_glitch.mp3',
            'choices' => [
                ['id' => 1, 'text' => 'Ладно, что дальше?', 'next_scene' => 7, 'chapter_complete' => true]
            ],
            'fear_effect' => 17,
            'glitch_effect' => true
        ],
        10 => [
            'scene_id' => 10,
            'text' => 'Телефон разбивается об асфальт. Но в голове всё ещё звучит голос: "Другой найдётся. Всегда находится."',
            'image' => 'broken_phone.png',
            'audio' => 'glass_break.mp3',
            'choices' => [
                ['id' => 1, 'text' => 'Конец главы 1', 'next_scene' => 11, 'chapter_complete' => true]
            ],
            'fear_effect' => 15,
            'glitch_effect' => false
        ],
        11 => [
            'scene_id' => 11,
            'text' => 'Глава 1 завершена. Уровень страха влияет на следующие события. Готовы продолжить?',
            'image' => 'chapter_complete.png',
            'audio' => 'chapter_end.mp3',
            'choices' => [],
            'fear_effect' => 0,
            'glitch_effect' => false,
            'chapter_complete' => true
        ]
    ];
    
    $scene = $scenes[$sceneId] ?? null;
    
    if (!$scene) {
        return ['error' => 'Scene not found'];
    }
    
    // Apply fear effect
    if ($scene['fear_effect'] > 0) {
        $pdo = getDbConnection();
        $newFearLevel = updateFearLevel($user['id'], $scene['fear_effect'], "chapter_1_scene_{$sceneId}");
        $scene['new_fear_level'] = $newFearLevel;
    }
    
    return $scene;
}

/**
 * Calculate fear change from choice
 */
function calculateFearFromChoice($chapterId, $sceneId, $choiceId) {
    // Simple implementation - can be expanded with complex logic
    // Brave choices reduce fear, fearful choices increase it
    
    $braveChoices = [1]; // Example: choice ID 1 is always brave
    $fearfulChoices = [2, 3]; // Example: other choices are fearful
    
    if (in_array($choiceId, $braveChoices)) {
        return -2; // Reduce fear
    } elseif (in_array($choiceId, $fearfulChoices)) {
        return 5; // Increase fear
    }
    
    return 0;
}

/**
 * Get next scene based on choice
 */
function getNextScene($chapterId, $sceneId, $choiceId) {
    // This would normally query the database for the scene graph
    // Simplified implementation for Chapter 1
    
    if ($chapterId === 1) {
        $scenes = getChapter1Content($sceneId, ['id' => 0]);
        foreach ($scenes['choices'] as $choice) {
            if ($choice['id'] === $choiceId) {
                return [
                    'scene_id' => $choice['next_scene'],
                    'chapter_complete' => $choice['chapter_complete'] ?? false
                ];
            }
        }
    }
    
    return ['scene_id' => $sceneId + 1, 'chapter_complete' => false];
}

/**
 * Get fear impact on gameplay
 */
function getFearImpact($fearLevel) {
    if ($fearLevel < 20) {
        return 'normal';
    } elseif ($fearLevel < 50) {
        return 'elevated';
    } elseif ($fearLevel < 80) {
        return 'high';
    } else {
        return 'extreme';
    }
}
