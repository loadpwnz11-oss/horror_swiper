/**
 * DarkDate — Game State Manager
 * Управление состоянием, жизнями, таймерами
 */

const STORAGE_KEY = 'darkdate_state';
const ROUND_LIVES_DEFAULT = 3;
const SESSION_LIVES_DEFAULT = 3;
const RECOVERY_TIME_MS = 4 * 60 * 60 * 1000; // 4 часа
const RECOVERY_LIVES = 2;

export class GameState {
    constructor() {
        this.roundLives = ROUND_LIVES_DEFAULT;
        this.sessionLives = SESSION_LIVES_DEFAULT;
        this.maxRoundLives = ROUND_LIVES_DEFAULT;
        this.maxSessionLives = SESSION_LIVES_DEFAULT;
        this.recoveryEndTime = null;
        this.stats = {
            totalSwipes: 0,
            entitiesDodged: 0,
            entitiesAccepted: 0,
            victimsSaved: 0,
            victimsLost: 0,
            hintsReceived: 0
        };
    }

    /** Загрузка из localStorage */
    restore() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (!saved) return;

            const data = JSON.parse(saved);
            this.sessionLives = data.sessionLives ?? SESSION_LIVES_DEFAULT;
            this.roundLives = data.roundLives ?? ROUND_LIVES_DEFAULT;
            this.recoveryEndTime = data.recoveryEndTime ?? null;
            this.stats = { ...this.stats, ...data.stats };

            // Проверяем, истёк ли таймер восстановления
            this.checkRecovery();

        } catch (e) {
            console.warn('[State] Failed to restore:', e);
        }
    }

    /** Сохранение в localStorage */
    save() {
        try {
            const data = {
                sessionLives: this.sessionLives,
                roundLives: this.roundLives,
                recoveryEndTime: this.recoveryEndTime,
                stats: this.stats,
                lastSave: Date.now()
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch (e) {
            console.warn('[State] Failed to save:', e);
        }
    }

    /** Сброс жизней раунда */
    resetRoundLives() {
        this.roundLives = this.maxRoundLives;
    }

    /** Потеря жизни */
    loseLife() {
        this.stats.entitiesAccepted++;
        this.stats.totalSwipes++;

        // Сначала отнимаем жизни раунда
        if (this.roundLives > 0) {
            this.roundLives--;
            this.save();
            return { roundLives: this.roundLives, sessionLives: this.sessionLives };
        }
        
        // Если жизни раунда кончились, отнимаем жизнь сессии
        if (this.sessionLives > 0) {
            this.sessionLives--;
            
            // Если сессионные жизни кончились — запускаем таймер
            if (this.sessionLives <= 0 && !this.recoveryEndTime) {
                this.startRecoveryTimer();
            }
        }

        this.save();

        return { roundLives: this.roundLives, sessionLives: this.sessionLives };
    }

    /** Безопасный свайп (не сущность) */
    recordSafeSwipe() {
        this.stats.totalSwipes++;
        this.save();
    }

    /** Проверка наличия сессионных жизней */
    hasSessionLives() {
        this.checkRecovery();
        return this.sessionLives > 0;
    }

    /** Проверка наличия жизней раунда */
    hasRoundLives() {
        return this.roundLives > 0;
    }

    /** Запуск таймера восстановления */
    startRecoveryTimer() {
        this.recoveryEndTime = Date.now() + RECOVERY_TIME_MS;
        this.save();
    }

    /** Проверка истечения таймера */
    checkRecovery() {
        if (this.recoveryEndTime && Date.now() >= this.recoveryEndTime) {
            this.sessionLives = Math.min(
                this.sessionLives + RECOVERY_LIVES,
                this.maxSessionLives
            );
            this.recoveryEndTime = null;
            this.save();
        }
    }

    /** Получение оставшегося времени таймера (мс) */
    getRecoveryTimeRemaining() {
        if (!this.recoveryEndTime) return 0;
        const remaining = this.recoveryEndTime - Date.now();
        return remaining > 0 ? remaining : 0;
    }

    /** Форматирование времени для отображения */
    formatTime(ms) {
        const totalSec = Math.ceil(ms / 1000);
        const hours = Math.floor(totalSec / 3600);
        const minutes = Math.floor((totalSec % 3600) / 60);
        const seconds = totalSec % 60;
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }
}