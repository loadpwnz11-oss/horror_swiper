/**
 * DarkDate — UI Controller
 * Управление DOM, модалки, жизни, таймеры
 */

export class UIController {
    constructor(state, i18n) {
        this.state = state;
        this.i18n = i18n;
        this.timerInterval = null;
        this.notificationsQueue = []; // Очередь уведомлений
    }

    init() {
        this.bindGlobalEvents();
        this.updateLives();
    }

    bindGlobalEvents() {
        // Кнопка перезапуска
        document.getElementById('btn-restart')?.addEventListener('click', () => {
            window.darkdate?.restart();
        });

        // Кнопка модалки
        document.getElementById('modal-btn')?.addEventListener('click', () => {
            this.hideModal();
        });

        // Кнопка настроек
        document.getElementById('btn-settings')?.addEventListener('click', () => {
            this.showSettingsModal();
        });

        // Кнопка уведомлений - показывает панель с накопленными уведомлениями
        document.getElementById('btn-notifications')?.addEventListener('click', () => {
            this.showNotificationsPanel();
        });

        // Инициализация аудио при первом тапе
        document.addEventListener('click', () => {
            window.darkdate?.horror?.initAudio();
        }, { once: true });
    }

    // === ЖИЗНИ ===

    updateLives() {
        const roundContainer = document.getElementById('round-lives');
        const sessionContainer = document.getElementById('session-lives');

        this.renderHearts(roundContainer, this.state.roundLives, this.state.maxRoundLives);
        this.renderHearts(sessionContainer, this.state.sessionLives, this.state.maxSessionLives);
    }

    renderHearts(container, current, max) {
        if (!container) return;

        const hearts = container.querySelectorAll('.heart');
        hearts.forEach((heart, index) => {
            if (index >= current) {
                heart.classList.add('lost');
            } else {
                heart.classList.remove('lost');
            }
        });
    }

    animateHeartLoss(container, index) {
        const hearts = container?.querySelectorAll('.heart');
        if (hearts && hearts[index]) {
            hearts[index].classList.add('losing');
            setTimeout(() => {
                hearts[index].classList.remove('losing');
                hearts[index].classList.add('lost');
            }, 500);
        }
    }

    // === МОДАЛКИ ===

    showResultModal(result) {
        if (result.type === 'skip') return;

        // Для обычных людей (human) не показываем модалку - уже обрабатывается в app.js через toast
        // Для сущностей, жертв, охотников - добавляем уведомление в бейдж
        this.addNotification(result);
    }

    // === НАКОПЛЕНИЕ УВЕДОМЛЕНИЙ ===

    addNotification(result) {
        const badge = document.getElementById('notif-badge');
        
        // Добавляем уведомление в очередь с временем отправки
        // Храним ключи для перевода, а не переведённый текст
        this.notificationsQueue.push({
            id: Date.now(), // Уникальный ID для удаления
            titleKey: result.titleKey,
            title: result.title, // Фолбэк если нет ключа
            textKey: result.textKey,
            text: result.text, // Фолбэк если нет ключа
            icon: result.icon || '🔔',
            timestamp: new Date()
        });
        
        let count = this.notificationsQueue.length;
        
        if (badge) {
            badge.textContent = count;
            badge.classList.remove('hidden');
            
            // Красная волна (pulse animation)
            badge.classList.add('notification-pulse');
            setTimeout(() => {
                badge.classList.remove('notification-pulse');
            }, 300);
        }

        // Показываем тост-уведомление с заголовком и текстом
        const notification = this.notificationsQueue[this.notificationsQueue.length - 1];
        const title = notification.titleKey ? this.i18n.t(notification.titleKey) : notification.title;
        const text = notification.textKey ? this.i18n.t(notification.textKey) : notification.text;
        const message = title + (text ? ' ' + text : '');
        this.showToast(message || 'Уведомление', 2000);
    }

    clearNotifications() {
        this.notificationsQueue = [];
        const badge = document.getElementById('notif-badge');
        if (badge) {
            badge.textContent = '0';
            badge.classList.add('hidden');
        }
    }

    // === ТОСТ-УВЕДОМЛЕНИЯ (Временные) ===

    showToast(message, duration = 2000) {
        // Удаляем существующий тост если есть
        const existingToast = document.getElementById('toast-notification');
        if (existingToast) {
            existingToast.remove();
        }

        // Создаём новый тост
        const toast = document.createElement('div');
        toast.id = 'toast-notification';
        toast.className = 'toast-notification';
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(30, 30, 30, 0.95);
            color: #fff;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 14px;
            z-index: 10000;
            opacity: 0;
            transition: opacity 0.3s ease;
            max-width: 80%;
            text-align: center;
            pointer-events: none;
        `;

        document.body.appendChild(toast);

        // Показываем
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
        });

        // Скрываем через указанное время
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    hideModal() {
        document.getElementById('modal-overlay')?.classList.add('hidden');
    }

    // === НАСТРОЙКИ (Модальное окно) ===

    showSettingsModal() {
        const langs = this.i18n.getSupportedLanguages();
        const overlay = document.createElement('div');
        overlay.id = 'settings-overlay';
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `
            <div class="modal modal-settings" id="settings-modal">
                <h2 class="modal-title" data-i18n="settings.title">Настройки</h2>
                <div class="settings-content">
                    <div class="setting-item">
                        <label class="setting-label" data-i18n="settings.language">Язык:</label>
                        <div class="language-grid">
                            ${Object.entries(langs).map(([code, {name, flag}]) => `
                                <button class="lang-btn ${this.i18n.currentLang === code ? 'active' : ''}" 
                                        data-lang="${code}">
                                    <span class="lang-flag">${flag}</span>
                                    <span class="lang-name">${name}</span>
                                </button>
                            `).join('')}
                        </div>
                    </div>
                    <div class="setting-item setting-divider">
                        <label class="setting-label" data-i18n="settings.data">Данные:</label>
                        <button class="reset-btn" id="reset-progress-btn">
                            <span class="reset-icon">🗑️</span>
                            <span data-i18n="settings.reset_progress">Сбросить прогресс</span>
                        </button>
                    </div>
                </div>
                <button class="modal-btn" id="settings-close-btn" data-i18n="modal.continue">Закрыть</button>
            </div>
        `;

        document.body.appendChild(overlay);

        // Обработчик смены языка
        overlay.querySelectorAll('.lang-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const lang = e.currentTarget.dataset.lang;
                const clickedBtn = e.currentTarget; // Сохраняем ссылку до await
                
                await this.i18n.setLanguage(lang);
                
                // Обновляем активный класс
                overlay.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('active'));
                clickedBtn.classList.add('active');
                
                // Перерисовать карточки с новым языком
                window.darkdate?.renderCards();
            });
        });

        // Обработчик сброса прогресса
        document.getElementById('reset-progress-btn')?.addEventListener('click', () => {
            if (confirm(this.i18n.t('settings.reset_confirm') || 'Вы уверены? Весь прогресс будет потерян.')) {
                localStorage.removeItem('darkdate_state');
                this.showToast(this.i18n.t('settings.reset_done') || 'Прогресс сброшен!', 2000);
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        });

        // Закрытие модального окна
        document.getElementById('settings-close-btn').addEventListener('click', () => {
            overlay.remove();
        });
        
        // Закрытие по клику вне модалки
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.remove();
            }
        });
    }

    // === УВЕДОМЛЕНИЯ (Панель) ===

    showNotificationsPanel() {
        // Показываем панель с накопленными уведомлениями
        if (this.notificationsQueue.length === 0) {
            this.showToast(this.i18n.t('notifications.empty') || 'Уведомления пусты...', 2000);
            return;
        }
        
        // Создаём панель уведомлений
        const overlay = document.createElement('div');
        overlay.id = 'notifications-overlay';
        overlay.className = 'modal-overlay';
        
        // Переводим заголовки и тексты уведомлений на текущий язык
        const translatedNotifications = this.notificationsQueue.map(n => ({
            ...n,
            translatedTitle: n.titleKey ? this.i18n.t(n.titleKey) : n.title,
            translatedText: n.textKey ? this.i18n.t(n.textKey) : n.text,
            translatedTime: this.formatNotificationTime(n.timestamp)
        }));
        
        overlay.innerHTML = `
            <div class="modal modal-notifications" id="notifications-modal">
                <h2 class="modal-title">🔔 ${this.i18n.t('notifications.panel_title') || 'Уведомления'} (${this.notificationsQueue.length})</h2>
                <div class="notifications-content">
                    ${translatedNotifications.map(n => `
                        <div class="notification-item" data-id="${n.id}">
                            <span class="notification-icon">${n.icon}</span>
                            <div class="notification-text">
                                <strong>${n.translatedTitle}</strong>
                                <p>${n.translatedText || ''}</p>
                                <span class="notification-time">${n.translatedTime}</span>
                            </div>
                            <button class="notification-delete-btn" data-id="${n.id}" title="${this.i18n.t('notifications.delete') || 'Удалить'}">✕</button>
                        </div>
                    `).join('')}
                </div>
                <div class="notifications-actions">
                    <button class="modal-btn modal-btn-secondary" id="notifications-clear-all" data-i18n="notifications.clear_all">${this.i18n.t('notifications.clear_all')}</button>
                    <button class="modal-btn" id="notifications-close-btn" data-i18n="modal.continue">${this.i18n.t('modal.continue')}</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        // Обработчик удаления отдельного уведомления
        overlay.querySelectorAll('.notification-delete-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const notifId = parseInt(e.currentTarget.dataset.id);
                this.removeNotification(notifId);
                // Обновляем панель после удаления
                this.showNotificationsPanel();
            });
        });

        // Обработчик очистки всех уведомлений
        document.getElementById('notifications-clear-all')?.addEventListener('click', () => {
            this.clearNotifications();
            overlay.remove();
        });

        // Закрытие модального окна - НЕ очищаем уведомления, просто закрываем панель
        document.getElementById('notifications-close-btn').addEventListener('click', () => {
            overlay.remove();
        });
        
        // Закрытие по клику вне модалки - НЕ очищаем уведомления
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.remove();
            }
        });
    }

    // Форматирование времени для уведомления
    formatNotificationTime(date) {
        const now = new Date();
        const diff = now - date;
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        if (days > 0) {
            return date.toLocaleDateString(this.i18n.currentLang, { 
                day: 'numeric', 
                month: 'short',
                hour: '2-digit',
                minute: '2-digit'
            });
        } else if (hours > 0) {
            const h = hours;
            const m = minutes % 60;
            // Используем ключи локализации для времени
            let timeStr;
            if (h === 1) {
                timeStr = this.i18n.t('notifications.time_hour_ago');
            } else {
                timeStr = this.i18n.t('notifications.time_hours_ago').replace('{{count}}', h);
            }
            if (m > 0) {
                const minStr = m === 1 
                    ? this.i18n.t('notifications.time_minute_ago')
                    : this.i18n.t('notifications.time_minutes_ago').replace('{{count}}', m);
                return `${timeStr} ${minStr}`;
            }
            return timeStr;
        } else if (minutes > 0) {
            const m = minutes;
            if (m === 1) {
                return this.i18n.t('notifications.time_minute_ago');
            } else {
                return this.i18n.t('notifications.time_minutes_ago').replace('{{count}}', m);
            }
        } else {
            return this.i18n.t('notifications.just_now') || 'Только что';
        }
    }

    // Удаление одного уведомления по ID
    removeNotification(id) {
        this.notificationsQueue = this.notificationsQueue.filter(n => n.id !== id);
        
        // Обновляем бейдж
        const badge = document.getElementById('notif-badge');
        if (badge) {
            const count = this.notificationsQueue.length;
            if (count === 0) {
                badge.textContent = '0';
                badge.classList.add('hidden');
            } else {
                badge.textContent = count;
            }
        }
    }

    // === ТАЙМЕР ===

    showTimerScreen() {
        const emptyState = document.getElementById('empty-state');
        const timerDisplay = document.getElementById('timer-display');
        const timerValue = document.getElementById('timer-value');

        emptyState?.classList.remove('hidden');
        timerDisplay?.classList.remove('hidden');

        this.timerInterval = setInterval(() => {
            const remaining = this.state.getRecoveryTimeRemaining();

            if (remaining <= 0) {
                clearInterval(this.timerInterval);
                this.state.checkRecovery();
                this.updateLives();
                timerDisplay?.classList.add('hidden');
                window.darkdate?.startRound();
                return;
            }

            if (timerValue) {
                timerValue.textContent = this.state.formatTime(remaining);
            }
        }, 1000);
    }
}