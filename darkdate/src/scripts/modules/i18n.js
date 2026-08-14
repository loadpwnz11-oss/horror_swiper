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
        const profileData = this.translations.profiles?.[profileId];
        if (!profileData) return null;
        
        // Обработка тегов (массив)
        if (field.startsWith('tags[')) {
            const indexMatch = field.match(/tags\[(\d+)\]/);
            if (indexMatch) {
                const index = parseInt(indexMatch[1], 10);
                const tagsArray = profileData.tags;
                if (Array.isArray(tagsArray) && tagsArray[index] !== undefined) {
                    return tagsArray[index];
                }
            }
            return null;
        }
        
        // Обработка обычных полей (name, bio)
        return profileData[field] || null;
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
            
            // Перевод имени - сначала пробуем из i18n, потом fallback на оригинал
            const nameEl = card.querySelector('.card-name');
            if (nameEl) {
                let translatedName = this.translateProfileField(profileId, 'name');
                const age = nameEl.dataset.age || '';
                
                // Если перевода нет, берём оригинальное имя из data-original-name
                if (!translatedName || translatedName === `profiles.${profileId}.name`) {
                    translatedName = card.dataset.originalName || nameEl.textContent.replace(age, '');
                }
                nameEl.textContent = translatedName + age;
            }
            
            // Перевод био
            const bioEl = card.querySelector('.card-bio');
            if (bioEl) {
                let translatedBio = this.translateProfileField(profileId, 'bio');
                // Если перевода нет, берём оригинал из data-original-bio
                if (!translatedBio || translatedBio === `profiles.${profileId}.bio`) {
                    translatedBio = card.dataset.originalBio || bioEl.textContent;
                }
                bioEl.textContent = translatedBio;
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
                // Если переводов нет, используем оригинальные теги из data-original-tags
                if (tags.length === 0) {
                    const originalTags = JSON.parse(card.dataset.originalTags || '[]');
                    originalTags.forEach(tag => {
                        tags.push(`<span class="card-tag">${tag}</span>`);
                    });
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
    
    /** Получить перевод профиля по ID */
    getProfileTranslation(profileId) {
        const profileData = this.translations.profiles?.[profileId];
        return profileData || null;
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