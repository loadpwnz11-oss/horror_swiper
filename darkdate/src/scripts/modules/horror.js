/**
 * DarkDate — Horror Engine
 * Глитчи, тряска, темнота, фонарик, звуковые триггеры
 */

export class HorrorEngine {
    constructor() {
        this.app = document.getElementById('app');
        this.debuffOverlay = document.getElementById('debuff-overlay');
        this.activeDebuffs = new Set();
        this.audioContext = null;
    }

    /** Инициализация AudioContext (по пользовательскому действию) */
    initAudio() {
        if (this.audioContext) return;
        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }

    // === ГЛИТЧ ЭКРАНА ===

    triggerGlitch(duration = 500) {
        this.app.classList.add('glitch-active');
        setTimeout(() => this.app.classList.remove('glitch-active'), duration);
    }

    // === ТРЯСКА ЭКРАНА ===

    triggerShake(heavy = false) {
        const className = heavy ? 'screen-shake-heavy' : 'screen-shake';
        this.app.classList.add(className);
        setTimeout(() => this.app.classList.remove(className), heavy ? 800 : 500);

        // Вибрация
        if (navigator.vibrate) {
            navigator.vibrate(heavy ? [100, 50, 100, 50, 200] : [50, 30, 50]);
        }
    }

    // === КРАСНАЯ ВСПЫШКА ===

    triggerRedFlash() {
        this.app.classList.add('red-flash');
        setTimeout(() => this.app.classList.remove('red-flash'), 500);
    }

    // === ДЕБАФФ: ТЕМНОТА + ФОНАРИК ===

    activateFlashlight(duration = 5000) {
        const overlay = this.debuffOverlay;
        overlay.classList.remove('hidden');
        overlay.classList.add('active');
        this.activeDebuffs.add('darkness');

        // Отслеживаем палец/мышь для фонарика
        const moveHandler = (e) => {
            const x = e.touches ? e.touches[0].clientX : e.clientX;
            const y = e.touches ? e.touches[0].clientY : e.clientY;
            overlay.style.setProperty('--flash-x', `${x}px`);
            overlay.style.setProperty('--flash-y', `${y}px`);
        };

        document.addEventListener('mousemove', moveHandler);
        document.addEventListener('touchmove', moveHandler, { passive: true });

        setTimeout(() => {
            overlay.classList.add('hidden');
            overlay.classList.remove('active');
            this.activeDebuffs.delete('darkness');
            document.removeEventListener('mousemove', moveHandler);
            document.removeEventListener('touchmove', moveHandler);
        }, duration);
    }

    // === ДЕБАФФ: РАЗМЫТИЕ ===

    activateBlur(duration = 4000) {
        this.app.classList.add('vision-blur');
        this.activeDebuffs.add('blur');

        setTimeout(() => {
            this.app.classList.remove('vision-blur');
            this.activeDebuffs.delete('blur');
        }, duration);
    }

    // === КОМБО: ПРИНЯЛ СУЩНОСТЬ ===

    async triggerEntityAccepted() {
        this.initAudio();

        // Последовательность хоррор-эффектов
        this.triggerRedFlash();
        await this.delay(200);
        this.triggerShake(true);
        await this.delay(400);
        this.triggerGlitch(800);
        await this.delay(300);

        // Звук: низкий гул
        this.playDrone(0.8);

        // Статический шум
        this.app.classList.add('static-noise', 'scanlines');
        setTimeout(() => {
            this.app.classList.remove('static-noise', 'scanlines');
        }, 2000);
    }

    // === GAME OVER ===

    triggerGameOver() {
        this.triggerGlitch(2000);
        setTimeout(() => this.triggerShake(true), 500);
        this.playDrone(2);
    }

    // === ГЕНЕРАЦИЯ ЗВУКОВ (Web Audio API) ===

    playDrone(duration = 1) {
        if (!this.audioContext) return;

        try {
            const osc = this.audioContext.createOscillator();
            const gain = this.audioContext.createGain();

            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(60, this.audioContext.currentTime);
            osc.frequency.exponentialRampToValueAtTime(30, this.audioContext.currentTime + duration);

            gain.gain.setValueAtTime(0.15, this.audioContext.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, this.audioContext.currentTime + duration);

            osc.connect(gain);
            gain.connect(this.audioContext.destination);

            osc.start();
            osc.stop(this.audioContext.currentTime + duration);
        } catch (e) {
            console.warn('[Horror] Audio error:', e);
        }
    }

    playWhisper() {
        if (!this.audioContext) return;

        try {
            // Белый шум с фильтром = имитация шёпота
            const bufferSize = this.audioContext.sampleRate * 1;
            const buffer = this.audioContext.createBuffer(1, bufferSize, this.audioContext.sampleRate);
            const data = buffer.getChannelData(0);

            for (let i = 0; i < bufferSize; i++) {
                data[i] = (Math.random() * 2 - 1) * 0.05;
            }

            const source = this.audioContext.createBufferSource();
            source.buffer = buffer;

            const filter = this.audioContext.createBiquadFilter();
            filter.type = 'bandpass';
            filter.frequency.value = 800;
            filter.Q.value = 2;

            const gain = this.audioContext.createGain();
            gain.gain.setValueAtTime(0.1, this.audioContext.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, this.audioContext.currentTime + 1);

            source.connect(filter);
            filter.connect(gain);
            gain.connect(this.audioContext.destination);

            source.start();
        } catch (e) {
            console.warn('[Horror] Whisper error:', e);
        }
    }

    // === УТИЛИТЫ ===

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}