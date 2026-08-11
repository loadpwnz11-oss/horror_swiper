/**
 * DarkDate — UI Controller
 * Управление DOM, модалки, жизни, таймеры
 */

export class UIController {
    constructor(state, i18n) {
        this.state = state;
        this.i18n = i18n;
        this.timerInterval = null;
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

        // Кнопка уведомлений (заглушка)
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

        const overlay = document.getElementById('modal-overlay');
        const icon = document.getElementById('modal-icon');
        const title = document.getElementById('modal-title');
        const text = document.getElementById('modal-text');

        icon.textContent = result.icon || '❓';

        if (result.titleKey) {
            title.textContent = this.i18n.t(result.titleKey) || result.title || '';
        } else {
            title.textContent = result.title || '';
        }

        if (result.textKey) {
            text.textContent = this.i18n.t(result.textKey) || result.text || '';
        } else {
            text.textContent = result.text || '';
        }

        overlay.classList.remove('hidden');
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
        const overlay = document.createElement('div');
        overlay.id = 'settings-overlay';
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `
            <div class="modal" id="settings-modal">
                <h2 class="modal-title" data-i18n="settings.title">Настройки</h2>
                <div class="settings-content">
                    <div class="setting-item">
                        <label data-i18n="settings.language">Язык:</label>
                        <select id="language-select">
                            <option value="ru" ${this.i18n.currentLang === 'ru' ? 'selected' : ''}>Русский</option>
                            <option value="en" ${this.i18n.currentLang === 'en' ? 'selected' : ''}>English</option>
                            <option value="pt-BR" ${this.i18n.currentLang === 'pt-BR' ? 'selected' : ''}>Português (BR)</option>
                            <option value="zh-CN" ${this.i18n.currentLang === 'zh-CN' ? 'selected' : ''}>中文</option>
                            <option value="es" ${this.i18n.currentLang === 'es' ? 'selected' : ''}>Español</option>
                            <option value="ja" ${this.i18n.currentLang === 'ja' ? 'selected' : ''}>日本語</option>
                            <option value="de" ${this.i18n.currentLang === 'de' ? 'selected' : ''}>Deutsch</option>
                            <option value="id" ${this.i18n.currentLang === 'id' ? 'selected' : ''}>Bahasa Indonesia</option>
                        </select>
                    </div>
                </div>
                <button class="modal-btn" id="settings-close-btn" data-i18n="modal.continue">Закрыть</button>
            </div>
        `;

        document.body.appendChild(overlay);

        // Обработчик смены языка
        document.getElementById('language-select').addEventListener('change', async (e) => {
            await this.i18n.setLanguage(e.target.value);
            window.darkdate?.renderCards(); // Перерисовать карточки с новым языком
        });

        // Закрытие модального окна
        document.getElementById('settings-close-btn').addEventListener('click', () => {
            overlay.remove();
        });
    }

    // === УВЕДОМЛЕНИЯ (Панель) ===

    showNotificationsPanel() {
        // Временная заглушка - можно расширить функционал уведомлений
        console.log('[UI] Notifications panel clicked');
        alert(this.i18n.t('notifications.entity_warning') || 'Уведомления пока пусты...');
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