/**
 * DarkDate — Swipe Engine
 * Drag & Drop + кнопки для свайпов
 */

export class SwipeEngine {
    constructor(app) {
        this.app = app;
        this.currentCard = null;
        this.startX = 0;
        this.startY = 0;
        this.currentX = 0;
        this.currentY = 0;
        this.isDragging = false;
        this.threshold = 100; // пикселей для срабатывания свайпа

        this.bindButtons();
    }

    bindButtons() {
        document.getElementById('btn-like').addEventListener('click', () => {
            this.programmaticSwipe('right');
        });

        document.getElementById('btn-dislike').addEventListener('click', () => {
            this.programmaticSwipe('left');
        });
    }

    attachToCard(card) {
        this.currentCard = card;

        // Touch events
        card.addEventListener('touchstart', this.onTouchStart.bind(this), { passive: false });
        card.addEventListener('touchmove', this.onTouchMove.bind(this), { passive: false });
        card.addEventListener('touchend', this.onTouchEnd.bind(this));

        // Mouse events (для десктопа)
        card.addEventListener('mousedown', this.onMouseDown.bind(this));
        card.addEventListener('mousemove', this.onMouseMove.bind(this));
        card.addEventListener('mouseup', this.onMouseUp.bind(this));
        card.addEventListener('mouseleave', this.onMouseUp.bind(this));

        // Активация дебаффов при показе карточки
        this.checkDebuffs(card);
    }

    // === TOUCH HANDLERS ===

    onTouchStart(e) {
        if (e.touches.length !== 1) return;
        this.startDrag(e.touches[0].clientX, e.touches[0].clientY);
    }

    onTouchMove(e) {
        if (!this.isDragging) return;
        e.preventDefault();
        this.moveDrag(e.touches[0].clientX, e.touches[0].clientY);
    }

    onTouchEnd() {
        this.endDrag();
    }

    // === MOUSE HANDLERS ===

    onMouseDown(e) {
        this.startDrag(e.clientX, e.clientY);
    }

    onMouseMove(e) {
        if (!this.isDragging) return;
        this.moveDrag(e.clientX, e.clientY);
    }

    onMouseUp() {
        this.endDrag();
    }

    // === DRAG LOGIC ===

    startDrag(x, y) {
        this.isDragging = true;
        this.startX = x;
        this.startY = y;
        this.currentCard.classList.add('dragging');
    }

    moveDrag(x, y) {
        this.currentX = x - this.startX;
        this.currentY = y - this.startY;

        const rotation = this.currentX * 0.1; // градусы поворота
        const opacity = Math.min(Math.abs(this.currentX) / this.threshold, 1);

        this.currentCard.style.transform = `translate(${this.currentX}px, ${this.currentY}px) rotate(${rotation}deg)`;

        // Показываем штампы LIKE / NOPE
        const likeStamp = this.currentCard.querySelector('.stamp-like');
        const nopeStamp = this.currentCard.querySelector('.stamp-nope');

        if (this.currentX > 30) {
            likeStamp.style.opacity = opacity;
            nopeStamp.style.opacity = 0;
        } else if (this.currentX < -30) {
            nopeStamp.style.opacity = opacity;
            likeStamp.style.opacity = 0;
        } else {
            likeStamp.style.opacity = 0;
            nopeStamp.style.opacity = 0;
        }
    }

    endDrag() {
        if (!this.isDragging) return;
        this.isDragging = false;
        this.currentCard.classList.remove('dragging');

        if (Math.abs(this.currentX) > this.threshold) {
            const direction = this.currentX > 0 ? 'right' : 'left';
            this.animateSwipe(direction);
        } else {
            // Возврат на место
            this.currentCard.style.transition = 'transform 0.3s ease';
            this.currentCard.style.transform = '';
            this.currentCard.querySelector('.stamp-like').style.opacity = 0;
            this.currentCard.querySelector('.stamp-nope').style.opacity = 0;

            setTimeout(() => {
                if (this.currentCard) {
                    this.currentCard.style.transition = '';
                }
            }, 300);
        }

        this.currentX = 0;
        this.currentY = 0;
    }

    // === PROGRAMMATIC SWIPE (кнопки) ===

    programmaticSwipe(direction) {
        if (!this.currentCard) return;
        this.animateSwipe(direction);
    }

    animateSwipe(direction) {
        const card = this.currentCard;
        if (!card) return;

        const xMove = direction === 'right' ? window.innerWidth * 1.5 : -window.innerWidth * 1.5;
        const rotation = direction === 'right' ? 30 : -30;

        card.style.transition = 'transform 0.5s ease, opacity 0.5s ease';
        card.style.transform = `translate(${xMove}px, 0) rotate(${rotation}deg)`;
        card.style.opacity = '0';

        // Показываем штамп
        const stamp = direction === 'right'
            ? card.querySelector('.stamp-like')
            : card.querySelector('.stamp-nope');
        if (stamp) stamp.style.opacity = 1;

        // Вибрация
        if (navigator.vibrate) {
            navigator.vibrate(direction === 'right' ? [50] : [30, 20, 30]);
        }

        setTimeout(() => {
            this.app.handleSwipe(direction);
            this.app.renderCards();
        }, 500);
    }

    // === DEBUFFS ===

    checkDebuffs(card) {
        const type = card.dataset.profileType;
        const profileId = card.dataset.profileId;
        const profile = this.app.profiles.find(p => p.id === profileId);

        if (!profile || !profile.debuffOnView) return;

        const horror = this.app.horror;

        switch (profile.debuffOnView) {
            case 'darkness':
                horror.activateFlashlight(profile.debuffDuration || 5000);
                break;
            case 'blur':
                horror.activateBlur(profile.debuffDuration || 4000);
                break;
        }
    }
}