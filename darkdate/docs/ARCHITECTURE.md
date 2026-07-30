# 🏗️ Architecture — DarkDate

> Версия: 0.1.0  
> Последнее обновление: 2026-06-30  
> Статус: Актуально для v0.1.0 (Прототип)

---

## 📐 Общая архитектура

DarkDate следует **модульной архитектуре** с чётким разделением ответственности между компонентами. Приложение построено на **ванильном JavaScript (ES6 Modules)** без использования фреймворков.

```
┌─────────────────────────────────────────────────────────┐
│                    index.html                           │
│              (Точка входа, DOM-структура)               │
└─────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────┐
│                   app.js                                │
│         (Главный контроллер приложения)                 │
│  ┌──────────────────────────────────────────────────┐   │
│  │  DarkDateApp                                     │   │
│  │  - state: GameState                              │   │
│  │  - i18n: I18n                                    │   │
│  │  - horror: HorrorEngine                          │   │
│  │  - ui: UIController                              │   │
│  │  - swipe: SwipeEngine                            │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        ▼                   ▼                   ▼
┌──────────────┐   ┌──────────────┐   ┌──────────────┐
│  core/       │   │  modules/    │   │  data/       │
│  state.js    │   │  swipe.js    │   │  profiles.json│
│              │   │  horror.js   │   │  i18n/*.json │
│              │   │  ui.js       │   │              │
│              │   │  i18n.js     │   │              │
└──────────────┘   └──────────────┘   └──────────────┘
```

---

## 📁 Структура файлов

```
darkdate/
├── index.html                 # Главная HTML-страница
├── src/
│   ├── scripts/
│   │   ├── app.js            # ⭐ Главный entry point, класс DarkDateApp
│   │   ├── core/
│   │   │   └── state.js      # 💾 Управление состоянием игры (жизни, таймеры, статистика)
│   │   └── modules/
│   │       ├── swipe.js      # 👆 Движок свайпов (touch/mouse события)
│   │       ├── horror.js     # 👻 Хоррор-эффекты (глитчи, тряска, звук)
│   │       ├── ui.js         # 🎨 Контроллер UI (модалки, жизни, таймеры)
│   │       └── i18n.js       # 🌐 Интернационализация (RU/EN)
│   ├── styles/
│   │   ├── main.css          # Основные стили
│   │   ├── animations.css    # CSS-анимации
│   │   └── horror.css        # Хоррор-эффекты (глитчи, шум, сканлайны)
│   ├── data/
│   │   ├── profiles.json     # 📇 Профили персонажей (люди, сущности, жертвы, охотники)
│   │   └── i18n/
│   │       ├── ru.json       # Русская локаль
│   │       └── en.json       # Английская локаль
│   └── assets/
│       ├── images/           # Изображения персонажей
│       └── icons/
│           └── logo.svg      # Логотип игры
└── docs/
    ├── README_DOCS.md        # Навигатор по документации
    ├── GDD.md                # Game Design Document
    ├── ARCHITECTURE.md       # Этот файл
    ├── TECH_STACK.md         # Технологии и инструменты
    └── CHANGELOG.md          # История изменений
```

---

## 🔧 Модули

### 1. `app.js` — Главный контроллер

**Ответственность:** Инициализация приложения, координация всех модулей.

**Ключевые методы:**
- `init()` — загрузка профилей, состояния, инициализация модулей
- `loadProfiles()` — загрузка JSON с профилями
- `startRound()` — начало нового раунда
- `renderCards()` — рендеринг карточек в стеке
- `handleSwipe(direction)` — обработка свайпа
- `processSwipeResult(profile, isLike)` — логика результатов свайпа

**Зависимости:** Все модули (`GameState`, `SwipeEngine`, `HorrorEngine`, `UIController`, `I18n`)

---

### 2. `core/state.js` — Менеджер состояния

**Ответственность:** Сохранение/восстановление состояния игры, управление жизнями и таймерами.

**Ключевые свойства:**
- `roundLives` — жизни в текущем раунде
- `sessionLives` — общие сессионные жизни
- `recoveryEndTime` — время восстановления жизней
- `stats` — статистика игрока

**Ключевые методы:**
- `restore()` — загрузка из localStorage
- `save()` — сохранение в localStorage
- `loseLife()` — потеря жизни
- `hasSessionLives()` / `hasRoundLives()` — проверка доступных жизней
- `startRecoveryTimer()` — запуск таймера восстановления (4 часа)
- `formatTime(ms)` — форматирование времени для отображения

**Хранение данных:** `localStorage` (ключ: `darkdate_state`)

---

### 3. `modules/swipe.js` — Движок свайпов

**Ответственность:** Обработка жестов свайпа (touch/mouse), анимации карточек.

**Поддерживаемые события:**
- Touch: `touchstart`, `touchmove`, `touchend`
- Mouse: `mousedown`, `mousemove`, `mouseup`, `mouseleave`

**Ключевые методы:**
- `attachToCard(card)` — привязка обработчиков к карточке
- `programmaticSwipe(direction)` — программный свайп (кнопки)
- `animateSwipe(direction)` — анимация свайпа с вибрацией
- `checkDebuffs(card)` — активация дебаффов при показе карточки

**Порог срабатывания:** 100 пикселей смещения

---

### 4. `modules/horror.js` — Хоррор-движок

**Ответственность:** Визуальные и звуковые хоррор-эффекты.

**Эффекты:**
- `triggerGlitch(duration)` — глитч экрана
- `triggerShake(heavy)` — тряска экрана (обычная/сильная)
- `triggerRedFlash()` — красная вспышка
- `activateFlashlight(duration)` — темнота + фонарик (отслеживание курсора)
- `activateBlur(duration)` — размытие зрения
- `triggerEntityAccepted()` — комбо-эффект при принятии сущности
- `triggerGameOver()` — эффекты game over

**Звук (Web Audio API):**
- `playDrone(duration)` — низкий гул
- `playWhisper()` — имитация шёпота (белый шум + фильтр)

**CSS-классы эффектов:** `.glitch-active`, `.screen-shake`, `.red-flash`, `.static-noise`, `.scanlines`, `.vision-blur`

---

### 5. `modules/ui.js` — Контроллер интерфейса

**Ответственность:** Управление DOM-элементами, модалками, отображение жизней и таймеров.

**Ключевые методы:**
- `updateLives()` — обновление отображения жизней (сердечки)
- `renderHearts(container, current, max)` — рендеринг сердечек
- `showResultModal(result)` — показ модального окна результата
- `hideModal()` — скрытие модального окна
- `showTimerScreen()` — отображение таймера восстановления

**Анимации:**
- `animateHeartLoss(container, index)` — анимация потери сердца

---

### 6. `modules/i18n.js` — Интернационализация

**Ответственность:** Загрузка и применение переводов.

**Поддерживаемые языки:** RU, EN, ZH (заготовлен)

**Ключевые методы:**
- `load(lang)` — загрузка JSON перевода
- `t(key)` — получение перевода по ключу (поддержка вложенности: `'modal.title'`)
- `applyToDOM()` — применение переводов к элементам с `data-i18n`
- `setLanguage(lang)` — смена языка с сохранением в localStorage
- `getSavedLanguage()` — получение сохранённого языка

**Формат ключей:** Точечная нотация (например, `swipe.like`, `modal.match_title`)

---

## 📊 Поток данных

### Инициализация приложения

```
1. DOMContentLoaded
       ↓
2. Создание экземпляра DarkDateApp
       ↓
3. app.init()
   ├─ i18n.load('ru')
   ├─ loadProfiles() → fetch('src/data/profiles.json')
   ├─ state.restore() ← localStorage
   ├─ ui.init()
   ├─ swipe = new SwipeEngine(this)
   └─ showSplash()
       ↓
4. startRound() → renderCards()
```

### Обработка свайпа

```
1. Пользователь свайпает карточку
       ↓
2. SwipeEngine.onTouchEnd() / onMouseUp()
       ↓
3. animateSwipe(direction)
   ├─ Анимация ухода карточки
   ├─ Вибрация (navigator.vibrate)
   └─ Через 500ms: app.handleSwipe(direction)
       ↓
4. app.processSwipeResult(profile, isLike)
   ├─ Обновление статистики (state.stats)
   ├─ Если entity + like: state.loseLife()
   └─ Возврат результата (type, icon, title, text)
       ↓
5. ui.showResultModal(result)
       ↓
6. app.currentIndex++ → renderCards()
```

### Потеря жизни и восстановление

```
1. state.loseLife()
   ├─ roundLives--
   ├─ sessionLives--
   ├─ stats.entitiesAccepted++
   └─ save() → localStorage
       ↓
2. Если sessionLives <= 0:
   └─ startRecoveryTimer()
      └─ recoveryEndTime = Date.now() + 4 часа
       ↓
3. showTimerScreen()
   └─ setInterval (1 сек):
      ├─ getRecoveryTimeRemaining()
      ├─ formatTime() → обновление дисплея
      └─ Если время истекло:
         ├─ sessionLives += 2
         ├─ recoveryEndTime = null
         └─ startRound()
```

---

## 🔐 Хранение данных

### localStorage

| Ключ | Тип данных | Описание |
|------|-----------|----------|
| `darkdate_state` | Object | Состояние игры (жизни, таймеры, статистика) |
| `darkdate_lang` | String | Выбранный язык ('ru', 'en') |

### Структура `darkdate_state`:

```json
{
  "sessionLives": 3,
  "recoveryEndTime": 1719748800000,
  "stats": {
    "totalSwipes": 42,
    "entitiesDodged": 5,
    "entitiesAccepted": 2,
    "victimsSaved": 3,
    "victimsLost": 1,
    "hintsReceived": 2
  },
  "lastSave": 1719745200000
}
```

---

## 🎯 Расширяемость

### Добавление нового типа профиля

1. Добавить профиль в `profiles.json` с новым `type`
2. Обновить `app.processSwipeResult()` — добавить case для нового типа
3. При необходимости: добавить логику в `horror.js` или `ui.js`

### Добавление нового хоррор-эффекта

1. Добавить метод в `HorrorEngine` (например, `triggerDistortion()`)
2. Добавить CSS-классы в `horror.css`
3. Вызвать эффект из нужного места (например, в `handleSwipe()`)

### Добавление нового языка

1. Создать файл `src/data/i18n/{lang}.json`
2. Добавить язык в `I18n.supportedLangs`
3. Перевести все ключи из `ru.json`

---

## 🚀 Производительность

### Оптимизации

- **Ленивая загрузка изображений:** `loading="lazy"` на `<img>`
- **Рендеринг до 3 карточек:** Остальные создаются по мере необходимости
- **CSS-трансформации:** Используются для анимаций (аппаратное ускорение)
- **Минимальный reflow:** Изменения стилей через classList, не inline

### Потенциальные узкие места

- Загрузка тяжёлых изображений сущностей (1-4 MB каждое)
- Web Audio API инициализируется только по первому действию пользователя (требование браузеров)

---

## 🧪 Тестирование

### Ручное тестирование

1. **Свайпы:** Проверить все направления, кнопки Like/Dislike
2. **Жизни:** Потерять все жизни, проверить таймер восстановления
3. **Хоррор-эффекты:** Свайпнуть сущность, проверить глитчи/звук
4. **i18n:** Переключить язык, проверить все тексты
5. **Сохранение:** Обновить страницу, проверить восстановление состояния

### Автоматизация (TODO)

- Unit-тесты для `GameState` (Jest/Vitest)
- E2E-тесты для сценариев свайпов (Playwright/Cypress)

---

## 📝 Заметки для разработчиков

- **Не использовать глобальные переменные**, кроме `window.darkdate` (для отладки)
- **Все модули — ES6 classes** для единообразия
- **Константы выносить вверх файла** (магические числа — зло)
- **Комментировать сложные участки кода** на русском или английском
- **Следовать соглашениям именования:**
  - Классы: `PascalCase` (`DarkDateApp`, `GameState`)
  - Методы: `camelCase` (`handleSwipe`, `renderCards`)
  - Константы: `UPPER_SNAKE_CASE` (`STORAGE_KEY`, `ROUND_LIVES_DEFAULT`)

---

## 🔮 Планы на архитектуру (v0.2+)

- [ ] Вынести конфигурацию в отдельный файл (`config.js`)
- [ ] Добавить систему событий (EventEmitter) для слабой связанности
- [ ] Поддержка тем (светлая/тёмная/хоррор)
- [ ] WebSocket для мультиплеера
- [ ] Service Worker для offline-режима
- [ ] TypeScript для типизации