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

    hideModal() {
        document.getElementById('modal-overlay')?.classList.add('hidden');
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