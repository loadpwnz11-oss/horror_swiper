/**
 * DarkDate — Main Application Entry Point
 * Модульная архитектура, ES6 modules
 */

import { GameState } from './core/state.js';
import { SwipeEngine } from './modules/swipe.js';
import { HorrorEngine } from './modules/horror.js';
import { UIController } from './modules/ui.js';
import { I18n } from './modules/i18n.js';

class DarkDateApp {
    constructor() {
        this.state = new GameState();
        this.i18n = new I18n();
        this.horror = new HorrorEngine();
        this.ui = new UIController(this.state, this.i18n);
        this.swipe = null; // Инициализируется после загрузки профилей

        this.profiles = [];
        this.currentIndex = 0;
        this.timerInterval = null; // Для таймера восстановления

        this.init();
    }

    async init() {
        try {
            // 1. Загрузка языка
            const savedLang = this.i18n.getSavedLanguage();
            await this.i18n.load(savedLang);

            // 2. Загрузка профилей
            await this.loadProfiles();

            // 3. Восстановление состояния
            this.state.restore();

            // 4. Инициализация UI
            this.ui.init();

            // 5. Инициализация свайпов
            this.swipe = new SwipeEngine(this);

            // 6. Запуск splash screen
            this.showSplash();

        } catch (error) {
            console.error('[DarkDate] Init error:', error);
            document.body.innerHTML = `
                <div style="color:red;padding:20px;font-family:monospace;">
                    <h1>DarkDate Error</h1>
                    <p>${error.message}</p>
                </div>
            `;
        }
    }

    async loadProfiles() {
        const response = await fetch('src/data/profiles.json');
        const data = await response.json();
        this.profiles = this.shuffleArray(data.profiles);
    }

    shuffleArray(array) {
        const shuffled = [...array];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    }

    showSplash() {
        const splash = document.getElementById('splash-screen');
        const app = document.getElementById('app');

        setTimeout(() => {
            splash.classList.add('fade-out');
            setTimeout(() => {
                splash.style.display = 'none';
                app.classList.remove('hidden');
                this.startRound();
            }, 800);
        }, 2500);
    }

    startRound() {
        this.currentIndex = 0;
        this.state.resetRoundLives();
        this.ui.updateLives();
        this.renderCards();
        
        // Скрываем экран game over если он был показан
        document.getElementById('gameover-screen')?.classList.add('hidden');
    }

    renderCards() {
        const stack = document.getElementById('cards-stack');
        const emptyState = document.getElementById('empty-state');

        stack.innerHTML = '';

        if (this.currentIndex >= this.profiles.length || !this.state.hasSessionLives()) {
            emptyState.classList.remove('hidden');
            if (!this.state.hasSessionLives()) {
                this.showGameOver();
            }
            return;
        }

        emptyState.classList.add('hidden');

        // Рендерим до 3 карточек в стеке
        const cardsToShow = Math.min(3, this.profiles.length - this.currentIndex);

        for (let i = cardsToShow - 1; i >= 0; i--) {
            const profileIndex = this.currentIndex + i;
            const profile = this.profiles[profileIndex];
            const card = this.createCardElement(profile, i);
            stack.appendChild(card);
        }

        // Инициализируем свайп для верхней карточки
        const topCard = stack.lastElementChild;
        if (topCard) {
            this.swipe.attachToCard(topCard);
        }
    }

    createCardElement(profile, stackPosition) {
        const card = document.createElement('div');
        card.className = 'profile-card';
        card.dataset.profileId = profile.id;
        card.dataset.profileType = profile.type;

        if (stackPosition === 1) card.classList.add('card-behind-1');
        if (stackPosition === 2) card.classList.add('card-behind-2');

        // Horror: пульсация для сложных сущностей
        if (profile.type === 'entity' && profile.horrorHints.includes('menace_pulse')) {
            card.classList.add('card-menace');
        }

        const ageDisplay = profile.age !== null ? `, ${profile.age}` : '';
        const bioClass = profile.horrorHints.includes('corrupted_bio') ? 'glitch-text' : '';

        // Получаем перевод профиля из i18n
        const translatedProfile = this.i18n.getProfileTranslation(profile.id);
        const displayName = translatedProfile?.name || profile.name;
        const displayBio = translatedProfile?.bio || profile.bio;
        const displayTags = translatedProfile?.tags || profile.tags;

        card.innerHTML = `
            <div class="card-image-wrapper">
                <img class="card-image" src="${profile.image}" alt="${displayName}" loading="lazy"
                     onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22600%22><rect fill=%22%231a1a1a%22 width=%22400%22 height=%22600%22/><text x=%22200%22 y=%22300%22 fill=%22%23333%22 text-anchor=%22middle%22 font-size=%2248%22>?</text></svg>'">
                <div class="card-gradient"></div>
            </div>
            <div class="card-stamp stamp-like" data-i18n="swipe.like">${this.i18n.t('swipe.like')}</div>
            <div class="card-stamp stamp-nope" data-i18n="swipe.nope">${this.i18n.t('swipe.nope')}</div>
            <div class="card-info">
                <div class="card-name ${profile.horrorHints.includes('corrupted_name') ? 'glitch-text' : ''}"
                     ${profile.horrorHints.includes('corrupted_name') ? `data-text="${profile.name}"` : ''}>
                    ${displayName}${ageDisplay}
                </div>
                <div class="card-bio ${bioClass}"
                     ${bioClass ? `data-text="${profile.bio}"` : ''}>
                    ${displayBio}
                </div>
                <div class="card-tags">
                    ${displayTags.map(tag => `<span class="card-tag">${tag}</span>`).join('')}
                </div>
            </div>
        `;

        return card;
    }

    async handleSwipe(direction) {
        const profile = this.profiles[this.currentIndex];
        if (!profile) return;

        const isLike = direction === 'right';

        // Обработка результата
        const result = this.processSwipeResult(profile, isLike);

        // Хоррор-эффекты при свайпе сущности
        if (profile.type === 'entity' && isLike) {
            await this.horror.triggerEntityAccepted();
        }

        // Показываем результат только для важных событий (сущности, жертвы, охотник)
        // Для обычных людей (human) показываем только тост-уведомление
        if (result.type !== 'skip' && result.type !== 'match') {
            this.ui.showResultModal(result);
        } else if (result.type === 'match') {
            // Для совпадений показываем маленький тост вместо модалки
            this.ui.showToast(this.i18n.t('modal.match_title') + ' 💕', 1500);
        }

        this.currentIndex++;
    }

    processSwipeResult(profile, isLike) {
        switch (profile.type) {
            case 'human':
                if (isLike) {
                    return { type: 'match', icon: '💕', titleKey: 'modal.match_title', textKey: 'modal.match_text' };
                }
                return { type: 'skip', icon: '👋' };

            case 'entity':
                if (isLike) {
                    this.state.loseLife();
                    this.ui.updateLives();
                    return { type: 'entity_hit', icon: '👁️', titleKey: 'modal.entity_title', textKey: 'modal.entity_text' };
                }
                return { type: 'entity_dodged', icon: '✅', titleKey: 'modal.entity_dodged_title', textKey: 'modal.entity_dodged_text' };

            case 'victim':
                if (isLike) {
                    return { type: 'victim_saved', icon: '🕊️', titleKey: 'modal.victim_saved' };
                }
                return { type: 'victim_lost', icon: '💀', titleKey: 'modal.victim_lost' };

            case 'hunter':
                if (isLike) {
                    return { type: 'hunter_hint', icon: '🔫', titleKey: 'modal.hunter_title', text: profile.hintOnLike };
                }
                return { type: 'skip', icon: '👋' };

            default:
                return { type: 'skip', icon: '❓' };
        }
    }

    showGameOver() {
        const screen = document.getElementById('gameover-screen');
        screen.classList.remove('hidden');
        this.horror.triggerGameOver();
    }

    restart() {
        document.getElementById('gameover-screen').classList.add('hidden');
        
        // Проверяем, активен ли таймер восстановления
        if (this.state.recoveryEndTime && Date.now() < this.state.recoveryEndTime) {
            // Таймер ещё активен — показываем экран с таймером
            this.showNoSessionsScreen();
            return;
        }
        
        if (this.state.hasSessionLives()) {
            this.state.resetRoundLives(); // Сбрасываем жизни раунда при новом раунде
            this.profiles = this.shuffleArray(this.profiles);
            this.startRound();
        } else {
            // Если сессии кончились и нет активного таймера, показываем экран таймера
            this.showNoSessionsScreen();
        }
    }
    
    showNoSessionsScreen() {
        const screen = document.getElementById('no-sessions-screen');
        const timerValue = document.getElementById('nosessions-timer-value');
        screen.classList.remove('hidden');
        
        // Сохраняем интервал в state для возможности очистки при перезагрузке
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
        }
        
        this.timerInterval = setInterval(() => {
            const remaining = this.state.getRecoveryTimeRemaining();
            
            if (remaining <= 0) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
                this.state.checkRecovery();
                this.ui.updateLives();
                screen.classList.add('hidden');
                this.startRound();
                return;
            }
            
            if (timerValue) {
                timerValue.textContent = this.state.formatTime(remaining);
            }
        }, 1000);
    }
}

// Запуск приложения
document.addEventListener('DOMContentLoaded', () => {
    window.darkdate = new DarkDateApp();
});