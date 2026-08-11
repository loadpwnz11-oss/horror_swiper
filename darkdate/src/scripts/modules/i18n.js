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

    /** Получить перевод для профиля по ID и полю */
    translateProfileField(profileId, field, lang = null) {
        const targetLang = lang || this.currentLang;
        const translationKey = `profiles.${profileId}.${field}`;
        return this.t(translationKey);
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
        
        // Применяем перевод к карточкам профилей (динамически генерируемые элементы)
        this.applyToProfiles();
    }
    
    /** Применить перевод к полям профилей */
    applyToProfiles() {
        // Переводим все карточки профилей на странице
        document.querySelectorAll('.profile-card').forEach(card => {
            const profileId = card.dataset.profileId;
            if (!profileId) return;
            
            // Перевод имени
            const nameEl = card.querySelector('.card-name');
            if (nameEl) {
                const translatedName = this.translateProfileField(profileId, 'name');
                if (translatedName && translatedName !== `profiles.${profileId}.name`) {
                    // Сохраняем возраст если есть - берём из data атрибута
                    const age = nameEl.dataset.age || '';
                    nameEl.textContent = translatedName + age;
                }
            }
            
            // Перевод био
            const bioEl = card.querySelector('.card-bio');
            if (bioEl) {
                const translatedBio = this.translateProfileField(profileId, 'bio');
                if (translatedBio && translatedBio !== `profiles.${profileId}.bio`) {
                    bioEl.textContent = translatedBio;
                }
            }
            
            // Перевод тегов
            const tagContainer = card.querySelector('.card-tags');
            if (tagContainer) {
                const tags = [];
                let index = 0;
                let translatedTag;
                while ((translatedTag = this.translateProfileField(profileId, `tags[${index}]`)) 
                       && translatedTag !== `profiles.${profileId}.tags[${index}]`) {
                    tags.push(`<span class="card-tag">${translatedTag}</span>`);
                    index++;
                }
                if (tags.length > 0) {
                    tagContainer.innerHTML = tags.join('');
                }
            }
        });
        
        // Переводим штампы LIKE/NOPE
        document.querySelectorAll('.stamp-like').forEach(el => {
            el.textContent = this.t('swipe.like');
        });
        document.querySelectorAll('.stamp-nope').forEach(el => {
            el.textContent = this.t('swipe.nope');
        });
    }
    
    /** Получить все поддерживаемые языки с метаданными */
    getSupportedLanguages() {
        return {
            'ru': { name: 'Русский', flag: '🇷🇺' },
            'en': { name: 'English', flag: '🇬🇧' },
            'pt-BR': { name: 'Português (BR)', flag: '🇧🇷' },
            'zh-CN': { name: '中文', flag: '🇨🇳' },
            'es': { name: 'Español', flag: '🇪🇸' },
            'ja': { name: '日本語', flag: '🇯🇵' },
            'de': { name: 'Deutsch', flag: '🇩🇪' },
            'id': { name: 'Bahasa Indonesia', flag: '🇮🇩' }
        };
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

    /** Получить текущий язык */
    getCurrentLang() {
        return this.currentLang;
    }
}