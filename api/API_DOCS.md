# DarkDate API Documentation

## Base URL
```
/api/
```

## Authentication
All API requests (except registration/login) require authentication via Bearer token:
```
Authorization: Bearer <token>
```

---

## Auth API (`auth.php`)

### POST `auth.php?action=register`
Register a new user.

**Request:**
```json
{
  "username": "string",
  "password": "string",
  "email": "string (optional)"
}
```

**Response:**
```json
{
  "success": true,
  "user_id": 1,
  "token": "abc123..."
}
```

### POST `auth.php?action=login`
Login existing user.

**Request:**
```json
{
  "username": "string",
  "password": "string"
}
```

**Response:**
```json
{
  "success": true,
  "user": { "id": 1, "username": "player1", "fear_level": 0 },
  "token": "abc123..."
}
```

### POST `auth.php?action=logout`
Logout current user.

---

## Chat API (`chat.php`)

### GET `chat.php?action=history&limit=50`
Get chat message history.

**Response:**
```json
{
  "messages": [
    {
      "id": 1,
      "sender_type": "bot",
      "sender_id": "alice",
      "message": "Привет! Как дела?",
      "timestamp": "2024-01-01 12:00:00",
      "is_read": false,
      "message_type": "text"
    }
  ]
}
```

### POST `chat.php?action=send`
Send a message.

**Request:**
```json
{
  "message": "Привет, Алиса!",
  "recipient": "alice (optional)"
}
```

**Response:**
```json
{
  "success": true,
  "message_id": 42,
  "bot_responses": [...]
}
```

### GET `chat.php?action=notifications`
Get unread notifications.

---

## Story API (`story.php`)

### GET `story.php?action=progress`
Get current story progress.

**Response:**
```json
{
  "chapter": 1,
  "scene": 3,
  "choices_made": {"q1": "a2", "q2": "a1"},
  "fear_level": 45
}
```

### POST `story.php?action=choice`
Make a story choice.

**Request:**
```json
{
  "question_id": "q1",
  "answer_id": "a2"
}
```

**Response:**
```json
{
  "success": true,
  "new_scene": 4,
  "fear_change": +5,
  "next_messages": [...]
}
```

### POST `story.php?action=update_fear`
Update fear level manually.

**Request:**
```json
{
  "change": -10,
  "reason": "calming_event"
}
```

---

## Bots API (`bots.php`)

### GET `bots.php?action=list`
List all active bots.

**Response:**
```json
{
  "bots": [
    {
      "id": 1,
      "bot_key": "alice",
      "name": "Алиса",
      "personality_type": "friendly",
      "message_frequency": 8
    }
  ]
}
```

### GET `bots.php?action=greeting&bot_key=alice`
Get bot greeting.

**Response:**
```json
{
  "greeting": "Привет! :)",
  "bot": {...}
}
```

### POST `bots.php?action=respond`
Process user message and get bot responses.

**Request:**
```json
{
  "message": "Мне страшно..."
}
```

**Response:**
```json
{
  "user_fear_level": 65,
  "scheduled_responses": [
    {
      "bot_key": "alice",
      "bot_name": "Алиса",
      "message": "Тебе нехорошо? Я волнуюсь...",
      "delay_seconds": 3
    }
  ]
}
```

### POST `bots.php?action=spam_attack`
Trigger spam attack (testing).

**Response:**
```json
{
  "messages_sent": 15,
  "blocked_duration": 10
}
```

### POST `bots.php?action=glitch_message`
Send glitched message.

**Request:**
```json
{
  "message": "System failure"
}
```

---

## Error Responses

All errors follow this format:
```json
{
  "error": "Error message description"
}
```

**Common HTTP Status Codes:**
- `200` - Success
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden (blocked)
- `404` - Not Found
- `405` - Method Not Allowed
- `500` - Server Error

---

## Bot Personalities

| Type | Description | Behavior |
|------|-------------|----------|
| `friendly` | supportive | Comforts user when scared |
| `mysterious` | enigmatic | Hints at dark secrets |
| `aggressive` | hostile | Becomes more threatening |
| `glitch` | corrupted | Text gets corrupted |
| `spammer` | chaotic | Sends message floods |

---

## Fear System

Fear level ranges from 0-100:
- **0-30**: Normal behavior
- **31-60**: Increased bot activity
- **61-80**: Special fear-triggered responses
- **81-100**: Horror events, glitches, spam attacks

Fear increases from:
- Scary story events (+10-20)
- Glitch messages (+5)
- Spam attacks (+15)
- Night time activity (+1/hour)

Fear decreases from:
- Friendly bot interactions (-5)
- Calming story choices (-10)
- Time away from game (-5/hour)
