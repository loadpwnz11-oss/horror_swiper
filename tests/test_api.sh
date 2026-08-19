#!/bin/bash
# 🧪 DarkDate API Test Suite
# Автоматическая проверка всех endpoints API

# Настройки
BASE_URL="http://localhost/darkdate/api"
TEST_EMAIL="test_$(date +%s)@darkdate.com"
TEST_PASS="SecurePass123!"
USER_ID=""
SESSION_TOKEN=""
BOT_ID=1

echo "🚀 Запуск тестов DarkDate API..."
echo "================================"

# Цвета для вывода
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

pass_test() {
    echo -e "${GREEN}✓ PASS${NC}: $1"
}

fail_test() {
    echo -e "${RED}✗ FAIL${NC}: $1 - $2"
}

info() {
    echo -e "${YELLOW}ℹ INFO${NC}: $1"
}

# 1. Тест регистрации
echo ""
echo "1️⃣  Тест регистрации..."
REGISTER_RESPONSE=$(curl -s -X POST "$BASE_URL/auth.php" \
    -H "Content-Type: application/json" \
    -d "{\"action\":\"register\",\"email\":\"$TEST_EMAIL\",\"password\":\"$TEST_PASS\",\"username\":\"TestUser\"}")

if echo "$REGISTER_RESPONSE" | grep -q '"success":true'; then
    pass_test "Регистрация успешна"
    USER_ID=$(echo "$REGISTER_RESPONSE" | grep -o '"user_id":[0-9]*' | cut -d':' -f2)
    info "User ID: $USER_ID"
else
    fail_test "Регистрация" "$REGISTER_RESPONSE"
    exit 1
fi

# 2. Тест логина
echo ""
echo "2️⃣  Тест авторизации..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth.php" \
    -H "Content-Type: application/json" \
    -d "{\"action\":\"login\",\"email\":\"$TEST_EMAIL\",\"password\":\"$TEST_PASS\"}")

if echo "$LOGIN_RESPONSE" | grep -q '"success":true'; then
    pass_test "Авторизация успешна"
    SESSION_TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
    info "Token: ${SESSION_TOKEN:0:20}..."
else
    fail_test "Авторизация" "$LOGIN_RESPONSE"
    exit 1
fi

# 3. Тест получения списка ботов
echo ""
echo "3️⃣  Тест получения ботов..."
BOTS_RESPONSE=$(curl -s -X GET "$BASE_URL/bots.php?action=list")

if echo "$BOTS_RESPONSE" | grep -q '"success":true'; then
    pass_test "Список ботов получен"
    BOT_COUNT=$(echo "$BOTS_RESPONSE" | grep -o '"count":[0-9]*' | cut -d':' -f2)
    info "Найдено ботов: $BOT_COUNT"
else
    fail_test "Список ботов" "$BOTS_RESPONSE"
fi

# 4. Тест отправки сообщения
echo ""
echo "4️⃣  Тест отправки сообщения..."
MESSAGE_RESPONSE=$(curl -s -X POST "$BASE_URL/chat.php" \
    -H "Content-Type: application/json" \
    -d "{\"action\":\"send\",\"bot_id\":$BOT_ID,\"message\":\"Привет! Как дела?\",\"session_token\":\"$SESSION_TOKEN\"}")

if echo "$MESSAGE_RESPONSE" | grep -q '"success":true'; then
    pass_test "Сообщение отправлено"
    MESSAGE_ID=$(echo "$MESSAGE_RESPONSE" | grep -o '"message_id":[0-9]*' | cut -d':' -f2)
    info "Message ID: $MESSAGE_ID"
else
    fail_test "Отправка сообщения" "$MESSAGE_RESPONSE"
fi

# 5. Тест получения истории чата
echo ""
echo "5️⃣  Тест истории чата..."
HISTORY_RESPONSE=$(curl -s -X GET "$BASE_URL/chat.php?action=history&bot_id=$BOT_ID&session_token=$SESSION_TOKEN")

if echo "$HISTORY_RESPONSE" | grep -q '"success":true'; then
    pass_test "История чата получена"
    MSG_COUNT=$(echo "$HISTORY_RESPONSE" | grep -o '"message_count":[0-9]*' | cut -d':' -f2)
    info "Сообщений в истории: $MSG_COUNT"
else
    fail_test "История чата" "$HISTORY_RESPONSE"
fi

# 6. Тест прогресса сюжета
echo ""
echo "6️⃣  Тест прогресса сюжета..."
PROGRESS_RESPONSE=$(curl -s -X GET "$BASE_URL/story.php?action=progress&session_token=$SESSION_TOKEN")

if echo "$PROGRESS_RESPONSE" | grep -q '"success":true'; then
    pass_test "Прогресс сюжета получен"
    FEAR_LEVEL=$(echo "$PROGRESS_RESPONSE" | grep -o '"fear_level":[0-9]*' | cut -d':' -f2)
    info "Уровень страха: $FEAR_LEVEL"
else
    fail_test "Прогресс сюжета" "$PROGRESS_RESPONSE"
fi

# 7. Тест выбора варианта ответа
echo ""
echo "7️⃣  Тест выбора варианта..."
CHOICE_RESPONSE=$(curl -s -X POST "$BASE_URL/story.php" \
    -H "Content-Type: application/json" \
    -d "{\"action\":\"choice\",\"choice_id\":1,\"session_token\":\"$SESSION_TOKEN\"}")

if echo "$CHOICE_RESPONSE" | grep -q '"success":true'; then
    pass_test "Выбор принят"
else
    # Это может быть ожидаемым, если нет активных выборов
    info "Выбор не активен или принят: $CHOICE_RESPONSE"
fi

# 8. Тест уведомлений
echo ""
echo "8️⃣  Тест уведомлений..."
NOTIF_RESPONSE=$(curl -s -X GET "$BASE_URL/chat.php?action=notifications&session_token=$SESSION_TOKEN")

if echo "$NOTIF_RESPONSE" | grep -q '"success":true'; then
    pass_test "Уведомления получены"
else
    fail_test "Уведомления" "$NOTIF_RESPONSE"
fi

# 9. Тест неавторизованного доступа (должен вернуть ошибку)
echo ""
echo "9️⃣  Тест защиты (неавторизованный доступ)..."
UNAUTH_RESPONSE=$(curl -s -X POST "$BASE_URL/chat.php" \
    -H "Content-Type: application/json" \
    -d "{\"action\":\"send\",\"bot_id\":1,\"message\":\"Hack attempt\",\"session_token\":\"invalid_token\"}")

if echo "$UNAUTH_RESPONSE" | grep -q '"success":false'; then
    pass_test "Защита работает (доступ запрещен)"
else
    fail_test "Защита" "Доступ разрешен с неверным токеном!"
fi

# 10. Тест logout
echo ""
echo "🔟 Тест выхода..."
LOGOUT_RESPONSE=$(curl -s -X POST "$BASE_URL/auth.php" \
    -H "Content-Type: application/json" \
    -d "{\"action\":\"logout\",\"session_token\":\"$SESSION_TOKEN\"}")

if echo "$LOGOUT_RESPONSE" | grep -q '"success":true'; then
    pass_test "Выход выполнен успешно"
else
    fail_test "Выход" "$LOGOUT_RESPONSE"
fi

# Итоги
echo ""
echo "================================"
echo "✅ Тестирование завершено!"
echo "Тестовый пользователь: $TEST_EMAIL"
echo "Для очистки выполните:"
echo "  curl -X POST $BASE_URL/auth.php -H 'Content-Type: application/json' -d '{\"action\":\"delete_account\",\"email\":\"$TEST_EMAIL\",\"password\":\"$TEST_PASS\"}'"
