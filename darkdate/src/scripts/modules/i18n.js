/**
 * DarkDate — Internationalization Module
 * Поддержка: RU, EN, PT-BR, ZH-CN, ES, JA, DE, ID
 */

export class I18n {
    constructor() {
        this.currentLang = 'ru';
        this.translations = {};
        this.supportedLangs = ['ru', 'en', 'pt-BR', 'zh-CN', 'es', 'ja', 'de', 'id'];
    }

    async load(lang) {
        if (!this.supportedLangs.includes(lang)) {
            console.warn(`[i18n] Unsupported language: ${lang}, falling back to 'ru'`);
            lang = 'ru';
        }

        try {
            const response = await fetch(`src/data/i18n/${lang}.json`);
            this.translations = await response.json();
            this.currentLang = lang;
            document.documentElement.lang = lang;
            this.applyToDOM();
        } catch (e) {
            console.error(`[i18n] Failed to load ${lang}:`, e);
        }
    }

    /** Получить перевод по ключу (поддерживает вложенность: 'modal.title') */
    t(key) {
        return key.split('.').reduce((obj, part) => obj?.[part], this.translations) || key;
    }

    /** Применить переводы ко всем элементам с data-i18n */
    applyToDOM() {
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            const translation = this.t(key);
            if (translation !== key) {
                el.textContent = translation;
            }
        });
    }

    /** Смена языка */
    async setLanguage(lang) {
        await this.load(lang);
        localStorage.setItem('darkdate_lang', lang);
    }

    /** Получить сохранённый язык */
    getSavedLanguage() {
        return localStorage.getItem('darkdate_lang') || 'ru';
    }
}